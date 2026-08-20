# Development image for this repository.
#
# Deliberately thin. The package installs, timezone, zsh configuration and
# commitizen setup that used to live here are now in the devbase Feature:
# https://github.com/centralnicgroup-opensource/rtldev-middleware-devcontainer-features
#
# Add a layer here only for something the Feature cannot express -- as the two
# below are: a system library the PHP build needs, and an image-level config file.

FROM mcr.microsoft.com/devcontainers/base:2-ubuntu-24.04

# No ENV DEBIAN_FRONTEND here on purpose: set at image level it persists into the
# running container, so an interactive `apt` in a shell session also loses its
# frontend. The layer below sets it for itself.
ENV TERM=xterm-256color

ARG CONFIGURATION_DIR_PATH=supporting_files/configuration

# php8.3-intl only. The devcontainer PHP Feature installs a custom PHP build that
# does not compile bundled extensions like intl; this system package has the same
# PHP API version, and configuration/php/intl.ini loads its .so.
#
# Everything else that used to be installed here (wget jq git zip unzip curl
# shellcheck) comes from devbase's commonPackages.
# hadolint ignore=DL3008
RUN DEBIAN_FRONTEND=noninteractive apt-get update \
    && apt-get -y install --no-install-recommends \
       php8.3-intl \
    && apt-get -y autoremove \
    && apt-get -y clean \
    && rm -rf /var/lib/apt/lists/*

# Applied to the running PHP build's scan directory by
# supporting_files/scripts/post-create.sh -- that directory does not exist until
# the PHP Feature has run, which is why these are staged here rather than copied
# into place.
COPY ${CONFIGURATION_DIR_PATH}/php/ /opt/php-config/
