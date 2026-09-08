# =============================================================
#  OA Deployment Package Builder (Windows PowerShell 5.1+)
#
#  What it does:
#    1. Install pc-api composer dependencies (production)
#    2. Build pc-web (vite build)
#    3. Package backend (with vendor) + frontend (with dist)
#       + oa-baremetal scripts into one tar.gz
#
#  Output: dist/oa-deploy-v1.2.8-YYYYMMDD.tar.gz
#
#  Usage: Right-click -> "Run with PowerShell" (from oa-baremetal/scripts/)
#   OR:   powershell -ExecutionPolicy Bypass -File build-package.ps1
# =============================================================

$ErrorActionPreference = "Stop"
Set-Location (Split-Path $PSScriptRoot -Parent)  # chdir to oa-baremetal/

$VERSION = (Get-Content "VERSION" -ErrorAction SilentlyContinue)
if (-not $VERSION) { $VERSION = "1.2.8" }
$VERSION = $VERSION.Trim()

$DATE = Get-Date -Format "yyyyMMdd"
$OUT_NAME = "oa-deploy-v$VERSION-$DATE"
$OUT_DIR  = "dist"
$STAGE    = "$OUT_DIR\$OUT_NAME"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  OA Deployment Package Builder v$VERSION" -ForegroundColor Cyan
Write-Host "  Output: $OUT_DIR\$OUT_NAME.tar.gz" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# ---- 0. Tool detection ----
Write-Host "[0] Checking required tools..." -ForegroundColor Cyan
$tools = @(
    @{ name = "php";      cmd = "php --version" },
    @{ name = "composer"; cmd = "composer --version" },
    @{ name = "node";     cmd = "node --version" },
    @{ name = "npm";      cmd = "npm --version" },
    @{ name = "tar";      cmd = "tar --version" }
)
foreach ($t in $tools) {
    $name = $t.name
    $cmd = $t.cmd
    try {
        $output = cmd /c "$cmd 2>&1"
        $line = ($output | Select-Object -First 1)
        Write-Host "  [OK] $name : $line" -ForegroundColor Green
    } catch {
        Write-Host "  [FAIL] $name not found: $cmd" -ForegroundColor Red
        Write-Host "  Required: PHP 8.3+ / Composer 2 / Node 22 / Git-Bash tar" -ForegroundColor Yellow
        exit 1
    }
}
Write-Host ""

# ---- 1. Clean + prep stage dir ----
Write-Host "[1/5] Preparing stage directory..." -ForegroundColor Cyan
if (Test-Path $OUT_DIR) { Remove-Item $OUT_DIR -Recurse -Force }
New-Item -ItemType Directory -Path $STAGE | Out-Null

# ---- 2. Backend pc-api (with vendor) ----
Write-Host "[2/5] Copying pc-api..." -ForegroundColor Cyan
$dst = "$STAGE\pc-api"
New-Item -ItemType Directory -Path $dst -Force | Out-Null

$robocopyArgs = @(
    "..\pc-api", $dst,
    "/E",
    "/XD", "node_modules", ".git", "tests", "storage\framework\cache\data", "storage\logs",
    "/XF", ".env", ".env.example", "phpunit.xml"
)
$robolog = & robocopy @robocopyArgs 2>&1
if ($LASTEXITCODE -ge 8) {
    Write-Host "  [FAIL] robocopy exit=$LASTEXITCODE" -ForegroundColor Red
    exit 1
}

Write-Host "  [OK] pc-api copied" -ForegroundColor Green

# Install composer deps (production)
Write-Host "[2/5] Running composer install --no-dev..." -ForegroundColor Cyan
Push-Location $dst
$composerLog = & composer install --no-dev --optimize-autoloader --no-interaction --no-progress 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "  [FAIL] composer install failed" -ForegroundColor Red
    $composerLog | Select-Object -Last 20 | ForEach-Object { Write-Host "    $_" }
    Pop-Location
    exit 1
}
Pop-Location
Write-Host "  [OK] vendor installed" -ForegroundColor Green

# ---- 3. Frontend pc-web (vite build) ----
Write-Host "[3/5] Building pc-web (vite)..." -ForegroundColor Cyan
$webSrc = "..\pc-web"
$webDst = "$STAGE\pc-web-dist"

if (-not (Test-Path $webSrc)) {
    Write-Host "  [WARN] $webSrc not found, skipping frontend" -ForegroundColor Yellow
} else {
    Push-Location $webSrc
    if (-not (Test-Path "node_modules")) {
        Write-Host "  npm install..." -ForegroundColor Yellow
        & npm install --no-audit --no-fund --silent 2>&1 | Out-Null
    }
    Write-Host "  npm run build..." -ForegroundColor Yellow
    $buildLog = & npm run build 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host "  [FAIL] vite build failed" -ForegroundColor Red
        $buildLog | Select-Object -Last 20 | ForEach-Object { Write-Host "    $_" }
        Pop-Location
        exit 1
    }
    Pop-Location
    if (Test-Path "$webSrc\dist") {
        Copy-Item "$webSrc\dist" $webDst -Recurse -Force
        $size = (Get-ChildItem $webDst -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB
        Write-Host "  [OK] dist copied: $([math]::Round($size, 2)) MB" -ForegroundColor Green
    } else {
        Write-Host "  [FAIL] vite did not generate dist" -ForegroundColor Red
        exit 1
    }
}

# ---- 4. Copy oa-baremetal itself ----
Write-Host "[4/5] Copying oa-baremetal scripts..." -ForegroundColor Cyan
$baremetalName = Split-Path $PSScriptRoot -Leaf
& robocopy "..\$baremetalName" "$STAGE\$baremetalName" `
    "/E", `
    "/XD", "dist", ".git" `
    "/XF" 2>&1 | Out-Null

# VERSION at root
Copy-Item "VERSION" "$STAGE\VERSION" -Force

# ---- 5. tar.gz ----
Write-Host "[5/5] Compressing to tar.gz..." -ForegroundColor Cyan
$tarPath = "$OUT_DIR\$OUT_NAME.tar.gz"
Push-Location $OUT_DIR
& tar -czf "$OUT_NAME.tar.gz" "$OUT_NAME"
if ($LASTEXITCODE -ne 0) {
    Write-Host "  [FAIL] tar failed" -ForegroundColor Red
    Pop-Location
    exit 1
}
Pop-Location

$size = (Get-Item $tarPath).Length / 1MB
Write-Host ""
Write-Host "==========================================" -ForegroundColor Green
Write-Host "  Package built successfully" -ForegroundColor Green
Write-Host "  File: $tarPath" -ForegroundColor Green
Write-Host "  Size: $([math]::Round($size, 2)) MB" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "  1. scp $tarPath user@SERVER_IP:/tmp/" -ForegroundColor White
Write-Host "  2. ssh user@SERVER_IP" -ForegroundColor White
Write-Host "  3. cd /tmp && tar xzf oa-deploy-*.tar.gz" -ForegroundColor White
Write-Host "  4. cd $OUT_NAME && sudo bash deploy.sh" -ForegroundColor White
Write-Host ""
