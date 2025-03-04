<?php

namespace CNIC;

/**
 * CommandFormatter
 *
 * @package CNIC
 */
class CommandFormatter
{
    /**
     * Get the sorted command array based on priority
     *
     * @param array<string,string> $command The command array to be sorted
     * @return array<string,string> The sorted command array
     */
    public static function getSortedCommand(array $command): array
    {
        $priority = self::getPropertiesPriority();

        // Sort the command array based on priority
        uksort($command, function ($a, $b) use ($priority) {
            $priorityA = self::findPriority($a, $priority);
            $priorityB = self::findPriority($b, $priority);

            return $priorityA === $priorityB ? strcmp($a, $b) : $priorityA - $priorityB;
        });

        return $command;
    }

    /**
     * Flatten API command's nested arrays for easier handling
     *
     * @param array<mixed> $cmd API Command
     * @param bool $toupper flag to convert keys to uppercase or leave as is
     * @return array<mixed>
     */
    public static function flattenCommand($cmd, $toupper = true): array
    {
        $newcmd = [];
        foreach ($cmd as $key => $val) {
            if (isset($val)) {
                $val = preg_replace("/\r|\n/", "", $val);
                $newKey = $toupper ? \strtoupper($key) : $key;
                if (is_array($val)) {
                    foreach ($cmd[$key] as $idx => $v) {
                        $newcmd[$newKey . $idx] = $v;
                    }
                } else {
                    $newcmd[$newKey] = $val;
                }
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
     * Assign the priority of each key in the command array based on the key pattern
     *
     * @return array<string,mixed>
     */
    private static function getPropertiesContactFieldsWithPriority(): array
    {
        $keyProperties = [
            "COMMAND" => 1,
            "/^(DOMAIN|DNSZONE|NAMESERVER|ZONE|SUBUSER)[0-9]*$/i" => 2,
            "/^(PERIOD|ACTION|AUTH|TARGET|X-FEE-COMMAND|RENEWALMODE|LIMIT|WIDE)$/i" => 3,
            "/^(NS_LIST|TRANSFERLOCK|DNSSEC0|X-FEE-AMOUNT|LOG|TYPE|OBJECT|INACTIVE|OBJECTID|OBJECTCLASS|ORDER|ORDERBY|CURRENCYFROM|CURRENCYTO)$/i" => 4,
        ];

        $contactTypes = [
            "OWNERCONTACT|REGISTRANT" => 5,
            "ADMINCONTACT|TECHNICAL" => 6,
            "TECHCONTACT|BILLING" => 7,
            "BILLINGCONTACT|ADMIN" => 8,
        ];
        $contactFields = [
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
        ];

        return [
            "properties" => $keyProperties,
            "contact" => [
                "types" => $contactTypes,
                "fields" => $contactFields
            ]
        ];
    }

    /**
     * Generate the priority array with properties dynamically including contact fields and their priority
     *
     * @return array<string,int> The priority array
     */
    private static function getPropertiesPriority(): array
    {
        $propertiesWithPriority = self::getPropertiesContactFieldsWithPriority();

        foreach ($propertiesWithPriority["contact"]["types"] as $typePattern => $typePriority) {
            foreach ($propertiesWithPriority["contact"]["fields"] as $fieldPattern => $fieldPriority) {
                $propertiesWithPriority["properties"]["/^({$typePattern})[_0-9]*({$fieldPattern}[0-9]*)$/i"] = ($typePriority * 100) + $fieldPriority;
            }
        }

        return $propertiesWithPriority["properties"];
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
            if ((substr($pattern, 0, 1) === '/' && preg_match($pattern, $key)) || $pattern === $key) {
                return $priorityValue;
            }
        }
        return PHP_INT_MAX;
    }
}
