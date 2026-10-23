<?php
/**
 * Bootstrap: constantes, autoload (PSR-4 "App\" => src/), helpers e config.
 * Não inicia sessão nem envia headers — isso é feito por App\Application,
 * para que scripts CLI (bin/) possam reutilizar este arquivo.
 */

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/helpers.php';

return require BASE_PATH . '/config/config.php';
