<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\ClientFactory as CF;
use CNIC\CNR\SessionClient;
use CNIC\HttpTransport;
use CNIC\TransportInterface;
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
        $probe = new class extends SessionClient {
            public function exposeTransport(): TransportInterface
            {
                return $this->transport;
            }
        };
        $this->assertInstanceOf(HttpTransport::class, $probe->exposeTransport());
    }

    public function testSetTransportInjectsAndIsFluent(): void
    {
        $cl = CF::getClient("CNR");
        $fake = new class implements TransportInterface {
            /**
             * @param array<int, mixed> $options
             * @return array{0: string, 1: string|null}
             */
            #[\Override]
            public function post(string $url, string $data, int $timeout, string $userAgent, array $options = []): array
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
}
