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
 * **A response is sealed once constructed, and read-only thereafter
 * (RSRMID-2939).** There are no mutators here — `addColumn()`/`addRecord()` were
 * removed in v31 — because a column added after construction was silently absent
 * from every already-assembled record, and an added record changed four derived
 * pagination getters. Every column and record is built inside the constructor by
 * the brand's `populate()` hook. Do not re-add a mutator: it would reintroduce a
 * state a caller can observe only by having mutated it (guarded by
 * tests/ResponseSealSeamTest.php).
 *
 * **Records are iterated, not stepped.** This interface extends
 * {@see \IteratorAggregate}, so `foreach ($response as $record)` walks the rows
 * without touching shared state and can be repeated as often as a caller likes.
 * The former record cursor (`getCurrentRecord()`/`getNextRecord()`/
 * `getPreviousRecord()`/`rewindRecordList()`) was hidden mutable state shared by
 * every holder of the object: two consumers iterating one response interfered
 * with each other, the predicates that would have let a caller check the cursor
 * without moving it were `protected`, and nothing stated that re-iteration had to
 * be preceded by a rewind. Use `foreach`, or {@see self::getRecord()} for random
 * access.
 *
 * @extends \IteratorAggregate<int, RecordInterface>
 * @psalm-api
 * @package CNIC
 */
interface ResponseInterface extends \IteratorAggregate
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
     * Get column by column name, or null if the column does not exist
     */
    public function getColumn(string $columnName): ?ColumnInterface;

    /**
     * Get Data by Column Name and Index, or null if not found
     */
    public function getColumnIndex(string $columnName, int $recordIndex): mixed;

    /**
     * Get Column Names — the response's data columns, in wire order.
     *
     * Never includes the brand's response metadata (CNR: TOTAL, FIRST, LAST,
     * COUNT, LIMIT; IBS: transactid, status, message, code, the total_ prefixed
     * keys and domaincount). Those are not columns at all since RSRMID-2965 —
     * read them through {@see self::getPagination()} and the status accessors
     * instead of {@see self::getColumn()}/{@see self::getColumnIndex()}. The
     * former `getColumnKeys(true)` that stripped them is gone: with the column
     * set correct there is nothing left to strip.
     * @return string[] Array of Column Names
     */
    public function getColumnKeys(): array;

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
     * Get Index of first row in this response — the offset the current window
     * starts at, or `null` when the response carries no pagination metadata (a
     * non-list response).
     *
     * A pure read of the brand's own wire metadata since RSRMID-2965: it no
     * longer falls back to `0` because rows happen to be present, so "this is
     * page 1" and "this is not a list" are distinguishable answers.
     */
    public function getFirstRecordIndex(): ?int;

    /**
     * Get last record index of the current list query, or `null` when the
     * response carries no pagination metadata (a non-list response).
     *
     * Also a pure wire read since RSRMID-2965 — no `getRecordsCount() - 1`
     * fallback. Note it is an *offset into the whole result set*, not an index
     * into {@see self::getRecords()}: the window FIRST=100, LIMIT=10 answers
     * `109` here, while its last row is `getRecord(9)`. The two coincide on the
     * first page only.
     */
    public function getLastRecordIndex(): ?int;

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
     * Get Record at given index, or null if the index does not exist
     */
    public function getRecord(int $recordIndex): ?RecordInterface;

    /**
     * Get all Records
     * @return RecordInterface[] array of records
     */
    public function getRecords(): array;

    /**
     * Iterate the record list, keyed by record index.
     *
     * Redeclared here — {@see \IteratorAggregate} already requires it — so the
     * element type is stated on the contract consumers type against, rather than
     * only where it happens to be implemented. `foreach` is the supported way to
     * walk the rows: it holds its position in the loop rather than on the
     * response, so nothing a caller does while iterating is visible to another
     * holder of the same response, and a second `foreach` starts from the top
     * with no rewind step. See the class docblock for what this replaced.
     * @return \Traversable<int, RecordInterface>
     */
    #[\Override]
    public function getIterator(): \Traversable;

    /**
     * Get count of rows in this response
     */
    public function getRecordsCount(): int;

    /**
     * Get total count of records available for the list query, or `null` when
     * the response carries no TOTAL column (a non-list response)
     */
    public function getRecordsTotalCount(): ?int;

    /**
     * Get limit(ation) setting of the current list query — the count of
     * requested rows — or `null` when the response carries no LIMIT column.
     * `0` is a distinct, meaningful value (LIMIT=0 was requested); it no
     * longer collides with "absent"
     */
    public function getRecordsLimitation(): ?int;

    /**
     * Check if this list query has a next page.
     *
     * Answered from record offsets — `getLastRecordIndex() + 1 < getRecordsTotalCount()`
     * — not from page arithmetic, so it agrees with {@see getNextPageNumber()} even
     * when the current window is not aligned to a page boundary.
     *
     * That comparison assumes a window that actually holds rows. An empty one
     * reports a last index that is not a row index — CNR echoes
     * `LAST = FIRST` — so with a non-positive limit `LAST + 1 < TOTAL` can still
     * hold and the "next" offset walks back to the start of the list. Refuse a
     * non-positive limit before the arithmetic runs. `AbstractResponse` handles
     * this for every brand; an implementation writing its own must too.
     */
    public function hasNextPage(): bool;

    /**
     * Check if this list query has a previous page.
     *
     * Answered from record offsets — `getFirstRecordIndex() > 0` — not from page
     * arithmetic, so an unaligned window (e.g. FIRST=50, LIMIT=100) correctly
     * reports true instead of hiding behind a whole-page-number comparison.
     */
    public function hasPreviousPage(): bool;
}
