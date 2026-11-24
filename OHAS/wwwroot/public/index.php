<?php
/**
 * OHAS 单一入口：所有 HTTP 请求由此进入。
 * 站点根目录（phpStudy/宝塔）应指向本文件所在目录。
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Router;

/* 内建服务器（php -S）开发模式：静态资源直接放行 */
if (php_sapi_name() === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
        return false;
    }
}

(new Router())->dispatch();
