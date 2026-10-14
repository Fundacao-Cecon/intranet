<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class UploadValidator
{
    public function __construct(private DocumentNames $names) {}

    public function beforeStaging(UploadedFile $file): string
    {
        if (! $file->isValid()) {
            $this->reject('Falha na transmissão do arquivo.');
        }
        if ($file->getSize() > config('documents.max_file_bytes')) {
            $this->reject('Arquivo excede o limite de 20 MiB.');
        }
        $name = $this->names->validate($file->getClientOriginalName());
        if (! array_key_exists(strtolower(pathinfo($name, PATHINFO_EXTENSION)), config('documents.types'))) {
            $this->reject('Extensão de arquivo não permitida.');
        }

        return $name;
    }

    public function staged(string $stage, string $name): array
    {
        $path = Storage::disk('local')->path($stage);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! in_array($mime, config('documents.types')[$extension], true)) {
            $this->reject('O tipo do conteúdo não corresponde à extensão permitida.');
        }
        $markers = ['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml', 'odt' => 'content.xml', 'ods' => 'content.xml'];
        if (isset($markers[$extension])) {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                $this->reject('Documento compactado inválido.');
            }
            try {
                if ($zip->numFiles > 10000 || $zip->locateName($markers[$extension]) === false) {
                    $this->reject('Estrutura do documento inválida.');
                }
                if (in_array($extension, ['odt', 'ods'], true)) {
                    $entry = $zip->statName('mimetype');
                    $expected = $extension === 'odt' ? 'application/vnd.oasis.opendocument.text' : 'application/vnd.oasis.opendocument.spreadsheet';
                    if (! $entry || $entry['size'] > 100 || $zip->getFromName('mimetype') !== $expected) {
                        $this->reject('Estrutura do documento inválida.');
                    }
                }
            } finally {
                $zip->close();
            }
        }

        return ['media_type' => $mime, 'size_bytes' => filesize($path), 'sha256' => hash_file('sha256', $path)];
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['arquivos' => $message]);
    }
}
