<?php

namespace CNIC;

class ClientFactory
{
    public static function getClient($params, $logger = null)
    {
        if (!preg_match("/^HEXONET|RRPproxy$/", $params["registrar"])) {
            throw new \Exception("Registrar `" . $params["registrar"] . "` not supported.");
        }
        $clientClass = "\\CNIC\\" . $params["registrar"] . "\\SessionClient";
        $cl = new $clientClass();
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
            $cl->setReferer($params["referer"]);// GLOBALS["CONFIG"]["SystemURL"] TODO
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
            $loggerClass = "\\CNIC\\" . $params["registrar"] . "\\Logger";
            $logger = new $loggerClass();
        }
        $cl->setCustomLogger($logger);

        return $cl;
    }
}
