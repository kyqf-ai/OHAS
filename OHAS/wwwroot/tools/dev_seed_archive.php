<?php
/**
 * v0.5 自测种子（方案 §8.3）：张三 / 李四 / 王五 + 检测报告。
 * 自包含、幂等、可重入（函数带 function_exists 守卫，重复 include 不再致命；
 * selftest 在多个临时库上复用同一进程时按当前连接重复执行）。
 * 固定 asOf 期望值见 tools/selftest.php（asOf = 2026-11-19）。
 *
 * 场景设定：
 *  A 张三  在职，入职 2023-03-01；岗位“喷漆工”（配苯、甲苯）自 2023 起；
 *          2023、2024 年体检记录各挂 1 份个人体检报告；2025 年有体检记录但无原件；
 *          2024 年有参训记录并挂 1 份培训资料；有 1 份劳动合同（到期 2026-06-30）；无告知书
 *  B 李四  在职，入职 2022，岗位“行政”（未配危害因素），职务角色“主要负责人”；
 *          有合同；有任命文件；无职责说明
 *  C 王五  离职，职业史 2023-01-01 至 2024-06-30，岗位“调漆工”（配甲苯）；无任何文档
 *  检测    2024、2025 各 1 份定期检测报告，明细关联“喷漆工”岗位，
 *          2024 的报告有原件，2025 的没有
 */
declare(strict_types=1);

/* 仅限命令行 / 内建开发服务器；被 Web 服务器（Apache/nginx）误暴露时直接拒绝执行 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit('Forbidden');
}

use App\Core\Database;

require_once __DIR__ . '/make_sample_files.php';

if (!function_exists('seed_id')) {
    /** 合法身份证号：17 位前缀 + 校验码 */
    function seed_id(string $prefix17): string
    {
        $w = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $c = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += (int)$prefix17[$i] * $w[$i];
        }
        return $prefix17 . $c[$sum % 11];
    }
}

if (!function_exists('seed_doc')) {
    /** 建文档（真实样例文件；幂等） */
    function seed_doc(string $docType, string $title, ?int $year, string $linkType, ?int $linkId, string $expireDate = ''): int
    {
        $existing = Database::row(
            'SELECT id FROM documents WHERE title = ? AND COALESCE(doc_type,\'\') = ? AND link_type = ? AND link_id IS ?',
            [$title, $docType, $linkType, $linkId]
        );
        if ($existing) {
            return (int)$existing['id'];
        }
        $path = make_sample_file('pdf');
        $abs = \App\Helpers\Uploader::absPath($path);
        Database::run(
            'INSERT INTO documents (category, title, doc_date, expire_date, file_path, doc_type, period_year, original_name, file_hash, link_type, link_id, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                \App\Helpers\DocTypes::categoryOf($docType) ?: '其他',
                $title,
                $year !== null ? $year . '-01-01' : '',
                $expireDate,
                $path,
                $docType,
                $year,
                $title,
                ($abs !== null && is_file($abs)) ? sha1_file($abs) : '',
                $linkType,
                $linkId,
                now_str(),
                now_str(),
            ]
        );
        return (int)Database::lastId();
    }
}

if (!function_exists('dev_seed_archive_run')) {
    function dev_seed_archive_run(): void
    {
        $val = fn (string $sql, array $p = []) => Database::value($sql, $p);
        $now = now_str();

        /* ---------- 基础：危害因素 / 场所 / 岗位（自包含） ---------- */
        foreach ([['苯', '化学因素'], ['甲苯', '化学因素']] as [$hzName, $hzCat]) {
            if (!$val('SELECT id FROM hazards WHERE name = ? AND category = ?', [$hzName, $hzCat])) {
                Database::run('INSERT INTO hazards (name, category, status, created_at, updated_at) VALUES (?,?,?,?,?)', [$hzName, $hzCat, '启用', $now, $now]);
            }
        }
        $hz = fn (string $n) => (int)$val('SELECT id FROM hazards WHERE name = ? LIMIT 1', [$n]);

        foreach ([['喷涂车间', '2号厂房'], ['行政办公楼', '厂区北侧'], ['调漆间', '2号厂房东侧']] as [$wpName, $loc]) {
            if (!$val('SELECT id FROM workplaces WHERE name = ?', [$wpName])) {
                Database::run('INSERT INTO workplaces (name, location, status, created_at, updated_at) VALUES (?,?,?,?,?)', [$wpName, $loc, '启用', $now, $now]);
            }
        }
        $wp = fn (string $n) => (int)$val('SELECT id FROM workplaces WHERE name = ?', [$n]);

        $posts = [
            ['喷涂车间', '喷漆工', ['苯', '甲苯']],
            ['行政办公楼', '行政', []],
            ['调漆间', '调漆工', ['甲苯']],
        ];
        foreach ($posts as [$wpName, $poName, $hzNames]) {
            if (!$val('SELECT id FROM posts WHERE name = ? AND workplace_id = ?', [$poName, $wp($wpName)])) {
                Database::run('INSERT INTO posts (workplace_id, name, status, created_at, updated_at) VALUES (?,?,?,?,?)', [$wp($wpName), $poName, '启用', $now, $now]);
            }
            $pid = (int)$val('SELECT id FROM posts WHERE name = ? AND workplace_id = ?', [$poName, $wp($wpName)]);
            foreach ($hzNames as $h) {
                if (!$val('SELECT id FROM post_hazards WHERE post_id = ? AND hazard_id = ?', [$pid, $hz($h)])) {
                    Database::run('INSERT INTO post_hazards (post_id, hazard_id, exposure_type, created_at, updated_at) VALUES (?,?,?,?,?)', [$pid, $hz($h), '定点作业', $now, $now]);
                }
            }
        }
        $po = fn (string $n) => (int)$val('SELECT id FROM posts WHERE name = ? LIMIT 1', [$n]);

        /* ---------- 劳动者 ---------- */
        $workers = [
            ['张三', '男', seed_id('33010619910315003'), '2023-03-01', '在职', ''],
            ['李四', '女', seed_id('33010619880622004'), '2022-03-01', '在职', '主要负责人'],
            ['王五', '男', seed_id('33010619920718005'), '2023-01-01', '离职', ''],
        ];
        foreach ($workers as [$name, $gender, $idCard, $entry, $status, $duty]) {
            if (!$val('SELECT id FROM workers WHERE id_card = ?', [$idCard])) {
                Database::run(
                    'INSERT INTO workers (archive_no, name, gender, id_card, entry_date, status, duty_role, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
                    [date('Y') . str_pad((string)((int)Database::value('SELECT COUNT(*) FROM workers') + 1), 3, '0', STR_PAD_LEFT), $name, $gender, $idCard, $entry, $status, $duty, $now, $now]
                );
            } elseif ($duty !== '') {
                Database::run('UPDATE workers SET duty_role = ? WHERE id_card = ?', [$duty, $idCard]);
            }
        }
        $wk = fn (string $n) => (int)$val('SELECT id FROM workers WHERE name = ? LIMIT 1', [$n]);

        /* ---------- 职业史 ---------- */
        $histories = [
            ['张三', '喷漆工', '2023-03-01', null, '苯、甲苯', '水帘喷漆室；供气式全面罩'],
            ['李四', '行政', '2022-03-01', null, '', ''],
            ['王五', '调漆工', '2023-01-01', '2024-06-30', '甲苯', '局部排风；防毒口罩'],
        ];
        foreach ($histories as [$name, $poName, $start, $end, $snap, $prot]) {
            if (!$val('SELECT id FROM worker_posts WHERE worker_id = ? AND post_id = ?', [$wk($name), $po($poName)])) {
                Database::run(
                    'INSERT INTO worker_posts (worker_id, post_id, start_date, end_date, company_name, post_name, hazards_snapshot, protection_snapshot, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [$wk($name), $po($poName), $start, $end, '自测单位', $poName, $snap, $prot, $now, $now]
                );
            }
        }

        /* ---------- 体检批次与记录（张三 2023/2024/2025；2025 无原件） ---------- */
        foreach ([['2023-07-15'], ['2024-07-20'], ['2025-07-10']] as [$examDate]) {
            $bid = $val('SELECT id FROM exam_batches WHERE exam_date = ? AND agency = ?', [$examDate, '自测体检机构']);
            if (!$bid) {
                Database::run('INSERT INTO exam_batches (exam_date, agency, exam_type, planned_count, created_at, updated_at) VALUES (?,?,?,?,?,?)', [$examDate, '自测体检机构', '在岗期间', 1, $now, $now]);
                $bid = (int)Database::lastId();
            }
            $rid = $val('SELECT id FROM exam_records WHERE worker_id = ? AND exam_date = ?', [$wk('张三'), $examDate]);
            if (!$rid) {
                Database::run(
                    'INSERT INTO exam_records (worker_id, batch_id, exam_date, exam_type, conclusion, agency, post_name, hazards_snapshot, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [$wk('张三'), (int)$bid, $examDate, '在岗期间', '目前未见异常', '自测体检机构', '喷漆工', '苯、甲苯', $now, $now]
                );
                $rid = (int)Database::lastId();
            }
            if ($examDate !== '2025-07-10') {
                // 2023、2024：体检记录各挂 1 份个人体检报告；2025 有记录无原件
                seed_doc('W_EXAM', '体检报告 张三 ' . substr($examDate, 0, 4), (int)substr($examDate, 0, 4), 'exam_record', (int)$rid);
            }
        }

        /* ---------- 培训（张三 2024 参训 + 培训资料） ---------- */
        $tid = $val('SELECT id FROM trainings WHERE title = ?', ['自测 2024 年度职业健康培训']);
        if (!$tid) {
            Database::run('INSERT INTO trainings (type, title, hours, teacher, organizer, trained_on, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)',
                ['在岗期间培训', '自测 2024 年度职业健康培训', 4, '李卫平', '综合管理部', '2024-04-10', $now, $now]);
            $tid = (int)Database::lastId();
        }
        if (!$val('SELECT id FROM training_participants WHERE training_id = ? AND worker_id = ?', [(int)$tid, $wk('张三')])) {
            Database::run('INSERT INTO training_participants (training_id, worker_id, score, attended, created_at, updated_at) VALUES (?,?,?,?,?,?)',
                [(int)$tid, $wk('张三'), '95', '是', $now, $now]);
        }
        seed_doc('W_TRAIN', '培训记录 张三 2024', 2024, 'worker', $wk('张三'));

        /* ---------- 一次性资料 ---------- */
        seed_doc('W_CONTRACT', '劳动合同 张三', 2023, 'worker', $wk('张三'), '2026-06-30');   // 已到期 → hint
        seed_doc('W_CONTRACT', '劳动合同 李四', 2022, 'worker', $wk('李四'));
        seed_doc('W_APPOINT', '管理人员任命文件 李四', 2022, 'worker', $wk('李四'));
        // 张三无告知书；李四无职责说明（W_DUTY）；王五无任何文档 —— 保持不插入

        /* ---------- 检测报告（2024 有原件，2025 无） ---------- */
        foreach ([['JC-A-2024', '2024-05-10'], ['JC-A-2025', '2025-05-12']] as [$no, $testDate]) {
            $rid = $val('SELECT id FROM detection_reports WHERE report_no = ?', [$no]);
            if (!$rid) {
                Database::run('INSERT INTO detection_reports (report_no, agency, category, test_date, report_date, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
                    [$no, '自测检测机构', '定期检测', $testDate, date('Y-m-d', strtotime($testDate . ' +12 days')), $now, $now]);
                $rid = (int)Database::lastId();
                foreach (['苯', '甲苯'] as $h) {
                    Database::run('INSERT INTO detection_items (report_id, workplace_id, post_id, hazard_id, test_point, result, unit, limit_value, verdict, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                        [$rid, $wp('喷涂车间'), $po('喷漆工'), $hz($h), '喷漆室操作位', '3.1', 'mg/m³', '6', '合格', $now, $now]);
                }
            }
            if ($no === 'JC-A-2024') {
                seed_doc('U_DETECT_REPORT', '检测报告 ' . $no, (int)substr($testDate, 0, 4), 'detection_report', (int)$rid);
            }
        }

        echo "== v0.5 自测种子完成 ==\n";
        echo 'workers=' . Database::value('SELECT COUNT(*) FROM workers')
            . ' exam_records=' . Database::value('SELECT COUNT(*) FROM exam_records')
            . ' detection_reports=' . Database::value('SELECT COUNT(*) FROM detection_reports')
            . ' documents=' . Database::value('SELECT COUNT(*) FROM documents') . "\n";
    }
}

dev_seed_archive_run();
