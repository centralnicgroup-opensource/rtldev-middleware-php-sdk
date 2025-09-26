<?php

require __DIR__ . '/../../vendor/autoload.php';

$user = getenv('RTLDEV_MW_CI_USER_CNR');
$password = getenv('RTLDEV_MW_CI_USERPASSWORD_CNR');

if ($user === false || $password === false) {
    echo "Please provide environment variables RTLDEV_MW_CI_USER_CNR and RTLDEV_MW_CI_USERPASSWORD_CNR.\n";
    exit(1);
}
// --- SESSIONLESS API COMMUNICATION ---
echo "--- SESSION-LESS API COMMUNICATION ----\n";
$cl = \CNIC\ClientFactory::getClient([
    "registrar" => "CNR" // fka RRPproxy
]);
$cl->useOTESystem() //LIVE System would be used otherwise by default
    ->setCredentials($user, $password);
$r = $cl->request([
    "COMMAND" => "StatusAccount"
]);
$cl->close(); // close connection(s) to the API
print_r($r->getHash());

// --- SESSION BASED API COMMUNICATION ---
echo "--- SESSION-BASED API COMMUNICATION ----\n";
$cl = \CNIC\ClientFactory::getClient([
    "registrar" => "CNR" // fka RRPproxy
]);
$cl->useOTESystem() //LIVE System would be used otherwise by default
    ->setCredentials($user, $password);
$r = $cl->login();
if ($r->isSuccess()) {
    echo "LOGIN SUCCEEDED.\n";

    // Now reuse the created API session for further requests
    // You don't have to care about anything!
    $r = $cl->request([
        "COMMAND" => "StatusAccount"
    ]);
    print_r($r->getHash());

    // Perform session close and logout
    $r = $cl->logout(); // it covers $cl->close() as well
    if ($r->isSuccess()) {
        echo "LOGOUT SUCCEEDED.\n";
    } else {
        echo "LOGOUT FAILED.\n";
    }
} else {
    echo "LOGIN FAILED.\n";
    echo "NOTE: Session-based communication not yet correctly supported for RRPproxy.";
}
