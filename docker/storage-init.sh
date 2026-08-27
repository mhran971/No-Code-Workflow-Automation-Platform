#!/usr/bin/env sh
#
# Make the storage tree exist and be writable by the PHP user.
#
# This deliberately runs from each supervisord program rather than only from
# entrypoint.sh, because a mounted volume lands on /var/www/storage *after* the
# container process has already started (Railway logs the mount after
# "supervisord started with pid 1"). Anything the entrypoint created or chowned
# before that point is hidden behind the mount, and what surfaces instead is an
# empty, root-owned directory — while php-fpm workers, queue:work and
# schedule:work all run as www-data.
#
# Symptoms when this has not run: "Please provide a valid cache path." at boot,
# and 'The stream or file "/var/www/storage/logs/laravel.log" could not be
# opened in append mode: Failed to open stream: Permission denied' at runtime.
#
# Idempotent and always exits 0 — it must never be the reason a worker fails to
# start. The recursive chown is guarded so it is paid once, on the first start
# after a mount, rather than on every restart of a volume full of uploads.

cd /var/www || exit 0

mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/fonts \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache 2>/dev/null

if [ "$(stat -c %U storage 2>/dev/null)" != "www-data" ]; then
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null
    chmod -R ug+rwX storage bootstrap/cache 2>/dev/null
fi

# The log file itself: Monolog creates it on demand, but if a root-owned one is
# already sitting on the volume from an earlier boot, www-data cannot append to
# it and every write throws. Hand it over rather than leaving it poisoned.
if [ -e storage/logs/laravel.log ] && [ "$(stat -c %U storage/logs/laravel.log 2>/dev/null)" != "www-data" ]; then
    chown www-data:www-data storage/logs/laravel.log 2>/dev/null
fi

exit 0
