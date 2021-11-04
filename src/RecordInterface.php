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
     * @param array $data data object
     */
    public function __construct(array $data);

    /**
     * get row data
     * @return array row data
     */
    public function getData(): array;

    /**
     * get row data for given column
     * @param string $key column name
     * @return string|null row data for given column or null if column does not exist
     */
    public function getDataByKey($key): ?string;

    /**
     * check if record has data for given column
     * @param string $key column name
     * @return bool boolean result
     */
    //private function hasData($key): bool;
}
