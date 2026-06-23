<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\ClientFactory as CF;
use CNIC\CNR\SessionClient as CNRSessionClient;
use CNIC\IBS\SessionClient as IBSSessionClient;
use CNIC\MONIKER\SessionClient as MONIKERSessionClient;
use PHPUnit\Framework\TestCase;

final class ClientFactoryTest extends TestCase
{
    public function testReturnsCnrClient(): void
    {
        $this->assertInstanceOf(CNRSessionClient::class, CF::getClient("CNR"));
    }

    public function testCnicLegacyAliasReturnsCnrClient(): void
    {
        $this->assertInstanceOf(CNRSessionClient::class, CF::getClient("CNIC"));
    }

    public function testRegistrarMatchingIsCaseInsensitive(): void
    {
        $this->assertInstanceOf(CNRSessionClient::class, CF::getClient("cnr"));
    }

    public function testReturnsIbsClient(): void
    {
        $this->assertInstanceOf(IBSSessionClient::class, CF::getClient("IBS"));
    }

    public function testReturnsMonikerClient(): void
    {
        $this->assertInstanceOf(MONIKERSessionClient::class, CF::getClient("MONIKER"));
    }

    public function testUnsupportedRegistrarThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Registrar `InvalidRegistrar` not supported.");
        CF::getClient("InvalidRegistrar");
    }
}
