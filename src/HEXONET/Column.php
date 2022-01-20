<?php

#declare(strict_types=1);

/**
 * CNIC\HEXONET
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\HEXONET;

/**
 * HEXONET Column
 *
 * @package CNIC\HEXONET
 */

class Column implements \CNIC\ColumnInterface
{
    /**
     * count of column data entries
     * @var int
     */
    public $length;

    /**
     * column key name
     * @var string
     */
    private $key;
    /**
     * column data container
     * @var string[]
     */
    private $data;

    /**
     * Constructor
     *
     * @param string $key Column Name
     * @param string[] $data Column Data
     */
    public function __construct($key, $data)
    {
        $this->key = $key;
        $this->data = $data;
        $this->length = count($data);
    }

    /**
     * Get column name
     * @return string column name
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get column data
     * @return string[] column data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get column data at given index
     * @param integer $idx data index
     * @return string|null data at given index
     */
    public function getDataByIndex($idx): ?string
    {
        return $this->hasDataIndex($idx) ? $this->data[$idx] : null;
    }

    /**
     * Check if column has a given data index
     * @param integer $idx data index
     * @return bool result
     */
    private function hasDataIndex($idx): bool
    {
        return ($idx >= 0 && $idx < $this->length);
    }
}
