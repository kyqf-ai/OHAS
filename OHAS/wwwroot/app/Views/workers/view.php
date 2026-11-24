<?php
/**
 * 信息卡查看（表6-1 整卡只读视图）
 * 入口：劳动者信息卡列表「查看」按钮 / 姓名链接。
 * 区块：基本信息 / 职业史及危害接触史 / 既往病史 / 职业病诊断 /
 *       在职体检记录（按年度归集）。
 */
$w = $worker;
$photoUrl = !empty($w['photo']) ? url('media/photo', ['f' => $w['photo']]) : null;
$statusBadge = $w['status'] === '在职' ? 'bg-green-lt' : 'bg-secondary-lt';
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">劳动者个人信息卡（表6-1）</h3>
        <div class="card-subtitle">
            档案号 <span class="font-monospace"><?= e($w['archive_no']) ?></span>
            <span class="badge <?= $statusBadge ?> ms-2"><?= e($w['status']) ?></span>
            <span class="text-secondary ms-2">建档于 <?= e(mb_substr((string)$w['created_at'], 0, 10)) ?></span>
        </div>
        <div class="card-actions">
            <a class="btn btn-sm btn-primary" href="<?= url('workers/edit', ['id' => $w['id']]) ?>"><i class="ti ti-pencil me-1"></i>编辑</a>
            <a class="btn btn-sm" href="<?= url('workers/printCard', ['id' => $w['id']]) ?>" target="_blank"><i class="ti ti-printer me-1"></i>打印表6-1</a>
            <a class="btn btn-sm" href="<?= url('workers/index') ?>"><i class="ti ti-arrow-left me-1"></i>返回列表</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-auto">
                <div class="photo-box">
                    <?php if ($photoUrl): ?>
                        <img src="<?= e($photoUrl) ?>" alt="照片">
                    <?php else: ?>
                        暂无照片
                    <?php endif; ?>
                </div>
            </div>
            <div class="col">
                <div class="datagrid">
                    <div class="datagrid-item"><div class="datagrid-title">姓名</div><div class="datagrid-content"><?= e($w['name']) ?></div></div>
                    <div class="datagrid-item"><div class="datagrid-title">性别</div><div class="datagrid-content"><?= e($w['gender'] ?: '—') ?></div></div>
                    <div class="datagrid-item"><div class="datagrid-title">出生日期</div><div class="datagrid-content"><?= d($w['birth_date']) ?></div></div>
                    <div class="datagrid-item"><div class="datagrid-title">身份证号</div><div class="datagrid-content font-monospace"><?= e($w['id_card'] ?: '—') ?></div></div>
                    <div class="datagrid-item"><div class="datagrid-title">籍贯</div><div class="datagrid-content"><?= e($w['native_place'] ?: '—') ?></div></div>
                    <div class="datagrid-item"><div class="datagrid-title">婚姻状况</div><div class="datagrid-content"><?= e($w['marital_status'] ?: '—') ?></div></div>
                    <div class="datagrid-item"><div class="datagrid-title">文化程度</div><div class="datagrid-content"><?= e($w['education'] ?: '—') ?></div></div>
                    <div class="datagrid-item"><div class="datagrid-title">嗜好</div><div class="datagrid-content"><?= e($w['hobby'] ?: '—') ?></div></div>
                    <div class="datagrid-item"><div class="datagrid-title">参加工作时间</div><div class="datagrid-content"><?= d($w['work_start_date']) ?></div></div>
                    <div class="datagrid-item"><div class="datagrid-title">入职本单位时间</div><div class="datagrid-content"><?= d($w['entry_date']) ?></div></div>
                    <div class="datagrid-item"><div class="datagrid-title">联系电话</div><div class="datagrid-content"><?= e($w['phone'] ?: '—') ?></div></div>
                    <?php if (trim((string)($w['duty_role'] ?? '')) !== ''): ?>
                    <div class="datagrid-item"><div class="datagrid-title">职务角色</div><div class="datagrid-content"><?php foreach (array_filter(array_map('trim', explode(',', (string)$w['duty_role']))) as $r): ?><span class="badge bg-azure-lt me-1"><?= e($r) ?></span><?php endforeach; ?></div></div>
                    <?php endif; ?>
                    <div class="datagrid-item"><div class="datagrid-title">备注</div><div class="datagrid-content"><?= e($w['remark'] ?: '—') ?></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">职业史及职业病危害接触史</h3>
    </div>
    <?php if (!$w['history']): ?>
        <div class="empty empty-sm">
            <div class="empty-icon"><i class="ti ti-calendar"></i></div>
            <p class="empty-title">暂无记录</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead><tr><th class="w-auto">起止时间</th><th>工作单位</th><th>工种/岗位</th><th>接触职业病危害因素</th><th>防护措施</th></tr></thead>
            <tbody>
                <?php foreach ($w['history'] as $h): ?>
                <tr>
                    <td class="font-monospace small text-nowrap"><?= e($h['start_date']) ?> ~ <?= $h['end_date'] ? e($h['end_date']) : '<span class="badge bg-green-lt">至今</span>' ?></td>
                    <td><?= e($h['company_name']) ?></td>
                    <td>
                        <?= e($h['post_name'] ?: '—') ?>
                        <?= $h['post_id'] ? ' <span class="badge bg-blue-lt">本单位</span>' : ' <span class="badge bg-secondary-lt">外单位</span>' ?>
                    </td>
                    <td><?= e($h['hazards_snapshot'] ?: '—') ?></td>
                    <td class="text-secondary"><?= e($h['protection_snapshot'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">既往病史</h3>
    </div>
    <?php if (!$w['illnesses']): ?>
        <div class="empty empty-sm">
            <div class="empty-icon"><i class="ti ti-heart-rate-monitor"></i></div>
            <p class="empty-title">暂无记录</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead><tr><th>疾病名称</th><th>诊断时间</th><th>诊断医院</th><th>治疗结果</th><th>备注</th></tr></thead>
            <tbody>
                <?php foreach ($w['illnesses'] as $il): ?>
                <tr>
                    <td><?= e($il['disease']) ?></td>
                    <td><?= d($il['diag_date']) ?></td>
                    <td><?= e($il['hospital'] ?: '—') ?></td>
                    <td><?= e($il['outcome'] ?: '—') ?></td>
                    <td class="text-secondary"><?= e($il['remark'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">职业病诊断</h3>
    </div>
    <?php if (!$w['diseases']): ?>
        <div class="empty empty-sm">
            <div class="empty-icon"><i class="ti ti-file-text"></i></div>
            <p class="empty-title">暂无记录</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead><tr><th>职业病名称</th><th class="w-1">性质</th><th>诊断时间</th><th>诊断医院</th><th>诊断级别</th><th>处理情况</th></tr></thead>
            <tbody>
                <?php foreach ($w['diseases'] as $dz): ?>
                <tr>
                    <td><?= e($dz['name']) ?><?php if (!empty($dz['exam_record_id'])): ?><a class="badge bg-blue-lt ms-1" href="<?= url('exams/recordEdit', ['id' => $dz['exam_record_id']]) ?>" title="来源体检记录">体检</a><?php endif; ?></td>
                    <td><span class="badge <?= $dz['is_suspected'] ? 'bg-yellow-lt' : 'bg-red-lt' ?>"><?= $dz['is_suspected'] ? '疑似' : '确诊' ?></span></td>
                    <td><?= d($dz['diag_date']) ?></td>
                    <td><?= e($dz['hospital'] ?: '—') ?></td>
                    <td><?= e($dz['level'] ?: '—') ?></td>
                    <td class="text-secondary"><?= e($dz['handling'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">工作场所危害因素检测结果（表6-2）<span class="badge bg-blue-lt ms-2"><?= count($w['detections']) ?> 条</span></h3>
        <div class="card-subtitle">按本单位职业史（岗位 × 接害期间）自动归集，判定口径 GBZ 2.1-2019 / GBZ 2.2-2007</div>
        <div class="card-actions">
            <a class="btn btn-sm" href="<?= url('detections/items') ?>"><i class="ti ti-list-search me-1"></i>结果查询</a>
            <a class="btn btn-sm btn-primary" href="<?= url('detections/printItems', ['worker_id' => $w['id']]) ?>" target="_blank"><i class="ti ti-printer me-1"></i>打印表6-2</a>
        </div>
    </div>
    <?php if (!$w['detections']): ?>
        <div class="card-body">
            <div class="empty empty-sm">
                <div class="empty-icon"><i class="ti ti-flask"></i></div>
                <p class="empty-title">暂无归集到的检测结果</p>
                <p class="empty-subtitle text-secondary">在「四、监测检测档案 → 表4-3 检测报告与明细」登记检测报告并将明细关联到该劳动者的岗位后，结果将按检测日期自动归集到本卡。</p>
            </div>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <th>检测时间</th><th>危害因素</th><th>岗位</th><th>检测点</th>
                    <th>检测结果</th><th>接触限值</th><th class="w-1">判定</th><th>来源报告</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($w['detections'] as $it): [$vl, $vc] = \App\Models\DetectionItem::verdictBadge($it['verdict']); ?>
                <tr>
                    <td class="font-monospace"><?= d($it['test_date']) ?></td>
                    <td><?= e($it['hazard_name'] ?: '—') ?></td>
                    <td>
                        <?= e($it['post_name'] ?: $it['workplace_name'] ?: '—') ?>
                        <span class="badge bg-secondary-lt ms-1" title="归集依据：岗位直接匹配 / 场所+危害因素匹配"><?= e($it['match_basis']) ?></span>
                    </td>
                    <td class="text-secondary"><?= e($it['test_point'] ?: '—') ?></td>
                    <td class="font-monospace"><?= e($it['result'] ?: '—') ?><?= $it['unit'] !== '' ? ' <span class="text-secondary">' . e($it['unit']) . '</span>' : '' ?></td>
                    <td class="text-secondary small"><?= e($it['limit_value'] ?: '—') ?></td>
                    <td><span class="badge <?= $vc ?>"><?= e($vl) ?></span></td>
                    <td><a class="font-monospace text-body" href="<?= url('detections/show', ['id' => $it['report_id']]) ?>"><?= e($it['report_no']) ?></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">在职体检记录（按年度关联）<span class="badge bg-blue-lt ms-2"><?= (int)$w['exams_total'] ?> 条</span></h3>
        <div class="card-subtitle">历次职业健康检查按年归集到本卡，检查结论口径依据 GBZ 188-2025</div>
        <div class="card-actions">
            <a class="btn btn-sm btn-primary" href="<?= url('exams/recordCreate', ['worker_id' => $w['id']]) ?>"><i class="ti ti-plus me-1"></i>登记体检</a>
            <a class="btn btn-sm" href="<?= url('exams/printRecords', ['worker_id' => $w['id']]) ?>" target="_blank"><i class="ti ti-printer me-1"></i>打印表6-3</a>
        </div>
    </div>
    <?php if (!$w['exams']): ?>
        <div class="card-body">
            <div class="empty empty-sm">
                <div class="empty-icon"><i class="ti ti-heart-rate-monitor"></i></div>
                <p class="empty-title">暂无体检记录</p>
                <p class="empty-subtitle text-secondary">可到「健康监护 → 在职体检批次」按年度批次登记，或点击右上角「登记体检」为该劳动者单独登记。</p>
            </div>
        </div>
    <?php else: ?>
    <div class="card-body">
        <?php foreach ($w['exams'] as $year => $records): ?>
        <div class="mb-4">
            <div class="subheader mb-2"><i class="ti ti-calendar-event me-1"></i><?= e($year) ?> 年</div>
            <div class="timeline">
                <?php foreach ($records as $r):
                    [$badgeLabel, $badgeClass] = \App\Models\ExamRecord::conclusionBadge($r['conclusion']); ?>
                <div class="timeline-event">
                    <div class="timeline-badge"></div>
                    <div class="timeline-card">
                        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                            <div>
                                <span class="font-monospace me-2"><?= d($r['exam_date']) ?></span>
                                <span class="badge bg-blue-lt"><?= e($r['exam_type']) ?></span>
                                <span class="badge <?= $badgeClass ?> ms-1"><?= e($badgeLabel) ?></span>
                                <?php if ((int)$r['doc_count'] > 0): ?>
                                    <a class="ms-1 small" href="<?= url('documents/download', ['id' => $r['doc_id'], 'inline' => 1]) ?>" target="_blank"><i class="ti ti-file-upload me-1"></i>报告原件<?= (int)$r['doc_count'] > 1 ? '（' . (int)$r['doc_count'] . '）' : '' ?></a>
                                <?php endif; ?>
                            </div>
                            <div class="text-secondary small text-nowrap">
                                <?= e($r['agency'] ?: '') ?>
                                <a class="ms-2" href="<?= url('exams/recordEdit', ['id' => $r['id']]) ?>"><i class="ti ti-pencil"></i></a>
                            </div>
                        </div>
                        <?php if ($r['post_name'] !== '' || $r['hazards_snapshot'] !== ''): ?>
                        <div class="text-secondary small mt-1">
                            <?php if ($r['post_name'] !== ''): ?>受检岗位：<?= e($r['post_name']) ?><?php endif; ?>
                            <?php if ($r['hazards_snapshot'] !== ''): ?> · 接触危害：<?= e($r['hazards_snapshot']) ?><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($r['handling'] !== '' || $r['site_handling'] !== '' || $r['remark'] !== ''): ?>
                        <div class="small mt-1">
                            <?php if ($r['handling'] !== ''): ?><div>人员处理：<?= e($r['handling']) ?></div><?php endif; ?>
                            <?php if ($r['site_handling'] !== ''): ?><div class="text-secondary">现场处理：<?= e($r['site_handling']) ?></div><?php endif; ?>
                            <?php if ($r['remark'] !== ''): ?><div class="text-secondary">备注：<?= e($r['remark']) ?></div><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
