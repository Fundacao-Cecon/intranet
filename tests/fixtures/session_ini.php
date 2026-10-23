<?php
/**
 * Roda Application::startSession() num processo isolado e imprime, em JSON, o que
 * ficou configurado. Usado por tests/run.php (lê INTRANET_FORCE_HTTPS do ambiente).
 */
ob_start();

$config = require dirname(__DIR__, 2) . '/src/bootstrap.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
ini_set('session.save_path', sys_get_temp_dir());

$app = new App\Application($config);
$start = new ReflectionMethod($app, 'startSession');
$start->setAccessible(true);
$start->invoke($app);

$https = new ReflectionMethod($app, 'isHttps');
$https->setAccessible(true);

$p = session_get_cookie_params();
$out = [
    'gc'       => ini_get('session.gc_maxlifetime'),
    'secure'   => $p['secure'],
    'httponly' => $p['httponly'],
    'samesite' => $p['samesite'],
    'https'    => $https->invoke($app),
];
session_write_close();
ob_end_clean();
echo json_encode($out);
