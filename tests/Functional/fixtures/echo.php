<?php

declare(strict_types=1);

/**
 * CNICTEST\Functional
 * Copyright © CentralNic Group PLC
 *
 * Router script for the PHP built-in server used by HttpTransportTest.
 * Echoes selected request details back as JSON so the test can assert which
 * per-call cURL options actually reached the wire (notably the Referer header).
 */

header("Content-Type: application/json");

$referer = $_SERVER["HTTP_REFERER"] ?? "";
$ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
$body = file_get_contents("php://input");

echo (string) json_encode([
    "referer" => $referer,
    "ua" => $ua,
    "body" => is_string($body) ? $body : "",
]);
