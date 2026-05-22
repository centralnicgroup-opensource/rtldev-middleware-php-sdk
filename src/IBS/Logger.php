<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\IBS;

use CNIC\CNR\Response;
use CNIC\IBS\Response as IBSResponse;
use CNIC\LoggerInterface;

/**
 * IBS Logger
 *
 * @package CNIC\IBS
 */
final class Logger implements LoggerInterface
{
    /**
     * Output/log given data
     *
     * @param string $post Post request data in string format
     * @param Response $r Response to log
     * @param string|null $error Error message (optional)
     */
    #[\Override]
    public function log(string $post, Response $r, ?string $error = null): void
    {
        $requestUrl = '';
        if ($r instanceof IBSResponse) {
            $requestUrl = $r->getRequestURL();
        }

        echo (
            "R E Q U E S T\n" .
            "\tAPI:  " . $requestUrl . "\n" .
            "\tPOST: " . $post . "\n\n" .
            "R E S P O N S E\n" .
            ($error !== null && $error !== '' ? "\tHTTP communication failed: " . $error . "\n" : "") .
            "\t" . (preg_replace("/\n/", "\n\t", $r->getPlain()) ?? $r->getPlain())
        );
    }
}
