<?php

declare(strict_types=1);

/**
 * CNICTEST\Support
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST\Support;

use CNIC\ResponseParserInterface;

/**
 * Recording ResponseParserInterface double (RSRMID-2924).
 *
 * Ignores the wire entirely: it returns a canned hash and records what the
 * Response handed it, so a test can prove that the substitute — not the brand
 * parser — produced the response, and that it was fed the *translated* raw plus
 * the *sanitized* command. The parser twin of {@see SpyTransport}.
 *
 * The canned hash deliberately carries both brands' status keys (CNR's
 * CODE/DESCRIPTION and IBS's status/message) plus a PROPERTY block, so one
 * double drives the column/record assembly of either brand.
 *
 * Deliberately records only what is asserted on today, and takes no
 * configuration it is not given (Psalm runs with findUnusedCode, so a
 * speculative field or parameter is an error, not a convenience). Add one when
 * a test needs it.
 */
final class SpyResponseParser implements ResponseParserInterface
{
    /** @var array<string, mixed> canned hash returned in place of a real parse */
    private const HASH = [
        "CODE" => "999",
        "DESCRIPTION" => "from the substitute",
        "status" => "FAILURE",
        "message" => "from the substitute",
        "PROPERTY" => ["SUBSTITUTE" => ["a", "b"]]
    ];

    /** Raw response handed over by the Response; empty until the first call. */
    public string $seenRaw = "";

    /** @var array<string, string> command handed over by the Response */
    public array $seenCmd = [];

    /**
     * @param array<string, string> $cmd
     * @return array<string, mixed>
     */
    #[\Override]
    public function parse(string $raw, array $cmd = []): array
    {
        $this->seenRaw = $raw;
        $this->seenCmd = $cmd;
        return self::HASH;
    }
}
