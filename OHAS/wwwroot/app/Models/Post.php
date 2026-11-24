<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Post extends Model
{
    protected static string $table = 'posts';

    /** 岗位（含所属场所名与危害配置数） */
    public static function listWithMeta(): array
    {
        return Database::rows(
            'SELECT p.*, w.name AS workplace_name,
                    (SELECT COUNT(*) FROM post_hazards ph WHERE ph.post_id = p.id) AS hazard_count
             FROM posts p LEFT JOIN workplaces w ON w.id = p.workplace_id
             ORDER BY w.name, p.name'
        );
    }

    public static function options(): array
    {
        return Database::rows(
            "SELECT p.id, p.name, w.name AS workplace_name
             FROM posts p JOIN workplaces w ON w.id = p.workplace_id
             WHERE p.status = '启用' AND w.status = '启用'
             ORDER BY w.name, p.name"
        );
    }

    public static function referenced(int $id): bool
    {
        $n  = (int)Database::value('SELECT COUNT(*) FROM worker_posts WHERE post_id = ?', [$id]);
        $n += (int)Database::value('SELECT COUNT(*) FROM detection_items WHERE post_id = ?', [$id]);
        // 台账（表4-1、2-6、5-5）的岗位关联
        foreach (['materials', 'ppe_issues', 'health_accidents'] as $t) {
            $n += (int)Database::value("SELECT COUNT(*) FROM {$t} WHERE post_id = ?", [$id]);
        }
        return $n > 0;
    }

    /** 岗位危害配置明细 */
    public static function hazards(int $postId): array
    {
        return Database::rows(
            'SELECT ph.*, h.name AS hazard_name, h.category AS hazard_category, h.possible_disease
             FROM post_hazards ph JOIN hazards h ON h.id = ph.hazard_id
             WHERE ph.post_id = ? ORDER BY h.category, h.name',
            [$postId]
        );
    }

    /** 覆盖式保存岗位危害配置 */
    public static function saveHazards(int $postId, array $rows): void
    {
        Database::run('DELETE FROM post_hazards WHERE post_id = ?', [$postId]);
        foreach ($rows as $r) {
            if (empty($r['hazard_id'])) {
                continue;
            }
            PostHazard::insert([
                'post_id' => $postId,
                'hazard_id' => (int)$r['hazard_id'],
                'exposure_type' => $r['exposure_type'] ?? '',
                'source' => $r['source'] ?? '',
                'engineering_controls' => $r['engineering_controls'] ?? '',
                'ppe' => $r['ppe'] ?? '',
            ]);
        }
    }

    /** 岗位危害摘要（职业史快照用）：危害名 + 防护措施 */
    public static function hazardSummary(int $postId): array
    {
        $rows = self::hazards($postId);
        $names = [];
        $protections = [];
        foreach ($rows as $r) {
            $names[] = $r['hazard_name'];
            $parts = array_filter([$r['engineering_controls'], $r['ppe']], fn ($v) => $v !== '');
            if ($parts) {
                $protections[] = $r['hazard_name'] . '：' . implode('；', $parts);
            }
        }
        return [
            'hazards' => implode('、', array_unique($names)),
            'protection' => implode('；', $protections),
        ];
    }
}
