<?php

declare(strict_types=1);

namespace CNICTEST\Support;

/**
 * Test seam exposing the two protected Response hooks a brand uses to build its
 * column and record lists — `addColumn()` and `assembleRecords()`.
 *
 * Both are protected in `src/` on purpose (RSRMID-2939: a response is sealed once
 * constructed). {@see \CNICTEST\ResponseSealSeamTest} still has to *exercise* them
 * to prove the two bookkeeping invariants it guards — that a duplicate column name
 * is refused, and that assembling twice does not double the record list — and a
 * brand subclass calling its own hooks is the honest way to do that, closer to
 * what a real brand does than reflection would be.
 *
 * Declared here rather than inline so the anonymous subclass has a named type the
 * analysers can carry through a factory's return type. Nothing in `src/` knows
 * about it, and nothing here widens the sealed surface: an anonymous subclass in
 * a test can reach a protected hook regardless of whether this interface exists.
 *
 * @package CNICTEST\Support
 */
interface ColumnRegistrar
{
    /**
     * Register a column through the brand's own protected addColumn() hook.
     * @param array<array-key, mixed> $data array of column data
     */
    public function register(string $columnName, array $data): void;

    /**
     * Re-run the shared record assembly.
     */
    public function assembleAgain(): void;
}
