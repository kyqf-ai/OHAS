<?php
/** 工作台：统计卡片 · 数据准备向导 · 基础概览 · 快捷入口 · 最近建档 */
$statCards = [
    ['label' => '劳动者总数',     'value' => $stats['workers'],        'icon' => 'users',               'bg' => 'bg-primary-lt', 'note' => ''],
    ['label' => '在岗人数',       'value' => $stats['workers_onjob'],  'icon' => 'user-check',          'bg' => 'bg-blue-lt',    'note' => ''],
    ['label' => '在岗接害人数',   'value' => $stats['exposed'],        'icon' => 'biohazard',           'bg' => 'bg-orange-lt',  'note' => $stats['workers_no_history'] > 0 ? ('另有 ' . (int)$stats['workers_no_history'] . ' 人未补职业史') : '在职 · 岗位已配危害', 'url' => url('workers/index')],
    ['label' => '今年检测报告',   'value' => $stats['reports_year'],   'icon' => 'file-analytics',      'bg' => 'bg-blue-lt',    'note' => '超标 ' . (int)$stats['detect_exceed'] . ' 点', 'url' => url('detections/index')],
    ['label' => '今年体检批次',   'value' => $stats['exam_batches'],   'icon' => 'calendar-stats',      'bg' => 'bg-cyan-lt',    'note' => '实检 ' . (int)$stats['exam_records'] . ' 人次 · 待出 ' . (int)$stats['exam_pending'] . ' 人', 'url' => url('exams/index')],
    ['label' => '今年体检异常', 'value' => $stats['exam_abnormal'],  'icon' => 'heart-rate-monitor',  'bg' => 'bg-yellow-lt',  'note' => '本年未闭环 ' . (int)($dispositionStats['register'] + $dispositionStats['pending'] + $dispositionStats['notice'] + $dispositionStats['nonwritten'] + $dispositionStats['notice_doc'] + $dispositionStats['transfer']) . ' 人次 · 点此跟踪', 'url' => url('exams/records', ['abnormal' => 1])],
];
?>
<div class="row row-deck row-cards mb-3">
    <?php foreach ($statCards as $sc): ?>
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="card card-sm h-100">
            <div class="card-body">
                <div class="row align-items-center g-2">
                    <div class="col-auto">
                        <span class="avatar <?= $sc['bg'] ?>"><i class="ti ti-<?= $sc['icon'] ?> fs-2"></i></span>
                    </div>
                    <div class="col">
                        <div class="subheader"><?= e($sc['label']) ?></div>
                        <?php if (!empty($sc['url'])): ?>
                        <a class="h2 mb-0 d-block text-body text-decoration-none" href="<?= e($sc['url']) ?>"><?= (int)$sc['value'] ?></a>
                        <?php else: ?>
                        <div class="h2 mb-0"><?= (int)$sc['value'] ?></div>
                        <?php endif; ?>
                        <?php if ($sc['note']): ?><div class="small text-secondary"><?= e($sc['note']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-3">
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title">归档资料自查</h3>
                <div class="card-subtitle">按六类档案逐项核查 36 项应归档资料，列示缺项与存在缺口的劳动者</div>
                <div class="card-actions">
                    <a class="btn btn-sm btn-primary" href="<?= url('archive/checklist') ?>"><i class="ti ti-list-check me-1"></i>查看自查清单</a>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="d-flex flex-wrap gap-2 align-items-center pt-1">
                    <?php foreach (['done' => '已归档', 'partial' => '部分', 'todo' => '待归档', 'na' => '不适用'] as $st => $lbl):
                        [$t, $cls] = \App\Helpers\WorkerArchive::badge($st); ?>
                        <a class="text-decoration-none" href="<?= url('archive/checklist') ?>">
                            <span class="badge <?= $cls ?> fs-5"><?= $lbl ?> <?= (int)($checklistStats[$st] ?? 0) ?> 项</span>
                        </a>
                    <?php endforeach; ?>
                    <span class="badge bg-azure-lt fs-5">按需 <?= (int)($checklistStats['event'] ?? 0) ?> 项</span>
                    <?php if ((int)($expireStats['expired'] ?? 0) > 0): ?>
                        <a class="text-decoration-none" href="<?= url('archive/expiring') ?>">
                            <span class="badge bg-red-lt fs-5"><i class="ti ti-alert-triangle me-1"></i>已过期 <?= (int)$expireStats['expired'] ?> 份</span>
                        </a>
                    <?php endif; ?>
                    <?php if ((int)($expireStats['soon'] ?? 0) > 0): ?>
                        <a class="text-decoration-none" href="<?= url('archive/expiring') ?>">
                            <span class="badge bg-yellow-lt fs-5"><i class="ti ti-clock me-1"></i><?= (int)($expireStats['soon_days'] ?? 30) ?> 天内到期 <?= (int)$expireStats['soon'] ?> 份</span>
                        </a>
                    <?php endif; ?>
                    <a class="ms-2 text-decoration-none" href="<?= url('archive/checklist') ?>#gap-workers">
                        <span class="badge bg-red-lt fs-5"><i class="ti ti-user-search me-1"></i>有缺口 <?= (int)$gapCount ?> 人</span>
                    </a>
                </div>
                <?php if (!empty($gapWorkers)): ?>
                <div class="list-group list-group-flush list-group-hoverable mt-2">
                    <?php foreach ($gapWorkers as $gid => $g): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-1" href="<?= url('workers/files', ['id' => $gid]) ?>">
                        <i class="ti ti-user text-secondary"></i>
                        <span class="text-body"><?= e($g['name']) ?></span>
                        <span class="font-monospace text-secondary small"><?= e($g['archive_no']) ?></span>
                        <span class="badge bg-red-lt ms-auto"><?= (int)$g['gaps'] ?> 项缺口</span>
                        <span class="text-secondary small">去补录<i class="ti ti-chevron-right ms-1"></i></span>
                    </a>
                    <?php endforeach; ?>
                    <?php if ($gapCount > count($gapWorkers)): ?>
                    <a class="list-group-item list-group-item-action text-secondary py-1" href="<?= url('archive/checklist') ?>#gap-workers">
                        其余 <?= (int)($gapCount - count($gapWorkers)) ?> 人，见资料清单页<i class="ti ti-chevron-right ms-1"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title">数据准备向导</h3>
                <div class="card-subtitle">按序完成即可开始建档</div>
            </div>
            <div class="list-group list-group-flush list-group-hoverable">
                <?php foreach ($checklist as $i => $item): ?>
                <a class="list-group-item list-group-item-action d-flex gap-3 py-2 align-items-center" href="<?= e($item['url']) ?>">
                    <span class="avatar <?= $item['done'] ? 'bg-green-lt' : 'bg-primary-lt' ?>">
                        <?= $item['done'] ? '<i class="ti ti-check"></i>' : $i + 1 ?>
                    </span>
                    <div>
                        <div><?= e($item['label']) ?><?= $item['done'] ? '<span class="badge bg-green-lt ms-2">已完成</span>' : '' ?></div>
                        <div class="text-secondary small"><?= e($item['hint']) ?></div>
                    </div>
                    <div class="ms-auto text-secondary"><i class="ti ti-chevron-right"></i></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">基础数据概览</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table table-nowrap">
                    <tr><td class="text-secondary">单位名称</td><td class="text-end"><?= e($company['name'] !== '' ? $company['name'] : '未填写') ?></td></tr>
                    <tr><td class="text-secondary">启用作业场所</td><td class="text-end"><?= (int)$stats['workplaces'] ?> 个</td></tr>
                    <tr><td class="text-secondary">启用岗位</td><td class="text-end"><?= (int)$stats['posts'] ?> 个</td></tr>
                    <tr><td class="text-secondary">启用危害因素</td><td class="text-end"><?= (int)$stats['hazards'] ?> 项</td></tr>
                    <tr class="<?= $stats['posts_no_hazard'] > 0 ? 'table-warning' : '' ?>">
                        <td class="text-secondary">未配置危害的岗位</td>
                        <td class="text-end"><?= (int)$stats['posts_no_hazard'] ?> 个<?= $stats['posts_no_hazard'] > 0 ? ' <i class="ti ti-alert-triangle text-yellow"></i>' : '' ?></td>
                    </tr>
                    <tr class="<?= $stats['workers_no_history'] > 0 ? 'table-warning' : '' ?>">
                        <td class="text-secondary">在岗但无职业史</td>
                        <td class="text-end"><?= (int)$stats['workers_no_history'] ?> 人<?= $stats['workers_no_history'] > 0 ? ' <i class="ti ti-alert-triangle text-yellow"></i>' : '' ?></td>
                    </tr>
                </table>
            </div>
            <?php if ($stats['posts_no_hazard'] > 0): ?>
            <div class="card-footer">
                <span class="text-yellow"><i class="ti ti-alert-triangle me-1"></i></span>有岗位未配置危害因素，将影响表4-2汇总与表6-2归集，请尽快到「岗位设置」补齐。
            </div>
            <?php endif; ?>
        </div>

        <div class="card mb-3">
            <?php if ($stats['exam_abnormal'] > 0): ?><div class="card-status-top bg-danger"></div><?php endif; ?>
            <div class="card-header">
                <h3 class="card-title">体检异常提醒</h3>
                <div class="card-subtitle">表5-2 异常全量口径 · 本年待登记 <?= (int)($dispositionStats['register'] ?? 0) ?> / 待处置 <?= (int)($dispositionStats['pending'] ?? 0) ?> / 待告知 <?= (int)($dispositionStats['notice'] ?? 0) ?> / 非书面 <?= (int)($dispositionStats['nonwritten'] ?? 0) ?> / 待原件 <?= (int)($dispositionStats['notice_doc'] ?? 0) ?> / 待调离 <?= (int)($dispositionStats['transfer'] ?? 0) ?></div>
                <div class="card-actions">
                    <a class="btn btn-sm" href="<?= url('archive/clues') ?>">线索名单 <i class="ti ti-chevron-right ms-1"></i></a>
                    <a class="btn btn-sm" href="<?= url('exams/records', ['abnormal' => 1]) ?>">处置跟踪 <i class="ti ti-chevron-right ms-1"></i></a>
                </div>
            </div>
            <?php if (!$abnormalRecent): ?>
                <div class="card-body">
                    <div class="empty empty-sm py-4">
                        <div class="empty-icon"><i class="ti ti-circle-check text-green"></i></div>
                        <p class="empty-title">暂无异常结论记录</p>
                    </div>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>检查日期</th><th>姓名</th><th>结论</th><th>处置状态</th><th class="w-1"></th></tr></thead>
                    <tbody>
                        <?php foreach ($abnormalRecent as $ab):
                            [$bl, $bc] = \App\Models\ExamRecord::conclusionBadge($ab['conclusion']);
                            $disp = \App\Models\ExamRecord::dispositionStatus($ab); ?>
                        <tr>
                            <td class="font-monospace"><?= e($ab['exam_date']) ?></td>
                            <td><?= e($ab['worker_name']) ?></td>
                            <td><span class="badge <?= $bc ?>"><?= e($bl) ?></span></td>
                            <td><?php if ($disp): ?><span class="badge <?= $disp['class'] ?>"><?= e($disp['label']) ?></span><?php else: ?>—<?php endif; ?></td>
                            <td class="text-nowrap text-secondary">
                                <a class="text-body me-2" href="<?= url('workers/show', ['id' => $ab['worker_id']]) ?>"><i class="ti ti-id me-1"></i>信息卡</a>
                                <a class="text-body" href="<?= url('exams/recordEdit', ['id' => $ab['id']]) ?>"><i class="ti ti-pencil me-1"></i>处置登记</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">快捷入口</h3>
            </div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a class="btn btn-primary" href="<?= url('workers/create') ?>"><i class="ti ti-user-plus me-1"></i>新建劳动者信息卡</a>
                <a class="btn btn-primary" href="<?= url('exams/create') ?>"><i class="ti ti-calendar-plus me-1"></i>登记体检批次</a>
                <a class="btn btn-primary" href="<?= url('detections/create') ?>"><i class="ti ti-file-plus me-1"></i>登记检测报告</a>
                <a class="btn" href="<?= url('workers/import') ?>"><i class="ti ti-file-import me-1"></i>CSV 批量导入</a>
                <a class="btn" href="<?= url('posts/create') ?>"><i class="ti ti-briefcase me-1"></i>新建岗位</a>
                <a class="btn" href="<?= url('hazards/create') ?>"><i class="ti ti-biohazard me-1"></i>新增危害因素</a>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">最近建档</h3>
        <div class="card-actions">
            <a class="btn btn-sm" href="<?= url('workers/index') ?>">全部 <i class="ti ti-chevron-right ms-1"></i></a>
        </div>
    </div>
    <?php if (!$recent): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-id"></i></div>
            <p class="empty-title">还没有劳动者档案</p>
            <p class="empty-subtitle text-secondary">从「劳动者信息卡」或「CSV 批量导入」开始建档。</p>
            <div class="empty-action">
                <a href="<?= url('workers/create') ?>" class="btn btn-primary"><i class="ti ti-user-plus me-1"></i>新建劳动者信息卡</a>
            </div>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <th>档案号</th><th>姓名</th><th>性别</th><th>当前岗位</th>
                    <th>状态</th><th>入职时间</th><th>建档时间</th><th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $w): ?>
                <tr>
                    <td class="font-monospace"><?= e($w['archive_no']) ?></td>
                    <td><?= e($w['name']) ?></td>
                    <td><?= e($w['gender'] ?: '—') ?></td>
                    <td><?= e($w['current_post']) ?></td>
                    <td><span class="badge <?= $w['status'] === '在职' ? 'bg-green-lt' : 'bg-secondary-lt' ?>"><?= e($w['status']) ?></span></td>
                    <td><?= d($w['entry_date']) ?></td>
                    <td class="text-secondary"><?= e(mb_substr((string)$w['created_at'], 0, 10)) ?></td>
                    <td class="text-nowrap text-secondary">
                        <a class="text-muted ms-2" href="<?= url('workers/edit', ['id' => $w['id']]) ?>" title="编辑"><i class="ti ti-pencil"></i></a>
                        <a class="text-muted ms-2" href="<?= url('workers/printCard', ['id' => $w['id']]) ?>" target="_blank" title="打印表6-1"><i class="ti ti-printer"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
