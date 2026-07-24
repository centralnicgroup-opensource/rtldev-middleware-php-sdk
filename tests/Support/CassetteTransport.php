<?php

declare(strict_types=1);

namespace CNICTEST\Support;

use CNIC\TransportInterface;
use RuntimeException;

/**
 * Record/replay ("cassette") HTTP transport for offline request() tests
 * (RSRMID-2910).
 *
 * Sitting behind {@see \CNIC\TransportInterface}, it is injected onto a client
 * via {@see \CNIC\AbstractClient::setTransport()} and intercepts the single
 * choke point every brand's request()/login()/logout()/pagination call passes
 * through — {@see \CNIC\AbstractClient::executeCurl()}. Two modes:
 *
 *  - **record** (`RTLDEV_MW_RECORD` set): each {@see post()} is delegated to a
 *    real inner {@see \CNIC\HttpTransport}, the true wire bytes are captured and
 *    written to `{$dir}/{$cassette}.json`, and the live tuple is returned.
 *  - **replay** (default, CI): no inner transport, no network — {@see post()}
 *    returns the recorded exchanges back in the order they were captured.
 *
 * Cassettes are recorded at the transport layer (pre-`translate()`), so replay
 * feeds the raw bytes back through `newResponse()`/`translate()` exactly like a
 * live call. A cassette file is a bare JSON array of `{"raw", "error"}` exchange
 * objects, because one logical test operation (e.g. list pagination, or
 * login()+logout()) can drive several `post()` calls; successive calls under one
 * {@see useCassette()} map to successive array entries. `useCassette()` lives
 * here only, never on {@see \CNIC\TransportInterface}, so `src/` stays clean.
 *
 * @psalm-api
 */
final class CassetteTransport implements TransportInterface
{
    private string $cassette = "";

    /**
     * Recorded exchanges for the current cassette (replay mode), each a
     * `[raw, error]` tuple.
     * @var list<array{0: string, 1: string|null}>
     */
    private array $exchanges = [];

    /** Cursor into {@see $exchanges} for the current cassette. */
    private int $cursor = 0;

    /** Whether a cassette has been selected for the current run. */
    private bool $selected = false;

    /**
     * @param TransportInterface|null $inner real transport used in record mode; null in replay
     * @param string $dir directory holding the cassette JSON files
     * @param bool $record true to record live exchanges, false to replay
     */
    public function __construct(
        private ?TransportInterface $inner,
        private string $dir,
        private bool $record
    ) {
    }

    /**
     * Select the cassette used by the following {@see post()} calls, resetting
     * the exchange cursor. In replay mode the cassette file is loaded eagerly so
     * a missing recording fails fast with a clear, actionable message.
     */
    public function useCassette(string $name): void
    {
        $this->cassette = $name;
        $this->cursor = 0;
        $this->exchanges = [];
        $this->selected = true;

        if ($this->record) {
            return;
        }

        $file = $this->path();
        if (!is_file($file)) {
            throw new RuntimeException(
                "Cassette \"{$name}\" not found at {$file}. Record it with RTLDEV_MW_RECORD=1 (composer test:record)."
            );
        }
        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Cassette \"{$name}\" is malformed: {$file}");
        }
        $exchanges = [];
        /** @var mixed $ex */
        foreach ($decoded as $ex) {
            if (!is_array($ex) || !array_key_exists("raw", $ex)) {
                throw new RuntimeException("Cassette \"{$name}\" has a malformed exchange: {$file}");
            }
            $exchanges[] = [
                (string) $ex["raw"],
                array_key_exists("error", $ex) && $ex["error"] !== null ? (string) $ex["error"] : null,
            ];
        }
        $this->exchanges = $exchanges;
    }

    /**
     * @param array<int, mixed> $options
     * @return array{0: string, 1: string|null}
     */
    #[\Override]
    public function post(string $url, string $data, int $timeout, string $userAgent, array $options = []): array
    {
        if (!$this->selected) {
            throw new RuntimeException(
                "No cassette selected. Call useCassette() before driving a request through CassetteTransport."
            );
        }

        if ($this->record) {
            if (!$this->inner instanceof TransportInterface) {
                throw new RuntimeException("Record mode requires an inner transport.");
            }
            [$raw, $error] = $this->inner->post($url, $data, $timeout, $userAgent, $options);
            $this->exchanges[] = [$raw, $error];
            $this->flush();
            return [$raw, $error];
        }

        if (!array_key_exists($this->cursor, $this->exchanges)) {
            throw new RuntimeException(
                "Cassette \"{$this->cassette}\" exhausted after {$this->cursor} exchange(s); "
                . "the test made more requests than were recorded. Re-record with RTLDEV_MW_RECORD=1."
            );
        }
        return $this->exchanges[$this->cursor++];
    }

    #[\Override]
    public function close(): void
    {
        $this->inner?->close();
    }

    /**
     * Persist the current cassette's exchanges to disk (record mode).
     */
    private function flush(): void
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0o777, true);
        }
        $payload = array_map(
            static fn(array $ex): array => ["raw" => $ex[0], "error" => $ex[1]],
            $this->exchanges
        );
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException("Failed to encode cassette \"{$this->cassette}\".");
        }
        file_put_contents($this->path(), $json . "\n");
    }

    private function path(): string
    {
        return rtrim($this->dir, "/") . "/" . $this->cassette . ".json";
    }
}
