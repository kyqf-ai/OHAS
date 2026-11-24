<?php
/**
 * 表6-2 工作场所职业病危害因素检测结果（按规范样表版式打印，按人输出）
 * 归集口径：劳动者本单位职业史期间内，明细按「岗位」直接匹配或「同场所+配置危害因素」匹配。
 * 限值判定依据 GBZ 2.1-2019（含修改单）/ GBZ 2.2-2007。
 */
$w = $worker;
$rows = $w['detections'] ?? [];
$rowCount = max(8, count($rows));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>表6-2 工作场所职业病危害因素检测结果 - <?= e($w['name']) ?></title>
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
    .verdict { font-size: 10pt; }
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
    <button class="btn" onclick="window.print()">打印表6-2</button>
    <a class="btn btn-sub" href="<?= url('workers/show', ['id' => $w['id']]) ?>">返回信息卡</a>
    <a class="btn btn-sub" href="<?= url('detections/items') ?>">返回结果查询</a>
</div>

<div class="sheet">
    <div class="doc-no">档案号：<?= e($w['archive_no']) ?></div>
    <h1>表6-2&ensp;工作场所职业病危害因素检测结果</h1>
    <div class="meta">劳动者姓名：<b><?= e($w['name']) ?></b>用人单位：<b><?= e($company['name'] ?? '') ?></b></div>

    <table class="sheet-table">
        <thead>
            <tr>
                <th style="width:11%">岗位</th>
                <th style="width:13%">检测时间</th>
                <th style="width:16%">检测机构</th>
                <th style="width:15%">职业病危害因素名称</th>
                <th style="width:19%">职业病危害因素检测结果</th>
                <th style="width:13%">防护措施</th>
                <th style="width:13%">备注</th>
            </tr>
        </thead>
        <tbody>
            <?php $filled = 0; foreach ($rows as $it): $filled++; ?>
            <tr>
                <td><?= e($it['post_name'] ?: ($it['workplace_name'] ?: '—')) ?></td>
                <td class="nw"><?= e($it['test_date']) ?></td>
                <td><?= e($it['agency']) ?></td>
                <td><?= e($it['hazard_name'] ?: '—') ?></td>
                <td>
                    <?= e($it['result'] ?: '—') ?><?= $it['unit'] !== '' ? e(' ' . $it['unit']) : '' ?>
                    <?php if (($it['sample_type'] ?? '') !== ''): ?><span class="verdict">（<?= e($it['sample_type']) ?>）</span><?php endif; ?>
                    <?php if ($it['verdict'] === '超标'): ?><span class="verdict">（超标）</span>
                    <?php elseif ($it['verdict'] === '合格'): ?><span class="verdict">（合格）</span><?php endif; ?>
                </td>
                <td><?= e($it['protection'] ?: '—') ?></td>
                <td><?= e($it['remark'] ?: '—') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php for ($i = $filled; $i < $rowCount; $i++): ?>
            <tr class="empty-row"><td>&emsp;</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <div class="note">
        注：本表按劳动者本单位职业史自动归集检测结果；限值依据 GBZ 2.1-2019（含修改单）、GBZ 2.2-2007。
    </div>
</div>
</body>
</html>
