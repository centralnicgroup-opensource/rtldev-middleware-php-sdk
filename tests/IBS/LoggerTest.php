<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\IBS\Logger;
use CNIC\IBS\Response;
use PHPUnit\Framework\TestCase;

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

    public function testLogOutputContainsRequestSection(): void
    {
        ob_start();
        self::$logger->log("domain=test.com&apikey=mykey&password=***", self::$successResponse);
        $output = (string)ob_get_clean();
        $this->assertStringContainsString("R E Q U E S T", $output);
    }

    public function testLogOutputContainsResponseSection(): void
    {
        ob_start();
        self::$logger->log("domain=test.com&apikey=mykey&password=***", self::$successResponse);
        $output = (string)ob_get_clean();
        $this->assertStringContainsString("R E S P O N S E", $output);
    }

    public function testLogOutputContainsPostData(): void
    {
        ob_start();
        self::$logger->log("domain=test.com&apikey=mykey&password=***", self::$successResponse);
        $output = (string)ob_get_clean();
        $this->assertStringContainsString("domain=test.com&apikey=mykey&password=***", $output);
    }

    public function testLogOutputContainsApiUrl(): void
    {
        ob_start();
        self::$logger->log("apikey=mykey&password=***", self::$successResponse);
        $output = (string)ob_get_clean();
        $this->assertStringContainsString("https://api.internet.bs/", $output);
    }

    public function testLogWithoutErrorOmitsHttpErrorLine(): void
    {
        ob_start();
        self::$logger->log("apikey=mykey&password=***", self::$successResponse);
        $output = (string)ob_get_clean();
        $this->assertStringNotContainsString("HTTP communication failed", $output);
    }

    public function testLogWithErrorIncludesHttpErrorLine(): void
    {
        ob_start();
        self::$logger->log("apikey=mykey&password=***", self::$successResponse, "Connection timed out");
        $output = (string)ob_get_clean();
        $this->assertStringContainsString("HTTP communication failed: Connection timed out", $output);
    }

    public function testLogWithNullErrorOmitsHttpErrorLine(): void
    {
        ob_start();
        self::$logger->log("apikey=mykey&password=***", self::$successResponse);
        $output = (string)ob_get_clean();
        $this->assertStringNotContainsString("HTTP communication failed", $output);
    }

    public function testLogWithEmptyErrorOmitsHttpErrorLine(): void
    {
        ob_start();
        self::$logger->log("apikey=mykey&password=***", self::$successResponse, "");
        $output = (string)ob_get_clean();
        $this->assertStringNotContainsString("HTTP communication failed", $output);
    }

    public function testLogResponsePlainIsIndented(): void
    {
        ob_start();
        self::$logger->log("apikey=mykey&password=***", self::$successResponse);
        $output = (string)ob_get_clean();
        // Each line of the response plain is prefixed with a tab
        $this->assertMatchesRegularExpression("/\n\t/", $output);
    }
}
