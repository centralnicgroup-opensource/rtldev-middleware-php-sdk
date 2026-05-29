<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © CentralNic Group PLC
 */

namespace CNIC;

/**
 * Common Column Interface
 *
 * @psalm-api
 * @package CNIC
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
     */
    public function getDataByIndex(int $idx): mixed;

    /**
     * Check if column has a given data index
     *
     * @param int $idx data index
     * @return bool
     */
    //public function hasDataIndex(int $idx): bool;
}
