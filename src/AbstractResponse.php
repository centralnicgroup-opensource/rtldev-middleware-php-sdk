<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Shared Response foundation
 *
 * Brand-neutral base for every registrar Response. It owns the machinery that
 * is identical across brands — the constructor skeleton (template method),
 * command sanitisation, column/record bookkeeping, record-cursor navigation and
 * the derived pagination getters — and leaves the parts that genuinely differ
 * to the concrete subclasses:
 *
 *   - wire hooks: {@see translate()} / {@see populate()} (protected),
 *   - factories: {@see newRecord()} and {@see newResponseParser()} (protected),
 *   - the status/code accessors and addColumn declared on
 *     {@see ResponseInterface} (getCode/getDescription/isError/isSuccess,
 *     addColumn) — each reads a different wire shape, and addColumn additionally
 *     has to build its brand's own correctly-typed Column (see registerColumn()),
 *   - the pagination primitives, likewise declared on {@see ResponseInterface}
 *     (getCurrentPageNumber, getFirstRecordIndex, getLastRecordIndex,
 *     getRecordsTotalCount, getRecordsLimitation, hasNextPage, hasPreviousPage),
 *     which this base deliberately does NOT implement — not even as single-page
 *     defaults — so a brand that forgets pagination fails at declaration time
 *     instead of silently answering "one page, no next page".
 *
 * None of the members in those last two groups is declared abstract *here*: they
 * are interface methods this base simply never implements, so every concrete
 * brand must supply them. Do not add base defaults for the pagination primitives
 * — see docs/agents/architecture.md for why the seam is drawn there, and
 * tests/ResponsePaginationSeamTest.php, which refuses it.
 *
 * CNR\Response and IBS\Response both extend this as siblings — mirroring the
 * AbstractClient / AbstractSocketConfig / AbstractResponseTemplateManager /
 * AbstractResponseTranslator pattern — so neither brand is-a the other. The
 * CNR-only capabilities (telemetry, transient/pending status, list-hash) live
 * on CNR\Response via {@see ExtendedResponseInterface} and are deliberately NOT
 * part of this base, so brands like IBS/Moniker never inherit methods they
 * cannot support.
 *
 * @psalm-api
 * @package CNIC
 */
abstract class AbstractResponse implements ResponseInterface
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
     * the names matter, not their casing. Brand-specific by design: each brand
     * declares the keys it uses (CNR upper-case, IBS lower-/camel-case); the
     * neutral default masks nothing.
     * @var string[]
     */
    protected array $sensitiveFields = [];

    /**
     * plain API response
     */
    protected string $raw;

    /**
     * hash representation of plain API response.
     * Defaulted to an empty array because the concrete parse happens in the
     * abstract populate() hook (called from the constructor), which the analyser
     * cannot trace as an initialiser.
     * @var array<string, mixed>
     */
    protected array $hash = [];

    /**
     * Regex for pagination related column keys, stripped in getColumnKeys(true).
     * Brand-specific: each brand sets the keys its list endpoints emit. The
     * neutral default (matches only the empty string, i.e. no real key) strips
     * nothing, so a brand that does not paginate needs no override.
     * @var non-empty-string
     */
    protected string $paginationKeys = "/^$/";

    /**
     * Column names available in this response
     * @var string[]
     */
    protected array $columnKeys = [];

    /**
     * Container of Column Instances
     * @var ColumnInterface[]
     */
    protected array $columns = [];

    /**
     * Map of column name to its index in the column/columnKeys lists.
     * Maintained by registerColumn() to provide O(1) column lookup. First
     * occurrence wins, mirroring the previous array_search() behaviour.
     * @var array<string, int>
     */
    protected array $columnIndex = [];

    /**
     * Record Index we currently point to in record list
     */
    protected int $recordIndex = 0;

    /**
     * Record List (List of rows)
     * @var RecordInterface[]
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
     * The parser turning the translated raw response into {@see $hash}.
     * Defaults to the brand's own via {@see newResponseParser()}; a substitute
     * can be handed to the constructor. See {@see ResponseParserInterface}.
     */
    protected ResponseParserInterface $parser;

    /**
     * Constructor
     * @param string $raw API plain response
     * @param array<string, string> $cmd API command used within this request
     * @param array{CONNECTION_URL?: string} $placeholders vars the response description has dynamically replaced
     * @param array<string,mixed> $context context data for the response (for use in custom loggers etc., optional, has no impact on SDK behaviour)
     * @param ResponseParserInterface|null $parser parser to use instead of the brand default (see newResponseParser())
     */
    public function __construct(
        string $raw,
        array $cmd = [],
        array $placeholders = [],
        array $context = [],
        ?ResponseParserInterface $parser = null
    ) {
        $cmd = $this->sanitizeCommand($cmd);
        $this->context = $context;
        $this->command = $cmd;
        $this->requestUrl = $placeholders["CONNECTION_URL"] ?? "";
        // Assigned before populate(), which is where the parser is used.
        $this->parser = $parser ?? $this->newResponseParser();
        $this->raw = $this->translate($raw, $cmd, $placeholders);
        $this->populate();
    }

    /**
     * Translate the raw API response into its canonical form.
     * Brand-specific by the ResponseTranslator each subclass imports; $cmd is
     * already sanitized.
     * @param array<string, string> $cmd API command used within this request
     * @param array{CONNECTION_URL?: string} $placeholders
     */
    abstract protected function translate(string $raw, array $cmd, array $placeholders): string;

    /**
     * Parse the translated response into the hash and build the column/record
     * lists from it. Brand-specific because each brand's parser returns a
     * different hash shape (CNR nests columns under PROPERTY, IBS is a flat
     * key => value map). The sanitized command is available as $this->command,
     * and the parser — the brand default or the injected substitute — as
     * $this->parser.
     */
    abstract protected function populate(): void;

    /**
     * Instantiate the response parser for this brand.
     *
     * Factory hook mirroring {@see newRecord()} and
     * {@see \CNIC\AbstractClient::newTransport()}: it supplies the default, and
     * the constructor's $parser argument overrides it — so a substitute parser
     * needs neither reflection nor a subclass. `populate()` must parse through
     * `$this->parser`; instantiating a parser inline there behaves identically and
     * silently closes the seam, which is why the guard is structural
     * (tests/ResponseParserSeamTest.php).
     */
    abstract protected function newResponseParser(): ResponseParserInterface;

    /**
     * Instantiate the record type for this brand.
     *
     * Factory hook for addRecord(). Records share one shape across brands
     * (array<string,mixed>), so every brand currently returns the same shared
     * CNIC\Record — the hook stays abstract nonetheless, because it is the seam
     * a brand needing genuinely different row behaviour would implement, and
     * hard-coding the shared Record here would close it. (Unlike columns, whose
     * value types diverge and so cannot use a param-typed factory at all — see
     * registerColumn().)
     * @param array<string,mixed> $row
     */
    abstract protected function newRecord(array $row): RecordInterface;

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
     * addColumn(). Shared by all brands: each subclass populates the columns
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
            foreach ($this->columnKeys as $k) {
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
     * Get Plain API response
     */
    #[\Override]
    public function getPlain(): string
    {
        return $this->raw;
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
     * Register an already-constructed column into the list bookkeeping.
     *
     * The bookkeeping ($columns/$columnKeys/$columnIndex) is identical for every
     * brand; what differs is the column's value type. Rather than a param-typed
     * newColumn() factory — which cannot stay type-clean under PHPStan L9 / Psalm
     * L1, because CNR columns take string[] while IBS columns take mixed[] and a
     * shared factory would have to narrow one into the other — each brand's
     * addColumn() builds its own correctly-typed Column locally and hands the
     * finished instance here, so this shared helper never sees the brand types.
     */
    protected function registerColumn(ColumnInterface $col): static
    {
        $key = $col->getKey();
        $this->columns[] = $col;
        $this->columnKeys[] = $key;
        $this->columnIndex[$key] ??= count($this->columns) - 1;
        return $this;
    }

    /**
     * Add a record to the record list
     * @param array<string,mixed> $row
     */
    #[\Override]
    public function addRecord(array $row): static
    {
        $this->records[] = $this->newRecord($row);
        return $this;
    }

    /**
     * Get column by column name
     */
    #[\Override]
    public function getColumn(string $columnName): ?ColumnInterface
    {
        $idx = $this->columnIndex[$columnName] ?? null;
        return $idx === null ? null : $this->columns[$idx];
    }

    /**
     * Get Data by Column Name and Index
     */
    #[\Override]
    public function getColumnIndex(string $columnName, int $recordIndex): mixed
    {
        $col = $this->getColumn($columnName);
        return $col instanceof ColumnInterface ? $col->getDataByIndex($recordIndex) : null;
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
            $paginationKeys = preg_grep($this->paginationKeys, $this->columnKeys, PREG_GREP_INVERT) ?: [];
            return array_values($paginationKeys);
        }
        return $this->columnKeys;
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
     * Get Record of current record index
     */
    #[\Override]
    public function getCurrentRecord(): ?RecordInterface
    {
        return $this->hasCurrentRecord() ? $this->records[$this->recordIndex] : null;
    }

    /**
     * Get next record in record list
     */
    #[\Override]
    public function getNextRecord(): ?RecordInterface
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
    public function getPreviousRecord(): ?RecordInterface
    {
        if ($this->hasPreviousRecord()) {
            return $this->records[--$this->recordIndex];
        }
        return null;
    }

    /**
     * Get Record at given index
     */
    #[\Override]
    public function getRecord(int $recordIndex): ?RecordInterface
    {
        if ($recordIndex >= 0 && $this->getRecordsCount() > $recordIndex) {
            return $this->records[$recordIndex];
        }
        return null;
    }

    /**
     * Get all Records
     * @return RecordInterface[]
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

    /**
     * Get an array value from the hash by key, returning an empty array if not
     * found or not an array. The twin of {@see getHashString()} for the nested
     * blocks a brand's populate() reads (e.g. CNR's PROPERTY).
     * @return array<array-key, mixed>
     */
    protected function getHashArray(string $key): array
    {
        return array_key_exists($key, $this->hash) && is_array($this->hash[$key])
            ? $this->hash[$key]
            : [];
    }
}
