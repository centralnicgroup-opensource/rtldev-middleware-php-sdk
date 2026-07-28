<?php

declare(strict_types=1);

/**
 * CNICTEST\Functional
 * Copyright © Team Internet Group PLC
 *
 * Router script for the PHP built-in server used by HttpTransportTest.
 * Echoes selected request details back as JSON so the test can assert which
 * per-call cURL options actually reached the wire — the Referer header, the
 * user agent, and (since RSRMID-2919) the full request header set, which is
 * what proves the CURLOPT_HTTPHEADER merge semantics.
 *
 * A "?delay=<seconds>" query parameter stalls the response before any output,
 * so a test can assert that a caller-supplied CURLOPT_TIMEOUT actually aborts
 * the request instead of being silently discarded.
 */

$rawDelay = $_GET["delay"] ?? null;
$delay = is_string($rawDelay) && is_numeric($rawDelay) ? (float) $rawDelay : 0.0;
if ($delay > 0) {
    usleep((int) round($delay * 1000000.0));
}

header("Content-Type: application/json");

$referer = $_SERVER["HTTP_REFERER"] ?? "";
$ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
$body = file_get_contents("php://input");

// Rebuild the request headers from $_SERVER rather than relying on
// getallheaders(), which is not guaranteed across SAPIs. Names are lower-cased
// so assertions do not depend on how the header was cased on the wire.
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (!is_string($value) || !str_starts_with($key, "HTTP_")) {
        continue;
    }
    $headers[strtolower(str_replace("_", "-", substr($key, 5)))] = $value;
}
foreach (["CONTENT_TYPE" => "content-type", "CONTENT_LENGTH" => "content-length"] as $src => $dst) {
    if (isset($_SERVER[$src]) && is_string($_SERVER[$src])) {
        $headers[$dst] = $_SERVER[$src];
    }
}

echo (string) json_encode([
    "referer" => $referer,
    "ua" => $ua,
    "body" => is_string($body) ? $body : "",
    "headers" => $headers,
]);
