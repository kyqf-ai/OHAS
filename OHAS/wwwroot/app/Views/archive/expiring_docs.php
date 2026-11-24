<?php
/**
 * 到期资料名单：工作台/资料清单「已过期 / 即将到期」徽标的明细页。
 */
use App\Helpers\DocTypes;
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
        <a href="<?= url('documents/index') ?>" class="text-secondary"><i class="ti ti-arrow-left me-1"></i>返回分类文档</a>
        <div class="text-secondary mt-1">按有效期排序，含合同、资质证书等全部已登记有效期的原件</div>
    </div>
    <span class="badge bg-secondary-lt fs-5"><?= count($rows) ?> 份</span>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
            <tr><th>资料标题</th><th>资料类型</th><th>有效期至</th><th>状态</th><th class="w-1"></th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-secondary py-5">暂无到期资料</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['title'] ?: '（未命名）') ?></td>
                    <td class="text-secondary"><?= e(DocTypes::nameOf((string)($r['doc_type'] ?? '')) ?: $r['category']) ?></td>
                    <td class="font-monospace"><?= e($r['expire_date']) ?></td>
                    <td>
                        <?php if ($r['exp_state'] === 'expired'): ?>
                            <span class="badge bg-red-lt">已过期 <?= abs((int)$r['days_left']) ?> 天</span>
                        <?php else: ?>
                            <span class="badge bg-yellow-lt"><?= (int)$r['days_left'] ?> 天后到期</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><a class="btn btn-sm" href="<?= url('documents/index', ['expiry' => $r['exp_state']]) ?>">查看 <i class="ti ti-chevron-right ms-1"></i></a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="text-secondary small mt-3">
    <i class="ti ti-info-circle me-1"></i>「即将到期」阈值（当前 <?= (int)$soonDays ?> 天）可在 <code>config.php → expiry.soon_days</code> 调整，工作台与资料清单同步生效。
</div>
