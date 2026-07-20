<?php

declare(strict_types=1);

use CNIC\ClientFactory;
use CNIC\MONIKER\Client;

require __DIR__ . '/../vendor/autoload.php';

$user = getenv('RTLDEV_MW_CI_USER_MONIKER');
$password = getenv('RTLDEV_MW_CI_USERPASSWORD_MONIKER');

if ($user === false || $password === false) {
    echo "Please provide environment variables RTLDEV_MW_CI_USER_MONIKER and RTLDEV_MW_CI_USERPASSWORD_MONIKER.\n";
    exit(1);
}

// --- SESSIONLESS API COMMUNICATION ---
echo "--- SESSION-LESS API COMMUNICATION ----\n\n";
$cl = ClientFactory::getClient("MONIKER");
// The factory returns the shared CNIC\AbstractClient contract. The per-request
// endpoint path (request($cmd, $path)) is IBS/Moniker-specific, so narrow to
// the concrete Client before using it — the SDK guarantees the Moniker arm is one.
assert($cl instanceof Client);
$cl->useOTESystem()//LIVE System would be used otherwise by default
   //->setRemoteIPAddress("1.2.3.4") // provide ip address used for active ip filter
   ->setCredentials($user, $password)
   ->enableDebugMode();
$r = $cl->request(["tld" => "nl"], "Domain/Tldinfo");
$cl->close(); // close connection(s) to the API
#print_r($r->getPlain());

// --- SESSION BASED API COMMUNICATION ---
echo "\n\n--- SESSION-BASED API COMMUNICATION ----\n";
echo "-> Not supported for this brand.\n";
