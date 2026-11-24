<?php
$full = $enabled >= $max;
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="text-secondary">
        所有账号权限相同（平级管理员）。启用中 <strong><?= (int)$enabled ?></strong> / <?= (int)$max ?> 个；账号只能停用、不能删除，以保证操作日志可追溯到人。
    </div>
    <div class="d-flex gap-2">
        <a class="btn" href="<?= url('system/logs') ?>"><i class="ti ti-history me-1"></i>操作日志</a>
        <?php if ($full): ?>
        <span class="btn btn-primary disabled" title="启用账号已达上限，请先停用不再使用的账号"><i class="ti ti-user-plus me-1"></i>新增账号</span>
        <?php else: ?>
        <a class="btn btn-primary" href="<?= url('users/create') ?>"><i class="ti ti-user-plus me-1"></i>新增账号</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>用户名</th>
                    <th>姓名</th>
                    <th>状态</th>
                    <th>最近登录</th>
                    <th class="w-1">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                $isOn   = ($r['status'] ?? '启用') === '启用';
                $isMe   = (int)$r['id'] === $meId;
                $locked = !empty($r['locked_until']) && $r['locked_until'] > now_str();
                ?>
                <tr>
                    <td>
                        <code><?= e($r['username']) ?></code>
                        <?php if ($isMe): ?><span class="badge bg-blue-lt ms-1">当前登录</span><?php endif; ?>
                    </td>
                    <td><?= e($r['real_name']) ?></td>
                    <td>
                        <?php if ($isOn): ?><span class="badge bg-green-lt">启用</span><?php else: ?><span class="badge bg-secondary-lt">停用</span><?php endif; ?>
                        <?php if ($locked): ?><span class="badge bg-orange-lt ms-1" title="密码连续输错，锁定至 <?= e($r['locked_until']) ?>；重置密码可立即解除">已锁定</span><?php endif; ?>
                    </td>
                    <td class="text-secondary"><?= e($r['last_login_at'] ?: '从未登录') ?></td>
                    <td class="text-nowrap">
                        <a class="me-2" href="<?= url('users/edit', ['id' => (int)$r['id']]) ?>"><i class="ti ti-pencil me-1"></i>编辑 / 重置密码</a>
                        <?php if (!$isMe): ?>
                        <form method="post" action="<?= url('users/toggle') ?>" class="d-inline"
                              onsubmit="return confirm('<?= $isOn ? '确认停用该账号？其已登录的会话将立即失效。' : '确认启用该账号？' ?>');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-link p-0 align-baseline <?= $isOn ? 'text-danger' : '' ?>">
                                <i class="ti <?= $isOn ? 'ti-user-off' : 'ti-user-check' ?> me-1"></i><?= $isOn ? '停用' : '启用' ?>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="text-secondary mt-3 small">
    提示：新建账号后请把初始密码当面告知使用人，并让其用右上角头像菜单里的“修改密码”自行更换。上限可在 <code>config.php</code> 的 <code>users.max_enabled</code> 调整。
</div>
