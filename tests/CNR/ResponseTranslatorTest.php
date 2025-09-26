<?php

//declare(strict_types=1);

namespace CNICTEST\CNR;

use CNIC\CNR\Response as R;
use CNIC\CNR\ResponseTranslator as RT;
use CNIC\CNR\ResponseTemplateManager as RTM;

final class ResponseTranslatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test place holder vars replacement mechanism
     */
    public function testPlaceHolderReplacements(): void
    {
        $cmd = ["COMMAND" => "StatusAccount"];

        // ensure no vars are returned in response, just in case no place holder replacements are provided
        $r = new R("");
        $this->assertEquals(0, preg_match("/\{[A-Z_]+\}/", $r->getDescription()), "case 1");

        // ensure variable replacements are correctly handled in case place holder replacements are provided
        $r = new R("", ["COMMAND" => "StatusAccount"], ["CONNECTION_URL" => "123HXPHFOUND123"]);
        $this->assertEquals(true, preg_match("/123HXPHFOUND123/", $r->getDescription()), "case 2");
    }

    /**
     * Test isTemplateMatchHash method
     */
    public function testIsTemplateMatchHash(): void
    {
        $cmd = ["COMMAND" => "StatusAccount"];
        $r = new R("");
        $this->assertTrue(RTM::isTemplateMatchHash($r->getHash(), "empty"));
    }

    /**
     * Test isTemplateMatchPlain method
     */
    public function testIsTemplateMatchPlain(): void
    {
        $cmd = ["COMMAND" => "StatusAccount"];
        $r = new R("");
        $this->assertTrue(RTM::isTemplateMatchPlain($r->getPlain(), "empty"));
    }

    /**
     * Test constructor
     */
    public function testConstructorVars(): void
    {
        $cmd = ["COMMAND" => "StatusAccount"];
        $r = new R("");
        $this->assertEquals(423, $r->getCode());
        $this->assertEquals("Empty API response. Probably unreachable API end point", $r->getDescription());
    }

    /**
     * Test constructor with invalid API response
     */
    public function testInvalidResponse(): void
    {
        $cmd = ["COMMAND" => "StatusAccount"];
        $raw = RT::translate("[RESPONSE]\r\ncode=200\r\nqueuetime=0\r\nEOF\r\n", $cmd);

        $r = new R($raw);
        $this->assertEquals(423, $r->getCode());
        $this->assertEquals("Invalid API response. Contact Support", $r->getDescription());
    }

    /**
     * Test getHash method
     */
    public function testGetHash(): void
    {
        $cmd = ["COMMAND" => "StatusAccount"];
        $r = new R("");
        $h = $r->getHash();
        $this->assertEquals("423", $h["CODE"]);
        $this->assertEquals("Empty API response. Probably unreachable API end point", $h["DESCRIPTION"]);
    }

    /**
     * Test ACL error translation
     */
    public function testAclTranslation(): void
    {
        $cmd = ["COMMAND" => "StatusAccount"];
        $r = new R("[RESPONSE]\r\ncode=530\r\ndescription=Authorization failed; Operation forbidden by ACL\r\nEOF\r\n", $cmd);
        $this->assertEquals(530, $r->getCode());
        $this->assertEquals("Authorization failed; Used Command `StatusAccount` not white-listed by your Access Control List", $r->getDescription());
    }

    /**
     * Test Authorization Failed cases translation
     */
    public function testAuthorizationFailedTranslation(): void
    {
        $cmd = [
            "COMMAND" => "TransferDomain",
            "DOMAIN" => "test.com",
            "AUTH" => "wrongauth"
        ];

        // Test case 1: Authorization failed; Authorization failed [Authorization information for accessing resource is invalid]
        $r = new R("[RESPONSE]\r\ncode=530\r\ndescription=Authorization failed; Authorization failed [Authorization information for accessing resource is invalid]\r\nEOF\r\n", $cmd);
        $this->assertEquals(530, $r->getCode());
        $this->assertEquals("The provided Authorization Code (EPP Code) is incorrect. Please verify the correct Authorization Code with the current registrar and try again.", $r->getDescription());

        // Test case 2: Authorization failed; wrong auth code
        $r = new R("[RESPONSE]\r\ncode=530\r\ndescription=Authorization failed; wrong auth code\r\nEOF\r\n", $cmd);
        $this->assertEquals(530, $r->getCode());
        $this->assertEquals("The provided Authorization Code (EPP Code) is incorrect. Please verify the correct Authorization Code with the current registrar and try again.", $r->getDescription());

        // Test case 3: Authorization failed; Authorization failed [Invalid authorization information]
        $r = new R("[RESPONSE]\r\ncode=530\r\ndescription=Authorization failed; Authorization failed [Invalid authorization information]\r\nEOF\r\n", $cmd);
        $this->assertEquals(530, $r->getCode());
        $this->assertEquals("The provided Authorization Code (EPP Code) is incorrect. Please verify the correct Authorization Code with the current registrar and try again.", $r->getDescription());

        // Test case 4: Authorization failed; Authorization failed [domain:authinfo => HASH(...)]
        $r = new R("[RESPONSE]\r\ncode=530\r\ndescription=Authorization failed; Authorization failed [domain:authinfo => HASH(0x5565172318d8): the given password is wrong or the referenced object does not exist]\r\nEOF\r\n", $cmd);
        $this->assertEquals(530, $r->getCode());
        $this->assertEquals("The provided Authorization Code (EPP Code) is incorrect. Please verify the correct Authorization Code with the current registrar and try again.", $r->getDescription());

        // Test case 5: Authorization failed; Authorization failed [epp: Incorrect authInfo...]
        $r = new R("[RESPONSE]\r\ncode=530\r\ndescription=Authorization failed; Authorization failed [epp: Incorrect authInfo. You must provide the correct authInfo to perform this operation]\r\nEOF\r\n", $cmd);
        $this->assertEquals(530, $r->getCode());
        $this->assertEquals("The provided Authorization Code (EPP Code) is incorrect. Please verify the correct Authorization Code with the current registrar and try again.", $r->getDescription());

        // Test case 6: Authorization failed; Authorization failed [domain:authinfo => HASH(...)] - second variant
        $r = new R("[RESPONSE]\r\ncode=530\r\ndescription=Authorization failed; Authorization failed [domain:authinfo => HASH(0x556ccb31d230): the given password is wrong or the referenced object does not exist]\r\nEOF\r\n", $cmd);
        $this->assertEquals(530, $r->getCode());
        $this->assertEquals("The provided Authorization Code (EPP Code) is incorrect. Please verify the correct Authorization Code with the current registrar and try again.", $r->getDescription());

        // Test case 7: Authorization failed; Authorization failed [Invalid Authorization Information]
        $r = new R("[RESPONSE]\r\ncode=530\r\ndescription=Authorization failed; Authorization failed [Invalid Authorization Information]\r\nEOF\r\n", $cmd);
        $this->assertEquals(530, $r->getCode());
        $this->assertEquals("The provided Authorization Code (EPP Code) is incorrect. Please verify the correct Authorization Code with the current registrar and try again.", $r->getDescription());

        // Test case 8: Authorization failed [Invalid authorisation code.]
        $r = new R("[RESPONSE]\r\ncode=530\r\ndescription=Authorization failed [Invalid authorisation code.]\r\nEOF\r\n", $cmd);
        $this->assertEquals(530, $r->getCode());
        $this->assertEquals("The provided Authorization Code (EPP Code) is incorrect. Please verify the correct Authorization Code with the current registrar and try again.", $r->getDescription());

        // Test case 9: Authorization failed [fury:ciracode => 8473: Authorization password has expired.]
        $r = new R("[RESPONSE]\r\ncode=530\r\ndescription=Authorization failed [fury:ciracode => 8473: Authorization password has expired.]\r\nEOF\r\n", $cmd);
        $this->assertEquals(530, $r->getCode());
        $this->assertEquals("The provided Authorization Code (EPP Code) is incorrect. Please verify the correct Authorization Code with the current registrar and try again.", $r->getDescription());

        // enable this case when COMMAND driven transfer logic is implemented with PHP-SDK
        // Test case 9: Authorization failed; Authorization failed
        // $r = new R("[RESPONSE]\r\ncode=530\r\ndescription=Authorization failed; Authorization failed\r\nEOF\r\n", $cmd);
        // $this->assertEquals(530, $r->getCode());
        // $this->assertEquals("The provided Authorization Code (EPP Code) is incorrect. Please verify the correct Authorization Code with the current registrar and try again.", $r->getDescription());
    }
}
