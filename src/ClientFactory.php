<?php

namespace CNIC;

class ClientFactory
{
    /**
     * Returns Client Instance by configuration
     * @param array<mixed> $params configuration settings
     * @param \CNIC\HEXONET\Logger|\CNIC\CNR\Logger $logger Logger Instance (optional)
     * @return \CNIC\HEXONET\SessionClient|\CNIC\CNR\SessionClient
     * @throws \Exception
     */
    public static function getClient($params, $logger = null)
    {
        if (!(bool)preg_match("/^HEXONET|ISPAPI|KeySystems|RRPproxy|CNR|CNIC$/i", $params["registrar"])) {
            throw new \Exception("Registrar `" . $params["registrar"] . "` not supported.");
        }
        // if we dynamically instantiate via string, phpStan start complaining ...
        if ((bool)preg_match("/^HEXONET|ISPAPI$/i", $params["registrar"])) {
            $cl = new \CNIC\HEXONET\SessionClient();
        } else {
            $cl = new \CNIC\CNR\SessionClient();
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
        if (is_null($logger)) {
            // if we dynamically instantiate via string, phpStan start complaining ...
            if ((bool)preg_match("/^HEXONET|ISPAPI$/i", $params["registrar"])) {
                $logger = new \CNIC\HEXONET\Logger();
            } else {
                $logger = new \CNIC\CNR\Logger();
            }
        }
        $cl->setCustomLogger($logger);

        return $cl;
    }
}
