<?php

/**
 * CNIC\HEXONET
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\HEXONET;

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
    protected $status = null;

    public function __construct($cf)
    {
        $this->cl = $cf;
    }

    public function setId($objectid)
    {
        $this->id = $objectid;
    }

    public function setClass($objectclass)
    {
        $this->class = $objectclass;
        return $this->loadStatus();
    }

    public function loadStatus($refresh = false)
    {
        if (
            is_null($this->status)
            || $refresh
        ) {
            $this->status = $this->cl->request([
                "COMMAND" => "Status" . ucfirst(strtolower($this->class)),
                "DOMAIN" => $this->id
            ]);
        }
        return $this;
    }

    public function convert()
    {
        if (preg_match($this->class)) {
            $r = $this->cl->request([
                "COMMAND" => "ConvertIDN",
                "DOMAIN" => [$this->id]
            ]);
            if ($r->isSuccess()) {
                $d = $r->getRecord(0);
                if (!empty($d["IDN"])) {
                    return [
                        "idn" => $d["IDN"],
                        "punycode" => $d["ACE"],
                        "domain" => $this->id
                    ];
                }
            }
        }
        return [
            "idn" => $this->id,
            "punycode" => $this->id,
            "domain" => $this->id
        ];
    }

    public function convertbulk($domains)
    {
        $r = $this->cl->request([
            "COMMAND" => "ConvertIDN",
            "DOMAIN" => $domains
        ]);
        $list = [];
        foreach ($domains as $domain) {
            $list[] = [
                "idn" => $domain,
                "punycode" => $domain,
                "domain" => $domain
            ];
        }
        if ($r->isSuccess()) {
            $recs = $r->getRecords();
            foreach ($recs as $idx => $rec) {
                $d = $rec->getData();
                if (empty($d["IDN"])) {
                    continue;
                }
                $list[$idx]["idn"] = $d["IDN"];
                $list[$idx]["punycode"] = $d["ACE"];
            }
        }
        return $list;
    }
}
