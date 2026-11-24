<?php
$isEdit = (int)($wp['id'] ?? 0) > 0;
$action = $isEdit ? url('workplaces/edit', ['id' => $wp['id']]) : url('workplaces/create');
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= $isEdit ? '编辑作业场所' : '新增作业场所' ?></h3>
        <div class="card-actions">
            <a href="<?= url('workplaces/index') ?>" class="btn btn-sm"><i class="ti ti-arrow-left me-1"></i>返回列表</a>
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
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label required">场所名称</label>
                        <input type="text" class="form-control" name="name" value="<?= old('name', $wp['name'] ?? '') ?>" placeholder="如：一车间·打磨工段" required>
                        <?php if (form_error('name')): ?><div class="invalid-feedback d-block"><?= e(form_error('name')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label required">状态</label>
                        <select class="form-select" name="status" required>
                            <option value="启用" <?= old('status', $wp['status'] ?? '启用') === '启用' ? 'selected' : '' ?>>启用</option>
                            <option value="停用" <?= old('status', $wp['status'] ?? '') === '停用' ? 'selected' : '' ?>>停用</option>
                        </select>
                    </div>
                </div>
                <div class="w-100"></div>
                <div class="col-xl-8">
                    <div class="mb-3">
                        <label class="form-label">位置说明</label>
                        <input type="text" class="form-control" name="location" value="<?= old('location', $wp['location'] ?? '') ?>" placeholder="如：厂区东侧 1# 厂房二层">
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="mb-3">
                        <label class="form-label">备注</label>
                        <input type="text" class="form-control" name="remark" value="<?= old('remark', $wp['remark'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <div class="d-flex justify-content-end gap-2">
                <a class="btn" href="<?= url('workplaces/index') ?>">取消</a>
                <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>保存</button>
            </div>
        </div>
    </form>
</div>
