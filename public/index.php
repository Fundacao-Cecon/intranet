<?php
/**
 * Front controller da Intranet. Único ponto de entrada web (docroot = public/).
 * Toda a lógica está em src/ (ver docs/arquitetura.md).
 */

$config = require dirname(__DIR__) . '/src/bootstrap.php';

(new App\Application($config))->run();
