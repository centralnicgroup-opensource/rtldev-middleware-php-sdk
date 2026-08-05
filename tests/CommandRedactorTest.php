<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\CommandRedactor as CR;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CNIC\CommandRedactor.
 *
 * CommandRedactor is the single home for the matching/masking algorithm that
 * both AbstractSocketConfig::maskSensitiveCommand() and
 * AbstractResponse::sanitizeCommand() delegate to, so it is exercised
 * directly here rather than only indirectly through those two call sites.
 */
final class CommandRedactorTest extends TestCase
{
    public function testMaskConstantIsThreeAsterisks(): void
    {
        $this->assertSame("***", CR::MASK);
    }

    public function testSensitiveKeyIsMasked(): void
    {
        $out = CR::redact(["PASSWORD" => "secret"], ["PASSWORD"]);
        $this->assertSame(["PASSWORD" => "***"], $out);
    }

    public function testMatchingIsCaseInsensitiveWhenFieldIsUppercaseAndKeyIsLowercase(): void
    {
        $out = CR::redact(["password" => "secret"], ["PASSWORD"]);
        $this->assertSame(["password" => "***"], $out);
    }

    public function testMatchingIsCaseInsensitiveWhenFieldIsLowercaseAndKeyIsUppercase(): void
    {
        $out = CR::redact(["PASSWORD" => "secret"], ["password"]);
        $this->assertSame(["PASSWORD" => "***"], $out);
    }

    public function testNullValuesAreLeftUntouchedEvenWhenKeyMatches(): void
    {
        $out = CR::redact(["AUTH" => null], ["AUTH"]);
        $this->assertNull($out["AUTH"]);
    }

    public function testKeysNotInTheSensitiveListAreLeftUntouched(): void
    {
        $out = CR::redact(["DOMAIN" => "example.com"], ["PASSWORD", "AUTH"]);
        $this->assertSame(["DOMAIN" => "example.com"], $out);
    }

    public function testEmptySensitiveFieldsListMasksNothing(): void
    {
        $command = ["PASSWORD" => "secret", "AUTH" => "code"];
        $this->assertSame($command, CR::redact($command, []));
    }

    public function testNonSensitiveKeysAndValuesArePreservedUnchanged(): void
    {
        $command = [
            "COMMAND" => "TransferDomain",
            "DOMAIN" => "example.com",
            "AUTH" => "sup3r-s3cr3t",
        ];
        $out = CR::redact($command, ["AUTH"]);
        $this->assertSame(
            ["COMMAND" => "TransferDomain", "DOMAIN" => "example.com", "AUTH" => "***"],
            $out
        );
    }
}
