<?php

require __DIR__ . '/../../vendor/autoload.php';

$user = getenv('CNR_TEST_USER');
$password = getenv('CNR_TEST_PASSWORD');

if ($user === false || $password === false) {
    echo "Please provide environment variables CNR_TEST_USER and CNR_TEST_PASSWORD.\n";
    exit(1);
}
// --- SESSIONLESS API COMMUNICATION ---
echo "--- SESSION-LESS API COMMUNICATION ----\n";
$cl = \CNIC\ClientFactory::getClient([
    "registrar" => "CNR" // fka RRPproxy
]);
$cl->useOTESystem()//LIVE System would be used otherwise by default
   ->setCredentials($user, $password);
$r = $cl->request([
    "COMMAND" => "StatusAccount"
]);
print_r($r->getHash());

// --- SESSION BASED API COMMUNICATION ---
echo "--- SESSION-BASED API COMMUNICATION ----\n";
$cl = \CNIC\ClientFactory::getClient([
    "registrar" => "CNR" // fka RRPproxy
]);
$cl->useOTESystem()//LIVE System would be used otherwise by default
   ->setCredentials($user, $password);
$r = $cl->login();
// or this line for using 2FA
// $r = $cl->login('.. here your otp code ...');
if ($r->isSuccess()) {
    echo "LOGIN SUCCEEDED.\n";

    // Now reuse the created API session for further requests
    // You don't have to care about anything!
    $r = $cl->request([
        "COMMAND" => "StatusAccount"
    ]);
    print_r($r->getHash());

    // Perform session close and logout
    $r = $cl->logout();
    if ($r->isSuccess()) {
        echo "LOGOUT SUCCEEDED.\n";
    } else {
        echo "LOGOUT FAILED.\n";
    }
} else {
    echo "LOGIN FAILED.\n";
    echo "NOTE: Session-based communication not yet correctly supported for RRPproxy.";
}
