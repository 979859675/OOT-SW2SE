param([string]$WebView2Package)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$source = $PSScriptRoot
$build = Join-Path $source '.build'
$dist = Join-Path $source 'dist'
$sdkVersion = '1.0.4314.35'
$sdkHash = '90778deb1b77e36292828b4b5833c9255c27533f40e0ddd55891ae718944edc4'

New-Item -ItemType Directory -Force -Path $build, $dist | Out-Null
if (!$WebView2Package) {
    $packageName = "microsoft.web.webview2.$sdkVersion.nupkg"
    $WebView2Package = Join-Path $build $packageName
    if (!(Test-Path -LiteralPath $WebView2Package)) {
        $packageUrl = "https://api.nuget.org/v3-flatcontainer/microsoft.web.webview2/$sdkVersion/$packageName"
        Invoke-WebRequest -UseBasicParsing -Uri $packageUrl -OutFile $WebView2Package
    }
}

if ((Get-FileHash -LiteralPath $WebView2Package -Algorithm SHA256).Hash -ne $sdkHash) {
    throw 'The WebView2 SDK package does not match the pinned SHA-256 hash.'
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$components = [ordered]@{
    'Microsoft.Web.WebView2.Core' = 'lib/net462/Microsoft.Web.WebView2.Core.dll'
    'Microsoft.Web.WebView2.WinForms' = 'lib/net462/Microsoft.Web.WebView2.WinForms.dll'
    'WebView2Loader' = 'runtimes/win-x64/native/WebView2Loader.dll'
}
$archive = [System.IO.Compression.ZipFile]::OpenRead($WebView2Package)
try {
    foreach ($component in $components.GetEnumerator()) {
        $entry = $archive.GetEntry($component.Value)
        if ($null -eq $entry) { throw "SDK file missing: $($component.Value)" }
        $destination = Join-Path $build ($component.Key + '.dll')
        [System.IO.Compression.ZipFileExtensions]::ExtractToFile($entry, $destination, $true)
    }
}
finally { $archive.Dispose() }

function Compress-Resource {
    param([string]$InputFile, [string]$OutputFile)
    $inputStream = [System.IO.File]::OpenRead($InputFile)
    try {
        $outputStream = [System.IO.File]::Create($OutputFile)
        try {
            $compressor = [System.IO.Compression.GZipStream]::new(
                $outputStream, [System.IO.Compression.CompressionLevel]::Optimal)
            try { $inputStream.CopyTo($compressor) }
            finally { $compressor.Dispose() }
        }
        finally { $outputStream.Dispose() }
    }
    finally { $inputStream.Dispose() }
}

foreach ($component in $components.Keys) {
    Compress-Resource (Join-Path $build "$component.dll") (Join-Path $build "$component.gz")
}
Compress-Resource (Join-Path $source 'editor.html') (Join-Path $build 'editor.html.gz')

$compiler = Join-Path $env:WINDIR 'Microsoft.NET\Framework64\v4.0.30319\csc.exe'
if (!(Test-Path -LiteralPath $compiler)) {
    throw 'The x64 .NET Framework C# compiler was not found.'
}

$exe = Join-Path $dist 'OOT SW2SE.exe'
$license = Join-Path $source 'WebView2-LICENSE.txt'
$compilerArgs = @(
    '/nologo', '/target:winexe', '/platform:x64', '/optimize+', '/warn:4', '/utf8output',
    "/out:$exe",
    "/win32icon:$(Join-Path $source 'editor.ico')",
    "/win32manifest:$(Join-Path $source 'app.manifest')",
    '/r:System.Windows.Forms.dll', '/r:System.Drawing.dll', '/r:System.Web.Extensions.dll',
    "/r:$(Join-Path $build 'Microsoft.Web.WebView2.Core.dll')",
    "/r:$(Join-Path $build 'Microsoft.Web.WebView2.WinForms.dll')",
    "/resource:$license,WebView2-Lizenz.txt"
)
foreach ($resource in @('editor.html') + @($components.Keys)) {
    $compilerArgs += "/resource:$(Join-Path $build "$resource.gz"),$resource.gz"
}
$compilerArgs += Join-Path $source 'Program.cs'
& $compiler @compilerArgs
if ($LASTEXITCODE -ne 0) { throw "C# compilation failed with exit code $LASTEXITCODE." }

Copy-Item -LiteralPath $license -Destination $dist -Force
Write-Output $exe
