<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Contract for the low-level HTTP transport used by {@see AbstractClient}.
 *
 * Isolating the cURL layer behind this seam lets the request() lifecycle run
 * against a test double (e.g. a record/replay cassette transport) so the whole
 * path is exercisable offline, without touching the live API. The production
 * implementation is {@see HttpTransport}.
 *
 * @psalm-api
 * @package CNIC
 */
interface TransportInterface
{
    /**
     * Execute a POST request and return the raw response.
     *
     * @param string $url request URL
     * @param string $data serialized POST payload
     * @param int $timeout socket timeout in seconds
     * @param string $userAgent user agent header value
     * @param array<int, mixed> $options additional cURL options merged over the defaults
     * @return array{0: string, 1: string|null} [rawResponse, errorMessage|null]
     */
    public function post(string $url, string $data, int $timeout, string $userAgent, array $options = []): array;

    /**
     * Close and release any underlying connection/handle.
     */
    public function close(): void;
}
