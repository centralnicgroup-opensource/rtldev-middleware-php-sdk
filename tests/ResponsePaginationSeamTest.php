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
 * Locks the pagination seam of AbstractResponse (RSRMID-2912, declined).
 *
 * The 7 pagination primitives are declared on ResponseInterface and left
 * unimplemented on AbstractResponse on purpose, so a brand that forgets them
 * fails at declaration time instead of silently reporting "one page, no next
 * page" and losing pages 2..N of a list. The counterpart is that the 5 derived
 * getters, which are pure functions of those primitives, DO live on the base.
 *
 * This is a structural test by necessity, not by preference: hoisting
 * single-page defaults onto the base is behaviour-preserving (the defaults
 * would return exactly what IBS\Response returns today), so no behavioural
 * test can ever detect the erosion — only reflection can.
 *
 * Deleting or weakening this test is therefore a deliberate act: it requires
 * reopening the RSRMID-2912 conversation, not a passing cleanup.
 */
final class ResponsePaginationSeamTest extends TestCase
{
    /**
     * Pagination primitives that read brand-specific columns/status and must be
     * answered explicitly by every brand.
     *
     * Spelled out on purpose rather than derived from ResponseInterface minus
     * what AbstractResponse implements: a derived list would drop a primitive
     * the moment the base implemented it, so the erosion this test exists to
     * catch would silently redefine the expectation and the test would pass.
     * @var string[]
     */
    private const array PRIMITIVES = [
        "getCurrentPageNumber",
        "getFirstRecordIndex",
        "getLastRecordIndex",
        "getRecordsTotalCount",
        "getRecordsLimitation",
        "hasNextPage",
        "hasPreviousPage",
    ];

    /**
     * Getters derived purely from the primitives above, shared by all brands.
     * @var string[]
     */
    private const array DERIVED_GETTERS = [
        "getNextPageNumber",
        "getNumberOfPages",
        "getPagination",
        "getPreviousPageNumber",
        "getRecordsCount",
    ];

    /**
     * Every brand Response must declare all 7 primitives itself. MONIKER is not
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
