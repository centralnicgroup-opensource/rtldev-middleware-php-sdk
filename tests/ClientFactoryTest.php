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
    public function testCnrReturnsCnrSessionClient(): void
    {
        $this->assertInstanceOf(CNRSessionClient::class, CF::cnr());
    }

    public function testIbsReturnsIbsSessionClient(): void
    {
        $this->assertInstanceOf(IBSSessionClient::class, CF::ibs());
    }

    public function testMonikerReturnsMonikerSessionClient(): void
    {
        $this->assertInstanceOf(MONIKERSessionClient::class, CF::moniker());
    }
}
