<?php

declare(strict_types=1);

/**
 * MYCUSTOMNAMESPACE
 * Copyright © MYCUSTOMNAMESPACE
 */

namespace MYCUSTOMNAMESPACE;

use CNIC\AbstractLogger;
use CNIC\ResponseInterface;

/**
 * MYCUSTOMNAMESPACE Logger
 *
 * Two things vary in SDK debug output, and they are separate seams:
 *
 * 1. **The format** — implement `format()` and return the record. Extending
 *    {@see \CNIC\AbstractLogger} is all it takes; writing is handled for you.
 * 2. **The destination** — implement {@see \CNIC\LogSinkInterface} and hand it
 *    to the client with `setLogSink()`. If the stock format is fine and you only
 *    want the bytes somewhere else, that half is all you need — see
 *    `CustomLogSinkClass.php`.
 *
 * Use the class below when you want your own format. It takes a sink like any
 * other logger, so the two halves compose:
 *
 * ```php
 * $cl->enableDebugMode()
 *    ->setCustomLogger(new \MYCUSTOMNAMESPACE\Logger(new \MYCUSTOMNAMESPACE\FileSink("/var/log/cnic.log")));
 * ```
 *
 * Everything reaching `format()` is already masked — the response masks its own
 * stored command and the client passes a secured POST body — so do not reach
 * past `$r->getCommand()` for raw values when logging.
 *
 * @psalm-api
 * @package MYCUSTOMNAMESPACE
 */
class Logger extends AbstractLogger
{
    /**
     * Build the debug record. Return it; do not print it.
     *
     * @param string $post Post request data in string format (already secured)
     * @param ResponseInterface $r Response to log
     * @param string|null $error Error message (optional)
     * @return string Formatted debug record
     */
    #[\Override]
    public function format(string $post, ResponseInterface $r, ?string $error = null): string
    {
        // apply your custom formatting here
        return sprintf(
            "[%s] %s -> %d %s%s",
            $r->getRequestURL(),
            $post,
            $r->getCode(),
            $r->getDescription(),
            $error !== null && $error !== "" ? " (transport error: " . $error . ")" : ""
        );
    }
}
