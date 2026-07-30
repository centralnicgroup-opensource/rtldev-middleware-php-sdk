<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Common Logger Interface
 *
 * Two methods, and the split between them is the point (RSRMID-2925):
 * {@see format()} is the part that varies per brand and it **returns** the
 * record, {@see log()} is the part that only decides where the record goes. The
 * contract used to be `log()` alone, returning `void` and echoing internally, so
 * the formatting — the half with actual logic — could only be observed by
 * capturing output, and an integrator wanting the record elsewhere had to
 * reimplement the brand's format.
 *
 * Extend {@see AbstractLogger} to implement `format()` and inherit the sink
 * wiring; implement this interface directly only if you want to own the
 * destination too.
 *
 * @psalm-api
 * @package CNIC
 */
interface LoggerInterface
{
    /**
     * Build the debug record for the given request/response pair.
     *
     * Callers get the record as a string; nothing is written. Values are already
     * masked by the time they arrive here — the response masks its own stored
     * command ({@see AbstractResponse::sanitizeCommand()}) and the client passes
     * a secured POST body — so implementations must not undo that.
     *
     * @param string $post Post request data in string format (already secured)
     * @param ResponseInterface $r Response to log
     * @param string|null $error Error message (optional)
     * @return string Formatted debug record
     */
    public function format(string $post, ResponseInterface $r, ?string $error = null): string;

    /**
     * Write the debug record for the given request/response pair.
     *
     * @param string $post Post request data in string format (already secured)
     * @param ResponseInterface $r Response to log
     * @param string|null $error Error message (optional)
     */
    public function log(string $post, ResponseInterface $r, ?string $error = null): void;
}
