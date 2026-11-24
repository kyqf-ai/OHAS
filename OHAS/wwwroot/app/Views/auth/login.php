<?php $title = $title ?? '登录'; ?>
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
        <div class="text-center mb-4">
            <img src="<?= asset('assets/logo.svg') ?>" alt="Logo" height="60">
        </div>
        <div class="card card-md">
            <div class="card-body">
                <h2 class="h2 text-center mb-1"><?= e(cfg('app.name')) ?></h2>
                <p class="text-secondary text-center mb-4">劳动者个人职业健康监护档案 · 简化版</p>
                <?php $flash = flash_pull(); if ($flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible" role="alert">
                        <i class="ti ti-alert-triangle alert-icon"></i>
                        <div><?= e($flash['msg']) ?></div>
                        <a class="btn-close" data-bs-dismiss="alert" aria-label="关闭"></a>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= url('auth/login') ?>" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label required">用户名</label>
                        <input type="text" class="form-control" name="username" value="<?= old('username', 'admin') ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">密码</label>
                        <input type="password" class="form-control" name="password" placeholder="请输入管理员密码" required>
                    </div>
                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ti ti-login me-1"></i>登 录
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="text-center text-secondary small mt-3">
            连续 5 次密码错误将锁定账号 5 分钟 · 会话闲置 2 小时自动登出
        </div>
    </div>
</div>
</body>
</html>
