<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Contract for the brand parser that turns a raw API response into its hash form.
 *
 * Isolating the parse step behind this seam is the Response-tree twin of
 * {@see TransportInterface}: a substitute parser can be handed to a Response
 * (see {@see AbstractResponse::__construct()}) without reflection or
 * subclassing, and each brand's real parser can be exercised directly instead of
 * only through a fully constructed Response. The production implementations are
 * {@see \CNIC\CNR\ResponseParser} (line-oriented `key=value` with `PROPERTY[…]`
 * columns) and {@see \CNIC\IBS\ResponseParser} (JSON, falling back to plain
 * text), used by IBS and Moniker alike.
 *
 * The signature is deliberately uniform across brands even though only IBS reads
 * $cmd — a contract the two could not both satisfy would be no contract at all.
 * CNR's wire format is self-describing and its parser ignores the argument.
 *
 * @psalm-api
 * @package CNIC
 */
interface ResponseParserInterface
{
    /**
     * Parse a raw API response into its hash form.
     *
     * @param string $raw raw (already translated) API response
     * @param array<string, string> $cmd sanitized API command that produced the response
     * @return array<string, mixed>
     */
    public function parse(string $raw, array $cmd = []): array;
}
