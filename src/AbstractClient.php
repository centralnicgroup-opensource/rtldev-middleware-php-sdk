<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

use CNIC\AbstractSocketConfig;
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
     * Current module version.
     * Kept in sync automatically by semantic-release — see .releaserc.json.
     */
    private const string VERSION = "17.1.0";

    /**
     * context data for the client
     * @var array<string,mixed>
     */
    protected array $context = [];

    /**
     * API connection url
     */
    protected string $socketURL = "";

    /**
     * Object covering API connection data
     */
    protected AbstractSocketConfig $socketConfig;

    /**
     * activity flag for debug mode
     */
    protected bool $debugMode = false;

    /**
     * user agent
     */
    protected string $ua = "";

    /**
     * additional curl options to use
     * @var array<int, mixed>
     */
    protected array $curlopts = [];

    /**
     * logger instance for debug mode
     * @psalm-suppress PropertyNotSetInConstructor — set via abstract setDefaultLogger() called in __construct()
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
     */
    public function __construct()
    {
        $this->transport = new HttpTransport();
        $this->socketConfig = $this->newSocketConfig();
        $this->useLIVESystem();
        $this->setDefaultLogger();
    }

    /**
     * Perform API request using the given command.
     * Each client implements its own command serialisation and response type.
     *
     * Endpoint routing differs by brand and is intentionally not part of this
     * contract. CNR talks to a single fixed endpoint — the full script path
     * (e.g. `/api/call.cgi`) is baked into the configured URL and only the
     * hostname changes between OT&E and LIVE — so CNR needs no per-request path.
     * IBS/Moniker instead expose many endpoints under one host, where the path
     * selects the operation, so {@see \CNIC\IBS\Client::request()} widens this
     * signature with an optional `string $path` supplied per call. That widening
     * is deliberate and accepted by PHPStan (L9) and Psalm (L1); the trade-off is
     * that `$path` is reachable only through the concrete IBS/Moniker type, not
     * through this abstract. Keeping `$path` off the shared contract is by design:
     * CNR must never accept a per-request path, so it is not hoisted here.
     *
     * @param array<string, scalar|scalar[]|null> $cmd API command
     */
    abstract public function request(array $cmd = []): ResponseInterface;

    /**
     * Instantiate the SocketConfig for this client.
     * Subclasses return their own SocketConfig subtype.
     */
    abstract protected function newSocketConfig(): AbstractSocketConfig;

    /**
     * Set the default logger for this client.
     * Subclasses instantiate the appropriate Logger implementation.
     */
    abstract public function setDefaultLogger(): static;

    /**
     * Set custom logger to use instead of the default one.
     * Create your own class implementing \CNIC\LoggerInterface.
     */
    public function setCustomLogger(LoggerInterface $customLogger): static
    {
        $this->logger = $customLogger;
        return $this;
    }

    /**
     * Enable debug output to STDOUT
     */
    public function enableDebugMode(): static
    {
        $this->debugMode = true;
        return $this;
    }

    /**
     * Disable debug output
     */
    public function disableDebugMode(): static
    {
        $this->debugMode = false;
        return $this;
    }

    /**
     * Serialize given command for POST request including connection configuration data
     * @param array<string, string|null> $cmd API command to encode
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
     */
    public function setProxy(string $proxy = ""): static
    {
        if ($proxy === '') {
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
        if (array_key_exists(CURLOPT_PROXY, $this->curlopts) && is_string($this->curlopts[CURLOPT_PROXY])) {
            return $this->curlopts[CURLOPT_PROXY];
        }
        return null;
    }

    /**
     * Set Referer to use for API communication
     * @param string $referer Referer (optional, for reset)
     */
    public function setReferer(string $referer = ""): static
    {
        if ($referer === '') {
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
        if (array_key_exists(CURLOPT_REFERER, $this->curlopts) && is_string($this->curlopts[CURLOPT_REFERER])) {
            return $this->curlopts[CURLOPT_REFERER];
        }
        return null;
    }

    /**
     * Get the current module version
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Set another connection url to be used for API communication
     * @param string $value API connection url to set
     */
    public function setURL(string $value): static
    {
        $this->socketURL = $value;
        return $this;
    }

    /**
     * Set an API session id to be used for API communication
     * @param string $value API session id (optional, for reset)
     */
    public function setSession(string $value = ""): static
    {
        $this->socketConfig->setSession($value);
        return $this;
    }

    /**
     * Set Credentials to be used for API communication
     * @param string $uid account name (optional, for reset)
     * @param string $pw account password (optional, for reset)
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
     */
    public function setRoleCredentials(string $uid = "", string $role = "", string $pw = ""): static
    {
        $login = $uid;
        if ($role !== '') {
            $login .= $this->socketConfig->getRoleSeparator() . $role;
        }
        return $this->setCredentials($login, $pw);
    }

    /**
     * Activate High Performance Setup
     */
    public function useHighPerformanceConnectionSetup(): static
    {
        $parts = parse_url($this->getURL());
        if (isset($parts["host"]) && $parts["host"] !== '') {
            // Route to the co-located high-performance proxy on loopback.
            // The https->http downgrade is deliberate and safe: the request never
            // leaves the host — it targets a trusted local socket — so credentials
            // in the POST body are not exposed on the wire. Rebuild from the URL
            // components so only the host (and scheme) are swapped; a blind
            // str_replace would also clobber a hostname recurring in path/query.
            $url = "http://127.0.0.1"
                . (isset($parts["port"]) ? ":" . $parts["port"] : "")
                . ($parts["path"] ?? "")
                . (isset($parts["query"]) ? "?" . $parts["query"] : "");
            $this->setURL($url);
        }
        return $this;
    }

    /**
     * Convert domain names to idn + punycode if necessary
     * @param array<string> $domains list of domain names (or tlds)
     * @return array<int, array{idn: string|false, punycode: string|false}>
     */
    public function IDNConvert(array $domains): array
    {
        /** @var array<int, array{idn: string|false, punycode: string|false}> $result */
        $result = ConverterFactory::convert($domains);
        return $result;
    }

    /**
     * Auto convert API command parameters to punycode, if necessary.
     * @param array<string, string> $cmd API command
     * @return array<string, string>
     */
    protected function autoIDNConvert(array $cmd): array
    {
        if (
            !$this->socketConfig->getNeedsIDNConvert()
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
                $cmd[$idxs[$idx]] = (string)$row["punycode"];
            }
        }
        return $cmd;
    }

    /**
     * Delegate cURL execution to the transport layer.
     * @param string $data serialized POST payload
     * @param array{CONNECTION_URL: string} $cfg connection config
     * @param array<int, mixed> $extraCurlOpts additional cURL options merged over the defaults
     * @return array{0: string, 1: string|null} [rawResponse, errorMessage|null]
     */
    protected function executeCurl(string $data, array $cfg, array $extraCurlOpts = []): array
    {
        return $this->transport->post(
            $cfg["CONNECTION_URL"],
            $data,
            $this->socketConfig->getSocketTimeout(),
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
     * Get LIVE system URL
     */
    public function getLiveUrl(): string
    {
        return $this->socketConfig->getLiveUrl();
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
     */
    public function useOTESystem(): static
    {
        $this->isOTE = true;
        return $this->setURL($this->socketConfig->getOTEUrl());
    }

    /**
     * Set LIVE System for API communication (this is the default setting)
     */
    public function useLIVESystem(): static
    {
        $this->isOTE = false;
        return $this->setURL($this->socketConfig->getLiveUrl());
    }

    /**
     * Set context data for the client
     * @param array<string,mixed> $context
     */
    public function setContext(array $context): static
    {
        $this->context = $context;
        return $this;
    }
}
