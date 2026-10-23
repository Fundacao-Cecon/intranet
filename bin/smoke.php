<?php
/**
 * Smoke test de um servidor REAL (Apache/IIS/nginx): confere, só por HTTP e sem alterar nada,
 * que o docroot está em public/, que nada interno é servido e que os cabeçalhos de segurança
 * estão no ar. Não precisa de credenciais.
 *
 *   php bin/smoke.php https://intranet.exemplo.gov.br/
 *   php bin/smoke.php http://localhost:8080/ --login      (login/logout de verificação; usa SMOKE_USER e SMOKE_PASS)
 *
 * Saída: PASS / WARN / FAIL por item. Código de saída 1 se houver algum FAIL.
 * Só usa streams do PHP (não exige a extensão curl).
 */

if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}

$args = array_slice($argv, 1);
$withLogin = in_array('--login', $args, true);
$args = array_values(array_filter($args, function ($a) { return $a[0] !== '-'; }));
$base = rtrim($args[0] ?? '', '/');
if ($base === '' || !preg_match('#^https?://#i', $base)) {
    fwrite(STDERR, "Uso: php bin/smoke.php <url-base> [--login]\n"); exit(2);
}
$https = stripos($base, 'https://') === 0;

/** Contagem de resultados (PASS/WARN/FAIL). */
final class Tally
{
    /** @var array<string,int> */
    public static $n = ['PASS' => 0, 'WARN' => 0, 'FAIL' => 0];
}

function report(string $level, string $what, string $detail = ''): void
{
    Tally::$n[$level]++;
    printf("  %-5s %s%s\n", $level, $what, $detail !== '' ? "  — $detail" : '');
}

/** @return array [status, headers(minúsculas => valor), body] */
function http(string $method, string $url, ?array $form = null, array $headers = []): array
{
    $opts = ['http' => [
        'method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 20,
        'header' => implode("\r\n", $headers),
    ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]];
    if ($form !== null) {
        $opts['http']['content'] = http_build_query($form);
        $opts['http']['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
    }
    $fp = @fopen($url, 'rb', false, stream_context_create($opts));
    if ($fp === false) {
        return [0, [], ''];
    }
    $lines = stream_get_meta_data($fp)['wrapper_data'] ?? [];   // cabeçalhos (sem a variável mágica $http_response_header)
    $body = (string) stream_get_contents($fp);
    fclose($fp);
    $status = 0;
    $h = [];
    foreach ($lines as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) { $status = (int) $m[1]; $h = []; }
        elseif (strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $k = strtolower(trim($k));
            $h[$k] = isset($h[$k]) ? $h[$k] . "\n" . trim($v) : trim($v);
        }
    }
    return [$status, $h, $body];
}

echo "Smoke test: $base\n\n";

// 1. Página de entrada -----------------------------------------------------------------------
echo "Entrada\n";
[$st, $h, $body] = http('GET', "$base/");
if ($st === 0) {
    report('FAIL', 'servidor não respondeu', 'confira a URL, o certificado e o firewall');
    echo "\nResultado: " . json_encode(Tally::$n) . "\n";
    exit(1);
}
$st === 200 ? report('PASS', 'GET / responde 200') : report('FAIL', 'GET /', "status $st (esperado 200)");
strpos($body, 'Acesso Intranet') !== false
    ? report('PASS', 'mostra a tela de login')
    : report('FAIL', 'não mostra a tela de login', 'o docroot aponta para public/? o PHP está executando?');
preg_match('/<\?php|password_hash|\$_SESSION/', $body) ? report('FAIL', 'o corpo contém código-fonte PHP', 'PHP não está sendo executado!') : report('PASS', 'nenhum código-fonte no corpo');

// 2. Cabeçalhos e cookie ----------------------------------------------------------------------
echo "\nCabeçalhos\n";
$want = ['x-content-type-options' => 'nosniff', 'x-frame-options' => 'SAMEORIGIN', 'referrer-policy' => 'same-origin'];
foreach ($want as $k => $v) {
    ($h[$k] ?? '') === $v ? report('PASS', "$k: $v") : report('FAIL', $k, 'valor "' . ($h[$k] ?? 'ausente') . "\" (esperado $v)");
}
stripos($h['cache-control'] ?? '', 'no-store') !== false ? report('PASS', 'cache-control: no-store') : report('WARN', 'cache-control sem no-store', $h['cache-control'] ?? 'ausente');
$cookie = $h['set-cookie'] ?? '';
$cookie !== '' && stripos($cookie, 'HttpOnly') !== false && stripos($cookie, 'SameSite=') !== false
    ? report('PASS', 'cookie de sessão HttpOnly + SameSite')
    : report('FAIL', 'cookie de sessão sem HttpOnly/SameSite', $cookie ?: 'nenhum Set-Cookie');
if ($https) {
    stripos($cookie, 'secure') !== false ? report('PASS', 'cookie Secure em HTTPS') : report('FAIL', 'cookie sem Secure em HTTPS', 'atrás de proxy? defina INTRANET_FORCE_HTTPS=1');
    strpos($h['strict-transport-security'] ?? '', 'max-age=') === 0 ? report('PASS', 'HSTS ativo') : report('FAIL', 'sem HSTS em HTTPS', 'defina INTRANET_FORCE_HTTPS=1 se houver proxy');
} else {
    report('WARN', 'site em HTTP puro', 'senhas e cookies trafegam sem criptografia; use HTTPS');
}
isset($h['x-powered-by']) ? report('WARN', 'X-Powered-By expõe a versão do PHP', $h['x-powered-by'] . ' (expose_php = Off)') : report('PASS', 'sem X-Powered-By');
isset($h['server']) && preg_match('#\d+\.\d+#', $h['server']) ? report('WARN', 'cabeçalho Server expõe a versão', $h['server']) : report('PASS', 'Server não expõe versão');

// 3. Nada interno é servido ------------------------------------------------------------------------
echo "\nCaminhos internos (devem ser 403/404 e nunca vazar conteúdo)\n";
$internal = ['/database.db', '/storage/database.db', '/storage/', '/src/Auth.php', '/src/bootstrap.php', '/config/config.php', '/bin/create-user.php',
             '/tests/helpers.php', '/docs/README.md', '/.git/config', '/.env', '/composer.json', '/uploads/', '/_backup_original/index.php',
             '/web.config', '/.htaccess', '/.user.ini', '/public/index.php', '/index.php/../../src/Auth.php', '/%2e%2e/src/Auth.php', '/..%2fconfig/config.php'];
$lowRisk = ['/web.config', '/.htaccess', '/.user.ini']; // configuração do servidor: sem segredos, mas não deve ser pública
foreach ($internal as $p) {
    [$s, , $b] = http('GET', $base . $p);
    $leak = preg_match('/<\?php|password_hash|PDO\(|INTRANET_STORAGE|SQLite format/', $b);
    if ($leak) {
        report('FAIL', $p, "VAZOU conteúdo (status $s)");
    } elseif (in_array($s, [403, 404, 400, 401], true)) {
        report('PASS', $p, "status $s");
    } elseif ($s === 200 && in_array($p, $lowRisk, true) && trim($b) !== '') {
        report('WARN', $p, 'arquivo de configuração acessível pela web (bloqueie no servidor)');
    } elseif ($s === 200 && strpos($b, 'Acesso Intranet') !== false) {
        report('PASS', $p, 'cai no front controller (login)');
    } elseif ($s === 200 && trim($b) === '') {
        report('FAIL', $p, 'status 200 sem corpo: o arquivo PHP está acessível pela web (docroot errado?)');
    } else {
        report('FAIL', $p, "status $s inesperado");
    }
}

// 4. Comportamento da aplicação (sem credenciais) -----------------------------------------------------
echo "\nAplicação\n";
[$s, , $b] = http('GET', "$base/assets/css/app.css");
$s === 200 ? report('PASS', 'assets estáticos servidos') : report('FAIL', '/assets/css/app.css', "status $s");
[$s, , $b] = http('GET', "$base/assets/");
preg_match('/Index of|Directory listing|<title>.*(listing|Index)/i', $b) ? report('FAIL', 'listagem de diretório ligada em /assets/') : report('PASS', 'sem listagem de diretório');
[$s, , $b] = http('POST', "$base/", ['action' => 'login', 'user' => 'smoke', 'pass' => 'smoke']);
$s === 419 ? report('PASS', 'POST sem token CSRF é recusado (419)') : report('FAIL', 'POST sem CSRF', "status $s (esperado 419)");
[$s, $hh, $b] = http('GET', "$base/index.php?action=download&file=qualquer.txt");
$s === 200 && strpos($b, 'Acesso Intranet') !== false && empty($hh['content-disposition']) ? report('PASS', 'download sem login cai no login') : report('FAIL', 'download sem login', "status $s");
[$s, , $b] = http('GET', "$base/index.php?dir[]=x&file[]=y&action[]=z");
preg_match('/(Warning|Notice|Deprecated|Fatal error|Stack trace)\b/', $b)
    ? report('FAIL', 'o PHP exibe erros na tela', 'display_errors = Off no php.ini')
    : report('PASS', 'parâmetros malformados não exibem erros do PHP');

// 5. Login de verificação (opcional) ------------------------------------------------------------------
if ($withLogin) {
    echo "\nLogin de verificação\n";
    $user = getenv('SMOKE_USER');
    $pass = getenv('SMOKE_PASS');
    if (!$user || !$pass) {
        report('FAIL', '--login exige SMOKE_USER e SMOKE_PASS no ambiente');
    } else {
        $jar = function (array $h) { return preg_match('/(INTRANETSESSID=[^;]+)/', $h['set-cookie'] ?? '', $m) ? $m[1] : ''; };
        [$s, $hh, $b] = http('GET', "$base/");
        $sid = $jar($hh);
        preg_match('/name="_csrf" value="([^"]+)"/', $b, $m);
        [$s, $hh, $b] = http('POST', "$base/", ['action' => 'login', 'user' => $user, 'pass' => $pass, '_csrf' => $m[1] ?? ''], ["Cookie: $sid"]);
        $sid2 = $jar($hh) ?: $sid;
        $s === 302 ? report('PASS', 'login aceito (302)') : report('FAIL', 'login', "status $s");
        [$s, , $b] = http('GET', "$base/", null, ["Cookie: $sid2"]);
        strpos($b, 'csrf-token') !== false ? report('PASS', 'painel carrega após o login') : report('FAIL', 'painel não carregou após o login', 'credenciais erradas ou conta bloqueada?');
        http('GET', "$base/index.php?action=logout", null, ["Cookie: $sid2"]);
        [, , $b] = http('GET', "$base/", null, ["Cookie: $sid2"]);
        strpos($b, 'Acesso Intranet') !== false ? report('PASS', 'logout encerra a sessão') : report('FAIL', 'a sessão sobreviveu ao logout');
    }
}

echo "\nResultado: " . Tally::$n['PASS'] . " PASS, " . Tally::$n['WARN'] . " WARN, " . Tally::$n['FAIL'] . " FAIL\n";
exit(Tally::$n['FAIL'] > 0 ? 1 : 0);
