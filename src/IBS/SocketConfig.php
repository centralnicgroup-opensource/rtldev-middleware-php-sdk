<?php

#declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\IBS;

/**
 * IBS SocketConfig
 *
 * @package CNIC\IBS
 */

class SocketConfig extends \CNIC\HEXONET\SocketConfig
{
    /**
     * account name
     * @var string
     */
    protected $login;
    /**
     * account password
     * @var string
     */
    protected $pw;
    /**
     * remote ip address (ip filter)
     * @var string
     */
    protected $remoteaddr;
    /**
     * list of http request parameters
     * @var array<string>
     */
    protected $parameters;

    /**
     * @param array<mixed> $parameters
     */
    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
        $this->login = "";
        $this->pw = "";
        $this->remoteaddr = "";
    }

        /**
     * Get POST data container of connection data
     * @param array<mixed> $command API Command to request
     * @param bool $secured if password has to be returned "hidden"
     * @return array<string,string>
     */
    protected function getPOSTDataParams($command, $secured)
    {
        $params = $command; // here $command is just an array of request parameters
        if (strlen($this->login)) {
            $params[$this->parameters["login"]] = $this->login;
        }
        if (strlen($this->pw)) {
            $params[$this->parameters["password"]] = $secured ? "***" : $this->pw;
        }
        if (strlen($this->remoteaddr) && isset($this->parameters["ipfilter"])) {
            $params[$this->parameters["ipfilter"]] = $this->remoteaddr;
        }
        return $params;
    }

    /**
     * Create POST data string out of connection data
     * @param array<int|string,mixed> $command API Command to request
     * @param bool $secured if password has to be returned "hidden"
     * @return string POST data string
     */
    public function getPOSTData($command = [], $secured = false)
    {
        $params = $this->getPOSTDataParams($command, $secured);
        return http_build_query($params);//RFC1738 x-www-form-urlencoded as default
    }

        /**
     * Set account name to use
     * @param string $value account name
     * @return $this
     */
    public function setLogin($value)
    {
        $this->login = $value;
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

    /**
     * Set account password to use
     * @param string $value account password
     * @return $this
     */
    public function setPassword($value)
    {
        $this->pw = $value;
        return $this;
    }

    /**
     * Set Remote IP Address to use
     * @param string $value remote ip address
     * @return $this
     */
    public function setRemoteAddress($value)
    {
        $this->remoteaddr = $value;
        return $this;
    }
}
