<?php

namespace App\Support;

/**
 * Lê INTRANET_TRUSTED_PROXIES: a lista de proxies (IP ou faixa CIDR, separados por vírgula) em
 * que a API confia para dizer qual é o IP do cliente (X-Forwarded-For).
 *
 * Vazio ou ausente = não confia em ninguém, e vale o endereço da conexão. É o padrão certo para
 * desenvolvimento e para a API exposta direto. Atrás do proxy da instituição, liste o endereço
 * dele; sem isso todos os clientes pareceriam ter o IP do proxy e o limite por IP do login
 * (T020) bloquearia todo mundo ao mesmo tempo.
 *
 * "*" confia em qualquer origem. Só use se a API NÃO for alcançável sem passar pelo proxy: com ele,
 * qualquer cliente que fale direto com a API poderia forjar o IP e escapar do limitador.
 */
final class TrustedProxies
{
    /** @return list<string> */
    public static function parse(?string $valor): array
    {
        if ($valor === null) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $valor)), fn (string $item) => $item !== ''));
    }
}
