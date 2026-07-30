<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\ClientFactory as CF;
use CNIC\IBS\Logger;
use CNIC\IBS\Response;
use CNICTEST\Support\CollectingSink;
use PHPUnit\Framework\TestCase;

/**
 * The IBS debug format, asserted on the string format() returns rather than on
 * captured output (RSRMID-2925). The two tests at the bottom are the ones that
 * still care about the destination: the shipped echo sink must emit exactly
 * what format() returns, and an injected sink must take over completely.
 */
final class LoggerTest extends TestCase
{
    private static Logger $logger;
    private static Response $successResponse;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        self::$logger = new Logger();
        self::$successResponse = new Response(
            "status=SUCCESS\r\nmessage=Command completed successfully\r\n",
            [],
            ["CONNECTION_URL" => "https://api.internet.bs/"]
        );
    }

    public function testFormatContainsRequestSection(): void
    {
        $out = self::$logger->format("domain=test.com&apikey=mykey&password=***", self::$successResponse);
        $this->assertStringContainsString("R E Q U E S T", $out);
    }

    public function testFormatContainsResponseSection(): void
    {
        $out = self::$logger->format("domain=test.com&apikey=mykey&password=***", self::$successResponse);
        $this->assertStringContainsString("R E S P O N S E", $out);
    }

    public function testFormatContainsPostData(): void
    {
        $out = self::$logger->format("domain=test.com&apikey=mykey&password=***", self::$successResponse);
        $this->assertStringContainsString("domain=test.com&apikey=mykey&password=***", $out);
    }

    public function testFormatContainsApiUrl(): void
    {
        $out = self::$logger->format("apikey=mykey&password=***", self::$successResponse);
        $this->assertStringContainsString("https://api.internet.bs/", $out);
    }

    public function testFormatWithoutErrorOmitsHttpErrorLine(): void
    {
        $out = self::$logger->format("apikey=mykey&password=***", self::$successResponse);
        $this->assertStringNotContainsString("HTTP communication failed", $out);
    }

    public function testFormatWithErrorIncludesHttpErrorLine(): void
    {
        $out = self::$logger->format("apikey=mykey&password=***", self::$successResponse, "Connection timed out");
        $this->assertStringContainsString("HTTP communication failed: Connection timed out", $out);
    }

    public function testFormatWithEmptyErrorOmitsHttpErrorLine(): void
    {
        $out = self::$logger->format("apikey=mykey&password=***", self::$successResponse, "");
        $this->assertStringNotContainsString("HTTP communication failed", $out);
    }

    public function testFormatIndentsResponsePlain(): void
    {
        $out = self::$logger->format("apikey=mykey&password=***", self::$successResponse);
        // Each line of the response plain is prefixed with a tab
        $this->assertMatchesRegularExpression("/\n\t/", $out);
    }

    /**
     * Masking happens before formatting, so the string a caller can now obtain
     * carries no secret.
     *
     * The POST body is the only place an IBS record can leak one — this format
     * renders the URL, the body and the plain response, and never the stored
     * command — so the body here is the real one, produced by the client the way
     * {@see \CNIC\AbstractClient::performRequest()} produces it. Handing in a
     * pre-masked literal would assert nothing: it would pass with masking
     * switched off entirely.
     */
    public function testFormatLeaksNeitherPasswordNorTransferAuthInfo(): void
    {
        $cl = CF::ibs();
        $cl->setCredentials("myuser", "s3cr3t");
        $post = $cl->getPOSTData(["command" => "Domain/Transfer", "transferAuthInfo" => "authc0de"], true);

        $out = self::$logger->format($post, self::$successResponse);

        $this->assertStringNotContainsString("s3cr3t", $out, "the account password must not reach the record");
        $this->assertStringNotContainsString("authc0de", $out, "the transfer auth code must not reach the record");
        $this->assertStringContainsString("myuser", $out, "non-sensitive parameters must survive masking");
        $this->assertStringContainsString("%2A%2A%2A", $out, "both sensitive values must be masked, not dropped");
    }

    /**
     * The byte-for-byte pin on the IBS record. `enableDebugMode()` emitted
     * exactly this before the format/sink split (RSRMID-2925) and must keep
     * emitting it; the substring assertions above would all survive a changed
     * label, separator or indent, and this one is why that does not matter.
     */
    public function testTheRecordIsByteForByteWhatItWasBeforeTheSplit(): void
    {
        // Note the CRLFs: the indent is inserted after each \n of the raw plain
        // response, so the wire's \r stays where it was.
        $expected = "R E Q U E S T\n\tAPI:  https://api.internet.bs/\n\tPOST: apikey=mykey\n\n"
            . "R E S P O N S E\n\tstatus=SUCCESS\r\n\tmessage=Command completed successfully\r\n\t";

        $this->assertSame($expected, self::$logger->format("apikey=mykey", self::$successResponse));
    }

    /**
     * Default behaviour is unchanged: with the shipped echo sink, log() emits
     * exactly the bytes format() returns.
     */
    public function testLogEchoesTheFormattedStringByDefault(): void
    {
        $expected = self::$logger->format("apikey=mykey&password=***", self::$successResponse);
        $this->expectOutputString($expected);
        self::$logger->log("apikey=mykey&password=***", self::$successResponse);
    }

    public function testLogWritesToTheInjectedSinkInsteadOfOutput(): void
    {
        $sink = new CollectingSink();
        $logger = new Logger($sink);
        $this->expectOutputString("");
        $logger->log("apikey=mykey&password=***", self::$successResponse);
        $this->assertSame(
            $logger->format("apikey=mykey&password=***", self::$successResponse),
            $sink->contents()
        );
    }
}
