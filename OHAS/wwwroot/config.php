<?php
/**
 * 全局配置：站点信息 / 数据库 / 会话 / 分页 / 上传限制。
 * 修改后无需重启服务，下一次请求即生效。
 */
declare(strict_types=1);

return [
    'app' => [
        'name'     => '职业健康档案管理系统',
        'version'  => '1.0.0beta',
        // 库结构版本单一来源为 App\Core\Migrator::VERSION

        // 地址模式：path = /index.php/workers/edit（Apache/phpStudy 默认支持 PATH_INFO）
        //           query = /index.php?r=workers/edit（nginx 未透传 PATH_INFO 时改用此模式）
        'url_mode'       => 'path',
        'timezone'       => 'Asia/Shanghai',
    ],

    'db' => [
        // SQLite 数据库文件（位于 Web 根之外，需 storage 目录可写）。
        // 环境变量 OHAS_DB 可覆盖（自测/多实例用）：php -r / tools 使用同一份代码指向临时库
        'path' => getenv('OHAS_DB') !== false && getenv('OHAS_DB') !== ''
            ? getenv('OHAS_DB')
            : __DIR__ . '/storage/ohas.db',
    ],

    'session' => [
        'name'     => 'OHASSESS',
        'lifetime' => 7200,   // 闲置自动登出（秒），当前 2 小时
    ],

    'users' => [
        'max_enabled' => 5,   // 同时启用的账号上限（平级管理员，不分角色）
    ],

    'page' => [
        'size' => 10,         // 列表每页条数
    ],

    'expiry' => [
        'soon_days' => 30,    // 「即将到期」阈值（天）：工作台与资料清单共用，避免两处口径漂移
    ],

    'upload' => [
        'photo_exts'      => ['jpg', 'jpeg', 'png'],
        'photo_max_bytes' => 2 * 1024 * 1024,   // 照片 ≤ 2MB
        // doc_* 由控制器读取，收紧配置即可即时生效
        'doc_exts'        => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'],
        'doc_max_bytes'   => 20 * 1024 * 1024,  // 体检/检测报告原件 ≤ 20MB
    ],
];
