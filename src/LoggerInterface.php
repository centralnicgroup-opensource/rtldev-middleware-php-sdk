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
     */
    public function log(string $post, \CNIC\HEXONET\Response $r, string $error = null);
}
