<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\IBS;

use CNIC\CNR\Response as CNRResponse;
use CNIC\ColumnInterface;
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
     * Regex for pagination related column keys
     * @var non-empty-string
     */
    protected string $paginationkeys = "/^(.+)?count|total(_.+)?$/"; // to be extended

    /**
     * Constructor
     * @param string $raw API plain response
     * @param array<string, string> $cmd API command used within this request
     * @param array{CONNECTION_URL?: string} $ph placeholder array to get vars in response description dynamically replaced
     * @param array<string,mixed> $context context data for the response (for use in custom loggers etc., optional, has no impact on SDK behaviour)
     */
    public function __construct(string $raw, array $cmd = [], array $ph = [], array $context = [])
    {
        if (isset($cmd["password"])) { // make password no longer accessible
            $cmd["password"] = "***";
        }

        $this->context = $context;
        $this->raw = RT::translate($raw, $cmd, $ph);
        $parsedHash = RP::parse($this->raw, $cmd);
        $this->hash = $parsedHash;
        $this->requestUrl = $ph["CONNECTION_URL"] ?? "";
        $this->command = $cmd;
        $this->columnkeys = [];
        $this->columns = [];
        $this->recordIndex = 0;
        $this->records = [];

        $colKeys = array_map("strval", array_keys($this->hash));
        $count = 0;
        foreach ($colKeys as $k) {
            $this->addColumn($k, is_array($this->hash[$k]) && array_is_list($this->hash[$k]) ? $this->hash[$k] : [$this->hash[$k]]);
            $col = $this->getColumn($k);
            if ($col instanceof ColumnInterface) {
                $count2 = count($col->getData());
                if ($count2 > $count) {
                    $count = $count2;
                }
            }
        }
        for ($i = 0; $i < $count; $i++) {
            $d = [];
            foreach ($colKeys as $k) {
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
     * Check if current API response represents an error case
     */
    #[\Override]
    public function isError(): bool
    {
        return ($this->getHashString("status") === "FAILURE");
    }

    /**
     * Check if current API response represents a success case
     */
    #[\Override]
    public function isSuccess(): bool
    {
        return ($this->getHashString("status") === "SUCCESS" || !$this->isError());
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
        foreach ($this->columnkeys as $k) {
            if ((bool)preg_match("/^(.+)?count|total(_.+)?$/", $k)) {
                return (is_numeric($this->hash[$k]) ? intval($this->hash[$k], 10) : 0) - 1;
            }
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
