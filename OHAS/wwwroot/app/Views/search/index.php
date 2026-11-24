<?php /** 全局搜索结果（命中单人已在控制器 302 到个人资料页） */ ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">搜索“<?= e($q) ?>”</h3>
        <div class="card-actions">
            <span class="badge bg-blue-lt"><?= count($rows) ?> 人</span>
            <?php if ($exact): ?><span class="badge bg-secondary-lt ms-1">身份证号精确匹配</span><?php endif; ?>
        </div>
    </div>
    <?php if ($q === ''): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-search"></i></div>
            <p class="empty-title">输入姓名 / 身份证号 / 档案号</p>
            <p class="empty-subtitle text-secondary">使用页面上方的搜索框检索劳动者；身份证号将精确匹配，姓名支持模糊。</p>
        </div>
    <?php elseif (!$rows): ?>
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-user-question"></i></div>
            <p class="empty-title">没有找到“<?= e($q) ?>”</p>
            <p class="empty-subtitle text-secondary">可核对姓名或身份证号后重试；确未建档的人员请先建立信息卡。</p>
            <div class="empty-action d-flex gap-2">
                <a class="btn" href="<?= url('workers/index', ['q' => $q]) ?>"><i class="ti ti-list me-1"></i>在劳动者列表中查看</a>
                <a class="btn btn-primary" href="<?= url('workers/create') ?>"><i class="ti ti-user-plus me-1"></i>新建信息卡</a>
            </div>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr><th>档案号</th><th>姓名</th><th>性别</th><th>身份证号</th><th>当前岗位</th><th>状态</th><th class="w-1"></th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $w): ?>
                <tr>
                    <td class="font-monospace"><?= e($w['archive_no']) ?></td>
                    <td><a class="text-body fw-bold" href="<?= url('workers/files', ['id' => $w['id']]) ?>"><?= e($w['name']) ?></a></td>
                    <td><?= e($w['gender'] ?: '—') ?></td>
                    <td class="font-monospace text-secondary"><?= e(mask_id_card($w['id_card'])) ?></td>
                    <td><?= e($w['current_post'] ?: '—') ?></td>
                    <td><span class="badge <?= $w['status'] === '在职' ? 'bg-green-lt' : 'bg-secondary-lt' ?>"><?= e($w['status']) ?></span></td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-primary" href="<?= url('workers/files', ['id' => $w['id']]) ?>"><i class="ti ti-folders me-1"></i>资料全景</a>
                        <a class="btn btn-sm" href="<?= url('workers/show', ['id' => $w['id']]) ?>">信息卡</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
