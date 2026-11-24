<?php
/**
 * 应用引导：配置加载、类自动加载、会话、时区
 * 所有请求经 public/index.php 进入本文件。
 */
declare(strict_types=1);

define('OHAS_ROOT', dirname(__DIR__));
define('APP_START', microtime(true));

$config = require OHAS_ROOT . '/config.php';
$GLOBALS['__config'] = $config;

date_default_timezone_set($config['app']['timezone']);

/* ---------- 错误处理：内网工具系统，页面报错便于定位 ---------- */
$debug = is_file(OHAS_ROOT . '/storage/debug.on');
ini_set('display_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);
// 无论是否调试都写日志，保证生产环境错误可追踪
ini_set('log_errors', '1');
ini_set('error_log', OHAS_ROOT . '/storage/logs/php-error.log');

/* 兜底：Router 之外（引导 / 会话 / 库迁移）抛出的未捕获异常，写日志并给出 500 页，而不是空白页 */
set_exception_handler(function (\Throwable $e) use ($debug): void {
    @error_log('[' . date('Y-m-d H:i:s') . '] 未捕获 ' . get_class($e) . ': ' . $e->getMessage()
        . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() . "\n", 3, OHAS_ROOT . '/storage/logs/app-error.log');
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $msg = $debug ? $e->getMessage() : '请稍后重试；若持续出现，请联系管理员并查看 storage/logs/app-error.log。';
    echo '<!doctype html><meta charset="utf-8"><title>系统错误</title>'
        . '<body style="font-family:sans-serif;padding:2rem"><h3>系统开小差了</h3><p>'
        . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p></body>';
});

/* ---------- PSR-4 风格自动加载：App\ → app/ ---------- */
spl_autoload_register(function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = OHAS_ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/* ---------- 通用助手函数（视图与控制器共用） ---------- */

/** 读取配置：cfg('db.path') */
function cfg(string $dot, $default = null)
{
    $val = $GLOBALS['__config'];
    foreach (explode('.', $dot) as $seg) {
        if (!is_array($val) || !array_key_exists($seg, $val)) {
            return $default;
        }
        $val = $val[$seg];
    }
    return $val;
}

/**
 * 请求日期统一补零：参数名以 _date / _on / _at 结尾（及 deadline、as_of）且值为合法的 Y-n-j 时，规整为 YYYY-MM-DD。
 * 系统按字符串比较/排序日期，"2019-1-10" < "2019-1-5" 会让区间校验与排序出错。
 */
function normalize_request_dates(): void
{
    $walk = function (array &$arr) use (&$walk): void {
        foreach ($arr as $k => &$v) {
            if (is_array($v)) {
                $walk($v);
            } elseif (is_string($v) && is_string($k) && preg_match('/(_date|_on|_at)$|^(deadline|as_of)$/', $k)) {
                $v = \App\Core\Validator::normalizeDate($v);
            }
        }
    };
    $walk($_GET);
    $walk($_POST);
}

/** HTML 输出转义（XSS 防护） */
function e($value): string
{
    if (is_array($value)) {
        return '';   // 数组型参数（?q[]=x）不当字符串输出，避免 "Array" 与 Warning
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** 生成链接：url('workers/edit', ['id' => 5]) */
function url(string $path = '', array $params = []): string
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/';
    $mode = cfg('app.url_mode', 'path');
    if ($path === '' || $path === '/') {
        $href = $base . 'index.php' . ($mode === 'path' ? '' : '');
    } elseif ($mode === 'path') {
        $href = $base . 'index.php/' . ltrim($path, '/');
    } else {
        $href = $base . 'index.php?r=' . $path;
    }
    if ($params) {
        $href .= (strpos($href, '?') === false ? '?' : '&') . http_build_query($params);
    }
    return $href;
}

/** 静态资源地址：asset('assets/app.css') */
function asset(string $res): string
{
    // 应用根入口部署时，资源实际在 public/ 下，由根 index.php 指定基准；
    // 标准部署（站点根指向 public/）时退化为 SCRIPT_NAME 所在目录。
    $base = $_SERVER['OHAS_ASSET_BASE'] ?? (rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/');
    return rtrim($base, '/') . '/' . ltrim($res, '/');
}

/** 表单旧值回填 */
function old(string $key, $default = '')
{
    return e($_SESSION['_old'][$key] ?? $default);
}

/** 字段级错误提示 */
function form_error(string $key): ?string
{
    return $_SESSION['_errors'][$key] ?? null;
}

/** 全部错误清单 */
function form_errors(): array
{
    return $_SESSION['_errors'] ?? [];
}

/** 会话一次性提示（读后即焚） */
function flash_pull(): ?array
{
    if (!empty($_SESSION['_flash'])) {
        $f = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $f;
    }
    return null;
}

/** 日期展示：2026-01-02 → 2026-01-02；空值 → — */
function d(?string $date): string
{
    return ($date === null || $date === '') ? '—' : $date;
}

/** 身份证号脱敏：保留前6后4（列表页展示用） */
function mask_id_card(?string $id): string
{
    if ($id === null || $id === '') {
        return '—';
    }
    $len = mb_strlen($id, 'UTF-8');
    return mb_substr($id, 0, 6, 'UTF-8') . str_repeat('*', max(0, $len - 10)) . ($len > 10 ? mb_substr($id, -4, null, 'UTF-8') : '');
}

/** 当前时间戳（文本，到秒） */
function now_str(): string
{
    return date('Y-m-d H:i:s');
}

/** CSRF 令牌（会话级） */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** CSRF 隐藏域（所有 POST 表单必须包含） */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/* ---------- 通用安全响应头（个人健康 / 身份信息页面不被嵌入他站、不被嗅探为其它类型） ---------- */
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
}

/* ---------- 会话启动（HttpOnly + SameSite，登录后防固定） ---------- */
$sessionName = $config['session']['name'];
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($sessionName);
    // 会话文件独立存放并与「闲置时限」对齐：
    //  - PHP 默认 session.gc_maxlifetime 仅 1440 秒（24 分钟），配置的 2 小时闲置时限会被服务器提前回收；
    //  - Debian/Ubuntu 默认 gc_probability=0（靠 cron 清理默认目录），自定义目录需显式开启概率回收；
    //  - strict_mode 拒绝客户端自拟的会话 ID（防固定）。
    $sessDir = OHAS_ROOT . '/storage/sessions';
    if (!is_dir($sessDir)) {
        @mkdir($sessDir, 0700, true);
    }
    if (is_dir($sessDir) && is_writable($sessDir)) {
        session_save_path($sessDir);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
    }
    ini_set('session.gc_maxlifetime', (string)max((int)$config['session']['lifetime'], 1440));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $isHttps,   // HTTPS（含反向代理）下 Cookie 仅走加密通道
    ]);
    session_start();
}

/* ---------- 闲置超时自动登出 ---------- */
$life = (int)$config['session']['lifetime'];
if (isset($_SESSION['uid'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $life) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['_flash'] = ['type' => 'warning', 'msg' => '长时间未操作，已自动登出，请重新登录。'];
    }
    $_SESSION['last_activity'] = time();
}

require OHAS_ROOT . '/app/Core/Router.php';
