<?php
$fmt = function (int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
};
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="text-secondary">
        备份包含数据库<?= $hasZip ? ' 与全部上传文件' : '（当前环境未启用 ZipArchive，仅备份数据库）' ?>，文件保存在 <code>storage/backups</code>。
    </div>
    <div class="d-flex gap-2">
        <a class="btn" href="<?= url('system/logs') ?>"><i class="ti ti-history me-1"></i>操作日志</a>
        <form method="post" action="<?= url('system/backupCreate') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit"><i class="ti ti-database-export me-1"></i>立即备份</button>
        </form>
    </div>
</div>

<?php
// 上传环境检查：任一项低于系统限制（20MB / 至少 10 个并发文件）时标黄提示
$phpIni = function (string $key): int {
    $v = trim((string)ini_get($key));
    if ($v === '') {
        return 0;
    }
    $n = (float)$v;
    $u = strtolower(substr($v, -1));
    return (int)round($n * ($u === 'g' ? 1073741824 : ($u === 'm' ? 1048576 : ($u === 'k' ? 1024 : 1))));
};
$upMax = $phpIni('upload_max_filesize');
$postMax = $phpIni('post_max_size');
$maxFiles = (int)ini_get('max_file_uploads');
$needBytes = (int)cfg('upload.doc_max_bytes');
$envWarn = ($upMax > 0 && $upMax < $needBytes) || ($postMax > 0 && $postMax < $needBytes) || ($maxFiles > 0 && $maxFiles < 10);
?>
<div class="card mb-3 <?= $envWarn ? 'border-warning' : '' ?>">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-cloud-upload me-1"></i>上传环境</h3>
        <?php if ($envWarn): ?><span class="badge bg-warning-lt ms-2">需要调整</span><?php endif; ?>
    </div>
    <div class="card-body py-2">
        <div class="datagrid">
            <div class="datagrid-item"><div class="datagrid-title">upload_max_filesize</div><div class="datagrid-content font-monospace"><?= $upMax > 0 ? e($fmt($upMax)) : '—' ?></div></div>
            <div class="datagrid-item"><div class="datagrid-title">post_max_size</div><div class="datagrid-content font-monospace"><?= $postMax > 0 ? e($fmt($postMax)) : '—' ?></div></div>
            <div class="datagrid-item"><div class="datagrid-title">max_file_uploads</div><div class="datagrid-content font-monospace"><?= $maxFiles > 0 ? $maxFiles : '—' ?></div></div>
            <div class="datagrid-item"><div class="datagrid-title">系统单文件限制</div><div class="datagrid-content font-monospace"><?= e($fmt($needBytes)) ?></div></div>
        </div>
        <?php if ($envWarn): ?>
        <div class="alert alert-warning mt-2 mb-0">
            <i class="ti ti-alert-triangle alert-icon"></i>
            <div>
                PHP 上传限制低于系统设置（单文件 <?= e($fmt($needBytes)) ?>、快速上传一次可选多个文件），大文件可能上传失败。
                请调整 php.ini：<code>upload_max_filesize</code>、<code>post_max_size</code> 建议 ≥ <?= e($fmt($needBytes)) ?>，<code>max_file_uploads</code> 建议 ≥ 10，调整后重启 PHP 服务。
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-folder-open me-1"></i>存储占用</h3>
        <div class="card-actions text-secondary">上传原件目录 storage/uploads</div>
    </div>
    <div class="card-body py-2">
        <div class="datagrid">
            <div class="datagrid-item"><div class="datagrid-title">上传文件数</div><div class="datagrid-content"><?= (int)$upFiles ?> 个</div></div>
            <div class="datagrid-item"><div class="datagrid-title">占用空间</div><div class="datagrid-content"><?= e($fmt((int)$upBytes)) ?></div></div>
            <div class="datagrid-item"><div class="datagrid-title">备份将包含</div><div class="datagrid-content"><?= $hasZip ? '数据库 + 全部上传文件' : '仅数据库（未启用 ZipArchive）' ?></div></div>
        </div>
        <?php if ((int)$upFiles > 0): ?>
        <div class="text-secondary small mt-1">备份 zip 体积约等于数据库与上传文件之和，请预留磁盘空间。</div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">备份文件</h3>
        <div class="card-actions text-secondary">共 <?= count($files) ?> 个</div>
    </div>
    <?php if (!$files): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-database"></i></div>
            <p class="empty-title">暂无备份</p>
            <p class="empty-subtitle text-secondary">建议在档案归档、批量导入前后执行备份。</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>文件名</th>
                    <th style="width: 120px;">大小</th>
                    <th style="width: 180px;">备份时间</th>
                    <th class="text-end" style="width: 160px;">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($files as $f): ?>
                <tr>
                    <td class="text-body"><?= e($f['name']) ?></td>
                    <td class="text-secondary"><?= e($fmt((int)$f['size'])) ?></td>
                    <td class="text-secondary"><?= e($f['time']) ?></td>
                    <td class="text-end">
                        <a class="text-body me-2" href="<?= url('system/backupDownload', ['file' => $f['name']]) ?>"><i class="ti ti-download me-1"></i>下载</a>
                        <form method="post" action="<?= url('system/backupDelete') ?>" class="d-inline"
                              onsubmit="return confirm('确定删除该备份文件？');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="file" value="<?= e($f['name']) ?>">
                            <button class="btn btn-sm btn-link text-danger p-0" type="submit"><i class="ti ti-trash me-1"></i>删除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="alert alert-warning mt-3">
    <i class="ti ti-alert-triangle alert-icon"></i>
    <div>备份文件包含全部职业健康档案数据，请妥善保管并定期转存至独立存储介质。</div>
</div>
