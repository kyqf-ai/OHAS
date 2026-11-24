<?php
/**
 * 表2-2 职业病危害项目申报基本情况表（Word 版式还原 + 系统数据自动汇总）
 * $records = [['company'=>...]]（单记录）；$places = [['name','items'=>[['name','cnt']],'uniq','sum_dup']]；$stats 汇总口径
 * 版式依据《职业病危害项目申报办法》表样：上半 5 列栅格；下半“职业病危害因素分布情况”跨行（场所×因素；不重复人数按场所合并）。
 */
$c = $records[0]['company'] ?? [];
$s = $stats;
$orient = 'portrait';
$printBtn = '打印表2-2';
$backUrl = $backUrl ?? url('prints/index');
$backLabel = '返回打印中心';
include OHAS_ROOT . '/app/Views/prints/_top.php';
?>
<?php
$scaleSel = '';
foreach (['大', '中', '小', '微'] as $ch) {
    if (mb_substr(trim((string)($c['scale'] ?? '')), 0, 1) === $ch) { $scaleSel = $ch; }
}
$circ = function (string $label, bool $on): string { return $label . ($on ? '&#9679;' : '&#9675;'); };
// 分布表行：至少 8 行（竖排标题需要高度；空白组 3 行供手工补填场所/因素）
$groups = $places;
$bodyRows = 0;
foreach ($groups as $g) { $bodyRows += max(1, count($g['items'])); }
while ($bodyRows < 8 || count($groups) < 2) {
    $k = count($groups) + 1;
    $groups[] = ['name' => '（作业场所' . $k . '）', 'items' => [['name' => '', 'cnt' => null], ['name' => '', 'cnt' => null], ['name' => '…', 'cnt' => null]], 'uniq' => null, 'blank' => true];
    $bodyRows += 3;
}
?>
    <h1 class="ptitle">表2-2&ensp;职业病危害项目申报基本情况表</h1>

    <table class="ptable dense">
        <colgroup><col style="width:17%"><col style="width:29%"><col style="width:20%"><col style="width:10%"><col style="width:24%"></colgroup>
        <tbody>
        <tr>
            <td class="plabel">单位名称</td>
            <td class="left"><?= e($c['name'] ?? '') ?></td>
            <td class="plabel" colspan="2">联系电话：</td>
            <td class="left"><?= e($c['hygienist_phone'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="plabel">单位注册地址</td>
            <td class="left"><?= e($c['reg_address'] ?? '') ?></td>
            <td class="plabel" colspan="2">工作场所地址</td>
            <td class="left"><?= e($c['work_address'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="plabel">申报类别</td>
            <td class="left">初次申报&#9675;变更申报&#9675;</td>
            <td class="plabel" colspan="2">变更原因</td>
            <td class="left">&nbsp;</td>
        </tr>
        <tr>
            <td class="plabel" rowspan="2">企业规模</td>
            <td class="left" rowspan="2"><?= $circ('大', $scaleSel === '大') ?>&nbsp;&nbsp;<?= $circ('中', $scaleSel === '中') ?>&nbsp;&nbsp;<?= $circ('小', $scaleSel === '小') ?>&nbsp;&nbsp;<?= $circ('微', $scaleSel === '微') ?></td>
            <td class="plabel" colspan="2">行业分类</td>
            <td class="left"><?= e($c['industry'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="plabel" colspan="2">注册类型</td>
            <td class="left">&nbsp;</td>
        </tr>
        <tr>
            <td class="plabel">法定代表人</td>
            <td class="left"><?= e($c['legal_person'] ?? '') ?></td>
            <td class="plabel" colspan="2">联系电话</td>
            <td class="left">&nbsp;</td>
        </tr>
        <tr>
            <td class="plabel" rowspan="2">职业卫生管理机构</td>
            <td class="left" rowspan="2">有&#9675;无&#9675;</td>
            <td class="plabel" rowspan="2">职业卫生管理<br>人员数</td>
            <td class="plabel">专职</td>
            <td class="left">&nbsp;</td>
        </tr>
        <tr>
            <td class="plabel">兼职</td>
            <td class="left">&nbsp;</td>
        </tr>
        <tr>
            <td class="plabel">劳动者总人数</td>
            <td class="left"><?= (int)$s['total_workers'] ?> 人（其中女工 <?= (int)$s['female_workers'] ?> 人）</td>
            <td class="plabel" colspan="2">职业病累计人数</td>
            <td class="left"><?= (int)$s['occupational'] ?> 人</td>
        </tr>
        <tr>
            <td class="plabel">接触职业病危害因素种类数（个）</td>
            <td class="left"><?= (int)$s['hazard_kinds'] ?></td>
            <td class="plabel" colspan="2">接触职业病危害因素人数（人）</td>
            <td class="left"><?= (int)$s['exposed_workers'] ?></td>
        </tr>
        </tbody>
    </table>

    <table class="ptable dense" style="margin-top:-1px">
        <colgroup><col style="width:8%"><col style="width:18%"><col style="width:36%"><col style="width:19%"><col style="width:19%"></colgroup>
        <tbody>
        <tr>
            <th rowspan="<?= $bodyRows + 2 ?>" style="writing-mode:vertical-rl;letter-spacing:3px;white-space:nowrap">职业病危害因素分布情况</th>
            <th>作业场所名称</th>
            <th>职业病危害因素名称</th>
            <th>接触人数（可重复）</th>
            <th>接触人数（不重复）</th>
        </tr>
        <?php foreach ($groups as $gi => $g): $n = max(1, count($g['items'])); ?>
            <?php foreach ($g['items'] as $ii => $it): ?>
            <tr class="pempty dense">
                <?php if ($ii === 0): ?><td rowspan="<?= $n ?>"><?= e($g['name']) ?></td><?php endif; ?>
                <td class="left"><?= e($it['name']) ?></td>
                <td><?= $it['cnt'] === null ? '' : (int)$it['cnt'] ?></td>
                <?php if ($ii === 0): ?><td rowspan="<?= $n ?>"><?= $g['uniq'] === null ? '' : (int)$g['uniq'] ?></td><?php endif; ?>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <tr>
            <td class="plabel" colspan="2">合计</td>
            <td><?= (int)$s['sum_dup'] ?></td>
            <td><?= (int)$s['exposed_workers'] ?></td>
        </tr>
        </tbody>
    </table>

    <div class="psign">
        <span>编制：　　　　　　</span>
        <span>审核（签字）：　　　　　　</span>
        <span>编制日期：　　年　　月　　日</span>
    </div>
    <div class="pnote">说明：人数按“场所×因素”统计，“不重复”按场所去重；空项手工填写，申报以系统回执为准。</div>
<?php include OHAS_ROOT . '/app/Views/prints/_bottom.php'; ?>
