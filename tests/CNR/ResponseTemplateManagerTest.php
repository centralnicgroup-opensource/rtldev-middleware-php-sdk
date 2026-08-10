<?php

declare(strict_types=1);

namespace CNICTEST\CNR;

use CNIC\CNR\Response as R;
use CNIC\CNR\ResponseTemplateManager as RTM;
use PHPUnit\Framework\TestCase;

final class ResponseTemplateManagerTest extends TestCase
{
    public function testGetTemplateNotFound(): void
    {
        $tpl = (new RTM())->getTemplate("IwontExist");
        $this->assertEquals(500, $tpl->getCode());
        $this->assertEquals("Response Template not found", $tpl->getDescription());
    }

    public function testGetTemplates(): void
    {
        $rtm = new RTM();
        $tpl = $rtm->getTemplates();
        $keys = array_keys($rtm->getRawTemplates());
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $tpl);
        }
    }

    public function testIsTemplateMatchHash(): void
    {
        $tpl = new R("");
        $this->assertEquals(true, (new RTM())->isTemplateMatchHash($tpl->getHash(), "empty"));
    }

    public function testIsTemplateMatchHashWithAMissingMatchKeyReturnsFalse(): void
    {
        // See the IBS twin: an incomplete hash must answer false rather than
        // emit "Undefined array key" and compare null (RSRMID-2941).
        $rtm = new RTM();
        $this->assertFalse($rtm->isTemplateMatchHash(["CODE" => "423"], "empty"));
        $this->assertFalse($rtm->isTemplateMatchHash(["DESCRIPTION" => "whatever"], "empty"));
        $this->assertFalse($rtm->isTemplateMatchHash([], "empty"));
    }

    public function testIsTemplateMatchPlain(): void
    {
        $tpl = new R("");
        $this->assertEquals(true, (new RTM())->isTemplateMatchPlain($tpl->getPlain(), "empty"));
    }

    public function testAddTemplate(): void
    {
        // providing template in plain text
        $rtm = new RTM();
        $tplid = "custom404";
        $descr = "Page not found";
        $code = 421;

        $rtm->addTemplate($tplid, "[RESPONSE]\r\nCODE=$code\r\nDESCRIPTION=$descr\r\nEOF\r\n");
        $this->assertEquals(true, $rtm->hasTemplate($tplid));
        $tpl = $rtm->getTemplate($tplid);
        $this->assertEquals($code, $tpl->getCode());
        $this->assertEquals($descr, $tpl->getDescription());
        // providing template by code and description
        $tplid = "custom2_404";
        $rtm->addTemplate($tplid, "$code", $descr);
        $this->assertEquals(true, $rtm->hasTemplate($tplid));
        $tpl = $rtm->getTemplate($tplid);
        $this->assertEquals($code, $tpl->getCode());
        $this->assertEquals($descr, $tpl->getDescription());
    }

    public function testRegistriesDoNotShareRegisteredTemplates(): void
    {
        // No tearDownAfterClass here on purpose (RSRMID-2941): nothing this
        // class registers reaches another, so there is nothing to reset.
        $mine = (new RTM())->addTemplate("scoped", "200", "only mine");
        $theirs = new RTM();

        $this->assertTrue($mine->hasTemplate("scoped"));
        $this->assertFalse($theirs->hasTemplate("scoped"));
        $this->assertTrue($theirs->hasTemplate("empty"), "the brand's built-ins are still there");
    }
}
