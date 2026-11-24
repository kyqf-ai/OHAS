<?php
/** 检查记录列表：按批次视图（含批次摘要与操作）或全局检索（表6-3 记录） */
$inBatch = $batch && !empty($batch['id']);
?>
<?php if ($inBatch): ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="row align-items-center g-3">
            <div class="col-auto">
                <span class="avatar avatar-md bg-blue-lt"><i class="ti ti-heart-rate-monitor fs-2"></i></span>
            </div>
            <div class="col">
                <div class="h3 mb-1"><?= d($batch['exam_date']) ?> · <?= e($batch['exam_type']) ?>体检</div>
                <div class="text-secondary">
                    检查机构：<?= e($batch['agency']) ?>
                    <?php if ((string)$batch['remark'] !== ''): ?> · <?= e($batch['remark']) ?><?php endif; ?>
                </div>
            </div>
            <div class="col-auto">
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-primary" href="<?= url('exams/pick', ['batch_id' => $batch['id']]) ?>"><i class="ti ti-user-plus me-1"></i>生成应检名单</a>
                    <a class="btn" href="<?= url('exams/recordCreate', ['batch_id' => $batch['id']]) ?>"><i class="ti ti-plus me-1"></i>单人补登</a>
                    <a class="btn" href="<?= url('exams/edit', ['id' => $batch['id']]) ?>"><i class="ti ti-pencil me-1"></i>编辑批次</a>
                </div>
            </div>
        </div>
        <div class="hr-text my-2">批次统计</div>
        <div class="row g-2 text-center justify-content-center">
            <div class="col-auto">
                <div class="subheader">应检</div>
                <div class="h2 mb-0"><?= (int)$batch['planned_count'] ?></div>
            </div>
            <div class="col-auto">
                <div class="subheader">已登记</div>
                <div class="h2 mb-0"><?= (int)$batch['registered_count'] ?></div>
            </div>
            <div class="col-auto">
                <div class="subheader">实检（已出结论）</div>
                <div class="h2 mb-0"><?= (int)$batch['actual_count'] ?></div>
            </div>
            <div class="col-auto">
                <div class="subheader">待出结论</div>
                <div class="h2 mb-0"><?= (int)$batch['pending_count'] ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($inBatch): $docs = $batchDocs; $linkType = 'exam_batch'; $linkId = (int)$batch['id']; $docHeading = '批次评价 / 总结报告原件'; include OHAS_ROOT . '/app/Views/partials/doc_list.php'; endif; ?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2" method="get" action="<?= url('exams/records') ?>">
        <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="exams/records"><?php endif; ?>
        <?php if (!empty($abnormal)): ?><input type="hidden" name="abnormal" value="1"><?php endif; ?>
        <?php if ($disposition !== ''): ?><input type="hidden" name="disposition" value="<?= e($disposition) ?>"><?php endif; ?>
        <?php if ($inBatch): ?><input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>"><?php endif; ?>
        <div class="input-icon">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="姓名 / 档案号 / 身份证号">
        </div>
        <select class="form-select w-auto" name="conclusion">
            <option value="">全部结论</option>
            <?php foreach ($conclusions as $cOpt): ?>
                <option value="<?= e($cOpt) ?>" <?= $cOpt === $conclusion ? 'selected' : '' ?>><?= e($cOpt) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit"><i class="ti ti-adjustments me-1"></i>筛选</button>
    </form>
    <div class="d-flex gap-2">
        <?php if (!$inBatch): ?>
            <?php if (!empty($abnormal)): ?>
                <a class="btn" href="<?= url('prints/sheet', ['t' => '表5-2']) ?>" target="_blank" title="职业健康检查异常结果登记表（表5-2）打印"><i class="ti ti-printer me-1"></i>打印登记表</a>
            <?php endif; ?>
            <a class="btn btn-primary" href="<?= url('exams/recordCreate') ?>"><i class="ti ti-plus me-1"></i>登记检查记录</a>
        <?php endif; ?>
        <?php if (!$inBatch): ?>
            <?php if (!empty($abnormal)): ?>
                <a class="btn" href="<?= url('exams/records') ?>"><i class="ti ti-list me-1"></i>查看全部记录（表6-3）</a>
            <?php else: ?>
                <a class="btn" href="<?= url('exams/records', ['abnormal' => 1]) ?>"><i class="ti ti-alert-triangle me-1"></i>仅看异常（表5-2）</a>
            <?php endif; ?>
        <?php endif; ?>
        <a class="btn" href="<?= url('exams/index') ?>"><i class="ti ti-arrow-left me-1"></i>批次列表</a>
    </div>
</div>

<?php if (!empty($abnormal) && !$inBatch && is_array($dispositionStats)): ?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <a class="btn btn-sm <?= $disposition === '' ? 'btn-primary' : '' ?>" href="<?= url('exams/records', ['abnormal' => 1]) ?>">全部 <?= (int)$dispositionStats['total'] ?></a>
    <a class="btn btn-sm <?= $disposition === 'register' ? 'btn-primary' : '' ?>" href="<?= url('exams/records', ['abnormal' => 1, 'disposition' => 'register']) ?>">待登记 <?= (int)$dispositionStats['register'] ?></a>
    <a class="btn btn-sm <?= $disposition === 'pending' ? 'btn-primary' : '' ?>" href="<?= url('exams/records', ['abnormal' => 1, 'disposition' => 'pending']) ?>">待处置 <?= (int)$dispositionStats['pending'] ?></a>
    <a class="btn btn-sm <?= $disposition === 'notice' ? 'btn-primary' : '' ?>" href="<?= url('exams/records', ['abnormal' => 1, 'disposition' => 'notice']) ?>">待告知 <?= (int)$dispositionStats['notice'] ?></a>
    <a class="btn btn-sm <?= $disposition === 'nonwritten' ? 'btn-primary' : '' ?>" href="<?= url('exams/records', ['abnormal' => 1, 'disposition' => 'nonwritten']) ?>">非书面告知 <?= (int)$dispositionStats['nonwritten'] ?></a>
    <a class="btn btn-sm <?= $disposition === 'notice_doc' ? 'btn-primary' : '' ?>" href="<?= url('exams/records', ['abnormal' => 1, 'disposition' => 'notice_doc']) ?>">待告知原件 <?= (int)$dispositionStats['notice_doc'] ?></a>
    <a class="btn btn-sm <?= $disposition === 'transfer' ? 'btn-primary' : '' ?>" href="<?= url('exams/records', ['abnormal' => 1, 'disposition' => 'transfer']) ?>">待调离 <?= (int)$dispositionStats['transfer'] ?></a>
    <a class="btn btn-sm <?= $disposition === 'closed' ? 'btn-primary' : '' ?>" href="<?= url('exams/records', ['abnormal' => 1, 'disposition' => 'closed']) ?>">已闭环 <?= (int)$dispositionStats['closed'] ?></a>
    <span class="text-secondary small align-self-center">闭环前置：职业病登记（如适用）→ 处理情况 → 书面签收并挂告知书原件 → 本人签字 → 禁忌证完成调离</span>
</div>
<?php endif; ?>

<div class="card">
    <?php if (!$data['rows']): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-file-text"></i></div>
            <p class="empty-title">暂无检查记录</p>
            <p class="empty-subtitle text-secondary"><?= $inBatch ? '可点击右上角「生成应检名单」批量建立待检记录，或「单人补登」。' : '可从批次进入登记，或直接登记单条检查记录。' ?></p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <th>检查日期</th><th>姓名</th><th>档案号</th><th>检查种类</th>
                    <th>检查结论</th><?php if (!empty($abnormal)): ?><th>处置状态</th><?php endif; ?>
                    <th>检查机构</th><th>受检岗位</th><th>报告</th><th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['rows'] as $r):
                    [$badgeLabel, $badgeClass] = \App\Models\ExamRecord::conclusionBadge($r['conclusion']); ?>
                <tr>
                    <td class="font-monospace"><?= d($r['exam_date']) ?></td>
                    <td>
                        <a href="<?= url('workers/show', ['id' => $r['worker_id']]) ?>"><?= e($r['worker_name']) ?></a>
                        <?php if ($r['worker_status'] !== '在职'): ?><span class="badge bg-secondary-lt ms-1"><?= e($r['worker_status']) ?></span><?php endif; ?>
                    </td>
                    <td class="font-monospace"><?= e($r['worker_archive_no']) ?></td>
                    <td><span class="badge bg-blue-lt"><?= e($r['exam_type']) ?></span></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= e($badgeLabel) ?></span></td>
                    <?php if (!empty($abnormal)):
                        $disp = \App\Models\ExamRecord::dispositionStatus($r);
                        $handling = trim((string)($r['handling'] ?? '')); ?>
                    <td>
                        <?php if ($disp): ?>
                            <span class="badge <?= $disp['class'] ?>"><?= e($disp['label']) ?></span>
                            <?php if ($handling !== ''): ?>
                                <div class="small text-secondary mt-1" title="<?= e($handling) ?>"><?= e(mb_substr($handling, 0, 12)) ?><?= mb_strlen($handling) > 12 ? '…' : '' ?></div>
                            <?php endif; ?>
                        <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="text-nowrap"><?= e($r['agency'] ?: '—') ?></td>
                    <td><?= e($r['post_name'] ?: '—') ?></td>
                    <td>
                        <?php if ((int)$r['doc_count'] > 0): ?>
                            <a href="<?= url('documents/download', ['id' => $r['doc_id'], 'inline' => 1]) ?>" target="_blank"><i class="ti ti-file-upload me-1"></i>查看<?= (int)$r['doc_count'] > 1 ? '（' . (int)$r['doc_count'] . '）' : '' ?></a>
                        <?php else: ?>
                            <span class="text-secondary">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap text-secondary">
                        <?php $dk = \App\Models\ExamRecord::diseaseKind($r['conclusion']); ?>
                        <?php if ($dk !== null): ?>
                            <?php if (!empty($r['disease_id'])): ?>
                                <a class="text-success me-2" href="<?= url('patients/index', ['type' => $dk === 1 ? 'suspected' : 'confirmed']) ?>" title="已登记到<?= $dk === 1 ? '表5-4' : '表5-3' ?>"><i class="ti ti-circle-check me-1"></i>已登记<?= $dk === 1 ? '5-4' : '5-3' ?></a>
                            <?php else: ?>
                                <a class="text-danger me-2" href="<?= url('exams/diseaseRegister', ['id' => $r['id']]) ?>"><i class="ti ti-file-plus me-1"></i>生成<?= $dk === 1 ? '疑似职业病' : '职业病' ?>登记</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a class="text-body me-2" href="<?= url('exams/recordEdit', ['id' => $r['id']]) ?>"><i class="ti ti-pencil me-1"></i>编辑</a>
                        <a class="text-body me-2" href="<?= url('exams/printRecords', ['worker_id' => $r['worker_id']]) ?>" target="_blank" title="打印该劳动者表6-3"><i class="ti ti-printer me-1"></i>表6-3</a>
                        <a class="text-danger" href="#" data-record-del="<?= $r['id'] ?>" data-name="<?= e($r['worker_name'] . ' ' . $r['exam_date']) ?>"><i class="ti ti-trash me-1"></i>删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php include OHAS_ROOT . '/app/Views/partials/pagination.php'; ?>
</div>

<form id="record-del-form" method="post" action="<?= url('exams/recordDelete') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="">
</form>
