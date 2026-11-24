<?php
/**
 * 接害判定唯一口径。
 *
 * 「当前接害」= 在职 + 本单位在岗职业史 + 岗位启用 + 该岗位当前配置了危害因素。
 * 「在岗」统一为 end_date IS NULL 或空串（历史脏数据两种都可能存在）。
 * 「历史接害」另按职业史段的 hazards_snapshot 兜底：岗位配置可能后来被删，
 *   当时确实接害的经历不能因此消失（仅用于工龄与个人年度矩阵）。
 *
 * 所有涉及接害人数的统计（工作台 / 表2-2 / 表4-2 / 资料清单）
 * 一律调用本类，不再各自手写 SQL。
 */
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

class Exposure
{
    /** 在岗 SQL 片段：wp = worker_posts 别名 */
    public const ACTIVE_SQL = "(wp.end_date IS NULL OR wp.end_date = '')";

    /** 当前接害人数（全单位去重） */
    public static function currentCount(): int
    {
        return (int)Database::value(
            'SELECT COUNT(DISTINCT wp.worker_id) FROM worker_posts wp
             JOIN posts p ON p.id = wp.post_id
             JOIN workers w ON w.id = wp.worker_id
             WHERE ' . self::ACTIVE_SQL . ' AND wp.post_id IS NOT NULL
               AND w.status = \'在职\' AND p.status = \'启用\'
               AND EXISTS (SELECT 1 FROM post_hazards ph WHERE ph.post_id = p.id)'
        );
    }

    /** 某岗位当前接害人数 [总, 女]（岗位停用时一律为 0，与表2-2 口径完全一致） */
    public static function postCurrentCount(int $postId): array
    {
        $post = Database::row('SELECT status FROM posts WHERE id = ?', [$postId]);
        if ($post === null || $post['status'] !== '启用') {
            return [0, 0];
        }
        $where = 'WHERE wp.post_id = ? AND ' . self::ACTIVE_SQL . "
            AND w.status = '在职'
            AND EXISTS (SELECT 1 FROM post_hazards ph WHERE ph.post_id = wp.post_id)";
        $total = (int)Database::value(
            'SELECT COUNT(DISTINCT wp.worker_id) FROM worker_posts wp JOIN workers w ON w.id = wp.worker_id ' . $where,
            [$postId]
        );
        $female = (int)Database::value(
            'SELECT COUNT(DISTINCT wp.worker_id) FROM worker_posts wp JOIN workers w ON w.id = wp.worker_id
             ' . $where . " AND w.gender = '女'",
            [$postId]
        );
        return [$total, $female];
    }

    /** 某场所 × 危害因素的当前接害人数 */
    public static function workplaceHazardCount(int $workplaceId, int $hazardId): int
    {
        return (int)Database::value(
            'SELECT COUNT(DISTINCT wp.worker_id) FROM worker_posts wp
             JOIN posts p ON p.id = wp.post_id
             JOIN workers w ON w.id = wp.worker_id
             JOIN post_hazards ph ON ph.post_id = p.id AND ph.hazard_id = ?
             WHERE p.workplace_id = ? AND ' . self::ACTIVE_SQL . '
               AND wp.post_id IS NOT NULL AND w.status = \'在职\' AND p.status = \'启用\'',
            [$hazardId, $workplaceId]
        );
    }

    /** 某场所当前接害人数（按人去重） */
    public static function workplaceCount(int $workplaceId): int
    {
        return (int)Database::value(
            'SELECT COUNT(DISTINCT wp.worker_id) FROM worker_posts wp
             JOIN posts p ON p.id = wp.post_id
             JOIN workers w ON w.id = wp.worker_id
             WHERE p.workplace_id = ? AND ' . self::ACTIVE_SQL . '
               AND wp.post_id IS NOT NULL AND w.status = \'在职\' AND p.status = \'启用\'
               AND EXISTS (SELECT 1 FROM post_hazards ph WHERE ph.post_id = p.id)',
            [$workplaceId]
        );
    }

    /** 当前配置了危害因素的岗位 ID 集合（历史判定用） */
    public static function hazardPosts(): array
    {
        $set = [];
        foreach (Database::rows('SELECT DISTINCT post_id FROM post_hazards') as $r) {
            $set[(int)$r['post_id']] = true;
        }
        return $set;
    }

    /**
     * 一段职业史是否属于接害经历（历史口径：快照非空 或 岗位现仍有危害配置）。
     * @param array $hazardPosts self::hazardPosts() 结果
     */
    public static function historyExposed(array $h, array $hazardPosts): bool
    {
        if (empty($h['post_id']) || (int)$h['post_id'] <= 0) {
            return false;   // 外单位经历无本单位岗位，按快照单独判定（见下方）
        }
        return trim((string)($h['hazards_snapshot'] ?? '')) !== ''
            || isset($hazardPosts[(int)$h['post_id']]);
    }

    /**
     * 外单位/无岗位职业史的接害判定：只看快照（没有岗位配置可查）。
     * 供工龄计算使用；个人年度矩阵按本单位口径不计这类经历。
     */
    public static function historyExposedAny(array $h, array $hazardPosts): bool
    {
        return trim((string)($h['hazards_snapshot'] ?? '')) !== '' || self::historyExposed($h, $hazardPosts);
    }

    /** 某年是否接害（本单位口径，供个人年度矩阵） */
    public static function exposedInYear(array $history, int $year, int $asOfYear, array $hazardPosts): bool
    {
        foreach ($history as $h) {
            $endYear = !empty($h['end_date'])
                ? (int)substr((string)$h['end_date'], 0, 4) : $asOfYear;
            if ((int)substr((string)($h['start_date'] ?? ''), 0, 4) <= $year && $year <= $endYear
                && self::historyExposed($h, $hazardPosts)) {
                return true;
            }
        }
        return false;
    }
}
