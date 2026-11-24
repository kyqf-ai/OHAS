<?php
$badge = [
    'login' => 'bg-green-lt', 'logout' => 'bg-secondary-lt', 'create' => 'bg-blue-lt',
    'update' => 'bg-yellow-lt', 'delete' => 'bg-red-lt', 'import' => 'bg-purple-lt', 'upload' => 'bg-azure-lt',
];
$actionLabels = [
    'login' => '登录', 'logout' => '退出', 'create' => '新增', 'update' => '修改',
    'delete' => '删除', 'import' => '导入', 'upload' => '上传',
    'login_fail' => '登录失败', 'download' => '下载', 'export' => '导出', 'view' => '查看', 'print' => '打印', 'install' => '安装',
];
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2" method="get" action="<?= url('system/logs') ?>">
        <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="system/logs"><?php endif; ?>
        <input type="text" class="form-control" style="width: 220px;" name="q" value="<?= e($q) ?>" placeholder="用户 / 摘要 / 对象">
        <select class="form-select" style="width: 150px;" name="action">
            <option value="">全部动作</option>
            <?php foreach ($actions as $a): ?>
                <option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($actionLabels[$a] ?? $a) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit"><i class="ti ti-search me-1"></i>筛选</button>
    </form>
    <a class="btn" href="<?= url('system/backup') ?>"><i class="ti ti-database-export me-1"></i>数据备份</a>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">操作日志</h3>
        <div class="card-actions text-secondary">共 <?= (int)$data['total'] ?> 条</div>
    </div>
    <?php if (!$rows): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-history"></i></div>
            <p class="empty-title">暂无日志</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th style="width: 150px;">时间</th>
                    <th style="width: 120px;">用户</th>
                    <th style="width: 90px;">动作</th>
                    <th>摘要</th>
                    <th style="width: 150px;">对象</th>
                    <th style="width: 130px;">IP</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="text-secondary"><?= e(substr((string)$r['created_at'], 0, 19)) ?></td>
                    <td><?= e($r['username'] ?: '—') ?></td>
                    <td><span class="badge <?= $badge[$r['action']] ?? 'bg-secondary-lt' ?>"><?= e($actionLabels[$r['action']] ?? $r['action']) ?></span></td>
                    <td><?= e($r['summary'] ?: '—') ?></td>
                    <td class="text-secondary"><?= e($r['object_type'] ?: '—') ?><?= $r['object_id'] ? ' #' . (int)$r['object_id'] : '' ?></td>
                    <td class="text-secondary"><?= e($r['ip'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include OHAS_ROOT . '/app/Views/partials/pagination.php'; ?>
