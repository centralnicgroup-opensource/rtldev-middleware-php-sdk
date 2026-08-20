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
     *
     * These are the **same-name** forwarders, which is all
     * {@see testEveryClientConfigMethodHasACounterpartOnTheConfig()} can check: it
     * looks the method up by the identical name on the config. `getPOSTData` was
     * missing until RSRMID-2966 — 16 entries against 17 same-name forwarders, a
     * one-writer gap that no failure could reveal.
     *
     * `setCredentials` is the 18th forwarder and deliberately **not** here: it fans
     * out to `setLogin()` + `setPassword()`, so the config has no method of that
     * name and listing it would fail the counterpart assertion. It gets its own
     * check in {@see testSetCredentialsFansOutToTheConfigsCredentialWriters()}.
     * 17 + 1 is the 18 that AbstractClient's class docblock counts.
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
        "getPOSTData",
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
     * The 18th forwarder, checked separately because it is the one that does not
     * share a name with what it forwards to: `setCredentials()` fans out to the
     * config's two credential writers. Listing it in FORWARDED_METHODS would fail
     * the counterpart assertion above and read as a defect in the seam rather than
     * a property of this one method.
     *
     * Scope, stated honestly: this pins that the credential surface the forwarder
     * targets still exists in two halves, nothing about the forwarder's body. What
     * the two writers *do* — clear the CNR session id — is behavioural and covered
     * by {@see testTheCredentialsClearSessionInvariantSurvivesAPreBuiltConfig()}.
     *
     * Renaming either writer while `CNR\SocketConfig` overrides it is already a
     * fatal error, since those overrides carry `#[\Override]`; this catches the
     * mutation the language does not, namely dropping the pair from both levels at
     * once in favour of one combined setter.
     *
     * @param class-string<AbstractClient> $clientClass
     */
    #[DataProvider("clientProvider")]
    public function testSetCredentialsFansOutToTheConfigsCredentialWriters(string $clientClass): void
    {
        $this->assertTrue(
            (new ReflectionClass($clientClass))->hasMethod("setCredentials"),
            "{$clientClass}::setCredentials() must exist."
        );
        $config = new ReflectionClass(AbstractSocketConfig::class);
        foreach (["setLogin", "setPassword"] as $writer) {
            $this->assertTrue(
                $config->hasMethod($writer),
                "AbstractSocketConfig::{$writer}() must exist — {$clientClass}::setCredentials() fans out to "
                . "setLogin() + setPassword() rather than answering from client-side state (RSRMID-2921)."
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

    /**
     * The name every brand constructor and every factory method must use for the
     * config parameter. Named arguments bind to the *implementation*, not to a
     * shared abstract or interface, so `CF::cnr(socketConfig: $cfg)` keeps working
     * only while all seven declarations agree on the spelling.
     */
    private const string CONFIG_PARAMETER = "socketConfig";

    /**
     * The construction routes each brand offers, paired with the config instance
     * handed over. A fresh config per route on purpose: reusing one across both
     * would quietly assert that two clients may share a config, which is a
     * consequence of adopt-by-reference and not a property this guard pins.
     *
     * @return array<string, array{0: \Closure(): list<array{0: AbstractSocketConfig, 1: AbstractClient}>}>
     */
    public static function constructionProvider(): array
    {
        return [
            "CNR" => [static function (): array {
                $direct = new CNRSocketConfig();
                $viaFactory = new CNRSocketConfig();
                return [[$direct, new CNRClient($direct)], [$viaFactory, CF::cnr($viaFactory)]];
            }],
            "IBS" => [static function (): array {
                $direct = new IBSSocketConfig();
                $viaFactory = new IBSSocketConfig();
                return [[$direct, new IBSClient($direct)], [$viaFactory, CF::ibs($viaFactory)]];
            }],
            "MONIKER" => [static function (): array {
                $direct = new MONIKERSocketConfig();
                $viaFactory = new MONIKERSocketConfig();
                return [[$direct, new MONIKERClient($direct)], [$viaFactory, CF::moniker($viaFactory)]];
            }],
        ];
    }

    /**
     * Every client whose constructor parameter must be narrowed to its own brand's
     * config, and the class it must be narrowed to.
     *
     * One row per brand since RSRMID-2969. There used to be a second CNR row for
     * `SessionClient`, which declared no constructor of its own and inherited
     * CNR's; with the session lifecycle folded onto `CNR\Client` and that subclass
     * retired, `CNR\Client` *is* what `CF::cnr()` returns, so the extra row would
     * now assert the same thing twice under a different name.
     *
     * @return array<string, array{0: class-string<AbstractClient>, 1: class-string<AbstractSocketConfig>}>
     */
    public static function brandConfigTypeProvider(): array
    {
        return [
            "CNR" => [CNRClient::class, CNRSocketConfig::class],
            "IBS" => [IBSClient::class, IBSSocketConfig::class],
            "MONIKER" => [MONIKERClient::class, MONIKERSocketConfig::class],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: class-string<AbstractSocketConfig>}>
     */
    public static function factoryMethodProvider(): array
    {
        return [
            "cnr" => ["cnr", CNRSocketConfig::class],
            "ibs" => ["ibs", IBSSocketConfig::class],
            "moniker" => ["moniker", MONIKERSocketConfig::class],
        ];
    }

    /**
     * A supplied config is **adopted, not copied**: `getSocketConfig()` hands back
     * the caller's own instance (RSRMID-2966).
     *
     * The failure mode is a defensive `clone` in a constructor. That is the exact
     * shape RSRMID-2921 removed — configuration with two homes — reintroduced as a
     * copy the caller cannot see, and it is behaviour-preserving on the day it
     * lands: the clone agrees with the original until one of the two is written.
     * Identity is therefore the assertion, and the write-through check behind it is
     * what makes the identity claim mean something rather than restate `assertSame`.
     *
     * Revisit only if a config becomes genuinely immutable, at which point copying
     * costs nothing and this guard has no defect left to prevent.
     *
     * @param \Closure(): list<array{0: AbstractSocketConfig, 1: AbstractClient}> $routes
     */
    #[DataProvider("constructionProvider")]
    public function testASuppliedConfigIsAdoptedNotCopied(\Closure $routes): void
    {
        foreach ($routes() as [$cfg, $cl]) {
            $this->assertSame(
                $cfg,
                $cl->getSocketConfig(),
                $cl::class . " must adopt the supplied config, not a copy of it: a clone would give "
                . "configuration a second home again (RSRMID-2921/RSRMID-2966)."
            );

            // The identity above is only interesting because writes still cross it.
            $cfg->setSocketTimeout(17);
            $this->assertSame(17, $cl->getSocketTimeout());
            $cl->setReferer("https://adopted.example/");
            $this->assertSame("https://adopted.example/", $cfg->getReferer());
        }
    }

    /**
     * Each brand narrows the constructor parameter to its own config subtype.
     *
     * Widening it back to `?AbstractSocketConfig` is what this refuses. That change
     * type-checks, breaks no call site, and passes every behavioural test —
     * `new MONIKER\Client(new IBS\SocketConfig())` would then be legal and silently
     * point a Moniker client at the IBS host, because the two brands differ in
     * nothing but their endpoints. Only reflection on the declared type can refuse
     * it up front; a behavioural test would have to wait for someone to make the
     * mistake. PHP exempts constructors from LSP under class inheritance, which is
     * the sole reason the narrowing is expressible at all — do not "fix" a brand by
     * widening it to match the parent.
     *
     * Revisit if a brand's config genuinely has no subtype of its own, or if two
     * brands become endpoint-identical.
     *
     * @param class-string<AbstractClient> $clientClass
     * @param class-string<AbstractSocketConfig> $configClass
     */
    #[DataProvider("brandConfigTypeProvider")]
    public function testEveryBrandConstructorNarrowsItsConfigParameter(
        string $clientClass,
        string $configClass
    ): void {
        $ctor = (new ReflectionClass($clientClass))->getConstructor();
        $this->assertNotNull($ctor, "{$clientClass} must reach a constructor.");
        $params = $ctor->getParameters();
        $this->assertCount(
            1,
            $params,
            "{$clientClass}'s constructor takes the config and nothing else — client behaviour "
            . "(context, transport, logger, user agent) keeps its setters (RSRMID-2966)."
        );
        $type = $params[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame(
            $configClass,
            $type->getName(),
            "{$clientClass}'s config parameter must be narrowed to {$configClass}; a wider type lets a "
            . "foreign brand's config through, and endpoints are the only difference between IBS and MONIKER."
        );
        $this->assertTrue($type->allowsNull(), "{$clientClass}'s config parameter must be nullable.");
    }

    /**
     * The parameter stays optional, which is what makes the whole change additive:
     * every `new CNR\Client()` / `ClientFactory::cnr()` in the wild takes the
     * null branch and runs the code it always ran. Making it required is the one
     * way this becomes a breaking change, and it would break no test in this repo
     * that a default could not paper over — hence a structural pin.
     *
     * @param class-string<AbstractClient> $clientClass
     * @param class-string<AbstractSocketConfig> $configClass
     */
    #[DataProvider("brandConfigTypeProvider")]
    public function testTheConfigParameterStaysOptional(string $clientClass, string $configClass): void
    {
        $ctor = (new ReflectionClass($clientClass))->getConstructor();
        $this->assertNotNull($ctor);
        $this->assertSame(
            0,
            $ctor->getNumberOfRequiredParameters(),
            "{$clientClass} must stay constructible with no arguments — supplying a {$configClass} is "
            . "additive, and requiring it would break every existing consumer (RSRMID-2966)."
        );
    }

    /**
     * All seven declarations spell the parameter identically.
     *
     * Named arguments bind to the implementation rather than to the abstract or
     * interface a consumer typed against, so `CF::cnr(socketConfig: $cfg)` survives
     * only while the spelling agrees everywhere. A rename on one brand is invisible
     * to PHPStan and passes every positional call site, so nothing else in the
     * toolchain would report it.
     */
    public function testTheConfigParameterIsSpelledTheSameEverywhere(): void
    {
        foreach (self::brandConfigTypeProvider() as [$clientClass]) {
            $ctor = (new ReflectionClass($clientClass))->getConstructor();
            $this->assertNotNull($ctor);
            $this->assertSame(
                self::CONFIG_PARAMETER,
                $ctor->getParameters()[0]->getName(),
                "{$clientClass}'s config parameter must be named \$" . self::CONFIG_PARAMETER
                . " — named arguments bind to the implementation, so a per-brand spelling breaks callers."
            );
        }
        foreach (self::factoryMethodProvider() as [$method, $configClass]) {
            $rm = new ReflectionMethod(CF::class, $method);
            $this->assertSame(
                self::CONFIG_PARAMETER,
                $rm->getParameters()[0]->getName(),
                "ClientFactory::{$method}()'s config parameter must be named \$" . self::CONFIG_PARAMETER . "."
            );
            $type = $rm->getParameters()[0]->getType();
            $this->assertInstanceOf(\ReflectionNamedType::class, $type);
            $this->assertSame(
                $configClass,
                $type->getName(),
                "ClientFactory::{$method}() must accept {$configClass}, matching the brand client it builds."
            );
            $this->assertSame(
                0,
                $rm->getNumberOfRequiredParameters(),
                "ClientFactory::{$method}() must stay callable with no arguments."
            );
        }
    }

    /**
     * CNR's credentials-clear-session invariant has to hold when the config arrives
     * pre-built, which is the one behavioural risk a construction seam carries here:
     * the rule lives entirely on the config
     * ({@see \CNIC\CNR\SocketConfig::setLogin()} and `setPassword()` each clear the
     * session id), so adopting a caller's config must neither pre-empt it nor
     * reorder it.
     *
     * Both directions, because adopt-by-reference is what makes the second one work:
     * a write through the client is seen by the caller's config, and a write through
     * the caller's config is seen by the client.
     */
    public function testTheCredentialsClearSessionInvariantSurvivesAPreBuiltConfig(): void
    {
        $cfg = (new CNRSocketConfig())->setLogin("myaccountid")->setSession("sess-123");
        $cl = CF::cnr($cfg);
        $this->assertSame("sess-123", $cl->getSession(), "a pre-built session must reach the client");

        $cl->setCredentials("myaccountid", "mypassword");
        $this->assertNull(
            $cl->getSession(),
            "setting credentials must still discard the active session when the config was supplied "
            . "pre-built — a session and a password are alternative credentials on the wire."
        );

        $cfg->setSession("sess-456");
        $this->assertSame(
            "sess-456",
            $cl->getSession(),
            "a write through the caller's own config must be visible through the client it was handed to."
        );
    }

    /**
     * @return array<string, array{0: \Closure(): AbstractClient, 1: class-string<AbstractSocketConfig>}>
     */
    public static function defaultConstructionProvider(): array
    {
        return [
            "CNR" => [static fn(): AbstractClient => CF::cnr(), CNRSocketConfig::class],
            "IBS" => [static fn(): AbstractClient => CF::ibs(), IBSSocketConfig::class],
            "MONIKER" => [static fn(): AbstractClient => CF::moniker(), MONIKERSocketConfig::class],
        ];
    }

    /**
     * The null branch still mints the brand's own config — the half of the
     * constructor that existed before RSRMID-2966, and the reason that change is
     * additive rather than a migration.
     *
     * Asserted on the exact class, not with `instanceof`:
     * `MONIKER\SocketConfig extends IBS\SocketConfig`, so an
     * `instanceof IBSSocketConfig` check would pass for Moniker and hide exactly the
     * endpoint mix-up the narrowing guard above exists to prevent.
     *
     * @param \Closure(): AbstractClient $factory
     * @param class-string<AbstractSocketConfig> $configClass
     */
    #[DataProvider("defaultConstructionProvider")]
    public function testOmittingTheConfigStillYieldsTheBrandsOwnConfig(
        \Closure $factory,
        string $configClass
    ): void {
        $cl = $factory();
        $cfg = $cl->getSocketConfig();
        $this->assertSame(
            $configClass,
            $cfg::class,
            "a default-constructed client must build its own brand's config exactly, not a parent brand's."
        );
        $this->assertSame($cfg, $cl->getSocketConfig(), "the default config must be built once, not per call.");
        $this->assertSame($cfg->getLiveUrl(), $cfg->getURL(), "a default-constructed client starts on LIVE");
    }
}
