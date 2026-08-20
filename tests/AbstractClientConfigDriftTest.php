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
use CNIC\Exception\UnsupportedFeatureException;
use CNIC\IBS\SocketConfig as IBSSocketConfig;
use CNIC\System;
use CNICTEST\Support\SpyTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Regression tests for the three configuration drifts RSRMID-2921 closed, plus
 * the three lesser symptoms of the same split.
 *
 * Each of the first three was reproducible at v22.0.0 (b3c20ff) and each is now
 * prevented structurally rather than patched — see {@see ClientConfigSeamTest}
 * for the guard on the structure. These are the behavioural half: they state the
 * defect as an expectation, so a reader can see what used to happen.
 *
 * Assertions about what reaches the transport go through {@see SpyTransport} (the
 * RSRMID-2910 seam) rather than the client's own state, because a configuration
 * value that is stored correctly and not sent is exactly the defect class
 * RSRMID-2919 had to be opened for: "in the bag is not on the wire".
 */
final class AbstractClientConfigDriftTest extends TestCase
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
     * Drift 1 — the system flag and the URL disagreed, permanently.
     *
     * Before: `useOTESystem()->setURL($custom)` left `isOTE()` answering `true`
     * for the rest of the client's life while every request went to `$custom`.
     * The flag was stored on the client and the URL was never consulted again.
     *
     * After: the system is derived from the URL, so there is no flag left to
     * disagree — and an arbitrary URL yields `null`, which is the honest answer.
     * A caller warning "you are on OT&E" now gets it right or gets nothing.
     *
     * @param \Closure(): AbstractClient $factory
     */
    #[DataProvider("brandProvider")]
    public function testACustomUrlCannotLeaveTheSystemFlagLying(\Closure $factory): void
    {
        $cl = $factory();
        $cl->useOTESystem()->setURL("https://example.test/");

        $this->assertFalse($cl->isOTE(), "isOTE() must not still claim OT&E after the URL was replaced");
        $this->assertNull($cl->getSystem(), "an unrecognised endpoint has no OT&E-or-LIVE answer");
        $this->assertSame("https://example.test/", $cl->getURL());
    }

    /**
     * The recognised endpoints still answer, in both directions, and switching
     * back is enough to restore the system — there is no separate flag to reset.
     *
     * @param \Closure(): AbstractClient $factory
     */
    #[DataProvider("brandProvider")]
    public function testSystemAndUrlAgreeOnTheKnownEndpoints(\Closure $factory): void
    {
        $cl = $factory();

        $this->assertSame(System::LIVE, $cl->getSystem(), "LIVE is the default");
        $this->assertSame($cl->getLiveUrl(), $cl->getURL());
        $this->assertFalse($cl->isOTE());

        $cl->useOTESystem();
        $this->assertSame(System::OTE, $cl->getSystem());
        $this->assertSame($cl->getSocketConfig()->getOTEUrl(), $cl->getURL());
        $this->assertTrue($cl->isOTE());

        $cl->setURL("https://example.test/")->useLIVESystem();
        $this->assertSame(System::LIVE, $cl->getSystem());
        $this->assertSame($cl->getLiveUrl(), $cl->getURL());
    }

    /**
     * The URL the request actually goes to must be the one `getURL()` reports.
     * Asserted at the transport, since that is the copy that mattered and the one
     * the old `$socketURL` held on its own.
     */
    public function testTheReportedUrlIsTheUrlRequested(): void
    {
        $spy = new SpyTransport();
        $cl = CF::cnr();
        $cl->setTransport($spy)->setURL("https://example.test/");
        $cl->request(["COMMAND" => "StatusAccount"]);

        $this->assertSame("https://example.test/api/call.cgi", $spy->url);
    }

    /**
     * Drift 1, second half — high-performance routing used to cost the caller the
     * system.
     *
     * Before: the loopback rewrite was written straight into the client's
     * `$socketURL`, so the URL no longer matched the OT&E endpoint and `isOTE()`
     * (had it been derived) would have flipped to false; with the stored flag it
     * instead reported a system the URL contradicted. Either way the two parted.
     *
     * After: routing is a flag applied when the URL is read, so *which* system
     * sits behind the local proxy is untouched by the decision to go through it.
     *
     * @param \Closure(): AbstractClient $factory
     */
    #[DataProvider("brandProvider")]
    public function testHighPerformanceRoutingPreservesTheSelectedSystem(\Closure $factory): void
    {
        $cl = $factory();
        $cl->useOTESystem()->useHighPerformanceConnectionSetup();

        $this->assertTrue($cl->isOTE(), "routing through a local proxy does not change which system it fronts");
        $this->assertSame(System::OTE, $cl->getSystem());
        $this->assertSame("http://127.0.0.1/", $cl->getURL());
    }

    /**
     * Being a flag rather than a rewrite, high-performance routing is now
     * *readable*. It was not before: the rewrite left nothing behind but a URL a
     * caller would have had to pattern-match to recognise.
     *
     * @param \Closure(): AbstractClient $factory
     */
    #[DataProvider("brandProvider")]
    public function testHighPerformanceRoutingIsReadable(\Closure $factory): void
    {
        $cfg = $factory()->getSocketConfig();
        $this->assertFalse($cfg->usesHighPerformanceConnectionSetup());
        $cfg->useHighPerformanceConnectionSetup();
        $this->assertTrue($cfg->usesHighPerformanceConnectionSetup());
    }

    /**
     * Being a property of *how* to reach the endpoint rather than *which*, the
     * routing survives a later system switch. It did not before — the switch
     * rewrote the URL and silently undid it.
     */
    public function testHighPerformanceRoutingSurvivesASystemSwitch(): void
    {
        $cl = CF::cnr();
        $cl->useHighPerformanceConnectionSetup()->useOTESystem();
        $this->assertSame("http://127.0.0.1/", $cl->getURL());

        $cl->useLIVESystem();
        $this->assertSame("http://127.0.0.1/", $cl->getURL());
        $this->assertSame(System::LIVE, $cl->getSystem());
    }

    /**
     * Only the scheme and host are swapped: a blind string replace would also
     * clobber a hostname recurring in the path or query. Port, path and query are
     * carried over verbatim.
     */
    public function testHighPerformanceRoutingRewritesOnlySchemeAndHost(): void
    {
        $cl = CF::cnr();
        $cl->setURL("https://api.example.com:8443/api.example.com/call.cgi?foo=bar")
            ->useHighPerformanceConnectionSetup();

        $this->assertSame("http://127.0.0.1:8443/api.example.com/call.cgi?foo=bar", $cl->getURL());
    }

    /**
     * A URL with no host has nothing to redirect, so it is returned unchanged
     * (the pre-RSRMID-2921 behaviour, which guarded on the parsed host).
     */
    public function testHighPerformanceRoutingLeavesAHostlessUrlAlone(): void
    {
        $cl = CF::cnr();
        $cl->setURL("/relative/path")->useHighPerformanceConnectionSetup();

        $this->assertSame("/relative/path", $cl->getURL());
    }

    /**
     * Drift 2 — `getURL()` and `getLiveUrl()` answered from different objects: the
     * first from the client's `$socketURL`, the second from the config's
     * `$liveUrl`. Two homes for one subject, so nothing kept them consistent.
     *
     * @param \Closure(): AbstractClient $factory
     */
    #[DataProvider("brandProvider")]
    public function testUrlAndEndpointGettersAnswerFromOneHome(\Closure $factory): void
    {
        $cl = $factory();
        $cfg = $cl->getSocketConfig();

        $this->assertSame($cfg->getURL(), $cl->getURL());
        $this->assertSame($cfg->getLiveUrl(), $cl->getLiveUrl());

        // and the endpoint getters keep answering after the active URL moves on
        $cl->setURL("https://example.test/");
        $this->assertSame($cfg->getLiveUrl(), $cl->getLiveUrl());
        $this->assertSame("https://example.test/", $cl->getURL());
    }

    /**
     * Drift 3 — the proxy lived in the cURL bag, so resetting options dropped it.
     *
     * Before: `setProxy($p)->resetCurlOptions()` left `getProxy()` returning null.
     * `resetCurlOptions()` restores *option* defaults and had no business
     * forgetting the proxy, but it could not tell the two apart.
     *
     * After: proxy and referer are real state; the reset touches options only.
     *
     * @param \Closure(): AbstractClient $factory
     */
    #[DataProvider("brandProvider")]
    public function testResettingCurlOptionsKeepsTheProxyAndReferer(\Closure $factory): void
    {
        $cl = $factory();
        $cl->setProxy("http://proxy.test:8080")
            ->setReferer("https://referer.test/")
            ->setExtraCurlOptions([CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);

        $cl->resetCurlOptions();

        $this->assertSame("http://proxy.test:8080", $cl->getProxy(), "the proxy is not a cURL option default");
        $this->assertSame("https://referer.test/", $cl->getReferer());
        $this->assertSame([], $this->curlOptions($cl), "the option bag itself is back to the brand default");
    }

    /**
     * The dedicated state has to reach the wire, not merely read back — the proxy
     * used to be a bag key precisely because that was the shortest way to send it.
     */
    public function testProxyAndRefererReachTheTransport(): void
    {
        $spy = new SpyTransport();
        $cl = CF::cnr();
        $cl->setTransport($spy)->useOTESystem();
        $cl->setProxy("http://proxy.test:8080")->setReferer("https://referer.test/");
        $cl->request(["COMMAND" => "StatusAccount"]);

        $this->assertSame("http://proxy.test:8080", $spy->options[CURLOPT_PROXY] ?? null);
        $this->assertSame("https://referer.test/", $spy->options[CURLOPT_REFERER] ?? null);
    }

    /**
     * And they must stop reaching it once reset, or "real state" would just be a
     * leak in the other direction. The empty-string argument is the documented
     * reset for both setters.
     */
    public function testResetProxyAndRefererStopReachingTheTransport(): void
    {
        $spy = new SpyTransport();
        $cl = CF::cnr();
        $cl->setTransport($spy)->useOTESystem();
        $cl->setProxy("http://proxy.test:8080")->setReferer("https://referer.test/");
        $cl->setProxy()->setReferer();
        $cl->request(["COMMAND" => "StatusAccount"]);

        $this->assertNull($cl->getProxy());
        $this->assertNull($cl->getReferer());
        $this->assertArrayNotHasKey(CURLOPT_PROXY, $spy->options);
        $this->assertArrayNotHasKey(CURLOPT_REFERER, $spy->options);
    }

    /**
     * The dedicated proxy/referer state must beat the option bag *structurally*,
     * not merely because `setExtraCurlOptions()` refuses those two keys.
     *
     * `getDefaultCurlOpts()` is the hole that argument leaves: it seeds the bag
     * through the constructor without passing the guard, and it is deliberately
     * kept for a "genuinely protocol-mandatory option" (RSRMID-2915). A brand
     * default of CURLOPT_PROXY would otherwise bring drift 3 back inverted —
     * `getProxy()` reporting the setter's value while the request used the
     * default. The subclass below is that brand, written out because no real one
     * exists and the guarantee should not depend on that staying true.
     */
    public function testDedicatedProxyStateBeatsABrandDefaultOnTheWire(): void
    {
        $cfg = new class () extends IBSSocketConfig {
            #[\Override]
            protected function getDefaultCurlOpts(): array
            {
                return [CURLOPT_PROXY => "http://brand-default.test:1080"];
            }
        };

        // the default is in the bag, as the hook intends
        $this->assertSame(
            ["http://brand-default.test:1080"],
            array_values($cfg->getCurlOptions())
        );

        $cfg->setProxy("http://caller.test:8080");
        $this->assertSame("http://caller.test:8080", $cfg->getProxy());
        $this->assertSame(
            "http://caller.test:8080",
            $cfg->getCurlOptions()[CURLOPT_PROXY] ?? null,
            "the value getProxy() reports must be the value handed to the transport"
        );
    }

    /**
     * Symptom — the request timeout had two routes and the bag won, which took 22
     * lines of prose on the client to explain. There is one route now: the bag
     * refuses CURLOPT_TIMEOUT and names the setter that owns it.
     */
    public function testTheTimeoutHasExactlyOneRoute(): void
    {
        $cl = CF::cnr();
        $cl->setSocketTimeout(7);

        try {
            $cl->setExtraCurlOptions([CURLOPT_TIMEOUT => 3]);
            $this->fail("expected UnsupportedFeatureException");
        } catch (UnsupportedFeatureException $e) {
            $this->assertStringContainsString("CURLOPT_TIMEOUT", $e->getMessage());
            $this->assertStringContainsString("setSocketTimeout()", $e->getMessage());
        }

        $spy = new SpyTransport();
        $cl->setTransport($spy)->useOTESystem()->request(["COMMAND" => "StatusAccount"]);
        $this->assertSame(7, $spy->timeout);
        $this->assertArrayNotHasKey(CURLOPT_TIMEOUT, $spy->options);
    }

    /**
     * Symptom — `getUserAgent()` memoised the default into `$ua` on first call: a
     * getter performing a write, firing mid-request. There is nothing worth
     * memoising (a few constants and one `php_uname()`), so it is a pure read now.
     */
    public function testGetUserAgentDoesNotWrite(): void
    {
        $cl = CF::cnr();
        $first = $cl->getUserAgent();

        $p = new ReflectionProperty(AbstractClient::class, "userAgent");
        $this->assertSame("", $p->getValue($cl), "getUserAgent() must not have written the default back");
        $this->assertSame($first, $cl->getUserAgent(), "and must keep answering the same thing");
        $this->assertStringContainsString("PHP-SDK", $first);
    }

    /**
     * An explicitly set user agent still wins, and is what reaches the wire.
     */
    public function testAnExplicitUserAgentWinsAndIsSent(): void
    {
        $spy = new SpyTransport();
        $cl = CF::cnr();
        $cl->setTransport($spy)->useOTESystem()->setUserAgent("MyPlatform", "1.2.3");
        $cl->request(["COMMAND" => "StatusAccount"]);

        $this->assertStringStartsWith("MyPlatform (", $cl->getUserAgent());
        $this->assertSame($cl->getUserAgent(), $spy->userAgent);
    }

    /**
     * Symptom — `setCredentials()` silently discarded an active session. It still
     * does, because the invariant is correct (a session and a password are
     * alternative credentials and the newer one is authoritative) — what was
     * missing was anyone saying so. Pinned here so the ordering
     * `CNR\Client::reuseSession()` depends on stays deliberate.
     */
    public function testSettingCredentialsDiscardsAnActiveCnrSession(): void
    {
        $cl = CF::cnr();
        $cl->setCredentials("myaccount", "mypassword")->setSession("SESSION-ABC");
        $this->assertSame("SESSION-ABC", $cl->getSession());

        $cl->setCredentials("myaccount", "mypassword");
        $this->assertNull($cl->getSession(), "new credentials supersede the session, by design");
    }

    /**
     * Read the cURL option bag off a config.
     * @return array<int, mixed>
     */
    private function curlOptions(AbstractClient $cl): array
    {
        $p = new ReflectionProperty(AbstractSocketConfig::class, "curlOptions");
        /** @var array<int, mixed> $opts */
        $opts = $p->getValue($cl->getSocketConfig());
        return $opts;
    }
}
