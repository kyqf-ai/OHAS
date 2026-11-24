<?php
/** 体检批次列表：年度/种类筛选 + 机构检索 + 年度统计小卡 */
?>
<div class="row row-deck row-cards mb-3">
    <div class="col-6 col-sm-4 col-xl-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center g-2">
                    <div class="col-auto"><span class="avatar bg-green-lt"><i class="ti ti-calendar-stats fs-2"></i></span></div>
                    <div class="col">
                        <div class="subheader"><?= e(date('Y')) ?> 年批次</div>
                        <div class="h2 mb-0"><?= (int)$yearStats['batches'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4 col-xl-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center g-2">
                    <div class="col-auto"><span class="avatar bg-cyan-lt"><i class="ti ti-heart-rate-monitor fs-2"></i></span></div>
                    <div class="col">
                        <div class="subheader"><?= e(date('Y')) ?> 年实检人次</div>
                        <div class="h2 mb-0"><?= (int)$yearStats['records'] ?></div>
                        <div class="text-secondary small"><?= (int)$yearStats['pending'] ?> 人待出结论</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4 col-xl-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center g-2">
                    <div class="col-auto"><span class="avatar bg-orange-lt"><i class="ti ti-alert-triangle fs-2"></i></span></div>
                    <div class="col">
                        <div class="subheader">待处置异常结论</div>
                        <div class="h2 mb-0"><?= \App\Models\ExamRecord::abnormalCount() ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4 col-xl-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center g-2">
                    <div class="col-auto"><span class="avatar bg-blue-lt"><i class="ti ti-file-text fs-2"></i></span></div>
                    <div class="col">
                        <div class="subheader">检查结论口径</div>
                        <div class="subheader text-green">GBZ 188-2025</div>
                        <div class="small text-secondary mt-1">2026-08-01 起施行</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2" method="get" action="<?= url('exams/index') ?>">
        <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="exams/index"><?php endif; ?>
        <select class="form-select w-auto" name="year">
            <option value="">全部年度</option>
            <?php foreach ($years as $yOpt): ?>
                <option value="<?= e($yOpt) ?>" <?= $yOpt === $year ? 'selected' : '' ?>><?= e($yOpt) ?> 年</option>
            <?php endforeach; ?>
        </select>
        <select class="form-select w-auto" name="type">
            <option value="">全部检查种类</option>
            <?php foreach ($types as $tOpt): ?>
                <option value="<?= e($tOpt) ?>" <?= $tOpt === $type ? 'selected' : '' ?>><?= e($tOpt) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="input-icon">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="检查机构">
        </div>
        <button class="btn" type="submit"><i class="ti ti-adjustments me-1"></i>筛选</button>
    </form>
    <div class="d-flex gap-2">
        <a class="btn" href="<?= url('prints/sheet', ['t' => '表5-1']) ?>" target="_blank" title="职业健康检查结果汇总表（表5-1）打印"><i class="ti ti-printer me-1"></i>打印汇总表</a>
        <a class="btn" href="<?= url('exams/records') ?>"><i class="ti ti-list-search me-1"></i>检查记录查询</a>
        <a class="btn btn-primary" href="<?= url('exams/create') ?>"><i class="ti ti-plus me-1"></i>新增体检批次</a>
    </div>
</div>

<div class="card">
    <?php if (!$data['rows']): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-heart-rate-monitor"></i></div>
            <p class="empty-title">暂无体检批次</p>
            <p class="empty-subtitle text-secondary">每年组织职业健康体检后，在此登记批次（机构、日期、应检人数），再生成应检名单逐人登记结果。</p>
            <div class="empty-action">
                <a href="<?= url('exams/create') ?>" class="btn btn-primary"><i class="ti ti-plus me-1"></i>新增体检批次</a>
            </div>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <th>检查日期</th><th>检查种类</th><th>检查机构</th>
                    <th class="text-center">应检 / 实检</th><th class="w-1">完成率</th>
                    <th>批次报告</th><th>备注</th><th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['rows'] as $b): ?>
                <tr>
                    <td class="font-monospace"><?= d($b['exam_date']) ?></td>
                    <td><span class="badge bg-blue-lt"><?= e($b['exam_type']) ?></span></td>
                    <td class="text-nowrap"><?= e($b['agency']) ?></td>
                    <td class="text-center font-monospace" title="应检 / 实检（已出结论）；另有 <?= (int)$b['pending_count'] ?> 人待出结论">
                        <?= (int)$b['planned_count'] ?> / <?= (int)$b['actual_count'] ?>
                        <?php if (!empty($b['over_planned'])): ?>
                            <span class="badge bg-warning-lt ms-1" title="名单或实检人数已超过手工填写的应检人数，请核对并回填应检数">超应检</span>
                        <?php endif; ?>
                        <?php if ((int)$b['pending_count'] > 0): ?>
                            <span class="badge bg-secondary-lt ms-1" title="已生成待检记录、尚未登记结论"><?= (int)$b['pending_count'] ?> 待出</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$b['planned_count'] > 0): ?>
                            <div class="progress progress-sm w-100" title="实检/应检（待出结论不计入实检）">
                                <div class="progress-bar bg-<?= $b['done_ratio'] >= 100 ? 'green' : ($b['done_ratio'] >= 50 ? 'blue' : 'yellow') ?>"
                                     style="width: <?= min(100, (int)$b['done_ratio']) ?>%"></div>
                            </div>
                        <?php else: ?>
                            <span class="text-secondary">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$b['doc_count'] > 0): ?>
                            <a href="<?= url('documents/download', ['id' => $b['doc_id'], 'inline' => 1]) ?>" target="_blank"><i class="ti ti-file-upload me-1"></i>查看<?= (int)$b['doc_count'] > 1 ? '（' . (int)$b['doc_count'] . '）' : '' ?></a>
                        <?php else: ?>
                            <span class="text-secondary">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-secondary"><?= e(mb_substr((string)$b['remark'], 0, 20)) ?><?= mb_strlen((string)$b['remark']) > 20 ? '…' : '' ?></td>
                    <td class="text-nowrap text-secondary">
                        <a class="text-body me-2" href="<?= url('exams/records', ['batch_id' => $b['id']]) ?>"><i class="ti ti-list-details me-1"></i>检查记录</a>
                        <a class="text-body me-2" href="<?= url('exams/pick', ['batch_id' => $b['id']]) ?>"><i class="ti ti-user-plus me-1"></i>应检名单</a>
                        <a class="text-body me-2" href="<?= url('exams/edit', ['id' => $b['id']]) ?>"><i class="ti ti-pencil me-1"></i>编辑</a>
                        <a class="text-danger" href="#" data-del="<?= $b['id'] ?>" data-name="<?= e($b['exam_date'] . ' ' . $b['agency']) ?>"><i class="ti ti-trash me-1"></i>删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php include OHAS_ROOT . '/app/Views/partials/pagination.php'; ?>
</div>

<form id="del-form" method="post" action="<?= url('exams/delete') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="">
</form>
