<?php $s = $summary; ?>
<div class="row row-deck row-cards mb-3">
    <div class="col-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="subheader">解析总行数</div>
                <div class="h1 mb-0"><?= count($rows) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="subheader">将新增</div>
                <div class="h1 mb-0 text-primary"><?= (int)$s['new'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="subheader">将更新</div>
                <div class="h1 mb-0"><?= (int)$s['update'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-sm <?= $s['error'] > 0 ? 'card-status-top bg-warning' : '' ?>">
            <div class="card-body">
                <div class="subheader">错误行（不导入）</div>
                <div class="h1 mb-0 <?= $s['error'] > 0 ? 'text-yellow' : '' ?>"><?= (int)$s['error'] ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">导入预览确认</h3>
        <div class="card-actions">
            <a class="btn btn-sm" href="<?= url('workers/import') ?>"><i class="ti ti-refresh me-1"></i>重新上传</a>
        </div>
    </div>
    <?php if (!$rows): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-file-import"></i></div>
            <p class="empty-title">文件中没有数据行</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <th class="w-1 text-end">行号</th><th>姓名</th><th>身份证号</th><th>性别</th>
                    <th>出生日期</th><th>状态</th><th class="w-1">处理方式</th><th>错误原因</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr class="<?= $r['action'] === 'error' ? 'table-danger' : '' ?>">
                    <td class="text-end"><?= (int)$r['line'] ?></td>
                    <td><?= e($r['name'] ?: '—') ?></td>
                    <td class="font-monospace"><?= e(mask_id_card($r['id_card'])) ?></td>
                    <td><?= e($r['data']['gender'] ?? '—') ?></td>
                    <td><?= e($r['data']['birth_date'] ?? '—') ?></td>
                    <td><?= e($r['data']['status'] ?? '—') ?></td>
                    <td>
                        <?php if ($r['action'] === 'new'): ?><span class="badge bg-green-lt">新增建档</span>
                        <?php elseif ($r['action'] === 'update'): ?><span class="badge bg-blue-lt">更新</span>
                        <?php else: ?><span class="badge bg-red-lt">错误</span><?php endif; ?>
                    </td>
                    <td class="text-danger"><?= e(implode('；', $r['errors'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <div class="card-footer text-end">
        <div class="d-flex justify-content-end gap-2">
            <a class="btn" href="<?= url('workers/import') ?>">取消</a>
            <form method="post" action="<?= url('workers/importCommit') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit" <?= $s['new'] + $s['update'] === 0 ? 'disabled' : '' ?>>
                    <i class="ti ti-circle-check me-1"></i>确认导入<?= ($s['new'] + $s['update']) > 0 ? '（' . ((int)$s['new'] + (int)$s['update']) . ' 行）' : '' ?>
                </button>
            </form>
        </div>
    </div>
</div>
