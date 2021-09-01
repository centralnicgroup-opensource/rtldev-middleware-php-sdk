<?php

#declare(strict_types=1);

/**
 * MYCUSTOMNAMESPACE
 * Copyright © MYCUSTOMNAMESPACE
 */

namespace MYCUSTOMNAMESPACE;

/**
 * MYCUSTOMNAMESPACE Logger
 *
 * @package MYCUSTOMNAMESPACE
 */

class Logger implements \CNIC\LoggerInterface
{
    /**
     * output/log given data
     */
    public function log(string $post, \CNIC\ResponseInterface $r, string $error = null): void
    {
        // apply your custom logging / output here
    }
}
