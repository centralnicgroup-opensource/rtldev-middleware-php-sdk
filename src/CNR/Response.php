<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

use CNIC\AbstractResponse;
use CNIC\CNR\Column;
use CNIC\CNR\ResponseParser as RP;
use CNIC\CNR\ResponseTranslator as RT;
use CNIC\ColumnInterface;
use CNIC\Exception\UnsupportedFeatureException;
use CNIC\ExtendedResponseInterface;
use CNIC\Record;
use CNIC\ResponseParserInterface;

/**
 * CNR Response
 *
 * Extends the shared AbstractResponse with the CNR wire specifics — the
 * translate()/populate() hooks, the CODE/DESCRIPTION status accessors, the
 * CNR Column type and the column-driven pagination primitives — and adds the
 * richer CNR-only capabilities declared on {@see ExtendedResponseInterface}
 * (telemetry, transient/pending status and the list-hash projection) that flat
 * platforms like IBS/Moniker do not provide.
 *
 * @psalm-api
 * @package CNIC\CNR
 */
class Response extends AbstractResponse implements ExtendedResponseInterface
{
    /**
     * Command parameter keys carrying sensitive data (masked before storage).
     * CNR uses upper-case keys. Declared once in {@see SensitiveFields::KEYS},
     * shared with {@see \CNIC\CNR\SocketConfig}.
     * @var string[]
     */
    protected array $sensitiveFields = SensitiveFields::KEYS;

    /**
     * Regex for pagination related column keys.
     * The alternation is grouped so the ^…$ anchors apply to every keyword;
     * without the group only TOTAL/LAST are anchored and COUNT|LIMIT|FIRST
     * would match anywhere, wrongly stripping real columns such as COUNTRY,
     * FIRSTNAME, DISCOUNT or ACCOUNT from getColumnKeys()/getListHash().
     * @var non-empty-string
     */
    protected string $paginationKeys = "/^(TOTAL|COUNT|LIMIT|FIRST|LAST)$/";

    /**
     * Translate the raw API response into its canonical form using the CNR
     * translator. $cmd is already sanitized.
     * @param array<string, string> $cmd API command used within this request
     * @param array{CONNECTION_URL?: string} $placeholders
     * @param string|null $error transport error, if any; non-null means $raw is unusable
     */
    #[\Override]
    protected function translate(string $raw, array $cmd, array $placeholders, ?string $error = null): string
    {
        return RT::translate($raw, $cmd, $placeholders, $error);
    }

    /**
     * Parse the translated response into the hash and build the column/record
     * lists from it. CNR exposes its columns under the PROPERTY sub-array and
     * assembles records only when properties are present.
     */
    #[\Override]
    protected function populate(): void
    {
        $this->hash = $this->parser->parse($this->raw, $this->command);
        // A PROPERTY that is absent or not an array yields no columns and no
        // records — the same as the is_array() guard this replaced.
        $properties = $this->getHashArray("PROPERTY");
        if ($properties !== []) {
            foreach (array_keys($properties) as $k) {
                $key = strval($k);
                $this->addColumn($key, self::stringCells($key, $properties[$k]));
            }
            $this->assembleRecords();
        }
    }

    /**
     * Narrow one parsed PROPERTY entry to the string list a CNR Column takes.
     *
     * The CNR wire format is textual, so every cell of a real response is already
     * a string and this rejects nothing. It exists because the parse step is a
     * seam: the contract returns array<string, mixed>, so the brand's own shape has
     * to be re-established here rather than read off the concrete parser's return
     * type — which is also why CNR\Column binds its value type to string.
     *
     * A parser handing CNR anything else is contradicting that binding, so both
     * checks **throw** rather than skipping or coercing: silently dropping data
     * would be the no-op this project rules out, and coercing would invent a value
     * the wire could never carry. Keep the container check as strict as the cells —
     * casting it with (array) instead quietly turns a bare string into a one-cell
     * column while a bare int still throws one line later, coercing a bad container
     * while refusing a bad cell. Unreachable with either brand parser; reachable
     * only from a substitute, where it is a programming error and should say so.
     *
     * Note the deliberate asymmetry with populate(): a *missing or non-array*
     * PROPERTY block yields no columns rather than throwing, because most CNR
     * responses legitimately have none. This is about the contents of a block that
     * does exist.
     *
     * Written as an explicit loop rather than array_filter(..., "is_string"):
     * PHPStan narrows that form, Psalm does not (MixedReturnTypeCoercion), and
     * the loop leaves only the one MixedAssignment below to suppress — the same
     * trade already made in AbstractResponse::assembleRecords().
     * @return string[]
     * @throws UnsupportedFeatureException if the entry is not a list, or a cell is not a string
     */
    private static function stringCells(string $key, mixed $values): array
    {
        if (!is_array($values)) {
            throw new UnsupportedFeatureException(
                "CNR columns are string lists: PROPERTY[{$key}] is a " . get_debug_type($values) . "."
            );
        }
        $cells = [];
        /** @psalm-suppress MixedAssignment the loop variable is mixed by design; is_string() narrows it below */
        foreach ($values as $cell) {
            if (!is_string($cell)) {
                throw new UnsupportedFeatureException(
                    "CNR columns are string-valued: PROPERTY[{$key}] carries a " . get_debug_type($cell) . "."
                );
            }
            $cells[] = $cell;
        }
        return $cells;
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
     * @param string[] $data array of column data
     * @psalm-suppress MoreSpecificImplementedParamType CNR columns are always string-valued
     */
    #[\Override]
    public function addColumn(string $columnName, array $data): static
    {
        return $this->registerColumn(new Column($columnName, $data));
    }

    /**
     * Instantiate the record type for this brand.
     * @param array<string,mixed> $row
     */
    #[\Override]
    protected function newRecord(array $row): Record
    {
        return new Record($row);
    }

    /**
     * Instantiate the response parser for this brand.
     */
    #[\Override]
    protected function newResponseParser(): ResponseParserInterface
    {
        return new RP();
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
}
