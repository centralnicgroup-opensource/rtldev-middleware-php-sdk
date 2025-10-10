<?php

#declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\CNR;

use CNIC\CommandFormatter;
use CNIC\CNR\Logger as L;
use CNIC\CNR\SocketConfig;
use CNIC\CNR\Response;
use CNIC\IDNA\Factory\ConverterFactory;

/**
 * CNR API Client
 *
 * @package CNIC\CNR
 */
class Client
{
    /**
     * registrar api settings
     * @var array<mixed>
     */
    public $settings;

    /**
     * API connection url
     * @var string
     */
    protected $socketURL;

    /**
     * Object covering API connection data
     * @var SocketConfig
     */
    protected $socketConfig;

    /**
     * activity flag for debug mode
     * @var boolean
     */
    protected $debugMode;

    /**
     * user agent
     * @var string
     */
    protected $ua;

    /**
     * additional curl options to use
     * @var array<string>
     */
    protected $curlopts = [];

    /**
     * logger function name for debug mode
     * @var \CNIC\LoggerInterface
     */
    protected $logger;

    /**
     * is connected to OT&E
     * @var bool
     */
    public $isOTE = false;

    /**
     * curl handle cache
     * @var \CurlHandle|null
     */
    protected $chandle = null;

    /**
     * Constructor
     *
     * @param string $path Path to the configuration file
     */
    public function __construct($path = "")
    {
        $contents = file_get_contents($path) ?: "";
        $settings = json_decode($contents, true);
        if (is_null($settings) || $settings === false || $settings === true) {
            $settings = [];
        }
        $this->settings = $settings;
        $this->socketURL = "";
        $this->debugMode = false;
        $this->ua = "";
        $this->socketConfig = new SocketConfig($this->settings["parameters"] ?? []);
        $this->useLIVESystem();
        $this->setDefaultLogger();
    }

    /**
     * set custom logger to use instead of default one
     * create your own class implementing \CNIC\LoggerInterface
     * @param \CNIC\LoggerInterface $customLogger
     * @return $this
     */
    public function setCustomLogger($customLogger)
    {
        $this->logger = $customLogger;
        return $this;
    }

    /**
     * set default logger to use
     * @return $this
     */
    public function setDefaultLogger()
    {
        $this->logger = new L();
        return $this;
    }

    /**
     * Enable Debug Output to STDOUT
     * @return $this
     */
    public function enableDebugMode()
    {
        $this->debugMode = true;
        return $this;
    }

    /**
     * Disable Debug Output
     * @return $this
     */
    public function disableDebugMode()
    {
        $this->debugMode = false;
        return $this;
    }

    /**
     * Serialize given command for POST request including connection configuration data
     * @param string|array<string,mixed> $cmd API command to encode
     * @param bool $secured secure password (when used for output)
     * @return string
     */
    public function getPOSTData($cmd, $secured = false)
    {
        if (is_string($cmd)) {
            $command = [];
            parse_str($cmd, $command);
        } else {
            $command = $cmd;
        }
        return $this->socketConfig->getPOSTData($command, $secured);
    }

    /**
     * Get the API Session ID that is currently set
     * @return string|null
     */
    public function getSession()
    {
        $sessid = $this->socketConfig->getSession();
        return ($sessid === "" ? null : $sessid);
    }

    /**
     * Get the API connection url that is currently set
     * @return string
     */
    public function getURL()
    {
        return $this->socketURL;
    }

    /**
     * Set a custom user agent (for platforms that use this SDK)
     * @param string $str user agent label
     * @param string $rv user agent revision
     * @param array<string> $modules further modules to add to user agent string, format: ["<module1>/<version>", "<module2>/<version>", ... ]
     * @return $this
     */
    public function setUserAgent($str, $rv, $modules = [])
    {
        $mods = empty($modules) ? "" : " " . implode(" ", $modules);
        $this->ua = ($str . " (" . PHP_OS . "; " . php_uname("m") . "; rv:" . $rv . ")" . $mods . " php-sdk/" . $this->getVersion() . " php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION])
        );
        return $this;
    }

    /**
     * Get the user agent string
     * @return string
     */
    public function getUserAgent()
    {
        if (!strlen($this->ua)) {
            $this->ua = "PHP-SDK (" . PHP_OS . "; " . php_uname("m") . "; rv:" . $this->getVersion() . ") php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
        }
        return $this->ua;
    }

    /**
     * Set proxy to use for API communication
     * @param string $proxy proxy to use (optional, for reset)
     * @return $this
     */
    public function setProxy($proxy = "")
    {
        if (empty($proxy)) {
            unset($this->curlopts[CURLOPT_PROXY]);
        } else {
            $this->curlopts[CURLOPT_PROXY] = $proxy;
        }
        return $this;
    }

    /**
     * Get proxy configuration for API communication
     * @return string|null
     */
    public function getProxy()
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
    public function setReferer($referer = "")
    {
        if (empty($referer)) {
            unset($this->curlopts[CURLOPT_REFERER]);
        } else {
            $this->curlopts[CURLOPT_REFERER] = $referer;
        }
        return $this;
    }

    /**
     * Get Referer configuration for API communication
     * @return string|null
     */
    public function getReferer()
    {
        if (isset($this->curlopts[CURLOPT_REFERER])) {
            return $this->curlopts[CURLOPT_REFERER];
        }
        return null;
    }

    /**
     * Get the current module version
     * @return string
     */
    public function getVersion()
    {
        return "12.0.1";
    }

    /**
     * Set another connection url to be used for API communication
     * @param string $value API connection url to set
     * @return $this
     */
    public function setURL($value)
    {
        $this->socketURL = $value;
        return $this;
    }

    /**
     * Set an API session id to be used for API communication
     * @param string $value API session id (optional, for reset)
     * @return $this
     */
    public function setSession($value = "")
    {
        $this->socketConfig->setSession($value);
        return $this;
    }

    /**
     * Set an Remote IP Address to be used for API communication
     * To be used in case you have an active ip filter setting.
     * @param string $value Remote IP Address (optional, for reset)
     * @throws \Exception in case this feature is unsupported
     * @return $this
     */
    public function setRemoteIPAddress($value = "")
    {
        if (!empty($value) && !isset($this->settings["parameters"]["ipfilter"])) {
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
    public function setCredentials($uid = "", $pw = "")
    {
        $this->socketConfig->setLogin($uid);
        $this->socketConfig->setPassword($pw);
        return $this;
    }

    /**
     * Set Credentials to be used for API communication
     * @param string $uid account name (optional, for reset)
     * @param string $role role user id (optional, for reset)
     * @param string $pw role user password (optional, for reset)
     * @return $this
     */
    public function setRoleCredentials($uid = "", $role = "", $pw = "")
    {
        $login = $uid;
        if (!empty($role)) {
            $login .= $this->settings["roleSeparator"] . $role;
        }
        return $this->setCredentials($login, $pw);
    }

    /**
     * Convert domain names to idn + punycode if necessary
     * @param array<string> $domains list of domain names (or tlds)
     * @return array<mixed>
     */
    public function IDNConvert($domains)
    {
        return ConverterFactory::convert($domains);
    }

    /**
     * Auto convert API command parameters to punycode, if necessary.
     *
     * @param array<string> $cmd API command
     * @return array<string>
     */
    protected function autoIDNConvert($cmd)
    {
        // only convert if configured for the registrar
        // and ignore commands in string format (even deprecated)
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
        if (!empty($toconvert)) {
            $results = $this->IDNConvert($toconvert);
            foreach ($results as $idx => $row) {
                $cmd[$idxs[$idx]] = $row["punycode"];
            }
        }
        return $cmd;
    }

    /**
     * Perform API request using the given command
     * @param array<mixed> $cmd API command to request (optional for session login)
     * @return Response
     */
    public function request(array $cmd = [])
    {
        // flatten nested api command bulk parameters and sort them
        $mycmd = CommandFormatter::flattenCommand($cmd);
        // auto convert umlaut names to punycode
        $mycmd = $this->autoIDNConvert($mycmd);
        // request command to API
        $cfg = [
            "CONNECTION_URL" => $this->socketURL
        ];
        $data = $this->getPOSTData($mycmd);

        if (!$this->chandle) {
            $tmp = curl_init();
            if ($tmp === false) {
                $r = new Response("nocurl", $mycmd, $cfg);
                if ($this->debugMode) {
                    $secured = $this->getPOSTData($mycmd, true);
                    $this->logger->log($secured, $r, "CURL for PHP missing.");
                }
                return $r;
            }
            $this->chandle = $tmp;
        }

        curl_setopt_array($this->chandle, [
            // CURLOPT_VERBOSE         => $this->debugMode,
            CURLOPT_URL             => $cfg["CONNECTION_URL"],
            CURLOPT_CONNECTTIMEOUT  => 30, // 30s, 300s by default
            CURLOPT_TIMEOUT         => $this->settings["socketTimeout"],
            CURLOPT_POST            => 1,
            CURLOPT_HEADER          => 0,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_POSTFIELDS      => $data,
            CURLOPT_USERAGENT       => $this->getUserAgent(),
            CURLOPT_HTTPHEADER      => [
                "Expect:",
                "Content-Type: application/x-www-form-urlencoded", //UTF-8 implied
                "Content-Length: " . strlen($data),
                "Connection: keep-alive"
            ]
        ] + $this->curlopts);

        // which is by default tested for by phpStan
        /** @var string|false $r */
        $r = curl_exec($this->chandle);
        $error = null;
        if ($r === false) {
            $error = curl_error($this->chandle);
            $r = "httperror|" . $error;
        }
        $response = new Response($r, $mycmd, $cfg);

        if ($this->debugMode) {
            $secured = $this->getPOSTData($mycmd, true);
            $this->logger->log($secured, $response, $error);
        }
        return $response;
    }

    /**
     * Request the next page of list entries for the current list query
     * Useful for tables
     * @param Response $rr API Response of current page
     * @throws \Exception in case Command Parameter LAST is in use while using this method
     * @return Response|null
     */
    public function requestNextResponsePage($rr)
    {
        $mycmd = $rr->getCommand();
        if (array_key_exists("LAST", $mycmd)) {
            throw new \Exception("Parameter LAST in use. Please remove it to avoid issues in requestNextPage.");
        }
        $first = 0;
        if (array_key_exists("FIRST", $mycmd)) {
            $first = (int) $mycmd["FIRST"];
        }
        $total = $rr->getRecordsTotalCount();
        $limit = $rr->getRecordsLimitation();
        $first += $limit;
        if ($first < $total) {
            $mycmd["FIRST"] = $first;
            $mycmd["LIMIT"] = $limit;
            return $this->request($mycmd);
        }

        return null;
    }

    /**
     * Request all pages/entries for the given query command
     * @param array<string,mixed> $cmd API list command to use
     * @return Response[]
     */
    public function requestAllResponsePages($cmd)
    {
        $responses = [];
        $rr = $this->request(array_merge([], $cmd, ["FIRST" => 0]));
        $tmp = $rr;
        $idx = 0;
        do {
            $responses[$idx++] = $tmp;
            $tmp = $this->requestNextResponsePage($tmp);
        } while ($tmp !== null);
        return $responses;
    }

    /**
     * Close all curl handles
     * @return void
     */
    public function close()
    {
        if (!is_null($this->chandle)) {
            curl_close($this->chandle);
        }
    }

    /**
     * Set a data view to a given subuser
     * @param string $uid subuser account name
     * @return $this
     */
    public function setUserView($uid = "")
    {
        $this->socketConfig->setUser($uid);
        return $this;
    }

    /**
     * Activate High Performance Setup
     * @return $this
     */
    public function useHighPerformanceConnectionSetup()
    {
        $oldurl = $this->getURL();
        $hostname = parse_url($oldurl, PHP_URL_HOST);
        if (!empty($hostname)) {
            $url = str_replace($hostname, "127.0.0.1", $oldurl);
            $url = str_replace("https://", "http://", $url);
            $this->setURL($url);
        }
        return $this;
    }

    /**
     * Set OT&E System for API communication
     * @return $this
     */
    public function useOTESystem()
    {
        $this->isOTE = true;
        return $this->setURL($this->settings["env"]["ote"]["url"]);
    }

    /**
     * Set LIVE System for API communication (this is the default setting)
     * @return $this
     */
    public function useLIVESystem()
    {
        $this->isOTE = false;
        return $this->setURL($this->settings["env"]["live"]["url"]);
    }
}
