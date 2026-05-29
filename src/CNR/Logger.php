<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\CNR;

use CNIC\LoggerInterface;
use CNIC\ResponseInterface;

/**
 * CNR Logger
 *
 * @package CNIC\CNR
 */
final class Logger implements LoggerInterface
{
    /**
     * output/log given data
     * @param string $post post request data in string format
     * @param ResponseInterface $r Response to log
     * @param string|null $error error message (optional)
     */
    #[\Override]
    public function log(string $post, ResponseInterface $r, ?string $error = null): void
    {
         echo implode("\n", [
            print_r($r->getCommand(), true),
            $post,
            $error !== null && $error !== '' ? "HTTP communication failed: " . $error : "",
            $r->getPlain()
         ]);
    }
}
