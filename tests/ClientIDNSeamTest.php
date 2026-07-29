<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST;

use CNIC\AbstractClient;
use CNIC\AbstractSocketConfig;
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
 * Locks the IDN seam: rewriting a command's IDN parameters is CNR behaviour and
 * lives in {@see \CNIC\CNR\IDNCommandRewriter}, called from CNR's own
 * `buildCommand()` hook — not on the shared client behind a flag (RSRMID-2922).
 *
 * Until v24 the rules were 37 lines on {@see AbstractClient}, gated by
 * `needsIDNConvert`, a config flag that only `CNR\SocketConfig` set true. The
 * rules themselves are covered by `tests/CNR/IDNCommandRewriterTest.php` through
 * the module's public method, and end to end by the CNR cassette tests; what this
 * file guards is that they do not come back up.
 *
 * Structural by necessity, the same argument as {@see ClientSessionSeamTest},
 * {@see ClientConfigSeamTest} and {@see ResponsePaginationSeamTest}: a hoist back
 * onto the shared base behind a flag is behaviour-preserving the day it lands —
 * CNR still converts, IBS still does not — so no behavioural test can see it
 * arrive, only the drift it later causes. Reflection is the one instrument that
 * can refuse it up front. (That the rules needed reflection to be *tested* is why
 * they moved; that their absence needs reflection to be *guarded* is unavoidable.)
 */
final class ClientIDNSeamTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string}>
     */
    public static function clientClassProvider(): array
    {
        return [
            "AbstractClient" => [AbstractClient::class],
            "CNR\\Client" => [CNRClient::class],
            "IBS\\Client" => [IBSClient::class],
            "MONIKER\\Client" => [MONIKERClient::class],
        ];
    }

    /**
     * @param class-string $clientClass
     */
    #[DataProvider("clientClassProvider")]
    public function testNoClientCarriesTheCommandRewrite(string $clientClass): void
    {
        $this->assertFalse(
            (new ReflectionClass($clientClass))->hasMethod("autoIDNConvert"),
            $clientClass . " must not carry the IDN command rewrite — it belongs in "
            . "CNR\\IDNCommandRewriter, called from CNR's buildCommand() hook (RSRMID-2922)."
        );
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function configClassProvider(): array
    {
        return [
            "AbstractSocketConfig" => [AbstractSocketConfig::class],
            "CNR\\SocketConfig" => [CNRSocketConfig::class],
            "IBS\\SocketConfig" => [IBSSocketConfig::class],
            "MONIKER\\SocketConfig" => [MONIKERSocketConfig::class],
        ];
    }

    /**
     * @param class-string $configClass
     */
    #[DataProvider("configClassProvider")]
    public function testNoConfigCarriesAnIDNFlag(string $configClass): void
    {
        $rc = new ReflectionClass($configClass);
        $this->assertFalse(
            $rc->hasProperty("needsIDNConvert"),
            $configClass . " must not carry a needsIDNConvert flag: its only purpose was to "
            . "disable CNR behaviour for the two brands that never needed it (RSRMID-2922)."
        );
        $this->assertFalse($rc->hasMethod("getNeedsIDNConvert"), $configClass . "::getNeedsIDNConvert() is gone.");
    }
}
