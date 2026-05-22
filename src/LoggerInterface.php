<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © CentralNic Group PLC
 */

namespace CNIC;

use CNIC\CNR\Response;

/**
 * Common Logger Interface
 *
 * @psalm-api
 * @package CNIC
 */
interface LoggerInterface
{
    /**
     * Output/log given data
     *
     * @param string $post Post request data in string format
     * @param Response $r Response to log
     * @param string|null $error Error message (optional)
     */
    public function log(string $post, Response $r, ?string $error = null): void;
}
