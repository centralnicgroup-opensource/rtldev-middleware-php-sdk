<?php

#declare(strict_types=1);

/**
 * CNIC
 * Copyright © CentralNic Group PLC
 */

namespace CNIC;

/**
 * Common Column Interface
 *
 * @package CNIC
 */

interface ColumnInterface
{
    /**
     * Constructor
     *
     * @param string $key Column Name
     * @param string[] $data Column Data
     */
    public function __construct(string $key, array $data);

    /**
     * Get column name
     * @return string column name
     */
    public function getKey(): string;

    /**
     * Get column data
     * @return string[] column data
     */
    public function getData(): array;

    /**
     * Get column data at given index
     * @param integer $idx data index
     * @return string|null data at given index
     */
    public function getDataByIndex($idx): ?string;

    /**
     * Check if column has a given data index
     * @param integer $idx data index
     * @return bool result
     */
    //private function hasDataIndex($idx): bool;
}
