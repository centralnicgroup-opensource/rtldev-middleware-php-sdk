#!/usr/bin/env zsh
# shellcheck shell=bash
# Sources workspace env.sh (if present) before running PHPUnit so that
# environment variables set there are available to PHP Tools test runs, which
# spawn php directly without going through a shell session -- and therefore never
# see the ~/.zshenv autoload the devbase Feature sets up for terminals.
#
# The workspace is resolved from this script's own location
# (.devcontainer/supporting_files/scripts -> three levels up) rather than
# hardcoded, so a repository directory rename cannot leave this silently sourcing
# nothing. Falls back to no env.sh, never to an error: a missing file is the
# normal case for anyone running without credentials.
case "$0" in
    */*) SCRIPT_DIR="$(cd "${0%/*}" 2>/dev/null && pwd)" || SCRIPT_DIR="" ;;
    *) SCRIPT_DIR="" ;;
esac
WORKSPACE="$(cd "${SCRIPT_DIR}/../../.." 2>/dev/null && pwd)" || WORKSPACE=""

# shellcheck source=/dev/null
[ -n "${WORKSPACE}" ] && [ -f "${WORKSPACE}/env.sh" ] && source "${WORKSPACE}/env.sh"
exec "$@"
