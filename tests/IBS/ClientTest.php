<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\ClientFactory as CF;
use CNIC\IBS\Client as IBSClient;
use CNIC\IBS\Response as R;
use CNIC\IBS\SessionClient;
use CNIC\RoleCredentialsInterface;
use CNICTEST\Support\Cassettes;
use CNICTEST\Support\CassetteTransport;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public static SessionClient $cl;
    public static string $user;
    public static string $pw;
    /**
     * @var CassetteTransport record/replay transport driving the request() path
     */
    public static CassetteTransport $tape;
    /**
     * @var string absolute path to this brand's cassette directory
     */
    public static string $cassetteDir;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        self::$cl = CF::ibs();
        self::$cassetteDir = __DIR__ . "/cassettes";
        self::$tape = Cassettes::attach(self::$cl, self::$cassetteDir);

        if (Cassettes::isRecording()) {
            // Record mode makes real OTE calls, so real credentials are required.
            self::$user = (string) getenv("RTLDEV_MW_CI_USER_IBS");
            self::$pw = (string) getenv("RTLDEV_MW_CI_USERPASSWORD_IBS");
            if (self::$user === "" || self::$pw === "") {
                self::markTestSkipped("Recording needs RTLDEV_MW_CI_USER_IBS / RTLDEV_MW_CI_USERPASSWORD_IBS.");
            }
            return;
        }

        // Replay mode (default): deterministic dummy credentials — the transport
        // is served from committed cassettes, so credentials are never sent.
        self::$user = "test.user";
        self::$pw = "test.pw";
    }

    #[\Override]
    protected function tearDown(): void
    {
        Cassettes::throttle(); // record mode only; replay is offline
        parent::tearDown();
    }

    public function testGetPostDataSecured(): void
    {
        self::$cl->setCredentials(self::$user, self::$pw);
        $enc = self::$cl->getPOSTData([
            "domain" => "test.com",
            "ResponseFormat" => "JSON"
        ], true);
        $expected = http_build_query([
            "domain" => "test.com",
            "ResponseFormat" => "JSON",
            "apikey" => self::$user,
            "password" => "***"
        ]);
        self::$cl->setCredentials();
        $this->assertEquals($expected, $enc);
    }

    public function testGetPostDataObj(): void
    {
        $enc = self::$cl->getPOSTData(["domain" => "test.com"]);
        $this->assertEquals("domain=test.com", $enc);
    }

    public function testGetUrl(): void
    {
        $url = self::$cl->getURL();
        $this->assertEquals($url, self::$cl->getLiveUrl());
    }

    public function testGetUserAgent(): void
    {
        $ua = "PHP-SDK (" . PHP_OS . "; " . php_uname("m") . "; rv:" . self::$cl->getVersion() . ") php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
        $this->assertEquals($ua, self::$cl->getUserAgent());
    }

    public function testSetUserAgent(): void
    {
        $pid = "WHMCS";
        $rv = "7.7.0";
        $ua = $pid . " (" . PHP_OS . "; " . php_uname("m") . "; rv:" . $rv . ") php-sdk/" . self::$cl->getVersion() . " php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
        $cls = self::$cl->setUserAgent($pid, $rv);
        $this->assertInstanceOf(IBSClient::class, $cls);
        $this->assertEquals($ua, self::$cl->getUserAgent());
    }

    public function testSetUserAgentModules(): void
    {
        $pid = "WHMCS";
        $rv = "7.7.0";
        $mods = ["reg/2.6.2", "ssl/7.2.2", "dc/8.2.2"];
        $ua = $pid . " (" . PHP_OS . "; " . php_uname("m") . "; rv:" . $rv . ") " . implode(" ", $mods) . " php-sdk/" . self::$cl->getVersion() . " php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
        $cls = self::$cl->setUserAgent($pid, $rv, $mods);
        $this->assertInstanceOf(IBSClient::class, $cls);
        $this->assertEquals($ua, self::$cl->getUserAgent());
    }

    public function testSetUrl(): void
    {
        $oldurl = self::$cl->getURL();
        $hostname = parse_url($oldurl, PHP_URL_HOST);
        if (is_string($hostname) && $hostname !== "") {
            $newurl = str_replace($hostname, "127.0.0.1", $oldurl);
            $url = self::$cl->setURL($newurl)->getURL();
            $this->assertEquals($newurl, $url);
            self::$cl->setURL($oldurl);
        }
    }

    public function testSetCredentialsSet(): void
    {
        self::$cl->setCredentials("myapikey", "mypassword");
        $tmp = self::$cl->getPOSTData(["domain" => "test.com"]);
        $this->assertEquals("domain=test.com&apikey=myapikey&password=mypassword", $tmp);
    }

    public function testSetCredentialsReset(): void
    {
        self::$cl->setCredentials();
        $tmp = self::$cl->getPOSTData(["domain" => "test.com"]);
        $this->assertEquals("domain=test.com", $tmp);
    }

    public function testSessionAccessorsAreHarmlessNoOps(): void
    {
        // IBS/Moniker have no API session concept. Rather than the former
        // present-and-throwing overrides, the client inherits the shared base
        // accessors, which are harmless: getSession() reports null and
        // setSession() is a fluent no-op (the SocketConfig has nothing to store).
        $this->assertNull(self::$cl->getSession());
        $this->assertInstanceOf(IBSClient::class, self::$cl->setSession("test"));
        $this->assertNull(self::$cl->getSession());
    }

    public function testDoesNotSupportRoleCredentials(): void
    {
        // Role credentials are CNR-only (RoleCredentialsInterface): the role
        // separator is empty here, so inheriting the behaviour would forge a
        // garbage login. IBS/Moniker therefore do NOT implement the seam — the
        // method is absent, not present-and-throwing.
        $this->assertFalse(
            (new \ReflectionClass(self::$cl))->implementsInterface(RoleCredentialsInterface::class)
        );
    }

    public function testUseHighPerformanceConnectionSetupRewritesHostAndScheme(): void
    {
        // High-performance setup is brand-agnostic (a pure scheme+host rewrite
        // to loopback), so IBS/Moniker may opt in, supplying their own local
        // proxy. Only the scheme and host change; port, path and query survive.
        $cl = CF::ibs();
        $cl->setURL("https://api.example.com:8443/api.example.com/x?foo=bar");
        $cl->useHighPerformanceConnectionSetup();
        $this->assertSame(
            "http://127.0.0.1:8443/api.example.com/x?foo=bar",
            $cl->getURL()
        );
    }

    public function testSetProxy(): void
    {
        $this->assertNull(self::$cl->getProxy());
        self::$cl->setProxy("127.0.0.1");
        $this->assertEquals("127.0.0.1", self::$cl->getProxy());
        self::$cl->setProxy();
        $this->assertNull(self::$cl->getProxy());
    }

    public function testSetReferer(): void
    {
        $this->assertNull(self::$cl->getReferer());
        self::$cl->setReferer("https://www.internet.bs/");
        $this->assertEquals("https://www.internet.bs/", self::$cl->getReferer());
        self::$cl->setReferer();
        $this->assertNull(self::$cl->getReferer());
    }

    public function testRequestCurlExecFail(): void
    {
        // HTTP communication failure. Driven by a hand-authored `conn-error`
        // cassette via a dedicated replay-only transport, so a record run never
        // overwrites the fixture with a resolver-dependent message (RSRMID-2910).
        $cl = CF::ibs();
        $tape = new CassetteTransport(null, self::$cassetteDir, false);
        $cl->setTransport($tape);
        $tape->useCassette("conn-error");
        $cl->useOTESystem();
        $r = $cl->request(["domain" => "tronexats.com"], "Domain/Info");
        $this->assertInstanceOf(R::class, $r);
        $this->assertFalse($r->isSuccess());
        $this->assertStringContainsString("Command failed due to HTTP communication error", $r->getDescription());
    }

    public function testRequestSuccessDbg(): void
    {
        self::$tape->useCassette("request-success-dbg");
        self::$cl->enableDebugMode()
            ->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
        $r = self::$cl->request(["domain" => "tronexats.com"], "Domain/Check");
        $this->assertInstanceOf(R::class, $r);
        $this->assertTrue($r->isSuccess(), $r->getDescription());
    }

    public function testRequestSuccessNoDbg(): void
    {
        self::$tape->useCassette("request-success-nodbg");
        self::$cl->disableDebugMode();
        $r = self::$cl->request(["domain" => "tronexats.com"], "Domain/Check");
        $this->assertInstanceOf(R::class, $r);
        $this->assertTrue($r->isSuccess(), $r->getDescription());
    }

    public function testAutoIDNConvertReturnsCommandUnchanged(): void
    {
        // IBS handles IDN conversion server-side — the SDK must not alter command params
        $cmd = ["domain" => "dömäin.com", "ResponseFormat" => "JSON"];
        $method = (new \ReflectionClass(self::$cl))->getMethod("autoIDNConvert");
        $method->setAccessible(true);
        /** @var array<string> $result */
        $result = $method->invoke(self::$cl, $cmd);
        $this->assertSame($cmd, $result);
    }
}
