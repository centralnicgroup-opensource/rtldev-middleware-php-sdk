<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
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
     * Command parameter keys whose values carry sensitive data (account
     * password, domain authorization code, ...) and must be masked in the
     * "secured" POST body used for debug logging. Matching is case-insensitive
     * (see maskSensitiveCommand()), so only the names matter, not their casing.
     * Brand subclasses declare the keys their API uses; this mirrors the
     * corresponding Response::$sensitiveFields set for each brand so the debug
     * mask and the stored-command mask cover the same fields.
     * @var string[]
     */
    protected array $sensitiveFields = [];

    /**
     * Set account name to use
     * @param string $value account name
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
     */
    public function setSession(string $value = ""): static
    {
        return $this;
    }

    /**
     * Add persistent parameter to request (request API session)
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
     * Mask the values of the brand's sensitive command keys (see
     * $sensitiveFields) so command-level secrets — e.g. a domain transfer
     * authorization code — never reach the debug log in cleartext. Matching is
     * case-insensitive to stay robust against casing differences between what a
     * brand documents and what it actually sends. `null` values are left
     * untouched (they are dropped from the request, not logged).
     * @param array<string, string|null> $command API Command to mask
     * @return array<string, string|null>
     */
    protected function maskSensitiveCommand(array $command): array
    {
        $sensitive = array_map(strtolower(...), $this->sensitiveFields);
        foreach ($command as $key => $val) {
            if ($val !== null && in_array(strtolower($key), $sensitive, true)) {
                $command[$key] = "***";
            }
        }
        return $command;
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
