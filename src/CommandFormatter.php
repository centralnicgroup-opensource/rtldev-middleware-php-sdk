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
        $priority = self::getPriorityArray();

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
     * Get the priority array with regex patterns
     *
     * @return array<string,int> The priority array
     */
    private static function getPriorityArray(): array
    {
        $priority = [
            "COMMAND" => 1,
            "/^(DOMAIN|DNSZONE|NAMESERVER|ZONE|SUBUSER)[0-9]*$/" => 2,
            "/^(PERIOD|ACTION|AUTH|TARGET|X-FEE-COMMAND|RENEWALMODE|LIMIT|WIDE)$/" => 3,
            "/^(TRANSFERLOCK|DNSSEC0|X-FEE-AMOUNT|LOG|TYPE|OBJECT|INACTIVE|OBJECTID|OBJECTCLASS|ORDER|ORDERBY|CURRENCYFROM|CURRENCYTO)$/" => 4,
        ];

        $contactTypes = [
            "OWNERCONTACT" => 5,
            "ADMINCONTACT" => 6,
            "TECHCONTACT" => 7,
            "BILLINGCONTACT" => 8
        ];
        $contactFields = [
            "FIRSTNAME" => 1,
            "LASTNAME" => 2,
            "ORGANIZATION" => 3,
            "STREET" => 4,
            "ZIP" => 5,
            "CITY" => 6,
            "STATE" => 7,
            "COUNTRY" => 8,
            "PHONE" => 9,
            "EMAIL" => 10,
            "CONTACT" => 11
        ];

        foreach ($contactTypes as $type => $typePriority) {
            foreach ($contactFields as $field => $fieldPriority) {
                $priority["/^{$type}[0-9]+{$field}$/"] = ($typePriority * 10) + $fieldPriority;
            }
        }

        return $priority;
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
