<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\CNR\Response as CNRResponse;
use CNIC\IBS\Response as IBSResponse;
use CNIC\ResponseInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Locks the ResponseInterface contract from a consumer's seat (RSRMID-2918).
 *
 * CLAUDE.md mandates that consumers type against ResponseInterface, not the
 * concrete brand Response. That only works if every documented capability is
 * actually reachable through the interface — a method whose interface
 * declaration is narrower than its implementation is unusable to exactly the
 * consumers the project tells to depend on it.
 *
 * The guard here is deliberately static, not behavioural: the calls below are
 * made on ResponseInterface-typed variables, and both PHPStan L9 and Psalm L1
 * analyse tests/, so narrowing a signature on the interface again fails
 * `composer lint` (PHPStan: "invoked with 1 parameter, 0 required") even though
 * the runtime behaviour would be unchanged. The assertions additionally pin the
 * behaviour a consumer gets.
 */
final class ResponseInterfaceConsumerTest extends TestCase
{
    /**
     * A CNR list response carrying both real columns and pagination columns.
     */
    private function cnrListResponse(): ResponseInterface
    {
        return new CNRResponse(implode("\n", [
            "[RESPONSE]",
            "CODE=200",
            "DESCRIPTION=Command completed successfully",
            "PROPERTY[TOTAL][0]=2",
            "PROPERTY[FIRST][0]=0",
            "PROPERTY[LAST][0]=1",
            "PROPERTY[COUNT][0]=2",
            "PROPERTY[LIMIT][0]=2",
            "PROPERTY[FIRSTNAME][0]=Adrian",
            "PROPERTY[FIRSTNAME][1]=John",
            "EOF"
        ]));
    }

    /**
     * A consumer holding only the interface sees data columns, and cannot reach
     * response metadata through the column accessors at all.
     *
     * This replaces the guard's original subject. Until RSRMID-2965 the
     * capability tested here was `getColumnKeys(true)`, whose whole reason for
     * existing was that pagination metadata sat in the column pool and consumers
     * needed it filtered back out; the parameter was the drift RSRMID-2918 found
     * (interface declared none, implementation had one) and it is now retired
     * along with the modelling error behind it. What is worth pinning from a
     * consumer's seat is the replacement rule: the column accessors answer about
     * data, and metadata is reached through the pagination and status accessors
     * instead.
     */
    public function testConsumerSeesOnlyDataColumnsThroughTheInterface(): void
    {
        $r = $this->cnrListResponse();

        $keys = $r->getColumnKeys();
        $this->assertContains("FIRSTNAME", $keys);
        foreach (["TOTAL", "COUNT", "LIMIT", "FIRST", "LAST"] as $metaKey) {
            $this->assertNotContains($metaKey, $keys, "metadata is not a column");
            $this->assertNull($r->getColumn($metaKey), "and must not be reachable as one");
            $this->assertNull($r->getColumnIndex($metaKey, 0), "nor cell by cell");
        }
    }

    /**
     * The rule is brand-neutral: it holds on a brand whose metadata keys differ
     * entirely from CNR's.
     */
    public function testItHoldsForEveryBrandThroughTheInterface(): void
    {
        $r = new IBSResponse('{"status":"SUCCESS","domaincount":"1","domain":["example.com"]}');
        $this->assertInstanceOf(ResponseInterface::class, $r);

        $consumer = static fn(ResponseInterface $resp): array => $resp->getColumnKeys();
        $keys = $consumer($r);

        $this->assertContains("domain", $keys);
        $this->assertNotContains("domaincount", $keys, "IBS count key is metadata, not a column");
        $this->assertNotContains("status", $keys, "so is the transaction status");
    }

    /**
     * Pagination stays reachable through the interface — with the metadata gone
     * from the column pool, the primitives are the only route to it, so a
     * consumer typed against the interface must be able to ask.
     *
     * Static, like the rest of this file: the closure's declared parameter and
     * return types are the assertion, and both analysers cover tests/, so a
     * primitive narrowed or dropped from the interface fails `composer lint`
     * rather than only the suite.
     */
    public function testConsumerReachesPaginationThroughTheInterface(): void
    {
        $consumer = static fn(ResponseInterface $resp): array => [
            $resp->getFirstRecordIndex(),
            $resp->getLastRecordIndex(),
            $resp->getRecordsTotalCount(),
            $resp->getRecordsLimitation(),
        ];

        $this->assertSame([0, 1, 2, 2], $consumer($this->cnrListResponse()));
    }

    /**
     * ResponseInterface must not declare a constructor.
     *
     * The other half of the same drift: construction is the job of the brand
     * factory hooks (AbstractClient::newResponse(),
     * AbstractResponseTemplateManager::createResponseFromTemplateId()), each of which builds
     * its own concrete Response — nothing constructs through this interface, so
     * a __construct() declaration here constrains implementers for no caller's
     * benefit.
     *
     * Note on the mechanism, corrected in RSRMID-2923: PHP **does** enforce a
     * constructor declared on an *interface* (an incompatible implementation is
     * a declaration-time fatal). The exemption applies to class-to-class
     * inheritance only. This drift survived for a different reason — the
     * interface declared 3 parameters and AbstractResponse had grown a 4th
     * *optional* one ($context), and adding an optional parameter is legal
     * widening. So the original rationale ("PHP exempts constructors, therefore
     * the declaration constrains nobody") was wrong on both counts; the removal
     * stands on the design ground above instead.
     *
     * Reflection is still the only possible guard: a re-add would be legal PHP
     * (both implementations happen to match), so nothing else can see it.
     * (RSRMID-2918, mechanism corrected in RSRMID-2923.)
     */
    public function testTheInterfaceDeclaresNoConstructor(): void
    {
        $this->assertFalse(
            (new ReflectionClass(ResponseInterface::class))->hasMethod("__construct"),
            "ResponseInterface must not declare __construct() — construction belongs to the "
            . "brand factory hooks, and an interface should describe what a response can be "
            . "asked, not how it is built"
        );
    }
}
