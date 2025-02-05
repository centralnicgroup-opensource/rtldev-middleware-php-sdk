<?php

#declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\CNR;

/**
 * CNR SocketConfig
 *
 * @package CNIC\CNR
 */

class SocketConfig extends \CNIC\HEXONET\SocketConfig
{
    /**
     * parameter to trigger creation of a backend session
     * @var bool
     */
    private $persistent = false;

    /**
     * Create POST data string out of connection data
     * @param array<int|string,mixed> $command API Command to request
     * @param bool $secured if password has to be returned "hidden"
     * @return string POST data string
     */
    public function getPOSTData($command = [], $secured = false)
    {
        $params = $this->getPOSTDataParams($command, $secured);
        if ($this->getPersistent()) {
            $params["persistent"] = 1;
        }
        if (strlen($this->user)) {
            $params[$this->parameters["command"]] .= "\nSUBUSER={$this->user}";
        }
        return http_build_query($params);//RFC1738 x-www-form-urlencoded as default
    }

    /**
     * add persistent parameter to request (request api session)
     *
     * @param boolean $value
     * @return $this
     */
    public function setPersistent($value = false)
    {
        $this->persistent = ($value !== false);
        return $this;
    }

    /**
     * get persistent parameter returned
     * @return bool
     */
    public function getPersistent()
    {
        return $this->persistent;
    }

    /**
     * Set API Session ID to use
     * @param string $value API Session ID
     * @return $this
     */
    public function setSession($value)
    {
        $this->session = $value;
        $this->pw = "";
        return $this;
    }

    /**
     * Get current login (including role)
     *
     * @return string
     */
    public function getLogin(): string
    {
        return $this->login;
    }
}
