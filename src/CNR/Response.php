<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

use CNIC\CNR\Column;
use CNIC\CNR\ResponseParser as RP;
use CNIC\CNR\ResponseTranslator as RT;
use CNIC\ColumnInterface;
use CNIC\CommandFormatter;
use CNIC\ResponseInterface;

/**
 * CNR Response
 *
 * @psalm-api
 * @package CNIC\CNR
 */
class Response implements ResponseInterface
{
    /**
     * The API Command used within this request
     * @var array<string, string>
     */
    protected array $command = [];

    /**
     * Command parameter keys that carry sensitive data for this brand (account
     * password, domain authorization code, ...). Their values are masked before
     * the command is stored so they can never be read back (e.g. by custom
     * loggers). Matching is case-insensitive (see sanitizeCommand()), so only
     * the names matter, not their casing. Brand-specific by design: CNR uses
     * upper-case keys, IBS overrides with its own; a future brand simply
     * declares the keys it uses.
     * @var string[]
     */
    protected array $sensitiveFields = ["PASSWORD", "AUTH"];

    /**
     * plain API response
     */
    protected string $raw;

    /**
     * hash representation of plain API response
     * @var array<string, mixed>
     */
    protected array $hash;

    /**
     * Regex for pagination related column keys.
     * The alternation is grouped so the ^…$ anchors apply to every keyword;
     * without the group only TOTAL/LAST are anchored and COUNT|LIMIT|FIRST
     * would match anywhere, wrongly stripping real columns such as COUNTRY,
     * FIRSTNAME, DISCOUNT or ACCOUNT from getColumnKeys()/getListHash().
     * @var non-empty-string
     */
    protected string $paginationkeys = "/^(TOTAL|COUNT|LIMIT|FIRST|LAST)$/";

    /**
     * Column names available in this response
     * @var string[]
     */
    protected array $columnkeys = [];

    /**
     * Container of Column Instances
     * @var ColumnInterface[]
     */
    protected array $columns = [];

    /**
     * Map of column name to its index in the column/columnkeys lists.
     * Maintained by addColumn() to provide O(1) column lookup. First
     * occurrence wins, mirroring the previous array_search() behaviour.
     * @var array<string, int>
     */
    protected array $columnindex = [];

    /**
     * Record Index we currently point to in record list
     */
    protected int $recordIndex = 0;

    /**
     * Record List (List of rows)
     * @var Record[]
     */
    protected array $records = [];

    /**
     * Context data for the response
     * @var array<string,mixed>
     */
    protected array $context = [];

    /**
     * API request url
     */
    protected string $requestUrl = "";

    /**
     * Constructor
     * @param string $raw API plain response
     * @param array<string, string> $cmd API command used within this request
     * @param array{CONNECTION_URL?: string} $ph placeholder array to get vars in response description dynamically replaced
     * @param array<string,mixed> $context context data for the response (for use in custom loggers etc., optional, has no impact on SDK behaviour)
     */
    public function __construct(string $raw, array $cmd = [], array $ph = [], array $context = [])
    {
        $cmd = $this->sanitizeCommand($cmd);
        $this->context = $context;
        $this->command = $cmd;
        $this->requestUrl = $ph["CONNECTION_URL"] ?? "";
        $this->raw = $this->translate($raw, $cmd, $ph);
        $this->populate();
    }

    /**
     * Translate the raw API response into its canonical form.
     * Brand-specific by the ResponseTranslator each subclass imports; IBS
     * overrides this to use its own translator. $cmd is already sanitized.
     * @param array<string, string> $cmd API command used within this request
     * @param array{CONNECTION_URL?: string} $ph placeholder array for dynamic replacement
     */
    protected function translate(string $raw, array $cmd, array $ph): string
    {
        return RT::translate($raw, $cmd, $ph);
    }

    /**
     * Parse the translated response into the hash and build the column/record
     * lists from it. Parsing and column extraction are kept together because
     * each brand's parser returns a different hash shape: CNR exposes its
     * columns under the PROPERTY sub-array and assembles records only when
     * properties are present, while IBS (which overrides this) uses a flat
     * key => value map. The sanitized command is available as $this->command.
     */
    protected function populate(): void
    {
        $this->hash = RP::parse($this->raw);
        $properties = $this->hash["PROPERTY"] ?? null;
        if (is_array($properties)) {
            $colKeys = array_map(strval(...), array_keys($properties));
            foreach ($colKeys as $k) {
                $this->addColumn($k, $properties[$k]);
            }
            $this->assembleRecords();
        }
    }

    /**
     * Mask the brand's sensitive command keys (see $sensitiveFields) so their
     * values can never be read back from the response (e.g. by custom loggers).
     * Matching is case-insensitive to stay robust against casing differences
     * between what a brand documents and what it actually sends.
     * @param array<string, string> $cmd API command used within this request
     * @return array<string, string>
     */
    protected function sanitizeCommand(array $cmd): array
    {
        $sensitive = array_map(strtolower(...), $this->sensitiveFields);
        foreach (array_keys($cmd) as $key) {
            if (in_array(strtolower($key), $sensitive, true)) {
                $cmd[$key] = "***";
            }
        }
        return $cmd;
    }

    /**
     * Assemble the record (row) list from the columns already added via
     * addColumn(). Shared by CNR and IBS: each subclass populates the columns
     * with its own Column type beforehand, while the row assembly is identical.
     */
    protected function assembleRecords(): void
    {
        $count = 0;
        foreach ($this->columns as $col) {
            $count = max($count, count($col->getData()));
        }
        for ($i = 0; $i < $count; $i++) {
            $d = [];
            foreach ($this->columnkeys as $k) {
                $col = $this->getColumn($k);
                if ($col instanceof ColumnInterface) {
                    /** @psalm-suppress MixedAssignment getDataByIndex returns mixed by design — IBS columns hold arbitrary JSON values */
                    $v = $col->getDataByIndex($i);
                    if ($v !== null) {
                        /** @psalm-suppress MixedAssignment */
                        $d[$k] = $v;
                    }
                }
            }
            $this->addRecord($d);
        }
    }

    /**
     * Get context data for the response
     * @return array<string,mixed>
     */
    #[\Override]
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get Request URL
     */
    #[\Override]
    public function getRequestURL(): string
    {
        return $this->requestUrl;
    }

    /**
     * Get API response code
     */
    #[\Override]
    public function getCode(): int
    {
        return intval($this->getHashString("CODE"), 10);
    }

    /**
     * Get API response description
     */
    #[\Override]
    public function getDescription(): string
    {
        return $this->getHashString("DESCRIPTION");
    }

    /**
     * Get Plain API response
     */
    #[\Override]
    public function getPlain(): string
    {
        return $this->raw;
    }

    /**
     * Get Queuetime of API response
     */
    #[\Override]
    public function getQueuetime(): float
    {
        if (array_key_exists("QUEUETIME", $this->hash)) {
            return floatval($this->getHashString("QUEUETIME"));
        }
        return 0.00;
    }

    /**
     * Get API response as Hash
     * @return array<string, mixed>
     */
    #[\Override]
    public function getHash(): array
    {
        return $this->hash;
    }

    /**
     * Get Runtime of API response
     */
    #[\Override]
    public function getRuntime(): float
    {
        if (array_key_exists("RUNTIME", $this->hash)) {
            return floatval($this->getHashString("RUNTIME"));
        }
        return 0.00;
    }

    /**
     * Check if current API response represents an error case
     * API response code is an 5xx code
     */
    #[\Override]
    public function isError(): bool
    {
        return substr($this->getHashString("CODE"), 0, 1) === "5";
    }

    /**
     * Check if current API response represents a success case
     * API response code is an 2xx code
     */
    #[\Override]
    public function isSuccess(): bool
    {
        return substr($this->getHashString("CODE"), 0, 1) === "2";
    }

    /**
     * Check if current API response represents a temporary error case
     * API response code is an 4xx code
     */
    #[\Override]
    public function isTmpError(): bool
    {
        return substr($this->getHashString("CODE"), 0, 1) === "4";
    }

    /**
     * Check if current operation is returned as pending
     */
    #[\Override]
    public function isPending(): bool
    {
        return isset($this->hash["PENDING"]) && $this->hash["PENDING"] === "1";
    }

    /**
     * Add a column to the column list
     * @param string $key column name
     * @param string[] $data array of column data
     * @psalm-suppress MoreSpecificImplementedParamType CNR columns are always string-valued
     */
    #[\Override]
    public function addColumn(string $key, array $data): static
    {
        return $this->registerColumn(new Column($key, $data));
    }

    /**
     * Register an already-constructed column into the list bookkeeping.
     *
     * The bookkeeping ($columns/$columnkeys/$columnindex) is identical for every
     * brand; only the concrete Column type differs. Rather than a param-typed
     * newColumn() factory — which cannot stay type-clean under PHPStan L9 / Psalm
     * L1, because CNR\Column takes string[] while IBS\Column takes mixed[] and a
     * shared factory would have to narrow one into the other — each brand's
     * addColumn() builds its own correctly-typed Column locally and hands the
     * finished instance here, so this shared helper never sees the brand types.
     */
    protected function registerColumn(ColumnInterface $col): static
    {
        $key = $col->getKey();
        $this->columns[] = $col;
        $this->columnkeys[] = $key;
        $this->columnindex[$key] ??= count($this->columns) - 1;
        return $this;
    }

    /**
     * Instantiate the record type for this brand.
     *
     * Factory hook for addRecord(): the base builds a CNR\Record. A brand that
     * needs record-level behaviour of its own (IBS) overrides only this method
     * so its Record subclass is actually used. Records share one shape across
     * brands (array<string,mixed>), so unlike columns — where CNR is string[]
     * and IBS mixed — a single type-clean factory hook fits here.
     * @param array<string,mixed> $h row hash data
     */
    protected function newRecord(array $h): Record
    {
        return new Record($h);
    }

    /**
     * Add a record to the record list
     * @param array<string,mixed> $h row hash data
     */
    #[\Override]
    public function addRecord(array $h): static
    {
        $this->records[] = $this->newRecord($h);
        return $this;
    }

    /**
     * Get column by column name
     * @param string $key column name
     */
    #[\Override]
    public function getColumn(string $key): ?ColumnInterface
    {
        $idx = $this->columnindex[$key] ?? null;
        return $idx === null ? null : $this->columns[$idx];
    }

    /**
     * Get Data by Column Name and Index
     * @param string $colkey column name
     * @param int $index column data index
     */
    #[\Override]
    public function getColumnIndex(string $colkey, int $index): mixed
    {
        $col = $this->getColumn($colkey);
        return $col instanceof ColumnInterface ? $col->getDataByIndex($index) : null;
    }

    /**
     * Get Column Names
     * @param bool $filterPaginationKeys strip pagination columns
     * @return string[]
     */
    #[\Override]
    public function getColumnKeys(bool $filterPaginationKeys = false): array
    {
        if ($filterPaginationKeys) {
            // Ensure that preg_grep always returns an array
            $paginationKeys = preg_grep($this->paginationkeys, $this->columnkeys, PREG_GREP_INVERT) ?: [];
            return array_values($paginationKeys);
        }
        return $this->columnkeys;
    }

    /**
     * Get List of Columns
     * @return ColumnInterface[]
     */
    #[\Override]
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get Command used in this request
     * @return array<string, string>
     */
    #[\Override]
    public function getCommand(): array
    {
        return CommandFormatter::getSortedCommand($this->command);
    }

    /**
     * Get Command used in this request in plain text format
     */
    #[\Override]
    public function getCommandPlain(): string
    {
        return CommandFormatter::formatCommand($this->getCommand());
    }

    /**
     * Get Page Number of current List Query
     */
    #[\Override]
    public function getCurrentPageNumber(): ?int
    {
        $first = $this->getFirstRecordIndex();
        $limit = $this->getRecordsLimitation();
        if ($first !== null && $limit) {
            return intdiv($first, $limit) + 1;
        }
        return null;
    }

    /**
     * Get Record of current record index
     */
    #[\Override]
    public function getCurrentRecord(): ?Record
    {
        return $this->hasCurrentRecord() ? $this->records[$this->recordIndex] : null;
    }

    /**
     * Coerce a raw pagination column value to a base-10 integer.
     *
     * getDataByIndex() is typed mixed on ColumnInterface (IBS columns may
     * carry nested arrays/objects); the pagination columns FIRST/LAST/TOTAL/
     * LIMIT always hold a scalar numeric string, so anything non-scalar (incl.
     * a missing value) yields null and lets the caller fall back.
     */
    private function columnInt(mixed $value): ?int
    {
        return is_scalar($value) ? intval($value, 10) : null;
    }

    /**
     * Get Index of first row in this response
     */
    #[\Override]
    public function getFirstRecordIndex(): ?int
    {
        $col = $this->getColumn("FIRST");
        if ($col instanceof ColumnInterface) {
            return $this->columnInt($col->getDataByIndex(0)) ?? 0;
        }
        if ($this->getRecordsCount() !== 0) {
            return 0;
        }
        return null;
    }

    /**
     * Get last record index of the current list query
     */
    #[\Override]
    public function getLastRecordIndex(): ?int
    {
        $col = $this->getColumn("LAST");
        if ($col instanceof ColumnInterface) {
            $l = $this->columnInt($col->getDataByIndex(0));
            if ($l !== null) {
                return $l;
            }
        }
        $c = $this->getRecordsCount();
        if ($c !== 0) {
            return $c - 1;
        }
        return null;
    }

    /**
     * Get Response as List Hash including useful meta data for tables
     * @return array{LIST: list<array<string, mixed>>, meta: array{columns: string[], pg: array<string, int|null>}}
     */
    #[\Override]
    public function getListHash(): array
    {
        // Resolve the pagination-stripped column set once (regex runs a single
        // time inside getColumnKeys(true)); reuse it to filter every row via
        // array_intersect_key instead of a per-cell preg_match. Record data keys
        // are always a subset of the column keys (see assembleRecords()), so this
        // yields output identical to unsetting each pagination-matching cell.
        $columns = $this->getColumnKeys(true);
        $keepKeys = array_flip($columns);
        $lh = [];
        foreach ($this->records as $rec) {
            $lh[] = array_intersect_key($rec->getData(), $keepKeys);
        }
        return [
            "LIST" => $lh,
            "meta" => [
                "columns" => $columns,
                "pg" => $this->getPagination()
            ]
        ];
    }

    /**
     * Get next record in record list
     */
    #[\Override]
    public function getNextRecord(): ?Record
    {
        if ($this->hasNextRecord()) {
            return $this->records[++$this->recordIndex];
        }
        return null;
    }

    /**
     * Get Page Number of next list query
     */
    #[\Override]
    public function getNextPageNumber(): ?int
    {
        $cp = $this->getCurrentPageNumber();
        if ($cp === null) {
            return null;
        }
        $page = $cp + 1;
        if ($page > $this->getNumberOfPages()) {
            return null;
        }
        return $page;
    }

    /**
     * Get the number of pages available for this list query
     */
    #[\Override]
    public function getNumberOfPages(): int
    {
        $t = $this->getRecordsTotalCount();
        $limit = $this->getRecordsLimitation();
        if ($t && $limit) {
            return (int)ceil($t / $limit);
        }
        return 0;
    }

    /**
     * Get object containing all paging data
     * @return array<string,int|null>
     */
    #[\Override]
    public function getPagination(): array
    {
        return [
            "COUNT" => $this->getRecordsCount(),
            "CURRENTPAGE" => $this->getCurrentPageNumber(),
            "FIRST" => $this->getFirstRecordIndex(),
            "LAST" => $this->getLastRecordIndex(),
            "LIMIT" => $this->getRecordsLimitation(),
            "NEXTPAGE" => $this->getNextPageNumber(),
            "PAGES" => $this->getNumberOfPages(),
            "PREVIOUSPAGE" => $this->getPreviousPageNumber(),
            "TOTAL" => $this->getRecordsTotalCount()
        ];
    }

    /**
     * Get Page Number of previous list query
     */
    #[\Override]
    public function getPreviousPageNumber(): ?int
    {
        $cp = $this->getCurrentPageNumber();
        if ($cp === null) {
            return null;
        }
        $cp -= 1;
        if ($cp === 0) {
            return null;
        }
        return $cp;
    }

    /**
     * Get previous record in record list
     */
    #[\Override]
    public function getPreviousRecord(): ?Record
    {
        if ($this->hasPreviousRecord()) {
            return $this->records[--$this->recordIndex];
        }
        return null;
    }

    /**
     * Get Record at given index
     * @param int $idx record index
     */
    #[\Override]
    public function getRecord(int $idx): ?Record
    {
        if ($idx >= 0 && $this->getRecordsCount() > $idx) {
            return $this->records[$idx];
        }
        return null;
    }

    /**
     * Get all Records
     * @return Record[]
     */
    #[\Override]
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * Get count of rows in this response
     */
    #[\Override]
    public function getRecordsCount(): int
    {
        return count($this->records);
    }

    /**
     * Get total count of records available for the list query
     */
    #[\Override]
    public function getRecordsTotalCount(): int
    {
        $col = $this->getColumn("TOTAL");
        if ($col instanceof ColumnInterface) {
            $t = $this->columnInt($col->getDataByIndex(0));
            if ($t !== null) {
                return $t;
            }
        }
        return $this->getRecordsCount();
    }

    /**
     * Get limit(ation) setting of the current list query
     * This is the count of requested rows
     */
    #[\Override]
    public function getRecordsLimitation(): int
    {
        $col = $this->getColumn("LIMIT");
        if ($col instanceof ColumnInterface) {
            $l = $this->columnInt($col->getDataByIndex(0));
            if ($l !== null) {
                return $l;
            }
        }
        return $this->getRecordsCount();
    }

    /**
     * Check if this list query has a next page
     */
    #[\Override]
    public function hasNextPage(): bool
    {
        $cp = $this->getCurrentPageNumber();
        if ($cp === null) {
            return false;
        }
        return ($cp + 1 <= $this->getNumberOfPages());
    }

    /**
     * Check if this list query has a previous page
     */
    #[\Override]
    public function hasPreviousPage(): bool
    {
        $cp = $this->getCurrentPageNumber();
        if ($cp === null) {
            return false;
        }
        return ($cp - 1 > 0);
    }

    /**
     * Reset index in record list back to zero
     */
    #[\Override]
    public function rewindRecordList(): static
    {
        $this->recordIndex = 0;
        return $this;
    }

    /**
     * Check if the record list contains a record for the
     * current record index in use
     */
    protected function hasCurrentRecord(): bool
    {
        $len = $this->getRecordsCount();
        return (
            $len > 0 &&
            $this->recordIndex >= 0 &&
            $this->recordIndex < $len
        );
    }

    /**
     * Check if the record list contains a next record for the
     * current record index in use
     */
    protected function hasNextRecord(): bool
    {
        $next = $this->recordIndex + 1;
        return ($this->hasCurrentRecord() && ($next < $this->getRecordsCount()));
    }

    /**
     * Check if the record list contains a previous record for the
     * current record index in use
     */
    protected function hasPreviousRecord(): bool
    {
        return ($this->recordIndex > 0 && $this->hasCurrentRecord());
    }

    /**
     * Get a string value from the hash by key, returning a default if not found or not a string
     */
    protected function getHashString(string $key, string $default = ""): string
    {
        return array_key_exists($key, $this->hash) && is_string($this->hash[$key])
            ? $this->hash[$key]
            : $default;
    }
}
