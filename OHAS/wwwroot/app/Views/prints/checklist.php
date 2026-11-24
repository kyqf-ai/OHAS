<?php
/**
 * 单位资料清单打印：A4 横向。
 * 列：序号 | 档案类别 | 资料名称 | 自评项 | 状态 | 份数/进度 | 备注；状态用文字不用颜色。
 * 末尾填表人 / 填表日期（.pdate）。
 */
use App\Helpers\WorkerArchive;

$badge = fn (string $state) => WorkerArchive::badge($state);
$no = '单位资料清单';
$printBtn = '打印';
$backUrl = url('archive/checklist');
$backLabel = '返回资料清单';
$orient = 'landscape';
$extraHead = '<style>
table.ptable { table-layout: fixed; width: 100%; }
table.ptable th, table.ptable td { border: 1px solid #000; padding: 1.5mm 1.5mm; font-size: 9pt; word-break: break-all; }
table.ptable thead { display: table-header-group; }
.c-no { width: 14mm; text-align: center; }
.c-group { width: 24mm; }
.c-self { width: 14mm; text-align: center; }
.c-state { width: 18mm; text-align: center; }
.c-progress { width: 40mm; }
.c-note { width: 58mm; }
</style>';
include OHAS_ROOT . '/app/Views/prints/_top.php';
$items = $checklist['items'];
?>
<table style="width:100%; border:0; margin-bottom:2mm;">
    <tr>
        <td style="text-align:center; font-size:14pt; font-weight:bold; padding-bottom:1mm;"><?= e($company ?: cfg('app.name')) ?>·职业卫生档案资料归档清单</td>
    </tr>
    <tr>
        <td style="text-align:right; font-size:9pt;">打印日期：<?= date('Y-m-d') ?>　共 <?= count($items) ?> 项</td>
    </tr>
</table>

<table class="ptable">
    <thead>
        <tr>
            <th class="c-no">序号</th>
            <th class="c-group">档案类别</th>
            <th>资料名称</th>
            <th class="c-self">自评项</th>
            <th class="c-state">状态</th>
            <th class="c-progress">份数/进度</th>
            <th class="c-note">备注</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $it): ?>
        <tr>
            <td class="c-no"><?= e($it['code']) ?></td>
            <td class="c-group"><?= e(\App\Helpers\ArchiveChecklist::GROUPS[$it['group']] ?? '') ?></td>
            <td><?= e($it['name']) ?></td>
            <td class="c-self"><?= e($it['self']) ?></td>
            <td class="c-state"><?= e($badge($it['state'])[0]) ?></td>
            <td class="c-progress"><?php foreach ($it['parts'] as $p): ?><?= e($p['label']) ?>：<?= e($p['display']) ?>；<?php endforeach; ?></td>
            <td class="c-note"><?= $it['state'] === 'na' ? '不适用：' . e($it['na_reason']) : ($it['state'] === 'event' ? '按需归档' : '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<table style="width:100%; border:0; margin-top:6mm; font-size:10pt;">
    <tr>
        <td>填表人：<span class="pdate"><i class="y"></i></span></td>
        <td style="padding-left:30mm;">填表日期：<span class="pdate"><i class="y"></i>年<i class="m"></i>月<i class="d"></i>日</span></td>
    </tr>
</table>
<?php include OHAS_ROOT . '/app/Views/prints/_bottom.php'; ?>
