<?php

require __DIR__ . '/../../vendor/autoload.php';

$user = getenv('RTLDEV_MW_CI_USER_CNR');
$rolepassword = getenv('RTLDEV_MW_CI_ROLEPASSWORD_CNR');
$role = getenv('RTLDEV_MW_CI_ROLE_CNR');

$loopno = 10;

if ($user === false) {
    die("Please provide environment variable RTLDEV_MW_CI_USER_CNR.\n");
}
if ($role === false) {
    die("Please provide environment variable RTLDEV_MW_CI_ROLE_CNR.\n");
}
if ($rolepassword === false) {
    die("Please provide environment variables RTLDEV_MW_CI_ROLEPASSWORD_CNR.\n");
}

$cl = \CNIC\ClientFactory::getClient([
    "registrar" => "CNR" // fka RRPproxy
]);
$cl->useOTESystem() //LIVE System would be used otherwise by default
    ->setRoleCredentials($user, $role, $rolepassword);

// --- SESSIONLESS API COMMUNICATION ---
echo "--- SESSION-LESS API COMMUNICATION ----\n";

$start = microtime(true);
$i = 0;
while ($i < $loopno) {
    $i++;
    echo "########### Iteration $i (NOSESSION) ###########\n";
    $r = $cl->request([
        "COMMAND" => "StatusAccount"
    ]);
    print_r($r->getCommandPlain());
    print_r($r->getPlain());
    echo "################################################\n\n";
}
$cl->close();

$end1 = microtime(true) - $start;
echo "Time: $end1 seconds\n";

// --- SESSION BASED API COMMUNICATION ---
echo "--- SESSION-BASED API COMMUNICATION ----\n";

$cl = \CNIC\ClientFactory::getClient([
    "registrar" => "CNR" // fka RRPproxy
]);
$cl->useOTESystem() //LIVE System would be used otherwise by default
    ->setRoleCredentials($user, $role, $rolepassword);
$r = $cl->login();
if ($r->isSuccess()) {
    echo "LOGIN SUCCEEDED.\n";

    $start = microtime(true);
    $i = 0;
    while ($i < $loopno) {
        $i++;
        echo "########### Iteration $i (SESSION) ###########\n";
        $r = $cl->request([
            "COMMAND" => "StatusAccount"
        ]);
        print_r($r->getCommandPlain());
        print_r($r->getPlain());
        echo "###############################################\n\n";
    }

    // Perform session close and logout and closing curl handle(s)
    $r = $cl->logout();
    if ($r->isSuccess()) {
        echo "LOGOUT SUCCEEDED.\n";
    } else {
        echo "LOGOUT FAILED.\n";
    }
} else {
    echo "LOGIN FAILED.\n";
    echo "NOTE: Session-based communication not yet correctly supported for RRPproxy.\n";
}

$end2 = microtime(true) - $start;
echo "Time: $end2 seconds\n\n";

echo "number of requests tested:   #$loopno\n";
echo "sessionless communication:   $end1 seconds\n";
echo "session based communication: $end2 seconds\n";
