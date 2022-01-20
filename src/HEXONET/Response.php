<?php

#declare(strict_types=1);

/**
 * CNIC\HEXONET
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\HEXONET;

use CNIC\HEXONET\ResponseParser as RP;
use CNIC\HEXONET\ResponseTranslator as RT;

/**
 * HEXONET Response
 *
 * @package CNIC\HEXONET
 */
class Response implements \CNIC\ResponseInterface
{
    /**
     * The API Command used within this request
     * @var array
     */
    private $command;

     /**
     * plain API response
     * @var string
     */
    private $raw;

    /**
     * hash representation of plain API response
     * @var array
     */
    private $hash;

    /**
     * Column names available in this responsse
     * NOTE: this includes also FIRST, LAST, LIMIT, COUNT, TOTAL
     * and maybe further specific columns in case of a list query
     * @var string[]
     */
    private $columnkeys;
    /**
     * Container of Column Instances
     * @var Column[]
     */
    private $columns;
    /**
     * Record Index we currently point to in record list
     * @var int
     */
    private $recordIndex;
    /**
     * Record List (List of rows)
     * @var Record[]
     */
    private $records;

    /**
     * Constructor
     * @param string $raw API plain response
     * @param array $cmd API command used within this request
     * @param array $ph placeholder array to get vars in response description dynamically replaced
     */
    public function __construct($raw, $cmd = null, $ph = [])
    {
        if (isset($cmd["PASSWORD"])) { // make password no longer accessible
            $cmd["PASSWORD"] = "***";
        }

        $this->raw = RT::translate($raw, $cmd, $ph);
        $this->hash = RP::parse($this->raw);
        $this->command = $cmd;
        $this->columnkeys = [];
        $this->columns = [];
        $this->recordIndex = 0;
        $this->records = [];

        if (array_key_exists("PROPERTY", $this->hash)) {
            $colKeys = array_keys($this->hash["PROPERTY"]);
            $count = 0;
            foreach ($colKeys as $k) {
                $this->addColumn($k, $this->hash["PROPERTY"][$k]);
                $count2 = $this->getColumn($k)->length;
                if ($count2 > $count) {
                    $count = $count2;
                }
            }
            for ($i = 0; $i < $count; $i++) {
                $d = [];
                foreach ($colKeys as $k) {
                    $col = $this->getColumn($k);
                    if ($col) {
                        $v = $col->getDataByIndex($i);
                        if ($v !== null) {
                            $d[$k] = $v;
                        }
                    }
                }
                $this->addRecord($d);
            }
        }
    }

    /**
     * Get API response code
     * @return int API response code
     */
    public function getCode(): int
    {
        return intval($this->hash["CODE"], 10);
    }

    /**
     * Get API response description
     * @return string API response description
     */
    public function getDescription(): string
    {
        return $this->hash["DESCRIPTION"];
    }

    /**
     * Get Plain API response
     * @return string Plain API response
     */
    public function getPlain(): string
    {
        return $this->raw;
    }

    /**
     * Get Queuetime of API response
     * @return float Queuetime of API response
     */
    public function getQueuetime(): float
    {
        if (array_key_exists("QUEUETIME", $this->hash)) {
            return floatval($this->hash["QUEUETIME"]);
        }
        return 0.00;
    }

    /**
     * Get API response as Hash
     * @return array API response hash
     */
    public function getHash(): array
    {
        return $this->hash;
    }

    /**
     * Get Runtime of API response
     * @return float Runtime of API response
     */
    public function getRuntime(): float
    {
        if (array_key_exists("RUNTIME", $this->hash)) {
            return floatval($this->hash["RUNTIME"]);
        }
        return 0.00;
    }

    /**
     * Check if current API response represents an error case
     * API response code is an 5xx code
     * @return bool boolean result
     */
    public function isError(): bool
    {
        return substr($this->hash["CODE"], 0, 1) === "5";
    }

    /**
     * Check if current API response represents a success case
     * API response code is an 2xx code
     * @return bool boolean result
     */
    public function isSuccess(): bool
    {
        return substr($this->hash["CODE"], 0, 1) === "2";
    }

    /**
     * Check if current API response represents a temporary error case
     * API response code is an 4xx code
     * @return bool boolean result
     */
    public function isTmpError(): bool
    {
        return substr($this->hash["CODE"], 0, 1) === "4";
    }

    /**
     * Check if current operation is returned as pending
     * @return bool bool result
     */
    public function isPending(): bool
    {
        return isset($this->hash["PENDING"]) ? $this->hash["PENDING"] === "1" : false;
    }

    /**
     * Add a column to the column list
     * @param string $key column name
     * @param string[] $data array of column data
     * @return $this
     */
    public function addColumn($key, $data): self
    {
        $col = new Column($key, $data);
        $this->columns[] = $col;
        $this->columnkeys[] = $key;
        return $this;
    }

    /**
     * Add a record to the record list
     * @param array $h row hash data
     * @return $this
     */
    public function addRecord($h): self
    {
        $this->records[] = new Record($h);
        return $this;
    }

    /**
     * Get column by column name
     * @param string $key column name
     * @return Column|null column instance or null if column does not exist
     */
    public function getColumn($key): ?Column
    {
        return ($this->hasColumn($key) ? $this->columns[array_search($key, $this->columnkeys)] : null);
    }

    /**
     * Get Data by Column Name and Index
     * @param string $colkey column name
     * @param int $index column data index
     * @return string|null column data at index or null if not found
     */
    public function getColumnIndex($colkey, $index): ?string
    {
        $col = $this->getColumn($colkey);
        return $col ? $col->getDataByIndex($index) : null;
    }

    /**
     * Get Column Names
     * @return string[] Array of Column Names
     */
    public function getColumnKeys(): array
    {
        return $this->columnkeys;
    }

    /**
     * Get List of Columns
     * @return Column[] Array of Columns
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Get Command used in this request
     * @return array command
     */
    public function getCommand(): array
    {
        return $this->command;
    }

    /**
     * Get Command used in this request in plain text format
     * @return string command
     */
    public function getCommandPlain(): string
    {
        $tmp = "";
        foreach ($this->command as $key => $val) {
            $tmp .= "$key = $val\n";
        }
        return $tmp;
    }

    /**
     * Get Page Number of current List Query
     * @return int|null page number or null in case of a non-list response
     */
    public function getCurrentPageNumber(): ?int
    {
        $first = $this->getFirstRecordIndex();
        $limit = $this->getRecordsLimitation();
        if ($first !== null && $limit) {
            return (int)(floor($first / $limit) + 1);
        }
        return null;
    }

    /**
     * Get Record of current record index
     * @return Record|null Record or null in case of a non-list response
     */
    public function getCurrentRecord(): ?Record
    {
        return $this->hasCurrentRecord() ? $this->records[$this->recordIndex] : null;
    }

    /**
     * Get Index of first row in this response
     * @return int|null first row index
     */
    public function getFirstRecordIndex(): ?int
    {
        $col = $this->getColumn("FIRST");
        if ($col) {
            $f = $col->getDataByIndex(0);
            return $f === null ? 0 : intval($f, 10);
        }
        if ($this->getRecordsCount()) {
            return 0;
        }
        return null;
    }

    /**
     * Get last record index of the current list query
     * @return int|null record index or null for a non-list response
     */
    public function getLastRecordIndex(): ?int
    {
        $col = $this->getColumn("LAST");
        if ($col) {
            $l = $col->getDataByIndex(0);
            if ($l !== null) {
                return intval($l, 10);
            }
        }
        $c = $this->getRecordsCount();
        if ($c) {
            return $c - 1;
        }
        return null;
    }

    /**
     * Get Response as List Hash including useful meta data for tables
     * @return array hash including list meta data and array of rows in hash notation
     */
    public function getListHash(): array
    {
        $lh = [];
        foreach ($this->records as $rec) {
            $lh[] = $rec->getData();
        }
        return [
            "LIST" => $lh,
            "meta" => [
                "columns" => $this->getColumnKeys(),
                "pg" => $this->getPagination()
            ]
        ];
    }

    /**
     * Get next record in record list
     * @return Record|null Record or null in case there's no further record
     */
    public function getNextRecord(): ?Record
    {
        if ($this->hasNextRecord()) {
            return $this->records[++$this->recordIndex];
        }
        return null;
    }

    /**
     * Get Page Number of next list query
     * @return int|null page number or null if there's no next page
     */
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
     * @return int number of pages
     */
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
     * @return array paginator data
     */
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
     * @return int|null page number or null if there's no previous page
     */
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
     * @return Record|null Record or null if there's no previous record
     */
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
     * @return Record|null Record or null if index does not exist
     */
    public function getRecord($idx): ?Record
    {
        if ($idx >= 0 && $this->getRecordsCount() > $idx) {
            return $this->records[$idx];
        }
        return null;
    }

    /**
     * Get all Records
     * @return Record[] array of records
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * Get count of rows in this response
     * @return int count of rows
     */
    public function getRecordsCount(): int
    {
        return count($this->records);
    }

    /**
     * Get total count of records available for the list query
     * @return int total count of records or count of records for a non-list response
     */
    public function getRecordsTotalCount(): int
    {
        $col = $this->getColumn("TOTAL");
        if ($col) {
            $t = $col->getDataByIndex(0);
            if ($t !== null) {
                return intval($t, 10);
            }
        }
        return $this->getRecordsCount();
    }

    /**
     * Get limit(ation) setting of the current list query
     * This is the count of requested rows
     * @return int limit setting or count requested rows
     */
    public function getRecordsLimitation(): int
    {
        $col = $this->getColumn("LIMIT");
        if ($col) {
            $l = $col->getDataByIndex(0);
            if ($l !== null) {
                return intval($l, 10);
            }
        }
        return $this->getRecordsCount();
    }

    /**
     * Check if this list query has a next page
     * @return bool boolean result
     */
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
     * @return bool boolean result
     */
    public function hasPreviousPage(): bool
    {
        $cp = $this->getCurrentPageNumber();
        if ($cp === null) {
            return false;
        }
        return (($cp - 1) > 0);
    }

    /**
     * Reset index in record list back to zero
     * @return $this
     */
    public function rewindRecordList(): self
    {
        $this->recordIndex = 0;
        return $this;
    }

    /**
     * Check if column exists in response
     * @param string $key column name
     * @return bool boolean result
     */
    private function hasColumn($key): bool
    {
        return (array_search($key, $this->columnkeys) !== false);
    }

    /**
     * Check if the record list contains a record for the
     * current record index in use
     * @return bool boolean result
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
     * @return bool boolean result
     */
    private function hasNextRecord(): bool
    {
        $next = $this->recordIndex + 1;
        return ($this->hasCurrentRecord() && ($next < $this->getRecordsCount()));
    }

    /**
     * Check if the record list contains a previous record for the
     * current record index in use
     * @return bool boolean result
     */
    private function hasPreviousRecord(): bool
    {
        return ($this->recordIndex > 0 && $this->hasCurrentRecord());
    }
}
