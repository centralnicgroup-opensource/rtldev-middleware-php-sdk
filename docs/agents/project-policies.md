# Project policies

Reference for the SDK's version, packaging, dependency, and tooling-safety policies. CLAUDE.md carries the one-line reminders and links here for the full rationale.

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

## Platform Requirements (`ext-*`)

`composer.json` declares only the extensions that **can actually be absent** on a supported PHP: `ext-curl` (every request goes through `CNIC\HttpTransport`) and `ext-intl` (the IDN converter needs `idn_to_ascii()` — see [RSRMID-2977](https://centralnic.atlassian.net/browse/RSRMID-2977)). Both are optional builds, so declaring them turns a runtime fatal on a stripped-down host into an install-time refusal Composer can explain.

Do **not** add a requirement for an extension that is non-disableable core on PHP 8.3+. `ext-json` is the recurring candidate — `src/IBS/ResponseParser.php` does call `json_decode()`, but PHP 8.0 removed the `--disable-json` build option, so Composer's platform repository always provides `ext-json` and the constraint could never fail. The same applies to `ext-hash` and PCRE. A constraint that cannot fail is not documentation, it is noise in the one place consumers read to diagnose a failed install.

Extensions used only by a dependency are that dependency's to declare — `centralnic-reseller/idn-converter` requires `ext-mbstring` itself, and `src/` uses no `mb_*` function, so the SDK does not list it. Conversely, a transitive requirement is **not** a substitute for a direct one: `ext-intl` was already pulled in via idn-converter, and was still added to `require` because this SDK's own code path depends on it.

**Before adding an `ext-*` line, check two things:** that `src/` (not `tests/`, not `examples/`) actually reaches the extension, and that a supported PHP build can be missing it.

## Dependency Lockfile Policy

- **`composer.lock` is committed deliberately.** Conventional guidance says a library should not commit its lockfile because consumers ignore it (Composer resolves the library's constraints fresh into the consumer's own `composer.lock`). That still holds for consumers — keeping our lockfile does **not** affect downstream installs. We commit it anyway so that CI, devcontainer, and local developer setups all resolve the exact same dependency tree, giving reproducible lint/test runs and pinning the dev toolchain (PHPUnit, PHPStan, Psalm, Rector). Do not remove or git-ignore `composer.lock`.
- **`pnpm-lock.yaml` is committed** (the project migrated from npm to pnpm; the old `package-lock.json` is gone). Both lockfiles are `export-ignore`d in `.gitattributes` so they stay out of the Composer distribution archive.

## Distribution Archive (`.gitattributes`)

`export-ignore` in `.gitattributes` controls what Packagist serves as the **dist zip** — the tarball `composer require` actually downloads. It does **not** affect CI or local clones (`actions/checkout` and `git clone` always fetch the full tree), so excluding a file there is safe for the toolchain.

- **Keep the dist lean: only runtime essentials ship.** `src/`, `composer.json`, `LICENSE`, and `README.md` stay in the archive; everything dev-only is `export-ignore`d (CI/linters under `.github`, `tests`, `.devcontainer`, `.husky`, `.claude`, editor/formatter configs, `codecov.yml`, both lockfiles, `package.json`/`pnpm-workspace.yaml`, all `*.sh`).
- When adding a new dev-only file at the repo root, add a matching `export-ignore` line.
- Verify with `git check-attr export-ignore -- <path>` (expects `set`) or inspect the whole archive with `git archive HEAD | tar t`.
- **`README.md` is a distributed artifact, so its links have a constraint the other docs do not (RSRMID-2930).** It is the *only* documentation in the archive, and a **relative** link in it may only point at another shipping path (`LICENSE`, `src/…`) or an internal anchor. A relative link to `MIGRATION.md`, `examples/…`, `docs/…` or `env.example.sh` renders fine on GitHub and is **dead** for everyone who installed via `composer require` — use an absolute `https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/blob/master/…` URL instead. This bit twice before it was noticed: the `Migration Guide` link and the `env.example.sh` link were both relative, and the entire Usage section said "find a demo app in the `examples` folder" — a folder absent from the package. The corollary is a content rule, not just a link rule: **consumer-facing usage documentation belongs in `README.md`**, which is why RSRMID-2930 moved the canonical usage example out of `MIGRATION.md` and left a pointer behind rather than the reverse. To audit, extract the relative link targets from `README.md` and check each against `git archive HEAD | tar -t`.

## Lint & Formatting Toolchain

`composer lint` runs `prettier --check` → `phpcs` → `phpstan` → `psalm` → `shellcheck`. Per-script descriptions are in `composer.json`'s `scripts-descriptions` block (`composer run --list`), not duplicated here.

- **Both analysers cover `src/` and `tests/`** — the scope is aligned on purpose, because several guard tests work by making calls on interface-typed variables and relying on PHPStan L9 / Psalm L1 to reject a signature that narrows again (see the `getColumnKeys()` entry in [architecture.md](architecture.md)). Narrowing the analysed scope to `src/` would silently defuse those guards.
- **Psalm runs with `findUnusedCode="true"`.** Because PHPUnit invokes test classes and methods via reflection, `UnusedClass` is suppressed for `tests/` in `psalm.xml`; every other Psalm check applies to test code in full.
- **Suppression hygiene: a suppression Psalm never has to apply is itself an error** (`UnusedIssueHandlerSuppression`), so remove a stale entry rather than leaving it "just in case". This has paid for itself twice: the `LessSpecificReturnStatement` entry for `src/` died when RSRMID-2920 deleted the `final` leaf classes, and the `UnusedMethodCall` entry for `tests/` died in RSRMID-2922 when its last trigger — a discarded `setAccessible()` return value — was deleted with the reflection-based IDN tests.
- **Prettier is part of `composer lint` on purpose (RSRMID-2923).** It is a formatter rather than a linter, but leaving it out of the lint gate kept biting: the only enforcement was lint-staged, which `--write`s staged files at commit time instead of failing, so a Markdown edit could look clean all through a task and then reformat itself under you at commit — or drift permanently in a file no commit happened to stage (`CONTRIBUTING.md` had drifted that way). The check therefore runs **first**, and it invokes the pinned `node_modules/.bin/prettier` rather than `npx`, so the version is the one in `pnpm-lock.yaml` (prettier's output changes between minors). Consequence: `composer lint` now needs the Node toolchain installed (`pnpm install`). Fix violations with `composer prettier:fix`. Note `HISTORY.md` is in `.prettierignore`: it is semantic-release-generated, and prettier reflowed all ~700 lines on any human edit.
- **The formatting gate also runs on every PR (RSRMID-2929).** `composer lint` was still opt-in enforcement — it only helps whoever runs it, so a web-editor edit, a bot PR or a `--no-verify` commit could land unformatted. The shared `php-sdk-lint.yml` now carries a `prettier` job running `composer prettier`, which `.github/workflows/lint.yml` opts into with `prettier: true`. So the check is the *same script* in both places and cannot drift. Wiring detail — including why the shared job is opt-in and why `ci-success` needed no edit — is in [ci-release.md](ci-release.md).
- **`composer audit` is a pre-flight convenience, not a `composer.json` script.** Dependency CVE gating is already enforced on every PR by the shared `php-sdk-lint.yml` workflow (its `composer-audit` job runs `composer audit --no-dev`), which `.github/workflows/lint.yml` delegates to — there is no separate audit step to wire into this repo. See [ci-release.md](ci-release.md).

## Claude Code Allowlist (`.claude/settings.json`)

The Bash allowlist is intentionally scoped to known-safe, non-destructive commands only. The guiding rules:

- **Composer:** explicit script names only (`test`, `lint`, `codefix`, `phpstan`, `install`, …). Destructive subcommands (`require`, `update`, `remove`, `create-project`) are not allowed and will always prompt.
- **gh CLI:** read-only subcommands (`pr view/list/checks/create`, `issue view/list`, `run view/list`, `repo view`). `gh api` is intentionally omitted — it cannot be narrowed to safe endpoints without allowing arbitrary REST mutations.
- **git:** read-only operations only. `git branch` is limited to explicit list flags (`-a`, `-r`, `-v`, `-vv`, `--list`, `--show-current`); destructive flags (`-d`, `-D`, `-m`) will always prompt.

When adding new entries to the allowlist, confirm the command is strictly read-only or a known-safe project script before allowing it without a prompt.
