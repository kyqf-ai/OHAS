<?php
/**
 * 表5-5 职业病危害事故报告与处理记录表（一事故一页，Word 版式还原）
 */
$orient = 'portrait';
$printBtn = '打印表5-5';
$backUrl = $backUrl ?? url('prints/index');
$backLabel = '返回打印中心';
include OHAS_ROOT . '/app/Views/prints/_top.php';
foreach ($records as $idx => $a):
    $place = $a['workplace'] ?: $a['workplace_name'];
    $post  = $a['post'] ?: $a['post_name'];
?>
<div class="psheet-inner">
    <div class="pdoc-no">档案编号：&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
    <h1 class="ptitle">表5-5&ensp;职业病危害事故报告与处理记录表</h1>
    <div class="pmeta">
        <span>单位名称：<?= e($company ?: $a['company_name']) ?></span>
        <span>第 <?= $idx + 1 ?> / <?= count($records) ?> 条</span>
    </div>

    <table class="ptable">
        <tbody>
        <tr>
            <td class="plabel" style="width:16%">企业名称</td>
            <td class="left" style="width:34%"><?= e($a['company_name'] ?: ($company ?? '')) ?></td>
            <td class="plabel" style="width:16%">法定代表人</td>
            <td class="left" style="width:34%"><?= e($a['legal_person']) ?></td>
        </tr>
        <tr>
            <td class="plabel">事故报告人</td>
            <td class="left"><?= e($a['reporter']) ?></td>
            <td class="plabel">联系电话</td>
            <td class="left"><?= e($a['contact_phone']) ?></td>
        </tr>
        <tr>
            <td class="plabel" style="height:34mm">基本情况</td>
            <td colspan="3" class="left pcell-lines">
                <p>1．发生时间：<?= e($a['accident_time']) ?></p>
                <p>2．发生场所（车间名称）：<?= e($place) ?><?= $post ? '　岗位：' . e($post) : '' ?>　作业内容：<?= e($a['work_content']) ?></p>
                <p>3．危害因素：<?= e($a['hazard_factor']) ?></p>
                <p>4．接触 <?= (int)$a['exposure_count'] ?> 人；发病 <?= (int)$a['sick_count'] ?> 人；住院 <?= (int)$a['hospital_count'] ?> 人；死亡 <?= (int)$a['death_count'] ?> 人</p>
            </td>
        </tr>
        <tr>
            <td class="plabel" style="height:52mm">事故经过简述<br>（事件起因、患者主要临床表现、救援过程和处理情况）</td>
            <td colspan="3" class="left pwrap"><?= e($a['description']) ?></td>
        </tr>
        <tr>
            <td class="plabel" style="height:40mm">对事故原因和性质的初步认定意见</td>
            <td colspan="3" class="left pwrap"><?= e($a['preliminary_opinion']) ?></td>
        </tr>
        <tr>
            <td class="plabel" style="height:34mm">事件报告情况</td>
            <td colspan="3" class="left pcell-lines pfilled">
                <div class="pfill" style="--h:34mm">
                    <div>
                        <p>1．报告时间：<?= e($a['report_time']) ?></p>
                        <p>2．报告单位：<?= e($a['report_unit']) ?></p>
                    </div>
                    <?= \App\Helpers\PrintSpec::signLine('负责人（签名）：') ?>
                </div>
            </td>
        </tr>
        </tbody>
    </table>

    <div class="psign">
        <span>处理人：<?= e($a['handler']) ?></span>
        <span>（单位盖章）</span>
        <span>记录日期：<?= \App\Helpers\PrintSpec::dateBlank() ?></span>
    </div>
<?php if (trim((string)$a['remark']) !== ''): ?>
    <div class="pnote">备注：<?= e($a['remark']) ?></div>
<?php endif; ?>
</div>
<?php
endforeach;
include OHAS_ROOT . '/app/Views/prints/_bottom.php';
