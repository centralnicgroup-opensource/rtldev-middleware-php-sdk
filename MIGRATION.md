# Migration Guide

This guide explains how to upgrade the **`centralnic-reseller/php-sdk`** (namespace `CNIC\`) across its major versions, step by step, with before/after code.

Semantic versioning applies: **only major bumps (`X.0.0`) can break your code.** Minor and patch releases are backward compatible — you can take them freely. The per-release detail (including every fix and feature) lives in [HISTORY.md](HISTORY.md); this document focuses only on the changes that require you to _do something_ when upgrading.

> **Golden rule:** never skip straight to the newest major without reading every intervening major section below. Breaking changes accumulate — a call that was fine in v15 may have moved twice by v19. Upgrade one major at a time, run your test suite between each, and only then move to the next.

---

## Version compatibility at a glance

| From → To | PHP required | Headline breaking change                                       | Consumer action                        |
| --------- | ------------ | -------------------------------------------------------------- | -------------------------------------- |
| → v9.0.0  | **8.1+**     | PHP 8.1 minimum                                                | Bump your runtime                      |
| → v10.0.0 | 8.1+         | cURL handle cached/reused                                      | Call `close()` in sessionless flows    |
| → v11.0.0 | 8.1+         | IBS + Moniker brands added                                     | None (additive)                        |
| → v12.0.0 | 8.1+         | HEXONET brand removed (EOL)                                    | Migrate off HEXONET                    |
| → v13.0.0 | 8.1+         | IBS/Moniker switched to JSON API                               | Re-test IBS/Moniker data handling      |
| → v14.0.0 | **8.3+**     | Some classes `final`; `getPOSTData()` no longer takes a string | Bump runtime; stop subclassing finals  |
| → v15.0.0 | 8.3+         | Logger contract; IBS session methods removed                   | Retype loggers; guard session calls    |
| → v16.0.0 | 8.3+         | `ClientFactory::getClient()` signature slimmed                 | Configure the client yourself          |
| → v17.0.0 | 8.3+         | `getNextPageNumber()` returns `null` on last page              | Handle the `null` sentinel             |
| → v18.0.0 | 8.3+         | CNR-only response methods moved off `ResponseInterface`        | Narrow via `ExtendedResponseInterface` |
| → v19.0.0 | 8.3+         | `getClient()` removed; `setRoleCredentials()` moved            | Use `cnr()`/`ibs()`/`moniker()`        |

Two things to respect throughout:

- **Runtime floor vs. language ceiling.** The current runtime floor is **PHP 8.3** (CI tests 8.3 / 8.4 / 8.5). Run on newer PHP freely.
- **Type against interfaces, not concretes.** The clean upgrade path is to depend on `CNIC\ResponseInterface`, `CNIC\LoggerInterface`, etc. Code that reaches for concrete classes (`CNIC\CNR\Response`) or `method_exists()` fallbacks is what breaks across majors.

---

## → v9.0.0 — PHP 8.1 minimum

**What changed:** the SDK now requires **PHP 8.1 or higher**.

**What to respect:** this is purely a runtime bump — there is no API change. Upgrade your PHP runtime (and CI matrix) to 8.1+ before pulling v9.

```jsonc
// composer.json
"require": {
    "php": ">=8.1"
}
```

---

## → v10.0.0 — cURL handle is cached and reused

**What changed:** the client now caches its cURL handle and reuses it across requests for performance, instead of opening/closing a connection per call.

**What to respect:** in a **sessionless** flow you must explicitly release the connection when you are done, by calling `close()`. In a **session-based** flow this is handled for you — `logout()` already closes the connection.

```php
// Sessionless — BEFORE v10: connection was torn down automatically per request.
// AFTER v10: close() the client when finished.
$cl = ClientFactory::cnr();
$cl->useOTESystem()->setCredentials($user, $password);
$r = $cl->request(["COMMAND" => "StatusAccount"]);
$cl->close();   // <-- release the cached handle

// Session-based — no change needed: logout() closes the connection for you.
$cl->login();
// ...
$cl->logout();
```

---

## → v11.0.0 — Internet.bs (IBS) and Moniker (MONIKER) added

**What changed:** two new registrar brands were added — **Internet.bs** (`IBS`) and **Moniker** (`MONIKER`).

**What to respect:** this is **additive**. Existing single-brand code keeps working unchanged. If you now want to talk to more than one brand, this is the version that makes it possible — see the factory sections below for how brand selection evolved.

---

## → v12.0.0 — HEXONET brand removed (end of life)

**What changed:** support for the **HEXONET** brand was removed following its platform shutdown.

**What to respect:** if you still construct a HEXONET client, you must migrate. CNR (CentralNic Reseller, formerly RRPproxy) is the successor platform for that traffic. If you genuinely still need a HEXONET connection during a transition window, **pin to `^11`** until you have fully migrated — v12+ cannot talk to HEXONET at all.

```jsonc
// Stay on v11 ONLY while you still require HEXONET:
"require": { "centralnic-reseller/php-sdk": "^11" }
```

---

## → v13.0.0 — IBS / Moniker switched to the JSON API

**What changed:** the **IBS** and **Moniker** brands now speak the JSON API / response format instead of the previous format. **CNR is unaffected.**

**What to respect:** the **data structure of responses changed** for IBS and Moniker. Any code that reads specific keys/columns out of an IBS or Moniker response must be re-tested and, in places, adjusted. Drive your IBS/Moniker integration through a full test pass before shipping.

If you only use CNR, this major is a no-op for your code.

---

## → v14.0.0 — PHP 8.3 minimum, `final` classes, `getPOSTData()` tightened

**What changed:**

1. Minimum PHP is now **8.3**.
2. Several classes are now declared `final`.
3. `getPOSTData()` no longer accepts a **string** input (typed input only).

**What to respect:**

- Bump your runtime to **8.3+**.
- If you **subclassed** any SDK class, check it is not now `final` — extend by composition or type against the relevant interface instead. This is a good moment to stop depending on concrete classes altogether.
- If you called `getPOSTData()` with a string, pass the typed structure it now expects.

```php
// If you extended an SDK class that became final, prefer composition + interfaces:
final class MyLogger implements \CNIC\LoggerInterface { /* ... */ }
```

---

## → v15.0.0 — Logger contract + IBS session methods removed

Two independent breaking changes land here.

### 1. Logger implementations must type the response as `ResponseInterface`

**What changed:** custom loggers must type their response argument as `CNIC\ResponseInterface` — **not** the concrete `CNIC\CNR\Response`. `ResponseInterface` now exposes `getContext()`, so you can read logger context through the shared contract with no `method_exists()` fallback and no concrete-class dependency.

**What to respect:** update every custom logger (including WHMCS `cnic`/`ibs` loggers) to the new signature.

```php
// BEFORE
public function log(string $post, \CNIC\CNR\Response $r, ?string $error = null): void

// AFTER
public function log(string $post, \CNIC\ResponseInterface $r, ?string $error = null): void
```

### 2. IBS `SessionClient` no longer has session methods

**What changed:** `IBS\SessionClient` no longer defines `login()`, `logout()`, `saveSession()` or `reuseSession()`. Previously these existed but threw `\Exception("Method not supported")`.

**What to respect:** calling one of these on an IBS/Moniker client now raises a **fatal `Error` ("Call to undefined method")**, which a `try/catch (\Exception)` will **not** catch. If your code supports multiple brands, guard session calls with an `instanceof` check before calling them.

```php
// BEFORE — relying on a catchable exception:
try {
    $cl->login();
} catch (\Exception $e) { /* unsupported on IBS */ }

// AFTER — narrow to the brand that actually has sessions:
if ($cl instanceof \CNIC\CNR\SessionClient) {
    $cl->login();
}
```

---

## → v16.0.0 — `ClientFactory::getClient()` signature slimmed

**What changed:** `ClientFactory::getClient()` no longer accepts a `$params` array or a `$logger` argument. Its signature became `getClient(string $registrar)`. It also stopped decoding your password internally.

**What to respect:** you now **configure the returned client yourself** via its fluent setters, and you must **decode credentials yourself** before handing them over (the SDK is transport-faithful — it sends exactly what you give it).

```php
// BEFORE (≤ v15)
$cl = ClientFactory::getClient([
    "registrar"   => "CNR",
    "username"    => $user,
    "password"    => $pw,      // was html_entity_decode()'d internally
    "sandbox"     => true,
    "proxyserver" => $proxy,
], $logger);

// AFTER (v16)
$cl = ClientFactory::getClient("CNR");
$cl->useOTESystem()
    ->setCredentials($user, html_entity_decode($pw, ENT_QUOTES))  // decode yourself
    ->setProxy($proxy)
    ->setCustomLogger($logger);
```

> `getClient(string)` is itself removed in **v19** — see below. If you are jumping v15 → v19, you can move straight to the typed constructors and skip the string form.

---

## → v17.0.0 — `getNextPageNumber()` returns `null` on the last page

**What changed:** `getNextPageNumber()` now returns **`null`** when there is no next page, instead of clamping to the current (last) page number.

**What to respect:** anywhere you consumed the old clamped value, either switch to `hasNextPage()` or handle the `null` sentinel explicitly. A naive `while` loop that trusted the old behaviour could otherwise loop forever or dereference `null`.

```php
// BEFORE — last page returned its own number (clamped)
$next = $r->getNextPageNumber();

// AFTER — prefer the boolean guard
while ($r->hasNextPage()) {
    $next = $r->getNextPageNumber();   // guaranteed non-null inside the guard
    $r = $cl->requestNextResponsePage($r);
}

// ...or handle null directly
$next = $r->getNextPageNumber();
if ($next !== null) { /* fetch next page */ }
```

---

## → v18.0.0 — CNR-only response methods moved off `ResponseInterface`

**What changed:** five methods are **no longer part of `CNIC\ResponseInterface`** because they are CNR-specific telemetry/status:

`getQueuetime()`, `getRuntime()`, `isTmpError()`, `isPending()`, `getListHash()`

They now live on **`CNIC\ExtendedResponseInterface`** (implemented by CNR only). Also, `IBS\Response` no longer extends `CNR\Response`, and `IBS\Record` no longer extends `CNR\Record` — the brands are now siblings.

**What to respect:**

- Code holding a **concrete `CNR\Client` / `CNR\Response`** is **unaffected** — the methods are still right there.
- Code holding the **core `ResponseInterface`** (e.g. the generic `request()` return type, or brand-agnostic code) must **narrow** before calling them.

```php
$r = $cl->request(["COMMAND" => "StatusAccount"]);   // typed as ResponseInterface

// BEFORE — worked on any response
$queue = $r->getQueuetime();

// AFTER — narrow to the CNR-only capability first
if ($r instanceof \CNIC\ExtendedResponseInterface) {
    $queue = $r->getQueuetime();
    if ($r->isPending()) { /* ... */ }
}
```

---

## → v19.0.0 — Typed factory constructors; `setRoleCredentials()` relocated

The current major. Two related breaking changes.

### 1. `ClientFactory::getClient(string)` removed — use typed constructors

**What changed:** the string-dispatch `ClientFactory::getClient(string $registrar)` is **gone**, along with the `Registrar` enum and `UnknownRegistrarException`. In their place are three **typed named constructors**, each returning the concrete brand `SessionClient`:

```php
// BEFORE (v16–v18)
$cl = ClientFactory::getClient("CNR");
$cl = ClientFactory::getClient("IBS");
$cl = ClientFactory::getClient("MONIKER");

// AFTER (v19)
$cl = ClientFactory::cnr();      // -> CNIC\CNR\SessionClient
$cl = ClientFactory::ibs();      // -> CNIC\IBS\SessionClient
$cl = ClientFactory::moniker();  // -> CNIC\MONIKER\SessionClient
```

**What to respect:** the return types are now **precise**, so brand-specific capabilities are available directly with no narrowing on the normal path. `cnr()` gives you `login()`/`logout()`/`saveSession()`/`setRoleCredentials()` straight away; `ibs()`/`moniker()` simply don't expose session/role methods (they don't exist on those platforms). If you had a `switch` on a registrar string or the `Registrar` enum, replace it with a direct call to the right constructor.

### 2. `setRoleCredentials()` moved to `RoleCredentialsInterface` (CNR only)

**What changed:** `setRoleCredentials()` was **removed from the shared `AbstractClient`** and now lives on the CNR-only **`CNIC\RoleCredentialsInterface`**. (Inheriting it on IBS/Moniker would have forged an invalid login, since it depends on the CNR role separator.)

**What to respect:**

- If you build the client via `ClientFactory::cnr()`, you get a concrete `CNR\SessionClient` and can call `setRoleCredentials()` **directly** — no change needed.
- If you hold a client through the shared `AbstractClient`/generic type, **narrow first**.

```php
// Normal path — cnr() is fully typed, call it directly:
$cl = ClientFactory::cnr();
$cl->useOTESystem()
   ->setRoleCredentials($accountId, $roleId, $rolePassword);

// Generic/brand-agnostic code — narrow via the interface:
if ($cl instanceof \CNIC\RoleCredentialsInterface) {
    $cl->setRoleCredentials($accountId, $roleId, $rolePassword);
}
```

`getSession()` / `setSession()` / `useHighPerformanceConnectionSetup()` deliberately **remain** on `AbstractClient` (they are harmless and brand-agnostic) — they did not move.

---

## Reference: the canonical v19 usage

Bringing it together, here is idiomatic current-version code for each brand:

```php
use CNIC\ClientFactory;

// --- CNR (CentralNic Reseller, fka RRPproxy) ---
$cl = ClientFactory::cnr();
$cl->useOTESystem()                       // omit for LIVE (the default)
   ->setCredentials($user, $password);    // or ->setRoleCredentials($acct, $role, $pw)
$r = $cl->request(["COMMAND" => "StatusAccount"]);
if ($r->isSuccess()) {
    print_r($r->getHash());
}
$cl->close();                             // release the cached cURL handle

// --- IBS / Moniker (JSON API; no sessions, no roles) ---
$cl = ClientFactory::ibs();               // or ClientFactory::moniker()
$cl->useOTESystem()->setCredentials($user, $password);
$r = $cl->request([/* ... */]);
$cl->close();
```

For working, runnable examples per brand see [`examples/app_CNR.php`](examples/app_CNR.php), [`examples/app_IBS.php`](examples/app_IBS.php) and [`examples/app_MONIKER.php`](examples/app_MONIKER.php).

---

## Upgrade checklist

1. **Read every major section** between your current version and the target — do not skip.
2. **Bump one major at a time.** After each bump, run your test suite before moving on.
3. **Match the PHP floor** for your target (8.1 for v9–v13, **8.3** for v14+).
4. **Type against interfaces** (`ResponseInterface`, `ExtendedResponseInterface`, `RoleCredentialsInterface`, `LoggerInterface`) rather than concrete classes — this is what keeps future majors from breaking you.
5. **Re-test brand data handling** if you use IBS/Moniker across the v13 JSON switch.
6. **Decode your own credentials** (v16+) and **`close()` sessionless clients** (v10+).
7. Consult [HISTORY.md](HISTORY.md) for the exhaustive per-release change list.
