<?php

declare(strict_types=1);

/**
 * CNIC\MONIKER
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\MONIKER;

/**
 * Moniker API Client
 *
 * @psalm-api
 * @package CNIC\MONIKER
 */
final class SessionClient extends Client
{
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
