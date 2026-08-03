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
     * @param array<array-key, TValue> $data Column Data
     */
    public function __construct(
        private readonly string $columnName,
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
        return $this->columnName;
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
     * @return TValue|null
     */
    #[\Override]
    public function getDataByIndex(int $recordIndex): mixed
    {
        return $this->hasDataIndex($recordIndex) ? $this->data[$recordIndex] : null;
    }

    /**
     * Check if column has a given data index
     */
    private function hasDataIndex(int $recordIndex): bool
    {
        return ($recordIndex >= 0 && $recordIndex < $this->length);
    }

    /**
     * Get column data at given index, parsed as a date/time value.
     *
     * Opt-in narrowing over {@see self::getDataByIndex()}: returns `null` for
     * an out-of-range index, a non-string value, or a string
     * {@see ApiDateTime::tryFrom()} cannot parse. There is no throwing variant
     * here — use `ApiDateTime::from($col->getDataByIndex($recordIndex))` directly
     * if an unparsable value should be a loud failure instead.
     */
    #[\Override]
    public function getDateTimeByIndex(int $recordIndex): ?ApiDateTime
    {
        $value = $this->getDataByIndex($recordIndex);
        return is_string($value) ? ApiDateTime::tryFrom($value) : null;
    }
}
