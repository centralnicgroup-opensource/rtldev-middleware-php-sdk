<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\Paginator;
use PHPUnit\Framework\TestCase;

/**
 * The pagination arithmetic, exercised directly (RSRMID-2965).
 *
 * Every case here is a constructor call. That is the point of extracting the
 * derivation off AbstractResponse: the same coverage used to require a
 * hand-authored API response per case — tests/CNR/ResponseTest.php grew several
 * payloads whose only purpose was to control four integers — and offset grids no
 * brand emits could not be expressed at all.
 *
 * The wire-shape cases below (the three observed empty CNR windows, the IBS
 * single page) are stated as the tuple a brand's primitives would answer with, so
 * this file documents the grids the SDK has actually met without depending on a
 * parser to reproduce them. The brand tests still assert that each brand reads
 * those numbers off its own wire format.
 */
final class PaginatorTest extends TestCase
{
    /**
     * An aligned first page: page 1, a next page, no previous one.
     */
    public function testAlignedFirstPage(): void
    {
        $pg = new Paginator(first: 0, last: 9, total: 100, limit: 10, count: 10);

        $this->assertSame(1, $pg->getCurrentPageNumber());
        $this->assertTrue($pg->hasNextPage());
        $this->assertSame(2, $pg->getNextPageNumber());
        $this->assertFalse($pg->hasPreviousPage());
        $this->assertNull($pg->getPreviousPageNumber());
        $this->assertSame(10, $pg->getNumberOfPages());
    }

    /**
     * An aligned middle page pages in both directions.
     */
    public function testAlignedMiddlePage(): void
    {
        $pg = new Paginator(first: 10, last: 19, total: 100, limit: 10, count: 10);

        $this->assertSame(2, $pg->getCurrentPageNumber());
        $this->assertSame(3, $pg->getNextPageNumber());
        $this->assertTrue($pg->hasPreviousPage());
        $this->assertSame(1, $pg->getPreviousPageNumber());
    }

    /**
     * The last page has no next one, and says so as `false`/`null` rather than
     * by reporting a page beyond the end.
     */
    public function testLastPage(): void
    {
        $pg = new Paginator(first: 90, last: 99, total: 100, limit: 10, count: 10);

        $this->assertSame(10, $pg->getCurrentPageNumber());
        $this->assertFalse($pg->hasNextPage());
        $this->assertNull($pg->getNextPageNumber());
        $this->assertSame(9, $pg->getPreviousPageNumber());
    }

    /**
     * A short tail window is still the last page: fewer rows than the limit does
     * not invent a further page.
     */
    public function testShortTailWindow(): void
    {
        $pg = new Paginator(first: 20, last: 24, total: 25, limit: 10, count: 5);

        $this->assertSame(3, $pg->getCurrentPageNumber());
        $this->assertFalse($pg->hasNextPage());
        $this->assertSame(3, $pg->getNumberOfPages());
    }

    /**
     * An **unaligned** window — the case the offset grid exists for.
     *
     * FIRST=50 with LIMIT=100 is "page 1" by page arithmetic, but its next
     * request starts at offset 150, which lands on page 2, and it genuinely has
     * something before it even though it does not sit on a page boundary. A
     * page-number implementation reports "no previous page" here; an offset one
     * does not.
     */
    public function testUnalignedWindowPagesFromTheOffsetGrid(): void
    {
        $pg = new Paginator(first: 50, last: 149, total: 1000, limit: 100, count: 100);

        $this->assertSame(1, $pg->getCurrentPageNumber());
        $this->assertTrue($pg->hasNextPage());
        $this->assertSame(2, $pg->getNextPageNumber(), "the next request starts at offset 150");
        $this->assertTrue($pg->hasPreviousPage(), "FIRST > 0, so there is something before this window");
        $this->assertSame(1, $pg->getPreviousPageNumber());
    }

    /**
     * The first two observed empty CNR windows: a non-positive limit.
     *
     * `LAST + 1 < TOTAL` holds for both, so the offset arithmetic alone would
     * report a next page and a walk would restart near the beginning of the list.
     * The non-positive-limit gate is what refuses them — see
     * {@see Paginator::hasNextPage()} for the observed QueryDomainList shapes.
     */
    public function testEmptyWindowWithNonPositiveLimitHasNoNextPage(): void
    {
        $atStart = new Paginator(first: 0, last: 0, total: 1825820, limit: 0, count: 0);
        $farOut = new Paginator(first: 2000000, last: 2000000, total: 1825824, limit: 0, count: 0);

        foreach ([$atStart, $farOut] as $pg) {
            $this->assertFalse($pg->hasNextPage(), "a window of no rows cannot advance");
            $this->assertNull($pg->getNextPageNumber());
            $this->assertFalse($pg->hasPreviousPage(), "nor page backwards");
            $this->assertNull($pg->getCurrentPageNumber(), "and has no meaningful page number");
            $this->assertSame(0, $pg->getNumberOfPages());
        }
    }

    /**
     * The third observed empty CNR window: a positive limit at an offset past the
     * end. This one self-terminates on the arithmetic, because LAST echoes FIRST.
     */
    public function testEmptyWindowPastTheEndHasNoNextPage(): void
    {
        $pg = new Paginator(first: 20000000, last: 20000000, total: 1825824, limit: 10, count: 0);

        $this->assertFalse($pg->hasNextPage());
        $this->assertTrue($pg->hasPreviousPage(), "there is a whole list before this offset");
        $this->assertSame(182583, $pg->getNumberOfPages(), "ceil(1825824 / 10)");
    }

    /**
     * The defensive `LAST < FIRST` gate: a window that ends before it starts is
     * refused rather than sending the walk backwards.
     *
     * No observed CNR response does this — an empty window echoes LAST = FIRST —
     * so this pins the invariant `CNR\Client`'s `FIRST = LAST + 1` advance
     * depends on for monotonicity, against a future wire change or a substitute
     * parser.
     */
    public function testAWindowEndingBeforeItStartsHasNoNextPage(): void
    {
        $pg = new Paginator(first: 100, last: 50, total: 1000, limit: 10, count: 0);

        $this->assertFalse($pg->hasNextPage());
        $this->assertNull($pg->getNextPageNumber());
    }

    /**
     * A single-page brand (IBS): total == limit == the wire count, so the
     * arithmetic — not a brand special case — answers "one page, no next page".
     */
    public function testSinglePageGrid(): void
    {
        $pg = new Paginator(first: 0, last: 2, total: 3, limit: 3, count: 3);

        $this->assertSame(1, $pg->getCurrentPageNumber());
        $this->assertFalse($pg->hasNextPage());
        $this->assertFalse($pg->hasPreviousPage());
        $this->assertSame(1, $pg->getNumberOfPages());
    }

    /**
     * An empty single-page list (IBS `domaincount: 0`): a count key is present,
     * so the grid exists, but it pages through nothing.
     */
    public function testEmptySinglePageGrid(): void
    {
        $pg = new Paginator(first: 0, last: null, total: 0, limit: 0, count: 0);

        $this->assertSame(0, $pg->getNumberOfPages());
        $this->assertFalse($pg->hasNextPage());
        $this->assertNull($pg->getCurrentPageNumber());
    }

    /**
     * A response that carries no pagination metadata at all but does hold rows is
     * an implicit single page — the rule that lets a non-list response answer
     * every pagination question honestly instead of with stand-ins.
     */
    public function testNoMetadataWithRowsIsAnImplicitSinglePage(): void
    {
        $pg = new Paginator(first: null, last: null, total: null, limit: null, count: 1);

        $this->assertSame(1, $pg->getNumberOfPages());
        $this->assertNull($pg->getCurrentPageNumber());
        $this->assertFalse($pg->hasNextPage());
        $this->assertFalse($pg->hasPreviousPage());
        $this->assertNull($pg->getNextPageNumber());
        $this->assertNull($pg->getPreviousPageNumber());
    }

    /**
     * No metadata and no rows: nothing to page through.
     */
    public function testNoMetadataAndNoRowsHasNoPages(): void
    {
        $pg = new Paginator(first: null, last: null, total: null, limit: null, count: 0);

        $this->assertSame(0, $pg->getNumberOfPages());
    }

    /**
     * `0` and `null` stay different answers all the way through: a requested
     * limit of zero is a fact about the request, an absent one is the absence of
     * a list. Collapsing them is what the primitives stopped doing in
     * RSRMID-2943, and it has to survive the trip through this object.
     */
    public function testZeroIsNotNull(): void
    {
        $requested = (new Paginator(first: 0, last: 0, total: 0, limit: 0, count: 0))->toArray();
        $absent = (new Paginator(first: null, last: null, total: null, limit: null, count: 0))->toArray();

        $this->assertSame(0, $requested["LIMIT"]);
        $this->assertSame(0, $requested["TOTAL"]);
        $this->assertNull($absent["LIMIT"]);
        $this->assertNull($absent["TOTAL"]);
    }

    /**
     * toArray() is the published projection: `CNR\Response::getListHash()` puts it
     * under `meta.pg`, so its keys and their order are consumer-facing. Asserted
     * with assertSame, which compares arrays key-for-key **in order**.
     */
    public function testToArrayShapeIsTheWireFacingProjection(): void
    {
        $pg = new Paginator(first: 10, last: 19, total: 100, limit: 10, count: 10);

        $this->assertSame([
            "COUNT" => 10,
            "CURRENTPAGE" => 2,
            "FIRST" => 10,
            "LAST" => 19,
            "LIMIT" => 10,
            "NEXTPAGE" => 3,
            "PAGES" => 10,
            "PREVIOUSPAGE" => 1,
            "TOTAL" => 100
        ], $pg->toArray());
    }

    /**
     * The row count reported is the one it was given — the number of rows the
     * response holds — and not re-derived from the offsets, which would let a
     * lying wire count contradict the array getRecord() indexes.
     */
    public function testCountIsReportedAsGivenNotDerived(): void
    {
        $pg = new Paginator(first: 0, last: 9, total: 100, limit: 10, count: 3);

        $this->assertSame(3, $pg->toArray()["COUNT"]);
    }
}
