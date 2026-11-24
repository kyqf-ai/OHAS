<?php
/**
 * 表6-3 历次职业健康检查结果及处理情况（按规范样表版式打印，按人输出）
 * 结论口径注释按 GBZ 188-2025《职业健康监护技术规范》（2026-08-01 施行）。
 */
$w = $worker;
$rows = [];
foreach (($w['exams'] ?? []) as $yearGroup) {
    foreach ($yearGroup as $r) {
        $rows[] = $r;
    }
}
$rowCount = max(8, count($rows));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>表6-3 历次职业健康检查结果及处理情况 - <?= e($w['name']) ?></title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "SimSun", "宋体", "Noto Serif SC", serif; color: #000; background: #666; padding: 16px 0; }
    .sheet { width: 210mm; min-height: 250mm; margin: 0 auto; background: #fff; padding: 12mm; box-shadow: 0 2px 14px rgba(0,0,0,.4); }
    .doc-no { text-align: right; font-size: 12pt; margin-bottom: 4px; }
    h1 { text-align: center; font-size: 16pt; letter-spacing: 2px; margin: 6px 0 4px; }
    .meta { text-align: center; font-size: 12pt; margin-bottom: 12px; }
    .meta b { margin: 0 10px; }
    table.sheet-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.sheet-table th, table.sheet-table td {
        border: 1px solid #000; padding: 4px 5px; font-size: 10.5pt;
        word-break: normal; overflow-wrap: anywhere; vertical-align: middle; text-align: center;
    }
    th { height: 30px; }
    td { min-height: 30px; }
    .empty-row td { height: 34px; }
    td.nw { white-space: nowrap; }   /* 日期不拆行 */
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .note { font-size: 10.5pt; line-height: 1.7; margin-top: 8px; }
    .toolbar { width: 210mm; margin: 0 auto 10px; text-align: right; }
    .btn { display: inline-block; padding: 8px 22px; background: #1e5aa8; color: #fff; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; margin-right: 8px; text-decoration: none; }
    .btn-sub { background: #6b7280; }
    .hint { color: #ddd; font-size: 12px; margin-right: 12px; }
    @media print {
        body { background: #fff; padding: 0; }
        .toolbar { display: none; }
        .sheet { width: auto; box-shadow: none; padding: 0; }
        @page { size: A4 portrait; margin: 12mm; }
    }
</style>
</head>
<body>
<div class="toolbar no-print">
    <span class="hint">打印时请选择 A4 纵向；如需归档电子件可选择「另存为 PDF」</span>
    <button class="btn" onclick="window.print()">打印表6-3</button>
    <a class="btn btn-sub" href="<?= url('workers/show', ['id' => $w['id']]) ?>">返回信息卡</a>
    <a class="btn btn-sub" href="<?= url('exams/records') ?>">返回记录列表</a>
</div>

<div class="sheet">
    <div class="doc-no">档案号：<?= e($w['archive_no']) ?></div>
    <h1>表6-3&ensp;历次职业健康检查结果及处理情况</h1>
    <div class="meta">劳动者姓名：<b><?= e($w['name']) ?></b>用人单位：<b><?= e($company['name'] ?? '') ?></b></div>

    <table class="sheet-table">
        <thead>
            <tr>
                <th style="width:13%">检查日期</th>
                <th style="width:10%">检查种类</th>
                <th style="width:11%">检查结论</th>
                <th style="width:15%">检查机构</th>
                <th style="width:10%">岗位</th>
                <th style="width:13%">人员处理情况</th>
                <th style="width:13%">本人签字</th>
                <th style="width:15%">现场处理情况</th>
            </tr>
        </thead>
        <tbody>
            <?php $filled = 0; foreach ($rows as $r): $filled++; ?>
            <tr>
                <td class="nw"><?= e($r['exam_date']) ?></td>
                <td><?= e($r['exam_type']) ?></td>
                <td><?= e($r['conclusion'] !== '' ? $r['conclusion'] : '—') ?></td>
                <td><?= e($r['agency']) ?></td>
                <td><?= e($r['post_name']) ?></td>
                <td><?= e($r['handling'] ?: '—') ?></td>
                <td class="nw"><?= e($r['signed_at'] ?: '—') ?></td>
                <td><?= e($r['site_handling'] ?: '—') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php for ($i = $filled; $i < $rowCount; $i++): ?>
            <tr class="empty-row"><td>&emsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <div class="note">
        注：检查种类按 GBZ 188-2025 填写；结论分为目前未见异常、疑似职业病、职业禁忌证、其他疾病或异常及职业病诊断结论。无需整改或处理的项目划「—」。
    </div>
</div>
</body>
</html>
