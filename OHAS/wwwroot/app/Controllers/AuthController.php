<?php
/**
 * 登录 / 登出。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class AuthController extends Controller
{
    public function login(): void
    {
        if (Auth::check()) {
            $this->redirect('dashboard/index');
        }
        if ($this->isPost()) {
            $this->verifyCsrf();
            $username = (string)$this->post('username');
            $password = (string)$this->post('password');
            $result = Auth::attempt($username, $password);
            if ($result['ok']) {
                $this->flash('success', '欢迎回来，' . ($_SESSION['real_name'] ?: $_SESSION['username']) . '。');
                $this->redirect('dashboard/index');
            }
            $this->flash('danger', $result['msg']);
            $_SESSION['_old'] = ['username' => $username];
        }
        $this->viewRaw('auth/login', ['title' => '登录']);
    }

    /**
     * 登出：只接受 POST（带 CSRF 令牌），避免跨站页面或链接预取强制注销会话。
     */
    public function logout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->rejectMethod();
        }
        if (Auth::check()) {
            // 已登录会话核验令牌，防跨站伪造登出
            if (!$this->csrfValid()) {
                $this->flash('warning', '登出请求未通过校验，请重新操作。');
                $this->redirect('dashboard/index');
            }
            Auth::logout();
        }
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '已安全退出。'];
        header('Location: ' . url('auth/login'));
        exit;
    }
}
