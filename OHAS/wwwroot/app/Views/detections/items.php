<?php
/** 检测结果查询：跨报告按年度/场所/岗位/危害因素/判定/关键字筛选全部检测明细 */
$f = $filters;
?>
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="text-secondary small">
            <i class="ti ti-info-circle me-1"></i>
            汇总历年全部检测报告的明细数据，限值判定口径：<b>GBZ 2.1-2019</b>（含第2号修改单，化学有害因素）与 <b>GBZ 2.2-2007</b>（物理因素）；
            个人检测结果按岗位自动归集到信息卡查看页与表6-2 打印。
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2" method="get" action="<?= url('detections/items') ?>">
        <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="detections/items"><?php endif; ?>
        <select class="form-select w-auto" name="year">
            <option value="">全部年度</option>
            <?php foreach ($years as $yOpt): ?>
                <option value="<?= e($yOpt) ?>" <?= $yOpt === $f['year'] ? 'selected' : '' ?>><?= e($yOpt) ?> 年</option>
            <?php endforeach; ?>
        </select>
        <select class="form-select w-auto" name="workplace_id">
            <option value="">全部场所</option>
            <?php foreach ($workplaces as $wp): ?>
                <option value="<?= (int)$wp['id'] ?>" <?= (int)$wp['id'] === $f['workplace_id'] ? 'selected' : '' ?>><?= e($wp['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select w-auto" name="post_id">
            <option value="">全部岗位</option>
            <?php foreach ($posts as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === $f['post_id'] ? 'selected' : '' ?>><?= e($p['workplace_name']) ?> · <?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select w-auto" name="hazard_id" data-hazard-picker data-placeholder="全部危害因素">
            <option value="">全部危害因素</option>
            <?php foreach ($hazards as $cat => $group): ?>
                <optgroup label="<?= e($cat) ?>">
                    <?php foreach ($group as $h): ?>
                        <option value="<?= (int)$h['id'] ?>"<?= (string)$h['cas_no'] !== '' ? ' data-cas="' . e($h['cas_no']) . '"' : '' ?><?= $h['catalog_no'] ? ' data-no="' . (int)$h['catalog_no'] . '"' : '' ?><?= (int)$h['id'] === $f['hazard_id'] ? ' selected' : '' ?>><?= e($h['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <select class="form-select w-auto" name="verdict">
            <option value="">全部判定</option>
            <?php foreach (['合格', '超标', '未判定'] as $vOpt): ?>
                <option value="<?= e($vOpt) ?>" <?= $vOpt === $f['verdict'] ? 'selected' : '' ?>><?= e($vOpt) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="input-icon">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= e($f['q']) ?>" placeholder="检测点 / 报告编号 / 机构">
        </div>
        <button class="btn" type="submit"><i class="ti ti-adjustments me-1"></i>筛选</button>
    </form>
    <div class="d-flex gap-2">
        <a class="btn" href="<?= url('detections/index') ?>"><i class="ti ti-file-analytics me-1"></i>检测报告</a>
    </div>
</div>

<div class="card">
    <?php if (!$data['rows']): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-list-search"></i></div>
            <p class="empty-title">没有符合条件的检测明细</p>
            <p class="empty-subtitle text-secondary">请调整筛选条件；若还未登记检测报告，可到「检测报告与明细」开始录入。</p>
            <div class="empty-action">
                <a href="<?= url('detections/create') ?>" class="btn btn-primary"><i class="ti ti-plus me-1"></i>登记检测报告</a>
            </div>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <th>检测日期</th><th>报告编号</th><th>检测机构</th><th>作业场所</th><th>岗位</th>
                    <th>危害因素</th><th>检测点</th><th>检测结果</th><th>接触限值</th><th class="w-1">判定</th><th>防护措施</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['rows'] as $it): [$vl, $vc] = \App\Models\DetectionItem::verdictBadge($it['verdict']); ?>
                <tr>
                    <td class="font-monospace"><?= d($it['test_date']) ?></td>
                    <td><a class="font-monospace text-body" href="<?= url('detections/show', ['id' => $it['report_id']]) ?>"><?= e($it['report_no']) ?></a></td>
                    <td class="text-secondary text-nowrap"><?= e($it['agency']) ?></td>
                    <td><?= e($it['workplace_name'] ?: '—') ?></td>
                    <td><?php if ($it['post_name']): ?><?= e($it['post_name']) ?><?php else: ?><span class="text-secondary">未指定</span><?php endif; ?></td>
                    <td><?= e($it['hazard_name'] ?: '—') ?></td>
                    <td class="text-secondary"><?= e($it['test_point'] ?: '—') ?></td>
                    <td class="font-monospace"><?= e($it['result'] ?: '—') ?><?= $it['unit'] !== '' ? ' <span class="text-secondary">' . e($it['unit']) . '</span>' : '' ?>
                        <?php if (($it['sample_type'] ?? '') !== ''): ?><div class="small text-secondary"><?= e($it['sample_type']) ?></div><?php endif; ?></td>
                    <td class="text-secondary small"><?= e($it['limit_value'] ?: '—') ?></td>
                    <td><span class="badge <?= $vc ?>"><?= e($vl) ?></span></td>
                    <td class="text-secondary small"><?= e($it['protection'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php include OHAS_ROOT . '/app/Views/partials/pagination.php'; ?>
</div>
