<?php
/**
 * 资料类型注册表：资料类型的唯一来源。
 * 表单下拉、个人年度矩阵、单位资料清单的列与规则都从这里取值。
 *
 * 编码约定：W_ 劳动者类（scope=worker）、U_ 单位/记录类（scope=unit|record）、X_ 虚拟列（不入库）。
 * 字段说明：
 *   name      名称；scope worker|unit|record
 *   category  默认文档类别（Dict::documentCategories() 之一）
 *   group     归属档案组：three|management|training|monitoring|health|personal|general
 *   cycle     yearly（按年）| once（一次性/长期）| event（事件）| none（不判定）
 *   applies   仅 worker 类：all（全员）| exposed（接害年/曾接害）| role（有职务角色）
 *   require   always（无条件要求）| evidence（有登记证据才要求）
 *   links     允许挂载的 link_type 列表（空 = 单位级，不挂具体记录或可选 workplace）
 *   title_tpl 快速上传默认标题：{type} {name} {year}
 */
declare(strict_types=1);

namespace App\Helpers;

class DocTypes
{
    /** 虚拟列编码（不入库，仅个人矩阵使用） */
    public const X_DETECT = 'X_DETECT';

    /** @var array<string, array>|null 注册表缓存 */
    private static ?array $cache = null;

    private static function registry(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $w = [
            // ---- 一次性 / 长期（cycle=once）----
            'W_CONTRACT' => ['name' => '劳动合同', 'scope' => 'worker', 'category' => '劳动用工资料', 'group' => 'personal',
                'cycle' => 'once', 'applies' => 'all', 'require' => 'always', 'links' => ['worker'], 'title_tpl' => '{type} {name}'],
            'W_NOTICE' => ['name' => '职业病危害告知书', 'scope' => 'worker', 'category' => '劳动用工资料', 'group' => 'personal',
                'cycle' => 'once', 'applies' => 'exposed', 'require' => 'always', 'links' => ['worker'], 'title_tpl' => '{type} {name}'],
            'W_APPOINT' => ['name' => '管理人员任命文件', 'scope' => 'worker', 'category' => '劳动用工资料', 'group' => 'management',
                'cycle' => 'once', 'applies' => 'role', 'require' => 'always', 'links' => ['worker'], 'title_tpl' => '{type} {name}'],
            'W_DUTY' => ['name' => '管理人员职责说明', 'scope' => 'worker', 'category' => '劳动用工资料', 'group' => 'management',
                'cycle' => 'once', 'applies' => 'role', 'require' => 'always', 'links' => ['worker'], 'title_tpl' => '{type} {name}'],
            // ---- 年度类（cycle=yearly）----
            'W_TRAIN' => ['name' => '培训记录（签到、试卷）', 'scope' => 'worker', 'category' => '培训资料', 'group' => 'training',
                'cycle' => 'yearly', 'applies' => 'exposed_or_role', 'require' => 'always', 'links' => ['worker'], 'title_tpl' => '{type} {name} {year}'],
            'W_TRAIN_MGR' => ['name' => '管理人员培训证书/学时证明', 'scope' => 'worker', 'category' => '培训资料', 'group' => 'training',
                'cycle' => 'yearly', 'applies' => 'role', 'require' => 'always', 'links' => ['worker'], 'title_tpl' => '{type} {name} {year}'],
            'W_PPE' => ['name' => '防护用品领用签字', 'scope' => 'worker', 'category' => '其他', 'group' => 'management',
                'cycle' => 'yearly', 'applies' => 'exposed', 'require' => 'evidence', 'links' => ['worker'], 'title_tpl' => '{type} {name} {year}'],
            'W_EXAM' => ['name' => '体检报告', 'scope' => 'worker', 'category' => '体检报告', 'group' => 'health',
                'cycle' => 'yearly', 'applies' => 'exposed', 'require' => 'always', 'links' => ['worker', 'exam_record'], 'title_tpl' => '{type} {name} {year}'],
            'W_EXAM_NOTICE' => ['name' => '体检结果书面告知/签收', 'scope' => 'worker', 'category' => '体检报告', 'group' => 'health',
                'cycle' => 'yearly', 'applies' => 'exposed', 'require' => 'evidence', 'links' => ['worker', 'exam_record'], 'title_tpl' => '{type} {name} {year}'],
            // ---- 事件类（cycle=event，不判缺失）----
            'W_TRANSFER' => ['name' => '调岗通知书', 'scope' => 'worker', 'category' => '劳动用工资料', 'group' => 'health',
                'cycle' => 'event', 'applies' => '', 'require' => '', 'links' => ['worker', 'exam_record'], 'title_tpl' => '{type} {name}'],
            'W_RECHECK' => ['name' => '复查通知书及记录', 'scope' => 'worker', 'category' => '体检报告', 'group' => 'health',
                'cycle' => 'event', 'applies' => '', 'require' => '', 'links' => ['worker', 'exam_record'], 'title_tpl' => '{type} {name}'],
            'W_SUSPECT' => ['name' => '疑似职业病诊断申请材料', 'scope' => 'worker', 'category' => '诊断证明', 'group' => 'health',
                'cycle' => 'event', 'applies' => '', 'require' => '', 'links' => ['worker', 'disease'], 'title_tpl' => '{type} {name}'],
            'W_DIAG' => ['name' => '职业病诊断证明', 'scope' => 'worker', 'category' => '诊断证明', 'group' => 'health',
                'cycle' => 'event', 'applies' => '', 'require' => '', 'links' => ['worker', 'disease'], 'title_tpl' => '{type} {name}'],
            'W_COPY_SIGN' => ['name' => '监护档案复印件签收', 'scope' => 'worker', 'category' => '体检报告', 'group' => 'health',
                'cycle' => 'event', 'applies' => '', 'require' => '', 'links' => ['worker'], 'title_tpl' => '{type} {name}'],
            'W_OTHER' => ['name' => '其他个人资料', 'scope' => 'worker', 'category' => '其他', 'group' => 'personal',
                'cycle' => 'none', 'applies' => '', 'require' => '', 'links' => ['worker'], 'title_tpl' => '{type} {name}'],
        ];
        $u = [
            'U_RULES' => ['name' => '职业卫生管理制度/操作规程', 'scope' => 'unit', 'category' => '管理制度与操作规程', 'group' => 'management', 'links' => []],
            'U_ORG' => ['name' => '管理机构设立文件', 'scope' => 'unit', 'category' => '管理制度与操作规程', 'group' => 'management', 'links' => []],
            // 危害申报表由表2-2系统打印生成（无需上传原件），仅回执需归档
            'U_DECL_RECEIPT' => ['name' => '危害申报回执', 'scope' => 'unit', 'category' => '申报材料', 'group' => 'management', 'links' => []],
            'U_LAYOUT' => ['name' => '现场布局图/照片', 'scope' => 'unit', 'category' => '现场照片', 'group' => 'management', 'links' => []],
            'U_BOARD_PHOTO' => ['name' => '公告栏照片', 'scope' => 'unit', 'category' => '现场照片', 'group' => 'management', 'links' => []],
            'U_NOTICE_CARD' => ['name' => '告知卡', 'scope' => 'unit', 'category' => '现场照片', 'group' => 'management', 'links' => []],
            'U_PPE_STD' => ['name' => '防护用品配发标准', 'scope' => 'unit', 'category' => '管理制度与操作规程', 'group' => 'management', 'links' => []],
            'U_PPE_PURCHASE' => ['name' => '防护用品采购记录', 'scope' => 'unit', 'category' => '其他', 'group' => 'management', 'links' => []],
            'U_EMERGENCY_PLAN' => ['name' => '应急预案', 'scope' => 'unit', 'category' => '应急预案', 'group' => 'management', 'links' => []],
            'U_TRAIN_PLAN' => ['name' => '年度培训计划', 'scope' => 'unit', 'category' => '培训资料', 'group' => 'training', 'links' => [], 'cycle' => 'yearly'],
            'U_QUAL_DETECT' => ['name' => '检测机构资质证书', 'scope' => 'unit', 'category' => '资质证书', 'group' => 'monitoring', 'links' => []],
            'U_QUAL_EXAM' => ['name' => '体检机构资质证书', 'scope' => 'unit', 'category' => '资质证书', 'group' => 'health', 'links' => []],
            'U_PROMISE' => ['name' => '单位承诺说明', 'scope' => 'unit', 'category' => '其他', 'group' => 'general', 'links' => []],
            'U_CREDIT' => ['name' => '信用中国等网站查询结果', 'scope' => 'unit', 'category' => '其他', 'group' => 'general', 'links' => [], 'cycle' => 'yearly'],
            'U_PRE_EVAL' => ['name' => '预评价报告', 'scope' => 'record', 'category' => '三同时资料', 'group' => 'three', 'links' => ['project']],
            'U_DESIGN' => ['name' => '职业病防护设施设计专篇', 'scope' => 'record', 'category' => '三同时资料', 'group' => 'three', 'links' => ['project']],
            'U_CTRL_EVAL' => ['name' => '控制效果评价报告', 'scope' => 'record', 'category' => '三同时资料', 'group' => 'three', 'links' => ['project']],
            'U_ACCEPT' => ['name' => '竣工验收材料', 'scope' => 'record', 'category' => '三同时资料', 'group' => 'three', 'links' => ['project']],
            'U_FAC_PHOTO' => ['name' => '防护设施照片', 'scope' => 'record', 'category' => '现场照片', 'group' => 'management', 'links' => ['ledger_facilities']],
            'U_SIGN_PHOTO' => ['name' => '警示标识现场照片', 'scope' => 'record', 'category' => '现场照片', 'group' => 'management', 'links' => ['ledger_warning_signs']],
            'U_TRAIN_SIGN' => ['name' => '培训签到表', 'scope' => 'record', 'category' => '培训资料', 'group' => 'training', 'links' => ['ledger_trainings']],
            'U_TRAIN_PHOTO' => ['name' => '培训照片', 'scope' => 'record', 'category' => '培训资料', 'group' => 'training', 'links' => ['ledger_trainings']],
            'U_TRAIN_MATERIAL' => ['name' => '培训讲义', 'scope' => 'record', 'category' => '培训资料', 'group' => 'training', 'links' => ['ledger_trainings']],
            'U_TRAIN_EXAM' => ['name' => '培训试卷', 'scope' => 'record', 'category' => '培训资料', 'group' => 'training', 'links' => ['ledger_trainings']],
            'U_DRILL_FILE' => ['name' => '应急演练记录/照片/总结', 'scope' => 'record', 'category' => '应急预案', 'group' => 'management', 'links' => ['ledger_drills']],
            'U_EMG_PHOTO' => ['name' => '应急设施现场照片', 'scope' => 'record', 'category' => '现场照片', 'group' => 'management', 'links' => ['ledger_emergency_facilities']],
            'U_DETECT_REPORT' => ['name' => '检测/评价报告', 'scope' => 'record', 'category' => '检测评价报告', 'group' => 'monitoring', 'links' => ['detection_report']],
            'U_EXAM_SUMMARY' => ['name' => '职业健康检查总结报告', 'scope' => 'record', 'category' => '体检报告', 'group' => 'health', 'links' => ['exam_batch']],
            'U_RECT_PLAN' => ['name' => '超标点整改方案', 'scope' => 'record', 'category' => '检测评价报告', 'group' => 'monitoring', 'links' => ['ledger_rectifications']],
            'U_RECT_COMPARE' => ['name' => '整改前后对比', 'scope' => 'record', 'category' => '检测评价报告', 'group' => 'monitoring', 'links' => ['ledger_rectifications']],
        ];
        $x = [
            // 虚拟列：仅个人矩阵的“检测报告（间接）”使用，不入库、不出现在任何下拉
            self::X_DETECT => ['name' => '检测报告（间接）', 'scope' => 'worker', 'category' => '检测评价报告', 'group' => 'monitoring',
                'cycle' => 'yearly', 'applies' => 'exposed', 'require' => 'always', 'links' => [], 'title_tpl' => '', 'virtual' => true],
        ];
        self::$cache = $w + $u + $x;
        return self::$cache;
    }

    /** 全部类型（不含虚拟列） */
    public static function all(): array
    {
        return array_filter(self::registry(), fn ($t) => empty($t['virtual']));
    }

    /** 单个类型配置；不存在返回 null */
    public static function get(string $code): ?array
    {
        $t = self::registry()[$code] ?? null;
        if ($t === null || !empty($t['virtual'])) {
            return null;
        }
        return $t;
    }

    /** 按 scope 过滤（worker|unit|record） */
    public static function byScope(string $scope): array
    {
        return array_filter(self::all(), fn ($t) => ($t['scope'] ?? '') === $scope);
    }

    /** 下拉选项：code => 名称 */
    public static function options(string $scope = ''): array
    {
        $src = $scope !== '' ? self::byScope($scope) : self::all();
        $out = [];
        foreach ($src as $code => $t) {
            $out[$code] = $t['name'];
        }
        return $out;
    }

    /** 类型的默认文档类别 */
    public static function categoryOf(string $code): string
    {
        return (string)(self::registry()[$code]['category'] ?? '');
    }

    /** 类型名称（含虚拟列；未知编码原样返回） */
    public static function nameOf(string $code): string
    {
        if ($code === '') {
            return '';
        }
        return (string)(self::registry()[$code]['name'] ?? $code);
    }

    /**
     * 快速上传默认标题。$ctx：['name'=>劳动者姓名, 'year'=>年度]。
     * 占位符：{type}=类型名称、{name}=劳动者姓名、{year}=年度。
     */
    public static function defaultTitle(string $code, array $ctx = []): string
    {
        $t = self::registry()[$code] ?? null;
        if ($t === null) {
            return '';
        }
        $tpl = $t['title_tpl'] ?? '';
        if ($tpl === '') {
            $tpl = ($t['scope'] ?? '') === 'worker' ? '{type} {name}' : '{type}';
            if (($t['cycle'] ?? '') === 'yearly') {
                $tpl = ($t['scope'] ?? '') === 'worker' ? '{type} {name} {year}' : '{type} {year}';
            }
        }
        return trim(str_replace(
            ['{type}', '{name}', '{year}'],
            [$t['name'], (string)($ctx['name'] ?? ''), (string)($ctx['year'] ?? '')],
            $tpl
        ));
    }

    /**
     * 该资料类型是否适用于当前对象（规则唯一来源）。
     * $ctx：['exposed'=>本人该年/至今是否接害, 'role'=>是否有职务角色, 'evidence'=>是否有登记证据]
     * 读取注册表的 applies / require 字段；不再在业务代码里硬编码适用规则。
     */
    public static function appliesTo(string $code, array $ctx = []): bool
    {
        $t = self::registry()[$code] ?? null;
        if ($t === null) {
            return false;
        }
        $exposed = !empty($ctx['exposed']);
        $role = !empty($ctx['role']);
        $ok = match ($t['applies'] ?? '') {
            'all'             => true,
            'exposed'         => $exposed,
            'role'            => $role,
            'exposed_or_role' => $exposed || $role,
            default           => false,
        };
        if (!$ok) {
            return false;
        }
        return ($t['require'] ?? '') !== 'evidence' || !empty($ctx['evidence']);
    }

    /** 年度矩阵列（顺序即展示顺序；名称来自注册表） */
    public static function yearColumns(): array
    {
        $out = [];
        foreach ([self::X_DETECT, 'W_EXAM', 'W_EXAM_NOTICE', 'W_TRAIN', 'W_TRAIN_MGR', 'W_PPE'] as $code) {
            $out[$code] = ['name' => self::nameOf($code)];
        }
        return $out;
    }

    /** 一次性 / 长期资料类型 */
    public static function onceTypes(): array
    {
        return array_keys(array_filter(
            self::registry(),
            fn ($t) => ($t['cycle'] ?? '') === 'once'
        ));
    }

    /** 事件类资料类型（不判缺失） */
    public static function eventTypes(): array
    {
        return array_keys(array_filter(
            self::registry(),
            fn ($t) => ($t['cycle'] ?? '') === 'event'
        ));
    }

    /** 允许挂到某 link_type 的类型（下拉联动用） */
    public static function forLink(string $linkType): array
    {
        return array_filter(self::all(), fn ($t) => in_array($linkType, $t['links'] ?? [], true));
    }

    /** 从业务页（link_type）进入上传时预选的资料类型 */
    public static function defaultForLink(string $linkType): string
    {
        return match ($linkType) {
            'detection_report' => 'U_DETECT_REPORT',
            'exam_batch'       => 'U_EXAM_SUMMARY',
            'exam_record'      => 'W_EXAM',
            'ledger_facilities' => 'U_FAC_PHOTO',
            'ledger_warning_signs' => 'U_SIGN_PHOTO',
            'ledger_trainings' => 'U_TRAIN_SIGN',
            'ledger_drills'    => 'U_DRILL_FILE',
            'ledger_emergency_facilities' => 'U_EMG_PHOTO',
            'ledger_rectifications' => 'U_RECT_PLAN',
            default            => '',
        };
    }
}
