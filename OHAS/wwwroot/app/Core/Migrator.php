<?php
/**
 * 自动迁移：以 SQLite PRAGMA user_version 记录结构版本，启动时按需补齐。
 * 每一步都是幂等的；迁移失败显式抛错，由路由渲染错误页并写入错误日志。
 *
 * 迁移负责在保留既有数据的前提下收敛到当前结构：
 * - 危害因素字典按法定目录同步，系统内置值可刷新，用户维护值不覆盖；
 * - 台账的场所/岗位、设备/防护品/警示标识的危害因素、职业病登记来源体检记录等改用关联表和关联列；
 * - 同批次同人、同场培训同人、同场所同岗位名、岗位危害配置等用唯一索引防重；
 * - 报告原件统一存于 documents，业务记录删除时通过触发器解除关联，原件保留；
 * - 旧列只在确实存在时迁入数据并清空，不物理删除，规避 SQLite 兼容性风险；
 * - 新装库执行 schema.sql 后直接写入相同版本，结构一致性由自测校验。
 */
declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

class Migrator
{
    public const VERSION = 21;

    /** link_type => 业务表（静态 6 类；台账动态类与 disease 见 linkTables()） */
    private const LINK_TABLES = [
        'worker'           => 'workers',
        'workplace'        => 'workplaces',
        'project'          => 'projects',
        'detection_report' => 'detection_reports',
        'exam_batch'       => 'exam_batches',
        'exam_record'      => 'exam_records',
    ];

    /**
     * 全部 link_type => 业务表：静态业务类 + disease + 台账动态类。
     * projects 沿用已有 project，不生成 ledger_projects。
     */
    public static function linkTables(): array
    {
        $tables = self::LINK_TABLES;
        $tables['disease'] = 'diseases';
        $tables['archive_material'] = 'archive_materials';
        foreach (\App\Helpers\LedgerCatalog::all() as $key => $cfg) {
            $tables[\App\Helpers\DocLink::typeForLedger($key)] = $cfg['table'];
        }
        return $tables;
    }

    /** 补建「业务记录删除时解除文档关联」触发器（幂等，可重复执行；触发器名只含 [a-z_]） */
    public static function ensureDocUnlinkTriggers(PDO $pdo): void
    {
        foreach (self::linkTables() as $type => $table) {
            self::createDocUnlinkTrigger($pdo, $type, $table);
        }
    }

    /** 单个触发器创建（IF NOT EXISTS，幂等；表不存在时跳过——升级路径中表可能由更高版本迁移创建） */
    private static function createDocUnlinkTrigger(PDO $pdo, string $type, string $table): void
    {
        $has = $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name = '{$table}'")->fetchColumn();
        if (!$has) {
            return;
        }
        $pdo->exec(
            "CREATE TRIGGER IF NOT EXISTS trg_doc_unlink_{$type} AFTER DELETE ON {$table}
             BEGIN
                 UPDATE documents SET link_type = '', link_id = NULL
                 WHERE link_type = '{$type}' AND link_id = OLD.id;
             END"
        );
    }

    public static function run(PDO $pdo): void
    {
        try {
            $ver = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
            if ($ver >= self::VERSION) {
                return;
            }
            // 尚未建库（安装流程中）则跳过，建库完成后的首次连接再迁移
            $has = $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='documents'")->fetchColumn();
            if (!$has) {
                return;
            }
            // 并发保护：多个请求同时遇到旧库时只让一个执行迁移，其余等锁后复核版本即返回
            $lock = self::lock();
            try {
                $ver = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
                if ($ver < self::VERSION) {
                    self::applySteps($pdo, $ver);
                }
            } finally {
                self::unlock($lock);
            }
        } catch (Throwable $e) {
            // 迁移失败必须显式抛出：结构不一致会让全站不可用，需由路由渲染错误页并携带定位信息。
            @error_log('[OHAS migrate] 迁移失败（user_version=' . $ver . '）：' . $e->getMessage());
            throw new \RuntimeException(
                '数据库迁移失败：' . $e->getMessage() . '（详情见 storage/logs/app-error.log，请联系管理员处理后刷新重试）',
                0,
                $e
            );
        }
    }

    /** 迁移步骤本体（调用方已持锁且已复核版本）；各步骤均幂等 */
    private static function applySteps(PDO $pdo, int $ver): void
    {
        if ($ver < 4) {
            self::toV4($pdo);
        }
        if ($ver < 5) {
            self::toV5($pdo);
        }
        if ($ver < 6) {
            self::toV6($pdo);
        }
        // 目录同步在一次迁移中只执行一次；需要同步时整体包事务，避免逐条提交。
        if ($ver < 7) {
            self::toV7($pdo);
        } elseif ($ver < 9) {
            self::syncCatalogTx($pdo, $ver < 8 ? 'v8' : 'v9');
        }
        if ($ver < 10) {
            self::toV10($pdo);
        }
        if ($ver < 11) {
            self::toV11($pdo);
        }
        if ($ver < 12) {
            self::toV12($pdo);
        }
        if ($ver < 13) {
            self::toV13($pdo);
        }
        if ($ver < 14) {
            self::toV14($pdo);
        }
        if ($ver < 15) {
            self::toV15($pdo);
        }
        if ($ver < 16) {
            self::toV16($pdo);
        }
        if ($ver < 17) {
            self::toV17($pdo);
        }
        if ($ver < 18) {
            self::toV18($pdo);
        }
        if ($ver < 19) {
            self::toV19($pdo);
        }
        if ($ver < 20) {
            self::toV20($pdo);
        }
        if ($ver < 21) {
            self::toV21($pdo);
        }
        $pdo->exec('PRAGMA user_version = ' . self::VERSION);
        // 体检档案保管年限提示：配置项默认空，幂等插入，与结构版本无关。
        $pdo->exec("INSERT OR IGNORE INTO settings(key, value) VALUES ('retention_exam_years', '')");
    }

    /** 迁移互斥锁（文件锁，与数据库同目录）；拿不到文件句柄时退化为无锁执行 */
    private static function lock()
    {
        $fp = @fopen(dirname((string)cfg('db.path')) . '/migrate.lock', 'c');
        if ($fp !== false) {
            @flock($fp, LOCK_EX);
            return $fp;
        }
        return null;
    }

    private static function unlock($fp): void
    {
        if ($fp) {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    private static function toV4(PDO $pdo): void
    {
        $legacy = [
            // 表, 旧字段, 分类, link_type, 标题表达式, 机构表达式, 日期表达式, FROM 子句
            ['detection_reports', 'file_path', '检测评价报告', 'detection_report',
                "'检测报告 ' || t.report_no", 't.agency', "COALESCE(NULLIF(t.report_date,''), t.test_date, '')", 'detection_reports t'],
            ['exam_batches', 'report_file', '体检报告', 'exam_batch',
                "COALESCE(NULLIF(t.exam_date,''),'未填日期') || ' ' || t.exam_type || '体检批次报告'", 't.agency', "COALESCE(t.exam_date,'')", 'exam_batches t'],
            ['exam_records', 'report_file', '体检报告', 'exam_record',
                "'个人体检报告 ' || w.name || ' ' || COALESCE(t.exam_date,'')", "COALESCE(t.agency,'')", "COALESCE(t.exam_date,'')", 'exam_records t JOIN workers w ON w.id = t.worker_id'],
        ];

        // 需要迁移数据时，先留一份数据库副本
        $needBackup = false;
        foreach ($legacy as [$tbl, $col]) {
            if (self::hasColumn($pdo, $tbl, $col)
                && (int)$pdo->query("SELECT COUNT(*) FROM {$tbl} WHERE COALESCE({$col},'') <> ''")->fetchColumn() > 0) {
                $needBackup = true;
            }
        }
        if ($needBackup) {
            self::backup($pdo);
        }

        $pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            foreach ($legacy as [$tbl, $col, $cat, $type, $title, $issuer, $date, $from]) {
                if (!self::hasColumn($pdo, $tbl, $col)) {
                    continue;
                }
                $pdo->exec(
                    "INSERT INTO documents (category, title, issuer, doc_date, file_path, link_type, link_id, remark, created_at, updated_at)
                     SELECT '{$cat}', {$title}, {$issuer}, {$date}, t.{$col}, '{$type}', t.id, '由旧版报告字段迁入', '{$now}', '{$now}'
                     FROM {$from}
                     WHERE COALESCE(t.{$col},'') <> ''
                       AND NOT EXISTS (SELECT 1 FROM documents d WHERE d.file_path = t.{$col})"
                );
                $pdo->exec("UPDATE {$tbl} SET {$col} = '' WHERE COALESCE({$col},'') <> ''");
            }
            foreach (self::linkTables() as $type => $table) {
                self::createDocUnlinkTrigger($pdo, $type, $table);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** 职业病登记关联来源体检记录（表5-2 → 表5-3/5-4 联动）。 */
    private static function toV5(PDO $pdo): void
    {
        // 多语句 DDL 包进同一事务，中途失败整体回滚。
        $pdo->beginTransaction();
        try {
            if (!self::hasColumn($pdo, 'diseases', 'exam_record_id')) {
                $pdo->exec('ALTER TABLE diseases ADD COLUMN exam_record_id INTEGER DEFAULT NULL');
            }
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_diseases_exam_record ON diseases(exam_record_id) WHERE exam_record_id IS NOT NULL');
            $pdo->exec(
                'CREATE TRIGGER IF NOT EXISTS trg_disease_unlink_exam AFTER DELETE ON exam_records
                 BEGIN
                     UPDATE diseases SET exam_record_id = NULL WHERE exam_record_id = OLD.id;
                 END'
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** 台账场所/岗位关联（表4-1、2-6、2-7、5-5）。 */
    private static function toV6(PDO $pdo): void
    {
        // 表 => 需要补建的关联列
        $columns = [
            'materials'        => ['workplace_id' => 'workplaces', 'post_id' => 'posts'],
            'ppe_issues'       => ['workplace_id' => 'workplaces', 'post_id' => 'posts'],
            'warning_signs'    => ['workplace_id' => 'workplaces'],
            'health_accidents' => ['workplace_id' => 'workplaces', 'post_id' => 'posts'],
        ];
        foreach ($columns as $table => $cols) {
            foreach ($cols as $col => $ref) {
                if (!self::hasColumn($pdo, $table, $col)) {
                    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$col} INTEGER DEFAULT NULL REFERENCES {$ref}(id)");
                }
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$table}_" . substr($col, 0, -3) . " ON {$table}({$col})");
            }
        }

        $pdo->beginTransaction();
        try {
            self::linkLegacyPlaces($pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * 把自由文本中能唯一对上的场所/岗位名称关联到关联列。
     * 只写关联列，不改动原文本；对不上或有歧义的保持原样，由用户在页面中手工选择。
     */
    private static function linkLegacyPlaces(PDO $pdo): void
    {
        $wpByName = [];
        foreach ($pdo->query('SELECT id, name FROM workplaces') as $r) {
            $wpByName[trim((string)$r['name'])] = (int)$r['id'];
        }
        if (!$wpByName) {
            return;
        }
        $postsByName = [];   // 岗位名 => [[id, workplace_id], ...]
        $postInWp = [];      // workplace_id|岗位名 => id
        foreach ($pdo->query('SELECT id, workplace_id, name FROM posts') as $r) {
            $n = trim((string)$r['name']);
            $postsByName[$n][] = [(int)$r['id'], (int)$r['workplace_id']];
            $postInWp[(int)$r['workplace_id'] . '|' . $n] = (int)$r['id'];
        }

        // 解析一段文本 => [workplace_id|null, post_id|null]
        $resolve = function (string $text) use ($wpByName, $postsByName, $postInWp): array {
            $text = trim($text);
            if ($text === '') {
                return [null, null];
            }
            if (isset($wpByName[$text])) {
                return [$wpByName[$text], null];
            }
            if (isset($postsByName[$text]) && count($postsByName[$text]) === 1) {
                return [$postsByName[$text][0][1], $postsByName[$text][0][0]];
            }
            $tokens = preg_split('/[\\s\\/／·•\\-—–、，,;；]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($tokens) === 2 && isset($wpByName[$tokens[0]])) {
                $wid = $wpByName[$tokens[0]];
                $key = $wid . '|' . $tokens[1];
                if (isset($postInWp[$key])) {
                    return [$wid, $postInWp[$key]];
                }
            }
            return [null, null];
        };

        // 单列文本 + 场所/岗位（表4-1、表2-6）
        foreach ([['materials', 'usage_place'], ['ppe_issues', 'department']] as [$table, $col]) {
            $upd = $pdo->prepare("UPDATE {$table} SET workplace_id = ?, post_id = ? WHERE id = ?");
            $rows = $pdo->query(
                "SELECT id, {$col} AS txt FROM {$table}
                 WHERE workplace_id IS NULL AND post_id IS NULL AND COALESCE({$col},'') <> ''"
            )->fetchAll();
            foreach ($rows as $r) {
                [$wid, $pid] = $resolve((string)$r['txt']);
                if ($wid !== null) {
                    $upd->execute([$wid, $pid, (int)$r['id']]);
                }
            }
        }

        // 警示标识（表2-7）：设置地点与场所名称完全一致时关联场所
        $upd = $pdo->prepare('UPDATE warning_signs SET workplace_id = ? WHERE id = ?');
        foreach ($pdo->query("SELECT id, location FROM warning_signs WHERE workplace_id IS NULL AND COALESCE(location,'') <> ''")->fetchAll() as $r) {
            $t = trim((string)$r['location']);
            if (isset($wpByName[$t])) {
                $upd->execute([$wpByName[$t], (int)$r['id']]);
            }
        }

        // 事故报告（表5-5）：场所、岗位各一列
        $upd = $pdo->prepare('UPDATE health_accidents SET workplace_id = ?, post_id = ? WHERE id = ?');
        $rows = $pdo->query(
            "SELECT id, workplace_name, post_name FROM health_accidents
             WHERE workplace_id IS NULL AND post_id IS NULL
               AND (COALESCE(workplace_name,'') <> '' OR COALESCE(post_name,'') <> '')"
        )->fetchAll();
        foreach ($rows as $r) {
            $wn = trim((string)$r['workplace_name']);
            $pn = trim((string)$r['post_name']);
            $wid = $wn !== '' ? ($wpByName[$wn] ?? null) : null;
            $pid = null;
            if ($pn !== '') {
                if ($wid !== null && isset($postInWp[$wid . '|' . $pn])) {
                    $pid = $postInWp[$wid . '|' . $pn];
                } elseif ($wn === '' && isset($postsByName[$pn]) && count($postsByName[$pn]) === 1) {
                    [$pid, $wid] = $postsByName[$pn][0];
                }
            }
            if ($wid !== null) {
                $upd->execute([$wid, $pid, (int)$r['id']]);
            }
        }
    }

    /**
     * 危害因素字典按《职业病危害因素分类目录》同步。
     * 同步规则与 Hazard::syncCatalog() 一致（按名称+类别比对，只补不改用户数据）；
     * 仅清理未被业务引用且未被目录吸收的简化种子残留。
     */
    private static function toV7(PDO $pdo): void
    {
        $pdo->beginTransaction();
        try {
            $r = \App\Models\Hazard::syncCatalog();
            $inserted = $r['inserted'];
            $updated = $r['updated'];

            // 简化种子残留清理：仅删（名称+类别）与种子清单完全一致、目录未吸收
            // 且未被业务引用的行；被引用或用户改过的保留。
            $legacyDel = $pdo->prepare(
                'DELETE FROM hazards WHERE id IN (
                    SELECT h.id FROM hazards h
                    WHERE h.name = ? AND h.category = ? AND COALESCE(h.catalog_source, \'\') = \'\'
                      AND NOT EXISTS (SELECT 1 FROM post_hazards ph WHERE ph.hazard_id = h.id)
                      AND NOT EXISTS (SELECT 1 FROM detection_items di WHERE di.hazard_id = h.id)
                      AND NOT EXISTS (SELECT 1 FROM materials m WHERE m.hazard_id = h.id)
                      AND NOT EXISTS (SELECT 1 FROM ppe_issues pi WHERE pi.hazard_id = h.id)
                 )'
            );
            $cleaned = 0;
            foreach (\App\Helpers\HazardSeed::list() as $seed) {
                $legacyDel->execute([$seed['name'], $seed['category']]);
                $cleaned += $legacyDel->rowCount();
            }

            $pdo->commit();
            @error_log(sprintf('[OHAS migrate] v7 危害因素分类目录：新增 %d 条，更新 %d 条，清理旧种子 %d 条', $inserted, $updated, $cleaned));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** 同步危害因素目录（限值 / 可能职业病），单事务执行。 */
    private static function syncCatalogTx(PDO $pdo, string $label): void
    {
        $pdo->beginTransaction();
        try {
            $r = \App\Models\Hazard::syncCatalog();
            $pdo->commit();
            @error_log(sprintf('[OHAS migrate] %s 危害因素目录同步：新增 %d 条，更新 %d 条', $label, $r['inserted'], $r['updated']));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function hasColumn(PDO $pdo, string $table, string $col): bool
    {
        foreach ($pdo->query("PRAGMA table_info({$table})") as $r) {
            if ($r['name'] === $col) {
                return true;
            }
        }
        return false;
    }

    /**
     * 体检批次内同人、培训签到同人唯一索引。
     * 建索引前先清理既有重复数据，否则 CREATE UNIQUE INDEX 会失败：
     * - exam_records：同批次同人重复时优先保留「已出结论」的记录，同级再保留较早的（id 小）；
     * - training_participants：同场培训同人重复时保留最早一条。
     */
    private static function toV10(PDO $pdo): void
    {
        // 清理 + 建索引包进同一事务，避免中途失败导致防重索引永久缺失。
        $pdo->beginTransaction();
        try {
        $dupExams = (int)$pdo->query(
            "SELECT COUNT(*) FROM (
                SELECT batch_id, worker_id FROM exam_records
                WHERE batch_id IS NOT NULL
                GROUP BY batch_id, worker_id HAVING COUNT(*) > 1
             )"
        )->fetchColumn();
        if ($dupExams > 0) {
            $pdo->exec(
                "DELETE FROM exam_records
                 WHERE batch_id IS NOT NULL
                   AND id NOT IN (
                       SELECT id FROM (
                           SELECT id, ROW_NUMBER() OVER (
                               PARTITION BY batch_id, worker_id
                               ORDER BY CASE WHEN TRIM(COALESCE(conclusion,'')) <> '' THEN 0 ELSE 1 END, id
                           ) AS rn
                           FROM exam_records WHERE batch_id IS NOT NULL
                       ) WHERE rn = 1
                   )"
            );
            @error_log("[OHAS migrate] v10 清理 exam_records 同批次同人重复：涉及 {$dupExams} 组");
        }
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_exam_records_batch_worker ON exam_records(batch_id, worker_id) WHERE batch_id IS NOT NULL');

        $dupRoster = (int)$pdo->query(
            "SELECT COUNT(*) FROM (
                SELECT training_id, worker_id FROM training_participants
                GROUP BY training_id, worker_id HAVING COUNT(*) > 1
             )"
        )->fetchColumn();
        if ($dupRoster > 0) {
            $pdo->exec(
                "DELETE FROM training_participants
                 WHERE id NOT IN (
                     SELECT id FROM (
                         SELECT id, ROW_NUMBER() OVER (
                             PARTITION BY training_id, worker_id ORDER BY id
                         ) AS rn
                         FROM training_participants
                     ) WHERE rn = 1
                 )"
            );
            @error_log("[OHAS migrate] v10 清理 training_participants 同场培训同人重复：涉及 {$dupRoster} 组");
        }
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_training_participants ON training_participants(training_id, worker_id)');
        $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** 多账号：users 增加 status（启用/停用），既有账号默认启用。 */
    private static function toV12(PDO $pdo): void
    {
        $pdo->beginTransaction();
        try {
            if (!self::hasColumn($pdo, 'users', 'status')) {
                $pdo->exec("ALTER TABLE users ADD COLUMN status TEXT NOT NULL DEFAULT '启用'");
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * 表4-1 设备材料化学品 × 危害因素使用关联表。
     * - material_hazards 以 (material_id, hazard_id) 唯一，删设备时级联删关联；
     * - 若 materials.hazard_id 列存在，迁入后清空（列保留恒为 NULL：SQLite 对带外键列 DROP COLUMN 兼容性风险高）；
     * - INSERT OR IGNORE + 唯一索引保证重复执行不产生重复关联。
     */
    private static function toV11(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS material_hazards (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                material_id INTEGER NOT NULL REFERENCES materials(id) ON DELETE CASCADE,
                hazard_id   INTEGER NOT NULL REFERENCES hazards(id),
                created_at  TEXT NOT NULL,
                UNIQUE (material_id, hazard_id)
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_material_hazards_hazard ON material_hazards(hazard_id)');

        // 若存在 hazard_id 列，则把单一关联迁入关联表后清空
        if (self::hasColumn($pdo, 'materials', 'hazard_id')) {
            $moved = 0;
            $pdo->beginTransaction();
            try {
                $now = date('Y-m-d H:i:s');
                $pdo->exec(
                    "INSERT OR IGNORE INTO material_hazards (material_id, hazard_id, created_at)
                     SELECT id, hazard_id, '{$now}' FROM materials
                     WHERE hazard_id IS NOT NULL"
                );
                $moved = (int)$pdo->query('SELECT changes()')->fetchColumn();
                $pdo->exec('UPDATE materials SET hazard_id = NULL WHERE hazard_id IS NOT NULL');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            if ($moved > 0) {
                @error_log("[OHAS migrate] v11 表4-1 危害因素多选：迁入 {$moved} 条既有单一关联");
            }
        }
    }

    /**
     * 表2-6 / 表2-7 × 危害因素使用关联表：建关联表、迁入单值列、清空单值列。
     * 每一步都幂等：CREATE IF NOT EXISTS、INSERT OR IGNORE（有唯一约束）、单值列不存在则跳过。
     */
    private static function toV13(PDO $pdo): void
    {
        $defs = [
            // 关联表, 外键列, 主表
            ['ppe_issue_hazards',    'ppe_issue_id',    'ppe_issues'],
            ['warning_sign_hazards', 'warning_sign_id', 'warning_signs'],
        ];
        foreach ($defs as [$link, $fk, $main]) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS {$link} (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    {$fk}      INTEGER NOT NULL REFERENCES {$main}(id) ON DELETE CASCADE,
                    hazard_id  INTEGER NOT NULL REFERENCES hazards(id),
                    created_at TEXT NOT NULL,
                    UNIQUE ({$fk}, hazard_id)
                )"
            );
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$link}_hazard ON {$link}(hazard_id)");

            if (!self::hasColumn($pdo, $main, 'hazard_id')) {
                continue;   // 单值列不存在则跳过
            }
            $moved = 0;
            $pdo->beginTransaction();
            try {
                $now = date('Y-m-d H:i:s');
                $pdo->exec(
                    "INSERT OR IGNORE INTO {$link} ({$fk}, hazard_id, created_at)
                     SELECT id, hazard_id, '{$now}' FROM {$main}
                     WHERE hazard_id IS NOT NULL"
                );
                $moved = (int)$pdo->query('SELECT changes()')->fetchColumn();
                $pdo->exec("UPDATE {$main} SET hazard_id = NULL WHERE hazard_id IS NOT NULL");
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            if ($moved > 0) {
                @error_log("[OHAS migrate] v13 {$main} 危害因素多选：迁入 {$moved} 条既有单一关联");
            }
        }
    }

    /**
     * 为“先查后插”无约束的三处补唯一索引，防止并发/双开产生重复数据：
     * - post_hazards(post_id, hazard_id)：先清理既有重复（保留最早一条）再建索引；
     * - detection_reports(report_no)：存在重复数据时告警跳过（保留业务数据，不静默失败）；
     * - posts(workplace_id, name)：同上。
     */
    private static function toV14(PDO $pdo): void
    {
        $pdo->beginTransaction();
        try {
            // post_hazards：重复行无业务含义，直接去重（保留最早一条）
            $pdo->exec(
                'DELETE FROM post_hazards WHERE id NOT IN (
                    SELECT MIN(id) FROM post_hazards GROUP BY post_id, hazard_id
                 )'
            );
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_post_hazards_post_hazard ON post_hazards(post_id, hazard_id)');

            // detection_reports.report_no：无重复时建唯一索引；有重复则显式告警并跳过（不删业务数据）
            $dupReports = (int)$pdo->query(
                'SELECT COUNT(*) FROM (
                    SELECT report_no FROM detection_reports GROUP BY report_no HAVING COUNT(*) > 1
                 )'
            )->fetchColumn();
            if ($dupReports > 0) {
                @error_log("[OHAS migrate] v14：detection_reports 存在 {$dupReports} 组重复报告编号，暂不建唯一索引；请在系统中修正后重新启动");
            } else {
                $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_detection_reports_no ON detection_reports(report_no)');
            }

            // posts(name, workplace_id)：同上
            $dupPosts = (int)$pdo->query(
                'SELECT COUNT(*) FROM (
                    SELECT workplace_id, name FROM posts GROUP BY workplace_id, name HAVING COUNT(*) > 1
                 )'
            )->fetchColumn();
            if ($dupPosts > 0) {
                @error_log("[OHAS migrate] v14：posts 存在 {$dupPosts} 组同场所同名岗位，暂不建唯一索引；请在系统中修正后重新启动");
            } else {
                $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_posts_workplace_name ON posts(workplace_id, name)');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * 资料归档结构：
     * - documents 的 doc_type / period_year / original_name / file_hash 列与 idx_documents_type 索引；
     * - workers.duty_role（职务角色，逗号分隔）；
     * - archive_marks（“不适用”标记，单位级与个人级共用）；
     * - disease 与台账动态类 link_type 的文档解除关联触发器；
     * - 回填只处理无歧义项，不动 file_path / link_*：
     *   exam_record→W_EXAM、exam_batch→U_EXAM_SUMMARY、detection_report→U_DETECT_REPORT（年度取对应日期年份）；
     *   category“管理制度与操作规程”且未分类→U_RULES；category“应急预案”且未分类→U_EMERGENCY_PLAN；
     *   全部文档 original_name = title。
     * 所有步骤幂等：加列前 hasColumn、建表/索引用 IF NOT EXISTS、回填带条件。
     */
    private static function toV15(PDO $pdo): void
    {
        // 回填会改写文档行，先留一份数据库副本
        self::backup($pdo, 'v15');

        // ---- 结构变更（每条幂等） ----
        if (!self::hasColumn($pdo, 'documents', 'doc_type')) {
            $pdo->exec("ALTER TABLE documents ADD COLUMN doc_type TEXT NOT NULL DEFAULT ''");
        }
        if (!self::hasColumn($pdo, 'documents', 'period_year')) {
            $pdo->exec('ALTER TABLE documents ADD COLUMN period_year INTEGER DEFAULT NULL');
        }
        if (!self::hasColumn($pdo, 'documents', 'original_name')) {
            $pdo->exec("ALTER TABLE documents ADD COLUMN original_name TEXT NOT NULL DEFAULT ''");
        }
        if (!self::hasColumn($pdo, 'documents', 'file_hash')) {
            $pdo->exec("ALTER TABLE documents ADD COLUMN file_hash TEXT NOT NULL DEFAULT ''");
        }
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_documents_type ON documents(doc_type, period_year)');

        if (!self::hasColumn($pdo, 'workers', 'duty_role')) {
            $pdo->exec("ALTER TABLE workers ADD COLUMN duty_role TEXT NOT NULL DEFAULT ''");
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS archive_marks (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                scope       TEXT    NOT NULL CHECK (scope IN ('unit','worker')),
                item_code   TEXT    NOT NULL,
                worker_id   INTEGER DEFAULT NULL REFERENCES workers(id) ON DELETE CASCADE,
                period_year INTEGER DEFAULT NULL,
                reason      TEXT    NOT NULL,
                created_at  TEXT    NOT NULL,
                updated_at  TEXT    NOT NULL
            )"
        );
        $pdo->exec(
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_archive_marks
             ON archive_marks(scope, item_code, COALESCE(worker_id,0), COALESCE(period_year,0))'
        );

        // 为动态 link_type 补建解除关联触发器（幂等）
        foreach (self::linkTables() as $type => $table) {
            self::createDocUnlinkTrigger($pdo, $type, $table);
        }

        // ---- 回填（事务内；重复执行不产生变化） ----
        $pdo->beginTransaction();
        try {
            // 体检记录原件 → W_EXAM，年度 = 检查日期年份
            $pdo->exec(
                "UPDATE documents SET doc_type = 'W_EXAM',
                        period_year = COALESCE(period_year,
                            NULLIF(CAST(substr((SELECT r.exam_date FROM exam_records r WHERE r.id = documents.link_id),1,4) AS INTEGER),0))
                 WHERE link_type = 'exam_record' AND COALESCE(doc_type,'') = ''"
            );
            // 体检批次报告 → U_EXAM_SUMMARY
            $pdo->exec(
                "UPDATE documents SET doc_type = 'U_EXAM_SUMMARY',
                        period_year = COALESCE(period_year,
                            NULLIF(CAST(substr((SELECT b.exam_date FROM exam_batches b WHERE b.id = documents.link_id),1,4) AS INTEGER),0))
                 WHERE link_type = 'exam_batch' AND COALESCE(doc_type,'') = ''"
            );
            // 检测报告原件 → U_DETECT_REPORT
            $pdo->exec(
                "UPDATE documents SET doc_type = 'U_DETECT_REPORT',
                        period_year = COALESCE(period_year,
                            NULLIF(CAST(substr((SELECT dr.test_date FROM detection_reports dr WHERE dr.id = documents.link_id),1,4) AS INTEGER),0))
                 WHERE link_type = 'detection_report' AND COALESCE(doc_type,'') = ''"
            );
            // 按 category 回填两类制度文件
            $pdo->exec(
                "UPDATE documents SET doc_type = 'U_RULES'
                 WHERE category = '管理制度与操作规程' AND COALESCE(doc_type,'') = ''"
            );
            $pdo->exec(
                "UPDATE documents SET doc_type = 'U_EMERGENCY_PLAN'
                 WHERE category = '应急预案' AND COALESCE(doc_type,'') = ''"
            );
            // 全部文档回填 original_name = title（无更好来源）
            $pdo->exec("UPDATE documents SET original_name = title WHERE COALESCE(original_name,'') = ''");

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * 三张系统扩充台账：应急演练、应急设施、超标点整改。
     * 只建表建索引并补建附件解除关联触发器，不回填数据；全部步骤幂等。
     */
    private static function toV16(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drills (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                drill_date   TEXT DEFAULT '',
                name         TEXT NOT NULL,
                workplace_id INTEGER DEFAULT NULL REFERENCES workplaces(id),
                participants INTEGER NOT NULL DEFAULT 0,
                organizer    TEXT DEFAULT '',
                summary      TEXT DEFAULT '',
                remark       TEXT DEFAULT '',
                created_at   TEXT NOT NULL,
                updated_at   TEXT NOT NULL
            )"
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_drills_workplace ON drills(workplace_id)');

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS emergency_facilities (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                name         TEXT NOT NULL,
                model        TEXT DEFAULT '',
                workplace_id INTEGER DEFAULT NULL REFERENCES workplaces(id),
                quantity     TEXT DEFAULT '',
                location     TEXT DEFAULT '',
                check_date   TEXT DEFAULT '',
                status       TEXT NOT NULL DEFAULT '在用' CHECK (status IN ('在用','停用','报废')),
                remark       TEXT DEFAULT '',
                created_at   TEXT NOT NULL,
                updated_at   TEXT NOT NULL
            )"
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_emergency_facilities_workplace ON emergency_facilities(workplace_id)');

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS rectifications (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                report_id        INTEGER DEFAULT NULL REFERENCES detection_reports(id),
                item_desc        TEXT NOT NULL,
                plan             TEXT DEFAULT '',
                done_date        TEXT DEFAULT '',
                verify_report_id INTEGER DEFAULT NULL REFERENCES detection_reports(id),
                status           TEXT NOT NULL DEFAULT '整改中' CHECK (status IN ('整改中','已完成')),
                remark           TEXT DEFAULT '',
                created_at       TEXT NOT NULL,
                updated_at       TEXT NOT NULL
            )"
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rectifications_report ON rectifications(report_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rectifications_verify ON rectifications(verify_report_id)');

        // 建表后再建附件解除关联触发器，确保不会被跳过。
        foreach (['ledger_drills' => 'drills', 'ledger_emergency_facilities' => 'emergency_facilities', 'ledger_rectifications' => 'rectifications'] as $type => $table) {
            self::createDocUnlinkTrigger($pdo, $type, $table);
        }
    }

    /** 内部编制资料在线内容表（六类档案目录项在线编辑；外部报告仍走 documents 附件）。 */
    private static function toV17(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS archive_materials (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                group_key   TEXT NOT NULL,
                filing_seq  TEXT NOT NULL,
                doc_type    TEXT NOT NULL DEFAULT '',
                title       TEXT NOT NULL,
                doc_no      TEXT NOT NULL DEFAULT '',
                issuer      TEXT NOT NULL DEFAULT '',
                doc_date    TEXT NOT NULL DEFAULT '',
                body        TEXT NOT NULL DEFAULT '',
                created_at  TEXT NOT NULL,
                updated_at  TEXT NOT NULL,
                UNIQUE (group_key, filing_seq)
            )"
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_archive_materials_group ON archive_materials(group_key)');
        self::createDocUnlinkTrigger($pdo, 'archive_material', 'archive_materials');
    }

    /** 体检结果书面告知方式留痕（告知书原件以 W_EXAM_NOTICE 挂在 exam_record 上）。 */
    private static function toV18(PDO $pdo): void
    {
        // 幂等：schema.sql 已含该列时跳过，避免 duplicate column。
        if (!self::hasColumn($pdo, 'exam_records', 'notice_method')) {
            $pdo->exec("ALTER TABLE exam_records ADD COLUMN notice_method TEXT NOT NULL DEFAULT ''");
        }
    }

    /**
     * worker_posts.end_date 的空串规整为 NULL。
     * 全站“当前岗位 / 受检时岗位快照 / 岗位筛选”等查询按 NULL 判在岗，空串会让它们取不到在岗段，
     * 体检快照甚至会回落到更早的已结束岗位；写入侧使用 NULL，这里只清洗存量空串。
     * 幂等：只更新空串/纯空白，无则不动。
     */
    private static function toV19(PDO $pdo): void
    {
        $pdo->exec("UPDATE worker_posts SET end_date = NULL WHERE end_date IS NOT NULL AND TRIM(end_date) = ''");
    }

    /**
     * workers.leave_date（离岗/离职/退休日期）。
     * 非在职人员必须带离岗日期，并据此在同一事务收尾所有未结束职业史段，
     * 否则接害工龄、检测归集、个人年度矩阵都会把已离岗人员算成“持续至今接害”。
     * 幂等：schema.sql 已含该列时跳过；补列后不回填，离岗日期由用户确认。
     */
    private static function toV20(PDO $pdo): void
    {
        if (!self::hasColumn($pdo, 'workers', 'leave_date')) {
            $pdo->exec("ALTER TABLE workers ADD COLUMN leave_date TEXT NOT NULL DEFAULT ''");
        }
    }

    /**
     * 检测明细的采样类型与归集岗位快照。
     * sample_type 区分 TWA/STEL/MAC/峰，避免短时结果与时间加权限值错配；
     * matched_post_ids 在保存时冻结可归集岗位，后续改岗位危害配置不会漂移到历史检测。
     * 存量明细留空时继续按当前配置兜底，不回填。
     */
    private static function toV21(PDO $pdo): void
    {
        if (!self::hasColumn($pdo, 'detection_items', 'sample_type')) {
            $pdo->exec("ALTER TABLE detection_items ADD COLUMN sample_type TEXT NOT NULL DEFAULT ''");
        }
        if (!self::hasColumn($pdo, 'detection_items', 'matched_post_ids')) {
            $pdo->exec("ALTER TABLE detection_items ADD COLUMN matched_post_ids TEXT NOT NULL DEFAULT ''");
        }
    }

    /** 迁移前备份最多保留份数（其余按时间从旧到新清理，避免每次升级 / 并发各留一份而无限堆积） */
    private const BACKUP_KEEP = 5;

    private static function backup(PDO $pdo, string $label = 'v4'): void
    {
        try {
            $src = (string)cfg('db.path');
            // 备份目录跟随数据库所在目录，临时库/多实例各自隔离。
            $dir = dirname($src) . '/backups';
            if (!is_file($src) || !(is_dir($dir) || @mkdir($dir, 0775, true))) {
                return;
            }
            $dest = $dir . '/pre-migrate-' . $label . '-' . date('Ymd-His') . '.db';
            $ok = false;
            try {
                // VACUUM INTO 生成一致性快照（SQLite ≥ 3.27）；目标已存在时会报错，先清掉
                @unlink($dest);
                $pdo->exec("VACUUM INTO '" . str_replace("'", "''", $dest) . "'");
                $ok = is_file($dest) && filesize($dest) > 0;
            } catch (Throwable $e) {
                $ok = false;
            }
            if (!$ok) {
                // 低版本 SQLite 的兼容回退路径
                $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
                @copy($src, $dest);
            }
            self::pruneBackups($dir);
        } catch (Throwable $e) {
            // 备份失败不阻断迁移
        }
    }

    private static function pruneBackups(string $dir): void
    {
        $files = glob($dir . '/pre-migrate-*.db') ?: [];
        if (count($files) <= self::BACKUP_KEEP) {
            return;
        }
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));   // 新 → 旧
        foreach (array_slice($files, self::BACKUP_KEEP) as $old) {
            @unlink($old);
        }
    }
}
