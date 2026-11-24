<?php
/**
 * 媒体服务：照片经鉴权读取（存储目录不暴露真实路径）。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Uploader;

class MediaController extends Controller
{
    public function photo(): void
    {
        $f = (string)$this->get('f');
        $abs = Uploader::absPath($f);
        if ($abs === null || !is_file($abs)) {
            http_response_code(404);
            exit;
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string)filesize($abs));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        readfile($abs);
        exit;
    }

    /** 报告原件（体检报告 / 批次报告等）：PDF 与图片，浏览器内联查看 */
    public function file(): void
    {
        $f = (string)$this->get('f');
        $abs = Uploader::absPath($f);
        if ($abs === null || !is_file($abs)) {
            http_response_code(404);
            $title = '文件不存在';
            $hint  = '报告文件可能已被替换或删除。';
            include OHAS_ROOT . '/app/Views/errors/error.php';
            exit;
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mimes = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
        if (!isset($mimes[$ext])) {
            http_response_code(403);
            exit;
        }
        header('Content-Type: ' . $mimes[$ext]);
        header('Content-Length: ' . (string)filesize($abs));
        header('Content-Disposition: inline; filename="OHAS-report.' . $ext . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($abs);
        exit;
    }
}
