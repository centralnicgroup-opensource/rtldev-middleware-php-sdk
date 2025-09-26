<?php

#declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\CNR;

/**
 * CNR Column
 *
 * @package CNIC\CNR
 */
class Column // implements \CNIC\ColumnInterface
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
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Get column data
     * @return string[]
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get column data at given index
     * @param integer $idx data index
     * @return string|null
     */
    public function getDataByIndex($idx)
    {
        return $this->hasDataIndex($idx) ? $this->data[$idx] : null;
    }

    /**
     * Check if column has a given data index
     * @param integer $idx data index
     * @return bool
     */
    private function hasDataIndex($idx)
    {
        return ($idx >= 0 && $idx < $this->length);
    }
}
