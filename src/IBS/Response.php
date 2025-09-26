<?php

#declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\IBS;

use CNIC\IBS\ResponseParser as RP;
use CNIC\IBS\ResponseTranslator as RT;
use CNIC\CommandFormatter;
use CNIC\CNR\Column;
use CNIC\CNR\Record;

/**
 * IBS Response
 *
 * @package CNIC\IBS
 */
class Response extends \CNIC\CNR\Response // implements \CNIC\ResponseInterface
{
    /**
     * Regex for pagination related column keys
     * @var string
     */
    protected $paginationkeys = "/^(.+)?count|total(_.+)?$/"; // to be extended

    /**
     * API request url
     * @var string
     */
    protected $requestUrl = "";

    /**
     * Constructor
     * @param string $raw API plain response
     * @param array<string> $cmd API command used within this request
     * @param array<string> $ph placeholder array to get vars in response description dynamically replaced
     */
    public function __construct($raw, $cmd = [], $ph = [])
    {
        if (isset($cmd["password"])) { // make password no longer accessible
            $cmd["password"] = "***";
        }

        $this->raw = RT::translate($raw, $cmd, $ph);
        $this->hash = RP::parse($this->raw);
        $this->requestUrl = $ph["CONNECTION_URL"];
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

    /**
     * Get Request URL
     * @return string
     */
    public function getRequestURL()
    {
        return $this->requestUrl;
    }

    /**
     * Get API response code
     * @return int
     */
    public function getCode()
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
     * @return string
     */
    public function getStatus()
    {
        return $this->hash["status"];
    }

    /**
     * Get API response description
     * @return string
     */
    public function getDescription()
    {
        return $this->hash["message"] ?? $this->hash["product_0_message"] ?? "Command completed successfully";
    }

    /**
     * Get Plain API response
     * @return string
     */
    public function getPlain()
    {
        return $this->raw;
    }

    /**
     * Get Queuetime of API response
     * @throws \Exception
     */
    public function getQueuetime()
    {
        throw new \Exception("Not supported");
    }

    /**
     * Get API response as Hash
     * @return array<string,mixed>
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * Get Runtime of API response
     * @throws \Exception
     */
    public function getRuntime()
    {
        throw new \Exception("Not supported");
    }

    /**
     * Check if current API response represents an error case
     * API response code is an 5xx code
     * @return bool
     */
    public function isError()
    {
        return ($this->hash["status"] === "FAILURE");
    }

    /**
     * Check if current API response represents a success case
     * API response code is an 2xx code
     * @return bool
     */
    public function isSuccess()
    {
        return $this->hash["status"] === "SUCCESS";
    }

    /**
     * Check if current API response represents a temporary error case
     * @throws \Exception
     */
    public function isTmpError()
    {
        throw new \Exception("Not supported");
    }

    /**
     * Check if current operation is returned as pending
     * @throws \Exception
     */
    public function isPending()
    {
        throw new \Exception("Not supported");
    }

    /**
     * Add a column to the column list
     * @param string $key column name
     * @param string[] $data array of column data
     * @return $this
     */
    public function addColumn($key, $data)
    {
        $col = new Column($key, $data);
        $this->columns[] = $col;
        $this->columnkeys[] = $key;
        return $this;
    }

    /**
     * Add a record to the record list
     * @param array<string> $h row hash data
     * @return $this
     */
    public function addRecord($h)
    {
        $this->records[] = new Record($h);
        return $this;
    }

    /**
     * Get column by column name
     * @param string $key column name
     * @return Column|null
     */
    public function getColumn($key)
    {
        return ($this->hasColumn($key) ? $this->columns[array_search($key, $this->columnkeys)] : null);
    }

    /**
     * Get Data by Column Name and Index
     * @param string $colkey column name
     * @param int $index column data index
     * @return string|null
     */
    public function getColumnIndex($colkey, $index)
    {
        $col = $this->getColumn($colkey);
        return $col ? $col->getDataByIndex($index) : null;
    }

    /**
     * Get Column Names
     * @param bool $filterPaginationKeys strip pagination columns
     * @return string[]
     */
    public function getColumnKeys($filterPaginationKeys = false)
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
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Get Command used in this request
     * @return array<string>
     */
    public function getCommand()
    {
        return CommandFormatter::getSortedCommand($this->command);
    }

    /**
     * Get Command used in this request in plain text format
     * @return string
     */
    public function getCommandPlain()
    {
        return CommandFormatter::formatCommand($this->getCommand());
    }

    /**
     * Get Page Number of current List Query
     * @return int|null
     */
    public function getCurrentPageNumber()
    {
        return 1;
    }

    /**
     * Get Record of current record index
     * @return Record|null
     */
    public function getCurrentRecord()
    {
        return $this->hasCurrentRecord() ? $this->records[$this->recordIndex] : null;
    }

    /**
     * Get Index of first row in this response
     * @return int|null
     */
    public function getFirstRecordIndex()
    {
        return 0;
    }

    /**
     * Get last record index of the current list query
     * @return int|null
     */
    public function getLastRecordIndex()
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
    public function getListHash()
    {
        throw new \Exception("Not implemented.");
    }

    /**
     * Get next record in record list
     * @return Record|null
     */
    public function getNextRecord()
    {
        if ($this->hasNextRecord()) {
            return $this->records[++$this->recordIndex];
        }
        return null;
    }

    /**
     * Get Page Number of next list query
     * @return int|null
     */
    public function getNextPageNumber()
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
     * @return int
     */
    public function getNumberOfPages()
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
    public function getPagination()
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
     * @return int|null
     */
    public function getPreviousPageNumber()
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
     * @return Record|null
     */
    public function getPreviousRecord()
    {
        if ($this->hasPreviousRecord()) {
            return $this->records[--$this->recordIndex];
        }
        return null;
    }

    /**
     * Get Record at given index
     * @param int $idx record index
     * @return Record|null
     */
    public function getRecord($idx)
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
    public function getRecords()
    {
        return $this->records;
    }

    /**
     * Get count of rows in this response
     * @return int
     */
    public function getRecordsCount()
    {
        return count($this->records);
    }

    /**
     * Get total count of records available for the list query
     * @return int
     */
    public function getRecordsTotalCount()
    {
        return $this->getRecordsCount();
    }

    /**
     * Get limit(ation) setting of the current list query
     * This is the count of requested rows
     * @return int
     */
    public function getRecordsLimitation()
    {
        return $this->getRecordsCount();
    }

    /**
     * Check if this list query has a next page
     * @return bool
     */
    public function hasNextPage()
    {
        return false;
    }

    /**
     * Check if this list query has a previous page
     * @return bool
     */
    public function hasPreviousPage()
    {
        return false;
    }

    /**
     * Reset index in record list back to zero
     * @return $this
     */
    public function rewindRecordList()
    {
        $this->recordIndex = 0;
        return $this;
    }

    /**
     * Check if column exists in response
     * @param string $key column name
     * @return bool
     */
    private function hasColumn($key)
    {
        return (array_search($key, $this->columnkeys) !== false);
    }

    /**
     * Check if the record list contains a record for the
     * current record index in use
     * @return bool
     */
    private function hasCurrentRecord()
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
     * @return bool
     */
    private function hasNextRecord()
    {
        $next = $this->recordIndex + 1;
        return ($this->hasCurrentRecord() && ($next < $this->getRecordsCount()));
    }

    /**
     * Check if the record list contains a previous record for the
     * current record index in use
     * @return bool
     */
    private function hasPreviousRecord()
    {
        return ($this->recordIndex > 0 && $this->hasCurrentRecord());
    }
}
