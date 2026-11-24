<?php
/**
 * 台账的“场所 / 岗位”关联。
 *
 * 表4-1、表2-6、表2-7、表5-5 各有 workplace_id / post_id 关联列；
 * 文本列作为补充说明，用于下拉无对应项时手填。
 *
 * 台账配置里的 'place' 项：
 *   workplace  场所关联列名
 *   post       岗位关联列名（可为 null，如警示标识只关联场所）
 *   text       原文本列（列表、打印中与关联名称合并显示）
 *   post_text  岗位的原文本列（仅事故报告场所、岗位各有一列文本时使用）
 *   require    true = 场所与文本至少填一项（警示标识的"设置地点"）
 */
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

class LedgerPlace
{
    public static function config(array $cfg): ?array
    {
        return $cfg['place'] ?? null;
    }

    /** 场所、岗位名称对照：workplace / post（仅岗位名）/ post_full（场所 · 岗位）/ post_wp（岗位 => 场所id） */
    public static function names(): array
    {
        $out = ['workplace' => [], 'post' => [], 'post_full' => [], 'post_wp' => []];
        foreach (Database::rows('SELECT id, name FROM workplaces ORDER BY name ASC') as $r) {
            $out['workplace'][(string)$r['id']] = (string)$r['name'];
        }
        foreach (Database::rows(
            'SELECT p.id, p.name, p.workplace_id, w.name AS wname
             FROM posts p JOIN workplaces w ON w.id = p.workplace_id
             ORDER BY w.name ASC, p.name ASC'
        ) as $r) {
            $id = (string)$r['id'];
            $out['post'][$id] = (string)$r['name'];
            $out['post_full'][$id] = $r['wname'] . ' · ' . $r['name'];
            $out['post_wp'][$id] = (int)$r['workplace_id'];
        }
        return $out;
    }

    /** 去掉空白与常见分隔符，用于判断文本是否只是关联名称的重复 */
    public static function norm(string $s): string
    {
        return (string)preg_replace('/[\s\/／·•\-—–、，,;；（）()]+/u', '', $s);
    }

    /** 关联后的"场所 · 岗位"显示（未关联返回空串） */
    private static function linked(array $place, array $row, array $names): string
    {
        $wid = (string)($row[$place['workplace']] ?? '');
        $pid = !empty($place['post']) ? (string)($row[$place['post']] ?? '') : '';
        if ($pid !== '' && isset($names['post_full'][$pid])) {
            return $names['post_full'][$pid];
        }
        if ($wid !== '' && isset($names['workplace'][$wid])) {
            return $names['workplace'][$wid];
        }
        return '';
    }

    /**
     * 列表/打印中某一列的显示值。该列不属于场所岗位配置时返回 null，由调用方按原规则显示。
     */
    public static function display(array $cfg, array $row, string $col, array $names): ?string
    {
        $place = self::config($cfg);
        if ($place === null) {
            return null;
        }
        // 场所岗位列均为文本；非文本列（如表4-1 的多选危害因素数组）不在此处理
        if (!is_string($row[$col] ?? null)) {
            return null;
        }
        $text = trim((string)($row[$col] ?? ''));

        // 场所、岗位各有一列文本（事故报告）：分别显示
        if (!empty($place['post_text'])) {
            if ($col === $place['text']) {
                $wid = (string)($row[$place['workplace']] ?? '');
                return $wid !== '' && isset($names['workplace'][$wid]) ? $names['workplace'][$wid] : $text;
            }
            if ($col === $place['post_text']) {
                $pid = (string)($row[$place['post']] ?? '');
                return $pid !== '' && isset($names['post'][$pid]) ? $names['post'][$pid] : $text;
            }
            return null;
        }

        if ($col !== $place['text']) {
            return null;
        }
        $linked = self::linked($place, $row, $names);
        if ($linked === '') {
            return $text;
        }
        // 文本只是场所名、岗位名或两者之和（旧数据常见）时不再重复显示
        $wn = $names['workplace'][(string)($row[$place['workplace']] ?? '')] ?? '';
        $pn = !empty($place['post']) ? ($names['post'][(string)($row[$place['post']] ?? '')] ?? '') : '';
        $dups = array_filter([$linked, $wn, $pn, $wn . $pn]);
        foreach ($dups as $d) {
            if ($text === '' || self::norm($text) === self::norm($d)) {
                return $linked;
            }
        }
        return $linked . '（' . $text . '）';
    }

    /**
     * 校验并整理表单提交的场所/岗位。
     * 返回 [workplace_id|null, post_id|null, 错误信息数组]；
     * 只选了岗位时自动补上其所属场所；岗位与场所不匹配时报错。
     */
    public static function resolve(array $cfg, array $input, array $names): array
    {
        $place = self::config($cfg);
        $errors = [];
        $wid = trim((string)($input[$place['workplace']] ?? ''));
        $pid = !empty($place['post']) ? trim((string)($input[$place['post']] ?? '')) : '';

        if ($wid !== '' && !isset($names['workplace'][$wid])) {
            $errors[$place['workplace']] = '所选作业场所不存在。';
            $wid = '';
        }
        if ($pid !== '') {
            if (!isset($names['post'][$pid])) {
                $errors[$place['post']] = '所选岗位不存在。';
                $pid = '';
            } else {
                $owner = (string)$names['post_wp'][$pid];
                if ($wid === '') {
                    $wid = $owner;
                } elseif ($wid !== $owner) {
                    $errors[$place['post']] = '所选岗位不属于所选作业场所，请重新选择。';
                }
            }
        }
        return [$wid === '' ? null : (int)$wid, $pid === '' ? null : (int)$pid, $errors];
    }

    /**
     * 文本列整理：已通过下拉关联、且文本只是名称的重复时清空，避免"下拉 + 文本"两处各存一份。
     * $candidates 为与关联名称等同的写法（场所名、岗位名、场所+岗位名）。返回整理后的文本。
     */
    public static function cleanText(string $text, array $candidates): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        foreach ($candidates as $c) {
            if ((string)$c !== '' && self::norm($text) === self::norm((string)$c)) {
                return '';
            }
        }
        return $text;
    }
}
