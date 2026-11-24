<?php
/**
 * 列表类规范表样统一渲染（表2-1/2-3/2-4/2-6/2-7/4-1/4-2/4-3/5-1/5-2/5-3/5-4/5-6）
 * 版式规范：PrintSpec 表头矩阵 + print.css；Word 原表头结构 1:1 还原。
 *
 * $spec     PrintSpec 条目（含 heads/empty/signs/note/dense）
 * $rows     数据行（叶子列对齐的单元格数组）
 * $groups   表4-1 行组：[['label'=>'设备','count'=>3], ...]；其他表为 null
 * $metaLeft / $metaRight  表题下方两侧信息行
 * $company  单位名称
 * $backUrl  工具栏返回链接
 */
$thead = \App\Helpers\PrintSpec::renderHead($spec);
$cols = \App\Helpers\PrintSpec::leafCount($spec['heads']);
$dense = !empty($spec['dense']);
$grouped = is_array($groups);
$dataCols = $grouped ? $cols - 1 : $cols;
$lcols = array_flip($spec['lcols'] ?? []);          // 长文本列：左对齐
$off = $grouped ? 1 : 0;                            // 表4-1 首列为行组标签，数据格序号整体后移一列
$tdc = fn (int $j): string => isset($lcols[$j + $off]) ? ' class="left"' : '';
$backLabel = $backLabel ?? '返回打印中心';
$orient = $spec['orient'] ?? 'portrait';
$printBtn = '打印' . ($spec['no'] ?? '');
include OHAS_ROOT . '/app/Views/prints/_top.php';
?>

    <div class="pdoc-no">档案编号：&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
    <h1 class="ptitle"><?= e($spec['no']) ?>&ensp;<?= e($spec['title']) ?></h1>
    <div class="pmeta">
        <span><?= e($metaLeft ?? '') ?></span>
        <span><?= e($metaRight ?? '') ?></span>
    </div>

    <table class="ptable plist<?= $dense ? ' dense' : '' ?>">
        <?= $thead ?>
        <tbody>
        <?php if (!$rows): ?>
            <tr class="pempty<?= $dense ? ' dense' : '' ?>">
                <td colspan="<?= $cols ?>" class="left">（暂无记录，打印后手工填写）</td>
            </tr>
        <?php elseif ($grouped): ?>
            <?php $rowIdx = 0; foreach ($groups as $g): ?>
                <?php for ($i = 0; $i < $g['count']; $i++): $r = $rows[$rowIdx] ?? []; $rowIdx++; ?>
                <tr>
                    <?php if ($i === 0): ?>
                        <td class="pgroup" rowspan="<?= (int)$g['count'] ?>"><?= e($g['label']) ?></td>
                    <?php endif; ?>
                    <?php foreach (array_values($r) as $ci => $cell): ?>
                        <td<?= $tdc($ci) ?>><?= e((string)$cell) ?></td>
                    <?php endforeach; ?>
                    <?php for ($j = count($r); $j < $dataCols; $j++): ?><td></td><?php endfor; ?>
                </tr>
                <?php endfor; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <?php foreach (array_values($r) as $ci => $cell): ?>
                    <td<?= $tdc($ci) ?>><?= e((string)$cell) ?></td>
                <?php endforeach; ?>
                <?php for ($i = count($r); $i < $cols; $i++): ?><td></td><?php endfor; ?>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        <?= \App\Helpers\PrintSpec::renderEmpty((int)($spec['empty'] ?? 0), $cols, $dense) ?>
<?php if (!empty($spec['note']) || !empty($spec['signs'])): ?>
        <?php /* 说明 + 签名栏作为表内无边框末行：与末尾数据行同页，避免整页只剩一行签名 */ ?>
        <tr class="pfoot"><td colspan="<?= $cols ?>">
<?php if (!empty($spec['note'])): ?>
            <div class="pnote">说明：<?= e($spec['note']) ?></div>
<?php endif; ?>
            <?= \App\Helpers\PrintSpec::renderSigns($spec['signs'] ?? null) ?>
        </td></tr>
<?php endif; ?>
        </tbody>
    </table>

<?php include OHAS_ROOT . '/app/Views/prints/_bottom.php'; ?>
