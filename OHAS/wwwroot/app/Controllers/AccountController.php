<?php
/**
 * 账户：修改密码；操作日志与数据备份见系统管理（system）模块。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class AccountController extends Controller
{
    public function password(): void
    {
        if ($this->isPost()) {
            $this->verifyCsrf();
            $old = (string)$this->post('old_password');
            $new = (string)$this->post('new_password');
            $confirm = (string)$this->post('new_password_confirm');
            $user = Database::row('SELECT * FROM users WHERE id = ?', [(int)$_SESSION['uid']]);
            $errors = [];
            if (!$user || !password_verify($old, $user['password_hash'])) {
                $errors['old_password'] = '原密码不正确。';
            }
            if (mb_strlen($new) < 8) {
                $errors['new_password'] = '新密码长度不能少于 8 位。';
            } elseif (strlen($new) > 72) {
                $errors['new_password'] = '新密码过长（最多 72 字节，约 24 个汉字）。';
            }
            if ($new !== $confirm) {
                $errors['new_password_confirm'] = '两次输入的新密码不一致。';
            }
            if ($errors) {
                $this->failBack('account/password', $errors);
            }
            Database::run(
                'UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?',
                [password_hash($new, PASSWORD_DEFAULT), now_str(), (int)$_SESSION['uid']]
            );
            $this->log('update', 'user', (int)$_SESSION['uid'], '修改登录密码');
            $this->flash('success', '密码已修改，下次登录请使用新密码。');
            $this->redirect('account/password');
        }
        $this->view('account/password', [], '修改密码', 'account');
    }
}
