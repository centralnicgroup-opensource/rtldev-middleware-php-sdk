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
     * Output/log given data
     *
     * @param string $post Post request data in string format
     * @param \CNIC\CNR\Response $r Response to log
     * @param string|null $error Error message (optional)
     * @return void
     */
    public function log(string $post, \CNIC\CNR\Response $r, ?string $error = null): void
    {
        $requestUrl = '';
        if ($r instanceof \CNIC\IBS\Response) {
            $requestUrl = $r->getRequestURL();
        }

        echo (
            "R E Q U E S T\n" .
            "\tAPI:  " . $requestUrl . "\n" .
            "\tPOST: " . $post . "\n\n" .
            "R E S P O N S E\n" .
            ($error ? "\tHTTP communication failed: " . $error . "\n" : "") .
            "\t" . preg_replace("/\n/", "\n\t", $r->getPlain())
        );
    }
}
