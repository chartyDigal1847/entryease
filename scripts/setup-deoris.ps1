# EntryEase + DEORIS portal integration setup (Windows / XAMPP)
$ErrorActionPreference = "Stop"
Set-Location (Split-Path $PSScriptRoot -Parent)

Write-Host "Installing Composer dependencies..." -ForegroundColor Cyan
composer install

Write-Host "Running migrations..." -ForegroundColor Cyan
php artisan migrate --force

Write-Host "Clearing config cache..." -ForegroundColor Cyan
php artisan config:clear

Write-Host "DEORIS integration check..." -ForegroundColor Cyan
php artisan deoris:doctor

Write-Host ""
Write-Host "Next steps:" -ForegroundColor Green
Write-Host "  1. Set ENTRYEASE_EVENT_SECRET in .env (must match C:\xampp\htdocs\DEORIS\.env)"
Write-Host "  2. Start DEORIS portal: queue worker + reverb + deoris:events:listen"
Write-Host "  3. Start EntryEase: php artisan queue:work --queue=default,deoris-events"
Write-Host "  4. Optional: php artisan deoris:listen-portal (inbound from other modules)"
