<?php
/** 危害因素类别 → 徽标配色 */
$catBadge = [
    '粉尘'       => 'bg-yellow-lt',
    '化学因素'   => 'bg-orange-lt',
    '物理因素'   => 'bg-purple-lt',
    '生物因素'   => 'bg-green-lt',
    '放射性因素' => 'bg-red-lt',
    '其他因素'   => 'bg-secondary-lt',
];
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2" method="get" action="<?= url('hazards/index') ?>">
        <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="hazards/index"><?php endif; ?>
        <div class="input-icon">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="名称 / 职业病 / CAS 号">
        </div>
        <select class="form-select w-auto" name="category">
            <option value="">全部类别</option>
            <?php foreach ($categories as $cOpt): ?>
                <option value="<?= e($cOpt) ?>" <?= $cOpt === $cat ? 'selected' : '' ?>><?= e($cOpt) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select w-auto" name="status">
            <option value="">全部状态</option>
            <option value="启用" <?= $status === '启用' ? 'selected' : '' ?>>启用</option>
            <option value="停用" <?= $status === '停用' ? 'selected' : '' ?>>停用</option>
        </select>
        <select class="form-select w-auto" name="ps" title="每页显示条数">
            <?php foreach ([20, 30, 50] as $n): ?>
                <option value="<?= $n ?>" <?= (int)$ps === $n ? 'selected' : '' ?>>每页 <?= $n ?> 条</option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit"><i class="ti ti-adjustments me-1"></i>筛选</button>
    </form>
    <div class="d-flex gap-2">
        <form method="post" action="<?= url('hazards/syncCatalog') ?>" onsubmit="return confirm('将按《职业病危害因素分类目录》（国卫疾控发〔2015〕92号）补齐缺失条目、更新目录序号/CAS/适用范围，并按 GBZ 2.1-2019（含第1、2号修改单）、GBZ 2.2-2007、GB 18871 回填与刷新接触限值。\n\n不会删除自建条目；可能职业病仅回填空缺，已自行维护的接触限值不会被覆盖。是否继续？');">
            <?= csrf_field() ?>
            <button class="btn" type="submit"><i class="ti ti-refresh me-1"></i>同步目录</button>
        </form>
        <a class="btn btn-primary" href="<?= url('hazards/create') ?>"><i class="ti ti-plus me-1"></i>新增危害因素</a>
    </div>
</div>

<div class="card">
    <?php if (!$data['rows']): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-biohazard"></i></div>
            <p class="empty-title">暂无危害因素</p>
            <p class="empty-subtitle text-secondary">点击右上角「新增危害因素」添加自建条目，或点击「同步目录」导入《职业病危害因素分类目录》。</p>
            <div class="empty-action">
                <a href="<?= url('hazards/create') ?>" class="btn btn-primary"><i class="ti ti-plus me-1"></i>新增危害因素</a>
            </div>
        </div>
    <?php else: ?>
    <div class="card-header py-2">
        <span class="text-secondary small">接触限值依据：GBZ 2.1-2019《工作场所有害因素职业接触限值 第1部分：化学有害因素》（含第1、2号修改单）、GBZ 2.2-2007、GB 18871-2002；「—」表示现行标准未制定限值，具体执行以标准原文为准。</span>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <th class="w-1">类别</th><th>名称</th><th>CAS 号</th><th>可能导致的职业病</th>
                    <th>接触限值</th><th class="w-1">状态</th><th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['rows'] as $h): ?>
                <tr>
                    <td><span class="badge <?= $catBadge[$h['category']] ?? 'bg-blue-lt' ?>"><?= e($h['category']) ?></span></td>
                    <td>
                        <div><?= e($h['name']) ?></div>
                        <?php if ($h['restriction']): ?>
                            <div class="text-secondary small"><?= e($h['restriction']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-secondary font-monospace small text-nowrap"><?= e($h['cas_no'] ?: '—') ?></td>
                    <td><?= e($h['possible_disease'] ?: '—') ?></td>
                    <td class="small"><?= e($h['limit_note'] ?: '—') ?></td>
                    <td><span class="badge <?= $h['status'] === '启用' ? 'bg-green-lt' : 'bg-secondary-lt' ?>"><?= e($h['status']) ?></span></td>
                    <td class="text-nowrap text-secondary">
                        <a class="text-body me-2" href="<?= url('hazards/edit', ['id' => $h['id']]) ?>"><i class="ti ti-pencil me-1"></i>编辑</a>
                        <a class="text-danger" href="#" data-del="<?= $h['id'] ?>" data-name="<?= e($h['name']) ?>"><i class="ti ti-trash me-1"></i>删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php include OHAS_ROOT . '/app/Views/partials/pagination.php'; ?>
</div>

<form id="del-form" method="post" action="<?= url('hazards/delete') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="">
</form>
