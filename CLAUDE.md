# Project Instructions

## Project Overview

This is the **PHP SDK** for Team Internet backend APIs (CentralNic Reseller, Internet.bs, Moniker). It provides a unified connector library under the `CNIC\` namespace with sub-namespaces for each registrar brand (`CNR`, `IBS`, `MONIKER`).

## Architecture

- **Namespace root:** `CNIC\` mapped to `src/` (PSR-4)
- **Inheritance chain:** `CNR\Client` → `CNR\SessionClient` → `IBS\Client` → `IBS\SessionClient` → `MONIKER\SessionClient`
- **Config-driven:** Each sub-namespace has a `config.json` with API URLs, parameter mappings, and feature flags
- **Interfaces:** `ColumnInterface`, `RecordInterface`, `ResponseInterface`, `LoggerInterface` define contracts; all concrete classes formally declare `implements`
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

### Running Tests

```bash
composer test          # PHPUnit with coverage
composer lint          # PHP CodeSniffer
composer codefix       # Auto-fix coding standard violations
composer phpstan       # Static analysis
```

## Git Conventions

- **Commit messages:** Conventional Commits (`feat:`, `fix:`, `chore:`, `docs:`, `refactor:`, `test:`)
- **Default branch:** `master`
- **Versioning:** Semantic versioning managed by CI release workflow

## Important Files

| Path                           | Purpose                            |
| ------------------------------ | ---------------------------------- |
| `src/CNR/config.json`          | CNR API endpoints and settings     |
| `src/IBS/config.json`          | IBS API endpoints and settings     |
| `src/MONIKER/config.json`      | Moniker API endpoints and settings |
| `.github/linters/phpcs.xml`    | CodeSniffer PSR-12 config          |
| `.github/linters/phpstan.neon` | PHPStan level 8 config             |
| `phpunit.xml`                  | PHPUnit configuration              |

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
- **Workflow transitions:** To Do (`11`), In Progress (`21`), In Review (`41`), QA (`61`), Ready for Deployment (`51`), Done (`31`), Cancelled (`91`)

## Do NOT

- Read, display, or expose the contents of `env.sh` — it contains secrets
- Add dependencies without explicit request — this is a lightweight SDK
- Create custom exception classes — use `\Exception` directly
- Use mocking frameworks (Mockery, Prophecy) — use ResponseTemplateManager
- Add `@author` tags to docblocks
- Modify `config.json` files without understanding the API implications
