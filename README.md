# php-sdk

[![semantic-release](https://img.shields.io/badge/%20%20%F0%9F%93%A6%F0%9F%9A%80-semantic--release-e10079.svg)](https://github.com/semantic-release/semantic-release)
[![Build Status](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/workflows/Release/badge.svg?branch=master)](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/workflows/Release/badge.svg?branch=master)
[![Packagist](https://img.shields.io/packagist/v/centralnic-reseller/php-sdk.svg)](https://packagist.org/packages/centralnic-reseller/php-sdk)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/centralnic-reseller/php-sdk.svg)](https://packagist.org/packages/centralnic-reseller/php-sdk)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PRs welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/blob/master/CONTRIBUTING.md)
[![codecov](https://codecov.io/gh/centralnicgroup-opensource/rtldev-middleware-php-sdk/graph/badge.svg)](https://codecov.io/gh/centralnicgroup-opensource/rtldev-middleware-php-sdk)

This module is a connector library for the insanely fast CNIC Backend APIs (CentralNic Reseller, internet.bs, moniker). Do not hesitate to contact us in case of questions.

## Resources

- Documentation Links (PHP-SDK internal registrar id available in round brackets):
  - [CentralNic Reseller (CNR)](https://support.centralnicreseller.com/hc/en-gb/articles/13513253776285-Self-Development-Kit-for-PHP)
  - [Internet.bs (IBS)](https://faq.internetbs.net/hc/en-gb/articles/24953916500381-Self-Development-Kit-for-PHP)
  - [Moniker (MONIKER)](https://support.moniker.com/hc/en-gb/articles/24954146333981-Self-Development-Kit-for-PHP)
- [Release Notes](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/releases)
- [Migration Guide](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/blob/master/MIGRATION.md) — how to upgrade across major versions

## Usage

```sh
composer require centralnic-reseller/php-sdk
```

Idiomatic code for the **current** major, whatever that is when you read this — this section is kept up to date rather than pinned to the version that introduced the factory:

```php
use CNIC\ClientFactory;

// --- CNR (CentralNic Reseller, fka RRPproxy) ---
$cl = ClientFactory::cnr();                // returns a fully-typed CNR\SessionClient
$cl->useOTESystem()                        // omit for LIVE (the default)
   ->setCredentials($user, $password);     // or ->setRoleCredentials($acct, $role, $pw)
// CNR has one fixed script path, so request() defaults it — pass a command only.
$r = $cl->request(["COMMAND" => "StatusAccount"]);
if ($r->isSuccess()) {
    print_r($r->getHash());
}
$cl->close();                              // release the cached cURL handle

// --- IBS / Moniker (JSON API) ---
$cl = ClientFactory::ibs();                // or ClientFactory::moniker()
$cl->useOTESystem()->setCredentials($user, $password);
// This platform exposes many endpoints under one host and the *path* selects the
// operation, so pass it as the second argument — there is no default that works.
$r = $cl->request(["domain" => "example.com"], "Domain/Check");
if ($r->isSuccess()) {
    print_r($r->getHash());
}
$cl->close();
```

Two brand differences the snippet is deliberately explicit about:

- **The `$path` argument.** `request(array $cmd = [], string $path = "")` is symmetric across all brands, but only CNR has a meaningful default (`api/call.cgi`). On IBS/Moniker the path _is_ the operation, so omitting it sends the request to the bare host.
- **Sessions and role logins are CNR-only, by type.** `login()`, `logout()`, `saveSession()`, `getSession()`/`setSession()` and `setRoleCredentials()` exist on the CNR client and **do not exist** on `IBS\Client`/`MONIKER\Client` — calling one is a static-analysis error at the call site, not a runtime surprise. See [Migration Guide → v22.0.0](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/blob/master/MIGRATION.md#-v2200).

**Type against the interfaces, not the concrete classes.** Depending on `CNIC\ResponseInterface`, `CNIC\ColumnInterface`, `CNIC\RecordInterface` and `CNIC\LoggerInterface` is what keeps future majors from breaking you; code that reaches for `CNIC\CNR\Response` or uses `method_exists()` fallbacks is what does not survive them.

### Reading the rows of a list response

A response is fully assembled by the time you hold one, and read-only from then on. Walk its records with `foreach` — the response is iterable — or address them by index:

```php
$r = $cl->request(["COMMAND" => "QueryDomainList", "LIMIT" => "100"]);

foreach ($r as $index => $rec) {
    echo $index, ": ", $rec->getDataByKey("DOMAIN"), "\n";
}

$r->getRecord(0);           // ?RecordInterface — by index, or null if out of range
$r->getRecords();           // RecordInterface[] — the whole list
$r->getColumn("DOMAIN");    // ?ColumnInterface — column-wise instead of row-wise
$r->getPagination();        // COUNT / FIRST / LAST / LIMIT / TOTAL / PAGES / …
```

`foreach` keeps its position in the loop rather than on the response, so iterating is repeatable, needs no rewind step, and two places iterating the same response cannot interfere. If you are coming from a version with `getNextRecord()`/`rewindRecordList()`, see [Migration Guide → v31.0.0](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/blob/master/MIGRATION.md#-v3100).

### Debug output

`enableDebugMode()` writes one record per request to standard output. Two seams let you take it somewhere else, and they are independent:

```php
use CNIC\LogSinkInterface;

// 1. Keep the brand's format, change the destination.
final class FileSink implements LogSinkInterface
{
    public function __construct(private readonly string $path) {}

    public function write(string $message): void
    {
        file_put_contents($this->path, $message . PHP_EOL, FILE_APPEND);
    }
}

$cl->enableDebugMode()->setLogSink(new FileSink("/var/log/cnic.log"));

// 2. Change the format too: extend CNIC\AbstractLogger and implement one
//    method — the sink wiring comes with it.
final class MyLogger extends \CNIC\AbstractLogger
{
    #[\Override]
    public function format(string $post, \CNIC\ResponseInterface $response, ?string $error = null): string
    {
        return sprintf("[%d] %s\n", $response->getCode(), $post);
    }
}

$cl->setCustomLogger(new MyLogger(new FileSink("/var/log/cnic.log")));
```

Order matters between the two: `setLogSink()` rebuilds the **brand** logger around your sink, so call it before `setCustomLogger()`, not after.

`LoggerInterface::format()` **returns** the record rather than printing it, so you can route SDK debug output into your own logging without reimplementing a brand's format — and assert on it in your own tests without output buffering. Sensitive command values (`PASSWORD`, `AUTH`, `transferAuthInfo`) are already masked before they reach the formatter.

For working, runnable examples per brand — including the CNR session flow (`saveSession()`/`reuseSession()` across two stateless requests) — see [`examples/app_CNR.php`](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/blob/master/examples/app_CNR.php), [`examples/app_IBS.php`](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/blob/master/examples/app_IBS.php) and [`examples/app_MONIKER.php`](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/blob/master/examples/app_MONIKER.php). Those are not part of the Composer package — clone the repository to run them, as described under [Running the Demo Application](#running-the-demo-application).

## Date & time values

The APIs declare their date columns in **UTC** and emit two shapes: a full timestamp (`2026-07-25 07:46:34`, optionally with a fractional-second part, as CNR sends) and a bare calendar date (`2030/07/17`, as internet.bs/Moniker send). `CNIC\ApiDateTime` parses both into one flat, immutable struct, and accepts **either** `-` or `/` as the date separator — consistently within one value, so `2026-02/20` is refused. `$date`/`$dateTime` always come back with `-`, regardless of which one the source used:

```php
use CNIC\ApiDateTime;

$dt = ApiDateTime::from("2026-07-25 07:46:34");
$dt->ts;             // 1784965594
$dt->date;           // "2026-07-25"
$dt->dateTime;       // "2026-07-25 07:46:34"
$dt->tz;             // "UTC"
$dt->raw;            // "2026-07-25 07:46:34" — the input, verbatim
$dt->isDateOnly();   // false
$dt->toArray();      // ready for json_encode()
```

| Field      | Type           | CNR `2026-07-25 07:46:34` | internet.bs / Moniker `2030/07/17`   |
| ---------- | -------------- | ------------------------- | ------------------------------------ |
| `ts`       | `int\|null`    | `1784965594`              | **`null`** — exact instant unknown   |
| `date`     | `string`       | `2026-07-25`              | `2030-07-17` — always `-`, even here |
| `dateTime` | `string\|null` | `2026-07-25 07:46:34`     | **`null`**                           |
| `tz`       | `string`       | `UTC`                     | `UTC`                                |
| `raw`      | `string`       | `2026-07-25 07:46:34`     | `2030/07/17` — verbatim input        |

A bare calendar date names no instant, so `ts` and `dateTime` are **both null** for one — deliberately, rather than defaulting to midnight, which would be a fabricated instant indistinguishable from a real one. `date` is always populated, so there is unconditionally something to print; `$dt->ts === null` (or `isDateOnly()`) is the unambiguous test.

`raw` keeps whatever the source sent, including a fractional-second part `dateTime` discards. It is for display, logging and round-trip fidelity only — **compare and sort on `ts` or `date`, never on `raw`**, since `"2026/02/20"` sorts wrong against `"2026-03-01"` as plain strings.

Parsing is strict. Values PHP's own date handling would silently roll over into a _different_ instant — `2026-02-30` becoming `2026-03-02`, `2026-13-45` becoming `2027-02-14`, `0000-00-00` becoming `-0001-11-30` — are refused with a `CNIC\Exception\InvalidDateTimeException`, as are offset-bearing values (never silently relabelled UTC). Use `ApiDateTime::tryFrom()` when a `null` is preferable to an exception:

```php
ApiDateTime::tryFrom(null);          // null
ApiDateTime::tryFrom("2026-02-30");  // null — refused, not coerced
```

> [!NOTE]
> This is a **parser, not a formatter**. Responses are not rewritten: `getPlain()`, `getHash()` and `getListHash()` keep returning the raw API strings verbatim — internet.bs/Moniker dates keep their `/` separator — and this type is opt-in at the point where a value is actually used. There is no locale formatting and no `ext-intl` dependency — presenting a value in the viewer's timezone is a display concern for the consuming application:
>
> ```php
> (new \DateTimeImmutable("@{$dt->ts}"))->setTimezone(new \DateTimeZone("Europe/Berlin"));
> ```

`CNIC\Record::getDateTimeByKey()` and `CNIC\Column::getDateTimeByIndex()` do that narrowing for you, right where you already read a value — no `null` check on a non-string, missing, or unparsable value needed beyond the returned `?ApiDateTime` itself:

```php
$rec = $response->getRecord(0);
$expiry = $rec?->getDateTimeByKey("expirationdate"); // ?ApiDateTime — works for "-" or "/" input
$expiry?->date;       // "2030-07-17"
$expiry?->isDateOnly(); // true

$col = $response->getColumn("expirationdate");
$col?->getDateTimeByIndex(0); // same parsing, by column index instead of record key
```

Run `composer demo:datetime` for a runnable tour — it needs no credentials and makes no API calls.

## Dev Container

If you want to contribute, we recommend using Visual Studio Code and to follow the below setup instructions:

- Add an entry in your hosts file: `127.0.0.1         devsdk.centralnicreseller.net`

PHP SDK Data can be accessed via apache server at this url: `http://devsdk.centralnicreseller.net`

### Environment variables (`env.sh`)

The devcontainer looks for an `env.sh` file in the workspace root and **automatically sources it** in two places:

1. **Every new integrated-terminal session** — the file is sourced via `~/.zshenv` so credentials are available as soon as you open a terminal, without a manual `source env.sh`.
2. **PHPUnit runs triggered from the VSCode UI** — the PHPUnit wrapper script sources `env.sh` before invoking PHP, so IDE-triggered tests see the same variables as `composer test` does from the terminal.

`env.sh` is listed in `.gitignore` and will never be committed. Create it once in the workspace root with the variables you need — copy [`env.example.sh`](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/blob/master/env.example.sh) as a starting point.

> [!NOTE]
> The auto-loading takes effect for **new** terminal sessions. If your terminal was already open when you created or updated `env.sh`, run `source env.sh` once in that session or open a new terminal.

## Running the Demo Application

To run the demo application, follow these steps:

1. **Set your credentials** — create an `env.sh` in the workspace root (see [Environment variables (`env.sh`)](#environment-variables-envsh)), or replace the placeholders inside the demo file directly.

2. **Run the demo** for the brand you want:

   ```sh
   composer demo:cnr        # CentralNic Reseller  → examples/app_CNR.php
   composer demo:ibs        # internet.bs          → examples/app_IBS.php
   composer demo:moniker    # Moniker              → examples/app_MONIKER.php
   composer demo:datetime   # ApiDateTime parser   → examples/datetime.php (no credentials, no network)
   ```

   These are thin wrappers around plain PHP — edit the file listed on the right to change a demo, or run it directly without any tooling (`php -f examples/app_CNR.php`).

## CI / Testing

CI is powered by [reusable GitHub Actions workflows](https://github.com/centralnicgroup-opensource/rtldev-middleware-shareable-workflows). The test matrix covers:

| PHP Version | Status |
| ----------- | ------ |
| 8.3         | ✓      |
| 8.4         | ✓      |
| 8.5         | ✓      |

The matrix is configured via the repository variable `RTLDEV_MW_CI_PHP_MATRIX` and tracks the **actively-maintained** PHP versions — new versions are added as they enter active support and dropped once they reach end-of-life.

> [!NOTE]
> `composer.json` requires `php: >=8.3.0`, which sets the **minimum** only — the SDK runs on every version in the matrix above. Note that the source code itself is deliberately held to **PHP 8.3 language features** (Rector is pinned to 8.3) because the SDK also ships inside ionCube-encoded WHMCS integrations that cannot execute newer syntax. In short: runs on 8.3–8.5, but only _uses_ 8.3-level language features. Full rationale: [PHP Version Policy](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/blob/master/docs/agents/project-policies.md#php-version-policy).

## Maintainers

- **Kai Schwarz** - [KaiSchwarz-cnic](https://github.com/kaischwarz-cnic)
- **Asif Nawaz** - [AsifNawaz-cnic](https://github.com/AsifNawaz-cnic)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
