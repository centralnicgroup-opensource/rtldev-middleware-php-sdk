<?php

/**
 * CNIC\HEXONET
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\HEXONET;

use CNIC\HEXONET\ResponseTemplateManager as RTM;
use CNIC\HEXONET\Logger as L;

/**
 * HEXONET API Client
 *
 * @package CNIC\HEXONET
 */

class Domain extends ApiObject
{

    protected $status = null;
    protected $idnLanguage = null;

    public function getDomain()
    {
        return $this->getId();
    }
    public function setDomain($domain)
    {
        $this->setId($domain);
        $this->setClass("DOMAIN");
        return $this->loadStatus();
    }
    public function loadStatus($refresh = false)
    {
        if (
            is_null($this->status)
            || $refresh
        ) {
            $this->status = $this->cl->request([
                "COMMAND" => "StatusDomain",
                "DOMAIN" => $this->id
            ]);
        }
        return $this;
    }
    public function convert()
    {
        $r = $this->convertbulk([$this->id]);
        return [
            "idn" => $r["idn"][0],
            "punycode" => $r["punycode"][0],
            "domain" => $r["domain"][0]
        ];
    }
    public function convertbulk($domains)
    {
        $r = $this->cl->request([
            "COMMAND" => "ConvertIDN",
            "DOMAIN" => $domains
        ]);
        if ($r->getCode() === 200) {
            return $r->getRecords();
        }
        $r = [];
        foreach ($domains as $domain) {
            $r[] = [
                "idn" => $domain,
                "punycode" => $domain,
                "domain" => $domain
            ];
        }
    }

    /**
     * Get the domain's assigned auth code.
     *
     * @param array $params common module parameters
     * @param string $domain puny code domain name
     * @return array
     */
    public function getAuthCode()
    {
        // Expiring Authorization Codes
        // https://confluence.centralnic.com/display/RSR/Expiring+Authcodes
        // pending cases:
        // - RSRBE-3774
        // - RSRBE-3753
        if (preg_match("/\.de$/i", $this->id)) {
            $r = $this->cl->request([
                "COMMAND" => "DENIC_CreateAuthInfo1",
                "DOMAIN" => $this->id
            ]);
        } elseif (preg_match("/\.(eu|be)$/i", $this->id)) {
            $r = $this->cl->request([
                "COMMAND" => "RequestDomainAuthInfo",
                "DOMAIN" => $this->id
            ]);
            // TODO -> PENDING = 1|0
        } else {
            // default case for all other tlds
            $r = $this->status;
        }

        // check response
        if ($r->isSuccess()) {
            if (
                preg_match("/\.(fi|nz)$/i", $this->id)
                && $r->getDataByIndex("TRANSFERLOCK", 0) === "1"
            ) {
                return [
                    "success" => false,
                    "reason" => "LOCKED"
                ];
            }
            $col = $r->getColumn("AUTH");
            if (is_null($col)) {
                return [
                    "success" => false,
                    "reason" => "SENDTOREGISTRANT"
                ];
            }
            if (!strlen($col->getDataByIndex(0))) {
                return [
                    "success" => false,
                    "reason" => "CONTACTSUPPORT"
                ];
            }
            //htmlspecialchars -> fixed in (#5070 @ 6.2.0 GA) / (#4166 @ 5.3.0)
            return [
                "success" => true,
                "eppcode" => $col->getDataByIndex(0)
            ];
        }
        return [
            "success" => false,
            "reason" => $r->getDescription(),
            "code" => $r->getCode()
        ];
    }

    private function loadIDNLanguage()
    {
        if (is_null($this->idnLanguage)) {
            $r = $this->cl->request([
                "COMMAND" => "CheckIDNLanguage",
                "DOMAIN" => $this->id
            ]);
            if ($r->isSuccess()) {
                $this->idnLanguage = [
                    "success" => true,
                    "language" => strtolower($r->getDataByIndex("LANGUAGE", 0))
                ];
            } else {
                $this->idnLanguage = [
                    "success" => false,
                    "reason" => $r->getDescription(),
                    "code" => $r->getCode()
                ];
            }
        }
        return $this->idnLanguage;
    }

    public function getNameservers($params, $domain)
    {
        if ($this->status->isSuccess()) {
            return $this->status->getColumnData("NAMESERVER");
        }
        return [];
    }
}
