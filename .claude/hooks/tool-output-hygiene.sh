#!/usr/bin/env bash
#
# PreToolUse/Bash guard: keep context-expensive shell reads out of the agent's
# context window by redirecting them to the bounded tool equivalents.
#
# Denies three specific shapes only — each one dumps an unbounded amount of
# file content into context when a cheaper tool exists:
#
#   1. unbounded recursive grep   -> Grep tool with head_limit
#   2. sed -n '<from>,<to>p' file -> Read tool with offset/limit
#   3. bare `cat <file>`          -> Read tool
#
# Everything else passes untouched. In particular `head`/`tail`, `grep` inside a
# pipeline (`composer lint | grep -c error`), `cat` feeding a pipe, and heredocs
# are all legitimate and stay allowed — they are already bounded.
#
# Fails open: any unexpected input exits 0 without a decision.

set -uo pipefail

payload=$(command cat)
cmd=$(printf '%s' "$payload" | jq -r '.tool_input.command // empty' 2>/dev/null) || exit 0
[ -n "$cmd" ] || exit 0

deny() {
  jq -n --arg reason "$1" '{
    hookSpecificOutput: {
      hookEventName: "PreToolUse",
      permissionDecision: "deny",
      permissionDecisionReason: $reason
    }
  }'
  exit 0
}

matches() {
  printf '%s' "$cmd" | grep -qE "$1"
}

# --- 1. recursive grep with no bound on the match count ----------------------
if matches '(^|[|;&(]|[[:space:]])grep([[:space:]]+-[A-Za-z]*[rR]|[[:space:]]+--recursive)'; then
  if ! matches '\|[[:space:]]*head|[[:space:]]-[A-Za-z]*[cl]([[:space:]]|$)|--files-with-matches|--count|[[:space:]]-m[[:space:]]*[0-9]'; then
    deny 'Unbounded recursive grep: every match lands in context. Use the Grep tool with head_limit, plus output_mode:"files_with_matches" when you only need locations. To keep the shell form, bound it explicitly (| head -30, or -l / -c).'
  fi
fi

# --- 2. sed line-range extraction -------------------------------------------
if matches "(^|[|;&(]|[[:space:]])sed[[:space:]]+-[A-Za-z]*n" && matches "[0-9]+,[0-9]+p"; then
  deny 'sed -n line-range reads are the Read tool with offset/limit — same output, and the line numbers come back clickable.'
fi

# --- 3. bare `cat <file>` (no pipe, no redirect, no heredoc) -----------------
if matches '^[[:space:]]*cat[[:space:]]+[^|&;<>]+$' \
  && ! matches '[[:space:]](/proc/|/sys/|/dev/)'; then
  deny 'Reading a file with cat dumps all of it into context. Use the Read tool, with offset/limit if you only need part of it.'
fi

exit 0
