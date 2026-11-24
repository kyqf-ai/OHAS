<?php
/**
 * 分页部件（Tabler 版）：$data 为 Model::paginate 的返回值；保留当前查询参数。
 */
if (!function_exists('page_url')) {
    function page_url(int $p): string
    {
        $q = $_GET;
        $q['page'] = $p;
        $path = (isset($_GET['r']) && is_string($_GET['r'])) ? $_GET['r'] : ltrim($_SERVER['PATH_INFO'] ?? '/', '/');
        return url($path !== '' ? $path : 'dashboard/index', $q);
    }
}
$total = (int)($data['total'] ?? 0);
$page = (int)($data['page'] ?? 1);
$pages = (int)($data['pages'] ?? 1);
?>
<?php if ($total > 0): ?>
<div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2 border-top">
    <span class="text-secondary">共 <?= $total ?> 条 · 第 <?= $page ?>/<?= $pages ?> 页</span>
    <ul class="pagination m-0">
        <?php if ($page > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= e(page_url($page - 1)) ?>" aria-label="上一页"><i class="ti ti-chevron-left"></i></a></li>
        <?php else: ?>
            <li class="page-item disabled"><a class="page-link" tabindex="-1" aria-label="上一页"><i class="ti ti-chevron-left"></i></a></li>
        <?php endif; ?>
        <?php
        $from = max(1, $page - 2);
        $to = min($pages, $from + 4);
        $from = max(1, $to - 4);
        for ($i = $from; $i <= $to; $i++):
        ?>
            <?php if ($i === $page): ?>
                <li class="page-item active"><a class="page-link"><?= $i ?></a></li>
            <?php else: ?>
                <li class="page-item"><a class="page-link" href="<?= e(page_url($i)) ?>"><?= $i ?></a></li>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
            <li class="page-item"><a class="page-link" href="<?= e(page_url($page + 1)) ?>" aria-label="下一页"><i class="ti ti-chevron-right"></i></a></li>
        <?php else: ?>
            <li class="page-item disabled"><a class="page-link" tabindex="-1" aria-label="下一页"><i class="ti ti-chevron-right"></i></a></li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>
