<?php
namespace App\Services;

/**
 * Acesso seguro ao sistema de arquivos (uploads e lixeira).
 *
 * Toda pasta recebida do usuário passa por resolve(); todo nome de item passa
 * por safeItem()/cleanName(). Caminhos retornados usam "/" e terminam com "/".
 */
final class FileStorage
{
    const AREA_FILES = 'files';
    const AREA_TRASH = 'trash';

    /** @var array<string,string> */
    private $roots = [];
    /** @var string[] */
    private $hidden;

    public function __construct(string $uploadsDir, string $trashDir, array $hiddenNames)
    {
        foreach ([self::AREA_FILES => $uploadsDir, self::AREA_TRASH => $trashDir] as $area => $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $this->roots[$area] = rtrim(str_replace('\\', '/', realpath($dir)), '/') . '/';
        }
        $this->hidden = $hiddenNames;
    }

    public function root(string $area): string
    {
        return $this->roots[$area];
    }

    /**
     * Resolve uma subpasta pedida pelo usuário dentro da raiz da área.
     * Qualquer coisa suspeita (.., fora da raiz, inexistente) cai na raiz.
     *
     * @return array [string $absDir, string $cleanSub]
     */
    public function resolve(string $area, string $sub): array
    {
        $root = $this->roots[$area];
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $sub)) as $p) {
            if ($p === '' || $p === '.') {
                continue;
            }
            if ($p === '..' || strpos($p, "\0") !== false) {
                return [$root, ''];
            }
            $parts[] = $p;
        }
        if (!$parts) {
            return [$root, ''];
        }

        $clean = implode('/', $parts);
        $real = realpath($root . $clean);
        if ($real === false || !is_dir($real)) {
            return [$root, ''];
        }
        // Compara com "/" final para que "uploads" não aceite "uploads2".
        $real = str_replace('\\', '/', $real) . '/';
        if (!$this->startsWith($real, $root)) {
            return [$root, ''];
        }
        return [$root . $clean . '/', $clean];
    }

    /** Nome simples de item (sem diretórios); '' se inválido. */
    public function safeItem($name): string
    {
        if (!is_string($name)) { // ?file[]=x chega como array: não é um nome
            return '';
        }
        $name = basename(str_replace('\\', '/', $name));
        if ($name === '' || $name === '.' || $name === '..' || strpos($name, "\0") !== false) {
            return '';
        }
        return $name;
    }

    /** Era limparNome(): mantém letras, números, espaço, - _ . */
    public function cleanName(string $nome): string
    {
        $limpo = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $nome);
        return str_replace(['..', '/'], '', (string) $limpo);
    }

    /** Era renomearSeExistir(): "a.pdf" -> "a (1).pdf" se já existir. Só ESCOLHE o nome. */
    public function uniqueName(string $dir, string $nome): string
    {
        list($base, $ext) = $this->splitName($nome);
        $n = 0;
        do {
            $final = $this->candidate($base, $ext, $n++);
        } while (file_exists($dir . $final));
        return $final;
    }

    /**
     * Como uniqueName(), mas RESERVA o nome criando um arquivo vazio de forma atômica
     * (modo "x": falha se já existir). Dois uploads simultâneos com o mesmo nome nunca
     * recebem o mesmo nome; quem chamou depois substitui o arquivo vazio pelo conteúdo
     * (move_uploaded_file/rename sobrescrevem) ou o apaga se desistir.
     */
    public function reserveName(string $dir, string $nome): string
    {
        list($base, $ext) = $this->splitName($nome);
        for ($n = 0; $n < 100000; $n++) {
            $final = $this->candidate($base, $ext, $n);
            $h = @fopen($dir . $final, 'x');
            if ($h !== false) {
                fclose($h);
                return $final;
            }
        }
        throw new \RuntimeException('Não foi possível reservar um nome livre em ' . $dir);
    }

    /**
     * Reserva o nome de um arquivo que vai chegar por rename (mover), SEM limpar o nome: o que já existe
     * não é renomeado pelo caminho. Cria o arquivo vazio de forma atômica ("x") e, se o nome estiver
     * ocupado, tenta "a (1).pdf", "a (2).pdf"... Dois "mover" para o mesmo destino nunca se sobrescrevem;
     * o rename seguinte substitui o placeholder (ou o chamador o apaga).
     */
    public function reserveKeepingName(string $dir, string $nome): string
    {
        $info = pathinfo($nome);
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        for ($n = 0; $n < 100000; $n++) {
            $final = $this->candidate($info['filename'], $ext, $n);
            $h = @fopen($dir . $final, 'x');
            if ($h !== false) {
                fclose($h);
                return $final;
            }
        }
        throw new \RuntimeException('Não foi possível reservar um nome livre em ' . $dir);
    }

    /** @return array [string $baseLimpa, string $extComPonto] */
    private function splitName(string $nome): array
    {
        $info = pathinfo($nome);
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        $base = $this->cleanName($info['filename']);
        return [$base === '' ? 'arquivo' : $base, $ext];
    }

    private function candidate(string $base, string $ext, int $n): string
    {
        return $n === 0 ? $base . $ext : $base . ' (' . $n . ')' . $ext;
    }

    public function isHidden(string $name): bool
    {
        return in_array($name, $this->hidden, true);
    }

    /**
     * Lista uma pasta, sem itens ocultos, em ordem natural.
     *
     * @return array [string[] $dirs, string[] $files]
     */
    public function listDir(string $absDir): array
    {
        $dirs = [];
        $files = [];
        foreach (scandir($absDir) as $item) {
            if ($this->isHidden($item)) {
                continue;
            }
            if (is_dir($absDir . $item)) {
                $dirs[] = $item;
            } else {
                $files[] = $item;
            }
        }
        natcasesort($dirs);
        natcasesort($files);
        return [array_values($dirs), array_values($files)];
    }

    /**
     * $path é $ancestor ou está dentro dele? Caminhos lógicos relativos (sem "/" nas pontas);
     * compara por segmento ("a" não contém "ab") e ignora a caixa no Windows.
     */
    public function isSameOrInside(string $path, string $ancestor): bool
    {
        return $this->startsWith($path . '/', $ancestor . '/');
    }

    /** Tem alguma subpasta (não oculta)? Para ao achar a primeira: serve à árvore lateral. */
    public function hasSubdirs(string $absDir): bool
    {
        $h = @opendir($absDir);
        if ($h === false) {
            return false;
        }
        try {
            while (($entry = readdir($h)) !== false) {
                if (!$this->isHidden($entry) && is_dir($absDir . $entry)) {
                    return true;
                }
            }
        } finally {
            closedir($h);
        }
        return false;
    }

    /** Caminho usado como chave em files_meta (".lixeira/..." na lixeira). */
    public function metaPath(string $area, string $sub, string $item = ''): string
    {
        $path = trim($sub . '/' . $item, '/');
        return $area === self::AREA_TRASH ? trim('.lixeira/' . $path, '/') : $path;
    }

    /** Remove arquivo ou pasta (recursivo; não segue symlinks). */
    public function delete(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) as $obj) {
                if ($obj !== '.' && $obj !== '..') {
                    $this->delete($path . '/' . $obj);
                }
            }
            rmdir($path);
        } elseif (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }

    private function startsWith(string $haystack, string $prefix): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') { // Windows: sistema de arquivos ignora caixa
            return strncasecmp($haystack, $prefix, strlen($prefix)) === 0;
        }
        return strncmp($haystack, $prefix, strlen($prefix)) === 0;
    }
}
