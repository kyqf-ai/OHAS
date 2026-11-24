<?php
/**
 * 表2-5 职业病防护设施维护检修记录（一记录一页，Word 版式还原）
 */
$orient = 'portrait'; // 恢复 A4 纵向原版式：横向系早期为规避宋体下表尾折行的临时处理，全角空格方案根治后回归竖排（2026-11-18）
$printBtn = '打印表2-5';
$backUrl = $backUrl ?? url('prints/index');
$backLabel = '返回打印中心';
include OHAS_ROOT . '/app/Views/prints/_top.php';
foreach ($records as $idx => $m):
?>
<div class="psheet-inner">
    <div class="pdoc-no">档案编号：&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
    <h1 class="ptitle">表2-5&ensp;职业病防护设施维护检修记录</h1>
    <div class="pmeta">
        <span>单位名称：<?= e($company ?: '＿＿＿＿＿＿') ?></span>
        <span>第 <?= $idx + 1 ?> / <?= count($records) ?> 条</span>
    </div>

    <table class="ptable">
        <tbody>
        <tr>
            <td class="plabel" style="width:15%">车间名称</td>
            <td class="left" style="width:35%"><?= e($m['workplace'] ?: '') ?></td>
            <td class="plabel" style="width:15%">车间负责人</td>
            <td class="left" style="width:35%">&nbsp;</td>
        </tr>
        <tr>
            <td class="plabel">防护设备名称</td>
            <td class="left"><?= e($m['facility_name']) ?><?= $m['facility_model'] ? '（' . e($m['facility_model']) . '）' : '' ?></td>
            <td class="plabel">检修时间</td>
            <td class="left"><?= e($m['maintain_date']) ?></td>
        </tr>
        <tr>
            <td class="plabel">检修、维护情况<br>（包括检修的原因、检修部门、检修费用、检修效果等）</td>
            <td colspan="3" class="left pcell-lines" style="height:78mm">
                <?php if (trim((string)$m['reason']) !== ''): ?><p>检修原因：<?= e($m['reason']) ?></p><?php endif; ?>
                <?php if (trim((string)$m['department']) !== ''): ?><p>检修部门：<?= e($m['department']) ?></p><?php endif; ?>
                <?php if (trim((string)$m['cost']) !== ''): ?><p>检修费用：<?= e($m['cost']) ?> 元</p><?php endif; ?>
                <?php if (trim((string)$m['effect']) !== ''): ?><p>检修效果：<?= e($m['effect']) ?></p><?php endif; ?>
                <?php if (trim((string)$m['remark']) !== ''): ?><p>备注：<?= e($m['remark']) ?></p><?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="plabel" style="height:40mm">验收意见</td>
            <td colspan="3" class="left pwrap"><?= e($m['accept_opinion']) ?></td>
        </tr>
        </tbody>
    </table>

    <div class="psign">
        <span>负责人（签名）：　　　　　</span>
        <span><?= \App\Helpers\PrintSpec::dateBlank() ?></span>
    </div>
</div>
<?php
endforeach;
include OHAS_ROOT . '/app/Views/prints/_bottom.php';
