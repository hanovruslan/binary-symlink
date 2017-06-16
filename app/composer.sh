#!/usr/bin/env bash

echo "Current working directory: '"$(pwd)"'"

docker run --rm -ti \
  -v "${PWD}":/app \
  -v /usr/share/composer/cache:/composer/cache \
  -v "${SSH_AUTH_SOCK}":/ssh-auth.sock \
  -v /etc/passwd:/etc/passwd:ro \
  -v /etc/group:/etc/group:ro \
  -u $(id -u):$(id -g) \
  -e SSH_AUTH_SOCK=/ssh-auth.sock \
  -e TERM=xterm-256color \
  composer:1.4.2 $@
