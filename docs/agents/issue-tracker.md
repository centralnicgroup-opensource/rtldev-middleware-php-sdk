# Issue tracker: Jira (RSRMID)

Issues, tasks, stories, and PRDs for this repo live in **Jira Cloud**, project **RSRMID** (3rd-party Software Integrations / Middleware), component **PHP-SDK**. GitHub Issues is **not** used for tracking work. Use the Atlassian MCP tools (`mcp__claude_ai_Atlassian__*`) for all operations; there is no `gh issue` workflow here.

## Coordinates

- **Cloud ID:** `4e50e119-d5ea-4f89-afb1-d4cd47e40177`
- **Project:** `RSRMID`
- **Component:** `PHP-SDK` (id `10232`)
- **Issue types:** Task (`10002`), Bug (`10004`), Story (`10001`), Epic (`10000`)
- **Default parent epic:** `RSRMID-976` ("$3rd party module tech debt") — set as the `parent` on new issues by default, unless a more specific epic clearly applies.
- **Required fields on create:**
  - Work Category `customfield_12383` (select): Strategic `13284`, Maintain Revenue/BAU `13285`, Tech Debt `13286`, Security `13287`
  - Business Unit `customfield_10027` (multi-checkbox): CentralNic Reseller `10187` (default)
- **Workflow transitions:** To Do `11`, In Progress `21`, In Review `41`, QA `61`, Ready for Deployment `51`, Done `31`, Stand-by `71`, Cancelled `91`
- **Known account IDs:** Kai Schwarz `61358848ee2fd0006aac7b4f`, Asif Nawaz `62a84362bf7afc006f3b15e5`

## Conventions

- **Descriptions must be ADF** (Atlassian Document Format, JSON) — never markdown. Markdown renders literal `\n` instead of line breaks.
- **Create an issue:** `createJiraIssue` with the project, issue type, component, both required custom fields above, and `parent` = `RSRMID-976` (the default epic) unless a more specific one applies.
- **Read an issue:** `getJiraIssue` (add `fields`/`expand` as needed); `searchJiraIssuesUsingJql` for lists (e.g. `project = RSRMID AND component = PHP-SDK AND statusCategory != Done`).
- **Comment:** `addCommentToJiraIssue`.
- **Edit / set fields:** `editJiraIssue`.
- **Transition:** `getTransitionsForJiraIssue` then `transitionJiraIssue` with the transition id above.

## Branch / PR linkage

- Branch names are prefixed with the Jira issue id — e.g. `RSRMID-2821/short-description`.
- Every PR description includes the Jira issue link; after opening a PR, add the PR URL as a comment on the Jira issue.

## Closing an issue (mandatory time tracking)

An issue will not stay in **Done** without a worklog — Jira automation stamps a `missing-time-spent` label and auto-reopens it. Correct sequence:

1. Add a worklog (`addWorklogToJiraIssue`, `timeSpent`).
2. Remove the `missing-time-spent` label.
3. Transition to Done (`31`).

When the time amount isn't obvious, ask rather than guessing.

## When a skill says "publish to the issue tracker"

Create an RSRMID Jira issue (Task by default) with the component and required fields set.

## When a skill says "fetch the relevant ticket"

Resolve the `RSRMID-<n>` key and call `getJiraIssue`.
