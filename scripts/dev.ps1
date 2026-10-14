param([ValidateSet('prepare','build','install','assets','up','migrate','test','check','down','logs')][string]$Action = 'check')
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)
$dockerCommand = Get-Command docker -ErrorAction SilentlyContinue
if ($dockerCommand) { $dockerBin = Split-Path -Parent $dockerCommand.Source }
else {
    $dockerBin = Join-Path $env:LOCALAPPDATA 'Programs\DockerDesktop\resources\bin'
    if (-not (Test-Path (Join-Path $dockerBin 'docker.exe'))) { $dockerBin = 'C:\Program Files\Docker\Docker\resources\bin' }
}
$env:PATH = $dockerBin + ';' + $env:PATH
function Invoke-Compose { docker compose -f compose.yaml -f compose.development.yaml @args; if ($LASTEXITCODE -ne 0) { throw 'Comando Compose falhou.' } }
function New-RandomBytes { $bytes = New-Object byte[] 32; $rng = [Security.Cryptography.RandomNumberGenerator]::Create(); try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }; return ,$bytes }
function New-Secret { ([BitConverter]::ToString((New-RandomBytes)) -replace '-', '').ToLowerInvariant() }
switch ($Action) {
    'prepare' {
        if (-not (Test-Path '.env')) {
            $values = Get-Content 'docker/images.env'
            $values += 'APP_KEY=base64:' + [Convert]::ToBase64String((New-RandomBytes))
            foreach ($name in @('DB_ADMIN_PASSWORD','DB_MIGRATION_PASSWORD','DB_RUNTIME_PASSWORD','TEST_ADMIN_PASSWORD','TEST_MIGRATION_PASSWORD','TEST_RUNTIME_PASSWORD')) { $values += $name + '=' + (New-Secret) }
            [IO.File]::WriteAllText((Join-Path (Get-Location) '.env'), ($values -join "`n") + "`n", (New-Object Text.UTF8Encoding $false))
        }
        foreach ($lock in @('composer.lock','package-lock.json')) { if (-not (Test-Path $lock)) { [IO.File]::WriteAllText((Join-Path (Get-Location) $lock), "{}`n", (New-Object Text.UTF8Encoding $false)) } }
        Invoke-Compose config --quiet
    }
    'build' { Invoke-Compose build app }
    'install' {
        $lock = Get-Content composer.lock -Raw | ConvertFrom-Json
        if ($null -eq $lock.packages) { Invoke-Compose run --rm --no-deps app composer update --no-interaction --prefer-dist }
        else { Invoke-Compose run --rm --no-deps app composer install --no-interaction --prefer-dist }
    }
    'assets' {
        $lock = Get-Content package-lock.json -Raw | ConvertFrom-Json
        if ($null -eq $lock.lockfileVersion) { Invoke-Compose run --rm --no-deps node npm install --ignore-scripts --no-audit }
        else { Invoke-Compose run --rm --no-deps node npm ci --ignore-scripts --no-audit }
        Invoke-Compose run --rm --no-deps node npm run build
    }
    'migrate' { Invoke-Compose run --rm app php artisan migrate --database=pgsql_migrate --force }
    'up' { Invoke-Compose up -d --wait web }
    'test' { Invoke-Compose run --rm test }
    'check' { Invoke-Compose config --quiet; Invoke-Compose ps }
    'down' { Invoke-Compose down }
    'logs' { Invoke-Compose logs --tail 100 app web }
}
