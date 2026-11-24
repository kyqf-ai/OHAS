<?php
/**
 * 统一表样打印规范
 * ------------------------------------------------------------
 * 严格对应《职业卫生档案管理规范》（安监总厅安健〔2013〕101 号）所列六类档案
 * （一～六）的 23 张规范表样：每张表样在此登记「表号 / 标题 / 纸张方向 / Word 原表头结构 /
 * 最少空行数 / 表尾签名栏」，供 PrintController 与 prints/* 视图统一渲染，
 * 保证打印输出与已验收的表6-1 同等排版质量。
 *
 * 表头结构（heads）为“按行”的单元格矩阵，每个单元格：
 *   t      显示文本
 *   span   跨列数（默认 1）
 *   rspan  跨行数（默认 1）
 *   w      该单元格宽度（mm / %；仅表头首行需要，后续行由浏览器按列栅格对齐）
 * 数据行按「叶子列」数（首行之后各行的单元格总数）对齐。
 * lcols   长文本列的叶子列序号（0 起，含表4-1 的类别列）：数据格左对齐；其余列居中。
 */
declare(strict_types=1);

namespace App\Helpers;

class PrintSpec
{
    /** 表样标题（全站唯一来源：目录、导航、打印页都取这里） */
    public static function titleOf(string $no): string
    {
        return (string)(self::catalog()[$no]['title'] ?? '');
    }

    /**
     * 表样记录数统计 SQL（唯一来源）。打印中心与档案目录共用，
     * 避免两处各写一份表号→表的映射后漂移。
     */
    public static function countSqlOf(string $no): string
    {
        static $map = null;
        if ($map === null) {
            $map = [
                '表1-1' => 'SELECT COUNT(*) FROM projects',
                '表2-1' => 'SELECT COUNT(*) FROM mgmt_plan_checks',
                '表2-2' => "SELECT CASE WHEN TRIM(COALESCE(name,'')) <> '' THEN 1 ELSE 0 END FROM company LIMIT 1",
                '表2-3' => 'SELECT COUNT(*) FROM mgmt_budgets',
                '表2-4' => 'SELECT COUNT(*) FROM facilities',
                '表2-5' => 'SELECT COUNT(*) FROM facility_maintains',
                '表2-6' => 'SELECT COUNT(*) FROM ppe_issues',
                '表2-7' => 'SELECT COUNT(*) FROM warning_signs',
                '表2-8' => 'SELECT COUNT(*) FROM mgmt_inspections',
                '表2-9' => 'SELECT COUNT(*) FROM mgmt_supervisions',
                '表2-10' => 'SELECT COUNT(*) FROM drills',
                '表2-11' => 'SELECT COUNT(*) FROM emergency_facilities',
                '表3-1' => 'SELECT COUNT(*) FROM trainings',
                '表4-1' => 'SELECT COUNT(*) FROM materials',
                '表4-2' => 'SELECT COUNT(*) FROM post_hazards',
                '表4-3' => 'SELECT COUNT(*) FROM detection_reports',
                '表4-4' => 'SELECT COUNT(*) FROM rectifications',
                '表5-1' => 'SELECT COUNT(*) FROM exam_batches',
                '表5-2' => 'SELECT COUNT(*) FROM exam_records WHERE ' . \App\Models\ExamRecord::abnormalWhere('conclusion'),
                '表5-3' => 'SELECT COUNT(*) FROM diseases WHERE is_suspected = 0',
                '表5-4' => 'SELECT COUNT(*) FROM diseases WHERE is_suspected = 1',
                '表5-5' => 'SELECT COUNT(*) FROM health_accidents',
                '表5-6' => 'SELECT COUNT(*) FROM workers',
                '表6-1' => 'SELECT COUNT(*) FROM workers',
                '表6-2' => 'SELECT COUNT(*) FROM detection_items',
                '表6-3' => 'SELECT COUNT(*) FROM exam_records',
            ];
        }
        return $map[$no] ?? '';
    }

    /** 全部表样目录（表号 => 规格）。排序即打印中心展示顺序。 */
    public static function catalog(): array
    {
        return [
            /* ---------- 一 三同时 ---------- */
            '表1-1' => [
                'no' => '表1-1', 'title' => '建设项目职业卫生“三同时”审查登记表',
                'orient' => 'portrait', 'kind' => 'form',
                'intro' => '按项目逐份打印（一项目一页）：项目概况 + 预评价审核 / 设计审查 / 竣工验收三栏审查表。',
            ],

            /* ---------- 二 管理档案 ---------- */
            '表2-1' => [
                'no' => '表2-1', 'title' => '年度职业病防治计划实施检查表',
                'orient' => 'landscape', 'kind' => 'list', 'dense' => true,
                'colw' => ['10mm', '26mm', 'auto', '58mm', '26mm', '30mm'],
                'lcols' => [2, 3, 5],   // 长文本列（0 起的叶子列序号）：左对齐，多行时不参差
                'heads' => [
                    [['t' => '序号'], ['t' => '日期'], ['t' => '职业病防治计划内容'], ['t' => '实施情况'], ['t' => '实施负责人'], ['t' => '备注']],
                ],
                'empty' => 4,
                'signs' => ['检查人（签名）：', '负责人（签名）：', '日期：    年  月  日'],
            ],
            '表2-2' => [
                'no' => '表2-2', 'title' => '职业病危害项目申报基本情况表',
                'orient' => 'portrait', 'kind' => 'form',
                'intro' => '由单位信息、作业场所、岗位危害配置与劳动者数据自动汇总生成。',
            ],
            '表2-3' => [
                'no' => '表2-3', 'title' => '职业病防治经费一览表',
                'orient' => 'landscape', 'kind' => 'list', 'dense' => true,
                'colw' => ['10mm', '56mm', 'auto', '28mm', '26mm', '32mm'],
                'lcols' => [1, 2, 5],   // 长文本列（0 起的叶子列序号）：左对齐，多行时不参差
                'heads' => [
                    [['t' => '序号'], ['t' => '用途（经费项目）'], ['t' => '工作内容'], ['t' => '经费（万元）'], ['t' => '项目负责人'], ['t' => '备注']],
                ],
                'empty' => 3,
                'signs' => ['编制人：', '审核（签名）：', '日期：    年  月  日'],
                'note' => '金额为年度经费（万元），支出凭证另册归档。',
            ],
            '表2-4' => [
                'no' => '表2-4', 'title' => '职业病防护设施一览表',
                'orient' => 'landscape', 'kind' => 'list', 'dense' => true,
                'colw' => ['10mm', '42mm', '26mm', '36mm', 'auto', '42mm', '24mm', '20mm'],
                'lcols' => [1, 3, 4, 5],   // 长文本列（0 起的叶子列序号）：左对齐，多行时不参差
                'heads' => [
                    [['t' => '序号'], ['t' => '防护设施名称'], ['t' => '型号'], ['t' => '使用车间和岗位'], ['t' => '防护用途'], ['t' => '生产及安装单位'], ['t' => '验收日期'], ['t' => '状态']],
                ],
                'empty' => 3,
                'signs' => ['填表人：', '审核（签名）：', '日期：    年  月  日'],
            ],
            '表2-5' => [
                'no' => '表2-5', 'title' => '职业病防护设施维护检修记录',
                'orient' => 'portrait', 'kind' => 'form',
                'intro' => '按维护记录逐份打印（一记录一页），版式还原表2-5 单页表单。',
            ],
            '表2-6' => [
                'no' => '表2-6', 'title' => '年度个人防护用品发放使用记录',
                'orient' => 'landscape', 'kind' => 'list', 'dense' => true,
                'colw' => ['10mm', '34mm', '42mm', '40mm', '26mm', '18mm', '24mm', '26mm', 'auto'],
                'lcols' => [1, 2, 3, 8],   // 长文本列（0 起的叶子列序号）：左对齐，多行时不参差
                'heads' => [
                    [['t' => '序号'], ['t' => '车间名称'], ['t' => '接触职业病危害因素'], ['t' => '个人防护用品名称'], ['t' => '型号'], ['t' => '数量'], ['t' => '领取人'], ['t' => '领取日期'], ['t' => '备注']],
                ],
                'empty' => 4,
                'signs' => ['发放人（签名）：', '领取人（签名）：', '日期：    年  月  日'],
            ],
            '表2-7' => [
                'no' => '表2-7', 'title' => '工作场所警示标识一览表',
                'orient' => 'landscape', 'kind' => 'list', 'dense' => true,
                'colw' => ['10mm', '36mm', '34mm', '44mm', 'auto', '20mm', '26mm', '24mm'],
                'lcols' => [1, 2, 3, 4],   // 长文本列（0 起的叶子列序号）：左对齐，多行时不参差
                'heads' => [
                    [['t' => '序号'], ['t' => '作业区'], ['t' => '告知项目'], ['t' => '配置地点'], ['t' => '警示内容'], ['t' => '标识数量'], ['t' => '设置日期'], ['t' => '责任人']],
                ],
                'empty' => 4,
                'signs' => ['检查人（签名）：', '负责人（签名）：', '日期：    年  月  日'],
                'note' => '按 GBZ 158-2003 设置；破损、褪色及时更换。',
            ],
            '表2-8' => [
                'no' => '表2-8', 'title' => '职业卫生检查和处理记录',
                'orient' => 'portrait', 'kind' => 'form',
                'intro' => '按检查记录逐份打印（一记录一页），版式还原表2-8 单页表单。',
            ],
            '表2-9' => [
                'no' => '表2-9', 'title' => '职业卫生监管意见和落实情况记录表',
                'orient' => 'portrait', 'kind' => 'form',
                'intro' => '按监管意见逐份打印（一意见一页），版式还原表2-9 单页表单。',
            ],
            '表2-10' => [
                'no' => '表2-10', 'title' => '应急演练记录',
                'orient' => 'landscape', 'kind' => 'list', 'extended' => true,
                'intro' => '系统扩充表样（非规范附件表样）：按演练记录逐条打印。',
                'colw' => ['26mm', '32mm', '18mm', '22mm', 'auto'],
                'lcols' => [4],
                'heads' => [
                    [['t' => '演练日期'], ['t' => '演练名称'], ['t' => '参加人数'], ['t' => '组织人'], ['t' => '演练总结']],
                ],
                'empty' => 3,
                'signs' => ['填表人：', '审核（签名）：', '日期：    年  月  日'],
            ],
            '表2-11' => [
                'no' => '表2-11', 'title' => '应急设施台账',
                'orient' => 'landscape', 'kind' => 'list', 'extended' => true,
                'intro' => '系统扩充表样（非规范附件表样）：按应急设施逐条打印。',
                'colw' => ['10mm', '32mm', '26mm', '14mm', '34mm', '22mm', 'auto'],
                'lcols' => [6],
                'heads' => [
                    [['t' => '序号'], ['t' => '设施名称'], ['t' => '型号'], ['t' => '数量'], ['t' => '存放位置'], ['t' => '检查日期'], ['t' => '备注']],
                ],
                'empty' => 3,
                'signs' => ['填表人：', '审核（签名）：', '日期：    年  月  日'],
            ],

            /* ---------- 三 宣传培训 ---------- */
            '表3-1' => [
                'no' => '表3-1', 'title' => '职业卫生宣传培训记录表',
                'orient' => 'portrait', 'kind' => 'form',
                'intro' => '按培训记录逐份打印（一次培训一页）：培训信息 + 参训人员签到表。',
            ],

            /* ---------- 四 监测检测 ---------- */
            '表4-1' => [
                'no' => '表4-1', 'title' => '可能产生职业病危害的设备、材料、化学品一览表',
                'orient' => 'landscape', 'kind' => 'list', 'dense' => true, 'grouped' => true,
                'colw' => ['16mm', '48mm', 'auto', '52mm', '48mm', '38mm'],
                'lcols' => [1, 2, 3, 4, 5],   // 长文本列（0 起的叶子列序号）：左对齐，多行时不参差
                'heads' => [
                    [['t' => '类别'], ['t' => '设备、材料、化学品名称'], ['t' => '可能产生的职业病危害因素名称'], ['t' => '使用车间和岗位'], ['t' => '生产、供货单位'], ['t' => '备注']],
                ],
                'empty' => 3,
                'signs' => ['填表人：', '审核（签名）：', '日期：    年  月  日'],
                'note' => '多种危害因素名称以「、」分隔。',
            ],
            '表4-2' => [
                'no' => '表4-2', 'title' => '接触职业病危害因素汇总表',
                'orient' => 'landscape', 'kind' => 'list', 'dense' => true,
                'colw' => ['10mm', '28mm', '24mm', 'auto', '26mm', '20mm', '16mm', '16mm', '40mm', '40mm'],
                'lcols' => [1, 2, 3, 4, 8, 9],   // 长文本列（0 起的叶子列序号）：左对齐，多行时不参差
                'heads' => [
                    [
                        ['t' => '序号', 'rspan' => 2], ['t' => '作业场所', 'rspan' => 2], ['t' => '岗位', 'rspan' => 2],
                        ['t' => '职业病危害因素名称', 'rspan' => 2], ['t' => '危害来源', 'rspan' => 2], ['t' => '接触方式', 'rspan' => 2],
                        ['t' => '接触职业病危害人数', 'span' => 2], ['t' => '工程防护设施', 'rspan' => 2], ['t' => '个体防护用品', 'rspan' => 2],
                    ],
                    [
                        ['t' => '总人数'], ['t' => '女工数'],
                    ],
                ],
                'empty' => 3,
                'signs' => ['填表人：', '审核（签名）：', '日期：    年  月  日'],
            ],
            '表4-3' => [
                'no' => '表4-3', 'title' => '职业病危害因素日常监测、检测评价汇总表',
                'orient' => 'landscape', 'kind' => 'list', 'dense' => true,
                'colw' => ['10mm', '30mm', '38mm', '26mm', '18mm', 'auto', '20mm', '28mm', '50mm'],
                'lcols' => [1, 2, 8],   // 长文本列（0 起的叶子列序号）：左对齐，多行时不参差
                'heads' => [
                    [['t' => '序号'], ['t' => '车间'], ['t' => '职业病危害因素名称'], ['t' => '监测周期'], ['t' => '监测点数'], ['t' => '监测结果范围'], ['t' => '合格率(%)'], ['t' => '职业接触限值'], ['t' => '检测机构']],
                ],
                'empty' => 3,
                'signs' => ['填表人：', '审核（签名）：', '日期：    年  月  日'],
                'note' => '结果范围与合格率由检测明细汇总；限值按 GBZ 2.1-2019（含修改单）、GBZ 2.2-2007。',
            ],
            '表4-4' => [
                'no' => '表4-4', 'title' => '超标点整改台账',
                'orient' => 'landscape', 'kind' => 'list', 'extended' => true,
                'intro' => '系统扩充表样（非规范附件表样）：按超标整改项逐条打印。',
                'colw' => ['10mm', '30mm', '30mm', '36mm', '20mm', '20mm', 'auto'],
                'lcols' => [1, 2, 6],
                'heads' => [
                    [['t' => '序号'], ['t' => '来源报告'], ['t' => '整改事项'], ['t' => '整改方案'], ['t' => '完成日期'], ['t' => '状态'], ['t' => '复测报告']],
                ],
                'empty' => 3,
                'signs' => ['填表人：', '审核（签名）：', '日期：    年  月  日'],
            ],

            /* ---------- 五 健康监护 ---------- */
            '表5-1' => [
                'no' => '表5-1', 'title' => '职业健康检查结果汇总表',
                'orient' => 'landscape', 'kind' => 'list', 'dense' => true,
                'colw' => ['10mm', '26mm', '38mm', '22mm', '16mm', '16mm', '20mm', '20mm', '20mm', '24mm', '18mm', 'auto'],
                'lcols' => [2, 11],   // 长文本列（0 起的叶子列序号）：左对齐，多行时不参差
                'heads' => [
                    [
                        ['t' => '序号', 'rspan' => 2], ['t' => '检查日期', 'rspan' => 2], ['t' => '检查机构', 'rspan' => 2],
                        ['t' => '体检种类', 'rspan' => 2], ['t' => '应检人数', 'rspan' => 2], ['t' => '实检人数', 'rspan' => 2],
                        ['t' => '检查结果（人数）', 'span' => 5], ['t' => '备注', 'rspan' => 2],
                    ],
                    [
                        ['t' => '目前未见异常'], ['t' => '疑似职业病'], ['t' => '职业禁忌证'],
                        ['t' => '其他疾病或异常'], ['t' => '职业病'],
                    ],
                ],
                'empty' => 2,
                'signs' => ['填表人：', '审核（签名）：', '日期：    年  月  日'],
                'note' => '结论按 GBZ 188-2025；历史「未见异常」并入「目前未见异常」，待检记录不计入实检。',
            ],
            '表5-2' => [
                'no' => '表5-2', 'title' => '职业健康检查异常结果登记表',
                'orient' => 'landscape', 'kind' => 'list', 'dense' => true,
                'colw' => ['10mm', '22mm', '14mm', '14mm', '26mm', '40mm', '40mm', 'auto', '46mm'],
                'lcols' => [4, 5, 6, 7, 8],   // 长文本列（0 起的叶子列序号）：左对齐，多行时不参差
                'heads' => [
                    [['t' => '序号'], ['t' => '姓名'], ['t' => '性别'], ['t' => '年龄'], ['t' => '岗位'], ['t' => '接触职业病危害因素'], ['t' => '可能导致的职业病'], ['t' => '体检结论与处理意见'], ['t' => '落实情况']],
                ],
                'empty' => 3,
                'signs' => ['填表人：', '审核（签名）：', '日期：    年  月  日'],
            ],
            '表5-3' => [
                'no' => '表5-3', 'title' => '职业病患者一览表',
                'orient' => 'landscape', 'kind' => 'list', 'dense' => true,
                'colw' => ['10mm', '22mm', '14mm', '26mm', '18mm', '40mm', '34mm', '34mm', '26mm', 'auto'],
                'lcols' => [5, 6, 7, 9],   // 长文本列（0 起的叶子列序号）：左对齐，多行时不参差
                'heads' => [
                    [['t' => '序号'], ['t' => '姓名'], ['t' => '性别'], ['t' => '出生日期'], ['t' => '接害工龄'], ['t' => '车间、岗位'], ['t' => '职业病名'], ['t' => '诊断机构'], ['t' => '诊断日期'], ['t' => '处理情况']],
                ],
                'empty' => 3,
                'signs' => ['填表人：', '审核（签名）：', '日期：    年  月  日'],
            ],
            '表5-4' => [
                'no' => '表5-4', 'title' => '疑似职业病患者一览表',
                'orient' => 'landscape', 'kind' => 'list', 'dense' => true,
                'colw' => ['24mm', '14mm', '14mm', '38mm', '18mm', '40mm', '36mm', '30mm', 'auto'],
                'lcols' => [3, 5, 6, 8],   // 长文本列（0 起的叶子列序号）：左对齐，多行时不参差
                'heads' => [
                    [['t' => '姓名'], ['t' => '性别'], ['t' => '年龄'], ['t' => '车间、岗位'], ['t' => '接害工龄'], ['t' => '疑似职业病名'], ['t' => '体检机构'], ['t' => '体检日期'], ['t' => '处理情况']],
                ],
                'empty' => 3,
                'signs' => ['填表人：', '审核（签名）：', '日期：    年  月  日'],
            ],
            '表5-5' => [
                'no' => '表5-5', 'title' => '职业病危害事故报告与处理记录表',
                'orient' => 'portrait', 'kind' => 'form',
                'intro' => '按事故记录逐份打印（一事故一页），版式还原表5-5 单页表单。',
            ],
            '表5-6' => [
                'no' => '表5-6', 'title' => '职业健康监护档案汇总表',
                'orient' => 'landscape', 'kind' => 'list', 'dense' => true,
                'colw' => ['10mm', '34mm', '30mm', '24mm', '14mm', '26mm', '26mm', '36mm', '28mm', 'auto'],
                'lcols' => [1, 9],   // 长文本列（0 起的叶子列序号）：左对齐，多行时不参差
                'heads' => [
                    [
                        ['t' => '序号', 'rspan' => 2], ['t' => '部门/车间', 'rspan' => 2], ['t' => '档案编号', 'rspan' => 2],
                        ['t' => '姓名', 'rspan' => 2], ['t' => '性别', 'rspan' => 2], ['t' => '建档时间', 'rspan' => 2],
                        ['t' => '人员调离情况', 'span' => 3], ['t' => '备注', 'rspan' => 2],
                    ],
                    [
                        ['t' => '调离时间'], ['t' => '是否提供档案复印件'], ['t' => '劳动者签字'],
                    ],
                ],
                'empty' => 3,
                'signs' => ['填表人：', '档案管理员（签名）：', '日期：    年  月  日'],
                'note' => '劳动者离职时有权索取监护档案复印件，单位应如实、无偿提供并签章。',
            ],

            /* ---------- 六 个人档案（按人打印，另有入口） ---------- */
            '表6-1' => [
                'no' => '表6-1', 'title' => '劳动者个人信息卡',
                'orient' => 'portrait', 'kind' => 'person',
                'intro' => '按人打印：在「劳动者信息卡」页操作。',
                'route' => 'workers/index',
            ],
            '表6-2' => [
                'no' => '表6-2', 'title' => '工作场所职业病危害因素检测结果',
                'orient' => 'portrait', 'kind' => 'person',
                'intro' => '按人归集打印：在劳动者信息卡或检测结果查询页操作。',
                'route' => 'detections/items',
            ],
            '表6-3' => [
                'no' => '表6-3', 'title' => '历次职业健康检查结果及处理情况',
                'orient' => 'portrait', 'kind' => 'person',
                'intro' => '按人打印：在劳动者信息卡或体检记录页操作。',
                'route' => 'exams/records',
            ],
        ];
    }

    public static function get(string $no): ?array
    {
        return self::catalog()[$no] ?? null;
    }

    /** 档案分组（打印中心展示用）：类别序号 => 名称（唯一来源为 ArchiveCatalog::groups()） */
    public static function groups(): array
    {
        $out = [];
        foreach (\App\Helpers\ArchiveCatalog::groups() as $g) {
            $out[(int)$g['no']] = \App\Helpers\ArchiveCatalog::numeral((int)$g['no']) . '、' . $g['title'];
        }
        return $out;
    }

    /** 表号所属档案类别（表X-Y → X；按多字节安全解析） */
    public static function groupNo(string $no): int
    {
        return preg_match('/表(\d+)-\d+/u', $no, $m) ? (int)$m[1] : 0;
    }

    /* ==================== 渲染辅助 ==================== */

    /**
     * 表头矩阵的叶子列数（数据行应对齐到的列数）。
     * 模拟 colspan / rowspan 占位网格后取总列宽。
     */
    public static function leafCount(array $heads): int
    {
        $grid = [];
        foreach ($heads as $r => $row) {
            $c = 0;
            foreach ($row as $cell) {
                while (isset($grid[$r][$c])) {
                    $c++;
                }
                $span = max(1, (int)($cell['span'] ?? 1));
                $rs = max(1, (int)($cell['rspan'] ?? 1));
                for ($i = 0; $i < $rs; $i++) {
                    for ($j = 0; $j < $span; $j++) {
                        $grid[$r + $i][$c + $j] = true;
                    }
                }
                $c += $span;
            }
        }
        $max = 0;
        foreach ($grid as $row) {
            $keys = array_keys($row);
            $max = max($max, end($keys) + 1);
        }
        return $max;
    }

    /**
     * 渲染表头：列宽统一由 colgroup 声明（table-layout:fixed 下列宽的
     * 唯一可靠来源，保证表头合并单元格与数据行、跨页表头完全对齐），
     * 因此规范里以 colw（叶子列宽度序列，最后一项可为 auto）定义列宽。
     */
    public static function renderHead(array $spec): string
    {
        $html = '';
        if (!empty($spec['colw'])) {
            $html .= "<colgroup>\n";
            foreach ($spec['colw'] as $w) {
                $html .= '<col style="width:' . $w . '">';   // 单引号串不换行，避免产生文本节点破坏 colgroup
                $html .= "\n";
            }
            $html .= "</colgroup>\n";
        }
        $html .= "<thead>\n";
        foreach ($spec['heads'] as $row) {
            $html .= "<tr>\n";
            foreach ($row as $c) {
                $attrs = [];
                if (!empty($c['span']) && $c['span'] > 1) {
                    $attrs[] = 'colspan="' . (int)$c['span'] . '"';
                }
                if (!empty($c['rspan']) && $c['rspan'] > 1) {
                    $attrs[] = 'rowspan="' . (int)$c['rspan'] . '"';
                }
                $html .= '<th' . ($attrs ? ' ' . implode(' ', $attrs) : '') . '>' . e($c['t']) . "</th>\n";
            }
            $html .= "</tr>\n";
        }
        return $html . "</thead>\n";
    }

    /** 渲染空白待填行（$cols 列数） */
    public static function renderEmpty(int $rows, int $cols, bool $dense = false): string
    {
        if ($rows <= 0) {
            return '';
        }
        $html = '';
        for ($i = 0; $i < $rows; $i++) {
            $html .= '<tr class="pempty' . ($dense ? ' dense' : '') . '">' . str_repeat('<td></td>', $cols) . "</tr>\n";
        }
        return $html;
    }

    /**
     * 手写留白「年 月 日」：固定毫米宽度，不依赖空格字符。
     * 旧写法 "年  月  日" 用普通空格，HTML 会折叠成单个空格，导致无处落笔。
     */
    public static function dateBlank(): string
    {
        return '<span class="pdate"><i class="y"></i>年<i class="m"></i>月<i class="d"></i>日</span>';
    }

    /** 手写留白「时 分」 */
    public static function timeBlank(): string
    {
        return '<span class="pdate"><i class="m"></i>时<i class="m"></i>分</span>';
    }

    /** 手写数字留白（$small=true 为 8mm，否则 11mm） */
    public static function blank(bool $small = false): string
    {
        return '<i class="pb' . ($small ? ' pb-s' : '') . '"></i>';
    }

    /**
     * 渲染一行「签名 + 日期」（填写格内使用）：签名标签靠左、日期靠右。
     * $label 为空则只输出右对齐日期。
     */
    public static function signLine(string $label = ''): string
    {
        return '<p class="psignline"><span>' . e($label) . '</span>' . self::dateBlank() . "</p>\n";
    }

    /** 渲染表尾签名栏（文本中的「年 月 日」自动换成定宽手写留白） */
    public static function renderSigns(?array $signs): string
    {
        if (!$signs) {
            return '';
        }
        $out = array_map(function ($s) {
            $h = e($s);
            // 兼容普通空格 / 全角空格 / 无空格的各种写法
            $h = preg_replace('/年[\s\x{3000}]*月[\s\x{3000}]*日/u', self::dateBlank(), $h);
            return '<span>' . $h . '</span>';
        }, $signs);
        return '<div class="psign">' . implode('', $out) . "</div>\n";
    }

    /** 某一档案类别的全部表样（按 catalog 顺序） */
    public static function byGroup(int $groupNo): array
    {
        $out = [];
        foreach (self::catalog() as $no => $spec) {
            if (self::groupNo($no) === $groupNo) {
                $out[$no] = $spec;
            }
        }
        return $out;
    }
}
