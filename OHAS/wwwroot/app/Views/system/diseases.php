<?php
/** 《职业病分类和目录》（2024版）12类135种，供职业病诊断与档案自查对照。 */
$groups = \App\Helpers\DiseaseCatalog::groups();
$total = \App\Helpers\DiseaseCatalog::count();
?>
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <span class="avatar bg-red-lt"><i class="ti ti-virus fs-2"></i></span>
        <div class="col">
            <div class="h3 mb-1">职业病分类和目录（2024版）</div>
            <div class="text-secondary">
                <?= e(\App\Helpers\DiseaseCatalog::SOURCE) ?>，自 2025-08-01 施行，同时废止 2013 年版；
                共 <b><?= $total ?></b> 种，含 4 项开放性条款。职业病诊断登记应在本目录范围内。
            </div>
        </div>
        <a class="btn" href="<?= url('standards/index') ?>"><i class="ti ti-arrow-left me-1"></i>返回标准依据</a>
    </div>
</div>

<?php $idx = 0; foreach ($groups as $category => $subGroups): $idx++; ?>
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><?= $idx ?>. <?= e($category) ?></h3>
        <div class="card-subtitle">
            共 <?= array_sum(array_map('count', $subGroups)) ?> 种
        </div>
    </div>
    <div class="card-body">
        <?php foreach ($subGroups as $subName => $items): ?>
            <?php if ($subName !== ''): ?>
                <div class="hr-text hr-text-spaceless"><span class="text-secondary"><?= e($subName) ?>（<?= count($items) ?>）</span></div>
            <?php endif; ?>
            <ol class="mb-0 ps-4 lh-lg">
                <?php foreach ($items as $name): ?>
                    <li><?= e($name) ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
