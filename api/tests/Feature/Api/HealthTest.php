<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

test('health responde ok com o banco de pé, sem exigir login', function () {
    test()->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'checks' => ['database' => 'ok']])
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('health não expõe versão, caminhos nem usuários', function () {
    $corpo = test()->getJson('/api/v1/health')->getContent();

    expect($corpo)->not->toContain('Laravel')->not->toContain('.db')->not->toContain('username')->not->toContain('PHP');
});

test('sem a tabela users do esquema adotado, health devolve 503 degraded', function () {
    DB::statement('DROP TABLE users');

    test()->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertExactJson(['status' => 'degraded', 'checks' => ['database' => 'falha']]);
});

test('o log técnico sai em JSON, uma linha por evento', function () {
    $dir = sys_get_temp_dir().'/logs-'.bin2hex(random_bytes(4));
    config(['logging.channels.json.path' => $dir.'/app.log']);

    Log::channel('json')->warning('upload lento', ['arquivo' => 'relatório.pdf', 'ms' => 4200]);

    $arquivo = glob($dir.'/app-*.log')[0];
    $linhas = array_values(array_filter(explode("\n", file_get_contents($arquivo))));
    File::deleteDirectory($dir);

    expect($linhas)->toHaveCount(1);
    $evento = json_decode($linhas[0], true, flags: JSON_THROW_ON_ERROR);
    expect($evento['message'])->toBe('upload lento')
        ->and($evento['level_name'])->toBe('WARNING')
        ->and($evento['context'])->toBe(['arquivo' => 'relatório.pdf', 'ms' => 4200])
        ->and($evento)->toHaveKey('datetime');
});

test('o canal json é o padrão do exemplo de ambiente', function () {
    expect(file_get_contents(base_path('.env.example')))->toContain('LOG_CHANNEL=json');
});
