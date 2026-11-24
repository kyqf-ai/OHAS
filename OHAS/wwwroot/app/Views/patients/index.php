<?php /** 表5-3 / 表5-4 名单：数据取自劳动者信息卡「职业病诊断」 */ ?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2" method="get" action="<?= url('patients/index') ?>">
        <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="patients/index"><?php endif; ?>
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <div class="input-icon">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="姓名 / 档案号 / 病名">
        </div>
        <button class="btn" type="submit"><i class="ti ti-adjustments me-1"></i>筛选</button>
    </form>
    <div class="btn-group">
        <a class="btn <?= $type === 'confirmed' ? 'active' : '' ?>" href="<?= url('patients/index', ['type' => 'confirmed']) ?>">职业病患者（表5-3）</a>
        <a class="btn <?= $type === 'suspected' ? 'active' : '' ?>" href="<?= url('patients/index', ['type' => 'suspected']) ?>">疑似职业病（表5-4）</a>
        <a class="btn" href="<?= url('prints/sheet', ['t' => $type === 'suspected' ? '表5-4' : '表5-3']) ?>" target="_blank" title="一览表整表打印"><i class="ti ti-printer me-1"></i>打印</a>
    </div>
</div>

<?php if (!empty($pending)): ?>
<div class="alert alert-warning">
    <i class="ti ti-alert-triangle alert-icon"></i>
    <div>
        有 <b><?= (int)$pending ?></b> 条体检记录的结论为「<?= $type === 'suspected' ? '疑似职业病' : '职业病' ?>」，但尚未生成<?= $type === 'suspected' ? '表5-4' : '表5-3' ?>登记，名单人数与表5-2 可能不一致。
        <a class="ms-2" href="<?= url('exams/records', ['abnormal' => 1, 'conclusion' => $type === 'suspected' ? '疑似职业病' : '职业病']) ?>">去体检记录处理 →</a>
    </div>
</div>
<?php endif; ?>
<div class="card">
    <?php if (!$rows): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-heart-rate-monitor"></i></div>
            <p class="empty-title"><?= $q !== '' ? '没有符合条件的记录' : '暂无' . ($type === 'suspected' ? '疑似职业病' : '职业病') . '患者' ?></p>
            <p class="empty-subtitle text-secondary">名单由劳动者信息卡「职业病诊断」区块汇总；体检结论为疑似职业病/职业病的，可在体检记录页直接生成登记，也可在信息卡中手工登记。</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr><th>姓名</th><th>档案号</th><th>现岗位</th><th>病名</th><th>诊断时间</th><th>诊断医院</th><th>级别</th><th>处理情况</th><th>来源体检</th><th class="w-1">原件</th><th class="w-1"></th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <a href="<?= url('workers/show', ['id' => $r['worker_id']]) ?>"><?= e($r['worker_name']) ?></a>
                        <?php if ($r['worker_status'] !== '在职'): ?><span class="badge bg-secondary-lt ms-1"><?= e($r['worker_status']) ?></span><?php endif; ?>
                    </td>
                    <td class="font-monospace"><?= e($r['archive_no']) ?></td>
                    <td><?= e($r['current_post'] ?: '—') ?></td>
                    <td><?= e($r['name']) ?></td>
                    <td class="font-monospace"><?= d($r['diag_date']) ?></td>
                    <td><?= e($r['hospital'] ?: '—') ?></td>
                    <td><?= e($r['level'] ?: '—') ?></td>
                    <td class="text-secondary"><?= e($r['handling'] ?: '—') ?></td>
                    <td class="text-nowrap text-secondary">
                        <?php if (!empty($r['exam_record_id'])): ?>
                            <a href="<?= url('exams/recordEdit', ['id' => $r['exam_record_id']]) ?>"><?= d($r['src_exam_date']) ?> <?= e($r['src_exam_type']) ?></a>
                        <?php else: ?>手工登记<?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <?php if ((int)($r['doc_count'] ?? 0) > 0): ?>
                            <span class="badge bg-blue-lt"><?= (int)$r['doc_count'] ?> 份</span>
                        <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
                        <a class="text-body ms-1" href="<?= e(\App\Helpers\DocLink::uploadUrl('disease', (int)$r['id'])) ?>" title="上传诊断申请、诊断证明等原件"><i class="ti ti-upload"></i></a>
                    </td>
                    <td class="text-nowrap"><a class="btn btn-sm" href="<?= url('workers/edit', ['id' => $r['worker_id']]) ?>">在信息卡中维护</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php include OHAS_ROOT . '/app/Views/partials/pagination.php'; ?>
    <?php endif; ?>
</div>
