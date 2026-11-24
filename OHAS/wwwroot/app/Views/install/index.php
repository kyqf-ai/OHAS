<?php $title = $title ?? '系统安装'; ?>
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
                <p class="text-secondary text-center mb-4">首次部署：设置管理员密码 → 录入单位信息 → 初始化字典</p>
                <?php $flash = flash_pull(); if ($flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible" role="alert">
                        <i class="ti ti-alert-triangle alert-icon"></i>
                        <div><?= e($flash['msg']) ?></div>
                        <a class="btn-close" data-bs-dismiss="alert" aria-label="关闭"></a>
                    </div>
                <?php endif; ?>
                <?php if (form_errors()): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="ti ti-alert-triangle alert-icon"></i>
                        <div>
                            <?php foreach (form_errors() as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= url('install/index') ?>" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label required">管理员密码</label>
                        <input type="password" class="form-control" name="password" placeholder="至少 8 位，建议字母+数字组合" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">确认密码</label>
                        <input type="password" class="form-control" name="password_confirm" placeholder="再次输入密码" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">单位名称</label>
                        <input type="text" class="form-control" name="company_name" value="<?= old('company_name', '') ?>" placeholder="如：某某机械制造有限公司">
                    </div>
                    <label class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="seed_dict" value="1" checked>
                        <span class="form-check-label">内置《职业病危害因素分类目录》（国卫疾控发〔2015〕92号）全部 459 条
                            <span class="text-secondary d-block small">六类齐全：粉尘 52、化学因素 375、物理因素 15、放射性因素 8、生物因素 6、其他因素 3；含目录序号与 CAS 号，其中 339 条附现行标准接触限值（GBZ 2.1-2019 及其修改单、GBZ 2.2-2007、GB 18871）、151 条按《职业病分类和目录》（2024版）预填可能职业病，可在系统内增删改</span>
                        </span>
                    </label>
                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ti ti-circle-check me-1"></i>开始安装
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="text-center text-secondary small mt-3">
            安装完成后将在 storage 目录生成 SQLite 数据库文件与 installed.lock 锁文件；安装向导随即自动停用，重复访问不再进入本页。
        </div>
    </div>
</div>
</body>
</html>
