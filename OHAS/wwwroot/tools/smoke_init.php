<?php
/**
 * 冒烟测试初始化（tools/smoke.sh 调用，非独立入口）：
 * 在 OHAS_DB 指定的空文件上执行 schema.sql、建管理员、写安装锁、灌 v0.5 自测种子。
 * 用法：OHAS_DB=/path/smoke.db php tools/smoke_init.php
 */
declare(strict_types=1);

/* 仅限命令行 / 内建开发服务器；被 Web 服务器（Apache/nginx）误暴露时直接拒绝执行 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit('Forbidden');
}

$dbPath = getenv('OHAS_DB');
if (!$dbPath || !is_file($dbPath)) {
    fwrite(STDERR, "OHAS_DB 未设置或文件不存在\n");
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec(file_get_contents(OHAS_ROOT . '/database/schema.sql'));
\App\Core\Migrator::ensureDocUnlinkTriggers($pdo);
$pdo->exec('PRAGMA user_version = ' . \App\Core\Migrator::VERSION);

Database::run(
    "INSERT OR IGNORE INTO users (username, password_hash, real_name, role, created_at, updated_at)
     VALUES ('admin', ?, '管理员', 'admin', ?, ?)",
    [password_hash('admin12345', PASSWORD_DEFAULT), now_str(), now_str()]
);
Database::run(
    'INSERT INTO company (name, created_at, updated_at) SELECT ?,?,? WHERE NOT EXISTS (SELECT 1 FROM company)',
    ['冒烟测试单位', now_str(), now_str()]
);
file_put_contents(OHAS_ROOT . '/storage/installed.lock', date('Y-m-d H:i:s'));

// v0.5 自测种子（张三/李四/王五 + 检测报告 + 文档）
require __DIR__ . '/dev_seed_archive.php';
