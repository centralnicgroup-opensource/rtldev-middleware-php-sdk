# Migration Guide

This guide explains how to upgrade the **`centralnic-reseller/php-sdk`** (namespace `CNIC\`) across its major versions, step by step, with before/after code.

Semantic versioning applies: **only major bumps (`X.0.0`) can break your code.** Minor and patch releases are backward compatible — you can take them freely. The per-release detail (including every fix and feature) lives in [HISTORY.md](HISTORY.md); this document focuses only on the changes that require you to _do something_ when upgrading.

> **Golden rule:** never skip straight to the newest major without reading every intervening major section below. Breaking changes accumulate — a call that was fine in v15 may have moved twice by v19. Upgrade one major at a time, run your test suite between each, and only then move to the next.

---

## Version compatibility at a glance

| From → To | PHP required | Headline breaking change                                                          | Consumer action                                                                                                |
| --------- | ------------ | --------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| → v9.0.0  | **8.1+**     | PHP 8.1 minimum                                                                   | Bump your runtime                                                                                              |
| → v10.0.0 | 8.1+         | cURL handle cached/reused                                                         | Call `close()` in sessionless flows                                                                            |
| → v11.0.0 | 8.1+         | IBS + Moniker brands added                                                        | None (additive)                                                                                                |
| → v12.0.0 | 8.1+         | HEXONET brand removed (EOL)                                                       | Migrate off HEXONET                                                                                            |
| → v13.0.0 | 8.1+         | IBS/Moniker switched to JSON API                                                  | Re-test IBS/Moniker data handling                                                                              |
| → v14.0.0 | **8.3+**     | Some classes `final`; `getPOSTData()` no longer takes a string                    | Bump runtime; stop subclassing finals                                                                          |
| → v15.0.0 | 8.3+         | Logger contract; IBS session methods removed                                      | Retype loggers; guard session calls                                                                            |
| → v16.0.0 | 8.3+         | `ClientFactory::getClient()` signature slimmed                                    | Configure the client yourself                                                                                  |
| → v17.0.0 | 8.3+         | `getNextPageNumber()` returns `null` on last page                                 | Handle the `null` sentinel                                                                                     |
| → v18.0.0 | 8.3+         | CNR-only response methods moved off `ResponseInterface`                           | Narrow via `ExtendedResponseInterface`                                                                         |
| → v19.0.0 | 8.3+         | `getClient()` removed; `setRoleCredentials()` moved                               | Use `cnr()`/`ibs()`/`moniker()`                                                                                |
| → v20.0.0 | 8.3+         | IBS/Moniker no longer force IPv4; `getColumnKeys()` declares its `bool` parameter | Set `CURLOPT_IPRESOLVE` yourself if your host needs it; add the parameter if you implement `ResponseInterface` |
| → v21.0.0 | 8.3+         | `setExtraCurlOptions()` now reaches the wire; transport-owned options throw       | Audit what you pass it — options previously ignored now take effect, and seven now raise                       |
| → v22.0.0 | 8.3+         | Sessions are CNR-only by type; IBS/Moniker `SessionClient` deleted                | Drop `setSession()`/`getSession()` calls on IBS/Moniker; retype to `IBS\Client`/`MONIKER\Client`               |

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

> **This last paragraph is true of v19–v21 only, and v22 reverses half of it.** `getSession()`/`setSession()` were _not_ harmless there: on IBS/Moniker they forwarded to null-object stubs, so `setSession("x")` looked accepted and was discarded. They moved to `CNR\Client` in v22 — see [→ v22.0.0](#-v2200). `useHighPerformanceConnectionSetup()` genuinely is brand-agnostic and stays put. The text above is kept as it stood so a v18 → v19 upgrade still reads correctly.

---

## → v20.0.0 — IBS/Moniker no longer force IPv4; `ResponseInterface` matches its implementation

Three changes. **If you use IBS or Moniker, read change 1 — it is the only one that alters runtime behaviour, and it is the one that can affect a working integration.** Changes 2 and 3 are contract corrections on `ResponseInterface` that only matter if you implement that interface yourself.

### 1. IBS/Moniker no longer force IPv4 name resolution

**What changed:** the IBS and Moniker clients used to seed a brand-default cURL option forcing IPv4 (`CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4`) on every request. That default is gone; all brands now start with an empty cURL option bag.

It existed as a workaround for a small number of customers whose hosts had trouble reaching the API over IPv6. The library is known to work without it, and hard-coding one network's workaround into every integration is the wrong altitude — choosing a resolution mode belongs to the caller.

**What to respect:** if your host genuinely needs forced IPv4 — for example you saw IPv6 connect timeouts against the IBS/Moniker API before — set it explicitly after constructing the client:

```php
$client = \CNIC\ClientFactory::ibs();          // or ::moniker()
$client->setExtraCurlOptions([CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
```

`setExtraCurlOptions()` (added in v19 via RSRMID-2913) is the supported way to do this. **Be aware of its actual reach:** an option only reaches the wire if the transport does not already set that key itself. `CURLOPT_IPRESOLVE` is not one it sets, so the snippet above genuinely takes effect — but the transport's own values win on collision, so these are silently ignored when passed this way:

`CURLOPT_URL`, `CURLOPT_POST`, `CURLOPT_POSTFIELDS`, `CURLOPT_RETURNTRANSFER`, `CURLOPT_HEADER`, `CURLOPT_SSL_VERIFYPEER`, `CURLOPT_SSL_VERIFYHOST`, `CURLOPT_TIMEOUT`, `CURLOPT_CONNECTTIMEOUT`, `CURLOPT_USERAGENT`, `CURLOPT_HTTPHEADER`.

The first five keep the request envelope intact (overriding them would break response handling) and the next two stop this setter being used to weaken TLS verification. For the user agent, use `setUserAgent()`. **In v20 the request timeout has no public setter at all** — it comes from a protected `$socketTimeout` (300s) and `CURLOPT_TIMEOUT` passed here is discarded, so on this version it cannot be changed from outside the SDK. That was a gap rather than an intended limit; an earlier revision of this guide suggested a `setSocketTimeout()` method that did not yet exist at the time.

> **Everything in this sub-section describes v20 only, and v21 changes it.** The silent discard was the defect and was fixed there: your options now reach the wire, seven of the eleven keys above raise instead of being ignored, and the timeout gap is closed with a real `setSocketTimeout()`. If you are upgrading past v20, read [→ v21.0.0](#-v2100) and treat that as current. This text is kept as it stood so a v19 → v20 upgrade still reads correctly.

**One subtlety worth knowing:** an option you set this way is _caller_ state, so `resetCurlOptions()` discards it — whereas the old forced IPv4 was a brand default and survived a reset. If you call `resetCurlOptions()`, re-apply your options afterwards.

**How to tell whether this affects you:** it only matters if your host resolves the API hostname to an IPv6 address _and_ that path is broken. If your integration works today on a dual-stack or IPv4-only host, expect no change. If in doubt, add the option — it is a no-op on an IPv4-only host.

The remaining two changes are contract corrections on `CNIC\ResponseInterface`, both consequences of the same underlying problem: the interface had drifted out of step with the class that implements it. **Neither affects callers — only code that implements the interface itself.**

### 2. `getColumnKeys()` declares its `bool` parameter

**What changed:** `CNIC\ResponseInterface::getColumnKeys()` was declared without a parameter, while the implementation (`AbstractResponse::getColumnKeys(bool $filterPaginationKeys = false)`) has always accepted one. The interface now matches the implementation:

```php
// BEFORE (≤ v19) — in ResponseInterface
public function getColumnKeys(): array;

// AFTER (v20)
public function getColumnKeys(bool $filterPaginationKeys = false): array;
```

**Nothing changes at runtime.** No behaviour was altered — this is a contract correction, and `false` remains the default, so every existing call site keeps working unchanged.

**What to respect — two cases:**

**1. You only _call_ the SDK (the overwhelmingly common case): no action.** In fact this _fixes_ things for you. The project asks consumers to type against `CNIC\ResponseInterface` rather than the concrete `CNIC\CNR\Response`. Before v20 that advice conflicted with itself: stripping pagination columns was impossible for an interface-typed consumer without a static-analysis error, even though the call ran correctly.

```php
use CNIC\ResponseInterface;

function renderColumns(ResponseInterface $r): array
{
    // ≤ v19: ran fine, but PHPStan/Psalm rejected it —
    //   "Method CNIC\ResponseInterface::getColumnKeys() invoked with 1 parameter, 0 required."
    // The usual workarounds were to retype the parameter to the concrete
    // CNR\Response (losing brand-neutrality) or to silence the analyser.
    // v20: simply legal.
    return $r->getColumnKeys(true);
}
```

If you worked around it by typing against a concrete Response or by suppressing the error, you can now drop the workaround and go back to the interface.

**2. You _implement_ `ResponseInterface` yourself — add the parameter.** This is the breaking part. A custom implementation (or a test double) declaring the old no-argument signature is no longer compatible with the interface and will raise a fatal error at declaration time:

```php
// BEFORE (≤ v19)
final class MyResponse implements \CNIC\ResponseInterface
{
    public function getColumnKeys(): array { /* ... */ }
}

// AFTER (v20) — add the parameter and honour it
final class MyResponse implements \CNIC\ResponseInterface
{
    public function getColumnKeys(bool $filterPaginationKeys = false): array
    {
        // Return every column key when false; strip your pagination/metadata
        // columns when true. Extending CNIC\AbstractResponse instead gives you
        // a correct implementation for free.
    }
}
```

Extending `CNIC\AbstractResponse` (as `CNR\Response` and `IBS\Response` do) requires no change — it already had the parameter.

### 3. `__construct()` is no longer declared on the interface

**What changed:** `ResponseInterface` used to declare a constructor:

```php
// BEFORE (≤ v19)
public function __construct(string $raw, array $cmd, array $ph = []);

// AFTER (v20) — removed from the interface entirely
```

**What to respect: nothing, unless you _reflect_ on the interface** (see the caveat at the end of this sub-section). No call you make and no class you write needs changing.

The declaration never did anything. PHP exempts constructors from signature-compatibility checks, so it constrained no implementer — which is exactly how it drifted: it declared 3 parameters while `AbstractResponse::__construct()` had grown a 4th (`$context`), and nothing complained for as long as that was true. Removing it is a _relaxation_ of the contract, so no existing implementation stops satisfying the interface.

Nor does construction move: it was never done through this type. Responses are built by the brand factory hooks — `AbstractClient::newResponse()` and `AbstractResponseTemplateManager::createResponse()` — each of which instantiates its own concrete `Response`. The interface now describes only what a response can be _asked_, which is all a consumer ever needs from it.

```php
// Unchanged, and still the supported way to obtain a response:
$r = $cl->request(["COMMAND" => "StatusAccount"]);   // returns ResponseInterface

// If you build responses directly, keep naming the concrete class — as the SDK does:
$r = new \CNIC\CNR\Response($raw);
```

Verified rather than assumed: instantiating through a `class-string<ResponseInterface>` with either 3 or 4 arguments passes PHPStan level 9 and Psalm level 1 identically before and after this change.

**The one caveat — reflection on the interface itself.** If you inspect `ResponseInterface` reflectively (a DI container autowiring by interface, a doc generator, a test that asserts the contract), the constructor is now absent:

```php
$ctor = (new ReflectionClass(\CNIC\ResponseInterface::class))->getConstructor();
// ≤ v19: ReflectionMethod
// v20:   null   <-- ->getParameters() on this now raises a TypeError
```

Guard the call (`if ($ctor !== null)`) or reflect on the concrete `CNIC\CNR\Response` / `CNIC\IBS\Response`, which is where the real constructor has always lived. This is the only way the change can be observed from outside the SDK.

---

## → v21.0.0 — `setExtraCurlOptions()` reaches the wire; transport-owned options throw

**Read this if you call `setExtraCurlOptions()`.** If you do not, nothing here affects you: the default transport behaviour is unchanged, and the new timeout setter is purely additive.

### 1. Your cURL options now take effect

**What changed:** `setExtraCurlOptions()` used to be a half-truth. The value you passed landed in the client's option bag — so it read back correctly, and looked applied — and was then silently dropped one layer lower, because the transport merged its own defaults _over_ yours. Eleven keys never reached cURL. The order is reversed: **your options now win over the transport's defaults.**

The clearest case: you asked for a 5s timeout and got 300s, with no exception, no warning, and nothing to inspect that would tell you.

```php
$client->setExtraCurlOptions([CURLOPT_TIMEOUT => 5]);

// ≤ v20: the request still ran with the transport's 300s. Silently.
// v21:   the request times out after 5s, as asked.
```

Keys that were ignored before and now apply: `CURLOPT_TIMEOUT`, `CURLOPT_CONNECTTIMEOUT`, `CURLOPT_USERAGENT`, `CURLOPT_HTTPHEADER`.

**What to respect:** audit what you pass. This is the risk in this upgrade — an option you set long ago, saw no effect from, and left in place will now start doing what it says. That is especially worth checking for `CURLOPT_TIMEOUT` (a short value that was harmless while ignored can start aborting slow commands) and `CURLOPT_CONNECTTIMEOUT`. Options that already worked — `CURLOPT_IPRESOLVE`, `CURLOPT_PROXY`, `CURLOPT_REFERER`, `CURLOPT_CAINFO` and everything else the transport does not set — behave exactly as before.

### 2. Seven options now raise instead of being ignored

**What changed:** the options the transport genuinely must own are rejected loudly rather than discarded. Passing any of them to `setExtraCurlOptions()` raises `CNIC\Exception\UnsupportedFeatureException` on the next request, naming the offending constants:

```php
$client->setExtraCurlOptions([CURLOPT_RETURNTRANSFER => 0]);
$client->request(["COMMAND" => "StatusAccount"]);
// v21: CNIC\Exception\UnsupportedFeatureException
//      "cURL option(s) owned by CNIC\HttpTransport cannot be overridden:
//       CURLOPT_RETURNTRANSFER. ..."
```

The set is `CURLOPT_URL`, `CURLOPT_POST`, `CURLOPT_POSTFIELDS`, `CURLOPT_RETURNTRANSFER`, `CURLOPT_HEADER`, `CURLOPT_SSL_VERIFYPEER`, `CURLOPT_SSL_VERIFYHOST` — readable programmatically as `array_keys(CNIC\HttpTransport::PROTECTED_OPTIONS)` (the constant maps each option to its constant name).

The first five define the request envelope the response parser is written against (`CURLOPT_RETURNTRANSFER => 0`, for instance, makes `curl_exec()` return `true` and leaves the parser with nothing). The last two are TLS verification: disabling certificate checking is a deliberate, security-relevant act and is not something a generic convenience bag should be able to do. Both were already impossible — the difference is that you now find out.

**What to respect:** if you were passing one of these, remove it. It was never having any effect, so removing it changes nothing about how your integration behaves — it only stops the exception. If you believe you need one, that is a conversation for an issue, not a workaround.

**Where the exception surfaces:** at request time, not at `setExtraCurlOptions()` time, and before any connection is attempted. Wrap your first request in a smoke test after upgrading if you set options dynamically.

### 3. `CURLOPT_HTTPHEADER` appends instead of replacing

**What changed:** custom headers now reach the wire (they were previously ignored), and they are **appended** to the transport's header list rather than replacing it.

```php
$client->setExtraCurlOptions([
    CURLOPT_HTTPHEADER => ["X-Correlation-Id: " . $requestId],
]);
// v21 wire headers: Expect:, Content-Type, Content-Length, Connection, X-Correlation-Id
// ≤ v20: your header was dropped entirely.
```

**What to respect:** the transport's own four header lines — `Expect:`, `Content-Type`, `Content-Length`, `Connection` — are its to set, and restating one raises `UnsupportedFeatureException` (names matched case-insensitively):

```php
$client->setExtraCurlOptions([CURLOPT_HTTPHEADER => ["Content-Type: application/json"]]);
$client->request([/* ... */]);
// v21: CNIC\Exception\UnsupportedFeatureException
//      "HTTP header(s) owned by CNIC\HttpTransport cannot be overridden: content-type. ..."
```

This follows the same rule as the protected options above: `Content-Type` and `Content-Length` are derived from the POST body, and `Connection` follows from the reused cURL handle, so overriding one corrupts the request exactly as overriding `CURLOPT_POSTFIELDS` would. Silently letting a wrong `Content-Length` through would be the same class of defect this release is fixing, and appending a second `Content-Type` would just move the decision to the server. Add your own headers freely; leave those four alone.

### 4. New: the request timeout finally has a setter

**What changed (additive, nothing breaks):** `setSocketTimeout()` and `getSocketTimeout()` on the client.

Before v21 the request timeout could not be changed from outside the SDK **at all** — it came from a protected `$socketTimeout` (300s) with only a getter, the SocketConfig has no public accessor on the client, and the `CURLOPT_TIMEOUT` route was the one being discarded. There was no supported way to shorten or lengthen a request.

```php
$client = \CNIC\ClientFactory::cnr();
$client->setSocketTimeout(30);        // seconds; 0 means no timeout, per cURL
echo $client->getSocketTimeout();     // 30

$client->setSocketTimeout(-1);        // CNIC\Exception\InvalidConfigurationException
```

Prefer this over `setExtraCurlOptions([CURLOPT_TIMEOUT => …])` — it states the intent rather than the mechanism. Both work; if you set both, the cURL option bag wins.

A negative value is rejected rather than forwarded: cURL refuses it by returning `false` from `curl_setopt()`, which `curl_setopt_array()` does not surface, so passing it on would drop the setting with no signal — the very thing this release exists to stop. `CNIC\Exception\InvalidConfigurationException` is new in v21 and extends `CNIC\Exception\CnicException`, so existing `catch (\Exception)` code keeps catching it.

> A note if you read the v20 documentation: it briefly told callers to use `setSocketTimeout()` for timeouts, at a point when no such method existed. That was corrected in the v20 line, and the method is real as of v21.

---

## → v22.0.0 — API sessions are CNR-only, enforced by type

**Read this if you build an IBS or Moniker client, or if you name either brand's `SessionClient` type.** CNR code is unaffected: every session method is still there, on the same client, with the same behaviour and the same wire format.

### 1. `getSession()` / `setSession()` are gone from IBS and Moniker

**What changed:** both methods lived on the shared `AbstractClient` and were therefore inherited by every brand. They now live on **`CNIC\CNR\Client`** only.

They were never functional on IBS/Moniker. Those platforms have no API session concept, so the methods forwarded to null-object stubs on `AbstractSocketConfig` that stored nothing. Because `setSession()` is fluent, it returned `$this` and looked accepted:

```php
$cl = \CNIC\ClientFactory::ibs();

// ≤ v21: chainable, no error, no warning — and the value went nowhere.
$cl->setSession("abc123")->request([/* ... */]);
var_dump($cl->getSession());   // NULL, always

// v22: Error — Call to undefined method CNIC\IBS\Client::setSession()
```

That shipped for six majors (v15–v21). The v19 guide called the pair "harmless"; it was not harmless, it was just quiet.

**What to respect:** the failure mode is a **fatal `Error`**, not an exception — `try/catch (\Exception)` will **not** catch it, exactly as with the v15 session-method removal. Delete the calls; they were doing nothing. If you have brand-agnostic code that called them speculatively, narrow to the brand that has sessions:

```php
// BEFORE — called on whatever client was to hand
$cl->setSession($storedSessionId);

// AFTER — narrow first (a plain instanceof; there is no session interface)
if ($cl instanceof \CNIC\CNR\Client) {
    $cl->setSession($storedSessionId);
}
```

On the normal path you need no check at all: `ClientFactory::cnr()` returns a fully-typed `CNR\SessionClient`, so `setSession()` is simply there.

### 2. `IBS\SessionClient` and `MONIKER\SessionClient` are deleted

**What changed:** both classes were empty subclasses named after a lifecycle their platform does not have — since v15 they added no method at all. They are removed, and the factory returns the plain brand client:

```php
// BEFORE (v19–v21)
$cl = ClientFactory::ibs();      // -> CNIC\IBS\SessionClient
$cl = ClientFactory::moniker();  // -> CNIC\MONIKER\SessionClient

// AFTER (v22)
$cl = ClientFactory::ibs();      // -> CNIC\IBS\Client
$cl = ClientFactory::moniker();  // -> CNIC\MONIKER\Client
```

`CNIC\CNR\SessionClient` is untouched and remains what `cnr()` returns — it is now the only `SessionClient` in the SDK.

**What to respect — two cases:**

- **You name the type** in a property, parameter, return type, `instanceof` or `use` statement: retype to `CNIC\IBS\Client` / `CNIC\MONIKER\Client`. Grep for the two class names rather than waiting for a crash — a property or parameter typed against the deleted class raises a `TypeError` the moment a real client is assigned to it, but **`instanceof` against a class that no longer exists silently evaluates to `false`**, so a brand check written that way just stops matching. PHPStan/Psalm flag both.
- **You obtain the client from the factory and never spell the type** (the common case): no action. The API surface of what you get back is identical apart from the two session methods in change 1.

**The brand clients stay extensible.** `CNIC\MONIKER\Client` is the only class in the SDK that nothing internal extends any more, and sealing it would have been easy — but `CNR\Client` and `IBS\Client` are open only because something inside the SDK happens to extend them, which is no basis for letting extensibility differ per brand. If you subclass any brand client, that keeps working.

### 3. `AbstractSocketConfig` lost five accessors

**What changed:** `getSession()`, `setSession()`, `getPersistent()`, `setPersistent()` and `getRoleSeparator()` (plus the `$roleSeparator` property) are no longer on `CNIC\AbstractSocketConfig`. They exist only on `CNIC\CNR\SocketConfig`. Relatedly, the `persistent=1` request parameter is now emitted by CNR's own `getPOSTDataParams()` rather than by the shared `getPOSTData()`.

**What to respect:** nothing, unless you **subclass `AbstractSocketConfig`** yourself — the client never exposes its config object publicly, so there is no call path to these from outside the SDK. If you do subclass it and were overriding or calling one of the five, note that the base no longer declares it: an `#[\Override]` attribute on such a method is now a compile-time error, and the shared `getPOSTData()` is purely the encoding step (every parameter, brand-specific ones included, comes from `getPOSTDataParams()`).

**The encoded request body is unchanged.** CNR appends `persistent=1` last, so the bytes on the wire are byte-identical to v21 — asserted directly in the test suite. Nothing about CNR's session handshake moved.

One note for anyone **subclassing `CNR\Client`**: PHP typed properties are invariant, so the inherited `$socketConfig` stays declared as `AbstractSocketConfig`, and the session and role-credential methods narrow it through a single new protected accessor, `cnrConfig()`. Nothing is required of you — `newSocketConfig()` is typed to return the `final CNR\SocketConfig`, so an override cannot supply anything else, and the accessor's `instanceof` guard exists to satisfy static analysis rather than to describe a reachable failure.

### Why the methods are absent rather than throwing

This is a deliberate reversal of a v19 sub-decision, and it settles a policy the two preceding majors disagreed on. v19 chose silent no-ops for a capability a brand cannot honour and called them harmless. v21 chose the opposite for cURL options — `UnsupportedFeatureException`, "rather than being ignored". v22 picks the answer that is better than either where the type system can express it:

- **Prefer absence.** A method that does not exist is a static-analysis error at the call site — you find out while writing the code, not from a support ticket about a session that was never established.
- **Throw where absence is impossible** — where the method must exist on a shared surface for other reasons. That is the v21 case.
- **Never no-op.** A fluent setter that discards its argument is indistinguishable from one that works.

The practical consequence for you: brand capability differences now show up in your editor and in PHPStan/Psalm output, rather than at runtime or not at all.

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
