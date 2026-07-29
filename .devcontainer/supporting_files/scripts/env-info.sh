#!/usr/bin/env zsh
# shellcheck shell=bash
# Prints a grouped banner of the container, PHP and Node versions this repo runs
# on, so "which PHP/Composer/Node am I actually on?" is answerable at a glance.
#
# Pure output: no installs, no network, no writes, no secrets. Called from
# post-attach.sh on every VS Code container attach, and safe to run by hand.
#
# Deliberately NOT `set -e`: probing for a tool that is absent is the normal
# case, not an error. post-attach.sh runs under `set -euo pipefail`, so this
# script must always exit 0 no matter how many probes come up empty.
#
# Supported shells are zsh (the container default, and what post-attach.sh uses)
# and bash. `pipefail` is kept even though nothing here inspects a pipeline's
# status, because it makes a shell that cannot run this script correctly -- dash,
# which also lacks the $'...' quoting used for the colours below -- fail loudly
# on line 1 instead of printing rows full of literal escape sequences.
set -uo pipefail

# Resolve the workspace root from this script's own location
# (.devcontainer/supporting_files/scripts/ -> three levels up), so the relative
# package.json probe works regardless of the caller's cwd.
# Uses parameter expansion rather than dirname so that even an environment
# without coreutils on PATH degrades to empty rows instead of leaking an error.
case "$0" in
    */*) SCRIPT_DIR="$(cd "${0%/*}" 2>/dev/null && pwd)" || SCRIPT_DIR="" ;;
    *) SCRIPT_DIR="" ;;
esac
WORKSPACE="$(cd "${SCRIPT_DIR}/../../.." 2>/dev/null && pwd)" || WORKSPACE=""
if [ -n "${WORKSPACE}" ]; then
    cd "${WORKSPACE}" 2>/dev/null || true
fi

readonly LABEL_WIDTH=16
readonly MISSING="(not installed)"       # tool absent
readonly NO_VERSION="(version unknown)"  # tool present, version unreadable
readonly UNKNOWN="(unknown)"             # a fact that is not an installable thing

# --- colours -----------------------------------------------------------------
# Colourise only for an interactive terminal: honour NO_COLOR (any value, per
# no-color.org), a dumb TERM, and redirection to a file or pipe.
if [ -n "${NO_COLOR:-}" ] || [ "${TERM:-dumb}" = "dumb" ] || [ ! -t 1 ]; then
    C_RESET=''
    C_BOX=''
    C_GROUP=''
    C_LABEL=''
    C_VALUE=''
    C_NOTE=''
    C_WARN=''
    C_MISS=''
else
    C_RESET=$'\033[0m'
    C_BOX=$'\033[1;36m'    # bright cyan   - banner frame
    C_GROUP=$'\033[1;36m'  # bright cyan   - group headings
    C_LABEL=$'\033[0;37m'  # light gray    - row labels
    C_VALUE=$'\033[1;32m'  # bright green  - resolved versions
    C_NOTE=$'\033[0;90m'   # dark gray     - contextual notes
    C_WARN=$'\033[1;33m'   # bright yellow - constraint mismatch
    C_MISS=$'\033[0;33m'   # yellow        - absent tool
fi

have() { command -v "${1}" >/dev/null 2>&1; }

# bin_path <name> - absolute path of name on PATH, or empty.
bin_path() { command -v "${1}" 2>/dev/null || true; }

# ==== facts ==================================================================
# One PHP process collects everything readable off disk: the runtime version and
# its extensions, the Node engine constraint from package.json, and the npm/pnpm
# versions.
#
# npm and pnpm are read from their package manifests rather than by running
# `npm --version` / `pnpm --version`, which cost ~60ms and ~340ms respectively
# on every attach. The PHP process is needed for the runtime facts regardless,
# so resolving these two through it is essentially free.
#
# $resolve() verifies each manifest's "name" against the package it was asked
# about and returns nothing when they disagree. That is what makes a *wrong*
# version impossible: the worst case is "(version unknown)", never a
# neighbouring package's number.
#
# Emits "<key>=<value>" lines, plus "bin:<key>=1" for every binary that exists,
# so presence and version stay separate facts. The npm/pnpm paths arrive as
# "<package-name>=<path>" arguments because PATH lookup belongs in the shell.
collect_facts() {
    have php || return 0
    # The single quotes are deliberate: every $ below is PHP, not shell.
    # shellcheck disable=SC2016
    php -r '
$emit = function ($k, $v) { if ($v !== null && $v !== "") { printf("%s=%s\n", $k, $v); } };
$read = function ($path) {
    if (!is_file($path)) { return null; }
    $j = json_decode((string) @file_get_contents($path), true);
    return is_array($j) ? $j : null;
};

$emit("php", PHP_VERSION);
foreach (["xdebug", "curl", "intl"] as $ext) {
    $emit("ext-" . $ext, phpversion($ext) ?: null);
}

$pkg = $read("package.json");
if ($pkg !== null && isset($pkg["engines"]["node"]) && is_string($pkg["engines"]["node"])) {
    $emit("nodeengine", $pkg["engines"]["node"]);
}

// Walk up from the binary to the nearest package.json, and accept its version
// only if the manifest really is the package we asked about.
$resolve = function ($bin, $name) use ($read) {
    $real = realpath($bin);
    if ($real === false) { return null; }
    $dir = dirname($real);
    for ($i = 0; $i < 8; $i++) {
        if (is_file($dir . "/package.json")) {
            $j = $read($dir . "/package.json");
            if ($j !== null && ($j["name"] ?? null) === $name && isset($j["version"])) {
                return (string) $j["version"];
            }
            return null;
        }
        $up = dirname($dir);
        if ($up === $dir) { break; }
        $dir = $up;
    }
    return null;
};
foreach (array_slice($argv, 1) as $spec) {
    $parts = explode("=", $spec, 2);
    if (count($parts) === 2 && $parts[1] !== "") {
        // realpath() follows symlinks, so a dangling link counts as absent.
        if (realpath($parts[1]) !== false) { $emit("bin:" . $parts[0], "1"); }
        $emit($parts[0], $resolve($parts[1], $parts[0]));
    }
}
' -- \
        "npm=$(bin_path npm)" \
        "pnpm=$(bin_path pnpm)" \
        2>/dev/null || true
}

# Load the facts into a map once. Looking them up with `sed` per row would cost
# a process each, which is most of the subprocess budget for this script.
typeset -A FACTS
while IFS='=' read -r _key _value; do
    [ -n "${_key}" ] && FACTS[${_key}]="${_value}"
done <<FACTS_EOF
$(collect_facts)
FACTS_EOF

# fact <key> - collected value, or empty.
fact() { printf '%s\n' "${FACTS[${1}]:-}"; }

# --- probe helpers -----------------------------------------------------------
# These shell out, so they are reserved for versions not readable off disk: a
# compiled binary's own --version output.
#
# Budget: the whole script issues ~10 commands of its own (one each for php,
# git, gh, node, composer and uname, plus a sort/head pair for the Node engine
# check). `composer --version` then forks ~11 more internally, which is why it
# gets `-d /` below. Measure with:
#   strace -f -e trace=execve -o /tmp/t zsh env-info.sh && grep -c execve /tmp/t

# probe <command...> - first line of output, blanks trimmed; empty on failure.
probe() {
    have "${1}" || return 0
    local out nl='
'
    out="$("$@" 2>/dev/null)" || out=""
    out="${out%%"${nl}"*}"
    while [ "${out# }" != "${out}" ]; do out="${out# }"; done
    while [ "${out% }" != "${out}" ]; do out="${out% }"; done
    printf '%s\n' "${out}"
}

# field <n> <string> - whitespace-separated field n for n in 1..3, else the
# remainder of the line. Uses `read` rather than awk so a row costs no process.
field() {
    local n="${1}"
    shift
    local f1 f2 f3 rest
    read -r f1 f2 f3 rest <<FIELD_EOF
${*}
FIELD_EOF
    case "${n}" in
        1) printf '%s\n' "${f1}" ;;
        2) printf '%s\n' "${f2}" ;;
        3) printf '%s\n' "${f3}" ;;
        *) printf '%s\n' "${rest}" ;;
    esac
}

# ver_ge <a> <b> - true when dotted-numeric version a >= b.
ver_ge() {
    [ "$(printf '%s\n%s\n' "${1}" "${2}" | sort -V | head -1)" = "${2}" ]
}

# ==== output =================================================================
# The frame is sized from the title rather than hand-padded, so editing the
# title cannot leave the box misaligned.
banner() {
    local title="PHP-SDK - development environment" bar
    bar="$(printf '%*s' "$(( ${#title} + 4 ))" '')"
    bar="${bar// /-}"
    printf '%s+%s+%s\n' "${C_BOX}" "${bar}" "${C_RESET}"
    printf '%s|%s  %s  %s|%s\n' "${C_BOX}" "${C_RESET}" "${title}" "${C_BOX}" "${C_RESET}"
    printf '%s+%s+%s\n' "${C_BOX}" "${bar}" "${C_RESET}"
}

group() { printf '\n%s\n' "${C_GROUP}${1}${C_RESET}"; }

# emit_row <label> <rendered-value> [note] [note-colour]
emit_row() {
    local label="${1}" rendered="${2}" note="${3:-}" note_color="${4:-${C_NOTE}}"
    if [ -n "${note}" ]; then
        rendered="${rendered} ${note_color}${note}${C_RESET}"
    fi
    printf '  %s%-*s%s %s\n' "${C_LABEL}" "${LABEL_WIDTH}" "${label}" "${C_RESET}" "${rendered}"
}

# render <value> <placeholder> <placeholder-colour> -> REPLY
# Sets a global instead of printing, so a row costs no subshell.
render() {
    if [ -z "${1}" ]; then
        REPLY="${3}${2}${C_RESET}"
    else
        REPLY="${C_VALUE}${1}${C_RESET}"
    fi
}

# row <label> <value> [note] [note-colour]
# An empty value renders as "(not installed)" rather than a blank column.
row() {
    render "${2:-}" "${MISSING}" "${C_MISS}"
    emit_row "${1}" "${REPLY}" "${3:-}" "${4:-${C_NOTE}}"
}

# info <label> <value>
# Like row(), but for a fact that is not an installable thing (the OS name): an
# empty value is "(unknown)", because "(not installed)" would be nonsense.
info() {
    render "${2:-}" "${UNKNOWN}" "${C_NOTE}"
    emit_row "${1}" "${REPLY}"
}

# dep <label> <fact-key> [note]
# Presence and version are separate facts, so a binary that has gone reads as
# absent rather than as a working version, and a binary whose manifest cannot be
# trusted reads as "(version unknown)".
dep() {
    if [ -z "$(fact "bin:${2}")" ]; then
        row "${1}" "" "${3:-}"
    else
        local version
        version="$(fact "${2}")"
        row "${1}" "${version:-${NO_VERSION}}" "${3:-}"
    fi
}

# ==== groups =================================================================
show_container() {
    group "Container"

    local pretty arch
    # shellcheck disable=SC1091
    [ -r /etc/os-release ] && . /etc/os-release 2>/dev/null
    pretty="${PRETTY_NAME:-${NAME:-}}"
    arch="$(uname -m 2>/dev/null || true)"
    if [ -n "${pretty}" ] && [ -n "${arch}" ]; then
        pretty="${pretty} (${arch})"
    fi
    info "OS" "${pretty:-${arch}}"

    row "Git" "$(field 3 "$(probe git --version)")"
    row "GitHub CLI (gh)" "$(field 3 "$(probe gh --version)")"
}

show_php() {
    group "PHP runtime"

    # The runtime floor is 8.3 with no ceiling, but the source language-feature
    # ceiling is pinned at 8.3 because WHMCS ships ionCube-encoded (RSRMID-2826),
    # so an accidental drift needs to be visible here.
    row "PHP" "$(fact php)" "(language-feature ceiling: 8.3)"
    # `-d /` points Composer away from this project: its version does not depend
    # on the working directory, but scanning one makes it fork ~28 extra git
    # processes and take twice as long. --no-plugins keeps plugin code out too.
    row "Composer" "$(field 3 "$(probe composer --version --no-ansi --no-plugins -d /)")"
    # Xdebug ships no binary of its own - the version comes from the runtime.
    row "Xdebug" "$(fact ext-xdebug)"
    row "ext-curl" "$(fact ext-curl)" "(required by composer.json)"
    row "ext-intl" "$(fact ext-intl)"
}

show_node() {
    group "Node toolchain"

    local version constraint note="" note_color="${C_NOTE}" floor
    version="$(probe node --version)"
    version="${version#v}"
    constraint="$(fact nodeengine)"
    if [ -n "${constraint}" ]; then
        note="(package.json requires ${constraint})"
        # Only a ">=X.Y.Z" constraint is verdict-able here; any other form is
        # reported verbatim, without a pass/fail claim this cannot substantiate.
        # Highlighting a mismatch is as far as this goes - it never acts on one.
        floor="${constraint#>=}"
        if [ "${floor}" != "${constraint}" ] && [ -n "${version}" ] &&
            ! ver_ge "${version}" "${floor}"; then
            note="(below package.json requirement ${constraint})"
            note_color="${C_WARN}"
        fi
    fi
    row "Node.js" "${version}" "${note}" "${note_color}"

    dep "npm" npm
    dep "pnpm" pnpm
}

main() {
    banner
    show_container
    show_php
    show_node
    printf '\n'
}

main "$@"
exit 0
