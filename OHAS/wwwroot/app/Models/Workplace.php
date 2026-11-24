<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Workplace extends Model
{
    protected static string $table = 'workplaces';

    public static function options(): array
    {
        return Database::rows("SELECT id, name FROM workplaces WHERE status = '启用' ORDER BY name");
    }

    public static function referenced(int $id): bool
    {
        $n  = (int)Database::value('SELECT COUNT(*) FROM posts WHERE workplace_id = ?', [$id]);
        $n += (int)Database::value('SELECT COUNT(*) FROM detection_items WHERE workplace_id = ?', [$id]);
        $n += (int)Database::value('SELECT COUNT(*) FROM facilities WHERE workplace_id = ?', [$id]);
        // 台账（表4-1、2-6、2-7、5-5）的场所关联
        foreach (['materials', 'ppe_issues', 'warning_signs', 'health_accidents'] as $t) {
            $n += (int)Database::value("SELECT COUNT(*) FROM {$t} WHERE workplace_id = ?", [$id]);
        }
        return $n > 0;
    }
}
