# Domain Docs

How the engineering skills should consume this repo's domain documentation.

**Neither file exists yet.** The `/domain-modeling` skill (reached via `/grill-with-docs` and `/improve-codebase-architecture`) creates them lazily, when a term or a decision actually gets resolved — so **proceed silently** if they are absent. Do not flag the absence and do not propose creating them upfront.

If they do appear, read them before exploring the area they cover:

- **`CONTEXT.md`** (repo root) — the glossary. When your output names a domain concept (an issue title, a refactor proposal, a hypothesis, a test name), use the term as it defines it rather than drifting to a synonym it avoids. A concept missing from the glossary is a signal: either you are inventing language the project does not use (reconsider), or there is a real gap (note it for `/domain-modeling`).
- **`docs/adr/`** — architectural decision records. Read the ones touching the area you are about to work in, and if your output contradicts one, surface that explicitly rather than silently overriding it: _"Contradicts ADR-0007 (event-sourced orders) — but worth reopening because…"_

Settled architectural decisions currently live in [architecture.md](architecture.md), not in ADRs. This is a single-context repo, so the skill's multi-context layout (a root `CONTEXT-MAP.md` pointing at per-context files) does not apply.
