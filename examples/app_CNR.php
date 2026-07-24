<?php

declare(strict_types=1);

use CNIC\ClientFactory;

require __DIR__ . '/../vendor/autoload.php';

// --- AUTHENTICATION ---
// CNR supports two credential styles:
//
//   1. Account login  — the plain account id together with the account
//      password (a single-user, full-access login).
//
//   2. Role login      — a so-called "User" in the CNR Web Interface. The
//      login is a string composed of the CNR account id, a ":" separator and
//      the role/user id (e.g. "myaccount:myrole"); authentication then uses
//      that role user's *own* password, not the account password. Roles let
//      you grant scoped, per-user access under one account.
//
// This example uses the role login. A customer may equally use the account
// login by passing the account id and account password instead.
$account = getenv('RTLDEV_MW_CI_USER_CNR');
$role = getenv('RTLDEV_MW_CI_ROLE_CNR');
$password = getenv('RTLDEV_MW_CI_ROLEPASSWORD_CNR');

if ($account === false || $role === false || $password === false) {
    echo "Please provide environment variables RTLDEV_MW_CI_USER_CNR, RTLDEV_MW_CI_ROLE_CNR and RTLDEV_MW_CI_ROLEPASSWORD_CNR.\n";
    exit(1);
}
// Compose the role login "<account id>:<role id>".
$user = $account . ":" . $role;
// --- SESSIONLESS API COMMUNICATION ---
echo "--- SESSION-LESS API COMMUNICATION ----\n";
$cl = ClientFactory::cnr(); // fka RRPproxy
$cl->useOTESystem() //LIVE System would be used otherwise by default
    ->setCredentials($user, $password);
$r = $cl->request([
    "COMMAND" => "StatusAccount"
]);
$cl->close(); // close connection(s) to the API
print_r($r->getHash());

// --- SESSION-BASED API COMMUNICATION (saveSession / reuseSession) ---
// A SessionClient logs in once and reuses the resulting API session for further
// requests. In a stateless web app (e.g. WHMCS) every HTTP request is a *fresh*
// PHP process, so you cannot keep the logged-in $cl object around. Instead:
//   1. log in once, then saveSession() the credentials + session id into $_SESSION;
//   2. on every following request, build a fresh client and reuseSession() from
//      $_SESSION to talk to the API without logging in again.
// Below we simulate two separate requests using one $store array in place of
// the real $_SESSION superglobal.
echo "--- SESSION-BASED API COMMUNICATION ----\n";
$store = []; // stands in for $_SESSION

// ---- Request #1: log in and persist the session -------------------------
// cnr() returns a fully-typed CNR\SessionClient, so CNR-specific session
// handling (login/logout/saveSession) is available directly — no narrowing.
$cl = ClientFactory::cnr(); // fka RRPproxy
$cl->useOTESystem() //LIVE System would be used otherwise by default
    ->setCredentials($user, $password);
$r = $cl->login();
if ($r->isSuccess()) {
    echo "LOGIN SUCCEEDED.\n";
    // Persist login + session id into $_SESSION for the next request to pick up.
    $cl->saveSession($store);
    $cl->close(); // this PHP request ends; the object is gone, the session lives on
    echo "SESSION SAVED (id: " . ($store["socketcfg"]["session"] ?? "n/a") . ").\n";

    // ---- Request #2: a brand-new process rebuilds from $_SESSION --------
    $cl = ClientFactory::cnr();
    // No login() and no password needed — reuseSession() restores the account
    // login and the session id straight from $_SESSION. Point at the same
    // system the session was created on (OT&E here).
    $cl->useOTESystem()
        ->reuseSession($store);
    $r = $cl->request([
        "COMMAND" => "StatusAccount"
    ]);
    if ($r->isSuccess()) {
        echo "SESSION REUSED SUCCESSFULLY (no re-login).\n";
        print_r($r->getHash());
    } else {
        echo "SESSION REUSE FAILED (session may have expired).\n";
    }

    // Done for good: log out to invalidate the shared session.
    $r = $cl->logout();
    echo $r->isSuccess() ? "LOGOUT SUCCEEDED.\n" : "LOGOUT FAILED.\n";
} else {
    echo "LOGIN FAILED.\n";
}
