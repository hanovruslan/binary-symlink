#!/usr/bin/env bash

${COMPOSER_DIR:-/app}/bin/phpunit -c ${COMPOSER_DIR:-/app}/symlinks/phpunit-config.xml ${@}
