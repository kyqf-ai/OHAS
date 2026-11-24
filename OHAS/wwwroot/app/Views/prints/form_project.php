<?php
/**
 * 表1-1 建设项目职业卫生"三同时"审查登记表（一项目一页，Word 版式还原）
 * $records 项目记录数组；$spec PrintSpec 条目；$company 单位名称；$multiSheet 是否多页
 */
$orient = 'portrait';
$printBtn = '打印表1-1';
$backUrl = $backUrl ?? url('prints/index');
$backLabel = '返回打印中心';
include OHAS_ROOT . '/app/Views/prints/_top.php';
foreach ($records as $idx => $p):
?>
<div class="psheet-inner">
    <div class="pdoc-no">档案编号：&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
    <h1 class="ptitle">表1-1&ensp;建设项目职业卫生“三同时”审查登记表</h1>
    <div class="pmeta">
        <span>单位名称：<?= e($company ?: '＿＿＿＿＿＿') ?></span>
        <span>第 <?= $idx + 1 ?> / <?= count($records) ?> 项</span>
    </div>

    <table class="ptable">
        <tbody>
        <tr>
            <td class="plabel" style="width:22%">项目名称</td>
            <td colspan="5" class="left"><?= e($p['name']) ?></td>
        </tr>
        <tr>
            <td class="plabel">项目类型</td>
            <td colspan="2" class="left"><?= e($p['type']) ?></td>
            <td class="plabel" style="width:16%">项目投资（万元）</td>
            <td colspan="2" class="left"><?= e($p['investment']) ?></td>
        </tr>
        <tr>
            <td class="plabel">建设工期</td>
            <td colspan="5" class="left"><?= $p['start_date'] ? e($p['start_date']) : \App\Helpers\PrintSpec::dateBlank() ?> 至 <?= $p['end_date'] ? e($p['end_date']) : \App\Helpers\PrintSpec::dateBlank() ?></td>
        </tr>
        <tr>
            <td class="plabel">存在的主要职业病危害因素</td>
            <td colspan="5" class="left pwrap" style="height:44px"><?= e($p['main_hazards']) ?></td>
        </tr>
        </tbody>
    </table>

    <table class="ptable" style="margin-top:-1px">
        <thead>
        <tr>
            <th colspan="3" style="width:36%">预评价审核</th>
            <th colspan="3" style="width:34%">设计审查（严重危害项目）</th>
            <th colspan="3" style="width:30%">竣工验收</th>
        </tr>
        <tr>
            <th style="width:11%">年月</th><th style="width:10%">结论</th><th style="width:15%">审核单位</th>
            <th style="width:10%">年月</th><th style="width:10%">结论</th><th style="width:14%">审查单位</th>
            <th style="width:10%">年月</th><th style="width:10%">结论</th><th style="width:10%">验收单位</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td colspan="3" class="left pwrap" style="height:70px"><?= e($p['pre_review']) ?></td>
            <td colspan="3" class="left pwrap"><?= e($p['design_review']) ?></td>
            <td colspan="3" class="left pwrap"><?= e($p['completion']) ?></td>
        </tr>
        <tr class="pempty"><td colspan="9" class="left">（如需分次登记审核 / 审查 / 验收进展，可在下方空行补充）</td></tr>
        <?= \App\Helpers\PrintSpec::renderEmpty(2, 9) ?>
        </tbody>
    </table>

<?php if (!empty($p['remark'])): ?>
    <div class="pnote">备注：<?= e($p['remark']) ?></div>
<?php endif; ?>
    <div class="psign">
        <span>编制：　　　　　</span>
        <span>审核（签名）：　　　　　</span>
        <span>编制日期：<?= \App\Helpers\PrintSpec::dateBlank() ?></span>
    </div>
    <div class="pnote">说明：项目类型按新建、改建、扩建、技改、引进填报；批文原件归入三同时档案。</div>
</div>
<?php
endforeach;
include OHAS_ROOT . '/app/Views/prints/_bottom.php';
