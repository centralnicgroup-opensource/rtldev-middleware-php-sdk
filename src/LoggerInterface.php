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
 * Two methods, and the split between them is the point: {@see format()} is the
 * part that varies per brand and it **returns** the record, {@see log()} only
 * decides where the record goes. Do not collapse them back into a single
 * echoing `log()` — see the logger-seam entry in docs/agents/architecture.md.
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
     * Callers get the record as a string; nothing is written. Both arguments are
     * already masked when they arrive — the response masks its own stored
     * command ({@see AbstractResponse::sanitizeCommand()}, so `getCommand()` and
     * `getCommandPlain()` are safe) and the client passes a secured POST body —
     * so implementations must not undo that. The exception is
     * {@see ResponseInterface::getContext()}, which is caller-supplied and
     * deliberately untouched: an implementation logging it masks it itself.
     *
     * @param string $post Post request data in string format (already masked)
     */
    public function format(string $post, ResponseInterface $response, ?string $error = null): string;

    /**
     * Write the debug record for the given request/response pair.
     *
     * @param string $post Post request data in string format (already masked)
     */
    public function log(string $post, ResponseInterface $response, ?string $error = null): void;
}
