<?php
/**
 * 简化种子清单，仅供迁移识别并清理未被引用的残留，请勿删除。
 * 安装与新数据同步均使用 HazardCatalog（《职业病危害因素分类目录》459 条完整版）。
 * 粉尘/化学因素限值取通行值，依据 GBZ 2.1-2019（含第2号修改单 GBZ 2.1-2019/XG2-2024）；
 * 物理因素依据 GBZ 2.2-2007。
 */
declare(strict_types=1);

namespace App\Helpers;

class HazardSeed
{
    /** @return array<array{name:string,category:string,possible_disease:string,limit_note:string}> */
    public static function list(): array
    {
        return [
            // 粉尘
            ['name' => '矽尘（游离二氧化硅粉尘）', 'category' => '粉尘', 'possible_disease' => '矽肺', 'limit_note' => '总尘PC-TWA 1 mg/m³（GBZ 2.1-2019）'],
            ['name' => '煤尘', 'category' => '粉尘', 'possible_disease' => '煤工尘肺', 'limit_note' => '总尘PC-TWA 4 mg/m³'],
            ['name' => '电焊烟尘', 'category' => '粉尘', 'possible_disease' => '电焊工尘肺', 'limit_note' => '总尘PC-TWA 4 mg/m³'],
            ['name' => '水泥粉尘', 'category' => '粉尘', 'possible_disease' => '水泥尘肺', 'limit_note' => '总尘PC-TWA 4 mg/m³'],
            ['name' => '石棉粉尘', 'category' => '粉尘', 'possible_disease' => '石棉肺、胸膜间皮瘤', 'limit_note' => '总尘PC-TWA 0.8 mg/m³'],
            ['name' => '木粉尘', 'category' => '粉尘', 'possible_disease' => '呼吸道刺激、过敏性皮炎', 'limit_note' => '总尘PC-TWA 3 mg/m³'],
            ['name' => '棉尘', 'category' => '粉尘', 'possible_disease' => '棉尘病', 'limit_note' => '总尘PC-TWA 1 mg/m³'],
            ['name' => '砂轮磨尘（其他粉尘）', 'category' => '粉尘', 'possible_disease' => '其他尘肺', 'limit_note' => '其他粉尘PC-TWA 8 mg/m³'],
            // 化学因素
            ['name' => '苯', 'category' => '化学因素', 'possible_disease' => '苯中毒、白血病', 'limit_note' => 'PC-TWA 6 mg/m³'],
            ['name' => '甲苯', 'category' => '化学因素', 'possible_disease' => '甲苯中毒', 'limit_note' => 'PC-TWA 50 mg/m³'],
            ['name' => '二甲苯', 'category' => '化学因素', 'possible_disease' => '二甲苯中毒', 'limit_note' => 'PC-TWA 100 mg/m³'],
            ['name' => '一氧化碳', 'category' => '化学因素', 'possible_disease' => '一氧化碳中毒', 'limit_note' => 'PC-TWA 20 mg/m³'],
            ['name' => '硫化氢', 'category' => '化学因素', 'possible_disease' => '硫化氢中毒', 'limit_note' => 'MAC 10 mg/m³'],
            ['name' => '氨', 'category' => '化学因素', 'possible_disease' => '氨中毒、化学性眼灼伤', 'limit_note' => 'PC-TWA 20，PC-STEL 30 mg/m³'],
            ['name' => '氯气', 'category' => '化学因素', 'possible_disease' => '氯气中毒', 'limit_note' => 'MAC 1 mg/m³'],
            ['name' => '甲醛', 'category' => '化学因素', 'possible_disease' => '甲醛中毒', 'limit_note' => 'MAC 0.5 mg/m³'],
            ['name' => '硫酸酸雾', 'category' => '化学因素', 'possible_disease' => '牙酸蚀病、皮肤灼伤', 'limit_note' => 'PC-TWA 1 mg/m³'],
            ['name' => '盐酸酸雾', 'category' => '化学因素', 'possible_disease' => '牙酸蚀病', 'limit_note' => 'MAC 7.5 mg/m³'],
            ['name' => '氢氧化钠', 'category' => '化学因素', 'possible_disease' => '化学性皮肤灼伤、眼灼伤', 'limit_note' => 'MAC 2 mg/m³'],
            ['name' => '锰及其化合物', 'category' => '化学因素', 'possible_disease' => '锰中毒', 'limit_note' => 'PC-TWA 0.15 mg/m³'],
            ['name' => '铅及其无机化合物', 'category' => '化学因素', 'possible_disease' => '铅中毒', 'limit_note' => 'PC-TWA 0.03 mg/m³（铅烟）'],
            ['name' => '铬及其化合物', 'category' => '化学因素', 'possible_disease' => '铬鼻病、皮肤溃疡', 'limit_note' => '三价铬PC-TWA 0.5 mg/m³'],
            ['name' => '汞及其化合物', 'category' => '化学因素', 'possible_disease' => '汞中毒', 'limit_note' => '金属汞PC-TWA 0.02 mg/m³'],
            ['name' => '正己烷', 'category' => '化学因素', 'possible_disease' => '正己烷中毒（周围神经损害）', 'limit_note' => 'PC-TWA 100 mg/m³'],
            ['name' => '三氯乙烯', 'category' => '化学因素', 'possible_disease' => '三氯乙烯中毒、药疹样皮炎', 'limit_note' => 'PC-TWA 30 mg/m³'],
            ['name' => '汽油', 'category' => '化学因素', 'possible_disease' => '汽油中毒', 'limit_note' => 'PC-TWA 300 mg/m³'],
            // 物理因素
            ['name' => '噪声', 'category' => '物理因素', 'possible_disease' => '职业性噪声聋', 'limit_note' => '85 dB(A)（8h等效声级，GBZ 2.2-2007）'],
            ['name' => '高温', 'category' => '物理因素', 'possible_disease' => '职业性中暑', 'limit_note' => 'WBGT限值按接触时间率查表（GBZ 2.2-2007）'],
            ['name' => '紫外辐射', 'category' => '物理因素', 'possible_disease' => '电光性眼炎、电光性皮炎', 'limit_note' => '电焊弧光按GBZ 2.2-2007'],
            ['name' => '工频电磁场', 'category' => '物理因素', 'possible_disease' => '—', 'limit_note' => '5 kV/m（工频电场，GBZ 2.2-2007）'],
            ['name' => '手传振动', 'category' => '物理因素', 'possible_disease' => '手臂振动病', 'limit_note' => '4 h等能量频率计权加速度 5 m/s²（GBZ 2.2-2007）'],
            // 生物因素
            ['name' => '布鲁氏菌', 'category' => '生物因素', 'possible_disease' => '职业性布鲁氏菌病', 'limit_note' => '—'],
            ['name' => '炭疽杆菌', 'category' => '生物因素', 'possible_disease' => '职业性炭疽', 'limit_note' => '—'],
            // 放射性因素
            ['name' => '电离辐射（X、γ射线）', 'category' => '放射性因素', 'possible_disease' => '放射性肿瘤、外照射放射病', 'limit_note' => '按GB 18871职业照射剂量限值'],
        ];
    }
}
