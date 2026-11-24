<?php
/**
 * 账号管理：企业内部多地点使用，3~5 个平级管理员账号。
 *
 * 设计取舍（按使用场景从简）：
 *  - 所有账号权限相同，不分角色，也不关联业务对象；
 *  - 账号只能停用、不能删除（操作日志按用户名留痕，删了就追溯不到人）；
 *  - 同时启用的账号数有上限（config: users.max_enabled，默认 5）；
 *  - 任何账号都可以为其他账号重置密码（同时解除登录锁定）；
 *  - 不能停用自己，因此永远至少保留一个可登录的账号。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class UserController extends Controller
{
    private function maxEnabled(): int
    {
        return max(1, (int)cfg('users.max_enabled', 5));
    }

    private function enabledCount(): int
    {
        return (int)Database::value("SELECT COUNT(*) FROM users WHERE status = '启用'");
    }

    public function index(): void
    {
        $rows = Database::rows(
            'SELECT id, username, real_name, status, last_login_at, locked_until, created_at
             FROM users ORDER BY id'
        );
        $this->view('users/index', [
            'rows'    => $rows,
            'enabled' => $this->enabledCount(),
            'max'     => $this->maxEnabled(),
            'meId'    => (int)($_SESSION['uid'] ?? 0),
        ], '账号管理', 'system_users');
    }

    public function create(): void
    {
        if ($this->isPost()) {
            $this->verifyCsrf();
            $username = strtolower((string)$this->post('username'));
            $realName = (string)$this->post('real_name');
            $pw       = $this->postRaw('password');
            $pw2      = $this->postRaw('password_confirm');

            $errors = [];
            if (!preg_match('/^[a-z0-9_]{3,20}$/', $username)) {
                $errors['username'] = '用户名为 3~20 位小写字母、数字或下划线。';
            } elseif (Database::value('SELECT COUNT(*) FROM users WHERE username = ? COLLATE NOCASE', [$username])) {
                $errors['username'] = '该用户名已存在。';
            }
            if ($realName === '' || mb_strlen($realName) > 20) {
                $errors['real_name'] = '姓名不能为空，且不超过 20 字。';
            }
            $errors += $this->passwordErrors($pw, $pw2, true, $username);
            if (!isset($errors['username']) && $this->enabledCount() >= $this->maxEnabled()) {
                $errors['username'] = '启用的账号已达上限（' . $this->maxEnabled() . ' 个），请先停用不再使用的账号。';
            }
            if ($errors) {
                $this->failBack('users/create', $errors, ['username' => $username, 'real_name' => $realName]);
            }

            // 启用数上限校验必须串行化，避免并发创建突破 max_enabled；事务收尾统一由 Database::immediate() 处理。
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $newId = (int)Database::immediate(function () use ($username, $hash, $realName): int {
                if ($this->enabledCount() >= $this->maxEnabled()) {
                    return 0;   // 已达上限（事务内只返回信号，退出/跳转放到事务结束之后）
                }
                Database::run(
                    "INSERT INTO users (username, password_hash, real_name, role, status, created_at, updated_at)
                     VALUES (?, ?, ?, 'admin', '启用', ?, ?)",
                    [$username, $hash, $realName, now_str(), now_str()]
                );
                return Database::lastId();
            });
            if ($newId === 0) {
                $this->failBack('users/create', ['username' => '启用的账号已达上限（' . $this->maxEnabled() . ' 个），请先停用不再使用的账号。'], ['username' => $username, 'real_name' => $realName]);
            }
            $this->log('create', 'user', $newId, '新增账号：' . $username . '（' . $realName . '）');
            $this->flash('success', '账号已创建：' . $username . '。请把初始密码当面告知使用人，并建议其登录后自行修改。');
            $this->redirect('users/index');
        }

        $this->view('users/form', ['user' => null, 'isEdit' => false], '新增账号', 'system_users');
    }

    public function edit(): void
    {
        $id = (int)$this->input('id');
        $user = Database::row('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            $this->flash('danger', '账号不存在。');
            $this->redirect('users/index');
        }

        if ($this->isPost()) {
            $this->verifyCsrf();
            $realName = (string)$this->post('real_name');
            $pw       = $this->postRaw('password');
            $pw2      = $this->postRaw('password_confirm');

            $errors = [];
            if ($realName === '' || mb_strlen($realName) > 20) {
                $errors['real_name'] = '姓名不能为空，且不超过 20 字。';
            }
            $errors += $this->passwordErrors($pw, $pw2, false, (string)$user['username']);
            if ($errors) {
                $this->failBack('users/edit', $errors, ['real_name' => $realName], ['id' => $id]);
            }

            if ($pw !== '') {
                // 重置密码同时解除登录锁定
                Database::run(
                    'UPDATE users SET real_name = ?, password_hash = ?, failed_attempts = 0, locked_until = NULL, updated_at = ? WHERE id = ?',
                    [$realName, password_hash($pw, PASSWORD_DEFAULT), now_str(), $id]
                );
            } else {
                Database::run('UPDATE users SET real_name = ?, updated_at = ? WHERE id = ?', [$realName, now_str(), $id]);
            }
            $this->log('update', 'user', $id, '修改账号：' . $user['username'] . ($pw !== '' ? '（已重置密码）' : ''));
            $this->flash('success', $pw !== '' ? '账号信息已保存，密码已重置。' : '账号信息已保存。');
            $this->redirect('users/index');
        }

        $this->view('users/form', ['user' => $user, 'isEdit' => true], '编辑账号', 'system_users');
    }

    /** 停用 / 启用（POST） */
    public function toggle(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $user = Database::row('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            $this->done(false, '账号不存在。', 'users/index');
        }
        if ($id === (int)($_SESSION['uid'] ?? 0)) {
            $this->done(false, '不能停用自己当前登录的账号。', 'users/index');
        }

        if (($user['status'] ?? '启用') === '启用') {
            Database::run("UPDATE users SET status = '停用', updated_at = ? WHERE id = ?", [now_str(), $id]);
            $this->log('update', 'user', $id, '停用账号：' . $user['username']);
            $this->done(true, '账号 ' . $user['username'] . ' 已停用，其已登录的会话将立即失效。', 'users/index');
        }

        // 启用数上限校验用 BEGIN IMMEDIATE 串行化，并发启用不再突破 max_enabled（见 create() 的说明）
        $enabled = (bool)Database::immediate(function () use ($id): bool {
            if ($this->enabledCount() >= $this->maxEnabled()) {
                return false;
            }
            Database::run(
                "UPDATE users SET status = '启用', failed_attempts = 0, locked_until = NULL, updated_at = ? WHERE id = ?",
                [now_str(), $id]
            );
            return true;
        });
        if (!$enabled) {
            $this->done(false, '启用的账号已达上限（' . $this->maxEnabled() . ' 个），请先停用其他账号。', 'users/index');
        }
        $this->log('update', 'user', $id, '启用账号：' . $user['username']);
        $this->done(true, '账号 ' . $user['username'] . ' 已启用。', 'users/index');
    }

    /** 密码校验：新增必填；编辑时留空表示不修改 */
    private function passwordErrors(string $pw, string $pw2, bool $required, string $username = ''): array
    {
        $errs = [];
        if ($pw === '' && !$required) {
            return $errs;
        }
        if (mb_strlen($pw) < 8) {
            $errs['password'] = '密码长度不能少于 8 位。';
        } elseif (strlen($pw) > 72) {
            $errs['password'] = '密码过长（最多 72 字节，约 24 个汉字）。';   // bcrypt 只识别前 72 字节，超出部分会被静默忽略
        } elseif ($username !== '' && strtolower($pw) === strtolower($username)) {
            $errs['password'] = '密码不能与用户名相同。';
        }
        if ($pw !== $pw2) {
            $errs['password_confirm'] = '两次输入的密码不一致。';
        }
        return $errs;
    }
}
