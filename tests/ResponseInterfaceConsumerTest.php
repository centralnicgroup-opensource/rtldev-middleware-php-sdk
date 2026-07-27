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
    private static function cnrListResponse(): ResponseInterface
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
     * getColumnKeys(true) must be callable through the interface.
     *
     * Before RSRMID-2918 the interface declared getColumnKeys() with no
     * parameter while AbstractResponse implemented it with one, so this line
     * was a static-analysis error for any interface-typed consumer even though
     * it ran correctly.
     */
    public function testConsumerCanStripPaginationColumnsThroughTheInterface(): void
    {
        $r = self::cnrListResponse();

        $all = $r->getColumnKeys();
        $this->assertContains("FIRSTNAME", $all);
        $this->assertContains("COUNT", $all, "unfiltered keys must still list pagination columns");

        $filtered = $r->getColumnKeys(true);
        $this->assertContains("FIRSTNAME", $filtered);
        foreach (["TOTAL", "COUNT", "LIMIT", "FIRST", "LAST"] as $paginationKey) {
            $this->assertNotContains($paginationKey, $filtered);
        }
    }

    /**
     * The default must stay "keep everything", so an existing no-argument call
     * through the interface is unaffected by the signature widening.
     */
    public function testOmittingTheArgumentKeepsEveryColumn(): void
    {
        $r = self::cnrListResponse();
        $this->assertSame($r->getColumnKeys(), $r->getColumnKeys(false));
    }

    /**
     * The capability is brand-neutral: it is declared on the shared interface,
     * so it must work on a brand whose pagination keys differ entirely.
     */
    public function testItWorksForEveryBrandThroughTheInterface(): void
    {
        $r = new IBSResponse('{"status":"SUCCESS","domaincount":"1","domain":["example.com"]}');
        $this->assertInstanceOf(ResponseInterface::class, $r);

        $consumer = static fn(ResponseInterface $resp): array => $resp->getColumnKeys(true);
        $filtered = $consumer($r);

        $this->assertContains("domain", $filtered);
        $this->assertNotContains("domaincount", $filtered, "IBS pagination key must be stripped");
    }

    /**
     * ResponseInterface must not declare a constructor.
     *
     * The other half of the same drift: construction is the job of the brand
     * factory hooks (AbstractClient::newResponse(),
     * AbstractResponseTemplateManager::createResponse()), each of which builds
     * its own concrete Response — nothing constructs through this interface. A
     * __construct() declaration here constrains nobody, because PHP exempts
     * constructors from signature-compatibility checks, which is exactly how it
     * drifted unnoticed: it declared 3 parameters while AbstractResponse had
     * grown a 4th ($context).
     *
     * Reflection is the only possible guard — PHP will never complain about the
     * mismatch, and no behavioural test can see it either. (RSRMID-2918.)
     */
    public function testTheInterfaceDeclaresNoConstructor(): void
    {
        $this->assertFalse(
            (new ReflectionClass(ResponseInterface::class))->hasMethod("__construct"),
            "ResponseInterface must not declare __construct() — construction belongs to the "
            . "brand factory hooks, and PHP does not enforce constructor signatures anyway"
        );
    }
}
