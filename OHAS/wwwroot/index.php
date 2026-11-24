<?php
/**
 * 应用根目录兜底入口（兼容根目录 / 任意深度子目录部署）。
 *
 * 站点根指向应用根目录即可：http://host/ 或 http://host/ZYWS/ 都能直接访问。
 * 本文件不改写 SCRIPT_NAME，让 url() 生成的链接保持 /ZYWS/index.php/... 形式；
 * 只单独告诉 asset() 静态资源实际在 public/ 下，从而不暴露 public 路径于链接中。
 */
declare(strict_types=1);

// 当前入口脚本在 Web 中的路径，例如 /index.php、/ZYWS/index.php
$script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
// 应用安装的 URL 前缀：根目录部署为空串，子目录部署为 /ZYWS 之类
$base = rtrim(dirname($script), '/');

// 静态资源基准（public 子目录）；bootstrap 的 asset() 会优先读它
$_SERVER['OHAS_ASSET_BASE'] = $base . '/public/';

// 从请求 URI 中剥掉入口前缀，得到路由真正要用的 PATH_INFO
$uri   = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$entry = $base . '/index.php';

if ($uri === $base || $uri === $base . '/') {
    $path = '/';                                       // / 或 /ZYWS/
} elseif ($uri === $entry) {
    $path = '/';                                       // /index.php 或 /ZYWS/index.php
} elseif (str_starts_with($uri, $entry . '/')) {
    $path = substr($uri, strlen($entry));              // /index.php/a/b → /a/b
} elseif ($base !== '' && str_starts_with($uri, $base . '/')) {
    $path = substr($uri, strlen($base));               // /ZYWS/a/b → /a/b（伪静态）
} else {
    $path = $uri;
}
$_SERVER['PATH_INFO'] = $path !== '' ? $path : '/';

require __DIR__ . '/public/index.php';
