<?php

declare(strict_types=1);

/**
 * CNICTEST\Support
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST\Support;

use CNIC\Exception\CnicException;
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
    private const string DEFAULT_RAW = "[RESPONSE]\r\nCODE=200\r\nDESCRIPTION=Command completed successfully\r\nEOF\r\n";

    /** Timeout handed over by the client; -1 until the first call. */
    public int $timeout = -1;

    /** @var array<int, mixed> cURL option bag handed over by the client */
    public array $options = [];

    /** Connection URL handed over by the client; empty until the first call. */
    public string $url = "";

    /** User agent handed over by the client; empty until the first call. */
    public string $userAgent = "";

    /**
     * The encoded payload handed over by the client; empty until the first
     * call. Recorded so "the bytes on the wire are what getPOSTData() produced"
     * is assertable end to end (RSRMID-2940) rather than only in isolation.
     */
    public string $data = "";

    /** Whether the client delegated {@see close()} down to this transport. */
    public bool $closed = false;

    /**
     * @param string $raw canned wire response to return (defaults to a CNR success)
     * @param string|null $error canned transport error; non-null means $raw is
     *        unusable, which is how a cURL failure (timeout, DNS, refused
     *        connection) reaches the client. Added in RSRMID-2969 so the
     *        transport-error branches of the CNR session lifecycle are reachable
     *        offline instead of only against a live API.
     * @param CnicException|null $throw canned throw from {@see post()}, raised
     *        instead of returning. A transport error and a transport *throw* are
     *        different events that reach the client differently: the former
     *        arrives as element [1] and becomes a 421 response, the latter
     *        propagates out of `request()` and past every line after it. Added in
     *        RSRMID-2980 to observe what a client does with the state and the
     *        resources it owns when the call it wraps does not return — see
     *        {@see \CNIC\CNR\Client::login()} for the production path that throws.
     *
     *        Typed to `CnicException`, not `\Throwable`: {@see \CNIC\TransportInterface::post()}
     *        declares only {@see \CNIC\Exception\UnsupportedFeatureException}, and
     *        this project throws nothing outside the `CNIC\Exception` hierarchy.
     *        A double that can raise a `\TypeError` would widen the seam's
     *        contract rather than reproduce it.
     */
    public function __construct(
        private readonly string $raw = self::DEFAULT_RAW,
        private readonly ?string $error = null,
        private readonly ?CnicException $throw = null
    ) {
    }

    /**
     * The arguments are recorded before a canned throw is raised, so a test can
     * still assert on what reached the transport of a call that failed.
     *
     * @param array<int, mixed> $options
     * @return array{0: string, 1: string|null}
     * @throws CnicException the canned $throw, when one was supplied
     */
    #[\Override]
    public function post(string $url, string $data, int $timeoutSeconds, string $userAgent, array $options = []): array
    {
        $this->url = $url;
        $this->data = $data;
        $this->timeout = $timeoutSeconds;
        $this->userAgent = $userAgent;
        $this->options = $options;
        if ($this->throw instanceof CnicException) {
            throw $this->throw;
        }
        return [$this->raw, $this->error];
    }

    #[\Override]
    public function close(): void
    {
        $this->closed = true;
    }
}
