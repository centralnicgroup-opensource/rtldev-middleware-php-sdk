<?php

//declare(strict_types=1);

namespace CNICTEST;

use CNIC\ClientFactory as CF;
use CNIC\HEXONET\Client as CL;
use CNIC\HEXONET\Response as R;

final class CNRClientTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \CNIC\HEXONET\SessionClient $cl
     */
    public static $cl;
    /**
     * @var string user name
     */
    public static $user;
    /**
     * @var string password
     */
    public static $pw;

    public static function setUpBeforeClass(): void
    {
        //session_start();
        self::$cl = CF::getClient([
            "registrar" => "CNR"
        ]);
        self::$user = getenv("TESTS_USER_CNR") ?: "";
        self::$pw = getenv("TESTS_USERPASSWORD_CNR") ?: "";
    }

    public function testGetPOSTDataSecured(): void
    {
        self::$cl->setCredentials(self::$user, self::$pw);
        $enc = self::$cl->getPOSTData([
            "COMMAND" => "CheckAuthentication",
            "SUBUSER" => self::$user,
            "PASSWORD" => self::$pw
        ], true);
        #$pwenc = rawurlencode(self::$pw);
        self::$cl->setCredentials();

        $expected = implode("&", [
            "s_login=" . self::$user,
            "s_pw=%2A%2A%2A",
            implode("%0A", [
                "s_command=COMMAND%3DCheckAuthentication",
                "SUBUSER%3D" . self::$user,
                "PASSWORD%3D%2A%2A%2A"
            ])
        ]);

        $this->assertEquals(
            $expected,
            $enc
        );
    }

    public function testGetPOSTDataObj(): void
    {
        $enc = self::$cl->getPOSTData([
            "COMMAND" => "ModifyDomain",
            "AUTH" => "gwrgwqg%&\\44t3*"
        ]);
        $this->assertEquals("s_command=COMMAND%3DModifyDomain%0AAUTH%3Dgwrgwqg%25%26%5C44t3%2A", $enc);
    }

    public function testGetPOSTDataStr(): void
    {
        $enc = self::$cl->getPOSTData("COMMAND=StatusAccount");
        $this->assertEquals("s_command=COMMAND%3DStatusAccount", $enc);
    }

    public function testGetPOSTDataNull(): void
    {
        $enc = self::$cl->getPOSTData([
            "COMMAND" => "ModifyDomain",
            "AUTH" => null
        ]);
        $this->assertEquals("s_command=COMMAND%3DModifyDomain", $enc);
    }

    public function testGetSession(): void
    {
        $sessid = self::$cl->getSession();
        $this->assertNull($sessid);
    }

    public function testGetSessionIDSet(): void
    {
        $sess = "testsession12345";
        $sessid = self::$cl->setSession($sess)->getSession();
        $this->assertEquals($sessid, $sess);
        self::$cl->setSession();
    }

    public function testGetURL(): void
    {
        $url = self::$cl->getURL();
        $this->assertEquals($url, self::$cl->settings["env"]["live"]["url"]);
    }

    public function testGetUserAgent(): void
    {
        $ua = "PHP-SDK (" . PHP_OS . "; " . php_uname("m") . "; rv:" . self::$cl->getVersion() . ") php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
        $this->assertEquals(self::$cl->getUserAgent(), $ua);
    }

    public function testSetUserAgent(): void
    {
        $pid = "WHMCS";
        $rv = "7.7.0";
        $ua = $pid . " (" . PHP_OS . "; " . php_uname("m") . "; rv:" . $rv . ") php-sdk/" . self::$cl->getVersion() . " php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
        $cls = self::$cl->setUserAgent($pid, $rv);
        $this->assertInstanceOf(CL::class, $cls);
        $this->assertEquals(self::$cl->getUserAgent(), $ua);
    }

    public function testSetUserAgentModules(): void
    {
        $pid = "WHMCS";
        $rv = "7.7.0";
        $mods = ["reg/2.6.2", "ssl/7.2.2", "dc/8.2.2"];
        $ua = $pid . " (" . PHP_OS . "; " . php_uname("m") . "; rv:" . $rv . ") " . implode(" ", $mods) . " php-sdk/" . self::$cl->getVersion() . " php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
        $cls = self::$cl->setUserAgent($pid, $rv, $mods);
        $this->assertInstanceOf(CL::class, $cls);
        $this->assertEquals(self::$cl->getUserAgent(), $ua);
    }

    public function testSetURL(): void
    {
        $oldurl = self::$cl->getURL();
        $hostname = parse_url($oldurl, PHP_URL_HOST);
        if (!empty($hostname)) {
            $newurl = str_replace($hostname, "127.0.0.1", $oldurl);
            $url = self::$cl->setURL($newurl)->getURL();
            $this->assertEquals($url, $newurl);
            self::$cl->setURL($oldurl);
        }
    }

    public function testSetOTPSet(): void
    {
        $this->expectException(\Exception::class);
        self::$cl->setOTP("12345678");
    }

    public function testSetOTPReset(): void
    {
        self::$cl->setOTP();
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_command=COMMAND%3DStatusAccount");
    }

    public function testSetSessionSet(): void
    {
        self::$cl->setSession("12345678");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_sessionid=12345678&s_command=COMMAND%3DStatusAccount");
    }

    public function testSetSessionCredentials(): void
    {
        // credentials and otp code have to be unset when session id is set
        self::$cl->setRoleCredentials("myaccountid", "myrole", "mypassword")
            ->setSession("12345678");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_sessionid=12345678&s_command=COMMAND%3DStatusAccount");
    }

    public function testSetSessionReset(): void
    {
        self::$cl->setSession();
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_command=COMMAND%3DStatusAccount");
    }

    public function testSaveReuseSession(): void
    {
        self::$cl->setSession("12345678")
            ->saveSession($_SESSION);
        $cl2 = CF::getClient([
            "registrar" => "CNR"
        ]);
        $cl2->reuseSession($_SESSION);
        $tmp = $cl2->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_sessionid=12345678&s_command=COMMAND%3DStatusAccount");
        self::$cl->setSession();
    }

    public function testSetRemoteIPAddressSet(): void
    {
        $this->expectException(\Exception::class);
        self::$cl->setRemoteIPAddress("10.10.10.10");
    }

    public function testSetRemoteIPAddressReset(): void
    {
        self::$cl->setRemoteIPAddress();
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_command=COMMAND%3DStatusAccount");
    }

    public function testSetCredentialsSet(): void
    {
        self::$cl->setCredentials("myaccountid", "mypassword");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_login=myaccountid&s_pw=mypassword&s_command=COMMAND%3DStatusAccount");
    }

    public function testSetCredentialsReset(): void
    {
        self::$cl->setCredentials();
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_command=COMMAND%3DStatusAccount");
    }

    public function testSetRoleCredentialsSet(): void
    {
        self::$cl->setRoleCredentials("myaccountid", "myroleid", "mypassword");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_login=myaccountid%3Amyroleid&s_pw=mypassword&s_command=COMMAND%3DStatusAccount");
    }

    public function testSetRoleCredentialsReset(): void
    {
        self::$cl->setRoleCredentials();
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_command=COMMAND%3DStatusAccount");
    }

    public function testLoginCredsOK(): void
    {
        /**
         * curl -X POST --data-urlencode 's_command=COMMAND%3DStartSession%0Apersistent%3D1' --data-urlencode 's_login=self::$user' --data-urlencode 's_pw=self::$pw' https://api-ote.rrpproxy.net/api/call.cgi
         */
        $this->markTestSkipped('RSRTPM-3111');
        /*
        self::$cl->useOTESystem()
                ->setCredentials(self::$user, self::$pw);
        $r = self::$cl->login();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $rec = $r->getRecord(0);
        $this->assertNotNull($rec);
        $this->assertNotNull($rec->getDataByKey("SESSIONID"));
        */
    }

    /*public function testLoginRoleCredsOK(): void
    {
        self::$cl->setRoleCredentials(self::$user, "testrole", self::$pw);
        $r = self::$cl->login();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $rec = $r->getRecord(0);
        $this->assertNotNull($rec);
        $this->assertNotNull($rec->getDataByKey("SESSION"));
    }*/

    public function testLoginCredsFAIL(): void
    {
        self::$cl->setCredentials(self::$user, "WRONGPASSWORD");
        $r = self::$cl->login();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isError(), true);
    }

    //TODO -> not covered: login failed; http timeout
    //TODO -> not covered: login succeeded; no session returned

    public function testLoginExtendedCredsOK(): void
    {
        $this->markTestSkipped('RSRTPM-3111'); //TODO
        /*
        self::$cl->useOTESystem()
                ->setCredentials(self::$user, self::$pw);
        $r = self::$cl->loginExtended([
            "TIMEOUT" => 60
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $rec = $r->getRecord(0);
        $this->assertNotNull($rec);
        $this->assertNotNull($rec->getDataByKey("SESSION"));
        */
    }

    public function testLogoutOK(): void
    {
        $this->markTestSkipped('RSRTPM-3111'); //TODO
        /*
        $r = self::$cl->logout();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        */
    }

    public function testLogoutFAIL(): void
    {
        $r = self::$cl->logout();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isError(), true);
    }

    public function testRequestFlattenCommand(): void
    {
        self::$cl->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
        $r = self::$cl->request([
            "COMMAND" => "CheckDomains",
            "DOMAIN" => ["example.com", "example.net"]
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $this->assertEquals($r->getCode(), 200);
        $this->assertEquals($r->getDescription(), "Command completed successfully");
        $cmd = $r->getCommand();
        $keys = array_keys($cmd);
        $this->assertEquals(in_array("DOMAIN0", $keys), true);
        $this->assertEquals(in_array("DOMAIN1", $keys), true);
        $this->assertEquals(in_array("DOMAIN", $keys), false);
        $this->assertEquals($cmd["DOMAIN0"], "example.com");
        $this->assertEquals($cmd["DOMAIN1"], "example.net");
    }

    public function testIdnaToAscii(): void
    {
        // phpX-intl as requirement for idna_to_ascii
        $idns = [
            "öbb.at"                    => "xn--bb-eka.at",
            "Öbb.at"                    => "xn--bb-eka.at",
            "xn--bb-eka.at"             => "xn--bb-eka.at",
            "XN--BB-EKA.AT"             => "xn--bb-eka.at",
            "faß.de"                    => "xn--fa-hia.de",
            "faß.com"                   => "fass.com",
            "xn--fa-hia.de"             => "xn--fa-hia.de",
            //"\ud83d\udca9"              => "xn--ls8h",
            //"\ud87e\udcca"              => "xn--w60j",
            //"\udb40\udd00\ud87e\udcca"  => "xn--w60j",
            "₹.com"                     => "xn--yzg.com",
            "𑀓.com"                     => "xn--n00d.com",
            //"\u0080.com"                => "throws!",
            //"xn--a.com"                 => "throws!",
            "a‌b"                        => "ab", // different, even though looking similar
            "xn--ab-j1t"                => "xn--ab-j1t",
            "ȡog.de"                    => "xn--og-09a.de",
            "☕.de"                      => "xn--53h.de",
            "I♥NY.de"                   => "xn--iny-zx5a.de",
            "ＡＢＣ・日本.co.jp"          => "xn--abc-rs4b422ycvb.co.jp",
            "日本｡co｡jp"                 => "xn--wgv71a.co.jp",
            "日本｡co．jp"                => "xn--wgv71a.co.jp",
            //"日本⒈co．jp"               => "throws!",
            //"x\u0327\u0301.de"          => "xn--x-xbb7i.de",
            "x̧́.de"                      => "xn--x-xbb7i.de",
            //"x\u0301\u0327.de"          => "xn--x-xbb7i.de",
            "σόλος.gr"                  => "xn--wxaikc6b.gr",
            "Σόλος.gr"                  => "xn--wxaikc6b.gr",
            "ΣΌΛΟΣ.grﻋﺮﺑﻲ.de"           => "xn--wxaikc6b.xn--gr-gtd9a1b0g.de",
            "عربي.de"                   => "xn--ngbrx4e.de",
            "نامهای.de"                 => "xn--mgba3gch31f.de",
            //"نامه\u200Cای.de"           => "xn--mgba3gch31f.de"
        ];
        foreach ($idns as $idn => $ace) {
            $tmp = idn_to_ascii(
                $idn,
                ((bool)preg_match("/\.(be|ca|de|fr|pm|re|swiss|tf|wf|yt)\.?$/i", $idn)) ?
                    IDNA_NONTRANSITIONAL_TO_ASCII :
                    IDNA_DEFAULT,
                INTL_IDNA_VARIANT_UTS46
            );
            $this->assertEquals($ace, $tmp, "Failure: " . $idn . " -> " . $tmp . " vs. " . $ace);
        }
    }

    /*public function testWhmcsIdn(): void
    {
        $idns = [
            "öbb.at"                    => "xn--bb-eka.at",
            "Öbb.at"                    => "xn--bb-eka.at",
            "xn--bb-eka.at"             => "xn--bb-eka.at",
            "XN--BB-EKA.AT"             => "xn--bb-eka.at",
            "faß.de"                    => "xn--fa-hia.de",
            "faß.com"                   => "fass.com",
            "xn--fa-hia.de"             => "xn--fa-hia.de",
            //"\ud83d\udca9"              => "xn--ls8h",
            //"\ud87e\udcca"              => "xn--w60j",
            //"\udb40\udd00\ud87e\udcca"  => "xn--w60j",
            "₹.com"                     => "xn--yzg.com",
            "𑀓.com"                     => "xn--n00d.com",
            //"\u0080.com"                => "throws!",
            //"xn--a.com"                 => "throws!",
            "a‌b"                        => "ab", // different, even though looking similar
            "xn--ab-j1t"                => "xn--ab-j1t",
            "ȡog.de"                    => "xn--og-09a.de",
            "☕.de"                      => "xn--53h.de",
            "I♥NY.de"                   => "xn--iny-zx5a.de",
            "ＡＢＣ・日本.co.jp"          => "xn--abc-rs4b422ycvb.co.jp",
            "日本｡co｡jp"                 => "xn--wgv71a.co.jp",
            "日本｡co．jp"                => "xn--wgv71a.co.jp",
            //"日本⒈co．jp"               => "throws!",
            //"x\u0327\u0301.de"          => "xn--x-xbb7i.de",
            "x̧́.de"                      => "xn--x-xbb7i.de",
            //"x\u0301\u0327.de"          => "xn--x-xbb7i.de",
            "σόλος.gr"                  => "xn--wxaikc6b.gr",
            "Σόλος.gr"                  => "xn--wxaikc6b.gr",
            "ΣΌΛΟΣ.grﻋﺮﺑﻲ.de"           => "xn--wxaikc6b.xn--gr-gtd9a1b0g.de",
            "عربي.de"                   => "xn--ngbrx4e.de",
            "نامهای.de"                 => "xn--mgba3gch31f.de",
            //"نامه\u200Cای.de"           => "xn--mgba3gch31f.de"
        ];
        foreach($idns as $idn => $ace) {
            require_once("/var/www/whmcsdev1/vendor/whmcs/whmcs-foundation/lib/Domains/Idna.php");
            require_once("/var/www/whmcsdev1/vendor/whmcs/whmcs-foundation/lib/Domains/Domain.php");
            try {
                $domain = new \WHMCS\Domains\Domain($idn);
                $acenew = $domain->getDomain();
                //$idnnew = $domain->getDomain(true);
                $this->assertEquals($ace, $acenew, "Decode Failure: ". $idn . " -> " . $acenew . " vs. ". $ace);
                //$this->assertEquals($idn, $idnnew, "Encode Failure: ". $acenew . " -> " . $idnnew . " vs. ". $idn);
            } catch(\Exception $e) {
                echo "---------------------------------------------------------------------------------------------------------";
                echo "---------------------------------------------------------------------------------------------------------";
                var_dump($idn);
                echo "---------------------------------------------------------------------------------------------------------";
                echo "---------------------------------------------------------------------------------------------------------";
                var_dump($e->getMessage());
                die();
            }
        }
    }*/

    public function testRequestAUTOIdnConvert(): void
    {
        self::$cl->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
        $r = self::$cl->request([
            "COMMAND" => "CheckDomains",
            "DOMAIN" => ["example.com", "dömäin.example", "example.net"]
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $this->assertEquals($r->getCode(), 200);
        $this->assertEquals($r->getDescription(), "Command completed successfully");
        $this->assertNotNull($r->getColumn("DOMAINCHECK"));
        // If api-side idn conversion wouldn't be working, you globally get
        // 505 Invalid attribute value syntax; DOMAIN1: (e.g. xn--d^min-ira7j.com)
        // In addition the API Command has to stay unchanged
        $cmd = $r->getCommand();
        $keys = array_keys($cmd);
        $this->assertEquals(in_array("DOMAIN0", $keys), true);
        $this->assertEquals(in_array("DOMAIN1", $keys), true);
        $this->assertEquals(in_array("DOMAIN2", $keys), true);
        $this->assertEquals(in_array("DOMAIN", $keys), false);
        $this->assertEquals("example.com", $cmd["DOMAIN0"]);
        $this->assertEquals("dömäin.example", $cmd["DOMAIN1"]);
        $this->assertEquals("example.net", $cmd["DOMAIN2"]);
    }

    public function testRequestAUTOIdnConvert1a(): void
    {
        self::$cl->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
        $r = self::$cl->request([
            "COMMAND" => "StatusNameserver",
            "NAMESERVER" => "dömäin.example"
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), false);
        $this->assertEquals($r->getCode(), 545);
        // TODO:---------- EXCEPTION [BEGIN] --------
        // Api-side idn conversion isn't yet implemented for NAMESERVER parameters.
        // You get "505 Invalid attribute value syntax; NAMESERVER: (dömain.com)" [kschwarz]
        // JIRA ISSUE ID - RSRBE-7149
        // If covered, the api command shouldn't get changed any longer.
        $cmd = $r->getCommand();
        $val = function_exists("idn_to_ascii") ? "xn--dmin-moa0i.example" : "dömäin.example";
        $this->assertEquals($cmd["NAMESERVER"], $val);
        //--------------- EXCEPTION [END] -----------
    }

    public function testRequestAUTOIdnConvert2(): void
    {
        self::$cl->setCredentials(self::$user, self::$pw)
                ->useOTESystem();
        $r = self::$cl->request([
            "COMMAND" => "QueryObjectlogList",
            "OBJECTID" => "dömäin.com",
            "OBJECTCLASS" => "DOMAIN",
            "MINDATE" => date("Y-m-d H:i:s"),
            "LIMIT" => 1
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $cmd = $r->getCommand();
        $this->assertEquals($r->getCode(), 200);
        $keys = array_keys($cmd);
        $this->assertEquals(in_array("OBJECTID", $keys), true);
        $val = function_exists("idn_to_ascii") ? "xn--dmin-moa0i.com" : "dömäin.com";
        $this->assertEquals($cmd["OBJECTID"], $val);
    }

    public function testRequestCodeTmpErrorDbg(): void
    {
        self::$cl->enableDebugMode()
            ->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
        $r = self::$cl->request(["COMMAND" => "StatusAccount"]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $this->assertEquals($r->getCode(), 200);
        $this->assertEquals($r->getDescription(), "Command completed successfully");
        //TODO: this response is a tmp error in node-sdk; "httperror" template
    }

    public function testRequestCodeTmpErrorNoDbg(): void
    {
        self::$cl->disableDebugMode();
        $r = self::$cl->request(["COMMAND" => "StatusAccount"]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $this->assertEquals($r->getCode(), 200);
        $this->assertEquals($r->getDescription(), "Command completed successfully");
        //TODO: this response is a tmp error in node-sdk; "httperror" template
    }

    public function testRequestNextResponsePageNoLast(): void
    {
        $r = self::$cl->request([
            "COMMAND" => "QueryDomainList",
            "LIMIT" => 2,
            "FIRST" => 0
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $nr = self::$cl->requestNextResponsePage($r);
        $this->assertNotNull($nr);
        $this->assertInstanceOf(R::class, $nr);
        if ($nr !== null) {
            $this->assertEquals($nr->isSuccess(), true);
            $this->assertEquals($nr->getRecordsLimitation(), 2);
            $this->assertEquals($nr->getRecordsCount(), 2);
            $this->assertEquals($nr->getFirstRecordIndex(), 2);
            $this->assertEquals($nr->getLastRecordIndex(), 3);
        }
        $this->assertEquals($r->getRecordsLimitation(), 2);
        $this->assertEquals($r->getRecordsCount(), 2);
        $this->assertEquals($r->getFirstRecordIndex(), 0);
        $this->assertEquals($r->getLastRecordIndex(), 1);
    }

    public function testRequestNextResponsePageLast(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Parameter LAST in use. Please remove it to avoid issues in requestNextPage.");
        $r = self::$cl->request([
            "COMMAND" => "QueryDomainList",
            "LIMIT" => 2,
            "FIRST" => 0,
            "LAST"  => 1
        ]);
        $this->assertInstanceOf(R::class, $r);
        self::$cl->requestNextResponsePage($r);
    }

    public function testRequestNextResponsePageNoFirst(): void
    {
        self::$cl->disableDebugMode();
        $r = self::$cl->request([
            "COMMAND" => "QueryDomainList",
            "LIMIT" => 2
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $nr = self::$cl->requestNextResponsePage($r);
        $this->assertNotNull($nr);
        $this->assertInstanceOf(R::class, $nr);
        if ($nr !== null) {
            $this->assertEquals($nr->isSuccess(), true);
            $this->assertEquals($nr->getRecordsLimitation(), 2);
            $this->assertEquals($nr->getRecordsCount(), 2);
            $this->assertEquals($nr->getFirstRecordIndex(), 2);
            $this->assertEquals($nr->getLastRecordIndex(), 3);
        }

        $this->assertEquals($r->getRecordsLimitation(), 2);
        $this->assertEquals($r->getRecordsCount(), 2);
        $this->assertEquals($r->getFirstRecordIndex(), 0);
        $this->assertEquals($r->getLastRecordIndex(), 1);
    }

    public function testRequestAllResponsePagesOK(): void
    {
        $pages = self::$cl->requestAllResponsePages([
            "COMMAND" => "QueryDomainList",
            "FIRST" => 0,
            "LIMIT" => 100
        ]);
        $this->assertGreaterThan(0, count($pages));
        foreach ($pages as &$p) {
            $this->assertInstanceOf(R::class, $p);
            $this->assertEquals($p->isSuccess(), true);
        }
    }

    public function testSetUserView(): void
    {
        $this->markTestSkipped('RSRTPM-3111'); //TODO
        /*
        self::$cl->setUserView("docutest01");
        $r = self::$cl->request([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        */
    }

    public function testResetUserView(): void
    {
        self::$cl->setUserView();
        $r = self::$cl->request([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
    }

    public function testSetProxy(): void
    {
        $this->assertEquals(self::$cl->getProxy(), null);
        self::$cl->setProxy("127.0.0.1");
        $this->assertEquals(self::$cl->getProxy(), "127.0.0.1");
        self::$cl->setProxy();
        $this->assertEquals(self::$cl->getProxy(), null);
    }

    public function testSetReferer(): void
    {
        $this->assertEquals(self::$cl->getReferer(), null);
        self::$cl->setReferer("https://www.hexonet.net/");
        $this->assertEquals(self::$cl->getReferer(), "https://www.hexonet.net/");
        self::$cl->setReferer();
        $this->assertEquals(self::$cl->getReferer(), null);
    }

    public function testUseHighPerformanceConnectionSetup(): void
    {
        $oldurl = self::$cl->getURL();
        $hostname = parse_url($oldurl, PHP_URL_HOST);
        if (!empty($hostname)) {
            $newurl = str_replace($hostname, "127.0.0.1", $oldurl);
            $newurl = str_replace("https://", "http://", $newurl);
            self::$cl->useHighPerformanceConnectionSetup();
            $this->assertEquals(self::$cl->getURL(), $newurl);
        }
    }
}
