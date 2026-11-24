<?php
/** 检测报告表单（监测检测档案目录第 8~9 项数据源）：编号 / 类别 / 机构 / 日期 / 结论 / 原件归档 */
$isEdit = (int)($report['id'] ?? 0) > 0;
$action = $isEdit ? url('detections/edit', ['id' => $report['id']]) : url('detections/create');
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= $isEdit ? '编辑检测报告' : '登记检测报告' ?></h3>
        <div class="card-subtitle">报告类别对应监测检测档案目录第 8~9 项；限值判定口径 GBZ 2.1-2019（含修改单）/ GBZ 2.2-2007</div>
        <div class="card-actions">
            <a href="<?= url('detections/index') ?>" class="btn btn-sm"><i class="ti ti-arrow-left me-1"></i>返回报告列表</a>
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
                        <label class="form-label required">报告编号</label>
                        <input type="text" class="form-control" name="report_no" value="<?= old('report_no', $report['report_no'] ?? '') ?>" placeholder="如：JC-2026-018" required>
                        <?php if (form_error('report_no')): ?><div class="invalid-feedback d-block"><?= e(form_error('report_no')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label required">报告类别</label>
                        <select class="form-select" name="category" required>
                            <?php foreach ($categories as $cOpt): ?>
                                <option value="<?= e($cOpt) ?>" <?= old('category', $report['category'] ?? '定期检测') === $cOpt ? 'selected' : '' ?>><?= e($cOpt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (form_error('category')): ?><div class="invalid-feedback d-block"><?= e(form_error('category')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label required">检测机构</label>
                        <input type="text" class="form-control" name="agency" value="<?= old('agency', $report['agency'] ?? '') ?>" placeholder="如：××职业卫生检测评价有限公司" required>
                        <?php if (form_error('agency')): ?><div class="invalid-feedback d-block"><?= e(form_error('agency')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label">检测日期</label>
                        <input type="date" class="form-control" name="test_date" value="<?= old('test_date', $report['test_date'] ?? '') ?>">
                        <div class="form-hint">现场采样/检测实施日期，表6-2 归集按此日期匹配职业史区间</div>
                        <?php if (form_error('test_date')): ?><div class="invalid-feedback d-block"><?= e(form_error('test_date')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="w-100"></div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label">报告日期</label>
                        <input type="date" class="form-control" name="report_date" value="<?= old('report_date', $report['report_date'] ?? '') ?>">
                        <?php if (form_error('report_date')): ?><div class="invalid-feedback d-block"><?= e(form_error('report_date')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label">报告原件</label>
                        <div class="form-control-plaintext text-secondary small">保存后在报告详情页上传（可多份，统一归入「分类文档」）。</div>
                    </div>
                </div>
                <div class="col-md-12 col-xl-6">
                    <div class="mb-3">
                        <label class="form-label">结论摘要</label>
                        <textarea class="form-control" name="conclusion" rows="2" placeholder="如：共检测 12 个点，11 项合格，1 项超标（喷漆房甲苯短时接触浓度超标，已列入治理计划）"><?= old('conclusion', $report['conclusion'] ?? '') ?></textarea>
                        <?php if (form_error('conclusion')): ?><div class="invalid-feedback d-block"><?= e(form_error('conclusion')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-12">
                    <div class="mb-3">
                        <label class="form-label">备注</label>
                        <input type="text" class="form-control" name="remark" value="<?= old('remark', $report['remark'] ?? '') ?>" placeholder="如：2026 年度定期检测（第三次全面检测）">
                        <?php if (form_error('remark')): ?><div class="invalid-feedback d-block"><?= e(form_error('remark')) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <div class="d-flex justify-content-end gap-2">
                <a class="btn" href="<?= url('detections/index') ?>">取消</a>
                <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>保存</button>
            </div>
        </div>
    </form>
</div>
