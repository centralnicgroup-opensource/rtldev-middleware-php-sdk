<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

use CNIC\Exception\UnsupportedFeatureException;

/**
 * Low-level HTTP transport over cURL.
 * Owns the cURL handle lifecycle and exposes a single post() method.
 *
 * @package CNIC
 */
final class HttpTransport implements TransportInterface
{
    /**
     * cURL options this transport owns; a caller may not override them through
     * the per-call option bag ({@see AbstractClient::setExtraCurlOptions()}).
     * Passing one of these to {@see post()} raises
     * {@see UnsupportedFeatureException} rather than being silently dropped.
     *
     * Two kinds of option, protected for two different reasons:
     * - Request-envelope invariants — CURLOPT_URL, POST, POSTFIELDS,
     *   RETURNTRANSFER, HEADER. Response parsing is written against exactly this
     *   envelope; CURLOPT_RETURNTRANSFER => 0, for instance, makes curl_exec()
     *   return true and leaves the parser with nothing to read.
     * - TLS verification posture — CURLOPT_SSL_VERIFYPEER, SSL_VERIFYHOST.
     *   Turning certificate verification off is a deliberate, security-relevant
     *   act and must not be reachable through a generic convenience bag.
     *
     * Everything else the transport sets (CURLOPT_TIMEOUT, CONNECTTIMEOUT,
     * USERAGENT) is a default the caller may override. CURLOPT_HTTPHEADER is
     * the one middle case: callers may add header lines but not restate the
     * transport's own — see {@see appendHeaders()}.
     *
     * Keyed by the cURL constant, valued with its name, so the rejection
     * message can name what the caller passed instead of handing back a bare
     * integer to look up. One list, not two: a second list of the same
     * constants could drift out of step with this one.
     *
     * Keep the list exactly the envelope and TLS keys — widening it silently
     * re-breaks legitimate tuning, narrowing it gives away one of the two
     * guarantees. Pinned by
     * HttpTransportCurlOptionsTest::testProtectedOptionsAreExactlyTheEnvelopeAndTlsKeys().
     *
     * @var array<int, string>
     */
    public const PROTECTED_OPTIONS = [
        CURLOPT_URL => "CURLOPT_URL",
        CURLOPT_POST => "CURLOPT_POST",
        CURLOPT_POSTFIELDS => "CURLOPT_POSTFIELDS",
        CURLOPT_RETURNTRANSFER => "CURLOPT_RETURNTRANSFER",
        CURLOPT_HEADER => "CURLOPT_HEADER",
        CURLOPT_SSL_VERIFYPEER => "CURLOPT_SSL_VERIFYPEER",
        CURLOPT_SSL_VERIFYHOST => "CURLOPT_SSL_VERIFYHOST",
    ];

    private ?\CurlHandle $handle = null;

    /**
     * Execute a POST request and return the raw response.
     *
     * The caller's $options win over the transport's own defaults on key
     * collision, except for {@see PROTECTED_OPTIONS}, which are rejected.
     * CURLOPT_HTTPHEADER is additive: caller lines are appended to the
     * transport's, and restating one of the transport's own headers is rejected
     * rather than allowed to override it (see {@see appendHeaders()}).
     *
     * A non-null element [1] means the request failed and the payload is
     * unusable: on failure this transport returns ["", $error] — empty raw,
     * the cURL error message in [1] — and the caller (see
     * {@see AbstractResponseTranslator}) discards the raw side entirely in
     * favour of the "httperror" template. See {@see TransportInterface::post()}
     * for the contract every implementation must honour.
     *
     * @param array<int, mixed> $options additional cURL options, overriding the transport defaults
     * @return array{0: string, 1: string|null} [rawResponse, errorMessage|null] — errorMessage !== null means rawResponse is unusable
     * @throws UnsupportedFeatureException if $options contains a transport-owned option or header
     */
    #[\Override]
    public function post(
        string $url,
        string $data,
        int $timeoutSeconds,
        string $userAgent,
        array $options = []
    ): array {
        // Reject before touching the handle: a protected option is a
        // programming error, not a network event, and must not cost a request.
        self::rejectProtectedOptions($options);

        if (!$this->handle instanceof \CurlHandle) {
            $tmp = curl_init();
            // curl_init() only returns false when the curl extension is
            // unavailable; ext-curl is a hard composer requirement, so this is
            // unreachable in any supported environment — assert it rather than
            // branch on it.
            \assert($tmp !== false);
            $this->handle = $tmp;
        }

        // Reset per-call options on the reused handle so that options from a
        // previous call (e.g. proxy/referer) cannot leak into this one. This
        // preserves the live connection, DNS and SSL session caches, so the
        // keep-alive benefit of handle reuse is retained.
        curl_reset($this->handle);

        $headers = [
            "Expect:",
            "Content-Type: application/x-www-form-urlencoded", //UTF-8 implied
            "Content-Length: " . strlen($data),
            "Connection: keep-alive"
        ];
        // Caller headers are appended to the transport's, never replace them.
        // A non-array value is deliberately left in $options for cURL to reject
        // with its own TypeError — loud, and before anything is sent.
        if (isset($options[CURLOPT_HTTPHEADER]) && is_array($options[CURLOPT_HTTPHEADER])) {
            $headers = self::appendHeaders($headers, $options[CURLOPT_HTTPHEADER]);
            unset($options[CURLOPT_HTTPHEADER]);
        }

        // $options on the LEFT of the union: PHP's + keeps the left operand's
        // value on a duplicate key, so the caller wins over these defaults.
        // Anything the caller must NOT win on was rejected above.
        curl_setopt_array($this->handle, $options + [
            // CURLOPT_VERBOSE         => true,
            CURLOPT_URL             => $url,
            CURLOPT_CONNECTTIMEOUT  => 30, // 30s connect timeout (cURL defaults to 300s when this is not set explicitly)
            CURLOPT_TIMEOUT         => $timeoutSeconds,
            CURLOPT_POST            => 1,
            CURLOPT_HEADER          => 0,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_SSL_VERIFYPEER  => true, // explicit (cURL default) — verify the peer's certificate
            CURLOPT_SSL_VERIFYHOST  => 2,    // explicit (cURL default) — certificate host must match
            CURLOPT_POSTFIELDS      => $data,
            CURLOPT_USERAGENT       => $userAgent,
            CURLOPT_HTTPHEADER      => $headers
        ]);

        $r = curl_exec($this->handle);
        \assert(\is_string($r) || $r === false);
        if ($r === false) {
            return ["", curl_error($this->handle)];
        }
        return [$r, null];
    }

    /**
     * Fail loudly when the caller asks to override an option the transport owns.
     * Do not downgrade this to an ignore: silently discarding the option leaves the
     * caller believing a setting applied when it did not.
     *
     * @param array<int, mixed> $options
     * @throws UnsupportedFeatureException
     */
    private static function rejectProtectedOptions(array $options): void
    {
        $rejected = array_intersect_key(self::PROTECTED_OPTIONS, $options);
        if ($rejected === []) {
            return;
        }
        $names = array_values($rejected);
        throw new UnsupportedFeatureException(
            "cURL option(s) owned by " . self::class . " cannot be overridden: " . implode(", ", $names)
            . ". They define the request envelope the response parser depends on, or the TLS verification"
            . " posture; remove them from the option bag."
        );
    }

    /**
     * Append caller-supplied header lines to the transport's own.
     *
     * Additive only, and restating one of the transport's headers is rejected.
     * The transport's list describes the request envelope — Content-Type and
     * Content-Length are derived from the POST body, and Connection follows
     * from the reused handle — so letting a caller replace one is the same
     * class of mistake as letting them override CURLOPT_POSTFIELDS, which
     * {@see PROTECTED_OPTIONS} already forbids. Overriding by name would also
     * be the quiet kind of failure this change exists to remove: a wrong
     * Content-Length corrupts the request with no signal at all. Appending
     * blindly is no better — two Content-Type lines leave the winner up to the
     * server — so a collision is an error, not a merge.
     *
     * @param list<string> $base transport headers
     * @param array<array-key, mixed> $extra caller headers
     * @return list<string>
     * @throws UnsupportedFeatureException if a caller line restates a transport header
     */
    private static function appendHeaders(array $base, array $extra): array
    {
        $owned = [];
        foreach ($base as $line) {
            $owned[self::headerName($line)] = true;
        }
        // Non-string entries are dropped rather than coerced; cURL would reject
        // them anyway, and guessing at what a caller meant is the behaviour this
        // change exists to remove.
        foreach (array_filter($extra, "is_string") as $line) {
            $name = self::headerName($line);
            if (isset($owned[$name])) {
                throw new UnsupportedFeatureException(
                    "HTTP header(s) owned by " . self::class . " cannot be overridden: " . $name
                    . ". Content-Type/Content-Length describe the POST body and Connection follows from"
                    . " the reused handle; add your own headers instead of restating these."
                );
            }
            $owned[$name] = true;
            $base[] = $line;
        }
        return $base;
    }

    /**
     * Extract the lower-cased field name from a "Name: value" header line.
     * A line with no colon (malformed) is treated as its own name, so it is
     * neither mistaken for nor able to collide with a real header.
     */
    private static function headerName(string $line): string
    {
        $pos = strpos($line, ":");
        return strtolower(trim($pos === false ? $line : substr($line, 0, $pos)));
    }

    /**
     * Close and reset the cURL handle.
     */
    #[\Override]
    public function close(): void
    {
        $this->handle = null; // CurlHandle freed automatically by GC (curl_close deprecated since PHP 8.5)
    }
}
