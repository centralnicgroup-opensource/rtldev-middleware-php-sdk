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
 * Declares what a column can be *asked*, not how it is built. Do not re-add a
 * __construct() declaration: nothing constructs through this type (columns come
 * from a brand's addColumn(), which names its concrete class), and PHP *does*
 * enforce a constructor declared on an interface — unlike one inherited from a
 * parent class — so the declaration constrained every implementer without
 * giving a caller anything. Guarded by tests/RecordColumnSeamTest.php;
 * rationale in docs/agents/architecture.md.
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
     */
    public function getDataByIndex(int $idx): mixed;

    /**
     * Get column data at given index, parsed as a date/time value.
     *
     * Returns `null` for an out-of-range index, a non-string value, or a
     * string that cannot be parsed — see {@see ApiDateTime::tryFrom()}.
     */
    public function getDateTimeByIndex(int $idx): ?ApiDateTime;
}
