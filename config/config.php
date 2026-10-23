<?php
/**
 * Configuração da Intranet.
 *
 * Valores sensíveis ou específicos do servidor devem vir de variáveis de
 * ambiente (ver docs/operacao.md). Nada de senha neste arquivo.
 */

$base = dirname(__DIR__);
$env = static function (string $key, $default = null) {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
};

$storage = rtrim(str_replace('\\', '/', (string) $env('INTRANET_STORAGE', $base . '/storage')), '/');

return [
    // Nunca ligar em produção: mostra erros na tela.
    'debug' => $env('INTRANET_DEBUG', '0') === '1',

    // Tudo que muda em runtime fica FORA do docroot (public/).
    'paths' => [
        'storage'  => $storage,
        'uploads'  => $storage . '/uploads',
        'trash'    => $storage . '/trash',
        'database' => $storage . '/database.db',
        'logs'     => $storage . '/logs',
    ],

    'allowed_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico',
        'pdf', 'txt', 'csv', 'rtf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods',
        'dwg', 'dxf', 'xml', 'json', 'log', 'msg', 'eml', 'ofx',
        'mp4', 'mov', 'avi', 'mp3', 'wav',
        'zip', 'rar', '7z', 'tar', 'gz',
    ],

    // Só estas extensões abrem no navegador (inline). Todo o resto
    // (inclusive SVG/XML) é sempre entregue como download.
    'inline_mime' => [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
        'mp4'  => 'video/mp4',
        'txt'  => 'text/plain; charset=utf-8',
    ],
    'image_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],

    // Nomes que nunca aparecem na listagem nem podem ser baixados.
    'hidden_names' => ['.', '..', '.htaccess', 'web.config', '.lixeira', 'database.db'],

    'auth' => [
        'max_attempts'        => 5,      // falhas por usuário+IP na janela
        'lockout_minutes'     => 15,
        'min_password_length' => 8,
        'session_lifetime'    => 28800,  // 8h de inatividade
        'session_absolute'    => 43200,  // 12h desde o login, mesmo com uso contínuo
    ],

    // 1 = site atrás de proxy/balanceador que termina o HTTPS (o PHP não vê
    // $_SERVER['HTTPS']): força cookie Secure e envia HSTS.
    'force_https' => $env('INTRANET_FORCE_HTTPS', '0') === '1',

    'logs' => [
        'retention_days' => 180,
    ],
];
