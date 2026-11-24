<?php
/** 检测报告列表：年度/类别筛选 + 编号/机构检索 + 年度统计小卡（监测检测档案口径） */
?>
<div class="row row-deck row-cards mb-3">
    <div class="col-6 col-sm-4 col-xl-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center g-2">
                    <div class="col-auto"><span class="avatar bg-blue-lt"><i class="ti ti-file-analytics fs-2"></i></span></div>
                    <div class="col">
                        <div class="subheader"><?= e(date('Y')) ?> 年检测报告</div>
                        <div class="h2 mb-0"><?= (int)$stats['reports'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4 col-xl-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center g-2">
                    <div class="col-auto"><span class="avatar bg-cyan-lt"><i class="ti ti-point-dots fs-2"></i></span></div>
                    <div class="col">
                        <div class="subheader"><?= e(date('Y')) ?> 年检测点</div>
                        <div class="h2 mb-0"><?= (int)$stats['items'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4 col-xl-3">
        <div class="card card-sm">
            <?php if ($stats['exceeds'] > 0): ?><div class="card-status-top bg-danger"></div><?php endif; ?>
            <div class="card-body">
                <div class="row align-items-center g-2">
                    <div class="col-auto"><span class="avatar <?= $stats['exceeds'] > 0 ? 'bg-red-lt' : 'bg-green-lt' ?>"><i class="ti ti-alert-triangle fs-2"></i></span></div>
                    <div class="col">
                        <div class="subheader">超标检测点</div>
                        <div class="h2 mb-0"><?= (int)$stats['exceeds'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4 col-xl-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center g-2">
                    <div class="col-auto"><span class="avatar bg-blue-lt"><i class="ti ti-ruler fs-2"></i></span></div>
                    <div class="col">
                        <div class="subheader">限值判定口径</div>
                        <div class="subheader text-green">GBZ 2.1-2019 / 2.2-2007</div>
                        <div class="small text-secondary mt-1">化学因素 / 物理因素现行接触限值</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2" method="get" action="<?= url('detections/index') ?>">
        <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="detections/index"><?php endif; ?>
        <select class="form-select w-auto" name="year">
            <option value="">全部年度</option>
            <?php foreach ($years as $yOpt): ?>
                <option value="<?= e($yOpt) ?>" <?= $yOpt === $year ? 'selected' : '' ?>><?= e($yOpt) ?> 年</option>
            <?php endforeach; ?>
        </select>
        <select class="form-select w-auto" name="category">
            <option value="">全部类别</option>
            <?php foreach ($categories as $cOpt): ?>
                <option value="<?= e($cOpt) ?>" <?= $cOpt === $category ? 'selected' : '' ?>><?= e($cOpt) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="input-icon">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="报告编号 / 检测机构">
        </div>
        <button class="btn" type="submit"><i class="ti ti-adjustments me-1"></i>筛选</button>
    </form>
    <div class="d-flex gap-2">
        <a class="btn" href="<?= url('prints/sheet', ['t' => '表4-3']) ?>" target="_blank" title="检测评价汇总表（表4-3）打印"><i class="ti ti-printer me-1"></i>打印汇总表</a>
        <a class="btn" href="<?= url('detections/items') ?>"><i class="ti ti-list-search me-1"></i>检测结果查询</a>
        <a class="btn" href="<?= url('detections/yearView') ?>"><i class="ti ti-calendar-stats me-1"></i>年度视图</a>
        <a class="btn btn-primary" href="<?= url('detections/create') ?>"><i class="ti ti-plus me-1"></i>登记检测报告</a>
    </div>
</div>

<div class="card">
    <?php if (!$data['rows']): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-file-analytics"></i></div>
            <p class="empty-title">暂无检测报告</p>
            <p class="empty-subtitle text-secondary">取得职业病危害因素检测报告后在此登记（编号、机构、日期），再逐条录入检测明细；明细将按岗位自动归集到劳动者信息卡（表6-2）。</p>
            <div class="empty-action">
                <a href="<?= url('detections/create') ?>" class="btn btn-primary"><i class="ti ti-plus me-1"></i>登记检测报告</a>
            </div>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <th>报告编号</th><th>类别</th><th>检测机构</th><th>检测日期</th><th>报告日期</th>
                    <th class="text-center">明细 / 超标</th><th class="w-1">合格率</th>
                    <th>报告原件</th><th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['rows'] as $r): ?>
                <tr>
                    <td><a class="font-monospace text-body" href="<?= url('detections/show', ['id' => $r['id']]) ?>"><?= e($r['report_no']) ?></a></td>
                    <td><span class="badge bg-blue-lt"><?= e($r['category']) ?></span></td>
                    <td class="text-nowrap"><?= e($r['agency']) ?></td>
                    <td class="font-monospace"><?= d($r['test_date']) ?></td>
                    <td class="font-monospace text-secondary"><?= d($r['report_date']) ?></td>
                    <td class="text-center font-monospace">
                        <?= (int)$r['item_count'] ?> /
                        <?php if ((int)$r['exceed_count'] > 0): ?><span class="text-red"><?= (int)$r['exceed_count'] ?></span>
                        <?php else: ?>0<?php endif; ?>
                        <?php if ((int)($r['undetermined_count'] ?? 0) > 0): ?><div class="small text-secondary">未判定 <?= (int)$r['undetermined_count'] ?></div><?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$r['item_count'] > 0 && (int)($r['undetermined_count'] ?? 0) >= (int)$r['item_count']): ?>
                            <span class="text-secondary">全部未判定</span>
                        <?php elseif ((int)$r['item_count'] > 0): ?>
                            <div class="progress progress-sm w-100" title="合格点/已判定点">
                                <div class="progress-bar bg-<?= $r['pass_ratio'] >= 100 ? 'green' : ($r['pass_ratio'] >= 80 ? 'blue' : 'red') ?>"
                                     style="width: <?= min(100, (int)$r['pass_ratio']) ?>%"></div>
                            </div>
                        <?php else: ?>
                            <span class="text-secondary">未录明细</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$r['doc_count'] > 0): ?>
                            <a href="<?= url('documents/download', ['id' => $r['doc_id'], 'inline' => 1]) ?>" target="_blank"><i class="ti ti-file-upload me-1"></i>查看<?= (int)$r['doc_count'] > 1 ? '（' . (int)$r['doc_count'] . '）' : '' ?></a>
                        <?php else: ?>
                            <span class="text-secondary">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap text-secondary">
                        <a class="text-body me-2" href="<?= url('detections/show', ['id' => $r['id']]) ?>"><i class="ti ti-list-details me-1"></i>明细</a>
                        <a class="text-body me-2" href="<?= url('detections/edit', ['id' => $r['id']]) ?>"><i class="ti ti-pencil"></i>编辑</a>
                        <a class="text-danger" href="#" data-del="<?= $r['id'] ?>" data-name="<?= e($r['report_no'] . ' ' . $r['agency']) ?>"><i class="ti ti-trash me-1"></i>删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php include OHAS_ROOT . '/app/Views/partials/pagination.php'; ?>
</div>

<form id="del-form" method="post" action="<?= url('detections/delete') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="">
</form>
