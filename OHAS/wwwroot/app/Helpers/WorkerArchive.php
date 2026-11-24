<?php
/**
 * 个人资料全景：输入一个劳动者，输出其历年全部资料的归档状态。
 *
 * WorkerArchive::build($workerId, $range, $asOf) 返回：
 *   header   基本信息 + 当前岗位 + 职务角色 + 接害属性（当前）
 *   once     一次性/长期资料（W_CONTRACT W_NOTICE W_APPOINT W_DUTY）
 *   years    每年一行：posts / hazards / exposed / cells[type] = {state, docs[], hint, ...}
 *   events   事件类（W_TRANSFER W_RECHECK W_SUSPECT W_DIAG W_COPY_SIGN），按日期倒序
 *   others   W_OTHER、未分类及关联到此人但类型不在矩阵里的文档
 *   summary  各状态格子计数（仅计数，无得分）
 *
 * 状态：done / done_indirect / reg_only / todo / na / none（全站不出现分数与百分比）。
 * 年度判定统一用 SQL 片段 COALESCE(period_year, NULLIF(CAST(substr(doc_date,1,4) AS INTEGER),0))。
 */
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

class WorkerArchive
{
    /** 文档年度 SQL 片段（全站唯一口径） */
    public const DOC_YEAR_SQL = 'COALESCE(d.period_year, NULLIF(CAST(substr(d.doc_date,1,4) AS INTEGER),0))';

    /**
     * 年度矩阵列 / 一次性 / 事件类：统一由 DocTypes 注册表派生。
     * 保留这三个静态方法供视图与控制器调用（原常量已移除，避免两处维护）。
     */
    public static function yearColumns(): array { return DocTypes::yearColumns(); }
    public static function onceTypes(): array { return DocTypes::onceTypes(); }
    public static function eventTypes(): array { return DocTypes::eventTypes(); }

    /**
     * 某年份的异常线索是否已有同年份或更晚的对应材料覆盖。
     * 无年度信息的历史材料不覆盖有明确日期的新线索，避免“挂过一份旧调岗通知就永远不再提醒”。
     */
    private static function clueCovered(int $year, array $docs): bool
    {
        foreach ($docs as $d) {
            $dy = (int)($d['y'] ?? 0);
            if ($dy > 0 && ($year <= 0 || $dy >= $year)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 年度格子状态（build 单人视图与 yearStates 批量视图共用唯一判定）。
     * $ev：has_doc 有原件 / has_reg 有登记记录 / has_indirect_doc 间接报告带原件 /
     *      has_indirect_reg 间接报告仅登记。
     */
    public static function yearState(string $type, array $ev): string
    {
        $hasDoc = !empty($ev['has_doc']);
        $hasReg = !empty($ev['has_reg']);
        switch ($type) {
            case 'X_DETECT':
                // 统一走 DetectionItem::reportState：有原件→done_indirect，有报告无原件→reg_only，无→todo
                if (!empty($ev['has_indirect_reg']) || !empty($ev['has_indirect_doc'])) {
                    return \App\Models\DetectionItem::reportState([
                        ['has_doc' => !empty($ev['has_indirect_doc'])],
                    ]);
                }
                return 'todo';
            case 'W_EXAM':
            case 'W_TRAIN':
                return $hasDoc ? 'done' : ($hasReg ? 'reg_only' : 'todo');
            case 'W_EXAM_NOTICE':
            case 'W_TRAIN_MGR':
                return $hasDoc ? 'done' : 'todo';
            case 'W_PPE':
                return $hasDoc ? 'done' : 'reg_only';
        }
        return 'none';
    }

    /**
     * 构建个人资料全景。
     * @param string $range 3y | 5y | all（只截取显示，不影响状态判定起点）
     * @param string|null $asOf 截止日期（默认今天；自测传固定值）
     */
    public static function build(int $workerId, string $range = '3y', ?string $asOf = null): ?array
    {
        $worker = Database::row('SELECT * FROM workers WHERE id = ?', [$workerId]);
        if ($worker === null) {
            return null;
        }
        $asOf = $asOf ?: date('Y-m-d');
        $asOfYear = (int)substr($asOf, 0, 4);
        $roles = array_values(array_filter(array_map('trim', explode(',', (string)$worker['duty_role']))));
        $hasRole = $roles !== [];

        /* ---------- 关联文档（本人 + 本人体检记录 + 本人职业病登记） ---------- */
        $examIds = array_map('intval', array_column(
            Database::rows('SELECT id FROM exam_records WHERE worker_id = ?', [$workerId]),
            'id'
        ));
        $diseaseIds = array_map('intval', array_column(
            Database::rows('SELECT id FROM diseases WHERE worker_id = ?', [$workerId]),
            'id'
        ));
        $docWhere = "(d.link_type = 'worker' AND d.link_id = ?)";
        $docParams = [$workerId];
        if ($examIds) {
            $docWhere .= " OR (d.link_type = 'exam_record' AND d.link_id IN (" . implode(',', $examIds) . "))";
        }
        if ($diseaseIds) {
            $docWhere .= " OR (d.link_type = 'disease' AND d.link_id IN (" . implode(',', $diseaseIds) . "))";
        }
        $docs = Database::rows(
            'SELECT d.*, ' . self::DOC_YEAR_SQL . ' AS y FROM documents d WHERE ' . $docWhere . ' ORDER BY d.id DESC',
            $docParams
        );
        foreach ($docs as &$dc) {
            $dc['y'] = (int)$dc['y'];
            $dc['type_name'] = DocTypes::nameOf((string)$dc['doc_type']);
        }
        unset($dc);

        // 按类型 × 年度 / 挂载方式分组（y=0 表示无任何年度信息，归入“其他资料”）
        $byTypeYear = [];       // doc_type => year => [docs]（挂在本人）
        $byExamYear = [];       // 体检记录挂载：年 => [docs]
        $unclassified = [];     // 本人挂载且未分类
        $noYear = [];           // 有类型但无年度信息（展示到“其他资料”，避免丢失）
        foreach ($docs as $dc) {
            if ($dc['link_type'] === 'exam_record') {
                $byExamYear[$dc['y']][] = $dc;
                continue;
            }
            if ($dc['link_type'] === 'disease') {
                continue;   // 事件区单独展示
            }
            $t = (string)$dc['doc_type'];
            if ($t === '') {
                $unclassified[] = $dc;
                continue;
            }
            if ((int)$dc['y'] === 0) {
                $noYear[] = $dc;
                continue;
            }
            $byTypeYear[$t][$dc['y']][] = $dc;
        }

        /* ---------- 职业史与年度接害属性 ---------- */
        $history = Database::rows(
            'SELECT wp.*, p.name AS post_real_name FROM worker_posts wp LEFT JOIN posts p ON p.id = wp.post_id
             WHERE wp.worker_id = ? ORDER BY wp.start_date ASC, wp.id ASC',
            [$workerId]
        );
        $hazardPosts = Exposure::hazardPosts();
        $overlap = function (array $h, int $year) use ($asOfYear): bool {
            $endYear = !empty($h['end_date'])
                ? (int)substr((string)$h['end_date'], 0, 4) : $asOfYear;
            return (int)substr((string)$h['start_date'], 0, 4) <= $year && $year <= $endYear;
        };
        $yearPosts = [];    // year => [岗位段]
        $exposed = [];      // year => bool
        $range2 = self::rangeYears($worker, $history, $docs, $asOf);
        for ($y = $range2['start']; $y <= $range2['end']; $y++) {
            foreach ($history as $h) {
                if ($overlap($h, $y)) {
                    $yearPosts[$y][] = $h;
                }
            }
            $exposed[$y] = Exposure::exposedInYear($yearPosts[$y] ?? [], $y, $asOfYear, $hazardPosts);
        }
        $everExposed = in_array(true, $exposed, true);

        /* ---------- 登记证据 ---------- */
        $examYears = [];    // 体检记录：年 => [记录]
        foreach (Database::rows('SELECT id, exam_date, exam_type, conclusion FROM exam_records WHERE worker_id = ? ORDER BY exam_date DESC', [$workerId]) as $r) {
            $examYears[(int)substr((string)$r['exam_date'], 0, 4)][] = $r;
        }
        $trainYears = [];   // 参训记录：年 => [培训]
        foreach (Database::rows(
            'SELECT t.id, t.title, t.trained_on, t.type FROM training_participants tp JOIN trainings t ON t.id = tp.training_id
             WHERE tp.worker_id = ? ORDER BY t.trained_on DESC',
            [$workerId]
        ) as $r) {
            $trainYears[(int)substr((string)$r['trained_on'], 0, 4)][] = $r;
        }
        $ppeYears = [];     // 领用记录：年 => 条数
        foreach (Database::rows('SELECT issued_on, item_name, quantity FROM ppe_issues WHERE worker_id = ?', [$workerId]) as $r) {
            $y = (int)substr((string)$r['issued_on'], 0, 4);
            $ppeYears[$y] = ($ppeYears[$y] ?? 0) + 1;
        }

        /* ---------- 间接汇入的检测报告 ---------- */
        $indirect = [];     // year => [报告行（含 has_doc），按报告去重、因素合并]
        $reportRows = \App\Models\DetectionItem::reportIdsForWorker($workerId);
        $reportIds = array_values(array_unique(array_map(fn ($r) => (int)$r['id'], $reportRows)));
        $reportDocs = [];
        if ($reportIds) {
            foreach (Database::rows(
                "SELECT d.link_id AS rid, COUNT(*) AS c FROM documents d
                 WHERE d.link_type = 'detection_report' AND d.link_id IN (" . implode(',', $reportIds) . ')
                 GROUP BY d.link_id',
                []
            ) as $r) {
                $reportDocs[(int)$r['rid']] = (int)$r['c'];
            }
        }
        $byReport = [];
        foreach ($reportRows as $r) {
            $rid = (int)$r['id'];
            if (!isset($byReport[$rid])) {
                $byReport[$rid] = $r;
            } elseif ($r['hazard_name'] && !str_contains((string)$byReport[$rid]['hazard_name'], (string)$r['hazard_name'])) {
                $byReport[$rid]['hazard_name'] .= '、' . $r['hazard_name'];
            }
        }
        foreach ($byReport as $r) {
            $r['has_doc'] = ($reportDocs[(int)$r['id']] ?? 0) > 0;
            $indirect[(int)substr((string)$r['test_date'], 0, 4)][] = $r;
        }
        // 体检批次文档（间接参考，不让体检格变已归档）
        $batchRef = [];
        if ($examIds) {
            $bids = array_values(array_filter(array_map('intval', array_column(
                Database::rows('SELECT DISTINCT batch_id FROM exam_records WHERE worker_id = ? AND batch_id IS NOT NULL', [$workerId]),
                'batch_id'
            ))));
            if ($bids) {
                foreach (Database::rows(
                    "SELECT b.id, b.exam_date, b.exam_type, d.title FROM exam_batches b
                     JOIN documents d ON d.link_type = 'exam_batch' AND d.link_id = b.id
                     WHERE b.id IN (" . implode(',', $bids) . ') ORDER BY b.exam_date DESC'
                ) as $r) {
                    $batchRef[(int)substr((string)$r['exam_date'], 0, 4)][] = $r;
                }
            }
        }

        /* ---------- 不适用标记 ---------- */
        $marks = [];    // "type|year" => reason
        foreach (Database::rows(
            "SELECT item_code, period_year, reason FROM archive_marks WHERE scope = 'worker' AND worker_id = ?",
            [$workerId]
        ) as $m) {
            $marks[(string)$m['item_code'] . '|' . (int)$m['period_year']] = (string)$m['reason'];
        }

        /* ---------- 年度矩阵 ---------- */
        $years = [];
        $summary = ['done' => 0, 'done_indirect' => 0, 'reg_only' => 0, 'todo' => 0, 'na' => 0, 'none' => 0];
        for ($y = $range2['start']; $y <= $range2['end']; $y++) {
            $cells = [];
            $postsTxt = [];
            $hazardsTxt = [];
            foreach ($yearPosts[$y] ?? [] as $h) {
                $postsTxt[] = $h['post_name'] ?: $h['post_real_name'];
                if (trim((string)$h['hazards_snapshot']) !== '') {
                    $hazardsTxt[] = $h['hazards_snapshot'];
                }
            }
            $isExposed = $exposed[$y] ?? false;

            foreach (self::yearColumns() as $type => $meta) {
                $cell = ['state' => 'none', 'docs' => [], 'hint' => '', 'count' => 0];
                // 适用性统一由 DocTypes 的 applies / require 驱动
                $evidence = match ($type) {
                    'W_EXAM_NOTICE' => !empty($examYears[$y]) || !empty($byTypeYear['W_EXAM'][$y]),
                    'W_PPE'         => !empty($ppeYears[$y]),
                    default         => true,
                };
                $applicable = DocTypes::appliesTo($type, [
                    'exposed' => $isExposed, 'role' => $hasRole, 'evidence' => $evidence,
                ]);
                if (isset($marks[$type . '|' . $y])) {
                    $cell['state'] = 'na';
                    $cell['hint'] = $marks[$type . '|' . $y];
                    $cells[$type] = $cell;
                    $summary['na']++;
                    continue;
                }
                if (!$applicable) {
                    $cells[$type] = $cell;   // none
                    $summary['none']++;
                    continue;
                }
                $ev = ['has_doc' => false, 'has_reg' => false,
                           'has_indirect_doc' => false, 'has_indirect_reg' => false];
                    switch ($type) {
                        case 'X_DETECT':
                            $reports = $indirect[$y] ?? [];
                            $withDoc = array_values(array_filter($reports, fn ($r) => $r['has_doc']));
                            $ev['has_indirect_doc'] = (bool)$withDoc;
                            $ev['has_indirect_reg'] = !$withDoc && (bool)$reports;
                            if ($withDoc) {
                                $cell['count'] = count($withDoc);
                                $src = $withDoc[0];
                                $cell['hint'] = '来源报告 ' . $src['report_no']
                                    . '（' . ($src['post_name'] ?: '按因素') . ($src['hazard_name'] ? ' · ' . $src['hazard_name'] : '') . '）';
                                $cell['reports'] = $withDoc;
                            } elseif ($reports) {
                                $cell['count'] = count($reports);
                                $cell['hint'] = '报告已登记但无原件';
                                $cell['reports'] = $reports;
                            }
                            break;
                        case 'W_EXAM':
                            $own = $byTypeYear['W_EXAM'][$y] ?? [];
                            $onExam = $byExamYear[$y] ?? [];
                            if ($own || $onExam) {
                                $ev['has_doc'] = true;
                                $cell['docs'] = array_merge($own, $onExam);
                                $cell['count'] = count($cell['docs']);
                            } else {
                                $ev['has_reg'] = !empty($examYears[$y]);
                            }
                            if (!empty($batchRef[$y])) {
                                $cell['ref'] = $batchRef[$y];   // 间接参考（不计入状态）
                            }
                            break;
                        case 'W_EXAM_NOTICE':
                        case 'W_TRAIN':
                        case 'W_TRAIN_MGR':
                        case 'W_PPE':
                            $own = $byTypeYear[$type][$y] ?? [];
                            $ev['has_doc'] = (bool)$own;
                            if ($own) {
                                $cell['docs'] = $own;
                                $cell['count'] = count($own);
                            } elseif ($type === 'W_TRAIN') {
                                $ev['has_reg'] = !empty($trainYears[$y]);
                            }
                            break;
                    }
                    $cell['state'] = self::yearState($type, $ev);
                    if ($cell['state'] === 'reg_only' && $cell['hint'] === '') {
                        $cell['hint'] = match ($type) {
                            'W_EXAM'      => '有体检记录，缺原件',
                            'W_TRAIN'     => '有参训记录，缺原件',
                            'X_DETECT'    => '报告已登记但无原件',
                            'W_PPE'       => '有领用登记（' . (int)($ppeYears[$y] ?? 0) . ' 条），缺签字原件',
                            default       => '有登记记录，缺原件',
                        };
                    }
                    $cells[$type] = $cell;
                    $summary[$cell['state']] = ($summary[$cell['state']] ?? 0) + 1;
            }

            $years[$y] = [
                'year'     => $y,
                'posts'    => implode('、', array_values(array_unique(array_filter($postsTxt)))),
                'hazards'  => implode('、', array_unique(array_filter($hazardsTxt))),
                'exposed'  => $isExposed,
                'cells'    => $cells,
            ];
        }

        // range 截取（状态判定按全区间，展示按 range）
        $shown = $years;
        if ($range === '3y' || $range === '5y') {
            $span = $range === '3y' ? 2 : 4;
            $minY = max($range2['start'], $range2['end'] - $span);
            $shown = array_filter($years, fn ($row) => $row['year'] >= $minY);
        }

        /* ---------- 一次性资料 ---------- */
        $once = [];
        foreach (self::onceTypes() as $type) {
            $item = ['type' => $type, 'name' => DocTypes::nameOf($type), 'docs' => [], 'state' => 'todo', 'hint' => ''];
            $all = [];
            foreach ($docs as $dc) {
                if ((string)$dc['doc_type'] === $type) {
                    $all[] = $dc;
                }
            }
            $applicable = DocTypes::appliesTo($type, [
                'exposed' => $everExposed, 'role' => $hasRole, 'evidence' => true,
            ]);
            if (isset($marks[$type . '|0'])) {
                $item['state'] = 'na';
                $item['hint'] = $marks[$type . '|0'];
            } elseif (!$applicable) {
                $item['state'] = 'none';
            } elseif ($all) {
                $item['state'] = 'done';
                $item['docs'] = $all;
                if ($type === 'W_CONTRACT') {
                    // 合同取 expire_date 最大的一份，已早于 asOf 则提示（只提示）
                    $latest = null;
                    foreach ($all as $dc) {
                        if ((string)$dc['expire_date'] !== '' && ($latest === null || strcmp((string)$dc['expire_date'], (string)$latest['expire_date']) > 0)) {
                            $latest = $dc;
                        }
                    }
                    if ($latest !== null && strcmp((string)$latest['expire_date'], $asOf) < 0) {
                        $item['hint'] = '合同已到期（' . $latest['expire_date'] . '），请核对续签资料';
                    }
                }
                // 新增或换入接害岗位段时，若没有同年度或更晚的告知书，给出线索提示
                if ($type === 'W_NOTICE') {
                    foreach ($history as $h) {
                        if (!\App\Helpers\Exposure::historyExposed($h, $hazardPosts)) {
                            continue;
                        }
                        $startYear = (int)substr((string)($h['start_date'] ?? ''), 0, 4);
                        if ($startYear > 0 && !self::clueCovered($startYear, $all)) {
                            $item['hint'] = '接害岗位段（' . $h['start_date'] . ' 起）未见覆盖的危害告知书，换岗后应重新告知';
                            break;
                        }
                    }
                }
            }
            $once[$type] = $item;
        }

        /* ---------- 事件类 ---------- */
        $events = [];
        foreach (self::eventTypes() as $type) {
            $list = [];
            foreach ($docs as $dc) {
                if ((string)$dc['doc_type'] === $type) {
                    $dc['from'] = $dc['link_type'] === 'disease' ? '职业病登记' : '本人';
                    $list[] = $dc;
                }
            }
            $events[$type] = ['type' => $type, 'name' => DocTypes::nameOf($type), 'docs' => $list, 'hint' => ''];
        }
        // 线索提示：职业禁忌证 → 调岗；疑似职业病 → 疑似诊断申请；复查体检 → 复查通知书
        $taboo = [];
        $suspect = [];
        $recheck = [];
        foreach ($examYears as $y => $list) {
            foreach ($list as $r) {
                if ((string)$r['conclusion'] === '职业禁忌证') {
                    $taboo[$y] = $r;
                }
                if ((string)$r['conclusion'] === '疑似职业病') {
                    $suspect[$y] = $r;
                }
                if ((string)$r['exam_type'] === '复查') {
                    $recheck[$y] = $r;
                }
            }
        }
        $diag = [];
        foreach (Database::rows('SELECT name, is_suspected, diag_date FROM diseases WHERE worker_id = ?', [$workerId]) as $dz) {
            if ((int)$dz['is_suspected'] === 1 && !empty($dz['diag_date'])) {
                $suspect[(int)substr((string)$dz['diag_date'], 0, 4)] = ['exam_date' => $dz['diag_date'], 'conclusion' => $dz['name']];
            }
            if ((int)$dz['is_suspected'] === 0 && !empty($dz['diag_date'])) {
                $diag[(int)substr((string)$dz['diag_date'], 0, 4)] = true;
            }
        }
        // 旧年份的调岗/诊断材料不能覆盖后来新出现的异常结论，按“结论年份 ≤ 材料年份”匹配
        foreach ([
            [$taboo, 'W_TRANSFER', ' 年体检结论为「职业禁忌证」，未见调岗通知书'],
            [$suspect, 'W_SUSPECT', ' 年有「疑似职业病」结论/登记，未见诊断申请材料'],
            [$recheck, 'W_RECHECK', ' 年有复查体检记录，未见复查通知书及记录'],
        ] as [$signals, $type, $hintText]) {
            krsort($signals);
            foreach ($signals as $y => $_) {
                $docs = $events[$type]['docs'];
                if ($type === 'W_SUSPECT') {
                    $docs = array_merge($docs, $events['W_DIAG']['docs']);
                }
                if (!self::clueCovered((int)$y, $docs)) {
                    $events[$type]['hint'] = $y . $hintText;
                    break;
                }
            }
        }
        // 确诊登记的诊断证明；离岗人员监护档案复印件签收
        krsort($diag);
        foreach ($diag as $y => $_) {
            if (!self::clueCovered((int)$y, $events['W_DIAG']['docs'])) {
                $events['W_DIAG']['hint'] = $y . ' 年有确诊职业病登记，未见诊断证明原件';
                break;
            }
        }
        if (($worker['status'] ?? '') !== '在职' && $everExposed && !$events['W_COPY_SIGN']['docs']) {
            $events['W_COPY_SIGN']['hint'] = '已离岗且有接害史，未见监护档案复印件签收记录';
        }

        /* ---------- 其他资料 ---------- */
        $others = [];
        foreach ($unclassified as $dc) {
            $dc['type_name'] = '未分类';
            $others[] = $dc;
        }
        foreach ($noYear as $dc) {
            $others[] = $dc;
        }
        $covered = array_merge(array_keys(self::yearColumns()), self::onceTypes(), self::eventTypes());
        foreach ($byTypeYear as $t => $byYear) {
            if (!in_array($t, $covered, true)) {   // W_OTHER、未知编码及未进矩阵的类型
                foreach ($byYear as $list) {
                    foreach ($list as $dc) {
                        $others[] = $dc;
                    }
                }
            }
        }

        /* ---------- 头部 ---------- */
        $currentPost = Database::value(
            'SELECT post_name FROM worker_posts WHERE worker_id = ? AND post_id IS NOT NULL AND end_date IS NULL ORDER BY id DESC LIMIT 1',
            [$workerId]
        ) ?? '';

        return [
            'worker'   => $worker,
            'header'   => [
                'name'        => (string)$worker['name'],
                'archive_no'  => (string)$worker['archive_no'],
                'id_card'     => (string)$worker['id_card'],
                'status'      => (string)$worker['status'],
                'current_post' => (string)$currentPost,
                'duty_role'   => $roles,
                'exposed_now' => $exposed[$asOfYear] ?? false,
                'entry_date'  => (string)$worker['entry_date'],
            ],
            'range'    => $range,
            'years'    => $shown,          // 截取后的展示区间
            'allYears' => $years,          // 全区间（导出/打印用）
            'once'     => $once,
            'events'   => $events,
            'others'   => $others,
            'summary'  => $summary,
        ];
    }

    /**
     * 年份范围（回退链）：
     * 起点 = 入职年份 → 最早职业史年份 → 最早关联文档年份 → 当年；
     * 终点 = 在职取当年；非在职取最后一段职业史 end_date 年份 → 最晚关联文档年份 → 当年。
     */
    public static function rangeYears(array $worker, array $history, array $docs, string $asOf): array
    {
        $asOfYear = (int)substr($asOf, 0, 4);
        $start = null;
        if ((string)($worker['entry_date'] ?? '') !== '') {
            $start = (int)substr((string)$worker['entry_date'], 0, 4);
        }
        if ($start === null) {
            foreach ($history as $h) {
                $y = (int)substr((string)$h['start_date'], 0, 4);
                if ($y > 0 && ($start === null || $y < $start)) {
                    $start = $y;
                }
            }
        }
        if ($start === null) {
            foreach ($docs as $dc) {
                if ((int)$dc['y'] > 0 && ($start === null || (int)$dc['y'] < $start)) {
                    $start = (int)$dc['y'];
                }
            }
        }
        $start = $start ?? $asOfYear;

        $end = null;
        if (($worker['status'] ?? '') === '在职') {
            $end = $asOfYear;
        } else {
            foreach ($history as $h) {
                if ($h['end_date'] !== null && (string)$h['end_date'] !== '') {
                    $y = (int)substr((string)$h['end_date'], 0, 4);
                    if ($y > 0 && ($end === null || $y > $end)) {
                        $end = $y;
                    }
                }
            }
        }
        if ($end === null) {
            foreach ($docs as $dc) {
                if ((int)$dc['y'] > 0 && ($end === null || (int)$dc['y'] > $end)) {
                    $end = (int)$dc['y'];
                }
            }
        }
        $end = $end ?? $asOfYear;

        if ($end < $start) {
            $end = $start;
        }
        if ($end > $asOfYear) {
            $end = max($start, $asOfYear);   // 未来年份不展示
        }
        return ['start' => $start, 'end' => $end];
    }

    /** 状态徽标（展示统一入口） */
    public static function badge(string $state): array
    {
        return match ($state) {
            'done'          => ['已归档', 'bg-green-lt'],
            'done_indirect' => ['已归档（间接）', 'bg-teal-lt'],
            'reg_only'      => ['仅登记', 'bg-yellow-lt'],
            'todo'          => ['待归档', 'bg-red-lt'],
            'partial'       => ['部分', 'bg-orange-lt'],
            'na'            => ['不适用', 'bg-secondary-lt'],
            'event'         => ['按需', 'bg-azure-lt'],
            'pending'       => ['待台账', 'bg-secondary-lt'],
            default         => ['—', ''],
        };
    }

    /**
     * 批量计算某年度列在全部劳动者上的格子状态。
     * 供单位清单 worker_years 部分与工作台“有缺口的劳动者”使用，避免逐人 build()。
     * 返回 [worker_id => [year => state]]（state：none/done/reg_only/todo/na）。
     * 支持 W_EXAM / W_EXAM_NOTICE / W_TRAIN / W_TRAIN_MGR / W_PPE（X_DETECT 间接列不含）。
     * $ignoreMarks=true 时忽略「不适用」标记，状态只反映适用性（none = 当年不适用该项）——
     * 用于判断“是否曾接害”：陈旧的 na 标记（先标不适用、后更正职业史）不应让人被当作接害。
     */
    public static function yearStates(string $type, ?string $asOf = null, bool $ignoreMarks = false): array
    {
        if (!in_array($type, ['W_EXAM', 'W_EXAM_NOTICE', 'W_TRAIN', 'W_TRAIN_MGR', 'W_PPE'], true)) {
            return [];
        }
        $asOf = $asOf ?: date('Y-m-d');
        $asOfYear = (int)substr($asOf, 0, 4);

        $workers = Database::rows('SELECT id, status, entry_date, duty_role FROM workers');
        if (!$workers) {
            return [];
        }
        // 职业史 + 配置了危害的岗位
        $history = Database::rows(
            'SELECT wp.worker_id, wp.post_id, wp.start_date, wp.end_date, wp.hazards_snapshot
             FROM worker_posts wp ORDER BY wp.worker_id, wp.start_date'
        );
        $hazardPosts = Exposure::hazardPosts();
        // 相关文档：挂在本人且带年度（各类型 + W_EXAM 作证据）
        $docYears = [];         // worker_id => type => [year => n]
        foreach (Database::rows(
            "SELECT d.link_id AS wid, d.doc_type, " . self::DOC_YEAR_SQL . ' AS y, COUNT(*) AS c
             FROM documents d WHERE d.link_type = \'worker\' AND COALESCE(d.doc_type,\'\') IN (\'W_EXAM\',\'W_EXAM_NOTICE\',\'W_TRAIN\',\'W_TRAIN_MGR\',\'W_PPE\')
             GROUP BY d.link_id, d.doc_type, y'
        ) as $r) {
            $docYears[(int)$r['wid']][(string)$r['doc_type']][(int)$r['y']] = (int)$r['c'];
        }
        // 挂在体检记录上的 W_EXAM 文档（按检查年计入）
        $examDocYears = [];     // worker_id => [year => n]
        foreach (Database::rows(
            "SELECT er.worker_id AS wid, " . self::DOC_YEAR_SQL . ' AS y, COUNT(*) AS c
             FROM documents d JOIN exam_records er ON er.id = d.link_id
             WHERE d.link_type = \'exam_record\' GROUP BY er.worker_id, y'
        ) as $r) {
            $examDocYears[(int)$r['wid']][(int)$r['y']] = (int)$r['c'];
        }
        // 体检记录年份
        $examYears = [];
        foreach (Database::rows('SELECT worker_id, exam_date FROM exam_records') as $r) {
            $examYears[(int)$r['worker_id']][(int)substr((string)$r['exam_date'], 0, 4)] = true;
        }
        // 参训年份
        $trainYears = [];
        foreach (Database::rows('SELECT tp.worker_id, t.trained_on FROM training_participants tp JOIN trainings t ON t.id = tp.training_id') as $r) {
            $trainYears[(int)$r['worker_id']][(int)substr((string)$r['trained_on'], 0, 4)] = true;
        }
        // 领用年份
        $ppeYears = [];
        foreach (Database::rows('SELECT worker_id, issued_on FROM ppe_issues') as $r) {
            $y = (int)substr((string)$r['issued_on'], 0, 4);
            $ppeYears[(int)$r['worker_id']][$y] = ($ppeYears[(int)$r['worker_id']][$y] ?? 0) + 1;
        }
        // 年份范围兜底用：本人 + 其体检记录 + 其职业病登记上的全部文档（不限类型，与 build() 一致）
        $rangeDocYears = [];    // worker_id => [year => n]
        foreach (Database::rows(
            'SELECT wid, y, COUNT(*) AS c FROM (
                SELECT d.link_id AS wid, ' . self::DOC_YEAR_SQL . " AS y FROM documents d WHERE d.link_type = 'worker'
                UNION ALL
                SELECT er.worker_id AS wid, " . self::DOC_YEAR_SQL . " AS y FROM documents d JOIN exam_records er ON er.id = d.link_id WHERE d.link_type = 'exam_record'
                UNION ALL
                SELECT ds.worker_id AS wid, " . self::DOC_YEAR_SQL . " AS y FROM documents d JOIN diseases ds ON ds.id = d.link_id WHERE d.link_type = 'disease'
             ) WHERE y > 0 GROUP BY wid, y"
        ) as $r) {
            $rangeDocYears[(int)$r['wid']][(int)$r['y']] = (int)$r['c'];
        }
        // 不适用标记
        $marks = [];
        foreach (Database::rows("SELECT worker_id, item_code, period_year FROM archive_marks WHERE scope = 'worker'") as $m) {
            $marks[(int)$m['worker_id']][(string)$m['item_code'] . '|' . (int)$m['period_year']] = true;
        }

        // 按人聚合职业史
        $histBy = [];
        foreach ($history as $h) {
            $histBy[(int)$h['worker_id']][] = $h;
        }

        $out = [];
        foreach ($workers as $w) {
            $wid = (int)$w['id'];
            $roles = array_filter(array_map('trim', explode(',', (string)$w['duty_role'])));
            $hasRole = $roles !== [];
            // 年份范围（文档年份兜底集合）：与 build() 同一集合，避免个人页与批量统计的年份范围不一致
            $docYs = [];
            foreach ($rangeDocYears[$wid] ?? [] as $y => $n) {
                $docYs[] = ['y' => $y];
            }
            $rng = self::rangeYears($w, $histBy[$wid] ?? [], $docYs, $asOf);
            // 逐年接害（与 build / 工作台 / 表2-2 同口径）
            for ($y = $rng['start']; $y <= $rng['end']; $y++) {
                $isExposed = Exposure::exposedInYear($histBy[$wid] ?? [], $y, $asOfYear, $hazardPosts);
                if (!$ignoreMarks && isset($marks[$wid][$type . '|' . $y])) {
                    $out[$wid][$y] = 'na';
                    continue;
                }
                $hasDoc = ($docYears[$wid][$type][$y] ?? 0) > 0;
                $evidence = match ($type) {
                    'W_EXAM_NOTICE' => !empty($examYears[$wid][$y]) || ($docYears[$wid]['W_EXAM'][$y] ?? 0) > 0,
                    'W_PPE'         => !empty($ppeYears[$wid][$y]),
                    default         => true,
                };
                if (!DocTypes::appliesTo($type, [
                    'exposed' => $isExposed, 'role' => $hasRole, 'evidence' => $evidence,
                ])) {
                    $out[$wid][$y] = 'none';
                    continue;
                }
                $out[$wid][$y] = self::yearState($type, [
                    'has_doc' => $hasDoc || ($type === 'W_EXAM' && ($examDocYears[$wid][$y] ?? 0) > 0),
                    'has_reg' => $type === 'W_EXAM' ? !empty($examYears[$wid][$y])
                        : ($type === 'W_TRAIN' ? !empty($trainYears[$wid][$y]) : false),
                ]);
            }
        }
        return $out;
    }

    /**
     * 事件类线索人数：体检结论为职业禁忌证/疑似职业病（含疑似职业病登记）、
     * 或存在复查体检记录，但未发现对应事件类资料的人数。
     * 供清单 C30–C32 说明列使用；返回 [doc_type => 人数]。
     *   W_TRANSFER：职业禁忌证结论 → 无 W_TRANSFER；
     *   W_RECHECK：复查体检（exam_type=复查）→ 无 W_RECHECK；
     *   W_SUSPECT：疑似职业病结论/登记 → 无 W_SUSPECT 且无 W_DIAG；
     *   W_DIAG：确诊职业病登记 → 无 W_DIAG 诊断证明；
     *   W_COPY_SIGN：非在职且有接害史 → 无 W_COPY_SIGN 档案复印件签收。
     */
    public static function eventClues(): array
    {
        $counts = ['W_TRANSFER' => 0, 'W_RECHECK' => 0, 'W_SUSPECT' => 0, 'W_DIAG' => 0, 'W_COPY_SIGN' => 0];
        foreach (self::eventClueWorkers() as $row) {
            foreach (explode('、', $row['clues']) as $code) {
                $code = trim($code);
                if (isset($counts[$code])) {
                    $counts[$code]++;
                }
            }
        }
        return $counts;
    }

    /**
     * 指定事件类型集合下的线索人数（按人去重）。
     * 同一人同时命中多个类型（如 C32 的 W_SUSPECT + W_DIAG）只算 1 人，与线索名单页的人数一致。
     */
    public static function eventClueCount(array $types): int
    {
        $n = 0;
        foreach (self::eventClueWorkers() as $row) {
            $codes = array_map('trim', explode('、', (string)$row['clues']));
            if (array_intersect($types, $codes)) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * 事件线索人员名单（工作台/清单数字的可点击明细）。
     * 同一人多年份线索按“异常年份 ≤ 对应材料年份”判定是否被覆盖：
     * 一份旧调岗通知不能让后来新出现的职业禁忌证永久静默。
     * 返回 [['id','name','archive_no','clues','labels']]，clues 为未归档的事件类类型编码（、分隔）。
     */
    public static function eventClueWorkers(): array
    {
        $labels = [
            'W_TRANSFER' => '职业禁忌证（调岗材料）',
            'W_RECHECK'  => '复查体检（复查结论材料）',
            'W_SUSPECT'  => '疑似职业病（诊断/安置材料）',
            'W_DIAG'     => '确诊职业病（诊断证明）',
            'W_COPY_SIGN'=> '离岗档案复印件签收',
        ];
        // 已有事件类材料：劳动者 → 类型 → 年份列表（挂在本人 / 体检记录 / 职业病登记上均算）
        $have = [];
        // 劳动者 id 在同一条 SQL 内解析
        foreach (Database::rows(
            "SELECT d.doc_type,
                    CASE d.link_type
                        WHEN 'worker' THEN d.link_id
                        WHEN 'disease' THEN (SELECT x.worker_id FROM diseases x WHERE x.id = d.link_id)
                        WHEN 'exam_record' THEN (SELECT e.worker_id FROM exam_records e WHERE e.id = d.link_id)
                        ELSE NULL
                    END AS wid,
                    COALESCE(d.period_year, NULLIF(CAST(substr(d.doc_date,1,4) AS INTEGER),0)) AS y
               FROM documents d
              WHERE d.doc_type IN ('W_TRANSFER','W_RECHECK','W_SUSPECT','W_DIAG','W_COPY_SIGN')"
        ) as $r) {
            $wid = (int)($r['wid'] ?? 0);
            if ($wid > 0) {
                $have[(string)$r['doc_type']][$wid][] = (int)$r['y'];
            }
        }
        // 每条线索带自己的发生年份，按人+类型去重
        $items = [];
        $add = function (int $wid, string $code, int $year) use (&$items): void {
            if ($wid > 0) {
                $items[] = [$wid, $code, $year];
            }
        };
        foreach (Database::rows('SELECT worker_id, exam_date, exam_type, conclusion FROM exam_records') as $r) {
            $wid = (int)$r['worker_id'];
            $year = (int)substr((string)$r['exam_date'], 0, 4);
            if ((string)$r['conclusion'] === '职业禁忌证') {
                $add($wid, 'W_TRANSFER', $year);
            }
            if ((string)$r['conclusion'] === '疑似职业病') {
                $add($wid, 'W_SUSPECT', $year);
            }
            if ((string)$r['exam_type'] === '复查') {
                $add($wid, 'W_RECHECK', $year);
            }
        }
        foreach (Database::rows('SELECT worker_id, diag_date FROM diseases WHERE is_suspected = 1') as $r) {
            $add((int)$r['worker_id'], 'W_SUSPECT', (int)substr((string)$r['diag_date'], 0, 4));
        }
        // 确诊职业病登记同样需要诊断证明原件
        foreach (Database::rows('SELECT worker_id, diag_date FROM diseases WHERE is_suspected = 0') as $r) {
            $add((int)$r['worker_id'], 'W_DIAG', (int)substr((string)$r['diag_date'], 0, 4));
        }
        // 非在职且有接害史的劳动者，离岗时应提供监护档案复印件并签收
        foreach (Database::rows(
            "SELECT w.id FROM workers w
              WHERE w.status <> '在职'
                AND EXISTS (SELECT 1 FROM worker_posts wp WHERE wp.worker_id = w.id
                             AND (TRIM(COALESCE(wp.hazards_snapshot,'')) <> ''
                                  OR EXISTS (SELECT 1 FROM post_hazards ph WHERE ph.post_id = wp.post_id)))"
        ) as $r) {
            $add((int)$r['id'], 'W_COPY_SIGN', 0);
        }
        $covered = function (int $wid, string $code, int $year) use ($have): bool {
            $types = $code === 'W_SUSPECT' ? ['W_SUSPECT', 'W_DIAG'] : [$code];
            foreach ($types as $t) {
                foreach ($have[$t][$wid] ?? [] as $dy) {
                    // 材料没填年度也没填日期（$dy = 0）时无法比较先后，按“已有材料”算覆盖，
                    // 否则用户明明挂了原件，人员仍永远留在线索名单里
                    if ($dy <= 0 || $year <= 0 || $dy >= $year) {
                        return true;
                    }
                }
            }
            return false;
        };
        $byWorker = [];
        foreach ($items as [$wid, $code, $year]) {
            if (!$covered($wid, $code, $year)) {
                $byWorker[$wid][$code] = true;
            }
        }
        $out = [];
        $workerMap = [];
        if ($byWorker) {
            foreach (Database::rows('SELECT id, name, archive_no FROM workers') as $wr) {
                $workerMap[(int)$wr['id']] = $wr;
            }
        }
        foreach ($byWorker as $wid => $codes) {
            $w = $workerMap[(int)$wid] ?? null;
            if ($w === null) {
                continue;
            }
            $parts = array_keys($codes);
            $out[] = [
                'id' => $wid,
                'name' => (string)$w['name'],
                'archive_no' => (string)$w['archive_no'],
                'clues' => implode('、', $parts),
                'labels' => implode(' / ', array_map(fn ($c) => $labels[$c], $parts)),
            ];
        }
        usort($out, fn ($a, $b) => strcmp($a['archive_no'], $b['archive_no']));
        return $out;
    }

    /**
     * 有缺口的劳动者（工作台卡片 / 列表）：
     * 一次性资料缺失（按适用性）或近 3 年年度格 todo/reg_only（不含 X_DETECT 间接列）。
     * 返回 [id => ['name'=>, 'archive_no'=>, 'gaps'=>n]]，$limit>0 时只取前 N 人。
     */
    public static function gapWorkers(?string $asOf = null, int $limit = 0): array
    {
        $asOf = $asOf ?: date('Y-m-d');
        $asOfYear = (int)substr($asOf, 0, 4);
        $minY = $asOfYear - 2;

        // 一次性资料缺失
        $everExposed = [];   // worker_id => bool（任一年接害）
        $hasRole = [];
        foreach (self::yearStates('W_EXAM', $asOf, true) as $wid => $byYear) {   // 忽略不适用标记：陈旧标记不应让人被当作接害
            foreach ($byYear as $state) {
                if ($state !== 'none') { $everExposed[$wid] = true; }
            }
        }
        foreach (Database::rows('SELECT id, duty_role FROM workers') as $w) {
            if (trim((string)$w['duty_role']) !== '') {
                $hasRole[(int)$w['id']] = true;
            }
        }
        $onceTypes = [ // type => 适用函数
            'W_CONTRACT' => fn () => true,
            'W_NOTICE' => fn (int $wid) => !empty($everExposed[$wid]),
            'W_APPOINT' => fn (int $wid) => !empty($hasRole[$wid]),
            'W_DUTY' => fn (int $wid) => !empty($hasRole[$wid]),
        ];
        $hasOnce = [];    // type => [wid => n]
        foreach (Database::rows(
            "SELECT link_id AS wid, doc_type, COUNT(*) AS c FROM documents
             WHERE link_type = 'worker' AND doc_type IN ('W_CONTRACT','W_NOTICE','W_APPOINT','W_DUTY')
             GROUP BY link_id, doc_type"
        ) as $r) {
            $hasOnce[(string)$r['doc_type']][(int)$r['wid']] = (int)$r['c'];
        }
        $naOnce = [];
        foreach (Database::rows("SELECT worker_id, item_code FROM archive_marks WHERE scope = 'worker' AND period_year IS NULL") as $m) {
            $naOnce[(int)$m['worker_id']][(string)$m['item_code']] = true;
        }

        $yearTypes = ['W_EXAM', 'W_EXAM_NOTICE', 'W_TRAIN', 'W_TRAIN_MGR', 'W_PPE'];
        $states = [];
        foreach ($yearTypes as $t) {
            $states[$t] = self::yearStates($t, $asOf);
        }

        $out = [];
        foreach (Database::rows('SELECT id, name, archive_no FROM workers ORDER BY id') as $w) {
            $wid = (int)$w['id'];
            $gaps = 0;
            foreach ($onceTypes as $t => $applies) {
                if ($applies($wid) && empty($hasOnce[$t][$wid]) && empty($naOnce[$wid][$t])) {
                    $gaps++;
                }
            }
            foreach ($yearTypes as $t) {
                foreach ($states[$t][$wid] ?? [] as $y => $state) {
                    if ($y >= $minY && in_array($state, ['todo', 'reg_only'], true)) {
                        $gaps++;
                    }
                }
            }
            if ($gaps > 0) {
                $out[$wid] = ['name' => (string)$w['name'], 'archive_no' => (string)$w['archive_no'], 'gaps' => $gaps];
            }
        }
        if ($limit > 0 && count($out) > $limit) {
            $out = array_slice($out, 0, $limit, true);
        }
        return $out;
    }
}
