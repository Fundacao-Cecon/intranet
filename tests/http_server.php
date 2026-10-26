<?php
/**
 * Sobe/derruba servidores PHP embutidos para os testes de integração (startServer).
 * Requer $tmp (pasta temporária do teste) definido antes do uso.
 */

// Servidores de teste (o principal e as instâncias extras da seção "Configurações do servidor").
$GLOBALS['__servers'] = [];
register_shutdown_function(function () use ($tmp) {
    foreach ($GLOBALS['__servers'] as $p) {
        if (is_resource($p)) { proc_terminate($p); proc_close($p); }
    }
    rrm($tmp);
});

/**
 * Sobe `php -S` numa porta livre. Sob tests/coverage.php o servidor também é medido
 * (grava o JSON ao fim de cada requisição).
 *
 * @param string[] $ini  diretivas extras (ex.: ['post_max_size=8M'])
 * @param string|null $docroot  raiz servida (padrão: public/)
 * @return string        URL base
 */
function startServer(string $storage, array $ini = [], array $env = [], ?string $docroot = null): string
{
    global $tmp;
    $sock = stream_socket_server('tcp://127.0.0.1:0', $en, $es);
    $port = (int) substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
    fclose($sock);

    $flags = coverageFlags();
    $defaults = ['post_max_size=1M', 'upload_max_filesize=1M', 'display_errors=0', "session.save_path=$tmp/sessions"];
    $map = [];
    foreach (array_merge($defaults, $ini) as $d) { [$k] = explode('=', $d, 2); $map[$k] = $d; } // $ini sobrepõe
    // Valor entre aspas: no INI um "~" solto é operador, e o temp do Windows pode ter nome curto
    // (C:/Users/RUNNER~1/...), o que corrompia session.save_path e dava 419 em todo login.
    foreach ($map as $k => $d) { $flags[] = '-d'; $flags[] = $k . '="' . substr($d, strlen($k) + 1) . '"'; }

    $proc = proc_open(
        array_merge([PHP_BINARY], $flags, ['-S', "127.0.0.1:$port", '-t', $docroot ?? dirname(__DIR__) . '/public']),
        [0 => ['pipe', 'r'], 1 => ['file', "$tmp/server-$port.log", 'a'], 2 => ['file', "$tmp/server-$port.log", 'a']],
        $pipes,
        null,
        array_merge(getenv(), ['INTRANET_STORAGE' => $storage, 'INTRANET_DEBUG' => '0', 'INTRANET_FORCE_HTTPS' => '0'], $env)
    );
    $GLOBALS['__servers'][] = $proc;
    for ($i = 0, $up = false; $i < 100 && !$up; $i++) {
        $c = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2);
        if ($c) { fclose($c); $up = true; } else { usleep(100000); }
    }
    if (!$up) { exit("servidor de teste não subiu (veja $tmp/server-$port.log)\n"); }
    return "http://127.0.0.1:$port";
}
