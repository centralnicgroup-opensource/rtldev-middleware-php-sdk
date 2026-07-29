<?php

declare(strict_types=1);

namespace CNICTEST\CNR;

use CNIC\CNR\ResponseParser as RP;
use CNIC\ResponseParserInterface;
use PHPUnit\Framework\TestCase;

/**
 * Direct tests for the CNR parser.
 *
 * Before RSRMID-2924 gave the parse step a contract of its own, none of this
 * was reachable without constructing a full Response from a raw wire payload,
 * and the CNR parser had no test file at all.
 */
final class ResponseParserTest extends TestCase
{
    private function parser(): ResponseParserInterface
    {
        return new RP();
    }

    public function testParsesScalarKeysUppercased(): void
    {
        $raw = "[RESPONSE]\r\nCODE=200\r\nDescription=Command completed successfully\r\nEOF\r\n";
        $this->assertSame(
            ["CODE" => "200", "DESCRIPTION" => "Command completed successfully"],
            $this->parser()->parse($raw)
        );
    }

    public function testAcceptsBothLineEndings(): void
    {
        $this->assertSame(
            $this->parser()->parse("CODE=200\r\nDESCRIPTION=ok\r\n"),
            $this->parser()->parse("CODE=200\nDESCRIPTION=ok\n")
        );
    }

    public function testTrimsWhitespaceAroundTheSeparatorAndAtTheEnd(): void
    {
        $raw = "CODE\t = \t200   \nDESCRIPTION = ok\n";
        $this->assertSame(["CODE" => "200", "DESCRIPTION" => "ok"], $this->parser()->parse($raw));
    }

    public function testKeepsEverythingAfterTheFirstSeparator(): void
    {
        // a value may itself contain "=" — only the first separator splits
        $raw = "DESCRIPTION=a=b=c\n";
        $this->assertSame(["DESCRIPTION" => "a=b=c"], $this->parser()->parse($raw));
    }

    public function testIgnoresLinesWithoutASeparator(): void
    {
        $raw = "[RESPONSE]\nCODE=200\nEOF\n";
        $this->assertSame(["CODE" => "200"], $this->parser()->parse($raw));
    }

    public function testEmptyResponseYieldsEmptyHash(): void
    {
        $this->assertSame([], $this->parser()->parse(""));
    }

    public function testGroupsPropertiesIntoIndexedLists(): void
    {
        $raw = "[RESPONSE]\r\nPROPERTY[DOMAIN][0]=example.com\r\nPROPERTY[DOMAIN][1]=example.net\r\n"
            . "PROPERTY[TOTAL][0]=2\r\nCODE=200\r\nEOF\r\n";
        $this->assertSame(
            [
                "CODE" => "200",
                "PROPERTY" => [
                    "DOMAIN" => ["example.com", "example.net"],
                    "TOTAL" => ["2"]
                ]
            ],
            $this->parser()->parse($raw)
        );
    }

    public function testPropertyNamesAreUppercasedAndStrippedOfWhitespace(): void
    {
        $raw = "property[domain check][0]=210 available\r\n";
        $this->assertSame(
            ["PROPERTY" => ["DOMAINCHECK" => ["210 available"]]],
            $this->parser()->parse($raw)
        );
    }

    public function testNoPropertyKeyWhenTheResponseCarriesNoProperties(): void
    {
        // The absence of this key is what makes CNR yield zero records for a
        // scalar-only response — see CNR\Response::populate().
        $this->assertArrayNotHasKey("PROPERTY", $this->parser()->parse("CODE=200\r\nDESCRIPTION=ok\r\n"));
    }

    public function testCommandArgumentIsIgnored(): void
    {
        // The CNR wire format is self-describing; $cmd exists only so the two
        // brand parsers share one contract (RSRMID-2924).
        $raw = "CODE=200\r\nDESCRIPTION=ok\r\n";
        $this->assertSame(
            $this->parser()->parse($raw),
            $this->parser()->parse($raw, ["COMMAND" => "StatusAccount", "ResponseFormat" => "JSON"])
        );
    }
}
