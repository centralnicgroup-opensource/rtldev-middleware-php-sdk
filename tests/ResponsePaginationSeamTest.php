<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\AbstractResponse;
use CNIC\CNR\Response as CNRResponse;
use CNIC\IBS\Response as IBSResponse;
use CNIC\Paginator;
use CNIC\ResponseInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Locks the pagination seam at the wire (RSRMID-2912 declined, RSRMID-2918
 * delivered, RSRMID-2943 narrowed, RSRMID-2965 relocated the derivation).
 *
 * **Directive.** The seam is drawn at the wire: a brand Response declares
 * exactly the four methods that read its own pagination metadata
 * (getFirstRecordIndex, getLastRecordIndex, getRecordsTotalCount,
 * getRecordsLimitation) and nothing else. Every derivation from those four
 * answers is shared, written once, and never reimplemented per brand — since
 * RSRMID-2965 on the {@see Paginator} value object, which `AbstractResponse`
 * only assembles.
 *
 * **Failure mode prevented.** A brand that answers a derived question itself —
 * whether through a hoisted base default, a shared trait, or a hand-rolled
 * `hasNextPage()` of its own — reports "one page, no next page" for a list that
 * genuinely has more, and a consumer paging through it loses pages 2..N with no
 * error anywhere. The RSRMID-2943 history below is what that looks like in
 * practice: two arithmetic paths over the same four numbers, agreeing only when
 * FIRST happened to be a multiple of LIMIT.
 *
 * **Why the guard must be structural.** Every erosion this refuses is
 * behaviour-preserving on the day it lands. Hoisting single-page defaults onto
 * the base matches what IBS already answers, so no behavioural test can
 * distinguish "IBS answered this itself" from "IBS inherited a default that
 * happens to match". A brand re-declaring `hasNextPage()` with the same
 * arithmetic passes every existing assertion — right up to the first wire shape
 * where the copy and the original disagree. Only reflection can tell them apart.
 *
 * **Note on the assertion that is NOT made here.** Re-pointing the old
 * ownership assertion at Paginator — `assertDeclaredBy(Paginator, Paginator, …)`
 * — would be vacuous: Paginator is `final`, so there is nowhere else the method
 * could be declared, which makes it an existence check dressed as an ownership
 * check (and if the method were absent, ReflectionMethod would throw before the
 * comparison ran). It would stay green while a brand reintroduced its own
 * `hasNextPage()`. The load-bearing half is therefore the **negative on the
 * brands** in {@see testNoBrandDeclaresADerivedGetter()}.
 *
 * **Revisit condition.** A brand whose "more results" signal is a cursor or
 * opaque token rather than a record offset. For such a brand `hasNextPage()`
 * genuinely becomes a wire read again (whatever the cursor field is called), and
 * it belongs back in PRIMITIVES. RSRMID-2965 did **not** invoke that condition:
 * both brands still page by offset, and the derivation only moved further out of
 * the brands' reach.
 *
 * **History.** RSRMID-2912 first proposed hoisting defaults and was declined,
 * closed as Cancelled; RSRMID-2918 delivered this guard with 7 primitives (the
 * four metadata readers plus getCurrentPageNumber/hasNextPage/hasPreviousPage)
 * and 5 derived getters. RSRMID-2943 found that split had pinned a
 * misclassification — those three read no wire metadata, so pinning them as
 * "primitives" protected nothing while forcing brands to hand-roll arithmetic
 * that could (and, for CNR, did) disagree with the equivalent page-number
 * getters on an unaligned offset window — and narrowed it to 4 primitives / 8
 * derived. RSRMID-2965 moved 6 of those 8 off the response entirely: they need
 * no payload, no columns and no records, so requiring a hand-authored API
 * response to exercise an offset grid was the last thing tying them to a
 * Response. Full account in docs/agents/architecture.md.
 */
final class ResponsePaginationSeamTest extends TestCase
{
    /**
     * Pagination primitives that read a brand's own wire metadata and must be
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
     * The derivations that moved to {@see Paginator} in RSRMID-2965, and must
     * not reappear on a response by any route.
     *
     * `getPagination()` is deliberately **not** in this list: it stays on
     * ResponseInterface, so every brand inherits it and a hasMethod() check
     * would fail spuriously. It is covered by
     * {@see testAbstractResponseOwnsWhatIsLeft()} instead.
     * @var string[]
     */
    private const array DERIVED_GETTERS = [
        "getCurrentPageNumber",
        "getNextPageNumber",
        "getNumberOfPages",
        "getPreviousPageNumber",
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
     * The load-bearing half: no brand Response answers a derived question, by
     * any route.
     *
     * `hasMethod()` rather than `getDeclaringClass()` on purpose — it is true for
     * a method arriving from anywhere: declared on the brand, inherited from a
     * hoisted base default, composed in through a trait, or re-added to
     * ResponseInterface (which would force every brand to implement it). All
     * four are the same defect from a consumer's seat, and this is the assertion
     * the old shape could not make, because back then the base legitimately had
     * these methods and every brand inherited them.
     */
    public function testNoBrandDeclaresADerivedGetter(): void
    {
        foreach ([CNRResponse::class, IBSResponse::class] as $brand) {
            $reflection = new ReflectionClass($brand);
            foreach (self::DERIVED_GETTERS as $method) {
                $this->assertFalse(
                    $reflection->hasMethod($method),
                    "{$brand}::{$method}() must not exist: the derivation belongs to CNIC\\Paginator "
                    . "and is shared by every brand (RSRMID-2965). A brand answering it — by declaring "
                    . "it, inheriting a base default, using a trait, or having it re-added to "
                    . "ResponseInterface — is the drift this guard refuses."
                );
            }
        }
    }

    /**
     * The positive half that is still worth asserting: the two pagination-facing
     * members that stayed are owned by the shared base, not by a brand.
     *
     * `getPagination()` is where the four brand primitives meet the shared
     * arithmetic, and `getRecordsCount()` is the row count `getRecord()`
     * bounds-checks against. Either one migrating into a brand would let that
     * brand assemble its own paginator, or count rows its own way, which is the
     * same erosion by a different door.
     */
    public function testAbstractResponseOwnsWhatIsLeft(): void
    {
        foreach (["getPagination", "getRecordsCount"] as $method) {
            $this->assertDeclaredBy(
                AbstractResponse::class,
                AbstractResponse::class,
                $method,
                "must be declared on the shared base so no brand assembles its own pagination"
            );
        }
    }

    /**
     * Paginator must stay constructible without a wire payload: a `final` class
     * whose constructor takes builtin scalars only.
     *
     * This is the assertion the pre-RSRMID-2965 shape could not make at all, and
     * it pins what the extraction was *for*. The arithmetic is now testable by
     * writing five integers (see tests/PaginatorTest.php) instead of
     * hand-authoring an API response that carries four of them — but only while
     * nothing in the constructor needs a Response. A `fromResponse()` convenience
     * constructor, or a `ResponseInterface` parameter added "just for
     * convenience", would re-couple the two and quietly take that back, so any
     * non-builtin parameter type fails here.
     */
    public function testPaginatorIsConstructibleWithoutAResponse(): void
    {
        $reflection = new ReflectionClass(Paginator::class);
        $this->assertTrue($reflection->isFinal(), "Paginator is a value object, not an extension point");

        foreach ((new ReflectionMethod(Paginator::class, "__construct"))->getParameters() as $param) {
            $type = $param->getType();
            $this->assertInstanceOf(
                ReflectionNamedType::class,
                $type,
                "Paginator::__construct() parameter \${$param->getName()} must have a single named "
                . "builtin type — a union or intersection type is how a class type sneaks in"
            );
            $this->assertTrue(
                $type->isBuiltin(),
                "Paginator::__construct() parameter \${$param->getName()} is typed {$type->getName()}, "
                . "not a builtin: the paginator must be constructible from plain numbers, with no "
                . "reference to a Response (RSRMID-2965)"
            );
        }
    }

    /**
     * Assert which class owns $method as seen from $viewedFrom.
     *
     * getDeclaringClass() is the mechanism here: it answers "who actually
     * declared this?" through inheritance, interface implementation and trait
     * composition alike, so it catches erosion arriving by any of those routes —
     * including a SinglePageResponse trait, whose methods reflection attributes
     * to the using class.
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
