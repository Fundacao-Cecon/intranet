<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PrepareDemo extends Command
{
    protected $signature = 'intranet:demo';

    protected $description = 'Cria demonstração fictícia somente no banco local, com senhas aleatórias.';

    public function handle(): int
    {
        if (posix_geteuid() === 0) {
            $this->error('Execute a demonstração com --user www-data para manter permissões do storage.');

            return self::FAILURE;
        }
        if (! app()->environment('local') || config('database.connections.pgsql.database') !== 'intranet_dev') {
            $this->error('Demonstração permitida somente no ambiente local intranet_dev.');

            return self::FAILURE;
        }
        if (User::whereIn('username', ['demo.user', 'demo.admin'])->exists()) {
            $this->error('Contas de demonstração já existem; nenhuma senha ou dado foi sobrescrito.');

            return self::FAILURE;
        }
        $keys = [];
        try {
            $result = DB::transaction(function () use (&$keys) {
                $accounts = [];
                foreach (['user', 'admin'] as $role) {
                    $password = Str::random(24);
                    $accounts[] = ['username' => 'demo.'.$role, 'password' => $password, 'role' => $role];
                    $user = User::create(['username' => 'demo.'.$role, 'password_hash' => $password, 'role' => $role]);
                }
                $folder = Item::create(['kind' => 'folder', 'name' => 'Demonstração — dados fictícios', 'created_by_id' => $user->id, 'created_by_snapshot' => $user->username]);
                $subfolder = Item::create(['kind' => 'folder', 'name' => 'Orientações', 'parent_id' => $folder->id, 'created_by_snapshot' => 'Equipe fictícia']);
                $txt = "INTRANET FCECON — DEMONSTRAÇÃO\n\nEste documento é fictício e foi criado para validar consulta e download.\nNão contém informação institucional ou pessoal.\n";
                $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=');
                foreach ([['Guia de consulta.txt', $txt, 'text/plain', $folder->id], ['Exemplo.pdf', $this->pdf(), 'application/pdf', $folder->id], ['Imagem de exemplo.png', $png, 'image/png', $folder->id], ['Leia primeiro.txt', $txt, 'text/plain', $subfolder->id]] as [$name, $bytes, $mime, $parent]) {
                    $key = 'documents/'.Str::uuid().'.'.pathinfo($name, PATHINFO_EXTENSION);
                    $keys[] = $key;
                    Storage::disk('local')->put($key, $bytes);
                    Item::create(['kind' => 'file', 'name' => $name, 'parent_id' => $parent, 'storage_key' => $key, 'size_bytes' => strlen($bytes), 'media_type' => $mime, 'created_by_id' => $user->id, 'created_by_snapshot' => $user->username]);
                }

                return ['accounts' => $accounts, 'folder_id' => $folder->id];
            });
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            foreach ($keys as $key) {
                Storage::disk('local')->delete($key);
            }
            $this->error('Não foi possível preparar a demonstração.');

            return self::FAILURE;
        }
    }

    private function pdf(): string
    {
        $text = 'BT /F1 20 Tf 60 760 Td (FCECON - DEMONSTRACAO) Tj 0 -40 Td /F1 12 Tf (Documento ficticio para validar a visualizacao privada.) Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Length '.strlen($text)." >>\nstream\n".$text."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
