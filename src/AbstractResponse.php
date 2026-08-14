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
 *     (getFirstRecordIndex, getLastRecordIndex, getRecordsTotalCount,
 *     getRecordsLimitation) — the four methods that read a brand's own
 *     pagination metadata off its hash (metadata is not column data since
 *     RSRMID-2965 — see {@see AbstractResponse::$metaKeys}) — which this base
 *     deliberately does NOT implement —
 *     not even as single-page defaults — so a brand that forgets pagination
 *     fails at declaration time instead of silently answering "one page, no
 *     next page". The seam is drawn at the wire: a brand answers only what its
 *     own metadata says, and this base does every arithmetic derivation from those
 *     four answers (getCurrentPageNumber, hasNextPage, hasPreviousPage,
 *     getNextPageNumber, getPreviousPageNumber, getNumberOfPages included —
 *     RSRMID-2943 moved them here because they read no column of their own).
 *
 * None of the members in those last two groups is declared abstract *here*: they
 * are interface methods this base simply never implements, so every concrete
 * brand must supply them. Do not add base defaults for the four pagination
 * primitives — see docs/agents/architecture.md for why the seam is drawn there,
 * and tests/ResponsePaginationSeamTest.php, which refuses it.
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
     * Regex for the response-level metadata keys this brand's wire format mixes
     * in among the data keys — pagination counters and, on brands that carry
     * them, the transaction-level status fields.
     *
     * **Metadata is not column data (RSRMID-2965).** A key matching this is
     * never registered as a column, so it appears in neither
     * {@see getColumnKeys()}, {@see getColumns()} nor any assembled record; the
     * pagination primitives read it straight off {@see $hash} instead. It used to
     * become a one-cell column beside a 200-cell data column, which made
     * {@see assembleRecords()} size the record list as if the metadata were a
     * row: an empty window carrying nothing but counters reported one phantom
     * record whose entire content was metadata, and a populated list put the
     * metadata on row 0 only. Both follow from the modelling error, not from the
     * row assembly — which needed no change once the column set became correct.
     *
     * Brand-specific: each brand sets the keys its own endpoints emit, and the
     * two sets stay independent on purpose — CNR's QueryDomainHistoryList
     * returns a data column named STATUS, which a set shared with IBS would
     * silently delete. The neutral default (matches only the empty string, i.e.
     * no real key) excludes nothing, so a brand without metadata keys needs no
     * override.
     * @var non-empty-string
     */
    protected string $metaKeys = "/^$/";

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
     * brand, and both brands build the same shared CNIC\Column: CNR responses
     * are plaintext (always strings) and IBS/Moniker responses are JSON
     * (arbitrary values, nested arrays and objects included), a difference
     * expressed as a native return type on ColumnInterface::getStringByIndex()
     * rather than a per-brand Column subclass. Each brand's addColumn() still
     * builds its Column locally and hands the finished instance here, so this
     * shared helper never has to construct one itself — see
     * IBS\Response::addColumn()/CNR\Response::addColumn().
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
     * getRecordsCount() and, through it, the pagination getters IBS derives
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
     *
     * Data columns only. There is nothing left to filter here since
     * RSRMID-2965: a brand's populate() never registers a metadata key as a
     * column, so the list this returns is already free of them and the former
     * `getColumnKeys(bool $filterPaginationKeys)` — with its preg_grep over
     * every call — has no work to do. Do not re-add the flag: it existed only
     * because metadata was mixed into the column pool, and a boolean parameter
     * on a public interface is the cost that modelling error was charging every
     * consumer.
     * @return string[]
     */
    #[\Override]
    public function getColumnKeys(): array
    {
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
     * Get Page Number of current List Query, derived from the offset grid.
     *
     * A pure function of {@see getFirstRecordIndex()} and
     * {@see getRecordsLimitation()} — both wire-column primitives every brand
     * answers for itself — so this needs no brand override (RSRMID-2943).
     * `null` when either is unavailable, or when the limit is non-positive: a
     * non-positive window size has no meaningful page number.
     */
    #[\Override]
    public function getCurrentPageNumber(): ?int
    {
        $first = $this->getFirstRecordIndex();
        $limit = $this->getRecordsLimitation();
        if ($first === null || $limit === null || $limit <= 0) {
            return null;
        }
        return intdiv($first, $limit) + 1;
    }

    /**
     * Check if this list query has a next page.
     *
     * Answered from the offset grid directly — `LAST + 1 < TOTAL` — rather than
     * from page numbers, so it agrees with {@see getNextPageNumber()} even when
     * the current window is not aligned to a page boundary (e.g. FIRST=50,
     * LIMIT=100 is "page 1" but its next request starts at 150, not 200).
     *
     * An **empty** window is the case to keep in mind: CNR answers one by
     * echoing `LAST = FIRST` (with `COUNT = 0`), rather than by omitting LAST or
     * reporting a row index. Observed shapes, all `QueryDomainList`:
     *
     *   FIRST=0,        LIMIT=0  -> count=0, first=0,        last=0,        total=1825820
     *   FIRST=2000000,  LIMIT=0  -> count=0, first=2000000,  last=2000000,  total=1825824
     *   FIRST=20000000, LIMIT=10 -> count=0, first=20000000, last=20000000, total=1825824
     *
     * The third self-terminates on the arithmetic below, because LAST echoes an
     * offset far past TOTAL. The first two do not: `LAST + 1 < TOTAL` holds, and
     * without a gate `CNR\Client::requestNextResponsePage()` would advance to
     * `FIRST = 1` and re-walk the list from near the start. What stops them is
     * the **non-positive LIMIT** gate — the older of the two guards here, which
     * `CNR\Client` has relied on to terminate since before the offset grid
     * existed (see tests/CNR/ClientTest.php testRequestNextResponsePageZeroLimit).
     *
     * The `LAST < FIRST` gate is **defensive only** — no observed CNR response
     * does it, precisely because an empty window echoes `LAST = FIRST`. It pins
     * the invariant the client's advance depends on: since `LAST >= FIRST`
     * always, `FIRST = LAST + 1` strictly increases and the walk is monotonic.
     * A future wire change (or a substitute parser) that broke that would send
     * pagination backwards rather than failing, so it is refused here.
     *
     * Note that {@see getRecordsCount()} is still NOT the gate to use, however
     * much "an empty window has no next page" sounds like the same statement. It
     * reports how many rows *this* response holds — which, since RSRMID-2965, is
     * honestly 0 for the empty windows above — and says nothing about whether
     * more exist beyond them. A server that answered an empty window mid-list
     * would terminate the walk on that reading, while the limit gate refuses only
     * what was actually requested: a window of no rows.
     */
    #[\Override]
    public function hasNextPage(): bool
    {
        $limit = $this->getRecordsLimitation();
        if ($limit === null || $limit <= 0) {
            return false;
        }
        $first = $this->getFirstRecordIndex();
        $last = $this->getLastRecordIndex();
        $total = $this->getRecordsTotalCount();
        if ($first === null || $last === null || $total === null) {
            return false;
        }
        if ($last < $first) {
            return false;
        }
        return $last + 1 < $total;
    }

    /**
     * Check if this list query has a previous page.
     *
     * Answered from the offset grid directly — `FIRST > 0` — for the same
     * reason as {@see hasNextPage()}: an unaligned window still has a
     * well-defined "before it" even though it does not sit on a page boundary.
     *
     * The same LIMIT<=0 gate as {@see hasNextPage()} applies, for the same
     * reason: a non-positive window size cannot page backward either.
     */
    #[\Override]
    public function hasPreviousPage(): bool
    {
        $limit = $this->getRecordsLimitation();
        if ($limit === null || $limit <= 0) {
            return false;
        }
        $first = $this->getFirstRecordIndex();
        return $first !== null && $first > 0;
    }

    /**
     * Get Page Number of next list query.
     *
     * Computed from the *offset* the next request will actually start at
     * (`getLastRecordIndex() + 1`) rather than from `getCurrentPageNumber() + 1`.
     * The two agree on every window this can be asked about — for a full window
     * LAST + 1 is FIRST + LIMIT, so `intdiv(LAST + 1, LIMIT) + 1` reduces to
     * `intdiv(FIRST, LIMIT) + 2`; a short window only occurs at the tail, where
     * {@see hasNextPage()} is already false. The offset form is used anyway for
     * two reasons: it is the same grid {@see hasNextPage()} answers from, so the
     * predicate and this getter cannot drift apart under a later edit, and it
     * mirrors {@see getPreviousPageNumber()}, where the offset form and
     * `getCurrentPageNumber() - 1` genuinely do differ on an unaligned window.
     *
     * The value is a page number over the aligned grid, which stays a derived
     * view: for an unaligned window (FIRST=50, LIMIT=100) the request offsets
     * are exact and the page number is the page that offset lands on.
     *
     * The `$limit`/`$last` null-checks below are unreachable while
     * {@see hasNextPage()} holds true — it already required both to be
     * non-null and `$limit` positive — but PHPStan level 9 cannot see across
     * that method boundary, so they stay to keep the return type honestly
     * `?int` rather than asserting past the analyser. Do not "simplify" them
     * away.
     */
    #[\Override]
    public function getNextPageNumber(): ?int
    {
        if (!$this->hasNextPage()) {
            return null;
        }
        $limit = $this->getRecordsLimitation();
        $last = $this->getLastRecordIndex();
        if ($limit === null || $limit <= 0 || $last === null) {
            return null;
        }
        return intdiv($last + 1, $limit) + 1;
    }

    /**
     * Get the number of pages available for this list query.
     *
     * `0` when either total or limit is unavailable and this response holds no
     * records (nothing to page through); `1` when it holds records but is not
     * itself a paginated list (an implicit single page, mirroring IBS's
     * always-one-page model). Otherwise the ceiling of total/limit.
     */
    #[\Override]
    public function getNumberOfPages(): int
    {
        $t = $this->getRecordsTotalCount();
        $limit = $this->getRecordsLimitation();
        if ($t === null || $limit === null) {
            return $this->getRecordsCount() === 0 ? 0 : 1;
        }
        if ($t > 0 && $limit > 0) {
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
     * Get Page Number of previous list query.
     *
     * Computed from the offset the previous request would start at
     * (`max(0, FIRST - LIMIT)`), not from `getCurrentPageNumber() - 1`: for an
     * unaligned window the two disagree the same way {@see getNextPageNumber()}'s
     * do, and the offset form is the one that matches what would actually be
     * requested. For an aligned FIRST both forms reduce to the same classic
     * value.
     *
     * The null-checks below are unreachable while {@see hasPreviousPage()}
     * holds true — it already required both to be non-null and `$limit`
     * positive — but stay for the same PHPStan-level-9 reason documented on
     * {@see getNextPageNumber()}. Do not "simplify" them away.
     */
    #[\Override]
    public function getPreviousPageNumber(): ?int
    {
        if (!$this->hasPreviousPage()) {
            return null;
        }
        $first = $this->getFirstRecordIndex();
        $limit = $this->getRecordsLimitation();
        if ($first === null || $limit === null || $limit <= 0) {
            return null;
        }
        return intdiv(max(0, $first - $limit), $limit) + 1;
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

    /**
     * Is this wire key response metadata rather than data?
     *
     * The one place {@see $metaKeys} is matched, called from each brand's
     * populate() before it registers a column. Shared so that "which keys are
     * metadata" is answered identically for every brand while *what* those keys
     * are stays brand-specific — see {@see $metaKeys} for why the two sets must
     * not be merged.
     */
    protected function isMetaKey(string $key): bool
    {
        return preg_match($this->metaKeys, $key) === 1;
    }
}
