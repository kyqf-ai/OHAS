<?php
/**
 * 业务页中的「原件」区块：列出关联到本记录的文档，并提供上传入口。
 * 原件统一保存在分类文档（documents），这里只是它在业务页的视图。
 * 变量：$docs（DocLink::forLink）、$linkType、$linkId、$docHeading（可选，默认「报告原件」）
 */
$docHeading = $docHeading ?? '报告原件';
$defaultDocType = $defaultDocType ?? '';   // 可选：上传时预选的资料类型
?>
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-file-text me-1"></i><?= e($docHeading) ?>
            <span class="badge bg-blue-lt ms-2"><?= count($docs) ?> 份</span>
        </h3>
        <div class="card-actions">
            <a class="btn btn-sm btn-primary" href="<?= e(\App\Helpers\DocLink::uploadUrl($linkType, (int)$linkId) . ($defaultDocType !== '' ? '&doc_type=' . rawurlencode($defaultDocType) : '')) ?>"><i class="ti ti-upload me-1"></i>上传原件</a>
        </div>
    </div>
    <?php if (!$docs): ?>
        <div class="card-body text-secondary py-3">暂无原件。可上传 PDF / 扫描件 / Word / Excel，同一记录可上传多份；原件同时归入「分类文档」统一管理。</div>
    <?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($docs as $dc): ?>
        <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
            <a class="text-body" href="<?= url('documents/download', ['id' => $dc['id'], 'inline' => 1]) ?>" target="_blank"><i class="ti ti-file-text me-1"></i><span class="doc-title-text" data-doc-id="<?= (int)$dc['id'] ?>"><?= e($dc['title']) ?></span></a>
            <?php if ((string)$dc['doc_type'] !== ''): ?>
                <span class="badge bg-cyan-lt"><?= e(\App\Helpers\DocTypes::nameOf((string)$dc['doc_type'])) ?></span>
            <?php else: ?>
                <span class="badge bg-secondary-lt">未分类</span>
            <?php endif; ?>
            <span class="badge bg-blue-lt"><?= e($dc['category']) ?></span>
            <?php if ((string)$dc['doc_date'] !== ''): ?><span class="text-secondary small font-monospace"><?= d($dc['doc_date']) ?></span><?php endif; ?>
            <span class="ms-auto text-nowrap">
                <a class="text-body me-2" href="<?= url('documents/download', ['id' => $dc['id']]) ?>" title="下载"><i class="ti ti-download"></i></a>
                <a class="text-body me-2" href="#" title="改名" data-rename="<?= (int)$dc['id'] ?>" data-title="<?= e($dc['title']) ?>"><i class="ti ti-pencil"></i></a>
                <a class="text-body" href="<?= url('documents/edit', ['id' => $dc['id']]) ?>" title="编辑 / 替换文件"><i class="ti ti-settings-2"></i></a>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
