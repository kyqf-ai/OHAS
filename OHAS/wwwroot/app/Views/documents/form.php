<?php
$isEdit = ($mode ?? 'create') === 'edit';
$action = $isEdit ? url('documents/edit', ['id' => (int)$doc['id']]) : url('documents/create');
$val = function (string $key, string $default = '') use ($doc): string {
    return e($_SESSION['_old'][$key] ?? ($doc[$key] ?? $default));
};
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= $isEdit ? '编辑文档' : '上传文档' ?></h3>
        <div class="card-actions">
            <a href="<?= url('documents/index') ?>" class="btn btn-sm"><i class="ti ti-arrow-left me-1"></i>返回列表</a>
        </div>
    </div>
    <?php if (form_errors()): ?>
    <div class="card-body pb-0">
        <div class="alert alert-danger" role="alert">
            <i class="ti ti-alert-triangle alert-icon"></i>
            <div><?php foreach (form_errors() as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
        </div>
    </div>
    <?php endif; ?>
    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6 col-xl-4">
                    <label class="form-label required">文档分类</label>
                    <select class="form-select" name="category" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= e($c) ?>" <?= $val('category') === e($c) ? 'selected' : '' ?>><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (form_error('category')): ?><div class="invalid-feedback d-block"><?= e(form_error('category')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 col-xl-5">
                    <label class="form-label required">文档标题</label>
                    <input type="text" class="form-control" name="title" value="<?= $val('title') ?>" required>
                    <?php if (form_error('title')): ?><div class="invalid-feedback d-block"><?= e(form_error('title')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">文档编号</label>
                    <input type="text" class="form-control" name="doc_no" value="<?= $val('doc_no') ?>">
                </div>

                <div class="col-md-6 col-xl-4">
                    <label class="form-label">资料类型</label>
                    <select class="form-select" name="doc_type" id="doc-type">
                        <option value="">— 未分类 —</option>
                        <?php foreach ($docTypes as $code => $dname): ?>
                            <option value="<?= e($code) ?>" <?= $val('doc_type') === e($code) ? 'selected' : '' ?>><?= e($dname) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">选择后自动带出文档分类；个人年度矩阵与单位清单按资料类型归档。</div>
                    <?php if (form_error('doc_type')): ?><div class="invalid-feedback d-block"><?= e(form_error('doc_type')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 col-xl-2">
                    <label class="form-label">所属年度</label>
                    <input type="number" class="form-control" name="period_year" min="1900" max="2100"
                           value="<?= $val('period_year') ?>" placeholder="留空取文档日期年份">
                    <?php if (form_error('period_year')): ?><div class="invalid-feedback d-block"><?= e(form_error('period_year')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 col-xl-4">
                    <label class="form-label">出具 / 发文单位</label>
                    <input type="text" class="form-control" name="issuer" value="<?= $val('issuer') ?>">
                </div>
                <div class="col-md-6 col-xl-2">
                    <label class="form-label">文档日期</label>
                    <input type="date" class="form-control" name="doc_date" value="<?= $val('doc_date') ?>">
                </div>
                <div class="col-md-6 col-xl-2">
                    <label class="form-label">有效期至</label>
                    <input type="date" class="form-control" name="expire_date" value="<?= $val('expire_date') ?>">
                    <div class="form-hint">留空表示长期有效</div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">关联对象</label>
                    <select class="form-select" name="link_type" id="link-type">
                        <option value="">不关联</option>
                        <?php foreach ($linkTypes as $code => $label): ?>
                            <option value="<?= e($code) ?>" <?= $val('link_type') === e($code) ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 col-xl-5" id="link-pick" style="<?= $val('link_type') === '' ? 'display:none' : '' ?>">
                    <label class="form-label">关联记录</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="link-q" placeholder="输入关键字检索，再在右侧选择" autocomplete="off">
                        <select class="form-select" name="link_id" id="link-id" style="max-width:62%">
                            <?php if ($val('link_id') !== '' && !empty($linkLabel)): ?>
                                <option value="<?= $val('link_id') ?>" selected><?= e($linkLabel) ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php if (form_error('link_id')): ?><div class="invalid-feedback d-block"><?= e(form_error('link_id')) ?></div><?php endif; ?>
                    <div class="form-hint">原件会出现在所选记录的页面中；不选记录则仅在分类文档中管理。</div>
                </div>
                <input type="hidden" name="back" value="<?= e((string)($_SESSION['_old']['back'] ?? ($doc['back'] ?? 0))) ?>">

                <div class="col-12">
                    <label class="form-label <?= $isEdit ? '' : 'required' ?>">文档文件</label>
                    <input type="file" class="form-control" name="file" <?= $isEdit ? '' : 'required' ?>
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    <div class="form-hint">
                        支持 PDF / 图片 / Word / Excel，单个文件不超过 20MB。
                        <?php if ($isEdit && !empty($doc['file_path'])): ?>
                            当前文件：<a href="<?= url('documents/download', ['id' => $doc['id']]) ?>">下载查看</a>，重新上传将替换原文件。
                        <?php endif; ?>
                    </div>
                    <?php if (form_error('file')): ?><div class="invalid-feedback d-block"><?= e(form_error('file')) ?></div><?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label">备注</label>
                    <input type="text" class="form-control" name="remark" value="<?= $val('remark') ?>">
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a class="btn" href="<?= url('documents/index') ?>">取消</a>
            <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>保存</button>
        </div>
    </form>
</div>

<script>
(function () {
    /* 资料类型 → 自动带出文档分类 */
    var dtSel = document.getElementById('doc-type');
    var catSel = document.querySelector('select[name="category"]');
    var catByType = <?= json_encode(array_map(fn ($c) => \App\Helpers\DocTypes::categoryOf((string)$c), array_keys($docTypes ?? [])), JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE) ?>;
    if (dtSel && catSel) {
        dtSel.addEventListener('change', function () {
            var cat = catByType[dtSel.value] || '';
            if (cat) {
                Array.prototype.forEach.call(catSel.options, function (o) {
                    if (o.value === cat) o.selected = true;
                });
            }
        });
    }

    var typeSel = document.getElementById('link-type');
    var pick = document.getElementById('link-pick');
    var q = document.getElementById('link-q');
    var sel = document.getElementById('link-id');
    var endpoint = <?= json_encode(url('documents/targets'), JSON_UNESCAPED_SLASHES) ?>;
    var timer = null;
    function load() {
        var t = typeSel.value;
        pick.style.display = t === '' ? 'none' : '';
        if (t === '') { sel.innerHTML = ''; return; }
        var keep = sel.value, keepText = sel.options.length ? sel.options[sel.selectedIndex].text : '';
        var u = endpoint + (endpoint.indexOf('?') > -1 ? '&' : '?') + 'type=' + encodeURIComponent(t) + '&q=' + encodeURIComponent(q.value);
        fetch(u, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
            sel.innerHTML = '';
            var has = false;
            (d.items || []).forEach(function (it) {
                var o = document.createElement('option');
                o.value = it.id; o.textContent = it.label;
                if (String(it.id) === keep) { o.selected = true; has = true; }
                sel.appendChild(o);
            });
            if (keep && !has && keepText) {
                var o = document.createElement('option'); o.value = keep; o.textContent = keepText; o.selected = true;
                sel.insertBefore(o, sel.firstChild);
            }
            if (!sel.options.length) {
                var e = document.createElement('option'); e.value = ''; e.textContent = '没有匹配的记录'; sel.appendChild(e);
            }
        });
    }
    typeSel.addEventListener('change', function () { sel.innerHTML = ''; q.value = ''; load(); });
    q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 250); });
    if (typeSel.value !== '') { load(); }
})();
</script>
