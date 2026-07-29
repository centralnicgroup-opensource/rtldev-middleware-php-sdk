#!/usr/bin/env zsh
# shellcheck shell=bash
# Appends a source line for env.sh to ~/.zshenv (once) so that every new
# integrated-terminal session inherits the workspace environment variables
# without requiring a manual `source env.sh`, then prints the environment
# banner so the active toolchain versions are visible on every attach.
set -euo pipefail

WORKSPACE="/usr/share/rtldev-middleware-php-sdk"
SCRIPTS="${WORKSPACE}/.devcontainer/supporting_files/scripts"
MARKER="# workspace-env (auto-loaded by devcontainer post-attach)"

if [ -f "${WORKSPACE}/env.sh" ]; then
    if ! grep -qF "${MARKER}" ~/.zshenv 2>/dev/null; then
        {
            printf '\n%s\n' "${MARKER}"
            printf '. "%s/env.sh"\n' "${WORKSPACE}"
        } >> ~/.zshenv
    fi
fi

# Report the toolchain versions. Invoked through zsh rather than directly (and
# tested with -f, not -x) to match how devcontainer.json runs post-create.sh and
# post-attach.sh: the workspace is a bind mount from the host, so the executable
# bit can be absent, and a lost +x must not silently disable the banner.
#
# `|| true` on top of the script's own `exit 0`: under `set -e` a banner that
# somehow failed must never break the attach.
if [ -f "${SCRIPTS}/env-info.sh" ]; then
    zsh "${SCRIPTS}/env-info.sh" || true
fi
