<?php
/**
 * 本地开发服务器（仅调试用，部署时不需要）：
 *   cd ohas && php -S 127.0.0.1:8090 tools/dev-server.php
 * 作用：public/ 下的静态资源由本路由直接输出（无需 -t 指定 docroot），
 *       其余请求交给 public/index.php。
 */
declare(strict_types=1);

/* 仅限命令行 / 内建开发服务器；被 Web 服务器（Apache/nginx）误暴露时直接拒绝执行 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit('Forbidden');
}

$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = realpath(__DIR__ . '/../public');
$file = $base . $uri;
$real = realpath($file);

/* 静态资源：白名单扩展名 + 路径限定在 public/ 内，直接输出 */
if ($uri !== '/' && $real !== false && $base !== false
    && str_starts_with($real, $base . DIRECTORY_SEPARATOR)
    && pathinfo($real, PATHINFO_EXTENSION) !== 'php') {
    $ext   = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    $mimes = [
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'eot'  => 'application/vnd.ms-fontobject',
        'map'  => 'application/json',
        'txt'  => 'text/plain; charset=utf-8',
    ];
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string)filesize($real));
    readfile($real);
    return true;
}

/* 业务请求：PATH_INFO 语义，剥掉脚本名前缀 /index.php/workers/edit → /workers/edit */
$path = $uri;
if (str_starts_with($path, '/index.php')) {
    $path = substr($path, strlen('/index.php'));
}
$_SERVER['PATH_INFO'] = $path;
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../public/index.php';
