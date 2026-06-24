<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\LoggerInterface;
use CNIC\ResponseInterface;

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
     * @param ResponseInterface $r Response to log
     * @param string|null $error Error message (optional)
     */
    #[\Override]
    public function log(string $post, ResponseInterface $r, ?string $error = null): void
    {
        echo (
            "R E Q U E S T\n" .
            "\tAPI:  " . $r->getRequestURL() . "\n" .
            "\tPOST: " . $post . "\n\n" .
            "R E S P O N S E\n" .
            ($error !== null && $error !== '' ? "\tHTTP communication failed: " . $error . "\n" : "") .
            "\t" . (preg_replace("/\n/", "\n\t", $r->getPlain()) ?? $r->getPlain())
        );
    }
}
