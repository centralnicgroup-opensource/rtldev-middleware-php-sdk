<?php

#declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\IBS;

/**
 * IBS ResponseParser
 *
 * @package CNIC\IBS
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
        /** @var array<string,mixed> $result */
        $result = [];
        $tmp = preg_replace("/\r\n/", "\n", $raw);
        if (is_null($tmp)) {
            $tmp = $raw;
        }
        $arr = explode("\n", $tmp);
        foreach ($arr as $str) {
            list($varName, $value) = explode("=", $str, 2);
            $varName = trim($varName);
            $value = trim($value);
            $result[$varName] = $value;
        }
        return $result;
    }
}
