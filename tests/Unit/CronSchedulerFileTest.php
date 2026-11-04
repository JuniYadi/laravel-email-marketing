<?php

it('runs the Laravel scheduler as a supervisor program instead of cron.d', function () {
    $root = dirname(__DIR__, 2);
    $services = file_get_contents($root.'/docker/supervisor/app-services.conf');

    expect(file_exists($root.'/docker/laravel-scheduler'))->toBeFalse()
        ->and($services)->toContain('[program:scheduler]')
        ->and($services)->toContain('command=php /var/www/html/artisan schedule:work --no-interaction')
        ->and($services)->toContain('directory=/var/www/html');
});
