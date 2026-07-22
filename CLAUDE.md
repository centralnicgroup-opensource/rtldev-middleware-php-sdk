# Project Instructions

## Project Overview

This is the **PHP SDK** for Team Internet backend APIs (CentralNic Reseller, Internet.bs, Moniker). It provides a unified connector library under the `CNIC\` namespace with sub-namespaces for each registrar brand (`CNR`, `IBS`, `MONIKER`).

## Architecture

- **Namespace root:** `CNIC\` mapped to `src/` (PSR-4)
- **Shared abstracts (in `CNIC\`):**
  - `AbstractClient` — shared foundation for all registrar API clients; subclasses provide `request()`, the default logger, and the SocketConfig subtype
  - `AbstractSocketConfig` — shared base for all SocketConfig classes; subclasses provide `getPOSTDataParams()` and their own `$parameters` array
  - `HttpTransport` — extracted cURL layer; owns the cURL handle lifecycle and exposes `post()` (single request) plus `close()` (release the handle)
  - `AbstractResponseTemplateManager` — shared template container plus its concrete `addTemplate()`/`getTemplates()`/`hasTemplate()`/`isTemplateMatch*()` operations; subclasses redeclare their `$templates` array and supply the abstract hooks — `getTemplate()` (fetch/build one template), `generateTemplate()` (wire format), `createResponse()`, `parseResponse()`, and `matchKeys()` (the two compared hash keys: `CODE`/`DESCRIPTION` for CNR, `status`/`message` for IBS)
  - `AbstractResponseTranslator` — shared `translate()`/`findMatch()`/`replacePlaceholders()` pipeline; subclasses supply narrow hooks — `templates()` (the brand's template container), the two rewrite maps (`descriptionRegexMap()`/`descriptionRawPatternMap()`), `fieldName()` (`description` for CNR, `message` for IBS), and `hasMissingRequiredFields()` (the invalid-template fallback trigger: `CODE`/`DESCRIPTION` for CNR, `status` for IBS). Placeholder stripping is unified on a per-field callback (only inside the human-readable field), **not** global — so `{UPPER}` content in other data fields is preserved. (Ref: RSRMID-2893.)
  - `AbstractResponse` — shared foundation for all registrar Response classes; owns the constructor skeleton (template method), command sanitisation, column/record bookkeeping (`registerColumn()`/`addRecord()`), record-cursor navigation and the derived pagination getters. Subclasses supply the wire hooks (`translate()`/`populate()`), the record factory (`newRecord(): RecordInterface`), the status/code accessors (`getCode`/`getDescription`/`isError`/`isSuccess`) and the pagination primitives (`getCurrentPageNumber`/`getFirstRecordIndex`/`getLastRecordIndex`/`getRecordsTotalCount`/`getRecordsLimitation`/`hasNextPage`/`hasPreviousPage`) that read brand-specific columns/status. Implements the core `ResponseInterface` only — the CNR-only capabilities are **not** here (see `ExtendedResponseInterface`). (Ref: RSRMID-2904.)
  - `AbstractRecord` — shared foundation for all registrar Record classes; holds all record behaviour (`getData()`/`getDataByKey()`), since record data has one shape (`array<string,mixed>`) across brands. `CNR\Record` and `IBS\Record` are empty markers extending it, instantiated by each Response's `newRecord()` hook. (Ref: RSRMID-2904.)
  - `Registrar` enum — backed by string values `CNR`, `CNIC` (legacy alias), `IBS`, `MONIKER`; used by `ClientFactory` for registrar matching
  - `System` enum — string-backed `OTE`/`LIVE`; the API system a client is on. `AbstractClient` holds it as `protected System $system` (default `LIVE`); `useOTESystem()`/`useLIVESystem()` set it, `isOTE()` and `getSystem()` read it. The public bool `isOTE()` API is preserved — the enum only replaces the internal `bool` flag. (Ref: RSRMID-2896.)
- **Inheritance chain:**
  - `CNR\Client` and `IBS\Client` both extend `AbstractClient` directly
  - `MONIKER\Client` extends `IBS\Client` — Moniker and IBS share the same API platform; only their `SocketConfig` (endpoints/credentials) differs
  - `CNR\SessionClient extends CNR\Client` and uses the `SessionCapable` trait for login/logout
  - `IBS\SessionClient extends IBS\Client` and `MONIKER\SessionClient extends MONIKER\Client` — these are thin wrappers with no session-based login/logout
  - `CNR\SocketConfig` and `IBS\SocketConfig` extend `AbstractSocketConfig` directly; `MONIKER\SocketConfig extends IBS\SocketConfig` (mirroring `MONIKER\Client extends IBS\Client`), reaching `AbstractSocketConfig` transitively
  - `CNR\Response` and `IBS\Response` both extend `AbstractResponse` as **siblings** (neither is-a the other); `CNR\Record` and `IBS\Record` both extend `AbstractRecord` as siblings. MONIKER has no Response/Record of its own — it reuses IBS's.
- **IBS and CNR are siblings, not parent/child:** `IBS\Response` and `CNR\Response` both extend `AbstractResponse`; `IBS\Record` and `CNR\Record` both extend `AbstractRecord`. Each brand adds only its parsing/status/pagination differences on top of the shared base. `IBS\Column` is a **standalone** implementation (does not extend `CNR\Column`) because IBS JSON responses carry mixed-typed values (strings, nested objects, lists) that CNR columns do not. (Historical note: IBS previously extended `CNR\Response`/`CNR\Record` directly and threw `UnsupportedFeatureException` from 5 inherited CNR-only methods — a refused-bequest smell removed in RSRMID-2904 by pulling the shared machinery up into `AbstractResponse`/`AbstractRecord` and segregating the interface.)
- **Response construction is a template method:** `AbstractResponse::__construct()` defines the skeleton and delegates the brand-specific steps to protected hook methods — `translate()` (raw-response translation), `populate()` (parse + record assembly) and `newRecord()` (record factory). Both `CNR\Response` and `IBS\Response` implement those hooks (each marked `#[\Override]`) to plug in their own `ResponseParser`/`ResponseTranslator`, PROPERTY-vs-flat-JSON handling and Record type. When adding a new brand or changing how a response is parsed, implement the hooks — do not reimplement the constructor.
- **Record/column construction is factory-hooked, but the two use opposite hook shapes because the types allow only that:** `AbstractResponse::addRecord()` delegates to an abstract protected `newRecord(array $h): RecordInterface` **output** hook — each brand implements it to build its own Record (`CNR\Record` / `IBS\Record`). Records work as an output factory because both brands share one shape (`array<string,mixed>`). Columns **cannot** use that shape: `CNR\Column` takes `string[]` while `IBS\Column` takes mixed JSON values, so a param-typed `newColumn(string, array): ColumnInterface` factory can't stay type-clean under PHPStan L9 / Psalm L1 — the base factory would have to narrow `array<array-key,mixed>` into CNR's `string[]` constructor, which both analysers reject (`argument.type` / `MixedArgumentTypeCoercion`) and the toolchain forbids silencing. The bookkeeping is instead de-duplicated the **other way round**: each brand's `addColumn()` builds its own correctly-typed Column locally and hands the finished instance to a shared `AbstractResponse::registerColumn(ColumnInterface $col): static` helper that owns the `$columns`/`$columnkeys`/`$columnindex` bookkeeping once. So the input to the shared step is polymorphic (a constructed column), not a param-typed factory. Don't try to "symmetrise" columns onto a `newColumn()` factory like records — it is infeasible under strict analysis; keep the `registerColumn()` shape. (Ref: RSRMID-2899, superseding the RSRMID-2898 note that left `addColumn()` fully duplicated.)
- **Core vs. extended Response contract (interface segregation):** `ResponseInterface` is the **universal** contract every brand fully supports. The richer capabilities of the CNR-class API — telemetry (`getQueuetime()`/`getRuntime()`), transient/pending status (`isTmpError()`/`isPending()`) and the table-friendly `getListHash()` — live on `ExtendedResponseInterface extends ResponseInterface`, implemented **only** by `CNR\Response`. IBS/Moniker responses implement the core interface only, so those 5 methods are simply **absent** there (not present-and-throwing). `AbstractClient::request()` returns the core `ResponseInterface`; a consumer holding that shared type must narrow via `instanceof \CNIC\ExtendedResponseInterface` before calling any of the 5. Consumers holding the concrete `CNR\Client`/`CNR\Response` are unaffected — `CNR\Client::request()` returns the concrete `CNR\Response`, which has everything. Do **not** re-add the 5 to the core interface. (Ref: RSRMID-2904.)
- **Config-driven:** Each sub-namespace's `SocketConfig` (extending `AbstractSocketConfig`) carries the API config as typed properties — endpoints (`$liveUrl`/`$oteUrl`), the POST parameter map (`$parameters`), and feature flags (`$socketTimeout`, `$needsIDNConvert`, `$roleSeparator`). (Historical note: this config previously lived in per-namespace `config.json` files, removed in favour of typed properties.)
- **Interfaces:** `ColumnInterface`, `RecordInterface`, `ResponseInterface`, `ExtendedResponseInterface`, `LoggerInterface` (all in `CNIC\`) define contracts. All concrete classes formally declare `implements`:
  - `CNR\Column`, `IBS\Column` → `ColumnInterface`
  - `CNR\Record`, `IBS\Record` → `RecordInterface` (both extend `AbstractRecord`, which implements it)
  - `CNR\Response` → `ExtendedResponseInterface` (which extends `ResponseInterface`); `IBS\Response` → `ResponseInterface` (both extend `AbstractResponse`, which implements the core `ResponseInterface`)
  - `CNR\Logger`, `IBS\Logger` → `LoggerInterface`
  - Type-hint against the interface rather than the concrete class (e.g. `LoggerInterface` not `Logger`, `ResponseInterface` not `Response`). Narrow to `ExtendedResponseInterface` only when you need one of the 5 CNR-only capabilities.
- **Static utilities:** `ResponseParser::parse()` and `ResponseTranslator` (both `CNR\` and `IBS\`) for parsing/translating raw API responses; `CommandFormatter::flattenCommand()` for request serialisation. The brand `ResponseTemplateManager` and `ResponseTranslator` classes extend the shared `CNIC\AbstractResponseTemplateManager` / `CNIC\AbstractResponseTranslator` bases above and keep only their per-brand data + hooks — when changing template/translation behaviour, edit the shared pipeline in the abstract and the brand differences in the hooks, don't reimplement the pipeline per brand.
- **Factory pattern:** `ClientFactory::getClient()` returns the shared `AbstractClient` base type (not a `SessionClient|IBSSessionClient|MONIKERSessionClient` union). Every shared operation — credentials, referer, user-agent, proxy, logging, OT&E/LIVE switching, and the base `request(array $cmd)` — is available directly on that type. Brand-specific capabilities are intentionally **not** on the shared contract and must be reached by narrowing to the concrete type: CNR session handling (`login()`/`logout()`/`saveSession()`) via `instanceof \CNIC\CNR\SessionClient`, or the IBS/Moniker per-request endpoint path (`request($cmd, $path)`) via `instanceof \CNIC\IBS\Client`. (Ref: v17.1.0, commit 5cea723 — the union return type was replaced because a single common type is what consumers can reason about cleanly.)
- **Endpoint routing differs by brand — IBS/Moniker `request()` widens the signature by design:** CNR talks to a **single fixed endpoint**. The full script path (e.g. `/api/call.cgi`) is baked into the configured URL (`CNR\SocketConfig::$liveUrl`/`$oteUrl`); only the hostname changes between OT&E and LIVE, so CNR never needs a per-request path and `CNR\Client::request(array $cmd = [])` matches the `AbstractClient` contract exactly. IBS/Moniker instead expose **many endpoints under one host**, where the path selects the operation. Their SocketConfig configures the **host only** (trailing slash, no path), and `IBS\Client::request(array $cmd = [], string $path = "")` appends a per-request `$path` — so it **widens** the abstract `request(array $cmd = [])` with an optional parameter. This widening is **deliberate**, not a defect: PHP permits it and both PHPStan (L9) and Psalm (L1) accept it. The trade-off — `$path` is reachable only through the concrete IBS/Moniker `Client` type, not through `AbstractClient` — is intended: CNR must never accept a per-request path, so `$path` is **not** hoisted onto the shared contract. When touching these `request()` methods, preserve this asymmetry; do not "align" the signatures by adding `$path` to the abstract. (Ref: RSRMID-2864.)
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
- **Exceptions:** throw an exception from the `CNIC\Exception` hierarchy — a class extending the base `CnicException` (which extends `\Exception`). Reuse the existing types where they fit — `UnsupportedFeatureException` (capability not available on this platform/response), `UnknownRegistrarException` (unresolvable registrar identifier), `PaginationException` (list-pagination misuse) — and add a new `CnicException` subclass when a genuinely distinct failure mode arises, rather than reaching for a bare `\Exception`. The hierarchy is **additive and non-breaking**: because every class ultimately extends `\Exception`, existing `catch (\Exception)` consumer code keeps working, so introducing or extending it needs no `BREAKING CHANGE`/major bump. (Ref: RSRMID-2895.) Do **not** create parallel ad-hoc exception types outside `CNIC\Exception`. (Historical note: this policy previously read "throw `\Exception` directly (no custom exception hierarchy)"; that was reversed in RSRMID-2895 so consumers can distinguish failure modes without string-matching messages.)
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

There are **two independent axes** here — the runtime versions the SDK is allowed to _run on_, and the newest PHP _language features the source may use_. They are not the same number, and conflating them is the usual source of confusion.

### Runtime support (what the SDK runs on) — floor is 8.3, no ceiling

- `composer.json` declares `"php": ">=8.3.0"`. This sets the **minimum** only; there is no upper bound.
- The SDK supports every **actively-maintained PHP version** and the CI test matrix runs against all of them — currently **8.3, 8.4, and 8.5** (matrix configured via the `RTLDEV_MW_CI_PHP_MATRIX` repo variable). The matrix is **not** a cap: add new PHP versions as they enter active support and drop versions once they reach end-of-life.
- **8.3 is the floor** because that is the minimum PHP supported by the WHMCS releases the SDK is deployed into — **WHMCS 9 (GA)** and **WHMCS 8.13 (LTS)** both support PHP 8.3. Do not raise the `composer.json` minimum above 8.3 until WHMCS raises its own minimum; track [RSRMID-2826](https://centralnic.atlassian.net/browse/RSRMID-2826) for the unblocking condition.

### Language-feature ceiling (what the source may use) — pinned at 8.3

- The source (and the modernizations Rector applies) must **not use PHP language features newer than 8.3**, even though the code runs fine on 8.4/8.5. `rector.php` is pinned accordingly — `->withPhpVersion(PhpVersion::PHP_83)` plus `SetList::PHP_83` — and must stay there.
- **Why the ceiling is stricter than the runtime range:** the SDK is used mainly inside our **WHMCS Domain Registrar Integrations**. WHMCS is **closed-source and shipped ionCube-encoded**, and the **ionCube encoder does not support the latest PHP language features**. Code that mixes SDK sources with ionCube-encoded WHMCS modules must therefore stay within the language level ionCube + the supported WHMCS versions can handle — which is PHP 8.3. Using an 8.4/8.5-only syntax would break in exactly the environment the SDK targets, regardless of the runtime PHP version.
- **The ceiling is set by the most restrictive consumer, which is WHMCS.** The SDK is _also_ used in our **Blesta Domain Registrar Integration**, which is **not** PHP-version-restricted (no ionCube-encoding constraint). Blesta would happily accept newer syntax — but because the same SDK sources ship into WHMCS too, the WHMCS/ionCube limit is the binding one and the 8.3 ceiling holds for all consumers.
- **Practical rule:** you may run and test on newer PHP, but do **not** hand-write or `rector:fix` your way into 8.4+ syntax. Do not bump `rector.php` beyond 8.3.

## Dependency Lockfile Policy

- **`composer.lock` is committed deliberately.** Conventional guidance says a library should not commit its lockfile because consumers ignore it (Composer resolves the library's constraints fresh into the consumer's own `composer.lock`). That still holds for consumers — keeping our lockfile does **not** affect downstream installs. We commit it anyway so that CI, devcontainer, and local developer setups all resolve the exact same dependency tree, giving reproducible lint/test runs and pinning the dev toolchain (PHPUnit, PHPStan, Psalm, Rector). Do not remove or git-ignore `composer.lock`.
- **`pnpm-lock.yaml` is committed** (the project migrated from npm to pnpm; the old `package-lock.json` is gone). Both lockfiles are `export-ignore`d in `.gitattributes` so they stay out of the Composer distribution archive.

## Distribution Archive (`.gitattributes`)

`export-ignore` in `.gitattributes` controls what Packagist serves as the **dist zip** — the tarball `composer require` actually downloads. It does **not** affect CI or local clones (`actions/checkout` and `git clone` always fetch the full tree), so excluding a file there is safe for the toolchain.

- **Keep the dist lean: only runtime essentials ship.** `src/`, `composer.json`, `LICENSE`, and `README.md` stay in the archive; everything dev-only is `export-ignore`d (CI/linters under `.github`, `tests`, `.devcontainer`, `.husky`, `.claude`, editor/formatter configs, `codecov.yml`, both lockfiles, `package.json`/`pnpm-workspace.yaml`, all `*.sh`).
- When adding a new dev-only file at the repo root, add a matching `export-ignore` line.
- Verify with `git check-attr export-ignore -- <path>` (expects `set`) or inspect the whole archive with `git archive HEAD | tar t`.

## CI / GitHub Actions

Most workflows in `.github/workflows/` are thin wrappers that **delegate to the shared reusable workflows** in `centralnicgroup-opensource/rtldev-middleware-shareable-workflows` (pinned `@main`):

- `lint.yml` → `php-sdk-lint.yml` (phpcs, phpstan, psalm, `composer audit --no-dev` CVE gating, trufflehog, actionlint, hadolint, shellcheck)
- `test.yml` → `php-sdk-test.yml` (PHP matrix tests, **Codecov** coverage upload via `codecov-action`, and a `dependabot` auto-merge job that chains into `auto-merge-dependabot-pr.yml`)
- `release.yml` → `php-sdk-release.yml`; `daily-node-dependency-refresh.yml` and `whmcs-php-check.yml` likewise delegate.
- `rector.yml` is the one substantial repo-local workflow (monthly modernization PR).

Because of this, **caching, the test matrix, coverage upload, and audit gating are configured in the shared repo, not here** — don't try to add them to the local wrappers.

### Reusable-workflow permissions (important)

A reusable workflow's effective `GITHUB_TOKEN` is the **intersection** of the root caller's top-level `permissions:` and the called job's own `permissions:`. A called workflow can **never** exceed what the caller grants. Consequences:

- **`test.yml` must keep `contents: write` + `pull-requests: write`.** The shared `php-sdk-test.yml` chains into `auto-merge-dependabot-pr.yml`, which needs both; downgrading the caller to `contents: read` silently breaks Dependabot auto-merge. This is intentional, not an over-grant.
- **`lint.yml` uses `contents: read` + `pull-requests: write`** (no auto-merge) — keep it least-privilege.
- When changing a wrapper's permissions, check what the shared workflow it calls actually needs before tightening.

### Action pinning

Third-party actions are used directly in only two workflows (`test.yml`, `rector.yml`); everything else delegates. Pin those to **commit SHAs with a `# vX` comment** (e.g. `actions/checkout@9c091bb… # v7`) rather than floating tags — a moved tag would run unreviewed code with the workflow's token. The Dependabot `github-actions` ecosystem (`.github/dependabot.yml`, weekly) bumps both the SHA and the comment automatically, so there is no manual upkeep. Any newly-introduced direct action use should be SHA-pinned the same way.

## Git Conventions

- **Commit messages:** Angular/Conventional Commits with **mandatory scope**: `<type>(<scope>): <summary>` — e.g. `fix(psalm): resolve static analysis warnings`, `feat(ibs): add response translation`. Never append a `Co-Authored-By:` trailer.
- **Commit type selection:** `fix` and `feat` are reserved for changes to library source code in `src/` — they trigger a release. For everything else use a non-releasing type: `ci` for CI workflows and devcontainer, `build` for build tooling or scripts, `chore` for housekeeping, `docs` for documentation, `test` for test-only changes, `refactor` for internal restructuring without behaviour change.
- **Breaking changes:** When a `src/` change breaks the public API, add a `BREAKING CHANGE: <short summary>` line to the commit message body (blank line after the subject). This triggers a **major** version bump via semantic-release. Example:

  ```
  feat(client): remove deprecated setProxy() method

  BREAKING CHANGE: setProxy() has been removed; use HttpTransport::withProxy() instead.
  ```

- **Branch creation:** always branch from an up-to-date default branch. Before `git checkout -b`, run `git checkout master && git pull --ff-only` so the new branch starts from the latest `origin/master`. Branching from a stale local `master` (or another feature branch) risks re-doing work that already landed upstream.
- **Branch naming:** prefix with the Jira issue ID — e.g. `RSRMID-2821/short-description`
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
- Create ad-hoc exception classes **outside** the `CNIC\Exception` hierarchy, or throw a bare `\Exception` for a new failure mode — extend `CnicException` instead (see **Class Patterns → Exceptions**)
- Use mocking frameworks (Mockery, Prophecy) — use ResponseTemplateManager
- Add `@author` tags to docblocks
- Add `Co-Authored-By:` trailers to commit messages
