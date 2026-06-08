<?php

declare(strict_types=1);

/**
 * MYCUSTOMNAMESPACE
 * Copyright © MYCUSTOMNAMESPACE
 */

namespace MYCUSTOMNAMESPACE;

use CNIC\LoggerInterface;
use CNIC\ResponseInterface;

/**
 * MYCUSTOMNAMESPACE Logger
 *
 * @psalm-api
 * @package MYCUSTOMNAMESPACE
 */
class Logger implements LoggerInterface
{
    /**
     * Output/log given data
     *
     * @param string $post Post request data in string format
     * @param ResponseInterface $r Response to log
     * @param string|null $error Error message (optional)
     */
    #[\Override]
    public function log(string $post, ResponseInterface $r, ?string $error = null): void
    {
        // apply your custom logging / output here
    }
}
