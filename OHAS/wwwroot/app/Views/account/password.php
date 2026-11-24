<div class="card" style="max-width: 560px;">
    <div class="card-header">
        <h3 class="card-title">修改密码</h3>
    </div>
    <?php if (form_errors()): ?>
    <div class="card-body pb-0">
        <div class="alert alert-danger alert-dismissible" role="alert">
            <i class="ti ti-alert-triangle alert-icon"></i>
            <div><?php foreach (form_errors() as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="关闭"></a>
        </div>
    </div>
    <?php endif; ?>
    <form method="post" action="<?= url('account/password') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label required">原密码</label>
                <input type="password" class="form-control" name="old_password" required>
            </div>
            <div class="mb-3">
                <label class="form-label required">新密码</label>
                <input type="password" class="form-control" name="new_password" placeholder="至少 8 位" required>
                <?php if (form_error('new_password')): ?><div class="invalid-feedback d-block"><?= e(form_error('new_password')) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label required">确认新密码</label>
                <input type="password" class="form-control" name="new_password_confirm" required>
                <?php if (form_error('new_password_confirm')): ?><div class="invalid-feedback d-block"><?= e(form_error('new_password_confirm')) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="card-footer text-end">
            <button class="btn btn-primary" type="submit"><i class="ti ti-key me-1"></i>保存新密码</button>
        </div>
    </form>
</div>
