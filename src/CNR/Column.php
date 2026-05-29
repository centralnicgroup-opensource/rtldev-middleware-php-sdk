<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\CNR;

use CNIC\ColumnInterface;

/**
 * CNR Column
 *
 * @psalm-api
 * @package CNIC\CNR
 */
class Column implements ColumnInterface
{
    /**
     * count of column data entries
     */
    public int $length;

    /**
     * column key name
     */
    private string $key;

    /**
     * column data container
     * @var string[]
     */
    private array $data;

    /**
     * Constructor
     *
     * @param string $key Column Name
     * @param string[] $data Column Data
     */
    public function __construct(string $key, array $data)
    {
        $this->key = $key;
        $this->data = $data;
        $this->length = count($data);
    }

    /**
     * Get column name
     */
    #[\Override]
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get column data
     * @return string[]
     */
    #[\Override]
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get column data at given index
     * @param integer $idx data index
     */
    #[\Override]
    public function getDataByIndex(int $idx): mixed
    {
        return $this->hasDataIndex($idx) ? $this->data[$idx] : null;
    }

    /**
     * Check if column has a given data index
     * @param integer $idx data index
     */
    private function hasDataIndex(int $idx): bool
    {
        return ($idx >= 0 && $idx < $this->length);
    }
}
