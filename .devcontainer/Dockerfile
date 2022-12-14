FROM composer:latest as composer
FROM node:latest as nodejs
FROM mcr.microsoft.com/devcontainers/base:ubuntu

ARG PHP_VERSION=7.3
ENV PHP_VERSION=$PHP_VERSION

#Environment variables to configure php
ENV PHP_UPLOAD_MAX_FILESIZE 10M
ENV PHP_POST_MAX_SIZE 10M

ENV REFRESHED_AT 2022-12-20
ENV DEBIAN_FRONTEND noninteractive
ENV TZ=Europe/Berlin
ENV APT_KEY_DONT_WARN_ON_DANGEROUS_USAGE=DontWarn

ENV CONFIGURATION_DIR_PATH supporting_files/configuration
ENV SCRIPTS_DIR_PATH supporting_files/scripts

# Install packages
# Suppress an apt-key warning about standard out not being a terminal. Use in this script is safe.
RUN apt-get update \
    && apt-get -y upgrade \
    && apt-get -y install software-properties-common cron
RUN LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php
RUN apt-key adv --keyserver keyserver.ubuntu.com --recv 4F4EA0AAE5267A6C
# install apt deps (first row for puppeteer)
RUN apt-get -y install libnss3 libnspr4 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2
RUN apt-get -y install postfix python3-setuptools wget jq sudo git apache2 php${PHP_VERSION} php${PHP_VERSION}-xdebug libapache2-mod-php${PHP_VERSION} pwgen php${PHP_VERSION}-apcu php${PHP_VERSION}-gd php${PHP_VERSION}-xml php${PHP_VERSION}-mbstring zip unzip php${PHP_VERSION}-zip curl php${PHP_VERSION}-curl
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
# Cleanup
RUN apt-get -y autoremove && \
    apt-get -y clean

# Add image configuration and scripts
ADD ${SCRIPTS_DIR_PATH}/post-create.sh /post-create.sh
RUN chmod 755 /post-create.sh

# Time Zone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Add config files
COPY ${CONFIGURATION_DIR_PATH}/php.ini /etc/php/${PHP_VERSION}/cli/php.ini
COPY ${CONFIGURATION_DIR_PATH}/php.ini /etc/php/${PHP_VERSION}/apache2/php.ini
COPY ${CONFIGURATION_DIR_PATH}/01-xdebug.ini /etc/php/${PHP_VERSION}/apache2/conf.d/20-xdebug.ini
COPY ${CONFIGURATION_DIR_PATH}/01-xdebug.ini /etc/php/${PHP_VERSION}/cli/conf.d/20-xdebug.ini
COPY ${CONFIGURATION_DIR_PATH}/01-error_reporting.ini /etc/php/${PHP_VERSION}/apache2/conf.d/20-error_reporting.ini
COPY ${CONFIGURATION_DIR_PATH}/01-error_reporting.ini /etc/php/${PHP_VERSION}/cli/conf.d/20-error_reporting.ini

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

# specifics to user vscode
ARG USERNAME="vscode"
ARG USERHOME="/home/${USERNAME}"
RUN usermod --shell /usr/bin/zsh ${USERNAME} \
    && usermod -a -G www-data ${USERNAME} \
    && usermod -a -G staff ${USERNAME}

# Nodejs Installation
COPY --from=nodejs /usr/local/include/node /usr/local/include/node
COPY --from=nodejs /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=nodejs /usr/local/bin /usr/local/bin
RUN npm install --global npm@latest

# nvm and nodejs install
COPY ${CONFIGURATION_DIR_PATH}/.zshrc ${USERHOME}/.zshrc
COPY ${CONFIGURATION_DIR_PATH}/.czrc ${USERHOME}/.czrc
RUN chown ${USERNAME}.${USERNAME} ${USERHOME}/.zshrc ${USERHOME}/.czrc \
    && chmod +x ${USERHOME}/.zshrc ${USERHOME}/.czrc

# Add volumes for the app
ENTRYPOINT exec top -b