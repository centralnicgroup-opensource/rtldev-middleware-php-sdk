<?php

declare(strict_types=1);

namespace CNICTEST\MONIKER;

use CNIC\IBS\Response as R;
use CNIC\IBS\ResponseTemplateManager as RTM;
use CNIC\IBS\ResponseTranslator as RT;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    // --- ResponseTemplateManager tests ---

    public function testGetTemplateNotFound(): void
    {
        $tpl = RTM::getTemplate("IwontExist");
        $this->assertEquals("FAILURE", $tpl->getHash()["status"] ?? null);
        $this->assertEquals("500 Response Template not found", $tpl->getDescription());
    }

    public function testGetTemplates(): void
    {
        $tpl = RTM::getTemplates();
        foreach (array_keys(RTM::$templates) as $key) {
            $this->assertArrayHasKey($key, $tpl);
        }
    }

    public function testIsTemplateMatchHash(): void
    {
        $r = new R("");
        $this->assertTrue(RTM::isTemplateMatchHash($r->getHash(), "empty"));
    }

    public function testIsTemplateMatchPlain(): void
    {
        $r = new R("");
        $this->assertTrue(RTM::isTemplateMatchPlain($r->getPlain(), "empty"));
    }

    public function testAddTemplate(): void
    {
        // providing template in plain text
        $tplid = "custom403";
        RTM::addTemplate($tplid, "status=FAILURE\r\nmessage=Forbidden\r\n");
        $this->assertTrue(RTM::hasTemplate($tplid));
        $tpl = RTM::getTemplate($tplid);
        $this->assertEquals("FAILURE", $tpl->getHash()["status"] ?? null);
        $this->assertEquals("Forbidden", $tpl->getDescription());

        // providing template by status and description
        $tplid = "custom2_403";
        RTM::addTemplate($tplid, "FAILURE", "Forbidden");
        $this->assertTrue(RTM::hasTemplate($tplid));
        $tpl = RTM::getTemplate($tplid);
        $this->assertEquals("FAILURE", $tpl->getHash()["status"] ?? null);
        $this->assertEquals("Forbidden", $tpl->getDescription());
    }

    // --- ResponseTranslator tests ---

    public function testPlaceHolderReplacements(): void
    {
        // no placeholders left in response when none provided
        $r = new R("");
        $this->assertEquals(0, preg_match("/\{[A-Z_]+\}/", $r->getDescription()));

        // placeholder replaced when provided
        $r = new R("", [], ["CONNECTION_URL" => "123HXPHFOUND123"]);
        $this->assertStringContainsString("123HXPHFOUND123", $r->getDescription());
    }

    public function testConstructorEmptyResponse(): void
    {
        $r = new R("");
        $this->assertEquals("FAILURE", $r->getHash()["status"] ?? null);
        $this->assertEquals("423 Empty API response. Probably unreachable API end point", $r->getDescription());
    }

    public function testInvalidResponse(): void
    {
        // JSON without status field
        $raw = RT::translate('{"somekey":"somevalue"}', []);
        $r = new R($raw);
        $this->assertEquals("FAILURE", $r->getHash()["status"] ?? null);
        $this->assertEquals("423 Invalid API response. Contact Support", $r->getDescription());

        // plain text without status field
        $raw2 = RT::translate("somekey=somevalue\r\n", []);
        $r2 = new R($raw2);
        $this->assertEquals("FAILURE", $r2->getHash()["status"] ?? null);
        $this->assertEquals("423 Invalid API response. Contact Support", $r2->getDescription());
    }

    public function testHttpErrorTemplate(): void
    {
        $r = new R("httperror|Connection timed out");
        $this->assertTrue($r->isError());
        $this->assertEquals("FAILURE", $r->getHash()["status"] ?? null);
        $this->assertStringContainsString("Connection timed out", $r->getDescription());
    }

    // --- JSON response tests (with ResponseFormat=JSON command) ---

    public function testJsonSuccessResponse(): void
    {
        $cmd = ["ResponseFormat" => "JSON"];
        $json = '{"transactid":"xyz789","status":"SUCCESS","domain":"ibstest.com","expirationdate":"2026/02/20"}';
        $r = new R($json, $cmd);
        $this->assertTrue($r->isSuccess());
        $this->assertEquals("SUCCESS", $r->getHash()["status"] ?? null);
        $this->assertEquals("ibstest.com", $r->getHash()["domain"]);
        $this->assertEquals("2026/02/20", $r->getHash()["expirationdate"]);
    }

    public function testJsonErrorResponse(): void
    {
        $cmd = ["ResponseFormat" => "JSON"];
        $json = '{"transactid":"abc123","status":"FAILURE","message":"Permission denied! \"available123test.com\" permission is not granted.","code":100005}';
        $r = new R($json, $cmd);
        $this->assertTrue($r->isError());
        $this->assertEquals("FAILURE", $r->getHash()["status"] ?? null);
        $this->assertStringContainsString("Permission denied!", $r->getDescription());
        $this->assertEquals(100005, $r->getCode());
    }

    public function testJsonDomainInfoResponse(): void
    {
        $cmd = ["ResponseFormat" => "JSON"];
        $data = [
            "transactid" => "8986680508b740347a73e339b5c3bd67",
            "status" => "SUCCESS",
            "domain" => "ibstest.com",
            "expirationdate" => "2026/02/20",
            "registrationdate" => "2025/02/20",
            "paiduntil" => "2026/02/20",
            "domainstatus" => "EXPIRED",
            "contacts" => [
                "registrant" => ["firstname" => "Middle", "lastname" => "Ware"],
                "admin" => ["firstname" => "Kai", "lastname" => "Schwarz"]
            ],
            "nameserver" => ["ns1.ispapi.net", "ns2.ispapi.net"],
            "transferauthinfo" => "qCg+ic'G1m"
        ];
        $json = (string) json_encode($data);
        $r = new R($json, $cmd);
        $this->assertTrue($r->isSuccess());
        $this->assertEquals("ibstest.com", $r->getHash()["domain"]);
        $this->assertEquals("2026/02/20", $r->getHash()["expirationdate"]);
        $this->assertEquals("2025/02/20", $r->getHash()["registrationdate"]);
        $this->assertEquals("2026/02/20", $r->getHash()["paiduntil"]);
        // nested objects and arrays preserved
        $this->assertIsArray($r->getHash()["contacts"]);
        $nameserver = $r->getHash()["nameserver"];
        $this->assertIsArray($nameserver);
        $this->assertEquals([
            "registrant" => ["firstname" => "Middle", "lastname" => "Ware"],
            "admin"      => ["firstname" => "Kai",    "lastname" => "Schwarz"],
        ], $r->getHash()["contacts"]);
        $this->assertEquals("ns1.ispapi.net", $nameserver[0]);
    }

    public function testNoCurlTemplate(): void
    {
        $r = new R("nocurl");
        $this->assertTrue($r->isError());
        $this->assertEquals("FAILURE", $r->getHash()["status"] ?? null);
        $this->assertStringContainsString("curl_init failed", $r->getDescription());
    }

    public function testEmptyResponseWithJsonCommand(): void
    {
        $cmd = ["ResponseFormat" => "JSON"];
        $r = new R("", $cmd);
        $this->assertTrue($r->isError());
        $this->assertEquals("FAILURE", $r->getHash()["status"] ?? null);
        $this->assertStringContainsString("Empty API response", $r->getDescription());
    }

    #[\Override]
    public static function tearDownAfterClass(): void
    {
        // Templates are process-wide static state — drop this class' own so
        // they do not leak into later test classes (RSRMID-2924).
        RTM::resetTemplates();
    }
}
