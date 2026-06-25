<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\CNR\Response as CNRResponse;
use CNIC\IBS\Column as IBSColumn;
use CNIC\IBS\ResponseParser as RP;
use CNIC\IBS\ResponseTranslator as RT;
use CNIC\ResponseInterface;

/**
 * IBS Response
 *
 * Extends CNR\Response and only overrides what genuinely differs for the IBS
 * platform: the JSON-shaped response parsing (constructor), the status/code/
 * description accessors, the IBS column type, the not-supported contract
 * methods and the flat (single-page) pagination model. Every other accessor
 * and the record-cursor navigation are inherited unchanged from CNR\Response.
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
        $this->raw = RT::translate($raw, $cmd, $ph);
        $this->hash = RP::parse($this->raw, $cmd);

        // IBS responses are flat key => value maps; each hash entry becomes a
        // column. List values are kept as-is, anything else is wrapped into a
        // single-cell list so the shared record assembly can iterate them.
        $colKeys = array_map("strval", array_keys($this->hash));
        foreach ($colKeys as $k) {
            $this->addColumn($k, is_array($this->hash[$k]) && array_is_list($this->hash[$k]) ? $this->hash[$k] : [$this->hash[$k]]);
        }
        $this->assembleRecords();
    }

    /**
     * Get API response code
     */
    #[\Override]
    public function getCode(): int
    {
        foreach (["code", "product_0_code"] as $key) {
            if (isset($this->hash[$key]) && is_numeric($this->hash[$key])) {
                return intval($this->hash[$key]);
            }
        }
        return 200; // default code
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
        return $this->getHashString("message")
            ?: $this->getHashString("product_0_message")
            ?: "Command completed successfully";
    }

    /**
     * Get Queuetime of API response
     * @throws \Exception
     */
    #[\Override]
    public function getQueuetime(): float
    {
        throw new \Exception("Not supported");
    }

    /**
     * Get Runtime of API response
     * @throws \Exception
     */
    #[\Override]
    public function getRuntime(): float
    {
        throw new \Exception("Not supported");
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
     * @throws \Exception
     */
    #[\Override]
    public function isTmpError(): bool
    {
        throw new \Exception("Not supported");
    }

    /**
     * Check if current operation is returned as pending
     * @throws \Exception
     */
    #[\Override]
    public function isPending(): bool
    {
        throw new \Exception("Not supported");
    }

    /**
     * Add a column to the column list
     * @param string $key column name
     * @param array<array-key, mixed> $data array of column data
     * @return $this
     */
    #[\Override]
    public function addColumn(string $key, array $data): static
    {
        $col = new IBSColumn($key, $data);
        $this->columns[] = $col;
        $this->columnkeys[] = $key;
        $this->columnindex[$key] ??= count($this->columns) - 1;
        return $this;
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
     * @throws \Exception
     */
    #[\Override]
    public function getListHash(): array
    {
        throw new \Exception("Not implemented.");
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
