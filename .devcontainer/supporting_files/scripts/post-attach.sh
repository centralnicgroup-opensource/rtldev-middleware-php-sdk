#!/usr/bin/env zsh
# Appends a source line for env.sh to ~/.zshenv (once) so that every new
# integrated-terminal session inherits the workspace environment variables
# without requiring a manual `source env.sh`.
set -euo pipefail

WORKSPACE="/usr/share/rtldev-middleware-php-sdk"
MARKER="# workspace-env (auto-loaded by devcontainer post-attach)"

if [ -f "${WORKSPACE}/env.sh" ]; then
    if ! grep -qF "${MARKER}" ~/.zshenv 2>/dev/null; then
        {
            printf '\n%s\n' "${MARKER}"
            printf '. "%s/env.sh"\n' "${WORKSPACE}"
        } >> ~/.zshenv
    fi
fi
