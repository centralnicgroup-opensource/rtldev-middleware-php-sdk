<?php

namespace CNIC;

final class ClientFactory
{

    public function getClient($registrarid)
    {
        if (!preg_match("/^HEXONET|RRPproxy$/", $registrarid)) {
            throw new \Exception("Registrar `$registrarid` not supported.");
        }
        $cl = "\\CNIC\\" . $registrarid . "\\SessionClient";
        return new $cl();
    }
}
