<?php

declare(strict_types=1);

/**
 * MYCUSTOMNAMESPACE
 * Copyright © MYCUSTOMNAMESPACE
 */

namespace MYCUSTOMNAMESPACE;

use CNIC\LogSinkInterface;

/**
 * MYCUSTOMNAMESPACE log sink
 *
 * The destination half of the debug-output seam — all you need when the brand's
 * format already suits you and you only want the bytes somewhere other than
 * STDOUT (a file, a PSR-3 logger, the WHMCS/Blesta module log):
 *
 * ```php
 * $cl->enableDebugMode()
 *    ->setLogSink(new \MYCUSTOMNAMESPACE\FileSink("/var/log/cnic.log"));
 * ```
 *
 * See `CustomLoggerClass.php` for the format half.
 *
 * @psalm-api
 * @package MYCUSTOMNAMESPACE
 */
class FileSink implements LogSinkInterface
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * @param string $message Formatted debug record
     */
    #[\Override]
    public function write(string $message): void
    {
        file_put_contents($this->path, $message . PHP_EOL, FILE_APPEND);
    }
}
