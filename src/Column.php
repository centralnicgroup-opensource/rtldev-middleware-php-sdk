<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Shared Column implementation
 *
 * Brand-neutral, immutable column of response data. Every brand stores the same
 * thing — a key plus an ordered bag of values — so all behaviour lives here and
 * is instantiated directly by each Response's addColumn(). What differs between
 * brands is only the *value type*: CNR responses are plaintext and carry strings,
 * IBS/Moniker responses are JSON and carry arbitrary values (nested arrays and
 * objects included). That difference is expressed by the TValue template
 * parameter, so `CNR\Column extends Column<string>` gains the narrower
 * `getDataByIndex(): ?string` without re-implementing anything.
 *
 * @template TValue
 * @psalm-api
 * @package CNIC
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
     * @param array<array-key, TValue> $data Column Data
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
     * @return array<array-key, TValue>
     */
    #[\Override]
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get column data at given index
     * @param integer $idx data index
     * @return TValue|null
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
