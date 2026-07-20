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

## Usage

`composer require centralnic-reseller/php-sdk`

Find a demo app for the Brand of choice in the examples folder that should help you with getting started.

e.g. `examples/app_CNR.php` etc.

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
