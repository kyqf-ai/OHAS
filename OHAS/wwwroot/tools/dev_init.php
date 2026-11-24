<?php
/**
 * 开发环境初始化：直接建库 + 建管理员 + 写安装锁（绕过 Web 安装向导）。
 */
declare(strict_types=1);

/* 仅限命令行 / 内建开发服务器；被 Web 服务器（Apache/nginx）误暴露时直接拒绝执行 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

set_exception_handler(function (Throwable $e) {
    echo "[EXCEPTION] " . $e->getMessage() . "\n at " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
});
echo "== 建库 ==\n";
$dbFile = cfg('db.path');
if (!is_file($dbFile)) {
    touch($dbFile);
}
Database::pdo()->exec(file_get_contents(OHAS_ROOT . '/database/schema.sql'));
Database::pdo()->exec('PRAGMA user_version = ' . \App\Core\Migrator::VERSION);   // 新库 schema.sql 已是最新结构
// schema.sql 未含文档解除关联触发器（正常安装由 Migrator v4 补建），此处一并补上
\App\Core\Migrator::ensureDocUnlinkTriggers(Database::pdo());
echo "schema 完成\n";

// 危害因素字典：内置《职业病危害因素分类目录》全部 459 条
$r = \App\Models\Hazard::syncCatalog();
echo "危害因素字典：新增 {$r['inserted']} 条，更新 {$r['updated']} 条\n";

// 管理员
Database::run(
    'INSERT OR IGNORE INTO users (username, password_hash, real_name, role, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?)',
    ['admin', password_hash('admin12345', PASSWORD_DEFAULT), '管理员', 'admin', now_str(), now_str()]
);

// 单位信息
Database::run(
    'INSERT INTO company (name, credit_code, reg_address, work_address, legal_person, hygienist_name, hygienist_phone, industry, scale, created_at, updated_at)
     SELECT ?,?,?,?,?,?,?,?,?,?,? WHERE NOT EXISTS (SELECT 1 FROM company)',
    ['杭州宏达机械制造有限公司', '91330100MA27XX5A6B', '杭州市经济技术开发区宏达路88号', '杭州市经济技术开发区宏达路88号', '王建国', '李卫平', '0571-88996655', '通用设备制造业', '小型', now_str(), now_str()]
);

// 作业场所
$workplaces = [['一车间（冲压）', '1号厂房一层'], ['二车间（焊接）', '1号厂房二层'], ['三车间（喷涂）', '2号厂房一层'], ['五金仓库', '厂区东侧库房']];
foreach ($workplaces as $i => $wp) {
    Database::run('INSERT INTO workplaces (name, location, status, created_at, updated_at) SELECT ?,?,?,?,? WHERE NOT EXISTS (SELECT 1 FROM workplaces WHERE name = ?)',
        [$wp[0], $wp[1], '启用', now_str(), now_str(), $wp[0]]);
}

// 岗位
$posts = [
    ['一车间（冲压）', '冲压操作工'], ['一车间（冲压）', '模具维修工'],
    ['二车间（焊接）', '电焊工'], ['二车间（焊接）', '打磨工'],
    ['三车间（喷涂）', '喷漆工'], ['三车间（喷涂）', '调漆工'],
    ['五金仓库', '仓库保管员'],
];
foreach ($posts as $p) {
    Database::run('INSERT INTO posts (workplace_id, name, status, created_at, updated_at)
                   SELECT (SELECT id FROM workplaces WHERE name = ?),?,?,?,? WHERE NOT EXISTS (SELECT 1 FROM posts WHERE name = ?)',
        [$p[0], $p[1], '启用', now_str(), now_str(), $p[1]]);
}

// 劳动者
$workers = [
    ['张伟国', '男', '1985-03-12', '浙江杭州', '已婚', '大专', '无', '33010619850312001X', '2008-07-01', '2015-03-10', '在职'],
    ['刘志强', '男', '1979-11-25', '安徽合肥', '已婚', '高中', '吸烟', '340102197911250018', '2001-02-01', '2010-06-15', '在职'],
    ['陈美娟', '女', '1990-06-08', '浙江宁波', '未婚', '本科', '无', '33020319900608002X', '2013-07-01', '2016-09-01', '在职'],
    ['王秀兰', '女', '1982-09-30', '江苏苏州', '已婚', '初中', '无', '320502198209300022', '2003-03-01', '2012-01-10', '在职'],
    ['李建军', '男', '1975-01-18', '浙江绍兴', '已婚', '中专', '吸烟、饮酒', '330602197501180032', '1998-08-01', '2005-04-20', '在职'],
    ['赵春梅', '女', '1988-04-22', '浙江杭州', '已婚', '大专', '无', '330106198804220049', '2011-02-14', '2017-08-01', '在职'],
    ['孙国庆', '男', '1983-10-05', '河南郑州', '已婚', '高中', '吸烟', '410102198310050056', '2006-06-01', '2013-11-01', '在职'],
    ['周晓芳', '女', '1993-12-16', '浙江温州', '未婚', '大专', '无', '330303199312160066', '2016-07-01', '2018-03-01', '在职'],
    ['吴铁柱', '男', '1970-05-09', '浙江杭州', '已婚', '初中', '饮酒', '330106197005090079', '1992-03-01', '2004-01-01', '在职'],
    ['郑丽华', '女', '1986-08-14', '江西南昌', '已婚', '高中', '无', '360102198608140084', '2007-09-01', '2014-05-01', '在职'],
];
$seq = (int)Database::value('SELECT COUNT(*) FROM workers');
foreach ($workers as $w) {
    if (Database::value('SELECT id FROM workers WHERE id_card = ?', [$w[7]])) { continue; }
    $seq++;
    $archiveNo = date('Y') . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
    Database::run(
        'INSERT INTO workers (archive_no, name, gender, birth_date, native_place, marital_status, education, hobby, id_card, work_start_date, entry_date, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$archiveNo, $w[0], $w[1], $w[2], $w[3], $w[4], $w[5], $w[6], $w[7], $w[8], $w[9], $w[10], now_str(), now_str()]
    );
}

// 岗位危害配置
foreach ([
    ['正丁醇', '化学因素', '丁醇中毒', 'PC-TWA 100 mg/m³'],
    ['臭氧', '化学因素', '臭氧中毒', 'MAC 0.3 mg/m³'],
] as $extra) {
    if (!Database::value('SELECT id FROM hazards WHERE name = ?', [$extra[0]])) {
        Database::run('INSERT INTO hazards (name, category, possible_disease, limit_note, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
            [$extra[0], $extra[1], $extra[2], $extra[3], '启用', now_str(), now_str()]);
    }
}
$haz = fn(string $n) => (int)Database::value('SELECT id FROM hazards WHERE name = ? LIMIT 1', [$n]);
$noise = $haz('噪声'); $dust = $haz('电焊烟尘'); $xylene = $haz('二甲苯'); $toluene = $haz('甲苯'); $butyl = $haz('正丁醇'); $ozone = $haz('臭氧'); $hand = $haz('振动');
if ($noise && $dust && $xylene) {
    $cfgs = [
        ['冲压操作工', [[$noise, '定点作业', '冲压机、空压机', '设备隔声罩、消声器', '耳塞（SNR≥29dB）'], [$hand, '定点作业', '冲压作业', '减振垫', '防振手套']]],
        ['模具维修工', [[$noise, '巡检作业', '冲压机检修', '检修错峰作业', '耳塞']]],
        ['电焊工', [[$dust, '定点作业', '电焊作业', '局部排烟除尘系统', '自吸过滤式防尘口罩、护目镜'], [$ozone, '定点作业', '电焊作业', '局部排烟', '全面罩']]],
        ['打磨工', [[$dust, '定点作业', '打磨作业', '湿式作业、排风', '自吸过滤式防尘口罩、防噪声耳塞'], [$noise, '定点作业', '角磨机', '隔声屏', '耳塞']]],
        ['喷漆工', [[$xylene, '定点作业', '喷漆作业', '水帘喷漆室、送排风', '供气式全面罩、防溶剂手套'], [$toluene, '定点作业', '喷漆作业', '水帘喷漆室', '供气式全面罩']]],
        ['调漆工', [[$xylene, '定点作业', '调漆间', '局部排风柜', '防毒口罩、防溶剂手套'], [$butyl, '定点作业', '调漆间', '局部排风', '防毒口罩']]],
        ['仓库保管员', []],
    ];
    foreach ($cfgs as $c) {
        $pid = (int)Database::value('SELECT id FROM posts WHERE name = ?', [$c[0]]);
        if (!$pid) continue;
        foreach ($c[1] as $ph) {
            Database::run('INSERT INTO post_hazards (post_id, hazard_id, exposure_type, source, engineering_controls, ppe, created_at, updated_at)
                           SELECT ?,?,?,?,?,?,?,? WHERE NOT EXISTS (SELECT 1 FROM post_hazards WHERE post_id = ? AND hazard_id = ?)',
                [$pid, $ph[0], $ph[1], $ph[2], $ph[3], $ph[4], now_str(), now_str(), $pid, $ph[0]]);
        }
    }
}

// 在岗记录（worker_posts：本单位，接害快照）
$exposed = [
    ['张伟国', '冲压操作工', '噪声、手传振动', '隔声罩、消声器；耳塞、防振手套'],
    ['王秀兰', '冲压操作工', '噪声', '隔声罩；耳塞'],
    ['郑丽华', '冲压操作工', '噪声', '隔声罩；耳塞'],
    ['周晓芳', '模具维修工', '噪声', '耳塞'],
    ['刘志强', '电焊工', '电焊烟尘、臭氧', '局部排烟；防尘口罩'],
    ['陈美娟', '打磨工', '电焊烟尘（打磨粉尘）、噪声', '湿式作业、排风；防尘口罩、耳塞'],
    ['孙国庆', '电焊工', '电焊烟尘、臭氧', '局部排烟；防尘口罩'],
    ['李建军', '喷漆工', '二甲苯、甲苯', '水帘喷漆室；供气式全面罩'],
    ['赵春梅', '调漆工', '二甲苯、正丁醇', '排风柜；防毒口罩'],
    ['吴铁柱', '喷漆工', '二甲苯、甲苯', '水帘喷漆室；供气式全面罩'],
];
foreach ($exposed as $x) {
    $wid = (int)Database::value('SELECT id FROM workers WHERE name = ?', [$x[0]]);
    $pid = (int)Database::value('SELECT id FROM posts WHERE name = ?', [$x[1]]);
    $entry = (string)Database::value('SELECT entry_date FROM workers WHERE id = ?', [$wid]);
    if ($wid && $pid && !Database::value('SELECT id FROM worker_posts WHERE worker_id = ? AND post_id = ?', [$wid, $pid])) {
        Database::run('INSERT INTO worker_posts (worker_id, post_id, start_date, end_date, company_name, post_name, hazards_snapshot, protection_snapshot, created_at, updated_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$wid, $pid, $entry, null, '杭州宏达机械制造有限公司', $x[1], $x[2], $x[3], now_str(), now_str()]);
    }
}

echo "== 完成 ==\n";
echo "危害配置: " . Database::value('SELECT COUNT(*) FROM post_hazards') . " 条\n";
echo "劳动者: " . Database::value('SELECT COUNT(*) FROM workers') . " 人\n";
file_put_contents(OHAS_ROOT . '/storage/installed.lock', date('Y-m-d H:i:s'));
echo "installed.lock 已写入\n";
