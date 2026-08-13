<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST\Exception;

use CNIC\AbstractClient;
use CNIC\AbstractSocketConfig;
use CNIC\Exception\UnsupportedFeatureException;
use CNIC\HttpTransport;
use PHPUnit\Framework\TestCase;

/**
 * Direct unit tests for the structured context UnsupportedFeatureException
 * carries alongside its message (RSRMID-2967): the three named constructors
 * and the four accessors that read them back.
 */
final class UnsupportedFeatureExceptionContextTest extends TestCase
{
    public function testAPlainlyConstructedInstanceAnswersEmptyContext(): void
    {
        $e = new UnsupportedFeatureException("boom");
        $this->assertSame([], $e->getRejectedCurlOptions());
        $this->assertSame([], $e->getReplacementSetters());
        $this->assertNull($e->getOwningClass());
        $this->assertNull($e->getRejectedHeaderName());
    }

    public function testTransportOwnedCurlOptionsComposesMessageAndContext(): void
    {
        $rejected = [CURLOPT_URL => "CURLOPT_URL", CURLOPT_HEADER => "CURLOPT_HEADER"];
        $e = UnsupportedFeatureException::transportOwnedCurlOptions($rejected, HttpTransport::class);

        $this->assertStringContainsString("CURLOPT_URL", $e->getMessage());
        $this->assertStringContainsString("CURLOPT_HEADER", $e->getMessage());
        $this->assertStringContainsString(HttpTransport::class, $e->getMessage());

        $this->assertSame($rejected, $e->getRejectedCurlOptions());
        $this->assertSame([], $e->getReplacementSetters());
        $this->assertSame(HttpTransport::class, $e->getOwningClass());
        $this->assertNull($e->getRejectedHeaderName());
    }

    public function testSdkManagedCurlOptionsComposesMessageAndContext(): void
    {
        $rejected = [
            CURLOPT_USERAGENT => [
                "option" => "CURLOPT_USERAGENT",
                "owner"  => AbstractClient::class,
                "setter" => "setUserAgent()",
            ],
        ];
        $e = UnsupportedFeatureException::sdkManagedCurlOptions($rejected, AbstractSocketConfig::class);

        $this->assertStringContainsString(
            "CURLOPT_USERAGENT (use " . AbstractClient::class . "::setUserAgent())",
            $e->getMessage()
        );

        $this->assertSame([CURLOPT_USERAGENT => "CURLOPT_USERAGENT"], $e->getRejectedCurlOptions());
        $this->assertSame(
            [CURLOPT_USERAGENT => AbstractClient::class . "::setUserAgent()"],
            $e->getReplacementSetters()
        );
        $this->assertSame(AbstractSocketConfig::class, $e->getOwningClass());
        $this->assertNull($e->getRejectedHeaderName());
    }

    public function testTransportOwnedHeaderComposesMessageAndContext(): void
    {
        $e = UnsupportedFeatureException::transportOwnedHeader("content-length", HttpTransport::class);

        $this->assertStringContainsString("content-length", $e->getMessage());
        $this->assertStringContainsString(HttpTransport::class, $e->getMessage());

        $this->assertSame("content-length", $e->getRejectedHeaderName());
        $this->assertSame(HttpTransport::class, $e->getOwningClass());
        $this->assertSame([], $e->getRejectedCurlOptions());
        $this->assertSame([], $e->getReplacementSetters());
    }
}
