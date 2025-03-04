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
 * @method int getCode() Get API response code
 * @method string getDescription() Get API response description
 * @method string getPlain() Get Plain API response
 * @method float getQueuetime() Get Queuetime of API response
 * @method array<string> getHash() Get API response as Hash
 * @method float getRuntime() Get Runtime of API response
 * @method bool isError() Check if current API response represents an error case
 * @method bool isSuccess() Check if current API response represents a success case
 * @method bool isTmpError() Check if current API response represents a temporary error case
 * @method bool isPending() Check if current operation is returned as pending
 * @method ResponseInterface addColumn(string $key, array<string> $data) Add a column to the column list
 * @method ResponseInterface addRecord(array<string> $h) Add a record to the record list
 * @method ColumnInterface|null getColumn(string $key) Get column by column name
 * @method string|null getColumnIndex(string $colkey, int $index) Get Data by Column Name and Index
 * @method array<string> getColumnKeys() Get Column Names
 * @method ColumnInterface[] getColumns() Get List of Columns
 * @method array<string> getCommand() Get Command used in this request
 * @method string getCommandPlain() Get Command used in this request in plain text format
 * @method int|null getCurrentPageNumber() Get Page Number of current List Query
 * @method RecordInterface|null getCurrentRecord() Get Record of current record index
 * @method int|null getFirstRecordIndex() Get Index of first row in this response
 * @method int|null getLastRecordIndex() Get last record index of the current list query
 * @method array<string> getListHash() Get Response as List Hash including useful meta data for tables
 * @method RecordInterface|null getNextRecord() Get next record in record list
 * @method int|null getNextPageNumber() Get Page Number of next list query
 * @method int getNumberOfPages() Get the number of pages available for this list query
 * @method array<string> getPagination() Get object containing all paging data
 * @method int|null getPreviousPageNumber() Get Page Number of previous list query
 * @method RecordInterface|null getPreviousRecord() Get previous record in record list
 * @method RecordInterface|null getRecord(int $idx) Get Record at given index
 * @method RecordInterface[] getRecords() Get all Records
 * @method int getRecordsCount() Get count of rows in this response
 * @method int getRecordsTotalCount() Get total count of records available for the list query
 * @method int getRecordsLimitation() Get limit(ation) setting of the current list query
 * @method bool hasNextPage() Check if this list query has a next page
 * @method bool hasPreviousPage() Check if this list query has a previous page
 * @method ResponseInterface rewindRecordList() Reset index in record list back to zero
 */
interface ResponseInterface
{
    /**
     * Constructor
     * @param string $raw API plain response
     * @param array<string> $cmd API command used within this request
     * @param array<string> $ph placeholder array to get vars in response description dynamically replaced
     */
    public function __construct(string $raw, array $cmd, array $ph = []);

    /**
     * Get API response code
     * @return int API response code
     */
    public function getCode(): int;

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
     * @return array<string> API response hash
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
     * @param array<string> $data array of column data
     * @return ResponseInterface
     */
    public function addColumn(string $key, array $data): ResponseInterface;

    /**
     * Add a record to the record list
     * @param array<string> $h row hash data
     * @return ResponseInterface
     */
    public function addRecord(array $h): ResponseInterface;

    /**
     * Get column by column name
     * @param string $key column name
     * @return ColumnInterface|null column instance or null if column does not exist
     */
    public function getColumn(string $key): ?ColumnInterface;

    /**
     * Get Data by Column Name and Index
     * @param string $colkey column name
     * @param int $index column data index
     * @return string|null column data at index or null if not found
     */
    public function getColumnIndex(string $colkey, int $index): ?string;

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
     * @return array<string> command
     */
    public function getCommand(): array;

    /**
     * Get Command used in this request in plain text format
     * @return string command
     */
    public function getCommandPlain(): string;

    /**
     * Get Page Number of current List Query
     * @return int|null page number or null in case of a non-list response
     */
    public function getCurrentPageNumber(): ?int;

    /**
     * Get Record of current record index
     * @return RecordInterface|null Record or null in case of a non-list response
     */
    public function getCurrentRecord(): ?RecordInterface;

    /**
     * Get Index of first row in this response
     * @return int|null first row index
     */
    public function getFirstRecordIndex(): ?int;

    /**
     * Get last record index of the current list query
     * @return int|null record index or null for a non-list response
     */
    public function getLastRecordIndex(): ?int;

    /**
     * Get Response as List Hash including useful meta data for tables
     * @return array<string> hash including list meta data and array of rows in hash notation
     */
    public function getListHash(): array;

    /**
     * Get next record in record list
     * @return RecordInterface|null Record or null in case there's no further record
     */
    public function getNextRecord(): ?RecordInterface;

    /**
     * Get Page Number of next list query
     * @return int|null page number or null if there's no next page
     */
    public function getNextPageNumber(): ?int;

    /**
     * Get the number of pages available for this list query
     * @return int number of pages
     */
    public function getNumberOfPages(): int;

    /**
     * Get object containing all paging data
     * @return array<string> paginator data
     */
    public function getPagination(): array;

    /**
     * Get Page Number of previous list query
     * @return int|null page number or null if there's no previous page
     */
    public function getPreviousPageNumber(): ?int;

    /**
     * Get previous record in record list
     * @return RecordInterface|null Record or null if there's no previous record
     */
    public function getPreviousRecord(): ?RecordInterface;

    /**
     * Get Record at given index
     * @param int $idx record index
     * @return RecordInterface|null Record or null if index does not exist
     */
    public function getRecord(int $idx): ?RecordInterface;

    /**
     * Get all Records
     * @return RecordInterface[] array of records
     */
    public function getRecords();

    /**
     * Get count of rows in this response
     * @return int count of rows
     */
    public function getRecordsCount(): int;

    /**
     * Get total count of records available for the list query
     * @return int total count of records or count of records for a non-list response
     */
    public function getRecordsTotalCount(): int;

    /**
     * Get limit(ation) setting of the current list query
     * This is the count of requested rows
     * @return int limit setting or count requested rows
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
     * @return ResponseInterface
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
