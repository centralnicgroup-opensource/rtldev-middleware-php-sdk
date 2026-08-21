<?php

declare(strict_types=1);

//declare(strict_types=1);

namespace CNICTEST\CNR;

use CNIC\ClientFactory as CF;
use CNIC\CNR\Client as CL;
use CNIC\CNR\Response as R;
use CNIC\CNR\ResponseTemplateManager as RTM;
use CNIC\CNR\SocketConfig as SC;
use CNIC\Exception\PaginationException;
use CNIC\Exception\UnsupportedFeatureException;
use CNIC\HttpTransport;
use CNIC\IDNA\Factory\ConverterFactory;
use CNIC\Paginator;
use CNIC\RoleCredentialsInterface;
use CNIC\System;
use CNICTEST\Support\Cassettes;
use CNICTEST\Support\CassetteTransport;
use CNICTEST\Support\SpyTransport;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public static CL $cl;
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

    /**
     * The credentials are this test's precondition, so they are stated by the
     * config it is built from rather than written onto the shared client and
     * hand-undone afterwards (RSRMID-2966). The `setCredentials()` reset that used
     * to close this test was load-bearing for the three tests below, which assert
     * an *absence* of credentials — a coupling nothing in either test mentioned.
     */
    public function testGetPostDataSecured(): void
    {
        $cl = CF::cnr((new SC())->setLogin(self::$user)->setPassword(self::$pw));
        $enc = $cl->getPOSTData([
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

        $this->assertEquals(
            $expected,
            $enc
        );
    }

    public function testGetPostDataSecuredMasksAuth(): void
    {
        // AUTH (EPP transfer authorization code) must be masked in the secured
        // POST body used for debug logging, not only PASSWORD (RSRMID-2897).
        // Credential-free by construction, not by whatever ran before.
        $enc = (new CL())->getPOSTData([
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
        $enc = (new CL())->getPOSTData([
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
        $enc = (new CL())->getPOSTData([
            "COMMAND" => "ModifyDomain",
            "AUTH" => null
        ]);
        $this->assertEquals("s_command=COMMAND%3DModifyDomain", $enc);
    }

    public function testGetSession(): void
    {
        // A fresh client has no session. On the shared client that held only while
        // no earlier test had left one behind.
        $this->assertNull((new CL())->getSession());
    }

    public function testGetSessionIdSet(): void
    {
        $sess = "testsession12345";
        // Own client, so the trailing setSession() reset this test used to need is
        // gone with it.
        $this->assertEquals($sess, (new CL())->setSession($sess)->getSession());
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
        $cl = new CL();
        $tape = Cassettes::attach($cl, self::$cassetteDir);
        $tape->useCassette("resolve-connection-url");
        $cl->setCredentials(self::$user, self::$pw)->useOTESystem();
        $r = $cl->request(["COMMAND" => "StatusAccount"]);
        $this->assertEquals("https://api-ote.rrpproxy.net/api/call.cgi", $r->getRequestURL());
    }

    /**
     * Since RSRMID-2921 the system is *derived* from the configured URL rather
     * than stored beside it, which is what stops the two disagreeing — and why
     * getSystem() is nullable: an unrecognised endpoint has no honest
     * OT&E-or-LIVE answer. Both halves are covered brand-wide in
     * CNICTEST\AbstractClientConfigDriftTest; this keeps the statement next to
     * the rest of the CNR client's contract.
     */
    public function testGetSystem(): void
    {
        // LIVE is the default; isOTE() must agree with getSystem()
        $cl = new CL();
        $this->assertSame(System::LIVE, $cl->getSystem());
        $this->assertFalse($cl->isOTE());

        $cl->useOTESystem();
        $this->assertSame(System::OTE, $cl->getSystem());
        $this->assertTrue($cl->isOTE());

        // No "restore the baseline" step: the client is this test's own, so nothing
        // later can observe the switch. Stating the same selection at construction
        // is the other half of that (RSRMID-2966).
        $ote = CF::cnr((new SC())->useOTESystem());
        $this->assertSame(System::OTE, $ote->getSystem());
        $this->assertTrue($ote->isOTE());
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
        $cl = new CL();
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
        // Own client, so there is no URL to put back afterwards — the shared client
        // is what made that restore necessary, not setURL() itself.
        $cl = new CL();
        $oldurl = $cl->getURL();
        $hostname = parse_url($oldurl, PHP_URL_HOST);
        if (is_string($hostname) && $hostname !== "") {
            $newurl = str_replace($hostname, "127.0.0.1", $oldurl);
            $this->assertEquals($newurl, $cl->setURL($newurl)->getURL());
        }
    }

    /**
     * A session with no login beside it. The precondition is the *absence* of
     * credentials, which the config states outright; on the shared client it was
     * true only because testGetPostDataSecured() happened to end with a
     * `setCredentials()` reset several tests earlier (RSRMID-2966).
     */
    public function testSetSessionSet(): void
    {
        $cl = CF::cnr((new SC())->setSession("12345678"));
        $this->assertEquals(
            "s_sessionid=12345678&s_command=COMMAND%3DStatusAccount",
            $cl->getPOSTData(["COMMAND" => "StatusAccount"])
        );
    }

    public function testSetSessionCredentials(): void
    {
        // credentials have to be unset when session id is set.
        // Own client rather than a pre-built config: the fan-out through
        // setRoleCredentials() is what this test is about, so it has to run through
        // the client's own setters.
        $cl = new CL();
        $cl->setRoleCredentials("myaccountid", "myrole", "mypassword")
            ->setSession("12345678");
        $this->assertEquals(
            "s_login=myaccountid%3Amyrole&s_sessionid=12345678&s_command=COMMAND%3DStatusAccount",
            $cl->getPOSTData(["COMMAND" => "StatusAccount"])
        );
    }

    /**
     * setSession("") drops the session and leaves no password behind it, so the next
     * request carries only the login.
     *
     * That login is this test's precondition and now arrives with the config. It
     * used to be whatever testSetSessionCredentials() had left on the shared client,
     * which made the two tests silently order-dependent — reordering or running
     * either alone changed the result. The role separator comes from the config
     * rather than being spelled `":"` here, so the expectation still cannot drift
     * from the SDK's own answer.
     */
    public function testSetSessionReset(): void
    {
        $cfg = new SC();
        $cl = CF::cnr(
            $cfg->setLogin("myaccountid" . $cfg->getRoleSeparator() . "myrole")->setSession("12345678")
        );
        $cl->setSession();
        $this->assertEquals(
            "s_login=myaccountid%3Amyrole&s_command=COMMAND%3DStatusAccount",
            $cl->getPOSTData(["COMMAND" => "StatusAccount"])
        );
    }

    public function testSaveReuseSession(): void
    {
        // The saving client's state is stated at construction, so this test no
        // longer inherits a login from testSetSessionCredentials() nor has to reset
        // the shared client on the way out.
        $cfg = new SC();
        $source = CF::cnr(
            $cfg->setLogin("myaccountid" . $cfg->getRoleSeparator() . "myrole")->setSession("12345678")
        );
        $session = [];
        $source->saveSession($session);

        $cl2 = new CL();
        $cl2->reuseSession($session);
        $this->assertEquals(
            "s_login=myaccountid%3Amyrole&s_sessionid=12345678&s_command=COMMAND%3DStatusAccount",
            $cl2->getPOSTData(["COMMAND" => "StatusAccount"])
        );
    }

    public function testSetCredentialsSet(): void
    {
        $cl = new CL();
        $cl->setCredentials("myaccountid", "mypassword");
        $this->assertEquals(
            "s_login=myaccountid&s_pw=mypassword&s_command=COMMAND%3DStatusAccount",
            $cl->getPOSTData(["COMMAND" => "StatusAccount"])
        );
    }

    public function testSetCredentialsReset(): void
    {
        // Reset from a config that carries credentials, so the empty result is the
        // reset's doing and not the starting state's.
        $cl = CF::cnr((new SC())->setLogin("myaccountid")->setPassword("mypassword"));
        $cl->setCredentials();
        $this->assertEquals(
            "s_command=COMMAND%3DStatusAccount",
            $cl->getPOSTData(["COMMAND" => "StatusAccount"])
        );
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
        $cl = new CL();
        $cl->setRoleCredentials("myaccountid", "myroleid", "mypassword");
        $this->assertEquals(
            "s_login=myaccountid%3Amyroleid&s_pw=mypassword&s_command=COMMAND%3DStatusAccount",
            $cl->getPOSTData(["COMMAND" => "StatusAccount"])
        );
    }

    public function testSetRoleCredentialsReset(): void
    {
        // Starts from a role login supplied by the config, so the empty result
        // proves the reset rather than the starting state.
        $cfg = new SC();
        $cl = CF::cnr(
            $cfg->setLogin("myaccountid" . $cfg->getRoleSeparator() . "myroleid")->setPassword("mypassword")
        );
        $cl->setRoleCredentials();
        $this->assertEquals(
            "s_command=COMMAND%3DStatusAccount",
            $cl->getPOSTData(["COMMAND" => "StatusAccount"])
        );
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

    /**
     * login() with the API reachable but returning no SESSIONID.
     *
     * The success branch runs, so setSession() is called — with the empty string
     * the null-coalesce supplies. getSession() then answers null rather than "",
     * which is the distinction that matters to a caller deciding whether it holds
     * a usable session.
     *
     * Driven through the transport seam rather than a cassette (RSRMID-2969):
     * these two branches were marked "not covered" for as long as the lifecycle
     * sat in a trait only reachable through SessionClient, and they were always
     * reachable offline — nobody had written them.
     */
    public function testLoginSucceedsWithNoSessionIdReturned(): void
    {
        $spy = new SpyTransport();
        $cl = (new CL())->setTransport($spy);
        $cl->setCredentials("myaccountid", "mypassword");

        $r = $cl->login();

        $this->assertTrue($r->isSuccess(), $r->getPlain());
        $this->assertNull($cl->getSession(), "no SESSIONID on the wire must leave no session, not an empty one");
        // Both halves of "persistent for its own request only", or the pair of
        // assertions cannot tell the reset apart from never setting it at all.
        $this->assertStringContainsString(
            "persistent=1",
            $spy->data,
            "login()'s own request is the one that must carry persistent=1"
        );
        $this->assertFalse(
            $cl->getSocketConfig()->getPersistent(),
            "login() makes the connection persistent for its own request only and must put it back"
        );
    }

    /**
     * login() when the transport fails outright — a cURL timeout, where $raw is
     * unusable and the error arrives as the declared parameter (RSRMID-2937).
     *
     * Note which predicate this lands on. CNR is three-state (2xx/4xx/5xx) and a
     * transport failure is code 421, so it is a **temporary** error:
     * `isTmpError()` is true while `isError()` is false. That is precisely why
     * {@see \CNIC\CNR\Client::login()} guards its session write on
     * `isSuccess()` and not on `!isError()` — the latter would treat a timeout as
     * good enough and overwrite a live session with the empty string the
     * null-coalesce supplies. Asserted here so the guard cannot be "simplified"
     * into the negation without a red test.
     *
     * So the failure branch leaves the session alone: a timed-out login has not
     * established anything. persistent is still put back, because that happens
     * after the branch rather than inside it.
     */
    public function testLoginFailsOnATransportError(): void
    {
        $cl = (new CL())->setTransport(new SpyTransport("", "Connection timed out"));
        $cl->setCredentials("myaccountid", "mypassword")->setSession("PRE-EXISTING");

        $r = $cl->login();

        $this->assertFalse($r->isSuccess(), $r->getPlain());
        $this->assertTrue($r->isTmpError(), "a transport failure is CNR code 421 — temporary, not a 5xx");
        $this->assertFalse($r->isError(), "421 is not a hard error; login() must not rely on isError() here");
        $this->assertStringContainsString("Connection timed out", $r->getDescription());
        $this->assertSame(
            "PRE-EXISTING",
            $cl->getSession(),
            "a failed login must not touch the session it did not replace"
        );
        $this->assertFalse($cl->getSocketConfig()->getPersistent());
    }

    /**
     * login() when request() throws rather than returning (RSRMID-2980).
     *
     * Driven through the **real** HttpTransport rather than a double, so the
     * throw being reachable is a fact about production code: `post()` rejects a
     * transport-owned cURL option before it touches the handle, and
     * `setExtraCurlOptions()` deliberately does not pre-empt that check (which
     * options a transport owns is its own business, and the transport is
     * injectable). That is the "reachable, not hypothetical" path the ticket
     * named.
     *
     * The URL is pointed at a closed local port even so. Nothing is sent today —
     * the rejection happens first — but that guarantee is borrowed from
     * `HttpTransport::PROTECTED_OPTIONS`, and a client built by `new CL()`
     * defaults to the **LIVE** endpoint. Narrowing that constant would turn this
     * test into a production request on its way to `fail()`, so the offline
     * guarantee is made local instead of borrowed.
     *
     * Before the fix, `setPersistent(false)` sat on the line after the call and
     * was skipped by the throw, leaving `persistent` stuck true — every later
     * request on this client would then silently ask the API for a session. The
     * assertion is on the state *after* the exception, so moving the reset back
     * out of the `finally` fails here.
     */
    public function testLoginPutsPersistentBackWhenTheRequestThrows(): void
    {
        $cl = (new CL())->setTransport(new HttpTransport());
        $cl->setCredentials("myaccountid", "mypassword");
        $cl->getSocketConfig()->setURL("http://127.0.0.1:1/");
        $cl->getSocketConfig()->setExtraCurlOptions([CURLOPT_POSTFIELDS => "hijacked"]);

        try {
            $cl->login();
            $this->fail("a transport-owned cURL option must make login() throw");
        } catch (UnsupportedFeatureException) {
            // expected — the assertion below is the actual subject
        }

        $this->assertFalse(
            $cl->getSocketConfig()->getPersistent(),
            "a login that threw must still put persistent back, or every later request asks for a session"
        );
    }

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
     * logout() when request() throws rather than returning (RSRMID-2980).
     *
     * The counterpart to {@see testLoginPutsPersistentBackWhenTheRequestThrows},
     * which carries the argument for why the throw is reachable. This one needs
     * a spy rather than the real transport: what is under test is that `close()`
     * reached the transport, and HttpTransport offers nothing to assert that on.
     * The spy raises the same exception the real one would.
     *
     * Before the fix, `close()` sat on the line after the call and was skipped
     * by the throw, leaking the connection handle for the client's remaining
     * lifetime. The session is asserted untouched as well: clearing it belongs
     * to the success branch, so a StopSession that never completed must not make
     * this client forget an id that may still be live server-side.
     */
    public function testLogoutClosesTheTransportWhenTheRequestThrows(): void
    {
        $spy = new SpyTransport(
            throw: UnsupportedFeatureException::transportOwnedCurlOptions(
                [CURLOPT_POSTFIELDS => "CURLOPT_POSTFIELDS"],
                HttpTransport::class
            )
        );
        $cl = (new CL())->setTransport($spy);
        $cl->setCredentials("myaccountid")->setSession("STILL-LIVE");

        try {
            $cl->logout();
            $this->fail("a throwing transport must make logout() throw");
        } catch (UnsupportedFeatureException) {
            // expected — the assertions below are the actual subject
        }

        $this->assertTrue(
            $spy->closed,
            "a logout that threw must still close the transport, or the connection handle leaks"
        );
        $this->assertSame(
            "STILL-LIVE",
            $cl->getSession(),
            "an unconfirmed StopSession must not clear a session id that may still be live server-side"
        );
    }

    /**
     * HTTP communication failure maps to code 421. Driven by a hand-authored
     * `conn-error` cassette (a captured `["raw" => "", "error" => "…"]` tuple),
     * so the exact failure and description are exercised offline — replacing
     * the former bogus-host integration test. Always replay: a dedicated
     * replay-only transport means a record run never overwrites the fixture
     * with a resolver-dependent message (RSRMID-2910).
     */
    public function testRequestCurlExecFail2(): void
    {
        $cl = new CL();
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
        $tpls = (new RTM())->addTemplate(
            "listLimitZero",
            "[RESPONSE]\r\nPROPERTY[COUNT][0]=0\r\nPROPERTY[FIRST][0]=0\r\nPROPERTY[LAST][0]=0\r\n"
            . "PROPERTY[LIMIT][0]=0\r\nPROPERTY[TOTAL][0]=1725494\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=0.286\r\nEOF\r\n"
        );
        $r = new R("listLimitZero", [
            "COMMAND" => "QueryDomainList",
            "FIRST" => "0",
            "LIMIT" => "0"
        ], templates: $tpls);
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
        // Paginator::hasNextPage() returns false here, and requestNextResponsePage()
        // must return null accordingly (termination logic is no longer duplicated).
        $tpls = (new RTM())->addTemplate(
            "listLastPage",
            "[RESPONSE]\r\nPROPERTY[COUNT][0]=2\r\nPROPERTY[FIRST][0]=8\r\nPROPERTY[LAST][0]=9\r\n"
            . "PROPERTY[LIMIT][0]=2\r\nPROPERTY[TOTAL][0]=10\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=0.286\r\nEOF\r\n"
        );
        $r = new R("listLastPage", [
            "COMMAND" => "QueryDomainList",
            "FIRST" => "8",
            "LIMIT" => "2"
        ], templates: $tpls);
        $pg = $r->getPagination();
        $this->assertTrue($r->isSuccess());
        $this->assertFalse($pg->hasNextPage());
        $this->assertNull($pg->getNextPageNumber());
        $this->assertNull(self::$cl->requestNextResponsePage($r));
    }

    public function testRequestNextResponsePagePastTheEnd(): void
    {
        // RSRMID-2943 regression guard, consumer-facing half. A caller can hand
        // this method a window it requested past the end of the list itself —
        // requestAllResponsePages() never builds one, since it stops while
        // LAST+1 < TOTAL still holds. Verbatim capture: CNR answers such a
        // window with COUNT=0 and LAST echoing FIRST, which is what makes the
        // offset comparison terminate (20000001 < 1825824 is false) with a
        // POSITIVE limit, where the LIMIT<=0 guard does not apply.
        $tpls = (new RTM())->addTemplate(
            "listPastTheEnd",
            "[RESPONSE]\r\nPROPERTY[COLUMN][0]=domain\r\nPROPERTY[COUNT][0]=0\r\nPROPERTY[FIRST][0]=20000000\r\n"
            . "PROPERTY[LAST][0]=20000000\r\nPROPERTY[LIMIT][0]=10\r\nPROPERTY[TOTAL][0]=1825824\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=15.892\r\nEOF\r\n"
        );
        $r = new R("listPastTheEnd", [
            "COMMAND" => "QueryDomainList",
            "FIRST" => "20000000",
            "LIMIT" => "10"
        ], templates: $tpls);
        $this->assertTrue($r->isSuccess());
        $this->assertFalse($r->getPagination()->hasNextPage());
        $this->assertNull(self::$cl->requestNextResponsePage($r));
    }

    public function testAdvanceIsRefusedWhenGetPaginationDisagreesWithTheOffsets(): void
    {
        // Unreachable through any real response, and deliberately so. Since
        // RSRMID-2943 the predicate and the offsets are derived from the same
        // four readers, so hasNextPage() === true already implies a positive
        // LIMIT and a non-null LAST — which is exactly why the re-check below
        // it reads like dead code and would survive a "simplification" review.
        //
        // It is not dead: it is what stops the derivation being re-split. The
        // subclass here is the response that does not exist yet — a brand, or a
        // consumer subclass, whose getPagination() answers from something other
        // than its own offsets and so can hand back a Paginator saying "yes" over
        // a window with no LIMIT to advance by. Without the guard the very next
        // lines compute FIRST from null and re-request the list from the top,
        // which is the forever-loop RSRMID-2943 set out to make impossible. Drop
        // the guard and this test fails; drop Paginator::hasNextPage()'s own null
        // checks and it still passes, because the two protect different halves
        // of the same invariant.
        $tpls = (new RTM())->addTemplate(
            "listWithoutLimit",
            "[RESPONSE]\r\nPROPERTY[COLUMN][0]=domain\r\nPROPERTY[COUNT][0]=2\r\nPROPERTY[FIRST][0]=0\r\n"
            . "PROPERTY[LAST][0]=1\r\nPROPERTY[TOTAL][0]=1825824\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=0.377\r\nEOF\r\n"
        );
        $r = new class ("listWithoutLimit", ["COMMAND" => "QueryDomainList"], templates: $tpls) extends R {
            #[\Override]
            public function getPagination(): Paginator
            {
                // A fabricated grid (LIMIT=10) that disagrees with this response's
                // own wire data (no LIMIT column at all), so hasNextPage() answers
                // true from an offset the response itself cannot advance by.
                return new Paginator(0, 1, 1825824, 10, 2);
            }
        };
        $this->assertTrue($r->getPagination()->hasNextPage());
        $this->assertNull($r->getRecordsLimitation());
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

    /**
     * A list response with a next page, for the pagination-continuation tests
     * below. Two records of a ten-record total, so hasNextPage() is true and
     * LIMIT/LAST are both usable.
     */
    private const string PAGED_LIST = "[RESPONSE]\r\nPROPERTY[DOMAIN][0]=a.com\r\nPROPERTY[DOMAIN][1]=b.com\r\n"
        . "PROPERTY[COUNT][0]=2\r\nPROPERTY[FIRST][0]=0\r\nPROPERTY[LAST][0]=1\r\n"
        . "PROPERTY[LIMIT][0]=2\r\nPROPERTY[TOTAL][0]=10\r\n"
        . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nEOF\r\n";

    /**
     * Page 2 must carry the command parameters that were actually sent, not the
     * response's masked copy of them (RSRMID-2975).
     *
     * `AUTH` and `PASSWORD` are masked *before* the command is stored on a
     * Response, so `getCommand()` can only ever answer `"***"` for them — asserted
     * here too, because the fix must not have loosened that. Building the
     * continuation from `getCommand()` therefore re-sent the literal mask as the
     * parameter's value, and it reached the wire: this test reads the encoded body
     * out of the transport spy rather than any client-side bag, so it fails if the
     * mask is on the wire even when every in-process accessor looks right.
     *
     * Note which half of the request is affected. The client's own credentials
     * travel as `s_login`/`s_pw` off the SocketConfig and were never in the
     * command, so authentication was fine on every page; what broke was a
     * *command parameter*, which the API may accept — answering page 2 from a
     * different result set than page 1 rather than erroring. That silence is why
     * this went unnoticed, and why the assertion is on the bytes.
     */
    public function testRequestNextResponsePageSendsTheUnmaskedCommandParameters(): void
    {
        $spy = new SpyTransport(self::PAGED_LIST);
        $cl = (new CL())->setTransport($spy);
        $cl->setCredentials("myaccountid", "mypassword");

        $r = $cl->request([
            "COMMAND" => "QueryDomainList",
            "AUTH"    => "s3cr3t-auth-code",
            "LIMIT"   => 2,
            "FIRST"   => 0
        ]);
        $this->assertTrue($r->isSuccess(), $r->getPlain());
        $this->assertStringContainsString("AUTH%3Ds3cr3t-auth-code", $spy->data, "page 1 sends the real value");
        $this->assertSame("***", $r->getCommand()["AUTH"], "the response must still answer masked");

        $nr = $cl->requestNextResponsePage($r);

        $this->assertNotNull($nr);
        $this->assertStringContainsString(
            "AUTH%3Ds3cr3t-auth-code",
            $spy->data,
            "page 2 must re-send the parameter that was sent, not the mask"
        );
        $this->assertStringNotContainsString("AUTH%3D%2A%2A%2A", $spy->data);
        $this->assertStringContainsString("FIRST%3D2", $spy->data, "and it must still advance the offset");
    }

    /**
     * A Response this client did not produce is not in its command map, and
     * falling back to the response's own command is correct exactly when nothing
     * in it was masked. That is the overwhelmingly common case — no list command
     * carries `AUTH` — so it must keep working untouched rather than being
     * collateral damage of RSRMID-2975.
     *
     * Pins the non-regression half of the fix: a guard that refused every foreign
     * Response would also pass the test above.
     */
    public function testRequestNextResponsePageStillContinuesAForeignResponseWithNothingMasked(): void
    {
        $tpls = (new RTM())->addTemplate("pagedList", self::PAGED_LIST);
        $r = new R("pagedList", ["COMMAND" => "QueryDomainList", "LIMIT" => "2"], templates: $tpls);
        $cl = (new CL())->setTransport(new SpyTransport(self::PAGED_LIST));
        $cl->setCredentials("myaccountid", "mypassword");

        $nr = $cl->requestNextResponsePage($r);

        $this->assertNotNull($nr);
        $this->assertTrue($nr->isSuccess(), $nr->getPlain());
    }

    /**
     * A foreign Response whose command *was* masked has no unmasked copy anywhere
     * — not on the response, not in this client's map — so there is nothing to
     * recover and the only honest options are to throw or to put the mask on the
     * wire. It throws (RSRMID-2975): silently sending `"***"` as a parameter value
     * is the defect itself, and a caller cannot tell it happened.
     *
     * Detection is by value, not by key, so it also holds for a Response subclass
     * that widened $sensitiveFields past CNR\SensitiveFields::KEYS.
     */
    public function testRequestNextResponsePageRefusesAForeignResponseWithAMaskedCommand(): void
    {
        $tpls = (new RTM())->addTemplate("pagedList", self::PAGED_LIST);
        $r = new R(
            "pagedList",
            ["COMMAND" => "QueryDomainList", "AUTH" => "s3cr3t-auth-code", "LIMIT" => "2"],
            templates: $tpls
        );
        $cl = (new CL())->setTransport(new SpyTransport(self::PAGED_LIST));

        $this->expectException(PaginationException::class);
        $this->expectExceptionMessage("Cannot continue pagination from a Response this client did not produce");
        $cl->requestNextResponsePage($r);
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
        // A fresh client, not the shared static one: since RSRMID-2921 the
        // high-performance route is a sticky flag on the config rather than a
        // one-off URL rewrite, so there is nothing to restore afterwards — and
        // leaving it on would silently redirect every later test on self::$cl to
        // loopback. That stickiness is the point (it no longer costs the caller
        // isOTE()); the cost is that this test may not share a client.
        $cl = CF::cnr();
        $oldurl = $cl->getURL();
        $hostname = parse_url($oldurl, PHP_URL_HOST);
        if (is_string($hostname) && $hostname !== "") {
            $newurl = str_replace($hostname, "127.0.0.1", $oldurl);
            $newurl = str_replace("https://", "http://", $newurl);
            $cl->useHighPerformanceConnectionSetup();
            $this->assertEquals($cl->getURL(), $newurl);
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
