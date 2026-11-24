<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2" method="get" action="<?= url('workplaces/index') ?>">
        <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="workplaces/index"><?php endif; ?>
        <div class="input-icon">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="场所名称 / 位置说明">
        </div>
        <button class="btn" type="submit"><i class="ti ti-adjustments me-1"></i>筛选</button>
    </form>
    <a class="btn btn-primary" href="<?= url('workplaces/create') ?>"><i class="ti ti-plus me-1"></i>新增作业场所</a>
</div>

<div class="card">
    <?php if (!$data['rows']): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-building-warehouse"></i></div>
            <p class="empty-title">暂无作业场所</p>
            <p class="empty-subtitle text-secondary">请先建立场所（车间/区域），再在其下设置岗位。</p>
            <div class="empty-action">
                <a href="<?= url('workplaces/create') ?>" class="btn btn-primary"><i class="ti ti-plus me-1"></i>新增作业场所</a>
            </div>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <th>场所名称</th><th>位置说明</th><th class="w-1 text-end">岗位数</th>
                    <th class="w-1">状态</th><th>备注</th><th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['rows'] as $wp): ?>
                <tr>
                    <td><?= e($wp['name']) ?></td>
                    <td class="text-secondary"><?= e($wp['location'] ?: '—') ?></td>
                    <td class="text-end"><?= (int)$wp['post_count'] ?></td>
                    <td><span class="badge <?= $wp['status'] === '启用' ? 'bg-green-lt' : 'bg-secondary-lt' ?>"><?= e($wp['status']) ?></span></td>
                    <td class="text-secondary"><?= e($wp['remark'] ?: '—') ?></td>
                    <td class="text-nowrap text-secondary">
                        <a class="text-body me-2" href="<?= url('detections/yearView', ['workplace_id' => (int)$wp['id']]) ?>" title="该场所岗位历年检测报告与原件状态"><i class="ti ti-calendar-stats me-1"></i>年度检测</a>
                        <a class="text-body me-2" href="<?= url('workplaces/edit', ['id' => $wp['id']]) ?>"><i class="ti ti-pencil me-1"></i>编辑</a>
                        <a class="text-danger" href="#" data-del="<?= $wp['id'] ?>" data-name="<?= e($wp['name']) ?>"><i class="ti ti-trash me-1"></i>删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php include OHAS_ROOT . '/app/Views/partials/pagination.php'; ?>
</div>

<form id="del-form" method="post" action="<?= url('workplaces/delete') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="">
</form>
