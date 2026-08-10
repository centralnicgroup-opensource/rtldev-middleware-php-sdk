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
 * objects included). That difference used to be expressed as a `TValue` template
 * parameter narrowed by a brand subclass, but the narrowing never survived
 * {@see ColumnInterface} — which is not generic — so no consumer holding the
 * interface (every reachable path: getColumn()/getColumns()) ever saw it. It is
 * expressed instead as a native return type on the interface itself, exactly as
 * {@see self::getDateTimeByIndex()} already narrows: {@see self::getStringByIndex()}.
 *
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
     * @param array<array-key, mixed> $data Column Data
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
     * @return array<array-key, mixed>
     */
    #[\Override]
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get column data at given index
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
     * Get column data at given index, narrowed to a string.
     *
     * Returns `null` for an out-of-range index or a non-string value. CNR
     * cells are always strings; IBS/Moniker JSON cells may be nested arrays
     * or objects, which yield null here — use {@see self::getDataByIndex()}
     * for the raw value in that case.
     */
    #[\Override]
    public function getStringByIndex(int $recordIndex): ?string
    {
        /** @psalm-suppress MixedAssignment getDataByIndex() returns mixed by design; is_string() narrows it below */
        $value = $this->getDataByIndex($recordIndex);
        return is_string($value) ? $value : null;
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
        /** @psalm-suppress MixedAssignment getDataByIndex() returns mixed by design; is_string() narrows it below */
        $value = $this->getDataByIndex($recordIndex);
        return is_string($value) ? ApiDateTime::tryFrom($value) : null;
    }
}
