<?php
/**
 * 个人资料目录打印：A4 纵向。
 * 列：年度 | 资料 | 文件标题 | 日期 | 页数备注；表头含完整身份证号（打印页用完整号）；
 * 长标题自动换行，每行 page-break-inside: avoid，thead 跨页重复；末尾核对人 / 核对日期（.pdate）。
 */
use App\Helpers\WorkerArchive;

$h = $a['header'];
$no = '个人资料目录';
$printBtn = '打印';
$backUrl = url('workers/files', ['id' => (int)$a['worker']['id']]);
$backLabel = '返回个人资料页';
$extraHead = '<style>
table.ptable { table-layout: fixed; width: 100%; }
table.ptable th, table.ptable td { border: 1px solid #000; padding: 2mm 1.5mm; font-size: 9pt; word-break: break-all; }
table.ptable thead { display: table-header-group; }
.w-year { width: 16mm; text-align: center; }
.w-kind { width: 34mm; }
.w-date { width: 24mm; text-align: center; }
.w-note { width: 30mm; }
.pcount-hint { font-size: 8pt; color: #555; }
</style>';
include OHAS_ROOT . '/app/Views/prints/_top.php';
$badge = fn (string $state) => WorkerArchive::badge($state);
?>
<table class="psheet-head" style="width:100%; border:0; margin-bottom:2mm;">
    <tr>
        <td style="text-align:center; font-size:14pt; font-weight:bold; padding-bottom:1mm;"><?= e($company ?: cfg('app.name')) ?><?= $company ? '' : '' ?>·个人职业健康监护档案资料目录</td>
    </tr>
</table>
<table style="width:100%; border:0; font-size:10pt; margin-bottom:2mm;">
    <tr>
        <td>姓名：<b><?= e($h['name']) ?></b></td>
        <td>档案号：<?= e($h['archive_no'] ?: '—') ?></td>
        <td>身份证号：<?= e($h['id_card'] ?: '—') ?></td>
        <td>在职状态：<?= e($h['status']) ?></td>
        <td>当前岗位：<?= e($h['current_post'] ?: '—') ?></td>
        <td>打印日期：<?= date('Y-m-d') ?></td>
    </tr>
</table>

<table class="ptable">
    <thead>
        <tr>
            <th class="w-year">年度</th>
            <th class="w-kind">资料</th>
            <th>文件标题</th>
            <th class="w-date">日期</th>
            <th class="w-note">页数/备注</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $row = function (string $year, string $kind, string $title, string $date, string $note = ''): void {
        echo '<tr><td class="w-year">' . e($year) . '</td><td class="w-kind">' . e($kind) . '</td><td>' . e($title) . '</td><td class="w-date">' . e($date ?: '—') . '</td><td class="w-note">' . e($note) . '</td></tr>';
    };
    foreach (array_reverse($a['allYears'], true) as $y => $yr): ?>
        <?php foreach (WorkerArchive::yearColumns() as $type => $meta):
            $cell = $yr['cells'][$type];
            if ($cell['state'] === 'none') { continue; }
            [$lbl] = $badge($cell['state']);
            foreach (($cell['docs'] ?? []) as $dc) {
                $row((string)$y, $meta['name'] . '（' . $lbl . '）', (string)$dc['title'], (string)$dc['doc_date'], ($dc['link_type'] ?? '') === 'exam_record' ? '挂体检记录' : '');
            }
            foreach (($cell['reports'] ?? []) as $r) {
                $row((string)$y, $meta['name'] . '（' . ($r['has_doc'] ? '已归档（间接）' : '仅登记') . '）',
                    '检测报告 ' . $r['report_no'] . '（' . ($r['post_name'] ?: '按因素') . ($r['hazard_name'] ? ' · ' . $r['hazard_name'] : '') . '）',
                    (string)$r['test_date'], $r['has_doc'] ? '' : '原件待补');
            }
            if (!$cell['docs'] && !($cell['reports'] ?? []) && $cell['state'] !== 'na') {
                $row((string)$y, $meta['name'], '（' . $lbl . ($cell['hint'] ? '：' . $cell['hint'] : '') . '）', '', '');
            } elseif ($cell['state'] === 'na') {
                $row((string)$y, $meta['name'], '（不适用：' . $cell['hint'] . '）', '', '');
            }
        endforeach; ?>
    <?php endforeach; ?>
    <?php foreach ($a['once'] as $item): ?>
        <?php if ($item['state'] === 'none') { continue; } ?>
        <?php if ($item['docs']) {
            foreach ($item['docs'] as $dc) {
                $row('一次性', $item['name'], (string)$dc['title'], (string)$dc['doc_date'], $item['hint'] ? $item['hint'] : '');
            }
        } else {
            $row('一次性', $item['name'], '（' . $badge($item['state'])[0] . '）', '', $item['hint']);
        } ?>
    <?php endforeach; ?>
    <?php foreach ($a['events'] as $item): ?>
        <?php foreach ($item['docs'] as $dc) {
            $row('事件', $item['name'], (string)$dc['title'], (string)$dc['doc_date'], ($dc['from'] ?? '') === '职业病登记' ? '挂职业病登记' : '');
        } ?>
    <?php endforeach; ?>
    <?php foreach ($a['others'] as $dc) {
        $row('其他', (string)$dc['type_name'], (string)$dc['title'], (string)$dc['doc_date'], '');
    } ?>
    </tbody>
</table>

<table style="width:100%; border:0; margin-top:8mm; font-size:10pt;">
    <tr>
        <td>核对人：<span class="pdate"><i class="y"></i></span></td>
        <td style="padding-left:20mm;">核对日期：<span class="pdate"><i class="y"></i>年<i class="m"></i>月<i class="d"></i>日</span></td>
    </tr>
</table>
<p class="pcount-hint" style="margin-top:4mm;">注：本目录含个人身份与健康信息，请妥善保管。</p>
<?php include OHAS_ROOT . '/app/Views/prints/_bottom.php'; ?>
