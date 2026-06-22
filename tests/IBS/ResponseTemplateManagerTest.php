<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\IBS\Response as R;
use CNIC\IBS\ResponseTemplateManager as RTM;
use PHPUnit\Framework\TestCase;

final class ResponseTemplateManagerTest extends TestCase
{
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

    public function testGenerateTemplate(): void
    {
        $this->assertSame(
            "status=SUCCESS\r\nmessage=Command completed successfully\r\n",
            RTM::generateTemplate("SUCCESS", "Command completed successfully")
        );
    }

    public function testHasTemplate(): void
    {
        $this->assertTrue(RTM::hasTemplate("empty"));
        $this->assertFalse(RTM::hasTemplate("IwontExist"));
    }

    public function testIsTemplateMatchHash(): void
    {
        $r = new R("");
        $this->assertTrue(RTM::isTemplateMatchHash($r->getHash(), "empty"));

        // non-matching hash returns false
        $this->assertFalse(RTM::isTemplateMatchHash(
            ["status" => "SUCCESS", "message" => "Command completed successfully"],
            "empty"
        ));
    }

    public function testIsTemplateMatchPlain(): void
    {
        $r = new R("");
        $this->assertTrue(RTM::isTemplateMatchPlain($r->getPlain(), "empty"));

        // non-matching plain response returns false
        $this->assertFalse(RTM::isTemplateMatchPlain(
            "status=SUCCESS\r\nmessage=Command completed successfully\r\n",
            "empty"
        ));
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

    public function testAddTemplateReturnsSelfForChaining(): void
    {
        $this->assertInstanceOf(RTM::class, RTM::addTemplate("chainA", "FAILURE", "A"));

        RTM::addTemplate("chainB", "FAILURE", "B")::addTemplate("chainC", "FAILURE", "C");
        $this->assertTrue(RTM::hasTemplate("chainB"));
        $this->assertTrue(RTM::hasTemplate("chainC"));
    }
}
