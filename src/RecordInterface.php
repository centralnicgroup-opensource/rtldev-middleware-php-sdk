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
 * Declares what a record can be *asked*, not how it is built. The
 * __construct(array $data) declaration this carried until RSRMID-2923 is
 * deliberately gone, for the same reason as {@see ColumnInterface} and
 * {@see ResponseInterface}: records are built by each Response's newRecord()
 * hook, which names its concrete class, so nothing constructs through this type
 * and the declaration only limited implementers to a plain array-backed row.
 * Do not re-add it — guarded by tests/RecordColumnSeamTest.php.
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
     * Get row data for given column
     *
     * @param string $key column name
     * @return mixed row data for given column or null if column does not exist
     */
    public function getDataByKey(string $key): mixed;

    /**
     * Check if record has data for given column
     *
     * @param string $key column name
     * @return bool boolean result
     */
    //public function hasData(string $key): bool;
}
