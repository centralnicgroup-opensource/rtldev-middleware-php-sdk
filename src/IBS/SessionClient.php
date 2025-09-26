<?php

#declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\IBS;

/**
 * IBS API Client
 *
 * @package CNIC\IBS
 */
class SessionClient extends \CNIC\IBS\Client
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

    /**
     * Perform API login to start session-based communication
     * @throws \Exception
     * @return Response
     */
    public function login()
    {
        throw new \Exception("Method not supported");
    }

    /**
     * Perform API logout to close API session in use
     * @throws \Exception
     * @return Response
     */
    public function logout()
    {
        throw new \Exception("Method not supported");
    }

    /**
     * Apply session data (session id, login) to given php session object
     * @param array<string,mixed> $session php session instance ($_SESSION)
     * @throws \Exception
     * @return $this
     */
    public function saveSession(&$session)
    {
        throw new \Exception("Method not supported");
    }

    /**
     * Use existing configuration out of php session object
     * to rebuild and reuse connection settings
     * @param array<string,mixed> $session php session object ($_SESSION)
     * @throws \Exception
     * @return $this
     */
    public function reuseSession(&$session)
    {
        throw new \Exception("Method not supported");
    }
}
