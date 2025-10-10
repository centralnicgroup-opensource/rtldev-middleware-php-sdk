<?php

namespace CNIC;

/**
 * ClientFactory
 *
 * @package CNIC
 */
class ClientFactory
{
    /**
     * Returns Client Instance by configuration
     *
     * @param array<mixed> $params Configuration settings
     * @param \CNIC\CNR\Logger|null $logger Logger Instance (optional)
     * @return \CNIC\CNR\SessionClient|\CNIC\IBS\SessionClient
     * @throws \Exception
     */
    public static function getClient($params, ?\CNIC\CNR\Logger $logger = null)
    {
        // if we dynamically instantiate via string, phpStan starts complaining ...
        switch (strtoupper($params["registrar"])) {
            case "CNR":
            case "CNIC":
                $cl = new \CNIC\CNR\SessionClient();
                $cl->setCustomLogger($logger ?? new \CNIC\CNR\Logger());
                break;
            case "IBS":
                $cl = new \CNIC\IBS\SessionClient();
                $cl->setCustomLogger($logger ?? new \CNIC\IBS\Logger());
                break;
            case "MONIKER":
                $cl = new \CNIC\MONIKER\SessionClient();
                $cl->setCustomLogger($logger ?? new \CNIC\IBS\Logger());
                break;
            case "HEXONET":
            case "ISPAPI":
                throw new \Exception("Registrar `" . $params["registrar"] . "` has seen EOL, use version 11 of this library.");
            default:
                throw new \Exception("Registrar `" . $params["registrar"] . "` not supported.");
        }

        if (!empty($params["sandbox"])) {
            $cl->useOTESystem();
        }
        if (
            !empty($params["username"])
            && !empty($params["password"])
        ) {
            $cl->setCredentials(
                $params["username"],
                html_entity_decode($params["password"], ENT_QUOTES)
            );
        }
        if (!empty($params["referer"])) {
            $cl->setReferer($params["referer"]); // GLOBALS["CONFIG"]["SystemURL"] TODO
        }
        if (!empty($params["ua"])) {
            $cl->setUserAgent(
                $params["ua"]["name"],
                $params["ua"]["version"],
                $params["ua"]["modules"]
            ); // "WHMCS", $GLOBALS["CONFIG"]["Version"], $modules TODO
        }
        if (!empty($params["logging"])) {
            $cl->enableDebugMode(); // activate logging
        }
        if (!empty($params["proxyserver"])) {
            $cl->setProxy($params["proxyserver"]);
        }
        return $cl;
    }
}
