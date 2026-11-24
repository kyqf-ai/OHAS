<?php
/**
 * 档案号生成器：前缀 + 年份 + 4位流水号，如 2026-0001。
 */
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;
use App\Models\Setting;

class ArchiveNo
{
    /** 生成下一号（年度内递增；并发窗口极小，唯一索引兜底） */
    public static function next(): string
    {
        $prefix = Setting::get('archive_prefix');
        $year = date('Y');
        $like = $prefix . $year . '-%';
        $max = (string)Database::value(
            'SELECT MAX(CAST(SUBSTR(archive_no, ?) AS INTEGER)) FROM workers WHERE archive_no LIKE ?',
            [strlen($prefix . $year . '-') + 1, $like]
        );
        $seq = ($max === '' ? 0 : (int)$max) + 1;
        return sprintf('%s%s-%04d', $prefix, $year, $seq);
    }
}
