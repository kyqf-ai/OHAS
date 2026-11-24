<?php
/**
 * 认证：单管理员登录、锁定与登出。
 * 安全基线：password_hash(bcrypt) 存储；5 次失败锁定 5 分钟；登录成功重建会话。
 */
declare(strict_types=1);

namespace App\Core;

use App\Models\OperationLog;

class Auth
{
    private static ?string $dummy = null;

    /** 仅用于计时对齐的占位哈希（进程内惰性生成一次） */
    private static function dummyHash(): string
    {
        return self::$dummy ??= password_hash('ohas-timing-dummy', PASSWORD_DEFAULT);
    }

    public static function check(): bool
    {
        return !empty($_SESSION['uid']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return [
            'id'       => $_SESSION['uid'],
            'username' => $_SESSION['username'] ?? '',
            'real_name' => $_SESSION['real_name'] ?? '',
        ];
    }

    /**
     * 尝试登录。
     * @return array{ok:bool,msg:string}
     */
    public static function attempt(string $username, string $password): array
    {
        $user = Database::row('SELECT * FROM users WHERE username = ? COLLATE NOCASE', [$username]);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '-';

        if ($user === null) {
            // 抹平「用户不存在」与「密码错误」的响应耗时差，避免通过计时探测有效用户名
            password_verify($password, self::dummyHash());
            OperationLog::add('login_fail', '', null, '尝试登录，用户名不存在：' . $username, $ip);
            return ['ok' => false, 'msg' => '用户名或密码错误。'];
        }

        // 锁定检查
        if ($user['locked_until'] !== null && $user['locked_until'] > now_str()) {
            $left = strtotime($user['locked_until']) - time();
            $mins = max(1, (int)ceil($left / 60));
            OperationLog::add('login_fail', '', (int)$user['id'], '锁定期间尝试登录', $ip);
            return ['ok' => false, 'msg' => '连续失败次数过多，账号已锁定，请约 ' . $mins . ' 分钟后再试。'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            // 原子自增再读回：并发爆破时「读-改-写」不会丢计数
            Database::run('UPDATE users SET failed_attempts = failed_attempts + 1, updated_at = ? WHERE id = ?', [now_str(), $user['id']]);
            $attempts = (int)Database::value('SELECT failed_attempts FROM users WHERE id = ?', [$user['id']]);
            if ($attempts >= 5) {
                Database::run(
                    'UPDATE users SET failed_attempts = 0, locked_until = ?, updated_at = ? WHERE id = ?',
                    [date('Y-m-d H:i:s', time() + 300), now_str(), $user['id']]
                );
                OperationLog::add('login_fail', '', (int)$user['id'], '连续5次密码错误，账号锁定5分钟', $ip);
                return ['ok' => false, 'msg' => '密码连续错误5次，账号已锁定5分钟。'];
            }
            OperationLog::add('login_fail', '', (int)$user['id'], '密码错误（第' . $attempts . '次）', $ip);
            return ['ok' => false, 'msg' => '用户名或密码错误。'];
        }

        // 密码正确但账号已停用：拒绝登录（不清零失败计数，也不建会话）
        if (($user['status'] ?? '启用') === '停用') {
            OperationLog::add('login_fail', '', (int)$user['id'], '已停用账号尝试登录：' . $user['username'], $ip);
            return ['ok' => false, 'msg' => '该账号已停用，请联系其他管理员启用。'];
        }

        // 哈希算法 / 成本参数升级后，登录成功时顺带按新参数重新哈希
        if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        // 登录成功：清零、记录、重建会话（防会话固定）
        Database::run(
            'UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = ?, updated_at = ? WHERE id = ?',
            [now_str(), now_str(), $user['id']]
        );
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['real_name'] = $user['real_name'];
        $_SESSION['last_activity'] = time();
        OperationLog::add('login', '', (int)$user['id'], '管理员登录', $ip);
        return ['ok' => true, 'msg' => ''];
    }

    /**
     * 每次请求校验已登录会话：账号被停用或删除后立即失效，并同步姓名等显示信息。
     * 仅一次主键查询，3~5 个账号的内部使用场景开销可忽略。
     */
    public static function validateSession(): void
    {
        if (!self::check()) {
            return;
        }
        $u = Database::row('SELECT username, real_name, status FROM users WHERE id = ?', [(int)$_SESSION['uid']]);
        if ($u === null || ($u['status'] ?? '启用') === '停用') {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_flash'] = ['type' => 'warning', 'msg' => '账号已被停用或不存在，请联系其他管理员。'];
            return;
        }
        $_SESSION['username'] = $u['username'];
        $_SESSION['real_name'] = $u['real_name'];
    }

    public static function logout(): void
    {
        if (self::check()) {
            OperationLog::add('logout', '', (int)$_SESSION['uid'], '管理员登出', $_SERVER['REMOTE_ADDR'] ?? '-');
        }
        $_SESSION = [];
        session_regenerate_id(true);
    }
}
