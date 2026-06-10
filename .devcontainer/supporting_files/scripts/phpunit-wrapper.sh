#!/usr/bin/env zsh
# Sources workspace env.sh (if present) before running PHPUnit so that
# environment variables set there are available to PHP Tools test runs,
# which spawn php directly without going through a shell session.
WORKSPACE="/usr/share/rtldev-middleware-php-sdk"
# shellcheck source=/dev/null
[ -f "${WORKSPACE}/env.sh" ] && source "${WORKSPACE}/env.sh"
exec "$@"
