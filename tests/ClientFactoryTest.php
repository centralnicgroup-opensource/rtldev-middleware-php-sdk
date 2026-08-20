<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\ClientFactory as CF;
use CNIC\CNR\Client as CNRClient;
use CNIC\IBS\Client as IBSClient;
use CNIC\MONIKER\Client as MONIKERClient;
use PHPUnit\Framework\TestCase;

final class ClientFactoryTest extends TestCase
{
    /**
     * Re-decided in RSRMID-2969, not merely retargeted. This was
     * `assertInstanceOf(CNR\SessionClient::class, CF::cnr())`; with the session
     * lifecycle folded onto `CNR\Client` and `SessionClient` retired, the honest
     * assertion is the same one IBS and MONIKER already use — assertSame on the
     * class name.
     *
     * The distinction matters because the retired name was briefly considered as
     * a `class_alias` for `CNR\Client`. Under an alias the *old* assertion would
     * have kept passing while proving nothing at all: an alias and its target are
     * the same class, so `assertInstanceOf` cannot tell them apart. assertSame on
     * `::class` states which class the factory actually hands out, so neither an
     * alias nor a reintroduced subclass can satisfy it by accident.
     */
    public function testCnrReturnsCnrClient(): void
    {
        $this->assertSame(CNRClient::class, CF::cnr()::class);
    }

    /**
     * IBS/Moniker return the plain brand Client: those platforms have no session
     * lifecycle, and the empty subclasses that used to carry a "SessionClient"
     * name were removed in RSRMID-2920. assertSame on the class name rather than
     * assertInstanceOf, so re-introducing a subclass would fail here instead of
     * passing by inheritance.
     */
    public function testIbsReturnsIbsClient(): void
    {
        $this->assertSame(IBSClient::class, CF::ibs()::class);
    }

    public function testMonikerReturnsMonikerClient(): void
    {
        $this->assertSame(MONIKERClient::class, CF::moniker()::class);
    }
}
