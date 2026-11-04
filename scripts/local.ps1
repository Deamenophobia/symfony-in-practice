param(
    [ValidateSet('Start', 'Stop')][string]$Action = 'Start',
    [string]$Php = 'C:\dev\sdk\php-8.4.27\php.exe'
)

$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent $PSScriptRoot
$runtime = Join-Path $repo '.runtime'
$pidFile = Join-Path $runtime 'http-process-id'
New-Item -ItemType Directory -Path $runtime -Force | Out-Null
$server = $null
if (Test-Path -LiteralPath $pidFile) {
    $server = Get-CimInstance Win32_Process -Filter "ProcessId=$(Get-Content -LiteralPath $pidFile)" |
        Where-Object { $_.Name -eq 'php.exe' -and $_.CommandLine.Contains($repo) }
}
if ($Action -eq 'Stop') {
    if ($server) { Stop-Process -Id $server.ProcessId }
    Write-Output 'Stopped the Symfony loopback API.'
    exit
}
if (-not $server) {
    if (Get-NetTCPConnection -LocalPort 8089 -State Listen -ErrorAction SilentlyContinue) {
        throw 'Port 8089 is occupied.'
    }
    $server = Start-Process -FilePath $Php -ArgumentList @('-S', '127.0.0.1:8089', '-t', ('"' + $repo + '\public"'), ('"' + $repo + '\public\index.php"')) `
        -WorkingDirectory $repo -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput "$runtime\http.log" -RedirectStandardError "$runtime\http-error.log"
    $server.Id | Set-Content -LiteralPath $pidFile
}
Write-Output 'Symfony API: http://127.0.0.1:8089. MySQL must already be running; use scripts/reset.php to seed the disposable database.'
