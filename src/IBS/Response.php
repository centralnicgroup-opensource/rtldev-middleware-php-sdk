<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\IBS;

use CNIC\CNR\Column;
use CNIC\CNR\Record;
use CNIC\CNR\Response as CNRResponse;
use CNIC\CommandFormatter;
use CNIC\IBS\ResponseParser as RP;
use CNIC\IBS\ResponseTranslator as RT;

/**
 * IBS Response
 *
 * @psalm-api
 * @package CNIC\IBS
 */
class Response extends CNRResponse
{
    /**
     * Regex for pagination related column keys
     * @var non-empty-string
     */
    protected string $paginationkeys = "/^(.+)?count|total(_.+)?$/"; // to be extended

    /**
     * Context data for the response
     * @var array<string,mixed>
     */
    protected array $context;

    /**
     * Constructor
     * @param string $raw API plain response
     * @param array<string> $cmd API command used within this request
     * @param array<string> $ph placeholder array to get vars in response description dynamically replaced
     * @param array<string,mixed> $context context data for the response (for use in custom loggers etc., optional, has no impact on SDK behaviour)
     */
    public function __construct(string $raw, array $cmd = [], array $ph = [], array $context = [])
    {
        if (isset($cmd["password"])) { // make password no longer accessible
            $cmd["password"] = "***";
        }

        $this->context = $context;
        $this->raw = RT::translate($raw, $cmd, $ph);
        $this->hash = RP::parse($this->raw, $cmd);
        $this->requestUrl = $ph["CONNECTION_URL"] ?? "";
        $this->command = $cmd;
        $this->columnkeys = [];
        $this->columns = [];
        $this->recordIndex = 0;
        $this->records = [];

        $colKeys = array_map("strval", array_keys($this->hash));
        $count = 0;
        foreach ($colKeys as $k) {
            $this->addColumn($k, [ $this->hash[$k] ]);
            $col = $this->getColumn($k);
            if (!is_null($col)) {
                $count2 = $col->length;
                if ($count2 > $count) {
                    $count = $count2;
                }
            }
        }
        for ($i = 0; $i < $count; $i++) {
            $d = [];
            foreach ($colKeys as $k) {
                $col = $this->getColumn($k);
                if ($col instanceof Column) {
                    $v = $col->getDataByIndex($i);
                    if ($v !== null) {
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
            if (isset($this->hash[$key])) {
                return intval($this->hash[$key]);
            }
        }
        return 200; // default code
    }

    /**
     * Get API response code
     */
    public function getStatus(): string
    {
        return $this->hash["status"];
    }

    /**
     * Get API response description
     */
    #[\Override]
    public function getDescription(): string
    {
        return $this->hash["message"] ?? $this->hash["product_0_message"] ?? "Command completed successfully";
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
     * @throws \Exception
     */
    #[\Override]
    public function getQueuetime(): float
    {
        throw new \Exception("Not supported");
    }

    /**
     * Get API response as Hash
     * @return array<string,mixed>
     */
    #[\Override]
    public function getHash(): array
    {
        return $this->hash;
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
     * API response code is an 5xx code
     */
    #[\Override]
    public function isError(): bool
    {
        return ($this->hash["status"] === "FAILURE");
    }

    /**
     * Check if current API response represents a success case
     * API response code is an 2xx code
     */
    #[\Override]
    public function isSuccess(): bool
    {
        return $this->hash["status"] === "SUCCESS";
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
     * @param string[] $data array of column data
     * @return $this
     */
    #[\Override]
    public function addColumn(string $key, array $data): static
    {
        $col = new Column($key, $data);
        $this->columns[] = $col;
        $this->columnkeys[] = $key;
        return $this;
    }

    /**
     * Add a record to the record list
     * @param array<string,mixed> $h row hash data
     * @return $this
     */
    #[\Override]
    public function addRecord(array $h): static
    {
        $this->records[] = new Record($h);
        return $this;
    }

    /**
     * Get column by column name
     * @param string $key column name
     */
    #[\Override]
    public function getColumn(string $key): ?Column
    {
        return ($this->hasColumn($key) ? $this->columns[array_search($key, $this->columnkeys)] : null);
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
        return $col instanceof Column ? $col->getDataByIndex($index) : null;
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
     * @return Column[]
     */
    #[\Override]
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get Command used in this request
     * @return array<string>
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
        return 1;
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
        static $last = null;

        if (is_null($last)) {
            foreach ($this->columnkeys as $k) {
                if ((bool)preg_match("/^(.+)?count|total(_.+)?$/", $k)) {
                    $last = intval($this->hash[$k], 10) - 1;
                    return $last;
                }
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
        $pages = $this->getNumberOfPages();
        return ($page <= $pages ? $page : $pages);
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
            return (int)ceil($t / $this->getRecordsLimitation());
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

    /**
     * Reset index in record list back to zero
     * @return $this
     */
    #[\Override]
    public function rewindRecordList(): static
    {
        $this->recordIndex = 0;
        return $this;
    }

    /**
     * Check if column exists in response
     * @param string $key column name
     */
    private function hasColumn(string $key): bool
    {
        return in_array($key, $this->columnkeys);
    }

    /**
     * Check if the record list contains a record for the
     * current record index in use
     */
    private function hasCurrentRecord(): bool
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
    private function hasNextRecord(): bool
    {
        $next = $this->recordIndex + 1;
        return ($this->hasCurrentRecord() && ($next < $this->getRecordsCount()));
    }

    /**
     * Check if the record list contains a previous record for the
     * current record index in use
     */
    private function hasPreviousRecord(): bool
    {
        return ($this->recordIndex > 0 && $this->hasCurrentRecord());
    }
}
