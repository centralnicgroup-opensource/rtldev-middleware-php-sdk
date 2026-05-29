<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\CNR;

use CNIC\RecordInterface;

/**
 * CNR Record
 *
 * @psalm-api
 * @package CNIC\CNR
 */
class Record implements RecordInterface
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
     * @var array<string,mixed>
     */
    private array $data;

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
     * @param array<string,mixed> $data data object
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * get row data
     * @return array<string,mixed>
     */
    #[\Override]
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * get row data for given column
     * @param string $key column name
     */
    #[\Override]
    public function getDataByKey(string $key): mixed
    {
        if ($this->hasData($key)) {
            return $this->data[$key];
        }
        return null;
    }

    /**
     * check if record has data for given column
     * @param string $key column name
     */
    private function hasData(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }
}
