<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Destination for formatted debug output.
 *
 * The counterpart of {@see LoggerInterface::format()}: the logger decides what
 * the debug record looks like, a sink decides where it goes. Implement this to
 * route SDK debug output into a file, a PSR-3 logger or a host application's own
 * log without reimplementing a brand's format — see
 * {@see AbstractClient::setLogSink()}. (Ref: RSRMID-2925.)
 *
 * @psalm-api
 * @package CNIC
 */
interface LogSinkInterface
{
    /**
     * Write one formatted debug record.
     *
     * @param string $message Formatted debug record as returned by LoggerInterface::format()
     */
    public function write(string $message): void;
}
