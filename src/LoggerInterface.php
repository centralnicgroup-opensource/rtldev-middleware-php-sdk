<?php

#declare(strict_types=1);

/**
 * CNIC
 * Copyright © CentralNic Group PLC
 */

namespace CNIC;

/**
 * Common Logger Interface
 *
 * @package CNIC
 * @method void log(string $post, \CNIC\HEXONET\Response $r, string $error = null) Output/log given data
 */
interface LoggerInterface
{
    /**
     * Output/log given data
     *
     * @param string $post Post request data in string format
     * @param \CNIC\HEXONET\Response $r Response to log
     * @param string|null $error Error message
     */
    public function log(string $post, \CNIC\HEXONET\Response $r, string $error = null): void;
}
