$ErrorActionPreference = 'Stop'
$projectDir = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$propertiesPath = Join-Path $PSScriptRoot 'maven-wrapper.properties'
$line = Get-Content $propertiesPath | Where-Object { $_ -match '^distributionUrl=' } | Select-Object -First 1
if (-not $line) { throw 'Falta distributionUrl en maven-wrapper.properties' }
$url = $line.Substring('distributionUrl='.Length).Replace('\:', ':')
$installDir = Join-Path $projectDir '.mvn\apache-maven-3.9.11'
if (Test-Path (Join-Path $installDir 'bin\mvn.cmd')) { exit 0 }
$zipFile = Join-Path $env:TEMP 'minimarket-apache-maven-3.9.11.zip'
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Write-Host "Descargando Maven desde $url"
    Invoke-WebRequest -Uri $url -OutFile $zipFile -UseBasicParsing
    Expand-Archive -LiteralPath $zipFile -DestinationPath (Join-Path $projectDir '.mvn') -Force
    if (-not (Test-Path (Join-Path $installDir 'bin\mvn.cmd'))) { throw 'No se pudo instalar Maven en .mvn' }
} finally {
    if (Test-Path $zipFile) { Remove-Item $zipFile -Force }
}
