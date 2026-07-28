<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\ClientFactory as CF;
use CNIC\CNR\SessionClient as CNRSessionClient;
use CNIC\IBS\Client as IBSClient;
use CNIC\MONIKER\Client as MONIKERClient;
use PHPUnit\Framework\TestCase;

final class ClientFactoryTest extends TestCase
{
    public function testCnrReturnsCnrSessionClient(): void
    {
        $this->assertInstanceOf(CNRSessionClient::class, CF::cnr());
    }

    /**
     * IBS/Moniker return the plain brand Client, not a "SessionClient": those
     * platforms have no session lifecycle, and the empty subclasses that used to
     * carry the name were removed in RSRMID-2920. assertSame on the class name
     * rather than assertInstanceOf, so re-introducing a subclass would fail here
     * instead of passing by inheritance.
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
