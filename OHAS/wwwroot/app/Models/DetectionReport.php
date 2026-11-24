<?php
/**
 * 检测报告（监测检测档案目录第 8~9 项：职业病危害检测与评价报告书 / 结果报告）。
 * 类别口径：定期检测（每年一次，委托技术服务机构）/ 日常监测 /
 * 现状评价 / 控制效果评价；限值判定依据 GBZ 2.1-2019（含修改单）与 GBZ 2.2-2007。
 */
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class DetectionReport extends Model
{
    protected static string $table = 'detection_reports';

    /** 列表检索：年度 + 类别 + 编号/机构关键字 */
    public static function search(string $year, string $category, string $q, int $page, int $size): array
    {
        $where = ['1=1'];
        $params = [];
        if ($year !== '') {
            $where[] = 'test_date LIKE :yr';
            $params[':yr'] = $year . '%';
        }
        if ($category !== '') {
            $where[] = 'category = :ct';
            $params[':ct'] = $category;
        }
        if ($q !== '') {
            $where[] = '(report_no LIKE :q1 OR agency LIKE :q2)';
            $params[':q1'] = '%' . $q . '%';
            $params[':q2'] = '%' . $q . '%';
        }
        $result = static::paginate(implode(' AND ', $where), $params, 'test_date DESC, id DESC', $page, $size);
        foreach ($result['rows'] as &$row) {
            $row = self::withCount($row);
        }
        return $result;
    }

    /** 附加明细数 / 超标点数 / 合格率 */
    public static function withCount(array $row): array
    {
        $row = \App\Helpers\DocLink::withSummary($row, 'detection_report');
        $row['item_count'] = (int)Database::value(
            'SELECT COUNT(*) FROM detection_items WHERE report_id = ?',
            [$row['id']]
        );
        $row['exceed_count'] = (int)Database::value(
            "SELECT COUNT(*) FROM detection_items WHERE report_id = ? AND verdict = '超标'",
            [$row['id']]
        );
        $row['undetermined_count'] = (int)Database::value(
            "SELECT COUNT(*) FROM detection_items WHERE report_id = ? AND (verdict IS NULL OR TRIM(verdict) = '')",
            [$row['id']]
        );
        $row['pass_ratio'] = self::passPercent($row['item_count'], $row['exceed_count'], $row['undetermined_count']);
        return $row;
    }

    /**
     * 合格率（整数百分比，全站唯一口径）：未判定的检测点从分母剔除，不冒充合格；
     * 没有超标才显示 100%，有超标最高 99%（向下取整）。
     * 直接四舍五入会让 199 个检测点中 1 个超标显示成“合格率 100%”，对合规报告是反向误导。
     */
    public static function passPercent(int $total, int $exceeds, int $undetermined = 0): int
    {
        $determined = $total - max(0, $undetermined);
        if ($determined <= 0) {
            return 0;
        }
        if ($exceeds <= 0) {
            return 100;
        }
        return max(0, min(99, (int)floor(($determined - $exceeds) * 100 / $determined)));
    }

    /** 已有年份（倒序），用于筛选下拉 */
    public static function years(): array
    {
        $rows = Database::rows(
            "SELECT DISTINCT substr(test_date, 1, 4) AS y FROM detection_reports
             WHERE test_date IS NOT NULL AND test_date != '' ORDER BY y DESC"
        );
        return array_map(fn ($r) => (string)$r['y'], $rows);
    }

    /** 某年度检测概况（列表统计卡 / 工作台） */
    public static function yearStats(string $year): array
    {
        $reports = (int)Database::value(
            'SELECT COUNT(*) FROM detection_reports WHERE test_date LIKE ?',
            [$year . '%']
        );
        $items = (int)Database::value(
            'SELECT COUNT(*) FROM detection_items di JOIN detection_reports dr ON dr.id = di.report_id
             WHERE dr.test_date LIKE ?',
            [$year . '%']
        );
        $exceeds = (int)Database::value(
            "SELECT COUNT(*) FROM detection_items di JOIN detection_reports dr ON dr.id = di.report_id
             WHERE dr.test_date LIKE ? AND di.verdict = '超标'",
            [$year . '%']
        );
        $undetermined = (int)Database::value(
            "SELECT COUNT(*) FROM detection_items di JOIN detection_reports dr ON dr.id = di.report_id
             WHERE dr.test_date LIKE ? AND (di.verdict IS NULL OR TRIM(di.verdict) = '')",
            [$year . '%']
        );
        return [
            'reports' => $reports,
            'items'   => $items,
            'exceeds' => $exceeds,
            'undetermined' => $undetermined,
            'pass_ratio' => self::passPercent($items, $exceeds, $undetermined),
        ];
    }
}
