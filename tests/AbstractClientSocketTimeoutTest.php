<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST;

use CNIC\AbstractClient;
use CNIC\ClientFactory as CF;
use CNIC\Exception\InvalidConfigurationException;
use CNICTEST\Support\SpyTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the request timeout setter (RSRMID-2919).
 *
 * Until now the request timeout could not be changed from outside the SDK at
 * all: $socketTimeout is a protected property on AbstractSocketConfig with only
 * a getter, the SocketConfig has no public accessor on the client, and
 * CURLOPT_TIMEOUT passed through setExtraCurlOptions() was discarded by the
 * transport. Closing that gap is independent of the precedence decision, so it
 * is tested separately from the cURL option bag.
 *
 * The assertions go through a spy TransportInterface (the RSRMID-2910 seam) so
 * they check the timeout that actually reaches the transport, not merely the
 * value stored on the config.
 *
 * Why a spy and not a cassette, given that this drives request(): the cassettes
 * record raw response bytes to replay an API conversation, and nothing about a
 * recorded exchange can show what timeout the client handed the transport. The
 * subject here is the arguments, not the conversation — so the same injection
 * seam is used with a recording double instead. Keep genuine API-behaviour
 * tests on cassettes; this is not one.
 */
final class AbstractClientSocketTimeoutTest extends TestCase
{
    /**
     * @return array<string, array{0: \Closure(): AbstractClient}>
     */
    public static function brandProvider(): array
    {
        return [
            "CNR" => [static fn(): AbstractClient => CF::cnr()],
            "IBS" => [static fn(): AbstractClient => CF::ibs()],
            "MONIKER" => [static fn(): AbstractClient => CF::moniker()],
        ];
    }

    /**
     * @param \Closure(): AbstractClient $factory
     */
    #[DataProvider("brandProvider")]
    public function testDefaultSocketTimeoutIsUnchanged(\Closure $factory): void
    {
        // 300s has always been the default; adding a setter must not move it.
        $this->assertSame(300, $factory()->getSocketTimeout());
    }

    /**
     * @param \Closure(): AbstractClient $factory
     */
    #[DataProvider("brandProvider")]
    public function testSetSocketTimeoutIsReadBack(\Closure $factory): void
    {
        $cl = $factory();
        $cl->setSocketTimeout(45);
        $this->assertSame(45, $cl->getSocketTimeout());
    }

    public function testSetSocketTimeoutIsFluent(): void
    {
        $cl = CF::cnr();
        $this->assertSame($cl, $cl->setSocketTimeout(10));
    }

    /**
     * The point of the setter: the value must reach the transport, which is
     * where the old CURLOPT_TIMEOUT route silently failed.
     */
    public function testSocketTimeoutReachesTheTransport(): void
    {
        $spy = new SpyTransport();
        $cl = CF::cnr();
        $cl->setTransport($spy)->useOTESystem();
        $cl->setSocketTimeout(7);
        $cl->request(["COMMAND" => "StatusAccount"]);

        $this->assertSame(7, $spy->timeout);
    }

    public function testDefaultSocketTimeoutReachesTheTransport(): void
    {
        $spy = new SpyTransport();
        $cl = CF::cnr();
        $cl->setTransport($spy)->useOTESystem();
        $cl->request(["COMMAND" => "StatusAccount"]);

        $this->assertSame(300, $spy->timeout);
    }

    /**
     * Two routes now lead to the request timeout. They are not in conflict —
     * both are caller-initiated — but the precedence is documented, so pin it:
     * the client hands the config's value to the transport as the $timeout
     * argument and the bag's CURLOPT_TIMEOUT alongside it, and the transport
     * lets the caller's option win (asserted on the wire in
     * {@see \CNICTEST\Functional\HttpTransportTest}).
     */
    public function testBothTimeoutRoutesReachTheTransport(): void
    {
        $spy = new SpyTransport();
        $cl = CF::cnr();
        $cl->setTransport($spy)->useOTESystem();
        $cl->setSocketTimeout(7)->setExtraCurlOptions([CURLOPT_TIMEOUT => 3]);
        $cl->request(["COMMAND" => "StatusAccount"]);

        $this->assertSame(7, $spy->timeout);
        $this->assertSame(3, $spy->options[CURLOPT_TIMEOUT] ?? null);
    }

    /**
     * 0 is cURL's "no timeout" and a legitimate ask for a long-running command;
     * it must not be mistaken for "unset" and replaced by the default.
     */
    public function testZeroMeansNoTimeoutAndIsPreserved(): void
    {
        $spy = new SpyTransport();
        $cl = CF::cnr();
        $cl->setTransport($spy)->useOTESystem();
        $cl->setSocketTimeout(0);
        $cl->request(["COMMAND" => "StatusAccount"]);

        $this->assertSame(0, $cl->getSocketTimeout());
        $this->assertSame(0, $spy->timeout);
    }

    /**
     * A negative timeout must not be accepted. Verified against PHP 8.3.31:
     * curl_setopt() returns false for CURLOPT_TIMEOUT => -5, and
     * curl_setopt_array()'s return value is not inspected, so forwarding it
     * would drop the setting with no signal whatsoever — the exact failure this
     * whole change removes. Rejecting at the setter puts the error where the
     * mistake is.
     */
    public function testNegativeTimeoutIsRejected(): void
    {
        $cl = CF::cnr();
        $this->expectException(InvalidConfigurationException::class);
        $cl->setSocketTimeout(-5);
    }

    public function testRejectedTimeoutLeavesTheConfiguredValueIntact(): void
    {
        $cl = CF::cnr();
        $cl->setSocketTimeout(20);
        try {
            $cl->setSocketTimeout(-1);
        } catch (InvalidConfigurationException) {
            // expected
        }
        $this->assertSame(20, $cl->getSocketTimeout(), "a rejected value must not have been applied");
    }
}
