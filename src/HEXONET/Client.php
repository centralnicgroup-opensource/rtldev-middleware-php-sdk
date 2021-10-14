<?php

#declare(strict_types=1);

/**
 * CNIC\HEXONET
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\HEXONET;

use CNIC\HEXONET\ResponseTemplateManager as RTM;
use CNIC\HEXONET\Logger as L;

/**
 * HEXONET API Client
 *
 * @package CNIC\HEXONET
 */

class Client
{
    /**
     * registrar api settings
     * @var array
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
     * @var array
     */
    protected $curlopts = [];
    /**
     * logger function name for debug mode
     * @var Logger
     */
    protected $logger;

    public function __construct(string $path = "")
    {
        $this->settings = json_decode(file_get_contents($path), true);
        $this->socketURL = "";
        $this->debugMode = false;
        $this->ua = "";
        $this->socketConfig = new SocketConfig($this->settings["parameters"]);
        $this->useLIVESystem();
        $this->setDefaultLogger();
    }

    /**
     * Return Domain Object
     * @param string $domainstr domain name
     * @return Domain
     */
    public function getDomain($domainstr) {
        $domain = new Domain($this);
        $domain->setId($domainstr);
        return $domain;
    }

    /**
     * set custom logger to use instead of default one
     * create your own class inheriting from \CNIC\Logger and overriding method log
     * @param Logger $customLogger
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
     * @param string|array $cmd API command to encode
     * @param bool $secured secure password (when used for output)
     * @return string encoded POST data string
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
     * @return string|null API Session ID currently in use
     */
    public function getSession()
    {
        $sessid = $this->socketConfig->getSession();
        return ($sessid === "" ? null : $sessid);
    }

    /**
     * Get the API connection url that is currently set
     * @return string API connection url currently in use
     */
    public function getURL()
    {
        return $this->socketURL;
    }

    /**
     * Set a custom user agent (for platforms that use this SDK)
     * @param string $str user agent label
     * @param string $rv user agent revision
     * @param array $modules further modules to add to user agent string, format: ["<module1>/<version>", "<module2>/<version>", ... ]
     * @return $this
     */
    public function setUserAgent($str, $rv, $modules = [])
    {
        $mods = empty($modules) ? "" : " " . implode(" ", $modules);
        $this->ua = (
            $str . " (" . PHP_OS . "; " . php_uname("m") . "; rv:" . $rv . ")" . $mods . " php-sdk/" . $this->getVersion() . " php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION])
        );
        return $this;
    }

    /**
     * Get the user agent string
     * @return string user agent string
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
     * @return string module version
     */
    public function getVersion()
    {
        return "6.1.1";
    }

    /**
     * Apply session data (session id and system entity) to given php session object
     * @param array $session php session instance ($_SESSION)
     * @return $this
     */
    public function saveSession(&$session)
    {
        $session["socketcfg"] = [
            "entity" => $this->socketConfig->getSystemEntity(),
            "session" => $this->socketConfig->getSession()
        ];
        return $this;
    }

    /**
     * Use existing configuration out of php session object
     * to rebuild and reuse connection settings
     * @param array $session php session object ($_SESSION)
     * @return $this
     */
    public function reuseSession(&$session)
    {
        $this->socketConfig->setSystemEntity($session["socketcfg"]["entity"]);
        $this->setSession($session["socketcfg"]["session"]);
        return $this;
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
     * Set one time password to be used for API communication
     * @param string $value one time password (optional, for reset)
     * @throws \Exception in case this feature is not supported
     * @return $this
     */
    public function setOTP($value = "")
    {
        if (!empty($value) && !isset($this->settings["parameters"]["otp"])) {
            throw new \Exception("Feature `OTP` not supported");
        }
        $this->socketConfig->setOTP($value);
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
     * Flatten API command's nested arrays for easier handling
     * @param array $cmd API Command
     * @return array
     */
    protected function flattenCommand($cmd)
    {
        $newcmd = [];
        foreach ($cmd as $key => $val) {
            if (isset($val)) {
                $val = preg_replace("/\r|\n/", "", $val);
                $newKey = \strtoupper($key);
                if (is_array($val)) {
                    foreach ($cmd[$key] as $idx => $v) {
                        $newcmd[$newKey . $idx] = $v;
                    }
                } else {
                    $newcmd[$newKey] = $val;
                }
            }
        }
        return $newcmd;
    }

    /**
     * Auto convert API command parameters to punycode, if necessary.
     * @param array|string $cmd API command
     * @return array
     */
    protected function autoIDNConvert($cmd)
    {
        // only convert if configured for the registrar
        // don't convert for convertidn command to avoid endless loop
        // and ignore commands in string format (even deprecated)
        if (
            !$this->settings["needsIDNConvert"]
            || is_string($cmd)
            || preg_match("/^CONVERTIDN$/i", $cmd["COMMAND"])
        ) {
            return $cmd;
        }
        $cmdkeys = array_keys($cmd);
        $prodregex = "/^(DOMAIN|NAMESERVER|DNSZONE|OBJECTID)([0-9]*)$/";
        $keys = preg_grep($prodregex, $cmdkeys);
        if (empty($keys)) {
            return $cmd;
        }
        $toconvert = [];
        $idxs = [];
        foreach ($keys as $key) {
            if (
                isset($cmd[$key])
                && preg_match("/[^a-z0-9\.\- ]/i", $cmd[$key])
                && (
                    ($key !== "OBJECTID")
                    || preg_match("/^(DOMAIN|DELETEDDOMAIN|DOMAINAPPLICATION|NAMESERVER|DNSZONE)$/", $cmd["OBJECTCLASS"])
                )
            ) {
                $toconvert[] = $cmd[$key];
                $idxs[] = $key;
            }
        }
        if (empty($toconvert)) {
            return $cmd;
        }
        $r = $this->request([
            "COMMAND" => "ConvertIDN",
            "DOMAIN" => $toconvert
        ]);
        if ($r->isSuccess()) {
            $col = $r->getColumn("ACE");
            if ($col) {
                foreach ($col->getData() as $idx => $pc) {
                    $cmd[$idxs[$idx]] = $pc;
                }
            }
        }
        return $cmd;
    }

    /**
     * Perform API request using the given command
     * @param array $cmd API command to request
     * @return Response Response
     */
    public function request($cmd)
    {
        // flatten nested api command bulk parameters
        $mycmd = $this->flattenCommand($cmd);
        // auto convert umlaut names to punycode
        $mycmd = $this->autoIDNConvert($mycmd);
        // request command to API
        $cfg = [
            "CONNECTION_URL" => $this->socketURL
        ];
        $data = $this->getPOSTData($mycmd);
        $curl = curl_init($cfg["CONNECTION_URL"]);
        // PHP 7.3 return false vs. 7.4 throws an Exception
        // when setting the URL to "\0"
        // @codeCoverageIgnoreStart
        if ($curl === false) {
            $r = new Response("nocurl", $mycmd, $cfg);
            if ($this->debugMode) {
                $secured = $this->getPOSTData($mycmd, true);
                $this->logger->log($secured, $r, "CURL for PHP missing.");
            }
            return $r;
        }
        // @codeCoverageIgnoreEnd
        curl_setopt_array($curl, [
            CURLOPT_VERBOSE         => $this->debugMode,
            CURLOPT_CONNECTTIMEOUT  => 5000,
            CURLOPT_TIMEOUT         => $this->settings["socketTimeout"],
            CURLOPT_POST            => 1,
            CURLOPT_POSTFIELDS      => $data,
            CURLOPT_HEADER          => 0,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_USERAGENT       => $this->getUserAgent(),
            CURLOPT_HTTPHEADER      => [
                "Expect:",
                "Content-Type: application/x-www-form-urlencoded",//UTF-8 implied
                "Content-Length: " . strlen($data)
            ]
        ] + $this->curlopts);
        $r = curl_exec($curl);
        $error = null;
        if ($r === false) {
            $r = "httperror";
            $error = curl_error($curl);
        }
        $r = new Response($r, $mycmd, $cfg);

        curl_close($curl);
        if ($this->debugMode) {
            $secured = $this->getPOSTData($mycmd, true);
            $this->logger->log($secured, $r, $error);
        }
        return $r;
    }

    /**
     * Request the next page of list entries for the current list query
     * Useful for tables
     * @param Response $rr API Response of current page
     * @throws \Exception in case Command Parameter LAST is in use while using this method
     * @return Response|null Response or null in case there are no further list entries
     */
    public function requestNextResponsePage($rr)
    {
        $mycmd = $rr->getCommand();
        if (array_key_exists("LAST", $mycmd)) {
            throw new \Exception("Parameter LAST in use. Please remove it to avoid issues in requestNextPage.");
        }
        $first = 0;
        if (array_key_exists("FIRST", $mycmd)) {
            $first = $mycmd["FIRST"];
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
     * @param array $cmd API list command to use
     * @return Response[] Responses
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
        $url = str_replace($hostname, "127.0.0.1", $oldurl);
        return $this->setURL($url);
    }

    /**
     * Set OT&E System for API communication
     * @return $this
     */
    public function useOTESystem()
    {
        if (isset($this->settings["env"]["ote"]["entity"])) {
            $this->socketConfig->setSystemEntity($this->settings["env"]["ote"]["entity"]);
        }
        return $this->setURL($this->settings["env"]["ote"]["url"]);
    }

    /**
     * Set LIVE System for API communication (this is the default setting)
     * @return $this
     */
    public function useLIVESystem()
    {
        if (isset($this->settings["env"]["ote"]["entity"])) {
            $this->socketConfig->setSystemEntity($this->settings["env"]["live"]["entity"]);
        }
        return $this->setURL($this->settings["env"]["live"]["url"]);
    }
}
