<?php
/** 体检批次表单（表5-1 数据源）：日期 / 种类 / 机构 / 应检人数 / 批次报告 */
$isEdit = (int)($batch['id'] ?? 0) > 0;
$action = $isEdit ? url('exams/edit', ['id' => $batch['id']]) : url('exams/create');
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= $isEdit ? '编辑体检批次' : '新增体检批次' ?></h3>
        <div class="card-subtitle">检查种类依据 GBZ 188-2025（上岗前 / 在岗期间 / 离岗时 / 应急健康检查 等）</div>
        <div class="card-actions">
            <a href="<?= url('exams/index') ?>" class="btn btn-sm"><i class="ti ti-arrow-left me-1"></i>返回批次列表</a>
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
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label required">检查日期</label>
                        <input type="date" class="form-control" name="exam_date" required value="<?= old('exam_date', $batch['exam_date'] ?? '') ?>">
                        <div class="form-hint">批次日期必填：生成应检名单时将复制到每条待检记录</div>
                        <?php if (form_error('exam_date')): ?><div class="invalid-feedback d-block"><?= e(form_error('exam_date')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label required">检查种类</label>
                        <select class="form-select" name="exam_type" required>
                            <?php foreach ($types as $tOpt): ?>
                                <option value="<?= e($tOpt) ?>" <?= old('exam_type', $batch['exam_type'] ?? '在岗期间') === $tOpt ? 'selected' : '' ?>><?= e($tOpt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (form_error('exam_type')): ?><div class="invalid-feedback d-block"><?= e(form_error('exam_type')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label required">检查机构</label>
                        <input type="text" class="form-control" name="agency" value="<?= old('agency', $batch['agency'] ?? '') ?>" placeholder="如：××市职业病防治院" required>
                        <?php if (form_error('agency')): ?><div class="invalid-feedback d-block"><?= e(form_error('agency')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label">应检人数</label>
                        <input type="number" min="0" step="1" class="form-control" name="planned_count" value="<?= old('planned_count', (string)($batch['planned_count'] ?? 0)) ?>">
                        <div class="form-hint">实检人数按登记的检查记录自动统计，无需填写</div>
                        <?php if (form_error('planned_count')): ?><div class="invalid-feedback d-block"><?= e(form_error('planned_count')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="w-100"></div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">报告原件</label>
                        <div class="form-control-plaintext text-secondary small">保存后在该批次的检查记录页上传总评/总结报告（可多份，统一归入「分类文档」）。</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-8">
                    <div class="mb-3">
                        <label class="form-label">备注</label>
                        <input type="text" class="form-control" name="remark" value="<?= old('remark', $batch['remark'] ?? '') ?>" placeholder="如：2026 年度在岗期间职业健康检查">
                        <?php if (form_error('remark')): ?><div class="invalid-feedback d-block"><?= e(form_error('remark')) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <div class="d-flex justify-content-end gap-2">
                <a class="btn" href="<?= url('exams/index') ?>">取消</a>
                <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>保存</button>
            </div>
        </div>
    </form>
</div>
