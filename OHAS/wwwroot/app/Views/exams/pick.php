<?php
/**
 * 应检名单生成：勾选在岗人员批量建立待检记录（结论待出）。
 * 默认勾选「有接害因素」的在岗人员（口径：在岗接害人员定期健康检查），
 * 无接害因素者不默认勾选，可手动勾选或一键全选。
 */
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">生成应检名单</h3>
        <div class="card-subtitle">
            <?= d($batch['exam_date']) ?> · <?= e($batch['exam_type']) ?> · <?= e($batch['agency']) ?>
            —— 勾选后批量建立待检记录，体检完成后逐人登记结论
        </div>
        <div class="card-actions">
            <a href="<?= url('exams/records', ['batch_id' => $batch['id']]) ?>" class="btn btn-sm"><i class="ti ti-arrow-left me-1"></i>返回批次记录</a>
        </div>
    </div>
    <form method="get" action="<?= url('exams/pick') ?>" class="card-body pb-0">
        <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="exams/pick"><?php endif; ?>
        <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
        <div class="d-flex flex-wrap gap-2 mb-3">
            <div class="input-icon">
                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="姓名 / 档案号 / 身份证号">
            </div>
            <button class="btn" type="submit"><i class="ti ti-search me-1"></i>搜索</button>
            <span class="text-secondary align-self-center small">
                仅列出「在职」人员；已登记的显示为灰且不可勾选；默认勾选有接害因素的人员
            </span>
        </div>
    </form>
    <form id="pick-form" method="post" action="<?= url('exams/pickCreate') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>">
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover" id="pick-table">
                <thead>
                    <tr>
                        <th class="w-1">
                            <span class="d-flex flex-column gap-1">
                                <input type="checkbox" class="form-check-input" id="check-all">
                                <button type="button" class="btn btn-sm btn-link p-0 text-secondary small" id="check-hazard" title="只勾选接触职业病危害因素的人员">仅勾接害</button>
                            </span>
                        </th>
                        <th>姓名</th><th>性别</th><th>档案号</th><th>当前岗位</th><th>接触职业病危害因素</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                    <tr><td colspan="6">
                        <div class="empty empty-sm py-3">
                            <p class="empty-title">没有符合条件的在职人员</p>
                        </div>
                    </td></tr>
                    <?php else: foreach ($rows as $w):
                        $added = isset($existing[(int)$w['id']]);
                        $hasHazards = trim((string)$w['current_hazards']) !== ''; ?>
                    <tr class="<?= $added ? 'table-secondary text-secondary' : '' ?>">
                        <td>
                            <?php if ($added): ?>
                                <span class="badge bg-secondary-lt">已登记</span>
                            <?php else: ?>
                                <input type="checkbox" class="form-check-input pick-item" name="worker_ids[]" value="<?= (int)$w['id'] ?>"
                                       data-hazards="<?= $hasHazards ? '1' : '0' ?>" <?= $hasHazards ? 'checked' : '' ?>>
                            <?php endif; ?>
                        </td>
                        <td><?= e($w['name']) ?></td>
                        <td><?= e($w['gender'] ?: '—') ?></td>
                        <td class="font-monospace"><?= e($w['archive_no']) ?></td>
                        <td><?= e($w['current_post'] ?: '—') ?></td>
                        <td class="text-secondary"><?= e($w['current_hazards'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="text-secondary small">
                勾选 <b id="pick-count">0</b> 人；生成后记录日期与种类默认取批次信息，结论为「待出结论」
            </div>
            <div class="d-flex gap-2">
                <a class="btn" href="<?= url('exams/records', ['batch_id' => $batch['id']]) ?>">取消</a>
                <button class="btn btn-primary" type="submit" id="pick-submit"><i class="ti ti-user-plus me-1"></i>生成待检记录</button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    var all = document.getElementById('check-all');
    var items = document.querySelectorAll('.pick-item');
    var count = document.getElementById('pick-count');
    var form = document.getElementById('pick-form');
    var submitBtn = document.getElementById('pick-submit');

    function refresh() {
        var n = 0;
        items.forEach(function (c) { if (c.checked) n++; });
        if (count) count.textContent = n;
        if (submitBtn) submitBtn.disabled = (n === 0);
    }
    if (all) {
        all.addEventListener('change', function () {
            items.forEach(function (c) { c.checked = all.checked; });
            refresh();
        });
    }
    // 一键「仅勾接害」：默认勾选口径的快捷恢复
    var hazBtn = document.getElementById('check-hazard');
    if (hazBtn) {
        hazBtn.addEventListener('click', function () {
            items.forEach(function (c) { c.checked = c.dataset.hazards === '1'; });
            if (all) all.checked = false;
            refresh();
        });
    }
    items.forEach(function (c) { c.addEventListener('change', refresh); });
    refresh();

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (submitBtn) submitBtn.disabled = true;
            var fd = new FormData(form);
            fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        window.location.href = data.redirect;
                    } else {
                        alert(data.msg || '生成失败');
                        if (submitBtn) submitBtn.disabled = false;
                    }
                })
                .catch(function () {
                    alert('网络异常，请重试');
                    if (submitBtn) submitBtn.disabled = false;
                });
        });
    }
})();
</script>
