<?php /** 某清单项的缺失名单：逐行“去上传”入口 + CSV 导出 */ ?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
        <a href="<?= url('archive/checklist') ?>" class="text-secondary"><i class="ti ti-arrow-left me-1"></i>返回资料清单</a>
        <div class="mt-1">
            <span class="badge bg-blue-lt"><?= e($item['code']) ?></span>
            <b class="ms-2"><?= e($item['name']) ?></b>
            <span class="text-secondary ms-2">自评项 <?= e($item['self']) ?></span>
        </div>
    </div>
    <a class="btn" href="<?= url('archive/missing', ['item' => $item['code'], 'format' => 'csv']) ?>"><i class="ti ti-file-export me-1"></i>导出 CSV</a>
</div>

<div class="card">
    <?php if (!$rows): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-circle-check"></i></div>
            <p class="empty-title">没有缺失对象</p>
            <p class="empty-subtitle text-secondary">该项资料按当前数据已齐备（或已标记不适用）。</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead><tr><th>对象</th><th style="width: 260px;">说明</th><th class="text-end">操作</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['label']) ?></td>
                    <td class="text-secondary"><?= e($r['sub']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($r['kind'] === 'person' || $r['kind'] === 'cell'): ?>
                            <a class="btn btn-sm btn-primary" href="<?= e($r['url']) ?>"><i class="ti ti-upload me-1"></i>去上传</a>
                        <?php elseif ($r['kind'] === 'record'): ?>
                            <a class="btn btn-sm btn-primary" href="<?= e($r['url']) ?>"><i class="ti ti-external-link me-1"></i>去记录页上传</a>
                        <?php elseif ($r['kind'] === 'ledger'): ?>
                            <a class="btn btn-sm btn-primary" href="<?= e($r['url']) ?>"><i class="ti ti-notebook me-1"></i>去台账</a>
                        <?php else: ?>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#missing-upload-modal"
                                    data-doc-type="<?= e($item['parts'][0]['raw']['types'][0] ?? '') ?>" data-label="<?= e($item['name']) ?>"><i class="ti ti-upload me-1"></i>上传</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-secondary">共 <?= count($rows) ?> 条；人员类对象点击“去上传”进入个人资料页对应年度格子。</div>
    <?php endif; ?>
</div>

<div class="modal modal-blur fade" id="missing-upload-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= url('documents/quick') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="doc_type" value="">
                <input type="hidden" name="link_type" value="">
                <input type="hidden" name="link_id" value="">
                <input type="hidden" name="period_year" value="<?= (int)date('Y') ?>">
                <input type="hidden" name="back" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">上传：<span id="mum-label">资料</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">文件（可多选）</label>
                        <input type="file" class="form-control" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">标题（留空自动生成）</label>
                        <input type="text" class="form-control" name="title">
                    </div>
                    <div class="col-6">
                        <label class="form-label">文档日期</label>
                        <input type="date" class="form-control" name="doc_date" value="<?= date('Y-m-d') ?>">
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

<script>
(function () {
    var m = document.getElementById('missing-upload-modal');
    if (m) {
        m.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) return;
            m.querySelector('[name="doc_type"]').value = btn.getAttribute('data-doc-type') || '';
            document.getElementById('mum-label').textContent = btn.getAttribute('data-label') || '资料';
        });
    }
})();
</script>
