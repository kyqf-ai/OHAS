<?php $done = $done ?? ['new' => 0, 'update' => 0, 'error' => 0]; ?>
<div class="row row-deck row-cards mb-3">
    <div class="col-6 col-lg-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="subheader">新增建档</div>
                <div class="h1 mb-0 text-primary"><?= (int)$done['new'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="subheader">更新</div>
                <div class="h1 mb-0"><?= (int)$done['update'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card card-sm <?= $done['error'] > 0 ? 'card-status-top bg-warning' : '' ?>">
            <div class="card-body">
                <div class="subheader">失败行</div>
                <div class="h1 mb-0 <?= $done['error'] > 0 ? 'text-yellow' : '' ?>"><?= (int)$done['error'] ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">导入结果报告</h3>
        <div class="card-actions">
            <a class="btn btn-sm" href="<?= url('workers/index') ?>">前往劳动者信息卡 <i class="ti ti-chevron-right ms-1"></i></a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th class="w-1 text-end">行号</th><th>姓名</th><th class="w-1">结果</th><th>说明</th></tr></thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                <tr class="<?= $r['action'] === 'error' ? 'table-danger' : '' ?>">
                    <td class="text-end"><?= (int)$r['line'] ?></td>
                    <td><?= e($r['name'] ?: '—') ?></td>
                    <td>
                        <?php if ($r['action'] === 'new'): ?><span class="badge bg-green-lt">新增</span>
                        <?php elseif ($r['action'] === 'update'): ?><span class="badge bg-blue-lt">更新</span>
                        <?php else: ?><span class="badge bg-red-lt">失败</span><?php endif; ?>
                    </td>
                    <td><?= e($r['msg']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-end">
        <div class="d-flex justify-content-end gap-2">
            <a class="btn" href="<?= url('workers/import') ?>"><i class="ti ti-upload me-1"></i>继续导入下一批</a>
            <a class="btn btn-primary" href="<?= url('workers/index') ?>"><i class="ti ti-list-details me-1"></i>前往劳动者信息卡</a>
        </div>
    </div>
</div>
