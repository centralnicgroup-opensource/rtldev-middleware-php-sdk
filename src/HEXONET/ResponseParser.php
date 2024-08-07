<?php

#declare(strict_types=1);

/**
 * CNIC\HEXONET
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\HEXONET;

/**
 * HEXONET ResponseParser
 *
 * @package CNIC\HEXONET
 */
final class ResponseParser
{
    /**
     * Method to parse plain API response into js object
     * @param string $raw API plain response
     * @return array<string, mixed> API response as hash
     */
    public static function parse($raw)
    {
        /** @var array<string, mixed> $hash */
        $hash = [];
        $tmp = preg_replace("/\r\n/", "\n", $raw);
        if (is_null($tmp)) {
            $tmp = $raw;
        }
        $rlist = explode("\n", $tmp);
        foreach ($rlist as $item) {
            if (preg_match("/^([^\=]*[^\t\= ])[\t ]*=[\t ]*(.*)$/", $item, $m)) {
                $attr = $m[1];
                $value = $m[2];
                $value = preg_replace("/[\t ]*$/", "", $value);
                if (preg_match("/^property\[([^\]]*)\]/i", $attr, $m)) {
                    if (!array_key_exists("PROPERTY", $hash)) {
                        $hash["PROPERTY"] = [];
                    }
                    $prop = strtoupper($m[1]);
                    $tmp = preg_replace("/\s/", "", $prop);
                    if (!is_null($tmp)) {
                        $prop = $tmp;
                    }
                    if (array_key_exists($prop, $hash["PROPERTY"])) {
                        $hash["PROPERTY"][$prop][] = $value;
                    } else {
                        $hash["PROPERTY"][$prop] = [$value];
                    }
                } else {
                    $hash[strtoupper($attr)] = $value;
                }
            }
        }
        return $hash;
    }

    /**
     * Serialize given parsed response hash back to plain text
     * @param array<mixed> $r API response as hash
     * @return string plain API response
     */
    public static function serialize($r)
    {
        $plain = "[RESPONSE]";
        if (array_key_exists("PROPERTY", $r)) {
            foreach ($r["PROPERTY"] as $key => $vals) {
                foreach ($vals as $idx => $val) {
                    $plain .= "\r\nPROPERTY[" . $key . "][" . $idx . "]=" . $val;
                }
            }
        }
        if (array_key_exists("CODE", $r)) {
            $plain .= "\r\nCODE=" . $r["CODE"];
        }
        if (array_key_exists("DESCRIPTION", $r)) {
            $plain .= "\r\nDESCRIPTION=" . $r["DESCRIPTION"];
        }
        if (array_key_exists("QUEUETIME", $r)) {
            $plain .= "\r\nQUEUETIME=" . $r["QUEUETIME"];
        }
        if (array_key_exists("RUNTIME", $r)) {
            $plain .= "\r\nRUNTIME=" . $r["RUNTIME"];
        }
        $plain .= "\r\nEOF\r\n";
        return $plain;
    }
}
