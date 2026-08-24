# Summary

<!-- What changes, and why. One or two sentences; the commit history has the detail. -->

**Jira:** RSRMID-<!-- id --> <!-- Internal contributors only. Community PRs: leave this line as-is or delete it. -->

## Type of change

- [ ] Bug fix (`fix`) — **releases a patch**
- [ ] New feature (`feat`) — **releases a minor**
- [ ] Breaking change — `BREAKING CHANGE:` in the commit body, plus a MIGRATION.md section
- [ ] Non-releasing (`ci` / `build` / `chore` / `docs` / `test` / `refactor`)

<!-- `fix` and `feat` are reserved for changes under src/, because they trigger a
     release. Anything else takes a non-releasing type. -->

## How this was verified

<!-- What you actually ran, and what it said. "Tests pass" is not verification;
     "composer test — 214 passed, 0 failed" is. Say so if something is untested. -->

## Checklist

- [ ] Commit messages follow `<type>(<scope>): <summary>` with a scope
- [ ] `composer lint` and `composer test` run locally, and pass
- [ ] Documentation updated where behaviour changed
- [ ] A breaking change carries its MIGRATION.md section and compatibility-table row in this same PR
- [ ] No guard test (`tests/*SeamTest.php`) was deleted or weakened to make this pass
- [ ] No secrets, credentials or internal hostnames in the diff

## Notes for the reviewer

<!-- Anything worth a second pair of eyes: a decision you were unsure about, a
     trade-off you took, something deliberately left out of scope. -->
