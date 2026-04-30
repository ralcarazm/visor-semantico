<?php
declare(strict_types=1);

define('KM_ROOT', dirname(__DIR__));

$config = require KM_ROOT . '/config.php';

$autoload = KM_ROOT . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

/**
 * Autocargador mínimo para que la aplicación funcione aunque no se haya
 * ejecutado composer install. Composer seguirá usándose si está disponible.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'KnowledgeMap\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = KM_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

if (($config['app']['debug'] ?? false) === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
}

foreach (($config['paths'] ?? []) as $path) {
    if (is_string($path) && $path !== '' && !is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}

/**
 * Lee valores de configuración mediante notación de puntos.
 *
 * Ejemplo:
 * km_config('app.name')
 */
function km_config(?string $key = null, mixed $default = null): mixed
{
    global $config;

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function km_storage_path(string $relativePath): string
{
    $relativePath = ltrim(str_replace(['..', '\\'], ['', '/'], $relativePath), '/');
    return rtrim((string) km_config('paths.storage'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
}
