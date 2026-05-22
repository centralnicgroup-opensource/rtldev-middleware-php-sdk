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
final class Logger implements \CNIC\LoggerInterface
{
    /**
     * output/log given data
     * @param string $post post request data in string format
     * @param \CNIC\CNR\Response $r Response to log
     * @param string|null $error error message (optional)
     * @return void
     */
    #[\Override]
    public function log($post, \CNIC\CNR\Response $r, ?string $error = null): void
    {
         echo implode("\n", [
            print_r($r->getCommand(), true),
            $post,
            ($error !== null && $error !== '') ? "HTTP communication failed: " . $error : "",
            $r->getPlain()
         ]);
    }
}
