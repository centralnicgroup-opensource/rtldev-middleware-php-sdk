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
 * @final
 */
final class ResponseParser
{
    /**
     * Method to parse plain API response into js object
     * @param string $raw API plain response
     * @return array<string,mixed>
     */
    public static function parse($raw)
    {
        /** @var array<string,mixed> $hash */
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
}
