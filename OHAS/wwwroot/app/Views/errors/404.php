<?php $title = $title ?? '404'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - <?= e(cfg('app.name')) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= asset('assets/logo.svg') ?>">
<link rel="stylesheet" href="<?= asset('assets/vendor/tabler.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/vendor/tabler-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
</head>
<body class="d-flex flex-column auth-body">
<div class="page page-center">
    <div class="container container-tight py-4">
        <div class="card card-lg">
            <div class="card-body p-4 text-center">
                <div class="display-1 fw-bolder text-primary-lt">404</div>
                <h1 class="h2 mt-2 mb-2">页面不存在</h1>
                <div class="text-secondary"><?= e($hint ?? '请检查地址是否正确，或从左侧菜单重新进入。') ?></div>
                <div class="mt-4">
                    <a class="btn btn-primary" href="<?= url('dashboard/index') ?>">
                        <i class="ti ti-arrow-left me-1"></i>返回工作台
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
