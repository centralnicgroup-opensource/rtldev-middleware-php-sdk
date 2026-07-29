<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

use CNIC\ResponseParserInterface;

/**
 * CNR ResponseParser
 *
 * Turns the line-oriented CNR wire format (`KEY=value`, with list columns under
 * `PROPERTY[NAME][index]`) into the response hash. Instantiable and stateless —
 * see {@see ResponseParserInterface} for why the parse step is a seam rather
 * than a static call.
 *
 * @psalm-api
 * @package CNIC\CNR
 * @final
 */
final class ResponseParser implements ResponseParserInterface
{
    /**
     * Method to parse plain API response into js object
     *
     * The CNR wire format is self-describing, so $cmd is accepted only to keep
     * the contract uniform across brands (IBS needs it to pick its JSON or
     * plain-text branch) and is deliberately unused here.
     *
     * @param string $raw API plain response
     * @param array<string, string> $cmd API command used within this request (unused)
     * @return array<string, string|array<string, list<string>>>
     */
    #[\Override]
    public function parse(string $raw, array $cmd = []): array
    {
        /** @var array<string, string|array<string, list<string>>> $hash */
        $hash = [];
        /** @var array<string, list<string>> $properties */
        $properties = [];
        $tmp = preg_replace("/\r\n/", "\n", $raw);
        if (is_null($tmp)) {
            $tmp = $raw;
        }
        $rlist = explode("\n", $tmp);
        foreach ($rlist as $item) {
            if (preg_match("/^([^\=]*[^\t\= ])[\t ]*=[\t ]*(.*)$/", $item, $m)) {
                $attr = $m[1];
                $value = $m[2];
                $value = preg_replace("/[\t ]*$/", "", $value) ?? $value;
                if (preg_match("/^property\[([^\]]*)\]/i", $attr, $m)) {
                    $prop = strtoupper($m[1]);
                    $tmp = preg_replace("/\s/", "", $prop);
                    if (!is_null($tmp)) {
                        $prop = $tmp;
                    }
                    if (array_key_exists($prop, $properties)) {
                        $properties[$prop][] = $value;
                    } else {
                        $properties[$prop] = [$value];
                    }
                } else {
                    $hash[strtoupper($attr)] = $value;
                }
            }
        }
        if ($properties !== []) {
            $hash["PROPERTY"] = $properties;
        }
        return $hash;
    }
}
