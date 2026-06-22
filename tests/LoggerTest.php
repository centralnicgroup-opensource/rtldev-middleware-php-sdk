<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\LoggerInterface;
use CNIC\ResponseInterface;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    public function testAllowsResponseInterfaceLoggerSignature(): void
    {
        $logger = new class implements LoggerInterface {
            #[\Override]
            public function log(string $post, ResponseInterface $r, ?string $error = null): void
            {
            }
        };

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }
}
