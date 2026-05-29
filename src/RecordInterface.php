<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © CentralNic Group PLC
 */

namespace CNIC;

/**
 * Common Record Interface
 *
 * @psalm-api
 * @package CNIC
 */
interface RecordInterface
{
    /**
     * Constructor
     * e.g.
     * <code>
     * $data = [
     *   "DOMAIN" => "mydomain.com",
     *   "USER"   => "test.user",
     *   // ... further column data ...
     * ];
     * </code>
     * @param array<string, mixed> $data data object
     */
    public function __construct(array $data);

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
