<?php
/**
 * 路由器：将请求映射到控制器方法。
 * 兼容两种地址：
 *   PATH_INFO：/index.php/workers/edit?id=5
 *   查询参数：/index.php?r=workers/edit&id=5
 */
declare(strict_types=1);

namespace App\Core;

class Router
{
    /** @var array<string, class-string> 控制器别名（小写段名 → 类名） */
    private const CONTROLLERS = [
        'install'   => \App\Controllers\InstallController::class,
        'auth'      => \App\Controllers\AuthController::class,
        'dashboard' => \App\Controllers\DashboardController::class,
        'company'   => \App\Controllers\CompanyController::class,
        'hazards'   => \App\Controllers\HazardController::class,
        'workplaces' => \App\Controllers\WorkplaceController::class,
        'posts'     => \App\Controllers\PostController::class,
        'workers'   => \App\Controllers\WorkerController::class,
        'search'    => \App\Controllers\SearchController::class,
        'media'     => \App\Controllers\MediaController::class,
        'account'   => \App\Controllers\AccountController::class,
        'exams'     => \App\Controllers\ExamController::class,
        'patients'  => \App\Controllers\PatientController::class,
        'detections' => \App\Controllers\DetectionController::class,
        'standards' => \App\Controllers\StandardsController::class,
        'archive'   => \App\Controllers\ArchiveController::class,
        'ledgers'   => \App\Controllers\LedgerController::class,
        'prints'    => \App\Controllers\PrintController::class,
        'documents' => \App\Controllers\DocumentController::class,
        'system'    => \App\Controllers\SystemController::class,
        'users'     => \App\Controllers\UserController::class,
    ];

    /** 免登录白名单（控制器 => [方法, ...] 或 ['*']） */
    private const OPEN = [
        'install' => ['*'],
        'auth'    => ['login', 'logout'],
    ];

    public function dispatch(): void
    {
        // 整个调度（含会话校验、数据库打开与库迁移、控制器构造）都在 try 内，
        // 保证任何异常都有统一兜底，不会出现无提示的空白 500
        try {
            $this->run();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    private function run(): void
    {
        $segments = $this->resolve();
        $ctrlKey = $segments[0] ?? 'dashboard';
        $action  = $segments[1] ?? ($ctrlKey === 'dashboard' ? 'index' : 'index');

        // 控制器不存在 → 404
        if (!isset(self::CONTROLLERS[$ctrlKey])) {
            $this->abort404();
        }
        $class = self::CONTROLLERS[$ctrlKey];

        // 安装检测：未安装时一切请求导向安装向导
        $installed = self::isInstalled();
        if (!$installed && $ctrlKey !== 'install') {
            header('Location: ' . url('install/index'));
            exit;
        }
        if ($installed && $ctrlKey === 'install') {
            header('Location: ' . url('auth/login'));
            exit;
        }

        // 已登录会话：核对账号仍然有效（被停用即时掉线）
        if ($installed) {
            Auth::validateSession();
        }

        // 登录校验
        $open = self::OPEN[$ctrlKey] ?? [];
        if (!in_array('*', $open, true) && !in_array($action, $open, true) && !Auth::check()) {
            if ($this->wantsJson()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'msg' => '未登录或会话已过期'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            header('Location: ' . url('auth/login'));
            exit;
        }

        $controller = new $class();
        if (!$this->isAction($controller, $action)) {
            $this->abort404();
        }

        normalize_request_dates();
        $controller->$action();
    }

    /**
     * 该方法是否可作为 URL 动作：公开实例方法、非魔术/构造、无必填参数、非基类方法。
     * 静态辅助方法不纳入路由，避免被直接访问。
     */
    private function isAction(object $controller, string $action): bool
    {
        try {
            $ref = new \ReflectionMethod($controller, $action);
        } catch (\ReflectionException $e) {
            return false;
        }
        return $ref->isPublic() && !$ref->isStatic() && !$ref->isConstructor() && !$ref->isDestructor()
            && !str_starts_with($ref->getName(), '__')
            && $ref->getNumberOfRequiredParameters() === 0
            && $ref->getDeclaringClass()->getName() !== Controller::class;
    }

    /** 解析当前请求的控制器/方法 */
    private function resolve(): array
    {
        $path = $_SERVER['PATH_INFO'] ?? '';
        if ($path === '' && isset($_GET['r']) && is_string($_GET['r'])) {
            $path = '/' . ltrim((string)$_GET['r'], '/');
        }
        $path = trim($path ?? '', '/');
        if ($path === '') {
            return ['dashboard', 'index'];
        }
        $segments = array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));
        if (count($segments) > 2) {
            $this->abort404();   // 仅支持 控制器/方法 两级
        }
        return array_map('strtolower', $segments);
    }

    public static function isInstalled(): bool
    {
        return is_file(OHAS_ROOT . '/storage/installed.lock');
    }

    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || strpos($accept, 'application/json') !== false;
    }

    private function abort404(): void
    {
        http_response_code(404);
        $title = '404 页面不存在';
        $hint  = '请检查地址是否正确，或从左侧菜单重新进入。';
        include OHAS_ROOT . '/app/Views/errors/404.php';
        exit;
    }

    private function handleException(\Throwable $e): void
    {
        $msg = $e->getMessage();
        // 参考编号：页面与日志一一对应，管理员据此在 app-error.log 里定位完整堆栈
        $ref = strtoupper(bin2hex(random_bytes(3)));
        @error_log('[' . now_str() . '] #' . $ref . ' ' . get_class($e) . ': ' . $msg . ' @ ' . $e->getFile() . ':' . $e->getLine()
            . "\n" . $e->getTraceAsString() . "\n", 3, OHAS_ROOT . '/storage/logs/app-error.log');

        // SQLite 外键约束 → 引用保护提示
        if ($e instanceof \PDOException && stripos($msg, 'FOREIGN KEY') !== false) {
            http_response_code(500);
            $title = '删除受限';
            $hint  = '该记录已被业务数据引用（职业史、检测明细等），不能删除；如不再使用，可将其改为「停用」。';
            include OHAS_ROOT . '/app/Views/errors/error.php';
            exit;
        }
        http_response_code(500);
        $title = '系统开小差了';
        if ($e instanceof \PDOException) {
            // 数据库底层报错含 SQL / 表名 / 路径，不直接展示给页面
            $hint = '数据库操作失败，请稍后重试；若持续出现请联系管理员。（参考编号 ' . $ref . '，详见 storage/logs/app-error.log）';
        } else {
            $hint = '错误信息：' . mb_substr(str_replace(OHAS_ROOT, '.', $msg), 0, 300) . '（参考编号 ' . $ref . '）';
        }
        if (is_file(OHAS_ROOT . '/storage/debug.on')) {
            $hint .= "\n" . $msg . "\n" . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString();
        }
        include OHAS_ROOT . '/app/Views/errors/error.php';
        exit;
    }
}
