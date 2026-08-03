<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\AbstractLogger;
use CNIC\ResponseInterface;

/**
 * IBS Logger
 *
 * Formatting only — the destination belongs to the sink the base class writes
 * to. Do not add a `log()` override here; it is final upstream.
 *
 * @package CNIC\IBS
 */
final class Logger extends AbstractLogger
{
    /**
     * Build the IBS debug record: a labelled REQUEST/RESPONSE block with the
     * plain response indented by one tab per line.
     *
     * @param string $post Post request data in string format (already masked)
     */
    #[\Override]
    public function format(string $post, ResponseInterface $response, ?string $error = null): string
    {
        return "R E Q U E S T\n" .
            "\tAPI:  " . $response->getRequestURL() . "\n" .
            "\tPOST: " . $post . "\n\n" .
            "R E S P O N S E\n" .
            ($error !== null && $error !== '' ? "\tHTTP communication failed: " . $error . "\n" : "") .
            "\t" . (preg_replace("/\n/", "\n\t", $response->getPlain()) ?? $response->getPlain());
    }
}
