FROM composer:lts as composer
FROM node:latest as nodejs
FROM mcr.microsoft.com/devcontainers/base:ubuntu

ARG PHP_VERSION=8.0
ENV PHP_VERSION=$PHP_VERSION

#Environment variables to configure php
ENV PHP_UPLOAD_MAX_FILESIZE 10M
ENV PHP_POST_MAX_SIZE 10M

ENV REFRESHED_AT 2022-12-20
ENV DEBIAN_FRONTEND noninteractive
ENV DEBIAN_FRONTEND=noninteractive
# Set a default value for ZSH_CUSTOM if it's not already set
ENV ZSH_CUSTOM=/home/vscode/.oh-my-zsh/custom
ENV TERM=xterm-256color
ENV TZ=Europe/Berlin
ENV APT_KEY_DONT_WARN_ON_DANGEROUS_USAGE=DontWarn

ENV CONFIGURATION_DIR_PATH=supporting_files/configuration
ENV SCRIPTS_DIR_PATH=supporting_files/scripts

# Install packages
# Suppress an apt-key warning about standard out not being a terminal. Use in this script is safe.
RUN apt-get update \
    && apt-get -y upgrade \
    && apt-get -y install software-properties-common cron
RUN LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php && \
    apt-key adv --keyserver keyserver.ubuntu.com --recv 4F4EA0AAE5267A6C
    
RUN apt-get -y install postfix python3-setuptools wget jq sudo git php${PHP_VERSION} php${PHP_VERSION}-intl php${PHP_VERSION}-xdebug pwgen php${PHP_VERSION}-apcu php${PHP_VERSION}-gd php${PHP_VERSION}-xml php${PHP_VERSION}-mbstring zip unzip php${PHP_VERSION}-zip curl php${PHP_VERSION}-curl

# Cleanup
RUN apt-get -y autoremove && \
    apt-get -y clean

# Add image configuration and scripts
ADD ${SCRIPTS_DIR_PATH}/post-create.sh /post-create.sh

# Time Zone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Copy shell scripts and config files
# Use multi-stage build pattern for better caching
COPY ${SCRIPTS_DIR_PATH}/ /usr/local/bin/
COPY ${ARCHIVES_DIR_PATH} /tmp/archives/
COPY ${CONFIGURATION_DIR_PATH}/php/ /etc/php/${PHP_VERSION}/

# Composer 2.x Installation
# Install Composer
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

######################### DEVELOPMENT ENVIRONMENT ADDITIONS ###############################
# install latest git & zsh
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
RUN set -eux; \
    sudo -u ${USERNAME} git clone --depth=1 --single-branch \
        https://github.com/romkatv/powerlevel10k.git $ZSH_CUSTOM/themes/powerlevel10k && \
    sudo -u ${USERNAME} git clone --depth=1 --single-branch \
        https://github.com/zsh-users/zsh-autosuggestions $ZSH_CUSTOM/plugins/zsh-autosuggestions
# copy zsh and committizen configuration file and zsh theme powerline10k
COPY --chown=${USERNAME}:${USERNAME} ${CONFIGURATION_DIR_PATH}/home/ ${USERHOME}/
