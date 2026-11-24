<?php
/**
 * 检测明细（表6-2 归集数据源）：
 *  - 一行 = 一个检测点的一次检测结果，挂接检测报告；
 *  - 场所/岗位/危害因素三选二关联：优先关联到岗位（表6-2 按人归集主口径），
 *    未指明岗位时按「同场所 + 岗位配置的危害因素」归集；
 *  - 判定结论（合格/超标）限值依据 GBZ 2.1-2019（含第2号修改单）与 GBZ 2.2-2007。
 */
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class DetectionItem extends Model
{
    protected static string $table = 'detection_items';

    /** 判定结论取值 */
    public const VERDICTS = ['合格', '超标'];

    /** 采样/限值类型（GBZ 2.1/2.2 限值备注常同时给多个类型，录入时须声明可比口径） */
    public const SAMPLE_TYPES = ['TWA', 'STEL', 'MAC', '峰'];

    /**
     * 检测年度格子状态（与 WorkerArchive::yearState 的 X_DETECT 列同口径）：
     * 有带原件的报告→done_indirect；有报告无原件→reg_only；无报告→todo。
     * 供检测年度视图与个人矩阵 X_DETECT 列共用，避免两处各写一套。
     */
    public static function reportState(array $reports): string
    {
        if (!$reports) {
            return 'todo';
        }
        foreach ($reports as $r) {
            if (!empty($r['has_doc'])) {
                return 'done_indirect';
            }
        }
        return 'reg_only';
    }

    /** 某报告下全部明细（详情页表格） */
    public static function forReport(int $reportId): array
    {
        return Database::rows(
            'SELECT di.*, p.name AS post_name, h.name AS hazard_name, wpl.name AS workplace_name
             FROM detection_items di
             LEFT JOIN posts p ON p.id = di.post_id
             LEFT JOIN hazards h ON h.id = di.hazard_id
             LEFT JOIN workplaces wpl ON wpl.id = di.workplace_id
             WHERE di.report_id = ?
             ORDER BY di.id ASC',
            [$reportId]
        );
    }

    /** 跨报告检索（检测结果查询页）：年度/场所/岗位/危害/判定/检测点关键字 */
    public static function search(array $filters, int $page, int $size): array
    {
        $where = ["dr.test_date IS NOT NULL AND dr.test_date != ''"];
        $params = [];
        if (!empty($filters['year'])) {
            $where[] = 'dr.test_date LIKE :yr';
            $params[':yr'] = $filters['year'] . '%';
        }
        if (!empty($filters['workplace_id'])) {
            $where[] = 'di.workplace_id = :wp';
            $params[':wp'] = (int)$filters['workplace_id'];
        }
        if (!empty($filters['post_id'])) {
            $where[] = 'di.post_id = :pt';
            $params[':pt'] = (int)$filters['post_id'];
        }
        if (!empty($filters['hazard_id'])) {
            $where[] = 'di.hazard_id = :hz';
            $params[':hz'] = (int)$filters['hazard_id'];
        }
        if (($filters['verdict'] ?? '') !== '') {
            if ($filters['verdict'] === '未判定') {
                $where[] = "(di.verdict IS NULL OR di.verdict = '')";
            } else {
                $where[] = 'di.verdict = :vd';
                $params[':vd'] = $filters['verdict'];
            }
        }
        if (!empty($filters['q'])) {
            $where[] = '(di.test_point LIKE :q1 OR dr.report_no LIKE :q2 OR dr.agency LIKE :q3)';
            $params[':q1'] = '%' . $filters['q'] . '%';
            $params[':q2'] = '%' . $filters['q'] . '%';
            $params[':q3'] = '%' . $filters['q'] . '%';
        }
        $w = implode(' AND ', $where);
        $total = (int)Database::value(
            "SELECT COUNT(*) FROM detection_items di JOIN detection_reports dr ON dr.id = di.report_id WHERE {$w}",
            $params
        );
        $pages = max(1, (int)ceil($total / $size));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $size;
        $rows = Database::rows(
            "SELECT di.*, dr.report_no, dr.agency, dr.test_date, dr.report_date, dr.category,
                    p.name AS post_name, h.name AS hazard_name, wpl.name AS workplace_name
             FROM detection_items di
             JOIN detection_reports dr ON dr.id = di.report_id
             LEFT JOIN posts p ON p.id = di.post_id
             LEFT JOIN hazards h ON h.id = di.hazard_id
             LEFT JOIN workplaces wpl ON wpl.id = di.workplace_id
             WHERE {$w}
             ORDER BY dr.test_date DESC, di.id ASC
             LIMIT {$size} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * 表6-2 按人归集（信息卡查看页 / 打印）：
     * 匹配规则 —— 劳动者本单位职业史（worker_posts.post_id）期间内：
     *  a) 明细直接关联该岗位 → 按「岗位」归集；
     *  b) 明细未指明岗位，但场所一致且危害因素在该岗位配置清单内 → 按「因素」归集；
     * 检测时间须落在该段职业史起止区间内（空截止视为至今）。
     */
    public static function forWorker(int $workerId): array
    {
        $idList = self::matchedItemIds($workerId);
        if ($idList === '') {
            return [];
        }
        $rows = Database::rows(
            "SELECT di.*, dr.report_no, dr.agency, dr.test_date, dr.category,
                    p.name AS post_name, h.name AS hazard_name, wpl.name AS workplace_name
             FROM detection_items di
             JOIN detection_reports dr ON dr.id = di.report_id
             LEFT JOIN posts p ON p.id = di.post_id
             LEFT JOIN hazards h ON h.id = di.hazard_id
             LEFT JOIN workplaces wpl ON wpl.id = di.workplace_id
             WHERE di.id IN ({$idList})
             ORDER BY dr.test_date DESC, di.id ASC"
        );
        // 归集依据标记：该劳动者职业史中直接命中岗位的 → 岗位；否则 → 因素
        $postIds = Database::rows(
            'SELECT post_id FROM worker_posts WHERE worker_id = ? AND post_id IS NOT NULL',
            [$workerId]
        );
        $workerPosts = array_map('intval', array_column($postIds, 'post_id'));
        foreach ($rows as &$r) {
            $r['match_basis'] = in_array((int)$r['post_id'], $workerPosts, true) ? '岗位' : '因素';
        }
        return $rows;
    }

    /**
     * 与 forWorker 完全同口径的「可汇入检测报告」集合。
     * 返回报告行（id、report_no、test_date、category、agency 及命中明细的岗位/因素），
     * 个人页“检测报告（间接）”列与导出资料包均以此为唯一口径。
     */
    public static function reportIdsForWorker(int $workerId): array
    {
        $idList = self::matchedItemIds($workerId);
        if ($idList === '') {
            return [];
        }
        return Database::rows(
            "SELECT DISTINCT dr.id, dr.report_no, dr.test_date, dr.category, dr.agency,
                    di.post_id, di.hazard_id, p.name AS post_name, h.name AS hazard_name, wpl.name AS workplace_name
             FROM detection_items di
             JOIN detection_reports dr ON dr.id = di.report_id
             LEFT JOIN posts p ON p.id = di.post_id
             LEFT JOIN hazards h ON h.id = di.hazard_id
             LEFT JOIN workplaces wpl ON wpl.id = di.workplace_id
             WHERE di.id IN ({$idList})
             ORDER BY dr.test_date DESC, dr.id ASC"
        );
    }

    /** 保存明细时冻结可归集岗位：直接岗位，或同场所且配置了同一危害因素的全部岗位 */
    public static function snapshotPostIds(int $postId, ?int $workplaceId, int $hazardId): string
    {
        $ids = [];
        if ($postId > 0) {
            $ids[] = $postId;
        } elseif ($workplaceId !== null && $workplaceId > 0 && $hazardId > 0) {
            foreach (Database::rows(
                'SELECT p.id FROM posts p JOIN post_hazards ph ON ph.post_id = p.id
                  WHERE p.workplace_id = ? AND ph.hazard_id = ?',
                [$workplaceId, $hazardId]
            ) as $r) {
                $ids[] = (int)$r['id'];
            }
        }
        $ids = array_values(array_unique(array_filter($ids, fn (int $v): bool => $v > 0)));
        return implode(',', $ids);
    }

    /** 匹配明细 id 集合（SQL 片段唯一来源，forWorker / reportIdsForWorker 共用同一口径） */
    private static function matchedItemIds(int $workerId): string
    {
        $ids = Database::rows(
            "SELECT DISTINCT di.id
             FROM detection_items di
             JOIN detection_reports dr ON dr.id = di.report_id
             JOIN worker_posts wp ON wp.worker_id = :wid
                  AND wp.post_id IS NOT NULL
                  AND (wp.end_date IS NULL OR wp.end_date = '' OR dr.test_date <= wp.end_date)
                  AND dr.test_date >= wp.start_date
                  AND (
                        /* 新明细按保存时冻结的岗位快照归集；旧明细（快照空）继续按当前配置兜底 */
                        (TRIM(COALESCE(di.matched_post_ids,'')) <> ''
                         AND ',' || di.matched_post_ids || ',' LIKE '%,' || wp.post_id || ',%')
                        OR (
                            TRIM(COALESCE(di.matched_post_ids,'')) = ''
                            AND (
                                di.post_id = wp.post_id
                                OR (
                                    di.post_id IS NULL
                                    AND di.workplace_id = (SELECT p.workplace_id FROM posts p WHERE p.id = wp.post_id)
                                    AND di.hazard_id IN (SELECT ph.hazard_id FROM post_hazards ph WHERE ph.post_id = wp.post_id)
                                )
                            )
                        )
                  )",
            [':wid' => $workerId]
        );
        if (!$ids) {
            return '';
        }
        return implode(',', array_map('intval', array_column($ids, 'id')));
    }

    /* ========== 超标明细 → 整改草稿 ========== */

    /**
     * 整改草稿的“超标点及因素”文字：场所 · 岗位 · 危害因素（检测点）：结果 单位，限值 …
     * 空段落自动省略；总长限 200 字（与 rectifications.item_desc 校验一致）。
     */
    public static function rectifyDesc(array $it): string
    {
        $where = array_values(array_filter([
            trim((string)($it['workplace_name'] ?? '')),
            trim((string)($it['post_name'] ?? '')),
        ], static fn($v) => $v !== ''));
        $head = implode(' · ', $where);
        $hazard = trim((string)($it['hazard_name'] ?? ''));
        $point  = trim((string)($it['test_point'] ?? ''));
        $what = $hazard !== '' ? $hazard : '危害因素未指定';
        if ($point !== '') {
            $what .= '（' . $point . '）';
        }
        $result = trim((string)($it['result'] ?? ''));
        $unit   = trim((string)($it['unit'] ?? ''));
        $limit  = trim((string)($it['limit_value'] ?? ''));
        $tail = '';
        if ($result !== '') {
            $tail = '：' . $result . ($unit !== '' ? ' ' . $unit : '');
        }
        if ($limit !== '') {
            $tail .= ($tail !== '' ? '，' : '：') . '限值 ' . $limit;
        }
        $desc = ($head !== '' ? $head . ' · ' : '') . $what . $tail;
        if (mb_strlen($desc, 'UTF-8') > 200) {
            $desc = mb_substr($desc, 0, 199, 'UTF-8') . '…';
        }
        return $desc;
    }

    /**
     * 某报告的超标明细 → 整改草稿候选，并标出已存在者（同报告 + 同文字）。
     * 返回 [['desc' => string, 'exists' => bool], ...]；同一报告内文字重复的只保留一条。
     */
    public static function rectifyCandidates(int $reportId): array
    {
        $have = [];
        foreach (Database::rows('SELECT item_desc FROM rectifications WHERE report_id = ?', [$reportId]) as $r) {
            $have[(string)$r['item_desc']] = true;
        }
        $seen = [];
        $out = [];
        foreach (self::forReport($reportId) as $it) {
            if ((string)$it['verdict'] !== '超标') {
                continue;
            }
            $desc = self::rectifyDesc($it);
            if (isset($seen[$desc])) {
                continue;
            }
            $seen[$desc] = true;
            $out[] = ['desc' => $desc, 'exists' => isset($have[$desc])];
        }
        return $out;
    }

    /**
     * 把某报告的超标点生成整改记录草稿（只新增，已存在跳过，可重复调用）。
     * 返回 ['added' => 新增条数, 'skipped' => 已存在条数, 'total' => 超标点数]。
     */
    public static function generateRectifications(int $reportId): array
    {
        $cands = self::rectifyCandidates($reportId);
        $added = 0;
        $skipped = 0;
        Database::tx(function () use ($cands, $reportId, &$added, &$skipped) {
            $ts = now_str();
            foreach ($cands as $c) {
                if ($c['exists']) {
                    $skipped++;
                    continue;
                }
                Database::run(
                    "INSERT INTO rectifications (report_id, item_desc, plan, done_date, verify_report_id, status, remark, created_at, updated_at)
                     VALUES (?, ?, '', '', NULL, '整改中', '', ?, ?)",
                    [$reportId, $c['desc'], $ts, $ts]
                );
                $added++;
            }
        });
        return ['added' => $added, 'skipped' => $skipped, 'total' => count($cands)];
    }

    /** 判定徽标（列表 / 打印共用） */
    public static function verdictBadge(?string $verdict): array
    {
        $v = (string)$verdict;
        if ($v === '合格') {
            return ['合格', 'bg-green-lt'];
        }
        if ($v === '超标') {
            return ['超标', 'bg-red-lt'];
        }
        return ['未判定', 'bg-secondary-lt'];
    }
}
