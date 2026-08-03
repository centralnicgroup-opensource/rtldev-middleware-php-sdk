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
 * Both arguments arrive masked: the response masks its own stored command (so
 * `getCommand()` and `getCommandPlain()` are safe) and the client passes a
 * masked POST body. The one thing that is **not** masked is
 * `$response->getContext()` — that array is whatever you put there yourself, so
 * if you log it, mask it yourself.
 *
 * @psalm-api
 * @package MYCUSTOMNAMESPACE
 */
class Logger extends AbstractLogger
{
    /**
     * Build the debug record. Return it; do not print it.
     *
     * @param string $post Post request data in string format (already masked)
     * @return string Formatted debug record
     */
    #[\Override]
    public function format(string $post, ResponseInterface $response, ?string $error = null): string
    {
        // apply your custom formatting here
        return sprintf(
            "[%s] %s -> %d %s%s",
            $response->getRequestURL(),
            $post,
            $response->getCode(),
            $response->getDescription(),
            $error !== null && $error !== "" ? " (transport error: " . $error . ")" : ""
        );
    }
}
