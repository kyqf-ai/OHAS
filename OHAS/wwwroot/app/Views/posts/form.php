<?php
/** 岗位表单（Tabler 版）：基本信息 + 危害因素配置动态行 */
$isEdit = $mode === 'edit';
$action = $isEdit ? url('posts/edit', ['id' => $post['id']]) : url('posts/create');
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= $isEdit ? '编辑岗位' : '新增岗位' ?></h3>
        <div class="card-actions">
            <a href="<?= url('posts/index') ?>" class="btn btn-sm"><i class="ti ti-arrow-left me-1"></i>返回列表</a>
        </div>
    </div>
    <?php if (form_errors()): ?>
    <div class="card-body pb-0">
        <div class="alert alert-danger alert-dismissible" role="alert">
            <i class="ti ti-alert-triangle alert-icon"></i>
            <div><?php foreach (form_errors() as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="关闭"></a>
        </div>
    </div>
    <?php endif; ?>
    <form method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="card-body pb-0">
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label required">所属作业场所</label>
                        <select class="form-select" name="workplace_id" id="workplace-select" required>
                            <option value="">— 请选择 —</option>
                            <?php foreach ($workplaces as $wOpt): ?>
                                <option value="<?= (int)$wOpt['id'] ?>" <?= (int)old('workplace_id', $post['workplace_id'] ?? 0) === (int)$wOpt['id'] ? 'selected' : '' ?>>
                                    <?= e($wOpt['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (form_error('workplace_id')): ?><div class="invalid-feedback d-block"><?= e(form_error('workplace_id')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label required">岗位名称</label>
                        <input type="text" class="form-control" name="name" value="<?= old('name', $post['name'] ?? '') ?>" placeholder="如：打磨工" required>
                        <?php if (form_error('name')): ?><div class="invalid-feedback d-block"><?= e(form_error('name')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-2">
                    <div class="mb-3">
                        <label class="form-label required">状态</label>
                        <select class="form-select" name="status" required>
                            <option value="启用" <?= old('status', $post['status'] ?? '启用') === '启用' ? 'selected' : '' ?>>启用</option>
                            <option value="停用" <?= old('status', $post['status'] ?? '') === '停用' ? 'selected' : '' ?>>停用</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">备注</label>
                        <input type="text" class="form-control" name="remark" value="<?= old('remark', $post['remark'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="hr-text hr-text-spaceless">
                <span class="bg-surface px-2"><i class="ti ti-biohazard me-1 text-secondary"></i>危害因素配置</span>
            </div>
            <p class="text-secondary small mb-3">此处维护「岗位 × 危害因素 × 防护措施」，劳动者职业史选中岗位后自动带出以下内容。</p>

            <div class="ph-rows" id="ph-rows">
                <?php
                // 编辑模式：已有配置行；校验失败：从旧值重建；新增：两行空配置
                if ($hazards) {
                    $phRows = $hazards;
                } elseif (form_errors() && isset($_SESSION['_old']['ph_hazard_id']) && is_array($_SESSION['_old']['ph_hazard_id'])) {
                    $phRows = [];
                    foreach ($_SESSION['_old']['ph_hazard_id'] as $i => $hid) {
                        $phRows[] = [
                            'hazard_id' => $hid,
                            'exposure_type' => $_SESSION['_old']['ph_exposure_type'][$i] ?? '',
                            'source' => $_SESSION['_old']['ph_source'][$i] ?? '',
                            'engineering_controls' => $_SESSION['_old']['ph_engineering_controls'][$i] ?? '',
                            'ppe' => $_SESSION['_old']['ph_ppe'][$i] ?? '',
                        ];
                    }
                    $phRows = $phRows ?: array_fill(0, 1, ['hazard_id' => '', 'exposure_type' => '', 'source' => '', 'engineering_controls' => '', 'ppe' => '']);
                } else {
                    $phRows = array_fill(0, 2, ['hazard_id' => '', 'exposure_type' => '', 'source' => '', 'engineering_controls' => '', 'ppe' => '']);
                }
                ?>
                <?php foreach ($phRows as $ph): ?>
                <div class="ph-row">
                    <div>
                        <label class="form-label">危害因素</label>
                        <select class="form-select" name="ph_hazard_id[]" data-hazard-picker>
                            <option value="">— 选择危害因素 —</option>
                            <?php foreach ($hazardGroups as $cat => $group): ?>
                                <optgroup label="<?= e($cat) ?>">
                                    <?php foreach ($group as $hz): ?>
                                        <option value="<?= (int)$hz['id'] ?>" data-disease="<?= e($hz['possible_disease']) ?>"<?= (string)$hz['cas_no'] !== '' ? ' data-cas="' . e($hz['cas_no']) . '"' : '' ?><?= $hz['catalog_no'] ? ' data-no="' . (int)$hz['catalog_no'] . '"' : '' ?><?= (string)$ph['hazard_id'] === (string)$hz['id'] ? ' selected' : '' ?>>
                                            <?= e($hz['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">接触方式</label>
                        <select class="form-select" name="ph_exposure_type[]">
                            <option value="">—</option>
                            <?php foreach ($exposureTypes as $et): ?>
                                <option value="<?= e($et) ?>" <?= ($ph['exposure_type'] ?? '') === $et ? 'selected' : '' ?>><?= e($et) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">危害来源</label>
                        <input type="text" class="form-control" name="ph_source[]" value="<?= e($ph['source'] ?? '') ?>" placeholder="设备/工序">
                    </div>
                    <div>
                        <label class="form-label">工程防护设施</label>
                        <input type="text" class="form-control" name="ph_engineering_controls[]" value="<?= e($ph['engineering_controls'] ?? '') ?>" placeholder="如：布袋除尘器">
                    </div>
                    <div>
                        <label class="form-label">个体防护用品</label>
                        <input type="text" class="form-control" name="ph_ppe[]" value="<?= e($ph['ppe'] ?? '') ?>" placeholder="如：3M 7502防毒面具">
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-icon ph-del" title="移除此行"><i class="ti ti-x"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex align-items-center gap-3 mt-3">
                <button type="button" class="btn" id="ph-add"><i class="ti ti-plus me-1"></i>增加一行危害配置</button>
                <span class="text-yellow small" id="ph-warn" <?= $isEdit && !$hazards ? '' : 'hidden' ?>>
                    <i class="ti ti-alert-triangle me-1"></i>该岗位尚未配置任何危害因素，将影响表4-2汇总与表6-2归集。
                </span>
            </div>
        </div>

        <div class="card-footer text-end">
            <div class="d-flex justify-content-end gap-2">
                <a class="btn" href="<?= url('posts/index') ?>">取消</a>
                <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>保存</button>
            </div>
        </div>
    </form>
</div>
