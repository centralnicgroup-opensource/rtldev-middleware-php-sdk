<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Common Record Interface
 *
 * Declares what a record can be *asked*, not how it is built. Do not re-add a
 * __construct() declaration, for the same reason as {@see ColumnInterface}:
 * records come from each Response's newRecord() hook, which names its concrete
 * class, so the declaration only limited implementers to a plain array-backed
 * row. Guarded by tests/RecordColumnSeamTest.php.
 *
 * @psalm-api
 * @package CNIC
 */
interface RecordInterface
{
    /**
     * Get row data
     *
     * @return array<string, mixed> row data
     */
    public function getData(): array;

    /**
     * Get row data for given column, or null if the column does not exist
     */
    public function getDataByKey(string $key): mixed;

    /**
     * Get row data for given column, parsed as a date/time value.
     *
     * Returns `null` for a missing key, a non-string value, or a string that
     * cannot be parsed — see {@see ApiDateTime::tryFrom()}.
     */
    public function getDateTimeByKey(string $key): ?ApiDateTime;
}
