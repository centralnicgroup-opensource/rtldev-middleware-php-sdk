<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Log sink writing to standard output — the shipped default, and what keeps
 * `enableDebugMode()` emitting the bytes consumers expect.
 *
 * @psalm-api
 * @package CNIC
 */
final class EchoSink implements LogSinkInterface
{
    #[\Override]
    public function write(string $message): void
    {
        echo $message;
    }
}
