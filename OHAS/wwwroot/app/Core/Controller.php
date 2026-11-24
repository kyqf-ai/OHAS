<?php
/**
 * 基础控制器：请求调度与响应。
 * POST 请求统一校验 CSRF 令牌；提供视图渲染、重定向、闪存等能力。
 */
declare(strict_types=1);

namespace App\Core;

use App\Models\OperationLog;

abstract class Controller
{
    /** 渲染应用布局页面 */
    protected function view(string $tpl, array $data = [], string $title = '', string $active = ''): void
    {
        $data['__tpl'] = $tpl;
        $data['__title'] = $title;
        $data['__active'] = $active;
        $content = View::render($tpl, $data);
        echo View::render('layouts/app', [
            'content'  => $content,
            'title'    => $title,
            'active'   => $active,
        ]);
        // 表单旧值与错误一次性使用
        unset($_SESSION['_old'], $_SESSION['_errors']);
    }

    /** 渲染独立版式页面（登录、安装、打印等） */
    protected function viewRaw(string $tpl, array $data = []): void
    {
        echo View::render($tpl, $data);
        unset($_SESSION['_old'], $_SESSION['_errors']);
    }

    protected function redirect(string $path, array $params = []): void
    {
        header('Location: ' . url($path, $params));
        exit;
    }

    /** 跳转到已生成的完整地址（如 DocLink::url 的结果） */
    protected function redirectUrl(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function back(): void
    {
        // Referer 仅在确认同源时才作为跳转目标，避免外部站点构成开放重定向；否则回退到工作台
        // 同源时只取「路径 + 查询串」重新拼接：即使 Referer 形如 http://本机//evil.com/x，
        // 也不会生成 //evil.com 这类协议相对地址（开放重定向）
        $target = url('dashboard/index');
        $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $p = $ref !== '' ? parse_url($ref) : false;
        $selfHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if (is_array($p) && isset($p['host']) && $selfHost !== '') {
            $host = strtolower((string)$p['host']);
            $refHost = $host . (isset($p['port']) ? ':' . $p['port'] : '');
            $selfName = (string)preg_replace('/:\d+$/', '', $selfHost);
            if ($refHost === $selfHost || $host === $selfName) {
                $path = '/' . ltrim((string)($p['path'] ?? '/'), "/\\");
                $target = $path . (isset($p['query']) ? '?' . $p['query'] : '');
            }
        }
        header('Location: ' . $target);
        exit;
    }

    /** 是否 AJAX 请求（前端 fetch 会带 X-Requested-With / Accept: application/json） */
    protected function isAjax(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || strpos($accept, 'application/json') !== false;
    }

    /**
     * 操作完成后的统一应答：AJAX → JSON；普通表单 → 闪存提示 + 跳转。
     * 让删除类端点无论被 fetch 还是原生表单调用，提示都不会丢。
     */
    protected function done(bool $ok, string $msg, string $path, array $params = []): void
    {
        if ($this->isAjax()) {
            $this->json(['ok' => $ok, 'msg' => $msg]);
        }
        $this->flash($ok ? 'success' : 'danger', $msg);
        $this->redirect($path, $params);
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ---------------- 输入 ---------------- */

    protected function input(string $key, $default = null)
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        if (is_array($v)) {
            return $default;   // 数组型参数（id[]=1 / name[]=x）一律视为缺省，避免 (int)['1']===1 与 "Array" 入库
        }
        return is_string($v) ? trim($v) : $v;
    }

    protected function post(string $key, $default = '')
    {
        $v = $_POST[$key] ?? $default;
        if (is_array($v)) {
            return $default;   // 需要数组请用 postArray()
        }
        return is_string($v) ? trim($v) : $v;
    }

    /** 原样读取 POST 字符串（不 trim，密码用）；数组型参数视为空串 */
    protected function postRaw(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? $v : '';
    }

    protected function postArray(string $key): array
    {
        $v = $_POST[$key] ?? [];
        return is_array($v) ? $v : [];
    }

    protected function get(string $key, $default = '')
    {
        $v = $_GET[$key] ?? $default;
        if (is_array($v)) {
            return $default;
        }
        return is_string($v) ? trim($v) : $v;
    }

    protected function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /* ---------------- CSRF ---------------- */

    /**
     * CSRF 校验：写操作端点统一调用。
     * 只接受 POST：GET 等其它方法直接拒绝，避免不读 POST 输入的端点被跨站触发。
     */
    protected function verifyCsrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->rejectMethod();
        }
        if (!$this->csrfValid()) {
            http_response_code(419);
            $title = '页面已过期';
            $hint  = '表单令牌校验未通过，请返回刷新页面后重新提交。';
            include OHAS_ROOT . '/app/Views/errors/error.php';
            exit;
        }
    }

    /**
     * 令牌比对。会话里没有令牌、或请求里没带令牌，一律判失败。
     * （原写法 hash_equals($_SESSION['_csrf'] ?? '', $_POST['_token'] ?? '') 在两侧都为空时返回 true，
     *   会话尚未生成令牌的请求可不带令牌直接通过校验。）
     */
    protected function csrfValid(): bool
    {
        $sess = $_SESSION['_csrf'] ?? '';
        $tok  = $_POST['_token'] ?? '';
        return is_string($sess) && $sess !== '' && is_string($tok) && $tok !== '' && hash_equals($sess, $tok);
    }

    /** 非 POST 访问写操作端点 → 405（该操作只接受表单提交） */
    protected function rejectMethod(): void
    {
        http_response_code(405);
        $title = '请求方式不允许';
        $hint  = '该操作只接受表单（POST）提交，请从页面按钮进入，不要直接访问地址。';
        include OHAS_ROOT . '/app/Views/errors/error.php';
        exit;
    }

    /* ---------------- 闪存与回填 ---------------- */

    protected function flash(string $type, string $msg): void
    {
        $_SESSION['_flash'] = ['type' => $type, 'msg' => $msg];
    }

    /** 校验失败 → 携带错误与旧值返回表单 */
    protected function failBack(string $to, array $errors, array $old = [], array $params = []): void
    {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = $old;
        $this->flash('danger', '提交内容有误，请按提示修正。');
        $this->redirect($to, $params);
    }

    /* ---------------- 日志 ---------------- */

    protected function log(string $action, string $objectType = '', $objectId = null, string $summary = ''): void
    {
        OperationLog::add($action, $objectType, $objectId, $summary, $_SERVER['REMOTE_ADDR'] ?? '-');
    }
}
