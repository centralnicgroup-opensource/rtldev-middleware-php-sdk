<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
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
    public readonly int $length;

    /**
     * Constructor
     *
     * @param string $key Column Name
     * @param string[] $data Column Data
     * @psalm-suppress MoreSpecificImplementedParamType CNR columns are always string-valued
     */
    public function __construct(
        private readonly string $key,
        private readonly array $data
    ) {
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
    public function getDataByIndex(int $idx): string|null
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
