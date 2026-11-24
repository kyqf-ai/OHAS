<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * 设备材料化学品（表4-1）。
 * 危害因素为多对多关联（material_hazards）：同一设备/材料/化学品可能产生多种危害因素。
 * 读写逻辑已与表2-6、表2-7 共用 LedgerHazard，这里保留原有方法作为薄封装，调用方无需改动。
 */
class Material extends Model
{
    protected static string $table = 'materials';

    /** 最多可关联的危害因素数量（表4-1 打印列宽与可读性上限） */
    public const MAX_HAZARDS = LedgerHazard::MAX_HAZARDS;

    /** 批量取危害因素：material_id => [['id'=>, 'name'=>, 'status'=>], ...] */
    public static function hazardsBy(array $materialIds): array
    {
        return LedgerHazard::hazardsBy('materials', $materialIds);
    }

    /** 单条记录的危害因素名称串（「、」分隔；无关联返回 ''） */
    public static function hazardNames(int $materialId): string
    {
        return LedgerHazard::namesBy('materials', [$materialId])[$materialId] ?? '';
    }

    /** 单条记录已关联的危害因素 id 列表 */
    public static function hazardIds(int $materialId): array
    {
        return LedgerHazard::idsOf('materials', $materialId);
    }

    /** 覆盖式保存危害因素关联（批量插入，调用方需已在事务内） */
    public static function saveHazards(int $materialId, array $hazardIds): void
    {
        LedgerHazard::save('materials', $materialId, $hazardIds);
    }
}
