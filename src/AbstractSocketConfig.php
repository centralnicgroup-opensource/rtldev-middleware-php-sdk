<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © CentralNic Group PLC
 */

namespace CNIC;

/**
 * Shared base for all registrar SocketConfig implementations.
 * Concrete subclasses provide getPOSTDataParams() and their own
 * $parameters array shaped to the API they target.
 *
 * @package CNIC
 */
abstract class AbstractSocketConfig
{
    /**
     * account name
     */
    protected string $login = "";

    /**
     * account password
     */
    protected string $pw = "";

    /**
     * API OT&E endpoint URL
     */
    protected string $oteUrl = "";

    /**
     * API LIVE endpoint URL
     */
    protected string $liveUrl = "";

    /**
     * API socket timeout in seconds
     */
    protected int $socketTimeout = 300;

    /**
     * Whether API command values need IDN conversion
     */
    protected bool $needsIDNConvert = false;

    /**
     * Separator character for role credentials
     */
    protected string $roleSeparator = "";

    /**
     * Set account name to use
     * @param string $value account name
     * @return $this
     */
    public function setLogin(string $value): static
    {
        $this->login = $value;
        return $this;
    }

    /**
     * Get current login
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
    public function setPassword(string $value): static
    {
        $this->pw = $value;
        return $this;
    }

    /**
     * Get API Session ID in use
     */
    public function getSession(): string
    {
        return "";
    }

    /**
     * Set API Session ID to use
     * @param string $value API Session ID
     * @return $this
     */
    public function setSession(string $value = ""): static
    {
        return $this;
    }

    /**
     * Add persistent parameter to request (request API session)
     * @return $this
     */
    public function setPersistent(bool $value = false): static
    {
        return $this;
    }

    /**
     * Get persistent parameter
     */
    public function getPersistent(): bool
    {
        return false;
    }

    /**
     * Get OT&E endpoint URL
     */
    public function getOTEUrl(): string
    {
        return $this->oteUrl;
    }

    /**
     * Get LIVE endpoint URL
     */
    public function getLiveUrl(): string
    {
        return $this->liveUrl;
    }

    /**
     * Get socket timeout in seconds
     */
    public function getSocketTimeout(): int
    {
        return $this->socketTimeout;
    }

    /**
     * Get whether IDN conversion is needed
     */
    public function getNeedsIDNConvert(): bool
    {
        return $this->needsIDNConvert;
    }

    /**
     * Get role separator character
     */
    public function getRoleSeparator(): string
    {
        return $this->roleSeparator;
    }

    /**
     * Get POST data container of connection data
     * @param array<string, string|null> $command API Command to request
     * @param bool $secured if password has to be returned "hidden"
     * @return array<string, string|null>
     */
    abstract protected function getPOSTDataParams(array $command, bool $secured): array;

    /**
     * Create POST data string out of connection data
     * @param array<string, string|null> $command API Command to request
     * @param bool $secured if password has to be returned "hidden"
     * @return string POST data string
     */
    public function getPOSTData(array $command = [], bool $secured = false): string
    {
        $params = $this->getPOSTDataParams($command, $secured);
        if ($this->getPersistent()) {
            $params["persistent"] = "1";
        }
        return http_build_query($params);
    }
}
