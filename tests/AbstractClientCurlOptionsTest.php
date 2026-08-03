<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST;

use CNIC\AbstractClient;
use CNIC\AbstractSocketConfig;
use CNIC\ClientFactory as CF;
use CNIC\CNR\Client as CNRClient;
use CNIC\Exception\UnsupportedFeatureException;
use CNIC\IBS\Client as IBSClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for per-client cURL option tuning (RSRMID-2913):
 * setExtraCurlOptions() / resetCurlOptions(), and the SDK-managed keys those
 * refuse (RSRMID-2921).
 *
 * These run without any live API — they only mutate and introspect the protected
 * $curlOptions bag, which since RSRMID-2921 lives on the SocketConfig with the rest
 * of the connection configuration. The bag is read back via reflection (the same
 * pattern used by AbstractClientIDNTest) so the tests do not depend on the
 * client's forwarders alone.
 *
 * Scope note (RSRMID-2919): "in the bag" is not "on the wire", and this file only
 * proves the former. Every assertion here passed while the transport was silently
 * discarding half the options it was handed, which is precisely how that went
 * unnoticed for two releases. Assertions about what actually reaches cURL belong
 * in CNICTEST\HttpTransportCurlOptionsTest (the transport's own rejection guard,
 * offline) and CNICTEST\Functional\HttpTransportTest (the effective merge, over a
 * loopback socket). Keep it that way — do not "strengthen" a test here into
 * implying wire behaviour it cannot observe.
 *
 * The rejection tests below are the exception that proves the rule: they assert
 * that a value cannot be put in the bag at all, which is observable from here
 * precisely because nothing has to be sent for it to be true.
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
     * Read the protected $curlOptions bag off a client's config.
     * @return array<int, mixed>
     */
    private function curlOptions(AbstractClient $cl): array
    {
        $p = new \ReflectionProperty(AbstractSocketConfig::class, "curlOptions");
        /** @var array<int, mixed> $opts */
        $opts = $p->getValue($cl->getSocketConfig());
        return $opts;
    }

    public function testCnrDefaultCurlOptsIsEmpty(): void
    {
        $this->assertSame([], $this->curlOptions($this->cnr()));
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
        $this->assertSame([], $this->curlOptions($this->ibs()));
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
            $this->curlOptions($cl)
        );
    }

    /**
     * Two unmanaged keys, deliberately: the bag is for options the SDK has no
     * opinion about, and this is what merging into it looks like.
     */
    public function testSetExtraCurlOptionsMergesOverTheBag(): void
    {
        $cl = $this->cnr();
        $cl->setExtraCurlOptions([CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);

        $this->assertSame(5, $this->curlOptions($cl)[CURLOPT_CONNECTTIMEOUT]);
        $this->assertSame(CURL_IPRESOLVE_V4, $this->curlOptions($cl)[CURLOPT_IPRESOLVE]);

        // a later call merges, overriding only the colliding keys
        $cl->setExtraCurlOptions([CURLOPT_CONNECTTIMEOUT => 9]);
        $this->assertSame(9, $this->curlOptions($cl)[CURLOPT_CONNECTTIMEOUT]);
        $this->assertSame(CURL_IPRESOLVE_V4, $this->curlOptions($cl)[CURLOPT_IPRESOLVE]);
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
        $this->assertSame(CURL_IPRESOLVE_V6, $this->curlOptions($cl)[CURLOPT_IPRESOLVE]);
    }

    public function testSetExtraCurlOptionsIsFluent(): void
    {
        $cl = $this->cnr();
        $this->assertSame($cl, $cl->setExtraCurlOptions([CURLOPT_CONNECTTIMEOUT => 3]));
    }

    public function testResetCurlOptionsRestoresCnrEmptyDefault(): void
    {
        $cl = $this->cnr();
        $cl->setExtraCurlOptions([CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
        $cl->resetCurlOptions();
        $this->assertSame([], $this->curlOptions($cl));
    }

    /**
     * reset() discards caller options and restores the brand default — which,
     * since RSRMID-2915, is empty for IBS/Moniker too. Note the consequence for
     * anyone migrating: an explicitly opted-in IPRESOLVE is caller state, so
     * resetCurlOptions() drops it and it must be set again.
     *
     * What it must *not* drop is the proxy or the referer; those stopped being
     * bag keys in RSRMID-2921 and are asserted in
     * {@see AbstractClientConfigDriftTest::testResettingCurlOptionsKeepsTheProxyAndReferer()}.
     */
    public function testResetCurlOptionsRestoresIbsEmptyDefault(): void
    {
        $cl = $this->ibs();
        $cl->setExtraCurlOptions([CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
        $cl->resetCurlOptions();

        $this->assertSame([], $this->curlOptions($cl));
    }

    public function testResetCurlOptionsIsFluent(): void
    {
        $cl = $this->ibs();
        $this->assertSame($cl, $cl->resetCurlOptions());
    }

    /**
     * The managed set is a deliberate, closed list: every entry is a setting the
     * SDK already models with a setter of its own, so a bag key carrying a second
     * value would put two answers behind one question — `getProxy()` reporting
     * what `setProxy()` stored while the wire carried the bag's value.
     *
     * Pinning the exact list on purpose, as with
     * {@see \CNIC\HttpTransport::PROTECTED_OPTIONS}: widening it takes away a
     * caller's legitimate tuning, narrowing it re-opens a second home for a
     * value. Either direction has to be a deliberate edit, and the commit doing
     * it should name the setter that gains or loses ownership.
     */
    public function testManagedOptionsAreExactlyTheOnesWithTheirOwnSetter(): void
    {
        $this->assertSame(
            [
                CURLOPT_TIMEOUT,
                CURLOPT_USERAGENT,
                CURLOPT_PROXY,
                CURLOPT_REFERER,
            ],
            array_keys(AbstractSocketConfig::MANAGED_OPTIONS)
        );
    }

    /**
     * The constant doubles as the lookup used to build the rejection message, so
     * every entry must carry its own constant's name — a mismatch would point the
     * caller at the wrong option — and a setter that actually exists.
     */
    public function testManagedOptionsNameTheirConstantAndAnExistingSetter(): void
    {
        foreach (AbstractSocketConfig::MANAGED_OPTIONS as $opt => $entry) {
            $this->assertTrue(defined($entry["option"]), "{$entry['option']} is not a defined constant");
            $this->assertSame($opt, constant($entry["option"]), "{$entry['option']} does not name option {$opt}");

            $setter = rtrim($entry["setter"], "()");
            $this->assertTrue(
                method_exists(AbstractSocketConfig::class, $setter) || method_exists(AbstractClient::class, $setter),
                "{$entry['setter']} is named as the owner of {$entry['option']} but exists on neither the "
                . "config nor the client"
            );
        }
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function managedOptionProvider(): array
    {
        return [
            "CURLOPT_TIMEOUT" => [CURLOPT_TIMEOUT, "setSocketTimeout()"],
            "CURLOPT_USERAGENT" => [CURLOPT_USERAGENT, "setUserAgent()"],
            "CURLOPT_PROXY" => [CURLOPT_PROXY, "setProxy()"],
            "CURLOPT_REFERER" => [CURLOPT_REFERER, "setReferer()"],
        ];
    }

    /**
     * Rejection, not a silent winner. Before RSRMID-2921 all four landed in the
     * bag and quietly beat the setter that owns them on the wire, so `getProxy()`
     * and the request disagreed with no signal anywhere.
     */
    #[DataProvider("managedOptionProvider")]
    public function testManagedOptionsAreRejectedFromTheBag(int $opt, string $setter): void
    {
        $cl = $this->cnr();
        try {
            $cl->setExtraCurlOptions([$opt => "whatever"]);
            $this->fail("expected UnsupportedFeatureException for option {$opt}");
        } catch (UnsupportedFeatureException $e) {
            $this->assertStringContainsString($setter, $e->getMessage(), "the message must name the setter to use");
        }
    }

    public function testRejectionMessageListsEveryOffendingOption(): void
    {
        $cl = $this->cnr();
        try {
            $cl->setExtraCurlOptions([CURLOPT_PROXY => "p", CURLOPT_REFERER => "r"]);
            $this->fail("expected UnsupportedFeatureException");
        } catch (UnsupportedFeatureException $e) {
            $this->assertStringContainsString("CURLOPT_PROXY", $e->getMessage());
            $this->assertStringContainsString("CURLOPT_REFERER", $e->getMessage());
        }
    }

    /**
     * A rejected call must not have applied its unmanaged keys either: a partial
     * merge would leave the caller with a bag they never asked for and an
     * exception saying the call failed.
     */
    public function testARejectedCallAppliesNothing(): void
    {
        $cl = $this->cnr();
        try {
            $cl->setExtraCurlOptions([CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, CURLOPT_PROXY => "p"]);
        } catch (UnsupportedFeatureException) {
            // expected
        }
        $this->assertSame([], $this->curlOptions($cl), "the whole call is refused, not merged in part");
        $this->assertNull($cl->getProxy(), "and the managed value is not applied through the back door");
    }

    /**
     * The rejection is eager — at the setter, not on the next request — because
     * the config knows immediately and the error belongs where the mistake is.
     * The transport's own protected set is checked later, on the request, since
     * which options *it* owns is its business (and it is injectable).
     */
    public function testManagedOptionRejectionDoesNotNeedARequest(): void
    {
        $cl = $this->cnr();
        // no transport injected, no URL reachable: if this needed a request it
        // could not throw the SDK exception here.
        $this->expectException(UnsupportedFeatureException::class);
        $cl->setExtraCurlOptions([CURLOPT_USERAGENT => "sneaky/1.0"]);
    }

    /**
     * Options the SDK does not model stay caller-owned. CURLOPT_CONNECTTIMEOUT is
     * the interesting one: the transport sets a default for it and has no setter,
     * so the bag is the supported route and must keep working.
     */
    public function testUnmanagedOptionsPassThrough(): void
    {
        $cl = $this->cnr();
        $cl->setExtraCurlOptions([CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V6]);
        $this->assertSame(
            [CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V6],
            $this->curlOptions($cl)
        );
    }
}
