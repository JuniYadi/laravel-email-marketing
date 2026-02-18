<?php

it('contains unique critical nginx location blocks in docker config', function () {
    $configPath = dirname(__DIR__, 2).'/docker/nginx/default.conf';
    $config = file_get_contents($configPath);

    expect($config)->not->toBeFalse()
        ->and(substr_count($config, 'location = /favicon.ico'))->toBe(1)
        ->and(substr_count($config, 'location = /robots.txt'))->toBe(1)
        ->and(substr_count($config, 'location ~ \.php$'))->toBe(1);
});
