#!/bin/bash
set -euo pipefail  # Exit on error, undefined vars, pipe failures

# Environment setup
export DEBIAN_FRONTEND="noninteractive"
export PUPPETEER_SKIP_DOWNLOAD=true
export SHELL="/usr/bin/zsh"

# Configuration
readonly SCRIPT_NAME="post-create.sh"

# Color definitions
readonly COLOR_RESET='\033[0m'
readonly COLOR_INFO='\033[1;36m'     # Bright cyan for INFO
readonly COLOR_SUCCESS='\033[1;32m'  # Bright green for SUCCESS
readonly COLOR_ERROR='\033[1;31m'    # Bright red for ERROR
readonly COLOR_DETAIL='\033[0;37m'   # Light gray for details

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}
# Function to execute command with indented output
execute_with_indent() {
    local cmd="$1"
    local description="$2"
    local strip_formatting="${3:-false}"
    log_detail "Executing: ${description}"
    # Capture both stdout and stderr, preserve exit code
    local output
    local exit_code=0
    if output=$(eval "${cmd}" 2>&1); then
        # Success - show indented output
        if [[ -n "${output}" ]]; then
            if [[ "${strip_formatting}" == "true" ]]; then
                # Strip ANSI escape sequences (including tput formatting) but keep indentation
                echo "${output}" | sed -e 's/\x1b\[[0-9;]*m//g' -e 's/\x1b\[[0-9]*[A-Za-z]//g' | sed 's/^/     /'
            else
                # Add indentation normally
                echo "${output}" | sed 's/^/     /'
            fi
        fi
    else
        exit_code=$?
        # Failure - show indented error output
        log_error "Command failed with exit code ${exit_code}"
        if [[ -n "${output}" ]]; then
            if [[ "${strip_formatting}" == "true" ]]; then
                echo "${output}" | sed -e 's/\x1b\[[0-9;]*m//g' -e 's/\x1b\[[0-9]*[A-Za-z]//g' | sed 's/^/     /' >&2
            else
                echo "${output}" | sed 's/^/     /' >&2
            fi
        fi
        return ${exit_code}
    fi
}
# Logging functions
log_info() {
    echo ""
    echo -e "${COLOR_INFO}=> [INFO] $*${COLOR_RESET}"
}
log_error() {
    echo -e "${COLOR_ERROR}=> [ERROR]${COLOR_RESET} $*" >&2
}
log_success() {
    echo -e "${COLOR_SUCCESS}=> [SUCCESS]${COLOR_RESET} $*"
}
log_detail() {
    echo -e "${COLOR_DETAIL}   $*${COLOR_RESET}"
}
# Function to install pnpm package manager
setup_pnpm() {
    if ! command_exists pnpm; then
        execute_with_indent "sudo npm i --silent -g pnpm" "Installing pnpm globally"
        log_success "pnpm installed globally"
    else
        log_detail "pnpm already installed"
    fi
}
# Function to install global npm packages
setup_pnpm_global_packages() {
    log_info "Installing global npm packages..."
    
    # Export PNPM environment variables directly for current session
    export PNPM_HOME="$HOME/.local/share/pnpm"
    export PATH="$PNPM_HOME:$PATH"
    mkdir -p "$PNPM_HOME"

    if [[ "${GITHUB_CLI:-false}" != "true" ]]; then
        if [[ ! -f ~/.zshrc ]] || ! grep -q "pnpm" ~/.zshrc; then
            execute_with_indent "pnpm setup" "Setting up pnpm for current user"
        fi
        [[ -f ~/.zshrc ]] && ( set +u; source ~/.zshrc ) 2>/dev/null
    fi

    # Ensure pnpm global-bin-dir matches PNPM_HOME (avoids PATH mismatch)
    pnpm config set global-bin-dir "$PNPM_HOME" 2>/dev/null || true

    # Install global packages with error handling
    local packages=(
        "commitizen@latest"
        "cz-conventional-changelog@latest"
        "semantic-release-cli@latest"
    )

    log_detail "Installing packages: ${packages[*]}"
    if execute_with_indent "pnpm add -g ${packages[*]}" "Installing global packages"; then
        log_success "All global packages installed successfully"
    else
        log_error "Failed to install global packages"
        return 1
    fi
}
# Function to install dependencies
setup_php_nodejs_dependencies() {
    log_info "Installing project dependencies..."
    # Install PHP dependencies, don't use --no-dev in devcontainer
    if [[ -f "composer.json" ]]; then
        if execute_with_indent "composer install --optimize-autoloader --quiet" "Installing PHP dependencies with Composer"; then
            log_success "Composer dependencies installed"
        else
            log_error "Failed to install composer dependencies"
            return 1
        fi
    else
        log_error "composer.json not found"
        return 1
    fi

    # Install Node.js dependencies
    if [[ -f "package.json" ]]; then
        if execute_with_indent "pnpm install --frozen-lockfile --silent --force" "Installing Node.js dependencies with pnpm"; then
            log_success "Node.js dependencies installed"
        else
            if execute_with_indent "pnpm install --no-frozen-lockfile --silent --force" "Installing Node.js dependencies without frozen lockfile"; then
                log_detail "Node.js dependencies installed without frozen lockfile"
            else
                log_error "Failed to install Node.js dependencies"
                return 1
            fi
        fi
    else
        log_detail "No package.json found, skipping Node.js dependencies"
    fi
}
# Function to apply custom PHP ini settings
setup_php_config() {
    log_info "Applying custom PHP configuration..."
    local php_scan_dir
    php_scan_dir="$(php -i 2>/dev/null | grep -oP '(?<=Scan this dir => ).*')"
    if [[ -n "$php_scan_dir" && -d "$php_scan_dir" ]]; then
        if [[ -f /opt/php-config/99-custom.ini ]]; then
            sudo cp /opt/php-config/99-custom.ini "$php_scan_dir/99-custom.ini"
            log_success "PHP custom config applied to $php_scan_dir"
        fi
    else
        log_error "Could not determine PHP scan directory"
    fi
}
# Function to install zsh-autosuggestions plugin
setup_zsh_autosuggestions() {
    log_info "Installing zsh-autosuggestions plugin..."
    # Install zsh-autosuggestions for history-based inline completions.
    # Suggestions are driven by $HISTFILE, so suggestions persist
    # across container rebuilds automatically.
    local plugin_dir="${ZSH_CUSTOM:-/home/vscode/.oh-my-zsh/custom}/plugins/zsh-autosuggestions"
    mkdir -p "$(dirname "$plugin_dir")"
    if [[ -d "$plugin_dir/.git" ]]; then
        execute_with_indent "git -C \"$plugin_dir\" pull --ff-only" "Updating zsh-autosuggestions"
        log_success "zsh-autosuggestions updated"
    else
        # Remove a leftover/partial directory that isn't a valid git repo before cloning.
        rm -rf "$plugin_dir"
        if execute_with_indent "git clone --depth=1 https://github.com/zsh-users/zsh-autosuggestions \"$plugin_dir\"" "Cloning zsh-autosuggestions"; then
            log_success "zsh-autosuggestions installed"
        else
            log_error "Failed to install zsh-autosuggestions"
            return 1
        fi
    fi
}
# Function to setup symlinks for development files
setup_dev_symlinks() {
    if [[ "${GITHUB_CLI:-false}" == "true" ]]; then
        log_info "Skipping dev symlinks in CI environment"
        return 0
    fi
    log_info "Setting up development symlinks..."
    local files=(".zsh_history")
    for file in "${files[@]}"; do
        local source="/WSL_USER/${file}"
        local target="${HOME}/${file}"
        if [[ -f "${source}" && ! -L "${target}" ]]; then
            execute_with_indent "ln -sf '${source}' '${target}'" "Creating symlink for ${file}"
            log_success "Created symlink: ${file}"
        elif [[ -L "${target}" ]]; then
            log_detail "Symlink already exists: ${file}"
        else
            log_error "Source file not found: ${source}"
        fi
    done
}

# Main execution
main() {
    echo "=== ${SCRIPT_NAME} Starting ==="
    echo "Executed by: $(whoami)"
    echo "Working directory: $(pwd)"
    echo "Environment: ${GITHUB_CLI:-"development"}"
    # Store original working directory
    local original_dir
    original_dir=$(pwd)
    # Ensure we return to original directory on exit
    trap "cd '${original_dir}'" EXIT

    # install pnpm
    setup_pnpm
    # install zsh-autosuggestions plugin (before sourcing .zshrc)
    setup_zsh_autosuggestions
    # install commitizen and cz-conventional-changelog globally
    setup_pnpm_global_packages
    # apply custom PHP ini settings
    setup_php_config
    # use gh CLI for git credentials (clear system-level VS Code helper first)
    git config --local --replace-all credential.helper ''
    git config --local --add credential.helper '!gh auth git-credential'

    # install composer & nodejs deps
    setup_php_nodejs_dependencies
    # setup development symlinks
    setup_dev_symlinks
}

# Execute main function
main "$@"