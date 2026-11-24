<?php
/**
 * 六类职业卫生档案目录：严格对应《职业卫生档案管理规范》所列六类档案（一～六）。
 * 每张规范表样标注数据来源（台账 / 现有功能页 / 自动汇总），用于总览与完整度。
 */
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

class ArchiveCatalog
{
    /** 六类档案分组（一～六，共 23 张规范表样；表号编码 表1-1 ～ 表6-3 便于对照规范附件） */
    public static function groups(): array
    {
        $groups = [
            [
                'no'    => 1,
                'key'   => 'three',
                'docs'  => ['三同时资料'],
                'title' => '建设项目职业卫生“三同时”档案',
                'short' => '三同时档案',
                'intro' => '新建、改建、扩建、技术改造和技术引进项目，其职业病防护设施应与主体工程同时设计、同时施工、同时投入生产和使用。',
                'items' => [
                    ['no' => '表1-1', 'nav' => '三同时台账', 'title' => '建设项目职业卫生“三同时”情况表', 'desc' => '项目名称、类型、投资额，预评价、设计审查与竣工验收情况', 'table' => 'projects', 'ledger' => 'projects'],
                ],
            ],
            [
                'no'    => 2,
                'key'   => 'management',
                'docs'  => ['法律法规', '管理制度与操作规程', '申报材料', '现场照片', '应急预案', '资质证书'],
                'title' => '职业卫生管理档案',
                'short' => '管理档案',
                'intro' => '涵盖防治计划、危害申报、防治经费、防护设施、个人防护用品、警示标识、检查处理与监管意见落实等日常管理资料。',
                'items' => [
                    ['no' => '表2-1', 'nav' => '防治计划实施检查', 'title' => '年度职业病防治计划实施检查表', 'desc' => '年度计划内容、实施情况与负责人', 'table' => 'mgmt_plan_checks', 'ledger' => 'plan_checks'],
                    ['no' => '表2-2', 'nav' => '危害申报·单位信息', 'title' => '职业病危害项目申报基本情况表', 'desc' => '单位基本信息、危害因素及接触人数（由单位信息汇总）', 'count_sql' => "SELECT COALESCE((SELECT CASE WHEN TRIM(name) <> '' THEN 1 ELSE 0 END FROM company ORDER BY id LIMIT 1), 0)", 'route' => 'company/edit'],
                    ['no' => '表2-3', 'nav' => '防治经费', 'title' => '职业病防治经费一览表', 'desc' => '经费项目、工作内容、金额与负责人', 'table' => 'mgmt_budgets', 'ledger' => 'budgets'],
                    ['no' => '表2-4', 'nav' => '防护设施', 'title' => '职业病防护设施一览表', 'desc' => '设施名称、型号、用途、安装与验收情况', 'table' => 'facilities', 'ledger' => 'facilities'],
                    ['no' => '表2-5', 'nav' => '设施维护检修', 'title' => '职业病防护设施维护检修记录', 'desc' => '检修日期、原因、费用、效果与验收意见', 'table' => 'facility_maintains', 'ledger' => 'facility_maintains'],
                    ['no' => '表2-6', 'nav' => '防护用品发放', 'title' => '个人防护用品购买发放记录', 'desc' => '劳动者、防护用品、型号、数量与领取日期', 'table' => 'ppe_issues', 'ledger' => 'ppe_issues'],
                    ['no' => '表2-7', 'nav' => '警示标识', 'title' => '工作场所警示标识一览表', 'desc' => '设置地点、标识类别、对应危害因素与设置日期', 'table' => 'warning_signs', 'ledger' => 'warning_signs'],
                    ['no' => '表2-8', 'nav' => '检查和处理记录', 'title' => '职业卫生检查和处理记录', 'desc' => '检查内容、发现问题、处理措施与整改状态', 'table' => 'mgmt_inspections', 'ledger' => 'inspections'],
                    ['no' => '表2-9', 'nav' => '监管意见落实', 'title' => '职业卫生监管意见和落实情况', 'desc' => '监管部门意见、整改落实与完成状态', 'table' => 'mgmt_supervisions', 'ledger' => 'supervisions'],
                    // 系统扩充表样：非规范附件表样，标注为系统扩充
                    ['no' => '表2-10', 'nav' => '应急演练', 'title' => '应急演练记录', 'desc' => '演练日期、科目、参加人数与总结（系统扩充表样）', 'table' => 'drills', 'ledger' => 'drills', 'extended' => true],
                    ['no' => '表2-11', 'nav' => '应急设施', 'title' => '应急设施台账', 'desc' => '设施名称、型号、数量、存放位置与检查状态（系统扩充表样）', 'table' => 'emergency_facilities', 'ledger' => 'emergency_facilities', 'extended' => true],
                ],
            ],
            [
                'no'    => 3,
                'key'   => 'training',
                'docs'  => ['培训资料'],
                'title' => '职业卫生宣传培训档案',
                'short' => '宣传培训档案',
                'intro' => '记录用人单位主要负责人、职业卫生管理人员及劳动者参加职业卫生培训的情况。',
                'items' => [
                    ['no' => '表3-1', 'nav' => '培训记录与签到', 'title' => '职业卫生宣传培训记录表（含签到表）', 'desc' => '培训类型、内容、学时、授课人及参训人员签到', 'table' => 'trainings', 'ledger' => 'trainings'],
                ],
            ],
            [
                'no'    => 4,
                'key'   => 'monitoring',
                'docs'  => ['检测评价报告', '资质证书'],
                'extras' => [
                    // 作业场所 / 危害因素字典已移入「基础数据设置」菜单（用于初期建档）
                    ['key' => 'detection_items', 'nav' => '检测结果查询', 'route' => 'detections/items'],
                ],
                'title' => '职业病危害因素监测与检测评价档案',
                'short' => '监测检测档案',
                'intro' => '记录可能产生职业病危害的设备、材料、化学品，接触危害因素汇总，以及日常监测与检测评价结果。',
                'items' => [
                    ['no' => '表4-1', 'nav' => '设备材料化学品', 'title' => '可能产生职业病危害的设备、材料、化学品一览表', 'desc' => '设备/材料/化学品名称、对应危害因素（可多项）与使用场所', 'table' => 'materials', 'ledger' => 'materials'],
                    ['no' => '表4-2', 'nav' => '岗位危害配置', 'title' => '接触职业病危害因素汇总表', 'desc' => '岗位、危害因素、接触方式与防护措施（岗位危害配置汇总）', 'table' => 'post_hazards', 'route' => 'posts/index'],
                    ['no' => '表4-3', 'nav' => '检测报告与明细', 'title' => '职业病危害因素日常监测、检测评价汇总表', 'desc' => '检测报告、检测点结果、接触限值与判定结论', 'table' => 'detection_reports', 'route' => 'detections/index'],
                    // 系统扩充表样：非规范附件表样，标注为系统扩充
                    ['no' => '表4-4', 'nav' => '超标点整改', 'title' => '超标点整改台账', 'desc' => '超标来源报告、整改方案、完成情况与复测结果（系统扩充表样）', 'table' => 'rectifications', 'ledger' => 'rectifications', 'extended' => true],
                ],
            ],
            [
                'no'    => 5,
                'key'   => 'health',
                'docs'  => ['体检报告', '诊断证明'],
                'title' => '用人单位职业健康监护管理档案',
                'short' => '健康监护档案',
                'intro' => '记录职业健康检查组织情况、异常结果、职业病与疑似病例、危害事故及监护档案汇总。',
                'items' => [
                    ['no' => '表5-1', 'nav' => '体检批次', 'title' => '职业健康检查结果汇总表', 'desc' => '检查日期、机构、应检/实检人数与检查种类', 'table' => 'exam_batches', 'route' => 'exams/index'],
                    ['no' => '表5-2', 'nav' => '异常结果登记', 'title' => '职业健康检查异常结果登记表', 'desc' => '检查结论异常人员登记与处理情况', 'count_sql' => "SELECT COUNT(*) FROM exam_records WHERE TRIM(COALESCE(conclusion,'')) NOT IN ('', '目前未见异常', '未见异常')", 'route' => 'exams/records', 'params' => ['abnormal' => 1]],
                    ['no' => '表5-3', 'nav' => '职业病患者', 'title' => '职业病患者一览表', 'desc' => '确诊职业病人员的诊断与处理情况', 'count_sql' => 'SELECT COUNT(*) FROM diseases WHERE is_suspected = 0', 'route' => 'patients/index', 'params' => ['type' => 'confirmed']],
                    ['no' => '表5-4', 'nav' => '疑似职业病患者', 'title' => '疑似职业病患者一览表', 'desc' => '疑似职业病人员的检查与处理情况', 'count_sql' => 'SELECT COUNT(*) FROM diseases WHERE is_suspected = 1', 'route' => 'patients/index', 'params' => ['type' => 'suspected']],
                    ['no' => '表5-5', 'nav' => '危害事故报告', 'title' => '职业病危害事故报告与处理记录表', 'desc' => '事故经过、接触/发病/住院/死亡人数与处理意见', 'table' => 'health_accidents', 'ledger' => 'accidents'],
                    ['no' => '表5-6', 'nav' => '监护档案汇总', 'title' => '职业健康监护档案汇总表', 'desc' => '劳动者档案号、建档与转移时间、签字确认情况', 'table' => 'workers', 'route' => 'workers/index'],
                ],
            ],
            [
                'no'    => 6,
                'key'   => 'personal',
                'docs'  => ['劳动用工资料', '体检报告', '诊断证明'],
                'extras' => [
                    ['key' => 'import', 'nav' => 'CSV 批量导入', 'route' => 'workers/import'],
                ],
                'title' => '劳动者个人职业健康监护档案',
                'short' => '个人健康档案',
                'intro' => '一人一档，包含劳动者个人信息卡、工作场所危害因素检测结果及历次职业健康检查结果与处理情况。',
                'items' => [
                    ['no' => '表6-1', 'nav' => '劳动者信息卡', 'title' => '劳动者个人信息卡', 'desc' => '个人基本信息、职业史、既往病史与职业病诊断史', 'table' => 'workers', 'route' => 'workers/index'],
                    ['no' => '表6-2', 'nav' => '危害因素检测结果', 'title' => '工作场所职业病危害因素检测结果', 'desc' => '劳动者所在岗位的危害因素检测点、结果与限值', 'table' => 'detection_items', 'route' => 'detections/items'],
                    ['no' => '表6-3', 'nav' => '历次体检记录', 'title' => '历次职业健康检查结果及处理情况', 'desc' => '历次体检结论、处理意见与本人签字', 'table' => 'exam_records', 'route' => 'exams/records'],
                ],
            ],
        ];
        // 规范表样标题唯一来源为 PrintSpec；目录内不再单独维护，避免漂移。
        foreach ($groups as &$g) {
            foreach ($g['items'] as &$it) {
                $title = PrintSpec::titleOf((string)($it['no'] ?? ''));
                if ($title !== '') {
                    $it['title'] = $title;
                }
            }
            unset($it);
        }
        unset($g);
        return $groups;
    }

    /**
     * 六类档案盒官方目录（主线）：逐条对应六个附件的目录项，共 48 项。
     * 每项 [官方序号, 名称, 性质 form|doc, 落地键(表号|DocTypes|台账), 自评表关联项号, 判定清单项Cxx]。
     * form=规范表样；doc=文档类（挂原件）。self 对应自评表 1–19 项，空=无直接对应。
     * 第 6 列为该目录项的判定来源（资料清单 C01–C36）：有值则状态直接取清单判定，
     * 空值表示「不判定」（只提供上传入口，不显示 done/todo，也不按类别误计份数）。
     */
    public static function filingCatalog(): array
    {
        return [
            'three' => [
                ['1', '建设项目职业卫生"三同时"审查登记表', 'form', '表1-1', '5', 'C01'],
                ['2', '建设项目批准文件', 'doc', '', '5', 'C02'],
                ['3', '职业病危害预评价委托书与预评价报告', 'doc', 'U_PRE_EVAL', '5', 'C02'],
                ['4', '建设项目职业病防护设施设计专篇', 'doc', 'U_DESIGN', '5', 'C02'],
                ['5', '职业病危害控制效果评价委托书与控制效果评价报告', 'doc', 'U_CTRL_EVAL', '5', 'C02'],
                ['6', '对预评价报告、设计专篇、控制效果评价报告的评审意见', 'doc', '', '5', 'C02'],
                ['7', '安全监管部门审核、审查、验收批文', 'doc', 'U_ACCEPT', '5', 'C02'],
                ['8', '建设项目职业病危害防治法律责任承诺书', 'doc', '', '5', 'C35'],
                ['9', '全套竣工图纸、验收报告、竣工总结', 'doc', '', '5', 'C02'],
                ['10', '工程改建、扩建及维修、使用中变更的图纸及有关材料', 'doc', '', '5', ''],
            ],
            'management' => [
                ['1', '职业病防治法律、行政法规、规章、标准、文件', 'doc', '', '1', ''],
                ['2', '职业病防治领导机构及职业卫生管理机构成立文件', 'doc', 'U_ORG', '2', 'C04'],
                ['3', '职业病防治年度计划及实施方案', 'form', '表2-1', '1', ''],
                ['4', '职业卫生管理制度及重点岗位职业卫生操作规程', 'doc', 'U_RULES', '1', 'C03'],
                ['5', '职业病危害项目申报表及回执', 'form', '表2-2', '4', 'C07'],
                ['6', '职业病防治经费', 'form', '表2-3', '', ''],
                ['7', '职业病防护设施一览表', 'form', '表2-4', '10', 'C09'],
                ['8', '职业病防护设施维护和检修记录', 'form', '表2-5', '10', 'C10'],
                ['9', '个人防护用品的购买、发放使用记录', 'form', '表2-6', '11', 'C13'],
                ['10', '警示标识与职业病危害告知', 'form', '表2-7', '15', 'C15'],
                ['11', '职业病危害事故应急救援预案', 'doc', 'U_EMERGENCY_PLAN', '18', 'C16'],
                ['12', '用人单位职业卫生检查和处理记录', 'form', '表2-8', '', ''],
                ['13', '职业卫生监管意见和落实情况资料', 'form', '表2-9', '19', ''],
            ],
            'training' => [
                ['1', '用人单位职业卫生培训计划', 'doc', 'U_TRAIN_PLAN', '13', 'C20'],
                ['2', '负责人、职业卫生管理人员职业卫生培训证明', 'doc', 'W_TRAIN_MGR', '12', 'C19'],
                ['3', '劳动者职业卫生宣传培训', 'form', '表3-1', '13', 'C21'],
                ['4', '年度职业卫生培训工作总结', 'doc', '', '13', 'C21'],
            ],
            'monitoring' => [
                ['1', '生产工艺流程', 'doc', '', '', ''],
                ['2', '职业病危害因素检测点分布示意图', 'doc', '', '7', ''],
                ['3', '可能产生职业病危害设备、材料和化学品一览表', 'form', '表4-1', '', ''],
                ['4', '接触职业病危害因素汇总表', 'form', '表4-2', '', ''],
                ['5', '职业病危害因素日常监测季报汇总表', 'form', '表4-3', '7', 'C22'],
                ['6', '职业卫生技术服务机构资质证书', 'doc', 'U_QUAL_DETECT', '7', 'C24'],
                ['7', '职业病危害因素检测评价合同书', 'doc', '', '', ''],
                ['8', '职业病危害检测与评价报告书', 'doc', 'U_DETECT_REPORT', '7、8', 'C22'],
                ['9', '职业病危害因素检测与评价结果报告', 'doc', 'U_DETECT_REPORT', '9', 'C23'],
            ],
            'health' => [
                ['1', '职业健康检查机构资质证书', 'doc', 'U_QUAL_EXAM', '16', 'C28'],
                ['2', '职业健康检查结果汇总表', 'form', '表5-1', '16', 'C26'],
                ['3', '职业健康检查异常结果登记表', 'form', '表5-2', '16', 'C29'],
                ['4', '职业病患者、疑似职业病患者一览表', 'form', '表5-3、表5-4', '16', 'C32'],
                ['5', '职业病和疑似职业病人的报告', 'doc', '', '16', 'C32'],
                ['6', '职业病危害事故报告和处理记录', 'form', '表5-5', '', ''],
                ['7', '职业健康监护档案汇总表', 'form', '表5-6', '16', 'C26'],
            ],
            'personal' => [
                ['1', '劳动者个人信息卡', 'form', '表6-1', '', ''],
                ['2', '工作场所职业病危害因素检测结果', 'form', '表6-2', '', ''],
                ['3', '历次职业健康检查结果及处理情况', 'form', '表6-3', '16', ''],
                ['4', '历次职业健康体检报告、职业病诊疗等资料', 'doc', 'W_EXAM、W_DIAG', '16', 'C27'],
                ['5', '其他职业健康监护资料', 'doc', '', '', ''],
            ],
        ];
    }
/**
 * 目录项状态与入口（供档案盒目录页逐项核对）。
 * 状态唯一来源：目录项第 6 列指定的资料清单 Cxx（ArchiveChecklist::statusOf）；
 * 未指定 Cxx 的项为「不判定」（state=info），不再在本函数里另算 done/todo。
 */
public static function filingStatus(array $entry, string $groupKey): array
{
    [$seq, $name, $kind, $key, $self] = $entry;
    $checkCode = (string)($entry[5] ?? '');
    $count = 0;
    $url = '#';
    $label = '前往';
    $hint = '';
    $printUrl = null;

    if ($kind === 'form') {
        $forms = explode('、', $key);
        foreach ($forms as $formNo) {
            foreach (self::groups() as $g) {
                foreach ($g['items'] as $it) {
                    if (($it['no'] ?? '') === $formNo) {
                        $count += self::countItem($it);
                        if ($url === '#') {
                            $a = self::actions($it);
                            $url = $a['entry_url'];
                            $label = $a['entry_label'];
                            $printUrl = $a['print_url'];
                        }
                    }
                }
            }
        }
        $hint = '规范表样';
    } else {
        $types = array_values(array_filter(array_map('trim', explode('、', $key)), fn($x) => $x !== ''));
        if ($types) {
            $ph = implode(',', array_fill(0, count($types), '?'));
            $count = (int)Database::value("SELECT COUNT(*) FROM documents WHERE doc_type IN ($ph)", $types);
            $first = $types[0];
            $url = url('documents/index', ['doc_type' => $first]);
            $label = '查看原件';
            $hint = implode('、', array_map(fn($t) => \App\Helpers\DocTypes::nameOf($t), $types));
        } else {
            // 无落地键：只给上传入口，份数记 0（旧实现按首类别统计会把整盒文档算到本项）
            $g = self::group($groupKey);
            $cat = ($g['docs'] ?? [])[0] ?? '';
            $url = url('documents/create', ['category' => $cat]);
            $label = '上传原件';
            $hint = $cat !== '' ? '按「' . $cat . '」类别上传' : '上传后归入本档案盒';
        }
    }

    if ($checkCode !== '') {
        $checked = \App\Helpers\ArchiveChecklist::statusOf($checkCode);
        if ($checked !== null) {
            // 规范表样项：入口始终指向台账/业务录入页，Cxx 只提供状态判定，不改跳转目标。
            // 否则目录项（如表1-1）会被 C01 覆盖成清单页，导致「去录入」跳错地方。
            $isForm = $kind === 'form';
            return [
                'count' => $count, 'state' => $checked['state'],
                'url' => $isForm ? $url : url('archive/checklist', ['c' => $checkCode]),
                'label' => $isForm ? $label : '查看判定',
                'hint' => $isForm ? $hint : '判定来源：资料清单 ' . $checkCode . ' ' . $checked['name'],
                'print_url' => $printUrl, 'checklist' => $checkCode,
            ];
        }
    }
    // 无 Cxx 判定的项（系统扩充表样/个人表6-x 等）：不自行判定 done/todo，
    // 统一标 event（按需）并照常展示记录份数，避免与清单状态口径分叉。
    return [
        'count' => $count,
        'state' => 'event',
        'url' => $url, 'label' => $label,
        'hint' => $hint . ($count > 0 ? '（已录 ' . $count . ' 条，不判定）' : ''),
        'print_url' => $printUrl,
        'checklist' => '',
    ];
}

    /** 某档案盒目录项统计：总数 / 已归档数（仅统计有明确落地键的项，info 项不计入分母） */
    public static function filingStat(string $groupKey): array
    {
        $rows = self::filingCatalog()[$groupKey] ?? [];
        $checkable = 0; $done = 0;
        foreach ($rows as $e) {
            $st = self::filingStatus($e, $groupKey);
            if ($st['state'] === 'info') { continue; }
            $checkable++;
            if ($st['state'] === 'done') { $done++; }
        }
        return ['total' => count($rows), 'checkable' => $checkable, 'done' => $done];
    }

    /** 台账键 → 表号 / 档案组（LedgerCatalog 反向派生 archive/group 的唯一来源） */
    public static function ledgerPlacement(): array
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (self::groups() as $g) {
                foreach ($g['items'] as $it) {
                    if (!empty($it['ledger'])) {
                        $map[(string)$it['ledger']] = [
                            'archive' => (string)$it['no'],
                            'group'   => (string)$g['key'],
                        ];
                    }
                }
            }
        }
        return $map;
    }

    /** 档案类别序号的中文表述（1～6 → 一～六），供侧栏、页头与打印中心统一使用 */
    public static function numeral(int $no): string
    {
        return [1 => '一', 2 => '二', 3 => '三', 4 => '四', 5 => '五', 6 => '六'][$no] ?? (string)$no;
    }

    /** 档案类别图标（Tabler 图标名，唯一来源），供侧栏与总览页统一使用 */
    public static function icon(int $no): string
    {
        return [1 => 'building-factory-2', 2 => 'clipboard-list', 3 => 'school', 4 => 'flask', 5 => 'stethoscope', 6 => 'id'][$no] ?? 'folder';
    }

    /**
     * 资料管理模式：external（外部出报告，仅附件上传） / internal（内部编制，可在线编辑+附件+打印）。
     * 判定唯一来源：落地键中的 DocTypes 编码（外部报告类）或目录项性质。
     */
    private const EXTERNAL_DOC_TYPES = [
        'U_PRE_EVAL', 'U_DESIGN', 'U_CTRL_EVAL', 'U_ACCEPT', 'U_DETECT_REPORT',
        'U_EXAM_SUMMARY', 'U_QUAL_DETECT', 'U_QUAL_EXAM', 'U_DECL_RECEIPT',
        'W_EXAM', 'W_DIAG', 'W_TRAIN_MGR',
    ];

    /** 资料管理菜单中的模式（external=外部报告附件 / internal=内部编制可编辑） */
    public static function filingMode(array $entry): string
    {
        [$seq, $name, $kind, $key] = $entry;
        if ($kind === 'form') {
            return 'form';
        }
        foreach (explode('、', $key) as $code) {
            if (in_array(trim($code), self::EXTERNAL_DOC_TYPES, true)) {
                return 'external';
            }
        }
        return 'internal';
    }

    /** 按「档案组 key + 目录项序号」取单个官方目录项 */
    public static function filingItem(string $groupKey, string $seq): ?array
    {
        foreach (self::filingCatalog()[$groupKey] ?? [] as $entry) {
            if ($entry[0] === $seq) {
                return $entry;
            }
        }
        return null;
    }

    /** 官方目录项资料管理页 URL */
    public static function filingItemUrl(string $groupKey, string $seq): string
    {
        return url('archive/material', ['group' => $groupKey, 'seq' => $seq]);
    }

    /**
     * 官方目录项的最终入口：
     *  - form 类：直接进台账/业务页，附 filing 参数，让页面标题与面包屑显示目录项名称；
     *  - doc 类：进资料管理页（其标题本就是目录项名称）。
     * 这样「点项目看到的是项目，内容提取的是对应样表/资料」。
     */
    public static function filingTargetUrl(string $groupKey, string $seq): string
    {
        $entry = self::filingItem($groupKey, $seq);
        if ($entry === null) {
            return '#';
        }
        if (self::filingMode($entry) !== 'form') {
            return self::filingItemUrl($groupKey, $seq);
        }
        $base = '';
        foreach (explode('、', (string)$entry[3]) as $no) {
            $no = trim($no);
            foreach (self::group($groupKey)['items'] ?? [] as $it) {
                if ((string)$it['no'] === $no) {
                    $base = self::itemUrl($it);
                    break 2;
                }
            }
        }
        if ($base === '' || $base === '#') {
            return self::filingItemUrl($groupKey, $seq);
        }
        return $base . (str_contains($base, '?') ? '&' : '?')
            . 'filing=' . rawurlencode($groupKey . ':' . $seq);
    }

    /** 目录项对应的 DocTypes 编码数组 */
    public static function filingDocTypes(array $entry): array
    {
        $key = (string)($entry[3] ?? '');
        if ($key === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode('、', $key)), fn ($x) => $x !== ''));
    }

    /** 目录项下已上传的原件（按 doc_type 匹配；无键项按档案盒默认类别） */
    public static function filingDocs(array $entry, string $groupKey): array
    {
        $types = self::filingDocTypes($entry);
        if ($types) {
            $ph = implode(',', array_fill(0, count($types), '?'));
            return Database::rows(
                "SELECT * FROM documents WHERE doc_type IN ($ph) ORDER BY period_year DESC, id DESC",
                $types
            );
        }
        $cat = (self::group($groupKey)['docs'] ?? [])[0] ?? '';
        return $cat !== ''
            ? Database::rows('SELECT * FROM documents WHERE category = ? ORDER BY id DESC', [$cat])
            : [];
    }

    /** Cxx 清单项 → 所属官方目录项（取第一个匹配）；供资料汇总页直跳 */
    public static function filingByCheck(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        foreach (self::filingCatalog() as $gk => $entries) {
            foreach ($entries as $entry) {
                $checks = array_filter(preg_split('/[、,]/', (string)($entry[5] ?? '')), fn ($x) => trim($x) !== '');
                foreach ($checks as $c) {
                    if (strtoupper(trim($c)) === $code) {
                        return ['group' => $gk, 'seq' => $entry[0], 'entry' => $entry];
                    }
                }
            }
        }
        return null;
    }

    /** 按 key 取分组 */
    public static function group(string $key): ?array
    {
        foreach (self::groups() as $g) {
            if ($g['key'] === $key) {
                return $g;
            }
        }
        return null;
    }

    /**
     * 功能页定位：由页面 active 标识 + GET 参数反查「所属档案类别 / 表样」，
     * 供侧栏高亮与页头面包屑使用，使每个功能页都挂在六类档案之下。
     * 返回 ['group' => key|'', 'item' => '表5-1'|'extra:xxx'|'']
     */
    public static function locate(string $active, array $get = []): array
    {
        // 目录项直连台账页时带 filing=组key:序号：侧栏高亮回该官方目录项
        $filing = (string)($get['filing'] ?? '');
        if ($filing !== '' && str_contains($filing, ':')) {
            [$gk, $seq] = explode(':', $filing, 2);
            if (self::group($gk) !== null && self::filingItem($gk, $seq) !== null) {
                return ['group' => $gk, 'item' => 'filing:' . $seq];
            }
        }
        if (str_starts_with($active, 'ledgers_')) {
            $gk = substr($active, 8);
            $lk = (string)($get['key'] ?? '');
            if ($lk === '' && $gk === 'training') {
                $lk = 'trainings';   // 培训签到表页不带 key
            }
            $g = self::group($gk);
            foreach ($g['items'] ?? [] as $it) {
                if (!empty($it['ledger']) && $it['ledger'] === $lk) {
                    return ['group' => $gk, 'item' => $it['no']];
                }
            }
            return ['group' => $gk, 'item' => ''];
        }
        switch ($active) {
            // 基础数据设置：初期建档四步，面包屑挂到基础设置而非某类档案
            case 'company':         return ['group' => '', 'item' => 'basic:company'];
            case 'workplaces':      return ['group' => '', 'item' => 'basic:workplaces'];
            case 'hazards':         return ['group' => '', 'item' => 'basic:hazards'];
            case 'posts':           return ['group' => '', 'item' => 'basic:posts'];
            case 'archive':
                $gk = (string)($get['group'] ?? '');
                return ['group' => $gk, 'item' => ''];
            case 'archive_material':
                $gk = (string)($get['group'] ?? '');
                $seq = (string)($get['seq'] ?? '');
                return ['group' => $gk, 'item' => 'filing:' . $seq];
            case 'checklist':
                return ['group' => '', 'item' => ''];   // 资料清单：独立入口，不挂单类档案
            case 'detections':      return ['group' => 'monitoring', 'item' => '表4-3'];
            case 'detection_items': return ['group' => 'monitoring', 'item' => 'extra:detection_items'];
            case 'exams':           return ['group' => 'health', 'item' => '表5-1'];
            case 'exam_records':
                return !empty($get['abnormal'])
                    ? ['group' => 'health', 'item' => '表5-2']
                    : ['group' => 'personal', 'item' => '表6-3'];
            case 'patients':
                return ['group' => 'health', 'item' => (($get['type'] ?? '') === 'suspected') ? '表5-4' : '表5-3'];
            case 'workers':         return ['group' => 'personal', 'item' => '表6-1'];
            case 'import':          return ['group' => 'personal', 'item' => 'extra:import'];
        }
        return ['group' => '', 'item' => ''];
    }

    /** 规范表样对应的数据入口 */
    public static function itemUrl(array $item): string
    {
        if (!empty($item['ledger'])) {
            return url('ledgers/index', ['key' => $item['ledger']]);
        }
        if (!empty($item['route'])) {
            return url($item['route'], $item['params'] ?? []);
        }
        return '#';
    }

    /**
     * 单张规范表样的操作入口（唯一来源，供总览页/栏目页共用）：
     *  - 台账类：录入 + 整表打印
     *  - 其余：前往原功能页（个人表6-1~6-3 须在信息卡/检测结果/体检记录页按人打印）
     */
    public static function actions(array $item): array
    {
        // 个人档案表6-1~6-3：按人打印，入口保留在对应业务页
        if (in_array($item['no'], ['表6-1', '表6-2', '表6-3'], true)) {
            return [
                'entry_label' => '前往',
                'entry_url'   => self::itemUrl($item),
                'print_url'   => null,
                'print_hint'  => '按人打印，请在对应页面中操作',
            ];
        }
        if (!empty($item['ledger'])) {
            // 系统扩充表样：序号已顺次融入主表号，仍标注「系统扩充」并支持整表打印
            if (!empty($item['extended'])) {
                return [
                    'entry_label' => '录入',
                    'entry_url'   => url('ledgers/index', ['key' => $item['ledger']]),
                    'print_url'   => url('prints/sheet', ['t' => $item['no']]),
                    'print_hint'  => '整表打印（系统扩充表样，非规范附件表样）',
                ];
            }
            return [
                'entry_label' => '录入',
                'entry_url'   => url('ledgers/index', ['key' => $item['ledger']]),
                'print_url'   => url('prints/sheet', ['t' => $item['no']]),
                'print_hint'  => '整表打印（规范表样）',
            ];
        }
        // 其余表样（表2-2/4-2/4-3/5-1/5-2/5-3/5-4/5-5/5-6）：数据来自业务页，打印走统一表样打印
        return [
            'entry_label' => '前往',
            'entry_url'   => self::itemUrl($item),
            'print_url'   => url('prints/sheet', ['t' => $item['no']]),
            'print_hint'  => '整表打印（规范表样）',
        ];
    }

    /** 单张规范表样的记录数 */
    public static function countItem(array $item): int
    {
        try {
            // 规范表样统一走 PrintSpec 的表号→记录数映射，不再依赖各处 count_sql / table
            $sql = PrintSpec::countSqlOf((string)($item['no'] ?? ''));
            if ($sql !== '') {
                return (int)Database::value($sql);
            }
            if (!empty($item['count_sql'])) {
                return (int)Database::value($item['count_sql']);
            }
            if (!empty($item['table'])) {
                return (int)Database::value('SELECT COUNT(*) FROM ' . $item['table']);
            }
        } catch (\Throwable $e) {
            return 0;
        }
        return 0;
    }

    /** 分组表样建立情况：已建（有记录）/ 规范表样数；不换算百分比（迎检只看有无，不评分） */
    public static function groupStat(array $group): array
    {
        $filled = 0;
        $total = 0;
        foreach ($group['items'] as $i => $item) {
            $group['items'][$i]['count'] = self::countItem($item);
            // 系统扩充表样仍不计入「规范表样数」统计，但其录入数据照常显示
            if (!empty($item['extended'])) {
                continue;
            }
            $total++;
            if ($group['items'][$i]['count'] > 0) {
                $filled++;
            }
        }
        return [
            'group'  => $group,
            'items'  => $group['items'],
            'total'  => $total,
            'filled' => $filled,
        ];
    }

    /**
     * 基础数据设置（系统初期建档四步）：单位信息 → 作业场所 → 岗位设置 → 危害因素字典。
     * 侧栏独立菜单，各页面 active 标识用 basic_ 前缀，便于高亮与返回定位。
     */
    public static function basics(): array
    {
        return [
            ['key' => 'company',    'nav' => '单位信息',       'route' => 'company/edit'],
            ['key' => 'workplaces', 'nav' => '作业场所',       'route' => 'workplaces/index'],
            ['key' => 'posts',      'nav' => '岗位设置',       'route' => 'posts/index'],
            ['key' => 'hazards',    'nav' => '危害因素字典',   'route' => 'hazards/index'],
        ];
    }

    /** 六类档案整体统计（只统计有无，不评分） */
    public static function overview(): array
    {
        $out = ['groups' => [], 'total' => 0, 'filled' => 0];
        foreach (self::groups() as $g) {
            $stat = self::groupStat($g);
            $out['groups'][] = $stat;
            $out['total']   += $stat['total'];
            $out['filled']  += $stat['filled'];
        }
        return $out;
    }
}
