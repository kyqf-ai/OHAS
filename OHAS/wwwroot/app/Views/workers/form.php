<?php
/**
 * 劳动者信息卡（表6-1）：基本信息 + 职业史 + 既往病史 + 职业病诊断
 * 子表通过 fetch 提交（saveHistory/saveIllness/saveDisease），保存后整页刷新。
 */
$isEdit = (int)($worker['id'] ?? 0) > 0;
$action = $isEdit ? url('workers/edit', ['id' => $worker['id']]) : url('workers/create');
$photoUrl = $isEdit && !empty($worker['photo']) ? url('media/photo', ['f' => $worker['photo']]) : null;
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= $isEdit ? '信息卡编辑 · ' . e($worker['name']) : '新建劳动者信息卡' ?></h3>
        <div class="card-actions">
            <?php if ($isEdit): ?>
                <a class="btn btn-sm" href="<?= url('workers/printCard', ['id' => $worker['id']]) ?>" target="_blank">
                    <i class="ti ti-printer me-1"></i>打印表6-1
                </a>
            <?php endif; ?>
            <a class="btn btn-sm" href="<?= url('workers/index') ?>"><i class="ti ti-arrow-left me-1"></i>返回列表</a>
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

    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" id="worker-form">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-auto">
                    <div class="photo-box mb-2" id="photo-box">
                        <?php if ($photoUrl): ?>
                            <img src="<?= e($photoUrl) ?>" alt="照片">
                        <?php else: ?>
                            暂无照片
                        <?php endif; ?>
                    </div>
                    <input class="form-control form-control-sm" type="file" name="photo" accept="image/jpeg,image/png" id="photo-input">
                    <?php if (form_error('photo')): ?><div class="invalid-feedback d-block"><?= e(form_error('photo')) ?></div><?php endif; ?>
                    <div class="form-hint mt-1">jpg / png，≤2MB，自动压缩保存</div>
                </div>
                <div class="col">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6 col-xl-4">
                            <div class="mb-3">
                                <label class="form-label">档案号</label>
                                <input type="text" class="form-control font-monospace" name="archive_no" value="<?= old('archive_no', $worker['archive_no'] ?? '') ?>" placeholder="留空自动按 年份-流水号 生成">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-4">
                            <div class="mb-3">
                                <label class="form-label required">姓名</label>
                                <input type="text" class="form-control" name="name" value="<?= old('name', $worker['name'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-xl-4">
                            <div class="mb-3">
                                <label class="form-label">性别</label>
                                <select class="form-select" name="gender">
                                    <option value="">—</option>
                                    <option value="男" <?= old('gender', $worker['gender'] ?? '') === '男' ? 'selected' : '' ?>>男</option>
                                    <option value="女" <?= old('gender', $worker['gender'] ?? '') === '女' ? 'selected' : '' ?>>女</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-xl-4">
                            <div class="mb-3">
                                <label class="form-label">出生日期</label>
                                <input type="date" class="form-control" name="birth_date" value="<?= old('birth_date', $worker['birth_date'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-4">
                            <div class="mb-3">
                                <label class="form-label">身份证号</label>
                                <input type="text" class="form-control font-monospace" name="id_card" value="<?= old('id_card', $worker['id_card'] ?? '') ?>" placeholder="18位，可自动推导性别与出生日期" maxlength="18">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-4">
                            <div class="mb-3">
                                <label class="form-label">籍贯</label>
                                <input type="text" class="form-control" name="native_place" value="<?= old('native_place', $worker['native_place'] ?? '') ?>" placeholder="如：山东济南">
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-xl-4">
                            <div class="mb-3">
                                <label class="form-label">婚姻状况</label>
                                <select class="form-select" name="marital_status">
                                    <option value="">—</option>
                                    <?php foreach ($maritalOptions as $mOpt): ?>
                                        <option value="<?= e($mOpt) ?>" <?= old('marital_status', $worker['marital_status'] ?? '') === $mOpt ? 'selected' : '' ?>><?= e($mOpt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-xl-4">
                            <div class="mb-3">
                                <label class="form-label">文化程度</label>
                                <select class="form-select" name="education">
                                    <option value="">—</option>
                                    <?php foreach ($educationOptions as $eOpt): ?>
                                        <option value="<?= e($eOpt) ?>" <?= old('education', $worker['education'] ?? '') === $eOpt ? 'selected' : '' ?>><?= e($eOpt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-4">
                            <div class="mb-3">
                                <label class="form-label">嗜好</label>
                                <input type="text" class="form-control" name="hobby" value="<?= old('hobby', $worker['hobby'] ?? '') ?>" placeholder="如：吸烟、饮酒">
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-xl-4">
                            <div class="mb-3">
                                <label class="form-label">参加工作时间</label>
                                <input type="date" class="form-control" name="work_start_date" value="<?= old('work_start_date', $worker['work_start_date'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-xl-4">
                            <div class="mb-3">
                                <label class="form-label">入职本单位时间</label>
                                <input type="date" class="form-control" name="entry_date" value="<?= old('entry_date', $worker['entry_date'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-xl-4">
                            <div class="mb-3">
                                <label class="form-label required">在职状态</label>
                                <select class="form-select" name="status" required>
                                    <?php foreach ($statusOptions as $sOpt): ?>
                                        <option value="<?= e($sOpt) ?>" <?= old('status', $worker['status'] ?? '在职') === $sOpt ? 'selected' : '' ?>><?= e($sOpt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-xl-4">
                            <div class="mb-3">
                                <label class="form-label">离岗/离职日期</label>
                                <input type="date" class="form-control" name="leave_date" value="<?= old('leave_date', $worker['leave_date'] ?? '') ?>">
                                <div class="form-hint">状态非「在职」时必填；保存时自动据此收尾所有未结束职业史段</div>
                                <?php if (form_error('leave_date')): ?><div class="invalid-feedback d-block"><?= e(form_error('leave_date')) ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-xl-4">
                            <div class="mb-3">
                                <label class="form-label">联系电话</label>
                                <input type="text" class="form-control" name="phone" value="<?= old('phone', $worker['phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">职务角色</label>
                                <div class="d-flex gap-3 pt-1">
                                    <?php
                                    $dutySaved = array_filter(array_map('trim', explode(',', (string)($_SESSION['_old']['duty_role'] ?? $worker['duty_role'] ?? ''))));
                                    foreach (['主要负责人', '职业卫生管理人员'] as $role): ?>
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" name="duty_role[]" value="<?= e($role) ?>"
                                                <?= in_array($role, $dutySaved, true) ? 'checked' : '' ?>>
                                            <span class="form-check-label"><?= e($role) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-hint">勾选后需补充管理类资料：任命文件、职责说明、管理人员培训证书；不影响接害属性判定。</div>
                            </div>
                        </div>
                        <div class="w-100"></div>
                        <div class="col-xl-8">
                            <div class="mb-3">
                                <label class="form-label">备注</label>
                                <input type="text" class="form-control" name="remark" value="<?= old('remark', $worker['remark'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <div class="d-flex justify-content-end gap-2">
                <a class="btn" href="<?= url('workers/index') ?>">取消</a>
                <button class="btn btn-primary" type="submit">
                    <i class="ti ti-device-floppy me-1"></i><?= $isEdit ? '保存基本信息' : '建档并继续补充职业史' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<?php if ($isEdit): ?>
<div class="card" id="history-card">
    <div class="card-header">
        <h3 class="card-title">职业史及职业病危害接触史</h3>
        <div class="card-subtitle">本单位经历选岗位后自动带出接害因素与防护措施；外单位经历手工填写</div>
    </div>
    <?php if (!$worker['history']): ?>
        <div class="empty empty-sm">
            <div class="empty-icon"><i class="ti ti-calendar"></i></div>
            <p class="empty-title">暂无记录</p>
            <p class="empty-subtitle text-secondary">在下方表单添加第一段岗位经历。</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr><th class="w-auto">起止时间</th><th>工作单位</th><th>工种/岗位</th><th>接触职业病危害因素</th><th>防护措施</th><th class="w-1"></th></tr>
            </thead>
            <tbody>
                <?php foreach ($worker['history'] as $h): ?>
                <tr>
                    <td class="font-monospace small text-nowrap"><?= e($h['start_date']) ?> ~ <?= $h['end_date'] ? e($h['end_date']) : '<span class="badge bg-green-lt">至今</span>' ?></td>
                    <td><?= e($h['company_name']) ?></td>
                    <td>
                        <?= e($h['post_name'] ?: '—') ?>
                        <?= $h['post_id'] ? ' <span class="badge bg-blue-lt">本单位</span>' : ' <span class="badge bg-secondary-lt">外单位</span>' ?>
                    </td>
                    <td><?= e($h['hazards_snapshot'] ?: '—') ?></td>
                    <td class="text-secondary"><?= e($h['protection_snapshot'] ?: '—') ?></td>
                    <td class="text-nowrap text-secondary">
                        <a href="<?= url('workers/historyRow', ['id' => (int)$h['id']]) ?>" class="text-body me-2 history-edit" data-id="<?= (int)$h['id'] ?>"><i class="ti ti-pencil me-1"></i>编辑</a>
                        <a href="#" class="text-danger history-del" data-id="<?= (int)$h['id'] ?>" data-worker="<?= (int)$worker['id'] ?>"><i class="ti ti-trash me-1"></i>删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <form class="subform m-3" id="history-form" data-endpoint="<?= url('workers/saveHistory') ?>">
        <input type="hidden" name="worker_id" value="<?= (int)$worker['id'] ?>">
        <input type="hidden" name="row_id" value="">
        <div class="subform-title">添加岗位经历 <button type="button" class="btn btn-sm btn-outline-secondary subform-cancel" hidden><i class="ti ti-x me-1"></i>取消编辑</button></div>
        <div class="form-selectgroup mb-3">
            <label class="form-selectgroup-item">
                <input type="radio" name="mode" value="internal" class="form-selectgroup-input" checked>
                <span class="form-selectgroup-label"><i class="ti ti-building-factory-2 me-1"></i>本单位岗位</span>
            </label>
            <label class="form-selectgroup-item">
                <input type="radio" name="mode" value="external" class="form-selectgroup-input">
                <span class="form-selectgroup-label"><i class="ti ti-world me-1"></i>外单位经历</span>
            </label>
        </div>
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-xl-4" id="f-post">
                <div class="mb-3">
                    <label class="form-label required">本单位岗位</label>
                    <select class="form-select" name="post_id">
                        <option value="">— 请选择 —</option>
                        <?php foreach ($postOptions as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= e($p['workplace_name']) ?> · <?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">选中后将自动带出该岗位的接害因素与防护措施（可在岗位设置中维护）</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4" id="f-company" hidden>
                <div class="mb-3">
                    <label class="form-label required">工作单位</label>
                    <input type="text" class="form-control" name="company_name" placeholder="原单位全称">
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4" id="f-postname" hidden>
                <div class="mb-3">
                    <label class="form-label required">工种</label>
                    <input type="text" class="form-control" name="post_name_manual" placeholder="如：铸造工">
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4" id="f-hazards" hidden>
                <div class="mb-3">
                    <label class="form-label">接触职业病危害因素</label>
                    <input type="text" class="form-control" name="hazards_manual" placeholder="如：矽尘、噪声">
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4" id="f-protection" hidden>
                <div class="mb-3">
                    <label class="form-label">防护措施</label>
                    <input type="text" class="form-control" name="protection_manual" placeholder="如：防尘口罩、隔声耳塞">
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label required">开始日期</label>
                    <input type="date" class="form-control" name="start_date" required>
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label">结束日期</label>
                    <input type="date" class="form-control" name="end_date" placeholder="留空表示至今（当前岗位）">
                </div>
            </div>
            <div class="w-100"></div>
            <div class="col-xl-8">
                <div class="mb-3">
                    <label class="form-label">备注</label>
                    <input type="text" class="form-control" name="remark">
                </div>
            </div>
        </div>
        <div class="form-check mb-3">
            <input type="hidden" name="close_previous" value="0">
            <input class="form-check-input" type="checkbox" name="close_previous" value="1" id="close-previous" checked>
            <label class="form-check-label" for="close-previous">新段结束日期留空时，自动把已有在岗职业史收尾至新段开始日前一天（兼岗请取消勾选）</label>
        </div>
        <div class="subform-msg"></div>
        <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>保存岗位经历</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">既往病史</h3>
    </div>
    <?php if (!$worker['illnesses']): ?>
        <div class="empty empty-sm">
            <div class="empty-icon"><i class="ti ti-heart-rate-monitor"></i></div>
            <p class="empty-title">暂无记录</p>
            <p class="empty-subtitle text-secondary">如有既往病史，在下方表单登记。</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead><tr><th>疾病名称</th><th>诊断时间</th><th>诊断医院</th><th>治疗结果</th><th>备注</th><th class="w-1"></th></tr></thead>
            <tbody>
                <?php foreach ($worker['illnesses'] as $il): ?>
                <tr>
                    <td><?= e($il['disease']) ?></td>
                    <td><?= d($il['diag_date']) ?></td>
                    <td><?= e($il['hospital'] ?: '—') ?></td>
                    <td><?= e($il['outcome'] ?: '—') ?></td>
                    <td class="text-secondary"><?= e($il['remark'] ?: '—') ?></td>
                    <td class="text-secondary"><a href="#" class="text-danger illness-del" data-id="<?= (int)$il['id'] ?>" data-worker="<?= (int)$worker['id'] ?>" title="删除"><i class="ti ti-trash"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <form class="subform m-3" id="illness-form" data-endpoint="<?= url('workers/saveIllness') ?>">
        <input type="hidden" name="worker_id" value="<?= (int)$worker['id'] ?>">
        <input type="hidden" name="row_id" value="">
        <div class="subform-title">添加既往病史</div>
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label required">疾病名称</label>
                    <input type="text" class="form-control" name="disease" required>
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label">诊断时间</label>
                    <input type="date" class="form-control" name="diag_date">
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label">诊断医院</label>
                    <input type="text" class="form-control" name="hospital">
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label">治疗结果</label>
                    <input type="text" class="form-control" name="outcome" placeholder="如：治愈 / 好转 / 未愈">
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label">备注</label>
                    <input type="text" class="form-control" name="remark">
                </div>
            </div>
        </div>
        <div class="subform-msg"></div>
        <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>保存既往病史</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">职业病诊断</h3>
        <div class="card-subtitle">疑似病例请勾选「疑似」；本表同时作为表5-3、表5-4 的数据来源</div>
    </div>
    <?php if (!$worker['diseases']): ?>
        <div class="empty empty-sm">
            <div class="empty-icon"><i class="ti ti-file-text"></i></div>
            <p class="empty-title">暂无记录</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead><tr><th>职业病名称</th><th class="w-1">性质</th><th>诊断时间</th><th>诊断医院</th><th>诊断级别</th><th>处理情况</th><th class="w-1"></th></tr></thead>
            <tbody>
                <?php foreach ($worker['diseases'] as $dz): ?>
                <tr>
                    <td><?= e($dz['name']) ?><?php if (!empty($dz['exam_record_id'])): ?><a class="badge bg-blue-lt ms-1" href="<?= url('exams/recordEdit', ['id' => $dz['exam_record_id']]) ?>" title="来源体检记录">体检</a><?php endif; ?></td>
                    <td><span class="badge <?= $dz['is_suspected'] ? 'bg-yellow-lt' : 'bg-red-lt' ?>"><?= $dz['is_suspected'] ? '疑似' : '确诊' ?></span></td>
                    <td><?= d($dz['diag_date']) ?></td>
                    <td><?= e($dz['hospital'] ?: '—') ?></td>
                    <td><?= e($dz['level'] ?: '—') ?></td>
                    <td class="text-secondary"><?= e($dz['handling'] ?: '—') ?></td>
                    <td class="text-secondary"><a href="#" class="text-danger disease-del" data-id="<?= (int)$dz['id'] ?>" data-worker="<?= (int)$worker['id'] ?>" title="删除"><i class="ti ti-trash"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <form class="subform m-3" id="disease-form" data-endpoint="<?= url('workers/saveDisease') ?>">
        <input type="hidden" name="worker_id" value="<?= (int)$worker['id'] ?>">
        <input type="hidden" name="row_id" value="">
        <div class="subform-title">添加职业病诊断</div>
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label required">职业病名称</label>
                    <input type="text" class="form-control" name="name" required list="disease-options" placeholder="如：噪声聋（右耳）">
                    <div class="form-hint">可从《职业病分类和目录》（2024版，12类135种）中选择，也可按诊断证明填写具体名称</div>
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label">性质</label>
                    <select class="form-select" name="is_suspected">
                        <option value="0">确诊</option>
                        <option value="1">疑似</option>
                    </select>
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label">诊断时间</label>
                    <input type="date" class="form-control" name="diag_date">
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label">诊断医院</label>
                    <input type="text" class="form-control" name="hospital" placeholder="职业病防治院/所">
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label">诊断级别</label>
                    <input type="text" class="form-control" name="level" placeholder="如：轻度 / 壹期">
                </div>
            </div>
            <div class="col-6 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label">处理情况</label>
                    <input type="text" class="form-control" name="handling" placeholder="如：调离接害岗位">
                </div>
            </div>
            <div class="w-100"></div>
            <div class="col-xl-8">
                <div class="mb-3">
                    <label class="form-label">备注</label>
                    <input type="text" class="form-control" name="remark">
                </div>
            </div>
        </div>
        <div class="subform-msg"></div>
        <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>保存职业病诊断</button>
    </form>
</div>
<datalist id="disease-options">
    <?php foreach (\App\Helpers\DiseaseCatalog::all() as $dn): ?>
        <option value="<?= e($dn) ?>"></option>
    <?php endforeach; ?>
</datalist>
<?php endif; ?>
