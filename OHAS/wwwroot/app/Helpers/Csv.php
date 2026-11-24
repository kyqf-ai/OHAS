<?php
/**
 * CSV 工具：编码识别（UTF-8 BOM / UTF-8 / GBK）、分隔符识别、解析与模板生成。
 * 兼容 Excel 中文环境导出的 GBK 编码 CSV。
 */
declare(strict_types=1);

namespace App\Helpers;

class Csv
{
    /**
     * 将上传的 CSV 原文统一转为 UTF-8 文本。
     */
    public static function normalize(string $raw): string
    {
        // 去 UTF-8 BOM
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            return substr($raw, 3);
        }
        // UTF-16 LE/BE BOM（Excel「Unicode 文本」）
        if (strncmp($raw, "\xFF\xFE", 2) === 0 || strncmp($raw, "\xFE\xFF", 2) === 0) {
            $conv = @iconv('UTF-16', 'UTF-8', $raw);
            if ($conv !== false) {
                return $conv;
            }
        }
        // 本身就是合法 UTF-8
        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }
        // 否则按 GB18030 转换（中文 Excel 默认的 GBK 的超集，生僻字 / 扩展字不再丢失；
        // 使用 GB18030 转换，避免 GBK//IGNORE 静默丢弃生僻字导致姓名缺字
        if (function_exists('mb_convert_encoding')) {
            $conv = @mb_convert_encoding($raw, 'UTF-8', 'GB18030');
            if (is_string($conv) && $conv !== '') {
                return $conv;
            }
        }
        $conv = @iconv('GB18030', 'UTF-8//IGNORE', $raw);
        return $conv === false ? $raw : $conv;
    }

    /**
     * 解析为二维数组（第一行为表头）。
     * 自动识别逗号 / 分号 / 制表符。
     */
    public static function parse(string $text): array
    {
        $text = ltrim(str_replace(["\r\n", "\r"], "\n", $text), "\n");
        if (trim($text) === '') {
            return [];
        }
        $nl = strpos($text, "\n");
        $delim = self::detectDelimiter($nl === false ? $text : substr($text, 0, $nl));
        // 用流式 fgetcsv 解析：带引号的单元格内可含换行（Excel Alt+Enter 的备注）
        $fp = fopen('php://temp', 'r+');
        fwrite($fp, $text);
        rewind($fp);
        $rows = [];
        while (($cells = fgetcsv($fp, 0, $delim, '"', '')) !== false) {
            if ($cells === [null] || (count($cells) === 1 && trim((string)$cells[0]) === '')) {
                continue;   // 空行
            }
            $rows[] = array_map(fn ($c) => trim((string)$c), $cells);
        }
        fclose($fp);
        return $rows;
    }

    private static function detectDelimiter(string $line): string
    {
        $counts = [
            ','  => substr_count($line, ','),
            ';'  => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];
        arsort($counts);
        $best = array_key_first($counts);
        return $counts[$best] > 0 ? $best : ',';
    }

    /**
     * 生成模板内容（UTF-8 带 BOM，Excel 直接打开不乱码）。
     */
    public static function template(array $headers, array $sampleRow = []): string
    {
        $out = "\xEF\xBB\xBF";
        $out .= implode(',', array_map([self::class, 'cell'], $headers)) . "\r\n";
        if ($sampleRow) {
            $out .= implode(',', array_map([self::class, 'cell'], $sampleRow)) . "\r\n";
        }
        return $out;
    }

    public static function cell(string $v): string
    {
        // CSV / Excel 公式注入防护：以 = + - @ 制表符 回车 开头的单元格前补单引号，使其按文本显示
        // （导出处各自再做一遍的写法并存时不会重复添加：补过引号的值不再以这些字符开头）
        if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) {
            $v = "'" . $v;
        }
        if (preg_match('/[",\r\n]/', $v)) {
            return '"' . str_replace('"', '""', $v) . '"';
        }
        return $v;
    }
}
