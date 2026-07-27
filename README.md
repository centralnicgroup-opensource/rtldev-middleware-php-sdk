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
- [Migration Guide](MIGRATION.md) — how to upgrade across major versions

## Usage

`composer require centralnic-reseller/php-sdk`

Find a demo app for the Brand of choice in the examples folder that should help you with getting started.

e.g. `examples/app_CNR.php` etc.

## Date & time values

The APIs declare their date columns in **UTC** and emit two shapes: a full timestamp (`2026-07-25 07:46:34`, optionally with a fractional-second part, as CNR sends) and a bare calendar date (`2030-07-17`, as internet.bs/Moniker send). `CNIC\ApiDateTime` parses both into one flat, immutable struct:

```php
use CNIC\ApiDateTime;

$dt = ApiDateTime::from("2026-07-25 07:46:34");
$dt->ts;             // 1784965594
$dt->date;           // "2026-07-25"
$dt->dateTime;       // "2026-07-25 07:46:34"
$dt->tz;             // "UTC"
$dt->isDateOnly();   // false
$dt->toArray();      // ready for json_encode()
```

| Field      | Type           | CNR `2026-07-25 07:46:34` | internet.bs / Moniker `2030-07-17` |
| ---------- | -------------- | ------------------------- | ---------------------------------- |
| `ts`       | `int\|null`    | `1784965594`              | **`null`** — exact instant unknown |
| `date`     | `string`       | `2026-07-25`              | `2030-07-17`                       |
| `dateTime` | `string\|null` | `2026-07-25 07:46:34`     | **`null`**                         |
| `tz`       | `string`       | `UTC`                     | `UTC`                              |

A bare calendar date names no instant, so `ts` and `dateTime` are **both null** for one — deliberately, rather than defaulting to midnight, which would be a fabricated instant indistinguishable from a real one. `date` is always populated, so there is unconditionally something to print; `$dt->ts === null` (or `isDateOnly()`) is the unambiguous test.

Parsing is strict. Values PHP's own date handling would silently roll over into a _different_ instant — `2026-02-30` becoming `2026-03-02`, `2026-13-45` becoming `2027-02-14`, `0000-00-00` becoming `-0001-11-30` — are refused with a `CNIC\Exception\InvalidDateTimeException`, as are offset-bearing values (never silently relabelled UTC). Use `ApiDateTime::tryFrom()` when a `null` is preferable to an exception:

```php
ApiDateTime::tryFrom(null);          // null
ApiDateTime::tryFrom("2026-02-30");  // null — refused, not coerced
```

> [!NOTE]
> This is a **parser, not a formatter**. Responses are not rewritten: `getPlain()`, `getHash()` and `getListHash()` keep returning the raw API strings, and this type is opt-in at the point where a value is actually used. There is no locale formatting and no `ext-intl` dependency — presenting a value in the viewer's timezone is a display concern for the consuming application:
>
> ```php
> (new \DateTimeImmutable("@{$dt->ts}"))->setTimezone(new \DateTimeZone("Europe/Berlin"));
> ```

Run `composer demo:datetime` for a runnable tour — it needs no credentials and makes no API calls.

## Dev Container

If you want to contribute, we recommend using Visual Studio Code and to follow the below setup instructions:

- Add an entry in your hosts file: `127.0.0.1         devsdk.centralnicreseller.net`

PHP SDK Data can be accessed via apache server at this url: `http://devsdk.centralnicreseller.net`

### Environment variables (`env.sh`)

The devcontainer looks for an `env.sh` file in the workspace root and **automatically sources it** in two places:

1. **Every new integrated-terminal session** — the file is sourced via `~/.zshenv` so credentials are available as soon as you open a terminal, without a manual `source env.sh`.
2. **PHPUnit runs triggered from the VSCode UI** — the PHPUnit wrapper script sources `env.sh` before invoking PHP, so IDE-triggered tests see the same variables as `composer test` does from the terminal.

`env.sh` is listed in `.gitignore` and will never be committed. Create it once in the workspace root with the variables you need — copy [`env.example.sh`](env.example.sh) as a starting point.

> [!NOTE]
> The auto-loading takes effect for **new** terminal sessions. If your terminal was already open when you created or updated `env.sh`, run `source env.sh` once in that session or open a new terminal.

## Running the Demo Application

To run the demo application, follow these steps:

1. **Set Your Credentials**:
   You need to ensure your credentials are available. The recommended approach inside the devcontainer is to create an `env.sh` file in the workspace root — see [Environment variables (`env.sh`)](#environment-variables-envsh) for details.
   Alternatively, you can directly replace the credential placeholders inside the demo application file.

2. **Execute the Demo**: Once the credentials are configured, run the appropriate demo command:

   Run the below Composer scripts:

   ```sh
   # CentralNic Reseller
   composer demo:cnr
   # internet.bs
   composer demo:ibs
   # Moniker
   composer demo:moniker
   # ApiDateTime parser — no credentials or network needed
   composer demo:datetime
   ```

   These are thin wrappers around plain PHP, so you can also run the examples directly without any tooling, e.g. `php -f examples/app_CNR.php`.

3. **Update Demo Contents**:
   If you need to modify the demo contents, the relevant files are located at:

   ```plaintext
   # CentralNic Reseller
   examples/app_CNR.php
   # internet.bs
   examples/app_IBS.php
   # Moniker
   examples/app_MONIKER.php
   # ApiDateTime parser
   examples/datetime.php
   ```

## CI / Testing

CI is powered by [reusable GitHub Actions workflows](https://github.com/centralnicgroup-opensource/rtldev-middleware-shareable-workflows). The test matrix covers:

| PHP Version | Status |
| ----------- | ------ |
| 8.3         | ✓      |
| 8.4         | ✓      |
| 8.5         | ✓      |

The matrix is configured via the repository variable `RTLDEV_MW_CI_PHP_MATRIX` and tracks the **actively-maintained** PHP versions — new versions are added as they enter active support and dropped once they reach end-of-life.

> [!NOTE]
> `composer.json` requires `php: >=8.3.0`, which sets the **minimum** only — the SDK runs on every version in the matrix above. Note that the source code itself is deliberately held to **PHP 8.3 language features** (Rector is pinned to 8.3) because the SDK also ships inside ionCube-encoded WHMCS integrations that cannot execute newer syntax. In short: runs on 8.3–8.5, but only _uses_ 8.3-level language features. See the CLAUDE.md "PHP Version Policy" for the full rationale.

## Maintainers

- **Kai Schwarz** - [KaiSchwarz-cnic](https://github.com/kaischwarz-cnic)
- **Asif Nawaz** - [KaiSchwarz-cnic](https://github.com/AsifNawaz-cnic)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
