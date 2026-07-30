<?php

declare(strict_types=1);

namespace CNICTEST\CNR;

use CNIC\ClientFactory as CF;
use CNIC\CNR\Logger;
use CNIC\CNR\Response;
use CNICTEST\Support\CollectingSink;
use PHPUnit\Framework\TestCase;

/**
 * The CNR debug format, asserted on the string format() returns rather than on
 * captured output (RSRMID-2925). Before the format/sink split this class could
 * not exist without output buffering, and the format had no test at all.
 */
final class LoggerTest extends TestCase
{
    private static Response $successResponse;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        self::$successResponse = new Response(
            "[RESPONSE]\r\nCODE=200\r\nDESCRIPTION=Command completed successfully\r\nEOF\r\n",
            ["COMMAND" => "StatusAccount"],
            ["CONNECTION_URL" => "https://api.rrpproxy.net/api/call.cgi"]
        );
    }

    public function testFormatContainsTheCommand(): void
    {
        $out = (new Logger())->format("s=abc&command=StatusAccount", self::$successResponse);
        $this->assertStringContainsString("COMMAND", $out);
        $this->assertStringContainsString("StatusAccount", $out);
    }

    public function testFormatContainsThePostData(): void
    {
        $out = (new Logger())->format("s=abc&command=StatusAccount", self::$successResponse);
        $this->assertStringContainsString("s=abc&command=StatusAccount", $out);
    }

    public function testFormatContainsThePlainResponse(): void
    {
        $out = (new Logger())->format("s=abc", self::$successResponse);
        $this->assertStringContainsString("Command completed successfully", $out);
    }

    public function testFormatWithoutErrorOmitsTheHttpErrorLine(): void
    {
        $out = (new Logger())->format("s=abc", self::$successResponse);
        $this->assertStringNotContainsString("HTTP communication failed", $out);
    }

    public function testFormatWithEmptyErrorOmitsTheHttpErrorLine(): void
    {
        $out = (new Logger())->format("s=abc", self::$successResponse, "");
        $this->assertStringNotContainsString("HTTP communication failed", $out);
    }

    public function testFormatWithErrorIncludesTheHttpErrorLine(): void
    {
        $out = (new Logger())->format("s=abc", self::$successResponse, "Connection timed out");
        $this->assertStringContainsString("HTTP communication failed: Connection timed out", $out);
    }

    /**
     * Masking happens before formatting — the response masks its stored command
     * and the client hands in an already-secured POST body — so nothing the
     * formatter emits carries a secret. Verified on the returned string, which
     * is where a caller can now route it.
     *
     * Both of the format's secret-bearing inputs are real here: the response's
     * own command (which CNR renders via `print_r`) and a POST body produced by
     * the client exactly as {@see \CNIC\AbstractClient::performRequest()}
     * produces it. A hand-written pre-masked literal would pass with masking
     * switched off entirely.
     */
    public function testFormatLeaksNeitherPasswordNorAuthCode(): void
    {
        $cmd = ["COMMAND" => "TransferDomain", "PASSWORD" => "s3cr3t", "AUTH" => "authc0de"];
        $r = new Response(
            "[RESPONSE]\r\nCODE=200\r\nDESCRIPTION=Command completed successfully\r\nEOF\r\n",
            $cmd,
            ["CONNECTION_URL" => "https://api.rrpproxy.net/api/call.cgi"]
        );
        $cl = CF::cnr();
        $cl->setCredentials("myuser", "s3cr3t");

        $out = (new Logger())->format($cl->getPOSTData($cmd, true), $r);

        $this->assertStringNotContainsString("s3cr3t", $out, "the account password must not reach the record");
        $this->assertStringNotContainsString("authc0de", $out, "the domain auth code must not reach the record");
        $this->assertStringContainsString("myuser", $out, "non-sensitive parameters must survive masking");
        $this->assertStringContainsString("TransferDomain", $out, "the command itself must survive masking");
    }

    /**
     * The byte-for-byte pin on the CNR record. `enableDebugMode()` emitted
     * exactly this before the format/sink split (RSRMID-2925) and must keep
     * emitting it; the assertions above would all survive a reordering or a
     * changed separator, and this one is the reason that does not matter.
     */
    public function testTheRecordIsByteForByteWhatItWasBeforeTheSplit(): void
    {
        // The response block keeps the wire's CRLF line endings: getPlain() is raw.
        $expected = "Array\n(\n    [COMMAND] => StatusAccount\n)\n\ns=abc\n\n"
            . "[RESPONSE]\r\nCODE=200\r\nDESCRIPTION=Command completed successfully\r\nEOF\r\n";

        $this->assertSame($expected, (new Logger())->format("s=abc", self::$successResponse));
    }

    /**
     * Default behaviour is unchanged: with the shipped echo sink, log() emits
     * exactly the bytes format() returns.
     */
    public function testLogEchoesTheFormattedStringByDefault(): void
    {
        $logger = new Logger();
        $expected = $logger->format("s=abc", self::$successResponse);
        $this->expectOutputString($expected);
        $logger->log("s=abc", self::$successResponse);
    }

    /**
     * The same string, routed elsewhere — and nothing written to output.
     */
    public function testLogWritesToTheInjectedSinkInsteadOfOutput(): void
    {
        $sink = new CollectingSink();
        $logger = new Logger($sink);
        $this->expectOutputString("");
        $logger->log("s=abc", self::$successResponse);
        $this->assertSame([$logger->format("s=abc", self::$successResponse)], $sink->messages());
    }
}
