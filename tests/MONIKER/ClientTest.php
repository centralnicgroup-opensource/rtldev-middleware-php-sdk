<?php

declare(strict_types=1);

namespace CNICTEST\MONIKER;

use CNIC\ClientFactory as CF;
use CNIC\IBS\Client as IBSClient;
use CNIC\IBS\Response as R;
use CNIC\MONIKER\SessionClient;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public static SessionClient $cl;
    public static string $user;
    public static string $pw;

    public static function setUpBeforeClass(): void
    {
        $cl = CF::getClient(["registrar" => "MONIKER"]);
        \assert($cl instanceof SessionClient);
        self::$cl = $cl;

        self::$user = getenv("RTLDEV_MW_CI_USER_MONIKER") ?: "";
        self::$pw = getenv("RTLDEV_MW_CI_USERPASSWORD_MONIKER") ?: "";
        if (self::$user === "" || self::$pw === "") {
            self::markTestSkipped("Moniker credentials not set (RTLDEV_MW_CI_USER_MONIKER / RTLDEV_MW_CI_USERPASSWORD_MONIKER).");
        }
    }

    protected function tearDown(): void
    {
        sleep(2);
        parent::tearDown();
    }

    public function testGetPostDataSecured(): void
    {
        self::$cl->setCredentials(self::$user, self::$pw);
        $enc = self::$cl->getPOSTData([
            "tld" => "nl",
            "ResponseFormat" => "JSON"
        ], true);
        $expected = http_build_query([
            "tld" => "nl",
            "ResponseFormat" => "JSON",
            "apikey" => self::$user,
            "password" => "***"
        ]);
        self::$cl->setCredentials();
        $this->assertEquals($expected, $enc);
    }

    public function testGetPostDataObj(): void
    {
        $enc = self::$cl->getPOSTData(["tld" => "nl"]);
        $this->assertEquals("tld=nl", $enc);
    }

    public function testGetUrl(): void
    {
        $url = self::$cl->getURL();
        $this->assertEquals($url, self::$cl->getSettings()["env"]["live"]["url"]);
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
        if (!empty($hostname)) {
            $newurl = str_replace($hostname, "127.0.0.1", $oldurl);
            $url = self::$cl->setURL($newurl)->getURL();
            $this->assertEquals($newurl, $url);
            self::$cl->setURL($oldurl);
        }
    }

    public function testSetCredentialsSet(): void
    {
        self::$cl->setCredentials("myapikey", "mypassword");
        $tmp = self::$cl->getPOSTData(["tld" => "nl"]);
        $this->assertEquals("tld=nl&apikey=myapikey&password=mypassword", $tmp);
    }

    public function testSetCredentialsReset(): void
    {
        self::$cl->setCredentials();
        $tmp = self::$cl->getPOSTData(["tld" => "nl"]);
        $this->assertEquals("tld=nl", $tmp);
    }

    public function testGetSessionThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Feature `API Session` Not supported.");
        self::$cl->getSession();
    }

    public function testSetSessionThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Feature `API Session` not supported.");
        self::$cl->setSession("test");
    }

    public function testSetRoleCredentialsThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Feature `User Role` not supported.");
        self::$cl->setRoleCredentials("uid", "role", "pw");
    }

    public function testSetUserViewThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Feature `User View / Subresellersystem` not supported.");
        self::$cl->setUserView("subuser");
    }

    public function testUseHighPerformanceConnectionSetupThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Feature `High Performance Connection Setup` not supported.");
        self::$cl->useHighPerformanceConnectionSetup();
    }

    public function testSetRemoteIpAddressThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Feature `IP Filter` not supported");
        self::$cl->setRemoteIPAddress("10.10.10.10");
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
        self::$cl->setReferer("https://www.moniker.com/");
        $this->assertEquals("https://www.moniker.com/", self::$cl->getReferer());
        self::$cl->setReferer();
        $this->assertNull(self::$cl->getReferer());
    }

    public function testRequestCurlExecFail(): void
    {
        self::$cl->setCredentials(self::$user, self::$pw)
            ->useOTESystem()
            ->setURL("http://gregeragregaegaegag.com/geragaerg/");
        $r = self::$cl->request(["tld" => "nl"], "Domain/Tldinfo");
        $this->assertInstanceOf(R::class, $r);
        $this->assertFalse($r->isSuccess());
        $this->assertStringContainsString("Command failed due to HTTP communication error", $r->getDescription());
    }

    public function testRequestSuccessDbg(): void
    {
        self::$cl->enableDebugMode()
            ->setCredentials(self::$user, self::$pw)
            ->useOTESystem();
        $r = self::$cl->request(["tld" => "nl"], "Domain/Tldinfo");
        $this->assertInstanceOf(R::class, $r);
        $this->assertTrue($r->isSuccess(), $r->getDescription());
    }

    public function testRequestSuccessNoDbg(): void
    {
        self::$cl->disableDebugMode();
        $r = self::$cl->request(["tld" => "nl"], "Domain/Tldinfo");
        $this->assertInstanceOf(R::class, $r);
        $this->assertTrue($r->isSuccess(), $r->getDescription());
    }

    public function testDefaultUrlIsMonikerDomain(): void
    {
        // MONIKER\SessionClient must load its own config.json, not the IBS one
        $this->assertStringContainsString("moniker.com", self::$cl->getURL());
    }
}
