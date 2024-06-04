#!/bin/bash
# NOTE: This file will be executed as remoteUser (devcontainer.json)
echo "=> Script: post-create.sh Executed by: $(whoami)"

npm install --silent --global commitizen@latest cz-conventional-changelog@latest

# shellcheck source=/dev/null
source ~/.zshrc

# change github hooks directory to run scripts like pre-commit/pre-push
git config core.hooksPath $OLDPWD/.github/hooks

# install composer deps
composer install
npm ci

echo "=> Generating Symlinks for Zsh History and Git config"
# Create symlink for gitconfig and zsh history file
if [[  ! -L "${HOME}/.gitconfig" ]]; then
    ln -s "/WSL_USER/.gitconfig" "${HOME}/.gitconfig"
fi

if [[  ! -L "${HOME}/.zsh_history" ]]; then
    ln -s "/WSL_USER/.zsh_history" "${HOME}/.zsh_history"
fi

exit 0