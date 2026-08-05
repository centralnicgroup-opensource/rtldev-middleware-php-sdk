# Migration Guide

This guide explains how to upgrade the **`centralnic-reseller/php-sdk`** (namespace `CNIC\`) across its major versions, step by step, with before/after code.

Semantic versioning applies: **only major bumps (`X.0.0`) can break your code.** Minor and patch releases are backward compatible — you can take them freely. The per-release detail (including every fix and feature) lives in [HISTORY.md](HISTORY.md); this document focuses only on the changes that require you to _do something_ when upgrading.

> **Golden rule:** never skip straight to the newest major without reading every intervening major section below. Breaking changes accumulate — a call that was fine in v15 may have moved twice by v19. Upgrade one major at a time, run your test suite between each, and only then move to the next.

---

## Version compatibility at a glance

| From → To | PHP required | Headline breaking change                                                                                                          | Consumer action                                                                                                                                                                                    |
| --------- | ------------ | --------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| → v9.0.0  | **8.1+**     | PHP 8.1 minimum                                                                                                                   | Bump your runtime                                                                                                                                                                                  |
| → v10.0.0 | 8.1+         | cURL handle cached/reused                                                                                                         | Call `close()` in sessionless flows                                                                                                                                                                |
| → v11.0.0 | 8.1+         | IBS + Moniker brands added                                                                                                        | None (additive)                                                                                                                                                                                    |
| → v12.0.0 | 8.1+         | HEXONET brand removed (EOL)                                                                                                       | Migrate off HEXONET                                                                                                                                                                                |
| → v13.0.0 | 8.1+         | IBS/Moniker switched to JSON API                                                                                                  | Re-test IBS/Moniker data handling                                                                                                                                                                  |
| → v14.0.0 | **8.3+**     | Some classes `final`; `getPOSTData()` no longer takes a string                                                                    | Bump runtime; stop subclassing finals                                                                                                                                                              |
| → v15.0.0 | 8.3+         | Logger contract; IBS session methods removed                                                                                      | Retype loggers; guard session calls                                                                                                                                                                |
| → v16.0.0 | 8.3+         | `ClientFactory::getClient()` signature slimmed                                                                                    | Configure the client yourself                                                                                                                                                                      |
| → v17.0.0 | 8.3+         | `getNextPageNumber()` returns `null` on last page                                                                                 | Handle the `null` sentinel                                                                                                                                                                         |
| → v18.0.0 | 8.3+         | CNR-only response methods moved off `ResponseInterface`                                                                           | Narrow via `ExtendedResponseInterface`                                                                                                                                                             |
| → v19.0.0 | 8.3+         | `getClient()` removed; `setRoleCredentials()` moved                                                                               | Use `cnr()`/`ibs()`/`moniker()`                                                                                                                                                                    |
| → v20.0.0 | 8.3+         | IBS/Moniker no longer force IPv4; `getColumnKeys()` declares its `bool` parameter                                                 | Set `CURLOPT_IPRESOLVE` yourself if your host needs it; add the parameter if you implement `ResponseInterface`                                                                                     |
| → v21.0.0 | 8.3+         | `setExtraCurlOptions()` now reaches the wire; transport-owned options throw                                                       | Audit what you pass it — options previously ignored now take effect, and seven now raise                                                                                                           |
| → v22.0.0 | 8.3+         | Sessions are CNR-only by type; IBS/Moniker `SessionClient` deleted                                                                | Drop `setSession()`/`getSession()` calls on IBS/Moniker; retype to `IBS\Client`/`MONIKER\Client`                                                                                                   |
| → v23.0.0 | 8.3+         | Connection configuration has one home; `getSystem()` is nullable                                                                  | Handle `null` from `getSystem()`; move `CURLOPT_TIMEOUT`/`USERAGENT`/`PROXY`/`REFERER` to their own setters                                                                                        |
| → v24.0.0 | 8.3+         | CNR IDN command rewriting moved off the shared client into its own module                                                         | Nothing, unless you called or overrode `autoIDNConvert()`, or read/set `needsIDNConvert`                                                                                                           |
| → v25.0.0 | 8.3+         | One shared `Record` and `Column`; the brand `Record`/`IBS\Column` classes removed                                                 | Retype `CNR\Record`/`IBS\Record`/`AbstractRecord` → `CNIC\Record`, and `IBS\Column` → `CNIC\Column`                                                                                                |
| → v26.0.0 | 8.3+         | Response parsing is an injectable seam; `ResponseParser::parse()` is no longer static                                             | Call `(new ResponseParser())->parse(…)`; implement `newResponseParser()` in a custom Response/TemplateManager                                                                                      |
| → v27.0.0 | 8.3+         | Loggers `format()` a record and a sink writes it; `setDefaultLogger()` removed                                                    | Rename your `log()` body to `format()` and `return` the string; extend `CNIC\AbstractLogger`                                                                                                       |
| → v28.0.0 | 8.3+         | IBS/Moniker hash dates keep `/`; `RecordInterface`/`ColumnInterface` gained a date accessor; `IBS\Response::getStatus()` removed  | Accept `/` wherever you parsed a `getHash()`/`getPlain()`/`getListHash()` date; add the new method if you implement either interface directly; read `getHash()["status"]` instead of `getStatus()` |
| → v29.0.0 | 8.3+         | Public method parameters and six protected properties renamed to be self-describing                                               | Nothing, unless you pass named arguments (`getColumn(key: …)` → `columnName:`), implement an SDK interface (match the parameter names), or subclass and read `$this->pw`/`$ua`/`$curlopts`         |
| → v30.0.0 | 8.3+         | Transport error is a declared `?string $error` parameter, not a `"httperror\|"` prefix on the raw payload; `nocurl` template gone | Add the parameter if you override `newResponse()`/`translate()`; return `["", $error]` (not bytes) on failure if you implement `TransportInterface`                                                |
| → v31.0.0 | 8.3+         | `Response` is sealed after construction: the two mutators and the four record-cursor methods are off `ResponseInterface`          | Replace `getNextRecord()` loops with `foreach ($r as $rec)` (it yields the first row too) and `getCurrentRecord()` with `getRecord(0)`; take `populate()`'s three new arguments if you subclass    |

Two things to respect throughout:

- **Runtime floor vs. language ceiling.** The current runtime floor is **PHP 8.3** (CI tests 8.3 / 8.4 / 8.5). Run on newer PHP freely.
- **Type against interfaces, not concretes.** The clean upgrade path is to depend on `CNIC\ResponseInterface`, `CNIC\LoggerInterface`, etc. Code that reaches for concrete classes (`CNIC\CNR\Response`) or `method_exists()` fallbacks is what breaks across majors.

---

<a id="-v900"></a>
<a id="-v1000"></a>
<a id="-v1100"></a>
<a id="-v1200"></a>
<a id="-v1300"></a>

## → v9.0.0 – v13.0.0 — the pre-8.3 majors

These five majors predate the current PHP floor, so if you are still on one of them you are also on an end-of-life runtime — plan to land on v14+ (PHP 8.3) in the same effort. Four things need action; the rest was additive.

- **v9.0.0 — PHP 8.1 minimum.** A runtime bump with no API change: move your runtime and CI matrix to 8.1+ before pulling v9.
- **v10.0.0 — the cURL handle is cached and reused** across requests instead of being opened per call. In a **sessionless** flow you must now release it yourself with `close()` when you are done. Session-based flows need no change — `logout()` closes the connection for you.

  ```php
  $cl = ClientFactory::cnr();
  $cl->useOTESystem()->setCredentials($user, $password);
  $r = $cl->request(["COMMAND" => "StatusAccount"]);
  $cl->close();   // <-- release the cached handle
  ```

- **v11.0.0 — Internet.bs (`IBS`) and Moniker (`MONIKER`) added.** Purely additive; existing single-brand code is unaffected.
- **v12.0.0 — the HEXONET brand was removed** following that platform's shutdown, and v12+ cannot talk to it at all. CNR (formerly RRPproxy) is the successor platform. If you genuinely still need a HEXONET connection during a transition window, pin `"centralnic-reseller/php-sdk": "^11"` until you have migrated off it.
- **v13.0.0 — IBS/Moniker moved to the JSON API**, which **changed the data structure of their responses**. Any code reading specific keys or columns out of an IBS/Moniker response must be re-tested and in places adjusted. CNR is unaffected, so CNR-only integrations can treat this major as a no-op.

---

<a id="-v1400"></a>

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

<a id="-v1500"></a>

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

<a id="-v1600"></a>

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

<a id="-v1700"></a>

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

<a id="-v1800"></a>

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

<a id="-v1900"></a>

## → v19.0.0 — Typed factory constructors; `setRoleCredentials()` relocated

Two related breaking changes.

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

<a id="-v2000"></a>

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

The first five keep the request envelope intact (overriding them would break response handling) and the next two stop this setter being used to weaken TLS verification. For the user agent, use `setUserAgent()`. **In v20 the request timeout has no public setter at all** — it comes from a protected `$socketTimeout` (300s) and `CURLOPT_TIMEOUT` passed here is discarded, so on this version it cannot be changed from outside the SDK. That was a gap rather than an intended limit, and v21 closes it.

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

**1. You only _call_ the SDK (the overwhelmingly common case): no action**, and the correction _fixes_ something for you. Before v20, an interface-typed consumer could not call `$r->getColumnKeys(true)` without PHPStan/Psalm rejecting it (`invoked with 1 parameter, 0 required`), even though the call ran correctly — so the advice to type against `ResponseInterface` conflicted with itself. If you worked around that by retyping to the concrete `CNR\Response` or suppressing the error, drop the workaround and go back to the interface.

**2. You _implement_ `ResponseInterface` yourself — add the parameter.** This is the breaking part: a custom implementation or test double still declaring `getColumnKeys(): array` is no longer compatible with the interface and raises a fatal error at declaration time. Honour the flag (return every column key when `false`, strip your pagination/metadata columns when `true`), or extend `CNIC\AbstractResponse` — as `CNR\Response`/`IBS\Response` do — and inherit a correct implementation.

### 3. `__construct()` is no longer declared on the interface

**What changed:** `ResponseInterface` used to declare `__construct(string $raw, array $cmd, array $ph = [])`. It is gone from the interface entirely.

**What to respect: nothing, unless you _reflect_ on the interface.** Removal only relaxes the contract, so every existing implementation still satisfies it, and construction has not moved — responses are built by the brand factory hooks (`AbstractClient::newResponse()`, `AbstractResponseTemplateManager::createResponse()`), each naming its own concrete `Response`. If you build one directly, keep naming the concrete class: `new \CNIC\CNR\Response($raw)`.

The one observable effect is reflective. If you inspect the interface (a DI container autowiring by interface, a doc generator, a contract test), the constructor is now absent:

```php
$ctor = (new ReflectionClass(\CNIC\ResponseInterface::class))->getConstructor();
// ≤ v19: ReflectionMethod
// v20:   null   <-- ->getParameters() on this now raises a TypeError
```

Guard the call (`if ($ctor !== null)`) or reflect on the concrete `CNIC\CNR\Response` / `CNIC\IBS\Response`, which is where the real constructor has always lived. Why the interface is the wrong place to describe construction: see the interface-declaration entry in [docs/agents/architecture.md](docs/agents/architecture.md). (Ref: RSRMID-2918.)

---

<a id="-v2100"></a>

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

---

<a id="-v2200"></a>

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

> **`cnrConfig()` was renamed in v23** to the public, covariant `getSocketConfig()`. See [→ v23.0.0](#-v2300).

### Why the methods are absent rather than throwing

This is a deliberate reversal of a v19 sub-decision, and it settles a policy the two preceding majors disagreed on. v19 chose silent no-ops for a capability a brand cannot honour and called them harmless. v21 chose the opposite for cURL options — `UnsupportedFeatureException`, "rather than being ignored". v22 picks the answer that is better than either where the type system can express it:

- **Prefer absence.** A method that does not exist is a static-analysis error at the call site — you find out while writing the code, not from a support ticket about a session that was never established.
- **Throw where absence is impossible** — where the method must exist on a shared surface for other reasons. That is the v21 case.
- **Never no-op.** A fluent setter that discards its argument is indistinguishable from one that works.

The practical consequence for you: brand capability differences now show up in your editor and in PHPStan/Psalm output, rather than at runtime or not at all.

---

<a id="-v2300"></a>

## → v23.0.0 — connection configuration has one home

**What changed:** connection configuration was split between the client and its `SocketConfig`, with nothing keeping the two copies in step. It now lives entirely on the `SocketConfig`, reachable through a new public `getSocketConfig()`. Three defects went with the split; each is now impossible to express rather than fixed.

Everything you call on the client still exists — `setURL()`, `useOTESystem()`, `setProxy()`, `setCredentials()` and the rest are unchanged forwarders, and `$cl->useOTESystem()->setCredentials($u, $p)` is still the idiomatic call. Four narrower things changed, below.

### 1. `getSystem()` returns `?System` — the system is derived from the URL

**What changed:** the client no longer stores which system you selected. It derives it by comparing the configured URL against the brand's OT&E and LIVE endpoints, so the two can no longer disagree. A URL that is neither returns `null`.

```php
// BEFORE (v22): the flag and the URL parted company, permanently
$cl->useOTESystem()->setURL("https://staging.example/");
$cl->isOTE();      // true   <- wrong; requests go to staging.example
$cl->getSystem();  // System::OTE

// AFTER (v23)
$cl->useOTESystem()->setURL("https://staging.example/");
$cl->isOTE();      // false
$cl->getSystem();  // null   <- the SDK has no name for this endpoint
```

**What to respect:** if you read `getSystem()`, handle `null`. A `match` on it needs a default arm, and passing the result somewhere typed `System` is now a PHPStan/Psalm error — which is the point: the value was previously wrong rather than absent.

```php
// BEFORE
$label = $cl->getSystem()->value;

// AFTER — one of:
$label = $cl->getSystem()?->value ?? "CUSTOM";
$label = ($cl->getSystem() ?? System::LIVE)->value;
```

`isOTE()` still returns `bool` and needs no change, but its answer is now honest after a `setURL()`. If you were relying on it staying `true` across a custom URL, that reliance was on the defect.

### 2. High-performance routing is a flag, not a one-off URL rewrite

**What changed:** `useHighPerformanceConnectionSetup()` used to rewrite the stored URL to loopback once. It now records the intent and applies the rewrite whenever the URL is read.

Two consequences: your selected system survives it (previously the rewritten URL no longer matched the OT&E endpoint, so `isOTE()` was lost), and the routing survives a later `useOTESystem()`/`useLIVESystem()`/`setURL()` — routing through a local proxy is a statement about _how_ to reach the endpoint, not _which_ endpoint.

```php
// BEFORE (v22)
$cl->useOTESystem()->useHighPerformanceConnectionSetup();
$cl->getURL();   // http://127.0.0.1/
$cl->useLIVESystem();
$cl->getURL();   // https://api.rrpproxy.net/   <- routing silently undone

// AFTER (v23)
$cl->useOTESystem()->useHighPerformanceConnectionSetup();
$cl->isOTE();    // true (was effectively lost before)
$cl->useLIVESystem();
$cl->getURL();   // http://127.0.0.1/           <- still routed
```

**What to respect:** if you switched systems after enabling high-performance mode and depended on that switch turning it off, switch it on again after — or, better, enable it last. There is no disable method; construct a fresh client if you need one without it. Read the state with `$cl->getSocketConfig()->usesHighPerformanceConnectionSetup()`.

### 3. Four cURL options now raise instead of being set through the bag

**What changed:** `CURLOPT_TIMEOUT`, `CURLOPT_USERAGENT`, `CURLOPT_PROXY` and `CURLOPT_REFERER` each have a setter that owns them, so `setExtraCurlOptions()` refuses them with `UnsupportedFeatureException`, naming the setter to use. Previously the bag value quietly beat the setter on the wire while the getter kept reporting what the setter had stored — two answers behind one question.

```php
// BEFORE (v21–v22): worked, and silently outranked setSocketTimeout()/setProxy()
$cl->setExtraCurlOptions([
    CURLOPT_TIMEOUT   => 5,
    CURLOPT_PROXY     => "http://proxy.example:3128",
    CURLOPT_REFERER   => "https://my.app/",
    CURLOPT_USERAGENT => "MyPlatform/1.0",
]);

// AFTER (v23) — one setter each
$cl->setSocketTimeout(5)
   ->setProxy("http://proxy.example:3128")
   ->setReferer("https://my.app/")
   ->setUserAgent("MyPlatform", "1.0");
```

**What to respect:** grep your integration for those four constants. The rejection is **eager** — it throws from `setExtraCurlOptions()` itself, not on the next request — so a misconfiguration surfaces at setup. Every other option (`CURLOPT_CONNECTTIMEOUT`, `CURLOPT_IPRESOLVE`, `CURLOPT_HTTPHEADER`, …) is unaffected and still reaches the wire exactly as in v21. The transport's own protected set is unchanged too, and still rejected on the next request rather than at the setter — see [→ v21.0.0](#-v2100).

If you genuinely want the raw option rather than the intent, drive `CNIC\HttpTransport::post()` directly; it owns none of the four and accepts all of them.

### 4. `resetCurlOptions()` no longer forgets your proxy and referer

**What changed:** the proxy and the referer were stored as cURL bag keys, so `resetCurlOptions()` — whose job is restoring _option_ defaults — discarded them. They are separate state now.

```php
$cl->setProxy("http://proxy.example:3128")->resetCurlOptions();
$cl->getProxy();   // BEFORE: null      AFTER: "http://proxy.example:3128"
```

**What to respect:** if you were re-applying `setProxy()`/`setReferer()` after every `resetCurlOptions()`, that is now redundant but harmless. If you were relying on the reset to clear them, call `setProxy()`/`setReferer()` with no argument — the documented reset for both.

### For subclassers

- **`CNR\Client::cnrConfig()` (protected, v22) is now `getSocketConfig()` (public).** It is the covariant override of the base accessor, still the single point that narrows the invariant `$socketConfig` property to `CNR\SocketConfig`. Rename your calls; there is no alias, deliberately — two methods narrowing the same property would be two places to keep in step.
- **`AbstractClient::getDefaultCurlOpts()` moved to `AbstractSocketConfig`**, with the option bag it seeds. If you overrode it on a client subclass, move the override to your `SocketConfig` subclass. (No brand in the SDK overrides it, and the bar for doing so is a protocol-mandatory option — see [→ v20.0.0](#-v2000).)
- **`AbstractClient` no longer declares `$socketURL`, `$system` or `$curlopts`.** A subclass reading them gets an undefined-property error; read through `getSocketConfig()` instead.
- **`AbstractSocketConfig` now has a constructor** that seeds the active URL from `$liveUrl` and the option bag from `getDefaultCurlOpts()`. If your config subclass declares one, call `parent::__construct()` — without it the client starts with an empty URL.
- **`getUserAgent()` is a pure read.** It used to memoise the SDK default into `$ua` on first call; a subclass that inspected `$ua` to detect "has a UA been set" still works, and now gets the right answer.
- **`AbstractClient::executeCurl()` lost its third parameter.** `executeCurl(string $data, array $cfg, array $extraCurlOpts = [])` is now `executeCurl(string $data, array $cfg)`. Nothing in the SDK ever passed the third argument, and as an option route that skipped the managed-option check it was a way to put a second answer behind `getProxy()`. Configure the option before the request, or drive `HttpTransport::post()` yourself.
- **No `getOTEUrl()` was added on the client**, despite `getLiveUrl()` existing. Read it from `getSocketConfig()->getOTEUrl()`.

### One behaviour deliberately unchanged

`setCredentials()` still discards an active CNR session — a session and a password are alternative credentials on the wire and CNR treats the newer one as authoritative. That was true before and undocumented; it is now stated on the method and pinned by a test, because `reuseSession()` depends on the ordering (credentials first, session second).

---

<a id="-v2400"></a>

## → v24.0.0 — CNR IDN command rewriting is its own module

**What changed:** the SDK converts the IDN parameters of an outbound CNR command to punycode before sending it. Those rules used to live on the shared `AbstractClient`, as a protected `autoIDNConvert()` switched on by a `needsIDNConvert` flag on the `SocketConfig` — a flag only CNR ever set. They now live in `CNIC\CNR\IDNCommandRewriter`, called from `CNR\Client`'s own `buildCommand()` hook.

**If you just use the SDK, there is nothing to do.** The conversion is unchanged and still automatic — same parameters, same results, byte-identical requests on the wire. `IDNConvert()` is untouched:

```php
// Unchanged in v24: automatic on the request path…
$r = $cl->request(["COMMAND" => "StatusDomain", "OBJECTID" => "dömäin.com", "OBJECTCLASS" => "DOMAIN"]);
$r->getCommand()["OBJECTID"];   // "xn--dmin-moa0i.com"

// …and still available explicitly for a list of names you hold yourself.
$cl->IDNConvert(["münchen.de", "example.com"]);
```

Three things were removed. Each is only reachable from code that reached into the SDK's internals:

| Removed                                      | Was                                           | Now                                                                        |
| -------------------------------------------- | --------------------------------------------- | -------------------------------------------------------------------------- |
| `AbstractClient::autoIDNConvert()`           | `protected`, called by `performRequest()`     | `CNIC\CNR\IDNCommandRewriter::rewrite($cmd)` — `public static`             |
| `AbstractSocketConfig::getNeedsIDNConvert()` | `public`, answered `true` on CNR only         | nothing; the brand's `buildCommand()` decides, so there is no flag to read |
| `$needsIDNConvert` (property, all 3 configs) | `protected`, set `true` on `CNR\SocketConfig` | nothing                                                                    |

**What to respect** if you did any of the following:

- **You called the rules yourself** (via reflection, or from a subclass). Call the module directly — no client needed, which is the point of the change:

  ```php
  use CNIC\CNR\IDNCommandRewriter;

  // BEFORE (v23): reachable only through a client, and only via reflection
  $m = new \ReflectionMethod($cl, "autoIDNConvert");
  $m->setAccessible(true);
  $wire = $m->invoke($cl, ["NAMESERVER0" => "ns1.münchen.de"]);

  // AFTER (v24)
  $wire = IDNCommandRewriter::rewrite(["NAMESERVER0" => "ns1.münchen.de"]);
  ```

- **You overrode `autoIDNConvert()`** on a client subclass to change or disable the rewriting. The override is now dead code — nothing calls it. Override `buildCommand()` instead and choose there whether to call the rewriter:

  ```php
  final class MyClient extends \CNIC\CNR\Client
  {
      #[\Override]
      protected function buildCommand(array $cmd): array
      {
          // no IDN rewriting at all — or wrap the call, or add your own rules
          return \CNIC\CommandFormatter::flattenCommand($cmd);
      }
  }
  ```

- **You read `getNeedsIDNConvert()`** to find out whether a client converts. There is no flag to read; the answer is the brand. `$cl instanceof \CNIC\CNR\Client` is the honest test, and it is the one the SDK itself now makes structurally.

- **You set `$needsIDNConvert = true`** on your own `SocketConfig` subclass to get the conversion on IBS/Moniker. It never did anything useful — those platforms convert IDNs server-side, which is why the flag was false for them — and the property no longer exists, so PHP will report the declaration as unused rather than fail. If you have an IBS/Moniker command you believe needs client-side conversion, call `IDNCommandRewriter::rewrite()` on it yourself before passing it to `request()`, but check with support first.

**Why this happened:** see the IDN entry in [docs/agents/architecture.md](docs/agents/architecture.md) for the full decision record. (Ref: RSRMID-2922.)

---

<a id="-v2500"></a>

## → v25.0.0 — one shared `Record`, one shared `Column`

**What changed:** the record and column layer had one class per brand where the brands did not actually differ.

- `CNIC\CNR\Record` and `CNIC\IBS\Record` were byte-identical empty subclasses of `CNIC\AbstractRecord`. All three are gone, replaced by a single concrete **`CNIC\Record`**.
- `CNIC\IBS\Column` and `CNIC\CNR\Column` each implemented `ColumnInterface` independently, duplicating the length field, the constructor, `getKey()` and `getData()`. That body now lives once in a concrete **`CNIC\Column`**, which IBS/Moniker use directly — `CNIC\IBS\Column` is gone. `CNIC\CNR\Column` **stays**, reduced to the one thing that is genuinely CNR's: it binds the column's value type to `string` and narrows `getDataByIndex()` to `?string`.

**If you use the SDK through the interfaces, there is nothing to do.** `ResponseInterface` is unchanged; `getRecord()`/`getRecords()` still return `RecordInterface`, `getColumn()`/`getColumns()` still return `ColumnInterface`, and every method on them behaves identically:

```php
// Unchanged in v25
$rec = $r->getRecord(0);              // RecordInterface
$rec->getDataByKey("DOMAIN");
$col = $r->getColumn("DOMAIN");       // ColumnInterface
$col->getDataByIndex(0);
$col->length;
```

| Removed               | Replace with                                                 |
| --------------------- | ------------------------------------------------------------ |
| `CNIC\AbstractRecord` | `CNIC\Record` (concrete — instantiate or extend it directly) |
| `CNIC\CNR\Record`     | `CNIC\Record`                                                |
| `CNIC\IBS\Record`     | `CNIC\Record`                                                |
| `CNIC\IBS\Column`     | `CNIC\Column`                                                |

**What to respect** if you did any of the following:

- **You named a concrete record or column type** — in a type hint, an `instanceof`, or a `new`. Retype to the shared class:

  ```php
  // BEFORE (v24)
  use CNIC\CNR\Record;
  use CNIC\IBS\Column;

  function handle(Record $rec): void { /* … */ }
  if ($col instanceof Column) { /* … */ }

  // AFTER (v25)
  use CNIC\Column;
  use CNIC\Record;

  function handle(Record $rec): void { /* … */ }
  if ($col instanceof Column) { /* … */ }
  ```

  Better still, type against the interfaces — `RecordInterface` / `ColumnInterface` — which have not changed and will not move again. Note that `CNR\Column` now **extends** `CNIC\Column`, so `$col instanceof \CNIC\Column` is true for every brand, CNR included.

- **You extended `AbstractRecord`** to add your own record behaviour. Extend `CNIC\Record` instead — it is a plain concrete class, so nothing else about your subclass changes:

  ```php
  // BEFORE (v24)
  final class MyRecord extends \CNIC\AbstractRecord {}
  // AFTER (v25)
  final class MyRecord extends \CNIC\Record {}
  ```

- **You built a Response subclass with its own `newRecord()` hook.** The hook's declaration on the base is unchanged (`abstract protected function newRecord(array $h): RecordInterface`); only the class the built-in brands return has changed, so if you returned `CNR\Record`/`IBS\Record`, return `CNIC\Record`.

  Mind which class you extend, because return-type covariance binds you to the parent's declaration — this is not new, but the class it names has moved:

  - extending **`CNR\Response` or `IBS\Response`**: their `newRecord()` is declared `: Record`, so your override must return `CNIC\Record` or a subclass of it. Previously it had to return the brand's `CNR\Record`/`IBS\Record` or a subclass — the same constraint, pointing at a different class. To ship your own record type from here, extend `CNIC\Record` rather than implementing `RecordInterface` from scratch.
  - extending **`AbstractResponse`** directly (a new brand): the hook is only declared `: RecordInterface`, so any implementation of that interface is fair game. This is the seam for genuinely different record behaviour.

- **You relied on the generic type of a column.** `CNIC\Column` is templated on its value type (`@template TValue`). Static analysis will now infer `Column<string>` for CNR and `Column<mixed>` for IBS/Moniker, so passing a non-string array to a CNR column is a reported error where it previously was not. That is stricter than before, and only affects analysis — no runtime behaviour changed.

### Also in this release: `ColumnInterface` and `RecordInterface` no longer declare `__construct()`

**No action required — this only relaxes the contract**, so any class that satisfied either interface before still satisfies it. It is listed here because it is technically an interface change and you may notice it via reflection.

`ColumnInterface` declared `__construct(string $key, array $data)` and `RecordInterface` declared `__construct(array $data)`. Both are gone, following `ResponseInterface` in [→ v20.0.0](#-v2000). Nothing in the SDK constructs through these types — columns are built by each brand's `addColumn()` and records by its `newRecord()`, both naming a concrete class — so the declaration constrained implementers without giving callers anything. And it was a real constraint, not a decorative one: PHP fully enforces a constructor declared on an _interface_ (unlike one inherited from a parent class), so it ruled out, say, a column backed by a generator or a database cursor.

If you implement either interface, you may now give your class whatever constructor suits it:

```php
// Still valid, unchanged
final class MyColumn implements \CNIC\ColumnInterface
{
    public function __construct(string $key, array $data) { /* … */ }
    // …
}

// Now also valid — previously a declaration-time fatal
final class LazyColumn implements \CNIC\ColumnInterface
{
    public function __construct(private readonly string $key, private readonly \Closure $producer) {}
    // …
}
```

**Why this happened:** see the record/column entry in [docs/agents/architecture.md](docs/agents/architecture.md) for the full decision record. (Ref: RSRMID-2923.)

---

<a id="-v2600"></a>

## → v26.0.0 — response parsing is a seam

**What changed:** the step that turns a raw API response into its hash was a hard-wired static call (`RP::parse($this->raw)`) with a different signature per brand. It is now a contract, `CNIC\ResponseParserInterface`, with one signature, and the parser a Response uses can be replaced from the outside.

- **`parse()` is an instance method.** `CNIC\CNR\ResponseParser` and `CNIC\IBS\ResponseParser` still exist and still behave identically — they are now instantiable classes implementing the new interface instead of static utilities.
- **One signature for both brands:** `parse(string $raw, array $cmd = []): array`. CNR gained the second parameter and ignores it (its wire format is self-describing); IBS is unchanged.
- **New optional constructor argument on every Response:** `__construct(string $raw, array $cmd = [], array $ph = [], array $context = [], ?ResponseParserInterface $parser = null)`. Pass nothing and you get the brand's own parser, exactly as before.
- **New abstract hooks:** `AbstractResponse::newResponseParser()` and `AbstractResponseTemplateManager::newResponseParser()`. The latter **replaces** `AbstractResponseTemplateManager::parseResponse()`, which is gone.
- **New:** `AbstractResponseTemplateManager::resetTemplates()` restores a brand's built-in templates (additive).

**If you only use clients, responses and the interfaces, there is nothing to do.** Parsed output is byte-for-byte identical for every response the SDK handles — the cassette suite is the proof.

**What to respect** if you did any of the following:

- **You called `ResponseParser::parse()` directly.** Instantiate it:

  ```php
  // BEFORE (v25)
  use CNIC\IBS\ResponseParser as RP;
  $hash = RP::parse($raw, $cmd);

  // AFTER (v26)
  use CNIC\IBS\ResponseParser as RP;
  $hash = (new RP())->parse($raw, $cmd);
  ```

  Better still, type the variable as `CNIC\ResponseParserInterface` so either brand's parser — or your own — fits.

- **You wrote your own `Response` on top of `AbstractResponse`** (a new brand, or a customised one). Implement the new hook alongside `newRecord()`, and read the parser from `$this->parser` in `populate()` rather than naming a class:

  ```php
  // AFTER (v26)
  #[\Override]
  protected function newResponseParser(): \CNIC\ResponseParserInterface
  {
      return new MyResponseParser();
  }

  #[\Override]
  protected function populate(): void
  {
      $this->hash = $this->parser->parse($this->raw, $this->command);
      // … build columns/records from $this->hash
  }
  ```

  Using `$this->parser` is what makes the injected substitute take effect; hard-coding `new MyResponseParser()` inside `populate()` compiles and passes its tests, and silently closes the seam back up.

- **You supply a parser to a `CNR\Response`.** CNR columns are string-valued (`CNIC\CNR\Column` binds the shared column's value type to `string`), and the Response now checks that rather than inferring it from the concrete parser's return type. Each entry of your `PROPERTY` block must be a **list of strings**: a cell that is not a string, or an entry that is not a list, raises `CNIC\Exception\UnsupportedFeatureException` naming the column, instead of being silently dropped or coerced.

  ```php
  // Accepted
  return ["CODE" => "200", "PROPERTY" => ["DOMAIN" => ["example.com"]]];

  // Throws — not a list
  return ["CODE" => "200", "PROPERTY" => ["DOMAIN" => "example.com"]];
  // Throws — cell is not a string
  return ["CODE" => "200", "PROPERTY" => ["TOTAL" => [42]]];
  ```

  Omitting `PROPERTY` altogether is fine and unchanged — that is an ordinary CNR response with no columns. The stock CNR parser cannot produce a violating shape, so this only affects your own.

- **You extended `AbstractResponseTemplateManager`.** Replace your `parseResponse()` override with `newResponseParser()`:

  ```php
  // BEFORE (v25)
  #[\Override]
  protected static function parseResponse(string $plain): array
  {
      return MyResponseParser::parse($plain);
  }

  // AFTER (v26)
  #[\Override]
  protected static function newResponseParser(): \CNIC\ResponseParserInterface
  {
      return new MyResponseParser();
  }
  ```

- **You register response templates in a long-lived process or across test classes.** `AbstractResponseTemplateManager::$templates` is public static state with process lifetime, so anything you add stays visible everywhere afterwards. `resetTemplates()` now gives you the way back to the brand's built-ins:

  ```php
  \CNIC\IBS\ResponseTemplateManager::addTemplate("mycase", "FAILURE", "…");
  // … later, when the scenario is over
  \CNIC\IBS\ResponseTemplateManager::resetTemplates();
  ```

  It is `addTemplate()`'s counterpart, not a general undo: it restores what the container held the first time you called `addTemplate()` on that class, it is per brand, and a direct assignment to the public `$templates` property is outside its reach. Register through `addTemplate()` and it will always take you back.

**Why this happened:** see the parse-seam entry in [docs/agents/architecture.md](docs/agents/architecture.md) for the full decision record. (Ref: RSRMID-2924.)

---

<a id="-v2700"></a>

## → v27.0.0 — the logger seam moved from the sink to the format

**What changed:** `CNIC\LoggerInterface` used to be one method, `log(): void`, and every implementation ended in `echo`. The record itself — the only part that actually differs between brands — could not be obtained by anyone. It is now two halves:

- **`format(string $post, ResponseInterface $r, ?string $error = null): string`** — new on `LoggerInterface`. Builds the debug record and **returns** it.
- **`CNIC\LogSinkInterface::write(string $message): void`** — new. Decides where a record goes. `CNIC\EchoSink` is the shipped default and writes to standard output, exactly as before.
- **`CNIC\AbstractLogger`** — new base class. Takes a sink (defaulting to `EchoSink`), implements `log()` as `sink->write(format(…))`, and leaves `format()` abstract. `log()` is `final`: a subclass that reintroduced it would silently ignore an injected sink.
- **`AbstractClient::setLogSink(LogSinkInterface $sink)`** — new. Keeps the brand format, changes the destination.
- **`AbstractClient::setDefaultLogger()` is gone**, replaced by the protected factory hook `newLogger(LogSinkInterface $sink): LoggerInterface`.

**If you never wrote a logger, there is nothing to do.** `enableDebugMode()` emits the same bytes it always has — that is asserted per brand in the test suite.

**What to respect** if you did any of the following:

- **You wrote a custom logger.** Rename the body of `log()` to `format()`, return the string instead of echoing it, and extend `CNIC\AbstractLogger` — writing is inherited:

  ```php
  // BEFORE (v26)
  final class MyLogger implements \CNIC\LoggerInterface
  {
      public function log(string $post, \CNIC\ResponseInterface $r, ?string $error = null): void
      {
          echo "[{$r->getCode()}] {$post}\n";
      }
  }

  // AFTER (v27)
  final class MyLogger extends \CNIC\AbstractLogger
  {
      #[\Override]
      public function format(string $post, \CNIC\ResponseInterface $r, ?string $error = null): string
      {
          return "[{$r->getCode()}] {$post}\n";
      }
  }
  ```

  Implementing `LoggerInterface` directly still works and is the way to own the destination as well — but then you must supply **both** methods, and nothing calls `format()` for you.

- **Your logger existed only to redirect output** (a file, a PSR-3 logger, the WHMCS or Blesta module log). Delete it and write a sink instead; you keep the brand's format for free, which is what you previously had to reimplement:

  ```php
  // AFTER (v27)
  final class FileSink implements \CNIC\LogSinkInterface
  {
      public function __construct(private readonly string $path) {}

      #[\Override]
      public function write(string $message): void
      {
          file_put_contents($this->path, $message . PHP_EOL, FILE_APPEND);
      }
  }

  $cl->enableDebugMode()->setLogSink(new FileSink("/var/log/cnic.log"));
  ```

  `setLogSink()` rebuilds the brand logger around your sink, so it also discards anything previously passed to `setCustomLogger()`. Pass a fresh `new \CNIC\EchoSink()` to get back to the stock behaviour.

- **You called `setDefaultLogger()`.** It is gone. Use `setLogSink(new \CNIC\EchoSink())` — same effect, and it now says which destination it means.

- **You subclassed a brand client and overrode `setDefaultLogger()`.** Override `newLogger()` instead:

  ```php
  // AFTER (v27)
  #[\Override]
  protected function newLogger(\CNIC\LogSinkInterface $sink): \CNIC\LoggerInterface
  {
      return new MyLogger($sink);
  }
  ```

  Honour the `$sink` argument — that is what makes `setLogSink()` work for your subclass too.

- **You subclassed `CNIC\CNR\Logger` or `CNIC\IBS\Logger`.** You could not: both are `final`, and remain so. They now extend `AbstractLogger` and declare `format()` only.

- **You asserted on debug output with `ob_start()`.** Call `format()` and assert on the returned string, or hand the client a collecting sink. Output buffering is no longer needed to see what the SDK logs.

**Masking is unaffected** and still happens upstream of the formatter: the response masks its own stored command and the client passes an already-secured POST body, so nothing new becomes obtainable except the record you asked for. For why the seam moved, see the logger entry in [docs/agents/architecture.md](docs/agents/architecture.md). (Ref: RSRMID-2925.)

---

<a id="-v2800"></a>

## → v28.0.0 — IBS/Moniker date separators survive verbatim; Record/Column gained a date accessor; `IBS\Response::getStatus()` removed

**What changed:** `CNIC\IBS\ResponseParser` used to silently rewrite `/` to `-` in any string value whose key matched `/(date|paiduntil|expiration)$/i` — the only place the SDK mutated raw response data. That rewrite is gone: `getPlain()`, `getHash()` and `getListHash()` now return IBS/Moniker date values exactly as the API sent them, with `/` (e.g. `2030/07/17`), not `-`.

The rewrite was also over-broad: it matched on key suffix alone, so a non-date string such as `n/a` under an `updatedate` key came back corrupted as `n-a`. That corruption is also gone — every value now survives untouched, date or not.

`CNIC\ApiDateTime` gained the ability to parse the `/` form directly instead, so nothing is lost: it now accepts **either** `-` or `/` as the date separator (consistently within one value — `2026-02/20` and `2026/02-20` are both still refused), and `$date`/`$dateTime` always come back with `-` regardless of which one the source used.

Two new opt-in accessors do the narrowing at the point of use, instead of at parse time:

- `CNIC\Record::getDateTimeByKey(string $key): ?ApiDateTime`
- `CNIC\Column::getDateTimeByIndex(int $idx): ?ApiDateTime`

Both are also declared on `CNIC\RecordInterface`/`CNIC\ColumnInterface` — which is what makes this breaking even for a consumer who never reads a date.

`CNIC\ApiDateTime` also gained a new readonly property, `string $raw` — the original input string exactly as passed to `from()`/`tryFrom()`, unmodified (so it is also the only place a discarded fractional-second part, e.g. `.813`, survives). This is additive on the value object itself (it is `final` with a private constructor, so nothing you wrote could have broken), but `toArray()` gains a `"raw"` key — if you assert on the _whole_ array shape (e.g. `assertSame($expected, $dt->toArray())`), add that key.

A value with a trailing newline (e.g. read from a file or a form field, rather than from an API response) is now refused by `from()`/`tryFrom()` instead of being silently parsed — consistent with how the class already refuses `2026-02-30` rather than coercing it. No API response is affected.

**What to respect:**

- **If you parse `getPlain()`/`getHash()`/`getListHash()` dates yourself for IBS or Moniker**, they now carry `/`, not `-`. Either accept both separators in your own parsing, or switch to `ApiDateTime`, which already does:

  ```php
  // BEFORE (v27) — IBS/Moniker date values arrived pre-normalized to "-"
  $expiry = $rec->getDataByKey("expirationdate"); // "2030-07-17"

  // AFTER (v28) — the raw API separator survives
  $expiry = $rec->getDataByKey("expirationdate"); // "2030/07/17"

  // Parse it instead of hand-rolling the separator handling:
  $dt = $rec->getDateTimeByKey("expirationdate");
  $dt?->date; // "2030-07-17" — always "-", regardless of the source separator
  ```

- **If you implement `RecordInterface` or `ColumnInterface` directly** (rather than using `CNIC\Record`/`CNIC\Column`), add the new method:

  ```php
  // AFTER (v28)
  final class MyRecord implements \CNIC\RecordInterface
  {
      // ... existing getData()/getDataByKey() ...

      #[\Override]
      public function getDateTimeByKey(string $key): ?\CNIC\ApiDateTime
      {
          $value = $this->getDataByKey($key);
          return is_string($value) ? \CNIC\ApiDateTime::tryFrom($value) : null;
      }
  }
  ```

**Why this happened:** see the `ApiDateTime` entry in [docs/agents/architecture.md](docs/agents/architecture.md) for the full decision record. (Ref: RSRMID-2926.)

### `IBS\Response::getStatus()` removed

**What changed:** `CNIC\IBS\Response::getStatus()` is gone. It had zero callers anywhere in `src/`, and the value it returned was never anything more than `getHash()["status"]` — already reachable through the universal `ResponseInterface`, with no narrowing required. A one-method capability interface plus the narrowing ceremony that would come with it was not worth the API surface for a value a caller can already read off the hash.

**What to respect:**

```php
// before
$status = $r->getStatus();
// after
$status = (string)($r->getHash()["status"] ?? "");
```

`isError()`/`isSuccess()` are **unaffected** — both continue to read the same `status` hash key internally (via the protected `getHashString()`, which was never a public replacement path and stays that way); only the public getter that duplicated it is gone.

This is the one field where reading `status` off the hash directly is genuinely useful, not just a fallback: for `Domain/Check`, `status` is how the API reports `AVAILABLE`/`UNAVAILABLE` — a 200 response with `isError() === false` either way (see `tests/IBS/cassettes/request-success-dbg.json`, which carries `"status":"UNAVAILABLE"` alongside a 200 code). `isError()`/`isSuccess()` tell you whether the _command_ succeeded, not whether the domain is available — for that you still need `getHash()["status"]`.

**Why this happened:** see the `getStatus()` entry in [docs/agents/architecture.md](docs/agents/architecture.md) for the full decision record. (Ref: RSRMID-2927.)

---

<a id="-v2900"></a>

## → v29.0.0 — public method parameters and six protected properties renamed to be self-describing

**What changed:** every public method parameter whose name carried no meaning was renamed. Nothing else — no method was added, removed, renamed or retyped, no default changed, no behaviour changed. Every call that passes arguments **positionally** is unaffected.

Since PHP 8.0 a parameter name is part of the public API, because a caller may pass it as a named argument — and the runtime floor here is 8.3, so consumers can already do that. There is no parameter alias in PHP and therefore no deprecation path, which is why a rename has to ride a major.

**Who is affected — two groups, and only these two:**

1. **Callers using named arguments.** `$r->getColumn(key: "DOMAIN")` now raises `Error: Unknown named parameter $key` at runtime. The message does not mention the SDK, so it is worth grepping for before you upgrade. Typing against an interface does **not** insulate you: PHP binds a named argument to the _implementation's_ parameter name, so the call breaks whichever type your variable is declared as.
2. **Implementers and subclassers.** If you implement `LoggerInterface`, `TransportInterface`, `RecordInterface`, `ColumnInterface`, `ResponseInterface` or `RoleCredentialsInterface`, or extend `AbstractClient`, `AbstractLogger`, `AbstractResponse`, `AbstractResponseTemplateManager`, `AbstractResponseTranslator`, `AbstractSocketConfig`, or a brand `Client`/`Response`/`SocketConfig`/`ResponseTemplateManager`, your parameter names should match the new ones. PHP itself does not enforce this, so your code keeps running; but Psalm reports `ParamNameMismatch`, and your own callers bind to _your_ names — so a mismatch quietly leaves your class's contract diverging from the one documented here.

**What to respect:**

```php
// BEFORE (v28) — named arguments bound to the old names
$r->getColumn(key: "DOMAIN");
$cl->setUserAgent(str: "MyApp", rv: "1.0");
$cl->setCredentials(uid: "test.user", pw: "secret");

// AFTER (v29)
$r->getColumn(columnName: "DOMAIN");
$cl->setUserAgent(label: "MyApp", revision: "1.0");
$cl->setCredentials(login: "test.user", password: "secret");
```

The full set, grouped by the name that replaced them:

| New name          | Replaces                         | Where                                                                                                                                                                                                                       |
| ----------------- | -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `$columnName`     | `$key`, `$colkey`                | `ResponseInterface`/`AbstractResponse`/`CNR\Response`/`IBS\Response::addColumn()`, `getColumn()`, `getColumnIndex()` (1st); `RecordInterface`/`Record::getDataByKey()`, `getDateTimeByKey()`; `Column::__construct()` (1st) |
| `$recordIndex`    | `$idx`, `$index`                 | `ResponseInterface`/`AbstractResponse::getRecord()`, `getColumnIndex()` (2nd); `ColumnInterface`/`Column::getDataByIndex()`, `getDateTimeByIndex()`; `CNR\Column::getDataByIndex()`                                         |
| `$templateId`     | `$id`                            | `AbstractResponseTemplateManager::getTemplate()`, `addTemplate()`, `hasTemplate()`, `isTemplateMatchHash()` (2nd), `isTemplateMatchPlain()` (2nd); `CNR`/`IBS\ResponseTemplateManager::getTemplate()`                       |
| `$description`    | `$descr`                         | `AbstractResponseTemplateManager::addTemplate()` (3rd)                                                                                                                                                                      |
| `$response`       | `$r`                             | `LoggerInterface`/`AbstractLogger`/`CNR\Logger`/`IBS\Logger::format()`, `log()` (2nd)                                                                                                                                       |
| `$placeholders`   | `$ph`                            | `AbstractResponse::__construct()` (3rd), `translate()` (3rd, incl. both brand overrides); `AbstractResponseTranslator::translate()` (3rd), `replacePlaceholders()` (2nd)                                                    |
| `$row`            | `$h`                             | `ResponseInterface`/`AbstractResponse::addRecord()`; `AbstractResponse`/`CNR\Response`/`IBS\Response::newRecord()`                                                                                                          |
| `$timeoutSeconds` | `$seconds`, `$timeout`, `$value` | `AbstractClient`/`AbstractSocketConfig::setSocketTimeout()`; `TransportInterface`/`HttpTransport::post()` (3rd)                                                                                                             |
| `$maskSecrets`    | `$secured`                       | `AbstractClient`/`AbstractSocketConfig::getPOSTData()` (2nd), `getPOSTDataParams()` (2nd, incl. both brand overrides)                                                                                                       |
| `$login`          | `$value`, `$uid`                 | `AbstractSocketConfig`/`CNR\SocketConfig::setLogin()`; `AbstractClient::setCredentials()` (1st)                                                                                                                             |
| `$password`       | `$value`, `$pw`                  | `AbstractSocketConfig`/`CNR\SocketConfig::setPassword()`; `AbstractClient::setCredentials()` (2nd); `RoleCredentialsInterface`/`CNR\Client::setRoleCredentials()` (3rd)                                                     |
| `$accountId`      | `$uid`                           | `RoleCredentialsInterface`/`CNR\Client::setRoleCredentials()` (1st)                                                                                                                                                         |
| `$roleId`         | `$role`                          | `RoleCredentialsInterface`/`CNR\Client::setRoleCredentials()` (2nd)                                                                                                                                                         |
| `$session`        | `$value`                         | `CNR\Client`/`CNR\SocketConfig::setSession()`                                                                                                                                                                               |
| `$url`            | `$value`                         | `AbstractClient`/`AbstractSocketConfig::setURL()`                                                                                                                                                                           |
| `$persistent`     | `$value`                         | `CNR\SocketConfig::setPersistent()`                                                                                                                                                                                         |
| `$label`          | `$str`                           | `AbstractClient::setUserAgent()` (1st)                                                                                                                                                                                      |
| `$revision`       | `$rv`                            | `AbstractClient::setUserAgent()` (2nd)                                                                                                                                                                                      |
| `$currentPage`    | `$rr`                            | `CNR\Client::requestNextResponsePage()`                                                                                                                                                                                     |
| `$upperCaseKeys`  | `$toupper`                       | `CommandFormatter::flattenCommand()` (2nd)                                                                                                                                                                                  |
| `$responseHash`   | `$tpl`                           | `AbstractResponseTemplateManager::isTemplateMatchHash()` (1st)                                                                                                                                                              |

`$tpl` → `$responseHash` is the one rename that fixes a name meaning the **opposite** of what it said: the argument is the API response hash being tested _against_ a template, not the template. Post-rename the pair reads `isTemplateMatchHash(array $responseHash, string $templateId)`, which is the right way round.

Note `$accountId` and `$login` are deliberately different words for what looks like one concept: `setCredentials()` takes the login **as it goes on the wire**, while `setRoleCredentials()` takes the account and role ids **separately** and composes the login from them. They are not interchangeable.

`ApiDateTime::from()`/`tryFrom()` keep `$value`, mirroring PHP's own `BackedEnum::from()`, and are **not** part of this rename.

If you implement `RecordInterface`, the v28 snippet above needs its parameter renamed:

```php
// AFTER (v29)
#[\Override]
public function getDateTimeByKey(string $columnName): ?\CNIC\ApiDateTime
{
    $value = $this->getDataByKey($columnName);
    return is_string($value) ? \CNIC\ApiDateTime::tryFrom($value) : null;
}
```

**Why this happened:** the old names carried no meaning, so every one of them needed a `@param` line restating it — 48 such annotations are gone with this change, leaving only the ones that say something a name cannot (an array shape, a precondition, or what passing an empty string resets). Follow-up to RSRMID-2934, which did the same for internal parameters without breaking anything. (Ref: RSRMID-2935.)

### Six `protected` properties renamed too

**What changed:** the same defect on the property surface. This is a **separate** break from the parameter renames above, with a different audience: a protected property is reachable only from a subclass, so this affects you **only if you extend** `AbstractSocketConfig`, `AbstractClient` or `AbstractResponse` (or a brand `SocketConfig`/`Response`) and read the property directly.

| Old               | New               | On                                       |
| ----------------- | ----------------- | ---------------------------------------- |
| `$pw`             | `$password`       | `AbstractSocketConfig`                   |
| `$ua`             | `$userAgent`      | `AbstractClient`                         |
| `$curlopts`       | `$curlOptions`    | `AbstractSocketConfig`                   |
| `$paginationkeys` | `$paginationKeys` | `AbstractResponse`, `CNR`/`IBS\Response` |
| `$columnkeys`     | `$columnKeys`     | `AbstractResponse`                       |
| `$columnindex`    | `$columnIndex`    | `AbstractResponse`                       |

The last three were also the repo's only departures from its own camelCase-properties rule.

**What to respect:**

```php
// BEFORE (v28)
final class MyConfig extends \CNIC\AbstractSocketConfig
{
    protected function getPOSTDataParams(array $command, bool $maskSecrets): array
    {
        return ["u" => $this->login, "p" => $maskSecrets ? "***" : $this->pw];
    }
}

// AFTER (v29)
        return ["u" => $this->login, "p" => $maskSecrets ? "***" : $this->password];
```

Unlike the parameter renames, this one fails **loudly and statically**: reading `$this->pw` is an access to an undefined property, which PHPStan reports at level 9 rather than leaving to runtime. If you run either analyser over your integration you will be told; if you run neither, PHP emits a warning and yields `null` at runtime.

**Public** properties are untouched — `$templates` and `ApiDateTime`'s readonly fields were already accurate, and `System::$name`/`$value` are native enum properties.

**Why this happened:** `$pw` and `$ua` are the same meaningless-abbreviation defect as the parameters, and the rest violated the project's own naming standard; batching them into the one major this rename already costs is cheaper than a second breaking release later. (Ref: RSRMID-2935.)

---

<a id="-v3000"></a>

## → v30.0.0 — the transport error travels as a parameter, not encoded into the payload

**What changed:** `HttpTransport::post()` used to encode a failure twice — once as the tuple's error element `[1]`, and once as a `"httperror|"` prefix smuggled into the raw payload `[0]`, which `AbstractResponseTranslator::translate()` then string-split back off. The sentinel is gone: the error now travels only as an explicit, trailing `?string $error` parameter, appended last (default `null`) on every hook in the pipeline. The `nocurl` template id is also gone — it existed only for a `curl_init() === false` branch that is unreachable with `ext-curl` as a hard dependency, and is now an `assert()` like the rest of that file's defensive guards.

**Who is affected — three groups:**

1. **Anyone who subclassed a brand `Client` or `Response` to override `newResponse()` or `translate()`.** Both hooks gained a trailing `?string $error = null` parameter.
2. **Anyone who implements `TransportInterface` directly.** The contract `post()` already declared is now stated explicitly: a non-null element `[1]` means element `[0]` is unusable and will be discarded in favour of the `httperror` template. A transport that used to return real bytes alongside an advisory error will now have those bytes thrown away.
3. **Anyone calling `getTemplate("nocurl")` or `hasTemplate("nocurl")` on `CNR\ResponseTemplateManager`/`IBS\ResponseTemplateManager` directly.** This one is silent — nothing throws, the returned value just changes. `getTemplate("nocurl")` used to return `CODE=423`/`status=FAILURE` ("API access error: curl_init failed"); it now falls through to the generic `"notfound"` template instead — `CODE=500`/`status=FAILURE` ("Response Template not found"). `hasTemplate("nocurl")` flips from `true` to `false`.

**What to respect:**

```php
// BEFORE (v29) — the brand hooks took three parameters
abstract protected function newResponse(string $raw, array $cmd, array $cfg): ResponseInterface;
abstract protected function translate(string $raw, array $cmd, array $placeholders): string;

// AFTER (v30) — a trailing $error, appended last so positional callers are unaffected
abstract protected function newResponse(string $raw, array $cmd, array $cfg, ?string $error = null): ResponseInterface;
abstract protected function translate(string $raw, array $cmd, array $placeholders, ?string $error = null): string;
```

```php
// BEFORE (v29) — a transport could return bytes and an error together and both survived,
// because the caller string-split "httperror|" back off $raw
final class MyTransport implements TransportInterface
{
    public function post(string $url, string $data, int $timeoutSeconds, string $userAgent, array $options = []): array
    {
        // ...
        return ["httperror|" . $error, $error];
    }
}

// AFTER (v30) — $raw is unusable whenever $error is non-null; return it empty
final class MyTransport implements TransportInterface
{
    public function post(string $url, string $data, int $timeoutSeconds, string $userAgent, array $options = []): array
    {
        // ...
        return ["", $error];
    }
}
```

**Behaviour is otherwise unchanged:** `$error !== null` still selects the brand's `httperror` template, and `$error !== ""` still gates the `{HTTPERROR}` injection into it — the same two-level check as before, just reached through a parameter instead of a string split. A cassette or fixture that used to hand-author `["raw" => "httperror|<msg>", "error" => "<msg>"]` should now read `["raw" => "", "error" => "<msg>"]`.

**Why this happened:** the sentinel was redundant — the same information already existed as the tuple's `[1]`, and string-splitting `[0]` for it meant a pipe character _inside_ a cURL error message was a real truncation hazard (mitigated, never removed, by an `explode(..., 2)` limit). Declaring the error as its own parameter removes the encoding round-trip entirely. (Ref: RSRMID-2937.)

---

## → v31.0.0 — a `Response` is sealed once constructed; `foreach` replaces the record cursor

**What changed:** six methods came off `ResponseInterface`. Two were mutators (`addColumn()`, `addRecord()`); four were the record cursor (`getCurrentRecord()`, `getNextRecord()`, `getPreviousRecord()`, `rewindRecordList()`). A response is now fully assembled by its constructor and read-only afterwards, and its rows are walked with `foreach` — the interface extends `IteratorAggregate`.

**Who is affected — three groups:**

1. **Anyone stepping through records with the cursor.** `while ($rec = $r->getNextRecord())` no longer compiles. This is the one change likely to touch real code — see the rewrite below.
2. **Anyone calling `addColumn()` / `addRecord()` on a response.** Both are now `protected`. There is no replacement, because there was never a working use: see "Why this happened".
3. **Anyone who subclasses a brand `Response`.** The `populate()` hook changed signature — it now receives what it used to read off `$this`.

**What to respect — iterating records:**

```php
// BEFORE (v30) — a shared cursor, with a rewind protocol nothing declared
$r->rewindRecordList();
while (($rec = $r->getNextRecord()) !== null) {
    echo $rec->getDataByKey("domain");
}

// AFTER (v31) — foreach; the position lives in the loop, not on the response
foreach ($r as $rec) {
    echo $rec->getDataByKey("domain");
}
```

Note the `getNextRecord()` loop above never saw the **first** record: the cursor started at index 0 and `getNextRecord()` advanced before returning, so row 0 had to be fetched separately with `getCurrentRecord()`. If your loop looked like the "before" block and you never noticed a missing first row, you were probably reading a single-record response. `foreach` yields every row, first one included — so a `getNextRecord()` loop ported verbatim will now legitimately see **one more record than it used to**.

Random access is unchanged: `getRecord(int $recordIndex)` and `getRecords()` are still there, so `getCurrentRecord()` becomes `getRecord(0)`.

**What to respect — subclassing a brand `Response`:**

```php
// BEFORE (v30) — populate() read three pieces of half-initialised state off $this
protected function populate(): void
{
    $this->hash = $this->parser->parse($this->raw, $this->command);
    // ...
}

// AFTER (v31) — all three arrive as arguments; the $parser property is gone
protected function populate(string $raw, ResponseParserInterface $parser, array $cmd): void
{
    $this->hash = $parser->parse($raw, $cmd);
    // ...
}
```

`$this->raw`, `$this->command` and `$this->hash` are all still readable properties; only `$this->parser` was removed, since nothing after construction has a use for it. Injecting a substitute parser through the constructor is unaffected — the `$parser` argument still works exactly as in v26+.

**Also in this major, both silent unless you subclass:**

- **A duplicate column name now throws** `CNIC\Exception\DuplicateColumnException` (new, additive in the `CnicException` hierarchy). Previously `$columns`/`$columnKeys` appended the second column while the name-to-position index kept the first, leaving `getColumns()` holding a column `getColumn()` could never return. Unreachable from either shipped brand, and from a substitute parser too — both derive their column names from `array_keys()` of the parsed hash, and two distinct PHP array keys cannot stringify to one name.
- **`assembleRecords()` replaces the record list instead of appending to it,** so calling it twice yields the same rows rather than double.

**Why this happened:** every one of the six was a rule a caller had to know that no type expressed. A column added after construction was absent from every record, because records are assembled from the columns once, at the end of `populate()` — it appeared in `getColumns()` and `getColumnKeys()` and nowhere else. A record added afterwards changed `getRecordsCount()` and, through it, the four pagination getters IBS derives from it, so a caller could silently repaginate a finished response. And the cursor was hidden mutable state shared by every holder of the object: two consumers iterating one response interfered with each other, the predicates that would have let a caller test the cursor without moving it were `protected`, and nothing stated that re-iteration needed a `rewindRecordList()` first. `foreach` has none of those properties. (Ref: RSRMID-2939.)

---

## Reference: the canonical usage

**Once you have finished upgrading, check your code against [the Usage section of README.md](README.md#usage)** — it holds the worked, per-brand example of idiomatic code for the current major (client construction, the `$path` argument, `close()`, and which capabilities are CNR-only), kept up to date rather than pinned to any one version.

It lives there rather than here because `README.md` is the only documentation that ships in the Composer package, so a consumer who installed via `composer require` can read it without fetching anything else. (Ref: RSRMID-2930.)

---

## Upgrade checklist

1. **Read every major section** between your current version and the target — do not skip.
2. **Bump one major at a time.** After each bump, run your test suite before moving on.
3. **Match the PHP floor** for your target (8.1 for v9–v13, **8.3** for v14+).
4. **Type against interfaces** (`ResponseInterface`, `ExtendedResponseInterface`, `RoleCredentialsInterface`, `LoggerInterface`) rather than concrete classes — this is what keeps future majors from breaking you.
5. **Re-test brand data handling** if you use IBS/Moniker across the v13 JSON switch.
6. **Decode your own credentials** (v16+) and **`close()` sessionless clients** (v10+).
7. Consult [HISTORY.md](HISTORY.md) for the exhaustive per-release change list.
