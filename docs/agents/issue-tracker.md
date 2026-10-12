# Issue tracker: Jira (RSRMID)

Issues, tasks, stories, and PRDs for this repo live in **Jira Cloud**, project **RSRMID** (3rd-party Software Integrations / Middleware), component **PHP-SDK**. GitHub Issues is **not** used for tracking work. Use the Atlassian MCP tools (`mcp__claude_ai_Atlassian__*`) for all operations; there is no `gh issue` workflow here.

**Conventions live in the `jira-conventions` skill** (`.claude/skills/jira-conventions/SKILL.md`) — cloud ID, required fields, this repository's title prefix and component, the parent epic, issue-type and transition IDs, Markdown descriptions, and the worklog required before Done or Cancelled. Load it before creating, editing or transitioning an issue; this page does not repeat it.

## Tool usage

- **Read an issue:** `getJiraIssue` (add `fields`/`expand` as needed); `searchJiraIssuesUsingJql` for lists (e.g. `project = RSRMID AND component = PHP-SDK AND statusCategory != Done`).
- **Comment:** `addCommentToJiraIssue`.
- **Edit / set fields:** `editJiraIssue`.
- **Transition:** call `transitionJiraIssue` straight away with the id from the skill — the ids are static. Reach for `getTransitionsForJiraIssue` only to _diagnose_ a transition that failed or is genuinely unavailable (workflow changed, or a condition on the target status is unmet).

## Branch / PR linkage

- Branch names are prefixed with the Jira issue id — e.g. `RSRMID-2821-short-description`.
- Every PR description includes the Jira issue link; after opening a PR, add the PR URL as a comment on the Jira issue.

## When a skill says "publish to the issue tracker"

Create an RSRMID Jira issue (Task by default) as the `jira-conventions` skill describes.

## When a skill says "fetch the relevant ticket"

Resolve the `RSRMID-<n>` key and call `getJiraIssue`.
