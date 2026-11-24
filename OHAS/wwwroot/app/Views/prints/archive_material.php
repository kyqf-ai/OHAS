<?php
/**
 * 内部编制资料打印版式：A4 纵向，标题区 + 元信息 + 正文（按行保留分段）。
 */
$orient = 'portrait';
$no = $no ?? '内部资料';
$title = (string)($material['title'] ?? '');
$body = (string)($material['body'] ?? '');
$lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
?>
<?php include __DIR__ . '/_top.php'; ?>

<div class="doc-header" style="text-align:center;margin-bottom:18px;">
    <?php if (!empty($company)): ?><div style="font-size:11pt;letter-spacing:2px;"><?= e($company) ?></div><?php endif; ?>
    <h1 style="font-size:18pt;margin:8px 0 4px;"><?= e($title) ?></h1>
    <?php if (!empty($entry) && !empty($entry[0])): ?>
    <div style="font-size:9pt;color:#555;">六类档案 · 官方目录第 <?= e($entry[0]) ?> 项 · <?= e($entry[1] ?? '') ?></div>
    <?php endif; ?>
</div>

<table class="meta-table" style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:10pt;">
    <tr>
        <td style="border:1px solid #666;padding:5px 8px;width:14%;font-weight:bold;">文号</td>
        <td style="border:1px solid #666;padding:5px 8px;width:36%;"><?= e($material['doc_no'] ?? '—') ?></td>
        <td style="border:1px solid #666;padding:5px 8px;width:14%;font-weight:bold;">日期</td>
        <td style="border:1px solid #666;padding:5px 8px;width:36%;"><?= e($material['doc_date'] ?? '—') ?></td>
    </tr>
    <tr>
        <td style="border:1px solid #666;padding:5px 8px;font-weight:bold;">编制单位</td>
        <td colspan="3" style="border:1px solid #666;padding:5px 8px;"><?= e($material['issuer'] ?? '—') ?></td>
    </tr>
</table>

<div class="doc-body" style="font-size:11pt;line-height:1.9;white-space:pre-wrap;word-break:break-word;">
<?php foreach ($lines as $line): ?>
<div><?= e($line) ?></div>
<?php endforeach; ?>
</div>

<div style="margin-top:32px;text-align:right;font-size:10pt;">
    编制单位（盖章）：____________________　　日期：______年____月____日
</div>

<?php include __DIR__ . '/_bottom.php'; ?>
