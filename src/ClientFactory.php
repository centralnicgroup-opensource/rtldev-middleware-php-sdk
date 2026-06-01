<?php

declare(strict_types=1);

namespace CNIC;

use CNIC\CNR\Logger;
use CNIC\CNR\SessionClient;
use CNIC\IBS\Logger as IBSLogger;
use CNIC\IBS\SessionClient as IBSSessionClient;
use CNIC\MONIKER\SessionClient as MONIKERSessionClient;

/**
 * ClientFactory
 *
 * @psalm-api
 * @package CNIC
 */
class ClientFactory
{
    /**
     * Returns Client Instance by configuration
     *
     * @param array<mixed> $params Configuration settings
     * @param Logger|null $logger Logger Instance (optional)
     * @throws \Exception
     */
    public static function getClient(array $params, ?Logger $logger = null): SessionClient|IBSSessionClient
    {
        $registrar = strtoupper($params["registrar"]);
        $cl = match ($registrar) {
            "CNR", "CNIC"       => new SessionClient(),
            "IBS"               => new IBSSessionClient(),
            "MONIKER"           => new MONIKERSessionClient(),
            "HEXONET", "ISPAPI" => throw new \Exception("Registrar `{$params["registrar"]}` has seen EOL, use version 11 of this library."),
            default             => throw new \Exception("Registrar `{$params["registrar"]}` not supported."),
        };
        $cl->setCustomLogger($logger ?? ($cl instanceof IBSSessionClient ? new IBSLogger() : new Logger()));

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
