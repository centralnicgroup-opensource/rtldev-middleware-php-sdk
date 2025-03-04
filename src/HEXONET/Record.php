<?php

#declare(strict_types=1);

/**
 * CNIC\HEXONET
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\HEXONET;

/**
 * HEXONET Record
 *
 * @package CNIC\HEXONET
 */
class Record // implements \CNIC\RecordInterface
{
    /**
     * row data container
     * e.g.
     * <code>
     * $data = [
     *   "DOMAIN" => "mydomain.com",
     *   "USER"   => "test.user",
     *   // ... further column data ...
     * ];
     * </code>
     * @var array<string>
     */
    private $data;

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
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * get row data
     * @return array<string>
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * get row data for given column
     * @param string $key column name
     * @return string|null
     */
    public function getDataByKey($key)
    {
        if ($this->hasData($key)) {
            return $this->data[$key];
        }
        return null;
    }

    /**
     * check if record has data for given column
     * @param string $key column name
     * @return bool
     */
    private function hasData($key)
    {
        return array_key_exists($key, $this->data);
    }
}
