<?php
/**
 * 表2-9 职业卫生监管意见和落实情况记录表（一意见一页，Word 版式还原）
 * 系统字段映射：发现主要存在的问题=监管意见摘录；整改落实情况=落实情况+状态
 */
$orient = 'portrait';
$printBtn = '打印表2-9';
$backUrl = $backUrl ?? url('prints/index');
$backLabel = '返回打印中心';
include OHAS_ROOT . '/app/Views/prints/_top.php';
foreach ($records as $idx => $r):
?>
<div class="psheet-inner">
    <div class="pdoc-no">档案编号：&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
    <h1 class="ptitle">表2-9&ensp;职业卫生监管意见和落实情况记录表</h1>
    <div class="pmeta">
        <span>单位名称：<?= e($company ?: '＿＿＿＿＿＿') ?></span>
        <span>第 <?= $idx + 1 ?> / <?= count($records) ?> 条</span>
    </div>

    <table class="ptable">
        <tbody>
        <tr>
            <td class="plabel" style="width:18%">上级检查部门</td>
            <td class="left" style="width:32%"><?= e($r['supervision_unit']) ?></td>
            <td class="plabel" style="width:14%">检查日期</td>
            <td class="left" style="width:36%"><?= e($r['opinion_date']) ?></td>
        </tr>
        <tr>
            <td class="plabel" style="height:52mm">发现主要存在的问题<br>（主要内容摘录，附原件）</td>
            <td colspan="3" class="left pwrap"><?= e($r['opinion_content']) ?></td>
        </tr>
        <tr>
            <td class="plabel" style="height:40mm">要求整改的措施及建议</td>
            <td colspan="3" class="left pcell-lines pfilled">
                <div class="pfill" style="--h:40mm"><div></div><?= \App\Helpers\PrintSpec::signLine() ?></div>
            </td>
        </tr>
        <tr>
            <td class="plabel" style="height:40mm">用人单位领导<br>审批意见</td>
            <td colspan="3" class="left pcell-lines pfilled">
                <div class="pfill" style="--h:40mm"><div></div><?= \App\Helpers\PrintSpec::signLine() ?></div>
            </td>
        </tr>
        <tr>
            <td class="plabel" style="height:52mm">整改落实情况</td>
            <td colspan="3" class="left pcell-lines pfilled">
                <div class="pfill" style="--h:52mm">
                    <div>
                        <p><?= e($r['rectification']) ?></p>
                        <p>落实状态：<?= e($r['status']) ?><?= $r['remark'] ? '；' . e($r['remark']) : '' ?></p>
                    </div>
                    <?= \App\Helpers\PrintSpec::signLine('负责人（签名）：') ?>
                </div>
            </td>
        </tr>
        </tbody>
    </table>

    <div class="pnote">说明：检查记录、整改回执等原件另册归档。</div>
</div>
<?php
endforeach;
include OHAS_ROOT . '/app/Views/prints/_bottom.php';
