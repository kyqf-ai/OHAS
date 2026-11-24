<?php
/**
 * 自测脚本（v0.5.0 建，后续版本持续扩充）。纯 CLI，不起 Web 服务。
 * 引导 app/bootstrap.php，通过修改 $GLOBALS['__config']['db']['path'] + 重置 PDO 单例切换临时库。
 *
 * 用法：
 *   php tools/selftest.php upgrade      v0.4.14 夹具库 → 迁移 → 断言回填与幂等
 *   php tools/selftest.php schema-diff  全新 schema 库 与 升级所得库 的结构比对
 *   php tools/selftest.php matrix       §8.3 种子 → 个人年度矩阵断言（v0.5.1）
 *   php tools/selftest.php checklist    §8.3 种子 → 单位清单断言（v0.5.2；v0.5.3 含三张新台账）
 *   php tools/selftest.php robust       输入健壮性回归（数组参数/日期补零/CSV 换行/路由白名单）
 *   php tools/selftest.php closure      状态收尾 / 异常闭环 / 检测口径（审核意见回归）
 *   php tools/selftest.php registry     注册表一致性（孤儿资料类型编码 / 目录项数）
 *   php tools/selftest.php all          依次执行以上全部
 *
 * 断言用简单 ok(bool, msg)，不引入测试框架。
 */
declare(strict_types=1);

/* 仅限命令行 / 内建开发服务器；被 Web 服务器（Apache/nginx）误暴露时直接拒绝执行 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Core\Migrator;

/* ---------- 输出与断言 ---------- */
$PASS = 0;
$FAIL = 0;
function ok(bool $cond, string $msg): void
{
    global $PASS, $FAIL;
    if ($cond) {
        $PASS++;
        echo "  [PASS] {$msg}\n";
    } else {
        $FAIL++;
        echo "  [FAIL] {$msg}\n";
    }
}
function section(string $title): void
{
    echo "\n== {$title} ==\n";
}
function finish(): void
{
    global $PASS, $FAIL;
    echo "\n----------\nPASS {$PASS}  FAIL {$FAIL}\n";
    exit($FAIL > 0 ? 1 : 0);
}

/* ---------- 临时库管理 ---------- */
/** 直连 PDO（不触发 Migrator），用于装载 SQL 文件与校验数据 */
function rawPdo(string $path): PDO
{
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

/** 切换应用数据库连接（bootstrap 后改配置 + 重置单例） */
function useDb(string $path): void
{
    $GLOBALS['__config']['db']['path'] = $path;
    $rp = new ReflectionProperty(\App\Core\Database::class, 'pdo');
    $rp->setAccessible(true);
    $rp->setValue(null, null);
}

/** 全新安装路径：schema.sql + 触发器 + user_version（与 InstallController 一致） */
function makeFreshDb(string $path): void
{
    @unlink($path);
    touch($path);
    $pdo = rawPdo($path);
    $pdo->exec(file_get_contents(OHAS_ROOT . '/database/schema.sql'));
    Migrator::ensureDocUnlinkTriggers($pdo);
    $pdo->exec('PRAGMA user_version = ' . Migrator::VERSION);
}

/** v0.4.14 旧库：夹具 SQL + user_version=14 */
function makeFixtureDb(string $path): void
{
    @unlink($path);
    touch($path);
    $pdo = rawPdo($path);
    $pdo->exec(file_get_contents(OHAS_ROOT . '/tools/fixtures/v0414.sql'));
    $pdo->exec('PRAGMA user_version = 14');
}

/** 触发应用层迁移（Database::pdo() 首连即迁移） */
function runAppMigration(string $path): void
{
    useDb($path);
    Database::pdo();
}

/* ============================================================
 * upgrade：v0.4.14 → 当前版本
 * ============================================================ */
function cmdUpgrade(): void
{
    section('upgrade：v0.4.14 夹具库升级');
    $db = sys_get_temp_dir() . '/ohas-selftest-upgrade-' . getmypid() . '.db';
    makeFixtureDb($db);

    $before = rawPdo($db)->query('SELECT id, category, title, doc_date, file_path, link_type, link_id FROM documents ORDER BY id')->fetchAll();

    runAppMigration($db);

    $pdo = rawPdo($db);
    $after = $pdo->query('SELECT id, category, title, doc_date, file_path, link_type, link_id FROM documents ORDER BY id')->fetchAll();
    ok(count($after) === count($before), '文档数量不变（' . count($before) . ' → ' . count($after) . '）');
    $pathsSame = true;
    foreach ($before as $i => $row) {
        if ($row['file_path'] !== $after[$i]['file_path'] || $row['link_type'] !== $after[$i]['link_type'] || $row['link_id'] !== $after[$i]['link_id']) {
            $pathsSame = false;
        }
    }
    ok($pathsSame, 'file_path 与 link_* 全部不变');

    $docs = [];
    foreach ($pdo->query('SELECT id, title, doc_type, period_year, original_name FROM documents ORDER BY id')->fetchAll() as $r) {
        $docs[(int)$r['id']] = $r;
    }
    ok($docs[1]['doc_type'] === 'W_EXAM' && (int)$docs[1]['period_year'] === 2024, 'exam_record 原件 → W_EXAM / 2024');
    ok($docs[2]['doc_type'] === 'U_EXAM_SUMMARY' && (int)$docs[2]['period_year'] === 2024, 'exam_batch 报告 → U_EXAM_SUMMARY / 2024');
    ok($docs[3]['doc_type'] === 'U_DETECT_REPORT' && (int)$docs[3]['period_year'] === 2024, 'detection_report 原件 → U_DETECT_REPORT / 2024');
    ok($docs[4]['doc_type'] === 'U_RULES', '类别“管理制度与操作规程”→ U_RULES');
    ok($docs[5]['doc_type'] === 'U_EMERGENCY_PLAN', '类别“应急预案”→ U_EMERGENCY_PLAN');
    ok($docs[6]['doc_type'] === '', '体检机构资质证书保持未分类（doc_type 为空）');
    ok($docs[7]['doc_type'] === '', '挂劳动者的“其他”文档保持未分类');
    $allNamed = true;
    foreach ($docs as $r) {
        if ($r['original_name'] !== $r['title']) {
            $allNamed = false;
        }
    }
    ok($allNamed, '全部文档 original_name = title');

    ok((int)$pdo->query('PRAGMA user_version')->fetchColumn() === Migrator::VERSION, 'user_version = ' . Migrator::VERSION);

    // 幂等：再跑一次，全部行不变
    $snapshot = $pdo->query('SELECT * FROM documents ORDER BY id')->fetchAll();
    runAppMigration($db);
    $pdo2 = rawPdo($db);
    $snapshot2 = $pdo2->query('SELECT * FROM documents ORDER BY id')->fetchAll();
    ok(json_encode($snapshot) === json_encode($snapshot2), '重复迁移幂等（documents 逐行一致）');
    ok((int)$pdo2->query('PRAGMA user_version')->fetchColumn() === Migrator::VERSION, '重复迁移后 user_version 不变');

    // 新结构可用：duty_role / archive_marks / idx_documents_type
    ok((bool)$pdo2->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='archive_marks'")->fetchColumn(), 'archive_marks 表已建');
    $hasDuty = false;
    foreach ($pdo2->query('PRAGMA table_info(workers)')->fetchAll() as $c) {
        if ($c['name'] === 'duty_role') { $hasDuty = true; }
    }
    ok($hasDuty, 'workers.duty_role 已加');
    ok((bool)$pdo2->query("SELECT 1 FROM sqlite_master WHERE type='index' AND name='idx_documents_type'")->fetchColumn(), 'idx_documents_type 已建');

    // v0.5.3：三张系统扩充台账（升级到 16 无异常）
    foreach (['drills', 'emergency_facilities', 'rectifications'] as $t) {
        ok((bool)$pdo2->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='{$t}'")->fetchColumn(), "升级库已建表 {$t}");
    }
    foreach (['trg_doc_unlink_ledger_drills', 'trg_doc_unlink_ledger_emergency_facilities', 'trg_doc_unlink_ledger_rectifications'] as $trg) {
        ok((bool)$pdo2->query("SELECT 1 FROM sqlite_master WHERE type='trigger' AND name='{$trg}'")->fetchColumn(), "升级库已建触发器 {$trg}");
    }

    @unlink($db);
}

/* ============================================================
 * schema-diff：全新安装 vs 升级所得
 * ============================================================ */
function cmdSchemaDiff(): void
{
    section('schema-diff：全新安装库 与 升级所得库 结构比对');
    $fresh = sys_get_temp_dir() . '/ohas-selftest-fresh-' . getmypid() . '.db';
    $upg = sys_get_temp_dir() . '/ohas-selftest-upg-' . getmypid() . '.db';
    makeFreshDb($fresh);
    makeFixtureDb($upg);
    runAppMigration($upg);

    $a = rawPdo($fresh);
    $b = rawPdo($upg);

    // 表集合
    $tables = fn (PDO $p) => array_column($p->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(), 'name');
    ok($tables($a) === $tables($b), '表集合一致（共 ' . count($tables($a)) . ' 张）');

    // 列集合（顺序无关：ALTER TABLE 追加列在尾部，只比名称+类型+默认值）
    $cols = fn (PDO $p, string $t) => array_map(
        fn ($c) => $c['name'] . '|' . $c['type'] . '|' . $c['notnull'] . '|' . (string)$c['dflt_value'],
        $p->query("PRAGMA table_info({$t})")->fetchAll()
    );
    $colDiff = [];
    foreach ($tables($a) as $t) {
        $ca = $cols($a, $t);
        $cb = $cols($b, $t);
        sort($ca);
        sort($cb);
        if ($ca !== $cb) {
            $colDiff[] = $t;
        }
    }
    ok(!$colDiff, '全部表列定义一致（顺序无关）' . ($colDiff ? '，差异表：' . implode(',', $colDiff) : ''));

    // 索引（名称 + 索引列）
    $idx = function (PDO $p): array {
        $out = [];
        foreach ($p->query("SELECT name, tbl_name FROM sqlite_master WHERE type='index' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll() as $ix) {
            $cols = array_column($p->query('PRAGMA index_info(' . $ix['name'] . ')')->fetchAll(), 'name');
            $out[$ix['name']] = $ix['tbl_name'] . ':' . implode(',', $cols);
        }
        return $out;
    };
    $ia = $idx($a);
    $ib = $idx($b);
    ok($ia === $ib, '索引名称与列一致（共 ' . count($ia) . ' 个）');

    // 触发器
    $trg = fn (PDO $p) => array_column($p->query("SELECT name FROM sqlite_master WHERE type='trigger' ORDER BY name")->fetchAll(), 'name');
    ok($trg($a) === $trg($b), '触发器集合一致（共 ' . count($trg($a)) . ' 个，含台账/职业病解除关联）');

    ok((int)$a->query('PRAGMA user_version')->fetchColumn() === (int)$b->query('PRAGMA user_version')->fetchColumn(), 'user_version 一致');

    @unlink($fresh);
    @unlink($upg);
}

/* ============================================================
 * matrix：§8.3 种子 → 个人年度矩阵断言（asOf = 2026-11-19，range = all）
 * ============================================================ */
function seedArchiveDb(string $path): void
{
    makeFreshDb($path);
    useDb($path);
    // 种子（smoke_init 同款：管理员/单位/安装锁可省，只造业务数据）
    $before = error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    require __DIR__ . '/dev_seed_archive.php';
    error_reporting($before);
}

function cmdMatrix(): void
{
    section('matrix：§8.3 个人年度矩阵（asOf=2026-11-19，range=all）');
    $db = sys_get_temp_dir() . '/ohas-selftest-matrix-' . getmypid() . '.db';
    seedArchiveDb($db);
    useDb($db);

    $wOf = fn (string $n) => (int)\App\Core\Database::value('SELECT id FROM workers WHERE name = ? LIMIT 1', [$n]);
    $A = \App\Helpers\WorkerArchive::build($wOf('张三'), 'all', '2026-11-19');
    $B = \App\Helpers\WorkerArchive::build($wOf('李四'), 'all', '2026-11-19');
    $C = \App\Helpers\WorkerArchive::build($wOf('王五'), 'all', '2026-11-19');

    $years = fn (array $m) => implode(',', array_keys($m['years']));
    $cell = function (array $m, int $y, string $t): string {
        return $m['allYears'][$y]['cells'][$t]['state'] ?? 'MISSING';
    };

    ok($years($A) === '2023,2024,2025,2026', "A 年份范围 2023–2026（实际 {$years($A)}）");
    foreach ([2023 => 'done', 2024 => 'done', 2025 => 'reg_only', 2026 => 'todo'] as $y => $want) {
        ok($cell($A, $y, 'W_EXAM') === $want, "A 体检 {$y} = {$want}（实际 " . $cell($A, $y, 'W_EXAM') . '）');
    }
    foreach ([2023 => 'todo', 2024 => 'todo', 2025 => 'todo', 2026 => 'none'] as $y => $want) {
        ok($cell($A, $y, 'W_EXAM_NOTICE') === $want, "A 体检告知 {$y} = {$want}（实际 " . $cell($A, $y, 'W_EXAM_NOTICE') . '）');
    }
    foreach ([2023 => 'todo', 2024 => 'done', 2025 => 'todo', 2026 => 'todo'] as $y => $want) {
        ok($cell($A, $y, 'W_TRAIN') === $want, "A 培训 {$y} = {$want}（实际 " . $cell($A, $y, 'W_TRAIN') . '）');
    }
    foreach ([2023 => 'todo', 2024 => 'done_indirect', 2025 => 'reg_only', 2026 => 'todo'] as $y => $want) {
        ok($cell($A, $y, 'X_DETECT') === $want, "A 检测（间接）{$y} = {$want}（实际 " . $cell($A, $y, 'X_DETECT') . '）');
    }
    ok($A['once']['W_CONTRACT']['state'] === 'done' && str_contains($A['once']['W_CONTRACT']['hint'] ?? '', '已到期'), 'A 合同 done 且 hint 含「已到期」');
    ok($A['once']['W_NOTICE']['state'] === 'todo', 'A 告知书 = todo');
    ok($A['header']['exposed_now'] === true, 'A 当前接害 = true');

    ok($years($B) === '2022,2023,2024,2025,2026', "B 年份范围 2022–2026（实际 {$years($B)}）");
    foreach ([2022, 2023, 2024, 2025, 2026] as $y) {
        foreach (['W_EXAM', 'W_EXAM_NOTICE', 'X_DETECT', 'W_PPE'] as $t) {
            ok($cell($B, $y, $t) === 'none', "B {$y} " . \App\Helpers\DocTypes::nameOf($t) . " = none（实际 " . $cell($B, $y, $t) . '）');
        }
        ok($cell($B, $y, 'W_TRAIN') === 'todo', "B 培训 {$y} = todo（实际 " . $cell($B, $y, 'W_TRAIN') . '）');
        ok($cell($B, $y, 'W_TRAIN_MGR') === 'todo', "B 管理人员培训 {$y} = todo（实际 " . $cell($B, $y, 'W_TRAIN_MGR') . '）');
    }
    ok($B['once']['W_APPOINT']['state'] === 'done', 'B 任命 = done');
    ok($B['once']['W_DUTY']['state'] === 'todo', 'B 职责 = todo');
    ok($B['once']['W_NOTICE']['state'] === 'none', 'B 告知书 = none（非接害）');
    ok($B['header']['exposed_now'] === false, 'B 当前接害 = false');

    ok($years($C) === '2023,2024', "C 年份范围 2023–2024（实际 {$years($C)}）");
    foreach ([2023, 2024] as $y) {
        ok($cell($C, $y, 'W_EXAM') === 'todo', "C 体检 {$y} = todo（实际 " . $cell($C, $y, 'W_EXAM') . '）');
    }
    ok($C['once']['W_CONTRACT']['state'] === 'todo', 'C 合同 = todo');

    // range 截取
    $A3 = \App\Helpers\WorkerArchive::build($wOf('张三'), '3y', '2026-11-19');
    ok(implode(',', array_keys($A3['years'])) === '2024,2025,2026', 'A range=3y 截取为 2024–2026');

    @unlink($db);
}

/* ============================================================
 * checklist：§8.3 种子 → 36 项清单状态断言（S4 期望表，asOf = 2026-11-19）
 * 期望表由实现者依据附录 A 规则对种子数据逐项推算：
 *   done    C05（李四有任命）、C26（接害花名册可打印：张三当前接害）
 *   partial C21（有培训台账但 0/1 挂原件）、C22（1/2 定期检测报告有原件）、C33（2/3 人有合同）
 *   event   C30、C31、C32
 *   todo    其余 28 项（v0.5.3 起 C17/C18/C25 台账已生效、种子无记录 → todo）；na 0 项；pending 0 项
 * ============================================================ */
function cmdChecklist(): void
{
    section('checklist：§8.3 单位清单（asOf=2026-11-19）');
    $db = sys_get_temp_dir() . '/ohas-selftest-checklist-' . getmypid() . '.db';
    seedArchiveDb($db);
    useDb($db);

    $all = \App\Helpers\ArchiveChecklist::all('2026-11-19');
    $expect = [
        // code => state
        'C01' => 'todo', 'C02' => 'todo', 'C03' => 'todo', 'C04' => 'todo',
        'C05' => 'done', 'C06' => 'todo', 'C07' => 'todo', 'C08' => 'todo',
        'C09' => 'todo', 'C10' => 'todo', 'C11' => 'todo', 'C12' => 'todo',
        'C13' => 'todo', 'C14' => 'todo', 'C15' => 'todo', 'C16' => 'todo',
        'C17' => 'todo', 'C18' => 'todo', 'C19' => 'todo', 'C20' => 'todo',
        'C21' => 'partial', 'C22' => 'partial', 'C23' => 'todo', 'C24' => 'todo',
        'C25' => 'todo', 'C26' => 'done', 'C27' => 'todo', 'C28' => 'todo',
        'C29' => 'todo', 'C30' => 'event', 'C31' => 'event', 'C32' => 'event',
        'C33' => 'partial', 'C34' => 'todo', 'C35' => 'todo', 'C36' => 'todo',
    ];
    $mismatch = [];
    foreach ($expect as $code => $want) {
        $got = $all['items'][$code]['state'] ?? 'MISSING';
        if ($got !== $want) {
            $mismatch[] = "{$code} 期望 {$want} 实际 {$got}";
        }
    }
    ok(!$mismatch, '36 项状态全部符合期望表' . ($mismatch ? '，差异：' . implode('；', $mismatch) : ''));
    ok(count($all['items']) === 36, '清单共 36 项（实际 ' . count($all['items']) . '）');

    $stats = $all['stats'];
    ok($stats === ['done' => 2, 'partial' => 3, 'todo' => 28, 'na' => 0, 'event' => 3, 'pending' => 0],
        '状态计数 done=2 partial=3 todo=28 na=0 event=3 pending=0（实际 ' . json_encode($stats, JSON_UNESCAPED_UNICODE) . '）');

    // 关键 part 数值抽查
    $c21 = $all['items']['C21'];
    ok(strpos($c21['parts'][0]['display'], '1 条') !== false, 'C21 近三年培训 = 1 条');
    ok($c21['parts'][1]['display'] === '0/1 场培训已挂原件', 'C21 培训佐证 0/1');
    $c22 = $all['items']['C22'];
    ok($c22['parts'][0]['display'] === '1/2 份报告已挂原件', 'C22 定期检测报告 1/2');
    $c27 = $all['items']['C27'];
    ok($c27['parts'][0]['display'] === '0/3 批次已挂原件', 'C27 批次总结 0/3');
    $c29 = $all['items']['C29'];
    ok($c29['parts'][0]['display'] === '0/3 格', 'C29 体检告知 0/3 格（张三 2023-2025）');
    $c33 = $all['items']['C33'];
    ok($c33['parts'][0]['display'] === '2/3 人', 'C33 劳动合同 2/3 人');

    // 缺失名单
    $missing = \App\Helpers\ArchiveChecklist::missing('C33', '2026-11-19');
    ok(count($missing) === 1 && $missing[0]['label'] === '王五', 'C33 缺失名单 = 王五 1 人');
    $missing29 = \App\Helpers\ArchiveChecklist::missing('C29', '2026-11-19');
    ok(count($missing29) === 3, 'C29 缺失名单 3 格（张三 2023/2024/2025）');

    // 有缺口的劳动者（S4 扩展）
    $gaps = \App\Helpers\WorkerArchive::gapWorkers('2026-11-19');
    ok(count($gaps) === 3, '有缺口的劳动者 = 3 人（张三/李四/王五）');

    // v0.5.3 扩展：C17/C18/C25 台账生效，录入记录 + 挂原件后变为可判定
    $now = now_str();
    Database::run('INSERT INTO drills (drill_date, name, participants, organizer, summary, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
        ['2026-06-20', '溶剂泄漏应急演练', 12, '综合管理部', '演练达到预期目标', $now, $now]);
    $drillId = (int)Database::lastId();
    $efid = null;
    Database::run('INSERT INTO emergency_facilities (name, model, quantity, location, check_date, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)',
        ['洗眼器', 'XYQ-2', '2', '喷涂车间东侧', '2026-09-01', '在用', $now, $now]);
    $efid = (int)Database::lastId();
    $rid24 = (int)Database::value("SELECT id FROM detection_reports WHERE report_no = 'JC-A-2024'");
    Database::run('INSERT INTO rectifications (report_id, item_desc, plan, done_date, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
        [$rid24, '喷漆室操作位苯超标', '加装局部排风并复查', '2026-08-15', '已完成', $now, $now]);
    $rectId = (int)Database::lastId();

    $item17 = \App\Helpers\ArchiveChecklist::item('C17', '2026-11-19');
    ok($item17['state'] === 'partial', 'C17 录入 1 场演练无附件 = partial（实际 ' . $item17['state'] . '）');
    ok($item17['parts'][0]['display'] === '已有 1 条', 'C17 演练台账 = 1 条');
    ok($item17['parts'][1]['display'] === '0/1 场演练已挂原件', 'C17 演练佐证 0/1');
    $item18 = \App\Helpers\ArchiveChecklist::item('C18', '2026-11-19');
    ok($item18['state'] === 'partial', 'C18 录入 1 件设施无照片 = partial（实际 ' . $item18['state'] . '）');
    $item25 = \App\Helpers\ArchiveChecklist::item('C25', '2026-11-19');
    ok($item25['state'] === 'partial', 'C25 录入 1 条整改无佐证 = partial（实际 ' . $item25['state'] . '）');

    // 挂上原件后 C17 → done（演练记录附件 U_DRILL_FILE）
    $path = make_sample_file('pdf');
    Database::run(
        "INSERT INTO documents (category, title, doc_date, file_path, doc_type, period_year, original_name, file_hash, link_type, link_id, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
        ['应急预案', '演练记录 溶剂泄漏应急演练', '2026-06-20', $path, 'U_DRILL_FILE', 2026, '演练记录 溶剂泄漏应急演练', sha1_file(\App\Helpers\Uploader::absPath($path)), 'ledger_drills', $drillId, $now, $now]
    );
    $item17b = \App\Helpers\ArchiveChecklist::item('C17', '2026-11-19');
    ok($item17b['state'] === 'done', 'C17 挂上附件后 = done（实际 ' . $item17b['state'] . '）');

    // v0.5.3：事件类线索人数（C30–C32 说明列）
    $clues0 = \App\Helpers\WorkerArchive::eventClues();
    ok($clues0 === ['W_TRANSFER' => 0, 'W_RECHECK' => 0, 'W_SUSPECT' => 0, 'W_DIAG' => 0, 'W_COPY_SIGN' => 1],
        '种子线索：无异常结论，离职接害人员缺复印件签收 1 人（实际 ' . json_encode($clues0, JSON_UNESCAPED_UNICODE) . '）');
    // 张三 2025 体检结论改职业禁忌证 → W_TRANSFER 线索 1 人；C30 说明含提示
    Database::run("UPDATE exam_records SET conclusion = '职业禁忌证' WHERE exam_date = '2025-07-10'");
    $clues1 = \App\Helpers\WorkerArchive::eventClues();
    ok($clues1['W_TRANSFER'] === 1, '职业禁忌证结论 → W_TRANSFER 线索 1 人（实际 ' . $clues1['W_TRANSFER'] . '）');
    $item30 = \App\Helpers\ArchiveChecklist::item('C30', '2026-11-19');
    ok(($item30['parts'][0]['hint'] ?? '') === '另有 1 人有线索待核实', 'C30 说明含“另有 1 人有线索待核实”');
    // 个人页事件 hint 同步
    $wid = (int)Database::value("SELECT id FROM workers WHERE name = '张三'");
    $m = \App\Helpers\WorkerArchive::build($wid, 'all', '2026-11-19');
    ok(str_contains($m['events']['W_TRANSFER']['hint'], '职业禁忌证'), '张三个人页 W_TRANSFER hint 含「职业禁忌证」');

    // 标不适用（S10）：标记 C01 后状态变化，取消后恢复
    Database::run(
        "INSERT INTO archive_marks (scope, item_code, worker_id, period_year, reason, created_at, updated_at) VALUES ('unit','C01',NULL,NULL,'近三年无建设项目',?,?)",
        [now_str(), now_str()]
    );
    $item = \App\Helpers\ArchiveChecklist::item('C01', '2026-11-19');
    ok($item['state'] === 'na' && $item['na_reason'] === '近三年无建设项目', 'S10：标记后 C01 = 不适用（含原因）');
    Database::run("DELETE FROM archive_marks WHERE scope = 'unit' AND item_code = 'C01'");
    $item = \App\Helpers\ArchiveChecklist::item('C01', '2026-11-19');
    ok($item['state'] === 'todo', 'S10：取消后 C01 恢复 todo');

    // v0.5.5：不适用白名单按自评表“合理缺项”收窄（仅第5、8、18项 → C01/C02、C23、C16/C17/C18）
    $allowed = [];
    foreach (\App\Helpers\ArchiveChecklist::catalog() as $cc => $cfg) {
        if (\App\Helpers\ArchiveChecklist::naAllowed($cc)) {
            $allowed[] = $cc;
        }
    }
    sort($allowed);
    ok($allowed === ['C01', 'C02', 'C16', 'C17', 'C18', 'C23'], 'NA 白名单 = C01/C02/C16/C17/C18/C23（实际 ' . implode(',', $allowed) . '）');
    Database::run(
        "INSERT INTO archive_marks (scope, item_code, worker_id, period_year, reason, created_at, updated_at) VALUES ('unit','C03',NULL,NULL,'历史遗留标记',?,?)",
        [now_str(), now_str()]
    );
    $item03 = \App\Helpers\ArchiveChecklist::item('C03', '2026-11-19');
    ok($item03['state'] !== 'na', '白名单外的历史 NA 标记（C03）不生效（实际 ' . $item03['state'] . '）');
    Database::run("DELETE FROM archive_marks WHERE scope = 'unit' AND item_code = 'C03'");
    ok(\App\Helpers\ArchiveChecklist::catalog()['C23']['self'] === '8、9', 'C23 现状评价报告 对应自评项 8、9');

    @unlink($db);
}

/* ============================================================
 * robust：输入健壮性回归（v0.5.7 测试修复）
 * ============================================================ */
function cmdRobust(): void
{
    section('robust：数组型参数 / 日期补零 / CSV 引号换行 / 路由白名单');

    // 1) e() 不再把数组当字符串输出
    ok(e(['x']) === '', 'e(array) 返回空串而非 "Array"');

    // 2) Validator：数组值判无效；日期补零
    $v = \App\Core\Validator::make(['name' => ['x']], ['name' => 'required|max:30'], ['name' => '姓名']);
    ok($v->fails() && isset($v->errors()['name']), 'Validator：name[] 被判格式不正确');
    $v = \App\Core\Validator::make(['d' => '2020-1-5', 'e' => ''], ['d' => 'date', 'e' => 'date'], ['d' => '日期', 'e' => '日期2']);
    ok(!$v->fails() && $v->validated()['d'] === '2020-01-05', 'Validator：2020-1-5 规范化为 2020-01-05');
    ok($v->validated()['e'] === '', 'Validator：空日期保持空串');
    ok(\App\Core\Validator::normalizeDate('2020-02-30') === '2020-02-30', 'normalizeDate：非法日期原样返回（由 isDate 拒绝）');
    ok(!\App\Core\Validator::isDate('2020-02-30'), 'isDate：2020-02-30 非法');

    // 2b) int 位数上限 / not_before 日期先后 / 请求日期补零 / 台账规则
    $v = \App\Core\Validator::make(['n' => '99999999999999999999'], ['n' => 'int'], ['n' => '数量']);
    ok($v->fails(), 'Validator：int 超过 9 位被拒（不再静默截成 PHP_INT_MAX）');
    $v = \App\Core\Validator::make(['n' => '123456789'], ['n' => 'int'], ['n' => '数量']);
    ok(!$v->fails(), 'Validator：9 位整数通过');
    $rules = ['s' => 'date', 'e' => 'date|not_before:s'];
    $labels = ['s' => '开始日期', 'e' => '结束日期'];
    ok(!\App\Core\Validator::make(['s' => '2019-1-5', 'e' => '2019-1-10'], $rules, $labels)->fails(), 'not_before：2019-1-5 ~ 2019-1-10 合法区间不被误拒');
    ok(\App\Core\Validator::make(['s' => '2019-3-10', 'e' => '2019-3-9'], $rules, $labels)->fails(), 'not_before：2019-3-9 早于 2019-3-10 被拒');
    ok(!\App\Core\Validator::make(['s' => '', 'e' => '2019-3-9'], $rules, $labels)->fails(), 'not_before：对方为空则跳过');
    $_POST = ['start_date' => '2019-1-5', 'issued_on' => '2020-2-3', 'doc_no' => '2020-1-5', 'rows' => [['check_date' => '2021-9-1']], 'deadline' => '2022-4-8'];
    $_GET = ['as_of' => '2023-5-6'];
    normalize_request_dates();
    ok($_POST['start_date'] === '2019-01-05' && $_POST['issued_on'] === '2020-02-03' && $_POST['deadline'] === '2022-04-08', '请求入口：*_date / *_on / deadline 补零');
    ok($_POST['rows'][0]['check_date'] === '2021-09-01' && $_GET['as_of'] === '2023-05-06', '请求入口：嵌套数组与 GET 的日期补零');
    ok($_POST['doc_no'] === '2020-1-5', '请求入口：非日期类参数名（如文档编号）不被改写');
    $_POST = [];
    $_GET = [];
    $projRules = \App\Helpers\LedgerCatalog::rules(\App\Helpers\LedgerCatalog::all()['projects']);
    ok(str_contains($projRules['end_date'] ?? '', 'not_before:start_date'), '台账：工程项目竣工日期规则含 not_before:start_date');

    // 3) CSV：引号内换行 / 引号转义 / 往返
    $rows = \App\Helpers\Csv::parse("姓名,备注\r\n张三,\"第一行\r\n第二行, 含逗号\"\r\n李四,\"含\"\"引号\"\"\"\r\n\r\n王五,普通\r\n");
    ok(count($rows) === 4, 'CSV：引号内换行不再被拆成多行（得 ' . count($rows) . ' 行，期望 4）');
    ok(($rows[1][1] ?? '') === "第一行\n第二行, 含逗号", 'CSV：多行单元格内容完整');
    ok(($rows[2][1] ?? '') === '含"引号"', 'CSV：双引号转义');
    $rt = \App\Helpers\Csv::parse("x,y\n1," . \App\Helpers\Csv::cell("a\nb\"c,d") . "\n");
    ok(($rt[1][1] ?? '') === "a\nb\"c,d", 'CSV：cell() 导出后再 parse 往返一致');

    // 4) Router：仅放行真正的 action
    $ref = new ReflectionMethod(\App\Core\Router::class, 'isAction');
    $ref->setAccessible(true);
    $router = (new ReflectionClass(\App\Core\Router::class))->newInstanceWithoutConstructor();
    $led = new \App\Controllers\LedgerController();
    ok($ref->invoke($router, $led, 'index') === true, 'Router：ledgers/index 是合法 action');
    ok($ref->invoke($router, $led, 'hazardsField') === false, 'Router：辅助方法 hazardsField 不再是路由');
    ok($ref->invoke($router, $led, '__construct') === false, 'Router：__construct 不是路由');
    ok($ref->invoke($router, $led, 'noSuchAction') === false, 'Router：不存在的方法 → 非路由');

    // 5) v0.5.10 加固回归
    // 5.1 CSV 公式注入：cell() 自身即中和以 = + - @ 开头的单元格
    ok(\App\Helpers\Csv::cell('=1+1') === "'=1+1", 'CSV：= 开头的单元格被中和');
    ok(\App\Helpers\Csv::cell('@SUM(A1)') === "'@SUM(A1)", 'CSV：@ 开头的单元格被中和');
    ok(\App\Helpers\Csv::cell('-2+3') === "'-2+3", 'CSV：- 开头的单元格被中和');
    ok(\App\Helpers\Csv::cell('张三') === '张三', 'CSV：普通文本不受影响');
    ok(\App\Helpers\Csv::cell("'=1+1") === "'=1+1", 'CSV：已补引号的值不会被重复补引号');
    // 5.2 GB18030 编码识别（Excel 中文环境另存的 CSV）
    if (function_exists('iconv')) {
        $gb = @iconv('UTF-8', 'GB18030', "姓名,备注\n龘靐,生僻字");
        if ($gb !== false) {
            ok(\App\Helpers\Csv::normalize($gb) === "姓名,备注\n龘靐,生僻字", 'CSV：GB18030 文件（含生僻字）转码无丢字');
        }
    }
    // 5.3 CSRF：任一侧令牌为空都不得通过（原 hash_equals("", "") === true）
    $csrf = new class extends \App\Core\Controller {
        public function check(): bool
        {
            return $this->csrfValid();
        }
    };
    $savedSess = $_SESSION['_csrf'] ?? null;
    $savedPost = $_POST;
    $_SESSION['_csrf'] = '';
    $_POST = [];
    ok($csrf->check() === false, 'CSRF：会话无令牌 + 请求无令牌 → 拒绝');
    $_POST = ['_token' => ''];
    ok($csrf->check() === false, 'CSRF：会话无令牌 + 请求空令牌 → 拒绝');
    $_SESSION['_csrf'] = 'abc123';
    $_POST = [];
    ok($csrf->check() === false, 'CSRF：会话有令牌 + 请求无令牌 → 拒绝');
    $_POST = ['_token' => ['abc123']];
    ok($csrf->check() === false, 'CSRF：数组型令牌 → 拒绝');
    $_POST = ['_token' => 'abc123'];
    ok($csrf->check() === true, 'CSRF：令牌一致 → 通过');
    if ($savedSess === null) {
        unset($_SESSION['_csrf']);
    } else {
        $_SESSION['_csrf'] = $savedSess;
    }
    $_POST = $savedPost;
    // 5.4 上传器：数组型字段 / 超限错误码 / 路径空字节
    $up = \App\Helpers\Uploader::save(['name' => ['a.pdf'], 'tmp_name' => ['/x'], 'error' => [0], 'size' => [1]], 'documents', ['pdf'], 1024);
    ok($up['ok'] === false, '上传：数组型 $_FILES 字段被拒绝');
    $up = \App\Helpers\Uploader::save(['name' => 'a.pdf', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0], 'documents', ['pdf'], 1024);
    ok($up['ok'] === false && str_contains($up['msg'], 'upload_max_filesize'), '上传：超过 php.ini 上限给出明确提示');
    $up = \App\Helpers\Uploader::save(['error' => UPLOAD_ERR_NO_FILE], 'documents', ['pdf'], 1024);
    ok($up['ok'] === true && $up['path'] === '', '上传：未选择文件视为空成功');
    ok(\App\Helpers\Uploader::absPath("documents/a\0b.pdf") === null, '上传：存储名含空字节 → 拒绝');
    ok(\App\Helpers\Uploader::absPath('../config.php') === null, '上传：目录穿越 → 拒绝');

    // 迁移幂等：新库 schema.sql 已含新列，版本号被回拨（如 dev_init 旧写法）后重跑迁移不得报 duplicate column
    $tmp = sys_get_temp_dir() . '/ohas-selftest-robust-' . getmypid() . '.db';
    makeFreshDb($tmp);
    rawPdo($tmp)->exec('PRAGMA user_version = 10');
    $err = '';
    try {
        runAppMigration($tmp);
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
    ok($err === '', '迁移幂等：已是最新结构的库回拨 user_version=10 后重跑不报错' . ($err !== '' ? '（' . mb_substr($err, 0, 80) . '）' : ''));
    ok((int)Database::value('PRAGMA user_version') === Migrator::VERSION, '迁移幂等：重跑后 user_version 回到最新');

    // Database::immediate：提交 / 回滚 / 无悬空事务（手工 BEGIN + PDO::commit() 在 PHP 8.x 会抛 "no active transaction"）
    Database::pdo()->exec('CREATE TABLE t_imm (x INTEGER)');
    $r = Database::immediate(function (): int {
        Database::run('INSERT INTO t_imm (x) VALUES (1)');
        return 7;
    });
    ok($r === 7 && (int)Database::value('SELECT COUNT(*) FROM t_imm') === 1, 'immediate：正常返回并已提交');
    $thrown = false;
    try {
        Database::immediate(function (): void {
            Database::run('INSERT INTO t_imm (x) VALUES (2)');
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException $e) {
        $thrown = true;
    }
    ok($thrown && (int)Database::value('SELECT COUNT(*) FROM t_imm') === 1, 'immediate：回调抛异常 → 回滚并上抛');
    Database::run('INSERT INTO t_imm (x) VALUES (3)');
    ok((int)Database::value('SELECT COUNT(*) FROM t_imm') === 2, 'immediate：结束后连接可正常写入（无悬空事务）');
    $pdo2 = rawPdo($tmp);
    ok((int)$pdo2->query('SELECT COUNT(*) FROM t_imm')->fetchColumn() === 2, 'immediate：另一连接能读到已提交数据（确已落盘）');
    @unlink($tmp);
    @unlink($tmp . '-wal');
    @unlink($tmp . '-shm');

    // 业务口径回归（v0.5.10 业务逻辑复核）
    section('robust：业务口径（接害工龄 / 历史快照 / 在岗空串 / 身份证）');
    $bd = sys_get_temp_dir() . '/ohas-selftest-biz-' . getmypid() . '.db';
    makeFreshDb($bd);
    useDb($bd);
    $now = now_str();
    $pidH = (int)Database::value('SELECT post_id FROM post_hazards GROUP BY post_id ORDER BY COUNT(*) DESC LIMIT 1');
    if ($pidH === 0) {
        Database::run("INSERT INTO workplaces (name, created_at, updated_at) VALUES ('测试车间', ?, ?)", [$now, $now]);
        $wpl = Database::lastId();
        Database::run("INSERT INTO posts (workplace_id, name, status, created_at, updated_at) VALUES (?, '测试岗', '启用', ?, ?)", [$wpl, $now, $now]);
        $pidH = Database::lastId();
        $hz = (int)Database::value('SELECT id FROM hazards LIMIT 1');
        if ($hz === 0) {
            Database::run("INSERT INTO hazards (name, category, created_at, updated_at) VALUES ('噪声', '物理因素', ?, ?)", [$now, $now]);
            $hz = Database::lastId();
        }
        Database::run('INSERT INTO post_hazards (post_id, hazard_id, created_at, updated_at) VALUES (?, ?, ?, ?)', [$pidH, $hz, $now, $now]);
    }
    Database::run("INSERT INTO workers (name, gender, status, archive_no, created_at, updated_at) VALUES ('业务口径测试', '男', '在职', 'B-0001', ?, ?)", [$now, $now]);
    $wid = Database::lastId();

    // 接害工龄：兼岗 / 重叠段按区间并集计，不重复累加
    $segs = [['2020-01-01', '2020-12-31'], ['2020-01-01', '2020-12-31'], ['2020-07-01', '2021-06-30']];
    foreach ($segs as [$s, $e]) {
        Database::run("INSERT INTO worker_posts (worker_id, post_id, post_name, company_name, start_date, end_date, created_at, updated_at) VALUES (?, ?, '岗', '本单位', ?, ?, ?, ?)", [$wid, $pidH, $s, $e, $now, $now]);
    }
    ok(\App\Models\WorkerPost::exposureYears($wid, '2026-01-01') === 1.5, '接害工龄：重叠段取区间并集（2020-01-01~2021-06-30 ≈ 1.5 年，不是 3.0）');
    Database::run('DELETE FROM worker_posts WHERE worker_id = ?', [$wid]);

    // 历史快照：补填结束日期不得抹掉当时的接害因素；岗位危害配置清空后仍判为接害
    $rid = \App\Models\WorkerPost::saveRow($wid, ['post_id' => $pidH, 'start_date' => '2018-01-01', 'end_date' => ''], null);
    $snap = (string)\App\Models\WorkerPost::find($rid)['hazards_snapshot'];
    Database::run('DELETE FROM post_hazards WHERE post_id = ?', [$pidH]);
    \App\Models\WorkerPost::saveRow($wid, ['post_id' => $pidH, 'start_date' => '2018-01-01', 'end_date' => '2020-12-31'], $rid);
    $after = \App\Models\WorkerPost::find($rid);
    ok($snap !== '' && (string)$after['hazards_snapshot'] === $snap, '历史快照：补填结束日期后保留当时的接害快照');
    ok(\App\Helpers\Exposure::historyExposed($after, \App\Helpers\Exposure::hazardPosts()), '历史快照：岗位配置清空后该历史段仍判为接害');

    // 迁移 v19：旧库在岗段 end_date 为空串 → 规整为 NULL，体检受检岗位快照取到在岗岗位
    Database::run('DELETE FROM worker_posts WHERE worker_id = ?', [$wid]);
    $pA = (int)$pidH;
    Database::run("INSERT INTO posts (workplace_id, name, status, created_at, updated_at) VALUES ((SELECT workplace_id FROM posts WHERE id = ?), '新岗位', '启用', ?, ?)", [$pA, $now, $now]);
    $pB = Database::lastId();
    Database::run("INSERT INTO worker_posts (worker_id, post_id, post_name, company_name, start_date, end_date, created_at, updated_at) VALUES (?, ?, '旧岗位', '本单位', '2018-01-01', '2022-12-31', ?, ?)", [$wid, $pA, $now, $now]);
    Database::run("INSERT INTO worker_posts (worker_id, post_id, post_name, company_name, start_date, end_date, created_at, updated_at) VALUES (?, ?, '新岗位', '本单位', '2023-01-01', '', ?, ?)", [$wid, $pB, $now, $now]);
    rawPdo($bd)->exec('PRAGMA user_version = 18');
    runAppMigration($bd);
    ok((int)Database::value("SELECT COUNT(*) FROM worker_posts WHERE end_date = ''") === 0, '迁移 v19：worker_posts.end_date 空串已规整为 NULL');
    ok(\App\Models\ExamRecord::snapshotFor($wid, '2026-05-01')['post_name'] === '新岗位', '受检时岗位：在岗段（原为空串）取到新岗位，不回落到旧岗位');
    @unlink($bd);
    @unlink($bd . '-wal');
    @unlink($bd . '-shm');

    // 检测合格率：有超标不得显示 100%
    ok(\App\Models\DetectionReport::passPercent(200, 1) === 99, '合格率：200 点 1 超标 → 99%（不是 100%）');
    ok(\App\Models\DetectionReport::passPercent(200, 0) === 100 && \App\Models\DetectionReport::passPercent(0, 0) === 0, '合格率：全合格 → 100%；无数据 → 0%');
    ok(\App\Models\DetectionReport::passPercent(4, 4) === 0 && \App\Models\DetectionReport::passPercent(3, 1) === 66, '合格率：全超标 → 0%；2/3 → 66%（向下取整）');

    // 体检批次完成率：未全部完成不显示 100%，已开始不显示 0%
    ok(\App\Models\ExamBatch::ratio(299, 300) === 99, '完成率：299/300 → 99%（不是 100%）');
    ok(\App\Models\ExamBatch::ratio(1, 300) === 1, '完成率：1/300 → 1%（不是 0%）');
    ok(\App\Models\ExamBatch::ratio(300, 300) === 100 && \App\Models\ExamBatch::ratio(350, 300) === 100, '完成率：实检≥应检 → 100%');
    ok(\App\Models\ExamBatch::ratio(0, 300) === 0 && \App\Models\ExamBatch::ratio(5, 0) === 0, '完成率：未开始 / 无分母 → 0%');

    // 身份证：未来的出生日期不合法
    $mk = static function (string $p17): string {
        $w = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $c = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $s = 0;
        for ($i = 0; $i < 17; $i++) {
            $s += (int)$p17[$i] * $w[$i];
        }
        return $p17 . $c[$s % 11];
    };
    ok(\App\Helpers\IdCard::valid($mk('110105' . date('Ymd') . '123')), '身份证：出生日期为今天 → 合法');
    ok(!\App\Helpers\IdCard::valid($mk('110105' . date('Ymd', strtotime('+1 day')) . '123')), '身份证：出生日期为明天 → 不合法（此前只比年份，当年未到的日期也通过）');
}

/* ============================================================
 * closure：状态联动收尾 / 异常闭环 / 检测口径（审核意见回归）
 * ============================================================ */
function cmdClosure(): void
{
    section('closure：状态联动收尾 / 异常闭环 / 检测口径');
    $db = sys_get_temp_dir() . '/ohas-selftest-closure-' . getmypid() . '.db';
    makeFreshDb($db);
    useDb($db);
    $now = now_str();
    $year = date('Y');

    // 非在职状态收尾所有在岗职业史
    Database::run(
        "INSERT INTO workers (archive_no, name, gender, status, entry_date, leave_date, created_at, updated_at)
         VALUES ('C-0001','离岗测试','男','离职','2017-01-01','2025-06-30',?,?)",
        [$now, $now]
    );
    $wid = (int)Database::lastId();
    Database::run(
        "INSERT INTO worker_posts (worker_id, company_name, post_name, start_date, end_date, created_at, updated_at)
         VALUES (?, '外单位','旧岗','2018-01-01',NULL,?,?), (?, '外单位','兼岗','2019-01-01',NULL,?,?)",
        [$wid, $now, $now, $wid, $now, $now]
    );
    ok(\App\Models\WorkerPost::closeAllOpen($wid, '2025-06-30') === 2, '离岗收尾：两段在岗职业史一次收尾');
    ok((int)Database::value("SELECT COUNT(*) FROM worker_posts WHERE worker_id = ? AND end_date IS NULL", [$wid]) === 0, '离岗收尾：不再有“至今”段继续增长');
    ok(\App\Models\WorkerPost::exposureYears($wid, '2026-12-31') < 8.0, '离岗收尾：接害工龄不再算到今天');

    // 新增调岗段默认收尾上一段
    Database::run("UPDATE workers SET status = '在职', leave_date = '' WHERE id = ?", [$wid]);
    Database::run('DELETE FROM worker_posts WHERE worker_id = ?', [$wid]);
    Database::run(
        "INSERT INTO worker_posts (worker_id, company_name, post_name, start_date, end_date, created_at, updated_at)
         VALUES (?, '本单位','旧岗','2020-01-01',NULL,?,?)",
        [$wid, $now, $now]
    );
    $newId = \App\Models\WorkerPost::saveRow($wid, [
        'post_id' => 0, 'company_name' => '本单位', 'post_name' => '新岗',
        'start_date' => '2023-06-01', 'end_date' => '',
    ], null);
    ok(\App\Models\WorkerPost::closeOpenBefore($wid, '2023-06-01', $newId) === 1, '调岗收尾：默认关闭上一段在岗职业史');
    ok(Database::value('SELECT end_date FROM worker_posts WHERE worker_id = ? AND id <> ?', [$wid, $newId]) === '2023-05-31', '调岗收尾：上一段结束于新开始日前一天');

    // 异常全量口径 + 闭环前置登记 + H2 书面告知/原件
    Database::run(
        "INSERT INTO exam_records (worker_id, exam_date, exam_type, conclusion, handling, notice_method, signed_at, created_at, updated_at)
         VALUES (?, ?, '在岗期间', '职业病', '调离岗位', '书面签收', ?, ?, ?)",
        [$wid, $year . '-05-01', $year . '-05-20', $now, $now]
    );
    $eid = (int)Database::lastId();
    $row = \App\Models\ExamRecord::find($eid);
    ok(\App\Models\ExamRecord::dispositionStatus($row)['key'] === 'register', '闭环：已处理并签字但未生成职业病登记 = 待登记');
    $stats = \App\Models\ExamRecord::dispositionStats($year);
    ok($stats['register'] === 1 && $stats['closed'] === 0, '闭环统计：待登记 1、已闭环 0');
    Database::run(
        "INSERT INTO diseases (worker_id, name, is_suspected, diag_date, exam_record_id, created_at, updated_at)
         VALUES (?, '噪声聋', 0, ?, ?, ?, ?)",
        [$wid, $year . '-06-01', $eid, $now, $now]
    );
    ok(\App\Models\ExamRecord::dispositionStatus(\App\Models\ExamRecord::find($eid))['key'] === 'notice_doc',
        'H2：书面签收但未挂告知书原件 = 待告知书原件');
    Database::run(
        "INSERT INTO documents (category, title, doc_date, file_path, doc_type, period_year, original_name, file_hash, link_type, link_id, created_at, updated_at)
         VALUES ('体检报告','体检结果书面告知','" . $year . "-05-20','tmp.pdf','W_EXAM_NOTICE'," . $year . ",'告知书','x','exam_record',?,?,?)",
        [$eid, $now, $now]
    );
    ok(\App\Models\ExamRecord::dispositionStatus(\App\Models\ExamRecord::find($eid))['key'] === 'closed',
        'H2：挂上告知书原件后认定为已闭环');
    // 电话告知即使签字也不得闭环
    Database::run(
        "INSERT INTO exam_records (worker_id, exam_date, exam_type, conclusion, handling, notice_method, signed_at, created_at, updated_at)
         VALUES (?, ?, '在岗期间', '职业病', '电话告知本人', '电话告知', ?, ?, ?)",
        [$wid, $year . '-05-02', $year . '-05-25', $now, $now]
    );
    $phoneId = (int)Database::lastId();
    Database::run(
        "INSERT INTO diseases (worker_id, name, is_suspected, diag_date, exam_record_id, created_at, updated_at)
         VALUES (?, '噪声聋', 0, ?, ?, ?, ?)",
        [$wid, $year . '-06-02', $phoneId, $now, $now]
    );
    ok(\App\Models\ExamRecord::dispositionStatus(\App\Models\ExamRecord::find($phoneId))['key'] === 'nonwritten',
        'H2：电话告知不得显示已闭环');
    Database::run(
        "INSERT INTO exam_records (worker_id, exam_date, exam_type, conclusion, created_at, updated_at)
         VALUES (?, ?, '在岗期间', '其他疾病或异常', ?, ?)",
        [$wid, $year . '-07-01', $now, $now]
    );
    ok(\App\Models\ExamRecord::abnormalCount($year) >= 3, '异常口径：其他疾病或异常计入异常全量口径');

    // H3：禁忌证 + 未结束接害段 + 完整告知仍不得闭环，须调离材料
    Database::run("UPDATE worker_posts SET hazards_snapshot = '噪声' WHERE id = ?", [$newId]);
    Database::run(
        "INSERT INTO exam_records (worker_id, exam_date, exam_type, conclusion, handling, notice_method, signed_at, created_at, updated_at)
         VALUES (?, ?, '在岗期间', '职业禁忌证', '调离接害岗位', '书面签收', ?, ?, ?)",
        [$wid, $year . '-08-01', $year . '-08-20', $now, $now]
    );
    $tabooId = (int)Database::lastId();
    Database::run(
        "INSERT INTO documents (category, title, doc_date, file_path, doc_type, period_year, original_name, file_hash, link_type, link_id, created_at, updated_at)
         VALUES ('体检报告','体检结果书面告知','" . $year . "-08-20','tmp.pdf','W_EXAM_NOTICE'," . $year . ",'告知书','x','exam_record',?,?,?)",
        [$tabooId, $now, $now]
    );
    ok(\App\Models\ExamRecord::dispositionStatus(\App\Models\ExamRecord::find($tabooId))['key'] === 'transfer',
        'H3：禁忌证仍有未结束接害段且无调岗材料 = 待调离');
    Database::run(
        "INSERT INTO documents (category, title, doc_date, file_path, doc_type, period_year, original_name, file_hash, link_type, link_id, created_at, updated_at)
         VALUES ('劳动用工资料','调岗通知书','" . $year . "-08-25','tmp.pdf','W_TRANSFER'," . $year . ",'调岗','x','exam_record',?,?,?)",
        [$tabooId, $now, $now]
    );
    ok(\App\Models\ExamRecord::dispositionStatus(\App\Models\ExamRecord::find($tabooId))['key'] === 'closed',
        'H3：挂上调岗通知书后禁忌证处置闭环');

    // 离岗类检查的受检人范围
    Database::run(
        "INSERT INTO workers (archive_no, name, gender, status, created_at, updated_at)
         VALUES ('C-0002','已离职无历史','女','离职',?,?)",
        [$now, $now]
    );
    $other = \App\Models\Worker::find((int)Database::lastId());
    $otherId = (int)$other['id'];
    ok(!\App\Models\ExamRecord::canExamine($other, '在岗期间'), '受检范围：离职且无接害史不进入在岗期间名单');
    ok(\App\Models\ExamRecord::canExamine($other, '离岗时'), '受检范围：离职人员可登记离岗时体检');
    $candidateSql = 'SELECT COUNT(*) FROM workers w WHERE ' . \App\Models\ExamRecord::candidateWhere('在岗期间');
    ok((int)Database::value($candidateSql) === 1, '受检范围：SQL 片段与 PHP 判断一致（仅 1 人进入在岗期间名单）');

    // 未判定不抬高合格率
    ok(\App\Models\DetectionReport::passPercent(10, 0, 4) === 100, '合格率：未判定剔除分母，全合格仍为 100%');
    ok(\App\Models\DetectionReport::passPercent(10, 1, 4) === 83, '合格率：10 点 4 点未判定且 1 点超标 → 83%（不是 90%）');

    // Q2/Q4：采样类型与归集岗位快照（配置变更后历史归集不漂移）
    Database::run("INSERT INTO workplaces (name, created_at, updated_at) VALUES ('测试场所', ?, ?)", [$now, $now]);
    $wpId = (int)Database::lastId();
    Database::run("INSERT INTO posts (workplace_id, name, status, created_at, updated_at) VALUES (?, '接害岗', '启用', ?, ?)", [$wpId, $now, $now]);
    $postId = (int)Database::lastId();
    Database::run("INSERT INTO hazards (name, category, created_at, updated_at) VALUES ('测试噪声','物理因素',?,?)", [$now, $now]);
    $hazardId = (int)Database::lastId();
    Database::run('INSERT INTO post_hazards (post_id, hazard_id, created_at, updated_at) VALUES (?, ?, ?, ?)', [$postId, $hazardId, $now, $now]);
    $snapshot = \App\Models\DetectionItem::snapshotPostIds(0, $wpId, $hazardId);
    ok($snapshot === (string)$postId, 'Q2：同场所同因素岗位在保存时冻结为归集快照');
    Database::run(
        "INSERT INTO worker_posts (worker_id, post_id, post_name, company_name, start_date, end_date, created_at, updated_at)
         VALUES (?, ?, '接害岗', '本单位', '2020-01-01', NULL, ?, ?)",
        [$wid, $postId, $now, $now]
    );
    Database::run(
        "INSERT INTO detection_reports (report_no, category, test_date, agency, created_at, updated_at)
         VALUES ('D-001', '定期检测', ?, '自测机构', ?, ?)",
        [$year . '-09-01', $now, $now]
    );
    $drId = (int)Database::lastId();
    Database::run(
        "INSERT INTO detection_items (report_id, workplace_id, hazard_id, test_point, result, unit, limit_value, sample_type, verdict, matched_post_ids, created_at, updated_at)
         VALUES (?, ?, ?, '1#点位', '80', 'dB(A)', 'PC-TWA 85 dB(A)', 'TWA', '合格', ?, ?, ?)",
        [$drId, $wpId, $hazardId, $snapshot, $now, $now]
    );
    $itemId = (int)Database::lastId();
    ok(\App\Models\DetectionItem::find($itemId)['sample_type'] === 'TWA', 'Q4：检测明细采样类型已持久化');
    $before = count(\App\Models\DetectionItem::forWorker($wid));
    Database::run('DELETE FROM post_hazards WHERE post_id = ?', [$postId]);
    ok(\App\Models\DetectionItem::forWorker($wid) > [], 'Q2：岗位危害配置删除后历史检测仍按保存时快照归集');
    ok($before === count(\App\Models\DetectionItem::forWorker($wid)), 'Q2：归集结果数量稳定，不随岗位配置漂移');

    // 旧调岗材料不能覆盖后来新出现的职业禁忌证（用另一人隔离 H3 已挂的同记录调岗材料）
    Database::run(
        "INSERT INTO exam_records (worker_id, exam_date, exam_type, conclusion, created_at, updated_at)
         VALUES (?, '2024-05-01', '在岗期间', '职业禁忌证', ?, ?)",
        [$otherId, $now, $now]
    );
    Database::run(
        "INSERT INTO documents (category, title, doc_date, file_path, doc_type, period_year, original_name, file_hash, link_type, link_id, created_at, updated_at)
         VALUES ('劳动用工资料','调岗通知书','2025-01-10','tmp.pdf','W_TRANSFER',2025,'调岗通知书','x','worker',?,?,?)",
        [$otherId, $now, $now]
    );
    $clues = \App\Helpers\WorkerArchive::eventClues();
    ok($clues['W_TRANSFER'] === 0, '事件线索：2024 禁忌证已有 2025 调岗材料覆盖');
    Database::run(
        "INSERT INTO exam_records (worker_id, exam_date, exam_type, conclusion, created_at, updated_at)
         VALUES (?, '2026-05-01', '在岗期间', '职业禁忌证', ?, ?)",
        [$otherId, $now, $now]
    );
    ok(\App\Helpers\WorkerArchive::eventClues()['W_TRANSFER'] === 1, '事件线索：旧的调岗材料不能覆盖 2026 年新禁忌证');
    // 无年度、无日期的事件材料无法比较先后，按“已有材料”覆盖线索（否则挂了原件仍永远在名单里）
    Database::run(
        "INSERT INTO documents (category, title, doc_date, file_path, doc_type, period_year, original_name, file_hash, link_type, link_id, created_at, updated_at)
         VALUES ('劳动用工资料','调岗通知书（无日期）','','tmp.pdf','W_TRANSFER',NULL,'调岗通知书','y-undated','worker',?,?,?)",
        [$otherId, $now, $now]
    );
    ok(\App\Helpers\WorkerArchive::eventClues()['W_TRANSFER'] === 0, '事件线索：无日期的调岗材料视为已覆盖');
    ok(\App\Helpers\WorkerArchive::eventClueCount(['W_SUSPECT', 'W_DIAG']) <= count(\App\Helpers\WorkerArchive::eventClueWorkers()), '事件线索人数按人去重，不超过名单人数');

    @unlink($db);
    @unlink($db . '-wal');
    @unlink($db . '-shm');
}

/* ============================================================
 * 入口
 * ============================================================ */
/** 注册表一致性：所有被目录/清单引用的 DocTypes 编码必须在注册表存在（孤儿编码检查） */
function cmdRegistry(): void
{
    section('注册表一致性（孤儿编码）');
    $types = \App\Helpers\DocTypes::all();
    $codes = array_keys($types);
    ok(count($codes) >= 45, 'DocTypes 注册类型 ≥45');
    ok(isset($types['W_EXAM_NOTICE']), 'W_EXAM_NOTICE 体检结果告知类型已注册');

    $orphans = [];
    foreach (\App\Helpers\ArchiveCatalog::filingCatalog() as $gk => $entries) {
        foreach ($entries as $e) {
            if (($e[2] ?? '') !== 'doc') {
                continue;
            }
            foreach (explode('、', (string)($e[3] ?? '')) as $code) {
                $code = trim($code);
                if ($code !== '' && !str_starts_with($code, '表') && !isset($types[$code])) {
                    $orphans[] = $gk . '#' . $e[0] . ': ' . $code;
                }
            }
        }
    }
    ok(!$orphans, '官方目录无孤儿资料类型' . ($orphans ? '：' . implode(', ', $orphans) : ''));

    $checkOrphans = [];
    foreach (\App\Helpers\ArchiveChecklist::catalog() as $code => $cfg) {
        foreach ($cfg['parts'] ?? [] as $p) {
            foreach ($p['types'] ?? [] as $t) {
                if (!isset($types[$t])) {
                    $checkOrphans[] = $code . ': ' . $t;
                }
            }
        }
    }
    ok(!$checkOrphans, '36项清单无孤儿资料类型' . ($checkOrphans ? '：' . implode(', ', $checkOrphans) : ''));

    ok(count(\App\Helpers\ArchiveCatalog::filingCatalog()) === 6, '官方目录仍为六类档案');
    $total = 0;
    foreach (\App\Helpers\ArchiveCatalog::filingCatalog() as $entries) {
        $total += count($entries);
    }
    ok($total === 48, '官方目录共 48 项（实际 ' . $total . '）');
}

$cmd = $argv[1] ?? 'all';
switch ($cmd) {
    case 'upgrade':
        cmdUpgrade();
        break;
    case 'schema-diff':
        cmdSchemaDiff();
        break;
    case 'matrix':
        cmdMatrix();
        break;
    case 'checklist':
        cmdChecklist();
        break;
    case 'robust':
        cmdRobust();
        break;
    case 'closure':
        cmdClosure();
        break;
    case 'registry':
        cmdRegistry();
        break;
    case 'all':
    default:
        cmdUpgrade();
        cmdSchemaDiff();
        cmdMatrix();
        cmdChecklist();
        cmdRobust();
        cmdClosure();
        cmdRegistry();
        break;
}
finish();
