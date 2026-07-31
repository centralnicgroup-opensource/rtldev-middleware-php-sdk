<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Common Column Interface
 *
 * Declares what a column can be *asked*, not how it is built. The
 * __construct(string $key, array $data) declaration this carried until
 * RSRMID-2923 is deliberately gone: nothing constructs through this type (every
 * column is built by a brand's addColumn(), which names its concrete class), so
 * the declaration bought no caller anything while constraining every
 * implementer — and unlike class inheritance, PHP *does* enforce a constructor
 * declared on an interface, so that constraint had teeth. It ruled out any
 * column not born from a plain (key, array) pair, and it was what forced the
 * old hand-rolled CNR\Column's @psalm-suppress MoreSpecificImplementedParamType.
 * Do not re-add it — the same call was made for ResponseInterface in
 * RSRMID-2918, and it is guarded by tests/RecordColumnSeamTest.php.
 *
 * @psalm-api
 * @package CNIC
 */
interface ColumnInterface
{
    /**
     * Get column name
     */
    public function getKey(): string;

    /**
     * Get column data
     *
     * @return array<array-key, mixed>
     */
    public function getData(): array;

    /**
     * Get column data at given index
     *
     * @param int $idx data index
     */
    public function getDataByIndex(int $idx): mixed;

    /**
     * Check if column has a given data index
     *
     * @param int $idx data index
     * @return bool
     */
    //public function hasDataIndex(int $idx): bool;

    /**
     * Get column data at given index, parsed as a date/time value.
     *
     * Returns `null` for an out-of-range index, a non-string value, or a
     * string that cannot be parsed — see {@see ApiDateTime::tryFrom()}.
     *
     * @param int $idx data index
     */
    public function getDateTimeByIndex(int $idx): ?ApiDateTime;
}
