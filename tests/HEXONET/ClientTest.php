<?php

//declare(strict_types=1);

namespace CNICTEST;

use CNIC\ClientFactory as CF;
use CNIC\HEXONET\Client as CL;
use CNIC\HEXONET\Response as R;

final class HexonetClientTest extends \PHPUnit\Framework\TestCase
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
            "registrar" => "HEXONET"
        ]);
        self::$user = getenv("TESTS_USER_HEXONET") ?: "";
        self::$pw = getenv("TESTS_USERPASSWORD_HEXONET") ?: "";
    }

    public function testGetPOSTDataSecured(): void
    {
        self::$cl->setCredentials(self::$user, self::$pw);
        $enc = self::$cl->getPOSTData([
            "COMMAND" => "CheckAuthentication",
            "SUBUSER" => self::$user,
            "PASSWORD" => self::$pw
        ], true);
        self::$cl->setCredentials();
        $this->assertEquals(
            http_build_query([
                "s_entity" => "54cd",
                "s_login" => self::$user,
                "s_pw" => "***",
                "s_command" => "COMMAND=CheckAuthentication\nSUBUSER=" . self::$user . "\nPASSWORD=***"
            ]),
            $enc
        );
    }

    public function testGetPOSTDataObj(): void
    {
        $enc = self::$cl->getPOSTData([
            "COMMAND" => "ModifyDomain",
            "AUTH" => "gwrgwqg%&\\44t3*"
        ]);
        //$this->assertEquals($enc, "s_entity=54cd&COMMAND=ModifyDomain%0AAUTH%3Dgwrgwqg%25%26%5C44t3%2A");
        $this->assertEquals("s_entity=54cd&s_command=COMMAND%3DModifyDomain%0AAUTH%3Dgwrgwqg%25%26%5C44t3%2A", $enc);
    }

    public function testGetPOSTDataStr(): void
    {
        $enc = self::$cl->getPOSTData("COMMAND=StatusAccount");
        $this->assertEquals("s_entity=54cd&s_command=COMMAND%3DStatusAccount", $enc);
    }

    public function testGetPOSTDataNull(): void
    {
        $enc = self::$cl->getPOSTData([
            "COMMAND" => "ModifyDomain",
            "AUTH" => null
        ]);
        $this->assertEquals($enc, "s_entity=54cd&s_command=COMMAND%3DModifyDomain");
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

    public function testSetOTPSetThrows(): void
    {
        unset(self::$cl->settings["parameters"]["otp"]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Feature `OTP` not supported");
        self::$cl->setOTP("12345678");
    }

    public function testSetOTPSet(): void
    {
        // restore what we dropped in previous test
        self::$cl->settings["parameters"]["otp"] = "s_otp";
        self::$cl->setOTP("12345678");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_entity=54cd&s_otp=12345678&s_command=COMMAND%3DStatusAccount");
    }

    public function testSetOTPReset(): void
    {
        self::$cl->setOTP();
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_entity=54cd&s_command=COMMAND%3DStatusAccount");
    }

    public function testSetSessionSet(): void
    {
        self::$cl->setSession("12345678");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_entity=54cd&s_session=12345678&s_command=COMMAND%3DStatusAccount");
    }

    public function testSetSessionCredentials(): void
    {
        // credentials and otp code have to be unset when session id is set
        self::$cl->setRoleCredentials("myaccountid", "myrole", "mypassword")
            ->setOTP("12345678")
            ->setSession("12345678");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_entity=54cd&s_session=12345678&s_command=COMMAND%3DStatusAccount");
    }

    public function testSetSessionReset(): void
    {
        self::$cl->setSession();
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_entity=54cd&s_command=COMMAND%3DStatusAccount");
    }

    public function testSaveReuseSession(): void
    {
        self::$cl->setSession("12345678")
            ->saveSession($_SESSION);
        $cl2 = CF::getClient([
            "registrar" => "HEXONET"
        ]);
        $cl2->reuseSession($_SESSION);
        $tmp = $cl2->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_entity=54cd&s_session=12345678&s_command=COMMAND%3DStatusAccount");
        self::$cl->setSession();
    }

    public function testSetRemoteIPAddressSetThrows(): void
    {
        unset(self::$cl->settings["parameters"]["ipfilter"]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Feature `IP Filter` not supported");
        self::$cl->setRemoteIPAddress("10.10.10.10");
    }

    public function testSetRemoteIPAddressSet(): void
    {
        // restore what we dropped in previous test
        self::$cl->settings["parameters"]["ipfilter"] = "s_remoteaddr";
        self::$cl->setRemoteIPAddress("10.10.10.10");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_entity=54cd&s_remoteaddr=10.10.10.10&s_command=COMMAND%3DStatusAccount");
    }

    public function testSetRemoteIPAddressReset(): void
    {
        self::$cl->setRemoteIPAddress();
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_entity=54cd&s_command=COMMAND%3DStatusAccount");
    }

    public function testSetCredentialsSet(): void
    {
        self::$cl->setCredentials("myaccountid", "mypassword");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_entity=54cd&s_login=myaccountid&s_pw=mypassword&s_command=COMMAND%3DStatusAccount");
    }

    public function testSetCredentialsReset(): void
    {
        self::$cl->setCredentials();
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_entity=54cd&s_command=COMMAND%3DStatusAccount");
    }

    public function testSetRoleCredentialsSet(): void
    {
        self::$cl->setRoleCredentials("myaccountid", "myroleid", "mypassword");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_entity=54cd&s_login=myaccountid%21myroleid&s_pw=mypassword&s_command=COMMAND%3DStatusAccount");
    }

    public function testSetRoleCredentialsReset(): void
    {
        self::$cl->setRoleCredentials();
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_entity=54cd&s_command=COMMAND%3DStatusAccount");
    }

    public function testLoginCredsOK(): void
    {
        self::$cl->useOTESystem()
            ->setCredentials(self::$user, self::$pw);
        $r = self::$cl->login();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $rec = $r->getRecord(0);
        $this->assertNotNull($rec);
        if (!is_null($rec)) { // phpStan
            $this->assertNotNull($rec->getDataByKey("SESSION"));
        }
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
        self::$cl->useOTESystem()
            ->setCredentials(self::$user, self::$pw);
        $r = self::$cl->loginExtended([
            "TIMEOUT" => 60
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $rec = $r->getRecord(0);
        $this->assertNotNull($rec);
        if (!is_null($rec)) { // phpStan
            $this->assertNotNull($rec->getDataByKey("SESSION"));
        }
    }

    public function testLogoutOK(): void
    {
        $r = self::$cl->logout();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
    }

    public function testLogoutFAIL(): void
    {
        $r = self::$cl->logout();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isError(), true);
    }

    public function testRequestCurlInitFail(): void
    {
        $this->markTestSkipped("Re-Activate with PHP8. PHP 7.4 throws an error.");
        /*
        self::$cl->settings["env"]["ote"]["url"] = "\0";
        self::$cl->setCredentials(self::$user, self::$pw)
                ->useOTESystem();
        $r = self::$cl->request([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), false);
        $this->assertEquals($r->getCode(), 421);
        $this->assertEquals($r->getDescription(), "Command failed due to HTTP communication error");
        */
    }

    public function testRequestCurlExecFail2(): void
    {
        self::$cl->settings["env"]["ote"]["url"] = "http://gregeragregaegaegag.com/geragaerg/call.cgi";
        self::$cl->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
        $r = self::$cl->request([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), false);
        $this->assertEquals($r->getCode(), 421);
        $this->assertEquals($r->getDescription(), "Command failed due to HTTP communication error");
    }

    public function testRequestFlattenCommand(): void
    {
        $cfgpath = implode(DIRECTORY_SEPARATOR, ["src", "HEXONET", "config.json"]);
        $orgsettings = json_decode(file_get_contents($cfgpath), true);
        // restore
        self::$cl->settings["env"]["ote"]["url"] = $orgsettings["env"]["ote"]["url"];
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
        $cmd = $r->getCommand();
        $keys = array_keys($cmd);
        $this->assertEquals(in_array("DOMAIN0", $keys), true);
        $this->assertEquals(in_array("DOMAIN1", $keys), true);
        $this->assertEquals(in_array("DOMAIN2", $keys), true);
        $this->assertEquals(in_array("DOMAIN", $keys), false);
        $this->assertEquals($cmd["DOMAIN0"], "example.com");
        $this->assertEquals($cmd["DOMAIN1"], "xn--dmin-moa0i.example");
        $this->assertEquals($cmd["DOMAIN2"], "example.net");
    }

    public function testRequestAUTOIdnConvert2(): void
    {
        self::$cl->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
        $r = self::$cl->request([
            "COMMAND" => "QueryObjectlogList",
            "OBJECTID" => "dömäin.example",
            "OBJECTCLASS" => "DOMAIN",
            "MINDATE" => date("Y-m-d H:i:s"),
            "OPERATIONTYPE" => "INBOUND_TRANSFER",
            "OPERATIONSTATUS" => "SUCCESSFUL",
            "ORDERBY" => "LOGDATEDESC",
            "LIMIT" => 1
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $cmd = $r->getCommand();
        $this->assertEquals($r->getCode(), 200);
        $keys = array_keys($cmd);
        $this->assertEquals(in_array("OBJECTID", $keys), true);
        $this->assertEquals($cmd["OBJECTID"], "xn--dmin-moa0i.example");
    }

    public function testRequestAUTOIdnConvert3(): void
    {
        self::$cl->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
        $r = self::$cl->request([
            "COMMAND" => "QueryObjectlogList",
            "OBJECTID" => "dömäin.example",
            "OBJECTCLASS" => "SSLCERT",
            "MINDATE" => date("Y-m-d H:i:s"),
            "OPERATIONTYPE" => "INBOUND_TRANSFER",
            "OPERATIONSTATUS" => "SUCCESSFUL",
            "ORDERBY" => "LOGDATEDESC",
            "LIMIT" => 1
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), false);
        $cmd = $r->getCommand();
        $this->assertEquals($r->getCode(), 541);
        $keys = array_keys($cmd);
        $this->assertEquals(in_array("OBJECTID", $keys), true);
        $this->assertEquals($cmd["OBJECTID"], "dömäin.example");
    }

    public function testRequestCodeTmpErrorDbg(): void
    {
        self::$cl->enableDebugMode()
            ->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
        $r = self::$cl->request(["COMMAND" => "GetUserIndex"]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $this->assertEquals($r->getCode(), 200);
        $this->assertEquals($r->getDescription(), "Command completed successfully");
        //TODO: this response is a tmp error in node-sdk; "httperror" template
    }

    public function testRequestCodeTmpErrorNoDbg(): void
    {
        self::$cl->disableDebugMode();
        $r = self::$cl->request(["COMMAND" => "GetUserIndex"]);
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
        if (!is_null($nr)) { // phpStan
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
        if (!is_null($nr)) { // phpStan
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
            "COMMAND" => "QueryUserList",
            "FIRST" => 0,
            "LIMIT" => 10
        ]);
        $this->assertGreaterThan(0, count($pages));
        foreach ($pages as &$p) {
            $this->assertInstanceOf(R::class, $p);
            $this->assertEquals($p->isSuccess(), true);
        }
    }

    public function testSetUserView(): void
    {
        self::$cl->setUserView("hexotestman.com");
        $r = self::$cl->request([
            "COMMAND" => "GetUserIndex"
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
    }

    public function testResetUserView(): void
    {
        self::$cl->setUserView();
        $r = self::$cl->request([
            "COMMAND" => "GetUserIndex"
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
