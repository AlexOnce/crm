# Script de empaquetado para despliegue en cPanel
Write-Host "================================================" -ForegroundColor Cyan
Write-Host "   PREPARANDO PAQUETE PARA DESPLIEGUE CPANEL    " -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan

$baseDir = Get-Location
$zipOutput = Join-Path $baseDir "crm_cpanel_deploy.zip"
$tempDir = Join-Path $baseDir "temp_deploy_cpanel"

if (Test-Path $tempDir) {
    Remove-Item -Recurse -Force $tempDir
}
if (Test-Path $zipOutput) {
    Remove-Item -Force $zipOutput
}

Write-Host "`n1. Compilando frontend para produccion..." -ForegroundColor Yellow
npm run build

Write-Host "`n2. Limpiando caches de Laravel..." -ForegroundColor Yellow
php artisan optimize:clear

Write-Host "`n3. Copiando archivos a carpeta temporal..." -ForegroundColor Yellow
New-Item -ItemType Directory -Path $tempDir | Out-Null

$includeDirs = @("app", "bootstrap", "config", "database", "public", "resources", "routes", "vendor")
foreach ($dir in $includeDirs) {
    if (Test-Path $dir) {
        Write-Host " - Copiando $dir..." -ForegroundColor Gray
        Copy-Item -Path $dir -Destination (Join-Path $tempDir $dir) -Recurse -Force
    }
}

# Crear estructura limpia de storage
Write-Host " - Creando estructura limpia de storage..." -ForegroundColor Gray
$storageDirs = @(
    "storage\app\public",
    "storage\framework\cache\data",
    "storage\framework\sessions",
    "storage\framework\testing",
    "storage\framework\views",
    "storage\logs"
)
foreach ($sDir in $storageDirs) {
    $fullSDir = Join-Path $tempDir $sDir
    New-Item -ItemType Directory -Path $fullSDir -Force | Out-Null
    New-Item -ItemType File -Path (Join-Path $fullSDir ".gitignore") -Force | Out-Null
}

# Copiar archivos individuales esenciales
$includeFiles = @(
    "artisan",
    "composer.json",
    "composer.lock",
    ".htaccess",
    ".env.production"
)
foreach ($file in $includeFiles) {
    if (Test-Path $file) {
        Write-Host " - Copiando $file..." -ForegroundColor Gray
        Copy-Item -Path $file -Destination (Join-Path $tempDir $file) -Force
    }
}

# Renombrar .env.production a .env.cpanel en el paquete temporal para conveniencia
if (Test-Path (Join-Path $tempDir ".env.production")) {
    Copy-Item -Path (Join-Path $tempDir ".env.production") -Destination (Join-Path $tempDir ".env") -Force
}

Write-Host "`n4. Generando archivo ZIP comprimido (crm_cpanel_deploy.zip)..." -ForegroundColor Yellow
Compress-Archive -Path "$tempDir\*" -DestinationPath $zipOutput -CompressionLevel Optimal

Write-Host "`n5. Limpiando temporales..." -ForegroundColor Yellow
Remove-Item -Recurse -Force $tempDir

$zipSize = (Get-Item $zipOutput).Length / 1MB
Write-Host "`n================================================" -ForegroundColor Green
Write-Host " PAQUETE GENERADO CON EXITO!" -ForegroundColor Green
Write-Host " Archivo: crm_cpanel_deploy.zip" -ForegroundColor White
Write-Host " Tamano: $([math]::Round($zipSize, 2)) MB" -ForegroundColor White
Write-Host " Ubicacion: $zipOutput" -ForegroundColor White
Write-Host "================================================" -ForegroundColor Green
