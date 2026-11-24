<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2" method="get" action="<?= url('workers/index') ?>">
        <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="workers/index"><?php endif; ?>
        <div class="input-icon">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="姓名 / 档案号 / 身份证号">
        </div>
        <select class="form-select w-auto" name="status">
            <option value="">全部状态</option>
            <?php foreach ($statusOptions as $st): ?>
                <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e($st) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select w-auto" name="post">
            <option value="">全部当前岗位</option>
            <?php foreach ($postOptions as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $postId === (int)$p['id'] ? 'selected' : '' ?>>
                    <?= e($p['workplace_name']) ?> · <?= e($p['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit"><i class="ti ti-adjustments me-1"></i>筛选</button>
    </form>
    <div class="d-flex gap-2">
        <a class="btn" href="<?= url('prints/sheet', ['t' => '表5-6']) ?>" target="_blank" title="职业健康监护档案汇总表（表5-6）打印"><i class="ti ti-printer me-1"></i>打印档案汇总表</a>
        <a class="btn" href="<?= url('workers/import') ?>"><i class="ti ti-file-import me-1"></i>CSV 批量导入</a>
        <a class="btn btn-primary" href="<?= url('workers/create') ?>"><i class="ti ti-plus me-1"></i>新建信息卡</a>
    </div>
</div>

<div class="card">
    <?php if (!$data['rows']): ?>
        <?php if ($q !== '' || $status !== '' || $postId > 0): ?>
        <div class="empty empty-sm">
            <div class="empty-icon"><i class="ti ti-search"></i></div>
            <p class="empty-title">没有符合筛选条件的劳动者</p>
        </div>
        <?php else: ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-id"></i></div>
            <p class="empty-title">还没有劳动者档案</p>
            <p class="empty-subtitle text-secondary">点击右上角「新建信息卡」逐人建档，或使用 CSV 批量导入基本信息。</p>
            <div class="empty-action d-flex gap-2">
                <a href="<?= url('workers/create') ?>" class="btn btn-primary"><i class="ti ti-user-plus me-1"></i>新建信息卡</a>
                <a href="<?= url('workers/import') ?>" class="btn"><i class="ti ti-file-import me-1"></i>CSV 批量导入</a>
            </div>
        </div>
        <?php endif; ?>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <th>档案号</th><th>姓名</th><th>性别</th><th>出生日期</th>
                    <th>身份证号</th><th>当前岗位</th><th class="w-1">状态</th><th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['rows'] as $w): ?>
                <tr>
                    <td class="font-monospace"><?= e($w['archive_no']) ?></td>
                    <td><a class="text-body fw-bold" href="<?= url('workers/show', ['id' => $w['id']]) ?>"><?= e($w['name']) ?></a></td>
                    <td><?= e($w['gender'] ?: '—') ?></td>
                    <td><?= d($w['birth_date']) ?></td>
                    <td class="font-monospace text-secondary"><?= e(mask_id_card($w['id_card'])) ?></td>
                    <td><?= e($w['current_post'] ?: '—') ?></td>
                    <td><span class="badge <?= $w['status'] === '在职' ? 'bg-green-lt' : 'bg-secondary-lt' ?>"><?= e($w['status']) ?></span></td>
                    <td class="text-nowrap text-secondary">
                        <a class="text-body me-2" href="<?= url('workers/files', ['id' => $w['id']]) ?>" title="个人资料全景"><i class="ti ti-folders me-1"></i>资料</a>
                        <a class="text-body me-2" href="<?= url('workers/show', ['id' => $w['id']]) ?>"><i class="ti ti-eye me-1"></i>查看</a>
                        <a class="text-body me-2" href="<?= url('workers/edit', ['id' => $w['id']]) ?>"><i class="ti ti-pencil me-1"></i>编辑</a>
                        <a class="text-body me-2" href="<?= url('workers/printCard', ['id' => $w['id']]) ?>" target="_blank"><i class="ti ti-printer me-1"></i>打印表6-1</a>
                        <a class="text-danger" href="#" data-del="<?= $w['id'] ?>" data-name="<?= e($w['name']) ?>"
                           data-docs="<?= (int)($w['doc_count'] ?? 0) ?>" data-exam="<?= (int)($w['exam_count'] ?? 0) ?>"
                           data-disease="<?= (int)($w['disease_count'] ?? 0) ?>" data-training="<?= (int)($w['training_count'] ?? 0) ?>"
                           data-ppe="<?= (int)($w['ppe_count'] ?? 0) ?>"><i class="ti ti-trash me-1"></i>删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php include OHAS_ROOT . '/app/Views/partials/pagination.php'; ?>
</div>

<form id="del-form" method="post" action="<?= url('workers/delete') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="">
</form>
