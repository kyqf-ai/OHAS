<?php
/**
 * 安装向导（首次部署）：创建数据库、设置管理员密码、录入单位信息、初始化危害因素字典。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Hazard;

class InstallController extends Controller
{
    public function index(): void
    {
        if ($this->isPost()) {
            $this->verifyCsrf();
            $this->install();
        }
        $this->viewRaw('install/index', [
            'title' => '系统安装',
            'company' => ['name' => ''],
        ]);
    }

    private function install(): void
    {
        $password  = $this->postRaw('password');
        $confirm   = $this->postRaw('password_confirm');
        $seedDict  = isset($_POST['seed_dict']);
        $companyName = trim($this->postRaw('company_name'));

        $errors = [];
        if (mb_strlen($password) < 8) {
            $errors['password'] = '密码长度不能少于 8 位。';
        } elseif (strlen($password) > 72) {
            $errors['password'] = '密码过长（最多 72 字节，约 24 个汉字）。';
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = '两次输入的密码不一致。';
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = ['company_name' => $companyName];
            $this->flash('danger', '请按提示修正后重试。');
            $this->redirect('install/index');
        }

        try {
            // 1) 建库
            $db = Database::pdo();
            $db->exec(file_get_contents(OHAS_ROOT . '/database/schema.sql'));

            // 2)~4) 管理员 / 单位信息 / 危害因素字典：整体事务，
            //     中途失败全部回滚，重试安装不会撞 admin 唯一约束而卡死
            $count = 0;
            Database::tx(function () use ($db, $password, $companyName, $seedDict, &$count): void {
                // 2) 管理员（幂等：重试时已存在则跳过）
                Database::run(
                    "INSERT OR IGNORE INTO users (username, password_hash, real_name, role, created_at, updated_at)
                     VALUES ('admin', ?, '管理员', 'admin', ?, ?)",
                    [password_hash($password, PASSWORD_DEFAULT), now_str(), now_str()]
                );

                // 3) 单位信息（幂等）
                Database::run(
                    'INSERT INTO company (name, created_at, updated_at) SELECT ?,?,? WHERE NOT EXISTS (SELECT 1 FROM company)',
                    [$companyName, now_str(), now_str()]
                );

                // 4) 危害因素字典：内置《职业病危害因素分类目录》全部 459 条
                //    （含目录序号/CAS/适用范围，及常用因素的可能职业病与接触限值补充）
                if ($seedDict) {
                    $r = Hazard::syncCatalog();
                    $count = $r['inserted'] + $r['updated'];
                }
            });

            // 4.5) 标记库版本：新库已是最新结构与目录，免去首次登录时重复迁移
            \App\Core\Migrator::ensureDocUnlinkTriggers($db);   // 建库脚本不含文档解除关联触发器
            $db->exec('PRAGMA user_version = ' . \App\Core\Migrator::VERSION);

            // 5) 写安装锁（库结构版本取 Migrator::VERSION）
            file_put_contents(
                OHAS_ROOT . '/storage/installed.lock',
                'installed_at=' . now_str() . "\nversion=" . cfg('app.version') . "\nschema_version=" . \App\Core\Migrator::VERSION . "\n"
            );
            \App\Models\OperationLog::add('install', 'system', null, "系统安装完成，内置职业病危害因素分类目录 {$count} 条", $_SERVER['REMOTE_ADDR'] ?? '-');
        } catch (\Throwable $e) {
            $_SESSION['_errors'] = ['password' => '安装失败：' . mb_substr($e->getMessage(), 0, 200)];
            $this->redirect('install/index');
        }

        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '安装完成，请使用 admin 与刚设置的密码登录。'];
        header('Location: ' . url('auth/login'));
        exit;
    }
}
