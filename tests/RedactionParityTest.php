<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\CNR\Response as CNRResponse;
use CNIC\CNR\SensitiveFields as CNRSensitiveFields;
use CNIC\CNR\SocketConfig as CNRSocketConfig;
use CNIC\IBS\Response as IBSResponse;
use CNIC\IBS\SensitiveFields as IBSSensitiveFields;
use CNIC\IBS\SocketConfig as IBSSocketConfig;
use CNIC\MONIKER\SocketConfig as MonikerSocketConfig;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Anti-drift pin for the per-brand sensitive-field lists.
 *
 * Before RSRMID-2938 each brand declared its sensitive command keys twice —
 * once as a literal array on its SocketConfig and again as an identical
 * literal array on its Response — with nothing but a docblock ("Mirrors
 * ...") keeping the two in sync. Both now read the same shared constant
 * ({@see CNRSensitiveFields::KEYS} / {@see IBSSensitiveFields::KEYS}), so this
 * is a value-equality pin, not a structural seam guard: it protects against
 * a future edit reintroducing a second, independent literal on one side
 * that silently drifts from the other, not against a refactor of how the
 * masking itself works.
 */
final class RedactionParityTest extends TestCase
{
    /**
     * @param class-string $class
     * @return string[]
     */
    private function defaultSensitiveFields(string $class): array
    {
        /** @var string[] $default */
        $default = (new ReflectionClass($class))->getDefaultProperties()["sensitiveFields"];
        return $default;
    }

    public function testCnrSocketConfigAndResponseAgreeWithSharedConstant(): void
    {
        $this->assertSame(CNRSensitiveFields::KEYS, $this->defaultSensitiveFields(CNRSocketConfig::class));
        $this->assertSame(CNRSensitiveFields::KEYS, $this->defaultSensitiveFields(CNRResponse::class));
    }

    public function testIbsSocketConfigAndResponseAgreeWithSharedConstant(): void
    {
        $this->assertSame(IBSSensitiveFields::KEYS, $this->defaultSensitiveFields(IBSSocketConfig::class));
        $this->assertSame(IBSSensitiveFields::KEYS, $this->defaultSensitiveFields(IBSResponse::class));
    }

    /**
     * MONIKER declares no sensitive-field list of its own — it is the same
     * platform as IBS, so `MONIKER\SocketConfig extends IBS\SocketConfig`
     * inherits the list and `MONIKER\Client` reuses `IBS\Response` outright
     * (there is no `MONIKER\Response`). This asserts that inheritance rather
     * than leaving it as a docblock claim: adding a MONIKER-local override
     * that drifts from the IBS list is precisely the regression this file
     * exists to catch, and it would otherwise pass unnoticed.
     */
    public function testMonikerInheritsTheIbsListRatherThanDeclaringItsOwn(): void
    {
        $this->assertSame(IBSSensitiveFields::KEYS, $this->defaultSensitiveFields(MonikerSocketConfig::class));
    }
}
