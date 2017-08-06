#!/usr/bin/env bash

version=${COMPOSER_VERSION:-"1.4.2"}
user=${COMPOSER_USER:-"$(id -u)"}
group=${COMPOSER_GROUP:-"$(id -g)"}

echo "Current working directory: '"$(pwd)"'"

docker run --rm -ti \
  -v "${PWD}":/app \
  -v /usr/share/composer/cache:/composer/cache \
  -v "${SSH_AUTH_SOCK}":/ssh-auth.sock \
  -v /etc/passwd:/etc/passwd:ro \
  -v /etc/group:/etc/group:ro \
  -u "${user}":"${group}" \
  -e SSH_AUTH_SOCK=/ssh-auth.sock \
  composer:"${version}" \
  "${@}"