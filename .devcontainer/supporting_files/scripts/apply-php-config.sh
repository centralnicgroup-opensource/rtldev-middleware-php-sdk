#!/usr/bin/env zsh
# shellcheck shell=bash
#
# Repository-specific PHP ini provisioning. Wired to onCreateCommand -- read the
# ordering note below before moving it.
#
# Everything that used to be in this file -- pnpm, the global packages,
# zsh-autosuggestions, the gh credential helper, composer/pnpm dependency
# installation and the .zsh_history symlink -- is the devbase Feature's job now.
# Do not re-add any of it here; if something shared is missing, raise it against
# the Feature so every consuming repository gets the fix:
# https://github.com/centralnicgroup-opensource/rtldev-middleware-devcontainer-features
#
# What is left is the one step no Feature can own, because it is specific to how
# this repository runs PHP:
#
#   * intl.ini loads the system php8.3-intl .so, because the devcontainer PHP
#     Feature builds PHP without bundled intl.
#   * zz-custom.ini pins xdebug.mode=coverage, without which `composer test`
#     produces no coverage at all.
#
# Both files are baked into the image at /opt/php-config (see Dockerfile) and
# copied into the running PHP build's scan directory here, because that
# directory belongs to the PHP the Feature installed.
#
# ORDERING -- this must run at onCreateCommand, not postCreateCommand.
# centralnic-reseller/idn-converter requires ext-intl, which only exists once
# intl.ini above is in place. devbase installs project dependencies from its own
# postCreateCommand, and a Feature's lifecycle hooks run before the consuming
# devcontainer.json's hooks of the same phase -- so as a postCreateCommand this
# lands *after* composer has already run and failed with "your lock file does not
# contain a compatible set of packages". onCreateCommand is the first of the
# three create-phase commands, which puts it ahead of every postCreateCommand.
# The same ordering keeps xdebug out of step-debug mode while composer runs.

set -euo pipefail

readonly SCRIPT_NAME="apply-php-config"
readonly PHP_CONFIG_SRC="/opt/php-config"

log() { echo "=> [${SCRIPT_NAME}] $*"; }
err() { echo "=> [${SCRIPT_NAME}] ERROR: $*" >&2; }

# Applies every *.ini from PHP_CONFIG_SRC to the active PHP build's scan
# directory. Fails loudly: a missing coverage setting turns `composer test` into
# a silently coverage-less run, which is worse than a failed create.
setup_php_config() {
    local scan_dir
    scan_dir="$(php --ini 2>/dev/null | awk -F': ' '/Scan for additional/ {print $2; exit}')" || true

    if [ -z "${scan_dir}" ] || [ ! -d "${scan_dir}" ]; then
        err "could not determine the PHP scan directory (php --ini)"
        return 1
    fi

    local ini_file copied=0
    for ini_file in "${PHP_CONFIG_SRC}"/*.ini; do
        [ -f "${ini_file}" ] || continue
        sudo cp "${ini_file}" "${scan_dir}/$(basename "${ini_file}")"
        copied=$((copied + 1))
    done

    if [ "${copied}" -eq 0 ]; then
        err "no .ini files found in ${PHP_CONFIG_SRC}"
        return 1
    fi

    log "applied ${copied} PHP ini file(s) to ${scan_dir}"
}

main() {
    setup_php_config
    log "finished"
}

main "$@"
