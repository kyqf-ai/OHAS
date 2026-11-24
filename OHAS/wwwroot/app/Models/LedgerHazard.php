<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * 台账 × 危害因素 多对多关联（通用）。
 *
 * 同一台设备/材料/化学品（表4-1）、同一件防护用品（表2-6）、同一块警示标识（表2-7）
 * 往往对应多种危害因素，三张台账共用这一套读写逻辑，各自落在独立的关联表：
 *
 *   materials     → material_hazards     (material_id)
 *   ppe_issues    → ppe_issue_hazards    (ppe_issue_id)
 *   warning_signs → warning_sign_hazards (warning_sign_id)
 *
 * 表名、列名全部来自下面的白名单常量，不接受外部传入，不存在 SQL 注入面。
 */
class LedgerHazard
{
    /** 每条记录最多可关联的危害因素数量（打印列宽与可读性上限） */
    public const MAX_HAZARDS = 12;

    /** 台账表 => [关联表, 外键列] */
    private const LINKS = [
        'materials'     => ['material_hazards',     'material_id'],
        'ppe_issues'    => ['ppe_issue_hazards',    'ppe_issue_id'],
        'warning_signs' => ['warning_sign_hazards', 'warning_sign_id'],
    ];

    /** 该台账表是否支持危害因素多选 */
    public static function supports(string $table): bool
    {
        return isset(self::LINKS[$table]);
    }

    /** @return array{0:string,1:string} [关联表, 外键列] */
    private static function link(string $table): array
    {
        if (!isset(self::LINKS[$table])) {
            throw new \InvalidArgumentException('不支持危害因素多选的台账表：' . $table);
        }
        return self::LINKS[$table];
    }

    /**
     * 批量取危害因素：记录id => [['id'=>, 'name'=>, 'status'=>], ...]
     * 按字典类别、名称排序（与危害因素字典顺序一致）；无关联的 id 也补空数组。
     */
    public static function hazardsBy(string $table, array $recordIds): array
    {
        [$link, $fk] = self::link($table);
        $ids = array_values(array_unique(array_map('intval', $recordIds)));
        $out = [];
        if (!$ids) {
            return $out;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::rows(
            "SELECT l.{$fk} AS rid, h.id, h.name, h.status
             FROM {$link} l JOIN hazards h ON h.id = l.hazard_id
             WHERE l.{$fk} IN ({$ph})
             ORDER BY l.{$fk}, h.category, h.name",
            $ids
        );
        foreach ($rows as $r) {
            $out[(int)$r['rid']][] = [
                'id'     => (int)$r['id'],
                'name'   => (string)$r['name'],
                'status' => (string)$r['status'],
            ];
        }
        foreach ($ids as $id) {
            if (!isset($out[$id])) {
                $out[$id] = [];
            }
        }
        return $out;
    }

    /** 批量取名称串：记录id => '噪声、振动'（无关联为 ''）。打印表样用 */
    public static function namesBy(string $table, array $recordIds): array
    {
        $out = [];
        foreach (self::hazardsBy($table, $recordIds) as $rid => $list) {
            $out[$rid] = implode('、', array_column($list, 'name'));
        }
        return $out;
    }

    /** 单条记录已关联的危害因素 id 列表 */
    public static function idsOf(string $table, int $recordId): array
    {
        return array_column(self::hazardsBy($table, [$recordId])[$recordId] ?? [], 'id');
    }

    /**
     * 覆盖式保存关联：先删后插，批量 INSERT。
     * 会对 id 去重（关联表有 UNIQUE(记录, 危害因素)，重复 id 会令整个事务回滚）。
     * 调用方需已处于事务内（LedgerController 用 Database::tx 包住主表与关联表）。
     */
    public static function save(string $table, int $recordId, array $hazardIds): void
    {
        [$link, $fk] = self::link($table);
        Database::run("DELETE FROM {$link} WHERE {$fk} = ?", [$recordId]);

        $ids = [];
        foreach ($hazardIds as $hid) {
            $hid = (int)$hid;
            if ($hid > 0) {
                $ids[$hid] = true;
            }
        }
        $ids = array_keys($ids);
        if (!$ids) {
            return;
        }

        // 每批最多 200 行 × 3 个参数 = 600 个占位符，低于 SQLite 旧版本 999 的上限
        $now = now_str();
        foreach (array_chunk($ids, 200) as $chunk) {
            $params = [];
            foreach ($chunk as $hid) {
                array_push($params, $recordId, $hid, $now);
            }
            Database::run(
                "INSERT INTO {$link} ({$fk}, hazard_id, created_at) VALUES "
                    . implode(',', array_fill(0, count($chunk), '(?,?,?)')),
                $params
            );
        }
    }

    /**
     * 列表搜索用的 EXISTS 子句：按危害因素名称也能搜到该台账记录。
     * 含一个 ? 占位符（LIKE 的值）。
     */
    public static function searchExists(string $table): string
    {
        [$link, $fk] = self::link($table);
        return "EXISTS (SELECT 1 FROM {$link} l JOIN hazards h ON h.id = l.hazard_id"
            . " WHERE l.{$fk} = {$table}.id AND h.name LIKE ?)";
    }

    /** 某个危害因素被三张台账引用的次数（危害因素字典删除保护 / 引用统计用） */
    public static function usageCount(int $hazardId): int
    {
        $n = 0;
        foreach (self::LINKS as [$link]) {
            $n += (int)Database::value("SELECT COUNT(*) FROM {$link} WHERE hazard_id = ?", [$hazardId]);
        }
        return $n;
    }
}
