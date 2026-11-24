<?php
$labels = [
    '上岗前培训' => '上岗前培训', '在岗期间培训' => '在岗期间培训', '离岗培训' => '离岗培训',
    '职业卫生管理人员培训' => '职业卫生管理人员培训', '主要负责人培训' => '主要负责人培训', '其他' => '其他',
];
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
        <a href="<?= url('ledgers/index', ['key' => 'trainings']) ?>" class="text-secondary"><i class="ti ti-arrow-left me-1"></i>返回培训记录</a>
        <div class="text-secondary mt-1">表3-1 附页 · 参训人员签到表</div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="<?= url('prints/sheet', ['t' => '表3-1', 'id' => (int)$training['id']]) ?>" target="_blank" title="打印本期培训记录表（含签到表）"><i class="ti ti-printer me-1"></i>打印本期记录表</a>
        <a class="btn" href="<?= url('ledgers/edit', ['key' => 'trainings', 'id' => (int)$training['id']]) ?>"><i class="ti ti-pencil me-1"></i>编辑培训信息</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-secondary small">培训类型</div>
                <div class="h3 m-0"><?= e($training['type']) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">培训内容</div>
                <div class="h3 m-0"><?= e($training['title'] ?: '—') ?></div>
            </div>
            <div class="col-md-2">
                <div class="text-secondary small">实施日期</div>
                <div class="h3 m-0"><?= e(d($training['trained_on'])) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-secondary small">授课人 / 学时</div>
                <div class="h3 m-0"><?= e($training['teacher'] ?: '—') ?> / <?= e((string)$training['hours']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row row-cards">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">添加参训人员</h3></div>
            <form method="post" action="<?= url('ledgers/rosterAdd') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="training_id" value="<?= (int)$training['id'] ?>">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required">劳动者</label>
                        <select class="form-select" name="worker_id" required>
                            <option value="">— 请选择 —</option>
                            <?php foreach ($workers as $w): ?>
                                <option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?><?= $w['archive_no'] ? '（' . e($w['archive_no']) . '）' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">成绩</label>
                        <input type="text" class="form-control" name="score" placeholder="如：合格 / 95">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">是否签到</label>
                        <select class="form-select" name="attended">
                            <option value="是">是</option>
                            <option value="否">否</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">备注</label>
                        <input type="text" class="form-control" name="remark">
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button class="btn btn-primary" type="submit"><i class="ti ti-plus me-1"></i>加入签到表</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">签到人员（<?= count($rows) ?> 人）</h3>
            </div>
            <?php if (!$rows): ?>
                <div class="empty">
                    <div class="empty-icon"><i class="ti ti-users"></i></div>
                    <p class="empty-title">暂无签到人员</p>
                    <p class="empty-subtitle text-secondary">从左侧选择劳动者加入签到表。</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>姓名</th><th>档案号</th><th>性别</th><th>成绩</th><th>签到</th><th>备注</th><th class="text-end">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= e($r['worker_name']) ?></td>
                            <td class="text-secondary"><?= e($r['archive_no'] ?: '—') ?></td>
                            <td><?= e($r['gender'] ?: '—') ?></td>
                            <td><?= e($r['score'] ?: '—') ?></td>
                            <td>
                                <span class="badge <?= $r['attended'] === '是' ? 'bg-green-lt' : 'bg-yellow-lt' ?>"><?= e($r['attended']) ?></span>
                            </td>
                            <td class="text-secondary"><?= e($r['remark'] ?: '—') ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= url('ledgers/rosterDelete') ?>" class="d-inline"
                                      onsubmit="return confirm('确定从签到表移除该人员？');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="training_id" value="<?= (int)$training['id'] ?>">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <button class="btn btn-sm btn-link text-danger p-0" type="submit"><i class="ti ti-trash me-1"></i>移除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
