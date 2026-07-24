<?php

declare(strict_types=1);

namespace CNICTEST\Support;

use CNIC\TransportInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for the record/replay cassette transport (RSRMID-2910). These run
 * fully offline: record mode is driven by an in-memory fake inner transport, so
 * no network and no credentials are involved.
 */
final class CassetteTransportTest extends TestCase
{
    private string $dir = "";

    #[\Override]
    protected function setUp(): void
    {
        $dir = sys_get_temp_dir() . "/cnic-cassette-test-" . uniqid("", true);
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        $this->dir = $dir;
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->dir !== "" && is_dir($this->dir)) {
            $files = glob($this->dir . "/*");
            foreach ($files === false ? [] : $files as $f) {
                unlink($f);
            }
            rmdir($this->dir);
        }
    }

    /**
     * A fake inner transport returning a scripted queue of exchanges, so record
     * mode can be exercised without a live API.
     * @param array<int, array{0: string, 1: string|null}> $queue
     */
    private function fakeInner(array $queue): TransportInterface
    {
        return new class ($queue) implements TransportInterface {
            /** @param array<int, array{0: string, 1: string|null}> $queue */
            public function __construct(private array $queue)
            {
            }
            /**
             * @param array<int, mixed> $options
             * @return array{0: string, 1: string|null}
             */
            #[\Override]
            public function post(string $url, string $data, int $timeout, string $userAgent, array $options = []): array
            {
                $next = array_shift($this->queue);
                return $next ?? ["", null];
            }
            #[\Override]
            public function close(): void
            {
            }
        };
    }

    public function testRecordWritesOrderedExchangesThenReplayReturnsThem(): void
    {
        $inner = $this->fakeInner([
            ["RAW-ONE", null],
            ["RAW-TWO", null],
        ]);
        $rec = new CassetteTransport($inner, $this->dir, true);
        $rec->useCassette("paged");
        $this->assertSame(["RAW-ONE", null], $rec->post("u", "d", 30, "UA"));
        $this->assertSame(["RAW-TWO", null], $rec->post("u", "d", 30, "UA"));

        // the cassette file exists and holds both exchanges in order
        $file = $this->dir . "/paged.json";
        $this->assertFileExists($file);
        $decoded = json_decode((string) file_get_contents($file), true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
        $first = $decoded[0];
        $second = $decoded[1];
        $this->assertIsArray($first);
        $this->assertIsArray($second);
        $this->assertSame("RAW-ONE", $first["raw"]);
        $this->assertSame("RAW-TWO", $second["raw"]);

        // replay (no inner transport, no network) returns the same tuples in order
        $rep = new CassetteTransport(null, $this->dir, false);
        $rep->useCassette("paged");
        $this->assertSame(["RAW-ONE", null], $rep->post("u", "d", 30, "UA"));
        $this->assertSame(["RAW-TWO", null], $rep->post("u", "d", 30, "UA"));
    }

    public function testReplayServesHandAuthoredConnErrorFixture(): void
    {
        // conn-error cassettes are hand-authored fixtures (no recording needed).
        file_put_contents(
            $this->dir . "/conn-error.json",
            (string) json_encode([
                ["raw" => "httperror|Could not resolve host: nope.invalid", "error" => "Could not resolve host: nope.invalid"],
            ])
        );
        $rep = new CassetteTransport(null, $this->dir, false);
        $rep->useCassette("conn-error");
        [$raw, $error] = $rep->post("u", "d", 30, "UA");
        $this->assertStringStartsWith("httperror|", $raw);
        $this->assertSame("Could not resolve host: nope.invalid", $error);
    }

    public function testReplayMissingCassetteThrows(): void
    {
        $rep = new CassetteTransport(null, $this->dir, false);
        $this->expectException(RuntimeException::class);
        $rep->useCassette("does-not-exist");
        $rep->post("u", "d", 30, "UA");
    }

    public function testReplayExhaustedCassetteThrows(): void
    {
        file_put_contents(
            $this->dir . "/single.json",
            (string) json_encode([["raw" => "ONLY", "error" => null]])
        );
        $rep = new CassetteTransport(null, $this->dir, false);
        $rep->useCassette("single");
        $rep->post("u", "d", 30, "UA");
        $this->expectException(RuntimeException::class);
        $rep->post("u", "d", 30, "UA"); // second call exceeds recorded exchanges
    }

    public function testPostWithoutCassetteSelectedThrows(): void
    {
        $rep = new CassetteTransport(null, $this->dir, false);
        $this->expectException(RuntimeException::class);
        $rep->post("u", "d", 30, "UA");
    }
}
