<?php

declare(strict_types=1);

//declare(strict_types=1);

namespace CNICTEST\CNR;

use CNIC\ClientFactory as CF;
use CNIC\CNR\Client as CL;
use CNIC\CNR\Response as R;
use CNIC\CNR\ResponseTemplateManager as RTM;
use CNIC\CNR\SessionClient;
use CNIC\Exception\PaginationException;
use CNIC\IDNA\Factory\ConverterFactory;
use CNIC\RoleCredentialsInterface;
use CNIC\System;
use CNICTEST\Support\Cassettes;
use CNICTEST\Support\CassetteTransport;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public static SessionClient $cl;
    /**
     * @var CassetteTransport record/replay transport driving the request() path
     */
    public static CassetteTransport $tape;
    /**
     * @var string absolute path to this brand's cassette directory
     */
    public static string $cassetteDir;
    /**
     * @var string user name (with role as we don't know the account pw)
     */
    public static string $user;
    /**
     * @var string user name excluding role
     */
    public static string $userNoRole;
    /**
     * @var string password
     */
    public static string $pw;
    /**
     * @var string role id
     */
    public static string $role;
    /**
     * @var string role password
     */
    public static string $rolepw;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        self::$cl = CF::cnr();
        self::$cassetteDir = __DIR__ . "/cassettes";
        self::$tape = Cassettes::attach(self::$cl, self::$cassetteDir);

        if (Cassettes::isRecording()) {
            // Record mode: real OTE calls, so real credentials are required.
            // Never exit(1) — a missing-cred record run skips cleanly (RSRMID-2910).
            self::$user = (string) getenv("RTLDEV_MW_CI_USER_CNR");
            self::$role = (string) getenv("RTLDEV_MW_CI_ROLE_CNR");
            self::$rolepw = (string) getenv("RTLDEV_MW_CI_ROLEPASSWORD_CNR");
            if (self::$user === "" || self::$role === "" || self::$rolepw === "") {
                self::markTestSkipped(
                    "Recording needs RTLDEV_MW_CI_USER_CNR / RTLDEV_MW_CI_ROLE_CNR / RTLDEV_MW_CI_ROLEPASSWORD_CNR."
                );
            }
            // qmtest pass is unknown, we emulate it via role
            self::$userNoRole = self::$user;
            self::$pw = self::$rolepw;
            self::$user .= ":" . self::$role;
            return;
        }

        // Replay mode (default): deterministic dummy credentials. The transport
        // is served from committed cassettes, so credentials are never sent; the
        // fixed values only keep the getPOSTData() masking assertions stable.
        self::$userNoRole = "test.user";
        self::$role = "test.role";
        self::$pw = self::$rolepw = "test.pw";
        self::$user = self::$userNoRole . ":" . self::$role;
    }

    #[\Override]
    protected function tearDown(): void
    {
        Cassettes::throttle(); // record mode only; replay needs no delay
        parent::tearDown();
    }

    public function testGetPostDataSecured(): void
    {
        self::$cl->setCredentials(self::$user, self::$pw);
        $enc = self::$cl->getPOSTData([
            "COMMAND" => "CheckAuthentication",
            "SUBUSER" => self::$user,
            "PASSWORD" => self::$pw
        ], true);

        $expected = http_build_query([
            "s_login" => self::$user,
            "s_pw" => "***",
            "s_command" => implode("\n", [
                "COMMAND=CheckAuthentication",
                "SUBUSER=" . self::$user,
                "PASSWORD=***"
            ])
        ]);

        self::$cl->setCredentials();

        $this->assertEquals(
            $expected,
            $enc
        );
    }

    public function testGetPostDataSecuredMasksAuth(): void
    {
        // AUTH (EPP transfer authorization code) must be masked in the secured
        // POST body used for debug logging, not only PASSWORD (RSRMID-2897).
        $enc = self::$cl->getPOSTData([
            "COMMAND" => "TransferDomain",
            "DOMAIN" => "example.com",
            "AUTH" => "sup3r-s3cr3t-auth"
        ], true);

        $expected = http_build_query([
            "s_command" => implode("\n", [
                "COMMAND=TransferDomain",
                "DOMAIN=example.com",
                "AUTH=***"
            ])
        ]);

        $this->assertEquals($expected, $enc);
        $this->assertStringNotContainsString("sup3r-s3cr3t-auth", $enc);
    }

    public function testGetPostDataObj(): void
    {
        $enc = self::$cl->getPOSTData([
            "COMMAND" => "ModifyDomain",
            "AUTH" => "gwrgwqg%&\\44t3*"
        ]);
        $this->assertEquals(
            "s_command=COMMAND%3DModifyDomain%0AAUTH%3Dgwrgwqg%25%26%5C44t3%2A",
            $enc
        );
    }

    public function testGetPostDataNull(): void
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

    public function testGetSessionIdSet(): void
    {
        $sess = "testsession12345";
        $sessid = self::$cl->setSession($sess)->getSession();
        $this->assertEquals($sessid, $sess);
        self::$cl->setSession();
    }

    public function testGetUrl(): void
    {
        $url = self::$cl->getURL();
        $this->assertEquals($url, self::$cl->getLiveUrl());
    }

    /**
     * The base URL now carries only host + trailing slash; the script path is
     * the default `$path` appended by AbstractClient::performRequest(). Lock the
     * realign + concatenation so neither drifts back into the SocketConfig URL.
     * Exercised on OT&E to avoid a LIVE production call — the connection URL is
     * recorded on the Response regardless of the HTTP outcome (RSRMID-2909).
     */
    public function testRequestResolvesConnectionUrl(): void
    {
        $cl = new SessionClient();
        $tape = Cassettes::attach($cl, self::$cassetteDir);
        $tape->useCassette("resolve-connection-url");
        $cl->setCredentials(self::$user, self::$pw)->useOTESystem();
        $r = $cl->request(["COMMAND" => "StatusAccount"]);
        $this->assertEquals("https://api-ote.rrpproxy.net/api/call.cgi", $r->getRequestURL());
    }

    public function testGetSystem(): void
    {
        // LIVE is the default; isOTE() must agree with getSystem()
        $this->assertSame(System::LIVE, self::$cl->getSystem());
        $this->assertFalse(self::$cl->isOTE());

        self::$cl->useOTESystem();
        $this->assertSame(System::OTE, self::$cl->getSystem());
        $this->assertTrue(self::$cl->isOTE());

        // restore default so later tests keep the expected baseline
        self::$cl->useLIVESystem();
        $this->assertSame(System::LIVE, self::$cl->getSystem());
        $this->assertFalse(self::$cl->isOTE());
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

    public function testSetContext(): void
    {
        // use a dedicated client so credential/context state does not leak
        // into the shared static client used by the other tests
        $cl = new SessionClient();
        $tape = Cassettes::attach($cl, self::$cassetteDir);
        $tape->useCassette("set-context");
        $context = ["traceId" => "abc123", "attempt" => 1];
        $cls = $cl->setContext($context);
        $this->assertInstanceOf(CL::class, $cls);

        // context set on the client must propagate into every Response
        $cl->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
        $r = $cl->request(["COMMAND" => "CheckDomains", "DOMAIN" => ["example.com"]]);
        $this->assertSame($context, $r->getContext());
    }

    public function testSetUrl(): void
    {
        $oldurl = self::$cl->getURL();
        $hostname = parse_url($oldurl, PHP_URL_HOST);
        if (is_string($hostname) && $hostname !== "") {
            $newurl = str_replace($hostname, "127.0.0.1", $oldurl);
            $url = self::$cl->setURL($newurl)->getURL();
            $this->assertEquals($url, $newurl);
            self::$cl->setURL($oldurl);
        }
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
        // credentials have to be unset when session id is set
        self::$cl->setRoleCredentials("myaccountid", "myrole", "mypassword")
            ->setSession("12345678");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals(
            "s_login=myaccountid%3Amyrole&s_sessionid=12345678&s_command=COMMAND%3DStatusAccount",
            $tmp
        );
    }

    public function testSetSessionReset(): void
    {
        self::$cl->setSession();
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals(
            "s_login=myaccountid%3Amyrole&s_command=COMMAND%3DStatusAccount",
            $tmp
        );
    }

    public function testSaveReuseSession(): void
    {
        $session = [];
        self::$cl->setSession("12345678")
            ->saveSession($session);
        $cl2 = new SessionClient();
        $cl2->reuseSession($session);
        $tmp = $cl2->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals(
            "s_login=myaccountid%3Amyrole&s_sessionid=12345678&s_command=COMMAND%3DStatusAccount",
            $tmp
        );
        self::$cl->setSession();
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

    public function testImplementsRoleCredentialsInterface(): void
    {
        // Role credentials are a CNR-only capability, segregated onto
        // RoleCredentialsInterface (mirrors ExtendedResponseInterface). The CNR
        // client implements the seam; IBS/Moniker deliberately do not.
        $this->assertInstanceOf(RoleCredentialsInterface::class, self::$cl);
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

    public function testLoginCredsOk(): void
    {
        self::$tape->useCassette("login-creds-ok");
        self::$cl->useOTESystem()->setCredentials(self::$user, self::$pw);
        $r = self::$cl->login();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true, $r->getPlain());
        $rec = $r->getRecord(0);
        $this->assertNotNull($rec);
        $this->assertNotNull($rec->getDataByKey("SESSIONID"));
    }

    public function testLoginRoleCredsOk(): void
    {
        self::$tape->useCassette("login-role-creds-ok");
        self::$cl->setRoleCredentials(self::$userNoRole, self::$role, self::$rolepw);
        $r = self::$cl->login();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true, $r->getPlain());
        $rec = $r->getRecord(0);
        $this->assertNotNull($rec);
        $this->assertNotNull($rec->getDataByKey("SESSIONID"));
    }

    public function testLoginCredsFail(): void
    {
        self::markTestSkipped("CNR locks accounts temporarily on failed login attempts / temp. ip ban, so we skip this test for now.");
        //self::$cl->setCredentials("UNKNOWNACC", "WRONGPASSWORD");
        //$r = self::$cl->login();
        //$this->assertInstanceOf(R::class, $r);
        //$this->assertEquals($r->isError(), true, $r->getPlain());
    }

    //TODO -> not covered: login failed; http timeout
    //TODO -> not covered: login succeeded; no session returned

    public function testLogoutOk(): void
    {
        self::$tape->useCassette("logout-ok");
        self::$cl->setCredentials(self::$user, self::$pw);
        $r = self::$cl->login();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true, $r->getPlain());
        $r = self::$cl->logout();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true, $r->getPlain());
    }

    public function testLogoutFail(): void
    {
        self::$tape->useCassette("logout-fail");
        $r = self::$cl->logout();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isError(), true);
    }

    /**
     * HTTP communication failure maps to code 421. Driven by a hand-authored
     * `conn-error` cassette (a captured `httperror|…` tuple), so the exact
     * failure and description are exercised offline — replacing the former
     * bogus-host integration test. Always replay: a dedicated replay-only
     * transport means a record run never overwrites the fixture with a
     * resolver-dependent message (RSRMID-2910).
     */
    public function testRequestCurlExecFail2(): void
    {
        $cl = new SessionClient();
        $tape = new CassetteTransport(null, self::$cassetteDir, false);
        $cl->setTransport($tape);
        $tape->useCassette("conn-error");
        $cl->useOTESystem();
        $r = $cl->request([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), false);
        $this->assertEquals($r->getCode(), 421);
        $this->assertEquals($r->getDescription(), "Command failed due to HTTP communication error (Could not resolve host: gregeragregaegaegag.com).");
    }

    public function testRequestFlattenCommand(): void
    {
        self::$tape->useCassette("flatten-command");
        self::$cl->setCredentials(self::$user, self::$pw)
            ->useOTESystem(); // re-reads the correct OTE URL from settings
        $r = self::$cl->request([
            "COMMAND" => "CheckDomains",
            "DOMAIN" => ["example.com", "example.net"]
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true, (
            $r->getCommandPlain() . "\n\n" .
            $r->getPlain() . "\n\n" .
            self::$cl->getPOSTData($r->getCommand())
        ));
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
            $tmp = \idn_to_ascii(
                $idn,
                (bool)preg_match("/\.(art|be|ca|de|fr|pm|re|swiss|tf|wf|yt)\.?$/i", $idn) ?
                    IDNA_NONTRANSITIONAL_TO_ASCII :
                    IDNA_DEFAULT,
                INTL_IDNA_VARIANT_UTS46
            );
            // idn_to_ascii() returns string|false; a false here is a genuine
            // conversion failure, not an empty result. Assert it away first so
            // the message below concatenates a real string (no cast needed) and
            // a failure reads clearly instead of as an empty conversion.
            $this->assertNotFalse($tmp, "idn_to_ascii() failed for: " . $idn);
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

    public function testRequestAutomaticIdnConvert(): void
    {
        self::$tape->useCassette("idn-convert");
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

    public function testRequestAutomaticIdnConvert1a(): void
    {
        self::$tape->useCassette("idn-convert-1a");
        self::$cl->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
        $r = self::$cl->request([
            "COMMAND" => "StatusNameserver",
            "NAMESERVER" => "dömäin.example"
        ]);
        $this->assertInstanceOf(R::class, $r);
        /*$this->assertEquals($r->isSuccess(), false);
        $this->assertEquals($r->getCode(), 545);*/
        // TODO:---------- EXCEPTION [BEGIN] --------
        // Api-side idn conversion isn't yet implemented for NAMESERVER parameters.
        // You get "505 Invalid attribute value syntax; NAMESERVER: (dömain.com)" [kschwarz]
        // JIRA ISSUE ID - RSRBE-7149
        // If covered, the api command shouldn't get changed any longer.
        $cmd = $r->getCommand();
        $convert = ConverterFactory::convert($cmd["NAMESERVER"]);
        $this->assertEquals($cmd["NAMESERVER"], $convert["punycode"]);
        //--------------- EXCEPTION [END] -----------
    }

    public function testRequestAutomaticIdnConvert2(): void
    {
        self::$tape->useCassette("idn-convert-2");
        self::$cl->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
        $r = self::$cl->request([
            "COMMAND" => "StatusDomain",
            "OBJECTID" => "dömäin.com",
            "OBJECTCLASS" => "DOMAIN",
            "MINDATE" => date("Y-m-d H:i:s"),
            "LIMIT" => 1
        ]);
        // $this->assertInstanceOf(R::class, $r);
        // $this->assertEquals($r->isSuccess(), true);
        // $this->assertEquals($r->getCode(), 200);
        $cmd = $r->getCommand();
        $keys = array_keys($cmd);
        $this->assertEquals(in_array("OBJECTID", $keys), true);
        $convert = ConverterFactory::convert($cmd["OBJECTID"]);
        $this->assertEquals($cmd["OBJECTID"], $convert["punycode"]);
    }

    public function testRequestCodeTmpErrorDbg(): void
    {
        self::$tape->useCassette("code-tmperror-dbg");
        self::$cl->enableDebugMode()
            ->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
        $r = self::$cl->request(["COMMAND" => "StatusAccount"]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $this->assertEquals($r->getCode(), 200);
        $this->assertEquals($r->getDescription(), "Command completed successfully");
        //TODO: this response is a tmp error in php-sdk; "httperror" template
    }

    public function testRequestCodeTmpErrorNoDbg(): void
    {
        self::$tape->useCassette("code-tmperror-nodbg");
        self::$cl->disableDebugMode();
        $r = self::$cl->request(["COMMAND" => "StatusAccount"]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $this->assertEquals($r->getCode(), 200);
        $this->assertEquals($r->getDescription(), "Command completed successfully");
        //TODO: this response is a tmp error in php-sdk; "httperror" template
    }

    public function testRequestNextResponsePageNoLast(): void
    {
        self::$tape->useCassette("next-page-no-last");
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
        $this->assertEquals($nr->isSuccess(), true);
        $this->assertEquals($nr->getRecordsLimitation(), 2);
        $this->assertEquals($nr->getRecordsCount(), 2);
        $this->assertEquals($nr->getFirstRecordIndex(), 2);
        $this->assertEquals($nr->getLastRecordIndex(), 3);
        $this->assertEquals($r->getRecordsLimitation(), 2);
        $this->assertEquals($r->getRecordsCount(), 2);
        $this->assertEquals($r->getFirstRecordIndex(), 0);
        $this->assertEquals($r->getLastRecordIndex(), 1);
    }

    public function testRequestNextResponsePageLast(): void
    {
        self::$tape->useCassette("next-page-last");
        $this->expectException(PaginationException::class);
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
        self::$tape->useCassette("next-page-no-first");
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
        $this->assertEquals($nr->isSuccess(), true);
        $this->assertEquals($nr->getRecordsLimitation(), 2);
        $this->assertEquals($nr->getRecordsCount(), 2);
        $this->assertEquals($nr->getFirstRecordIndex(), 2);
        $this->assertEquals($nr->getLastRecordIndex(), 3);
        $this->assertEquals($r->getRecordsLimitation(), 2);
        $this->assertEquals($r->getRecordsCount(), 2);
        $this->assertEquals($r->getFirstRecordIndex(), 0);
        $this->assertEquals($r->getLastRecordIndex(), 1);
    }

    public function testRequestNextResponsePageZeroLimit(): void
    {
        // Real CNR response shape for `QueryDomainList` with LIMIT = 0:
        // count/limit come back as 0 while total reflects the full list size.
        // Without the guard in requestNextResponsePage(), $first never advances
        // and requestAllResponsePages() would loop forever.
        RTM::addTemplate(
            "listLimitZero",
            "[RESPONSE]\r\nPROPERTY[COUNT][0]=0\r\nPROPERTY[FIRST][0]=0\r\nPROPERTY[LAST][0]=0\r\n"
            . "PROPERTY[LIMIT][0]=0\r\nPROPERTY[TOTAL][0]=1725494\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=0.286\r\nEOF\r\n"
        );
        $r = new R("listLimitZero", [
            "COMMAND" => "QueryDomainList",
            "FIRST" => "0",
            "LIMIT" => "0"
        ]);
        $this->assertTrue($r->isSuccess());
        $this->assertSame(0, $r->getRecordsLimitation());
        $this->assertSame(1725494, $r->getRecordsTotalCount());
        // The guard must stop pagination rather than re-request the same page.
        $this->assertNull(self::$cl->requestNextResponsePage($r));
    }

    public function testRequestNextResponsePageLastPage(): void
    {
        // Final page of a multi-page list (FIRST=8, LIMIT=2, TOTAL=10): the
        // current page already holds the last rows, so there is no next page.
        // Response::hasNextPage() returns false here, and requestNextResponsePage()
        // must return null accordingly (termination logic is no longer duplicated).
        RTM::addTemplate(
            "listLastPage",
            "[RESPONSE]\r\nPROPERTY[COUNT][0]=2\r\nPROPERTY[FIRST][0]=8\r\nPROPERTY[LAST][0]=9\r\n"
            . "PROPERTY[LIMIT][0]=2\r\nPROPERTY[TOTAL][0]=10\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=0.286\r\nEOF\r\n"
        );
        $r = new R("listLastPage", [
            "COMMAND" => "QueryDomainList",
            "FIRST" => "8",
            "LIMIT" => "2"
        ]);
        $this->assertTrue($r->isSuccess());
        $this->assertFalse($r->hasNextPage());
        $this->assertNull($r->getNextPageNumber());
        $this->assertNull(self::$cl->requestNextResponsePage($r));
    }

    public function testRequestAllResponsePagesOk(): void
    {
        self::$tape->useCassette("all-pages");
        self::$cl->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
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
        self::$cl->setReferer("https://www.centralnicreseller.com/");
        $this->assertEquals(self::$cl->getReferer(), "https://www.centralnicreseller.com/");
        self::$cl->setReferer();
        $this->assertEquals(self::$cl->getReferer(), null);
    }

    public function testUseHighPerformanceConnectionSetup(): void
    {
        $oldurl = self::$cl->getURL();
        $hostname = parse_url($oldurl, PHP_URL_HOST);
        if (is_string($hostname) && $hostname !== "") {
            $newurl = str_replace($hostname, "127.0.0.1", $oldurl);
            $newurl = str_replace("https://", "http://", $newurl);
            self::$cl->useHighPerformanceConnectionSetup();
            $this->assertEquals(self::$cl->getURL(), $newurl);
        }
    }

    /**
     * High-performance setup must swap only the scheme and host, preserving the
     * port, path and query — even when the hostname also appears in the path.
     */
    public function testHighPerformanceSetupRewritesOnlyHostAndScheme(): void
    {
        $cl = CF::cnr();
        $cl->setURL("https://api.example.com:8443/api.example.com/call.cgi?foo=bar");
        $cl->useHighPerformanceConnectionSetup();
        $this->assertSame(
            "http://127.0.0.1:8443/api.example.com/call.cgi?foo=bar",
            $cl->getURL()
        );
    }

    public function testSortCommandParams(): void
    {
        $params = [
            "OWNERCONTACT0STATE" => "Chrzanów",
            "ADMINCONTACT0ZIP" => "32-500",
            "TECHCONTACT0COUNTRY" => "PL",
            "TECHCONTACT0LASTNAME" => "Dudek",
            "OWNERCONTACT0FIRSTNAME" => "Adrian",
            "ADMINCONTACT0FIRSTNAME" => "Adrian",
            "ADMINCONTACT0EMAIL" => "kontakt@weblix.pl",
            "OWNERCONTACT0COUNTRY" => "PL",
            "BILLINGCONTACT0PHONE" => "791748958",
            "NAMESERVER1" => "ns2.hostlix.pl",
            "BILLINGCONTACT0EMAIL" => "kontakt@weblix.pl",
            "OWNERCONTACT0PHONE" => "791748958",
            "TRANSFERLOCK" => "1",
            "TECHCONTACT0ORGANIZATION" => "Weblix Adrian Dudek",
            "NAMESERVER2" => "ns3.hostlix.pl",
            "BILLINGCONTACT0STATE" => "Chrzanów",
            "BILLINGCONTACT0STREET" => "Jana Peckowskiego 2/2",
            "ADMINCONTACT0STREET" => "Jana Peckowskiego 2/2",
            "TECHCONTACT0FIRSTNAME" => "Adrian",
            "OWNERCONTACT0CITY" => "malopolska",
            "NAMESERVER3" => "ns4.hostlix.pl",
            "DOMAIN0" => "przewodnik-trojmiasto.pl",
            "DOMAIN1" => "przewodnik-trojmiasto.pl",
            "DNSZONE" => "przewodnik-trojmiasto.pl.",
            "WIDE" => "1",
            "OWNERCONTACT0ORGANIZATION" => "Weblix Adrian Dudek",
            "NAMESERVER0" => "ns1.hostlix.pl",
            "OWNERCONTACT0STREET" => "Jana Peckowskiego 2/2",
            "OWNERCONTACT0EMAIL" => "kontakt@weblix.pl",
            "BILLINGCONTACT0LASTNAME" => "Dudek",
            "BILLINGCONTACT0COUNTRY" => "PL",
            "TECHCONTACT0STREET" => "Jana Peckowskiego 2/2",
            "ADMINCONTACT0COUNTRY" => "PL",
            "BILLINGCONTACT0ZIP" => "32-500",
            "TECHCONTACT0PHONE" => "791748958",
            "BILLINGCONTACT0ORGANIZATION" => "Weblix Adrian Dudek",
            "ADMINCONTACT0CITY" => "malopolska",
            "BILLINGCONTACT0CITY" => "malopolska",
            "OWNERCONTACT0ZIP" => "32-500",
            "OWNERCONTACT0LASTNAME" => "Dudek",
            "COMMAND" => "AddDomains",
            "TECHCONTACT0ZIP" => "32-500",
            "ADMINCONTACT0STATE" => "Chrzanów",
            "ADMINCONTACT0LASTNAME" => "Dudek",
            "ADMINCONTACT0PHONE" => "791748958",
            "PERIOD" => "1",
            "ACTION" => "1",
            "ZONE" => "1",
            "TECHCONTACT0STATE" => "Chrzanów",
            "TECHCONTACT0CITY" => "malopolska",
            "ADMINCONTACT0ORGANIZATION" => "Weblix Adrian Dudek",
            "BILLINGCONTACT0CONTACT" => "P-332424",
            "BILLINGCONTACT0FIRSTNAME" => "Adrian",
            "TECHCONTACT0EMAIL" => "kontakt@weblix.pl",
            "ZELDA" => "1",
            "yorks" => "1",
            "LOVE" => "PHP"
        ];

        self::$tape->useCassette("sort-command-params");
        $response = self::$cl->request($params);
        $expected = [
            "COMMAND" => "AddDomains",
            "DNSZONE" => "przewodnik-trojmiasto.pl.",
            "DOMAIN0" => "przewodnik-trojmiasto.pl",
            "DOMAIN1" => "przewodnik-trojmiasto.pl",
            "NAMESERVER0" => "ns1.hostlix.pl",
            "NAMESERVER1" => "ns2.hostlix.pl",
            "NAMESERVER2" => "ns3.hostlix.pl",
            "NAMESERVER3" => "ns4.hostlix.pl",
            "ZONE" => "1",
            "ACTION" => "1",
            "PERIOD" => "1",
            "WIDE" => "1",
            "TRANSFERLOCK" => "1",
            "OWNERCONTACT0FIRSTNAME" => "Adrian",
            "OWNERCONTACT0LASTNAME" => "Dudek",
            "OWNERCONTACT0ORGANIZATION" => "Weblix Adrian Dudek",
            "OWNERCONTACT0STREET" => "Jana Peckowskiego 2/2",
            "OWNERCONTACT0ZIP" => "32-500",
            "OWNERCONTACT0CITY" => "malopolska",
            "OWNERCONTACT0STATE" => "Chrzanów",
            "OWNERCONTACT0COUNTRY" => "PL",
            "OWNERCONTACT0PHONE" => "791748958",
            "OWNERCONTACT0EMAIL" => "kontakt@weblix.pl",
            "ADMINCONTACT0FIRSTNAME" => "Adrian",
            "ADMINCONTACT0LASTNAME" => "Dudek",
            "ADMINCONTACT0ORGANIZATION" => "Weblix Adrian Dudek",
            "ADMINCONTACT0STREET" => "Jana Peckowskiego 2/2",
            "ADMINCONTACT0ZIP" => "32-500",
            "ADMINCONTACT0CITY" => "malopolska",
            "ADMINCONTACT0STATE" => "Chrzanów",
            "ADMINCONTACT0COUNTRY" => "PL",
            "ADMINCONTACT0PHONE" => "791748958",
            "ADMINCONTACT0EMAIL" => "kontakt@weblix.pl",
            "TECHCONTACT0FIRSTNAME" => "Adrian",
            "TECHCONTACT0LASTNAME" => "Dudek",
            "TECHCONTACT0ORGANIZATION" => "Weblix Adrian Dudek",
            "TECHCONTACT0STREET" => "Jana Peckowskiego 2/2",
            "TECHCONTACT0ZIP" => "32-500",
            "TECHCONTACT0CITY" => "malopolska",
            "TECHCONTACT0STATE" => "Chrzanów",
            "TECHCONTACT0COUNTRY" => "PL",
            "TECHCONTACT0PHONE" => "791748958",
            "TECHCONTACT0EMAIL" => "kontakt@weblix.pl",
            "BILLINGCONTACT0FIRSTNAME" => "Adrian",
            "BILLINGCONTACT0LASTNAME" => "Dudek",
            "BILLINGCONTACT0ORGANIZATION" => "Weblix Adrian Dudek",
            "BILLINGCONTACT0STREET" => "Jana Peckowskiego 2/2",
            "BILLINGCONTACT0ZIP" => "32-500",
            "BILLINGCONTACT0CITY" => "malopolska",
            "BILLINGCONTACT0STATE" => "Chrzanów",
            "BILLINGCONTACT0COUNTRY" => "PL",
            "BILLINGCONTACT0PHONE" => "791748958",
            "BILLINGCONTACT0EMAIL" => "kontakt@weblix.pl",
            "BILLINGCONTACT0CONTACT" => "P-332424",
            "LOVE" => "PHP",
            "YORKS" => "1",
            "ZELDA" => "1",
        ];
        $this->assertEquals($expected, $response->getCommand());
    }
}
