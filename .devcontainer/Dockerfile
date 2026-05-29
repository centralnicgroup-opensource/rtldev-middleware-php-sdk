FROM mcr.microsoft.com/devcontainers/base:2-ubuntu-24.04

ENV DEBIAN_FRONTEND=noninteractive
# Set a default value for ZSH_CUSTOM if it's not already set
ENV ZSH_CUSTOM=/home/vscode/.oh-my-zsh/custom
ENV TERM=xterm-256color
ARG TZ=Europe/Berlin
ENV TZ=$TZ

ARG CONFIGURATION_DIR_PATH=supporting_files/configuration
ARG SCRIPTS_DIR_PATH=supporting_files/scripts

# Install non-PHP system packages
# hadolint ignore=DL3008
RUN apt-get update \
    && apt-get -y upgrade \
    && apt-get -y install --no-install-recommends \
       wget jq git zip unzip curl \
       php8.3-intl \
    && apt-get -y autoremove \
    && apt-get -y clean \
    && rm -rf /var/lib/apt/lists/*

# Time Zone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Copy shell scripts and config files
COPY ${SCRIPTS_DIR_PATH}/ /usr/local/bin/
COPY ${SCRIPTS_DIR_PATH}/post-create.sh /post-create.sh
COPY ${CONFIGURATION_DIR_PATH}/php/ /opt/php-config/

######################### DEVELOPMENT ENVIRONMENT ADDITIONS ###############################
# zsh and commitizen (cz) configurations
ARG USERNAME="vscode"
ARG USERHOME="/home/${USERNAME}"
RUN set -eux; \
    usermod --shell /usr/bin/zsh -aG www-data ${USERNAME}

# copy zsh and commitizen configuration files
COPY --chown=${USERNAME}:${USERNAME} ${CONFIGURATION_DIR_PATH}/home/ ${USERHOME}/
