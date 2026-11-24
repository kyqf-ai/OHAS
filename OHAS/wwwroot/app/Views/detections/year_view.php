<?php
/**
 * 检测报告年度视图：按 作业场所 → 岗位 → 年度 展示检测报告与原件状态。
 * 与个人页检测列对称：已归档（间接）/ 仅登记 / 待归档；点格子展开该年报告明细。
 */
use App\Helpers\WorkerArchive;

$badge = fn (string $state) => WorkerArchive::badge($state);
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
        <a href="<?= url('detections/index') ?>" class="text-secondary"><i class="ti ti-arrow-left me-1"></i>返回检测报告列表</a>
        <div class="text-secondary mt-1 small">
            行 = 岗位（按作业场所分组），列 = 年度。格子含义与个人页「检测报告（间接）」列一致：
            <span class="badge bg-teal-lt">已归档（间接）</span> 报告已传原件；
            <span class="badge bg-yellow-lt">仅登记</span> 有报告无原件；
            <span class="badge bg-red-lt">待归档</span> 配置了危害因素的岗位当年无报告。
        </div>
    </div>
    <div class="d-flex gap-2">
        <form method="get" action="<?= url('detections/yearView') ?>" class="d-flex gap-2">
            <select class="form-select form-select-sm" name="workplace_id" style="width:auto;">
                <option value="">全部场所</option>
                <?php foreach ($workplaces as $w): ?>
                    <option value="<?= (int)$w['id'] ?>" <?= $workplaceId === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="range" value="<?= e($range) ?>">
            <button class="btn btn-sm" type="submit"><i class="ti ti-filter me-1"></i>筛选</button>
        </form>
        <div class="btn-group btn-group-sm">
            <a class="btn <?= $range === '3y' ? 'btn-primary' : '' ?>" href="<?= e(url('detections/yearView', array_filter(['workplace_id' => $workplaceId, 'range' => '3y'], fn ($v) => $v !== 0))) ?>">近 3 年</a>
            <a class="btn <?= $range === '5y' ? 'btn-primary' : '' ?>" href="<?= e(url('detections/yearView', array_filter(['workplace_id' => $workplaceId, 'range' => '5y'], fn ($v) => $v !== 0))) ?>">近 5 年</a>
            <a class="btn <?= $range === 'all' ? 'btn-primary' : '' ?>" href="<?= e(url('detections/yearView', array_filter(['workplace_id' => $workplaceId, 'range' => 'all'], fn ($v) => $v !== 0))) ?>">全部</a>
        </div>
    </div>
</div>

<?php $sumTxt = ''; foreach (['done_indirect' => '已归档（间接）', 'reg_only' => '仅登记', 'todo' => '待归档'] as $st => $lbl): if ($summary[$st] > 0) $sumTxt .= '<span class="badge ' . $badge($st)[1] . ' me-1">' . $lbl . ' ' . $summary[$st] . '</span>'; endforeach; ?>
<?php if ($sumTxt !== ''): ?>
<div class="mb-3"><?= $sumTxt ?></div>
<?php endif; ?>

<div class="card">
    <?php if (!$rows): ?>
    <div class="empty">
        <div class="empty-icon"><i class="ti ti-calendar-stats"></i></div>
        <p class="empty-title">暂无可展示的岗位</p>
        <p class="empty-subtitle text-secondary">先在「岗位与危害配置」为接害岗位配置危害因素，或登记检测报告。</p>
        <div class="empty-action">
            <a class="btn btn-primary" href="<?= url('detections/create') ?>"><i class="ti ti-plus me-1"></i>登记检测报告</a>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-sm table-hover">
            <thead>
                <tr>
                    <th style="min-width:170px;">作业场所 / 岗位</th>
                    <?php foreach ($years as $y): ?><th class="text-center"><?= $y ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $wpKey => $r): ?>
                <tr class="table-secondary">
                    <td colspan="<?= count($years) + 1 ?>" class="fw-bold"><i class="ti ti-building-factory-2 me-1 text-secondary"></i><?= e($r['name']) ?></td>
                </tr>
                <?php foreach ($r['posts'] as $poKey => $po): ?>
                <tr>
                    <td>
                        <?= e($po['name']) ?>
                        <?php if ($po['hazard_count'] > 0): ?><span class="badge bg-blue-lt"><?= (int)$po['hazard_count'] ?> 项危害</span><?php endif; ?>
                        <?php if ($po['stopped']): ?><span class="badge bg-secondary-lt">已停用</span><?php endif; ?>
                    </td>
                    <?php foreach ($years as $y):
                        $c = $states[$wpKey][$poKey][$y] ?? ['state' => 'todo', 'reports' => [], 'n' => 0];
                        [$lbl, $cls] = $badge($c['state']);
                        $show = $c['state'] !== 'todo' || !empty($po['needs']);
                    ?>
                    <td class="text-center text-nowrap">
                        <?php if (!$show): ?>
                            <span class="text-secondary">—</span>
                        <?php elseif ($c['state'] === 'todo'): ?>
                            <span class="badge <?= $cls ?>"><?= e($lbl) ?></span>
                        <?php else: ?>
                            <a href="#yc-<?= (int)$wpKey ?>-<?= (int)$poKey ?>-<?= $y ?>" data-cell-toggle="yc-<?= (int)$wpKey ?>-<?= (int)$poKey ?>-<?= $y ?>" class="badge <?= $cls ?> text-decoration-none"><?= e($lbl) ?> <?= (int)$c['n'] ?></a>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php foreach ($years as $y):
                    $c = $states[$wpKey][$poKey][$y] ?? ['state' => 'todo', 'reports' => [], 'n' => 0];
                    if ($c['state'] === 'todo' || !($c['reports'] ?? [])) continue;
                ?>
                <tr class="cell-detail d-none" id="yc-<?= (int)$wpKey ?>-<?= (int)$poKey ?>-<?= $y ?>">
                    <td colspan="<?= count($years) + 1 ?>" class="bg-secondary-lt py-2">
                        <div class="fw-bold mb-1"><?= e($r['name']) ?> · <?= e($po['name']) ?> · <?= $y ?> 年</div>
                        <?php foreach ($c['reports'] as $rep): ?>
                        <div class="ms-3 mb-1">
                            <i class="ti ti-file-analytics me-1 text-secondary"></i>
                            <a class="text-body" href="<?= url('detections/show', ['id' => (int)$rep['id']]) ?>"><?= e($rep['report_no']) ?></a>
                            <span class="text-secondary small"><?= e($rep['test_date']) ?> · <?= e($rep['category']) ?></span>
                            <?php if ($rep['exceeds'] > 0): ?><span class="badge bg-red-lt">超标 <?= (int)$rep['exceeds'] ?> 点</span><?php endif; ?>
                            <?php if ($rep['has_doc']): ?>
                                <span class="badge bg-green-lt">有原件</span>
                            <?php else: ?>
                                <span class="badge bg-yellow-lt">无原件</span>
                                <a class="btn btn-sm btn-primary py-0 px-1" href="<?= url('detections/show', ['id' => (int)$rep['id']]) ?>#docs"><i class="ti ti-upload"></i> 去上传</a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
/* 格子展开/收起（与个人页同款交互） */
document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-cell-toggle]');
    if (!t) return;
    e.preventDefault();
    var row = document.getElementById(t.getAttribute('data-cell-toggle'));
    if (row) { row.classList.toggle('d-none'); }
});
</script>
