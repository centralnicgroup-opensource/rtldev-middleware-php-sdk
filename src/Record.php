<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Shared Record implementation
 *
 * Brand-neutral record (row) of a list response. Record data has one shape
 * across brands (array<string,mixed>) and no brand has ever needed to read a
 * row differently, so there is exactly one Record and every Response's
 * newRecord() factory hook returns it. The hook itself stays a per-brand
 * declaration: a brand that genuinely needs different row behaviour implements
 * RecordInterface and returns that from its own newRecord() instead.
 *
 * @psalm-api
 * @package CNIC
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

    /**
     * Get row data for given column, parsed as a date/time value.
     *
     * Opt-in narrowing over {@see self::getDataByKey()}: returns `null` for a
     * missing key, a non-string value, or a string {@see ApiDateTime::tryFrom()}
     * cannot parse. There is no throwing variant here — use
     * `ApiDateTime::from($rec->getDataByKey($key))` directly if an unparsable
     * value should be a loud failure instead.
     *
     * @param string $key column name
     */
    #[\Override]
    public function getDateTimeByKey(string $key): ?ApiDateTime
    {
        /** @psalm-suppress MixedAssignment getDataByKey() returns mixed by design; is_string() narrows it below */
        $value = $this->getDataByKey($key);
        return is_string($value) ? ApiDateTime::tryFrom($value) : null;
    }
}
