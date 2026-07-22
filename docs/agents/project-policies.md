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

## Dependency Lockfile Policy

- **`composer.lock` is committed deliberately.** Conventional guidance says a library should not commit its lockfile because consumers ignore it (Composer resolves the library's constraints fresh into the consumer's own `composer.lock`). That still holds for consumers — keeping our lockfile does **not** affect downstream installs. We commit it anyway so that CI, devcontainer, and local developer setups all resolve the exact same dependency tree, giving reproducible lint/test runs and pinning the dev toolchain (PHPUnit, PHPStan, Psalm, Rector). Do not remove or git-ignore `composer.lock`.
- **`pnpm-lock.yaml` is committed** (the project migrated from npm to pnpm; the old `package-lock.json` is gone). Both lockfiles are `export-ignore`d in `.gitattributes` so they stay out of the Composer distribution archive.

## Distribution Archive (`.gitattributes`)

`export-ignore` in `.gitattributes` controls what Packagist serves as the **dist zip** — the tarball `composer require` actually downloads. It does **not** affect CI or local clones (`actions/checkout` and `git clone` always fetch the full tree), so excluding a file there is safe for the toolchain.

- **Keep the dist lean: only runtime essentials ship.** `src/`, `composer.json`, `LICENSE`, and `README.md` stay in the archive; everything dev-only is `export-ignore`d (CI/linters under `.github`, `tests`, `.devcontainer`, `.husky`, `.claude`, editor/formatter configs, `codecov.yml`, both lockfiles, `package.json`/`pnpm-workspace.yaml`, all `*.sh`).
- When adding a new dev-only file at the repo root, add a matching `export-ignore` line.
- Verify with `git check-attr export-ignore -- <path>` (expects `set`) or inspect the whole archive with `git archive HEAD | tar t`.

## Claude Code Allowlist (`.claude/settings.json`)

The Bash allowlist is intentionally scoped to known-safe, non-destructive commands only. The guiding rules:

- **Composer:** explicit script names only (`test`, `lint`, `codefix`, `phpstan`, `install`, …). Destructive subcommands (`require`, `update`, `remove`, `create-project`) are not allowed and will always prompt.
- **gh CLI:** read-only subcommands (`pr view/list/checks/create`, `issue view/list`, `run view/list`, `repo view`). `gh api` is intentionally omitted — it cannot be narrowed to safe endpoints without allowing arbitrary REST mutations.
- **git:** read-only operations only. `git branch` is limited to explicit list flags (`-a`, `-r`, `-v`, `-vv`, `--list`, `--show-current`); destructive flags (`-d`, `-D`, `-m`) will always prompt.

When adding new entries to the allowlist, confirm the command is strictly read-only or a known-safe project script before allowing it without a prompt.
