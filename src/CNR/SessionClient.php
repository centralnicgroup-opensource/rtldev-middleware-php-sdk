<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\CNR;

/**
 * CNR Session API Client
 *
 * @psalm-api
 * @package CNIC\CNR
 */
class SessionClient extends Client
{
    use SessionCapable;

    /**
     * Constructor
     * @throws \Exception
     */
    public function __construct()
    {
        $reflection = new \ReflectionClass(get_called_class());
        $fname = $reflection->getFileName();
        if ($fname === false) {
            throw new \Exception("Reflection failed");
        }
        $cfgpath = implode(DIRECTORY_SEPARATOR, [dirname($fname), "config.json"]);
        parent::__construct($cfgpath);
    }
}
