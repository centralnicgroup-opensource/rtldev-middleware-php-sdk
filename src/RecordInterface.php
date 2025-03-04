<?php

#declare(strict_types=1);

/**
 * CNIC
 * Copyright © CentralNic Group PLC
 */

namespace CNIC;

/**
 * Common Record Interface
 *
 * @package CNIC
 * @method __construct(array<string> $data) Constructor
 * @method array<string> getData() Get row data
 * @method string|null getDataByKey(string $key) Get row data for given column
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
     * @param array<string> $data data object
     */
    public function __construct(array $data);

    /**
     * Get row data
     *
     * @return array<string> row data
     */
    public function getData(): array;

    /**
     * Get row data for given column
     *
     * @param string $key column name
     * @return string|null row data for given column or null if column does not exist
     */
    public function getDataByKey(string $key): ?string;

    /**
     * Check if record has data for given column
     *
     * @param string $key column name
     * @return bool boolean result
     */
    //public function hasData(string $key): bool;
}
