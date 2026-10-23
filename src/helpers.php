<?php
/**
 * Funções utilitárias globais (escape, formatação, URL, view).
 */

/** Escape HTML (era h() no index.php original). */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** 1536 => "1.50 KB" (era formatarTamanho). */
function formatarTamanho($bytes): string
{
    $bytes = (float) $bytes;
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)       return number_format($bytes / 1024, 2) . ' KB';
    return (int) $bytes . ' bytes';
}

/** Monta URL do front controller; parâmetros vazios são descartados. */
function url(array $params = []): string
{
    $params = array_filter($params, static function ($v) {
        return $v !== null && $v !== '' && $v !== false;
    });
    return 'index.php' . ($params ? '?' . http_build_query($params) : '');
}

/** Renderiza src/Views/<name>.php com as variáveis informadas. */
function view(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    include BASE_PATH . '/src/Views/' . $name . '.php';
}
