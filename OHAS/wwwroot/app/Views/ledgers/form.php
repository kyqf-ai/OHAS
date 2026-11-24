<?php
$isEdit = ($mode ?? 'create') === 'edit';
$action = $isEdit
    ? url('ledgers/edit', ['key' => $key, 'id' => (int)$row['id']])
    : url('ledgers/create', ['key' => $key]);
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= $isEdit ? '编辑' : '新增' ?> · <?= e($cfg['title']) ?></h3>
        <div class="card-actions">
            <span class="badge bg-blue-lt"><?= e($cfg['archive']) ?></span>
            <a href="<?= url('ledgers/index', ['key' => $key]) ?>" class="btn btn-sm ms-2"><i class="ti ti-arrow-left me-1"></i>返回列表</a>
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
    <form method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($cfg['fields'] as $f):
                    $name = $f['name'];
                    $type = $f['type'] ?? 'text';
                    $val = $_SESSION['_old'][$name] ?? ($row[$name] ?? '');
                    $required = !empty($f['required']);
                    // 多选危害因素整行更宽（459 条目录的可检索多选）；其余字段维持原栅格
                    $col = in_array($type, ['textarea', 'hazards'], true) ? 'col-12' : 'col-md-6 col-xl-4';
                ?>
                <div class="<?= $col ?>">
                    <div class="mb-2">
                        <label class="form-label <?= $required ? 'required' : '' ?>"><?= e($f['label']) ?></label>
                        <?php if ($type === 'textarea'): ?>
                            <textarea class="form-control" name="<?= e($name) ?>" rows="2" <?= $required ? 'required' : '' ?>><?= e($val) ?></textarea>
                        <?php elseif ($type === 'hazards'): ?>
                            <?php
                            // 多选危害因素：旧值（校验失败回填）或记录关联均为 id 数组
                            $sel = is_array($val) ? $val : (is_string($val) && $val !== '' ? [$val] : []);
                            $sel = array_map('strval', $sel);
                            ?>
                            <select class="form-select" name="<?= e($name) ?>[]" multiple size="1"
                                    data-hazard-picker data-hazard-multi>
                                <?php foreach (($hazardGroups ?? []) as $cat => $group): ?>
                                <optgroup label="<?= e($cat) ?>">
                                    <?php foreach ($group as $hz): ?>
                                    <option value="<?= (int)$hz['id'] ?>" data-disease="<?= e($hz['possible_disease'] ?? '') ?>"<?= (string)($hz['cas_no'] ?? '') !== '' ? ' data-cas="' . e($hz['cas_no']) . '"' : '' ?><?= !empty($hz['catalog_no']) ? ' data-no="' . (int)$hz['catalog_no'] . '"' : '' ?><?= in_array((string)$hz['id'], $sel, true) ? ' selected' : '' ?>>
                                        <?= e($hz['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($type === 'select'): ?>
                            <select class="form-select" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?>
                                    <?= !empty($f['place']) ? 'data-place="' . e($f['place']) . '"' : '' ?>>
                                <option value=""><?= !empty($f['place']) ? '— 可选 —' : '— 请选择 —' ?></option>
                                <?php foreach (($options[$name] ?? []) as $ov => $ol): ?>
                                    <option value="<?= e($ov) ?>"
                                        <?= ($f['place'] ?? '') === 'post' ? 'data-workplace="' . (int)($names['post_wp'][(string)$ov] ?? 0) . '"' : '' ?>
                                        <?= (string)$val === (string)$ov ? 'selected' : '' ?>><?= e($ol) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($type === 'date'): ?>
                            <input type="date" class="form-control" name="<?= e($name) ?>" value="<?= e($val) ?>" <?= $required ? 'required' : '' ?>>
                        <?php elseif ($type === 'number'): ?>
                            <input type="number" step="<?= e($f['step'] ?? '1') ?>" min="0" class="form-control" name="<?= e($name) ?>" value="<?= e($val) ?>" <?= $required ? 'required' : '' ?>>
                        <?php else: ?>
                            <input type="text" class="form-control" name="<?= e($name) ?>" value="<?= e($val) ?>"
                                   placeholder="<?= e($f['placeholder'] ?? '') ?>" <?= $required ? 'required' : '' ?>>
                        <?php endif; ?>
                        <?php if (!empty($f['help'])): ?><div class="form-hint"><?= e($f['help']) ?></div><?php endif; ?>
                        <?php if (($f['place'] ?? '') === 'post'): ?><div class="form-hint">选择岗位后将自动带出所属场所。</div><?php endif; ?>
                        <?php if (form_error($name)): ?><div class="invalid-feedback d-block"><?= e(form_error($name)) ?></div><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a class="btn" href="<?= url('ledgers/index', ['key' => $key]) ?>">取消</a>
            <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>保存</button>
        </div>
    </form>
</div>
<?php if ($isEdit && !empty($docs)): ?>
<a id="docs"></a>
<?php include OHAS_ROOT . '/app/Views/partials/doc_list.php'; ?>
<?php elseif ($isEdit): ?>
<a id="docs"></a>
<?php
$linkType = $linkType ?? \App\Helpers\DocLink::typeForLedger($key);
$linkId = (int)$row['id'];
$docs = [];
include OHAS_ROOT . '/app/Views/partials/doc_list.php';
?>
<?php endif; ?>
<?php if (!empty($cfg['place'])): ?>
<script>
/* 场所 / 岗位联动：选岗位自动带出所属场所；选场所后岗位下拉只保留该场所的岗位 */
(function () {
    var wp = document.querySelector('select[data-place="workplace"]');
    var ps = document.querySelector('select[data-place="post"]');
    if (!wp || !ps) return;
    function filterPosts() {
        var w = wp.value;
        Array.prototype.forEach.call(ps.options, function (o) {
            if (!o.value) return;
            var hide = w !== '' && o.getAttribute('data-workplace') !== w;
            o.hidden = hide;
            o.disabled = hide;
        });
        var cur = ps.options[ps.selectedIndex];
        if (cur && cur.value && cur.disabled) ps.value = '';
    }
    wp.addEventListener('change', filterPosts);
    ps.addEventListener('change', function () {
        var cur = ps.options[ps.selectedIndex];
        if (cur && cur.value) {
            wp.value = cur.getAttribute('data-workplace') || '';
            filterPosts();
        }
    });
    filterPosts();
})();
</script>
<?php endif; ?>
