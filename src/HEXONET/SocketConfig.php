<?php

#declare(strict_types=1);

/**
 * CNIC\HEXONET
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\HEXONET;

/**
 * HEXONET SocketConfig
 *
 * @package CNIC\HEXONET
 */

class SocketConfig
{
    /**
     * API system entity. "54cd" for LIVE system; "1234" for OT&E system
     * @var string
     */
    private $entity;
    /**
     * account name
     * @var string
     */
    private $login;
    /**
     * one time password (2FA)
     * @var string
     */
    private $otp;
    /**
     * account password
     * @var string
     */
    private $pw;
    /**
     * remote ip address (ip filter)
     * @var string
     */
    private $remoteaddr;
    /**
     * API session id
     * @var string
     */
    private $session;
    /**
     * subuser account name (subuser specific data view)
     * @var string
     */
    private $user;
    /**
     * list of http request parameters
     * @var array
     */
    private $parameters;

    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
        $this->entity = "";
        $this->login = "";
        $this->otp = "";
        $this->pw = "";
        $this->remoteaddr = "";
        $this->session = "";
        $this->user = "";
    }

    /**
     * Create POST data string out of connection data
     * @param bool $secured if password has to be returned "hidden"
     * @return string POST data string
     */
    public function getPOSTData($secured = false)
    {
        $params = [];
        if (strlen($this->entity) && isset($this->parameters["entity"])) {
            $params[$this->parameters["entity"]] = $this->entity;
        }
        if (strlen($this->login)) {
            $params[$this->parameters["login"]] = $this->login;
        }
        if (strlen($this->otp) && isset($this->parameters["otp"])) {
            $params[$this->parameters["otp"]] = $this->otp;
        }
        if (strlen($this->pw)) {
            $params[$this->parameters["password"]] = $secured ? "***" : $this->pw;
        }
        if (strlen($this->remoteaddr) && isset($this->parameters["ipfilter"])) {
            $params[$this->parameters["ipfilter"]] = $this->remoteaddr;
        }
        if (strlen($this->session)) {
            $params[$this->parameters["session"]] = $this->session;
        }
        if (strlen($this->user) && isset($this->parameters["subuser"])) {
            $params[$this->parameters["subuser"]] = $this->user;
        }
        return http_build_query($params);
    }

    /**
     * Get API Session ID in use
     * @return string API Session ID
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Get API System Entity in use
     * @return string API System Entity
     */
    public function getSystemEntity()
    {
        return $this->entity;
    }

    /**
     * Set account name to use
     * @param string $value account name
     * @return $this
     */
    public function setLogin($value)
    {
        $this->session = "";
        $this->login = $value;
        return $this;
    }

    /**
     * Set one time password to use
     * @param string $value one time password
     * @return $this
     */
    public function setOTP($value)
    {
        $this->session = "";
        $this->otp = $value;
        return $this;
    }

    /**
     * Set account password to use
     * @param string $value account password
     * @return $this
     */
    public function setPassword($value)
    {
        $this->session = "";
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

    /**
     * Set API Session ID to use
     * @param string $value API Session ID
     * @return $this
     */
    public function setSession($value)
    {
        $this->session = $value;
        $this->login = "";
        $this->pw = "";
        $this->otp = "";
        return $this;
    }

    /**
     * Set API System Entity to use
     * This is set to 54cd / LIVE System by default
     * @param string $value API System Entity
     * @return $this
     */
    public function setSystemEntity($value)
    {
        $this->entity = $value;
        return $this;
    }

    /**
     * Set subuser account name (for subuser data view)
     * @param string $value subuser account name
     * @return $this
     */
    public function setUser($value)
    {
        $this->user = $value;
        return $this;
    }
}
