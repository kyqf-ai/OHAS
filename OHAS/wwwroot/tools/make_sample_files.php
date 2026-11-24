<?php
/**
 * 生成最小合法 PDF / PNG 字节（v0.5.0 自测用）。
 * - makeSample('pdf') / makeSample('png')：返回字节串（不落盘）
 * - CLI：php tools/make_sample_files.php [输出目录]
 *   在输出目录生成 sample.pdf / sample.png / sample2.pdf，并打印绝对路径（供种子建文档）。
 */
declare(strict_types=1);

/* 仅限命令行 / 内建开发服务器；被 Web 服务器（Apache/nginx）误暴露时直接拒绝执行 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../app/bootstrap.php';

function make_sample(string $kind, string $label = 'OHAS sample'): string
{
    if ($kind === 'png') {
        // 1x1 白色 PNG
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        ) ?: '';
    }
    // 最小合法 PDF（含 /Title）
    $title = '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $label) . ')';
    $objs = [];
    $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objs[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objs[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
    $objs[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $stream = "BT /F1 12 Tf 72 770 Td {$title} Tj ET";
    $objs[5] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objs as $n => $body) {
        $offsets[$n] = strlen($pdf);
        $pdf .= "{$n} 0 obj\n{$body}\nendobj\n";
    }
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($objs as $n => $body) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$n]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
    return $pdf;
}

/** 存入 storage/uploads/documents/<Ym>/ 并返回相对路径（种子建文档用） */
function make_sample_file(string $kind = 'pdf'): string
{
    $bytes = make_sample($kind);
    $rel = 'documents/' . date('Ym') . '/';
    $dir = OHAS_ROOT . '/storage/uploads/' . $rel;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'sample-' . bin2hex(random_bytes(8)) . '.' . $kind;
    file_put_contents($dir . $name, $bytes);
    return $rel . $name;
}

if (PHP_SAPI === 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $out = $argv[1] ?? null;
    if ($out !== null) {
        if (!is_dir($out)) {
            mkdir($out, 0775, true);
        }
        foreach (['pdf' => 'sample.pdf', 'png' => 'sample.png'] as $kind => $file) {
            $path = rtrim($out, '/') . '/' . $file;
            file_put_contents($path, make_sample($kind));
            echo $path . "\n";
        }
        file_put_contents(rtrim($out, '/') . '/sample2.pdf', make_sample('pdf', 'OHAS sample 2'));
        echo rtrim($out, '/') . "/sample2.pdf\n";
        exit(0);
    }
    // 默认落盘到上传目录并打印相对路径
    echo make_sample_file('pdf') . "\n";
    echo make_sample_file('png') . "\n";
}
