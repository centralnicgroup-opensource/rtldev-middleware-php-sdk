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
        $result = json_decode($raw, true);
        if (!is_null($result)) {
            foreach ($result as $key => $value) {
                if (preg_match("/date|paiduntil|expiration$/i", $key)) {
                    $result[$key] = str_replace("/", "-", $value);
                }
            }
            return $result;
        }
        return [
            "status" => "FAILURE",
            "message" => "423 Invalid JSON API response. Contact Support"
        ];
    }
}
