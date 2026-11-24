<?php
/**
 * 官方目录项资料管理页。
 *   external：外部机构出具报告——仅附件上传/查看；
 *   internal：单位内部编制——结构化字段 + 大文本正文在线编辑 + 附件 + 打印。
 */
use App\Helpers\DocTypes;
use App\Helpers\WorkerArchive;

[$seq, $name, $kind, $key, $self, $checkCode] = $entry;
$isInternal = $mode === 'internal';
$sb = WorkerArchive::badge($status['state']);
$stateLabel = $sb[0];
$materialId = (int)($material['id'] ?? 0);
$hasBody = ($materialId > 0 && trim((string)($material['body'] ?? '')) !== '');
$printUrl = $isInternal && $hasBody
    ? url('prints/archiveMaterial', ['id' => $materialId])
    : '';
$docHeading = $isInternal ? '资料附件' : '报告原件';
$defaultDocType = $docTypes[0] ?? '';
$linkType = 'archive_material';
$linkId = $materialId;
$uploadUrl = \App\Helpers\DocLink::uploadUrl($linkType, $linkId);
if ($docTypes) {
    $uploadUrl .= '&doc_type=' . rawurlencode($docTypes[0]);
}
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
        <a href="<?= url('archive/show', ['group' => $group['key']]) ?>" class="text-secondary">
            <i class="ti ti-arrow-left me-1"></i>返回<?= e($group['short']) ?>
        </a>
        <div class="text-secondary mt-1">
            六类职业卫生档案之<?= e(\App\Helpers\ArchiveCatalog::numeral((int)$group['no'])) ?>
            · 官方目录第 <?= e($seq) ?> 项
        </div>
    </div>
    <div class="d-flex gap-2">
        <?php if ($checkCode !== ''): ?>
        <a class="btn" href="<?= url('archive/checklist', ['c' => $checkCode]) ?>"><i class="ti ti-list-check me-1"></i>查看清单判定</a>
        <?php endif; ?>
        <?php if ($printUrl !== ''): ?>
        <a class="btn" href="<?= e($printUrl) ?>" target="_blank"><i class="ti ti-printer me-1"></i>打印资料</a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="flex-fill">
                <h2 class="m-0"><?= e($name) ?></h2>
                <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge <?= $isInternal ? 'bg-blue-lt' : 'bg-cyan-lt' ?>">
                        <i class="ti ti-<?= $isInternal ? 'pencil' : 'upload' ?> me-1"></i>
                        <?= $isInternal ? '内部编制 · 可在线编辑' : '外部出具 · 附件上传' ?>
                    </span>
                    <span class="badge <?= $sb[1] ?>"><?= e($stateLabel) ?></span>
                    <?php if ($key !== ''): ?><span class="text-secondary small">落地：<?= e($key) ?></span><?php endif; ?>
                    <?php if ($checkCode !== ''): ?><span class="text-secondary small">判定项：<?= e($checkCode) ?></span><?php endif; ?>
                </div>
            </div>
            <div class="text-end" style="min-width:120px;">
                <div class="text-secondary">已上传原件</div>
                <div class="h1 m-0"><?= count($docs) ?><span class="text-secondary fs-3"> 份</span></div>
            </div>
        </div>
    </div>
</div>

<?php if ($isInternal): ?>
<form method="post" action="<?= url('archive/materialSave') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="group" value="<?= e($group['key']) ?>">
    <input type="hidden" name="seq" value="<?= e($seq) ?>">
    <input type="hidden" name="doc_type" value="<?= e($docTypes[0] ?? '') ?>">
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="ti ti-edit me-1"></i>在线编制</h3>
            <div class="card-actions text-secondary">填写后保存，可继续上传附件；正文可直接打印</div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required">标题</label>
                    <input type="text" class="form-control" name="title" maxlength="120" required
                           value="<?= e($material['title'] ?? $name) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">文号 / 编号</label>
                    <input type="text" class="form-control" name="doc_no" maxlength="80"
                           value="<?= e($material['doc_no'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">日期</label>
                    <input type="date" class="form-control" name="doc_date"
                           value="<?= e($material['doc_date'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">编制 / 发布日期单位</label>
                    <input type="text" class="form-control" name="issuer" maxlength="120" placeholder="如：本单位职业病防治领导小组"
                           value="<?= e($material['issuer'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">正文</label>
                    <textarea class="form-control" name="body" rows="16" style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace"
                              placeholder="按规范逐条编写；支持分段与简单文本排版。"><?= e($material['body'] ?? '') ?></textarea>
                    <div class="form-text">纯文本保存，零外部依赖；打印时自动分页。上限 20 万字。</div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>保存资料</button>
            <?php if ($printUrl !== ''): ?>
            <a class="btn" href="<?= e($printUrl) ?>" target="_blank"><i class="ti ti-printer me-1"></i>打印</a>
            <?php else: ?>
            <span class="text-secondary small align-self-center">保存正文后可打印</span>
            <?php endif; ?>
            <?php if ($materialId > 0): ?>
            <button type="button" class="btn btn-danger ms-auto" data-bs-toggle="modal" data-bs-target="#material-delete-modal">
                <i class="ti ti-trash me-1"></i>删除正文
            </button>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if ($materialId > 0): ?>
<div class="modal fade" id="material-delete-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header"><h4 class="modal-title">删除在线正文</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button></div>
            <div class="modal-body">确认删除在线正文？已上传附件会保留。</div>
            <div class="modal-footer">
                <button type="button" class="btn" data-bs-dismiss="modal">取消</button>
                <form method="post" action="<?= url('archive/materialDelete') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$materialId ?>">
                    <button type="submit" class="btn btn-danger">确认删除</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php else: ?>
<div class="card mb-3">
    <div class="card-body text-secondary">
        <i class="ti ti-info-circle me-1"></i>
        该项资料由外部机构出具（如检测、评价、体检、资质证书等），系统仅保存原件附件与登记信息，不提供在线正文编辑。
        请使用下方「上传原件」把 PDF / 扫描件归档到本目录项。
    </div>
</div>
<?php endif; ?>

<?php include OHAS_ROOT . '/app/Views/partials/doc_list.php'; ?>
