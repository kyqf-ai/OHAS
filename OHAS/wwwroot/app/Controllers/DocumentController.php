<?php
/**
 * 分类文档管理（13 类）：上传、检索、到期提醒与下载。
 *
 * 文档带资料类型 doc_type 与所属年度 period_year（资料类型注册表 DocTypes）；
 * 上传时记录 original_name（原始文件名，title 改名不影响）与 file_hash（同一关联对象下重复上传仅提示）；
 * 提供槽位快速上传 documents/quick（多文件）与就地改名 documents/rename（AJAX）。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Helpers\DocLink;
use App\Helpers\Dict;
use App\Helpers\DocTypes;
use App\Helpers\Uploader;

class DocumentController extends Controller
{
    public function index(): void
    {
        $q = (string)$this->get('q');
        $category = (string)$this->get('category');
        $expiry = (string)$this->get('expiry');
        $docType = (string)$this->get('doc_type');
        $unclassified = (string)$this->get('unclassified');
        if ($docType === '__unclassified__') {   // 下拉里的“未分类”选项
            $unclassified = '1';
            $docType = '';
        }

        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where .= " AND (title LIKE ? OR doc_no LIKE ? OR issuer LIKE ?
                OR EXISTS (SELECT 1 FROM workers w WHERE w.name LIKE ?
                    AND ((d.link_type = 'worker' AND w.id = d.link_id)
                      OR EXISTS (SELECT 1 FROM exam_records er WHERE er.id = d.link_id AND d.link_type = 'exam_record' AND er.worker_id = w.id))))";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($category !== '' && in_array($category, Dict::documentCategories(), true)) {
            $where .= ' AND category = ?';
            $params[] = $category;
        }
        if ($docType !== '' && DocTypes::get($docType) !== null) {
            $where .= ' AND doc_type = ?';
            $params[] = $docType;
        }
        if ($unclassified === '1') {
            $where .= " AND COALESCE(doc_type,'') = ''";
        }
        $today = date('Y-m-d');
        $soonDays = max(1, (int)cfg('expiry.soon_days', 30));
        $soon = date('Y-m-d', strtotime('+' . $soonDays . ' days'));
        if ($expiry === 'expired') {
            $where .= " AND expire_date IS NOT NULL AND expire_date <> '' AND expire_date < ?";
            $params[] = $today;
        } elseif ($expiry === 'soon') {
            $where .= " AND expire_date IS NOT NULL AND expire_date <> '' AND expire_date >= ? AND expire_date <= ?";
            $params[] = $today;
            $params[] = $soon;
        }

        $size = max(5, (int)cfg('page.size', 10));
        $total = (int)Database::value('SELECT COUNT(*) FROM documents d WHERE ' . $where, $params);
        $pages = max(1, (int)ceil($total / $size));
        $page = min(max(1, (int)$this->get('page', 1)), $pages);
        $offset = ($page - 1) * $size;
        $rows = Database::rows(
            'SELECT d.* FROM documents d WHERE ' . $where . ' ORDER BY d.id DESC LIMIT ' . $size . ' OFFSET ' . $offset,
            $params
        );
        foreach ($rows as &$r) {
            $r['link_label'] = (!empty($r['link_type']) && !empty($r['link_id']))
                ? DocLink::label((string)$r['link_type'], (int)$r['link_id']) : '';
        }
        unset($r);

        $this->view('documents/index', [
            'rows'       => $rows,
            'categories' => Dict::documentCategories(),
            'docTypes'   => DocTypes::options(),
            'docType'    => $docType,
            'unclassified' => $unclassified,
            'q'          => $q,
            'category'   => $category,
            'expiry'     => $expiry,
            'today'      => $today,
            'soon'       => $soon,
            'data'       => ['total' => $total, 'page' => $page, 'pages' => $pages],
        ], '分类文档管理', 'documents');
    }

    public function create(): void
    {
        $cat = (string)$this->get('category');
        if (!in_array($cat, Dict::documentCategories(), true)) {
            $cat = Dict::documentCategories()[0];
        }
        // 从业务页（检测报告/体检批次/体检记录/台账…）点「上传原件」进入：预选分类、资料类型与关联对象
        $type = (string)$this->get('link_type');
        $lid = (int)$this->get('link_id');
        if (!isset(DocLink::types()[$type]) || !DocLink::exists($type, $lid)) {
            $type = '';
            $lid = 0;
        }
        $docType = (string)$this->get('doc_type');
        if (DocTypes::get($docType) === null) {
            $docType = $type !== '' ? DocTypes::defaultForLink($type) : '';
        }
        $this->form([
            'id' => 0, 'category' => $cat, 'doc_type' => $docType, 'period_year' => (int)$this->get('period_year'),
            'link_type' => $type, 'link_id' => $lid ?: '',
            'back' => $type !== '' && (string)$this->get('back') === '1' ? 1 : 0,
        ], 'create');
    }

    /** 关联对象检索（文档表单的下拉搜索） */
    public function targets(): void
    {
        $type = (string)$this->get('type');
        if (!isset(DocLink::types()[$type])) {
            $this->json(['ok' => true, 'items' => []]);
        }
        $this->json(['ok' => true, 'items' => DocLink::search($type, trim((string)$this->get('q')))]);
    }

    public function edit(): void
    {
        $id = (int)$this->get('id');
        $doc = Database::row('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$doc) {
            $this->flash('danger', '文档不存在。');
            $this->redirect('documents/index');
        }
        $this->form($doc, 'edit');
    }

    private function form(array $doc, string $mode): void
    {
        if ($this->isPost()) {
            $this->verifyCsrf();
            $categories = Dict::documentCategories();
            $v = Validator::make($_POST, [
                'category'    => 'required|in:' . implode(',', $categories),
                'title'       => 'required|max:120',
                'doc_no'      => 'max:80',
                'issuer'      => 'max:120',
                'doc_date'    => 'date',
                'expire_date' => 'date|not_before:doc_date',
                'doc_type'    => 'in:' . implode(',', array_keys(DocTypes::all())),
                'period_year' => 'int',
                'link_type'   => 'in:' . implode(',', array_keys(DocLink::types())),
                'link_id'     => 'int',
                'remark'      => 'max:300',
            ], [
                'category' => '文档分类', 'title' => '文档标题', 'doc_no' => '文档编号',
                'issuer' => '出具单位', 'doc_date' => '文档日期', 'expire_date' => '有效期至',
                'doc_type' => '资料类型', 'period_year' => '所属年度',
                'link_type' => '关联对象', 'link_id' => '关联记录', 'remark' => '备注',
            ]);
            if ($v->fails()) {
                $this->failBack('documents/' . $mode, $v->errors(), $_POST, $mode === 'edit' ? ['id' => (int)$doc['id']] : []);
            }
            $data = $v->validated();
            $data['link_id'] = $data['link_id'] === '' ? null : (int)$data['link_id'];
            if ($data['link_type'] === '' || $data['link_id'] === null) {
                $data['link_type'] = '';
                $data['link_id'] = null;
            } elseif (!DocLink::exists($data['link_type'], $data['link_id'])) {
                $this->failBack('documents/' . $mode, ['link_id' => '所选关联记录不存在，请重新选择。'], $_POST, $mode === 'edit' ? ['id' => (int)$doc['id']] : []);
            }
            // 资料类型是分类唯一来源：有类型时类别一律由类型派生，不再保留手工类别，
            // 避免同一份资料 category 与 doc_type 长期矛盾。旧的无类型文档继续用 category 兜底。
            if ($data['doc_type'] !== '') {
                $cat = DocTypes::categoryOf($data['doc_type']);
                if ($cat !== '') {
                    $data['category'] = $cat;
                }
            }
            // 年度：留空时取文档日期年份
            $data['period_year'] = $data['period_year'] === '' ? null : (int)$data['period_year'];
            if ($data['period_year'] === null && $data['doc_date'] !== '') {
                $data['period_year'] = (int)substr($data['doc_date'], 0, 4) ?: null;
            }
            $backToTarget = $data['link_type'] !== '' && (string)$this->post('back') === '1';

            $file = $_FILES['file'] ?? null;
            $newPath = '';
            if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                // 扩展名与大小读取 config.php upload.doc_*
                $res = Uploader::save(
                    $file,
                    'documents',
                    (array)cfg('upload.doc_exts'),
                    (int)cfg('upload.doc_max_bytes'),
                    [
                        'application/pdf', 'image/jpeg', 'image/png',
                        'application/msword', 'application/CDFV2*', 'application/x-ole-storage',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/zip',   // 部分环境把 docx/xlsx 识别为 zip
                        'application/octet-stream',
                    ]
                );
                if (!$res['ok']) {
                    $this->failBack('documents/' . $mode, ['file' => $res['msg']], $_POST, $mode === 'edit' ? ['id' => (int)$doc['id']] : []);
                }
                $newPath = $res['path'];
            } elseif ($mode === 'create') {
                $this->failBack('documents/create', ['file' => '请选择要上传的文档文件。'], $_POST);
            }

            $now = now_str();
            // 写库本身失败时清理刚落盘的新文件，避免产生无记录引用的孤儿文件；
            // 写库成功之后的步骤（提示、日志、查重）出错不能删文件，否则记录会指向已不存在的原件
            $written = false;
            try {
                if ($mode === 'edit') {
                    $data['updated_at'] = $now;
                    if ($newPath !== '') {
                        $data['file_path'] = $newPath;
                        // 替换原件时同步更新 original_name，资料包目录中的原文件名才准确
                        $data['original_name'] = mb_substr((string)($file['name'] ?? ''), 0, 200);
                        $abs = Uploader::absPath($newPath);
                        $data['file_hash'] = ($abs !== null && is_file($abs)) ? sha1_file($abs) : '';
                    }
                    $sets = implode(', ', array_map(fn ($c) => $c . ' = ?', array_keys($data)));
                    Database::run(
                        'UPDATE documents SET ' . $sets . ' WHERE id = ?',
                        array_merge(array_values($data), [(int)$doc['id']])
                    );
                    $written = true;
                    if ($newPath !== '' && !empty($doc['file_path'])) {
                        Uploader::remove((string)$doc['file_path']);
                    }
                    $this->log('update', 'document', (int)$doc['id'], '修改文档：' . $data['title']);
                    $this->flash('success', '文档已更新。');
                } else {
                    $data['file_path'] = $newPath;
                    $abs = Uploader::absPath($newPath);
                    $data['file_hash'] = ($abs !== null && is_file($abs)) ? sha1_file($abs) : '';
                    $data['original_name'] = mb_substr((string)($file['name'] ?? ''), 0, 200);
                    $data['created_at'] = $now;
                    $data['updated_at'] = $now;
                    $cols = array_keys($data);
                    Database::run(
                        'INSERT INTO documents (' . implode(',', $cols) . ') VALUES ('
                            . implode(',', array_fill(0, count($cols), '?')) . ')',
                        array_values($data)
                    );
                    $written = true;
                    $newId = Database::lastId();
                    $this->log('upload', 'document', $newId, '上传文档：' . $data['title']);
                    $this->flash('success', '文档已上传。' . $this->dupHint($data['file_hash'], (string)$data['link_type'], (int)($data['link_id'] ?? 0), $newId));
                }
            } catch (\Throwable $e) {
                if (!$written && $newPath !== '') {
                    Uploader::remove($newPath);
                }
                throw $e;
            }
            if ($backToTarget) {
                $this->redirectUrl(DocLink::url($data['link_type'], (int)$data['link_id']));
            }
            $this->redirect('documents/index');
        }

        $this->view('documents/form', [
            'doc'        => $doc,
            'mode'       => $mode,
            'categories' => Dict::documentCategories(),
            'docTypes'   => DocTypes::options(),
            'linkTypes'  => DocLink::types(),
            'linkLabel'  => (!empty($doc['link_type']) && !empty($doc['link_id'])) ? DocLink::label((string)$doc['link_type'], (int)$doc['link_id']) : '',
        ], ($mode === 'edit' ? '编辑文档' : '上传文档'), 'documents');
    }

    /**
     * 槽位快速上传：多文件，每个文件生成一条文档。
     * 参数：doc_type, link_type, link_id, period_year, doc_date, title(可空), back
     */
    public function quick(): void
    {
        $this->verifyCsrf();
        $docType = (string)$this->post('doc_type');
        $typeCfg = DocTypes::get($docType);
        if ($typeCfg === null) {
            $this->done(false, '资料类型无效，请从页面重新进入。', 'documents/index');
        }
        $linkType = (string)$this->post('link_type');
        $linkId = (int)$this->post('link_id');
        if ($linkType !== '' && (!isset(DocLink::types()[$linkType]) || !DocLink::exists($linkType, $linkId))) {
            $this->done(false, '关联对象无效，请从页面重新进入。', 'documents/index');
        }
        if ($linkType === '') {
            $linkId = 0;
        }
        $year = (int)$this->post('period_year');
        if ($year !== 0 && ($year < 1900 || $year > 2100)) {
            $year = 0;
        }
        $docDate = (string)$this->post('doc_date');
        if ($docDate !== '' && !Validator::isDate($docDate)) {
            $docDate = '';
        } elseif ($docDate !== '') {
            $docDate = Validator::normalizeDate($docDate);
        }
        if ($year === 0 && $docDate !== '') {
            $year = (int)substr($docDate, 0, 4) ?: 0;
        }
        $title = trim(mb_substr((string)$this->post('title'), 0, 120));

        // 上下文：劳动者姓名（默认标题用）
        $ctx = ['year' => $year !== 0 ? $year : ''];
        if ($linkType === 'worker') {
            $ctx['name'] = (string)Database::value('SELECT name FROM workers WHERE id = ?', [$linkId]);
        }

        $files = $_FILES['files'] ?? null;
        $list = [];
        if (is_array($files) && isset($files['error'])) {
            if (is_array($files['error'])) {
                // 多文件：<input name="files[]" multiple>
                foreach ($files['error'] as $i => $err) {
                    $list[] = [
                        'name' => (string)($files['name'][$i] ?? ''),
                        'tmp_name' => (string)($files['tmp_name'][$i] ?? ''),
                        'error' => (int)$err,
                        'size' => (int)($files['size'][$i] ?? 0),
                    ];
                }
            } elseif ((int)$files['error'] !== UPLOAD_ERR_NO_FILE) {
                // 单文件：<input name="files">（兼容 curl -F files=@…）
                $list[] = [
                    'name' => (string)($files['name'] ?? ''),
                    'tmp_name' => (string)($files['tmp_name'] ?? ''),
                    'error' => (int)$files['error'],
                    'size' => (int)($files['size'] ?? 0),
                ];
            }
        }
        if (!$list) {
            $this->done(false, '请选择要上传的文件。', 'documents/index');
        }

        $saved = 0;
        $errors = [];
        $dupMsgs = [];
        $exts = (array)cfg('upload.doc_exts');
        $maxBytes = (int)cfg('upload.doc_max_bytes');
        $mimes = [
            'application/pdf', 'image/jpeg', 'image/png',
            'application/msword', 'application/CDFV2*', 'application/x-ole-storage',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip', 'application/octet-stream',
        ];
        foreach ($list as $i => $file) {
            $res = Uploader::save($file, 'documents', $exts, $maxBytes, $mimes);
            if (!$res['ok']) {
                $errors[] = '第 ' . ($i + 1) . ' 个文件未保存：' . $res['msg'];
                continue;
            }
            $abs = Uploader::absPath($res['path']);
            $hash = ($abs !== null && is_file($abs)) ? sha1_file($abs) : '';
            $t = $title;
            if ($t === '') {
                $t = DocTypes::defaultTitle($docType, $ctx);
                if ($t === '') {
                    $t = pathinfo($file['name'], PATHINFO_FILENAME);
                }
            }
            if (count($list) > 1) {
                $t .= '（' . ($i + 1) . '）';
            }
            $now = now_str();
            Database::run(
                'INSERT INTO documents (category, title, doc_date, file_path, doc_type, period_year, original_name, file_hash, link_type, link_id, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    DocTypes::categoryOf($docType) ?: '其他', $t, $docDate, $res['path'], $docType,
                    $year !== 0 ? $year : null,
                    mb_substr($file['name'], 0, 200), $hash,
                    $linkType, $linkType !== '' ? $linkId : null, $now, $now,
                ]
            );
            $saved++;
            $dup = $this->dupHint($hash, $linkType, $linkId, Database::lastId());
            if ($dup !== '') {
                $dupMsgs[] = $dup;
            }
            $this->log('upload', 'document', Database::lastId(), '快速上传文档：' . $t);
        }

        if ($saved > 0 || $errors) {
            $msg = ($saved > 0 ? '已上传 ' . $saved . ' 份文档。' : '')
                . ($errors ? ' ' . implode(' ', $errors) : '')
                . (($saved > 0 && $dupMsgs) ? ' ' . implode(' ', array_unique($dupMsgs)) : '');
            $this->flash($saved > 0 ? ($errors ? 'warning' : 'success') : 'danger', trim($msg));
        }
        if ($linkType !== '') {
            $this->redirectUrl(DocLink::url($linkType, $linkId));
        }
        if ((string)$this->post('back') === '1') {
            $this->back();
        }
        $this->redirect('documents/index');
    }

    /** 同一关联对象下重复 file_hash 的提示（不阻止上传） */
    private function dupHint(string $hash, string $linkType, int $linkId, int $exceptId): string
    {
        if ($hash === '' || $linkType === '') {
            return '';
        }
        $n = (int)Database::value(
            'SELECT COUNT(*) FROM documents WHERE file_hash = ? AND link_type = ? AND link_id = ? AND id != ?',
            [$hash, $linkType, $linkId, $exceptId]
        );
        return $n > 0 ? '提示：该对象下已有内容相同的文件 ' . $n . ' 份。' : '';
    }

    /** 就地改名（AJAX，返回 JSON）：只改 title，不动文件与 original_name */
    public function rename(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $title = trim((string)$this->post('title'));
        $doc = Database::row('SELECT id, title FROM documents WHERE id = ?', [$id]);
        if (!$doc) {
            $this->json(['ok' => false, 'msg' => '文档不存在。']);
        }
        if ($title === '' || mb_strlen($title) > 120) {
            $this->json(['ok' => false, 'msg' => '标题不能为空，且不超过 120 字。']);
        }
        Database::run('UPDATE documents SET title = ?, updated_at = ? WHERE id = ?', [$title, now_str(), $id]);
        $this->log('update', 'document', $id, '文档改名：' . $doc['title'] . ' → ' . $title);
        $this->json(['ok' => true, 'msg' => '已改名。', 'title' => $title]);
    }

    /** 文档列表批量归类：设置资料类型；仅作用于未分类或所选行 */
    public function classify(): void
    {
        $this->verifyCsrf();
        $docType = (string)$this->post('doc_type');
        if (DocTypes::get($docType) === null) {
            $this->done(false, '资料类型无效。', 'documents/index');
        }
        $ids = array_map('intval', $this->postArray('ids'));
        $ids = array_values(array_filter($ids, fn ($i) => $i > 0));
        if (!$ids) {
            $this->done(false, '请先勾选要归类的文档。', 'documents/index');
        }
        $onlyUnclassified = (string)$this->post('only_unclassified') === '1';
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE documents SET doc_type = ?, updated_at = ? WHERE id IN ({$ph})";
        $params = array_merge([$docType, now_str()], $ids);
        if ($onlyUnclassified) {
            $sql .= " AND COALESCE(doc_type,'') = ''";
        }
        Database::run($sql, $params);
        $this->log('update', 'document', null, '批量归类 ' . count($ids) . ' 份文档为 ' . DocTypes::nameOf($docType));
        $this->done(true, '已将所选文档设为「' . DocTypes::nameOf($docType) . '」。', 'documents/index');
    }

    public function delete(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $doc = Database::row('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$doc) {
            $this->done(false, '文档不存在或已被删除。', 'documents/index');
        }
        Database::run('DELETE FROM documents WHERE id = ?', [$id]);
        Uploader::remove((string)$doc['file_path']);
        $this->log('delete', 'document', $id, '删除文档：' . $doc['title']);
        // 支持 back=1 回到来源页（个人资料页、台账编辑页等）
        if ((string)$this->post('back') === '1' && $this->isAjax()) {
            $this->json(['ok' => true, 'msg' => '文档已删除。']);
        }
        if ((string)$this->post('back') === '1') {
            $this->back();
        }
        $this->done(true, '文档已删除。', 'documents/index');
    }

    /** 文档下载（含 Word/Excel）；挂在劳动者名下（直接或经体检记录/检测报告间接）的下载写日志 */
    public function download(): void
    {
        $id = (int)$this->get('id');
        $doc = Database::row('SELECT * FROM documents WHERE id = ?', [$id]);
        $abs = $doc ? Uploader::absPath((string)$doc['file_path']) : null;
        if ($abs === null || !is_file($abs)) {
            http_response_code(404);
            $title = '文件不存在';
            $hint = '文档可能已被替换或删除。';
            include OHAS_ROOT . '/app/Views/errors/error.php';
            exit;
        }
        // 隐私审计：挂在劳动者名下（直接，或经体检记录、检测报告间接汇入个人页）的文档下载写操作日志
        if (in_array((string)$doc['link_type'], ['worker', 'exam_record', 'disease', 'detection_report'], true)) {
            $this->log('download', 'document', (int)$doc['id'], '下载文档：' . $doc['title']);
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mimes = [
            'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        $name = ($doc['title'] !== '' ? $doc['title'] : 'document') . '.' . $ext;
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . (string)filesize($abs));
        header('X-Content-Type-Options: nosniff');
        $disp = (string)$this->get('inline') === '1' && in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true) ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disp . "; filename*=UTF-8''" . rawurlencode($name));
        readfile($abs);
        exit;
    }
}
