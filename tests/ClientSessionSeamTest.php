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

/**
 * Locks the session seam: API sessions are a CNR-only capability and are absent
 * on IBS/Moniker by type, not by convention (RSRMID-2920).
 *
 * Until v22 the five session/persistent accessors sat on the shared
 * AbstractSocketConfig as null objects — `getSession()` returned `""`, the
 * setters returned `$this` unchanged — and AbstractClient delegated to them. On
 * IBS/Moniker `setSession("SESSION-ABC")` therefore looked accepted (the setter
 * is fluent) and was silently discarded. RSRMID-2911 had recorded that as
 * "harmless"; RSRMID-2919 then took the opposite line for the same defect class,
 * making a capability the platform cannot honour throw rather than be ignored.
 * RSRMID-2920 settled the conflict in a third direction that is only available
 * at this seam: make the capability *absent*, so a brand mismatch is a static
 * analysis error at the call site instead of anything discovered at runtime.
 *
 * Structural by necessity. Absence cannot be exercised behaviourally — calling a
 * method that is gone is a fatal `Error`, not something a test can assert
 * against without reflection — so only reflection can catch the stubs creeping
 * back onto the shared base, which is exactly how they survived from v11 to v21.
 * Weakening this test reopens the decision recorded in
 * docs/agents/architecture.md.
 */
final class ClientSessionSeamTest extends TestCase
{
    /**
     * The config-side accessors carrying CNR's session/persistent concept.
     *
     * Spelled out rather than derived from CNR\SocketConfig, so hoisting one back
     * onto the shared base cannot silently redefine what is asserted here.
     * @var string[]
     */
    private const array CONFIG_SESSION_METHODS = [
        "getSession",
        "setSession",
        "getPersistent",
        "setPersistent",
    ];

    /**
     * The client-side half of the same capability.
     * @var string[]
     */
    private const array CLIENT_SESSION_METHODS = [
        "getSession",
        "setSession",
    ];

    /**
     * @return array<string, array{0: class-string<AbstractSocketConfig>}>
     */
    public static function sessionlessConfigProvider(): array
    {
        return [
            "IBS" => [IBSSocketConfig::class],
            "MONIKER" => [MONIKERSocketConfig::class],
        ];
    }

    /**
     * @return array<string, array{0: class-string<AbstractClient>}>
     */
    public static function sessionlessClientProvider(): array
    {
        return [
            "IBS" => [IBSClient::class],
            "MONIKER" => [MONIKERClient::class],
        ];
    }

    /**
     * The shared config must carry no session state at all — not even a stub.
     */
    public function testAbstractSocketConfigHasNoSessionAccessors(): void
    {
        $rc = new ReflectionClass(AbstractSocketConfig::class);
        foreach (self::CONFIG_SESSION_METHODS as $method) {
            $this->assertFalse(
                $rc->hasMethod($method),
                "AbstractSocketConfig must not declare {$method}(): sessions are CNR-only."
            );
        }
    }

    /**
     * getRoleSeparator() had a single consumer — CNR\Client::setRoleCredentials()
     * — and belongs with the capability it serves, which RSRMID-2911 had already
     * made CNR-only on the client side via RoleCredentialsInterface. Leaving the
     * separator on the shared config left half the split behind.
     */
    public function testAbstractSocketConfigHasNoRoleSeparator(): void
    {
        $rc = new ReflectionClass(AbstractSocketConfig::class);
        $this->assertFalse($rc->hasMethod("getRoleSeparator"));
        $this->assertFalse($rc->hasProperty("roleSeparator"));
    }

    /**
     * The shared client must not expose an accessor whose only job is to forward
     * to a capability the brand may not have.
     */
    public function testAbstractClientHasNoSessionAccessors(): void
    {
        $rc = new ReflectionClass(AbstractClient::class);
        foreach (self::CLIENT_SESSION_METHODS as $method) {
            $this->assertFalse(
                $rc->hasMethod($method),
                "AbstractClient must not declare {$method}(): sessions are CNR-only."
            );
        }
    }

    /**
     * @param class-string<AbstractSocketConfig> $configClass
     */
    #[DataProvider("sessionlessConfigProvider")]
    public function testSessionlessConfigsHaveNoSessionAccessors(string $configClass): void
    {
        $rc = new ReflectionClass($configClass);
        foreach ([...self::CONFIG_SESSION_METHODS, "getRoleSeparator"] as $method) {
            $this->assertFalse(
                $rc->hasMethod($method),
                "{$configClass} must not have {$method}(): the platform has no session concept."
            );
        }
    }

    /**
     * @param class-string<AbstractClient> $clientClass
     */
    #[DataProvider("sessionlessClientProvider")]
    public function testSessionlessClientsHaveNoSessionAccessors(string $clientClass): void
    {
        $rc = new ReflectionClass($clientClass);
        foreach (self::CLIENT_SESSION_METHODS as $method) {
            $this->assertFalse(
                $rc->hasMethod($method),
                "{$clientClass} must not have {$method}(): the platform has no session concept."
            );
        }
    }

    /**
     * The empty IBS/Moniker SessionClient subclasses advertised a capability the
     * platform does not have; the factory hands out the brand Client instead.
     *
     * String class names on purpose: referencing the deleted classes as `::class`
     * would make static analysis resolve symbols that must no longer exist.
     */
    public function testEmptySessionClientSubclassesAreGone(): void
    {
        $this->assertFalse(class_exists("CNIC\\IBS\\SessionClient"));
        $this->assertFalse(class_exists("CNIC\\MONIKER\\SessionClient"));
    }

    /**
     * The factory's return types are the seam consumers actually see. IBS/Moniker
     * yield the brand Client — nothing named "session".
     */
    public function testFactoryHandsOutSessionlessClientsForIbsAndMoniker(): void
    {
        $this->assertSame(IBSClient::class, CF::ibs()::class);
        $this->assertSame(MONIKERClient::class, CF::moniker()::class);
    }

    /**
     * CNR keeps the whole capability, in one place.
     */
    public function testCnrRetainsTheSessionCapability(): void
    {
        $config = new ReflectionClass(CNRSocketConfig::class);
        foreach ([...self::CONFIG_SESSION_METHODS, "getRoleSeparator"] as $method) {
            $this->assertTrue($config->hasMethod($method), "CNR\\SocketConfig must own {$method}().");
        }
        $client = new ReflectionClass(CNRClient::class);
        foreach (self::CLIENT_SESSION_METHODS as $method) {
            $this->assertTrue($client->hasMethod($method), "CNR\\Client must own {$method}().");
        }
    }

    /**
     * `persistent=1` is a CNR wire parameter. It used to be appended by the
     * shared getPOSTData() on behalf of every brand, gated on a getter that only
     * CNR could ever answer truthfully; it now comes from CNR's own
     * getPOSTDataParams(). Its position — last, after the command — is asserted
     * because the move must not reorder the body.
     */
    public function testPersistentParameterIsEmittedByCnrItself(): void
    {
        $cnr = new CNRSocketConfig();
        $cnr->setLogin("myaccount")->setPassword("mypassword")->setPersistent(true);
        $this->assertSame(
            "s_login=myaccount&s_pw=mypassword&s_command=COMMAND%3DStartSession&persistent=1",
            $cnr->getPOSTData(["COMMAND" => "StartSession"])
        );
        $cnr->setPersistent(false);
        $this->assertStringNotContainsString("persistent", $cnr->getPOSTData(["COMMAND" => "StartSession"]));
    }
}
