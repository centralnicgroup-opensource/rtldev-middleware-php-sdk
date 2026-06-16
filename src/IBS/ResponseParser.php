<?php

declare(strict_types=1);

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
    public static function parse(string $raw, array $cmd = []): array
    {
        $isJson = $cmd === [] || (isset($cmd["ResponseFormat"]) && strtoupper($cmd["ResponseFormat"]) === "JSON");

        /** @var array<string, mixed>|null $result */
        $result = $isJson ? json_decode($raw, true) : null;

        // Plain text key=value format (templates and non-JSON responses)
        if (is_null($result)) {
            $data = [];
            foreach (preg_split("/\r\n|\n/", $raw) ?: [] as $line) {
                $line = trim($line);
                if ($line !== "" && ($pos = strpos($line, "=")) !== false) {
                    $data[substr($line, 0, $pos)] = substr($line, $pos + 1);
                }
            }
            $result = $data === [] ? null : $data;
        }

        if (is_null($result)) {
            return [
                "status" => "FAILURE",
                "message" => "423 Invalid API response. Contact Support"
            ];
        }

        // Normalize date separators (handles nested arrays)
        array_walk_recursive($result, function (mixed &$value, string $key): void {
            if (is_string($value) && preg_match("/date|paiduntil|expiration$/i", $key)) {
                $value = str_replace("/", "-", $value);
            }
        });

        return $result;
    }
}
