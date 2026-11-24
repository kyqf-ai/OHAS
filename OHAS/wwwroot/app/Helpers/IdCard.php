<?php
/**
 * 居民身份证号工具：18 位校验（GB 11643）、出生日期与性别推导。
 */
declare(strict_types=1);

namespace App\Helpers;

class IdCard
{
    private const WEIGHT = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
    private const CODES  = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];

    public static function valid(string $id): bool
    {
        $id = strtoupper(trim($id));
        if (!preg_match('/^\d{17}[\dX]$/', $id)) {
            return false;
        }
        // 出生日期段合法性
        $y = (int)substr($id, 6, 4);
        $m = (int)substr($id, 10, 2);
        $d = (int)substr($id, 12, 2);
        // 出生日期须是真实日期、不早于 1900 年且不晚于今天
        if (!checkdate($m, $d, $y) || $y < 1900 || sprintf('%04d-%02d-%02d', $y, $m, $d) > date('Y-m-d')) {
            return false;
        }
        // 校验码
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += (int)$id[$i] * self::WEIGHT[$i];
        }
        return self::CODES[$sum % 11] === $id[17];
    }

    /** 由身份证号推导出生日期（YYYY-MM-DD），无效返回 null */
    public static function birth(string $id): ?string
    {
        $id = strtoupper(trim($id));
        if (!preg_match('/^\d{17}[\dX]$/', $id)) {
            return null;
        }
        return substr($id, 6, 4) . '-' . substr($id, 10, 2) . '-' . substr($id, 12, 2);
    }

    /** 由身份证号推导性别（顺序码奇男偶女），无效返回 null */
    public static function gender(string $id): ?string
    {
        $id = strtoupper(trim($id));
        if (!preg_match('/^\d{17}[\dX]$/', $id)) {
            return null;
        }
        return ((int)$id[16]) % 2 === 1 ? '男' : '女';
    }
}
