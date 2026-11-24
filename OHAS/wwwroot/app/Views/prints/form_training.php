<?php
/**
 * 表3-1 职业卫生宣传培训记录表（一次培训一页：培训信息 + 参训人员签到表，Word 版式还原）
 */
$orient = 'portrait';
$printBtn = '打印表3-1';
$backUrl = $backUrl ?? url('prints/index');
$backLabel = '返回打印中心';
include OHAS_ROOT . '/app/Views/prints/_top.php';
foreach ($records as $idx => $t):
    $parts = $t['participants'];
?>
<div class="psheet-inner">
    <div class="pdoc-no">档案编号：&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
    <h1 class="ptitle">表3-1&ensp;职业卫生宣传培训记录表</h1>
    <div class="pmeta">
        <span>单位名称：<?= e($company ?: '＿＿＿＿＿＿') ?></span>
        <span>第 <?= $idx + 1 ?> / <?= count($records) ?> 期</span>
    </div>

    <table class="ptable">
        <tbody>
        <tr>
            <td class="plabel" style="width:16%">培训类型</td>
            <td class="left" style="width:34%"><?= e($t['type']) ?></td>
            <td class="plabel" style="width:16%">实施日期</td>
            <td class="left" style="width:34%"><?= e($t['trained_on']) ?></td>
        </tr>
        <tr>
            <td class="plabel">培训内容</td>
            <td colspan="3" class="left"><?= e($t['title']) ?></td>
        </tr>
        <tr>
            <td class="plabel">学时</td>
            <td class="left"><?= e(rtrim(rtrim((string)$t['hours'], '0'), '.')) ?> 学时</td>
            <td class="plabel">授课人</td>
            <td class="left"><?= e($t['teacher']) ?></td>
        </tr>
        <tr>
            <td class="plabel">组织部门</td>
            <td class="left"><?= e($t['organizer']) ?></td>
            <td class="plabel">参加部门</td>
            <td class="left"><?= e($t['department']) ?></td>
        </tr>
        </tbody>
    </table>

    <table class="ptable" style="margin-top:4mm">
        <thead>
        <tr>
            <th style="width:12%">序号</th>
            <th style="width:34%">部门</th>
            <th style="width:34%">姓名（签字）</th>
            <th style="width:20%">成绩</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$parts): ?>
            <?= \App\Helpers\PrintSpec::renderEmpty(12, 4) ?>
        <?php else: ?>
            <?php foreach ($parts as $i => $p): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td class="left"><?= e(trim(($p['cur_workplace'] ? $p['cur_workplace'] : '') . ($p['cur_post'] ? '·' . $p['cur_post'] : ''), '·')) ?></td>
                <td class="left"><?= e($p['worker_name']) ?><?= $p['attended'] === '否' ? '（未签到）' : '' ?></td>
                <td><?= e($p['score']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?= \App\Helpers\PrintSpec::renderEmpty(max(2, 12 - count($parts)), 4) ?>
        <?php endif; ?>
        </tbody>
    </table>

<?php if (trim((string)$t['remark']) !== ''): ?>
    <div class="pnote">备注：<?= e($t['remark']) ?></div>
<?php endif; ?>
    <div class="psign">
        <span>填表人：　　　　　</span>
        <span>审核（签名）：　　　　　　</span>
        <span><?= \App\Helpers\PrintSpec::dateBlank() ?></span>
    </div>
</div>
<?php
endforeach;
include OHAS_ROOT . '/app/Views/prints/_bottom.php';
