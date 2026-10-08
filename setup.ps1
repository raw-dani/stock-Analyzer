# US Stock Volume Analyzer - One-Click Setup
# Jalankan sekali: .\setup.ps1

$ErrorActionPreference = "Stop"
$proj = "D:\Yii2 Project\saham\stock-Analyzer"
Set-Location $proj

Write-Host "=== 1/6 Composer install ===" -ForegroundColor Cyan
composer install --ignore-platform-reqs --no-interaction --prefer-dist
if ($LASTEXITCODE -ne 0) { throw "composer install gagal" }

Write-Host "=== 2/6 Buat folder ===" -ForegroundColor Cyan
New-Item -ItemType Directory -Path "app/data" -Force | Out-Null
New-Item -ItemType Directory -Path "runtime/logs" -Force | Out-Null
New-Item -ItemType Directory -Path "runtime/queue" -Force | Out-Null
New-Item -ItemType Directory -Path "web/assets" -Force | Out-Null

Write-Host "=== 3/6 Buat .env (SQLite, sync queue) ===" -ForegroundColor Cyan
if (-not (Test-Path ".env")) {
    Copy-Item "deploy/.env.example" ".env"
}
@'
DB_DRIVER=sqlite
DB_DSN=sqlite:@app/data/stocks.db
DB_SCHEMA_CACHE=0
QUEUE_DRIVER=sync
'@ | Set-Content ".env"

Write-Host "=== 4/6 Perbaiki migrasi SQLite ===" -ForegroundColor Cyan
$mig = "migrations/m240921_000001_add_threshold_data_to_alert.php"
$content = Get-Content $mig -Raw
$content = $content -replace "        \$this->alterColumn\('\{\{%alert\}\}', 'condition_type', [^\n]*\n", ""
$content = $content -replace "        \$this->alterColumn\('\{\{%alert\}\}', 'condition_type', [^\n]*\n", ""
Set-Content $mig $content

Write-Host "=== 5/6 Migrasi database ===" -Foreground Cyan
php "yii" migrate/up --interactive=0
if ($LASTEXITCODE -ne 0) { throw "migrate gagal" }

Write-Host "=== 6/6 Seed admin user ===" -ForegroundColor Cyan
php "scripts/seed-admin.php"

Write-Host ""
Write-Host "SELESAI. Sekarang jalankan server:" -ForegroundColor Green
Write-Host "  php -S 127.0.0.1:8089 -t web" -ForegroundColor Yellow
Write-Host "  Buka: http://127.0.0.1:8089" -ForegroundColor Yellow