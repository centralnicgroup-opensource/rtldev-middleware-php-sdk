# Custom Git Hooks Directory

This directory contains custom Git hooks for this repository. It is important to note that this is **not** the default `.git/hooks` directory, nor is it a standard `.github` directory provided by GitHub. This is a custom setup intended to provide additional functionality and enforce certain rules within the development workflow.

## Setting Up the Custom Hooks Directory

To ensure that Git uses the hooks in this directory, the `core.hooksPath` configuration has been set to this path. This configuration redirects Git to look for hook scripts here instead of the default location.

### Steps to Set Up the Custom Hooks Path

1. **Configure Git to Use Custom Hooks Path**

   Run the following command in your terminal within the repository:
   ```bash
   git config core.hooksPath [your repo directory]/.github/hooks
