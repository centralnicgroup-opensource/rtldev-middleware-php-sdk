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
use CNIC\CNR\SocketConfig as CNRSocketConfig;
use CNIC\IBS\Client as IBSClient;
use CNIC\IBS\SocketConfig as IBSSocketConfig;
use CNIC\MONIKER\Client as MONIKERClient;
use CNIC\MONIKER\SocketConfig as MONIKERSocketConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Locks the configuration seam: connection configuration lives on the
 * SocketConfig and nowhere else (RSRMID-2921).
 *
 * Until v23 the client kept its own copies of part of it — `$socketURL`,
 * `$system`, the `$curlOptions` bag — beside the config's endpoints and timeout,
 * with no invariant tying the two sets together. Three defects followed, each
 * with its own regression test in {@see AbstractClientConfigDriftTest}; this file
 * guards the structural property that makes all three unrepresentable rather than
 * fixed.
 *
 * Structural by necessity, the same argument as {@see ClientSessionSeamTest} and
 * {@see ResponsePaginationSeamTest}: re-adding a client-side copy of a
 * configuration value is behaviour-preserving on the day it lands — the copy
 * agrees with the original until something writes one of them — so no behavioural
 * test can see it arrive. Only the drift it *later* causes is observable, which is
 * how this survived to v22. Reflection is the instrument that can refuse the copy
 * up front.
 */
final class ClientConfigSeamTest extends TestCase
{
    /**
     * Connection-configuration state that must exist on the config and not on the
     * client. Names both the historical client property (`socketURL`) and the
     * config's current one (`url`), so re-adding either spelling to a client is a
     * failure. Same for the option bag, renamed `curlopts` -> `curlOptions` in
     * RSRMID-2935 — both spellings stay listed here.
     * @var string[]
     */
    private const array CONFIG_OWNED_PROPERTIES = [
        "url",
        "socketURL",
        "system",
        "curlopts",
        "curlOptions",
        "proxy",
        "referer",
        "highPerformance",
        "oteUrl",
        "liveUrl",
        "socketTimeout",
    ];

    /**
     * Configuration reachable through the client, each answered by the config.
     * The client's forwarders are kept for ergonomics; what must stay true is
     * that they are forwarders.
     * @var string[]
     */
    private const array FORWARDED_METHODS = [
        "getURL",
        "setURL",
        "getLiveUrl",
        "getSystem",
        "isOTE",
        "useOTESystem",
        "useLIVESystem",
        "useHighPerformanceConnectionSetup",
        "getProxy",
        "setProxy",
        "getReferer",
        "setReferer",
        "getSocketTimeout",
        "setSocketTimeout",
        "setExtraCurlOptions",
        "resetCurlOptions",
    ];

    /**
     * @return array<string, array{0: class-string<AbstractClient>}>
     */
    public static function clientProvider(): array
    {
        return [
            "CNR" => [CNRClient::class],
            "IBS" => [IBSClient::class],
            "MONIKER" => [MONIKERClient::class],
        ];
    }

    /**
     * @return array<string, array{0: class-string<AbstractSocketConfig>}>
     */
    public static function configProvider(): array
    {
        return [
            "CNR" => [CNRSocketConfig::class],
            "IBS" => [IBSSocketConfig::class],
            "MONIKER" => [MONIKERSocketConfig::class],
        ];
    }

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
     * No client in the tree may declare connection-configuration state. Walking
     * the whole hierarchy rather than just the declaring class, because a copy
     * re-added on `AbstractClient` is reachable from every brand.
     *
     * @param class-string<AbstractClient> $clientClass
     */
    #[DataProvider("clientProvider")]
    public function testClientsCarryNoConnectionConfigurationState(string $clientClass): void
    {
        $rc = new ReflectionClass($clientClass);
        foreach (self::CONFIG_OWNED_PROPERTIES as $property) {
            $this->assertFalse(
                $rc->hasProperty($property),
                "{$clientClass} must not carry \${$property}: connection configuration lives on the "
                . "SocketConfig (RSRMID-2921). Forward to it instead of keeping a copy."
            );
        }
    }

    /**
     * The other half of the same claim: the state is really on the config, so the
     * test above is not passing merely because the value stopped existing.
     */
    public function testTheSocketConfigOwnsTheConnectionState(): void
    {
        $rc = new ReflectionClass(AbstractSocketConfig::class);
        foreach (["url", "highPerformance", "proxy", "referer", "curlOptions", "oteUrl", "liveUrl", "socketTimeout"] as $p) {
            $this->assertTrue($rc->hasProperty($p), "AbstractSocketConfig must own \${$p}.");
        }
    }

    /**
     * Every configuration method on the client must be reachable *and* answered
     * by the config, so the two cannot disagree. Declared on the client and
     * declared on the config — a forwarder with no counterpart is a value with a
     * second home.
     *
     * @param class-string<AbstractClient> $clientClass
     */
    #[DataProvider("clientProvider")]
    public function testEveryClientConfigMethodHasACounterpartOnTheConfig(string $clientClass): void
    {
        $client = new ReflectionClass($clientClass);
        $config = new ReflectionClass(AbstractSocketConfig::class);
        foreach (self::FORWARDED_METHODS as $method) {
            $this->assertTrue($client->hasMethod($method), "{$clientClass}::{$method}() must exist.");
            $this->assertTrue(
                $config->hasMethod($method),
                "AbstractSocketConfig::{$method}() must exist — {$clientClass}::{$method}() has to forward "
                . "to it rather than answer from client-side state."
            );
        }
    }

    /**
     * A write through either route must be visible through the other. This is
     * "one home" stated behaviourally, and it is what a re-added client-side copy
     * would break the moment the two were written independently.
     *
     * @param \Closure(): AbstractClient $factory
     */
    #[DataProvider("brandProvider")]
    public function testWritesThroughEitherRouteAgree(\Closure $factory): void
    {
        $cl = $factory();
        $cfg = $cl->getSocketConfig();

        $cl->setURL("https://client.example/");
        $this->assertSame("https://client.example/", $cfg->getURL());
        $cfg->setURL("https://config.example/");
        $this->assertSame("https://config.example/", $cl->getURL());

        $cl->setProxy("http://proxy.example:3128");
        $this->assertSame("http://proxy.example:3128", $cfg->getProxy());
        $cfg->setProxy("http://other.example:3128");
        $this->assertSame("http://other.example:3128", $cl->getProxy());

        $cl->setReferer("https://referer.example/");
        $this->assertSame("https://referer.example/", $cfg->getReferer());
        $cfg->setReferer("https://other-referer.example/");
        $this->assertSame("https://other-referer.example/", $cl->getReferer());

        $cl->setSocketTimeout(11);
        $this->assertSame(11, $cfg->getSocketTimeout());
        $cfg->setSocketTimeout(22);
        $this->assertSame(22, $cl->getSocketTimeout());

        $cfg->useOTESystem();
        $this->assertTrue($cl->isOTE());
        $cl->useLIVESystem();
        $this->assertFalse($cfg->isOTE());
    }

    /**
     * The accessor is the point of the change: without it every configuration
     * value needed a hand-written forwarder or was unreachable, which is why 18
     * of them accumulated and why this was the repo's highest-churn file.
     *
     * @param \Closure(): AbstractClient $factory
     */
    #[DataProvider("brandProvider")]
    public function testGetSocketConfigReturnsTheSameInstanceEveryTime(\Closure $factory): void
    {
        $cl = $factory();
        $this->assertSame($cl->getSocketConfig(), $cl->getSocketConfig());
    }

    /**
     * CNR narrows the return type covariantly, which is what lets a CNR consumer
     * reach `getSession()`/`setPersistent()` without an `instanceof` of its own.
     * This replaced the protected `cnrConfig()`: two methods narrowing the same
     * invariant property would be two places to keep in step.
     */
    public function testCnrNarrowsTheConfigAccessorCovariantly(): void
    {
        $rm = new ReflectionMethod(CNRClient::class, "getSocketConfig");
        $this->assertSame(CNRClient::class, $rm->getDeclaringClass()->getName());
        $this->assertSame(CNRSocketConfig::class, (string) $rm->getReturnType());
        $this->assertInstanceOf(CNRSocketConfig::class, CF::cnr()->getSocketConfig());
    }

    /**
     * The narrowing point must stay single. `cnrConfig()` is gone, not aliased —
     * an alias is the second place the CLAUDE.md directive exists to prevent.
     */
    public function testTheFormerPrivateNarrowingAccessorIsGone(): void
    {
        $this->assertFalse((new ReflectionClass(CNRClient::class))->hasMethod("cnrConfig"));
    }

    /**
     * The cURL default hook moved to the config with the bag it seeds, so the
     * client must not offer a second one.
     *
     * @param class-string<AbstractClient> $clientClass
     */
    #[DataProvider("clientProvider")]
    public function testClientsHaveNoCurlOptionDefaultHook(string $clientClass): void
    {
        $this->assertFalse(
            (new ReflectionClass($clientClass))->hasMethod("getDefaultCurlOpts"),
            "{$clientClass} must not declare getDefaultCurlOpts(): it belongs with the option bag on the "
            . "SocketConfig (RSRMID-2921)."
        );
    }

    /**
     * The RSRMID-2915 ban, carried over to the hook's new home.
     *
     * It bans overrides unconditionally on purpose: the bar is a genuinely
     * protocol-mandatory option, not a workaround for one environment's
     * networking (which is what IBS's removed IPv4 default was). If a future brand
     * clears that bar, updating this test is part of the change — and the commit
     * doing so should say why the option is mandatory.
     *
     * @param class-string<AbstractSocketConfig> $configClass
     */
    #[DataProvider("configProvider")]
    public function testNoBrandOverridesTheDefaultCurlOptsHook(string $configClass): void
    {
        $declaring = (new ReflectionMethod($configClass, "getDefaultCurlOpts"))
            ->getDeclaringClass()->getName();
        $this->assertSame(
            AbstractSocketConfig::class,
            $declaring,
            "{$configClass} must inherit the empty default cURL options from AbstractSocketConfig; "
            . "{$declaring} overrides getDefaultCurlOpts() — see RSRMID-2915"
        );
    }

    /**
     * @return array<string, array{0: \Closure(): AbstractSocketConfig}>
     */
    public static function configFactoryProvider(): array
    {
        return [
            "CNR" => [static fn(): AbstractSocketConfig => new CNRSocketConfig()],
            "IBS" => [static fn(): AbstractSocketConfig => new IBSSocketConfig()],
            "MONIKER" => [static fn(): AbstractSocketConfig => new MONIKERSocketConfig()],
        ];
    }

    /**
     * Configuration must be buildable and assertable without a client — one of
     * the things the split cost, and the reason the state went to the config
     * rather than the client absorbing it.
     *
     * @param \Closure(): AbstractSocketConfig $factory
     */
    #[DataProvider("configFactoryProvider")]
    public function testAConfigIsUsableStandalone(\Closure $factory): void
    {
        $cfg = $factory();
        $this->assertSame($cfg->getLiveUrl(), $cfg->getURL(), "a fresh config starts on LIVE");
        $this->assertFalse($cfg->isOTE());
        $cfg->useOTESystem()->setProxy("http://p.example:8080")->setSocketTimeout(9);
        $this->assertTrue($cfg->isOTE());
        $this->assertSame("http://p.example:8080", $cfg->getProxy());
        $this->assertSame(9, $cfg->getSocketTimeout());
    }
}
