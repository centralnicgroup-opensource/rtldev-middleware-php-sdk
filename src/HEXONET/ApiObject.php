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

class ApiObject implements \CNIC\ApiObjectInterface
{
    /**
     * @var string object identifier
     */
    protected $id = null;
    /**
     * @var string object class/type
     */
    protected $class = null;
    /**
     * @var Client registrar's client instance
     */
    protected $cl = null;
    /**
     * @var Response|null registrar's status response
     */
    protected $status = null;

    /**
     * Constructor
     * @param \CNIC\HEXONET\Client $cf registrar's client instance
     */
    public function __construct($cf)
    {
        $this->cl = $cf;
    }

    /**
     * Set the related Object ID
     * @param string $objectid object id
     * @return $this
     */
    public function setId(string $objectid): self
    {
        $this->id = $objectid;
        return $this;
    }

    /**
     * Set the Object Class
     * @param string $objectclass object class
     * @return $this
     */
    public function setClass(string $objectclass): self
    {
        $this->class = $objectclass;
        return $this->loadStatus();
    }

    /**
     * Load Status Data
     * @param bool $refresh trigger fresh data load, by default false
     * @return $this
     */
    public function loadStatus(bool $refresh = false): self
    {
        if (
            isset($this->status)
            || $refresh
        ) {
            $this->status = $this->cl->request([
                "COMMAND" => "Status" . ucfirst(strtolower($this->class)),
                "DOMAIN" => $this->id
            ]);
        }
        return $this;
    }

    /**
     * IDN Conversion
     * @return array
     */
    public function convert(): array
    {
        if (preg_match("/^DOMAIN|DNSZONE|NAMESERVER$/i", $this->class)) {//TODO
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

    /**
     * Bulk IDN Conversion
     * @param array $domains List of Domains
     * @return array
     */
    public function convertbulk($domains): array
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
