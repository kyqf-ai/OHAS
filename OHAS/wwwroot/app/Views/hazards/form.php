<?php
$isEdit = (int)($hazard['id'] ?? 0) > 0;
$action = $isEdit ? url('hazards/edit', ['id' => $hazard['id']]) : url('hazards/create');
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= $isEdit ? '编辑危害因素' : '新增危害因素' ?></h3>
        <div class="card-actions">
            <a href="<?= url('hazards/index') ?>" class="btn btn-sm"><i class="ti ti-arrow-left me-1"></i>返回列表</a>
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
                        <label class="form-label required">名称</label>
                        <input type="text" class="form-control" name="name" value="<?= old('name', $hazard['name'] ?? '') ?>" placeholder="如：矽尘（游离SiO2含量≥10%）" required>
                        <?php if (form_error('name')): ?><div class="invalid-feedback d-block"><?= e(form_error('name')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label required">类别</label>
                        <select class="form-select" name="category" required>
                            <?php foreach ($categories as $cOpt): ?>
                                <option value="<?= e($cOpt) ?>" <?= old('category', $hazard['category'] ?? '') === $cOpt ? 'selected' : '' ?>><?= e($cOpt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6 col-xl-2">
                    <div class="mb-3">
                        <label class="form-label">目录序号</label>
                        <input type="text" class="form-control" name="catalog_no" value="<?= old('catalog_no', $hazard['catalog_no'] ?? '') ?>" placeholder="如：27">
                    </div>
                </div>
                <div class="col-md-6 col-xl-2">
                    <div class="mb-3">
                        <label class="form-label">CAS 号</label>
                        <input type="text" class="form-control" name="cas_no" value="<?= old('cas_no', $hazard['cas_no'] ?? '') ?>" placeholder="如：71-43-2">
                    </div>
                </div>
                <div class="w-100"></div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label">目录备注（适用范围）</label>
                        <input type="text" class="form-control" name="restriction" value="<?= old('restriction', $hazard['restriction'] ?? '') ?>" placeholder="如：限于井下工人">
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label">可能导致的职业病</label>
                        <input type="text" class="form-control" name="possible_disease" value="<?= old('possible_disease', $hazard['possible_disease'] ?? '') ?>" placeholder="如：矽肺">
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label">接触限值</label>
                        <input type="text" class="form-control" name="limit_note" value="<?= old('limit_note', $hazard['limit_note'] ?? '') ?>" placeholder="如：PC-TWA 3、PC-STEL 6 mg/m³（皮，G1）；总尘 PC-TWA 8、呼尘 4 mg/m³">
                        <div class="form-hint">内置值依据 GBZ 2.1-2019（含第1、2号修改单）、GBZ 2.2-2007、GB 18871，可自行修订；同步目录不会覆盖本栏已维护内容。</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label required">状态</label>
                        <select class="form-select" name="status" required>
                            <option value="启用" <?= old('status', $hazard['status'] ?? '启用') === '启用' ? 'selected' : '' ?>>启用</option>
                            <option value="停用" <?= old('status', $hazard['status'] ?? '') === '停用' ? 'selected' : '' ?>>停用</option>
                        </select>
                    </div>
                </div>
                <div class="w-100"></div>
                <div class="col-xl-8">
                    <div class="mb-3">
                        <label class="form-label">备注</label>
                        <input type="text" class="form-control" name="remark" value="<?= old('remark', $hazard['remark'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <div class="d-flex justify-content-end gap-2">
                <a class="btn" href="<?= url('hazards/index') ?>">取消</a>
                <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>保存</button>
            </div>
        </div>
    </form>
</div>
