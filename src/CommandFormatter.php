<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * CommandFormatter
 *
 * @package CNIC
 */
final class CommandFormatter
{
    /**
     * Static priority seed: the base property patterns plus the contact type and
     * field patterns from which getPropertiesPriority() builds the full map. The
     * data never changes at runtime, so it lives as a class constant rather than
     * a rebuilt-per-call literal.
     * @var array{properties: array<string, int>, contact: array{types: array<string, int>, fields: array<string, int>}}
     */
    private const array CONTACT_FIELDS_PRIORITY = [
        "properties" => [
            "COMMAND" => 1,
            "/^(DOMAIN|DNSZONE|NAMESERVER|ZONE|SUBUSER)[0-9]*$/i" => 2,
            "/^(PERIOD|ACTION|AUTH|TARGET|X-FEE-COMMAND|RENEWALMODE|LIMIT|WIDE)$/i" => 3,
            "/^(NS_LIST|TRANSFERLOCK|DNSSEC0|X-FEE-AMOUNT|LOG|TYPE|OBJECT|INACTIVE|OBJECTID|OBJECTCLASS|ORDER|ORDERBY|CURRENCYFROM|CURRENCYTO)$/i" => 4,
        ],
        "contact" => [
            "types" => [
                "OWNERCONTACT|REGISTRANT" => 5,
                "ADMINCONTACT|TECHNICAL" => 6,
                "TECHCONTACT|BILLING" => 7,
                "BILLINGCONTACT|ADMIN" => 8,
            ],
            "fields" => [
                "FIRSTNAME" => 1,
                "MIDDLENAME" => 2,
                "LASTNAME" => 3,
                "ORGANIZATION" => 4,
                "STREET" => 5,
                "ZIP" => 6,
                "CITY" => 7,
                "STATE" => 8,
                "COUNTRY" => 9,
                "PHONE|PHONENUMBER" => 10,
                "EMAIL" => 11,
                "CONTACT" => 12,
                "LEGALFORM" => 13,
                "IDENTIFICACION" => 14,
                "TIPO-IDENTIFICACION" => 15,
            ]
        ]
    ];

    /**
     * Memoized priority map produced by getPropertiesPriority().
     * The map is derived from purely static data (see CONTACT_FIELDS_PRIORITY)
     * and never depends on the command being sorted, so it is built once per
     * process and reused across every getSortedCommand() call (each request
     * flatten and each response getCommand()).
     * @var array<string,int>|null
     */
    private static ?array $priorityCache = null;

    /**
     * Get the sorted command array based on priority
     *
     * @param array<string,string> $command The command array to be sorted
     * @return array<string,string> The sorted command array
     */
    public static function getSortedCommand(array $command): array
    {
        $priority = self::getPropertiesPriority();

        // Decorate-sort-undecorate: resolve each key's priority exactly once
        // (O(n) findPriority calls) into a cache, then have the comparator read
        // the cached ints instead of re-scanning ~65 regex patterns on every
        // one of the ~2*n*log(n) comparisons.
        $keyPriority = [];
        foreach (array_keys($command) as $key) {
            $keyPriority[$key] = self::findPriority($key, $priority);
        }

        // Sort the command array based on priority
        uksort($command, function (string $a, string $b) use ($keyPriority) {
            $priorityA = $keyPriority[$a];
            $priorityB = $keyPriority[$b];

            return $priorityA === $priorityB ? strcmp($a, $b) : $priorityA - $priorityB;
        });

        return $command;
    }

    /**
     * Flatten API command's nested arrays for easier handling
     *
     * @param array<string,scalar|scalar[]|null> $cmd API Command
     * @param bool $toupper flag to convert keys to uppercase or leave as is
     * @return array<string,string>
     */
    public static function flattenCommand(array $cmd, bool $toupper = true): array
    {
        /** @var array<string,string> $newcmd */
        $newcmd = [];
        foreach ($cmd as $key => $val) {
            if (!isset($val)) {
                continue;
            }
            $newKey = $toupper ? \strtoupper($key) : $key;
            if (!is_array($val)) {
                $newv = (string)$val;
                $newcmd[$newKey] = preg_replace("/\r|\n/", "", $newv) ?? $newv;
                continue;
            }
            foreach ($val as $idx => $v) {
                $newv = (string)$v;
                $newcmd[$newKey . $idx] = preg_replace("/\r|\n/", "", $newv) ?? $newv;
            }
        }

        // Sort the command array based on priority
        return self::getSortedCommand($newcmd);
    }

    /**
     * Format the command array into a plain text string
     *
     * @param array<string,string> $command The command array to be formatted
     * @return string The formatted command string
     */
    public static function formatCommand(array $command): string
    {
        $tmp = "";

        foreach ($command as $key => $val) {
            $tmp .= "$key = $val\n";
        }
        return $tmp;
    }

    /**
     * Generate the priority array with properties dynamically including contact fields and their priority
     *
     * @return array<string,int> The priority array
     */
    private static function getPropertiesPriority(): array
    {
        if (self::$priorityCache !== null) {
            return self::$priorityCache;
        }

        $propertiesWithPriority = self::CONTACT_FIELDS_PRIORITY;

        foreach ($propertiesWithPriority["contact"]["types"] as $typePattern => $typePriority) {
            foreach ($propertiesWithPriority["contact"]["fields"] as $fieldPattern => $fieldPriority) {
                $propertiesWithPriority["properties"]["/^({$typePattern})[_0-9]*({$fieldPattern}[0-9]*)$/i"] = ($typePriority * 100) + $fieldPriority;
            }
        }

        return self::$priorityCache = $propertiesWithPriority["properties"];
    }

    /**
     * Find the priority of a given key
     *
     * @param string $key The key to find the priority for
     * @param array<string,int> $priority The priority array
     * @return int The priority value
     */
    private static function findPriority(string $key, array $priority): int
    {
        foreach ($priority as $pattern => $priorityValue) {
            // Check if the pattern is a regex or a plain string
            if ($pattern !== '' && (($pattern[0] === '/' && preg_match($pattern, $key)) || $pattern === $key)) {
                return $priorityValue;
            }
        }
        return PHP_INT_MAX;
    }
}
