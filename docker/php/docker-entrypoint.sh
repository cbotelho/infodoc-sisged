#!/bin/sh
set -eu

mkdir -p \
  /var/www/html/backups \
  /var/www/html/cache \
  /var/www/html/log \
  /var/www/html/tmp \
  /var/www/html/uploads \
  /var/www/html/uploads/attachments \
  /var/www/html/uploads/attachments_preview \
  /var/www/html/uploads/images \
  /var/www/html/uploads/users

chown -R www-data:www-data \
  /var/www/html/backups \
  /var/www/html/cache \
  /var/www/html/log \
  /var/www/html/tmp \
  /var/www/html/uploads

exec "$@"