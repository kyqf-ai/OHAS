<?php
$g = $stat['group'];
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
        <a href="<?= url('archive/index') ?>" class="text-secondary"><i class="ti ti-arrow-left me-1"></i>返回六类档案</a>
        <div class="text-secondary mt-1">六类职业卫生档案之<?= e(\App\Helpers\ArchiveCatalog::numeral((int)$g['no'])) ?></div>
    </div>
    <div class="d-flex gap-2">
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="flex-fill">
                <h2 class="m-0"><?= e($g['title']) ?></h2>
                <p class="text-secondary mt-2 mb-0"><?= e($g['intro']) ?></p>
            </div>
            <div class="text-end" style="min-width: 180px;">
                <div class="text-secondary">表样建立情况</div>
                <div class="h1 m-0 <?= ((int)$stat['total'] > 0 && (int)$stat['filled'] >= (int)$stat['total']) ? 'text-green' : '' ?>">已建 <?= (int)$stat['filled'] ?> / <?= (int)$stat['total'] ?><span class="text-secondary fs-3"> 张</span></div>
                <div class="text-secondary small mt-1">只统计有无记录，不评分</div>
            </div>
        </div>
    </div>
</div>

<?php
// 自评表 19 项映射（补充层）：按项号索引，供目录项标注"印证自评项"
$saMap = [];
foreach (\App\Helpers\ArchiveChecklist::selfAssessmentMap() as $sa) {
    $saMap[$sa[0]] = $sa;
}
?>
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">档案盒目录（按规范逐项核对）</h3>
        <div class="card-actions text-secondary">
            共 <?= count($filing) ?> 项 · 可判定 <?= (int)$filingStat['checkable'] ?> 项已归档 <?= (int)$filingStat['done'] ?> 项 · 按《职业卫生档案管理规范》附件目录
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <th class="text-center" style="width:56px;">序号</th>
                    <th>目录项（官方）</th>
                    <th>性质</th>
                    <th>系统落地 / 自评表关联</th>
                    <th class="text-center" style="width:88px;">原件/记录</th>
                    <th class="text-center" style="width:88px;">状态</th>
                    <th class="text-end" style="width:130px;">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filing as $row): $e = $row['entry']; $st = $row['status'];
                [$seq, $name, $kind, $key, $self] = $e;
                $selfLabels = [];
                foreach (array_filter(preg_split('/[、,]/', $self)) as $sn) {
                    if (isset($saMap[trim($sn)])) {
                        $selfLabels[] = trim($sn) . '.' . $saMap[trim($sn)][1];
                    }
                }
            ?>
                <tr>
                    <td class="text-center text-secondary"><?= e($seq) ?></td>
                    <td class="text-body">
                        <a href="<?= e(\App\Helpers\ArchiveCatalog::filingTargetUrl($g['key'], $seq)) ?>" class="text-body">
                            <?= e($name) ?>
                        </a>
                        <?php if (\App\Helpers\ArchiveCatalog::filingMode($e) === 'internal'): ?>
                            <i class="ti ti-pencil ms-1 text-secondary small" title="内部编制·可在线编辑"></i>
                        <?php endif; ?>
                    </td>
                    <td><?= $kind === 'form' ? '<span class="badge bg-blue-lt">规范表样</span>' : '<span class="badge bg-cyan-lt">文档原件</span>' ?></td>
                    <td class="text-secondary small">
                        <?php if ($key !== ''): ?><span class="text-body"><?= e($key) ?></span><?php endif; ?>
                        <?php if ($selfLabels): ?><div>自评表：<?= e(implode('、', $selfLabels)) ?></div><?php endif; ?>
                        <?php if ($key === '' && !$selfLabels): ?><div>—</div><?php endif; ?>
                    </td>
                    <td class="text-center"><?= (int)$st['count'] ?></td>
                    <td class="text-center">
                        <?php $sb = \App\Helpers\WorkerArchive::badge($st['state']); ?>
                        <span class="badge <?= $sb[1] ?>"><?= e($sb[0]) ?></span>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($st['url'] !== '#'): ?>
                        <a class="btn btn-sm" href="<?= e($st['url']) ?>" title="<?= e($st['hint']) ?>"><?= e($st['label']) ?><i class="ti ti-chevron-right ms-1"></i></a>
                        <?php else: ?><span class="text-secondary small">—</span><?php endif; ?>
                        <?php if (!empty($st['print_url'])): ?>
                        <a class="btn btn-sm btn-icon ms-1" href="<?= e($st['print_url']) ?>" target="_blank" title="整表打印（规范表样）"><i class="ti ti-printer"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($docs)): ?>
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">本类档案原件</h3>
        <div class="card-actions text-secondary">法规制度、报告、证书等原件按类别归入本类档案</div>
    </div>
    <div class="list-group list-group-flush">
        <?php foreach ($docs as $cat => $cnt): ?>
        <div class="list-group-item d-flex align-items-center gap-3">
            <i class="ti ti-file-text text-secondary"></i>
            <a class="text-body" href="<?= url('documents/index', ['category' => $cat]) ?>"><?= e($cat) ?></a>
            <span class="badge <?= $cnt > 0 ? 'bg-green-lt' : 'bg-yellow-lt' ?>"><?= $cnt > 0 ? $cnt . ' 份' : '待上传' ?></span>
            <a class="btn btn-sm ms-auto" href="<?= url('documents/create', ['category' => $cat]) ?>"><i class="ti ti-upload me-1"></i>上传</a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
