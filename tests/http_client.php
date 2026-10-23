<?php
/**
 * Cliente HTTP mínimo para os testes de integração (cookies por cliente, sem seguir
 * redirects, CSRF automático). Compartilhado por http_test.php, backup_test.php e large_test.php.
 */

final class Resp
{
    public $status = 0;
    public $headers = [];
    public $body = '';

    public function h(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function location(): string
    {
        return $this->h('location');
    }

    /** Texto do toast (flash) embutido na página, se houver. */
    public function flash(): string
    {
        return preg_match('/data-flash-text="([^"]*)"/', $this->body, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : '';
    }

    /** Mensagem de erro/sucesso da tela de login. */
    public function loginMessage(): string
    {
        return preg_match('/class="(?:error|success)">([^<]*)</', $this->body, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : '';
    }
}

final class Client
{
    public $jar;
    /** Tempo máximo por requisição (s). */
    public $timeout = 60;
    private $base;

    public function __construct(string $base)
    {
        $this->base = $base;
        $this->jar = tempnam(sys_get_temp_dir(), 'jar');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    public function req(string $method, string $path, $body = null, array $headers = []): Resp
    {
        $r = new Resp();
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_PATH_AS_IS     => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use ($r) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                    $r->status = (int) $m[1];
                    $r->headers = [];
                } elseif (strpos($line, ':') !== false) {
                    [$k, $v] = explode(':', $line, 2);
                    $k = strtolower(trim($k));
                    $r->headers[$k] = isset($r->headers[$k]) ? $r->headers[$k] . "\n" . trim($v) : trim($v);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $out = curl_exec($ch);
        if ($out === false) {
            throw new RuntimeException('curl: ' . curl_error($ch) . " ($method $path)");
        }
        $r->body = $out;
        curl_close($ch);
        return $r;
    }

    public function get(string $path, array $headers = []): Resp
    {
        return $this->req('GET', $path, null, $headers);
    }

    /** Token CSRF da sessão atual (lido da página). */
    public function token(): string
    {
        $p = $this->get('/');
        if (preg_match('/name="csrf-token" content="([^"]+)"/', $p->body, $m) || preg_match('/name="_csrf" value="([^"]+)"/', $p->body, $m)) {
            return $m[1];
        }
        throw new RuntimeException('sem token CSRF na página (status ' . $p->status . ')');
    }

    /** POST de formulário (urlencoded) com CSRF automático. */
    public function post(string $path, array $fields, bool $csrf = true, array $headers = []): Resp
    {
        if ($csrf) {
            $fields['_csrf'] = $this->token();
        }
        return $this->req('POST', $path, http_build_query($fields), $headers);
    }

    /** POST multipart (upload) com CSRF automático. */
    public function upload(string $path, array $files, array $fields = [], bool $ajax = true): Resp
    {
        $fields += ['_csrf' => $this->token(), 'action' => 'upload'];
        $i = 0;
        foreach ($files as $name => $content) {
            $tmpf = tempnam(sys_get_temp_dir(), 'up');
            file_put_contents($tmpf, $content);
            $fields['arquivos[' . $i++ . ']'] = new CURLFile($tmpf, 'application/octet-stream', $name);
        }
        $r = $this->req('POST', $path, $fields, $ajax ? ['X-Requested-With: XMLHttpRequest'] : []);
        return $r;
    }

    /** POST multipart de arquivos que já existem em disco (nome => caminho), sem carregá-los na memória. */
    public function uploadPaths(string $path, array $files, array $fields = []): Resp
    {
        $fields += ['_csrf' => $this->token(), 'action' => 'upload'];
        $i = 0;
        foreach ($files as $name => $file) {
            $fields['arquivos[' . $i++ . ']'] = new CURLFile($file, 'application/octet-stream', $name);
        }
        return $this->req('POST', $path, $fields, ['X-Requested-With: XMLHttpRequest']);
    }

    /** GET que grava o corpo direto em $dest (para respostas grandes). Devolve [status, headers]. */
    public function downloadTo(string $path, string $dest, array $headers = []): Resp
    {
        $r = new Resp();
        $fh = fopen($dest, 'wb');
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fh,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use ($r) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                    $r->status = (int) $m[1];
                    $r->headers = [];
                } elseif (strpos($line, ':') !== false) {
                    [$k, $v] = explode(':', $line, 2);
                    $r->headers[strtolower(trim($k))] = trim($v);
                }
                return strlen($line);
            },
        ]);
        curl_exec($ch);
        curl_close($ch);
        fclose($fh);
        return $r;
    }

    /** Segue um redirect relativo (Location: index.php?...). */
    public function follow(Resp $r): Resp
    {
        check(in_array($r->status, [301, 302, 303], true), 'esperava redirect, veio ' . $r->status . ' ' . substr($r->body, 0, 80));
        return $this->get('/' . ltrim($r->location(), '/'));
    }

    public function sessionId(): string
    {
        return preg_match('/INTRANETSESSID\s+(\S+)/', (string) file_get_contents($this->jar), $m) ? $m[1] : '';
    }
}
