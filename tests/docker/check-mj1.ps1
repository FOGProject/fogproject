[CmdletBinding()]
param(
    [Parameter()]
    [string]$AgentRoot = (Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) '..\agent'),

    [Parameter()]
    [string]$ServerRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,

    [Parameter(Mandatory = $true)]
    [string]$OutputDir
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$AgentRoot = (Resolve-Path -LiteralPath $AgentRoot).Path
$ServerRoot = (Resolve-Path -LiteralPath $ServerRoot).Path
if (-not (Test-Path -LiteralPath (Join-Path $AgentRoot 'go.mod') -PathType Leaf)) {
    throw "AgentRoot is not a Go module: $AgentRoot"
}
$docker = Get-Command docker -ErrorAction SilentlyContinue
if (-not $docker) { throw 'docker CLI is required' }

$runId = [Guid]::NewGuid().ToString('N')
$runRoot = Join-Path ([System.IO.Path]::GetFullPath($OutputDir)) "mj1-$runId"
New-Item -ItemType Directory -Path $runRoot -Force | Out-Null
$networkName = "fog-mj1-$runId"
$dbContainer = "fog-mj1-db-$runId"
$php74Image = 'fog-mj1-php:7.4'
$php83Image = 'fog-mj1-php:8.3'
$goImage = 'golang:1.26.7-bookworm'
$rootPassword = [Guid]::NewGuid().ToString('N')
$createdNetwork = $false
$createdDb = $false
$failed = $false
$failureMessage = $null

function Invoke-DockerLogged {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string[]]$DockerArgs
    )
    $logPath = Join-Path $runRoot "$Name.log"
    $safeArgs = foreach ($arg in $DockerArgs) {
        if ($arg -match '^(MJ1_DB_PASSWORD|MARIADB_ROOT_PASSWORD)=') { "$($arg.Split('=')[0])=<redacted>" }
        elseif ($arg -match '^--password=') { '--password=<redacted>' }
        else { $arg }
    }
    "COMMAND: docker $($safeArgs -join ' ')" | Set-Content -LiteralPath $logPath -Encoding utf8
    & $docker.Source @DockerArgs 2>&1 | Tee-Object -FilePath $logPath -Append
    $code = $LASTEXITCODE
    if ($code -ne 0) { throw "$Name failed with exit code $code (see $logPath)" }
}

function Invoke-PhpCommand {
    param(
        [Parameter(Mandatory = $true)][string]$Image,
        [Parameter(Mandatory = $true)][string]$Label,
        [Parameter(Mandatory = $true)][string]$Script
    )
    $dockerArgs = @(
        'run', '--pull=never', '--rm', '--network', $networkName,
        '--mount', "type=bind,source=$ServerRoot,target=/source,readonly",
        '--mount', "type=bind,source=$runRoot,target=/out",
        '--env', "MJ1_DB_DSN=mysql:host=mj1-db;dbname=fog_mj1;charset=utf8mb4",
        '--env', 'MJ1_DB_USER=root',
        '--env', "MJ1_DB_PASSWORD=$rootPassword",
        '--env', 'MJ1_DB_ALLOW_RESET=1',
        $Image, 'sh', '-lc', "mkdir -p /workcopy/packages /workcopy/tests /workcopy/docs /workcopy/bin /workcopy/build && cp -a /source/packages/. /workcopy/packages/ && cp -a /source/tests/. /workcopy/tests/ && cp -a /source/docs/. /workcopy/docs/ && cp -a /source/bin/. /workcopy/bin/ && cp -a /source/build/. /workcopy/build/ && cd /workcopy && $Script"
    )
    Invoke-DockerLogged -Name $Label -DockerArgs $dockerArgs
}

try {
    Invoke-DockerLogged -Name 'docker-version' -DockerArgs @('version', '--format', '{{.Client.Version}} / {{.Server.Version}}')

    # This runner is deliberately offline. Require all base images up front and
    # never let Docker silently pull or refresh a runtime during a test run.
    $requiredImages = @(
        $php74Image,
        $php83Image,
        $goImage,
        'mariadb:11.4'
    )
    foreach ($image in $requiredImages) {
        Invoke-DockerLogged -Name ("cache-" + ($image -replace '[^a-zA-Z0-9]+', '-')) -DockerArgs @('image', 'inspect', '--format', '{{.Id}} {{json .RepoTags}}', $image)
    }

    foreach ($runtime in @(@{ Label = 'php74'; Image = $php74Image }, @{ Label = 'php83'; Image = $php83Image })) {
        Invoke-DockerLogged -Name "$($runtime.Label)-runtime" -DockerArgs @('run', '--pull=never', '--network=none', '--rm', $runtime.Image, 'php', '-v')
        Invoke-DockerLogged -Name "$($runtime.Label)-modules" -DockerArgs @('run', '--pull=never', '--network=none', '--rm', $runtime.Image, 'php', '-m')
    }

    $goScript = @'
set -eu
cd /agent
GOPROXY=off GOSUMDB=off go mod download
go test ./... -count=1
GOOS=windows GOARCH=amd64 go vet ./...
        CGO_ENABLED=0 GOOS=windows GOARCH=amd64 go build -trimpath -ldflags "-s -w -X main.Version=0.1.17-pr-mj1" -o /out/fog-agent.exe ./cmd/fog-agent
CGO_ENABLED=0 go test -c -o /out/fog-agent-linux-integration.test ./cmd/fog-agent
'@
    Invoke-DockerLogged -Name 'go-full-vet-build-integration-binary' -DockerArgs @(
        'run', '--pull=never', '--network=none', '--rm',
        '--mount', "type=bind,source=$AgentRoot,target=/agent,readonly",
        '--mount', "type=bind,source=$runRoot,target=/out",
        '--mount', 'type=volume,source=fog-mj1-gomod,target=/go/pkg/mod',
        $goImage, 'sh', '-c', $goScript
    )

    Invoke-DockerLogged -Name 'create-network' -DockerArgs @('network', 'create', '--internal', '--label', 'fog.task=mj1', '--label', "fog.run=$runId", $networkName)
    $createdNetwork = $true
    Invoke-DockerLogged -Name 'start-mariadb114' -DockerArgs @(
        'run', '--pull=never', '-d', '--name', $dbContainer, '--network', $networkName, '--network-alias', 'mj1-db',
        '--label', 'fog.task=mj1', '--label', "fog.run=$runId", '--env', 'MARIADB_DATABASE=fog_mj1',
        '--env', "MARIADB_ROOT_PASSWORD=$rootPassword", 'mariadb:11.4'
    )
    $createdDb = $true

    $ready = $false
    for ($i = 0; $i -lt 25; $i++) {
        & $docker.Source exec $dbContainer mariadb-admin ping --silent "--password=$rootPassword" 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 2
    }
    if (-not $ready) { throw "MariaDB 11.4 did not become ready (see start-mariadb114.log)" }

    $phpSuites = @(
        'agent-mj1.test.php',
        'agent-directory-task.test.php',
        'agent-directory-operation.test.php',
        'ldap-computer-record.test.php',
        'agent-directory-join.test.php',
        'agent-directory-placement.test.php',
        'agent-directory.test.php',
        'agent-principal.test.php',
        'agent-mj1-route-permissions.test.php',
        'agent-route-nouns.test.php',
        'openapi-route-coverage.test.php',
        'permission-actions-declared.test.php',
        'agent-facts.test.php',
        'agent-api-not-redirected-on-stale-schema.test.php',
        'host-ad-mj1.test.php',
        'gettext-literal-msgid.test.php',
        'gettext-output-is-utf8.test.php',
        'schema-manifest-consistent.test.php',
        'schema-upgrade-replay.test.php',
        'schema-gate.test.php'
    )
    foreach ($runtime in @(@{ Label = 'php74'; Image = $php74Image }, @{ Label = 'php83'; Image = $php83Image })) {
        foreach ($suite in $phpSuites) {
            $suitePath = Join-Path $ServerRoot "tests\$suite"
            if (-not (Test-Path -LiteralPath $suitePath -PathType Leaf)) { throw "Missing PHP suite: $suitePath" }
            Invoke-PhpCommand -Image $runtime.Image -Label "$($runtime.Label)-$($suite -replace '\.test\.php$','')" -Script "LANG=zh_CN.UTF-8 php tests/$suite"
        }

        foreach ($suite in @(
            'agent-mj1-db.test.php',
            'agent-directory-operation-db.test.php',
            'agent-directory-local-marker-db.test.php',
            'agent-mj1-migration.test.php'
        )) {
            $suitePath = Join-Path $ServerRoot "tests\$suite"
            if (-not (Test-Path -LiteralPath $suitePath -PathType Leaf)) { throw "Missing PHP database suite: $suitePath" }
            Invoke-PhpCommand -Image $runtime.Image -Label "$($runtime.Label)-$($suite -replace '\.test\.php$','')" -Script "php tests/$suite"
        }
        Invoke-PhpCommand -Image $runtime.Image -Label "$($runtime.Label)-japanese-ad-form-save" -Script 'msgfmt --check -o packages/web/management/languages/ja_JP.UTF-8/LC_MESSAGES/messages.mo packages/web/management/languages/ja_JP.UTF-8/LC_MESSAGES/messages.po && php tests/host-ad-ui-save.test.php'
    }

    # Execute the compiled Linux integration binary in each PHP runtime so its fixture uses that version.
    foreach ($runtime in @(@{ Label = 'php74'; Image = $php74Image }, @{ Label = 'php83'; Image = $php83Image })) {
        Invoke-PhpCommand -Image $runtime.Image -Label "$($runtime.Label)-go-php-contract-directory-operation-v2" -Script "cp /out/fog-agent-linux-integration.test /workcopy/fog-agent-linux-integration.test && chmod 700 /workcopy/fog-agent-linux-integration.test && MJ1_SERVER_ROOT=/workcopy ./fog-agent-linux-integration.test -test.run '^TestDirectoryOperationGoPHP' -test.v"
    }

}
catch {
    $failed = $true
    $failureMessage = $_.Exception.Message
}
finally {
    if ($createdDb) {
        & $docker.Source rm -fv $dbContainer 2>&1 | Tee-Object -FilePath (Join-Path $runRoot 'cleanup-container.log')
        if ($LASTEXITCODE -ne 0) { $failed = $true }
    }
    if ($createdNetwork) {
        & $docker.Source network rm $networkName 2>&1 | Tee-Object -FilePath (Join-Path $runRoot 'cleanup-network.log')
        if ($LASTEXITCODE -ne 0) { $failed = $true }
    }
}

if ($failed) {
    "FAIL: $failureMessage`r`nEvidence: $runRoot" | Tee-Object -FilePath (Join-Path $runRoot 'summary.txt')
    exit 1
}
"PASS: MJ-1 checks completed. Evidence: $runRoot" | Tee-Object -FilePath (Join-Path $runRoot 'summary.txt')
exit 0
