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
 * @psalm-api
 * @package CNIC
 */
interface LoggerInterface
{
    /**
     * Output/log given data
     *
     * @param string $post Post request data in string format
     * @param ResponseInterface $r Response to log
     * @param string|null $error Error message (optional)
     */
    public function log(string $post, ResponseInterface $r, ?string $error = null): void;
}
