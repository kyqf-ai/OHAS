<?php
/**
 * 开发环境示例业务数据：检测报告/体检/培训/各台账/职业病登记。
 * 幂等：以关键字段存在性判断是否已插入。
 *
 * 用法：php tools/dev_seed.php           —— 完整业务种子（原行为）
 *       php tools/dev_seed.php archive   —— v0.5 自测种子（§8.3：张三/李四/王五 + 检测报告）
 */
declare(strict_types=1);

/* 仅限命令行 / 内建开发服务器；被 Web 服务器（Apache/nginx）误暴露时直接拒绝执行 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../app/bootstrap.php';

if (($argv[1] ?? '') === 'archive') {
    require __DIR__ . '/dev_seed_archive.php';
    exit(0);
}

use App\Core\Database;

function dbins(string $sql, array $p): void {
    global $_sql;
    $_sql = $sql;
    Database::run($sql, $p);
    $_sql = null;
}

$_sql = null;
set_exception_handler(function (Throwable $e) {
    global $_sql;
    echo "[EXCEPTION] " . $e->getMessage() . "\n";
    if ($_sql) echo "SQL: " . preg_replace('/\s+/', ' ', $_sql) . "\n";
    exit(1);
});

$val = fn(string $sql, array $p = []) => Database::value($sql, $p);
$ins = function (string $table, array $data): int {
    $cols = array_keys($data);
    $exists = $data['_exists'] ?? null;
    unset($data['_exists']);
    if ($exists) {
        return 0;
    }
    $data['created_at'] = now_str();
    $data['updated_at'] = now_str();
    $cols = array_keys($data);
    Database::run(
        'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')',
        array_values($data)
    );
    return (int)Database::lastId();
};

/* ---------- 检测报告与明细 ---------- */
$r1 = $val('SELECT id FROM detection_reports WHERE report_no = ?', ['ZD-2026-018']);
if (!$r1) {
    dbins('INSERT INTO detection_reports (report_no, agency, category, test_date, report_date, conclusion, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)',
        ['ZD-2026-018', '浙江安信职业卫生检测有限公司', '定期检测', '2026-04-08', '2026-04-22', '共检测 12 个点，11 个点合格，1 个点（二车间打磨位噪声）超标，建议加装隔声屏并缩短接触时间。', now_str(), now_str()]);
    $r1 = (int)Database::lastId();
    $items = [
        // workplace, post, hazard, point, result, unit, limit, verdict, protection
        ['一车间（冲压）', '冲压操作工', '噪声', '冲压机操作位', '86.5', 'dB(A)', '85', '合格', '隔声罩、耳塞'],
        ['一车间（冲压）', '冲压操作工', '振动', '冲压机手柄', '3.2', 'm/s²', '5', '合格', '减振垫、防振手套'],
        ['一车间（冲压）', '模具维修工', '噪声', '检修工位', '82.4', 'dB(A)', '85', '合格', '耳塞'],
        ['二车间（焊接）', '电焊工', '电焊烟尘', '焊接工位', '3.6', 'mg/m³', '4', '合格', '局部排烟、防尘口罩'],
        ['二车间（焊接）', '电焊工', '臭氧', '焊接工位', '0.21', 'mg/m³', '0.3', '合格', '局部排烟'],
        ['二车间（焊接）', '打磨工', '噪声', '打磨工位', '88.2', 'dB(A)', '85', '超标', '隔声屏、耳塞'],
        ['二车间（焊接）', '打磨工', '砂轮磨尘', '打磨工位', '6.4', 'mg/m³', '8', '合格', '湿式作业、排风、防尘口罩'],
        ['三车间（喷涂）', '喷漆工', '二甲苯', '喷漆室操作位', '45.6', 'mg/m³', '100', '合格', '水帘喷漆室、供气式全面罩'],
        ['三车间（喷涂）', '喷漆工', '甲苯', '喷漆室操作位', '28.3', 'mg/m³', '50', '合格', '水帘喷漆室'],
        ['三车间（喷涂）', '调漆工', '二甲苯', '调漆间', '52.1', 'mg/m³', '100', '合格', '排风柜、防毒口罩'],
        ['三车间（喷涂）', '调漆工', '正丁醇', '调漆间', '31.7', 'mg/m³', '100', '合格', '排风柜、防毒口罩'],
    ];
    foreach ($items as $it) {
        $wid = $val('SELECT id FROM workplaces WHERE name = ?', [$it[0]]);
        $pid = $val('SELECT id FROM posts WHERE name = ?', [$it[1]]);
        $hid = $val('SELECT id FROM hazards WHERE name = ?', [$it[2]]);
        dbins('INSERT INTO detection_items (report_id, workplace_id, post_id, hazard_id, test_point, result, unit, limit_value, verdict, protection, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$r1, $wid, $pid, $hid, $it[3], $it[4], $it[5], $it[6], $it[7], $it[8], now_str(), now_str()]);
    }
}
$r2 = $val('SELECT id FROM detection_reports WHERE report_no = ?', ['RC-2026-102']);
if (!$r2) {
    dbins('INSERT INTO detection_reports (report_no, agency, category, test_date, report_date, conclusion, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)',
        ['RC-2026-102', '浙江安信职业卫生检测有限公司', '日常监测', '2026-09-12', '2026-09-15', '三车间喷涂线日常监测 4 点，均合格。', now_str(), now_str()]);
    $r2 = (int)Database::lastId();
    foreach ([
        ['三车间（喷涂）', '喷漆工', '二甲苯', '喷漆室操作位', '48.9', 'mg/m³', '100', '合格', '水帘喷漆室、供气式全面罩'],
        ['三车间（喷涂）', '调漆工', '二甲苯', '调漆间', '55.4', 'mg/m³', '100', '合格', '排风柜'],
        ['三车间（喷涂）', '调漆工', '正丁醇', '调漆间', '35.2', 'mg/m³', '100', '合格', '排风柜'],
        ['三车间（喷涂）', '喷漆工', '甲苯', '喷漆室操作位', '30.1', 'mg/m³', '50', '合格', '水帘喷漆室'],
    ] as $it) {
        $wid = $val('SELECT id FROM workplaces WHERE name = ?', [$it[0]]);
        $pid = $val('SELECT id FROM posts WHERE name = ?', [$it[1]]);
        $hid = $val('SELECT id FROM hazards WHERE name = ?', [$it[2]]);
        dbins('INSERT INTO detection_items (report_id, workplace_id, post_id, hazard_id, test_point, result, unit, limit_value, verdict, protection, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$r2, $wid, $pid, $hid, $it[3], $it[4], $it[5], $it[6], $it[7], $it[8], now_str(), now_str()]);
    }
}

/* ---------- 体检批次与记录 ---------- */
$b1 = $val('SELECT id FROM exam_batches WHERE exam_date = ? AND agency = ?', ['2026-03-18', '杭州市职业病防治院']);
if (!$b1) {
    dbins('INSERT INTO exam_batches (exam_date, agency, exam_type, planned_count, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
        ['2026-03-18', '杭州市职业病防治院', '在岗期间', 10, '2026 年度在岗期间职业健康检查', now_str(), now_str()]);
    $b1 = (int)Database::lastId();
    // worker name => [conclusion, post, hazards, handling, site_handling]
    $records = [
        ['张伟国', '目前未见异常', '冲压操作工', '噪声、手传振动', '—', '继续落实噪声防护措施'],
        ['王秀兰', '目前未见异常', '冲压操作工', '噪声', '—', '—'],
        ['郑丽华', '其他疾病或异常', '冲压操作工', '噪声', '高血压，建议内科门诊随访，与职业因素无关', '—'],
        ['周晓芳', '目前未见异常', '模具维修工', '噪声', '—', '—'],
        ['刘志强', '疑似职业病', '电焊工', '电焊烟尘、臭氧', '双肺纹理增粗，建议 3 个月内到职业病防治院复查，复查期间暂时脱离接害岗位', '安排暂时脱离接害岗位，复查费用单位承担'],
        ['陈美娟', '职业禁忌证', '打磨工', '砂轮磨尘（其他粉尘）', '慢性阻塞性肺疾病，不宜继续接触粉尘作业，建议调离打磨岗位', '已调至五金仓库保管员岗位'],
        ['孙国庆', '目前未见异常', '电焊工', '电焊烟尘、臭氧', '—', '—'],
        ['李建军', '目前未见异常', '喷漆工', '二甲苯、甲苯', '—', '—'],
        ['赵春梅', '目前未见异常', '调漆工', '二甲苯、正丁醇', '—', '—'],
        ['吴铁柱', '其他疾病或异常', '喷漆工', '二甲苯、甲苯', '肝功能指标轻度异常，1 个月后复查肝功能', '已通知本人按期复查'],
    ];
    foreach ($records as $r) {
        $wid = (int)$val('SELECT id FROM workers WHERE name = ?', [$r[0]]);
        if (!$wid) continue;
        dbins('INSERT INTO exam_records (worker_id, batch_id, exam_date, exam_type, conclusion, agency, post_name, hazards_snapshot, handling, signed_at, site_handling, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$wid, $b1, '2026-03-18', '在岗期间', $r[1], '杭州市职业病防治院', $r[2], $r[3], $r[4], '2026-03-20', $r[5], now_str(), now_str()]);
    }
}
$b2 = $val('SELECT id FROM exam_batches WHERE exam_date = ? AND agency = ?', ['2026-09-20', '杭州市职业病防治院']);
if (!$b2) {
    dbins('INSERT INTO exam_batches (exam_date, agency, exam_type, planned_count, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
        ['2026-09-20', '杭州市职业病防治院', '在岗期间', 10, '2026 年下半年在岗期间职业健康检查（含刘志强复查）', now_str(), now_str()]);
    $b2 = (int)Database::lastId();
    foreach ([
        ['张伟国', '目前未见异常', '冲压操作工', '噪声、手传振动', '—', '—'],
        ['王秀兰', '目前未见异常', '冲压操作工', '噪声', '—', '—'],
        ['郑丽华', '目前未见异常', '冲压操作工', '噪声', '—', '—'],
        ['周晓芳', '目前未见异常', '模具维修工', '噪声', '—', '—'],
        ['刘志强', '目前未见异常', '电焊工', '电焊烟尘、臭氧', '复查双肺纹理未见明显异常', '恢复原岗位，加强防护'],
        ['孙国庆', '目前未见异常', '电焊工', '电焊烟尘、臭氧', '—', '—'],
        ['李建军', '目前未见异常', '喷漆工', '二甲苯、甲苯', '—', '—'],
        ['赵春梅', '目前未见异常', '调漆工', '二甲苯、正丁醇', '—', '—'],
        ['吴铁柱', '目前未见异常', '喷漆工', '二甲苯、甲苯', '肝功能复查正常', '—'],
        ['陈美娟', '目前未见异常', '仓库保管员', '—', '调离后复查未见异常', '—'],
    ] as $r) {
        $wid = (int)$val('SELECT id FROM workers WHERE name = ?', [$r[0]]);
        if (!$wid) continue;
        dbins('INSERT INTO exam_records (worker_id, batch_id, exam_date, exam_type, conclusion, agency, post_name, hazards_snapshot, handling, signed_at, site_handling, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$wid, $b2, '2026-09-20', '在岗期间', $r[1], '杭州市职业病防治院', $r[2], $r[3], $r[4], '2026-09-22', $r[5], now_str(), now_str()]);
    }
}

/* ---------- 职业病 / 疑似登记（表5-3/5-4） ---------- */
$dz1 = $val('SELECT id FROM diseases WHERE name = ? AND is_suspected = 1', ['电焊工尘肺（疑似）']);
if (!$dz1) {
    $wid = (int)$val('SELECT id FROM workers WHERE name = ?', ['刘志强']);
    $er = $val('SELECT id FROM exam_records WHERE worker_id = ? AND conclusion = ?', [$wid, '疑似职业病']);
    dbins('INSERT INTO diseases (worker_id, name, is_suspected, diag_date, hospital, level, handling, exam_record_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$wid, '电焊工尘肺（疑似）', 1, '2026-03-18', '杭州市职业病防治院', '—', '3 个月内复查；复查期间暂时脱离接害岗位，复查费用单位承担。2026-09 复查未见异常，恢复原岗位。', $er, now_str(), now_str()]);
}
$dz2 = $val('SELECT id FROM diseases WHERE name = ? AND is_suspected = 0', ['轻度噪声聋']);
if (!$dz2) {
    // 历史确诊病例（吴铁柱，老资历喷漆/冲压工）
    $wid = (int)$val('SELECT id FROM workers WHERE name = ?', ['吴铁柱']);
    dbins('INSERT INTO diseases (worker_id, name, is_suspected, diag_date, hospital, level, handling, exam_record_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$wid, '轻度噪声聋', 0, '2018-06-14', '浙江省职业病防治院', '轻度', '确诊后调离高噪声岗位，安排至喷漆岗（噪声合规），每年复查听力，工伤待遇按规定落实。', null, now_str(), now_str()]);
}

/* ---------- 培训（表3-1） ---------- */
$t1 = $val('SELECT id FROM trainings WHERE title = ?', ['2026 年度职业健康知识培训']);
if (!$t1) {
    dbins('INSERT INTO trainings (type, title, hours, department, teacher, organizer, trained_on, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
        ['在岗期间培训', '2026 年度职业健康知识培训', 4, '一车间、二车间、三车间', '李卫平（职业卫生管理员）', '综合管理部', '2026-02-20', '含职业病危害因素防护、防护用品正确佩戴、应急处置', now_str(), now_str()]);
    $t1 = (int)Database::lastId();
    foreach (['张伟国', '王秀兰', '郑丽华', '周晓芳', '刘志强', '陈美娟', '孙国庆'] as $i => $name) {
        $wid = (int)$val('SELECT id FROM workers WHERE name = ?', [$name]);
        if ($wid && !$val('SELECT id FROM training_participants WHERE training_id = ? AND worker_id = ?', [$t1, $wid])) {
            dbins('INSERT INTO training_participants (training_id, worker_id, score, attended, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
                [$t1, $wid, '92', '是', '', now_str(), now_str()]);
        }
    }
}
$t2 = $val('SELECT id FROM trainings WHERE title = ?', ['新入职劳动者岗前职业健康培训']);
if (!$t2) {
    dbins('INSERT INTO trainings (type, title, hours, department, teacher, organizer, trained_on, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
        ['上岗前培训', '新入职劳动者岗前职业健康培训', 6, '三车间', '李卫平（职业卫生管理员）', '综合管理部', '2026-07-05', '含喷涂作业危害、防护用品佩戴、应急救援', now_str(), now_str()]);
    $t2 = (int)Database::lastId();
    foreach (['李建军', '赵春梅', '吴铁柱'] as $name) {
        $wid = (int)$val('SELECT id FROM workers WHERE name = ?', [$name]);
        if ($wid && !$val('SELECT id FROM training_participants WHERE training_id = ? AND worker_id = ?', [$t2, $wid])) {
            dbins('INSERT INTO training_participants (training_id, worker_id, score, attended, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
                [$t2, $wid, '88', '是', '', now_str(), now_str()]);
        }
    }
}

/* ---------- 三同时（表1-1） ---------- */
$p1 = $val('SELECT id FROM projects WHERE name = ?', ['二号厂房喷涂线自动化改造项目']);
if (!$p1) {
    dbins('INSERT INTO projects (name, type, investment, start_date, end_date, main_hazards, pre_review, design_review, completion, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        ['二号厂房喷涂线自动化改造项目', '技改', '860', '2025-05-10', '2026-01-20', '二甲苯、甲苯、正丁醇', '2025-03 通过预评价审核（浙卫职预审〔2025〕14 号），结论：可行', '2025-04 通过职业病防护设施设计审查（严重危害项目），按审查意见完善水帘喷漆室设计', '2026-03 组织竣工验收并通过（浙卫职验收〔2026〕6 号），防护设施同步投入使用', '', now_str(), now_str()]);
}
$p2 = $val('SELECT id FROM projects WHERE name = ?', ['五金仓库扩建项目']);
if (!$p2) {
    dbins('INSERT INTO projects (name, type, investment, start_date, end_date, main_hazards, pre_review, design_review, completion, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        ['五金仓库扩建项目', '扩建', '120', '2026-06-01', '2026-08-30', '无显著职业病危害因素', '经分析不属于职业病危害一般或严重项目，进行自查备案', '不涉及', '2026-09 自查验收合格', '', now_str(), now_str()]);
}

/* ---------- 防护设施（表2-4）与维护（表2-5） ---------- */
$f1 = $val('SELECT id FROM facilities WHERE name = ?', ['水帘喷漆室']);
if (!$f1) {
    foreach ([
        ['水帘喷漆室', 'SL-4000', '三车间（喷涂）', '漆雾净化与通风', '杭州净化设备有限公司', '2021-05-18', '在用', '两工位，配套送排风'],
        ['局部排烟除尘系统', 'LY-12', '二车间（焊接）', '电焊烟尘捕集净化', '杭州净化设备有限公司', '2021-05-18', '在用', '覆盖 6 个焊接工位'],
        ['冲压设备隔声罩', 'GS-200', '一车间（冲压）', '隔声降噪', '宁波声学装备厂', '2020-11-02', '在用', '4 台冲压机加装'],
        ['调漆间排风柜', 'PF-15', '三车间（喷涂）', '有机溶剂通风稀释', '杭州净化设备有限公司', '2022-08-09', '在用', ''],
        ['移动式焊烟净化器', 'YJ-3', '二车间（焊接）', '零散焊点烟尘净化', '宁波声学装备厂', '2023-03-15', '检修', '滤芯更换周期检查中'],
    ] as $f) {
        $wid = $val('SELECT id FROM workplaces WHERE name = ?', [$f[2]]);
        dbins('INSERT INTO facilities (name, model, purpose, workplace_id, installer, accept_date, status, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$f[0], $f[1], $f[3], $wid, $f[4], $f[5], $f[6], $f[7], now_str(), now_str()]);
    }
}
$m1 = $val('SELECT id FROM facility_maintains WHERE maintain_date = ? AND facility_id = (SELECT id FROM facilities WHERE name = ?)', ['2026-07-12', '移动式焊烟净化器']);
if (!$m1) {
    $fid = (int)$val('SELECT id FROM facilities WHERE name = ?', ['移动式焊烟净化器']);
    dbins('INSERT INTO facility_maintains (facility_id, maintain_date, reason, department, cost, effect, accept_opinion, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$fid, '2026-07-12', '滤芯堵塞导致风量下降，烟气捕集效果变差', '设备部', '1200', '更换滤芯并校准风量，恢复正常', '经现场测试风量恢复至设计值，验收合格', '', now_str(), now_str()]);
    $fid2 = (int)$val('SELECT id FROM facilities WHERE name = ?', ['水帘喷漆室']);
    dbins('INSERT INTO facility_maintains (facility_id, maintain_date, reason, department, cost, effect, accept_opinion, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$fid2, '2026-05-06', '水帘板结垢、循环水泵流量不足，按季度保养计划进行维护', '设备部', '2600', '除垢清洗、更换水泵密封件，漆雾捕集效率恢复', '运行参数达到设计要求，验收合格，继续按季度保养', '', now_str(), now_str()]);
}

/* ---------- 防护用品发放（表2-6：防护的危害因素多选） ---------- */
if (!(int)$val('SELECT COUNT(*) FROM ppe_issues')) {
    $wOf = fn(string $n) => (int)$val('SELECT id FROM workers WHERE name = ?', [$n]);
    $hz = fn(string $n) => $val('SELECT id FROM hazards WHERE name = ?', [$n]);
    $wp = fn(string $n) => $val('SELECT id FROM workplaces WHERE name = ?', [$n]);
    $po = fn(string $n) => $val('SELECT id FROM posts WHERE name = ?', [$n]);
    $rows = [
        ['张伟国', '噪声', '一车间（冲压）', '冲压操作工', '耳塞（SNR≥29dB）', '3M-1110', '2 副', '2026-01-08'],
        ['张伟国', '手传振动', '一车间（冲压）', '冲压操作工', '防振手套', '登升-086', '2 双', '2026-01-08'],
        ['王秀兰', '噪声', '一车间（冲压）', '冲压操作工', '耳塞（SNR≥29dB）', '3M-1110', '2 副', '2026-01-08'],
        ['郑丽华', '噪声', '一车间（冲压）', '冲压操作工', '耳塞（SNR≥29dB）', '3M-1110', '2 副', '2026-01-08'],
        ['周晓芳', '噪声', '一车间（冲压）', '模具维修工', '耳塞（SNR≥29dB）', '3M-1110', '2 副', '2026-01-08'],
        ['刘志强', '电焊烟尘', '二车间（焊接）', '电焊工', '自吸过滤式防尘口罩', '3M-6200', '2 套', '2026-01-09'],
        ['刘志强', ['电焊烟尘', '紫外线'], '二车间（焊接）', '电焊工', '护目镜', '代尔塔-102', '1 副', '2026-01-09'],
        ['陈美娟', '砂轮磨尘（其他粉尘）', '二车间（焊接）', '打磨工', '自吸过滤式防尘口罩', '3M-6200', '2 套', '2026-01-09'],
        ['孙国庆', '电焊烟尘', '二车间（焊接）', '电焊工', '自吸过滤式防尘口罩', '3M-6200', '2 套', '2026-01-09'],
        ['李建军', ['二甲苯', '甲苯'], '三车间（喷涂）', '喷漆工', '供气式全面罩', '诺斯-SP', '1 套', '2026-01-10'],
        ['李建军', '二甲苯', '三车间（喷涂）', '喷漆工', '防溶剂手套', '安思尔-37', '4 双', '2026-01-10'],
        ['赵春梅', '二甲苯', '三车间（喷涂）', '调漆工', '防毒口罩', '3M-6800', '2 套', '2026-01-10'],
        ['吴铁柱', '二甲苯', '三车间（喷涂）', '喷漆工', '供气式全面罩', '诺斯-SP', '1 套', '2026-01-10'],
    ];
    foreach ($rows as $r) {
        dbins('INSERT INTO ppe_issues (worker_id, workplace_id, post_id, department, item_name, model, quantity, issued_on, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$wOf($r[0]), $wp($r[2]), $po($r[3]), '', $r[4], $r[5], $r[6], $r[7], '', now_str(), now_str()]);
        $pid = (int)$val('SELECT last_insert_rowid()');
        foreach ((array)$r[1] as $hname) {
            $hid = $hz($hname);
            if ($hid) {
                dbins('INSERT OR IGNORE INTO ppe_issue_hazards (ppe_issue_id, hazard_id, created_at) VALUES (?,?,?)',
                    [$pid, $hid, now_str()]);
            }
        }
    }
}

/* ---------- 警示标识（表2-7：对应危害因素多选） ---------- */
if (!(int)$val('SELECT COUNT(*) FROM warning_signs')) {
    $hz = fn(string $n) => $val('SELECT id FROM hazards WHERE name = ?', [$n]);
    $wp = fn(string $n) => $val('SELECT id FROM workplaces WHERE name = ?', [$n]);
    foreach ([
        ['一车间（冲压）', '冲压机操作位东侧入口', '警告标识', '噪声', '噪声有害', '2021-06-01'],
        ['一车间（冲压）', '空压机房门口', '指令标识', '噪声', '必须佩戴护耳器', '2021-06-01'],
        ['二车间（焊接）', '焊接工区入口', '警告标识', ['电焊烟尘', '臭氧', '紫外线'], '注意通风', '2021-06-01'],
        ['二车间（焊接）', '焊接工区入口', '指令标识', '电焊烟尘', '必须佩戴防尘口罩', '2021-06-01'],
        ['三车间（喷涂）', '喷漆室门口', '警告标识', '二甲苯', '当心中毒', '2021-06-01'],
        ['三车间（喷涂）', '调漆间门口', '指令标识', '二甲苯', '必须佩戴防毒口罩', '2021-06-01'],
        ['三车间（喷涂）', '调漆间内', '职业病危害告知卡', ['二甲苯', '甲苯'], '二甲苯、甲苯职业病危害告知卡（侵皮肤/呼吸道，防护措施与应急处理）', '2022-08-09'],
    ] as $w) {
        dbins('INSERT INTO warning_signs (workplace_id, location, sign_type, install_date, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
            [$wp($w[0]), $w[1], $w[2], $w[5], $w[4], now_str(), now_str()]);
        $sid = (int)$val('SELECT last_insert_rowid()');
        foreach ((array)$w[3] as $hname) {
            $hid = $hz($hname);
            if ($hid) {
                dbins('INSERT OR IGNORE INTO warning_sign_hazards (warning_sign_id, hazard_id, created_at) VALUES (?,?,?)',
                    [$sid, $hid, now_str()]);
            }
        }
    }
}

/* ---------- 设备材料化学品（表4-1：危害因素多选） ---------- */
if (!(int)$val('SELECT COUNT(*) FROM materials')) {
    $hz = fn(string $n) => $val('SELECT id FROM hazards WHERE name = ?', [$n]);
    $wp = fn(string $n) => $val('SELECT id FROM workplaces WHERE name = ?', [$n]);
    $po = fn(string $n) => $val('SELECT id FROM posts WHERE name = ?', [$n]);
    foreach ([
        ['设备', 'J23-63 冲压机', ['噪声', '振动'], '一车间（冲压）', '冲压操作工', '徐州锻压机床集团', ''],
        ['设备', 'ZX7-400 逆变直流焊机', ['电焊烟尘', '臭氧', '紫外线'], '二车间（焊接）', '电焊工', '上海沪工焊接集团', ''],
        ['设备', '角磨机 S1M-FF-150A', ['砂轮磨尘', '噪声'], '二车间（焊接）', '打磨工', '江苏东成电动工具', ''],
        ['材料', '不锈钢焊丝 ER308', ['电焊烟尘', '臭氧'], '二车间（焊接）', '电焊工', '杭州焊材供应站', ''],
        ['材料', '打磨片（树脂）', ['砂轮磨尘'], '二车间（焊接）', '打磨工', '杭州耗材供应站', ''],
        ['化学品', '稀释剂（天那水）', ['二甲苯', '甲苯'], '三车间（喷涂）', '调漆工', '宁波化工原料有限公司', '危化品，MSDS 已建档'],
        ['化学品', '聚氨酯清漆', ['二甲苯', '丁醇'], '三车间（喷涂）', '喷漆工', '宁波化工原料有限公司', '危化品，MSDS 已建档'],
        ['化学品', '工业酒精（清洗用）', [], '三车间（喷涂）', '喷漆工', '杭州化学试剂公司', ''],
    ] as $m) {
        dbins('INSERT INTO materials (type, name, workplace_id, post_id, usage_place, supplier, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
            [$m[0], $m[1], $wp($m[3]), $po($m[4]), '', $m[5], $m[6], now_str(), now_str()]);
        $mid = (int)$val('SELECT last_insert_rowid()');
        foreach ($m[2] as $hname) {
            $hid = $hz($hname);
            if ($hid) {
                dbins('INSERT INTO material_hazards (material_id, hazard_id, created_at) VALUES (?,?,?)',
                    [$mid, $hid, now_str()]);
            }
        }
    }
}

/* ---------- 管理台账（表2-1/2-3/2-8/2-9） ---------- */
if (!(int)$val('SELECT COUNT(*) FROM mgmt_plan_checks')) {
    dbins('INSERT INTO mgmt_plan_checks (plan_date, plan_content, implementation, owner_name, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
        ['2026-01-05', '完成 2026 年度工作场所职业病危害因素定期检测委托与实施', '4 月完成现场检测，5 月取得报告并向全员公示', '李卫平', '', now_str(), now_str()]);
    dbins('INSERT INTO mgmt_plan_checks (plan_date, plan_content, implementation, owner_name, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
        ['2026-01-05', '组织全员职业健康检查（在岗期间），应检 10 人', '3 月、9 月分两批完成，实检 10 人，异常结果已登记处理', '李卫平', '', now_str(), now_str()]);
    dbins('INSERT INTO mgmt_plan_checks (plan_date, plan_content, implementation, owner_name, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
        ['2026-01-05', '冲压车间噪声超标治理：加装隔声屏、缩短接害时间', '二车间打磨位加装移动隔声屏，复测合格', '王建国', '', now_str(), now_str()]);
    dbins('INSERT INTO mgmt_plan_checks (plan_date, plan_content, implementation, owner_name, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
        ['2026-01-05', '防护用品按时足量发放并督促正确佩戴', '按季度发放，发放记录见表 2-6', '李卫平', '', now_str(), now_str()]);
    dbins('INSERT INTO mgmt_plan_checks (plan_date, plan_content, implementation, owner_name, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
        ['2026-01-05', '开展职业健康知识培训与应急演练', '2 月完成全员培训，6 月完成洗眼器使用演练', '李卫平', '', now_str(), now_str()]);
}
if (!(int)$val('SELECT COUNT(*) FROM mgmt_budgets')) {
    foreach ([
        ['职业卫生管理机构的组织工作经费', '档案维护、制度修订、管理人员培训', '1.5', '李卫平'],
        ['生产车间改造（隔声屏）', '二车间打磨位移动隔声屏采购安装', '3.2', '王建国'],
        ['生产工艺改进', '喷涂线自动化改造减少人工接害', '12.0', '王建国'],
        ['防护设施建设与维护', '水帘喷漆室保养、焊烟净化器滤芯更换', '0.9', '李卫平'],
        ['个人劳动防护用品', '耳塞、防尘口罩、供气式面罩、手套等', '2.6', '李卫平'],
        ['职业健康检查费', '2026 年度在岗期间体检两批', '1.2', '李卫平'],
        ['职业病危害因素检测费', '2026 年定期检测与日常监测', '1.8', '李卫平'],
    ] as $b) {
        dbins('INSERT INTO mgmt_budgets (purpose, work_content, amount, owner_name, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
            [$b[0], $b[1], $b[2], $b[3], '', now_str(), now_str()]);
    }
}
if (!(int)$val('SELECT COUNT(*) FROM mgmt_inspections')) {
    dbins('INSERT INTO mgmt_inspections (check_date, check_content, issue, handle_measure, responsible_person, deadline, status, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
        ['2026-04-25', '二车间焊接工区：排烟设施运行、防护用品佩戴、警示标识完好性', '3 号工位排烟罩风量不足；1 名员工未佩戴防尘口罩', '检修排烟管道并更换风机皮带；班组长督促佩戴并纳入考核', '李卫平', '2026-05-10', '已整改', '', now_str(), now_str()]);
    dbins('INSERT INTO mgmt_inspections (check_date, check_content, issue, handle_measure, responsible_person, deadline, status, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
        ['2026-07-18', '三车间喷涂线：水帘喷漆室运行、供气式面罩气源、调漆间排风', '调漆间排风柜滤棉超期未换', '按周期更换滤棉并登记维护记录', '李卫平', '2026-07-25', '已整改', '', now_str(), now_str()]);
    dbins('INSERT INTO mgmt_inspections (check_date, check_content, issue, handle_measure, responsible_person, deadline, status, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
        ['2026-10-09', '一车间冲压区：隔声罩完好性、耳塞佩戴、防护设备维护', '2 号冲压机隔声罩门封条局部老化', '已报采购，待更换', '李卫平', '2026-10-31', '待整改', '', now_str(), now_str()]);
}
if (!(int)$val('SELECT COUNT(*) FROM mgmt_supervisions')) {
    dbins('INSERT INTO mgmt_supervisions (opinion_date, supervision_unit, opinion_content, rectification, status, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)',
        ['2026-05-14', '杭州市卫生健康委员会（职业卫生监督科）', '现场检查提出：1. 二车间打磨位噪声检测超标，须限期治理；2. 部分员工职业健康检查档案签字不齐全。', '已加装移动隔声屏并于 8 月复测合格（报告 ZD-2026-018 附后）；档案签字已全部补齐，整改报告已报备。', '已落实', '检查记录及整改回执存档', now_str(), now_str()]);
    dbins('INSERT INTO mgmt_supervisions (opinion_date, supervision_unit, opinion_content, rectification, status, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)',
        ['2026-09-03', '杭州市生态环境局开发区分局', '危险化学品的存放与使用须完善台账并加强通风。', '已完善化学品台账（表4-1）与 MSDS 存档；调漆间排风柜完成保养（见表2-5）。', '已落实', '', now_str(), now_str()]);
}

/* ---------- 事故报告（表5-5） ---------- */
if (!(int)$val('SELECT COUNT(*) FROM health_accidents')) {
    $wp = $val('SELECT id FROM workplaces WHERE name = ?', ['三车间（喷涂）']);
    $po = $val('SELECT id FROM posts WHERE name = ?', ['调漆工']);
    dbins('INSERT INTO health_accidents (company_name, legal_person, reporter, contact_phone, accident_time, workplace_id, post_id, workplace_name, post_name, work_content, exposure_count, sick_count, hospital_count, death_count, hazard_factor, description, preliminary_opinion, report_time, report_unit, handler, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        ['杭州宏达机械制造有限公司', '王建国', '李卫平', '0571-88996655', '2026-06-11 14:20', $wp, $po, '', '', '调漆作业', 1, 1, 1, 0, '二甲苯',
         '6 月 11 日 14:20，调漆工赵春梅在调漆间转移稀释剂时，因桶盖未拧紧倾倒，约 2 升稀释剂洒溅于地面，短时间吸入较高浓度二甲苯蒸气后出现头晕、恶心症状。',
         '初步认定为急性二甲苯轻度中毒事件（化学因素）。现场立即撤离人员、开大排风、清水冲洗地面；患者送开发区医院观察治疗，24 小时后症状消失出院。',
         '2026-06-11 15:05', '杭州市卫生健康委员会（电话报告）；属地街道卫生服务中心（书面报告）', '李卫平', '事件已按《职业病危害事故调查处理办法》完成内部调查与整改', now_str(), now_str()]);
}

echo "== 业务数据完成 ==\n";
foreach (['detection_reports', 'detection_items', 'exam_batches', 'exam_records', 'diseases', 'trainings', 'training_participants', 'projects', 'facilities', 'facility_maintains', 'ppe_issues', 'ppe_issue_hazards', 'warning_signs', 'warning_sign_hazards', 'materials', 'material_hazards', 'mgmt_plan_checks', 'mgmt_budgets', 'mgmt_inspections', 'mgmt_supervisions', 'health_accidents'] as $t) {
    echo str_pad($t, 22) . Database::value("SELECT COUNT(*) FROM {$t}") . "\n";
}
