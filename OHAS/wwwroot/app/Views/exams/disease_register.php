<?php
/**
 * 由体检记录生成职业病 / 疑似职业病登记（表5-3 / 表5-4）。
 * $record 体检记录，$worker 劳动者，$kind 1=疑似 0=确诊
 */
$isSus = (int)$kind === 1;
$batchId = (int)($record['batch_id'] ?? 0);
$backUrl = $batchId > 0 ? url('exams/records', ['batch_id' => $batchId]) : url('exams/records');
[$cLabel, $cClass] = \App\Models\ExamRecord::conclusionBadge($record['conclusion']);
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= $isSus ? '生成疑似职业病登记（表5-4）' : '生成职业病登记（表5-3）' ?></h3>
        <div class="card-subtitle">登记保存后同步显示在劳动者信息卡「职业病诊断」中，并与本条体检记录保持关联</div>
        <div class="card-actions">
            <a href="<?= e($backUrl) ?>" class="btn btn-sm"><i class="ti ti-arrow-left me-1"></i>返回记录列表</a>
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
    <form method="post" action="<?= url('exams/diseaseRegister', ['id' => (int)$record['id']]) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
        <div class="card-body">
            <div class="hr-text">来源体检记录</div>
            <div class="row g-3 mb-2">
                <div class="col-md-3"><div class="subheader">劳动者</div>
                    <a href="<?= url('workers/show', ['id' => $worker['id']]) ?>"><?= e($worker['name']) ?></a>
                    <span class="text-secondary font-monospace ms-1"><?= e($worker['archive_no']) ?></span></div>
                <div class="col-md-3"><div class="subheader">检查日期 / 种类</div><?= d($record['exam_date']) ?> · <?= e($record['exam_type']) ?></div>
                <div class="col-md-3"><div class="subheader">检查结论</div><span class="badge <?= $cClass ?>"><?= e($cLabel) ?></span></div>
                <div class="col-md-3"><div class="subheader">检查机构</div><?= e($record['agency'] ?: '—') ?></div>
                <?php if ((string)$record['post_name'] !== '' || (string)$record['hazards_snapshot'] !== ''): ?>
                <div class="col-12"><div class="subheader">受检时岗位 / 接害因素</div>
                    <?= e($record['post_name'] ?: '—') ?><?php if ((string)$record['hazards_snapshot'] !== ''): ?> · <?= e($record['hazards_snapshot']) ?><?php endif; ?></div>
                <?php endif; ?>
            </div>

            <div class="hr-text">登记内容</div>
            <div class="alert alert-info">
                <i class="ti ti-info-circle alert-icon"></i>
                <div>性质按体检结论自动确定为<b><?= $isSus ? '疑似职业病' : '职业病（确诊）' ?></b>。病名需按体检机构的结论或诊断证明填写，保存前请核对。</div>
            </div>
            <div class="row g-3">
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label required">职业病名称</label>
                        <input type="text" class="form-control <?= form_error('name') ? 'is-invalid' : '' ?>" name="name" required maxlength="60"
                               list="disease-options" value="<?= e(old('name')) ?>" placeholder="如：噪声聋、矽肺（疑似）">
                        <div class="form-hint">可从《职业病分类和目录》中选择，也可按体检报告填写具体名称</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label"><?= $isSus ? '提出疑似意见 / 诊断时间' : '诊断时间' ?></label>
                        <input type="date" class="form-control" name="diag_date" value="<?= e(old('diag_date', (string)$record['exam_date'])) ?>">
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">诊断（检查）机构</label>
                        <input type="text" class="form-control" name="hospital" maxlength="60" value="<?= e(old('hospital', (string)$record['agency'])) ?>">
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">诊断级别</label>
                        <input type="text" class="form-control" name="level" maxlength="30" value="<?= e(old('level')) ?>" placeholder="如：轻度 / 壹期（确诊后填写）">
                    </div>
                </div>
                <div class="col-md-6 col-xl-8">
                    <div class="mb-3">
                        <label class="form-label">处理情况</label>
                        <input type="text" class="form-control" name="handling" maxlength="200" value="<?= e(old('handling', (string)($record['handling'] ?? ''))) ?>" placeholder="如：已调离接害岗位，转诊职业病诊断机构">
                    </div>
                </div>
                <div class="col-12">
                    <div class="mb-3">
                        <label class="form-label">备注</label>
                        <input type="text" class="form-control" name="remark" maxlength="200" value="<?= e(old('remark')) ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="<?= e($backUrl) ?>" class="btn me-2">取消</a>
            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i>生成登记</button>
        </div>
    </form>
</div>
<datalist id="disease-options">
    <?php foreach (\App\Helpers\DiseaseCatalog::all() as $dn): ?>
        <option value="<?= e($dn) ?>"></option>
    <?php endforeach; ?>
</datalist>
