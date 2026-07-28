<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

use CNIC\AbstractSocketConfig;
use CNIC\Exception\InvalidConfigurationException;
use CNIC\Exception\UnsupportedFeatureException;
use CNIC\IDNA\Factory\ConverterFactory;
use CNIC\LoggerInterface;
use CNIC\ResponseInterface;
use CNIC\System;

/**
 * Shared foundation for all registrar API clients.
 * Concrete subclasses provide the request() implementation, the default
 * logger, and the appropriate SocketConfig subtype.
 *
 * ## Where configuration lives (RSRMID-2921)
 *
 * Not here. Connection configuration has one home — {@see AbstractSocketConfig}
 * — reachable through {@see getSocketConfig()}. This class used to keep its own
 * copies of part of it (`$socketURL`, `$system`, the `$curlopts` bag) alongside
 * the config's endpoints and timeout, with nothing tying the two sets together;
 * three defects followed from the split, and the full account is in the config's
 * class docblock. What is left here is client *behaviour*: the logger and debug
 * flag, the response context, the transport instance, and the SDK's own identity
 * (`VERSION`/`$ua`, which is versioned with this class and released from it).
 *
 * The configuration methods below are forwarders, and deliberately kept: they are
 * the documented ergonomic surface (`$cl->useOTESystem()->setCredentials(...)`)
 * and they now read and write the config's state rather than a copy of it, so a
 * forwarder cannot disagree with the config. What `getSocketConfig()` changes is
 * that a *new* setting no longer needs one — the accessor's absence is why ~26 of
 * these accumulated and why this was the repo's highest-churn file.
 *
 * Only capabilities every brand can actually honour live here. In particular
 * `getSession()`/`setSession()` do **not** — API sessions are a CNR concept, and
 * these sat here until v22 forwarding to null-object stubs on
 * {@see AbstractSocketConfig}, so `setSession()` on an IBS/Moniker client looked
 * accepted and was discarded. They now live on {@see \CNIC\CNR\Client} beside the
 * state they read. Do not hoist them (or role credentials, see
 * {@see \CNIC\RoleCredentialsInterface}) back up: a capability a brand cannot
 * honour belongs off the shared surface, not on it returning a constant.
 * (Ref: RSRMID-2920, reversing the RSRMID-2911 sub-decision that kept them here.)
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
    private const string VERSION = "22.0.0";

    /**
     * context data for the client
     * @var array<string,mixed>
     */
    protected array $context = [];

    /**
     * Object covering API connection data — the one home for connection
     * configuration; see {@see getSocketConfig()}.
     */
    protected AbstractSocketConfig $socketConfig;

    /**
     * activity flag for debug mode
     */
    protected bool $debugMode = false;

    /**
     * User agent sent with every request. Empty until {@see setUserAgent()} is
     * called; while it is empty {@see getUserAgent()} derives the SDK default.
     *
     * Client state rather than connection configuration: it identifies this
     * library (and the platform embedding it) and is built from {@see VERSION},
     * which semantic-release rewrites in this file.
     */
    protected string $ua = "";

    /**
     * logger instance for debug mode
     * @psalm-suppress PropertyNotSetInConstructor — set via abstract setDefaultLogger() called in __construct()
     */
    protected LoggerInterface $logger;

    /**
     * HTTP transport layer
     */
    protected TransportInterface $transport;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->transport = $this->newTransport();
        // Seeds itself: the config's constructor selects LIVE (the default
        // system) and the brand's default cURL options. The client used to call
        // useLIVESystem() here to initialise its own URL copy — there is no copy
        // to initialise any more.
        $this->socketConfig = $this->newSocketConfig();
        $this->setDefaultLogger();
    }

    /**
     * The connection configuration this client uses.
     *
     * Added in RSRMID-2921 as the missing piece that made ~26 forwarding methods
     * the *only* way to reach a configuration value, and this the highest-churn
     * file in the repo: every new setting needed a hand-written forwarder or was
     * unreachable from outside. It is also the seam that lets configuration be
     * built and asserted without constructing a client.
     *
     * Brands narrow the return type covariantly where they have their own config
     * capabilities — {@see \CNIC\CNR\Client::getSocketConfig()} returns the CNR
     * config, which is the one place the invariant property type is narrowed.
     */
    public function getSocketConfig(): AbstractSocketConfig
    {
        return $this->socketConfig;
    }

    /**
     * Perform API request using the given command.
     *
     * The shared request lifecycle lives in {@see performRequest()}; each brand's
     * public `request()` is a thin wrapper that pins its default `$path` and
     * declares a concrete Response return type. Every brand accepts an optional
     * `$path` appended to the configured base URL to select the endpoint: for
     * IBS/Moniker the path selects the operation (e.g. `Domain/Create`); for CNR
     * it defaults to the single fixed script path (`api/call.cgi`) and rarely
     * varies. The signature is symmetric across all brands.
     *
     * @param array<string, scalar|scalar[]|null> $cmd API command
     * @param string $path path segment appended to the base URL to select the endpoint
     */
    abstract public function request(array $cmd = [], string $path = ""): ResponseInterface;

    /**
     * Shared request lifecycle (template method). Brands vary it through exactly
     * two hooks — {@see buildCommand()} (command flattening) and
     * {@see newResponse()} (covariant Response factory) — plus their
     * {@see newSocketConfig()} subtype, which is where any brand-mandatory cURL
     * option would go now that the bag lives on the config (RSRMID-2921). No
     * brand declares one.
     *
     * @param array<string, scalar|scalar[]|null> $cmd API command
     * @param string $path path segment appended to the base URL to select the endpoint
     */
    protected function performRequest(array $cmd, string $path = ""): ResponseInterface
    {
        $mycmd = $this->autoIDNConvert($this->buildCommand($cmd));
        $cfg = ["CONNECTION_URL" => $this->socketConfig->getURL() . $path];
        $data = $this->getPOSTData($mycmd);
        [$raw, $error] = $this->executeCurl($data, $cfg);
        $response = $this->newResponse($raw, $mycmd, $cfg);
        if ($this->debugMode) {
            $this->logger->log($this->getPOSTData($mycmd, true), $response, $error);
        }
        return $response;
    }

    /**
     * Flatten and normalise the given command into wire form.
     * Brand-specific: CNR flattens as-is; IBS injects `ResponseFormat=JSON`.
     *
     * @param array<string, scalar|scalar[]|null> $cmd API command
     * @return array<string, string>
     */
    abstract protected function buildCommand(array $cmd): array;

    /**
     * Instantiate the brand Response for the given raw payload.
     * Return type is covariant so each brand pins its concrete Response.
     *
     * @param string $raw raw API response payload
     * @param array<string, string> $cmd flattened command that produced the response
     * @param array{CONNECTION_URL: string} $cfg connection config used for the request
     */
    abstract protected function newResponse(string $raw, array $cmd, array $cfg): ResponseInterface;

    /**
     * Instantiate the SocketConfig for this client.
     * Subclasses return their own SocketConfig subtype.
     */
    abstract protected function newSocketConfig(): AbstractSocketConfig;

    /**
     * Instantiate the HTTP transport for this client. Mirrors
     * {@see newSocketConfig()} — the default is the production cURL transport;
     * override or {@see setTransport()} to inject a test double so the
     * request() lifecycle can run offline.
     */
    protected function newTransport(): TransportInterface
    {
        return new HttpTransport();
    }

    /**
     * Inject a custom HTTP transport (e.g. a record/replay cassette transport
     * for offline tests) in place of the default {@see HttpTransport}.
     * @psalm-api
     */
    public function setTransport(TransportInterface $transport): static
    {
        $this->transport = $transport;
        return $this;
    }

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
     * Get the API connection url that is currently set
     */
    public function getURL(): string
    {
        return $this->socketConfig->getURL();
    }

    /**
     * Set the request timeout in seconds (default 300).
     *
     * The **only** way to change the timeout. Added in RSRMID-2919 to close a gap
     * — the value was unreachable from outside the SDK — and made the single
     * route in RSRMID-2921, which stopped the option bag offering a second one:
     * `CURLOPT_TIMEOUT` there is now rejected ({@see setExtraCurlOptions()})
     * rather than quietly overriding what {@see getSocketTimeout()} reports.
     *
     * @param int $seconds timeout in seconds (0 = no timeout, per cURL)
     * @throws InvalidConfigurationException on a negative value
     */
    public function setSocketTimeout(int $seconds): static
    {
        $this->socketConfig->setSocketTimeout($seconds);
        return $this;
    }

    /**
     * Get the request timeout in seconds currently configured.
     */
    public function getSocketTimeout(): int
    {
        return $this->socketConfig->getSocketTimeout();
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
     * Get the user agent string — the one set via {@see setUserAgent()}, or the
     * SDK default when none was.
     *
     * A pure read. It used to memoise the default into {@see $ua} on first call,
     * which meant a getter performing a write in the middle of a request; there
     * is nothing to memoise — the value is a handful of constants and one
     * `php_uname()` call.
     */
    public function getUserAgent(): string
    {
        if ($this->ua !== '') {
            return $this->ua;
        }
        return "PHP-SDK (" . PHP_OS . "; " . php_uname("m") . "; rv:" . $this->getVersion() . ") php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
    }

    /**
     * Merge additional cURL options into the bag, overriding existing values on
     * key collision. Forwards to {@see AbstractSocketConfig::setExtraCurlOptions()},
     * whose docblock carries the detail: what reaches the wire, and the two sets
     * of keys that are refused — the SDK-managed settings
     * ({@see AbstractSocketConfig::MANAGED_OPTIONS}, rejected here and now) and
     * the transport's own ({@see HttpTransport::PROTECTED_OPTIONS}, rejected on
     * the next request).
     * @param array<int, mixed> $opts cURL options keyed by CURLOPT_* constant
     * @throws UnsupportedFeatureException if $opts contains an SDK-managed option
     */
    public function setExtraCurlOptions(array $opts): static
    {
        $this->socketConfig->setExtraCurlOptions($opts);
        return $this;
    }

    /**
     * Restore the cURL option bag to the brand defaults, discarding anything
     * previously handed to {@see setExtraCurlOptions()}. Options only — the proxy
     * and referer are separate state and survive; see
     * {@see AbstractSocketConfig::resetCurlOptions()}.
     */
    public function resetCurlOptions(): static
    {
        $this->socketConfig->resetCurlOptions();
        return $this;
    }

    /**
     * Set proxy to use for API communication
     * @param string $proxy proxy to use (optional, for reset)
     */
    public function setProxy(string $proxy = ""): static
    {
        $this->socketConfig->setProxy($proxy);
        return $this;
    }

    /**
     * Get proxy configuration for API communication
     */
    public function getProxy(): ?string
    {
        return $this->socketConfig->getProxy();
    }

    /**
     * Set Referer to use for API communication
     * @param string $referer Referer (optional, for reset)
     */
    public function setReferer(string $referer = ""): static
    {
        $this->socketConfig->setReferer($referer);
        return $this;
    }

    /**
     * Get Referer configuration for API communication
     */
    public function getReferer(): ?string
    {
        return $this->socketConfig->getReferer();
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
        $this->socketConfig->setURL($value);
        return $this;
    }

    /**
     * Set Credentials to be used for API communication.
     *
     * On CNR this **discards any active API session**, which was true before
     * RSRMID-2921 too but stated nowhere: `CNR\SocketConfig::setLogin()`/
     * `setPassword()` clear the session id, because a session and a password are
     * alternative credentials on the wire and the newer one is authoritative. The
     * invariant is deliberate — `CNR\SessionCapable::reuseSession()` depends on
     * it, restoring the login first and the session second — so it is documented
     * rather than removed. Set the session *after* the credentials, never before.
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
     * Activate High Performance Setup — route requests through the co-located
     * proxy on loopback.
     *
     * Brand-agnostic and therefore shared (RSRMID-2911). Since RSRMID-2921 it
     * records a flag on the config instead of rewriting the URL once, so the
     * selected system survives it: {@see isOTE()} used to flip to false here,
     * because the rewritten loopback URL no longer matched the OT&E endpoint. See
     * {@see AbstractSocketConfig::useHighPerformanceConnectionSetup()}.
     */
    public function useHighPerformanceConnectionSetup(): static
    {
        $this->socketConfig->useHighPerformanceConnectionSetup();
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
     *
     * The configured options are handed over as they are, and beat the
     * transport's own defaults in {@see HttpTransport::post()} (PHP's `+` keeps
     * the left operand on a duplicate key).
     *
     * It used to take a third `$extraCurlOpts` argument merged in *over* the
     * configured options. Nothing in the SDK ever passed it, and as a route into
     * the option set that skipped {@see AbstractSocketConfig::MANAGED_OPTIONS} it
     * was a way for a subclass to put a second answer behind `getProxy()` — the
     * defect this ticket exists to close. Dropped in RSRMID-2921: a per-request
     * option belongs on the config before the request, or on a transport the
     * caller drives themselves.
     * @param string $data serialized POST payload
     * @param array{CONNECTION_URL: string} $cfg connection config
     * @return array{0: string, 1: string|null} [rawResponse, errorMessage|null]
     * @throws UnsupportedFeatureException if a transport-owned option was set
     */
    protected function executeCurl(string $data, array $cfg): array
    {
        return $this->transport->post(
            $cfg["CONNECTION_URL"],
            $data,
            $this->socketConfig->getSocketTimeout(),
            $this->getUserAgent(),
            $this->socketConfig->getCurlOptions()
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
     * Get LIVE system URL.
     *
     * There is deliberately no matching `getOTEUrl()` here. The asymmetry is
     * pre-existing — this forwarder is the one the SDK has always had, and it is
     * half of what made drift 2 visible — and RSRMID-2921 did not add its twin: a
     * change whose point is that a configuration value no longer needs a
     * hand-written forwarder should not open with a new one. Read the OT&E
     * endpoint from {@see getSocketConfig()}.
     */
    public function getLiveUrl(): string
    {
        return $this->socketConfig->getLiveUrl();
    }

    /**
     * Get the API system in use, or null when the configured URL is neither of
     * the brand's two known endpoints.
     *
     * Derived from the URL rather than stored beside it (RSRMID-2921), which is
     * what makes it impossible for the two to disagree — and why the return type
     * is nullable: after a `setURL()` to some other host there is no honest
     * OT&E-or-LIVE answer to give. See
     * {@see AbstractSocketConfig::getSystem()}.
     */
    public function getSystem(): ?System
    {
        return $this->socketConfig->getSystem();
    }

    /**
     * Check whether the OT&E system is in use
     */
    public function isOTE(): bool
    {
        return $this->socketConfig->isOTE();
    }

    /**
     * Set OT&E System for API communication
     */
    public function useOTESystem(): static
    {
        $this->socketConfig->useOTESystem();
        return $this;
    }

    /**
     * Set LIVE System for API communication (this is the default setting)
     */
    public function useLIVESystem(): static
    {
        $this->socketConfig->useLIVESystem();
        return $this;
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
