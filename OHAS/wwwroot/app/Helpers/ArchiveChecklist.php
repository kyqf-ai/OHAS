<?php
/**
 * 单位资料清单：36 项资料哪些已归档、缺在哪、缺谁的。
 *
 * part 种类：
 *   ledger      台账范围内记录数 ≥ 1（参数 key、可选 since=last3y）
 *   doc         该类型文档数 ≥ 1（types[]；可选 link、per_record、period=当年、filter、scope=last3y）
 *   workers     应有该资料的劳动者每人都有 ≥ 1 份（type、who=all/exposed/role）
 *   worker_years 个人年度矩阵中该列适用格子全部已归档（type、basis）
 *   system      系统可直接打印该表样（print、need）
 *   event       不判定，只统计份数（types[]）
 * optional=true 的 part 展示但不参与状态判定；pending=true 显示“待台账”不参与统计。
 *
 * 状态：na（不适用）/ event（按需）/ done（已归档）/ partial（部分）/ todo（待归档）。
 * 全站不出现分数、百分比、“合格/不合格”。
 */
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

class ArchiveChecklist
{
    /**
     * 到期资料名单：已过期 / soon_days 天内到期，供工作台徽标的明细页使用。
     */
    public static function expiringDocs(?string $asOf = null): array
    {
        $asOf = $asOf ?: date('Y-m-d');
        $soonDays = max(1, (int)cfg('expiry.soon_days', 30));
        $soon = date('Y-m-d', strtotime($asOf . ' +' . $soonDays . ' days'));
        $rows = Database::rows(
            "SELECT d.*, CASE WHEN d.expire_date < ? THEN 'expired' ELSE 'soon' END AS exp_state
             FROM documents d
             WHERE d.expire_date IS NOT NULL AND d.expire_date <> '' AND d.expire_date <= ?
             ORDER BY d.expire_date ASC, d.id ASC",
            [$asOf, $soon]
        );
        foreach ($rows as &$r) {
            $r['days_left'] = (int)round((strtotime((string)$r['expire_date']) - strtotime($asOf)) / 86400);
        }
        unset($r);
        return $rows;
    }

    /** @var array<string, array>|null 目录缓存 */
    private static ?array $catalog = null;

    /**
     * 分组标题：严格对应六个官方档案盒（一～六）。
     * 资料按规范归入六类：劳动合同/告知书归入个人档案；承诺/信用归入管理档案。
     */
    public const GROUPS = [
        'three'      => '一、三同时',
        'management' => '二、管理',
        'training'   => '三、培训',
        'monitoring' => '四、监测检测',
        'health'     => '五、健康监护',
        'personal'   => '六、个人档案',
    ];

    /**
     * 自评表 19 项 → 六类档案盒的分配（补充层，主线为 ArchiveCatalog::filingCatalog()）。
     * 每项 [项号, 自评项目, 是否关键项★, 归入档案盒key, 印证材料要点, 对应清单项代码]。
     * 第3项「职业卫生档案」为总纲项（六类齐全本身），不单列清单项，归入六类整体。
     */
    public static function selfAssessmentMap(): array
    {
        return [
            ['1',  '制度建立',              false, 'management', '制度文件或制度汇编',            ['C03']],
            ['2',  '管理人员/机构',         false, 'management', '机构设立/任命/职责文件',        ['C04', 'C05', 'C06']],
            ['3',  '职业卫生档案',          false, 'all',        '六类档案材料（总纲项）',         []],
            ['4',  '职业病危害项目申报',    true,  'management', '申报表和申报回执',              ['C07']],
            ['5',  '建设项目"三同时"',      false, 'three',      '近三年项目清单及四类要件',      ['C01', 'C02']],
            ['6',  '布局合理',              false, 'management', '布局图或照片',                  ['C08']],
            ['7',  '定期检测',              true,  'monitoring', '近三年定期检测报告、机构资质',  ['C22', 'C24']],
            ['8',  '现状评价',              false, 'monitoring', '现状评价报告',                  ['C23']],
            ['9',  '检测结果与治理',        false, 'monitoring', '最近检测/评价报告、超标整改',   ['C22', 'C23', 'C25']],
            ['10', '防护设施',              false, 'management', '防护设施清单及维护检修记录',    ['C09', 'C10']],
            ['11', '个人防护用品',          false, 'management', '配发标准、采购记录、领用签字',  ['C11', 'C12', 'C13']],
            ['12', '主要负责人与管理人员培训', true, 'training',  '培训合格证书或学时证明',      ['C19']],
            ['13', '劳动者培训',            true,  'training',   '年度培训计划、签到、讲义、试卷', ['C20', 'C21']],
            ['14', '合同告知',              true,  'personal',   '劳动合同、职业病危害告知书',    ['C33', 'C34']],
            ['15', '现场告知',              false, 'management', '公告栏、警示标识、告知卡照片',  ['C14', 'C15']],
            ['16', '职业健康检查',          true,  'health',     '花名册、检查总结报告、机构资质', ['C26', 'C27', 'C28']],
            ['17', '人员安置与结果告知',    true,  'health',     '结果告知、调岗、复查、诊断材料', ['C29', 'C30', 'C31', 'C32']],
            ['18', '应急预案与设施',        false, 'management', '应急预案、演练记录、应急设施',  ['C16', 'C17', 'C18']],
            ['19', '合规情况',              true,  'management', '单位承诺说明、信用查询结果',    ['C35', 'C36']],
        ];
    }

    public static function catalog(): array
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }
        $d = 'doc';
        self::$catalog = [
            // ---------- 一　三同时 ----------
            'C01' => ['name' => '近三年建设项目清单', 'self' => '5', 'na' => ['近三年无相关建设项目'], 'group' => 'three', 'parts' => [
                ['kind' => 'ledger', 'key' => 'projects', 'since' => 'last3y', 'label' => '近三年建设项目'],
            ]],
            'C02' => ['name' => '预评价报告、设施设计专篇、控评报告、验收材料', 'self' => '5', 'na' => ['近三年无相关建设项目'], 'group' => 'three', 'parts' => [
                ['kind' => $d, 'types' => ['U_PRE_EVAL', 'U_DESIGN', 'U_CTRL_EVAL', 'U_ACCEPT'], 'link' => 'project',
                 'per_record' => 'last3y_projects', 'label' => '项目四类要件齐全'],
            ]],
            // ---------- 二　管理 ----------
            'C03' => ['name' => '制度文件或制度汇编（含操作规程）', 'self' => '1', 'group' => 'management', 'parts' => [
                ['kind' => $d, 'types' => ['U_RULES'], 'label' => '制度文件'],
            ]],
            'C04' => ['name' => '管理机构设立或成立文件', 'self' => '2', 'group' => 'management', 'parts' => [
                ['kind' => $d, 'types' => ['U_ORG'], 'label' => '机构设立文件'],
            ]],
            'C05' => ['name' => '管理人员任命文件', 'self' => '2', 'group' => 'management', 'parts' => [
                ['kind' => 'workers', 'type' => 'W_APPOINT', 'who' => 'role', 'label' => '有职务角色人员'],
            ]],
            'C06' => ['name' => '管理人员工作职责说明', 'self' => '2', 'group' => 'management', 'parts' => [
                ['kind' => 'workers', 'type' => 'W_DUTY', 'who' => 'role', 'label' => '有职务角色人员'],
            ]],
            'C07' => ['name' => '职业病危害项目申报表和回执', 'self' => '4', 'group' => 'management', 'parts' => [
                ['kind' => 'system', 'print' => '表2-2', 'need' => 'company', 'label' => '申报表可打印'],
                ['kind' => $d, 'types' => ['U_DECL_RECEIPT'], 'label' => '申报回执'],
            ]],
            'C08' => ['name' => '现场布局图或照片', 'self' => '6', 'group' => 'management', 'parts' => [
                ['kind' => $d, 'types' => ['U_LAYOUT'], 'label' => '布局图/照片'],
            ]],
            'C09' => ['name' => '防护设施清单及照片', 'self' => '10', 'group' => 'management', 'parts' => [
                ['kind' => 'ledger', 'key' => 'facilities', 'label' => '防护设施台账'],
                ['kind' => $d, 'types' => ['U_FAC_PHOTO'], 'link' => 'ledger_facilities', 'per_record' => 'facilities', 'label' => '设施照片'],
            ]],
            'C10' => ['name' => '设施维护检修记录', 'self' => '10', 'group' => 'management', 'parts' => [
                ['kind' => 'ledger', 'key' => 'facility_maintains', 'label' => '维护检修记录'],
            ]],
            'C11' => ['name' => '防护用品配发标准', 'self' => '11', 'group' => 'management', 'parts' => [
                ['kind' => $d, 'types' => ['U_PPE_STD'], 'label' => '配发标准'],
            ]],
            'C12' => ['name' => '防护用品采购记录', 'self' => '11', 'group' => 'management', 'parts' => [
                ['kind' => $d, 'types' => ['U_PPE_PURCHASE'], 'label' => '采购记录'],
            ]],
            'C13' => ['name' => '个人领用签字表', 'self' => '11', 'group' => 'management', 'parts' => [
                ['kind' => 'ledger', 'key' => 'ppe_issues', 'label' => '发放记录'],
                ['kind' => 'worker_years', 'type' => 'W_PPE', 'basis' => 'ppe', 'optional' => true, 'label' => '个人领用签字（参考）'],
            ]],
            'C14' => ['name' => '公告栏照片', 'self' => '15', 'group' => 'management', 'parts' => [
                ['kind' => $d, 'types' => ['U_BOARD_PHOTO'], 'label' => '公告栏照片'],
            ]],
            'C15' => ['name' => '警示标识、告知卡现场照片', 'self' => '15', 'group' => 'management', 'parts' => [
                ['kind' => 'ledger', 'key' => 'warning_signs', 'label' => '警示标识台账'],
                ['kind' => $d, 'types' => ['U_SIGN_PHOTO'], 'link' => 'ledger_warning_signs', 'per_record' => 'warning_signs', 'label' => '标识现场照片'],
                ['kind' => $d, 'types' => ['U_NOTICE_CARD'], 'label' => '告知卡'],
            ]],
            'C16' => ['name' => '应急预案', 'self' => '18', 'na' => ['无急性职业病危害风险（如仅涉及噪声、粉尘等慢性危害）'], 'group' => 'management', 'parts' => [
                ['kind' => $d, 'types' => ['U_EMERGENCY_PLAN'], 'label' => '应急预案'],
            ]],
            'C17' => ['name' => '应急演练记录（含照片、总结）', 'self' => '18', 'na' => ['无急性职业病危害风险（如仅涉及噪声、粉尘等慢性危害）'], 'group' => 'management', 'parts' => [
                ['kind' => 'ledger', 'key' => 'drills', 'label' => '应急演练台账'],
                ['kind' => $d, 'types' => ['U_DRILL_FILE'], 'link' => 'ledger_drills', 'per_record' => 'drills', 'label' => '演练记录附件'],
            ]],
            'C18' => ['name' => '应急设施台账及现场照片', 'self' => '18', 'na' => ['无急性职业病危害风险（如仅涉及噪声、粉尘等慢性危害）'], 'group' => 'management', 'parts' => [
                ['kind' => 'ledger', 'key' => 'emergency_facilities', 'label' => '应急设施台账'],
                ['kind' => $d, 'types' => ['U_EMG_PHOTO'], 'link' => 'ledger_emergency_facilities', 'per_record' => 'emergency_facilities', 'label' => '设施现场照片'],
            ]],
            // ---------- 三　培训 ----------
            'C19' => ['name' => '主要负责人和管理人员培训证书或记录（含学时证明）', 'self' => '12', 'group' => 'training', 'parts' => [
                ['kind' => 'workers', 'type' => 'W_TRAIN_MGR', 'who' => 'role', 'label' => '有职务角色人员'],
            ]],
            'C20' => ['name' => '年度培训计划', 'self' => '13', 'group' => 'training', 'parts' => [
                ['kind' => $d, 'types' => ['U_TRAIN_PLAN'], 'period' => 'current_year', 'label' => '当年培训计划'],
            ]],
            'C21' => ['name' => '培训签到表、照片、讲义、试卷', 'self' => '13', 'group' => 'training', 'parts' => [
                ['kind' => 'ledger', 'key' => 'trainings', 'since' => 'last3y', 'label' => '近三年培训'],
                ['kind' => $d, 'types' => ['U_TRAIN_SIGN', 'U_TRAIN_PHOTO', 'U_TRAIN_MATERIAL', 'U_TRAIN_EXAM'], 'link' => 'ledger_trainings',
                 'per_record' => 'trainings_last3y', 'label' => '培训佐证材料'],
                ['kind' => 'worker_years', 'type' => 'W_TRAIN', 'basis' => 'train', 'optional' => true, 'label' => '个人培训记录（参考）'],
            ]],
            // ---------- 四　监测检测 ----------
            'C22' => ['name' => '近三年定期检测报告', 'self' => '7、9', 'group' => 'monitoring', 'parts' => [
                ['kind' => $d, 'types' => ['U_DETECT_REPORT'], 'link' => 'detection_report', 'per_record' => 'detect_last3y',
                 'filter' => ['category' => '定期检测', 'since' => 'last3y'], 'label' => '定期检测报告原件'],
            ]],
            'C23' => ['name' => '现状评价报告', 'self' => '8、9', 'na' => ['非危害严重单位，且未发生职业病危害事故'], 'group' => 'monitoring', 'parts' => [
                ['kind' => $d, 'types' => ['U_DETECT_REPORT'], 'link' => 'detection_report', 'per_record' => 'detect_status',
                 'filter' => ['category' => '现状评价'], 'label' => '现状评价报告原件'],
            ]],
            'C24' => ['name' => '检测机构资质证书', 'self' => '7', 'group' => 'monitoring', 'parts' => [
                ['kind' => $d, 'types' => ['U_QUAL_DETECT'], 'expiry' => true, 'label' => '检测机构资质'],
            ]],
            'C25' => ['name' => '超标点整改方案、整改前后对比照片或检测报告', 'self' => '9', 'group' => 'monitoring', 'parts' => [
                ['kind' => 'ledger', 'key' => 'rectifications', 'label' => '超标整改台账'],
                ['kind' => $d, 'types' => ['U_RECT_PLAN', 'U_RECT_COMPARE'], 'link' => 'ledger_rectifications', 'per_record' => 'rectifications', 'label' => '整改佐证材料'],
            ]],
            // ---------- 五　健康监护 ----------
            'C26' => ['name' => '接害劳动者花名册', 'self' => '16', 'group' => 'health', 'parts' => [
                ['kind' => 'system', 'print' => '表5-6', 'need' => 'exposed', 'label' => '花名册可打印'],
            ]],
            'C27' => ['name' => '历年职业健康检查总结报告', 'self' => '16', 'group' => 'health', 'parts' => [
                ['kind' => $d, 'types' => ['U_EXAM_SUMMARY'], 'link' => 'exam_batch', 'per_record' => 'exam_batches', 'label' => '批次总结报告'],
            ]],
            'C28' => ['name' => '体检机构资质证书', 'self' => '16', 'group' => 'health', 'parts' => [
                ['kind' => $d, 'types' => ['U_QUAL_EXAM'], 'expiry' => true, 'label' => '体检机构资质'],
            ]],
            'C29' => ['name' => '体检结果书面告知记录或签收表', 'self' => '17', 'group' => 'health', 'parts' => [
                ['kind' => 'worker_years', 'type' => 'W_EXAM_NOTICE', 'basis' => 'exam', 'label' => '接害人员体检告知'],
            ]],
            'C30' => ['name' => '调岗通知书', 'self' => '17', 'group' => 'health', 'parts' => [
                ['kind' => 'event', 'types' => ['W_TRANSFER'], 'label' => '调岗通知书'],
            ]],
            'C31' => ['name' => '复查通知书及记录', 'self' => '17', 'group' => 'health', 'parts' => [
                ['kind' => 'event', 'types' => ['W_RECHECK'], 'label' => '复查通知书及记录'],
            ]],
            'C32' => ['name' => '疑似职业病诊断申请材料', 'self' => '17', 'group' => 'health', 'parts' => [
                ['kind' => 'event', 'types' => ['W_SUSPECT', 'W_DIAG'], 'label' => '诊断申请/诊断证明'],
            ]],
            // ---------- 六、个人档案（原综合资料：劳动合同/告知书归入个人档案盒）----------
            'C33' => ['name' => '劳动合同（全员）', 'self' => '14', 'group' => 'personal', 'parts' => [
                ['kind' => 'workers', 'type' => 'W_CONTRACT', 'who' => 'all', 'label' => '全体劳动者'],
            ]],
            'C34' => ['name' => '职业病危害告知书（合同附件）', 'self' => '14', 'group' => 'personal', 'parts' => [
                ['kind' => 'workers', 'type' => 'W_NOTICE', 'who' => 'exposed', 'label' => '曾/现接害劳动者'],
            ]],
            // 合规证明（原综合资料）：归入二、管理档案盒（单位层面管理资料）
            'C35' => ['name' => '单位出具的承诺说明', 'self' => '19', 'group' => 'management', 'parts' => [
                ['kind' => $d, 'types' => ['U_PROMISE'], 'label' => '承诺说明'],
            ]],
            'C36' => ['name' => '信用中国或监管部门网站的查询结果', 'self' => '19', 'group' => 'management', 'parts' => [
                ['kind' => $d, 'types' => ['U_CREDIT'], 'period' => 'current_year', 'label' => '当年查询结果'],
            ]],
        ];
        return self::$catalog;
    }

    /**
     * 允许标“不适用”的原因（对应自评表“合理缺项”：第5项、第8项、第18项）。
     * 未配置 'na' 的清单项不允许标不适用；返回空数组表示不允许。
     */
    public static function naReasons(string $code): array
    {
        return self::catalog()[$code]['na'] ?? [];
    }

    public static function naAllowed(string $code): bool
    {
        return self::naReasons($code) !== [];
    }

    /** 全部 36 项（含判定结果）。$asOf 默认今天；自测传固定值。 */
    public static function all(?string $asOf = null): array
    {
        $asOf = $asOf ?: date('Y-m-d');
        $marks = [];
        foreach (Database::rows("SELECT item_code, reason FROM archive_marks WHERE scope = 'unit'") as $m) {
            $marks[(string)$m['item_code']] = (string)$m['reason'];
        }
        $items = [];
        foreach (self::catalog() as $code => $cfg) {
            // 自评表无“合理缺项”的项，历史遗留的不适用标记不生效
            $items[$code] = self::evaluate($code, $cfg, self::naAllowed($code) ? ($marks[$code] ?? null) : null, $asOf);
        }
        return ['asOf' => $asOf, 'items' => $items, 'stats' => self::summarize($items)];
    }

    /** 单项 */
    public static function item(string $code, ?string $asOf = null): ?array
    {
        $cfg = self::catalog()[$code] ?? null;
        if ($cfg === null) {
            return null;
        }
        $asOf = $asOf ?: date('Y-m-d');
        $mark = Database::row("SELECT reason FROM archive_marks WHERE scope = 'unit' AND item_code = ?", [$code]);
        return self::evaluate($code, $cfg, self::naAllowed($code) ? ($mark['reason'] ?? null) : null, $asOf);
    }

    /** 状态计数 */
    public static function stats(?string $asOf = null): array
    {
        $all = self::all($asOf);
        return $all['stats'];
    }

    private static function summarize(array $items): array
    {
        $stats = ['done' => 0, 'partial' => 0, 'todo' => 0, 'na' => 0, 'event' => 0, 'pending' => 0];
        foreach ($items as $it) {
            $stats[$it['state']] = ($stats[$it['state']] ?? 0) + 1;
        }
        return $stats;
    }

    /** 单项判定（公开入口：官方档案目录复用同一判定结果，禁止另起口径） */
    public static function statusOf(string $code, ?string $asOf = null): ?array
    {
        $cfg = self::catalog()[$code] ?? null;
        if ($cfg === null) {
            return null;
        }
        $asOf = $asOf ?: date('Y-m-d');
        $marks = [];
        foreach (Database::rows("SELECT item_code, reason FROM archive_marks WHERE scope = 'unit'") as $m) {
            $marks[(string)$m['item_code']] = (string)$m['reason'];
        }
        return self::evaluate($code, $cfg, self::naAllowed($code) ? ($marks[$code] ?? null) : null, $asOf);
    }

    /** 单项判定 */
    private static function evaluate(string $code, array $cfg, ?string $naReason, string $asOf): array
    {
        $parts = [];
        $hasEvent = false;    // 存在事件类必选 part
        $hasReal = false;     // 存在可判定的必选 part（非 event、非 pending）
        $allSatisfied = true;
        $anyProgress = false;
        foreach ($cfg['parts'] as $p) {
            $part = self::evalPart($p, $asOf);
            $parts[] = $part;
            if (!empty($p['optional']) || !empty($p['pending'])) {
                continue;
            }
            if (($p['kind'] ?? '') === 'event') {
                $hasEvent = true;
                continue;
            }
            $hasReal = true;
            if (!$part['satisfied']) {
                $allSatisfied = false;
            }
            if ($part['satisfied'] || $part['partial']) {
                $anyProgress = true;
            }
        }
        if ($naReason !== null) {
            $state = 'na';
        } elseif (!$hasReal && !$hasEvent) {
            $state = 'pending';   // 全部必选 part 均为待建台账：显示“待台账”、不参与统计
        } elseif (!$hasReal) {
            $state = 'event';     // 仅事件类 part（可含 pending 提示）→ 按需
        } elseif ($allSatisfied) {
            $state = 'done';
        } elseif ($anyProgress) {
            $state = 'partial';
        } else {
            $state = 'todo';
        }
        return [
            'code'   => $code,
            'name'   => $cfg['name'],
            'self'   => $cfg['self'],
            'group'  => $cfg['group'],
            'parts'  => $parts,
            'state'  => $state,
            'na_reason' => $naReason,
            'na_allowed' => self::naReasons($code),
        ];
    }

    /** 单个 part 判定 */
    private static function evalPart(array $p, string $asOf): array
    {
        $out = [
            'kind' => $p['kind'], 'label' => $p['label'] ?? '',
            'satisfied' => false, 'partial' => false, 'display' => '', 'hint' => '',
            'optional' => !empty($p['optional']), 'pending' => !empty($p['pending']),
            'raw' => $p,
        ];
        if ($out['pending']) {
            $out['display'] = '待台账';
            return $out;
        }
        $since = ($p['since'] ?? '') === 'last3y' ? ((int)substr($asOf, 0, 4) - 2) . '-01-01' : null;

        switch ($p['kind']) {
            case 'ledger':
                $cfg = LedgerCatalog::get($p['key']);
                if ($cfg === null) {
                    $out['display'] = '台账不存在';
                    return $out;
                }
                $dateCol = self::ledgerDateCol($p['key']);
                $n = (int)Database::value(
                    'SELECT COUNT(*) FROM ' . $cfg['table'] . ($since !== null && $dateCol !== null ? " WHERE {$dateCol} >= ?" : ''),
                    $since !== null && $dateCol !== null ? [$since] : []
                );
                $out['satisfied'] = $n > 0;
                $out['display'] = $n > 0 ? "已有 {$n} 条" : '暂无记录';
                break;

            case 'doc':
                if (empty($p['per_record'])) {
                    $where = ['doc_type IN (' . self::inMarks($p['types']) . ')'];
                    $params = $p['types'];
                    if (!empty($p['link'])) {
                        $where[] = 'link_type = ?';
                        $params[] = $p['link'];
                    }
                    if (($p['period'] ?? '') === 'current_year') {
                        // 当年：年度取 period_year，空则取文档日期年份
                        $where[] = 'COALESCE(period_year, NULLIF(CAST(substr(doc_date,1,4) AS INTEGER),0)) = ' . (int)substr($asOf, 0, 4);
                    }
                    $n = (int)Database::value('SELECT COUNT(*) FROM documents WHERE ' . implode(' AND ', $where), $params);
                    $out['satisfied'] = $n > 0;
                    $out['display'] = $n > 0 ? "已有 {$n} 份" : '暂无';
                    // 到期提示：阈值统一读取 config.expiry.soon_days（与工作台同一口径）
                    $soonDays = max(1, (int)cfg('expiry.soon_days', 30));
                    if (!empty($p['expiry']) && $n > 0) {
                        $base = implode(' AND ', $where);
                        $expired = (int)Database::value(
                            "SELECT COUNT(*) FROM documents WHERE {$base} AND expire_date IS NOT NULL AND expire_date <> '' AND expire_date < ?",
                            array_merge($params, [$asOf])
                        );
                        $soon = (int)Database::value(
                            "SELECT COUNT(*) FROM documents WHERE {$base} AND expire_date IS NOT NULL AND expire_date <> '' AND expire_date >= ? AND expire_date <= ?",
                            array_merge($params, [$asOf, date('Y-m-d', strtotime($asOf . ' +' . $soonDays . ' days'))])
                        );
                        if ($expired > 0) {
                            $out['hint'] = "{$expired} 份已过期";
                        } elseif ($soon > 0) {
                            $out['hint'] = "{$soon} 份 {$soonDays} 天内到期";
                        }
                    }
                } else {
                    $records = self::perRecordTargets($p['per_record'], $p, $asOf);
                    $m = count($records);
                    $n = 0;
                    if ($m > 0) {
                        $types = $p['types'];
                        $ph = self::inMarks($types);
                        $st = Database::run(
                            "SELECT link_id, COUNT(*) AS c FROM documents
                             WHERE doc_type IN ({$ph}) AND link_type = ? GROUP BY link_id",
                            array_merge($types, [$p['link']])
                        )->fetchAll();
                        $have = [];
                        foreach ($st as $r) {
                            $have[(int)$r['link_id']] = (int)$r['c'];
                        }
                        foreach ($records as $rec) {
                            if (($have[$rec['id']] ?? 0) > 0) {
                                $n++;
                            }
                        }
                    }
                    $out['satisfied'] = $m > 0 && $n === $m;
                    $out['partial'] = $n > 0 && $n < $m;
                    $out['display'] = $m > 0 ? "{$n}/{$m} " . self::perRecordUnit($p['per_record']) . '已挂原件' : '暂无对象';
                    if ($m === 0) {
                        $out['hint'] = '可标记“不适用”';
                    }
                }
                break;

            case 'workers':
                $who = $p['who'] ?? 'all';
                $all = self::workersByWho($who);
                $m = count($all);
                $n = 0;
                if ($m > 0) {
                    $ph = self::inMarks([$p['type']]);
                    $st = Database::run(
                        "SELECT DISTINCT link_id FROM documents WHERE link_type = 'worker' AND doc_type = {$ph}",
                        [$p['type']]
                    )->fetchAll();
                    $have = array_map(fn ($r) => (int)$r['link_id'], $st);
                    $n = count(array_intersect($all, $have));
                }
                $out['satisfied'] = $m > 0 && $n === $m;
                $out['partial'] = $n > 0 && $n < $m;
                $out['display'] = $m > 0 ? "{$n}/{$m} 人" : '暂无对象';
                if ($m === 0) {
                    $out['hint'] = '可标记“不适用”';
                }
                break;

            case 'worker_years':
                $states = WorkerArchive::yearStates($p['type'], $asOf);
                $m = 0;
                $n = 0;
                foreach ($states as $byYear) {
                    foreach ($byYear as $state) {
                        if ($state === 'na' || $state === 'none') {
                            continue;
                        }
                        $m++;
                        if ($state === 'done') {
                            $n++;
                        }
                    }
                }
                $out['satisfied'] = $m > 0 && $n === $m;
                $out['partial'] = $n > 0 && $n < $m;
                $out['display'] = $m > 0 ? "{$n}/{$m} 格" : '暂无对象';
                if ($m === 0) {
                    $out['hint'] = '可标记“不适用”';
                }
                break;

            case 'system':
                if (($p['need'] ?? '') === 'exposed') {
                    $ok = \App\Models\Worker::exposedCount() > 0;
                } else {
                    $co = \App\Models\Company::row();
                    $ok = $co !== null && trim((string)$co['name']) !== '';
                }
                $out['satisfied'] = $ok;
                $out['display'] = $ok ? '可打印' : '数据未录入';
                break;

            case 'event':
                $ph = self::inMarks($p['types']);
                $n = (int)Database::value("SELECT COUNT(*) FROM documents WHERE doc_type IN ({$ph})", $p['types']);
                $out['display'] = "{$n} 份";
                $out['count'] = $n;
                // 事件类线索：体检结论为职业禁忌证/疑似职业病（或复查体检）但未见对应资料的人数
                $clueN = WorkerArchive::eventClueCount($p['types']);
                if ($clueN > 0) {
                    $out['hint'] = "另有 {$clueN} 人有线索待核实";
                }
                break;
        }
        return $out;
    }

    /** per_record 的对象集合（id + 标签） */
    private static function perRecordTargets(string $scope, array $p, string $asOf): array
    {
        $since = ((int)substr($asOf, 0, 4) - 2) . '-01-01';
        switch ($scope) {
            case 'last3y_projects':
                return array_map(fn ($r) => ['id' => (int)$r['id'], 'label' => (string)$r['name'], 'url' => url('ledgers/edit', ['key' => 'projects', 'id' => (int)$r['id']])],
                    Database::rows('SELECT id, name FROM projects WHERE start_date >= ? ORDER BY id', [$since]));
            case 'facilities':
                return array_map(fn ($r) => ['id' => (int)$r['id'], 'label' => (string)$r['name'], 'url' => url('ledgers/edit', ['key' => 'facilities', 'id' => (int)$r['id']])],
                    Database::rows('SELECT id, name FROM facilities ORDER BY id'));
            case 'warning_signs':
                return array_map(fn ($r) => ['id' => (int)$r['id'], 'label' => (string)($r['location'] ?: '#' . $r['id']), 'url' => url('ledgers/edit', ['key' => 'warning_signs', 'id' => (int)$r['id']])],
                    Database::rows('SELECT id, location FROM warning_signs ORDER BY id'));
            case 'trainings_last3y':
                return array_map(fn ($r) => ['id' => (int)$r['id'], 'label' => (string)($r['title'] ?: $r['type']) . '（' . $r['trained_on'] . '）', 'url' => url('ledgers/edit', ['key' => 'trainings', 'id' => (int)$r['id']])],
                    Database::rows("SELECT id, title, type, trained_on FROM trainings WHERE trained_on >= ? ORDER BY id", [$since]));
            case 'exam_batches':
                return array_map(fn ($r) => ['id' => (int)$r['id'], 'label' => ($r['exam_date'] ?: '未填日期') . ' ' . $r['exam_type'] . '体检批次', 'url' => url('exams/records', ['batch_id' => (int)$r['id']])],
                    Database::rows('SELECT id, exam_date, exam_type FROM exam_batches ORDER BY id'));
            case 'detect_last3y':
            case 'detect_status':
                $filter = $p['filter'] ?? [];
                $where = ['category = ?'];
                $params = [$filter['category'] ?? '定期检测'];
                if (($filter['since'] ?? '') === 'last3y') {
                    $where[] = 'test_date >= ?';
                    $params[] = $since;
                }
                return array_map(fn ($r) => ['id' => (int)$r['id'], 'label' => (string)$r['report_no'] . '（' . $r['test_date'] . '）', 'url' => url('detections/show', ['id' => (int)$r['id']])],
                    Database::rows('SELECT id, report_no, test_date FROM detection_reports WHERE ' . implode(' AND ', $where) . ' ORDER BY id', $params));
            case 'drills':
                return array_map(fn ($r) => ['id' => (int)$r['id'], 'label' => (string)$r['name'], 'url' => url('ledgers/edit', ['key' => 'drills', 'id' => (int)$r['id']])],
                    Database::rows('SELECT id, name FROM drills ORDER BY id'));
            case 'emergency_facilities':
                return array_map(fn ($r) => ['id' => (int)$r['id'], 'label' => (string)$r['name'], 'url' => url('ledgers/edit', ['key' => 'emergency_facilities', 'id' => (int)$r['id']])],
                    Database::rows('SELECT id, name FROM emergency_facilities ORDER BY id'));
            case 'rectifications':
                return array_map(fn ($r) => ['id' => (int)$r['id'], 'label' => (string)$r['item_desc'], 'url' => url('ledgers/edit', ['key' => 'rectifications', 'id' => (int)$r['id']])],
                    Database::rows('SELECT id, item_desc FROM rectifications ORDER BY id'));
        }
        return [];
    }

    private static function perRecordUnit(string $scope): string
    {
        return match ($scope) {
            'last3y_projects' => '项目',
            'facilities' => '设施',
            'warning_signs' => '标识',
            'trainings_last3y' => '场培训',
            'exam_batches' => '批次',
            'drills' => '场演练',
            'emergency_facilities' => '件设施',
            'rectifications' => '条整改',
            default => '份报告',
        };
    }

    /** who=all/exposed/role 的 worker id 集合 */
    private static function workersByWho(string $who): array
    {
        if ($who === 'role') {
            return array_map('intval', array_column(
                Database::rows("SELECT id FROM workers WHERE COALESCE(duty_role,'') <> ''"),
                'id'
            ));
        }
        if ($who === 'exposed') {
            // 曾/现接害：任一年度矩阵体检列适用（= 接害）
            $ids = [];
            foreach (WorkerArchive::yearStates('W_EXAM') as $wid => $byYear) {
                foreach ($byYear as $state) {
                    if ($state !== 'none') {
                        $ids[$wid] = true;
                        break;
                    }
                }
            }
            return array_keys($ids);
        }
        return array_map('intval', array_column(Database::rows('SELECT id FROM workers'), 'id'));
    }

    /** 台账日期列（since 过滤用） */
    private static function ledgerDateCol(string $key): ?string
    {
        return match ($key) {
            'projects' => 'start_date',
            'trainings' => 'trained_on',
            'facility_maintains' => 'maintain_date',
            'ppe_issues' => 'issued_on',
            'drills' => 'drill_date',
            'inspections' => 'check_date',
            'supervisions' => 'opinion_date',
            'plan_checks' => 'plan_date',
            default => null,
        };
    }

    private static function inMarks(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    /**
     * 某项的缺失名单（archive/missing）：
     * 返回行 ['kind'=>person|record|upload, 'label'=>, 'sub'=>, 'url'=>]。
     */
    public static function missing(string $code, ?string $asOf = null): array
    {
        $item = self::item($code, $asOf);
        if ($item === null) {
            return [];
        }
        $rows = [];
        $asOf = $asOf ?: date('Y-m-d');
        foreach ($item['parts'] as $part) {
            if ($part['pending'] || $part['satisfied']) {
                continue;
            }
            $p = $part['raw'];
            switch ($p['kind'] ?? '') {
                case 'workers':
                    $all = self::workersByWho($p['who'] ?? 'all');
                    $ph = self::inMarks([$p['type']]);
                    $have = array_map('intval', array_column(Database::run(
                        "SELECT DISTINCT link_id FROM documents WHERE link_type = 'worker' AND doc_type = {$ph}",
                        [$p['type']]
                    )->fetchAll(), 'link_id'));
                    foreach (array_diff($all, $have) as $wid) {
                        $w = Database::row('SELECT id, name, archive_no FROM workers WHERE id = ?', [$wid]);
                        if ($w) {
                            $rows[] = ['kind' => 'person', 'label' => (string)$w['name'], 'sub' => (string)$w['archive_no'],
                                       'url' => url('workers/files', ['id' => (int)$w['id']])];
                        }
                    }
                    break;
                case 'worker_years':
                    $states = WorkerArchive::yearStates($p['type'], $asOf);
                    foreach ($states as $wid => $byYear) {
                        foreach ($byYear as $y => $state) {
                            if (in_array($state, ['todo', 'reg_only'], true)) {
                                $w = Database::row('SELECT id, name, archive_no FROM workers WHERE id = ?', [$wid]);
                                if ($w) {
                                    $rows[] = ['kind' => 'cell', 'label' => (string)$w['name'], 'sub' => $y . ' 年 · ' . DocTypes::nameOf($p['type']),
                                               'url' => url('workers/files', ['id' => (int)$w['id'], 'range' => 'all'])];
                                }
                            }
                        }
                    }
                    break;
                case 'doc':
                    if (!empty($p['per_record'])) {
                        foreach (self::perRecordTargets($p['per_record'], $p, $asOf) as $rec) {
                            $ph = self::inMarks($p['types']);
                            $c = (int)Database::value(
                                "SELECT COUNT(*) FROM documents WHERE doc_type IN ({$ph}) AND link_type = ? AND link_id = ?",
                                array_merge($p['types'], [$p['link'], $rec['id']])
                            );
                            if ($c === 0) {
                                $rows[] = ['kind' => 'record', 'label' => $rec['label'], 'sub' => '缺原件', 'url' => $rec['url']];
                            }
                        }
                    } else {
                        $rows[] = ['kind' => 'upload', 'label' => (string)($p['label'] ?? '该资料'), 'sub' => '尚未上传', 'url' => ''];
                    }
                    break;
                case 'ledger':
                    $rows[] = ['kind' => 'ledger', 'label' => '「' . $part['label'] . '」暂无记录', 'sub' => '去台账录入', 'url' => url('ledgers/index', ['key' => $p['key']])];
                    break;
            }
            if ($rows) {
                break;   // 只列第一个未满足 part 的缺失对象
            }
        }
        return $rows;
    }
}
