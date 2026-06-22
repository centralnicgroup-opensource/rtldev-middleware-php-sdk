# Project Instructions

## Project Overview

This is the **PHP SDK** for Team Internet backend APIs (CentralNic Reseller, Internet.bs, Moniker). It provides a unified connector library under the `CNIC\` namespace with sub-namespaces for each registrar brand (`CNR`, `IBS`, `MONIKER`).

## Architecture

- **Namespace root:** `CNIC\` mapped to `src/` (PSR-4)
- **Shared abstracts (in `CNIC\`):**
  - `AbstractClient` — shared foundation for all registrar API clients; subclasses provide `request()`, the default logger, and the SocketConfig subtype
  - `AbstractSocketConfig` — shared base for all SocketConfig classes; subclasses provide `getPOSTDataParams()` and their own `$parameters` array
  - `HttpTransport` — extracted cURL layer; owns the cURL handle lifecycle and exposes a single `post()` method
  - `Registrar` enum — backed by string values `CNR`, `CNIC` (legacy alias), `IBS`, `MONIKER`; used by `ClientFactory` for registrar matching
- **Inheritance chain:**
  - `CNR\Client` and `IBS\Client` both extend `AbstractClient` directly
  - `MONIKER\Client` extends `IBS\Client` — Moniker and IBS share the same API platform; only their `config.json` (endpoints/credentials) differs
  - `CNR\SessionClient extends CNR\Client` and uses the `SessionCapable` trait for login/logout
  - `IBS\SessionClient extends IBS\Client` and `MONIKER\SessionClient extends MONIKER\Client` — these are thin wrappers with no session-based login/logout
  - `CNR\SocketConfig`, `IBS\SocketConfig`, `MONIKER\SocketConfig` all extend `AbstractSocketConfig`
- **IBS shares CNR data models:** `IBS\Response` extends `CNR\Response`, `IBS\Record` extends `CNR\Record` — IBS adds only the parsing differences on top. `IBS\Column` is a **standalone** implementation (does not extend `CNR\Column`) because IBS JSON responses carry mixed-typed values (strings, nested objects, lists) that CNR columns do not.
- **Config-driven:** Each sub-namespace has a `config.json` with API URLs, parameter mappings, and feature flags
- **Interfaces:** `ColumnInterface`, `RecordInterface`, `ResponseInterface`, `LoggerInterface` (all in `CNIC\`) define contracts. All concrete classes formally declare `implements`:
  - `CNR\Column`, `IBS\Column` → `ColumnInterface`
  - `CNR\Record`, `IBS\Record` → `RecordInterface`
  - `CNR\Response`, `IBS\Response` → `ResponseInterface`
  - `CNR\Logger`, `IBS\Logger` → `LoggerInterface`
  - Type-hint against the interface rather than the concrete class (e.g. `LoggerInterface` not `Logger`, `ResponseInterface` not `Response`)
- **Static utilities:** `ResponseParser::parse()` and `ResponseTranslator` (both `CNR\` and `IBS\`) for parsing/translating raw API responses; `CommandFormatter::flattenCommand()` for request serialisation
- **Factory pattern:** `ClientFactory::getClient()` returns a `SessionClient|IBSSessionClient|MONIKERSessionClient` union type
- **Public API annotation:** classes and interfaces that form the public API are annotated `@psalm-api` to suppress unused-symbol warnings

## Coding Standards

### PHP Style

- **PSR-12** enforced via PHP CodeSniffer (config: `.github/linters/phpcs.xml`)
- **PHPStan Level 9** (strictest) for static analysis (config: `.github/linters/phpstan.neon`)
- **Psalm Level 1** (strictest) for additional static analysis (config: `.github/linters/psalm.xml`) — annotate public API symbols with `@psalm-api`
- **Both tools analyze `src/` and `tests/`** (aligned scope). Psalm runs with `findUnusedCode="true"`; because PHPUnit invokes test classes/methods via reflection, the dead-code family (`UnusedClass`, `PossiblyUnusedReturnValue`, `UnusedMethodCall`) is suppressed for `tests/` in `psalm.xml` to avoid false positives. All other Psalm checks apply to test code in full.
- Always include `declare(strict_types=1);` in new or modified files
- Use typed properties and return type declarations on all new code
- Use `@var` PHPDoc with generic array types: `array<string, mixed>`, `string[]`, `array<string>`

### Naming

- Classes: PascalCase (e.g., `ResponseParser`, `SessionClient`)
- Methods: camelCase (e.g., `getColumnIndex`, `hasNextPage`)
- Properties: camelCase with visibility (`protected array $context = []`)
- Constants: UPPER_SNAKE_CASE
- Import aliases: short uppercase abbreviations (`use CNIC\CNR\ResponseParser as RP;`)

### Class Patterns

- Setters use fluent interface (return `$this`)
- Throw `\Exception` directly (no custom exception hierarchy)
- Password fields must be sanitized before logging: `$cmd["PASSWORD"] = "***"`

### File Header

```php
<?php

declare(strict_types=1);

/**
 * CNIC\<SubNamespace>
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\<SubNamespace>;
```

## Testing

- **Framework:** PHPUnit 12+
- **Test namespace:** `CNICTEST\` mirroring `CNIC\` structure
- **Test classes:** Always `final class` extending `\PHPUnit\Framework\TestCase`
- **Method naming:** `testDescriptiveName` in camelCase
- **Mocking:** Use `ResponseTemplateManager::addTemplate()` to register mock API responses — do NOT use Mockery or Prophecy
- **Shared state:** Use `static` properties and `setUpBeforeClass()` for one-time client setup
- **No real API calls in unit tests** — all API responses are template-driven
- **MONIKER test files may mirror IBS test files and import IBS classes directly** — this is intentional. MONIKER and IBS share the same API platform and data format; only the brand URL and credentials differ. Do not flag this duplication as a coverage gap or suggest MONIKER-specific response/parser tests.

### Running Tests

```bash
composer test          # PHPUnit with coverage (.github/phpunit.xml)
composer lint          # phpcs + phpstan + psalm + shellcheck
composer codefix       # Auto-fix coding standard violations (phpcbf)
composer phpstan       # PHPStan static analysis only
composer psalm         # Psalm static analysis only (monochrome)
composer psalm:colored # Psalm static analysis (colored output)
composer rector        # Rector dry-run (detect modernization opportunities)
composer rector:fix    # Rector apply (write modernized code)
composer audit         # Check dependencies for known CVEs (Composer 2.4+)
```

## Codebase Modernization (Rector)

Rector is configured in `.github/linters/rector.php` targeting PHP 8.3 with `CODE_QUALITY`, `DEAD_CODE`, and `PHP_83` rulesets.

- **CI (lint workflow):** runs in dry-run mode (`composer rector`) — detects issues only, never writes.
- **Automated apply:** `.github/workflows/rector.yml` runs `composer rector:fix` on the first of each month and opens a PR (`chore/rector-modernization`) with commit message `chore(rector): apply automated modernization fixes`. Can also be triggered manually via `workflow_dispatch`.
- **Manual apply:** run `composer rector:fix` locally and open a PR with the same commit prefix.

## API Documentation

API docs are generated by **Doctum** (`composer docs`, config `.github/doctum.config.php`) together with a UML class diagram (`composer generate-uml`) into the `docs/` folder. `docs/` is **git-ignored** — it is not committed to the repository.

- **Local preview:** run `composer docs` then `composer docs:serve` (serves `docs/` on port 8000).
- **Publishing:** handled by semantic-release on release. The `@semantic-release/exec` plugin regenerates the docs in its `prepareCmd` and publishes them to the `gh-pages` branch in its `successCmd` (`.github/scripts/publish-docs.sh`, force-pushed as a single commit). Because `prepare`/`success` only run when a release is actually cut — and releases only happen on `feat`/`fix` (i.e. `src/` changes) — docs are regenerated and published **only when the library source changed**.
- **Hosting:** GitHub Pages serves the `gh-pages` branch at <https://centralnicgroup-opensource.github.io/rtldev-middleware-php-sdk/>. The publish script needs `GITHUB_TOKEN` (contents:write), already provided by the release workflow.

## PHP Version Policy

The SDK targets **PHP 8.3** as both its minimum and maximum supported version. This is not arbitrary — the SDK is deployed inside WHMCS environments and must align with what WHMCS itself supports:

- **WHMCS 9 (GA)** — minimum supported PHP: 8.3
- **WHMCS 8.13 (LTS)** — maximum supported PHP: 8.3

PHP 8.3 is therefore the correct ceiling: it is simultaneously the floor of the current GA release and the ceiling of the current LTS release.

Do **not** bump `composer.json`, `rector.php`, or CI matrix entries beyond PHP 8.3 until WHMCS raises its own minimum supported PHP version. Track [RSRMID-2826](https://centralnic.atlassian.net/browse/RSRMID-2826) for the unblocking condition.

## Dependency Lockfile Policy

- **`composer.lock` is committed deliberately.** Conventional guidance says a library should not commit its lockfile because consumers ignore it (Composer resolves the library's constraints fresh into the consumer's own `composer.lock`). That still holds for consumers — keeping our lockfile does **not** affect downstream installs. We commit it anyway so that CI, devcontainer, and local developer setups all resolve the exact same dependency tree, giving reproducible lint/test runs and pinning the dev toolchain (PHPUnit, PHPStan, Psalm, Rector). Do not remove or git-ignore `composer.lock`.
- **`pnpm-lock.yaml` is committed** (the project migrated from npm to pnpm; the old `package-lock.json` is gone). Both lockfiles are `export-ignore`d in `.gitattributes` so they stay out of the Composer distribution archive.

## Git Conventions

- **Commit messages:** Angular/Conventional Commits with **mandatory scope**: `<type>(<scope>): <summary>` — e.g. `fix(psalm): resolve static analysis warnings`, `feat(ibs): add response translation`. Never append a `Co-Authored-By:` trailer.
- **Commit type selection:** `fix` and `feat` are reserved for changes to library source code in `src/` — they trigger a release. For everything else use a non-releasing type: `ci` for CI workflows and devcontainer, `build` for build tooling or scripts, `chore` for housekeeping, `docs` for documentation, `test` for test-only changes, `refactor` for internal restructuring without behaviour change.
- **Breaking changes:** When a `src/` change breaks the public API, add a `BREAKING CHANGE: <short summary>` line to the commit message body (blank line after the subject). This triggers a **major** version bump via semantic-release. Example:

  ```
  feat(client): remove deprecated setProxy() method

  BREAKING CHANGE: setProxy() has been removed; use HttpTransport::withProxy() instead.
  ```

- **Branch naming:** prefix with the Jira issue ID — e.g. `RSRMID-2821/short-description`
- **Pull requests:** always include the Jira issue link in the PR description. After opening the PR, add the PR URL as a comment on the Jira issue.
- **Default branch:** `master`
- **Versioning:** Semantic versioning managed by CI release workflow

## Important Files

| Path                           | Purpose                                                 |
| ------------------------------ | ------------------------------------------------------- |
| `src/AbstractSocketConfig.php` | Shared abstract base for all SocketConfig classes       |
| `src/HttpTransport.php`        | Low-level cURL HTTP transport (extracted from clients)  |
| `src/Registrar.php`            | `Registrar` enum — string-backed, used by ClientFactory |
| `src/CNR/config.json`          | CNR API endpoints and settings                          |
| `src/IBS/config.json`          | IBS API endpoints and settings                          |
| `src/MONIKER/config.json`      | Moniker API endpoints and settings                      |
| `.github/linters/phpcs.xml`    | CodeSniffer PSR-12 config                               |
| `.github/linters/phpstan.neon` | PHPStan level 9 (strictest) config                      |
| `.github/linters/psalm.xml`    | Psalm level 1 (strictest) config                        |
| `.github/phpunit.xml`          | PHPUnit configuration                                   |
| `env.example.sh`               | Template for required env variables (copy to `env.sh`)  |

## Atlassian / JIRA

- **Cloud ID:** `4e50e119-d5ea-4f89-afb1-d4cd47e40177`
- **Default project:** `RSRMID` (3rd-party Software Integrations / Middleware)
- **Default component:** `PHP-SDK` (id: `10232`)
- **Work Category field:** `customfield_12383` (required, type: select)
  - `13284` = Strategic
  - `13285` = Maintain Revenue /BAU
  - `13286` = Tech Debt
  - `13287` = Security
- **Business Unit field:** `customfield_10027` (required, type: multi-checkbox)
  - `10187` = CentralNic Reseller (default for this SDK)
- **Issue types:** Task (`10002`), Bug (`10004`), Story (`10001`), Epic (`10000`)
- **Workflow transitions:** To Do (`11`), In Progress (`21`), In Review (`41`), QA (`61`), Ready for Deployment (`51`), Done (`31`), Stand-by (`71`), Cancelled (`91`)
- **Closing an issue (mandatory time tracking):** an issue will not stay in **Done** without a worklog — Jira automation stamps a `missing-time-spent` label on issues with no time logged and auto-reopens them. Correct sequence: (1) add a worklog (`timeSpent`); (2) remove the `missing-time-spent` label; (3) transition to Done (`31`). When the time amount isn't obvious, ask rather than guessing.
- **Known account IDs:** Kai Schwarz `61358848ee2fd0006aac7b4f`, Asif Nawaz `62a84362bf7afc006f3b15e5`
- **Issue descriptions:** always use ADF (Atlassian Document Format, JSON) — never markdown. Markdown renders literal `\n` characters instead of line breaks.

## Claude Code Allowlist (`.claude/settings.json`)

The Bash allowlist is intentionally scoped to known-safe, non-destructive commands only. The guiding rules:

- **Composer:** explicit script names only (`test`, `lint`, `codefix`, `phpstan`, `install`, …). Destructive subcommands (`require`, `update`, `remove`, `create-project`) are not allowed and will always prompt.
- **gh CLI:** read-only subcommands (`pr view/list/checks/create`, `issue view/list`, `run view/list`, `repo view`). `gh api` is intentionally omitted — it cannot be narrowed to safe endpoints without allowing arbitrary REST mutations.
- **git:** read-only operations only. `git branch` is limited to explicit list flags (`-a`, `-r`, `-v`, `-vv`, `--list`, `--show-current`); destructive flags (`-d`, `-D`, `-m`) will always prompt.

When adding new entries to the allowlist, confirm the command is strictly read-only or a known-safe project script before allowing it without a prompt.

## Do NOT

- Read, display, or expose the contents of `env.sh` — it contains secrets
- Add dependencies without explicit request — this is a lightweight SDK
- Create custom exception classes — use `\Exception` directly
- Use mocking frameworks (Mockery, Prophecy) — use ResponseTemplateManager
- Add `@author` tags to docblocks
- Add `Co-Authored-By:` trailers to commit messages
