#!/bin/sh
set -eu

PHP_UPLOAD_MAX_FILESIZE="${PHP_UPLOAD_MAX_FILESIZE:-1024M}"
PHP_POST_MAX_SIZE="${PHP_POST_MAX_SIZE:-1024M}"
PHP_MEMORY_LIMIT="${PHP_MEMORY_LIMIT:-512M}"
PHP_MAX_EXECUTION_TIME="${PHP_MAX_EXECUTION_TIME:-1200}"
PHP_MAX_INPUT_TIME="${PHP_MAX_INPUT_TIME:-1200}"
PHP_MAX_INPUT_VARS="${PHP_MAX_INPUT_VARS:-5000}"
PHP_DATE_TIMEZONE="${PHP_DATE_TIMEZONE:-America/Sao_Paulo}"
PHP_DISPLAY_ERRORS="${PHP_DISPLAY_ERRORS:-Off}"
PHP_LOG_ERRORS="${PHP_LOG_ERRORS:-On}"

cat > /usr/local/etc/php/conf.d/zz-runtime-overrides.ini <<EOF
upload_max_filesize = ${PHP_UPLOAD_MAX_FILESIZE}
post_max_size = ${PHP_POST_MAX_SIZE}
memory_limit = ${PHP_MEMORY_LIMIT}
max_execution_time = ${PHP_MAX_EXECUTION_TIME}
max_input_time = ${PHP_MAX_INPUT_TIME}
max_input_vars = ${PHP_MAX_INPUT_VARS}
date.timezone = ${PHP_DATE_TIMEZONE}
display_errors = ${PHP_DISPLAY_ERRORS}
log_errors = ${PHP_LOG_ERRORS}
EOF

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