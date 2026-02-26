<?php

it('uses a valid cron.d scheduler entry with explicit user and working directory', function () {
    $cronFilePath = dirname(__DIR__, 2).'/docker/laravel-scheduler';
    $cronFile = file_get_contents($cronFilePath);

    expect($cronFile)->not->toBeFalse()
        ->and($cronFile)->toContain('SHELL=/bin/sh')
        ->and($cronFile)->toContain('PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin')
        ->and($cronFile)->toContain('* * * * * www-data cd /var/www/html && /usr/local/bin/php artisan schedule:run --no-interaction >> /proc/1/fd/1 2>&1');
});
