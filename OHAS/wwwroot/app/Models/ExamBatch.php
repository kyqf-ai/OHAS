<?php
/**
 * 体检批次（表5-1）：每年一次的集中体检登记单位。
 * 实检人数不手填，由 exam_records 按批次聚合。
 */
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class ExamBatch extends Model
{
    protected static string $table = 'exam_batches';

    /** 列表检索：年度 + 检查种类 + 机构关键字 */
    public static function search(string $year, string $type, string $q, int $page, int $size): array
    {
        $where = ['1=1'];
        $params = [];
        if ($year !== '') {
            $where[] = "exam_date LIKE :yr";
            $params[':yr'] = $year . '%';
        }
        if ($type !== '') {
            $where[] = 'exam_type = :tp';
            $params[':tp'] = $type;
        }
        if ($q !== '') {
            $where[] = 'agency LIKE :q1';
            $params[':q1'] = '%' . $q . '%';
        }
        $result = static::paginate(implode(' AND ', $where), $params, 'exam_date DESC, id DESC', $page, $size);
        foreach ($result['rows'] as &$row) {
            $row = self::withCount($row);
        }
        return $result;
    }

    /**
     * 附加批次统计口径：
     * - registered_count：批次内检查记录总数（含待出结论）；
     * - actual_count：实检人数＝已出结论（conclusion 非空）的记录数，
     *   应检名单生成的待检记录不再被计入（否则一生成名单进度就满格）；
     * - pending_count：待出结论人数；
     * - done_ratio：实检 / 应检。
     */
    public static function withCount(array $row): array
    {
        $row = \App\Helpers\DocLink::withSummary($row, 'exam_batch');
        $row['registered_count'] = (int)Database::value(
            'SELECT COUNT(*) FROM exam_records WHERE batch_id = ?',
            [$row['id']]
        );
        $row['actual_count'] = (int)Database::value(
            "SELECT COUNT(*) FROM exam_records WHERE batch_id = ? AND TRIM(COALESCE(conclusion,'')) <> ''",
            [$row['id']]
        );
        $row['pending_count'] = $row['registered_count'] - $row['actual_count'];
        // 分母优先用应检人数，未填应检时退回登记人数（逐人登记场景）；
        // 完成率封顶 100%，避免实检超出应检时显示超过 100%。
        $denom = $row['planned_count'] > 0 ? $row['planned_count'] : $row['registered_count'];
        $row['done_ratio'] = self::ratio($row['actual_count'], (int)$denom);
        // 手工应检数与名单/实检数有偏差时单独标记，避免被完成率掩盖
        $row['over_planned'] = (int)$row['planned_count'] > 0
            && ($row['registered_count'] > (int)$row['planned_count'] || $row['actual_count'] > (int)$row['planned_count']);
        return $row;
    }

    /**
     * 完成率（整数百分比）：只有实检 ≥ 应检才显示 100%，已开始则至少 1%、未完成最多 99%。
     * 直接四舍五入会让 299/300 显示 100%（实际还有 1 人待出结论），1/300 显示 0%。
     */
    public static function ratio(int $actual, int $denom): int
    {
        if ($denom <= 0 || $actual <= 0) {
            return 0;
        }
        if ($actual >= $denom) {
            return 100;
        }
        return max(1, min(99, (int)round($actual * 100 / $denom)));
    }

    /** 已有年份（倒序），用于筛选下拉 */
    public static function years(): array
    {
        $rows = Database::rows(
            "SELECT DISTINCT substr(exam_date, 1, 4) AS y FROM exam_batches
             WHERE exam_date IS NOT NULL AND exam_date != '' ORDER BY y DESC"
        );
        return array_map(fn ($r) => (string)$r['y'], $rows);
    }

    /**
     * 本年度批次数与体检人次（工作台卡片）。
     * 体检人次＝已出结论的检查记录（实检口径）；待出结论另计 pending。
     */
    public static function yearStats(string $year): array
    {
        $like = $year . '%';
        $batches = (int)Database::value(
            'SELECT COUNT(*) FROM exam_batches WHERE exam_date LIKE ?',
            [$like]
        );
        $records = (int)Database::value(
            "SELECT COUNT(*) FROM exam_records WHERE exam_date LIKE ? AND TRIM(COALESCE(conclusion,'')) <> ''",
            [$like]
        );
        $pending = (int)Database::value(
            "SELECT COUNT(*) FROM exam_records WHERE exam_date LIKE ? AND TRIM(COALESCE(conclusion,'')) = ''",
            [$like]
        );
        return ['batches' => $batches, 'records' => $records, 'pending' => $pending];
    }
}
