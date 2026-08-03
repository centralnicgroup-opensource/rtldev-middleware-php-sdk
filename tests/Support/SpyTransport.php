<?php

declare(strict_types=1);

/**
 * CNICTEST\Support
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST\Support;

use CNIC\TransportInterface;

/**
 * Recording TransportInterface double (RSRMID-2919).
 *
 * Captures what the client hands the transport for a request and returns a
 * canned wire response, so a test can assert the *effective* arguments — the
 * timeout and the cURL option bag — without a network. This is the seam that
 * was missing when the setExtraCurlOptions() discard went unnoticed: the
 * existing tests only inspected the client's own $curlOptions bag, which looked
 * correct all along.
 *
 * For assertions about what the real cURL layer does with those options, see
 * {@see \CNICTEST\Functional\HttpTransportTest} — the merge happens inside
 * HttpTransport, which this double replaces.
 *
 * Deliberately records only what is asserted on today (Psalm runs with
 * findUnusedCode, so a speculative field is an error, not a convenience). Add a
 * field when a test needs it.
 *
 * It owns no cURL options, so — unlike HttpTransport — it accepts anything it
 * is handed, including the keys in {@see \CNIC\HttpTransport::PROTECTED_OPTIONS}.
 * That is correct for a recording double (its job is to report the arguments,
 * not to re-implement production rules), but it means a test written against
 * this class cannot show that an option is *permitted*. Assert rejection
 * against the real transport in CNICTEST\HttpTransportCurlOptionsTest.
 */
final class SpyTransport implements TransportInterface
{
    /** Canned CNR success response, enough to drive the full parse path. */
    private const DEFAULT_RAW = "[RESPONSE]\r\nCODE=200\r\nDESCRIPTION=Command completed successfully\r\nEOF\r\n";

    /** Timeout handed over by the client; -1 until the first call. */
    public int $timeout = -1;

    /** @var array<int, mixed> cURL option bag handed over by the client */
    public array $options = [];

    /** Connection URL handed over by the client; empty until the first call. */
    public string $url = "";

    /** User agent handed over by the client; empty until the first call. */
    public string $userAgent = "";

    /**
     * @param string $raw canned wire response to return (defaults to a CNR success)
     */
    public function __construct(private readonly string $raw = self::DEFAULT_RAW)
    {
    }

    /**
     * @param array<int, mixed> $options
     * @return array{0: string, 1: string|null}
     */
    #[\Override]
    public function post(string $url, string $data, int $timeoutSeconds, string $userAgent, array $options = []): array
    {
        $this->url = $url;
        $this->timeout = $timeoutSeconds;
        $this->userAgent = $userAgent;
        $this->options = $options;
        return [$this->raw, null];
    }

    #[\Override]
    public function close(): void
    {
    }
}
