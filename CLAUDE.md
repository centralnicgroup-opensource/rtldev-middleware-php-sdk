# Project Instructions

## Project Overview

This is the **PHP SDK** for Team Internet backend APIs (CentralNic Reseller, Internet.bs, Moniker). It provides a unified connector library under the `CNIC\` namespace with sub-namespaces for each registrar brand (`CNR`, `IBS`, `MONIKER`).

## Architecture

- **Namespace root:** `CNIC\` mapped to `src/` (PSR-4)
- **Inheritance chain:** `CNR\Client` and `IBS\Client` extend `CNIC\AbstractClient` directly. `MONIKER\Client` extends `IBS\Client` — Moniker and IBS share the same API platform; only their `config.json` (endpoints/credentials) differs. `CNR\SessionClient` uses the `SessionCapable` trait for login/logout; `IBS\SessionClient` and `MONIKER\SessionClient` are session-less.
- **Config-driven:** Each sub-namespace has a `config.json` with API URLs, parameter mappings, and feature flags
- **Interfaces:** `ColumnInterface`, `RecordInterface`, `ResponseInterface`, `LoggerInterface` (all in `CNIC\`) define contracts. All concrete classes formally declare `implements`:
  - `CNR\Column`, `IBS\Column` → `ColumnInterface`
  - `CNR\Record`, `IBS\Record` → `RecordInterface`
  - `CNR\Response`, `IBS\Response` → `ResponseInterface`
  - `CNR\Logger`, `IBS\Logger` → `LoggerInterface`
  - Type-hint against the interface rather than the concrete class (e.g. `LoggerInterface` not `Logger`, `ResponseInterface` not `Response`)
- **Factory pattern:** `ClientFactory::getClient()` for instantiation

## Coding Standards

### PHP Style

- **PSR-12** enforced via PHP CodeSniffer (config: `.github/linters/phpcs.xml`)
- **PHPStan Level 8** for static analysis (config: `.github/linters/phpstan.neon`)
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
- Static utility methods for parsers/formatters: `ResponseParser::parse()`, `CommandFormatter::flattenCommand()`
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

- **Framework:** PHPUnit 10.5+
- **Test namespace:** `CNICTEST\` mirroring `CNIC\` structure
- **Test classes:** Always `final class` extending `\PHPUnit\Framework\TestCase`
- **Method naming:** `testDescriptiveName` in camelCase
- **Mocking:** Use `ResponseTemplateManager::addTemplate()` to register mock API responses — do NOT use Mockery or Prophecy
- **Shared state:** Use `static` properties and `setUpBeforeClass()` for one-time client setup
- **No real API calls in unit tests** — all API responses are template-driven
- **MONIKER test files may mirror IBS test files and import IBS classes directly** — this is intentional. MONIKER and IBS share the same API platform and data format; only the brand URL and credentials differ. Do not flag this duplication as a coverage gap or suggest MONIKER-specific response/parser tests.

### Running Tests

```bash
composer test          # PHPUnit with coverage
composer lint          # PHP CodeSniffer
composer codefix       # Auto-fix coding standard violations
composer phpstan       # Static analysis
composer audit         # Check dependencies for known CVEs (Composer 2.4+)
```

## Codebase Modernization (Rector)

Rector is configured in `.github/linters/rector.php` targeting PHP 8.3 with `CODE_QUALITY`, `DEAD_CODE`, and `PHP_83` rulesets.

- **CI (lint workflow):** runs in dry-run mode (`composer rector`) — detects issues only, never writes.
- **Automated apply:** `.github/workflows/rector.yml` runs `composer rector:fix` on the first of each month and opens a PR (`chore/rector-modernization`) with commit message `chore(rector): apply automated modernization fixes`. Can also be triggered manually via `workflow_dispatch`.
- **Manual apply:** run `composer rector:fix` locally and open a PR with the same commit prefix.

## Git Conventions

- **Commit messages:** Conventional Commits (`feat:`, `fix:`, `chore:`, `docs:`, `refactor:`, `test:`)
- **Default branch:** `master`
- **Versioning:** Semantic versioning managed by CI release workflow

## Important Files

| Path                           | Purpose                                                |
| ------------------------------ | ------------------------------------------------------ |
| `src/CNR/config.json`          | CNR API endpoints and settings                         |
| `src/IBS/config.json`          | IBS API endpoints and settings                         |
| `src/MONIKER/config.json`      | Moniker API endpoints and settings                     |
| `.github/linters/phpcs.xml`    | CodeSniffer PSR-12 config                              |
| `.github/linters/phpstan.neon` | PHPStan level 8 config                                 |
| `phpunit.xml`                  | PHPUnit configuration                                  |
| `env.example.sh`               | Template for required env variables (copy to `env.sh`) |

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
- Modify `config.json` files without understanding the API implications
