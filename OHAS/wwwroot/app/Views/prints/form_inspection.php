<?php
/**
 * 表2-8 职业卫生检查和处理记录（一记录一页，Word 版式还原）
 * 系统字段映射：检查情况记录=检查内容+发现问题；整改意见=处理措施+责任人+整改期限；整改落实情况=整改状态
 */
$orient = 'portrait'; // 恢复 A4 纵向原版式：横向系早期为规避宋体下表尾折行的临时处理，全角空格方案根治后回归竖排（2026-11-18）
$printBtn = '打印表2-8';
$backUrl = $backUrl ?? url('prints/index');
$backLabel = '返回打印中心';
include OHAS_ROOT . '/app/Views/prints/_top.php';
foreach ($records as $idx => $r):
?>
<div class="psheet-inner">
    <div class="pdoc-no">档案编号：&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
    <h1 class="ptitle">表2-8&ensp;职业卫生检查和处理记录</h1>
    <div class="pmeta">
        <span>单位名称：<?= e($company ?: '＿＿＿＿＿＿') ?></span>
        <span>第 <?= $idx + 1 ?> / <?= count($records) ?> 条</span>
    </div>

    <table class="ptable">
        <tbody>
        <tr>
            <td class="plabel" style="width:13%">车间名称</td>
            <td class="left" style="width:37%">&nbsp;</td>
            <td class="plabel" style="width:13%">车间负责人</td>
            <td class="left" style="width:37%">&nbsp;</td>
        </tr>
        <tr>
            <td class="plabel">检查地点</td>
            <td colspan="3" class="left">&nbsp;</td>
        </tr>
        <tr>
            <td class="plabel">检查时间</td>
            <td colspan="3" class="left"><?= $r['check_date'] ? e($r['check_date']) : \App\Helpers\PrintSpec::dateBlank() ?>&emsp;<?= \App\Helpers\PrintSpec::timeBlank() ?> —— <?= \App\Helpers\PrintSpec::timeBlank() ?></td>
        </tr>
        <tr>
            <td class="plabel">检查情况记录：</td>
            <td colspan="3" class="left pcell-lines pfilled" style="height:58mm">
                <div class="pfill" style="--h:58mm">
                    <div>
                        <p>检查内容：<?= e($r['check_content']) ?></p>
                        <?php if (trim((string)$r['issue']) !== ''): ?><p>发现问题：<?= e($r['issue']) ?></p><?php endif; ?>
                    </div>
                    <?= \App\Helpers\PrintSpec::signLine('检查人员（签名）：') ?>
                </div>
            </td>
        </tr>
        <tr>
            <td class="plabel" style="height:40mm">整改意见</td>
            <td colspan="3" class="left pcell-lines pfilled">
                <div class="pfill" style="--h:40mm">
                    <div>
                        <?php if (trim((string)$r['handle_measure']) !== ''): ?><p>处理措施：<?= e($r['handle_measure']) ?></p><?php endif; ?>
                        <?php if (trim((string)$r['responsible_person']) !== ''): ?><p>责任人：<?= e($r['responsible_person']) ?></p><?php endif; ?>
                        <?php if (trim((string)$r['deadline']) !== ''): ?><p>整改期限：<?= e($r['deadline']) ?></p><?php endif; ?>
                    </div>
                    <?= \App\Helpers\PrintSpec::signLine('负责人（签名）：') ?>
                </div>
            </td>
        </tr>
        <tr>
            <td class="plabel" style="height:40mm">整改落实情况</td>
            <td colspan="3" class="left pcell-lines pfilled">
                <div class="pfill" style="--h:40mm">
                    <div>
                        <p>整改状态：<?= e($r['status']) ?><?= $r['deadline'] ? '（整改期限 ' . e($r['deadline']) . '）' : '' ?></p>
                        <?php if (trim((string)$r['remark']) !== ''): ?><p><?= e($r['remark']) ?></p><?php endif; ?>
                    </div>
                    <?= \App\Helpers\PrintSpec::signLine('车间负责人（签名）：') ?>
                </div>
            </td>
        </tr>
        </tbody>
    </table>

    <div class="pnote">说明：检查内容按现场实际情况填写；发现问题应填写处理措施和整改期限。</div>
</div>
<?php
endforeach;
include OHAS_ROOT . '/app/Views/prints/_bottom.php';
