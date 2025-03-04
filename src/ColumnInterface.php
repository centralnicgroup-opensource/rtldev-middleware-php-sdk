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
 * @method __construct(string $key, array<string> $data) Constructor
 * @method string getKey() Get column name
 * @method string[] getData() Get column data
 * @method string|null getDataByIndex(int $idx) Get column data at given index
 */
interface ColumnInterface
{
    /**
     * Constructor
     *
     * @param string $key Column Name
     * @param array<string> $data Column Data
     */
    public function __construct(string $key, array $data);

    /**
     * Get column name
     *
     * @return string
     */
    public function getKey(): string;

    /**
     * Get column data
     *
     * @return array<string>
     */
    public function getData(): array;

    /**
     * Get column data at given index
     *
     * @param int $idx data index
     * @return string|null
     */
    public function getDataByIndex(int $idx): ?string;

    /**
     * Check if column has a given data index
     *
     * @param int $idx data index
     * @return bool
     */
    //public function hasDataIndex(int $idx): bool;
}
