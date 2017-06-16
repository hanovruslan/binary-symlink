#!/usr/bin/env bash

sudo mkdir -p /usr/share/composer/cache \
&& sudo chown -R $(id -u) /usr/share/composer/cache
