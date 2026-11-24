<?php $c = $company ?? []; ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">单位信息</h3>
        <div class="card-subtitle">对应《职业病危害项目申报表》基本信息项，档案封皮与样表页眉引用单位名称</div>
        <div class="card-actions">
            <a class="btn" href="<?= url('prints/sheet', ['t' => '表2-2']) ?>" target="_blank" title="职业病危害项目申报基本情况表（表2-2）打印"><i class="ti ti-printer me-1"></i>打印申报表</a>
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
    <form method="post" action="<?= url('company/edit') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label required">单位名称</label>
                        <input type="text" class="form-control" name="name" value="<?= old('name', $c['name'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">统一社会信用代码</label>
                        <input type="text" class="form-control font-monospace" name="credit_code" value="<?= old('credit_code', $c['credit_code'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">法定代表人</label>
                        <input type="text" class="form-control" name="legal_person" value="<?= old('legal_person', $c['legal_person'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">行业分类</label>
                        <input type="text" class="form-control" name="industry" value="<?= old('industry', $c['industry'] ?? '') ?>" placeholder="如：制造业-通用设备制造业">
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">企业规模</label>
                        <input type="text" class="form-control" name="scale" value="<?= old('scale', $c['scale'] ?? '') ?>" placeholder="如：小型 / 中型">
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">职业卫生管理负责人</label>
                        <input type="text" class="form-control" name="hygienist_name" value="<?= old('hygienist_name', $c['hygienist_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">负责人联系方式</label>
                        <input type="text" class="form-control" name="hygienist_phone" value="<?= old('hygienist_phone', $c['hygienist_phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="w-100"></div>
                <div class="col-xl-8">
                    <div class="mb-3">
                        <label class="form-label">注册地址</label>
                        <input type="text" class="form-control" name="reg_address" value="<?= old('reg_address', $c['reg_address'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="mb-3">
                        <label class="form-label">工作场所地址</label>
                        <input type="text" class="form-control" name="work_address" value="<?= old('work_address', $c['work_address'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>保存</button>
        </div>
    </form>
</div>
