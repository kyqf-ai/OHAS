<?php
$expiryBadge = function (array $doc) use ($today, $soon): string {
    $d = (string)($doc['expire_date'] ?? '');
    if ($d === '') {
        return '<span class="badge bg-secondary-lt">长期</span>';
    }
    if ($d < $today) {
        return '<span class="badge bg-red-lt">已过期</span>';
    }
    if ($d <= $soon) {
        return '<span class="badge bg-yellow-lt">即将到期</span>';
    }
    return '<span class="badge bg-green-lt">有效</span>';
};
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2" method="get" action="<?= url('documents/index') ?>">
        <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="documents/index"><?php endif; ?>
        <input type="text" class="form-control" style="width: 200px;" name="q" value="<?= e($q) ?>" placeholder="标题 / 编号 / 单位 / 劳动者姓名">
        <select class="form-select" style="width: 180px;" name="category">
            <option value="">全部分类</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= e($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select" style="width: 200px;" name="doc_type">
            <option value="">全部资料类型</option>
            <option value="__unclassified__" <?= $unclassified === '1' ? 'selected' : '' ?>>未分类</option>
            <?php foreach ($docTypes as $code => $name): ?>
                <option value="<?= e($code) ?>" <?= $docType === $code ? 'selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select" style="width: 140px;" name="expiry">
            <option value="">全部有效期</option>
            <option value="expired" <?= $expiry === 'expired' ? 'selected' : '' ?>>已过期</option>
            <option value="soon" <?= $expiry === 'soon' ? 'selected' : '' ?>><?= max(1, (int)cfg('expiry.soon_days', 30)) ?> 天内到期</option>
        </select>
        <button class="btn" type="submit"><i class="ti ti-search me-1"></i>筛选</button>
    </form>
    <a class="btn btn-primary" href="<?= url('documents/create') ?>"><i class="ti ti-upload me-1"></i>上传文档</a>
</div>

<form id="classify-form" method="post" action="<?= url('documents/classify') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="only_unclassified" value="<?= $unclassified === '1' ? '1' : '0' ?>">
    <div class="card">
        <?php if (!$rows): ?>
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-folder"></i></div>
                <p class="empty-title">暂无文档</p>
                <p class="empty-subtitle text-secondary">按档案分类上传法律法规、制度、报告、证书等文件；个人资料在个人资料页上传。</p>
                <div class="empty-action">
                    <a href="<?= url('documents/create') ?>" class="btn btn-primary"><i class="ti ti-upload me-1"></i>上传文档</a>
                </div>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
                <thead>
                    <tr>
                        <th style="width: 26px;"><input type="checkbox" id="check-all" title="全选本页"></th>
                        <th style="width: 150px;">资料类型</th>
                        <th style="width: 130px;">分类</th>
                        <th>文档标题</th>
                        <th style="width: 150px;">关联对象</th>
                        <th style="width: 100px;">文档日期</th>
                        <th style="width: 80px;">年度</th>
                        <th style="width: 110px;">有效期至</th>
                        <th style="width: 90px;">状态</th>
                        <th class="text-end" style="width: 150px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $doc): ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= (int)$doc['id'] ?>"></td>
                        <td>
                            <?php if ((string)$doc['doc_type'] !== ''): ?>
                                <span class="badge bg-cyan-lt"><?= e(\App\Helpers\DocTypes::nameOf((string)$doc['doc_type'])) ?></span>
                            <?php else: ?><span class="badge bg-secondary-lt">未分类</span><?php endif; ?>
                        </td>
                        <td><span class="badge bg-blue-lt"><?= e($doc['category']) ?></span></td>
                        <td>
                            <div class="text-body doc-title-text" data-doc-id="<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?></div>
                            <?php if (!empty($doc['remark'])): ?><div class="text-secondary small"><?= e($doc['remark']) ?></div><?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if (!empty($doc['link_type']) && !empty($doc['link_id'])): ?>
                                <?php $ll = $doc['link_label'] ?? \App\Helpers\DocLink::label((string)$doc['link_type'], (int)$doc['link_id']); ?>
                                <?php if ($ll !== ''): ?>
                                    <a href="<?= e(\App\Helpers\DocLink::url((string)$doc['link_type'], (int)$doc['link_id'])) ?>"><i class="ti ti-link me-1 text-secondary"></i><?= e($ll) ?></a>
                                <?php else: ?><span class="text-warning">关联记录已删除</span><?php endif; ?>
                            <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
                        </td>
                        <td class="text-secondary"><?= e(d($doc['doc_date'])) ?></td>
                        <td class="text-secondary font-monospace"><?= $doc['period_year'] !== null && (string)$doc['period_year'] !== '' ? (int)$doc['period_year'] : '—' ?></td>
                        <td class="text-secondary"><?= e(d($doc['expire_date'])) ?></td>
                        <td><?= $expiryBadge($doc) ?></td>
                        <td class="text-end text-nowrap">
                            <a class="text-body me-2" href="<?= url('documents/download', ['id' => $doc['id'], 'inline' => 1]) ?>" target="_blank" title="查看"><i class="ti ti-eye"></i></a>
                            <a class="text-body me-2" href="<?= url('documents/download', ['id' => $doc['id']]) ?>" title="下载"><i class="ti ti-download"></i></a>
                            <a class="text-body me-2" href="#" data-rename="<?= (int)$doc['id'] ?>" data-title="<?= e($doc['title']) ?>" title="改名"><i class="ti ti-pencil"></i></a>
                            <a class="text-body me-2" href="<?= url('documents/edit', ['id' => $doc['id']]) ?>" title="编辑 / 替换文件"><i class="ti ti-settings-2"></i></a>
                            <a class="text-danger" href="#" data-del="<?= (int)$doc['id'] ?>" data-name="<?= e($doc['title']) ?>"><i class="ti ti-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 align-items-center bg-secondary-lt">
            <span class="text-secondary">已勾选 <b id="checked-count">0</b> 项：</span>
            <select class="form-select form-select-sm" style="width: auto;" name="doc_type" form="classify-form" required>
                <option value="">— 批量设为资料类型 —</option>
                <?php foreach ($docTypes as $code => $name): ?>
                    <option value="<?= e($code) ?>"><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-primary" type="submit" form="classify-form"><i class="ti ti-tag me-1"></i>批量归类</button>
            <span class="text-secondary small">未分类文档不参与个人矩阵与单位清单统计，请归类后再用于归档。</span>
        </div>
        <?php endif; ?>
    </div>
</form>

<?php include OHAS_ROOT . '/app/Views/partials/pagination.php'; ?>

<form id="del-form" method="post" action="<?= url('documents/delete') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="">
</form>

<script>
(function () {
    var all = document.getElementById('check-all');
    if (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('#classify-form input[name="ids[]"]').forEach(function (c) { c.checked = all.checked; });
            count();
        });
        document.addEventListener('change', function (e) {
            if (e.target && e.target.name === 'ids[]') count();
        });
        function count() {
            var n = document.querySelectorAll('#classify-form input[name="ids[]"]:checked').length;
            var el = document.getElementById('checked-count');
            if (el) el.textContent = n;
        }
    }
})();
</script>
