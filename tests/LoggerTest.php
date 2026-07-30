<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\AbstractLogger;
use CNIC\CNR\Response as CNRResponse;
use CNIC\EchoSink;
use CNIC\LoggerInterface;
use CNIC\LogSinkInterface;
use CNIC\ResponseInterface;
use CNICTEST\Support\CollectingSink;
use PHPUnit\Framework\TestCase;

/**
 * The two routes a consumer has for supplying their own logging: extend
 * AbstractLogger and own the format, or implement LoggerInterface and own the
 * format and the destination. Both must keep compiling under the shipped
 * signatures — this is the compile-time pin for the contract itself; the seam it
 * encodes is guarded by {@see LoggerSeamTest}.
 */
final class LoggerTest extends TestCase
{
    private static ResponseInterface $response;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        self::$response = new CNRResponse(
            "[RESPONSE]\r\nCODE=200\r\nDESCRIPTION=Command completed successfully\r\nEOF\r\n",
            ["COMMAND" => "StatusAccount"],
            ["CONNECTION_URL" => "https://api.rrpproxy.net/api/call.cgi"]
        );
    }

    public function testAFormatterOnlyLoggerNeedsNoWiringOfItsOwn(): void
    {
        $sink = new CollectingSink();
        $logger = new class ($sink) extends AbstractLogger {
            #[\Override]
            public function format(string $post, ResponseInterface $r, ?string $error = null): string
            {
                return "code=" . $r->getCode() . " post=" . $post;
            }
        };

        $logger->log("x=1", self::$response);

        $this->assertInstanceOf(LoggerInterface::class, $logger);
        $this->assertSame("code=200 post=x=1", $sink->contents());
    }

    public function testAFormatterOnlyLoggerDefaultsToStandardOutput(): void
    {
        $logger = new class extends AbstractLogger {
            #[\Override]
            public function format(string $post, ResponseInterface $r, ?string $error = null): string
            {
                return "post=" . $post;
            }
        };

        $this->expectOutputString("post=x=1");
        $logger->log("x=1", self::$response);
    }

    public function testAFullyCustomLoggerSatisfiesTheInterface(): void
    {
        $logger = new class implements LoggerInterface {
            #[\Override]
            public function format(string $post, ResponseInterface $r, ?string $error = null): string
            {
                return $post;
            }

            #[\Override]
            public function log(string $post, ResponseInterface $r, ?string $error = null): void
            {
            }
        };

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testACustomSinkSatisfiesTheSinkContract(): void
    {
        $sink = new class implements LogSinkInterface {
            public string $last = "";

            #[\Override]
            public function write(string $message): void
            {
                $this->last = $message;
            }
        };

        $sink->write("hello");

        $this->assertInstanceOf(LogSinkInterface::class, $sink);
        $this->assertSame("hello", $sink->last);
    }

    public function testTheShippedEchoSinkWritesToStandardOutput(): void
    {
        $this->expectOutputString("hello");
        (new EchoSink())->write("hello");
    }
}
