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

    public function testIbsDefaultCurlOptsForcesIpv4(): void
    {
        $this->assertSame(
            [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            $this->curlopts($this->ibs())
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

    public function testSetExtraCurlOptionsUserValueWinsOverBrandDefault(): void
    {
        $cl = $this->ibs();
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

    public function testResetCurlOptionsPreservesIbsForcedIpv4(): void
    {
        $cl = $this->ibs();
        $cl->setExtraCurlOptions([CURLOPT_TIMEOUT => 5, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V6]);
        $cl->resetCurlOptions();

        // reset restores the brand default — it must NOT wipe the bag to []
        // and silently drop IBS/Moniker's mandatory IPv4 resolution.
        $this->assertSame(
            [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            $this->curlopts($cl)
        );
    }

    public function testResetCurlOptionsIsFluent(): void
    {
        $cl = $this->ibs();
        $this->assertSame($cl, $cl->resetCurlOptions());
    }
}
