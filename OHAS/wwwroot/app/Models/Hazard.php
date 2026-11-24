<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Helpers\HazardCatalog;

class Hazard extends Model
{
    protected static string $table = 'hazards';

    /** 启用的危害因素（供下拉选择），按目录类别顺序与目录序号排列 */
    public static function groupedActive(): array
    {
        $rows = Database::rows("SELECT * FROM hazards WHERE status = '启用' ORDER BY category, catalog_no IS NULL, catalog_no, name");
        $grouped = [];
        foreach (HazardCatalog::categories() as $category) {
            $grouped[$category] = [];
        }
        foreach ($rows as $r) {
            $grouped[$r['category']][] = $r;
        }
        return array_filter($grouped);   // 去掉空类别
    }

    /**
     * 同步《职业病危害因素分类目录》：按（名称+类别）新增或更新目录字段。
     * - 目录字段（序号/CAS/适用范围/来源）始终刷新为目录值；
     * - 可能职业病：空缺回填；系统内置文案（legacyDiseases）刷新为最新口径，用户维护值不覆盖；
     * - 接触限值：空缺回填；系统内置文案（legacyNotes）刷新为最新值，用户维护值不覆盖；
     * - 不删除用户自建条目，不覆盖状态与备注。
     * @return array{inserted:int, updated:int}
     */
    public static function syncCatalog(): array
    {
        $extras = HazardCatalog::extras();        // 名称 => 可能职业病
        $legacyDisease = HazardCatalog::legacyDiseases();   // 名称 => 系统内置可能职业病（可安全刷新）
        $oels = HazardCatalog::oels();            // 名称 => 接触限值摘要
        $legacy = HazardCatalog::legacyNotes();   // 系统内置限值文案（可安全刷新）
        $inserted = $updated = 0;
        foreach (HazardCatalog::items() as $item) {
            $row = Database::row(
                'SELECT id, possible_disease, limit_note FROM hazards WHERE name = ? AND category = ?',
                [$item['name'], $item['category']]
            );
            if ($row) {
                $disease = (string)($row['possible_disease'] ?? '');
                $newDisease = (string)($extras[$item['name']] ?? '');
                if ($disease === '' && $newDisease !== '') {
                    // 空缺回填
                    $disease = $newDisease;
                } elseif ($disease !== '' && $newDisease !== '' && $disease !== $newDisease
                    && ($legacyDisease[$item['name']] ?? null) === $disease) {
                    // 与系统内置文案一致 = 用户未改过 → 刷新为最新口径
                    $disease = $newDisease;
                }
                $note = (string)($row['limit_note'] ?? '');
                if ($note === '' || in_array($note, $legacy, true)) {
                    $note = $oels[$item['name']] ?? $note;
                }
                Database::run(
                    'UPDATE hazards SET catalog_no = ?, cas_no = ?, restriction = ?, possible_disease = ?, limit_note = ?, catalog_source = ?, updated_at = ? WHERE id = ?',
                    [$item['no'], $item['cas'], $item['note'], $disease, $note, HazardCatalog::SOURCE, now_str(), (int)$row['id']]
                );
                $updated++;
                continue;
            }
            Database::run(
                'INSERT INTO hazards (name, category, catalog_no, cas_no, restriction, possible_disease, limit_note, catalog_source, status, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $item['name'], $item['category'], $item['no'], $item['cas'], $item['note'],
                    $extras[$item['name']] ?? '', $oels[$item['name']] ?? '', HazardCatalog::SOURCE, '启用',
                    now_str(), now_str(),
                ]
            );
            $inserted++;
        }
        return ['inserted' => $inserted, 'updated' => $updated];
    }

    /** 是否被业务数据引用（岗位配置 / 检测明细） */
    public static function referenced(int $id): bool
    {
        return self::refCount($id) > 0;
    }

    public static function refCount(int $id): int
    {
        $n  = (int)Database::value('SELECT COUNT(*) FROM post_hazards WHERE hazard_id = ?', [$id]);
        $n += (int)Database::value('SELECT COUNT(*) FROM detection_items WHERE hazard_id = ?', [$id]);
        // 表4-1 设备材料化学品、表2-6 防护用品发放、表2-7 警示标识均为多对多关联，都计入引用统计
        $n += LedgerHazard::usageCount($id);
        return $n;
    }
}
