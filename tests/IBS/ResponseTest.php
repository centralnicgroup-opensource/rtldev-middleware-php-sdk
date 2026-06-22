<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\IBS\Response as R;
use CNIC\IBS\ResponseParser as RP;
use CNIC\IBS\ResponseTemplateManager as RTM;
use CNIC\IBS\ResponseTranslator as RT;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testParseResponseWithDates(): void
    {
        $raw = (string) json_encode([
            "date" => "2021/12/31",
            "expirydate" => "2026/12/31",
            "paiduntil" => "2023/07/01",
            "EXPIRATION" => "2024/05/02"
        ]);
        $expected = [
            'date' => '2021-12-31',
            'expirydate' => '2026-12-31',
            'paiduntil' => '2023-07-01',
            'EXPIRATION' => '2024-05-02'
        ];
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithSpecialCharacters(): void
    {
        $input = [
            "idcard" => "1122/23/12",
            "key2"   => "value-with-spaces",
            "key3"   => "value/with/slashes"
        ];
        $raw = (string) json_encode($input);
        $expected = $input;
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithMultipleEqualSigns(): void
    {
        $input = [
            "key1" => "value=with=multiple=equals",
            "key2" => "=value2"
        ];
        $raw = (string) json_encode($input);
        $expected = $input;
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithUtf8AndSpecialCharacters(): void
    {
        $input = [
            'name' => 'José',
            'emoji' => '😊',
            'symbols' => '©™®'
        ];
        $raw = (string) json_encode($input);
        $expected = $input;
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithNumericKeysAndValues(): void
    {
        $input = [
            '123' => '456',
            '789' => '012'
        ];
        $raw = (string) json_encode($input);
        $expected = $input;
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithSingleValidLine(): void
    {
        $input = [
            'key1' => 'value1'
        ];
        $raw = (string) json_encode($input);
        $expected = $input;
        $this->assertSame($expected, RP::parse($raw));
    }

    // --- ResponseParser: plain text and invalid ---

    public function testParsePlainTextResponse(): void
    {
        $raw = "status=FAILURE\r\nmessage=403 Forbidden\r\n";
        $result = RP::parse($raw);
        $this->assertSame('FAILURE', $result['status']);
        $this->assertSame('403 Forbidden', $result['message']);
    }

    public function testParseInvalidResponse(): void
    {
        $raw = "this is not valid at all";
        $result = RP::parse($raw);
        $this->assertSame('FAILURE', $result['status']);
        $this->assertSame('423 Invalid API response. Contact Support', $result['message']);
    }

    // --- ResponseTemplateManager tests ---

    public function testGetTemplateNotFound(): void
    {
        $tpl = RTM::getTemplate("IwontExist");
        $this->assertEquals("FAILURE", $tpl->getStatus());
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
        $this->assertEquals("FAILURE", $tpl->getStatus());
        $this->assertEquals("Forbidden", $tpl->getDescription());

        // providing template by status and description
        $tplid = "custom2_403";
        RTM::addTemplate($tplid, "FAILURE", "Forbidden");
        $this->assertTrue(RTM::hasTemplate($tplid));
        $tpl = RTM::getTemplate($tplid);
        $this->assertEquals("FAILURE", $tpl->getStatus());
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
        $this->assertEquals("FAILURE", $r->getStatus());
        $this->assertEquals("423 Empty API response. Probably unreachable API end point", $r->getDescription());
    }

    public function testInvalidResponse(): void
    {
        // JSON without status field
        $raw = RT::translate('{"somekey":"somevalue"}', []);
        $r = new R($raw);
        $this->assertEquals("FAILURE", $r->getStatus());
        $this->assertEquals("423 Invalid API response. Contact Support", $r->getDescription());

        // plain text without status field
        $raw2 = RT::translate("somekey=somevalue\r\n", []);
        $r2 = new R($raw2);
        $this->assertEquals("FAILURE", $r2->getStatus());
        $this->assertEquals("423 Invalid API response. Contact Support", $r2->getDescription());
    }

    public function testHttpErrorTemplate(): void
    {
        $r = new R("httperror|Connection timed out");
        $this->assertTrue($r->isError());
        $this->assertEquals("FAILURE", $r->getStatus());
        $this->assertStringContainsString("Connection timed out", $r->getDescription());
    }

    // --- JSON response tests (with ResponseFormat=JSON command) ---

    public function testJsonSuccessResponse(): void
    {
        $cmd = ["ResponseFormat" => "JSON"];
        $json = '{"transactid":"xyz789","status":"SUCCESS","domain":"ibstest.com","expirationdate":"2026/02/20"}';
        $r = new R($json, $cmd);
        $this->assertTrue($r->isSuccess());
        $this->assertEquals("SUCCESS", $r->getStatus());
        $this->assertEquals("ibstest.com", $r->getHash()["domain"]);
        $this->assertEquals("2026-02-20", $r->getHash()["expirationdate"]);
    }

    public function testJsonErrorResponse(): void
    {
        $cmd = ["ResponseFormat" => "JSON"];
        $json = '{"transactid":"abc123","status":"FAILURE","message":"Permission denied! \"available123test.com\" permission is not granted.","code":100005}';
        $r = new R($json, $cmd);
        $this->assertTrue($r->isError());
        $this->assertEquals("FAILURE", $r->getStatus());
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
        $this->assertEquals("2026-02-20", $r->getHash()["expirationdate"]);
        $this->assertEquals("2025-02-20", $r->getHash()["registrationdate"]);
        $this->assertEquals("2026-02-20", $r->getHash()["paiduntil"]);
        // nested objects and arrays preserved
        $this->assertIsArray($r->getHash()["contacts"]);
        $nameserver = $r->getHash()["nameserver"];
        $this->assertIsArray($nameserver);
        $this->assertEquals([
            "registrant" => ["firstname" => "Middle", "lastname" => "Ware"],
            "admin"      => ["firstname" => "Kai",    "lastname" => "Schwarz"],
        ], $r->getHash()["contacts"]);
        $this->assertEquals("ns1.ispapi.net", $nameserver[0]);

        // One column per top-level JSON key: 10 total
        $this->assertCount(10, $r->getColumns());
        $colKeys = $r->getColumnKeys();
        $this->assertContains("domain", $colKeys);
        $this->assertContains("nameserver", $colKeys);
        $this->assertContains("contacts", $colKeys);

        // Two records: nameserver is the longest column (length 2)
        $this->assertCount(2, $r->getRecords());
    }

    public function testNoCurlTemplate(): void
    {
        $r = new R("nocurl");
        $this->assertTrue($r->isError());
        $this->assertEquals("FAILURE", $r->getStatus());
        $this->assertStringContainsString("curl_init failed", $r->getDescription());
    }

    public function testEmptyResponseWithJsonCommand(): void
    {
        $cmd = ["ResponseFormat" => "JSON"];
        $r = new R("", $cmd);
        $this->assertTrue($r->isError());
        $this->assertEquals("FAILURE", $r->getStatus());
        $this->assertStringContainsString("Empty API response", $r->getDescription());
    }
}
