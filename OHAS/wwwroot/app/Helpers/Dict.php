<?php
/**
 * 基础代码字典（对应规划书表8），取值对齐样表注释与档案规范表述。
 */
declare(strict_types=1);

namespace App\Helpers;

class Dict
{
    /** 危害因素六类（顺序与《职业病危害因素分类目录》正文一致） */
    public static function hazardCategories(): array
    {
        return \App\Helpers\HazardCatalog::categories();
    }

    public static function exposureTypes(): array
    {
        return ['定点作业', '巡检作业'];
    }

    public static function workerStatus(): array
    {
        return ['在职', '离岗', '退休', '离职'];
    }

    public static function maritalStatus(): array
    {
        return ['未婚', '已婚', '离异', '丧偶', '再婚'];
    }

    public static function education(): array
    {
        return ['小学', '初中', '高中', '中专', '中技', '大专', '本科', '硕士及以上'];
    }

    public static function yesNo(): array
    {
        return ['启用', '停用'];
    }

    /** 检查种类（表6-3 样表注释；四类法定检查依据 GBZ 188-2025，应急健康检查自 2025 版起纳入） */
    public static function examTypes(): array
    {
        return ['上岗前', '在岗期间', '离岗时', '应急健康检查', '离岗后医学随访', '复查', '医学观察', '职业病诊断'];
    }

    /**
     * 检查结论（GBZ 188-2025 口径，2026-08-01 施行）：四类判定 + 职业病诊断结论；
     * 「复查」不再作为结论（由检查机构另行安排补充检查）；空值表示待出结论。
     */
    public static function examConclusions(): array
    {
        return ['目前未见异常', '疑似职业病', '职业禁忌证', '其他疾病或异常', '职业病'];
    }

    /** 检测报告类别（检测评价依据 GBZ 2.1-2019 等现行标准） */
    public static function detectionCategories(): array
    {
        return ['定期检测', '日常监测', '现状评价', '控制效果评价'];
    }

    /** 分类文档归档分类（共 13 类） */
    public static function documentCategories(): array
    {
        return ['法律法规', '管理制度与操作规程', '三同时资料', '申报材料', '现场照片', '检测评价报告', '体检报告', '劳动用工资料', '资质证书', '诊断证明', '应急预案', '培训资料', '其他'];
    }

    /** 下拉选项 HTML */
    public static function options(array $values, ?string $selected = null): string
    {
        $html = '<option value="">— 请选择 —</option>';
        foreach ($values as $v) {
            $sel = $selected !== null && $v === $selected ? ' selected' : '';
            $html .= '<option value="' . e($v) . '"' . $sel . '>' . e($v) . '</option>';
        }
        return $html;
    }
}
