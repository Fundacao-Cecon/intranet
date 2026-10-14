<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PrivateFileReader
{
    private const INLINE = [
        'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'mp4' => 'video/mp4', 'txt' => 'text/plain',
    ];

    public function __construct(private AuditLogger $audit) {}

    public function respond(Request $request, Item $item, bool $download, bool $thumbnail): StreamedResponse
    {
        $key = $item->storage_key;
        abort_unless(is_string($key) && preg_match('#^documents/[A-Za-z0-9_./-]+$#D', $key)
            && ! in_array('..', explode('/', $key), true) && ! in_array('.', explode('/', $key), true), 404);
        $disk = Storage::disk('local');
        $privateRoot = realpath($disk->path(''));
        $root = realpath($disk->path('documents'));
        $path = realpath($disk->path($key));
        if (! $privateRoot || $root !== $privateRoot.DIRECTORY_SEPARATOR.'documents'
            || ! $path || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            $this->failure($request, $item, 'missing_or_unsafe_file');
            abort(404, 'Arquivo indisponível.');
        }
        $handle = null;
        try {
            $handle = fopen($path, 'rb');
            if (! is_resource($handle)) {
                throw new \RuntimeException('Arquivo indisponível.');
            }
            $size = fstat($handle)['size'];
            $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
            $extension = strtolower(pathinfo($item->name, PATHINFO_EXTENSION));
            $inline = isset(self::INLINE[$extension]) && self::INLINE[$extension] === $detected;
            if ($thumbnail && (! $inline || ! str_starts_with($detected, 'image/'))) {
                fclose($handle);
                abort(404);
            }
            $type = $inline ? $detected : 'application/octet-stream';
            if ($type === 'text/plain') {
                $type .= '; charset=UTF-8';
            }
            $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '_', $item->name);
            $fallback = str_replace('%', '_', Str::ascii($name));
            $headers = [
                'Content-Type' => $type,
                'Content-Disposition' => HeaderUtils::makeDisposition($download || ! $inline ? 'attachment' : 'inline', $name, $fallback ?: 'documento'),
                'Content-Length' => (string) $size, 'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => $type === 'application/pdf'
                    ? "default-src 'none'; object-src 'self'; frame-src 'self'; frame-ancestors 'self'"
                    : "sandbox allow-downloads; default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; frame-ancestors 'self'",
            ];
            $start = 0;
            $length = $size;
            $status = 200;
            if ($range = $request->header('Range')) {
                if (! preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $matches) || $size === 0 || ($matches[1] === '' && $matches[2] === '')) {
                    fclose($handle);
                    abort(416, 'Intervalo inválido.', ['Content-Range' => 'bytes */'.$size]);
                }
                $start = $matches[1] === '' ? max(0, $size - (int) $matches[2]) : (int) $matches[1];
                $end = $matches[1] === '' || $matches[2] === '' ? $size - 1 : min((int) $matches[2], $size - 1);
                if ($start > $end || $start >= $size || ($matches[1] === '' && (int) $matches[2] === 0)) {
                    fclose($handle);
                    abort(416, 'Intervalo inválido.', ['Content-Range' => 'bytes */'.$size]);
                }
                $length = $end - $start + 1;
                $status = 206;
                $headers['Content-Range'] = "bytes $start-$end/$size";
                $headers['Content-Length'] = (string) $length;
            }
            $action = $download ? 'DOWNLOAD_STARTED' : ($thumbnail ? 'THUMBNAIL_STARTED' : 'VIEW_STARTED');
            $this->audit->record($action, $item->name, 'success', $request->user(), $item, ip: $request->ip());

            return new StreamedResponse(function () use ($handle, $start, $length, $request) {
                try {
                    if ($request->isMethod('HEAD')) {
                        return;
                    }
                    fseek($handle, $start);
                    $remaining = $length;
                    while ($remaining > 0 && ! feof($handle) && ! connection_aborted()) {
                        $chunk = fread($handle, min(65536, $remaining));
                        if ($chunk === false || $chunk === '') {
                            break;
                        }
                        echo $chunk;
                        $remaining -= strlen($chunk);
                    }
                } finally {
                    fclose($handle);
                }
            }, $status, $headers);
        } catch (HttpException $exception) {
            throw $exception;
        } catch (Throwable) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            $this->failure($request, $item, 'read_unavailable');
            abort(503, 'Arquivo temporariamente indisponível.');
        }
    }

    private function failure(Request $request, Item $item, string $reason): void
    {
        Log::warning('Leitura privada indisponível.', ['item_id' => $item->id, 'reason_code' => $reason]);
        try {
            $this->audit->record('READ_FAILED', $item->name, 'failure', $request->user(), $item, ['reason_code' => $reason], $request->ip());
        } catch (Throwable) {
            Log::warning('Registro de auditoria indisponível.', ['item_id' => $item->id]);
        }
    }
}
