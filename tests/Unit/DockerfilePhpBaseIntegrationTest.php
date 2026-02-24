<?php

it('extends php-base runtime configuration without overriding core startup files', function () {
    $dockerfilePath = dirname(__DIR__, 2).'/Dockerfile';
    $dockerfile = file_get_contents($dockerfilePath);

    expect($dockerfile)->not->toBeFalse()
        ->and($dockerfile)->toContain('FROM ghcr.io/juniyadi/php-base:8.5')
        ->and($dockerfile)->toContain('NGINX_DOCROOT=/var/www/html/public')
        ->and($dockerfile)->toContain('NGINX_FRONT_CONTROLLER="/index.php?\$query_string"')
        ->and($dockerfile)->toContain('APP_BOOTSTRAP_CMD=')
        ->and($dockerfile)->toContain('COPY docker/supervisor/app-services.conf /etc/supervisor.d/app-services.conf')
        ->and($dockerfile)->not->toContain('CMD [')
        ->and($dockerfile)->not->toContain('COPY docker/crontab /etc/cron.d/laravel')
        ->and($dockerfile)->not->toContain('COPY docker/app-bootstrap.sh /usr/local/bin/app-bootstrap.sh')
        ->and($dockerfile)->not->toContain('COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf')
        ->and($dockerfile)->not->toContain('COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh')
        ->and($dockerfile)->not->toContain('ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]')
        ->and($dockerfile)->not->toContain('COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf');
});
