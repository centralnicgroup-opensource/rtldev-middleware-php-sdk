<?php

declare(strict_types=1);

use CNIC\ClientFactory;
use CNIC\IBS\Client;

require __DIR__ . '/../vendor/autoload.php';

$user = getenv('RTLDEV_MW_CI_USER_IBS');
$password = getenv('RTLDEV_MW_CI_USERPASSWORD_IBS');

if ($user === false || $password === false) {
    echo "Please provide environment variables RTLDEV_MW_CI_USER_IBS and RTLDEV_MW_CI_USERPASSWORD_IBS.\n";
    exit(1);
}

// --- SESSIONLESS API COMMUNICATION ---
echo "--- SESSION-LESS API COMMUNICATION ----\n\n";
$cl = ClientFactory::getClient("IBS");
// The factory returns the shared CNIC\AbstractClient contract. The per-request
// endpoint path (request($cmd, $path)) is IBS/Moniker-specific, so narrow to
// the concrete Client before using it — the SDK guarantees the IBS arm is one.
assert($cl instanceof Client);
$cl->useOTESystem()//LIVE System would be used otherwise by default
   ->setCredentials($user, $password)
   ->enableDebugMode();
$r = $cl->request(["domain" => "tronexats.com"], "Domain/Info");
$cl->close(); // close connection(s) to the API
echo $r->getPlain();
echo print_r($r->getHash(), true);

// --- SESSION BASED API COMMUNICATION ---
echo "\n\n--- SESSION-BASED API COMMUNICATION ----\n";
echo "-> Not supported for this brand.\n";
