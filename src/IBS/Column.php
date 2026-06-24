<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\ColumnInterface;

/**
 * IBS Column
 *
 * Stores column data as-is — nested arrays and objects are preserved without coercion.
 *
 * @psalm-api
 * @package CNIC\IBS
 */
class Column implements ColumnInterface
{
    /**
     * Count of column data entries
     */
    public readonly int $length;

    /**
     * @param string $key Column Name
     * @param array<array-key, mixed> $data Column Data
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
     * @return array<array-key, mixed>
     */
    #[\Override]
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get column data at given index
     * @param int $idx data index
     */
    #[\Override]
    public function getDataByIndex(int $idx): mixed
    {
        return $idx >= 0 && $idx < $this->length ? $this->data[$idx] : null;
    }
}
