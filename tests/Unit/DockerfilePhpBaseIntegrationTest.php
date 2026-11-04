<?php

it('builds on the pfnapp php base and hands off to its entrypoint', function () {
    $root = dirname(__DIR__, 2);
    $dockerfile = file_get_contents($root.'/Dockerfile');
    $bootstrap = file_get_contents($root.'/docker/app-bootstrap.sh');
    $services = file_get_contents($root.'/docker/supervisor/app-services.conf');

    expect($dockerfile)
        ->toContain('FROM ghcr.io/pfnapp/base/languages/php:8.5')
        ->toContain('install-php-extensions pgsql pdo_pgsql')
        ->toContain('>> /etc/supervisor/supervisord.conf')
        ->toContain('/etc/nginx/conf.d/custom/routes-laravel.conf')
        ->toContain('USER 10001:10001')
        ->toContain('EXPOSE 8080')
        ->toContain('ENTRYPOINT ["/usr/local/bin/app-bootstrap.sh"]')
        ->not->toContain('php-base')
        ->not->toContain('/etc/cron.d');

    expect($bootstrap)->toContain('exec /usr/local/bin/entrypoint.sh "$@"');

    expect($services)
        ->toContain('[program:queue]')
        ->toContain('schedule:work')
        ->not->toContain('user=www-data');
});
