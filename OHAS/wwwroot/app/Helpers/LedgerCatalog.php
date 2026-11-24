<?php
/**
 * 辅助台账配置：字段、校验与列表列。
 * 与《职业卫生档案管理规范》六类档案的规范表样一一对应，供通用台账控制器渲染。
 */
declare(strict_types=1);

namespace App\Helpers;

class LedgerCatalog
{
    public static function all(): array
    {
        $all = [
            'projects' => [
                'table'   => 'projects',
                'title'   => '建设项目“三同时”台账',
                'archive' => '表1-1',
                'group'   => 'three',
                'intro'   => '记录建设项目职业病防护设施“三同时”的预评价、设计审查与竣工验收情况。',
                'order'   => 'id DESC',
                'list'    => ['name', 'type', 'start_date', 'end_date'],
                'search'  => ['name', 'main_hazards'],
                'fields'  => [
                    ['name' => 'name', 'label' => '项目名称', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'type', 'label' => '项目类型', 'type' => 'select', 'required' => true, 'options' => ['新建', '改建', '扩建', '技改', '引进']],
                    // 金额类字段：numeric=填写时必须为 ≥0 的数字（拒绝负数与“拾贰”类文本）
                    ['name' => 'investment', 'label' => '投资额（万元）', 'type' => 'text', 'numeric' => true, 'max' => 16],
                    ['name' => 'start_date', 'label' => '开工日期', 'type' => 'date'],
                    ['name' => 'end_date', 'label' => '竣工日期', 'type' => 'date', 'not_before' => 'start_date'],
                    ['name' => 'main_hazards', 'label' => '主要职业病危害因素', 'type' => 'textarea'],
                    ['name' => 'pre_review', 'label' => '预评价审核情况', 'type' => 'textarea'],
                    ['name' => 'design_review', 'label' => '设计审查情况', 'type' => 'textarea'],
                    ['name' => 'completion', 'label' => '竣工验收情况', 'type' => 'textarea'],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
            'facilities' => [
                'table'   => 'facilities',
                'title'   => '职业病防护设施台账',
                'archive' => '表2-4',
                'group'   => 'management',
                'intro'   => '记录职业病防护设施的规格型号、安装验收与使用状态。',
                'order'   => 'id DESC',
                'list'    => ['name', 'model', 'workplace_id', 'status'],
                'search'  => ['name', 'model', 'purpose'],
                'fields'  => [
                    ['name' => 'name', 'label' => '设施名称', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'model', 'label' => '规格型号', 'type' => 'text'],
                    ['name' => 'purpose', 'label' => '用途', 'type' => 'text'],
                    ['name' => 'workplace_id', 'label' => '所在场所', 'type' => 'select', 'from' => 'workplaces'],
                    ['name' => 'installer', 'label' => '安装单位', 'type' => 'text'],
                    ['name' => 'accept_date', 'label' => '验收日期', 'type' => 'date'],
                    ['name' => 'status', 'label' => '状态', 'type' => 'select', 'required' => true, 'options' => ['在用', '停用', '检修', '报废']],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
            'facility_maintains' => [
                'table'   => 'facility_maintains',
                'title'   => '防护设施维护检修记录',
                'archive' => '表2-5',
                'group'   => 'management',
                'intro'   => '记录防护设施的检修原因、费用、效果与验收意见。',
                'order'   => 'maintain_date DESC, id DESC',
                'list'    => ['facility_id', 'maintain_date', 'reason', 'department'],
                'search'  => ['reason', 'department', 'effect'],
                'fields'  => [
                    ['name' => 'facility_id', 'label' => '防护设施', 'type' => 'select', 'required' => true, 'from' => 'facilities'],
                    ['name' => 'maintain_date', 'label' => '检修日期', 'type' => 'date', 'required' => true],
                    ['name' => 'reason', 'label' => '检修原因', 'type' => 'text'],
                    ['name' => 'department', 'label' => '检修部门', 'type' => 'text'],
                    ['name' => 'cost', 'label' => '费用', 'type' => 'text', 'numeric' => true, 'max' => 16],
                    ['name' => 'effect', 'label' => '检修效果', 'type' => 'text'],
                    ['name' => 'accept_opinion', 'label' => '验收意见', 'type' => 'textarea'],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
            'ppe_issues' => [
                'table'   => 'ppe_issues',
                'title'   => '个人防护用品购买发放记录',
                'archive' => '表2-6',
                'group'   => 'management',
                'intro'   => '记录劳动者个人防护用品的购买、发放与领取情况（同一件防护用品可对应多项防护的危害因素）。',
                'order'   => 'issued_on DESC, id DESC',
                'list'    => ['worker_id', 'department', 'item_name', 'hazard_ids', 'quantity', 'issued_on'],
                'search'  => ['item_name', 'department', 'model'],
                'place'   => ['workplace' => 'workplace_id', 'post' => 'post_id', 'text' => 'department'],
                'fields'  => [
                    ['name' => 'worker_id', 'label' => '领取人', 'type' => 'select', 'required' => true, 'from' => 'workers'],
                    ['name' => 'hazard_ids', 'label' => '防护危害因素', 'type' => 'hazards',
                        'help' => '同一件防护用品可能防护多种危害因素（如防尘口罩：电焊烟尘＋砂轮磨尘），可多选。'],
                    ['name' => 'workplace_id', 'label' => '车间/场所', 'type' => 'select', 'from' => 'workplaces', 'place' => 'workplace'],
                    ['name' => 'post_id', 'label' => '岗位', 'type' => 'select', 'from' => 'posts', 'place' => 'post'],
                    ['name' => 'department', 'label' => '补充说明（车间/部门）', 'head' => '车间/部门', 'type' => 'text',
                        'placeholder' => '下拉中没有对应场所（如行政部门）时，在此手填'],
                    ['name' => 'item_name', 'label' => '防护用品名称', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'model', 'label' => '型号', 'type' => 'text'],
                    ['name' => 'quantity', 'label' => '数量', 'type' => 'text', 'numeric' => true, 'max' => 16],
                    ['name' => 'issued_on', 'label' => '领取日期', 'type' => 'date'],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
            'trainings' => [
                'table'   => 'trainings',
                'title'   => '职业卫生宣传培训记录',
                'archive' => '表3-1',
                'group'   => 'training',
                'intro'   => '记录培训类型、内容、学时、授课人，并维护参训人员签到表。',
                'order'   => 'trained_on DESC, id DESC',
                'list'    => ['type', 'title', 'trained_on', 'teacher'],
                'search'  => ['title', 'teacher', 'organizer', 'department'],
                'roster'  => true,
                'fields'  => [
                    ['name' => 'type', 'label' => '培训类型', 'type' => 'select', 'required' => true, 'options' => ['上岗前培训', '在岗期间培训', '离岗培训', '职业卫生管理人员培训', '主要负责人培训', '其他']],
                    ['name' => 'title', 'label' => '培训内容', 'type' => 'text', 'required' => true, 'max' => 200],
                    ['name' => 'hours', 'label' => '学时', 'type' => 'number', 'step' => '0.5'],
                    ['name' => 'department', 'label' => '参加部门', 'type' => 'text'],
                    ['name' => 'teacher', 'label' => '授课人', 'type' => 'text'],
                    ['name' => 'organizer', 'label' => '组织部门', 'type' => 'text'],
                    ['name' => 'trained_on', 'label' => '实施日期', 'type' => 'date'],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
            'materials' => [
                'table'   => 'materials',
                'title'   => '设备、材料、化学品台账',
                'archive' => '表4-1',
                'group'   => 'monitoring',
                'intro'   => '记录可能产生职业病危害的设备、材料、化学品及其对应危害因素（同一设备/材料/化学品可选多项危害因素）。',
                'order'   => 'id DESC',
                'list'    => ['type', 'name', 'hazard_ids', 'usage_place'],
                'search'  => ['name', 'usage_place', 'supplier'],
                'place'   => ['workplace' => 'workplace_id', 'post' => 'post_id', 'text' => 'usage_place'],
                'fields'  => [
                    ['name' => 'type', 'label' => '类别', 'type' => 'select', 'required' => true, 'options' => ['设备', '材料', '化学品']],
                    ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'hazard_ids', 'label' => '可能产生的危害因素', 'type' => 'hazards',
                        'help' => '同一设备/材料/化学品可能产生多种危害因素（如噪声＋手传振动），可多选。'],
                    ['name' => 'workplace_id', 'label' => '使用场所', 'type' => 'select', 'from' => 'workplaces', 'place' => 'workplace'],
                    ['name' => 'post_id', 'label' => '使用岗位', 'type' => 'select', 'from' => 'posts', 'place' => 'post'],
                    ['name' => 'usage_place', 'label' => '补充说明（车间/岗位）', 'head' => '使用车间/岗位', 'type' => 'text',
                        'placeholder' => '下拉中没有对应场所/岗位，或有多处使用时，在此手填'],
                    ['name' => 'supplier', 'label' => '生产供货单位', 'type' => 'text'],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
            'plan_checks' => [
                'table'   => 'mgmt_plan_checks',
                'title'   => '年度职业病防治计划实施检查',
                'archive' => '表2-1',
                'group'   => 'management',
                'intro'   => '记录年度职业病防治计划的制定、实施检查与负责人。',
                'order'   => 'plan_date DESC, id DESC',
                'list'    => ['plan_date', 'plan_content', 'implementation', 'owner_name'],
                'search'  => ['plan_content', 'implementation', 'owner_name'],
                'fields'  => [
                    ['name' => 'plan_date', 'label' => '计划日期', 'type' => 'date', 'required' => true],
                    ['name' => 'plan_content', 'label' => '计划内容', 'type' => 'textarea', 'required' => true],
                    ['name' => 'implementation', 'label' => '实施情况', 'type' => 'textarea'],
                    ['name' => 'owner_name', 'label' => '负责人', 'type' => 'text'],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
            'budgets' => [
                'table'   => 'mgmt_budgets',
                'title'   => '职业病防治经费台账',
                'archive' => '表2-3',
                'group'   => 'management',
                'intro'   => '记录职业病防治经费的项目、工作内容、金额与负责人。',
                'order'   => 'id DESC',
                'list'    => ['purpose', 'amount', 'owner_name'],
                'search'  => ['purpose', 'work_content', 'owner_name'],
                'fields'  => [
                    ['name' => 'purpose', 'label' => '经费项目', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'work_content', 'label' => '工作内容', 'type' => 'textarea'],
                    ['name' => 'amount', 'label' => '金额（万元）', 'type' => 'text', 'numeric' => true, 'max' => 16],
                    ['name' => 'owner_name', 'label' => '负责人', 'type' => 'text'],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
            'warning_signs' => [
                'table'   => 'warning_signs',
                'title'   => '工作场所警示标识台账',
                'archive' => '表2-7',
                'group'   => 'management',
                'intro'   => '记录警示标识的设置地点、类别、对应危害因素与设置日期（同一块标识可对应多项危害因素）。',
                'order'   => 'install_date DESC, id DESC',
                'list'    => ['location', 'sign_type', 'hazard_ids', 'install_date'],
                'search'  => ['location', 'sign_type'],
                'place'   => ['workplace' => 'workplace_id', 'post' => null, 'text' => 'location', 'require' => true,
                    'require_msg' => '请选择所在场所，或手填设置地点。'],
                'fields'  => [
                    ['name' => 'workplace_id', 'label' => '所在场所', 'type' => 'select', 'from' => 'workplaces', 'place' => 'workplace',
                        'help' => '选择后可联查该场所的警示标识；未选场所时，请在“设置地点”手填。'],
                    ['name' => 'location', 'label' => '设置地点（具体位置）', 'head' => '设置地点', 'type' => 'text', 'max' => 120,
                        'placeholder' => '如：东侧入口、配电柜旁'],
                    ['name' => 'sign_type', 'label' => '标识类别', 'type' => 'select', 'options' => ['警告标识', '指令标识', '提示标识', '禁止标识', '职业病危害告知卡']],
                    ['name' => 'hazard_ids', 'label' => '对应危害因素', 'type' => 'hazards',
                        'help' => '同一块警示标识/告知卡可对应多种危害因素（如噪声＋粉尘），可多选。'],
                    ['name' => 'install_date', 'label' => '设置日期', 'type' => 'date'],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
            'inspections' => [
                'table'   => 'mgmt_inspections',
                'title'   => '职业卫生检查和处理记录',
                'archive' => '表2-8',
                'group'   => 'management',
                'intro'   => '记录职业卫生检查内容、发现问题、处理措施与整改状态。',
                'order'   => 'check_date DESC, id DESC',
                'list'    => ['check_date', 'check_content', 'status', 'responsible_person'],
                'search'  => ['check_content', 'issue', 'responsible_person'],
                'fields'  => [
                    ['name' => 'check_date', 'label' => '检查日期', 'type' => 'date', 'required' => true],
                    ['name' => 'check_content', 'label' => '检查内容', 'type' => 'textarea', 'required' => true],
                    ['name' => 'issue', 'label' => '发现问题', 'type' => 'textarea'],
                    ['name' => 'handle_measure', 'label' => '处理措施', 'type' => 'textarea'],
                    ['name' => 'responsible_person', 'label' => '责任人', 'type' => 'text'],
                    ['name' => 'deadline', 'label' => '整改期限', 'type' => 'date', 'not_before' => 'check_date'],
                    ['name' => 'status', 'label' => '整改状态', 'type' => 'select', 'required' => true, 'options' => ['待整改', '已整改']],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
            'supervisions' => [
                'table'   => 'mgmt_supervisions',
                'title'   => '监管意见和落实情况',
                'archive' => '表2-9',
                'group'   => 'management',
                'intro'   => '记录职业卫生监管部门的意见及整改落实情况。',
                'order'   => 'opinion_date DESC, id DESC',
                'list'    => ['opinion_date', 'supervision_unit', 'status'],
                'search'  => ['supervision_unit', 'opinion_content', 'rectification'],
                'fields'  => [
                    ['name' => 'opinion_date', 'label' => '意见日期', 'type' => 'date', 'required' => true],
                    ['name' => 'supervision_unit', 'label' => '监管部门', 'type' => 'text'],
                    ['name' => 'opinion_content', 'label' => '监管意见', 'type' => 'textarea'],
                    ['name' => 'rectification', 'label' => '落实情况', 'type' => 'textarea'],
                    ['name' => 'status', 'label' => '落实状态', 'type' => 'select', 'required' => true, 'options' => ['待落实', '已落实']],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
            'accidents' => [
                'table'   => 'health_accidents',
                'title'   => '职业病危害事故报告与处理',
                'archive' => '表5-5',
                'group'   => 'health',
                'intro'   => '记录职业病危害事故的经过、接触与伤亡人数、报告及处理情况。',
                'order'   => 'accident_time DESC, id DESC',
                'list'    => ['accident_time', 'workplace_name', 'hazard_factor', 'sick_count'],
                'search'  => ['workplace_name', 'post_name', 'hazard_factor', 'description'],
                'place'   => ['workplace' => 'workplace_id', 'post' => 'post_id', 'text' => 'workplace_name', 'post_text' => 'post_name'],
                'fields'  => [
                    ['name' => 'company_name', 'label' => '单位名称', 'type' => 'text'],
                    ['name' => 'legal_person', 'label' => '法定代表人', 'type' => 'text'],
                    ['name' => 'reporter', 'label' => '报告人', 'type' => 'text'],
                    ['name' => 'contact_phone', 'label' => '联系电话', 'type' => 'text'],
                    ['name' => 'accident_time', 'label' => '事故发生时间', 'type' => 'text', 'placeholder' => '如 2026-05-01 10:30'],
                    ['name' => 'workplace_id', 'label' => '发生场所', 'type' => 'select', 'from' => 'workplaces', 'place' => 'workplace'],
                    ['name' => 'post_id', 'label' => '发生岗位', 'type' => 'select', 'from' => 'posts', 'place' => 'post'],
                    ['name' => 'workplace_name', 'label' => '发生场所（手填）', 'head' => '发生场所', 'type' => 'text',
                        'placeholder' => '下拉中没有对应场所（如外单位、厂区道路）时手填'],
                    ['name' => 'post_name', 'label' => '发生岗位（手填）', 'head' => '发生岗位', 'type' => 'text',
                        'placeholder' => '下拉中没有对应岗位时手填'],
                    ['name' => 'work_content', 'label' => '作业内容', 'type' => 'text'],
                    ['name' => 'exposure_count', 'label' => '接触人数', 'type' => 'number'],
                    ['name' => 'sick_count', 'label' => '发病人数', 'type' => 'number'],
                    ['name' => 'hospital_count', 'label' => '住院人数', 'type' => 'number'],
                    ['name' => 'death_count', 'label' => '死亡人数', 'type' => 'number'],
                    ['name' => 'hazard_factor', 'label' => '危害因素', 'type' => 'text'],
                    ['name' => 'description', 'label' => '事故经过', 'type' => 'textarea'],
                    ['name' => 'preliminary_opinion', 'label' => '初步处理意见', 'type' => 'textarea'],
                    ['name' => 'report_time', 'label' => '报告时间', 'type' => 'text'],
                    ['name' => 'report_unit', 'label' => '报告单位', 'type' => 'text'],
                    ['name' => 'handler', 'label' => '处理人', 'type' => 'text'],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
            // ---------- 以下三张为系统扩充台账：非规范表样，打印中心不出 ----------
            'drills' => [
                'table'   => 'drills',
                'title'   => '应急演练记录',
                'archive' => '表2-10（系统扩充）',
                'group'   => 'management',
                'intro'   => '记录应急演练的日期、科目、参加人数与总结（系统扩充台账，非规范表样）。',
                'order'   => 'drill_date DESC, id DESC',
                'list'    => ['drill_date', 'name', 'workplace_id', 'participants'],
                'search'  => ['name', 'organizer', 'summary'],
                'fields'  => [
                    ['name' => 'drill_date', 'label' => '演练日期', 'type' => 'date', 'required' => true],
                    ['name' => 'name', 'label' => '演练科目', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'workplace_id', 'label' => '演练场所', 'type' => 'select', 'from' => 'workplaces'],
                    ['name' => 'participants', 'label' => '参加人数', 'type' => 'number'],
                    ['name' => 'organizer', 'label' => '组织部门', 'type' => 'text'],
                    ['name' => 'summary', 'label' => '演练总结', 'type' => 'textarea'],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
            'emergency_facilities' => [
                'table'   => 'emergency_facilities',
                'title'   => '应急设施台账',
                'archive' => '表2-11（系统扩充）',
                'group'   => 'management',
                'intro'   => '记录应急设施的型号、数量、存放位置与检查状态（系统扩充台账，非规范表样）。',
                'order'   => 'id DESC',
                'list'    => ['name', 'model', 'workplace_id', 'location', 'status'],
                'search'  => ['name', 'model', 'location'],
                'fields'  => [
                    ['name' => 'name', 'label' => '设施名称', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'model', 'label' => '规格型号', 'type' => 'text'],
                    ['name' => 'workplace_id', 'label' => '所在场所', 'type' => 'select', 'from' => 'workplaces'],
                    ['name' => 'quantity', 'label' => '数量', 'type' => 'text', 'numeric' => true, 'max' => 16],
                    ['name' => 'location', 'label' => '存放位置', 'type' => 'text', 'max' => 120],
                    ['name' => 'check_date', 'label' => '最近检查日期', 'type' => 'date'],
                    ['name' => 'status', 'label' => '状态', 'type' => 'select', 'required' => true, 'options' => ['在用', '停用', '报废']],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
            'rectifications' => [
                'table'   => 'rectifications',
                'title'   => '超标点整改台账',
                'archive' => '表4-4（系统扩充）',
                'group'   => 'monitoring',
                'intro'   => '记录检测超标点的整改方案、完成情况与复测结果（系统扩充台账，非规范表样）。',
                'order'   => 'id DESC',
                'list'    => ['report_id', 'item_desc', 'status', 'done_date'],
                'search'  => ['item_desc', 'plan'],
                'fields'  => [
                    ['name' => 'report_id', 'label' => '超标来源报告', 'type' => 'select', 'from' => 'detection_reports',
                        'help' => '选择检测报告后可在下方挂整改方案、对比照片等原件。'],
                    ['name' => 'item_desc', 'label' => '超标点及危害因素', 'type' => 'text', 'required' => true, 'max' => 200],
                    ['name' => 'plan', 'label' => '整改方案', 'type' => 'textarea'],
                    ['name' => 'done_date', 'label' => '完成日期', 'type' => 'date'],
                    ['name' => 'verify_report_id', 'label' => '复测报告', 'type' => 'select', 'from' => 'detection_reports'],
                    ['name' => 'status', 'label' => '整改状态', 'type' => 'select', 'required' => true, 'options' => ['整改中', '已完成']],
                    ['name' => 'remark', 'label' => '备注', 'type' => 'textarea'],
                ],
            ],
        ];
        // archive / group 由 ArchiveCatalog 的台账映射反向派生，避免两处维护漂移。
        $placement = \App\Helpers\ArchiveCatalog::ledgerPlacement();
        foreach ($all as $key => &$cfg) {
            if (isset($placement[$key])) {
                $cfg['archive'] = $placement[$key]['archive'];
                $cfg['group'] = $placement[$key]['group'];
            }
        }
        unset($cfg);
        return $all;
    }

    public static function get(string $key): ?array
    {
        $all = self::all();
        return $all[$key] ?? null;
    }

    /** 字段校验规则 */
    public static function rules(array $cfg): array
    {
        $rules = [];
        foreach ($cfg['fields'] as $f) {
            $r = [];
            if (!empty($f['required'])) {
                $r[] = 'required';
            }
            if (!empty($f['max'])) {
                $r[] = 'max:' . $f['max'];
            }
            if (($f['type'] ?? '') === 'date') {
                $r[] = 'date';
                if (!empty($f['not_before'])) {
                    $r[] = 'not_before:' . $f['not_before'];   // 例：竣工日期不早于开工日期
                }
            }
            if (($f['type'] ?? '') === 'number') {
                // 人数/学时类字段不允许负数：整数用 int，带小数步长的用 nonneg
                $r[] = isset($f['step']) ? 'nonneg' : 'int';
            }
            if (($f['type'] ?? '') === 'text' && !empty($f['numeric'])) {
                // 金额/数量类文本字段（表1-1 投资额、表2-3 金额、表2-5 费用、表2-6 数量）：
                // 填了就必须是 ≥0 的数字，杜绝负数金额与任意文本入库
                $r[] = 'nonneg';
            }
            // 下拉选项：无论是否必填都校验取值（未填通过，填了就必须在选项内）
            if (!empty($f['options'])) {
                $r[] = 'in:' . implode(',', $f['options']);
            }
            if ($r) {
                $rules[$f['name']] = implode('|', $r);
            }
        }
        return $rules;
    }

    /** 列表、打印表头：字段设有 head 时用 head（例如"补充说明"类字段在表头仍叫原名） */
    public static function heads(array $cfg): array
    {
        $out = [];
        foreach ($cfg['fields'] as $f) {
            $out[$f['name']] = $f['head'] ?? $f['label'];
        }
        return $out;
    }

    /** 字段中文标签 */
    public static function labels(array $cfg): array
    {
        $out = [];
        foreach ($cfg['fields'] as $f) {
            $out[$f['name']] = $f['label'];
        }
        return $out;
    }
}
