<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Pagination arithmetic over one list window (RSRMID-2965).
 *
 * A response answers five questions about its window from its own wire metadata
 * — where it starts, where it ends, how many rows exist in total, how many were
 * requested, and how many arrived. Everything else a caller asks about paging is
 * arithmetic over those five numbers, and it lives here rather than on
 * {@see AbstractResponse}: it needs no wire payload, no columns and no records,
 * so tying it to a Response only meant that testing an offset grid required
 * hand-authoring an API response to carry four integers.
 *
 * **This is a value object, and deliberately has no reference to a Response.**
 * Every input is a builtin scalar, so any grid — including ones no brand emits
 * yet — is one constructor call away. Do not add a `fromResponse()` convenience
 * constructor or a `ResponseInterface` parameter: that would re-couple the two
 * and put us back where hand-authored payloads were the only way to exercise the
 * arithmetic (guarded by tests/ResponsePaginationSeamTest.php).
 *
 * **Everything is computed in the constructor**, and the getters return what was
 * computed. That is not an optimisation — seven integer operations need none — it
 * is where the null handling lives. Each "is this grid usable?" check happens
 * exactly once, in the branch that also produces the value, so no getter has to
 * repeat a check that a predicate has already made. On {@see AbstractResponse}
 * those checks were duplicated in `getNextPageNumber()` and
 * `getPreviousPageNumber()` — unreachable while the matching predicate held
 * true, but PHPStan level 9 cannot see across a method boundary, so they had to
 * stay and be annotated "do not simplify these away".
 *
 * **Offsets, not page numbers, are the grid.** Every predicate and every page
 * number is answered from the offset a request would actually start at, so a
 * predicate and its corresponding getter cannot drift apart, and an unaligned
 * window (FIRST=50, LIMIT=100 — "page 1", whose next request starts at 150, not
 * 200) is handled rather than rounded away. Page numbers stay a derived view:
 * the request offsets are exact, and the number reported is the page that offset
 * lands on.
 *
 * @psalm-api
 * @package CNIC
 */
final class Paginator
{
    private readonly ?int $currentPage;

    private readonly bool $hasNext;

    private readonly ?int $nextPage;

    private readonly bool $hasPrevious;

    private readonly ?int $previousPage;

    private readonly int $pages;

    /**
     * @param int|null $first offset this window starts at (`FIRST`), null when the response carries no pagination metadata
     * @param int|null $last offset this window ends at (`LAST`); an empty window may echo `FIRST` here rather than a row offset
     * @param int|null $total rows available for the whole query (`TOTAL`), null when unknown
     * @param int|null $limit rows requested (`LIMIT`); `0` is a real request and stays distinct from null
     * @param int $count rows this response actually holds
     */
    public function __construct(
        private readonly ?int $first,
        private readonly ?int $last,
        private readonly ?int $total,
        private readonly ?int $limit,
        private readonly int $count
    ) {
        // The usable window size, or null when there is not one. A non-positive
        // limit is not a window: it was requested, so it is not "absent" — the
        // $limit property keeps that distinction for toArray() — but nothing can
        // be paged through in steps of zero, and treating it as a grid is what
        // let a walk restart from the beginning of the list (see hasNextPage()).
        // A local, because every derivation below is made here and nothing
        // afterwards needs it.
        $window = $limit !== null && $limit > 0 ? $limit : null;

        $currentPage = null;
        if ($window !== null && $first !== null) {
            $currentPage = intdiv($first, $window) + 1;
        }
        $this->currentPage = $currentPage;

        // "There is a next page" and "which page is it" are one decision, so
        // they are made together: the second cannot be asked in a state the
        // first has not already accepted.
        $hasNext = false;
        $nextPage = null;
        if (
            $window !== null
            && $first !== null
            && $last !== null
            && $total !== null
            && $last >= $first
            && $last + 1 < $total
        ) {
            $hasNext = true;
            $nextPage = intdiv($last + 1, $window) + 1;
        }
        $this->hasNext = $hasNext;
        $this->nextPage = $nextPage;

        $hasPrevious = false;
        $previousPage = null;
        if ($window !== null && $first !== null && $first > 0) {
            $hasPrevious = true;
            $previousPage = intdiv(max(0, $first - $window), $window) + 1;
        }
        $this->hasPrevious = $hasPrevious;
        $this->previousPage = $previousPage;

        // Note this reads $limit, not $window: a response that carries no limit
        // at all is an implicit single page when it holds rows (mirroring a
        // single-page brand's model), whereas a requested limit of 0 is a window
        // of nothing and pages through nothing.
        if ($total === null || $limit === null) {
            $this->pages = $count === 0 ? 0 : 1;
        } elseif ($total > 0 && $limit > 0) {
            $this->pages = (int)ceil($total / $limit);
        } else {
            $this->pages = 0;
        }
    }

    /**
     * Get Page Number of the current window, or null when it has no usable
     * offset grid (no metadata, or a non-positive limit — a window of no rows
     * has no meaningful page number).
     */
    public function getCurrentPageNumber(): ?int
    {
        return $this->currentPage;
    }

    /**
     * Check if this list query has a next page.
     *
     * Answered from the offset grid directly — `LAST + 1 < TOTAL` — rather than
     * from page numbers, so it agrees with {@see getNextPageNumber()} even when
     * the current window is not aligned to a page boundary.
     *
     * An **empty** window is the case to keep in mind, and the reason the
     * non-positive-limit gate exists. CNR answers one by echoing `LAST = FIRST`
     * (with `COUNT = 0`) rather than by omitting LAST or reporting a row offset.
     * Observed shapes, all `QueryDomainList`:
     *
     *   FIRST=0,        LIMIT=0  -> count=0, first=0,        last=0,        total=1825820
     *   FIRST=2000000,  LIMIT=0  -> count=0, first=2000000,  last=2000000,  total=1825824
     *   FIRST=20000000, LIMIT=10 -> count=0, first=20000000, last=20000000, total=1825824
     *
     * The third self-terminates on the arithmetic, because LAST echoes an offset
     * far past TOTAL. The first two do not: `LAST + 1 < TOTAL` holds, and without
     * a gate `CNR\Client::requestNextResponsePage()` would advance to `FIRST = 1`
     * and re-walk the list from near the start. What stops them is the
     * non-positive LIMIT gate — the older of the two guards here, which
     * `CNR\Client` has relied on to terminate since before the offset grid
     * existed (see tests/CNR/ClientTest.php testRequestNextResponsePageZeroLimit).
     *
     * The `LAST < FIRST` gate is **defensive only** — no observed CNR response
     * does it, precisely because an empty window echoes `LAST = FIRST`. It pins
     * the invariant the client's advance depends on: since `LAST >= FIRST`
     * always, `FIRST = LAST + 1` strictly increases and the walk is monotonic. A
     * future wire change (or a substitute parser) that broke that would send
     * pagination backwards rather than failing, so it is refused here.
     *
     * The row count is NOT the gate to use, however much "an empty window has no
     * next page" sounds like the same statement: it describes the rows in hand,
     * not whether more exist beyond them, so a server answering an empty window
     * mid-list would terminate a walk that should have continued.
     */
    public function hasNextPage(): bool
    {
        return $this->hasNext;
    }

    /**
     * Check if this list query has a previous page.
     *
     * Answered from the offset grid directly — `FIRST > 0` — for the same reason
     * as {@see hasNextPage()}: an unaligned window still has a well-defined
     * "before it" even though it does not sit on a page boundary. The same
     * non-positive-limit gate applies, because a window of no rows cannot page
     * backward either.
     */
    public function hasPreviousPage(): bool
    {
        return $this->hasPrevious;
    }

    /**
     * Get Page Number of the next list query, or null when there is none.
     *
     * Computed from the *offset* the next request will actually start at
     * (`LAST + 1`) rather than from the current page number plus one. The two
     * agree on every window this can be asked about — for a full window LAST + 1
     * is FIRST + LIMIT, so `intdiv(LAST + 1, LIMIT) + 1` reduces to
     * `intdiv(FIRST, LIMIT) + 2`; a short window only occurs at the tail, where
     * {@see hasNextPage()} is already false. The offset form is used anyway
     * because it is the same grid the predicate answers from, and because it
     * mirrors {@see getPreviousPageNumber()}, where the offset form and
     * "current - 1" genuinely do differ on an unaligned window.
     */
    public function getNextPageNumber(): ?int
    {
        return $this->nextPage;
    }

    /**
     * Get Page Number of the previous list query, or null when there is none.
     *
     * Computed from the offset the previous request would start at
     * (`max(0, FIRST - LIMIT)`), not from the current page number minus one: for
     * an unaligned window the two disagree, and the offset form is the one that
     * matches what would actually be requested. For an aligned FIRST both forms
     * reduce to the same classic value.
     */
    public function getPreviousPageNumber(): ?int
    {
        return $this->previousPage;
    }

    /**
     * Get the number of pages available for this list query.
     *
     * `0` when total or limit is unavailable and the response holds no rows
     * (nothing to page through); `1` when it holds rows but is not a paginated
     * list at all (an implicit single page). Otherwise the ceiling of
     * total/limit.
     */
    public function getNumberOfPages(): int
    {
        return $this->pages;
    }

    /**
     * Get all paging data in one hash.
     *
     * The keys and their order are the wire-facing projection this replaced
     * (`ResponseInterface::getPagination()` returned exactly this array before
     * RSRMID-2965), because it is what `CNR\Response::getListHash()` publishes
     * under `meta.pg` — a table renderer's payload, not an internal shape.
     * @return array<string, int|null>
     */
    public function toArray(): array
    {
        return [
            "COUNT" => $this->count,
            "CURRENTPAGE" => $this->currentPage,
            "FIRST" => $this->first,
            "LAST" => $this->last,
            "LIMIT" => $this->limit,
            "NEXTPAGE" => $this->nextPage,
            "PAGES" => $this->pages,
            "PREVIOUSPAGE" => $this->previousPage,
            "TOTAL" => $this->total
        ];
    }
}
