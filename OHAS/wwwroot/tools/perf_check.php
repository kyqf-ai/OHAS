<?php
/**
 * 性能与健壮性验证（v0.5.4，方案 §6.5 任务 4）：
 * 在 200 名劳动者、每人 30 份文档的合成数据上测量：
 *   - WorkerArchive::build()（workers/files 页核心）逐人耗时
 *   - ArchiveChecklist::all() + WorkerArchive::gapWorkers()（archive/checklist 页核心）耗时
 * 通过 OHAS_DB 指定临时库；先跑 smoke_init 建库，再执行本脚本。
 *
 * 用法：
 *   export OHAS_DB=/tmp/perf.db && touch $OHAS_DB && php tools/smoke_init.php >/dev/null
 *   php tools/perf_check.php            # 建合成数据（幂等：已建则跳过）并测量
 *   php tools/perf_check.php --reset    # 强制重建合成数据
 */
declare(strict_types=1);

/* 仅限命令行 / 内建开发服务器；被 Web 服务器（Apache/nginx）误暴露时直接拒绝执行 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit('Forbidden');
}

// make_sample_files.php 内部已 require_once app/bootstrap.php（引导应用）
require __DIR__ . '/make_sample_files.php';

use App\Core\Database;

$reset = in_array('--reset', $argv, true);
$asOf = date('Y-m-d');
$asOfYear = (int)substr($asOf, 0, 4);

/* ---------- 合成数据：200 人 × 30 份文档 ---------- */
$syntheticKey = 'perf_synthetic_v054';
$built = (string)Database::value("SELECT value FROM settings WHERE key = ?", [$syntheticKey]);
if ($reset || $built === '') {
    fwrite(STDERR, "== 建合成数据（200 劳动者 × 30 文档）==\n");
    $now = now_str();
    $t0 = microtime(true);

    // 基础：场所 / 岗位 / 危害（沿用种子里的喷漆工；smoke_init 已建则复用）
    $wpId = (int)Database::value("SELECT id FROM workplaces WHERE name = '喷涂车间' LIMIT 1");
    if ($wpId === 0) {
        Database::run('INSERT INTO workplaces (name, location, status, created_at, updated_at) VALUES (?,?,?,?,?)', ['喷涂车间', '性能测试', '启用', $now, $now]);
        $wpId = (int)Database::lastId();
    }
    $poId = (int)Database::value("SELECT id FROM posts WHERE name = '喷漆工' LIMIT 1");
    if ($poId === 0) {
        Database::run('INSERT INTO posts (workplace_id, name, status, created_at, updated_at) VALUES (?,?,?,?,?)', [$wpId, '喷漆工', '启用', $now, $now]);
        $poId = (int)Database::lastId();
        foreach (['苯', '甲苯'] as $hz) {
            $hid = (int)Database::value('SELECT id FROM hazards WHERE name = ? LIMIT 1', [$hz]);
            if ($hid === 0) {
                Database::run('INSERT INTO hazards (name, category, status, created_at, updated_at) VALUES (?,?,?,?,?)', [$hz, '化学因素', '启用', $now, $now]);
                $hid = (int)Database::lastId();
            }
            Database::run('INSERT INTO post_hazards (post_id, hazard_id, exposure_type, created_at, updated_at) VALUES (?,?,?,?,?)', [$poId, $hid, '定点作业', $now, $now]);
        }
    }

    // 文档样例文件（一个真实 PDF 轮流复用，内容哈希不追求唯一）
    $samplePath = make_sample_file('pdf');

    $batch = [];
    $workerIds = [];
    for ($i = 1; $i <= 200; $i++) {
        $name = '性能' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
        $idCard = '33010619900' . str_pad((string)($i % 10000000), 7, '0', STR_PAD_LEFT) . '3';   // 不校验，仅性能数据
        Database::run(
            'INSERT INTO workers (archive_no, name, gender, id_card, entry_date, status, duty_role, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
            ['P' . str_pad((string)$i, 4, '0', STR_PAD_LEFT), $name, '男', $idCard, '2020-03-01', '在职', $i % 20 === 0 ? '主要负责人' : '', $now, $now]
        );
        $wid = (int)Database::lastId();
        $workerIds[] = $wid;
        Database::run(
            'INSERT INTO worker_posts (worker_id, post_id, start_date, end_date, company_name, post_name, hazards_snapshot, protection_snapshot, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$wid, $poId, '2020-03-01', null, '性能测试单位', '喷漆工', '苯、甲苯', '防毒口罩', $now, $now]
        );
        // 30 份文档：2021–2026 六年，每年 W_EXAM/W_EXAM_NOTICE/W_TRAIN/W_PPE/W_OTHER 各 1 份
        $types = ['W_EXAM', 'W_EXAM_NOTICE', 'W_TRAIN', 'W_PPE', 'W_OTHER'];
        $k = 0;
        for ($y = $asOfYear - 5; $y <= $asOfYear; $y++) {
            foreach ($types as $ti => $t) {
                $k++;
                $batch[] = [$wid, $y, $t, $k];
                if (count($batch) >= 400) {
                    insertDocs($batch, $samplePath, $now);
                    $batch = [];
                }
            }
        }
    }
    if ($batch) {
        insertDocs($batch, $samplePath, $now);
    }
    Database::run('INSERT OR REPLACE INTO settings(key, value) VALUES (?,?)', [$syntheticKey, date('Y-m-d H:i:s')]);
    fwrite(STDERR, sprintf("   合成数据完成：%.1fs\n", microtime(true) - $t0));
}

/** 批量插入文档（单事务多语句） */
function insertDocs(array $batch, string $samplePath, string $now): void
{
    $values = [];
    $params = [];
    foreach ($batch as [$wid, $y, $t, $k]) {
        $values[] = '(?,?,?,?,?,?,?,?,?,?,?,?,?)';
        array_push($params,
            \App\Helpers\DocTypes::categoryOf($t) ?: '其他',
            $t . ' 性能文档 ' . $k,
            $y . '-06-15', '', $samplePath, $t, $y,
            $t . ' 性能文档 ' . $k, '',
            'worker', $wid, $now, $now
        );
    }
    Database::run(
        'INSERT INTO documents (category, title, doc_date, expire_date, file_path, doc_type, period_year, original_name, file_hash, link_type, link_id, created_at, updated_at)
         VALUES ' . implode(',', $values),
        $params
    );
}

/* ---------- 测量 ---------- */
echo "== 性能测量（asOf={$asOf}）==\n";
$wcount = (int)Database::value('SELECT COUNT(*) FROM workers');
$dcount = (int)Database::value('SELECT COUNT(*) FROM documents');
echo "   规模：workers={$wcount} documents={$dcount}\n";

// workers/files 核心：逐人 build（抽 20 人：前 10 + 后 10）
$ids = array_map('intval', array_column(Database::rows('SELECT id FROM workers ORDER BY id'), 'id'));
$sample = array_merge(array_slice($ids, 0, 10), array_slice($ids, -10));
$t0 = microtime(true);
$max = 0.0;
foreach ($sample as $wid) {
    $t1 = microtime(true);
    \App\Helpers\WorkerArchive::build($wid, 'all');
    $el = microtime(true) - $t1;
    if ($el > $max) { $max = $el; }
}
$buildAvg = (microtime(true) - $t0) / max(1, count($sample));
printf("   WorkerArchive::build：均值 %.1f ms/人，最大 %.1f ms\n", $buildAvg * 1000, $max * 1000);

// archive/checklist 核心：清单全量 + 缺口人员
$t0 = microtime(true);
\App\Helpers\ArchiveChecklist::all();
$cl = microtime(true) - $t0;
$t0 = microtime(true);
\App\Helpers\WorkerArchive::gapWorkers();
$gw = microtime(true) - $t0;
printf("   ArchiveChecklist::all：%.0f ms；gapWorkers：%.0f ms\n", $cl * 1000, $gw * 1000);

$pass = ($buildAvg < 1.0 && $max < 1.0 && ($cl + $gw) < 1.0);
echo $pass
    ? "PASS：均在 1 秒内\n"
    : "FAIL：超过 1 秒（build 均值/最大、checklist+gaps 见上），需对 documents(link_type,link_id)/(doc_type,period_year) 查询做合并或加索引\n";
exit($pass ? 0 : 1);
