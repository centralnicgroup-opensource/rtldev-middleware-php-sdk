<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

use CNIC\AbstractLogger;
use CNIC\ResponseInterface;

/**
 * CNR Logger
 *
 * Formatting only — the destination belongs to the sink the base class writes
 * to. Do not add a `log()` override here; it is final upstream.
 *
 * @package CNIC\CNR
 */
final class Logger extends AbstractLogger
{
    /**
     * Build the CNR debug record: command, POST body, optional transport error
     * and the plain response, joined by newlines.
     *
     * @param string $post post request data in string format (already masked)
     */
    #[\Override]
    public function format(string $post, ResponseInterface $response, ?string $error = null): string
    {
        return implode("\n", [
            print_r($response->getCommand(), true),
            $post,
            $error !== null && $error !== '' ? "HTTP communication failed: " . $error : "",
            $response->getPlain()
        ]);
    }
}
