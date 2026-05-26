FROM composer:lts AS composer
FROM mcr.microsoft.com/devcontainers/base:ubuntu

ARG PHP_VERSION=8.3
ENV PHP_VERSION=$PHP_VERSION

#Environment variables to configure php
ENV PHP_UPLOAD_MAX_FILESIZE=10M
ENV PHP_POST_MAX_SIZE=10M

ENV DEBIAN_FRONTEND=noninteractive
# Set a default value for ZSH_CUSTOM if it's not already set
ENV ZSH_CUSTOM=/home/vscode/.oh-my-zsh/custom
ENV TERM=xterm-256color
ARG TZ=Europe/Berlin
ENV TZ=$TZ

ARG CONFIGURATION_DIR_PATH=supporting_files/configuration
ARG SCRIPTS_DIR_PATH=supporting_files/scripts

# Install packages
# hadolint ignore=DL3008
RUN apt-get update \
    && apt-get -y upgrade \
    && apt-get -y install --no-install-recommends software-properties-common cron \
    && LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php \
    && apt-get -y install --no-install-recommends postfix python3-setuptools wget jq git \
       php${PHP_VERSION} php${PHP_VERSION}-intl php${PHP_VERSION}-xdebug \
       pwgen php${PHP_VERSION}-apcu php${PHP_VERSION}-gd php${PHP_VERSION}-xml \
       php${PHP_VERSION}-mbstring zip unzip php${PHP_VERSION}-zip curl \
       php${PHP_VERSION}-curl \
    && apt-get -y autoremove \
    && apt-get -y clean \
    && rm -rf /var/lib/apt/lists/*

# Time Zone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Copy shell scripts and config files
COPY ${SCRIPTS_DIR_PATH}/ /usr/local/bin/
COPY ${SCRIPTS_DIR_PATH}/post-create.sh /post-create.sh
COPY ${CONFIGURATION_DIR_PATH}/php/ /etc/php/${PHP_VERSION}/

# Composer 2.x Installation
# Install Composer
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

######################### DEVELOPMENT ENVIRONMENT ADDITIONS ###############################
# install latest git & zsh
# hadolint ignore=DL3008
RUN add-apt-repository ppa:git-core/ppa \
    && apt-get update \
    && apt-get -y install --no-install-recommends git \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && update-locale
# zsh and commitizen (cz) configurations
ARG USERNAME="vscode"
ARG USERHOME="/home/${USERNAME}"
RUN set -eux; \
    usermod --shell /usr/bin/zsh -aG www-data ${USERNAME}

# Clone the powerlevel10k theme for zsh and change owner
# Clone the zsh-autosuggestions repository and change owner
# Git operations - optimized with depth and single-branch
USER ${USERNAME}
RUN set -eux; \
    git clone --depth=1 --single-branch \
        https://github.com/romkatv/powerlevel10k.git $ZSH_CUSTOM/themes/powerlevel10k && \
    git clone --depth=1 --single-branch \
        https://github.com/zsh-users/zsh-autosuggestions $ZSH_CUSTOM/plugins/zsh-autosuggestions
USER root
# copy zsh and committizen configuration file and zsh theme powerline10k
COPY --chown=${USERNAME}:${USERNAME} ${CONFIGURATION_DIR_PATH}/home/ ${USERHOME}/
