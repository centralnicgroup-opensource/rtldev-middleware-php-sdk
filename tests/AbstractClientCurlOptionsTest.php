<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST;

use CNIC\AbstractClient;
use CNIC\ClientFactory as CF;
use CNIC\CNR\Client as CNRClient;
use CNIC\IBS\Client as IBSClient;
use CNIC\MONIKER\Client as MonikerClient;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the per-client cURL option tuning on AbstractClient
 * (RSRMID-2913): setExtraCurlOptions() / resetCurlOptions().
 *
 * These run without any live API — they only mutate and introspect the
 * protected $curlopts bag. The bag is read back via reflection (the same
 * pattern used by AbstractClientIDNTest) so the tests do not depend on the
 * key-specific getProxy()/getReferer() accessors alone.
 */
final class AbstractClientCurlOptionsTest extends TestCase
{
    private function cnr(): CNRClient
    {
        return CF::cnr();
    }

    private function ibs(): IBSClient
    {
        return CF::ibs();
    }

    /**
     * Read the protected $curlopts bag off a client.
     * @return array<int, mixed>
     */
    private function curlopts(AbstractClient $cl): array
    {
        $p = new \ReflectionProperty($cl, "curlopts");
        $p->setAccessible(true);
        /** @var array<int, mixed> $opts */
        $opts = $p->getValue($cl);
        return $opts;
    }

    public function testCnrDefaultCurlOptsIsEmpty(): void
    {
        $this->assertSame([], $this->curlopts($this->cnr()));
    }

    /**
     * IBS/Moniker must ship NO transport defaults either (RSRMID-2915).
     *
     * IBS used to seed CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, a workaround for
     * a handful of customers whose hosts misbehaved over IPv6. Hard-coding one
     * network's workaround into every integration is the wrong altitude —
     * choosing a resolution mode is the caller's call, via
     * setExtraCurlOptions(). Every brand now starts from an empty bag.
     */
    public function testIbsDefaultCurlOptsIsEmpty(): void
    {
        $this->assertSame([], $this->curlopts($this->ibs()));
    }

    /**
     * The removed default must remain reachable as an explicit opt-in, since
     * that is the migration path handed to affected integrations.
     */
    public function testIpv4CanStillBeForcedExplicitly(): void
    {
        $cl = $this->ibs();
        $cl->setExtraCurlOptions([CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
        $this->assertSame(
            [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            $this->curlopts($cl)
        );
    }

    public function testSetExtraCurlOptionsMergesOverTheBag(): void
    {
        $cl = $this->cnr();
        $cl->setExtraCurlOptions([CURLOPT_TIMEOUT => 5, CURLOPT_PROXY => "10.0.0.1"]);

        // merged into the live bag and observable via the key-specific getter
        $this->assertSame("10.0.0.1", $cl->getProxy());
        $this->assertSame(5, $this->curlopts($cl)[CURLOPT_TIMEOUT]);

        // a later call merges, overriding only the colliding keys
        $cl->setExtraCurlOptions([CURLOPT_TIMEOUT => 9]);
        $this->assertSame(9, $this->curlopts($cl)[CURLOPT_TIMEOUT]);
        $this->assertSame("10.0.0.1", $cl->getProxy());
    }

    /**
     * A caller's value must win on key collision. Set up the collision
     * explicitly now that no brand seeds one for us (RSRMID-2915).
     */
    public function testSetExtraCurlOptionsUserValueWinsOnCollision(): void
    {
        $cl = $this->ibs();
        $cl->setExtraCurlOptions([CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
        $cl->setExtraCurlOptions([CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V6]);
        $this->assertSame(CURL_IPRESOLVE_V6, $this->curlopts($cl)[CURLOPT_IPRESOLVE]);
    }

    public function testSetExtraCurlOptionsIsFluent(): void
    {
        $cl = $this->cnr();
        $this->assertSame($cl, $cl->setExtraCurlOptions([CURLOPT_TIMEOUT => 3]));
    }

    public function testResetCurlOptionsRestoresCnrEmptyDefault(): void
    {
        $cl = $this->cnr();
        $cl->setExtraCurlOptions([CURLOPT_TIMEOUT => 5])->setProxy("127.0.0.1");
        $cl->resetCurlOptions();
        $this->assertSame([], $this->curlopts($cl));
        $this->assertNull($cl->getProxy());
    }

    /**
     * reset() discards caller options and restores the brand default — which,
     * since RSRMID-2915, is empty for IBS/Moniker too. Note the consequence for
     * anyone migrating: an explicitly opted-in IPRESOLVE is caller state, so
     * resetCurlOptions() drops it and it must be set again.
     */
    public function testResetCurlOptionsRestoresIbsEmptyDefault(): void
    {
        $cl = $this->ibs();
        $cl->setExtraCurlOptions([CURLOPT_TIMEOUT => 5, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
        $cl->resetCurlOptions();

        $this->assertSame([], $this->curlopts($cl));
    }

    /**
     * The getDefaultCurlOpts() hook itself stays — it is the seam a brand or a
     * subclass can still use, and resetCurlOptions() is defined in terms of it.
     * Only IBS's override was removed, so every brand must now resolve to the
     * base's empty default.
     *
     * This bans overrides unconditionally on purpose: the bar is a genuinely
     * protocol-mandatory option, not a workaround for one environment's
     * networking (which is what the removed IPv4 default was). If a future brand
     * clears that bar, updating this test is part of the change — and the commit
     * doing so should say why the option is mandatory.
     */
    public function testNoBrandOverridesTheDefaultCurlOptsHook(): void
    {
        foreach ([CNRClient::class, IBSClient::class, MonikerClient::class] as $brand) {
            $declaring = (new \ReflectionMethod($brand, "getDefaultCurlOpts"))
                ->getDeclaringClass()->getName();
            $this->assertSame(
                AbstractClient::class,
                $declaring,
                "{$brand} must inherit the empty default cURL options from AbstractClient; "
                . "{$declaring} overrides getDefaultCurlOpts() — see RSRMID-2915"
            );
        }
    }

    public function testResetCurlOptionsIsFluent(): void
    {
        $cl = $this->ibs();
        $this->assertSame($cl, $cl->resetCurlOptions());
    }
}
