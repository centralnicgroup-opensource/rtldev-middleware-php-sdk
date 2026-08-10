<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\AbstractResponse;
use CNIC\CNR\Response as CNRResponse;
use CNIC\IBS\Response as IBSResponse;
use CNIC\ResponseInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Locks the pagination seam of AbstractResponse at the wire (RSRMID-2912
 * declined, RSRMID-2918 delivered, RSRMID-2943 narrowed).
 *
 * **Directive.** The seam is drawn at the wire: a brand Response declares
 * exactly the four methods that read its own pagination columns
 * (getFirstRecordIndex, getLastRecordIndex, getRecordsTotalCount,
 * getRecordsLimitation) and nothing else. AbstractResponse owns every
 * derivation from those four answers — including getCurrentPageNumber(),
 * hasNextPage() and hasPreviousPage(), which read no column of their own and
 * therefore do not belong in the brand-primitive set at all.
 *
 * **Failure mode prevented.** A brand that silently inherits column readers —
 * whether through a hoisted base default or a shared trait — reports "one
 * page, no next page" for a list that genuinely has more, and a consumer
 * paging through it loses pages 2..N with no error anywhere.
 *
 * **Why the guard must be structural.** Hoisting single-page defaults onto the
 * base is behaviour-preserving on the day it lands: IBS already returns
 * exactly those defaults today, so no behavioural test can distinguish "IBS
 * answered this itself" from "IBS silently inherited a base default that
 * happens to match its own answer". Only reflection, via getDeclaringClass(),
 * can tell the two apart.
 *
 * **Revisit condition.** A brand whose "more results" signal is a cursor or
 * opaque token rather than a record offset. For such a brand hasNextPage()
 * genuinely becomes a wire read again (whatever the cursor field is called),
 * and it belongs back in PRIMITIVES.
 *
 * **History.** RSRMID-2912 first proposed hoisting defaults and was declined,
 * closed as Cancelled; RSRMID-2918 is the issue that actually delivered this
 * guard, with 7 primitives (the four column readers plus
 * getCurrentPageNumber/hasNextPage/hasPreviousPage) and 5 derived getters.
 * RSRMID-2943 re-examined that split and found it had pinned a
 * misclassification: getCurrentPageNumber()/hasNextPage()/hasPreviousPage()
 * read no wire column — they are pure functions of the four column readers,
 * exactly like getNextPageNumber()/getNumberOfPages() already were — so
 * pinning them as "primitives" protected nothing while forcing brands to
 * hand-roll arithmetic that could (and, for CNR, did) disagree with the
 * equivalent page-number getters on an unaligned offset window. Narrowing to
 * 4 primitives / 8 derived puts every predicate and page number on the same
 * offset grid, so a predicate and its corresponding getter can no longer
 * disagree. Full account in docs/agents/architecture.md.
 */
final class ResponsePaginationSeamTest extends TestCase
{
    /**
     * Pagination primitives that read a brand's own wire columns and must be
     * answered explicitly by every brand.
     *
     * Spelled out on purpose rather than derived from ResponseInterface minus
     * what AbstractResponse implements: a derived list would drop a primitive
     * the moment the base implemented it, so the erosion this test exists to
     * catch would silently redefine the expectation and the test would pass.
     * @var string[]
     */
    private const array PRIMITIVES = [
        "getFirstRecordIndex",
        "getLastRecordIndex",
        "getRecordsTotalCount",
        "getRecordsLimitation",
    ];

    /**
     * Getters derived purely from the primitives above, shared by all brands.
     * @var string[]
     */
    private const array DERIVED_GETTERS = [
        "getCurrentPageNumber",
        "getNextPageNumber",
        "getNumberOfPages",
        "getPagination",
        "getPreviousPageNumber",
        "getRecordsCount",
        "hasNextPage",
        "hasPreviousPage",
    ];

    /**
     * Every brand Response must declare all 4 primitives itself. MONIKER is not
     * listed because it reuses IBS\Response verbatim.
     */
    public function testEachBrandDeclaresItsOwnPrimitives(): void
    {
        foreach ([CNRResponse::class, IBSResponse::class] as $brand) {
            foreach (self::PRIMITIVES as $method) {
                $this->assertDeclaredBy(
                    $brand,
                    $brand,
                    $method,
                    "must be declared by the brand itself, not inherited"
                );
            }
        }
    }

    /**
     * The base must stay silent about the primitives — not even single-page
     * defaults — so "brand forgot pagination" remains a declaration-time error.
     *
     * Reflection reports the primitives as abstract members of AbstractResponse
     * because it implements ResponseInterface, so their presence is not the
     * signal; what matters is that the *declaring* class is still the interface.
     * The moment the base grows a body for one, its declaring class becomes
     * AbstractResponse and this fails.
     */
    public function testAbstractResponseDeclaresNoPrimitives(): void
    {
        foreach (self::PRIMITIVES as $method) {
            $this->assertDeclaredBy(
                ResponseInterface::class,
                AbstractResponse::class,
                $method,
                "must stay owned by the interface contract — the base must not implement it, "
                . "see RSRMID-2912 (declined)"
            );
        }
    }

    /**
     * Positive half of the seam: the derived getters are shared machinery and
     * belong on the base, so brands never reimplement pagination arithmetic.
     */
    public function testAbstractResponseOwnsTheDerivedGetters(): void
    {
        foreach (self::DERIVED_GETTERS as $method) {
            $this->assertDeclaredBy(
                AbstractResponse::class,
                AbstractResponse::class,
                $method,
                "must be declared on the shared base so brands never reimplement pagination arithmetic"
            );
        }
    }

    /**
     * Assert which class owns $method as seen from $viewedFrom.
     *
     * getDeclaringClass() is the whole mechanism of this test: it answers "who
     * actually declared this?" through inheritance, interface implementation and
     * trait composition alike, so it catches erosion arriving by any of those
     * routes — including a SinglePageResponse trait, whose methods reflection
     * attributes to the using class.
     *
     * @param class-string $expected class that must own the declaration
     * @param class-string $viewedFrom class the method is resolved through
     */
    private function assertDeclaredBy(
        string $expected,
        string $viewedFrom,
        string $method,
        string $because
    ): void {
        $declaring = (new ReflectionMethod($viewedFrom, $method))->getDeclaringClass()->getName();
        $this->assertSame(
            $expected,
            $declaring,
            "{$viewedFrom}::{$method}() {$because}; it is declared by {$declaring}"
        );
    }
}
