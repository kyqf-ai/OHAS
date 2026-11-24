<?php
/**
 * 系统管理：操作日志查询与数据备份（数据库 + 上传文件）。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class SystemController extends Controller
{
    private const ACTIONS = ['login', 'login_fail', 'logout', 'create', 'update', 'delete', 'import', 'upload', 'download', 'export', 'view', 'print', 'install'];

    public function logs(): void
    {
        $q = (string)$this->get('q');
        $action = (string)$this->get('action');

        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where .= ' AND (username LIKE ? OR summary LIKE ? OR object_type LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($action !== '' && in_array($action, self::ACTIONS, true)) {
            $where .= ' AND action = ?';
            $params[] = $action;
        }

        $size = 20;
        $total = (int)Database::value('SELECT COUNT(*) FROM logs WHERE ' . $where, $params);
        $pages = max(1, (int)ceil($total / $size));
        $page = min(max(1, (int)$this->get('page', 1)), $pages);
        $offset = ($page - 1) * $size;
        $rows = Database::rows(
            'SELECT * FROM logs WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . $size . ' OFFSET ' . $offset,
            $params
        );

        $this->view('system/logs', [
            'rows'    => $rows,
            'q'       => $q,
            'action'  => $action,
            'actions' => self::ACTIONS,
            'data'    => ['total' => $total, 'page' => $page, 'pages' => $pages],
        ], '操作日志', 'system_logs');
    }

    public function backup(): void
    {
        // 上传文件占用（体积与文件数，含 documents 子目录）
        $uploads = OHAS_ROOT . '/storage/uploads';
        $upBytes = 0;
        $upFiles = 0;
        if (is_dir($uploads)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($uploads, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $upBytes += $f->getSize();
                    $upFiles++;
                }
            }
        }
        $this->view('system/backup', [
            'files' => $this->backupFiles(),
            'hasZip' => class_exists('ZipArchive'),
            'upBytes' => $upBytes,
            'upFiles' => $upFiles,
        ], '数据备份', 'system_backup');
    }

    public function backupCreate(): void
    {
        $this->verifyCsrf();
        @set_time_limit(0);   // 上传文件多时压缩耗时较长，避免被默认 30 秒执行时限截断
        $dir = $this->backupDir();
        $dbPath = (string)cfg('db.path');
        if (!is_file($dbPath)) {
            $this->flash('danger', '数据库文件不存在，备份失败。');
            $this->redirect('system/backup');
        }
        $hasZip = class_exists('ZipArchive');
        $name = 'ohas-' . date('Ymd-His') . ($hasZip ? '.zip' : '.db');
        $target = $dir . '/' . $name;
        $uploads = OHAS_ROOT . '/storage/uploads';

        // 一致性快照：VACUUM INTO 在单个读事务内生成完整副本。
        // 原写法 checkpoint 后直接压缩 ohas.db，压缩期间其他请求仍可写库，会得到「半新半旧」的文件；
        // 不支持 VACUUM INTO（SQLite < 3.27）时退回 checkpoint + 直接使用原文件
        $snap = $dir . '/.snapshot-' . bin2hex(random_bytes(4)) . '.db';
        $dbSource = $dbPath;
        try {
            Database::pdo()->exec("VACUUM INTO '" . str_replace("'", "''", $snap) . "'");
            if (is_file($snap) && filesize($snap) > 0) {
                $dbSource = $snap;
            }
        } catch (\Throwable $e) {
            @unlink($snap);
            try {
                Database::pdo()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            } catch (\Throwable $e2) {
                // 尽力而为
            }
        }

        $ok = false;
        $why = '';
        try {
            // 磁盘空间预检（压缩率按 1:1 保守估算——PDF / JPG 基本压不动）
            $need = (int)@filesize($dbSource);
            if ($hasZip && is_dir($uploads)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uploads, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    if ($f->isFile()) {
                        $need += $f->getSize();
                    }
                }
            }
            $free = @disk_free_space($dir);
            if ($free !== false && $free < $need * 1.05 + 1048576) {
                $why = '磁盘剩余空间不足（预计需要约 ' . round($need / 1048576, 1) . ' MB）。';
            } elseif ($hasZip) {
                $ok = $this->buildBackupZip($target, $dbSource, $uploads);
                $why = '压缩包写入未完成（请检查磁盘空间与 storage/backups 权限）。';
            } else {
                $ok = @copy($dbSource, $target);
                $why = '备份文件写入失败，请检查存储目录权限。';
            }
        } finally {
            @unlink($snap);
        }

        if (!$ok) {
            @unlink($target);   // 不留半截文件，也不能提示“备份已创建”
            $this->flash('danger', '备份失败：' . $why);
            $this->redirect('system/backup');
        }

        $this->log('create', 'backup', null, '创建数据备份：' . $name);
        $this->flash('success', '备份已创建：' . $name . '（含数据库' . ($hasZip ? '与上传文件' : '') . '）');
        $this->redirect('system/backup');
    }

    /**
     * 生成备份压缩包：数据库快照 + storage/uploads 全部文件。
     * ZipArchive 在 close() 之前会一直占用每个已添加文件的句柄，上传文件上千份时会撞系统句柄上限
     * （常见 1024）而整体失败，所以每添加 200 个文件就落盘一次再续写。
     */
    private function buildBackupZip(string $target, string $dbSource, string $uploads): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        $zip->addFile($dbSource, 'ohas.db');
        $n = 0;
        if (is_dir($uploads)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uploads, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($uploads) + 1));
                $zip->addFile($file->getPathname(), 'uploads/' . $rel);
                if (++$n % 200 === 0) {
                    if (!$zip->close() || $zip->open($target) !== true) {
                        return false;
                    }
                }
            }
        }
        if (!$zip->close()) {
            return false;
        }
        return is_file($target) && filesize($target) > 0;
    }

    public function backupDownload(): void
    {
        $name = basename((string)$this->get('file'));
        if (!preg_match('/^ohas-\d{8}-\d{6}\.(zip|db)$/', $name)) {
            http_response_code(404);
            exit;
        }
        $abs = $this->backupDir() . '/' . $name;
        if (!is_file($abs)) {
            http_response_code(404);
            exit;
        }
        // 备份含全库身份证号 / 健康数据：下载动作必须留痕
        $this->log('download', 'backup', null, '下载数据备份：' . $name);
        header('Content-Type: application/octet-stream');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . (string)filesize($abs));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        readfile($abs);
        exit;
    }

    public function backupDelete(): void
    {
        $this->verifyCsrf();
        $name = basename((string)$this->post('file'));
        if (preg_match('/^ohas-\d{8}-\d{6}\.(zip|db)$/', $name)) {
            $abs = $this->backupDir() . '/' . $name;
            if (is_file($abs)) {
                @unlink($abs);
                $this->log('delete', 'backup', null, '删除数据备份：' . $name);
                $this->flash('success', '备份已删除。');
            }
        }
        $this->redirect('system/backup');
    }

    private function backupDir(): string
    {
        $dir = OHAS_ROOT . '/storage/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function backupFiles(): array
    {
        $dir = $this->backupDir();
        $out = [];
        foreach (glob($dir . '/ohas-*') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $out[] = [
                'name' => basename($path),
                'size' => filesize($path),
                'time' => date('Y-m-d H:i:s', (int)filemtime($path)),
            ];
        }
        usort($out, fn ($a, $b) => strcmp($b['name'], $a['name']));
        return $out;
    }
}
