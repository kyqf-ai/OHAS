<?php
/**
 * 数据库：PDO SQLite 单例，预处理语句封装。
 */
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use Throwable;

class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $path = cfg('db.path');
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            try {
                self::$pdo = new PDO('sqlite:' . $path, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                self::fail('数据库打开失败（检查 storage 目录写权限）：' . $e->getMessage());
            }
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            self::$pdo->exec('PRAGMA busy_timeout = 5000');
            self::$pdo->exec('PRAGMA synchronous = NORMAL');
            Migrator::run(self::$pdo);
        }
        return self::$pdo;
    }

    public static function run(string $sql, array $params = [])
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function rows(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function row(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = [])
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function lastId(): int
    {
        return (int)self::pdo()->lastInsertId();
    }

    /** 事务包装：回调抛异常即回滚并上抛 */
    public static function tx(callable $fn)
    {
        $pdo = self::pdo();
        $owner = !$pdo->inTransaction();
        if ($owner) {
            $pdo->beginTransaction();
        }
        try {
            $result = $fn();
            if ($owner) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($owner && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * 写锁事务包装：BEGIN IMMEDIATE 先取写锁，把「先查后写」的并发检查（如账号启用上限）串行化；
     * 回调抛异常即回滚并上抛。
     * 注意：手工 exec('BEGIN …') 开启的事务，PDO 并不认（PHP 8.x 上 inTransaction() 为 false，
     * 随后的 PDO::commit() 会抛 "There is no active transaction"，事务悬空未提交），
     * 所以收尾也必须用 exec('COMMIT') / exec('ROLLBACK')，不能混用 commit()/rollBack()。
     * 回调里不要 exit / 重定向：需要中止请返回约定值，事务结束后再处理。
     */
    public static function immediate(callable $fn)
    {
        $pdo = self::pdo();
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $result = $fn();
            $pdo->exec('COMMIT');
            return $result;
        } catch (Throwable $e) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (Throwable $e2) {
                // 事务已被 SQLite 自行回滚（如 SQLITE_FULL）时忽略
            }
            throw $e;
        }
    }

    private static function fail(string $msg): void
    {
        http_response_code(500);
        $title = '数据库不可用';
        $hint  = $msg;
        include OHAS_ROOT . '/app/Views/errors/error.php';
        exit;
    }
}
