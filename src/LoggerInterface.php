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
 */

interface LoggerInterface
{
    /**
     * output/log given data
     * @param string $post post request data in string format
     * @param \CNIC\HEXONET\Response $r Response to log
     * @param string|null $error error message
     */
    public function log(string $post, \CNIC\HEXONET\Response $r, string $error = null): void;
}
