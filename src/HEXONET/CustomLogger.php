<?php

#declare(strict_types=1);

/**
 * MYCUSTOMNAMESPACE
 * Copyright © MYCUSTOMNAMESPACE
 */

namespace MYCUSTOMNAMESPACE;

use CNIC\Logger as L;
use CNIC\Response as R;

/**
 * MYCUSTOMNAMESPACE Logger
 *
 * @package MYCUSTOMNAMESPACE
 */

class Logger extends L
{
    /**
     * output/log given data
     */
    public function log(string $post, R $r, string $error = null): void
    {
        // apply your custom logging / output here
    }
}
