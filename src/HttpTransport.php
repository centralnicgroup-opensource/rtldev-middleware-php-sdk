<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © CentralNic Group PLC
 */

namespace CNIC;

/**
 * Low-level HTTP transport over cURL.
 * Owns the cURL handle lifecycle and exposes a single post() method.
 *
 * @package CNIC
 */
class HttpTransport
{
    private ?\CurlHandle $handle = null;

    /**
     * Execute a POST request and return the raw response.
     *
     * @param array<int, mixed> $options additional cURL options merged over the defaults
     * @return array{0: string, 1: string|null} [rawResponse, errorMessage|null]
     */
    public function post(string $url, string $data, int $timeout, string $userAgent, array $options = []): array
    {
        if (!$this->handle instanceof \CurlHandle) {
            $tmp = curl_init();
            if ($tmp === false) {
                return ["nocurl", "CURL for PHP missing."];
            }
            $this->handle = $tmp;
        }

        curl_setopt_array($this->handle, [
            // CURLOPT_VERBOSE         => true,
            CURLOPT_URL             => $url,
            CURLOPT_CONNECTTIMEOUT  => 30, // 30s, 300s by default
            CURLOPT_TIMEOUT         => $timeout,
            CURLOPT_POST            => 1,
            CURLOPT_HEADER          => 0,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_POSTFIELDS      => $data,
            CURLOPT_USERAGENT       => $userAgent,
            CURLOPT_HTTPHEADER      => [
                "Expect:",
                "Content-Type: application/x-www-form-urlencoded", //UTF-8 implied
                "Content-Length: " . (string)strlen($data),
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
    public function close(): void
    {
        if ($this->handle instanceof \CurlHandle) {
            curl_close($this->handle);
            $this->handle = null;
        }
    }
}
