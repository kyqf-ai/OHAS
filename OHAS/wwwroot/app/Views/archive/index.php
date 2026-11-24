<?php
/** 六类档案总览：按《职业卫生档案管理规范》所列六类档案组织（图标统一取 ArchiveCatalog::icon） */
?>
<div class="row row-cards mb-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="subheader">规范表样</div>
                <div class="h1 m-0"><?= (int)$overview['total'] ?><span class="text-secondary fs-3"> 张</span></div>
                <div class="text-secondary mt-1">对应《职业卫生档案管理规范》六类档案</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="subheader">已建立</div>
                <div class="h1 m-0 text-green"><?= (int)$overview['filled'] ?><span class="text-secondary fs-3"> 张</span></div>
                <div class="text-secondary mt-1">已有业务数据可归档的表样</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="subheader">尚无记录</div>
                <div class="h1 m-0 <?= ((int)$overview['total'] - (int)$overview['filled']) > 0 ? 'text-orange' : 'text-secondary' ?>"><?= max(0, (int)$overview['total'] - (int)$overview['filled']) ?><span class="text-secondary fs-3"> 张</span></div>
                <div class="text-secondary mt-1">已建 <?= (int)$overview['filled'] ?> / <?= (int)$overview['total'] ?> 张；只统计有无记录，不评分</div>
            </div>
        </div>
    </div>
</div>

<div class="row row-cards">
    <?php foreach ($overview['groups'] as $stat): $g = $stat['group']; ?>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="avatar avatar-sm bg-primary-lt me-2"><i class="ti ti-<?= e(\App\Helpers\ArchiveCatalog::icon((int)$g['no'])) ?>"></i></span>
                    <?= e(\App\Helpers\ArchiveCatalog::numeral((int)$g['no'])) ?>、<?= e($g['title']) ?>
                </h3>
                <div class="card-actions">
                    <span class="badge <?= ((int)$stat['total'] > 0 && (int)$stat['filled'] >= (int)$stat['total']) ? 'bg-green-lt' : ((int)$stat['filled'] > 0 ? 'bg-blue-lt' : 'bg-secondary-lt') ?>">
                        已建 <?= (int)$stat['filled'] ?> / <?= (int)$stat['total'] ?> 张
                    </span>
                </div>
            </div>
            <div class="card-body">
                <p class="text-secondary mb-3"><?= e($g['intro']) ?></p>
                <div class="table-responsive">
                    <table class="table table-sm table-vcenter">
                        <tbody>
                        <?php foreach ($stat['items'] as $it): $act = \App\Helpers\ArchiveCatalog::actions($it); ?>
                            <tr>
                                <td class="text-secondary text-nowrap" style="width: 72px;"><?= e($it['no']) ?></td>
                                <td>
                                    <a href="<?= e($act['entry_url']) ?>" class="text-body"><?= e($it['title']) ?></a>
                                </td>
                                <td class="text-end text-nowrap" style="width: 130px;">
                                    <?php if ($it['count'] > 0): ?>
                                        <span class="badge bg-green-lt"><?= (int)$it['count'] ?> 条</span>
                                    <?php else: ?>
                                        <span class="badge bg-yellow-lt">待建立</span>
                                    <?php endif; ?>
                                    <?php if ($act['print_url']): ?>
                                        <a class="btn btn-sm btn-icon btn-ghost-secondary ms-1" href="<?= e($act['print_url']) ?>" target="_blank" title="<?= e($act['print_hint']) ?>"><i class="ti ti-printer"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <a href="<?= url('archive/show', ['group' => $g['key']]) ?>" class="btn btn-sm btn-primary">
                    <i class="ti ti-folder-open me-1"></i>进入栏目
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row row-cards mt-1">
    <div class="col-md-6 col-lg-4">
        <a class="card card-link" href="<?= url('documents/index') ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="avatar bg-purple-lt"><i class="ti ti-folder fs-2"></i></span>
                <div>
                    <div class="h3 m-0">分类文档</div>
                    <div class="text-secondary">11 类档案原件上传与到期提醒</div>
                </div>
            </div>
        </a>
    </div>
</div>
