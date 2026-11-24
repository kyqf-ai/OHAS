<?php
/**
 * 检查记录表单（表6-3 单条记录）：
 * 受检人 → 检查信息（日期/种类/结论/机构）→ 快照（岗位/接害）→ 处理与签字 → 报告原件
 * 结论口径：GBZ 188-2025（目前未见异常/疑似职业病/职业禁忌证/其他疾病或异常/职业病）
 */
$isEdit = (int)($record['id'] ?? 0) > 0;
$action = $isEdit ? url('exams/recordEdit', ['id' => $record['id']]) : url('exams/recordCreate');
$batchId = (int)($record['batch_id'] ?? 0);
$oldWorker = (string)old('worker_id', (string)($record['worker_id'] ?? ''));
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= $isEdit ? '编辑检查记录' : '登记检查记录' ?></h3>
        <div class="card-subtitle">保存后自动关联到该劳动者的信息卡查看页与表6-3 打印件</div>
        <div class="card-actions">
            <a href="<?= $batchId > 0 ? url('exams/records', ['batch_id' => $batchId]) : url('exams/records') ?>" class="btn btn-sm"><i class="ti ti-arrow-left me-1"></i>返回记录列表</a>
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
    <form method="post" action="<?= e($action) ?>" id="record-form">
        <?= csrf_field() ?>
        <input type="hidden" name="batch_id" value="<?= $batchId ?>">
        <?php if ($isEdit): ?>
        <input type="hidden" name="worker_id" value="<?= (int)$record['worker_id'] ?>">
        <?php endif; ?>
        <div class="card-body">
            <div class="hr-text">受检劳动者</div>
            <div class="row g-3">
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label required">劳动者</label>
                        <select class="form-select" name="worker_id" id="worker-select" required <?= $isEdit ? 'disabled' : '' ?>>
                            <option value="">— 请选择 —</option>
                            <?php foreach ($workers as $wk): ?>
                                <option value="<?= (int)$wk['id'] ?>" data-post="<?= e($wk['current_post'] ?? '') ?>"
                                    <?= (string)$wk['id'] === $oldWorker ? 'selected' : '' ?>>
                                    <?= e($wk['name']) ?>（<?= e($wk['archive_no']) ?><?= $wk['current_post'] ? ' · ' . e($wk['current_post']) : '' ?><?= ($wk['status'] ?? '') !== '在职' ? ' · ' . e($wk['status']) : '' ?>）
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isEdit): ?>
                            <div class="form-hint">编辑时不可更换受检人；如需调整请删除后重新登记。</div>
                        <?php endif; ?>
                        <?php if (form_error('worker_id')): ?><div class="invalid-feedback d-block"><?= e(form_error('worker_id')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-8">
                    <div class="alert alert-info mb-0" id="snapshot-hint" hidden>
                        <i class="ti ti-info-circle alert-icon"></i>
                        <div id="snapshot-text"></div>
                    </div>
                </div>
            </div>

            <div class="hr-text">检查信息</div>
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label required">检查日期</label>
                        <input type="date" class="form-control" name="exam_date" id="exam-date" required value="<?= old('exam_date', $record['exam_date'] ?? '') ?>">
                        <?php if (form_error('exam_date')): ?><div class="invalid-feedback d-block"><?= e(form_error('exam_date')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label required">检查种类</label>
                        <select class="form-select" name="exam_type" required>
                            <?php foreach ($types as $tOpt): ?>
                                <option value="<?= e($tOpt) ?>" <?= old('exam_type', $record['exam_type'] ?? '在岗期间') === $tOpt ? 'selected' : '' ?>><?= e($tOpt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (form_error('exam_type')): ?><div class="invalid-feedback d-block"><?= e(form_error('exam_type')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label">检查结论</label>
                        <select class="form-select" name="conclusion" id="conclusion-select">
                            <option value="">— 待出结论 —</option>
                            <?php foreach ($conclusions as $cOpt): ?>
                                <option value="<?= e($cOpt) ?>" <?= old('conclusion', $record['conclusion'] ?? '') === $cOpt ? 'selected' : '' ?>><?= e($cOpt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">GBZ 188-2025 四类结论：目前未见异常 / 疑似职业病 / 职业禁忌证 / 其他疾病或异常；「职业病」为 171 号文表样扩充（诊断机构结论）。疑似职业病须告知并申请诊断；职业禁忌证须调离接害岗位</div>
                        <?php if (form_error('conclusion')): ?><div class="invalid-feedback d-block"><?= e(form_error('conclusion')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="mb-3">
                        <label class="form-label">检查机构</label>
                        <input type="text" class="form-control" name="agency" value="<?= old('agency', $record['agency'] ?? '') ?>" placeholder="如：××市职业病防治院">
                        <?php if (form_error('agency')): ?><div class="invalid-feedback d-block"><?= e(form_error('agency')) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="hr-text">受检时岗位与接触危害（快照，可修改）</div>
            <div class="row g-3">
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">受检时岗位</label>
                        <input type="text" class="form-control" name="post_name" id="post-name" value="<?= old('post_name', $record['post_name'] ?? '') ?>" placeholder="留空按检查日期自动带出当时岗位">
                        <?php if (form_error('post_name')): ?><div class="invalid-feedback d-block"><?= e(form_error('post_name')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-8">
                    <div class="mb-3">
                        <label class="form-label">受检时接触职业病危害因素</label>
                        <input type="text" class="form-control" name="hazards_snapshot" id="hazards-input" value="<?= old('hazards_snapshot', $record['hazards_snapshot'] ?? '') ?>" placeholder="留空按检查日期自动带出当时接害因素">
                        <?php if (form_error('hazards_snapshot')): ?><div class="invalid-feedback d-block"><?= e(form_error('hazards_snapshot')) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="hr-text">处理与签字（表6-3）</div>
            <div class="row g-3">
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">人员处理情况</label>
                        <textarea class="form-control" name="handling" rows="2" placeholder="调离、暂时脱离工作岗位、复查、医学观察、职业病诊断等处理安置情况"><?= old('handling', $record['handling'] ?? '') ?></textarea>
                        <div class="form-hint">结论为「目前未见异常」或「其他疾病或异常」的可填「—」</div>
                        <?php if (form_error('handling')): ?><div class="invalid-feedback d-block"><?= e(form_error('handling')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">现场处理情况</label>
                        <textarea class="form-control" name="site_handling" rows="2" placeholder="造成职业损害的作业岗位现场及个体防护用品整改达标情况"><?= old('site_handling', $record['site_handling'] ?? '') ?></textarea>
                        <?php if (form_error('site_handling')): ?><div class="invalid-feedback d-block"><?= e(form_error('site_handling')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">结果告知方式</label>
                        <select class="form-select" name="notice_method">
                            <option value="">未告知</option>
                            <?php foreach (['书面签收' => '书面签收', '当面告知' => '当面告知', '电话告知' => '电话告知', '其他' => '其他'] as $v => $lbl): ?>
                            <option value="<?= e($v) ?>" <?= old('notice_method', $record['notice_method'] ?? '') === $v ? 'selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">法定告知形式为「书面签收」；电话/当面/其他不算闭环。须在本记录「报告原件」区挂「体检结果书面告知/签收」原件；禁忌证还须挂调岗通知书或结束接害在岗段</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">本人签字确认日期</label>
                        <input type="date" class="form-control" name="signed_at" value="<?= old('signed_at', $record['signed_at'] ?? '') ?>">
                        <div class="form-hint">检查结果书面告知劳动者本人后填写</div>
                        <?php if (form_error('signed_at')): ?><div class="invalid-feedback d-block"><?= e(form_error('signed_at')) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="hr-text">报告原件归档</div>
            <div class="row g-3">
                <div class="col-md-6 col-xl-4">
                    <div class="mb-3">
                        <label class="form-label">报告原件</label>
                        <div class="form-control-plaintext text-secondary small">原件在页面下方「报告原件」区上传（可多份，统一归入「分类文档」）。</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-8">
                    <div class="mb-3">
                        <label class="form-label">备注</label>
                        <input type="text" class="form-control" name="remark" value="<?= old('remark', $record['remark'] ?? '') ?>">
                        <?php if (form_error('remark')): ?><div class="invalid-feedback d-block"><?= e(form_error('remark')) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <div class="d-flex justify-content-end gap-2">
                <a class="btn" href="<?= $batchId > 0 ? url('exams/records', ['batch_id' => $batchId]) : url('exams/records') ?>">取消</a>
                <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>保存</button>
            </div>
        </div>
    </form>
</div>

<script>
/* 选择受检人 / 修改检查日期后带出「受检当时」的岗位与接害快照（fetch workerSnapshot，带 exam_date） */
(function () {
    var sel = document.getElementById('worker-select');
    var hint = document.getElementById('snapshot-hint');
    var text = document.getElementById('snapshot-text');
    var postInput = document.getElementById('post-name');
    var hazInput = document.getElementById('hazards-input');
    var dateInput = document.getElementById('exam-date');
    if (!sel) return;

    function loadSnapshot() {
        var wid = sel.value;
        if (!wid || !hint) return;
        var urlBase = '<?= url('exams/workerSnapshot') ?>';
        var qs = 'worker_id=' + encodeURIComponent(wid);
        if (dateInput && dateInput.value) qs += '&exam_date=' + encodeURIComponent(dateInput.value);
        fetch(urlBase + (urlBase.indexOf('?') > -1 ? '&' : '?') + qs, {
            credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok) { hint.hidden = true; return; }
            var parts = [];
            if (data.archive_no) parts.push('档案号 ' + data.archive_no);
            if (data.post_name) parts.push('受检时岗位 ' + data.post_name);
            // 全部用 textContent 组装，防止姓名/快照中夹带 HTML 被执行
            text.textContent = '';
            var nameB = document.createElement('b');
            nameB.textContent = data.name || '';
            text.appendChild(nameB);
            if (parts.length) {
                text.appendChild(document.createTextNode(' · ' + parts.join(' · ')));
            }
            if (data.hazards) {
                var hz = document.createElement('div');
                hz.className = 'text-secondary mt-1';
                hz.textContent = '受检时接触：' + data.hazards;
                text.appendChild(hz);
            }
            hint.hidden = false;
            if (postInput && !postInput.value && data.post_name) postInput.value = data.post_name;
            if (hazInput && !hazInput.value && data.hazards) hazInput.value = data.hazards;
        }).catch(function () { hint.hidden = true; });
    }
    sel.addEventListener('change', function () {
        if (postInput) postInput.value = '';
        if (hazInput) hazInput.value = '';
        loadSnapshot();
    });
    // 检查日期变化：仅在快照两栏仍为空（待自动带出）时重新取当时岗位
    if (dateInput) {
        dateInput.addEventListener('change', function () {
            if (postInput && hazInput && postInput.value === '' && hazInput.value === '') loadSnapshot();
        });
    }
    if (sel.value) loadSnapshot();
})();
</script>

<?php if ((int)($record['id'] ?? 0) > 0): $linkType = 'exam_record'; $linkId = (int)$record['id']; $docHeading = '个人体检报告原件'; ?>
<div class="mt-3"><?php include OHAS_ROOT . '/app/Views/partials/doc_list.php'; ?></div>
<?php endif; ?>
