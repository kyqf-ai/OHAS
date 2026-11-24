<?php
/**
 * 职业健康检查记录（表6-3 历次职业健康检查结果及处理情况）。
 * 检查种类与结论口径依据 GBZ 188-2025《职业健康监护技术规范》
 * （2026-08-01 起施行，代替 GBZ 188-2014）：
 *  - 法定检查四类：上岗前 / 在岗期间 / 离岗时 / 应急健康检查；
 *  - 结论四类：目前未见异常 / 疑似职业病 / 职业禁忌证 / 其他疾病或异常，
 *    另含职业病诊断结论；「复查」不再作为体检结论（由机构安排补充检查）。
 */
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class ExamRecord extends Model
{
    protected static string $table = 'exam_records';

    /**
     * 需生成职业病登记的“重点结论”（不等于异常全量口径）。
     * 异常全量统一走 abnormalWhere()：已出结论且不是「目前未见异常/未见异常」。
     */
    public const ABNORMAL = ['疑似职业病', '职业禁忌证', '职业病'];
    public const DISEASE_CONCLUSIONS = ['疑似职业病', '职业病'];

    /** 不算异常的结论（含历史口径「未见异常」）；「待出结论」存空串，不落库 */
    public const NORMAL = ['', '目前未见异常', '未见异常'];

    /**
     * 天然面向已离岗人员的检查种类：人事可能先办离职再做离岗体检/随访/诊断，
     * 这几类不能再按 status='在职' 过滤。其他种类仍允许“有接害史的非在职人员”补检。
     */
    public const OFF_JOB_TYPES = ['离岗时', '离岗后医学随访', '职业病诊断', '复查', '应急健康检查'];
    /** 法定书面告知形式（职业病防治法第35条；电话/当面/其他不计入闭环） */
    public const WRITTEN_NOTICE = '书面签收';

    /** 受检人查询条件（SQL 片段，$alias 为 workers 表别名） */
    public static function candidateWhere(string $examType, string $alias = 'w'): string
    {
        if (in_array(trim($examType), self::OFF_JOB_TYPES, true)) {
            return '1=1';
        }
        return "({$alias}.status = '在职' OR EXISTS (
            SELECT 1 FROM worker_posts wp
             WHERE wp.worker_id = {$alias}.id
               AND (TRIM(COALESCE(wp.hazards_snapshot,'')) <> ''
                    OR EXISTS (SELECT 1 FROM post_hazards ph WHERE ph.post_id = wp.post_id))))";
    }

    /** 单条记录/批量生成时的受检人资格判定（与 candidateWhere 同口径） */
    public static function canExamine(array $worker, string $examType): bool
    {
        if (in_array(trim($examType), self::OFF_JOB_TYPES, true)) {
            return true;
        }
        if (($worker['status'] ?? '') === '在职') {
            return true;
        }
        $wid = (int)($worker['id'] ?? 0);
        return $wid > 0 && (int)Database::value(
            'SELECT 1 FROM worker_posts wp
              WHERE wp.worker_id = ?
                AND (TRIM(COALESCE(wp.hazards_snapshot,\'\')) <> \'\'
                     OR EXISTS (SELECT 1 FROM post_hazards ph WHERE ph.post_id = wp.post_id))
              LIMIT 1',
            [$wid]
        ) > 0;
    }

    /** 异常结果条件（单一口径，供列表 / 档案目录 / 打印共用；$col 为 SQL 列引用表达式） */
    public static function abnormalWhere(string $col): string
    {
        return "TRIM(COALESCE({$col},'')) NOT IN ('', '目前未见异常', '未见异常')";
    }

    /** 结论是否属于异常（与 abnormalWhere 同口径） */
    public static function isAbnormal(string $conclusion): bool
    {
        return !in_array(trim($conclusion), ['', '目前未见异常', '未见异常'], true);
    }

    /**
     * 处置状态（职业病防治法第35、36、71条；WS/T 880 §8.4.1）：
     *   register   待登记：疑似职业病/职业病未生成表5-3/5-4 登记
     *   pending    待处置：未填写人员处理情况
     *   notice     待告知：未填告知方式或本人签字日期
     *   nonwritten 已告知（非书面）：电话/当面/其他告知，依法不能计入闭环
     *   notice_doc 待告知书原件：书面签收但未挂 W_EXAM_NOTICE 原件
     *   transfer   待调离：职业禁忌证且仍有未结束的接害在岗职业史段、无调岗材料
     *   closed     已闭环：登记、处理、书面告知、签字、调离均完整
     * 仅对异常结论有意义；正常结论返回 null。
     */
    public static function dispositionStatus(array $row): ?array
    {
        if (!self::isAbnormal((string)($row['conclusion'] ?? ''))) {
            return null;
        }
        if (self::diseaseKind((string)($row['conclusion'] ?? '')) !== null) {
            $hasReg = array_key_exists('disease_id', $row)
                ? (int)($row['disease_id'] ?? 0) > 0
                : (int)Database::value('SELECT COUNT(*) FROM diseases WHERE exam_record_id = ?', [(int)($row['id'] ?? 0)]) > 0;
            if (!$hasReg) {
                return ['key' => 'register', 'label' => '待登记', 'class' => 'bg-orange-lt'];
            }
        }
        $hasHandling = trim((string)($row['handling'] ?? '')) !== '';
        $hasSigned   = trim((string)($row['signed_at'] ?? '')) !== '';
        $hasNotice   = trim((string)($row['notice_method'] ?? '')) !== '';
        if (!$hasHandling) {
            return ['key' => 'pending', 'label' => '待处置', 'class' => 'bg-red-lt'];
        }
        if (!$hasSigned || !$hasNotice) {
            return ['key' => 'notice', 'label' => '待告知', 'class' => 'bg-orange-lt'];
        }
        // 第35条要求书面告知：电话/当面/其他即使有签字也不得显示“已闭环”
        if (!str_contains((string)$row['notice_method'], '书面')) {
            return ['key' => 'nonwritten', 'label' => '已告知（非书面）', 'class' => 'bg-orange-lt'];
        }
        $hasDoc = array_key_exists('notice_doc_count', $row)
            ? (int)$row['notice_doc_count'] > 0
            : (int)Database::value(
                "SELECT COUNT(*) FROM documents WHERE doc_type = 'W_EXAM_NOTICE'
                   AND link_type = 'exam_record' AND link_id = ?",
                [(int)($row['id'] ?? 0)]
            ) > 0;
        if (!$hasDoc) {
            return ['key' => 'notice_doc', 'label' => '待告知书原件', 'class' => 'bg-orange-lt'];
        }
        // 第35条：禁忌证须调离原岗位；仍在接害在岗段且无调岗材料时不得闭环
        if (self::tabooPending($row)) {
            return ['key' => 'transfer', 'label' => '待调离', 'class' => 'bg-red-lt'];
        }
        return ['key' => 'closed', 'label' => '已闭环', 'class' => 'bg-green-lt'];
    }

    /** 职业禁忌证是否尚未调离接害岗位（无未结束接害段，或已有同记录/同年度之后的调岗材料视为已调离） */
    private static function tabooPending(array $row): bool
    {
        if ((string)($row['conclusion'] ?? '') !== '职业禁忌证') {
            return false;
        }
        $wid = (int)($row['worker_id'] ?? 0);
        if ($wid <= 0) {
            return false;
        }
        $open = (int)Database::value(
            "SELECT 1 FROM worker_posts wp
              WHERE wp.worker_id = ? AND (wp.end_date IS NULL OR TRIM(wp.end_date) = '')
                AND (TRIM(COALESCE(wp.hazards_snapshot,'')) <> ''
                     OR EXISTS (SELECT 1 FROM post_hazards ph WHERE ph.post_id = wp.post_id))
              LIMIT 1",
            [$wid]
        );
        if (!$open) {
            return false;
        }
        $hasTransfer = (int)Database::value(
            "SELECT 1 FROM documents d
              WHERE d.doc_type = 'W_TRANSFER'
                AND ((d.link_type = 'exam_record' AND d.link_id = ?)
                     OR (d.link_type = 'worker' AND d.link_id = ?
                         AND COALESCE(d.period_year, CAST(substr(d.doc_date,1,4) AS INTEGER))
                             >= CAST(substr(?,1,4) AS INTEGER)))
              LIMIT 1",
            [(int)($row['id'] ?? 0), $wid, (string)($row['exam_date'] ?? '')]
        );
        return !$hasTransfer;
    }

    /** 未生成职业病登记 WHEN 条件（$alias 为 exam_records 的别名或表名） */
    private static function unregisteredWhen(string $alias): string
    {
        return "{$alias}.conclusion IN ('疑似职业病','职业病')
                AND NOT EXISTS (SELECT 1 FROM diseases d WHERE d.exam_record_id = {$alias}.id)";
    }

    /** 书面告知方式（兼容历史自由文本中的“书面/书面告知”） */
    private static function writtenSql(string $alias): string
    {
        return "INSTR({$alias}.notice_method, '书面') > 0";
    }

    /** 该体检记录已挂体检结果书面告知原件 */
    private static function noticeDocSql(string $alias): string
    {
        return "EXISTS (SELECT 1 FROM documents d WHERE d.doc_type = 'W_EXAM_NOTICE'
                  AND d.link_type = 'exam_record' AND d.link_id = {$alias}.id)";
    }

    /** 职业禁忌证尚未调离：仍有接害在岗段且无同记录/同年度之后的调岗材料 */
    private static function tabooPendingSql(string $alias): string
    {
        return "{$alias}.conclusion = '职业禁忌证'
            AND EXISTS (SELECT 1 FROM worker_posts wp
                 WHERE wp.worker_id = {$alias}.worker_id
                   AND (wp.end_date IS NULL OR TRIM(wp.end_date) = '')
                   AND (TRIM(COALESCE(wp.hazards_snapshot,'')) <> ''
                        OR EXISTS (SELECT 1 FROM post_hazards ph WHERE ph.post_id = wp.post_id)))
            AND NOT EXISTS (SELECT 1 FROM documents d
                 WHERE d.doc_type = 'W_TRANSFER'
                   AND ((d.link_type = 'exam_record' AND d.link_id = {$alias}.id)
                        OR (d.link_type = 'worker' AND d.link_id = {$alias}.worker_id
                            AND COALESCE(d.period_year, CAST(substr(d.doc_date,1,4) AS INTEGER))
                                >= CAST(substr({$alias}.exam_date,1,4) AS INTEGER))))";
    }

    /** 异常结果处置统计（表5-2 快捷筛选 + 工作台提醒口径；$year 非空时只统计该年度） */
    public static function dispositionStats(string $year = ''): array
    {
        $abn = self::abnormalWhere('conclusion');
        $unreg = self::unregisteredWhen('exam_records');
        $params = [];
        $yearSql = '';
        if ($year !== '') {
            $yearSql = ' AND exam_date LIKE :yr';
            $params[':yr'] = $year . '%';
        }
        $base = "NOT ({$unreg}) AND TRIM(COALESCE(handling,'')) <> '' AND TRIM(COALESCE(signed_at,'')) <> '' AND TRIM(COALESCE(notice_method,'')) <> ''";
        $written = self::writtenSql('exam_records');
        $noticeDoc = self::noticeDocSql('exam_records');
        $tabooPending = self::tabooPendingSql('exam_records');
        $row = Database::row(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN {$unreg} THEN 1 ELSE 0 END) AS register,
                    SUM(CASE WHEN NOT ({$unreg}) AND TRIM(COALESCE(handling,'')) = '' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN NOT ({$unreg}) AND TRIM(COALESCE(handling,'')) <> ''
                              AND (TRIM(COALESCE(signed_at,'')) = '' OR TRIM(COALESCE(notice_method,'')) = '') THEN 1 ELSE 0 END) AS notice,
                    SUM(CASE WHEN {$base} AND NOT ({$written}) THEN 1 ELSE 0 END) AS nonwritten,
                    SUM(CASE WHEN {$base} AND {$written} AND NOT ({$noticeDoc}) THEN 1 ELSE 0 END) AS notice_doc,
                    SUM(CASE WHEN {$base} AND {$written} AND {$noticeDoc} AND ({$tabooPending}) THEN 1 ELSE 0 END) AS transfer,
                    SUM(CASE WHEN {$base} AND {$written} AND {$noticeDoc} AND NOT ({$tabooPending}) THEN 1 ELSE 0 END) AS closed
             FROM exam_records WHERE {$abn}{$yearSql}",
            $params
        );
        return [
            'total'   => (int)($row['total'] ?? 0),
            'register'=> (int)($row['register'] ?? 0),
            'pending' => (int)($row['pending'] ?? 0),
            'notice'  => (int)($row['notice'] ?? 0),
            'nonwritten' => (int)($row['nonwritten'] ?? 0),
            'notice_doc' => (int)($row['notice_doc'] ?? 0),
            'transfer' => (int)($row['transfer'] ?? 0),
            'closed'  => (int)($row['closed'] ?? 0),
        ];
    }

    /** 记录列表：批次（可选）+ 姓名/档案号关键字 + 结论筛选 */
    public static function search(int $batchId, string $q, string $conclusion, int $page, int $size, bool $abnormal = false, string $disposition = ''): array
    {
        $where = ['1=1'];
        $params = [];
        if ($batchId > 0) {
            $where[] = 'r.batch_id = :bid';
            $params[':bid'] = $batchId;
        }
        if ($q !== '') {
            $where[] = '(w.name LIKE :q1 OR w.archive_no LIKE :q2 OR w.id_card LIKE :q3)';
            $params[':q1'] = '%' . $q . '%';
            $params[':q2'] = '%' . $q . '%';
            $params[':q3'] = '%' . $q . '%';
        }
        if ($abnormal) {
            // 表5-2 口径：已出结论且不是「未见异常」（历史口径「未见异常」同样不算异常）
            $where[] = ExamRecord::abnormalWhere('r.conclusion');
        }
        if ($conclusion !== '') {
            if ($conclusion === '待出结论') {
                $where[] = "(r.conclusion IS NULL OR r.conclusion = '')";
            } else {
                $where[] = 'r.conclusion = :cl';
                $params[':cl'] = $conclusion;
            }
        }
        if ($abnormal && in_array($disposition, ['register', 'pending', 'notice', 'nonwritten', 'notice_doc', 'transfer', 'closed'], true)) {
            $unreg = self::unregisteredWhen('r');
            $base = "NOT ({$unreg}) AND TRIM(COALESCE(r.handling,'')) <> '' AND TRIM(COALESCE(r.signed_at,'')) <> '' AND TRIM(COALESCE(r.notice_method,'')) <> ''";
            $written = self::writtenSql('r');
            $noticeDoc = self::noticeDocSql('r');
            $tabooPending = self::tabooPendingSql('r');
            $where[] = match ($disposition) {
                'register'  => $unreg,
                'pending'   => "NOT ({$unreg}) AND TRIM(COALESCE(r.handling,'')) = ''",
                'notice'    => "NOT ({$unreg}) AND TRIM(COALESCE(r.handling,'')) <> '' AND (TRIM(COALESCE(r.signed_at,'')) = '' OR TRIM(COALESCE(r.notice_method,'')) = '')",
                'nonwritten'=> "{$base} AND NOT ({$written})",
                'notice_doc'=> "{$base} AND {$written} AND NOT ({$noticeDoc})",
                'transfer'  => "{$base} AND {$written} AND {$noticeDoc} AND ({$tabooPending})",
                'closed'    => "{$base} AND {$written} AND {$noticeDoc} AND NOT ({$tabooPending})",
            };
        }
        $w = implode(' AND ', $where);
        $total = (int)Database::value(
            "SELECT COUNT(*) FROM exam_records r JOIN workers w ON w.id = r.worker_id WHERE {$w}",
            $params
        );
        $pages = max(1, (int)ceil($total / $size));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $size;
        $rows = Database::rows(
            "SELECT r.*, w.name AS worker_name, w.archive_no AS worker_archive_no, w.status AS worker_status,
                    (SELECT exam_date FROM exam_batches b WHERE b.id = r.batch_id) AS batch_date,
                    COALESCE(NULLIF(TRIM(r.agency),''), (SELECT b.agency FROM exam_batches b WHERE b.id = r.batch_id)) AS agency,
                    COALESCE(NULLIF(TRIM(r.exam_type),''), (SELECT b.exam_type FROM exam_batches b WHERE b.id = r.batch_id)) AS exam_type,
                    (SELECT d.id FROM diseases d WHERE d.exam_record_id = r.id LIMIT 1) AS disease_id,
                    (SELECT COUNT(*) FROM documents d WHERE d.doc_type = 'W_EXAM_NOTICE'
                       AND d.link_type = 'exam_record' AND d.link_id = r.id) AS notice_doc_count,
                    " . \App\Helpers\DocLink::summarySql('exam_record', 'r.id') . "
             FROM exam_records r JOIN workers w ON w.id = r.worker_id
             WHERE {$w}
             ORDER BY r.exam_date DESC, r.id DESC
             LIMIT {$size} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** 某劳动者的历次检查记录（信息卡查看页 / 表6-3 打印） */
    public static function forWorker(int $workerId): array
    {
        return Database::rows(
            "SELECT r.*, b.exam_type AS batch_type, b.agency AS batch_agency, b.exam_date AS batch_date,
                    COALESCE(NULLIF(TRIM(r.agency),''), b.agency) AS agency,
                    COALESCE(NULLIF(TRIM(r.exam_type),''), b.exam_type) AS exam_type,
                    " . \App\Helpers\DocLink::summarySql('exam_record', 'r.id') . '
             FROM exam_records r
             LEFT JOIN exam_batches b ON b.id = r.batch_id
             WHERE r.worker_id = ?
             ORDER BY r.exam_date DESC, r.id DESC',
            [$workerId]
        );
    }

    /** 按年度分组（近年在前）：['2026' => [...], '2025' => [...]] */
    public static function forWorkerGrouped(int $workerId): array
    {
        $grouped = [];
        foreach (self::forWorker($workerId) as $row) {
            $y = mb_substr((string)($row['exam_date'] ?: $row['batch_date'] ?: ''), 0, 4);
            if ($y === '') {
                $y = '未填日期';
            }
            $grouped[$y][] = $row;
        }
        return $grouped;
    }

    /** 最近的异常结论记录（工作台提醒，异常口径与表5-2 完全一致） */
    public static function abnormalRecent(int $limit = 5): array
    {
        $abn = self::abnormalWhere('r.conclusion');
        return Database::rows(
            "SELECT r.id, r.worker_id, r.exam_date, r.conclusion,
                    COALESCE(NULLIF(TRIM(r.exam_type),''), (SELECT b.exam_type FROM exam_batches b WHERE b.id = r.batch_id)) AS exam_type,
                    COALESCE(NULLIF(TRIM(r.agency),''), (SELECT b.agency FROM exam_batches b WHERE b.id = r.batch_id)) AS agency,
                    r.handling, r.signed_at, r.notice_method,
                    (SELECT d.id FROM diseases d WHERE d.exam_record_id = r.id LIMIT 1) AS disease_id,
                    (SELECT COUNT(*) FROM documents d WHERE d.doc_type = 'W_EXAM_NOTICE'
                       AND d.link_type = 'exam_record' AND d.link_id = r.id) AS notice_doc_count,
                    w.name AS worker_name, w.archive_no AS worker_archive_no
             FROM exam_records r JOIN workers w ON w.id = r.worker_id
             WHERE {$abn}
             ORDER BY r.exam_date DESC, r.id DESC LIMIT " . max(1, $limit)
        );
    }

    /**
     * 异常结论总数（异常 = 已出结论且不是「目前未见异常/未见异常」，全站唯一口径）。
     * $year 非空时按体检年份过滤，与工作台相邻年度口径卡片（今年检测报告/今年体检批次）一致。
     */
    public static function abnormalCount(string $year = ''): int
    {
        $abn = self::abnormalWhere('conclusion');
        $params = [];
        $sql = "SELECT COUNT(*) FROM exam_records WHERE {$abn}";
        if ($year !== '') {
            $sql .= ' AND exam_date LIKE ?';
            $params[] = $year . '%';
        }
        return (int)Database::value($sql, $params);
    }

    /**
     * 受检时岗位与接害快照（生成记录时自动带出）。
     * - $examDate 给定时：取该日期在岗的本单位职业史段（start ≤ 日期 ≤ 结束或至今），
     *   补录历史批次 / 中途换岗时能带出「受检当时」的岗位；
     * - 未给日期：取当前在岗段；都取不到时退回最近一段。
     * 只取本单位经历（post_id 非空），外单位经历不作为本单位受检岗位。
     */
    public static function snapshotFor(int $workerId, ?string $examDate = null): array
    {
        $row = null;
        $date = trim((string)$examDate);
        if ($date !== '') {
            $row = Database::row(
                'SELECT post_name, hazards_snapshot FROM worker_posts
                 WHERE worker_id = ? AND post_id IS NOT NULL
                   AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)
                 ORDER BY id DESC LIMIT 1',
                [$workerId, $date, $date]
            );
        }
        if ($row === null) {
            $row = Database::row(
                'SELECT post_name, hazards_snapshot FROM worker_posts
                 WHERE worker_id = ? AND post_id IS NOT NULL AND end_date IS NULL
                 ORDER BY id DESC LIMIT 1',
                [$workerId]
            );
        }
        if ($row === null) {
            $row = Database::row(
                'SELECT post_name, hazards_snapshot FROM worker_posts
                 WHERE worker_id = ? AND post_id IS NOT NULL
                 ORDER BY end_date DESC, id DESC LIMIT 1',
                [$workerId]
            );
        }
        return [
            'post_name' => (string)($row['post_name'] ?? ''),
            'hazards_snapshot' => (string)($row['hazards_snapshot'] ?? ''),
        ];
    }

    /** 结论 → 职业病登记性质：疑似职业病=1（表5-4），职业病=0（表5-3），其余 null（不适用） */
    public static function diseaseKind(?string $conclusion): ?int
    {
        $c = trim((string)$conclusion);
        if ($c === '疑似职业病') {
            return 1;
        }
        if ($c === '职业病') {
            return 0;
        }
        return null;
    }

    /** 结论为疑似职业病/职业病、但尚未生成 5-3/5-4 登记的体检记录数（两表对数用） */
    public static function pendingDiseaseCount(?int $isSuspected = null): int
    {
        $cond = $isSuspected === null ? "r.conclusion IN ('疑似职业病','职业病')"
            : ($isSuspected === 1 ? "r.conclusion = '疑似职业病'" : "r.conclusion = '职业病'");
        return (int)Database::value(
            "SELECT COUNT(*) FROM exam_records r
             WHERE {$cond} AND NOT EXISTS (SELECT 1 FROM diseases d WHERE d.exam_record_id = r.id)"
        );
    }

    /** 结论徽标（列表 / 时间线共用） */
    public static function conclusionBadge(?string $conclusion): array
    {
        $c = (string)$conclusion;
        if ($c === '') {
            return ['label' => '待出结论', 'class' => 'bg-secondary-lt'];
        }
        $map = [
            '目前未见异常'   => ['目前未见异常', 'bg-green-lt'],
            '疑似职业病'     => ['疑似职业病', 'bg-red-lt'],
            '职业禁忌证'     => ['职业禁忌证', 'bg-orange-lt'],
            '其他疾病或异常' => ['其他疾病或异常', 'bg-blue-lt'],
            '职业病'         => ['职业病', 'bg-red text-white'],
        ];
        return $map[$c] ?? [$c, 'bg-secondary-lt'];
    }

    /** 某批次已有记录的劳动者 ID 集（应检名单排除用） */
    public static function workerIdsOfBatch(int $batchId): array
    {
        return array_map(
            'intval',
            array_column(
                Database::rows('SELECT worker_id FROM exam_records WHERE batch_id = ?', [$batchId]),
                'worker_id'
            )
        );
    }
}
