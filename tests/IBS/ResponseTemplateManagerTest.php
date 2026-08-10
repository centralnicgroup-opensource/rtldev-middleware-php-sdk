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
        $tpl = (new RTM())->getTemplate("IwontExist");
        $this->assertEquals("FAILURE", $tpl->getHash()["status"] ?? null);
        $this->assertEquals("500 Response Template not found", $tpl->getDescription());
    }

    public function testGetTemplates(): void
    {
        $rtm = new RTM();
        $tpl = $rtm->getTemplates();
        foreach (array_keys($rtm->getRawTemplates()) as $key) {
            $this->assertArrayHasKey($key, $tpl);
        }
    }

    public function testGenerateTemplate(): void
    {
        $this->assertSame(
            "status=SUCCESS\r\nmessage=Command completed successfully\r\n",
            (new RTM())->generateTemplate("SUCCESS", "Command completed successfully")
        );
    }

    public function testHasTemplate(): void
    {
        $rtm = new RTM();
        $this->assertTrue($rtm->hasTemplate("empty"));
        $this->assertFalse($rtm->hasTemplate("IwontExist"));
    }

    public function testIsTemplateMatchHash(): void
    {
        $rtm = new RTM();
        $r = new R("");
        $this->assertTrue($rtm->isTemplateMatchHash($r->getHash(), "empty"));

        // non-matching hash returns false
        $this->assertFalse($rtm->isTemplateMatchHash(
            ["status" => "SUCCESS", "message" => "Command completed successfully"],
            "empty"
        ));
    }

    public function testIsTemplateMatchHashWithAMissingMatchKeyReturnsFalse(): void
    {
        // A hash short of one of the brand's two match keys is ordinary caller
        // input, not a programming error: this used to index both hashes
        // unguarded and emit "Undefined array key" before comparing null
        // (RSRMID-2941). Both directions matter — a missing key on either side.
        $rtm = new RTM();
        $this->assertFalse($rtm->isTemplateMatchHash(["status" => "FAILURE"], "empty"));
        $this->assertFalse($rtm->isTemplateMatchHash(["message" => "whatever"], "empty"));
        $this->assertFalse($rtm->isTemplateMatchHash([], "empty"));
    }

    public function testIsTemplateMatchPlain(): void
    {
        $rtm = new RTM();
        $r = new R("");
        $this->assertTrue($rtm->isTemplateMatchPlain($r->getPlain(), "empty"));

        // non-matching plain response returns false
        $this->assertFalse($rtm->isTemplateMatchPlain(
            "status=SUCCESS\r\nmessage=Command completed successfully\r\n",
            "empty"
        ));
    }

    public function testIsTemplateMatchPlainAgreesWithAResponseParsedOnTheOtherBranch(): void
    {
        // The manager parses with no command, which selects the JSON branch;
        // this Response carries a command *without* ResponseFormat, so its own
        // populate() takes the plain-text branch. That is the divergent pair —
        // passing ResponseFormat=JSON here would put both on the same branch and
        // prove nothing. Matching a template must not depend on which branch
        // produced the hash. (Ref: RSRMID-2924.)
        $rtm = new RTM();
        $r = new R("", ["Command" => "DomainInfo"]);
        $this->assertTrue($rtm->isTemplateMatchHash($r->getHash(), "empty"));
        $this->assertTrue($rtm->isTemplateMatchPlain($r->getPlain(), "empty"));
    }

    public function testAddTemplate(): void
    {
        // providing template in plain text
        $rtm = new RTM();
        $tplid = "custom403";
        $rtm->addTemplate($tplid, "status=FAILURE\r\nmessage=Forbidden\r\n");
        $this->assertTrue($rtm->hasTemplate($tplid));
        $tpl = $rtm->getTemplate($tplid);
        $this->assertEquals("FAILURE", $tpl->getHash()["status"] ?? null);
        $this->assertEquals("Forbidden", $tpl->getDescription());

        // providing template by status and description
        $tplid = "custom2_403";
        $rtm->addTemplate($tplid, "FAILURE", "Forbidden");
        $this->assertTrue($rtm->hasTemplate($tplid));
        $tpl = $rtm->getTemplate($tplid);
        $this->assertEquals("FAILURE", $tpl->getHash()["status"] ?? null);
        $this->assertEquals("Forbidden", $tpl->getDescription());
    }

    public function testAddTemplateReturnsTheSameInstanceForChaining(): void
    {
        // The static predecessor returned `new static()` — a throwaway instance
        // of an all-static class, so the return value was fluent in shape only.
        // Now it must be the very object that received the template, or a chain
        // would register onto something the caller never sees (RSRMID-2941).
        $rtm = new RTM();
        $this->assertSame($rtm, $rtm->addTemplate("chainA", "FAILURE", "A"));

        $rtm->addTemplate("chainB", "FAILURE", "B")->addTemplate("chainC", "FAILURE", "C");
        $this->assertTrue($rtm->hasTemplate("chainB"));
        $this->assertTrue($rtm->hasTemplate("chainC"));
    }

    public function testRegistriesDoNotShareRegisteredTemplates(): void
    {
        // The point of RSRMID-2941: registering a template must not be visible
        // to anyone who did not ask for it. There is no tearDown here on
        // purpose — nothing this class registers can outlive its own instances,
        // which is exactly what the deleted resetTemplates() used to paper over.
        $mine = (new RTM())->addTemplate("scoped", "FAILURE", "only mine");
        $theirs = new RTM();

        $this->assertTrue($mine->hasTemplate("scoped"));
        $this->assertFalse($theirs->hasTemplate("scoped"));
        $this->assertTrue($theirs->hasTemplate("empty"), "the brand's built-ins are still there");
    }
}
