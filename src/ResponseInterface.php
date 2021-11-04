<?php

#declare(strict_types=1);

/**
 * CNIC
 * Copyright © CentralNic Group PLC
 */

namespace CNIC;

/**
 * Common Response Interface
 *
 * @package CNIC
 */
interface ResponseInterface
{

    /**
     * Constructor
     * @param string $raw API plain response
     * @param array $cmd API command used within this request
     * @param array $ph placeholder array to get vars in response description dynamically replaced
     */
    public function __construct(string $raw, array $cmd = null, array $ph = []);

    /**
     * Get API response code
     * @return integer API response code
     */
    public function getCode() : int;

    /**
     * Get API response description
     * @return string API response description
     */
    public function getDescription(): string;

    /**
     * Get Plain API response
     * @return string Plain API response
     */
    public function getPlain(): string;

    /**
     * Get Queuetime of API response
     * @return float Queuetime of API response
     */
    public function getQueuetime(): float;

    /**
     * Get API response as Hash
     * @return array API response hash
     */
    public function getHash(): array;

    /**
     * Get Runtime of API response
     * @return float Runtime of API response
     */
    public function getRuntime(): float;

    /**
     * Check if current API response represents an error case
     * API response code is an 5xx code
     * @return bool boolean result
     */
    public function isError(): bool;

    /**
     * Check if current API response represents a success case
     * API response code is an 2xx code
     * @return bool boolean result
     */
    public function isSuccess(): bool;

    /**
     * Check if current API response represents a temporary error case
     * API response code is an 4xx code
     * @return bool result
     */
    public function isTmpError(): bool;

    /**
     * Check if current operation is returned as pending
     * @return bool result
     */
    public function isPending(): bool;

    /**
     * Add a column to the column list
     * @param string $key column name
     * @param string[] $data array of column data
     * @return ResponseInterface
     */
    public function addColumn($key, $data): ResponseInterface;

    /**
     * Add a record to the record list
     * @param array $h row hash data
     * @return ResponseInterface
     */
    public function addRecord($h): ResponseInterface;

    /**
     * Get column by column name
     * @param string $key column name
     * @return ColumnInterface|null column instance or null if column does not exist
     */
    public function getColumn($key): ?ColumnInterface;

    /**
     * Get Data by Column Name and Index
     * @param string $colkey column name
     * @param integer $index column data index
     * @return string|null column data at index or null if not found
     */
    public function getColumnIndex($colkey, $index): ?string;

    /**
     * Get Column Names
     * @return string[] Array of Column Names
     */
    public function getColumnKeys(): array;

    /**
     * Get List of Columns
     * @return ColumnInterface[] Array of Columns
     */
    public function getColumns();

    /**
     * Get Command used in this request
     * @return array command
     */
    public function getCommand(): array;

    /**
     * Get Command used in this request in plain text format
     * @return string command
     */
    public function getCommandPlain(): string;

    /**
     * Get Page Number of current List Query
     * @return integer|null page number or null in case of a non-list response
     */
    public function getCurrentPageNumber(): ?int;

    /**
     * Get Record of current record index
     * @return RecordInterface|null Record or null in case of a non-list response
     */
    public function getCurrentRecord();

    /**
     * Get Index of first row in this response
     * @return integer|null first row index
     */
    public function getFirstRecordIndex(): ?int;

    /**
     * Get last record index of the current list query
     * @return integer|null record index or null for a non-list response
     */
    public function getLastRecordIndex(): ?int;

    /**
     * Get Response as List Hash including useful meta data for tables
     * @return array hash including list meta data and array of rows in hash notation
     */
    public function getListHash(): array;

    /**
     * Get next record in record list
     * @return RecordInterface|null Record or null in case there's no further record
     */
    public function getNextRecord();

    /**
     * Get Page Number of next list query
     * @return integer|null page number or null if there's no next page
     */
    public function getNextPageNumber(): ?int;

    /**
     * Get the number of pages available for this list query
     * @return integer number of pages
     */
    public function getNumberOfPages(): int;

    /**
     * Get object containing all paging data
     * @return array paginator data
     */
    public function getPagination(): array;

    /**
     * Get Page Number of previous list query
     * @return integer|null page number or null if there's no previous page
     */
    public function getPreviousPageNumber(): ?int;

    /**
     * Get previous record in record list
     * @return RecordInterface|null Record or null if there's no previous record
     */
    public function getPreviousRecord(): ?RecordInterface;

    /**
     * Get Record at given index
     * @param integer $idx record index
     * @return RecordInterface|null Record or null if index does not exist
     */
    public function getRecord($idx): ?RecordInterface;

    /**
     * Get all Records
     * @return RecordInterface[] array of records
     */
    public function getRecords();

    /**
     * Get count of rows in this response
     * @return integer count of rows
     */
    public function getRecordsCount(): int;

    /**
     * Get total count of records available for the list query
     * @return integer total count of records or count of records for a non-list response
     */
    public function getRecordsTotalCount(): int;

    /**
     * Get limit(ation) setting of the current list query
     * This is the count of requested rows
     * @return integer limit setting or count requested rows
     */
    public function getRecordsLimitation(): int;

    /**
     * Check if this list query has a next page
     * @return bool boolean result
     */
    public function hasNextPage(): bool;

    /**
     * Check if this list query has a previous page
     * @return bool boolean result
     */
    public function hasPreviousPage(): bool;

    /**
     * Reset index in record list back to zero
     * @return ResponseInterface;
     */
    public function rewindRecordList(): ResponseInterface;

    /**
     * Check if column exists in response
     * @param string $key column name
     * @return bool boolean result
     */
    //private function hasColumn($key): bool;

    /**
     * Check if the record list contains a record for the
     * current record index in use
     * @return bool boolean result
     */
    //private function hasCurrentRecord(): bool;

    /**
     * Check if the record list contains a next record for the
     * current record index in use
     * @return bool boolean result
     */
    //private function hasNextRecord(): bool;

    /**
     * Check if the record list contains a previous record for the
     * current record index in use
     * @return bool boolean result
     */
    //private function hasPreviousRecord(): bool;
}
