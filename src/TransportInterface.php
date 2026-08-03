<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

use CNIC\Exception\UnsupportedFeatureException;

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
     * $options carries the caller's transport tuning (see
     * {@see AbstractClient::setExtraCurlOptions()}). The contract is that a
     * caller's option **wins** over the implementation's own default for the same
     * key — an implementation that must own a key is required to reject it by
     * throwing {@see UnsupportedFeatureException}, naming what it refused, rather
     * than quietly ignoring it. Which keys those are is
     * implementation-specific: for the production transport they are
     * {@see HttpTransport::PROTECTED_OPTIONS}, while a test double typically
     * owns none and simply records what it was given.
     *
     * @param string $url request URL
     * @param string $data serialized POST payload
     * @param int $timeout socket timeout in seconds
     * @param string $userAgent user agent header value
     * @param array<int, mixed> $options additional cURL options, overriding the implementation's defaults
     * @return array{0: string, 1: string|null} [rawResponse, errorMessage|null]
     * @throws UnsupportedFeatureException if $options contains an option the implementation owns
     */
    public function post(string $url, string $data, int $timeout, string $userAgent, array $options = []): array;

    /**
     * Close and release any underlying connection/handle.
     */
    public function close(): void;
}
