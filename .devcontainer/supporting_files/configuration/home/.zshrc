# Path to your oh-my-zsh installation.
export ZSH=$HOME/.oh-my-zsh

ZSH_THEME="devcontainers"

plugins=(git zsh-autosuggestions)

source $ZSH/oh-my-zsh.sh

DISABLE_AUTO_UPDATE=true
DISABLE_UPDATE_PROMPT=true

# enable terminal commands history
HISTFILE=~/.zsh_history
HISTSIZE=1000
SAVEHIST=1000

# pnpm
export PNPM_HOME="/home/vscode/.local/share/pnpm"
case ":$PATH:" in
  *":$PNPM_HOME:"*) ;;
  *) export PATH="$PNPM_HOME:$PATH" ;;
esac
# pnpm end
