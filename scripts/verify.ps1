param([string]$Php = 'C:\dev\sdk\php-8.4.27\php.exe')

$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent $PSScriptRoot
$previousMode = $env:LAB_VERIFY_ONLY
Push-Location $repo
try {
    $env:LAB_VERIFY_ONLY = '1'
    & $Php bin/console cache:clear --env=test --no-debug
    if ($LASTEXITCODE -ne 0) { throw 'Test container refresh failed.' }
    & $Php bin/console lint:yaml config --env=test --no-debug
    if ($LASTEXITCODE -ne 0) { throw 'Configuration syntax failed.' }
    & $Php bin/console lint:container --env=test --no-debug
    if ($LASTEXITCODE -ne 0) { throw 'Container verification failed.' }
    & $Php bin/console doctrine:schema:validate --skip-sync --env=test --no-debug
    if ($LASTEXITCODE -ne 0) { throw 'Doctrine mapping validation failed.' }
    & $Php bin/phpunit
    if ($LASTEXITCODE -ne 0) { throw 'PHPUnit failed.' }
    foreach ($lab in @('01-http-kernel', '02-modern-php', '03-container', '04-validation', '05-doctrine', '06-security', '07-messenger', '08-console-and-tests')) {
        & $Php "scripts/labs/$lab.php"
        if ($LASTEXITCODE -ne 0) { throw "$lab failed." }
    }
    $phpFiles = Get-ChildItem src,scripts,tests,migrations -Filter '*.php' -File -Recurse
    foreach ($phpFile in $phpFiles) {
        & $Php -l $phpFile.FullName | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "PHP syntax failed: $($phpFile.FullName)" }
    }
    & $Php bin/console cache:warmup --env=prod --no-debug
    if ($LASTEXITCODE -ne 0) { throw 'Production container warmup failed.' }
    Write-Output "Verified PHPUnit, eight labs, configuration, mappings, and $($phpFiles.Count) PHP source files."
} finally {
    $env:LAB_VERIFY_ONLY = $previousMode
    Pop-Location
}
