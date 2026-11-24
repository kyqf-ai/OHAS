<?php
use App\Helpers\LedgerCatalog;
use App\Helpers\LedgerPlace;

$labels = LedgerCatalog::heads($cfg);
$byName = [];
foreach ($cfg['fields'] as $f) {
    $byName[$f['name']] = $f;
}
$hasPlace = LedgerPlace::config($cfg) !== null;
$show = function (string $col, array $row) use ($options, $byName, $cfg, $names): string {
    // 场所/岗位列：关联名称 + 补充说明
    $placeText = LedgerPlace::display($cfg, $row, $col, $names);
    if ($placeText !== null) {
        return $placeText === '' ? '—' : mb_strimwidth($placeText, 0, 60, '…', 'UTF-8');
    }
    // 表4-1 多选危害因素：显示控制器合并后的名称串
    if (($byName[$col]['type'] ?? '') === 'hazards') {
        $namesText = (string)($row[$col . '_names'] ?? '');
        return $namesText === '' ? '—' : mb_strimwidth($namesText, 0, 60, '…', 'UTF-8');
    }
    $v = (string)($row[$col] ?? '');
    if ($v === '') {
        return '—';
    }
    if (!empty($byName[$col]['from']) && isset($options[$col][$v])) {
        return $options[$col][$v];
    }
    return mb_strimwidth($v, 0, 48, '…', 'UTF-8');
};
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
        <a href="<?= url('archive/show', ['group' => $cfg['group']]) ?>" class="text-secondary"><i class="ti ti-arrow-left me-1"></i>返回档案栏目</a>
        <div class="mt-1">
            <span class="badge bg-blue-lt"><?= e($cfg['archive']) ?></span>
            <span class="text-secondary ms-2"><?= e($cfg['intro']) ?></span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <?php preg_match('/(表\d+-\d+)/u', (string)($cfg['archive'] ?? ''), $pm); ?>
        <?php if (!empty($pm[1])): ?>
            <a class="btn" href="<?= url('prints/sheet', ['t' => $pm[1]]) ?>" target="_blank" title="规范表样打印"><i class="ti ti-printer me-1"></i>打印</a>
        <?php else: ?>
            <span class="btn disabled" title="系统扩充台账，无规范表样"><i class="ti ti-printer-off me-1"></i>打印</span>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= url('ledgers/create', ['key' => $key]) ?>"><i class="ti ti-plus me-1"></i>新增记录</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= e($cfg['title']) ?></h3>
        <div class="card-actions">
            <form class="d-flex gap-2" method="get" action="<?= url('ledgers/index') ?>">
                <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="ledgers/index"><?php endif; ?>
                <input type="hidden" name="key" value="<?= e($key) ?>">
                <?php if ($hasPlace): ?>
                <select class="form-select form-select-sm" name="workplace_id" style="width: auto;">
                    <option value="">全部场所</option>
                    <?php foreach ($names['workplace'] as $wid => $wname): ?>
                        <option value="<?= e($wid) ?>" <?= (int)$wpFilter === (int)$wid ? 'selected' : '' ?>><?= e($wname) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <input type="text" class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="搜索">
                <button class="btn btn-sm" type="submit"><i class="ti ti-search me-1"></i>筛选</button>
            </form>
        </div>
    </div>
    <?php if (!$rows): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-list-details"></i></div>
            <p class="empty-title">暂无记录</p>
            <p class="empty-subtitle text-secondary">按 <?= e($cfg['archive']) ?> 要求录入「<?= e($cfg['title']) ?>」。</p>
            <div class="empty-action">
                <a href="<?= url('ledgers/create', ['key' => $key]) ?>" class="btn btn-primary"><i class="ti ti-plus me-1"></i>新增记录</a>
            </div>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <?php foreach ($cfg['list'] as $col): ?>
                        <th><?= e($labels[$col] ?? $col) ?></th>
                    <?php endforeach; ?>
                    <th class="w-1">原件</th>
                    <th class="text-end" style="width: 200px;">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($cfg['list'] as $col): ?>
                        <td><?= e($show($col, $row)) ?></td>
                    <?php endforeach; ?>
                    <td class="text-nowrap">
                        <?php if ((int)($row['doc_count'] ?? 0) > 0): ?>
                            <a class="badge bg-blue-lt" href="<?= url('ledgers/edit', ['key' => $key, 'id' => $row['id']]) ?>#docs"><?= (int)$row['doc_count'] ?> 份</a>
                        <?php else: ?>
                            <a class="text-secondary" href="<?= e(\App\Helpers\DocLink::uploadUrl($linkType, (int)$row['id'])) ?>" title="上传原件"><i class="ti ti-upload"></i></a>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if (!empty($cfg['roster'])): ?>
                            <a class="text-body me-2" href="<?= url('ledgers/roster', ['training_id' => $row['id']]) ?>"><i class="ti ti-users me-1"></i>签到表</a>
                        <?php endif; ?>
                        <a class="text-body me-2" href="<?= url('ledgers/edit', ['key' => $key, 'id' => $row['id']]) ?>"><i class="ti ti-pencil me-1"></i>编辑</a>
                        <a class="text-danger" href="#" data-del="<?= (int)$row['id'] ?>" data-name="<?= e($show($cfg['list'][0] ?? 'id', $row)) ?>"><i class="ti ti-trash me-1"></i>删除</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include OHAS_ROOT . '/app/Views/partials/pagination.php'; ?>

<form id="del-form" method="post" action="<?= url('ledgers/delete') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="key" value="<?= e($key) ?>">
    <input type="hidden" name="id" value="">
</form>
