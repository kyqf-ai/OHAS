<?php
/**
 * 视图：原生 PHP 模板渲染。
 * View::render('workers/form', $data) → app/Views/workers/form.php
 * 模板在独立闭包作用域中执行，extract 后包含模板，返回输出缓冲。
 */
declare(strict_types=1);

namespace App\Core;

class View
{
    public static function render(string $tpl, array $data = []): string
    {
        $file = OHAS_ROOT . '/app/Views/' . $tpl . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('视图不存在：' . $tpl);
        }
        // 闭包作用域执行模板：模板变量与渲染器内部变量完全隔离，
        // 避免控制器传入的 data/rows 等键名与方法参数相互覆盖。
        return (static function (string $__file, array $__vars) {
            extract($__vars, EXTR_OVERWRITE);
            unset($__vars);
            ob_start();
            include $__file;
            return (string)ob_get_clean();
        })($file, $data);
    }
}
