#!/usr/bin/env bash
#
# Publish the generated API documentation to the gh-pages branch.
#
# Invoked by semantic-release (@semantic-release/exec successCmd) and therefore
# runs ONLY when a release has actually been published — i.e. only when a
# releasing commit (feat/fix touching src/) was merged. The docs themselves are
# (re)generated earlier in the release run by the exec prepareCmd
# (`composer run-script docs && composer run-script generate-uml`).
#
# Requires GITHUB_TOKEN in the environment (set by the release workflow). The
# token needs contents:write on this repository.

set -euo pipefail

REPO_SLUG="centralnicgroup-opensource/rtldev-middleware-php-sdk"
BRANCH="gh-pages"
SRC_DIR="docs"

: "${GITHUB_TOKEN:?GITHUB_TOKEN must be set to publish documentation}"

if [ ! -d "$SRC_DIR" ] || [ -z "$(ls -A "$SRC_DIR" 2>/dev/null)" ]; then
    echo "::error::No documentation to publish — '$SRC_DIR' is missing or empty"
    exit 1
fi

# Stage the generated docs in an isolated directory so we never touch the
# release checkout. A fresh single-commit history is force-pushed to gh-pages,
# keeping the branch free of accumulated documentation history.
PUBLISH_DIR="$(mktemp -d)"
trap 'rm -rf "$PUBLISH_DIR"' EXIT

cp -a "$SRC_DIR/." "$PUBLISH_DIR/"
touch "$PUBLISH_DIR/.nojekyll" # disable Jekyll so files/dirs starting with _ are served

git -C "$PUBLISH_DIR" init -q -b "$BRANCH"
git -C "$PUBLISH_DIR" \
    -c user.name="github-actions[bot]" \
    -c user.email="41898282+github-actions[bot]@users.noreply.github.com" \
    add -A
git -C "$PUBLISH_DIR" \
    -c user.name="github-actions[bot]" \
    -c user.email="41898282+github-actions[bot]@users.noreply.github.com" \
    commit -q -m "docs: publish API documentation [skip ci]"

git -C "$PUBLISH_DIR" push -f -q \
    "https://x-access-token:${GITHUB_TOKEN}@github.com/${REPO_SLUG}.git" "$BRANCH"

echo "Published documentation to https://${REPO_SLUG%%/*}.github.io/${REPO_SLUG##*/}/"
