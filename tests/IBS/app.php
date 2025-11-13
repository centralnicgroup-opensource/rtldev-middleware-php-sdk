<?php

require __DIR__ . '/../../vendor/autoload.php';

$user = getenv('RTLDEV_MW_CI_USER_IBS');
$password = getenv('RTLDEV_MW_CI_USERPASSWORD_IBS');

if ($user === false || $password === false) {
    echo "Please provide environment variables RTLDEV_MW_CI_USER_IBS and RTLDEV_MW_CI_USERPASSWORD_IBS.\n";
    exit(1);
}

// --- SESSIONLESS API COMMUNICATION ---
echo "--- SESSION-LESS API COMMUNICATION ----\n\n";
$cl = \CNIC\ClientFactory::getClient([
    "registrar" => "IBS"
]);
$cl->useOTESystem()//LIVE System would be used otherwise by default
   //->setRemoteIPAddress("1.2.3.4") // provide ip address used for active ip filter
   ->setCredentials($user, $password)
   ->enableDebugMode();
$r = $cl->request(["domain" => "tronexats.com"], "Domain/Info");
$cl->close(); // close connection(s) to the API
echo $r->getPlain();
echo print_r($r->getHash(), true);

// --- SESSION BASED API COMMUNICATION ---
echo "\n\n--- SESSION-BASED API COMMUNICATION ----\n";
echo "-> Not supported for this brand.\n";
