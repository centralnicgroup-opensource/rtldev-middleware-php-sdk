<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\ClientFactory as CF;
use CNIC\CNR\SessionClient;
use CNIC\HttpTransport;
use CNIC\TransportInterface;
use CNICTEST\Support\SpyTransport;
use PHPUnit\Framework\TestCase;

/**
 * Locks the transport injection seam (RSRMID-2910): the request() lifecycle
 * must run against any TransportInterface, not only the hard-wired
 * HttpTransport, so it can be exercised offline. Verified without a network —
 * a canned in-memory transport is injected and its bytes are asserted to flow
 * back through the brand's translate()/newResponse() pipeline.
 */
final class TransportSeamTest extends TestCase
{
    public function testDefaultTransportIsHttpTransport(): void
    {
        // The default newTransport() hook must yield a real HttpTransport so
        // production behaviour is unchanged when nothing is injected.
        $this->assertInstanceOf(HttpTransport::class, (new SessionClient())->getTransport());
    }

    public function testSetTransportIsReadableBack(): void
    {
        $cl = CF::cnr();
        $spy = new SpyTransport();
        $this->assertSame($spy, $cl->setTransport($spy)->getTransport());
    }

    /**
     * The command-to-wire assertion the seam existed to make possible
     * (RSRMID-2940): performRequest() encodes the built command with
     * getPOSTData() and hands the result to the transport, but until
     * SpyTransport recorded `$data` the two halves were only ever asserted
     * apart — the payload in {@see \CNICTEST\CNR\ClientTest::testGetPostDataSecured()}
     * and the delivery here. A rewrite of either half alone would have kept
     * both green.
     */
    public function testTheBytesOnTheWireAreWhatGetPostDataProduced(): void
    {
        $cl = CF::cnr();
        $cl->setCredentials("test.user", "test.pw");
        $spy = new SpyTransport();
        $cl->setTransport($spy)->useOTESystem();

        $cl->request(["COMMAND" => "StatusAccount"]);

        $this->assertSame(
            $cl->getPOSTData(["COMMAND" => "StatusAccount"]),
            $spy->data,
            "the client must post exactly what getPOSTData() encodes for the same command."
        );
        // Unmasked: masking is for the debug log, never for the wire.
        $this->assertStringContainsString("test.pw", $spy->data);
    }

    /**
     * The client's close() is pure delegation, and nothing exercised it — every
     * close() in the suite ran against a transport instance directly, so the
     * client-to-transport hop was unasserted (RSRMID-2940).
     */
    public function testClientCloseDelegatesToTheTransport(): void
    {
        $spy = new SpyTransport();
        $cl = CF::cnr()->setTransport($spy);
        $this->assertFalse($spy->closed);

        $cl->close();

        $this->assertTrue($spy->closed, "AbstractClient::close() must close the injected transport.");
    }

    public function testSetTransportInjectsAndIsFluent(): void
    {
        $cl = CF::cnr();
        $fake = new class implements TransportInterface {
            /**
             * @param array<int, mixed> $options
             * @return array{0: string, 1: string|null}
             */
            #[\Override]
            public function post(string $url, string $data, int $timeoutSeconds, string $userAgent, array $options = []): array
            {
                // canned CNR wire response for a successful CheckDomains
                $raw = "[RESPONSE]\r\nCODE=200\r\nDESCRIPTION=Command completed successfully\r\n"
                    . "PROPERTY[DOMAINCHECK][0]=210 Domain name is available\r\n"
                    . "QUEUETIME=0\r\nRUNTIME=0.1\r\nEOF\r\n";
                return [$raw, null];
            }
            #[\Override]
            public function close(): void
            {
            }
        };

        $ret = $cl->setTransport($fake);
        $this->assertSame($cl, $ret, "setTransport() must be fluent");

        $cl->useOTESystem();
        $r = $cl->request(["COMMAND" => "CheckDomains", "DOMAIN" => ["example.com"]]);
        $this->assertTrue($r->isSuccess());
        $this->assertSame(200, $r->getCode());
        $this->assertNotNull($r->getColumn("DOMAINCHECK"));
    }

    /**
     * RSRMID-2937: TransportInterface::post()'s contract states that a
     * non-null element [1] means [0] is unusable. A transport that (against
     * the contract's spirit, but not forbidden by the type system) returns
     * real parseable bytes alongside a non-null error must still lose — the
     * httperror template wins and the bytes are discarded.
     */
    public function testTransportErrorDiscardsParseableBytes(): void
    {
        $cl = CF::cnr();
        $fake = new class implements TransportInterface {
            /**
             * @param array<int, mixed> $options
             * @return array{0: string, 1: string|null}
             */
            #[\Override]
            public function post(string $url, string $data, int $timeoutSeconds, string $userAgent, array $options = []): array
            {
                $raw = "[RESPONSE]\r\nCODE=200\r\nDESCRIPTION=Command completed successfully\r\nEOF\r\n";
                return [$raw, "Could not resolve host: example.invalid"];
            }
            #[\Override]
            public function close(): void
            {
            }
        };

        $cl->setTransport($fake)->useOTESystem();
        $r = $cl->request(["COMMAND" => "StatusAccount"]);

        $this->assertSame(421, $r->getCode());
        $this->assertStringContainsString("Could not resolve host: example.invalid", $r->getDescription());
    }
}
