<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © CentralNic Group PLC
 */

namespace CNIC;

use CNIC\CNR\SocketConfig;
use CNIC\IDNA\Factory\ConverterFactory;
use CNIC\LoggerInterface;
use CNIC\ResponseInterface;

/**
 * Shared foundation for all registrar API clients.
 * Concrete subclasses provide the request() implementation, the default
 * logger, and the appropriate SocketConfig subtype.
 *
 * @psalm-api
 * @package CNIC
 */
abstract class AbstractClient
{
    /**
     * context data for the client
     * @var array<string,mixed>
     */
    protected array $context = [];

    /**
     * registrar api settings
     * @var array<mixed>
     */
    protected array $settings;

    /**
     * API connection url
     */
    protected string $socketURL;

    /**
     * Object covering API connection data
     */
    protected SocketConfig $socketConfig;

    /**
     * activity flag for debug mode
     */
    protected bool $debugMode;

    /**
     * user agent
     */
    protected string $ua = '';

    /**
     * additional curl options to use
     * @var array<int, mixed>
     */
    protected array $curlopts = [];

    /**
     * logger instance for debug mode
     */
    protected LoggerInterface $logger;

    /**
     * is connected to OT&E
     */
    protected bool $isOTE = false;

    /**
     * HTTP transport layer
     */
    protected HttpTransport $transport;

    /**
     * Constructor
     *
     * @param string $path Path to the configuration file
     */
    public function __construct(string $path = "")
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            $contents = "";
        }
        $settings = json_decode($contents, true);
        if (is_null($settings) || $settings === false || $settings === true) {
            $settings = [];
        }
        $this->settings = $settings;
        $this->socketURL = "";
        $this->debugMode = false;
        $this->ua = "";
        $this->transport = new HttpTransport();
        $this->socketConfig = $this->newSocketConfig();
        $this->useLIVESystem();
        $this->setDefaultLogger();
    }

    /**
     * Perform API request using the given command.
     * Each client implements its own command serialisation and response type.
     * @param array<mixed> $cmd API command
     */
    abstract public function request(array $cmd = []): ResponseInterface;

    /**
     * Instantiate the SocketConfig for this client.
     * Subclasses return their own SocketConfig subtype.
     */
    abstract protected function newSocketConfig(): SocketConfig;

    /**
     * Set the default logger for this client.
     * Subclasses instantiate the appropriate Logger implementation.
     * @return $this
     */
    abstract public function setDefaultLogger(): static;

    /**
     * Set custom logger to use instead of the default one.
     * Create your own class implementing \CNIC\LoggerInterface.
     * @return $this
     */
    public function setCustomLogger(LoggerInterface $customLogger): static
    {
        $this->logger = $customLogger;
        return $this;
    }

    /**
     * Enable debug output to STDOUT
     * @return $this
     */
    public function enableDebugMode(): static
    {
        $this->debugMode = true;
        return $this;
    }

    /**
     * Disable debug output
     * @return $this
     */
    public function disableDebugMode(): static
    {
        $this->debugMode = false;
        return $this;
    }

    /**
     * Serialize given command for POST request including connection configuration data
     * @param array<string,mixed> $cmd API command to encode
     * @param bool $secured secure password (when used for output)
     */
    public function getPOSTData(array $cmd, bool $secured = false): string
    {
        return $this->socketConfig->getPOSTData($cmd, $secured);
    }

    /**
     * Get the API Session ID that is currently set
     */
    public function getSession(): ?string
    {
        $sessid = $this->socketConfig->getSession();
        return ($sessid === "" ? null : $sessid);
    }

    /**
     * Get the API connection url that is currently set
     */
    public function getURL(): string
    {
        return $this->socketURL;
    }

    /**
     * Set a custom user agent (for platforms that use this SDK)
     * @param string $str user agent label
     * @param string $rv user agent revision
     * @param array<string> $modules further modules to add to user agent string
     * @return $this
     */
    public function setUserAgent(string $str, string $rv, array $modules = []): static
    {
        $mods = $modules === [] ? "" : " " . implode(" ", $modules);
        $this->ua = $str . " (" . PHP_OS . "; " . php_uname("m") . "; rv:" . $rv . ")" . $mods . " php-sdk/" . $this->getVersion() . " php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
        return $this;
    }

    /**
     * Get the user agent string
     */
    public function getUserAgent(): string
    {
        if ($this->ua === '') {
            $this->ua = "PHP-SDK (" . PHP_OS . "; " . php_uname("m") . "; rv:" . $this->getVersion() . ") php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
        }
        return $this->ua;
    }

    /**
     * Set proxy to use for API communication
     * @param string $proxy proxy to use (optional, for reset)
     * @return $this
     */
    public function setProxy(string $proxy = ""): static
    {
        if ($proxy === '' || $proxy === '0') {
            unset($this->curlopts[CURLOPT_PROXY]);
        } else {
            $this->curlopts[CURLOPT_PROXY] = $proxy;
        }
        return $this;
    }

    /**
     * Get proxy configuration for API communication
     */
    public function getProxy(): ?string
    {
        if (isset($this->curlopts[CURLOPT_PROXY])) {
            return $this->curlopts[CURLOPT_PROXY];
        }
        return null;
    }

    /**
     * Set Referer to use for API communication
     * @param string $referer Referer (optional, for reset)
     * @return $this
     */
    public function setReferer(string $referer = ""): static
    {
        if ($referer === '' || $referer === '0') {
            unset($this->curlopts[CURLOPT_REFERER]);
        } else {
            $this->curlopts[CURLOPT_REFERER] = $referer;
        }
        return $this;
    }

    /**
     * Get Referer configuration for API communication
     */
    public function getReferer(): ?string
    {
        if (isset($this->curlopts[CURLOPT_REFERER])) {
            return $this->curlopts[CURLOPT_REFERER];
        }
        return null;
    }

    /**
     * Get the current module version
     */
    public function getVersion(): string
    {
        return "14.1.2";
    }

    /**
     * Set another connection url to be used for API communication
     * @param string $value API connection url to set
     * @return $this
     */
    public function setURL(string $value): static
    {
        $this->socketURL = $value;
        return $this;
    }

    /**
     * Set an API session id to be used for API communication
     * @param string $value API session id (optional, for reset)
     * @return $this
     */
    public function setSession(string $value = ""): static
    {
        $this->socketConfig->setSession($value);
        return $this;
    }

    /**
     * Set a Remote IP Address to be used for API communication
     * @param string $value Remote IP Address (optional, for reset)
     * @throws \Exception in case this feature is unsupported
     * @return $this
     */
    public function setRemoteIPAddress(string $value = ""): static
    {
        if ($value !== '' && $value !== '0' && !isset($this->settings["parameters"]["ipfilter"])) {
            throw new \Exception("Feature `IP Filter` not supported");
        }
        $this->socketConfig->setRemoteAddress($value);
        return $this;
    }

    /**
     * Set Credentials to be used for API communication
     * @param string $uid account name (optional, for reset)
     * @param string $pw account password (optional, for reset)
     * @return $this
     */
    public function setCredentials(string $uid = "", string $pw = ""): static
    {
        $this->socketConfig->setLogin($uid);
        $this->socketConfig->setPassword($pw);
        return $this;
    }

    /**
     * Set Role Credentials to be used for API communication
     * @param string $uid account name (optional, for reset)
     * @param string $role role user id (optional, for reset)
     * @param string $pw role user password (optional, for reset)
     * @return $this
     */
    public function setRoleCredentials(string $uid = "", string $role = "", string $pw = ""): static
    {
        $login = $uid;
        if ($role !== '' && $role !== '0') {
            $login .= $this->settings["roleSeparator"] . $role;
        }
        return $this->setCredentials($login, $pw);
    }

    /**
     * Set a data view to a given subuser
     * @param string $uid subuser account name
     * @return $this
     */
    public function setUserView(string $uid = ""): static
    {
        $this->socketConfig->setUser($uid);
        return $this;
    }

    /**
     * Activate High Performance Setup
     * @return $this
     */
    public function useHighPerformanceConnectionSetup(): static
    {
        $oldurl = $this->getURL();
        $hostname = parse_url($oldurl, PHP_URL_HOST);
        if (is_string($hostname) && $hostname !== '') {
            $url = str_replace($hostname, "127.0.0.1", $oldurl);
            $url = str_replace("https://", "http://", $url);
            $this->setURL($url);
        }
        return $this;
    }

    /**
     * Convert domain names to idn + punycode if necessary
     * @param array<string> $domains list of domain names (or tlds)
     * @return array<mixed>
     */
    public function IDNConvert(array $domains): array
    {
        return ConverterFactory::convert($domains);
    }

    /**
     * Auto convert API command parameters to punycode, if necessary.
     * @param array<string> $cmd API command
     * @return array<string>
     */
    protected function autoIDNConvert(array $cmd): array
    {
        if (
            !$this->settings["needsIDNConvert"]
            || !function_exists("idn_to_ascii")
        ) {
            return $cmd;
        }

        $asciipattern = "/^[a-zA-Z0-9\.-]+$/i";
        // DOMAIN params get auto-converted by API
        // RSRBE-7149 for NS coverage
        $keypattern = "/^(PARENTDOMAIN|NAMESERVER|NS|DNSZONE)([0-9]*)$/i";
        $objclasspattern = "/^(DOMAIN(APPLICATION|BLOCKING)?|NAMESERVER|NS|DNSZONE)$/i";
        $toconvert = [];
        $idxs = [];
        foreach ($cmd as $key => $val) {
            if (
                ((bool)preg_match($keypattern, $key)
                    // RSRTPM-3167: OBJECTID is a PATTERN in CNR API and not supporting IDNs
                    || ($key === "OBJECTID"
                        && isset($cmd["OBJECTCLASS"])
                        && (bool)preg_match($objclasspattern, $cmd["OBJECTCLASS"])
                    )
                )
                && !(bool)preg_match($asciipattern, $val)
            ) {
                $toconvert[] = $val;
                $idxs[] = $key;
            }
        }
        if ($toconvert !== []) {
            $results = $this->IDNConvert($toconvert);
            foreach ($results as $idx => $row) {
                $cmd[$idxs[$idx]] = $row["punycode"];
            }
        }
        return $cmd;
    }

    /**
     * Delegate cURL execution to the transport layer.
     * @param string $data serialized POST payload
     * @param array<string> $cfg connection config (must contain CONNECTION_URL)
     * @param array<int, mixed> $extraCurlOpts additional cURL options merged over the defaults
     * @return array{0: string, 1: string|null} [rawResponse, errorMessage|null]
     */
    protected function executeCurl(string $data, array $cfg, array $extraCurlOpts = []): array
    {
        return $this->transport->post(
            $cfg["CONNECTION_URL"],
            $data,
            $this->settings["socketTimeout"],
            $this->getUserAgent(),
            $extraCurlOpts + $this->curlopts
        );
    }

    /**
     * Close all cURL connections
     */
    public function close(): void
    {
        $this->transport->close();
    }

    /**
     * Get registrar API settings
     * @return array<mixed>
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Check whether the client is connected to the OT&E system
     */
    public function isOTE(): bool
    {
        return $this->isOTE;
    }

    /**
     * Set OT&E System for API communication
     * @return $this
     */
    public function useOTESystem(): static
    {
        $this->isOTE = true;
        return $this->setURL($this->settings["env"]["ote"]["url"]);
    }

    /**
     * Set LIVE System for API communication (this is the default setting)
     * @return $this
     */
    public function useLIVESystem(): static
    {
        $this->isOTE = false;
        return $this->setURL($this->settings["env"]["live"]["url"]);
    }

    /**
     * Set context data for the client
     * @param array<string,mixed> $context
     * @return $this
     */
    public function setContext(array $context): static
    {
        $this->context = $context;
        return $this;
    }
}
