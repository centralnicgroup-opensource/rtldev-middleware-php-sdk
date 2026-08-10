<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

use ArrayIterator;
use CNIC\Exception\DuplicateColumnException;
use Traversable;

/**
 * Shared Response foundation
 *
 * Brand-neutral base for every registrar Response. It owns the machinery that
 * is identical across brands — the constructor skeleton (template method),
 * command sanitisation, column/record bookkeeping, record iteration and
 * the derived pagination getters — and leaves the parts that genuinely differ
 * to the concrete subclasses:
 *
 *   - wire hooks: {@see translate()} / {@see populate()} (protected),
 *   - factories: {@see newRecord()} and {@see newResponseParser()} (protected),
 *   - the brand's own `addColumn()` (protected), which has to build its
 *     correctly-typed Column before handing it to {@see registerColumn()},
 *   - the status/code accessors declared on {@see ResponseInterface}
 *     (getCode/getDescription/isError/isSuccess) — each reads a different wire
 *     shape,
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
     * declares the keys it uses, sourced from a single per-brand constant (e.g.
     * {@see \CNIC\CNR\SensitiveFields::KEYS}) shared with the corresponding
     * SocketConfig class; the neutral default masks nothing.
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
     * Constructor
     *
     * Assembles the response completely: every column and record exists by the
     * time this returns, and nothing afterwards can add one (RSRMID-2939) — see
     * the sealing note on {@see ResponseInterface}.
     *
     * The parser is a constructor local, not a property, and reaches
     * {@see populate()} as an argument. So does the translated raw response and
     * the sanitized command. That is deliberate: while `populate()` read them off
     * `$this`, the order of the assignments above it was load-bearing and
     * enforced by nothing but a comment — moving the `$this->command` assignment
     * below the `populate()` call silently switched the IBS parser to its other
     * wire branch, because that parser reads the command to choose JSON vs plain
     * text. Passing them in makes the dependency a signature, so there is no
     * order left to get wrong. Do not reintroduce a `$parser` property: nothing
     * after construction has any use for it.
     *
     * @param string $raw API plain response
     * @param array<string, string> $cmd API command used within this request
     * @param array{CONNECTION_URL?: string} $placeholders vars the response description has dynamically replaced
     * @param array<string,mixed> $context context data for the response (for use in custom loggers etc., optional, has no impact on SDK behaviour)
     * @param ResponseParserInterface|null $parser parser to use instead of the brand default (see newResponseParser())
     * @param string|null $error transport error, if any; non-null means $raw is unusable and the brand's "httperror" template is substituted instead (see {@see AbstractResponseTranslator::translate()})
     * @param ResponseTemplateManagerInterface|null $templates registry the translator resolves template ids against; null uses the brand's built-ins. Supplying one is how a caller scopes a registered template to this response instead of to the whole process (RSRMID-2941)
     */
    public function __construct(
        string $raw,
        array $cmd = [],
        array $placeholders = [],
        array $context = [],
        ?ResponseParserInterface $parser = null,
        ?string $error = null,
        ?ResponseTemplateManagerInterface $templates = null
    ) {
        $cmd = $this->sanitizeCommand($cmd);
        $this->context = $context;
        $this->command = $cmd;
        $this->requestUrl = $placeholders["CONNECTION_URL"] ?? "";
        $translated = $this->translate($raw, $cmd, $placeholders, $error, $templates);
        $this->raw = $translated;
        $this->populate($translated, $parser ?? $this->newResponseParser(), $cmd);
    }

    /**
     * Translate the raw API response into its canonical form.
     * Brand-specific by the ResponseTranslator each subclass imports; $cmd is
     * already sanitized.
     * @param array<string, string> $cmd API command used within this request
     * @param array{CONNECTION_URL?: string} $placeholders
     * @param string|null $error transport error, if any; non-null means $raw is unusable (see {@see AbstractResponseTranslator::translate()})
     * @param ResponseTemplateManagerInterface|null $templates registry to resolve template ids against; null uses the brand's built-ins
     */
    abstract protected function translate(
        string $raw,
        array $cmd,
        array $placeholders,
        ?string $error = null,
        ?ResponseTemplateManagerInterface $templates = null
    ): string;

    /**
     * Parse the translated response into the hash and build the column/record
     * lists from it. Brand-specific because each brand's parser returns a
     * different hash shape (CNR nests columns under PROPERTY, IBS is a flat
     * key => value map).
     *
     * Everything it needs arrives as an argument rather than being read off a
     * half-initialised `$this` — see the constructor for why. Parse through the
     * given $parser: instantiating one inline behaves identically and silently
     * closes the injection seam, which is why the guard is structural
     * (tests/ResponseParserSeamTest.php).
     *
     * Called exactly once, from the constructor. It is the only place columns and
     * records are built, so it must finish the job: nothing afterwards can add to
     * either list.
     *
     * @param string $raw the translated response, as returned by {@see translate()}
     * @param ResponseParserInterface $parser the brand default or the injected substitute
     * @param array<string, string> $cmd API command used within this request, already sanitized
     */
    abstract protected function populate(string $raw, ResponseParserInterface $parser, array $cmd): void;

    /**
     * Instantiate the response parser for this brand.
     *
     * Factory hook mirroring {@see newRecord()} and
     * {@see \CNIC\AbstractClient::newTransport()}: it supplies the default, and
     * the constructor's $parser argument overrides it — so a substitute parser
     * needs neither reflection nor a subclass. Whichever wins is handed to
     * {@see populate()}, which must parse through it; instantiating a parser
     * inline there behaves identically and silently closes the seam, which is why
     * the guard is structural (tests/ResponseParserSeamTest.php).
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
     * Delegates the actual matching/masking to {@see CommandRedactor::redact()},
     * which is shared with {@see AbstractSocketConfig::maskSensitiveCommand()}.
     * Matching is case-insensitive to stay robust against casing differences
     * between what a brand documents and what it actually sends.
     * @param array<string, string> $cmd API command used within this request
     * @return array<string, string>
     */
    protected function sanitizeCommand(array $cmd): array
    {
        return CommandRedactor::redact($cmd, $this->sensitiveFields);
    }

    /**
     * Assemble the record (row) list from the columns already added via
     * addColumn(). Shared by all brands: each subclass populates the columns
     * with its own Column type beforehand, while the row assembly is identical.
     *
     * Replaces the record list rather than appending to it, so calling it twice
     * yields the same rows instead of doubling them (RSRMID-2939). No caller does
     * — each brand's populate() calls it once, at the end — but "assembles the
     * records" is what the name promises, and an append-only version made that
     * promise conditional on a call count nothing enforced.
     */
    protected function assembleRecords(): void
    {
        $this->records = [];
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
     *
     * A repeated column name is refused rather than half-registered
     * (RSRMID-2939). The three lists are one data structure: $columns/$columnKeys
     * are positional while $columnIndex maps a name to one position, so a second
     * column under an existing name used to append to the first two while the
     * `??=` kept the index pointing at the first — leaving getColumns() holding a
     * column getColumn() could never return, and getColumnKeys() listing a name
     * twice.
     *
     * Neither shipped brand can reach the throw, and neither can a substitute
     * parser: both brands derive their column names from array_keys() of the
     * parsed hash, and two distinct PHP array keys cannot stringify to the same
     * name. It guards the invariant against a *future* brand whose populate()
     * builds its columns some other way — there the collision is a programming
     * error and says so instead of silently desynchronising the three lists.
     * @throws DuplicateColumnException if a column of that name is already registered
     */
    protected function registerColumn(ColumnInterface $col): static
    {
        $key = $col->getKey();
        if (array_key_exists($key, $this->columnIndex)) {
            throw new DuplicateColumnException(
                "Column \"{$key}\" is already registered on this response; a column name resolves to "
                . "exactly one column."
            );
        }
        $this->columns[] = $col;
        $this->columnKeys[] = $key;
        $this->columnIndex[$key] = count($this->columns) - 1;
        return $this;
    }

    /**
     * Add a record to the record list.
     *
     * Protected since RSRMID-2939: a record added after construction changed
     * getRecordsCount() and, through it, the four pagination getters IBS derives
     * from it (getRecordsTotalCount/getRecordsLimitation/getLastRecordIndex/
     * getNumberOfPages) — so a caller could silently repaginate a finished
     * response. Only {@see assembleRecords()} calls this.
     * @param array<string,mixed> $row
     */
    protected function addRecord(array $row): static
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
     * Iterate the record list, keyed by record index.
     *
     * A fresh ArrayIterator per call, over a list that can no longer change: two
     * `foreach` loops over one response therefore see identical rows, in either
     * order, without a rewind step between them, and neither is observable to the
     * other. That is the property the removed record cursor could not offer —
     * see {@see ResponseInterface} for the full account.
     * @return Traversable<int, RecordInterface>
     */
    #[\Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->records);
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
