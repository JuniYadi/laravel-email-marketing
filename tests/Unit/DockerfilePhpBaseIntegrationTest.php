<?php

it('extends php-base runtime configuration without overriding core startup files', function () {
    $dockerfilePath = dirname(__DIR__, 2).'/Dockerfile';
    $dockerfile = file_get_contents($dockerfilePath);

    expect($dockerfile)->not->toBeFalse()
        ->and($dockerfile)->toContain('FROM ghcr.io/juniyadi/php-base:8.5')
        ->and($dockerfile)->toContain('COPY docker/supervisor/app-services.conf /etc/supervisor.d/app-services.conf')
        ->and($dockerfile)->toContain('COPY docker/app-bootstrap.sh /usr/local/bin/app-bootstrap.sh')
        ->and($dockerfile)->toContain('CMD ["sh", "-lc", "/usr/local/bin/app-bootstrap.sh && if command -v start.sh >/dev/null 2>&1; then exec \"$(command -v start.sh)\"; elif [ -x /usr/local/bin/start.sh ]; then exec /usr/local/bin/start.sh; elif [ -x /start.sh ]; then exec /start.sh; else echo \"ERROR: php-base start script not found\"; ls -la /usr/local/bin; exit 127; fi"]')
        ->and($dockerfile)->not->toContain('COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf')
        ->and($dockerfile)->not->toContain('COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh')
        ->and($dockerfile)->not->toContain('ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]')
        ->and($dockerfile)->not->toContain('COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf');
});
