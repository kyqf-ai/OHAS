#requires -Version 5.1
# OHAS 便携启动器：可从任意目录调用，项目路径均由脚本所在位置计算。
$ErrorActionPreference = 'Stop'

try {
    if (-not [Environment]::Is64BitOperatingSystem) {
        throw '此安装包包含 64 位 PHP，请在 64 位 Windows 10/11 上运行。'
    }

    $root = $PSScriptRoot
    $fennec = Join-Path $root 'Fennec.exe'
    $required = @(
        $fennec,
        (Join-Path $root 'fennec.ini'),
        (Join-Path $root 'wwwroot\public\index.php'),
        (Join-Path $root 'php-8.6\php-cgi.exe'),
        (Join-Path $root 'php-8.6\php.ini')
    )

    foreach ($file in $required) {
        if (-not (Test-Path -LiteralPath $file -PathType Leaf)) {
            throw "安装包文件不完整，缺少：$($file.Substring($root.Length).TrimStart('\'))"
        }
    }

    Set-Location -LiteralPath $root
    $explorer = Join-Path $env:WINDIR 'explorer.exe'

    # 显式加引号，确保 server 路径包含空格时仍作为同一个参数传递。
    Start-Process -FilePath $explorer -ArgumentList ('"{0}"' -f $fennec) -WorkingDirectory $root
}
catch {
    Add-Type -AssemblyName PresentationFramework
    [System.Windows.MessageBox]::Show(
        $_.Exception.Message,
        'OHAS 启动失败',
        [System.Windows.MessageBoxButton]::OK,
        [System.Windows.MessageBoxImage]::Error
    ) | Out-Null
    exit 1
}
