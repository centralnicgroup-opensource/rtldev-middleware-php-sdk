<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
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
     * Constructor
     * e.g.
     * <code>
     * $data = [
     *   "DOMAIN" => "mydomain.com",
     *   "USER"   => "test.user",
     *   // ... further column data ...
     * ];
     * </code>
     * @param array<string,mixed> $data row data
     */
    public function __construct(private readonly array $data)
    {
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
