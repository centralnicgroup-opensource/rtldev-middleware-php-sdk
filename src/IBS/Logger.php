<?php

#declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\IBS;

/**
 * IBS Logger
 *
 * @package CNIC\IBS
 */

class Logger implements \CNIC\LoggerInterface
{
    /**
     * output/log given data
     * @param string $post post request data in string format
     * @param Response $r Response to log
     * @param string|null $error error message
     */
    public function log($post, $r, $error = null): void
    {
        echo (
            "R E Q U E S T\n" .
            "\tAPI:  " . $r->getRequestURL() . "\n" .
            "\tPOST: " . $post . "\n\n" .
            "R E S P O N S E\n" .
            ($error ? "\tHTTP communication failed: " . $error . "\n" : "") .
            "\t" . preg_replace("/\n/", "\n\t", $r->getPlain())
        );
    }
}
