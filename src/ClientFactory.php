<?php

declare(strict_types=1);

namespace CNIC;

use CNIC\CNR\SessionClient;
use CNIC\IBS\SessionClient as IBSSessionClient;
use CNIC\MONIKER\SessionClient as MONIKERSessionClient;
use CNIC\Registrar;

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
     * @param array{registrar: string, username?: string, password?: string, sandbox?: bool, referer?: string, ua?: array{name: string, version: string, modules: string[]}, logging?: bool, proxyserver?: string} $params Configuration settings
     * @param LoggerInterface|null $logger Logger Instance (optional)
     * @throws \Exception
     */
    public static function getClient(array $params, ?LoggerInterface $logger = null): SessionClient|IBSSessionClient|MONIKERSessionClient
    {
        $cl = match (Registrar::tryFrom(strtoupper($params["registrar"]))) {
            Registrar::CNR, Registrar::CNIC => new SessionClient(),
            Registrar::IBS                  => new IBSSessionClient(),
            Registrar::MONIKER              => new MONIKERSessionClient(),
            null                            => throw new \Exception("Registrar `{$params["registrar"]}` not supported."),
        };
        if ($logger !== null) {
            $cl->setCustomLogger($logger);
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
