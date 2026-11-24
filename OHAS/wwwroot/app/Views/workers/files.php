<?php
/**
 * 个人资料全景：输入一个劳动者，一页看到此人历年全部资料与缺口。
 * 头部 / 一次性资料 / 年度矩阵 / 事件类 / 其他资料 / 快速上传模态框 / 标不适用。
 */
use App\Helpers\WorkerArchive;
use App\Helpers\DocTypes;

$h = $a['header'];
$wid = (int)$a['worker']['id'];
$badge = fn (string $state) => WorkerArchive::badge($state);
$rangeUrl = fn (string $r) => url('workers/files', ['id' => $wid, 'range' => $r]);
$sumTxt = '';
foreach (['done' => '已归档', 'done_indirect' => '间接', 'reg_only' => '仅登记', 'todo' => '待归档'] as $st => $lbl) {
    if (($a['summary'][$st] ?? 0) > 0) {
        $sumTxt .= '<span class="badge ' . $badge($st)[1] . ' me-1">' . $lbl . ' ' . $a['summary'][$st] . '</span>';
    }
}
?>
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">
            <?= e($h['name']) ?>
            <span class="font-monospace text-secondary ms-2"><?= e($h['archive_no']) ?></span>
            <span class="badge <?= $h['status'] === '在职' ? 'bg-green-lt' : 'bg-secondary-lt' ?> ms-2"><?= e($h['status']) ?></span>
            <?php if ($h['exposed_now']): ?><span class="badge bg-red-lt ms-1">接害</span><?php else: ?><span class="badge bg-secondary-lt ms-1">非接害</span><?php endif; ?>
            <?php foreach ($h['duty_role'] as $role): ?><span class="badge bg-azure-lt ms-1"><?= e($role) ?></span><?php endforeach; ?>
        </h3>
        <div class="card-actions">
            <form method="post" action="<?= url('workers/exportFiles') ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $wid ?>">
                <button class="btn btn-sm" type="submit"><i class="ti ti-file-zip me-1"></i>导出资料包</button>
            </form>
            <a class="btn btn-sm" href="<?= url('prints/workerFiles', ['id' => $wid]) ?>" target="_blank"><i class="ti ti-printer me-1"></i>打印资料目录</a>
            <a class="btn btn-sm" href="<?= url('workers/show', ['id' => $wid]) ?>"><i class="ti ti-id me-1"></i>信息卡</a>
        </div>
    </div>
    <div class="card-body py-2">
        <div class="datagrid">
            <div class="datagrid-item"><div class="datagrid-title">身份证号</div><div class="datagrid-content font-monospace"><?= e($h['id_card'] ?: '—') ?></div></div>
            <div class="datagrid-item"><div class="datagrid-title">当前岗位</div><div class="datagrid-content"><?= e($h['current_post'] ?: '—') ?></div></div>
            <div class="datagrid-item"><div class="datagrid-title">入职时间</div><div class="datagrid-content"><?= d($h['entry_date']) ?></div></div>
            <div class="datagrid-item"><div class="datagrid-title">年度格子计数</div><div class="datagrid-content"><?= $sumTxt ?: '<span class="text-secondary">—</span>' ?></div></div>
        </div>
    </div>
</div>

<?php /* ==================== 一次性资料 ==================== */ ?>
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-pin me-1"></i>一次性 / 长期资料</h3>
    </div>
    <div class="list-group list-group-flush">
        <?php foreach ($a['once'] as $item): ?>
        <div class="list-group-item">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="fw-bold"><?= e($item['name']) ?></span>
                <?php [$lbl, $cls] = $badge($item['state']); ?>
                <?php if ($item['state'] !== 'none'): ?><span class="badge <?= $cls ?>"><?= e($lbl) ?></span><?php endif; ?>
                <?php if ($item['state'] === 'todo'): ?>
                    <button class="btn btn-sm btn-primary py-0" data-bs-toggle="modal" data-bs-target="#quick-upload-modal" data-quick-upload data-doc-type="<?= e($item['type']) ?>" data-link-type="worker" data-link-id="<?= $wid ?>" data-year="" data-label="<?= e($item['name']) ?>"><i class="ti ti-upload me-1"></i>上传</button>
                <?php endif; ?>
                <?php if ($item['hint'] !== ''): ?><span class="text-warning small"><i class="ti ti-alert-triangle me-1"></i><?= e($item['hint']) ?></span><?php endif; ?>
            </div>
            <?php if ($item['docs']): ?>
            <div class="row g-1">
                <?php foreach ($item['docs'] as $dc): ?>
                <div class="col-auto doc-cell">
                    <a class="text-body small" href="<?= url('documents/download', ['id' => $dc['id'], 'inline' => 1]) ?>" target="_blank"><i class="ti ti-file-text me-1"></i><span class="doc-title-text" data-doc-id="<?= (int)$dc['id'] ?>"><?= e($dc['title']) ?></span></a>
                    <span class="text-secondary small"><?= e($dc['doc_date'] ?: '') ?></span>
                    <?php if (!empty($dc['file_missing'])): ?><span class="badge bg-red-lt">文件丢失</span><?php endif; ?>
                    <a class="text-secondary" href="#" data-rename="<?= (int)$dc['id'] ?>" data-title="<?= e($dc['title']) ?>"><i class="ti ti-pencil"></i></a>
                    <a class="text-secondary" href="<?= url('documents/download', ['id' => $dc['id']]) ?>"><i class="ti ti-download"></i></a>
                    <a class="text-danger" href="#" data-del="<?= (int)$dc['id'] ?>" data-name="<?= e($dc['title']) ?>" data-docs="0" data-del-doc="1"><i class="ti ti-trash"></i></a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php /* ==================== 年度矩阵 ==================== */ ?>
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-calendar-stats me-1"></i>年度资料矩阵</h3>
        <div class="card-actions btn-group btn-group-sm">
            <a class="btn <?= $range === '3y' ? 'btn-primary' : '' ?>" href="<?= e($rangeUrl('3y')) ?>">近 3 年</a>
            <a class="btn <?= $range === '5y' ? 'btn-primary' : '' ?>" href="<?= e($rangeUrl('5y')) ?>">近 5 年</a>
            <a class="btn <?= $range === 'all' ? 'btn-primary' : '' ?>" href="<?= e($rangeUrl('all')) ?>">全部</a>
        </div>
    </div>
    <?php if (!$a['years']): ?>
    <div class="card-body text-secondary">暂无年度区间：请先补充入职时间或职业史。</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-sm">
            <thead>
                <tr>
                    <th style="min-width:150px;">年度 / 当年岗位</th>
                    <?php foreach ($columns as $type => $meta): ?><th class="text-center"><?= e($meta['name']) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($a['years'] as $row): $y = $row['year']; ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= $y ?></div>
                        <div class="text-secondary small"><?= e($row['posts'] ?: '—') ?></div>
                        <?php if ($row['hazards'] !== ''): ?><div class="text-secondary small">接触：<?= e($row['hazards']) ?></div><?php endif; ?>
                    </td>
                    <?php foreach ($columns as $type => $meta): $cell = $row['cells'][$type]; [$lbl, $cls] = $badge($cell['state']); ?>
                    <td class="text-center text-nowrap">
                        <?php if ($cell['state'] === 'none'): ?>
                            <span class="text-secondary">—</span>
                        <?php else: ?>
                            <a href="#cell-<?= $y ?>-<?= e($type) ?>" data-cell-toggle="cell-<?= $y ?>-<?= e($type) ?>" class="badge <?= $cls ?> text-decoration-none">
                                <?= e($lbl) ?><?= $cell['count'] > 0 ? ' ' . $cell['count'] : '' ?>
                            </a>
                            <?php if ($type !== 'X_DETECT' && in_array($cell['state'], ['todo', 'reg_only'], true)): ?>
                                <button class="btn btn-sm py-0 px-1" title="上传" data-bs-toggle="modal" data-bs-target="#quick-upload-modal" data-quick-upload data-doc-type="<?= e($type) ?>"
                                        data-link-type="worker" data-link-id="<?= $wid ?>" data-year="<?= $y ?>" data-label="<?= e($meta['name']) ?>"><i class="ti ti-upload"></i></button>
                            <?php elseif ($type === 'X_DETECT' && $cell['state'] === 'reg_only' && !empty($cell['reports'])): ?>
                                <a class="btn btn-sm py-0 px-1" title="去检测报告上传原件" href="<?= url('detections/show', ['id' => (int)$cell['reports'][0]['id']]) ?>"><i class="ti ti-external-link"></i></a>
                            <?php endif; ?>
                            <?php if ($cell['state'] !== 'na'): ?>
                                <button class="btn btn-sm py-0 px-1 text-secondary" title="标不适用" data-bs-toggle="modal" data-bs-target="#na-modal" data-mark-na data-scope="worker" data-item-code="<?= e($type) ?>" data-worker-id="<?= $wid ?>" data-year="<?= $y ?>" data-label="<?= $y ?> 年·<?= e($meta['name']) ?>"><i class="ti ti-ban"></i></button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php foreach ($columns as $type => $meta): $cell = $row['cells'][$type]; if ($cell['state'] === 'none') continue; ?>
                <tr class="cell-detail d-none" id="cell-<?= $y ?>-<?= e($type) ?>">
                    <td colspan="<?= count($columns) + 1 ?>" class="bg-secondary-lt py-2">
                        <div class="fw-bold mb-1"><?= $y ?> 年 · <?= e($meta['name']) ?>
                            <?php if ($cell['hint'] !== ''): ?><span class="text-secondary small ms-2"><?= e($cell['hint']) ?></span><?php endif; ?>
                        </div>
                        <?php foreach (($cell['docs'] ?? []) as $dc): ?>
                        <div class="doc-cell ms-3 mb-1">
                            <a class="text-body" href="<?= url('documents/download', ['id' => $dc['id'], 'inline' => 1]) ?>" target="_blank"><i class="ti ti-file-text me-1"></i><span class="doc-title-text" data-doc-id="<?= (int)$dc['id'] ?>"><?= e($dc['title']) ?></span></a>
                            <span class="text-secondary small"><?= e($dc['doc_date'] ?: '') ?></span>
                            <?php if (($dc['link_type'] ?? '') === 'exam_record'): ?><span class="badge bg-blue-lt">挂体检记录</span><?php endif; ?>
                            <?php if (!empty($dc['file_missing'])): ?><span class="badge bg-red-lt">文件丢失</span><?php endif; ?>
                            <a class="text-secondary" href="#" data-rename="<?= (int)$dc['id'] ?>" data-title="<?= e($dc['title']) ?>"><i class="ti ti-pencil"></i></a>
                            <a class="text-secondary" href="<?= url('documents/download', ['id' => $dc['id']]) ?>"><i class="ti ti-download"></i></a>
                            <a class="text-danger" href="#" data-del="<?= (int)$dc['id'] ?>" data-name="<?= e($dc['title']) ?>" data-docs="0" data-del-doc="1"><i class="ti ti-trash"></i></a>
                        </div>
                        <?php endforeach; ?>
                        <?php foreach (($cell['reports'] ?? []) as $r): ?>
                        <div class="ms-3 mb-1">
                            <i class="ti ti-flask me-1 text-secondary"></i>
                            <a class="text-body" href="<?= url('detections/show', ['id' => (int)$r['id']]) ?>"><?= e($r['report_no']) ?></a>
                            <span class="text-secondary small"><?= e($r['test_date']) ?> · <?= e($r['category']) ?><?= $r['has_doc'] ? '' : ' · <span class="badge bg-yellow-lt">无原件</span>' ?></span>
                            <span class="text-secondary small">（<?= e($r['post_name'] ?: '按因素') ?><?= $r['hazard_name'] ? ' · ' . e($r['hazard_name']) : '' ?>）</span>
                        </div>
                        <?php endforeach; ?>
                        <?php foreach (($cell['ref'] ?? []) as $r): ?>
                        <div class="ms-3 mb-1 text-secondary small"><i class="ti ti-file-text me-1"></i>间接参考：<?= e($r['exam_date']) ?> <?= e($r['exam_type']) ?> 批次报告「<?= e($r['title']) ?>」（不计入已归档）</div>
                        <?php endforeach; ?>
                        <?php if (empty($cell['docs']) && empty($cell['reports']) && empty($cell['ref'])): ?>
                        <div class="ms-3 text-secondary small">尚无原件；<?= $cell['state'] === 'todo' ? '可点击上方上传按钮补录。' : '' ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php /* ==================== 事件类 ==================== */ ?>
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-flag me-1"></i>事件类资料（按需归档）</h3>
    </div>
    <div class="list-group list-group-flush">
        <?php foreach ($a['events'] as $item): ?>
        <div class="list-group-item">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="fw-bold"><?= e($item['name']) ?></span>
                <span class="badge bg-azure-lt"><?= count($item['docs']) ?> 份</span>
                <button class="btn btn-sm btn-primary py-0" data-bs-toggle="modal" data-bs-target="#quick-upload-modal" data-quick-upload data-doc-type="<?= e($item['type']) ?>" data-link-type="worker" data-link-id="<?= $wid ?>" data-year="" data-label="<?= e($item['name']) ?>"><i class="ti ti-upload me-1"></i>上传</button>
                <?php if ($item['hint'] !== ''): ?><span class="text-warning small"><i class="ti ti-alert-triangle me-1"></i><?= e($item['hint']) ?></span><?php endif; ?>
            </div>
            <?php foreach ($item['docs'] as $dc): ?>
            <div class="doc-cell ms-3 mb-1">
                <a class="text-body" href="<?= url('documents/download', ['id' => $dc['id'], 'inline' => 1]) ?>" target="_blank"><i class="ti ti-file-text me-1"></i><span class="doc-title-text" data-doc-id="<?= (int)$dc['id'] ?>"><?= e($dc['title']) ?></span></a>
                <span class="text-secondary small"><?= e($dc['doc_date'] ?: '') ?></span>
                <?php if (($dc['from'] ?? '') === '职业病登记'): ?><span class="badge bg-blue-lt"><?= e($dc['from']) ?></span><?php endif; ?>
                <?php if (!empty($dc['file_missing'])): ?><span class="badge bg-red-lt">文件丢失</span><?php endif; ?>
                <a class="text-secondary" href="#" data-rename="<?= (int)$dc['id'] ?>" data-title="<?= e($dc['title']) ?>"><i class="ti ti-pencil"></i></a>
                <a class="text-secondary" href="<?= url('documents/download', ['id' => $dc['id']]) ?>"><i class="ti ti-download"></i></a>
                <a class="text-danger" href="#" data-del="<?= (int)$dc['id'] ?>" data-name="<?= e($dc['title']) ?>" data-docs="0" data-del-doc="1"><i class="ti ti-trash"></i></a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php /* ==================== 其他资料 ==================== */ ?>
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-box me-1"></i>其他资料</h3>
        <div class="card-actions">
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#quick-upload-modal" data-quick-upload data-doc-type="W_OTHER" data-link-type="worker" data-link-id="<?= $wid ?>" data-year="" data-label="其他个人资料"><i class="ti ti-upload me-1"></i>上传</button>
        </div>
    </div>
    <?php if (!$a['others']): ?>
    <div class="card-body text-secondary py-3">暂无其他资料（含未分类文档）；在「分类文档」中批量归类的文档会出现在对应栏目。</div>
    <?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($a['others'] as $dc): ?>
        <div class="list-group-item doc-cell">
            <a class="text-body" href="<?= url('documents/download', ['id' => $dc['id'], 'inline' => 1]) ?>" target="_blank"><i class="ti ti-file-text me-1"></i><span class="doc-title-text" data-doc-id="<?= (int)$dc['id'] ?>"><?= e($dc['title']) ?></span></a>
            <span class="badge <?= (string)$dc['doc_type'] !== '' ? 'bg-cyan-lt' : 'bg-secondary-lt' ?>"><?= e($dc['type_name']) ?></span>
            <span class="text-secondary small"><?= e($dc['doc_date'] ?: '') ?></span>
            <?php if (!empty($dc['file_missing'])): ?><span class="badge bg-red-lt">文件丢失</span><?php endif; ?>
            <span class="ms-auto text-nowrap">
                <a class="text-secondary" href="#" data-rename="<?= (int)$dc['id'] ?>" data-title="<?= e($dc['title']) ?>"><i class="ti ti-pencil"></i></a>
                <a class="text-secondary" href="<?= url('documents/download', ['id' => $dc['id']]) ?>"><i class="ti ti-download"></i></a>
                <a class="text-danger" href="#" data-del="<?= (int)$dc['id'] ?>" data-name="<?= e($dc['title']) ?>" data-docs="0" data-del-doc="1"><i class="ti ti-trash"></i></a>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php /* ==================== 快速上传模态框 ==================== */ ?>
<div class="modal modal-blur fade" id="quick-upload-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= url('documents/quick') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="doc_type" value="">
                <input type="hidden" name="link_type" value="worker">
                <input type="hidden" name="link_id" value="<?= $wid ?>">
                <input type="hidden" name="period_year" value="">
                <input type="hidden" name="back" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">上传：<span id="qum-label">资料</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">文件（可多选）</label>
                        <input type="file" class="form-control" name="files[]" multiple required
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                        <div class="form-hint">支持 PDF / 图片 / Word / Excel，单个不超过 20MB。</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">标题（留空自动生成）</label>
                        <input type="text" class="form-control" name="title" placeholder="留空按「资料类型 姓名 年度」生成">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">文档日期</label>
                            <input type="date" class="form-control" name="doc_date" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-upload me-1"></i>上传</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php /* ==================== 标不适用模态框 ==================== */ ?>
<div class="modal modal-blur fade" id="na-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= url('archive/mark') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="scope" value="worker">
                <input type="hidden" name="back" value="1">
                <input type="hidden" name="item_code" value="">
                <input type="hidden" name="worker_id" value="<?= $wid ?>">
                <input type="hidden" name="period_year" value="">
                <div class="modal-header">
                    <h5 class="modal-title">标记“不适用”：<span id="na-label"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label required">原因（必填）</label>
                    <div class="mb-2 d-flex flex-wrap gap-1" id="na-quick-reasons">
                        <button type="button" class="btn btn-sm" data-reason="当年未接触该危害因素">当年未接触该危害因素</button>
                        <button type="button" class="btn btn-sm" data-reason="当年无该项活动">当年无该项活动</button>
                        <button type="button" class="btn btn-sm" data-reason="资料由外部单位保管">资料由外部单位保管</button>
                    </div>
                    <textarea class="form-control" name="reason" rows="2" required placeholder="填写不适用原因，如：当年未接触该危害因素"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-secondary"><i class="ti ti-ban me-1"></i>标记不适用</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php /* 文档删除表单（back=1 回到本页；data-del-doc 标记区分劳动者删除） */ ?>
<form id="doc-del-form" method="post" action="<?= url('documents/delete') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="">
    <input type="hidden" name="back" value="1">
</form>

<script>
/* 快速上传 / 标不适用模态框预填 + 格子展开 + 文档删除（本页专用，不依赖新库） */
(function () {
    var naModal = document.getElementById('na-modal');
    if (naModal) {
        naModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) return;
            var f = naModal.querySelector('form');
            f.querySelector('[name="item_code"]').value = btn.getAttribute('data-item-code') || '';
            f.querySelector('[name="worker_id"]').value = btn.getAttribute('data-worker-id') || '';
            f.querySelector('[name="period_year"]').value = btn.getAttribute('data-year') || '';
            document.getElementById('na-label').textContent = btn.getAttribute('data-label') || '';
        });
        /* 快捷原因填充 */
        naModal.addEventListener('click', function (e) {
            var b = e.target.closest('#na-quick-reasons [data-reason]');
            if (!b) return;
            naModal.querySelector('[name="reason"]').value = b.getAttribute('data-reason');
        });
    }
    var modal = document.getElementById('quick-upload-modal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) return;
            var f = modal.querySelector('form');
            f.querySelector('[name="doc_type"]').value = btn.getAttribute('data-doc-type') || '';
            f.querySelector('[name="link_type"]').value = btn.getAttribute('data-link-type') || 'worker';
            f.querySelector('[name="link_id"]').value = btn.getAttribute('data-link-id') || '<?= $wid ?>';
            f.querySelector('[name="period_year"]').value = btn.getAttribute('data-year') || '';
            document.getElementById('qum-label').textContent = btn.getAttribute('data-label') || '资料';
            if (btn.getAttribute('data-year')) {
                document.getElementById('qum-label').textContent += '（' + btn.getAttribute('data-year') + ' 年）';
            }
        });
    }
    /* 格子展开/收起 */
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-cell-toggle]');
        if (t) {
            e.preventDefault();
            var row = document.getElementById(t.getAttribute('data-cell-toggle'));
            if (row) row.classList.toggle('d-none');
            return;
        }
        /* 文档删除（本页 #doc-del-form，区别于劳动者删除） */
        var del = e.target.closest('[data-del-doc]');
        if (del) {
            e.preventDefault();
            var form = document.getElementById('doc-del-form');
            if (!form) return;
            if (window.confirm('确定删除文档「' + (del.dataset.name || '') + '」吗？文件将一并删除。')) {
                form.querySelector('[name="id"]').value = del.dataset.del;
                var fd = new FormData(form);
                fetch(form.action, {
                    method: 'POST', body: fd, credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).then(function (r) { return r.json(); })
                  .then(function (d) { if (d && d.ok) window.location.reload(); else alert((d && d.msg) || '删除失败'); })
                  .catch(function () { form.submit(); });
            }
        }
    });
})();
</script>
