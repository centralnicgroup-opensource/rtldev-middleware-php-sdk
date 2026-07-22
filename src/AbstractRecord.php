<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Shared Record foundation
 *
 * Brand-neutral base for every registrar Record. Record data has one shape
 * across brands (array<string,mixed>), so all behaviour lives here and the
 * per-brand subclasses are empty markers instantiated by each Response's
 * newRecord() factory hook. CNR\Record and IBS\Record both extend this as
 * siblings — neither is-a the other.
 *
 * @psalm-api
 * @package CNIC
 */
abstract class AbstractRecord implements RecordInterface
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
