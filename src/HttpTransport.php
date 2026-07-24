<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Low-level HTTP transport over cURL.
 * Owns the cURL handle lifecycle and exposes a single post() method.
 *
 * @package CNIC
 */
final class HttpTransport implements TransportInterface
{
    private ?\CurlHandle $handle = null;

    /**
     * Execute a POST request and return the raw response.
     *
     * @param array<int, mixed> $options additional cURL options merged over the defaults
     * @return array{0: string, 1: string|null} [rawResponse, errorMessage|null]
     */
    #[\Override]
    public function post(string $url, string $data, int $timeout, string $userAgent, array $options = []): array
    {
        if (!$this->handle instanceof \CurlHandle) {
            $tmp = curl_init();
            // @codeCoverageIgnoreStart
            // curl_init() only returns false when the curl extension is
            // unavailable; ext-curl is a hard composer requirement, so this
            // defensive guard is unreachable in any supported environment.
            if ($tmp === false) {
                return ["nocurl", "CURL for PHP missing."];
            }
            // @codeCoverageIgnoreEnd
            $this->handle = $tmp;
        }

        // Reset per-call options on the reused handle so that options from a
        // previous call (e.g. proxy/referer) cannot leak into this one. This
        // preserves the live connection, DNS and SSL session caches, so the
        // keep-alive benefit of handle reuse is retained.
        curl_reset($this->handle);

        curl_setopt_array($this->handle, [
            // CURLOPT_VERBOSE         => true,
            CURLOPT_URL             => $url,
            CURLOPT_CONNECTTIMEOUT  => 30, // 30s connect timeout (cURL defaults to 300s when this is not set explicitly)
            CURLOPT_TIMEOUT         => $timeout,
            CURLOPT_POST            => 1,
            CURLOPT_HEADER          => 0,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_SSL_VERIFYPEER  => true, // explicit (cURL default) — verify the peer's certificate
            CURLOPT_SSL_VERIFYHOST  => 2,    // explicit (cURL default) — certificate host must match
            CURLOPT_POSTFIELDS      => $data,
            CURLOPT_USERAGENT       => $userAgent,
            CURLOPT_HTTPHEADER      => [
                "Expect:",
                "Content-Type: application/x-www-form-urlencoded", //UTF-8 implied
                "Content-Length: " . strlen($data),
                "Connection: keep-alive"
            ]
        ] + $options);

        $r = curl_exec($this->handle);
        \assert(\is_string($r) || $r === false);
        if ($r === false) {
            $error = curl_error($this->handle);
            return ["httperror|" . $error, $error];
        }
        return [$r, null];
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
