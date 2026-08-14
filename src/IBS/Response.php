<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\AbstractResponse;
use CNIC\Column;
use CNIC\IBS\ResponseParser as RP;
use CNIC\IBS\ResponseTranslator as RT;
use CNIC\Record;
use CNIC\ResponseInterface;
use CNIC\ResponseParserInterface;
use CNIC\ResponseTemplateManagerInterface;

/**
 * IBS Response
 *
 * Extends the shared AbstractResponse and supplies only what differs for the
 * IBS platform: the JSON-shaped response parsing (the translate() and
 * populate() hooks), the code/description accessors and the flat
 * (single-page) pagination model. The constructor, column/record bookkeeping,
 * record-cursor navigation and derived pagination are inherited from
 * AbstractResponse.
 *
 * IBS does NOT provide the CNR-only telemetry/transient-status/list-hash
 * capabilities, so those methods are simply absent here rather than present and
 * throwing. They live on CNR\Response via CNIC\ExtendedResponseInterface;
 * consumers narrow to that interface before using them.
 *
 * @psalm-api
 * @package CNIC\IBS
 */
class Response extends AbstractResponse implements ResponseInterface
{
    /**
     * The count keys IBS emits alongside a list, as a regex alternation.
     *
     * Kept apart from the rest of {@see $metaKeys} because on this brand the
     * count keys are also a **lookup pattern**, not just an exclusion list: the
     * same fact arrives under four different names depending on the endpoint —
     * Domain/List carries "domaincount", Url-/EmailForward/List "total_rules",
     * DnsRecord/List "total_records", Nameserver/List "total_hosts" — so
     * {@see getRecordsTotalCount()} has to *scan* the hash for whichever one is
     * present. Do not "simplify" it into a plain array of names, and do not fold
     * it into $metaKeys: matching that would find "transactid" first.
     *
     * The alternation is anchored by both regexes below so it matches these keys
     * exactly and never as a substring. In particular the loose ".*count" form is
     * avoided on purpose: Domain/Count returns one top-level key per TLD the
     * reseller holds, and ".discount" is a real gTLD, so a key literally named
     * "discount" can occur and must NOT be treated as metadata. "totaldomains"
     * (Domain/Count's grand total) is intentionally not matched either —
     * Domain/Count is a portfolio-structure query, not a list, and its total is
     * meaningful aggregate data.
     */
    private const string COUNT_KEYS = "total_.*|domaincount";

    /**
     * Anchored form of {@see COUNT_KEYS}, for the count-key scan.
     * @var non-empty-string
     */
    private const string COUNT_KEY_PATTERN = "/^(" . self::COUNT_KEYS . ")$/";

    /**
     * Regex for IBS's response metadata keys — the count keys above plus the
     * transaction-level fields every IBS response carries at the root
     * (transactid, status, message, code).
     *
     * The transaction fields are metadata for the same reason the counters are
     * (RSRMID-2965): they describe the *response*, not a row. Registering them as
     * columns put "status" on row 0 of a domain list and on no other row, and made
     * an empty list — which returns status and domaincount and no "domain" key at
     * all — report one phantom row consisting entirely of metadata. They stay
     * reachable where they belong: {@see getCode()}, {@see getDescription()},
     * {@see isError()}/{@see isSuccess()} and {@see \CNIC\AbstractResponse::getHash()}
     * all read the hash, not the columns.
     * @var non-empty-string
     */
    protected string $metaKeys = "/^(transactid|status|message|code|" . self::COUNT_KEYS . ")$/";

    /**
     * IBS carries sensitive data under lower-/camel-case command keys. Declared
     * once in {@see SensitiveFields::KEYS}, shared with {@see \CNIC\IBS\SocketConfig}.
     * @var string[]
     */
    protected array $sensitiveFields = SensitiveFields::KEYS;

    /**
     * Translate the raw API response using the IBS translator.
     * @param array<string, string> $cmd API command used within this request
     * @param array{CONNECTION_URL?: string} $placeholders
     * @param string|null $error transport error, if any; non-null means $raw is unusable
     * @param ResponseTemplateManagerInterface|null $templates registry to resolve template ids against; null uses IBS's built-ins
     */
    #[\Override]
    protected function translate(
        string $raw,
        array $cmd,
        array $placeholders,
        ?string $error = null,
        ?ResponseTemplateManagerInterface $templates = null
    ): string {
        return RT::translate($raw, $cmd, $placeholders, $error, $templates);
    }

    /**
     * Parse the translated response with the IBS parser and build the columns
     * from it. The IBS parser needs the sanitized command — it reads it to choose
     * between the JSON and plain-text wire shapes, which is why the command
     * arrives as an argument rather than off $this (see
     * AbstractResponse::__construct()). IBS responses are flat key => value maps;
     * each **data** entry becomes a column, list values kept as-is and anything
     * else wrapped into a single-cell list so the shared record assembly can
     * iterate them.
     *
     * The metadata entries are skipped (RSRMID-2965) — see {@see $metaKeys} for
     * which ones and why. That is the whole fix for both of this brand's row
     * defects: uniform row shape, because "status" is no longer a one-cell column
     * landing on row 0 of an n-row list, and 0 records instead of 1 for an empty
     * list, because nothing is left to size a row from.
     * @param array<string, string> $cmd API command used within this request, already sanitized
     */
    #[\Override]
    protected function populate(string $raw, ResponseParserInterface $parser, array $cmd): void
    {
        $this->hash = $parser->parse($raw, $cmd);
        $colKeys = array_map(strval(...), array_keys($this->hash));
        foreach ($colKeys as $k) {
            if ($this->isMetaKey($k)) {
                continue;
            }
            $this->addColumn($k, is_array($this->hash[$k]) && array_is_list($this->hash[$k]) ? $this->hash[$k] : [$this->hash[$k]]);
        }
        $this->assembleRecords();
    }

    /**
     * Get API response code.
     *
     * IBS returns a numeric "code" on some responses even though it is not part
     * of the public API documentation, e.g. for these requests:
     * - /Domain/Info?domain=noexistingdomain.com&...
     * - /unknown/path?...
     * Two shapes occur: a top-level "code", and — since the switch to
     * ResponseFormat=JSON — a per-product code nested under product[0].code
     * (earlier "product_0_code", RTLDEV-16781). When present the code is returned
     * as-is; otherwise it is derived from the status: 200 for a success, 500 for
     * an error (see isError()).
     */
    #[\Override]
    public function getCode(): int
    {
        // Top-level numeric code.
        if (isset($this->hash["code"]) && is_numeric($this->hash["code"])) {
            return intval($this->hash["code"]);
        }
        // Per-product code nested under product[0] (ResponseFormat=JSON). Cast
        // each level to an array so a missing or scalar value degrades to
        // "absent" instead of a type error.
        $product = (array)($this->hash["product"] ?? []);
        $first = (array)($product[0] ?? []);
        if (isset($first["code"]) && is_numeric($first["code"])) {
            return intval($first["code"]);
        }
        // No explicit code: map to CNR's numeric convention via status —
        // 200 for a clear success, 500 for a clear error (see isError()).
        return $this->isSuccess() ? 200 : 500;
    }

    /**
     * Get API response description
     */
    #[\Override]
    public function getDescription(): string
    {
        // Top-level message.
        $message = $this->getHashString("message");
        if ($message !== "") {
            return $message;
        }
        // Per-product message nested under product[0].message (since the switch
        // to ResponseFormat=JSON; earlier flat "product_0_message", RTLDEV-16781),
        // mirroring getCode()'s product[0].code handling. Cast each level to an
        // array so a missing or scalar value degrades to "absent".
        $product = (array)($this->hash["product"] ?? []);
        $first = (array)($product[0] ?? []);
        if (isset($first["message"]) && is_string($first["message"]) && $first["message"] !== "") {
            return $first["message"];
        }
        // No explicit message: derive from status the same way getCode() derives
        // 200/500 — a success message for a success, a failure message otherwise.
        return $this->isSuccess() ? "Command completed successfully" : "Command failed";
    }

    /**
     * Check if current API response represents an error case.
     *
     * FAILURE is the only IBS status that signals an error. Every other status
     * means the command itself succeeded — "SUCCESS" for ordinary commands, and
     * for Domain/Check specifically "AVAILABLE"/"UNAVAILABLE", which report the
     * domain's registrability rather than a failure. Missing/empty statuses are
     * normalised to FAILURE upstream by ResponseTranslator's fallback templates.
     */
    #[\Override]
    public function isError(): bool
    {
        return ($this->getHashString("status") === "FAILURE");
    }

    /**
     * Check if current API response represents a success case.
     *
     * The complement of isError(): any non-FAILURE status (SUCCESS, AVAILABLE,
     * UNAVAILABLE, ...) is a success. See isError() for why FAILURE is the sole
     * error signal.
     */
    #[\Override]
    public function isSuccess(): bool
    {
        return !$this->isError();
    }

    /**
     * Add a column to the column list
     *
     * IBS responses are JSON, so column values are arbitrary (nested arrays and
     * objects included); the shared CNIC\Column is used as-is, exactly like
     * CNR\Response::addColumn(). Both brands build their column locally and hand
     * the finished instance to the shared registerColumn() bookkeeping — see
     * registerColumn().
     *
     * Protected since RSRMID-2939, and called only from populate(): records are
     * assembled from the columns once, at the end of construction, so a column
     * added afterwards was absent from every record — present in getColumns() and
     * getColumnKeys(), invisible to getRecord()/getRecords() and to iteration.
     * @param array<array-key, mixed> $data array of column data
     */
    protected function addColumn(string $columnName, array $data): static
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
     * Instantiate the response parser for this brand (Moniker inherits it).
     */
    #[\Override]
    protected function newResponseParser(): ResponseParserInterface
    {
        return new RP();
    }

    /**
     * The value of whichever count key this response carries, or `null` if it
     * carries none.
     *
     * The single wire read all four pagination primitives below are built from:
     * IBS returns the full result set in one page, so the count key is the only
     * pagination fact on the wire, and first/last/total/limit are four questions
     * about it. Scans for the first root key matching {@see COUNT_KEY_PATTERN}
     * because the key's name is endpoint-dependent — see {@see COUNT_KEYS}.
     *
     * Numeric strings are accepted as well as ints: the JSON wire is not
     * consistent about quoting counts.
     */
    private function metaCount(): ?int
    {
        foreach (array_keys($this->hash) as $key) {
            if (preg_match(self::COUNT_KEY_PATTERN, $key) === 1 && is_numeric($this->hash[$key])) {
                return intval($this->hash[$key]);
            }
        }
        return null;
    }

    /**
     * Get Index of first row in this response — `0` for a list, `null` for a
     * response that is not one.
     *
     * IBS's single page always starts at offset 0, so the only question is
     * whether this response describes a list at all; the presence of a count key
     * is what answers it. The former unconditional `0` was a stand-in
     * (RSRMID-2965) that made every Domain/Info or Domain/Check look like the
     * first page of a list.
     */
    #[\Override]
    public function getFirstRecordIndex(): ?int
    {
        return $this->metaCount() === null ? null : 0;
    }

    /**
     * Get last record index of the current list query, or `null` when this
     * response carries no count key, or carries one that counts nothing.
     *
     * `count - 1`, from the wire count rather than from the record list
     * (RSRMID-2965). An empty list answers `null`, not `-1`: with the metadata no
     * longer forming a phantom row there is no row for an index to point at, and
     * `-1` was never a usable answer anyway — it was the artefact that made the
     * old code abandon the count key altogether.
     */
    #[\Override]
    public function getLastRecordIndex(): ?int
    {
        $total = $this->metaCount();
        return $total === null || $total <= 0 ? null : $total - 1;
    }

    /**
     * Get total count of records available for the list query, or `null` when
     * this response carries no count key (it is not a list).
     *
     * No `getRecordsCount()` (RSRMID-2965): the wire count is the brand's own
     * answer to "how many are there", and the record count is a property of the
     * rows this object holds — {@see \CNIC\AbstractResponse::getRecord()}'s bounds
     * authority, which must stay grounded in the array it indexes. They agree on
     * every honest IBS list, and conflating them is what let a phantom row
     * masquerade as a total.
     */
    #[\Override]
    public function getRecordsTotalCount(): ?int
    {
        return $this->metaCount();
    }

    /**
     * Get limit(ation) setting of the current list query — the count of
     * requested rows — or `null` when this response carries no count key.
     *
     * IBS has no limit/offset concept: one request returns the whole result set,
     * so the window size *is* the total and both read the same count key. This is
     * not a stand-in for an absent LIMIT — it is what a single-page brand's limit
     * means — and it keeps the shared derivation answering "1 page, no next page"
     * from arithmetic rather than from a brand special case.
     */
    #[\Override]
    public function getRecordsLimitation(): ?int
    {
        return $this->metaCount();
    }
}
