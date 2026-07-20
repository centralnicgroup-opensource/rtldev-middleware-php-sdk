<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\AbstractClient;
use CNIC\ClientFactory as CF;
use CNIC\CNR\SessionClient as CNRSessionClient;
use CNIC\Exception\UnknownRegistrarException;
use CNIC\IBS\SessionClient as IBSSessionClient;
use CNIC\MONIKER\SessionClient as MONIKERSessionClient;
use PHPUnit\Framework\TestCase;

final class ClientFactoryTest extends TestCase
{
    public function testReturnsSharedAbstractClientType(): void
    {
        // The factory declares the shared AbstractClient contract rather than a
        // brand-specific union, so every arm is assignable to the common type.
        $this->assertInstanceOf(AbstractClient::class, CF::getClient("CNR"));
        $this->assertInstanceOf(AbstractClient::class, CF::getClient("IBS"));
        $this->assertInstanceOf(AbstractClient::class, CF::getClient("MONIKER"));
    }

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
        $this->expectException(UnknownRegistrarException::class);
        $this->expectExceptionMessage("Registrar `InvalidRegistrar` not supported.");
        CF::getClient("InvalidRegistrar");
    }
}
