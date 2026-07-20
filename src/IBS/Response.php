<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\CNR\Response as CNRResponse;
use CNIC\Exception\UnsupportedFeatureException;
use CNIC\IBS\Column as IBSColumn;
use CNIC\IBS\Record as IBSRecord;
use CNIC\IBS\ResponseParser as RP;
use CNIC\IBS\ResponseTranslator as RT;
use CNIC\ResponseInterface;

/**
 * IBS Response
 *
 * Extends CNR\Response and only overrides what genuinely differs for the IBS
 * platform: the JSON-shaped response parsing (the translate() and populate()
 * constructor hooks), the status/code/description accessors, the IBS column
 * type, the not-supported contract methods and the flat (single-page)
 * pagination model. The constructor itself and every other accessor and the
 * record-cursor navigation are inherited unchanged from CNR\Response.
 *
 * @psalm-api
 * @package CNIC\IBS
 */
class Response extends CNRResponse implements ResponseInterface
{
    /**
     * Regex for the count/metadata column keys IBS emits alongside a list.
     *
     * Derived from the real IBS list endpoints: Domain/List carries
     * "domaincount", while Url-/EmailForward/List use "total_rules" and
     * DnsRecord/List "total_records". The alternation is fully anchored so it
     * matches those keys exactly and never as a substring. In particular the
     * loose ".*count" form is avoided on purpose: Domain/Count returns one
     * top-level key per TLD the reseller holds, and ".discount" is a real gTLD,
     * so a key literally named "discount" can occur and must NOT be treated as
     * metadata. "totaldomains" (Domain/Count's grand total) is intentionally
     * NOT matched either — it is meaningful aggregate data, not list metadata.
     *
     * Used only to strip these columns in getColumnKeys(true)/getListHash().
     * It does NOT drive getLastRecordIndex(): IBS returns the full result set
     * in a single page, so the last index is the record-grounded count - 1.
     * @var non-empty-string
     */
    protected string $paginationkeys = "/^(total_.*|domaincount)$/";

    /**
     * IBS carries sensitive data under lower-/camel-case command keys.
     * @var string[]
     */
    protected array $sensitiveFields = ["password", "transferAuthInfo"];

    /**
     * Translate the raw API response using the IBS translator.
     * @param array<string, string> $cmd API command used within this request
     * @param array{CONNECTION_URL?: string} $ph placeholder array for dynamic replacement
     */
    #[\Override]
    protected function translate(string $raw, array $cmd, array $ph): string
    {
        return RT::translate($raw, $cmd, $ph);
    }

    /**
     * Parse the translated response with the IBS parser and build the columns
     * from it. The IBS parser needs the sanitized command (kept on
     * $this->command by the shared constructor). IBS responses are flat
     * key => value maps; each hash entry becomes a column, list values kept
     * as-is and anything else wrapped into a single-cell list so the shared
     * record assembly can iterate them.
     */
    #[\Override]
    protected function populate(): void
    {
        $this->hash = RP::parse($this->raw, $this->command);
        $colKeys = array_map(strval(...), array_keys($this->hash));
        foreach ($colKeys as $k) {
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
     * Get API response status
     */
    public function getStatus(): string
    {
        return $this->getHashString("status");
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
     * Get Queuetime of API response
     * @throws UnsupportedFeatureException
     */
    #[\Override]
    public function getQueuetime(): float
    {
        throw new UnsupportedFeatureException("Not supported");
    }

    /**
     * Get Runtime of API response
     * @throws UnsupportedFeatureException
     */
    #[\Override]
    public function getRuntime(): float
    {
        throw new UnsupportedFeatureException("Not supported");
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
     * Check if current API response represents a temporary error case
     * @throws UnsupportedFeatureException
     */
    #[\Override]
    public function isTmpError(): bool
    {
        throw new UnsupportedFeatureException("Not supported");
    }

    /**
     * Check if current operation is returned as pending
     * @throws UnsupportedFeatureException
     */
    #[\Override]
    public function isPending(): bool
    {
        throw new UnsupportedFeatureException("Not supported");
    }

    /**
     * Add a column to the column list
     *
     * IBS uses its own standalone Column (mixed-typed JSON values) rather than
     * CNR's string-only Column, so it builds that type here and hands it to the
     * shared registerColumn() bookkeeping in the base. The two Column
     * constructors have divergent value types (string[] vs mixed[]), which is
     * why a param-typed newColumn() factory would not stay type-clean — see
     * registerColumn().
     * @param string $key column name
     * @param array<array-key, mixed> $data array of column data
     */
    #[\Override]
    public function addColumn(string $key, array $data): static
    {
        return $this->registerColumn(new IBSColumn($key, $data));
    }

    /**
     * Instantiate the IBS record type so IBS-specific record behaviour applies.
     * @param array<string,mixed> $h row hash data
     */
    #[\Override]
    protected function newRecord(array $h): IBSRecord
    {
        return new IBSRecord($h);
    }

    /**
     * Get Page Number of current List Query
     */
    #[\Override]
    public function getCurrentPageNumber(): ?int
    {
        return 1;
    }

    /**
     * Get Index of first row in this response
     */
    #[\Override]
    public function getFirstRecordIndex(): ?int
    {
        return 0;
    }

    /**
     * Get last record index of the current list query
     */
    #[\Override]
    public function getLastRecordIndex(): ?int
    {
        // IBS returns the full result set in a single page — there is no
        // limit/offset/page cursor — so the last index is simply count - 1,
        // grounded in the actual record list (mirrors CNR\Response).
        //
        // We deliberately do NOT derive this from a "count" column (e.g.
        // domaincount, total_rules, total_records). That field equals the row
        // count when the list is populated (so it is redundant), but on an
        // empty list it is 0 while the meta keys (transactid/status/...) still
        // form one record — which made the old count-key logic underflow LAST
        // to -1, contradicting FIRST=0/COUNT=1. Grounding LAST in the records
        // keeps the pagination block internally coherent in every case.
        $c = $this->getRecordsCount();
        if ($c !== 0) {
            return $c - 1;
        }
        return null;
    }

    /**
     * Get Response as List Hash including useful meta data for tables
     * @throws UnsupportedFeatureException
     */
    #[\Override]
    public function getListHash(): array
    {
        throw new UnsupportedFeatureException("Not implemented.");
    }

    /**
     * Get total count of records available for the list query
     */
    #[\Override]
    public function getRecordsTotalCount(): int
    {
        return $this->getRecordsCount();
    }

    /**
     * Get limit(ation) setting of the current list query
     * This is the count of requested rows
     */
    #[\Override]
    public function getRecordsLimitation(): int
    {
        return $this->getRecordsCount();
    }

    /**
     * Check if this list query has a next page
     */
    #[\Override]
    public function hasNextPage(): bool
    {
        return false;
    }

    /**
     * Check if this list query has a previous page
     */
    #[\Override]
    public function hasPreviousPage(): bool
    {
        return false;
    }
}
