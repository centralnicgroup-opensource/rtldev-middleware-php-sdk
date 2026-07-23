# Project Instructions

> **How this file is organised:** this file holds the rules and facts needed on nearly every task. Verbose reference material with long rationale lives in `docs/agents/*.md` and is linked inline — read those on demand, don't duplicate them back here. When a policy detail changes, edit the linked doc (the single source of truth), not a summary.

## Project Overview

This is the **PHP SDK** for Team Internet backend APIs (CentralNic Reseller, Internet.bs, Moniker). It provides a unified connector library under the `CNIC\` namespace with sub-namespaces for each registrar brand (`CNR`, `IBS`, `MONIKER`).

## Architecture

Compact summary below; the **full deep dive** (per-class detail, design rationale, RSRMID history) is in [docs/agents/architecture.md](docs/agents/architecture.md) — read it before changing anything structural.

- **Namespace root:** `CNIC\` mapped to `src/` (PSR-4). Brand sub-namespaces: `CNR`, `IBS`, `MONIKER`.
- **Shared abstracts (in `CNIC\`):** `AbstractClient`, `AbstractSocketConfig`, `HttpTransport` (cURL layer), `AbstractResponseTemplateManager`, `AbstractResponseTranslator`, `AbstractResponse` (core contract, template-method constructor), `AbstractRecord`. Enums: `Registrar` (string-backed brand id) and `System` (`OTE`/`LIVE`).
- **Brands are siblings, not parent/child:** `CNR\Response`/`IBS\Response` both extend `AbstractResponse`; `CNR\Record`/`IBS\Record` both extend `AbstractRecord`. `MONIKER\Client extends IBS\Client` (same platform; only `SocketConfig` differs) and MONIKER reuses IBS's Response/Record. `IBS\Column` is standalone (not a `CNR\Column` subclass) because IBS carries mixed-typed JSON values.
- **Response construction is a template method:** `AbstractResponse::__construct()` is the skeleton; brands implement the `translate()`/`populate()`/`newRecord()` hooks. Add a brand or change parsing by implementing hooks — never reimplement the constructor.
- **Config-driven:** each `SocketConfig` (extends `AbstractSocketConfig`) carries endpoints/params/flags as typed properties (no more `config.json`).
- **Type-hint against interfaces:** `ColumnInterface`, `RecordInterface`, `ResponseInterface`, `ExtendedResponseInterface`, `LoggerInterface`. Use `LoggerInterface` not `Logger`, `ResponseInterface` not `Response`.
- **Public API symbols** are annotated `@psalm-api` to suppress unused-symbol warnings.

**Load-bearing "do NOT" directives (rationale in the deep-dive doc — do not undo these):**

- Do **not** re-add the 5 CNR-only methods (`getQueuetime`/`getRuntime`/`isTmpError`/`isPending`/`getListHash`) to the core `ResponseInterface` — they live on `ExtendedResponseInterface` (CNR only); narrow via `instanceof \CNIC\ExtendedResponseInterface` to use them.
- Do **not** reimplement the `request()` lifecycle per brand — it is a template method on `AbstractClient::performRequest()`. The public `request(array $cmd = [], string $path = "")` signature is **symmetric across all brands** (CNR just defaults `$path` to `api/call.cgi`); vary a brand only through the `buildCommand()`/`newResponse()` hooks and the `$curlopts` property. (Ref: RSRMID-2909, which deliberately dropped the earlier "CNR must never accept a per-request path" rule.)
- Do **not** "symmetrise" columns onto a `newColumn()` factory like records — it is infeasible under PHPStan L9 / Psalm L1; keep the `registerColumn(ColumnInterface)` shape.
- `ClientFactory::getClient()` returns the shared `AbstractClient` — reach brand-specific capabilities (CNR `login()`/`logout()`/`saveSession()`) by narrowing to the concrete type.

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
- **Exceptions:** throw an exception from the `CNIC\Exception` hierarchy — a class extending the base `CnicException` (which extends `\Exception`). Reuse the existing types where they fit — `UnsupportedFeatureException` (capability not available on this platform/response), `UnknownRegistrarException` (unresolvable registrar identifier), `PaginationException` (list-pagination misuse) — and add a new `CnicException` subclass when a genuinely distinct failure mode arises, rather than reaching for a bare `\Exception`. The hierarchy is **additive and non-breaking**: because every class ultimately extends `\Exception`, existing `catch (\Exception)` consumer code keeps working, so introducing or extending it needs no `BREAKING CHANGE`/major bump. (Ref: RSRMID-2895.) Do **not** create parallel ad-hoc exception types outside `CNIC\Exception`.
- Password fields must be sanitized before logging: `$cmd["PASSWORD"] = "***"`

### File Header

```php
<?php

declare(strict_types=1);

/**
 * CNIC\<SubNamespace>
 * Copyright © Team Internet Group PLC
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
- **Functional tests (`tests/Functional/`) are the one deliberate exception** — they drive `HttpTransport` against a local PHP built-in HTTP server over loopback (no external API) to assert wire-level behaviour that templates cannot, such as per-call cURL options not leaking across the reused handle. They start the server in `setUpBeforeClass()` and `markTestSkipped()` if it cannot bind a port, so a locked-down runner never turns them red.
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
composer demo:cnr      # Run the CNR demo app (examples/app_CNR.php)
composer demo:ibs      # Run the IBS demo app
composer demo:moniker  # Run the Moniker demo app
```

> **CI coverage:** `composer audit` is a pre-flight convenience command, not a `composer.json` script. Dependency CVE gating is already enforced on every PR by the shared `php-sdk-lint.yml` workflow (its `composer-audit` job runs `composer audit --no-dev`), which `.github/workflows/lint.yml` delegates to. There is no separate audit step to wire into this repo.

## Build, CI & Policies

Short reminders; full detail in the linked docs.

- **PHP versions — [docs/agents/project-policies.md](docs/agents/project-policies.md):** runtime floor is **8.3** (no ceiling; CI matrix 8.3/8.4/8.5), but the source **language-feature ceiling is pinned at 8.3** (WHMCS ships ionCube-encoded and can't handle newer syntax). Run/test on newer PHP, but never hand-write or `rector:fix` into 8.4+ syntax, and don't bump `rector.php` past 8.3.
- **Lockfiles — [docs/agents/project-policies.md](docs/agents/project-policies.md):** `composer.lock` and `pnpm-lock.yaml` are committed **deliberately** (reproducible CI/dev toolchain). Do not remove or git-ignore them.
- **Distribution archive — [docs/agents/project-policies.md](docs/agents/project-policies.md):** `export-ignore` in `.gitattributes` keeps the Packagist dist zip lean (only `src/`, `composer.json`, `LICENSE`, `README.md` ship). Add a matching `export-ignore` line for any new dev-only root file.
- **Claude Code allowlist — [docs/agents/project-policies.md](docs/agents/project-policies.md):** `.claude/settings.json` allows only known-safe, non-destructive commands. When adding entries, confirm the command is strictly read-only or a known-safe project script.
- **CI / GitHub Actions, Rector, generated docs — [docs/agents/ci-release.md](docs/agents/ci-release.md):** most workflows delegate to shared reusable workflows (caching/matrix/coverage/audit live in the shared repo, not here); Rector modernization runs monthly; Doctum API docs publish to `gh-pages` on release. Note the reusable-workflow permission intersection and SHA-pinning rules before touching workflows.

## Git Conventions

- **Commit messages:** Angular/Conventional Commits with **mandatory scope**: `<type>(<scope>): <summary>` — e.g. `fix(psalm): resolve static analysis warnings`, `feat(ibs): add response translation`. Never append a `Co-Authored-By:` trailer.
- **Commit type selection:** `fix` and `feat` are reserved for changes to library source code in `src/` — they trigger a release. For everything else use a non-releasing type: `ci` for CI workflows and devcontainer, `build` for build tooling or scripts, `chore` for housekeeping, `docs` for documentation, `test` for test-only changes, `refactor` for internal restructuring without behaviour change.
- **Breaking changes:** When a `src/` change breaks the public API, add a `BREAKING CHANGE: <short summary>` line to the commit message body (blank line after the subject). This triggers a **major** version bump via semantic-release. Example:

  ```
  feat(client): remove deprecated setProxy() method

  BREAKING CHANGE: setProxy() has been removed; use HttpTransport::withProxy() instead.
  ```

- **Branch creation:** always branch from an up-to-date default branch. Before `git checkout -b`, run `git checkout master && git pull --ff-only` so the new branch starts from the latest `origin/master`. Branching from a stale local `master` (or another feature branch) risks re-doing work that already landed upstream.
- **Branch naming:** prefix with the Jira issue ID — e.g. `RSRMID-2821/short-description` (see [docs/agents/issue-tracker.md](docs/agents/issue-tracker.md) for the Jira side)
- **Pull requests:** always include the Jira issue link in the PR description. After opening the PR, add the PR URL as a comment on the Jira issue.
- **Merging PRs:** use **rebase-merge** (`gh pr merge --rebase`). Squash merges are **disabled** at the repo level (`gh pr merge --squash` fails with "Squash merges are not allowed on this repository"); rebase keeps a linear history without merge commits.
- **Default branch:** `master`
- **Versioning:** Semantic versioning managed by CI release workflow

## Important Files

| Path                                      | Purpose                                                  |
| ----------------------------------------- | -------------------------------------------------------- |
| `src/AbstractSocketConfig.php`            | Shared abstract base for all SocketConfig classes        |
| `src/AbstractResponseTemplateManager.php` | Shared base for brand ResponseTemplateManager classes    |
| `src/AbstractResponseTranslator.php`      | Shared translate()/findMatch()/placeholder pipeline      |
| `src/AbstractResponse.php`                | Shared base for brand Response classes (core contract)   |
| `src/AbstractRecord.php`                  | Shared base for brand Record classes                     |
| `src/ResponseInterface.php`               | Universal Response contract (all brands)                 |
| `src/ExtendedResponseInterface.php`       | CNR-only capabilities (telemetry/status/list-hash)       |
| `src/HttpTransport.php`                   | Low-level cURL HTTP transport (extracted from clients)   |
| `src/Exception/CnicException.php`         | Base of the additive `CNIC\Exception` hierarchy          |
| `src/Registrar.php`                       | `Registrar` enum — string-backed, used by ClientFactory  |
| `src/System.php`                          | `System` enum — string-backed `OTE`/`LIVE` client system |
| `src/CNR/SocketConfig.php`                | CNR API endpoints and settings (typed properties)        |
| `src/IBS/SocketConfig.php`                | IBS API endpoints and settings (typed properties)        |
| `src/MONIKER/SocketConfig.php`            | Moniker API endpoints and settings (typed properties)    |
| `.github/linters/phpcs.xml`               | CodeSniffer PSR-12 config                                |
| `.github/linters/phpstan.neon`            | PHPStan level 9 (strictest) config                       |
| `.github/linters/psalm.xml`               | Psalm level 1 (strictest) config                         |
| `.github/phpunit.xml`                     | PHPUnit configuration                                    |
| `env.example.sh`                          | Template for required env variables (copy to `env.sh`)   |

## Atlassian / JIRA

Work is tracked in **Jira Cloud**, project `RSRMID`, component `PHP-SDK` — not GitHub Issues. Always-on rules for every session:

- **Descriptions must be ADF** (Atlassian Document Format, JSON) — never markdown. Markdown renders literal `\n` instead of line breaks.
- **Log time before Done:** an issue won't stay in **Done** without a worklog — Jira automation stamps a `missing-time-spent` label and auto-reopens it. Sequence: (1) add worklog (`timeSpent`); (2) remove the `missing-time-spent` label; (3) transition to Done. When the amount isn't obvious, ask rather than guessing.

The full **operational reference** — Cloud ID, project/component IDs, the required Work Category & Business Unit custom fields, issue-type & transition IDs, known account IDs, MCP tool names and JQL examples — is the single source of truth in [docs/agents/issue-tracker.md](docs/agents/issue-tracker.md). Update that file (not this section) when any ID or field changes.

## Do NOT

- Read, display, or expose the contents of `env.sh` — it contains secrets
- Add dependencies without explicit request — this is a lightweight SDK
- Create ad-hoc exception classes **outside** the `CNIC\Exception` hierarchy, or throw a bare `\Exception` for a new failure mode — extend `CnicException` instead (see **Class Patterns → Exceptions**)
- Use mocking frameworks (Mockery, Prophecy) — use ResponseTemplateManager
- Add `@author` tags to docblocks
- Add `Co-Authored-By:` trailers to commit messages

## Agent skills & reference docs

Detailed, on-demand reference lives under `docs/agents/` — read the relevant file when the task calls for it:

- **[docs/agents/architecture.md](docs/agents/architecture.md)** — full architecture deep dive (per-class detail, design rationale, RSRMID history).
- **[docs/agents/ci-release.md](docs/agents/ci-release.md)** — CI/GitHub Actions wiring, Rector modernization, Doctum API-doc pipeline.
- **[docs/agents/project-policies.md](docs/agents/project-policies.md)** — PHP version policy, lockfiles, distribution archive, Claude Code allowlist.
- **[docs/agents/issue-tracker.md](docs/agents/issue-tracker.md)** — Jira Cloud (project `RSRMID`, component `PHP-SDK`) via the Atlassian MCP tools; all issue IDs/fields/transitions.
- **[docs/agents/domain.md](docs/agents/domain.md)** — domain-doc layout: one `CONTEXT.md` + `docs/adr/` at the repo root (created lazily by the domain-modeling skill).
