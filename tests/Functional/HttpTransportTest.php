<?php

declare(strict_types=1);

namespace CNICTEST\Functional;

use CNIC\HttpTransport;
use PHPUnit\Framework\TestCase;

/**
 * Functional test for HttpTransport against a local PHP built-in HTTP server.
 *
 * Unlike the template-driven unit tests, this exercises the real cURL transport
 * over a loopback connection (no external API) so we can assert behaviour that
 * is only observable on the wire — notably that per-call cURL options do not
 * leak across the reused handle. Skips cleanly if the loopback server cannot be
 * started (e.g. a CI runner that forbids binding a port).
 */
final class HttpTransportTest extends TestCase
{
    /** @var resource|null */
    private static $proc = null;
    /** @var array<array-key,resource> */
    private static array $pipes = [];
    private static string $url = "";

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        // pick a free loopback port
        $probe = @stream_socket_server("tcp://127.0.0.1:0", $errno, $errstr);
        if ($probe === false) {
            return; // $url stays "" -> tests skip
        }
        $name = stream_socket_get_name($probe, false);
        fclose($probe);
        if ($name === false) {
            return;
        }
        $parts = explode(":", $name);
        $port = (int) end($parts);

        $proc = proc_open(
            [PHP_BINARY, "-S", "127.0.0.1:" . $port, __DIR__ . "/fixtures/echo.php"],
            [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]],
            $pipes
        );
        if (!is_resource($proc)) {
            return;
        }

        // poll until the server accepts connections (~2s budget)
        for ($i = 0; $i < 50; $i++) {
            $conn = @fsockopen("127.0.0.1", $port, $e, $s, 0.1);
            if ($conn !== false) {
                fclose($conn);
                self::$proc = $proc;
                self::$pipes = $pipes;
                self::$url = "http://127.0.0.1:" . $port . "/";
                return;
            }
            usleep(40000);
        }

        // never came up -> clean up so tests skip rather than hang
        proc_terminate($proc);
        proc_close($proc);
    }

    #[\Override]
    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$proc)) {
            proc_terminate(self::$proc);
            foreach (self::$pipes as $pipe) {
                fclose($pipe);
            }
            proc_close(self::$proc);
        }
        self::$proc = null;
        self::$pipes = [];
        self::$url = "";
    }

    #[\Override]
    protected function setUp(): void
    {
        if (self::$url === "") {
            $this->markTestSkipped("could not start local loopback HTTP server");
        }
    }

    public function testPerCallOptionsDoNotLeakAcrossReusedHandle(): void
    {
        $t = new HttpTransport();

        // call 1 sets a Referer via the per-call options
        [$raw1] = $t->post(self::$url, "x=1", 30, "UA", [CURLOPT_REFERER => "https://referer.test/one"]);
        $d1 = (array) json_decode($raw1, true);
        $this->assertSame("https://referer.test/one", $d1["referer"] ?? null);

        // call 2 passes no Referer -> it must NOT inherit call 1's value from the reused handle
        [$raw2] = $t->post(self::$url, "x=2", 30, "UA", []);
        $d2 = (array) json_decode($raw2, true);
        $this->assertSame("", $d2["referer"] ?? null, "referer leaked from the previous call on the reused handle");

        $t->close();
    }

    public function testHandleSurvivesSequentialPosts(): void
    {
        $t = new HttpTransport();
        [$raw1] = $t->post(self::$url, "first=1", 30, "UA");
        [$raw2] = $t->post(self::$url, "second=2", 30, "UA");
        $d1 = (array) json_decode($raw1, true);
        $d2 = (array) json_decode($raw2, true);
        $this->assertSame("first=1", $d1["body"] ?? null);
        $this->assertSame("second=2", $d2["body"] ?? null);
        $t->close();
    }

    public function testCloseThenPostRecreatesHandle(): void
    {
        $t = new HttpTransport();
        $t->post(self::$url, "a=1", 30, "UA");
        $t->close();
        [$raw2] = $t->post(self::$url, "b=2", 30, "UA");
        $d2 = (array) json_decode($raw2, true);
        $this->assertSame("b=2", $d2["body"] ?? null);
        $t->close();
    }

    public function testPostReturnsHttpErrorTupleOnConnectionFailure(): void
    {
        // Allocate then immediately release a loopback port so that nothing is
        // listening on it; cURL then fails fast with "connection refused",
        // exercising the curl_exec()-returns-false branch of post().
        $probe = @stream_socket_server("tcp://127.0.0.1:0", $errno, $errstr);
        if ($probe === false) {
            $this->markTestSkipped("cannot allocate a probe port");
        }
        $name = stream_socket_get_name($probe, false);
        fclose($probe);
        if ($name === false) {
            $this->markTestSkipped("cannot determine probe port");
        }
        $parts = explode(":", $name);
        $closedPort = (int) end($parts);

        $t = new HttpTransport();
        [$raw, $error] = $t->post("http://127.0.0.1:" . $closedPort . "/", "x=1", 2, "UA");
        $t->close();

        $this->assertStringStartsWith("httperror|", $raw);
        $this->assertIsString($error);
        $this->assertNotSame("", $error, "a cURL error message is expected on connection failure");
        // the raw payload carries the same error after the "httperror|" prefix
        $this->assertSame("httperror|" . $error, $raw);
    }
}
