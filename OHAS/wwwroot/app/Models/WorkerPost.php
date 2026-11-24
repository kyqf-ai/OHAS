<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class WorkerPost extends Model
{
    protected static string $table = 'worker_posts';

    /**
     * 保存职业史行：
     * - 本单位经历（post_id > 0）：自动带出单位名、工种、接害因素与防护措施快照
     * - 外单位经历（post_id 空）：使用手工填写内容
     */
    public static function saveRow(int $workerId, array $in, ?int $rowId = null): int
    {
        $postId = (int)($in['post_id'] ?? 0);
        $data = [
            'worker_id'    => $workerId,
            'start_date'   => $in['start_date'] ?? '',
            'end_date'     => ($in['end_date'] ?? '') !== '' ? $in['end_date'] : null,
            'company_name' => $in['company_name'] ?? '',
            'post_id'      => $postId > 0 ? $postId : null,
            'post_name'    => $in['post_name'] ?? '',
            'hazards_snapshot'    => $in['hazards_snapshot'] ?? '',
            'protection_snapshot' => $in['protection_snapshot'] ?? '',
            'remark'       => $in['remark'] ?? '',
        ];
        if ($postId > 0) {
            $post = Database::row(
                'SELECT p.name, w.name AS wp_name FROM posts p JOIN workplaces w ON w.id = p.workplace_id WHERE p.id = ?',
                [$postId]
            );
            if ($post) {
                $data['post_name'] = $post['name'];
                // 已结束的历史段：岗位未变且已有快照时保留原快照。快照记录的是当时的接害事实，
                // 若每次编辑（如补填结束日期）都按岗位「当前」配置重写，配置后来变过/被清空就会把
                // 历史接害经历连同接害工龄一并抹掉。在岗段（无结束日期）仍随编辑刷新为当前配置。
                $old = $rowId !== null && $rowId > 0 ? self::find($rowId) : null;
                $freeze = $old !== null
                    && (int)($old['post_id'] ?? 0) === $postId
                    && $data['end_date'] !== null
                    && trim((string)($old['hazards_snapshot'] ?? '')) !== '';
                if ($freeze) {
                    $data['hazards_snapshot'] = (string)$old['hazards_snapshot'];
                    $data['protection_snapshot'] = (string)($old['protection_snapshot'] ?? '');
                } else {
                    $summary = Post::hazardSummary($postId);
                    $data['hazards_snapshot'] = $summary['hazards'];
                    $data['protection_snapshot'] = $summary['protection'];
                }
                if (($data['company_name'] ?? '') === '') {
                    $data['company_name'] = Company::name();
                }
            }
        }
        if ($rowId !== null && $rowId > 0) {
            self::update($rowId, $data);
            return $rowId;
        }
        return self::insert($data);
    }

    /**
     * 状态改为非在职时，收尾该劳动者所有未结束的职业史段。
     * 结束日期取离岗日期；若历史段开始日晚于离岗日期（旧数据），则以开始日收尾，避免出现 end < start。
     * @return int 实际收尾的段数
     */
    public static function closeAllOpen(int $workerId, string $endDate): int
    {
        $endDate = substr(trim($endDate), 0, 10);
        if ($workerId <= 0 || !\App\Core\Validator::isDate($endDate)) {
            return 0;
        }
        $stmt = Database::run(
            'UPDATE worker_posts
                SET end_date = CASE WHEN start_date > ? THEN start_date ELSE ? END, updated_at = ?
              WHERE worker_id = ? AND (end_date IS NULL OR TRIM(end_date) = \'\')',
            [$endDate, $endDate, now_str(), $workerId]
        );
        return $stmt->rowCount();
    }

    /**
     * 岗位危害配置变更后，刷新仍在该岗位“在岗”的职业史快照，
     * 避免表6-1/信息卡显示旧快照而各项统计已按新配置计算。
     * 已结束段的快照保持冻结，不被后来的配置改动抹掉。
     * @return int 实际刷新的段数
     */
    public static function refreshOpenSnapshots(int $postId): int
    {
        if ($postId <= 0) {
            return 0;
        }
        $summary = Post::hazardSummary($postId);
        $stmt = Database::run(
            'UPDATE worker_posts SET hazards_snapshot = ?, protection_snapshot = ?, updated_at = ?
              WHERE post_id = ? AND (end_date IS NULL OR TRIM(end_date) = \'\')',
            [$summary['hazards'], $summary['protection'], now_str(), $postId]
        );
        return $stmt->rowCount();
    }

    /**
     * “至今”职业史段默认把已有未结束段收尾到新起始日前一天。
     * 兼岗可传 $exceptId 排除；同一天开始的段以开始日收尾，避免出现 end < start。
     * @return int 实际收尾的段数
     */
    public static function closeOpenBefore(int $workerId, string $startDate, ?int $exceptId = null): int
    {
        $startDate = substr(trim($startDate), 0, 10);
        if ($workerId <= 0 || !\App\Core\Validator::isDate($startDate)) {
            return 0;
        }
        $prevDay = date('Y-m-d', strtotime($startDate . ' -1 day'));
        $rows = Database::rows(
            'SELECT id, start_date FROM worker_posts
              WHERE worker_id = ? AND id <> ? AND start_date <= ? AND (end_date IS NULL OR TRIM(end_date) = \'\')',
            [$workerId, (int)$exceptId, $startDate]
        );
        $n = 0;
        foreach ($rows as $r) {
            $end = ((string)$r['start_date'] >= $startDate) ? $startDate : $prevDay;
            Database::run(
                'UPDATE worker_posts SET end_date = ?, updated_at = ? WHERE id = ?',
                [$end, now_str(), (int)$r['id']]
            );
            $n++;
        }
        return $n;
    }

    /**
     * 某日期在岗的本单位职业史段（含岗位与所属车间名称）。
     * 供打印（表5-3/5-4 车间岗位）与快照取值共用：优先精确匹配日期区间，
     * 取不到再退回当前在岗段、最近一段。外单位经历（post_id 空）不返回。
     * @return array{post_name:string, workplace_name:string}|null
     */
    public static function atDate(int $workerId, ?string $date): ?array
    {
        $date = trim((string)$date);
        $sql = 'SELECT wp.post_name, w2.name AS workplace_name
                FROM worker_posts wp
                JOIN posts p ON p.id = wp.post_id
                JOIN workplaces w2 ON w2.id = p.workplace_id
                WHERE wp.worker_id = ? AND wp.post_id IS NOT NULL %s
                ORDER BY wp.id DESC LIMIT 1';
        if ($date !== '') {
            $row = Database::row(
                sprintf($sql, 'AND wp.start_date <= ? AND (wp.end_date IS NULL OR wp.end_date >= ?)'),
                [$workerId, $date, $date]
            );
            if ($row !== null) {
                return $row;
            }
        }
        $row = Database::row(sprintf($sql, 'AND wp.end_date IS NULL'), [$workerId]);
        if ($row === null) {
            // 此分支为“取最近一段”，无需附加条件，用空串填充占位符
            $row = Database::row(sprintf($sql, ''), [$workerId]);
        }
        return $row;
    }

    /**
     * 接害工龄（年，保留 1 位小数）：职业史中接触危害因素的各段时长之和
     * （本单位 + 外单位经历都计入，截至 $asOf 日期）。
     * 不足 1 年返回 0.x；无接害职业史返回 null。
     */
    public static function exposureYears(int $workerId, ?string $asOf): ?float
    {
        $all = Database::rows(
            'SELECT start_date, end_date, post_id, hazards_snapshot FROM worker_posts WHERE worker_id = ?',
            [$workerId]
        );
        $hazardPosts = \App\Helpers\Exposure::hazardPosts();
        $rows = array_values(array_filter(
            $all,
            fn ($r) => \App\Helpers\Exposure::historyExposedAny($r, $hazardPosts)
        ));
        if (!$rows) {
            return null;
        }
        $asOf = substr(trim((string)$asOf), 0, 10);
        $today = date('Y-m-d');
        $cap = ($asOf !== '' && $asOf < $today) ? $asOf : $today;   // 截止日不能晚于今天
        $capTs = strtotime($cap);
        if ($capTs === false) {
            return null;
        }
        // 各段先收集为 [起, 止] 时间戳，再按「区间并集」计天数：兼岗 / 重叠录入的天数只算一次
        $segs = [];
        foreach ($rows as $r) {
            $start = substr((string)$r['start_date'], 0, 10);
            $end = $r['end_date'] !== null && $r['end_date'] !== ''
                ? substr((string)$r['end_date'], 0, 10) : $cap;
            if ($end > $cap) {
                $end = $cap;
            }
            $sTs = strtotime($start);
            $eTs = strtotime($end);
            if ($sTs === false || $eTs === false || $eTs < $sTs) {
                continue;
            }
            $segs[] = [$sTs, $eTs];
        }
        usort($segs, static fn ($a, $b) => $a[0] <=> $b[0]);
        $days = 0;
        $curS = null;
        $curE = null;
        foreach ($segs as [$sTs, $eTs]) {
            if ($curS !== null && $sTs <= $curE) {
                $curE = max($curE, $eTs);   // 与当前合并段重叠 → 延伸
                continue;
            }
            if ($curS !== null) {
                $days += (int)round(($curE - $curS) / 86400) + 1;
            }
            $curS = $sTs;
            $curE = $eTs;
        }
        if ($curS !== null) {
            $days += (int)round(($curE - $curS) / 86400) + 1;
        }
        if ($days <= 0) {
            return null;
        }
        return round($days / 365.25, 1);
    }
}
