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
     * Method to parse API response into associative array
     * @param string $raw API response
     * @param array<string> $cmd API command used within this request
     * @return array<string,mixed>
     */
    public static function parse($raw, $cmd = [])
    {
        $isJson = empty($cmd) || (isset($cmd["ResponseFormat"]) && strtoupper($cmd["ResponseFormat"]) === "JSON");

        if ($isJson) {
            $result = json_decode($raw, true);
            if (!is_null($result)) {
                foreach ($result as $key => $value) {
                    if (preg_match("/date|paiduntil|expiration$/i", $key)) {
                        $result[$key] = str_replace("/", "-", $value);
                    }
                }
                return $result;
            }
        }
        // Plain text key=value format (templates and non-JSON responses)
        $data = [];
        foreach (preg_split("/\r\n|\n/", $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line !== "" && ($pos = strpos($line, "=")) !== false) {
                $data[substr($line, 0, $pos)] = substr($line, $pos + 1);
            }
        }
        if (!empty($data)) {
            return $data;
        }
        return [
            "status" => "FAILURE",
            "message" => "423 Invalid API response. Contact Support"
        ];
    }
}
