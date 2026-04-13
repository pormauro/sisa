$ErrorActionPreference = 'Continue'

$workspace = Split-Path -Parent $PSScriptRoot
$script:results = @()

function Invoke-QAStep {
    param(
        [string]$Name,
        [string]$WorkingDirectory,
        [string]$Command
    )

    Write-Host "`n==> $Name"
    Push-Location $WorkingDirectory
    try {
        $rawOutput = @(& powershell -NoProfile -Command $Command 2>&1)
        $exitCode = $LASTEXITCODE
        if ($null -eq $exitCode) {
            $exitCode = 0
        }
    } finally {
        Pop-Location
    }

    $filteredOutput = $rawOutput
    if ($Name -eq 'Backend PHPUnit') {
        $filteredOutput = @($rawOutput | Where-Object {
            $_ -notmatch 'Error de conexi.n:'
        })
    }

    if ($Name -eq 'Backend PHPUnit' -and $exitCode -eq 0) {
        # Keep the shared baseline quiet when PHPUnit succeeds but an old setup message leaks to stdout.
    } else {
        $filteredOutput | ForEach-Object {
            $_
        }
    }

    $status = if ($exitCode -eq 0) { 'PASS' } else { 'FAIL' }
    $script:results += [pscustomobject]@{
        Step = $Name
        Status = $status
        ExitCode = $exitCode
    }
}

Invoke-QAStep -Name 'Backend PHPUnit' -WorkingDirectory (Join-Path $workspace 'sisa.api') -Command '.\vendor\bin\phpunit'
Invoke-QAStep -Name 'Frontend lint' -WorkingDirectory (Join-Path $workspace 'sisa.ui') -Command 'npm run lint'
Invoke-QAStep -Name 'Frontend cache guard' -WorkingDirectory (Join-Path $workspace 'sisa.ui') -Command 'npm run check:cache'
Invoke-QAStep -Name 'Frontend sync smoke' -WorkingDirectory (Join-Path $workspace 'sisa.ui') -Command 'npm run check:sync-smoke'

Write-Host "`n==> Summary"
$script:results | ForEach-Object {
    Write-Host ("{0,-24} {1} (exit {2})" -f $_.Step, $_.Status, $_.ExitCode)
}

$failed = @($script:results | Where-Object { $_.Status -eq 'FAIL' })
if ($failed.Count -gt 0) {
    exit 1
}

exit 0
