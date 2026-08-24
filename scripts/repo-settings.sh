#!/usr/bin/env bash
#
# Reconcile this repository's GitHub settings with .github/repo-settings.conf.
#
#   scripts/repo-settings.sh            # report drift, change nothing (default)
#   scripts/repo-settings.sh --apply    # make GitHub match the file
#
# Check mode never writes and exits 1 on drift, so it works as a scheduled CI job with
# a read-only token. Apply mode needs admin, and is deliberately not wired to
# `on: push` — a workflow with admin over its own repository can be made to apply a
# merged change to this file. Run it by hand or via workflow_dispatch.
#
# Settings the API cannot express stay manual; see TEMPLATE-SETUP.md section 8.

set -euo pipefail

MODE=check
CONFIG=".github/repo-settings.conf"
REPO=""

DRIFT=0
SKIPPED=0
UNCONFIGURED=0

# --- output ------------------------------------------------------------------

info() { printf '  %s\n' "$*"; }
head2() { printf '\n%s\n' "$*"; }
die() {
    printf 'error: %s\n' "$*" >&2
    exit 1
}

# Reports one field. In check mode it records drift; in apply mode the caller has
# already written the value, so this is just the log line.
#
# A wanted value that is still a {{TOKEN}} is a question nobody has answered, so there
# is nothing to compare it against — reported and counted separately from a real
# mismatch, and separately again from a field this token cannot read.
compare() {
    local label="$1" want="$2" got="$3"
    if [[ "$want" =~ \{\{[A-Z_]+\}\} ]]; then
        info "? ${label}: still a {{placeholder}} — not configured"
        UNCONFIGURED=$((UNCONFIGURED + 1))
    elif [[ "$got" == "unknown" ]]; then
        info "? ${label}: cannot read with this token — skipped"
        SKIPPED=$((SKIPPED + 1))
    elif [[ "$want" == "$got" ]]; then
        info "= ${label}: ${got}"
    else
        info "! ${label}: is '${got}', want '${want}'"
        DRIFT=$((DRIFT + 1))
    fi
}

# --- arguments ---------------------------------------------------------------

while [[ $# -gt 0 ]]; do
    case "$1" in
        --apply) MODE=apply ;;
        --check) MODE=check ;;
        --config)
            CONFIG="${2:-}"
            shift
            ;;
        --repo)
            REPO="${2:-}"
            shift
            ;;
        -h | --help)
            grep '^#' "$0" | grep -v '^#!' | cut -c 3-
            exit 0
            ;;
        *) die "unknown argument '$1' (try --help)" ;;
    esac
    shift
done

command -v gh >/dev/null 2>&1 || die "gh is required (https://cli.github.com)"
command -v jq >/dev/null 2>&1 || die "jq is required"

# Resolved before the cd so a path relative to the current directory works too. The
# ./ prefix stops `.` falling back to a PATH lookup for a slashless name.
[[ -f "$CONFIG" ]] && CONFIG=$(realpath "$CONFIG")

cd "$(git rev-parse --show-toplevel)" || die "not inside a git repository"
[[ -f "$CONFIG" ]] || die "no such config: ${CONFIG}"
[[ "$CONFIG" == /* ]] || CONFIG="./${CONFIG}"

# A repository still carrying template placeholders would have its description set to
# the literal "{{DESCRIPTION}}", so apply mode refuses rather than write nonsense.
#
# Check mode does not, because rtldev-middleware-template is itself a live repository:
# its identity fields are placeholders permanently and by design, so dying here would
# mean the one repository that ships this script could never run it, and its weekly
# drift job could only ever be red. The unanswered fields are reported as unconfigured
# instead, which leaves everything else — the merge buttons, features and security
# toggles, identical for the template and for anything created from it — genuinely
# checked rather than skipped along with them.
if [[ "$MODE" == "apply" ]] && grep -qE '\{\{[A-Z_]+\}\}' "$CONFIG"; then
    die "${CONFIG} still contains {{PLACEHOLDERS}} — finish TEMPLATE-SETUP.md section 1 first"
fi

# shellcheck source=/dev/null
. "$CONFIG"

: "${DESCRIPTION:=}" "${HOMEPAGE:=}" "${TOPICS:=}" "${IS_TEMPLATE:=false}"
: "${ALLOW_SQUASH_MERGE:=false}" "${ALLOW_REBASE_MERGE:=true}" "${ALLOW_MERGE_COMMIT:=false}"
: "${ALLOW_AUTO_MERGE:=true}" "${DELETE_BRANCH_ON_MERGE:=true}"
: "${HAS_ISSUES:=true}" "${HAS_PROJECTS:=false}" "${HAS_WIKI:=false}" "${HAS_DISCUSSIONS:=false}"
: "${PRIVATE_VULNERABILITY_REPORTING:=true}" "${VULNERABILITY_ALERTS:=true}"
: "${AUTOMATED_SECURITY_FIXES:=true}"
: "${SECRET_SCANNING:=true}" "${SECRET_SCANNING_PUSH_PROTECTION:=true}"
: "${RULESET_ENABLED:=false}" "${RULESET_NAME:=default-branch-protection}"
: "${REQUIRED_APPROVALS:=1}" "${DISMISS_STALE_REVIEWS:=true}"
: "${REQUIRE_LAST_PUSH_APPROVAL:=true}" "${REQUIRE_LINEAR_HISTORY:=true}"
: "${REQUIRED_CHECKS:=}" "${EXPECTED_SECRETS:=}" "${EXPECTED_VARIABLES:=}"

for name in IS_TEMPLATE ALLOW_SQUASH_MERGE ALLOW_REBASE_MERGE ALLOW_MERGE_COMMIT ALLOW_AUTO_MERGE \
    DELETE_BRANCH_ON_MERGE HAS_ISSUES HAS_PROJECTS HAS_WIKI HAS_DISCUSSIONS \
    PRIVATE_VULNERABILITY_REPORTING VULNERABILITY_ALERTS AUTOMATED_SECURITY_FIXES \
    SECRET_SCANNING SECRET_SCANNING_PUSH_PROTECTION RULESET_ENABLED \
    DISMISS_STALE_REVIEWS REQUIRE_LAST_PUSH_APPROVAL REQUIRE_LINEAR_HISTORY; do
    case "${!name}" in
        true | false) ;;
        *) die "${name} must be true or false, got '${!name}'" ;;
    esac
done

[[ -n "$REPO" ]] || REPO=$(gh repo view --json nameWithOwner --jq .nameWithOwner) ||
    die "could not determine the repository — pass --repo OWNER/NAME"

printf 'Repository: %s\nConfig:     %s\nMode:       %s\n' "$REPO" "$CONFIG" "$MODE"

# --- core settings -----------------------------------------------------------

head2 "Core settings"

actual=$(gh api "repos/${REPO}" 2>/dev/null) || die "cannot read repos/${REPO}"

# Low-scope tokens omit the merge flags entirely, which would otherwise read as drift
# on every field at once. An absent key is therefore "unknown", while a key present as
# null is a genuinely unset value — an empty description is drift, not a read failure.
read_field() {
    local key="$1" v
    v=$(jq -r --arg k "$key" '
        if has($k) then (if .[$k] == null then "" else (.[$k] | tostring) end)
        else "unknown" end' <<<"$actual")
    printf '%s' "$v"
}

desired_core=$(jq -n \
    --arg description "$DESCRIPTION" \
    --arg homepage "$HOMEPAGE" \
    --argjson is_template "$IS_TEMPLATE" \
    --argjson has_issues "$HAS_ISSUES" \
    --argjson has_projects "$HAS_PROJECTS" \
    --argjson has_wiki "$HAS_WIKI" \
    --argjson has_discussions "$HAS_DISCUSSIONS" \
    --argjson allow_squash_merge "$ALLOW_SQUASH_MERGE" \
    --argjson allow_rebase_merge "$ALLOW_REBASE_MERGE" \
    --argjson allow_merge_commit "$ALLOW_MERGE_COMMIT" \
    --argjson allow_auto_merge "$ALLOW_AUTO_MERGE" \
    --argjson delete_branch_on_merge "$DELETE_BRANCH_ON_MERGE" \
    '$ARGS.named')

if [[ "$MODE" == "apply" ]]; then
    gh api --method PATCH "repos/${REPO}" --input - <<<"$desired_core" >/dev/null ||
        die "failed to patch core settings (admin required)"
    actual=$(gh api "repos/${REPO}")
fi

while IFS=$'\t' read -r key want; do
    compare "$key" "$want" "$(read_field "$key")"
done < <(jq -r 'to_entries[] | "\(.key)\t\(.value | tostring)"' <<<"$desired_core")

# --- topics ------------------------------------------------------------------

head2 "Topics"

want_topics=$(tr ' ' '\n' <<<"$TOPICS" | grep -v '^$' | sort | tr '\n' ' ' | sed 's/ $//')
if [[ "$MODE" == "apply" ]]; then
    jq -n --arg t "$TOPICS" '{names: ($t | split(" ") | map(select(length > 0)))}' |
        gh api --method PUT "repos/${REPO}/topics" --input - >/dev/null ||
        die "failed to set topics"
fi
got_topics=$(gh api "repos/${REPO}/topics" --jq '.names | sort | join(" ")' 2>/dev/null || echo unknown)
compare "topics" "$want_topics" "$got_topics"

# --- security ----------------------------------------------------------------

head2 "Security"

# 204 means enabled, 404 disabled; anything else (403) means the token cannot see it.
probe_204() {
    local path="$1" err
    if err=$(gh api "$path" 2>&1 >/dev/null); then
        printf 'true'
    elif [[ "$err" == *"(HTTP 404)"* || "$err" == *"Not Found"* ]]; then
        printf 'false'
    else
        printf 'unknown'
    fi
}

probe_enabled_json() {
    local path="$1" body
    if body=$(gh api "$path" 2>/dev/null); then
        jq -r 'if has("enabled") then (.enabled | tostring) else "unknown" end' <<<"$body"
    else
        printf 'unknown'
    fi
}

toggle() { # path desired
    local path="$1" want="$2" method
    [[ "$want" == "true" ]] && method=PUT || method=DELETE
    gh api --method "$method" "$path" >/dev/null 2>&1 ||
        info "  (could not $method $path — needs admin, or the plan does not offer it)"
}

if [[ "$MODE" == "apply" ]]; then
    toggle "repos/${REPO}/vulnerability-alerts" "$VULNERABILITY_ALERTS"
    toggle "repos/${REPO}/automated-security-fixes" "$AUTOMATED_SECURITY_FIXES"
    toggle "repos/${REPO}/private-vulnerability-reporting" "$PRIVATE_VULNERABILITY_REPORTING"

    # Secret scanning rides along on the repo PATCH rather than its own endpoint, and
    # is rejected outright on plans without it — hence the tolerated failure.
    jq -n \
        --arg ss "$([[ "$SECRET_SCANNING" == true ]] && echo enabled || echo disabled)" \
        --arg pp "$([[ "$SECRET_SCANNING_PUSH_PROTECTION" == true ]] && echo enabled || echo disabled)" \
        '{security_and_analysis: {secret_scanning: {status: $ss}, secret_scanning_push_protection: {status: $pp}}}' |
        gh api --method PATCH "repos/${REPO}" --input - >/dev/null 2>&1 ||
        info "  (secret scanning not settable here — plan or permissions)"
fi

compare "vulnerability alerts" "$VULNERABILITY_ALERTS" "$(probe_204 "repos/${REPO}/vulnerability-alerts")"
compare "automated security fixes" "$AUTOMATED_SECURITY_FIXES" "$(probe_enabled_json "repos/${REPO}/automated-security-fixes")"
compare "private vulnerability reporting" "$PRIVATE_VULNERABILITY_REPORTING" "$(probe_enabled_json "repos/${REPO}/private-vulnerability-reporting")"

sa=$(gh api "repos/${REPO}" --jq '.security_and_analysis // empty' 2>/dev/null || true)
if [[ -n "$sa" ]]; then
    compare "secret scanning" "$([[ "$SECRET_SCANNING" == true ]] && echo enabled || echo disabled)" \
        "$(jq -r '.secret_scanning.status // "unknown"' <<<"$sa")"
    compare "secret scanning push protection" \
        "$([[ "$SECRET_SCANNING_PUSH_PROTECTION" == true ]] && echo enabled || echo disabled)" \
        "$(jq -r '.secret_scanning_push_protection.status // "unknown"' <<<"$sa")"
else
    compare "secret scanning" "-" "unknown"
fi

# --- branch protection -------------------------------------------------------

head2 "Branch protection"

if [[ "$RULESET_ENABLED" != "true" ]]; then
    info "- repository ruleset disabled in config; expecting an organisation ruleset"
    info "  verify: gh api orgs/OWNER/rulesets"
else
    ruleset_body=$(jq -n \
        --arg name "$RULESET_NAME" \
        --argjson approvals "$REQUIRED_APPROVALS" \
        --argjson dismiss "$DISMISS_STALE_REVIEWS" \
        --argjson last_push "$REQUIRE_LAST_PUSH_APPROVAL" \
        --argjson linear "$REQUIRE_LINEAR_HISTORY" \
        --arg checks "$REQUIRED_CHECKS" \
        '{
      name: $name,
      target: "branch",
      enforcement: "active",
      conditions: { ref_name: { include: ["~DEFAULT_BRANCH"], exclude: [] } },
      rules: (
        [
          { type: "deletion" },
          { type: "non_fast_forward" },
          { type: "pull_request", parameters: {
              required_approving_review_count: $approvals,
              dismiss_stale_reviews_on_push: $dismiss,
              require_last_push_approval: $last_push,
              require_code_owner_review: false,
              required_review_thread_resolution: false
          } }
        ]
        + (if $linear then [{ type: "required_linear_history" }] else [] end)
        + (if ($checks | length) > 0 then [{
            type: "required_status_checks",
            parameters: {
              strict_required_status_checks_policy: true,
              required_status_checks: ($checks | split(",") | map({ context: (. | gsub("^ +| +$";"")) }))
            }
          }] else [] end)
      )
    }')

    # Filtered with jq rather than `gh api --jq`, which takes no --arg.
    existing=$(gh api "repos/${REPO}/rulesets" 2>/dev/null |
        jq -r --arg n "$RULESET_NAME" 'map(select(.name == $n)) | .[0].id // empty' 2>/dev/null || echo "")

    if [[ "$MODE" == "apply" ]]; then
        # if/else rather than `A && B || C`: with the latter, a failure of the
        # success-message branch would also run the failure message (SC2015).
        if [[ -n "$existing" ]]; then
            if gh api --method PUT "repos/${REPO}/rulesets/${existing}" --input - <<<"$ruleset_body" >/dev/null; then
                info "= ruleset '${RULESET_NAME}' updated"
            else
                info "! ruleset update failed (admin required)"
            fi
        else
            if gh api --method POST "repos/${REPO}/rulesets" --input - <<<"$ruleset_body" >/dev/null; then
                info "= ruleset '${RULESET_NAME}' created"
            else
                info "! ruleset creation failed (admin required)"
            fi
        fi
    else
        compare "ruleset '${RULESET_NAME}'" "present" \
            "$([[ -n "$existing" ]] && echo present || echo absent)"
    fi
fi

# --- secrets and variables (names only, never values) ------------------------

if [[ -n "$EXPECTED_SECRETS" || -n "$EXPECTED_VARIABLES" ]]; then
    head2 "Secrets and variables"
    # `x=$(cmd) || x=unknown`, not `x=$(cmd || echo unknown)`. On a 403 `gh api`
    # exits non-zero *and* prints the error JSON to stdout, so the inline form
    # captures the JSON with "unknown" appended — which never equals "unknown", so
    # the guard below misses and every expected name is reported absent rather than
    # unreadable. That turns a permissions gap into a false drift every Monday.
    have_secrets=$(gh api "repos/${REPO}/actions/secrets" --jq '[.secrets[].name] | join(" ")' 2>/dev/null) || have_secrets=unknown
    have_vars=$(gh api "repos/${REPO}/actions/variables" --jq '[.variables[].name] | join(" ")' 2>/dev/null) || have_vars=unknown
    for want in $EXPECTED_SECRETS; do
        if [[ "$have_secrets" == "unknown" ]]; then
            compare "secret ${want}" "present" "unknown"
        else
            compare "secret ${want}" "present" \
                "$([[ " $have_secrets " == *" $want "* ]] && echo present || echo absent)"
        fi
    done
    for want in $EXPECTED_VARIABLES; do
        if [[ "$have_vars" == "unknown" ]]; then
            compare "variable ${want}" "present" "unknown"
        else
            compare "variable ${want}" "present" \
                "$([[ " $have_vars " == *" $want "* ]] && echo present || echo absent)"
        fi
    done
fi

# --- summary -----------------------------------------------------------------

head2 "Summary"
info "drift: ${DRIFT}   unreadable: ${SKIPPED}   unconfigured: ${UNCONFIGURED}"

if [[ "$MODE" == "apply" ]]; then
    info "applied. Re-run without --apply to confirm."
    exit 0
fi

if [[ "$DRIFT" -gt 0 ]]; then
    info "run with --apply to reconcile"
    exit 1
fi
if [[ "$UNCONFIGURED" -gt 0 ]]; then
    info "what is configured matches; answer the {{placeholders}} to cover the rest"
    exit 0
fi
info "settings match the config"
