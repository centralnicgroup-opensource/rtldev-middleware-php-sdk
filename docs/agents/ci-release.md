# CI, release & generated docs

Reference for the CI/GitHub Actions wiring, the Rector modernization flow, and the Doctum API-doc pipeline. CLAUDE.md carries the one-line summary and links here for the detail.

## CI / GitHub Actions

Most workflows in `.github/workflows/` are thin wrappers that **delegate to the shared reusable workflows** in `centralnicgroup-opensource/rtldev-middleware-shareable-workflows` (pinned `@main`):

- `lint.yml` → `php-sdk-lint.yml` (phpcs, phpstan, psalm, `composer audit --no-dev` CVE gating, trufflehog, actionlint, hadolint, shellcheck, prettier)
- `test.yml` → `php-sdk-test.yml` (PHP matrix tests, **Codecov** coverage upload via `codecov-action`, and a `dependabot` auto-merge job that chains into `auto-merge-dependabot-pr.yml`)
- `release.yml` → `php-sdk-release.yml`; `daily-node-dependency-refresh.yml` and `whmcs-php-check.yml` likewise delegate.
- `rector.yml` is the one substantial repo-local workflow (monthly modernization PR).

Because of this, **caching, the test matrix, coverage upload, and audit gating are configured in the shared repo, not here** — don't try to add them to the local wrappers.

### The one input the lint wrapper passes (`prettier: true`)

`php-sdk-lint.yml` gates its `prettier` job behind a `workflow_call` boolean input that defaults to **false**, and `lint.yml` passes `prettier: true`. The opt-in exists because that job is the only one in the shared workflow needing the *caller* to bring a Node toolchain (committed `pnpm-lock.yaml`, prettier in `devDependencies`, a `prettier` composer script) — an unconditional job would break the first consumer without one, and auto-detecting the config was rejected because a job that silently self-skips reports green while checking nothing (RSRMID-2929).

Two consequences worth knowing:

- **`ci-success` needs no per-job edit.** It gates on `needs.lint.result`, the conclusion of the whole reusable call, so any job added to the shared workflow is covered automatically. Do not expand it into a list of job names.
- **The lint workflow is merge-blocking, and the `name:` is what makes it so.** `lint.yml`'s `ci-success` job carries `name: "Lint: completed"`, so *that* is the check-run name it publishes; `test.yml` has an identically-keyed `ci-success` job with no `name:`, so it publishes the bare context `ci-success`. `master` protection requires **both** contexts, so a prettier (or phpcs, or psalm) failure blocks the merge. Until RSRMID-2929 only `test.yml`'s bare `ci-success` was required and the whole lint workflow was advisory. Two things follow: never drop or rename `lint.yml`'s `name:` — that silently orphans the required context and the gate goes advisory again with nothing turning red; and a startup failure of the reusable call now surfaces as a permanently *pending* required check rather than an invisible pass, because it produces zero check runs.
- **The job runs `composer prettier`, not a raw prettier call**, so the arguments and `.prettierignore` rules stay owned by this repo. It installs with `pnpm install --frozen-lockfile` so CI checks against the prettier version in `pnpm-lock.yaml` — prettier's output changes between minors, and a floating install would produce a red CI that cannot be reproduced locally. The job prints the version it used for exactly that comparison.

### Reusable-workflow permissions (important)

A reusable workflow's effective `GITHUB_TOKEN` is the **intersection** of the root caller's top-level `permissions:` and the called job's own `permissions:`. A called workflow can **never** exceed what the caller grants. Consequences:

- **`test.yml` must keep `contents: write` + `pull-requests: write`.** The shared `php-sdk-test.yml` chains into `auto-merge-dependabot-pr.yml`, which needs both; downgrading the caller to `contents: read` silently breaks Dependabot auto-merge. This is intentional, not an over-grant.
- **`lint.yml` uses `contents: read` + `pull-requests: write`** (no auto-merge) — keep it least-privilege.
- When changing a wrapper's permissions, check what the shared workflow it calls actually needs before tightening.

### Action pinning

Third-party actions are used directly in only two workflows (`test.yml`, `rector.yml`); everything else delegates. Pin those to **commit SHAs with a `# vX` comment** (e.g. `actions/checkout@9c091bb… # v7`) rather than floating tags — a moved tag would run unreviewed code with the workflow's token. The Dependabot `github-actions` ecosystem (`.github/dependabot.yml`, weekly) bumps both the SHA and the comment automatically, so there is no manual upkeep. Any newly-introduced direct action use should be SHA-pinned the same way. **The rule is scoped to this repo.** `rtldev-middleware-shareable-workflows` pins by floating major tag across all ~29 of its workflows and has its own daily Dependabot bumping them; a lone SHA-pinned step over there would be inconsistent with the file it lives in without buying anything the tag policy of that repo does not already accept. Follow the local convention of whichever repo the step lands in.

## Codebase Modernization (Rector)

Rector is configured in `.github/linters/rector.php` targeting PHP 8.3 with `CODE_QUALITY`, `DEAD_CODE`, and `PHP_83` rulesets. (The PHP 8.3 language-feature ceiling is a hard constraint — see [project-policies.md](project-policies.md).)

- **CI (lint workflow):** runs in dry-run mode (`composer rector`) — detects issues only, never writes.
- **Automated apply:** `.github/workflows/rector.yml` runs `composer rector:fix` on the first of each month and opens a PR (`chore/rector-modernization`) with commit message `chore(rector): apply automated modernization fixes`. Can also be triggered manually via `workflow_dispatch`.
- **Manual apply:** run `composer rector:fix` locally and open a PR with the same commit prefix.

## API Documentation

API docs are generated by **Doctum** (`composer docs`, config `.github/doctum.config.php`) together with a UML class diagram (`composer generate-uml`) into **`docs/api/`**, which is the only git-ignored part of `docs/` — the hand-written `docs/agents/` is version-controlled. (`composer generate-uml` needs a JVM and therefore does not run in the devcontainer; it runs in CI/release.)

- **Local preview:** run `composer docs` then `composer docs:serve` (serves `docs/api/` on port 8000).
- **Publishing:** handled by semantic-release on release. The `@semantic-release/exec` plugin regenerates the docs in its `prepareCmd` and publishes them to the `gh-pages` branch in its `successCmd` (`.github/scripts/publish-docs.sh`, force-pushed as a single commit). Because `prepare`/`success` only run when a release is actually cut — and releases only happen on `feat`/`fix` (i.e. `src/` changes) — docs are regenerated and published **only when the library source changed**.
- **Hosting:** GitHub Pages serves the `gh-pages` branch at <https://centralnicgroup-opensource.github.io/rtldev-middleware-php-sdk/>. The publish script needs `GITHUB_TOKEN` (contents:write), already provided by the release workflow.
