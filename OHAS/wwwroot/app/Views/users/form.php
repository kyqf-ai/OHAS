<?php
$isEdit = !empty($isEdit);
$action = $isEdit ? url('users/edit', ['id' => (int)$user['id']]) : url('users/create');
?>
<div class="card" style="max-width: 560px;">
    <div class="card-header">
        <h3 class="card-title"><?= $isEdit ? '编辑账号：' . e($user['username']) : '新增账号' ?></h3>
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
    <form method="post" action="<?= $action ?>" autocomplete="off">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$user['id'] ?>"><?php endif; ?>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label <?= $isEdit ? '' : 'required' ?>">用户名</label>
                <?php if ($isEdit): ?>
                <input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled>
                <div class="form-hint">用户名创建后不可修改（操作日志按用户名留痕）。</div>
                <?php else: ?>
                <input type="text" class="form-control" name="username" value="<?= old('username') ?>"
                       placeholder="如：zhangsan（3~20 位小写字母、数字或下划线）" maxlength="20" required>
                <?php if (form_error('username')): ?><div class="invalid-feedback d-block"><?= e(form_error('username')) ?></div><?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label required">姓名</label>
                <input type="text" class="form-control" name="real_name" value="<?= old('real_name', $user['real_name'] ?? '') ?>"
                       placeholder="如：张三（日志与右上角显示用）" maxlength="20" required>
                <?php if (form_error('real_name')): ?><div class="invalid-feedback d-block"><?= e(form_error('real_name')) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label <?= $isEdit ? '' : 'required' ?>"><?= $isEdit ? '重置密码（留空则不修改）' : '初始密码' ?></label>
                <input type="password" class="form-control" name="password" placeholder="至少 8 位" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                <?php if (form_error('password')): ?><div class="invalid-feedback d-block"><?= e(form_error('password')) ?></div><?php endif; ?>
                <?php if ($isEdit): ?><div class="form-hint">重置密码同时会解除该账号的登录锁定。</div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label <?= $isEdit ? '' : 'required' ?>">确认密码</label>
                <input type="password" class="form-control" name="password_confirm" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                <?php if (form_error('password_confirm')): ?><div class="invalid-feedback d-block"><?= e(form_error('password_confirm')) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <a class="btn" href="<?= url('users/index') ?>">返回</a>
            <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>保存</button>
        </div>
    </form>
</div>
