<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Common Response Interface
 *
 * The universal contract every brand Response fully supports. It describes what a
 * response can be asked, not how it is built: construction is deliberately NOT
 * part of this interface, and must not be re-added. Responses are created by the
 * brand factory hooks (AbstractClient::newResponse() and
 * AbstractResponseTemplateManager::createResponse()), each instantiating its own
 * concrete Response, so nothing in the SDK — or in a consumer — ever constructs
 * through this type. Put construction concerns on the factory hooks instead.
 * Rationale: the interface-declaration entry in docs/agents/architecture.md.
 *
 * @psalm-api
 * @package CNIC
 */
interface ResponseInterface
{
    /**
     * Get API response code
     */
    public function getCode(): int;

    /**
     * Get API response description
     */
    public function getDescription(): string;

    /**
     * Get Request URL
     */
    public function getRequestURL(): string;

    /**
     * Get Plain API response
     */
    public function getPlain(): string;

    /**
     * Get API response as Hash
     * @return array<string, mixed> API response hash
     */
    public function getHash(): array;

    /**
     * Check if current API response represents an error case (a 5xx code)
     */
    public function isError(): bool;

    /**
     * Check if current API response represents a success case (a 2xx code)
     */
    public function isSuccess(): bool;

    /**
     * Add a column to the column list
     * @param array<array-key, mixed> $data array of column data
     */
    public function addColumn(string $columnName, array $data): ResponseInterface;

    /**
     * Add a record to the record list
     * @param array<string, mixed> $row
     */
    public function addRecord(array $row): ResponseInterface;

    /**
     * Get column by column name, or null if the column does not exist
     */
    public function getColumn(string $columnName): ?ColumnInterface;

    /**
     * Get Data by Column Name and Index, or null if not found
     */
    public function getColumnIndex(string $columnName, int $recordIndex): mixed;

    /**
     * Get Column Names
     *
     * Pass true to strip the pagination/metadata columns the brand's list
     * endpoints emit alongside the real data (CNR: TOTAL, FIRST, LAST, COUNT,
     * LIMIT; IBS: the total_ prefixed keys and domaincount), leaving only the
     * columns a caller wants to render. Which keys count as pagination is
     * brand-specific — see the $paginationKeys property on each brand's Response.
     *
     * The parameter is declared here, not only on the implementation, because
     * consumers are expected to type against this interface: an interface
     * declaration narrower than the implementation makes the capability
     * unreachable to exactly those consumers. Keep the two in step.
     * @return string[] Array of Column Names
     */
    public function getColumnKeys(bool $filterPaginationKeys = false): array;

    /**
     * Get List of Columns
     * @return ColumnInterface[] Array of Columns
     */
    public function getColumns(): array;

    /**
     * Get Command used in this request
     * @return array<string, string> command
     */
    public function getCommand(): array;

    /**
     * Get Command used in this request in plain text format
     */
    public function getCommandPlain(): string;

    /**
     * Get context data for the response
     * @return array<string,mixed> context data
     */
    public function getContext(): array;

    /**
     * Get Page Number of current List Query, or null for a non-list response
     */
    public function getCurrentPageNumber(): ?int;

    /**
     * Get Record of current record index, or null for a non-list response
     */
    public function getCurrentRecord(): ?RecordInterface;

    /**
     * Get Index of first row in this response
     */
    public function getFirstRecordIndex(): ?int;

    /**
     * Get last record index of the current list query, or null for a non-list
     * response
     */
    public function getLastRecordIndex(): ?int;

    /**
     * Get next record in record list, or null if there is no further record
     */
    public function getNextRecord(): ?RecordInterface;

    /**
     * Get Page Number of next list query, or null if there is no next page
     */
    public function getNextPageNumber(): ?int;

    /**
     * Get the number of pages available for this list query
     */
    public function getNumberOfPages(): int;

    /**
     * Get object containing all paging data
     * @return array<string, int|null> paginator data
     */
    public function getPagination(): array;

    /**
     * Get Page Number of previous list query, or null if there is no previous page
     */
    public function getPreviousPageNumber(): ?int;

    /**
     * Get previous record in record list, or null if there is no previous record
     */
    public function getPreviousRecord(): ?RecordInterface;

    /**
     * Get Record at given index, or null if the index does not exist
     */
    public function getRecord(int $recordIndex): ?RecordInterface;

    /**
     * Get all Records
     * @return RecordInterface[] array of records
     */
    public function getRecords(): array;

    /**
     * Get count of rows in this response
     */
    public function getRecordsCount(): int;

    /**
     * Get total count of records available for the list query, or the count of
     * records for a non-list response
     */
    public function getRecordsTotalCount(): int;

    /**
     * Get limit(ation) setting of the current list query — the count of
     * requested rows
     */
    public function getRecordsLimitation(): int;

    /**
     * Check if this list query has a next page
     */
    public function hasNextPage(): bool;

    /**
     * Check if this list query has a previous page
     */
    public function hasPreviousPage(): bool;

    /**
     * Reset index in record list back to zero
     */
    public function rewindRecordList(): ResponseInterface;
}
