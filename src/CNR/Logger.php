<?php

#declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\CNR;

/**
 * CNR Logger
 *
 * @package CNIC\CNR
 */
class Logger implements \CNIC\LoggerInterface
{
    /**
     * output/log given data
     * @param string $post post request data in string format
     * @param Response $r Response to log
     * @param string|null $error error message
     * @return void
     */
    public function log($post, $r, $error = null): void
    {
         echo implode("\n", [
            print_r($r->getCommand(), true),
            $post,
            $error ? "HTTP communication failed: " . $error : "",
            $r->getPlain()
         ]);
    }
}
