<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
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
     * @param array<string, string> $cmd API command used within this request
     * @return array<string,mixed>
     */
    public static function parse(string $raw, array $cmd = []): array
    {
        $isJson = $cmd === [] || (isset($cmd["ResponseFormat"]) && strtoupper($cmd["ResponseFormat"]) === "JSON");

        $invalidResponse = [
            "status" => "FAILURE",
            "message" => "423 Invalid API response. Contact Support"
        ];

        /** @var array<string, mixed>|scalar|null $result */
        $result = $isJson ? json_decode($raw, true) : null;

        // A bare valid JSON scalar (number, quoted string, boolean) decodes to a
        // non-null, non-array value that array_walk_recursive() cannot handle.
        // Report it as an invalid response up front rather than routing it through
        // the plain-text parser below (which would mis-split a scalar containing "=").
        if (is_scalar($result)) {
            return $invalidResponse;
        }

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
            return $invalidResponse;
        }

        // Normalize date separators (handles nested arrays)
        array_walk_recursive($result, function (mixed &$value, string $key): void {
            if (is_string($value) && preg_match("/(date|paiduntil|expiration)$/i", $key)) {
                $value = str_replace("/", "-", $value);
            }
        });

        /** @psalm-var array<string, mixed> $result */
        return $result;
    }
}
