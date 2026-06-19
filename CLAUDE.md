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

## Git Conventions

- **Commit messages:** Angular/Conventional Commits with **mandatory scope**: `<type>(<scope>): <summary>` — e.g. `fix(psalm): resolve static analysis warnings`, `feat(ibs): add response translation`. Never append a `Co-Authored-By:` trailer.
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
