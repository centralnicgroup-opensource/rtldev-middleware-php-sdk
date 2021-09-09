<?php

/**
 * CNIC\HEXONET
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\HEXONET;

use CNIC\ClientFactory as CF;

/**
 * HEXONET API Object
 *
 * @package CNIC\HEXONET
 */

class ApiObject
{
    protected $id = null;
    protected $class = null;
    protected $cl = null;

    public function __construct($params, $logger = null)
    {
        // TODO move this away to upper layer
        /*if (!$params) {
            $params = \getregistrarconfigoptions($registrar);
        }*/
        // TODO need to find a nice way of injecting this
        /*
        $modules = [];
        foreach (self::getModuleVersions($params) as $key => $val) {
            $modules[] = "$key/$val";
        }
        */

        $this->cl = CF::getClient($params, $logger);
    }

    public function setId($objectid)
    {
        $this->id = $objectid;
    }

    public function setClass($objectclass)
    {
        $this->class = $objectclass;
    }
}
