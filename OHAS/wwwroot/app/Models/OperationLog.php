<?php
/**
 * 操作日志（logs 表）：仅追加，供系统管理模块查询。
 */
declare(strict_types=1);

namespace App\Models;

class OperationLog
{
    public static function add(string $action, string $objectType = '', $objectId = null, string $summary = '', string $ip = '-'): void
    {
        try {
            \App\Core\Database::run(
                'INSERT INTO logs (user_id, username, action, object_type, object_id, summary, ip, created_at) VALUES (?,?,?,?,?,?,?,?)',
                [
                    $_SESSION['uid'] ?? null,
                    $_SESSION['username'] ?? '',
                    $action,
                    $objectType,
                    $objectId,
                    mb_substr($summary, 0, 500),
                    $ip,
                    now_str(),
                ]
            );
        } catch (\Throwable $e) {
            // 日志失败不阻断业务
            @error_log('[log] ' . $e->getMessage() . "\n", 3, OHAS_ROOT . '/storage/logs/app-error.log');
        }
    }
}
