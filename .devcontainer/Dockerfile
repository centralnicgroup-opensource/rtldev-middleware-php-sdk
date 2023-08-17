FROM composer:lts as composer
FROM node:lts as nodejs
FROM mcr.microsoft.com/devcontainers/base:ubuntu

FROM php:7.4-cli as phpcli
ARG PHP_VERSION=7.4
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
    && apt-get upgrade -y \
    && apt-get install -y -f software-properties-common ca-certificates lsb-release apt-transport-https cron gnupg wget curl jq sudo git postfix python3-setuptools pwgen zip unzip

# # WORKAROUND: research how to use official php image 
# RUN apt-get update && apt install --no-install-recommends

#INSTALL XDEBUG
RUN pecl install xdebug-3.1.6 && docker-php-ext-enable xdebug

RUN apt-get update && apt-get install -y -f libonig-dev libzip-dev libcurl4-openssl-dev libxml2-dev zlib1g-dev libpng-dev libxml2-dev git \
    && docker-php-ext-install curl gd xml mbstring zip

# Install APCu and APC backward compatibility
RUN pecl install apcu \
    && pecl install apcu_bc-1.0.3 \
    && docker-php-ext-enable apcu --ini-name 10-docker-php-ext-apcu.ini \
    && docker-php-ext-enable apc --ini-name 20-docker-php-ext-apc.ini
    
#RUN apt-get -y install php${PHP_VERSION}-intl php${PHP_VERSION}-xdebug php${PHP_VERSION}-apcu php${PHP_VERSION}-gd php${PHP_VERSION}-xml php${PHP_VERSION}-mbstring  php${PHP_VERSION}-zip php${PHP_VERSION}-curl

# Cleanup
RUN apt-get -y autoremove && \
    apt-get -y clean

# Add image configuration and scripts
ADD ${SCRIPTS_DIR_PATH}/post-create.sh /post-create.sh

# Time Zone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Add config files
COPY ${CONFIGURATION_DIR_PATH}/php.ini /etc/php/${PHP_VERSION}/cli/php.ini
COPY ${CONFIGURATION_DIR_PATH}/01-xdebug.ini /etc/php/${PHP_VERSION}/cli/conf.d/20-xdebug.ini
COPY ${CONFIGURATION_DIR_PATH}/01-error_reporting.ini /etc/php/${PHP_VERSION}/cli/conf.d/20-error_reporting.ini

# Composer 2.x Installation
# Install Composer
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

######################### DEVELOPMENT ENVIRONMENT ADDITIONS ###############################

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

# zsh and commitizen configuration
COPY ${CONFIGURATION_DIR_PATH}/.zshrc ${USERHOME}/.zshrc
COPY ${CONFIGURATION_DIR_PATH}/.czrc ${USERHOME}/.czrc
RUN chown ${USERNAME}.${USERNAME} ${USERHOME}/.zshrc ${USERHOME}/.czrc