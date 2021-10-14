<?php

namespace CNIC;

class DomainFactory extends ClientFactory
{
    public static function getDomain($params, $logger = null)
    {
        self::getClient($params, $logger);
        $regClass = "\\CNIC\\" . $params["registrar"] . "\\Domain";
        $domain = new $regClass($cl);
        return $domain;
    }

    public static function getZone($tld)
    {
        return strtoupper(str_replace(".", "", $tld));
    }

    public static function getZones($tlds)
    {
        return array_map(["DomainFactory", "getZone"], $tlds);
    }
}
