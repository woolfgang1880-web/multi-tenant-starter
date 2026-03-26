<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('security:check-config', function () {
    $issues = [];
    $warnings = [];

    $env = (string) config('app.env');
    $isProd = $env === 'production';
    $debug = (bool) config('app.debug');
    $appKey = (string) config('app.key');
    $appUrl = (string) config('app.url');
    $logChannel = (string) config('logging.default');
    $cacheStore = (string) config('cache.default');
    $queueConnection = (string) config('queue.default');
    $sessionDriver = (string) config('session.driver');
    $sessionSecureCookie = (bool) config('session.secure');
    $sessionSameSite = config('session.same_site');
    $sanctumGuard = (array) config('sanctum.guard', []);
    $sanctumPrefix = (string) config('sanctum.token_prefix', '');

    if ($appKey === '' || Str::contains($appKey, 'base64:AAAAAAAA')) {
        $issues[] = 'APP_KEY ausente o débil. Ejecutar `php artisan key:generate` y usar secreto robusto.';
    }

    if ($isProd && $debug) {
        $issues[] = 'APP_DEBUG=true en producción.';
    }

    if ($isProd && Str::startsWith($appUrl, 'http://')) {
        $issues[] = 'APP_URL usa HTTP en producción. Debe ser HTTPS.';
    }

    if ($isProd && in_array($cacheStore, ['array', 'file', 'null'], true)) {
        $issues[] = "CACHE_STORE={$cacheStore} no recomendado para producción (rate limiting/locks no compartidos).";
    }

    if ($isProd && in_array($queueConnection, ['sync', 'null'], true)) {
        $issues[] = "QUEUE_CONNECTION={$queueConnection} no recomendado para producción.";
    }

    if ($isProd && in_array($sessionDriver, ['array', 'file'], true)) {
        $warnings[] = "SESSION_DRIVER={$sessionDriver}: posible pérdida de sesiones entre instancias.";
    }

    if ($isProd && ! $sessionSecureCookie) {
        $warnings[] = 'SESSION_SECURE_COOKIE no está activado.';
    }

    if ($isProd && $sessionSameSite === 'none' && ! $sessionSecureCookie) {
        $issues[] = 'SESSION_SAME_SITE=none requiere SESSION_SECURE_COOKIE=true.';
    }

    if ($isProd && in_array($logChannel, ['single', 'errorlog'], true)) {
        $warnings[] = "LOG_CHANNEL={$logChannel}: revisar estrategia centralizada/rotación para producción.";
    }

    if ($sanctumGuard !== []) {
        $warnings[] = 'sanctum.guard no está vacío. Este starter API-first espera Bearer-only.';
    }

    if ($sanctumPrefix === '') {
        $warnings[] = 'SANCTUM_TOKEN_PREFIX vacío. Recomendado definir prefijo para secret scanning.';
    }

    $this->newLine();
    $this->info('Security config check (starter-core)');
    $this->line("APP_ENV={$env}");

    foreach ($issues as $issue) {
        $this->error("ISSUE: {$issue}");
    }

    foreach ($warnings as $warning) {
        $this->warn("WARN: {$warning}");
    }

    if ($issues === [] && $warnings === []) {
        $this->info('OK: no se detectaron hallazgos con las reglas actuales.');
    }

    $this->newLine();
    $this->line('Nota: este comando no reemplaza hardening de red/proxy/infra.');

    return $issues === [] ? self::SUCCESS : self::FAILURE;
})->purpose('Revisión ligera de configuración sensible para despliegue');
