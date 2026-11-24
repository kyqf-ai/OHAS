<?php
/**
 * 文件上传：白名单 + MIME 复核 + 随机重命名 + 大小限制。
 * 存储目录位于 Web 根之外（storage/uploads），经控制器鉴权读取。
 */
declare(strict_types=1);

namespace App\Helpers;

class Uploader
{
    /**
     * 扩展名 → 允许的真实 MIME。
     * 图片 / PDF 的内容必须与扩展名一致（防止把 docx、zip、html 改名成 .pdf 传入）；
     * fileinfo 无法识别（octet-stream）时放行，由下游按扩展名固定 Content-Type 并加 nosniff。
     */
    private const STRICT_EXT_MIMES = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
    ];

    private const ERR_MSG = [
        UPLOAD_ERR_INI_SIZE   => '文件超过服务器允许的上传上限（php.ini 的 upload_max_filesize / post_max_size）。',
        UPLOAD_ERR_FORM_SIZE  => '文件超过表单限制的大小。',
        UPLOAD_ERR_PARTIAL    => '文件只上传了一部分，请重试。',
        UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录，请联系管理员。',
        UPLOAD_ERR_CANT_WRITE => '服务器写入失败，请检查磁盘空间与目录权限。',
        UPLOAD_ERR_EXTENSION  => '上传被服务器扩展拦截。',
    ];

    /** 照片压缩的像素上限：超出则不解码（解压炸弹会在 GD 解码时吃光内存），保留原图 */
    private const SHRINK_MAX_PIXELS = 36000000;

    /**
     * 校验并保存上传文件。
     * @param array $file $_FILES 单项
     * @param string $subDir 存储子目录，如 photos、documents
     * @param array $exts 允许的扩展名（小写）
     * @param int $maxBytes 大小上限
     * @param array $mimes 允许的 MIME 前缀或完整值（可空 = 跳过复核）
     * @return array{ok:bool,msg:string,path:string}
     *         path 成功时为相对存储名（如 photos/202601/a1b2c3.jpg）
     */
    public static function save(array $file, string $subDir, array $exts, int $maxBytes, array $mimes = []): array
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        // 数组型字段（file[] / 被篡改的参数）：拒绝，避免后面字符串拼接出现 "Array" 与 Warning
        if (is_array($err) || is_array($file['name'] ?? '') || is_array($file['tmp_name'] ?? '') || is_array($file['size'] ?? 0)) {
            return self::fail('上传参数不正确，请使用页面上的上传按钮。');
        }
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'msg' => '', 'path' => ''];   // 未选择文件视为空成功
        }
        $err = (int)$err;
        if ($err !== UPLOAD_ERR_OK) {
            return self::fail(self::ERR_MSG[$err] ?? ('上传失败（错误码 ' . $err . '），请重试。'));
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            return self::fail('上传的临时文件丢失，请重试。');
        }
        // 同时核对 PHP 报告的大小与磁盘上的实际大小，取较大者
        $size = max((int)($file['size'] ?? 0), (int)@filesize($tmp));
        if ($size <= 0) {
            return self::fail('文件为空（0 字节），请重新选择。');
        }
        if ($size > $maxBytes) {
            $mb = round($maxBytes / 1048576, 1);
            return self::fail("文件超过 {$mb}MB 大小限制。");
        }
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $exts, true)) {
            return self::fail('文件类型不允许，仅支持：' . implode(' / ', $exts) . '。');
        }

        // MIME 复核（fileinfo）
        if (function_exists('finfo_open') && $mimes) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $fi ? (string)finfo_file($fi, $tmp) : '';
            if ($fi) {
                finfo_close($fi);
            }
            $pass = false;
            foreach ($mimes as $m) {
                if ($mime === $m || ($m !== '' && str_ends_with($m, '*') && str_starts_with($mime, rtrim($m, '*')))) {
                    $pass = true;
                    break;
                }
            }
            // 图片 / PDF：识别出的真实类型必须与扩展名对应
            if ($pass && isset(self::STRICT_EXT_MIMES[$ext])
                && $mime !== 'application/octet-stream' && !in_array($mime, self::STRICT_EXT_MIMES[$ext], true)) {
                $pass = false;
            }
            if (!$pass) {
                return self::fail('文件内容与扩展名不符（检测到类型：' . $mime . '）。');
            }
        }

        // 存储：子目录/年月/随机名
        $rel = trim($subDir, '/') . '/' . date('Ym') . '/';
        $dir = OHAS_ROOT . '/storage/uploads/' . $rel;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {   // 并发创建同一目录时 mkdir 会失败，再判一次
            return self::fail('存储目录创建失败，请检查 storage/uploads 写权限。');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . $name;
        $moved = @move_uploaded_file($tmp, $dest);
        // 仅命令行（自测 / 脚本，tmp 文件并非 HTTP 上传流）才允许回退 copy；
        // Web 请求里 move_uploaded_file 失败说明 tmp_name 不是 PHP 接收的上传文件，必须拒绝
        if (!$moved && PHP_SAPI === 'cli') {
            $moved = @copy($tmp, $dest);
        }
        if (!$moved || !is_file($dest)) {
            return self::fail('文件保存失败，请检查目录权限与磁盘空间。');
        }
        return ['ok' => true, 'msg' => '', 'path' => $rel . $name];
    }

    /** 按存储名安全读取绝对路径（防目录穿越） */
    public static function absPath(string $stored): ?string
    {
        $stored = str_replace('\\', '/', $stored);
        if ($stored === '' || str_contains($stored, '..') || str_contains($stored, "\0") || str_starts_with($stored, '/')) {
            return null;
        }
        $abs = realpath(OHAS_ROOT . '/storage/uploads/' . $stored);
        $base = realpath(OHAS_ROOT . '/storage/uploads');
        if ($abs === false || $base === false || !str_starts_with($abs, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $abs;
    }

    /** 删除存储文件（忽略不存在） */
    public static function remove(string $stored): void
    {
        if ($stored === '') {
            return;
        }
        $abs = self::absPath($stored);
        if ($abs !== null && is_file($abs)) {
            @unlink($abs);
        }
    }

    /** 照片压缩（GD 可用时）：长边不超过 600px 的 JPEG / PNG */
    public static function shrinkPhoto(string $absPath): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            return;   // 无 GD 扩展时保留原图
        }
        $info = @getimagesize($absPath);
        if (!$info) {
            return;
        }
        [$w, $h] = $info;
        $max = 600;
        if ($w <= $max && $h <= $max) {
            return;
        }
        if ($w * $h > self::SHRINK_MAX_PIXELS) {
            return;   // 体积小但像素极大的图（解压炸弹）不解码，保留原图
        }
        $src = $info[2] === IMAGETYPE_JPEG ? @imagecreatefromjpeg($absPath)
            : ($info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($absPath) : null);
        if (!$src) {
            return;
        }
        $ratio = min($max / $w, $max / $h);
        $nw = (int)round($w * $ratio);
        $nh = (int)round($h * $ratio);
        $dst = imagecreatetruecolor($nw, $nh);
        if ($info[2] === IMAGETYPE_PNG) {
            // PNG 保持 PNG 编码与透明通道（避免内容变 JPEG 而扩展名/Content-Type 仍为 png）
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagepng($dst, $absPath, 6);
        } else {
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagejpeg($dst, $absPath, 88);
        }
        imagedestroy($src);
        imagedestroy($dst);
    }

    /** @return array{ok:bool,msg:string,path:string} */
    private static function fail(string $msg): array
    {
        return ['ok' => false, 'msg' => $msg, 'path' => ''];
    }
}
