<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2" method="get" action="<?= url('posts/index') ?>">
        <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="posts/index"><?php endif; ?>
        <div class="input-icon">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="岗位 / 场所名称">
        </div>
        <button class="btn" type="submit"><i class="ti ti-adjustments me-1"></i>筛选</button>
    </form>
    <div class="d-flex gap-2">
        <a class="btn" href="<?= url('prints/sheet', ['t' => '表4-2']) ?>" target="_blank" title="接触职业病危害因素汇总表（表4-2）打印"><i class="ti ti-printer me-1"></i>打印汇总表</a>
        <a class="btn btn-primary" href="<?= url('posts/create') ?>"><i class="ti ti-plus me-1"></i>新增岗位</a>
    </div>
</div>

<div class="card">
    <?php if (!$rows): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-briefcase"></i></div>
            <p class="empty-title">暂无岗位</p>
            <p class="empty-subtitle text-secondary">请先在「作业场所」建场所，再到此处建立岗位并配置危害因素。</p>
            <div class="empty-action">
                <a href="<?= url('posts/create') ?>" class="btn btn-primary"><i class="ti ti-plus me-1"></i>新增岗位</a>
            </div>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <th>所属场所</th><th>岗位名称</th><th class="w-1">危害配置</th>
                    <th class="w-1">状态</th><th>备注</th><th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $p): ?>
                <tr>
                    <td><?= e($p['workplace_name'] ?? '—') ?></td>
                    <td><?= e($p['name']) ?></td>
                    <td>
                        <?php if ((int)$p['hazard_count'] === 0): ?>
                            <span class="badge bg-yellow-lt">未配置</span>
                        <?php else: ?>
                            <span class="badge bg-blue-lt"><?= (int)$p['hazard_count'] ?> 项</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $p['status'] === '启用' ? 'bg-green-lt' : 'bg-secondary-lt' ?>"><?= e($p['status']) ?></span></td>
                    <td class="text-secondary"><?= e($p['remark'] ?: '—') ?></td>
                    <td class="text-nowrap text-secondary">
                        <a class="text-body me-2" href="<?= url('detections/yearView', ['workplace_id' => (int)$p['workplace_id']]) ?>" title="该场所岗位历年检测报告与原件状态"><i class="ti ti-calendar-stats me-1"></i>年度检测</a>
                        <a class="text-body me-2" href="<?= url('posts/edit', ['id' => $p['id']]) ?>"><i class="ti ti-pencil me-1"></i>编辑</a>
                        <a class="text-danger" href="#" data-del="<?= $p['id'] ?>" data-name="<?= e($p['name']) ?>"><i class="ti ti-trash me-1"></i>删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<form id="del-form" method="post" action="<?= url('posts/delete') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="">
</form>
