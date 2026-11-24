<?php
/**
 * 表6-1 劳动者个人信息卡（按规范样表版式打印）
 * A4 纵向；打印按钮调起浏览器打印（可另存 PDF 归档）。
 */
$w = $worker;
$histRows = max(4, count($w['history']));
$illRows  = max(4, count($w['illnesses']));
$disRows  = max(3, count($w['diseases']));
$photoUrl = !empty($w['photo']) ? url('media/photo', ['f' => $w['photo']]) : null;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>表6-1 劳动者个人信息卡 - <?= e($w['name']) ?></title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: "SimSun", "宋体", "Noto Serif SC", serif;
        color: #000; background: #666; padding: 16px 0;
    }
    .sheet {
        width: 210mm; min-height: 250mm; margin: 0 auto; background: #fff;
        padding: 12mm 12mm; box-shadow: 0 2px 14px rgba(0,0,0,.4);
    }
    .doc-no { text-align: right; font-size: 12pt; margin-bottom: 4px; }
    h1 { text-align: center; font-size: 16pt; letter-spacing: 2px; margin: 6px 0 14px; }
    table.sheet-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.sheet-table th, table.sheet-table td {
        border: 1px solid #000; padding: 5px 6px; font-size: 11.5pt;
        height: 34px; word-break: normal; overflow-wrap: anywhere; vertical-align: middle;
    }
    .info td.label { text-align: center; background: #f5f5f5; width: 17%; }
    .info td.label-wide { text-align: center; background: #f5f5f5; width: 14%; }
    .section { background: #f5f5f5; text-align: center; font-weight: bold; letter-spacing: 1px; height: 36px; }
    .sec-head th, .sec-head td { text-align: center; background: #f5f5f5; font-weight: bold; }
    .photo-cell { width: 33mm; text-align: center; vertical-align: middle; }
    .photo-cell img { max-width: 28mm; max-height: 38mm; }
    .photo-box { width: 28mm; height: 38mm; margin: 0 auto; border: 1px dashed #999; display: flex; align-items: center; justify-content: center; color: #999; font-size: 10pt; }
    .mono { font-family: "Courier New", monospace; }
    .empty-row td { height: 36px; }
    .nw { white-space: nowrap; }   /* 日期不从中间拆行 */
    tr { page-break-inside: avoid; }
    .toolbar { width: 210mm; margin: 0 auto 10px; text-align: right; }
    .btn {
        display: inline-block; padding: 8px 22px; background: #1e5aa8; color: #fff;
        border: none; border-radius: 4px; font-size: 14px; cursor: pointer; margin-right: 8px;
    }
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
    <button class="btn" onclick="window.print()">打印表6-1</button>
    <a class="btn btn-sub" href="<?= url('workers/edit', ['id' => $w['id']]) ?>">返回编辑</a>
    <a class="btn btn-sub" href="<?= url('workers/index') ?>">返回列表</a>
</div>

<div class="sheet">
    <div class="doc-no">档案号：<?= e($w['archive_no']) ?></div>
    <h1>表6-1&ensp;劳动者个人信息卡</h1>

    <table class="sheet-table info">
        <tr>
            <td class="label">姓&emsp;名</td><td><?= e($w['name']) ?></td>
            <td class="label">性&emsp;别</td><td><?= e($w['gender'] ?: '') ?></td>
            <td class="photo-cell" rowspan="3">
                <?php if ($photoUrl): ?>
                    <img src="<?= e($photoUrl) ?>" alt="照片">
                <?php else: ?>
                    <div class="photo-box">照片</div>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="label">籍&emsp;贯</td><td><?= e($w['native_place']) ?></td>
            <td class="label">婚&emsp;姻</td><td><?= e($w['marital_status']) ?></td>
        </tr>
        <tr>
            <td class="label">文化程度</td><td><?= e($w['education']) ?></td>
            <td class="label">嗜&emsp;好</td><td><?= e($w['hobby']) ?></td>
        </tr>
        <tr>
            <td class="label">参加工作时间</td>
            <td colspan="4"><?= d($w['work_start_date']) ?></td>
        </tr>
        <tr>
            <td class="label">身份证号</td>
            <td colspan="4" class="mono"><?= e($w['id_card'] ?: '') ?></td>
        </tr>

        <tr><td colspan="5" class="section">职业史及职业病危害接触史</td></tr>
        <tr class="sec-head">
            <th style="width:20%">起止时间</th>
            <th style="width:20%">工作单位</th>
            <th style="width:14%">工种</th>
            <th style="width:23%">接触职业病危害因素</th>
            <th style="width:23%">防护措施</th>
        </tr>
        <?php $filled = 0; foreach ($w['history'] as $h): $filled++; ?>
        <tr>
            <td class="mono"><span class="nw"><?= e($h['start_date']) ?></span> <?= $h['end_date'] ? '至 <span class="nw">' . e($h['end_date']) . '</span>' : '至今' ?></td>
            <td><?= e($h['company_name']) ?></td>
            <td><?= e($h['post_name']) ?></td>
            <td><?= e($h['hazards_snapshot']) ?></td>
            <td><?= e($h['protection_snapshot']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php for ($i = $filled; $i < 4; $i++): ?>
        <tr class="empty-row"><td>&emsp;</td><td></td><td></td><td></td><td></td></tr>
        <?php endfor; ?>

        <tr><td colspan="5" class="section">既往病史</td></tr>
        <tr class="sec-head">
            <th>疾病名称</th><th>诊断时间</th><th>诊断医院</th><th>治疗结果</th><th>备注</th>
        </tr>
        <?php $filled = 0; foreach ($w['illnesses'] as $il): $filled++; ?>
        <tr>
            <td><?= e($il['disease']) ?></td>
            <td><?= d($il['diag_date']) ?></td>
            <td><?= e($il['hospital']) ?></td>
            <td><?= e($il['outcome']) ?></td>
            <td><?= e($il['remark']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php for ($i = $filled; $i < 4; $i++): ?>
        <tr class="empty-row"><td>&emsp;</td><td></td><td></td><td></td><td></td></tr>
        <?php endfor; ?>

        <tr><td colspan="5" class="section">职业病诊断</td></tr>
        <tr class="sec-head">
            <th>职业病名称</th><th>诊断时间</th><th>诊断医院</th><th>诊断级别</th><th>备注</th>
        </tr>
        <?php $filled = 0; foreach ($w['diseases'] as $dz): $filled++; ?>
        <tr>
            <td><?= e($dz['name']) ?><?= $dz['is_suspected'] ? '（疑似）' : '' ?></td>
            <td><?= d($dz['diag_date']) ?></td>
            <td><?= e($dz['hospital']) ?></td>
            <td><?= e($dz['level']) ?></td>
            <td><?= e($dz['remark']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php for ($i = $filled; $i < 3; $i++): ?>
        <tr class="empty-row"><td>&emsp;</td><td></td><td></td><td></td><td></td></tr>
        <?php endfor; ?>
    </table>
</div>
</body>
</html>
