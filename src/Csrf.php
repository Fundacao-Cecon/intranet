<?php
namespace App;

/** Token CSRF por sessão. Toda requisição POST precisa enviá-lo. */
final class Csrf
{
    const FIELD = '_csrf';

    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function regenerate(): void
    {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    /** <input hidden> para formulários HTML. */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . h(self::token()) . '">';
    }

    /** Valida o token do POST atual (campo _csrf ou header X-CSRF-Token). */
    public static function verifyRequest(): bool
    {
        $sent = $_POST[self::FIELD] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $real = $_SESSION['csrf'] ?? '';
        return is_string($sent) && $real !== '' && hash_equals($real, $sent);
    }
}
