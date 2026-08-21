# Project Instructions

> **This file is read in full on every task**, so it holds only rules needed on nearly every task — one imperative line each. Rationale, alternatives and RSRMID history belong in the linked `docs/agents/*.md`; never summarise them back here. Which doc a change lands in: [CONTRIBUTING.md → Where a change gets documented](CONTRIBUTING.md#where-a-change-gets-documented).

## Project Overview

This is the **PHP SDK** for Team Internet backend APIs (CentralNic Reseller, Internet.bs, Moniker). It provides a unified connector library under the `CNIC\` namespace with sub-namespaces for each registrar brand (`CNR`, `IBS`, `MONIKER`).

## Architecture

Facts below; the class inventory is derivable from `src/` and the **full deep dive** (rationale, alternatives, ticket history) is in [docs/agents/architecture.md](docs/agents/architecture.md) — read it before changing anything structural.

- **Namespace root:** `CNIC\` mapped to `src/` (PSR-4). Brand sub-namespaces: `CNR`, `IBS`, `MONIKER`.
- **Shared abstracts (in `CNIC\`):** `AbstractClient`, `AbstractSocketConfig`, `HttpTransport`, `AbstractResponseTemplateManager`, `AbstractResponseTranslator`, `AbstractResponse`. Shared **concretes:** `Record`, `Column`, `ApiDateTime`, `Paginator` (`final`, a value object over five ints — never give it a `Response` reference). Enum: `System` (`OTE`/`LIVE`) — derived from the configured URL, never stored.
- **Brands are siblings, not parent/child:** `CNR\Response`/`IBS\Response` both extend `AbstractResponse`. `MONIKER\Client extends IBS\Client` (same platform; only `SocketConfig` differs) and reuses IBS's Response. No brand declares a `Record` or a `Column` — both are shared concretes, and a value-type narrowing goes in a native return type on the interface (`getStringByIndex()`/`getStringByKey()`), never in a generic.
- **Response construction is a template method:** brands implement the `translate()`/`populate()`/`newRecord()`/`newResponseParser()` hooks — never reimplement `AbstractResponse::__construct()`.
- **The `request()` lifecycle is a template method too** (`AbstractClient::performRequest()`), and public `request(array $cmd = [], string $path = "")` is symmetric across brands. Vary a brand only through `buildCommand()`/`newResponse()`/`newSocketConfig()`.
- **Config-driven:** each `SocketConfig` (extends `AbstractSocketConfig`) carries endpoints/params/flags as typed properties (no `config.json`).
- **Connection configuration lives on the `SocketConfig`, never on the client** — reach it via `AbstractClient::getSocketConfig()` (covariant `CNR\Client::getSocketConfig(): CNR\SocketConfig` is the one narrowing point).
- **Type-hint against interfaces:** `ColumnInterface`, `RecordInterface`, `ResponseInterface`, `ExtendedResponseInterface`, `RoleCredentialsInterface`, `ResponseParserInterface`, `ResponseTemplateManagerInterface` (registry) / `ResponseTemplateFactoryInterface` (opt-in Response production), `TransportInterface`, `LoggerInterface`, `LogSinkInterface`.
- **An interface declaration must match its implementation's signature** — a parameter that exists only on the implementation is unreachable to the interface-typed consumers this project mandates. Adding one to the interface is **breaking**.
- **Public API symbols** are annotated `@psalm-api` to suppress unused-symbol warnings.

**Load-bearing decisions are locked by guard tests** — the `tests/*SeamTest.php` files plus `tests/ResponseInterfaceConsumerTest.php`, `tests/AbstractClientConfigDriftTest.php`, `tests/HttpTransportCurlOptionsTest.php`, `tests/Functional/HttpTransportTest.php`. They are structural/reflection tests by necessity: undoing one of these decisions is behaviour-preserving on the day it lands, so a green suite is not evidence the decision still holds.

**If your change makes a guard test fail, you are undoing a decision, not fixing a test.** Read the guard's class docblock (directive, failure mode, revisit condition) and its entry in [architecture.md](docs/agents/architecture.md) before going further. Never delete or weaken a guard as a passing cleanup.

Three directives have no guard test and therefore live here:

- Do **not** "symmetrise" columns onto a `newColumn()` factory like records — keep the `registerColumn(ColumnInterface)` shape. The original "infeasible under PHPStan L9 / Psalm L1" argument died with `CNR\Column`; re-run the analysers before reopening, and do not quote it. (RSRMID-2899, RSRMID-2942)
- Do **not** rewrite date columns in the response data. Do **not** grow `CNIC\ApiDateTime` beyond an opt-in UTC-only **parser** — accepting both `-` and `/` separators is in scope, but no `in($tz)`, no locale formatting, no `ext-intl` **in this class** (the extension is required SDK-wide, for IDN conversion only), no markup helpers. (RSRMID-2318; RSRMID-2926; RSRMID-2977)
- Do **not** make `dateTime` fall back to `date` for date-only values — `ts` and `dateTime` are null _together_. (RSRMID-2318)

## Coding Standards

### PHP Style

- **PSR-12** (phpcs, `.github/linters/phpcs.xml`), **PHPStan level 9** (`.github/linters/phpstan.neon`), **Psalm level 1** (`.github/linters/psalm.xml`). Both analysers cover `src/` **and** `tests/` — keep it that way.
- Annotate public API symbols with `@psalm-api`. Remove a Psalm suppression that stops firing rather than keeping it "just in case" — a never-applied suppression fails the lint (`UnusedIssueHandlerSuppression`). Detail: [project-policies.md → Lint & Formatting Toolchain](docs/agents/project-policies.md).
- Always include `declare(strict_types=1);` in new or modified files.
- Use typed properties and return type declarations on all new code.
- Use `@var` PHPDoc with generic array types: `array<string, mixed>`, `string[]`, `array<string>`.

### Naming

- Classes: PascalCase (e.g., `ResponseParser`, `ResponseTemplateManager`)
- Methods: camelCase (e.g., `getColumnIndex`, `hasNextPage`)
- Properties: camelCase with visibility (`protected array $context = []`)
- Constants: UPPER_SNAKE_CASE
- Import aliases: short uppercase abbreviations (`use CNIC\CNR\ResponseParser as RP;`)

### Class Patterns

- Setters use fluent interface (return `$this`).
- **Exceptions:** throw from the `CNIC\Exception` hierarchy (base `CnicException extends \Exception`). Reuse `UnsupportedFeatureException` (capability absent on this platform/response, or a transport-owned cURL option/header), `MalformedResponseException` (API sent an unrepresentable shape), `PaginationException`, `InvalidConfigurationException` (config value out of range), `InvalidDateTimeException`; add a `CnicException` subclass for a genuinely new failure mode — the hierarchy is additive, so that needs no major bump. Never throw a bare `\Exception` or declare an exception type outside `CNIC\Exception`. Rationale: [architecture.md](docs/agents/architecture.md).
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

Rules below; harness detail (cassettes, functional tests, spies) is in [docs/agents/testing.md](docs/agents/testing.md).

- **Framework:** PHPUnit 12+, config `.github/phpunit.xml`. Test namespace `CNICTEST\` mirroring `CNIC\`.
- **Test classes:** always `final class` extending `\PHPUnit\Framework\TestCase`; methods `testDescriptiveName` in camelCase.
- **Mocking:** register mock API responses on a `ResponseTemplateManager` **instance** and hand it to the Response — `new Response($templateId, templates: (new RTM())->addTemplate(…))` — or use the hand-written spies (`SpyTransport`, `SpyResponseParser`). Do **not** add Mockery or Prophecy, and do **not** reintroduce a static template container (RSRMID-2941).
- **Shared state:** `static` properties + `setUpBeforeClass()` for one-time client setup.
- **No real API calls in unit tests.** `request()`-path tests replay committed cassettes offline (`composer test`); re-record against OT&E only with `composer test:record` when the exercised API behaviour changes. `tests/Functional/` is the one deliberate exception — a loopback HTTP server, skipped if it cannot bind a port.
- **Direct parser tests live in `tests/<Brand>/ResponseParserTest.php`** — keep parse assertions out of `ResponseTest.php`.
- **MONIKER test files may mirror IBS ones and import IBS classes** — intentional (same platform, no MONIKER-specific behaviour). Do not flag it as a coverage gap.
- **Adding a guard test (`tests/*SeamTest.php`)?** Its class docblock must state the directive, the failure mode prevented, why the guard must be structural, and what would justify revisiting the decision — then prove it non-vacuous by applying the mutation it refuses. Rules in [CONTRIBUTING.md → Guard tests](CONTRIBUTING.md#guard-tests).

### Running Tests

```bash
composer test          # PHPUnit with coverage — cassette replay, fully offline
composer test:record   # Re-record request() cassettes against OT&E (needs RTLDEV_MW_CI_* creds)
composer lint          # prettier --check + phpcs + phpstan + psalm + shellcheck
composer codefix       # Auto-fix PHP coding standard violations (phpcbf)
composer prettier:fix  # Auto-fix non-PHP formatting (Markdown/JSON/YAML)
```

`composer lint` needs the Node toolchain (`pnpm install`) for the prettier check. Every other script (`phpstan`, `psalm`, `rector`, `docs`, `demo:*`, …) is described in `composer.json`'s `scripts-descriptions` — run `composer run --list` rather than duplicating it here.

## Build, CI & Policies

Short reminders; full detail in the linked docs.

- **PHP versions — [project-policies.md](docs/agents/project-policies.md):** runtime floor **8.3**, no ceiling (CI matrix 8.3/8.4/8.5), but the source **language-feature ceiling is pinned at 8.3** (WHMCS ships ionCube-encoded). Never hand-write or `rector:fix` into 8.4+ syntax; don't bump `rector.php` past 8.3.
- **Lockfiles — [project-policies.md](docs/agents/project-policies.md):** `composer.lock` and `pnpm-lock.yaml` are committed deliberately. Do not remove or git-ignore them.
- **Distribution archive — [project-policies.md](docs/agents/project-policies.md):** `export-ignore` in `.gitattributes` keeps the Packagist dist lean (only `src/`, `composer.json`, `LICENSE`, `README.md` ship); add a matching line for any new dev-only root file. Because `README.md` is the only doc that ships, a **relative** link in it may only target a shipping path or an internal anchor — `MIGRATION.md`, `examples/`, `docs/`, `env.example.sh` must use absolute `github.com` URLs, and consumer-facing usage docs belong in `README.md`. Verify with `git archive HEAD | tar -t`.
- **Lint toolchain & CVE gating — [project-policies.md](docs/agents/project-policies.md):** prettier is part of `composer lint` on purpose **and is gated on every PR** by the shared lint workflow's `prettier` job (`lint.yml` opts in with `prettier: true`) — unformatted Markdown is a red build, not just a local nag; `composer audit` is a pre-flight convenience — CVE gating already runs in the shared lint workflow.
- **Claude Code allowlist — [project-policies.md](docs/agents/project-policies.md):** `.claude/settings.json` allows only strictly read-only commands or known-safe project scripts.
- **CI / Actions, Rector, generated docs — [ci-release.md](docs/agents/ci-release.md):** most workflows delegate to shared reusable workflows (caching/matrix/coverage/audit live there, not here); Rector runs monthly; Doctum API docs publish to `gh-pages` on release. `lint.yml` passes one input, `prettier: true` — keep it. Mind the reusable-workflow permission intersection and SHA-pinning rules (the latter is scoped to this repo, not the shared one).

## Git Conventions

- **Commit messages:** Angular/Conventional Commits with **mandatory scope**: `<type>(<scope>): <summary>` — e.g. `fix(psalm): resolve static analysis warnings`. Never append a `Co-Authored-By:` trailer.
- **Commit type selection:** `fix`/`feat` are reserved for `src/` changes — they trigger a release. Everything else uses a non-releasing type: `ci` (workflows, devcontainer), `build` (build tooling/scripts), `chore`, `docs`, `test`, `refactor` (internal restructuring, no behaviour change).
- **Breaking changes:** add a `BREAKING CHANGE: <summary>` line to the commit body (blank line after the subject) — this triggers a major bump. In the **same change** you must extend [MIGRATION.md](MIGRATION.md) with a `→ vX.0.0` section (what changed / what to respect / before→after code) plus its compatibility-table row, and link that section from the commit footer:

  ```
  feat(client): remove deprecated setProxy() method

  BREAKING CHANGE: setProxy() has been removed; use HttpTransport::withProxy() instead.

  See [MIGRATION.md → v20.0.0](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/blob/master/MIGRATION.md#-v2000)
  ```

- **Branch creation:** `git checkout master && git pull --ff-only` before `git checkout -b` — never branch from a stale local `master` or another feature branch.
- **Branch naming:** prefix with the Jira issue ID — e.g. `RSRMID-2821/short-description`.
- **Pull requests:** include the Jira issue link in the PR description; after opening, add the PR URL as a comment on the Jira issue.
- **Merging PRs:** rebase-merge (`gh pr merge --rebase`). Squash merges are disabled at the repo level.
- **Default branch:** `master`. Versioning: semantic-release via the CI release workflow.

## Important Paths

Class inventory is derivable from `src/` in a couple of greps and is deliberately not tabulated here (see [architecture.md](docs/agents/architecture.md)). The paths worth knowing because they are **not** guessable:

| Path                                                                     | Purpose                                                        |
| ------------------------------------------------------------------------ | -------------------------------------------------------------- |
| `.github/linters/{phpcs.xml,phpstan.neon,psalm.xml,rector.php}`          | Linter/analyser/modernization configs                          |
| `.github/phpunit.xml`                                                    | PHPUnit config (`stopOnDefect` + `failOnWarning/Notice/Risky`) |
| `tests/<Brand>/cassettes/`                                               | Committed `request()` cassettes (replay is the default)        |
| `env.example.sh`                                                         | Template for required env variables (copy to `env.sh`)         |
| `src/Exception/CnicException.php`                                        | Base of the additive `CNIC\Exception` hierarchy                |
| `src/{ResponseInterface,ResponseParserInterface,TransportInterface}.php` | The seams brands and tests substitute through                  |

## Atlassian / JIRA

Work is tracked in **Jira Cloud**, project `RSRMID`, component `PHP-SDK` — not GitHub Issues. Always-on rules:

- **Descriptions must be ADF** (Atlassian Document Format, JSON) — never markdown, which renders literal `\n`.
- **Log time before Done:** an issue won't stay in **Done** without a worklog — automation stamps `missing-time-spent` and reopens it. Sequence: (1) add worklog; (2) remove the label; (3) transition to Done. Ask when the amount isn't obvious.

All IDs, custom fields, transitions, account IDs and JQL examples: [docs/agents/issue-tracker.md](docs/agents/issue-tracker.md) — update that file, not this section.

## Tool-Output Hygiene

Every tool result is spent context, so prefer the bounded tool over the shell dump. A `PreToolUse` hook (`.claude/hooks/tool-output-hygiene.sh`) denies the three worst shapes and names the replacement — if it fires, take the replacement rather than working around it.

- **Searching:** the **Grep** tool with `head_limit` — add `output_mode: "files_with_matches"` when only locations matter. Where Grep is absent from the toolset (it is in some SDK-launched sessions), a bounded shell search is the fallback: `… | head -30`, `-l`, `-c`. What is never acceptable is an unbounded `grep -rn`.
- **Reading part of a file:** **Read** with `offset`/`limit`, never `sed -n '<from>,<to>p'` or a bare `cat`.
- **Noisy commands:** bound the output at the source — `composer lint 2>&1 | tail -30`, `git diff --stat` before any full `git diff`.
- **MCP calls:** batch field updates into a single `editJiraIssue`, and never re-fetch an issue or page you just mutated — the write response already confirms it.
- `BASH_MAX_OUTPUT_LENGTH` / `MAX_MCP_OUTPUT_TOKENS` in `.claude/settings.json` only truncate; a truncated result is a signal you asked the wrong way, not a result to work from.

## Model Routing

Opus decides, Sonnet implements: plan and review in the main thread, hand the mechanical work down. Definitions live in `.claude/agents/`.

- **Implementation** of an already-settled change goes to the `implementer` subagent (Sonnet). Trivial one-line edits stay inline — the delegation overhead outweighs the saving.
- **Review** goes to the `reviewer` subagent (Opus, pinned so review quality never silently drops to a cheaper model), or stays in the main thread. Never route a review to Sonnet.
- **Planning and architecture calls** stay on Opus; the built-in `Plan` agent inherits the session model, so it needs no pinning.
- **Fan-out reads** — sweeping `src/` for a pattern, reading several `docs/agents/*.md` — go to `Explore` or `general-purpose`, so the file dumps land in the subagent's context instead of this one.
- A subagent reports conclusions, not file contents. One that hands back a wall of source has defeated the point of delegating to it.

## Do NOT

- Read, display, or expose the contents of `env.sh` — it contains secrets
- Add dependencies without explicit request — this is a lightweight SDK
- Throw a bare `\Exception` or declare exception types outside `CNIC\Exception`
- Use mocking frameworks (Mockery, Prophecy) — use a `ResponseTemplateManager` instance or the repo's spies
- Add `@author` tags to docblocks
- Add `Co-Authored-By:` trailers to commit messages

## Agent skills & reference docs

Detailed, on-demand reference lives under `docs/agents/` — read the relevant file when the task calls for it:

- **[architecture.md](docs/agents/architecture.md)** — architecture deep dive: every settled decision, its guard test, alternatives rejected, RSRMID history.
- **[testing.md](docs/agents/testing.md)** — cassette record/replay harness, functional (loopback) tests, spies, MONIKER/IBS duplication.
- **[project-policies.md](docs/agents/project-policies.md)** — PHP version policy, lockfiles, distribution archive, lint toolchain, Claude Code allowlist.
- **[ci-release.md](docs/agents/ci-release.md)** — CI/GitHub Actions wiring, Rector modernization, Doctum API-doc pipeline.
- **[issue-tracker.md](docs/agents/issue-tracker.md)** — Jira Cloud via the Atlassian MCP tools; all issue IDs/fields/transitions.
- **[domain.md](docs/agents/domain.md)** — what to do about `CONTEXT.md`/`docs/adr/`: neither exists yet, so proceed silently; the domain-modeling skill creates them lazily.
