<?php
/**
 * 劳动者信息卡（M3，系统核心）：
 *  - 基本信息区（表6-1）
 *  - 职业史 / 既往病史 / 职业病诊断 三个子区
 *  - 表6-1 样表打印
 *  - CSV 批量导入（模板下载 / 预览确认 / 结果报告）
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\ArchiveNo;
use App\Helpers\Csv;
use App\Helpers\Dict;
use App\Helpers\IdCard;
use App\Helpers\Uploader;
use App\Models\Worker;
use App\Models\WorkerIllness;
use App\Models\WorkerPost;
use App\Models\Disease;

class WorkerController extends Controller
{
    /* ==================== 列表 ==================== */

    public function index(): void
    {
        $q = (string)$this->get('q');
        $status = (string)$this->get('status');
        $postId = (int)$this->get('post');
        $page = max(1, (int)$this->get('page', 1));
        $data = Worker::search($q, $status, $postId, $page, (int)cfg('page.size'));
        // 删除前复核：体检/职业病登记不可物理删除；文档、培训、领用会在确认后级联/解绑
        foreach ($data['rows'] as &$w) {
            $wid = (int)$w['id'];
            $w['doc_count'] = (int)Database::value(
                "SELECT COUNT(*) FROM documents WHERE (link_type = 'worker' AND link_id = :wid)
                   OR (link_type = 'exam_record' AND link_id IN (SELECT id FROM exam_records WHERE worker_id = :wid2))",
                [':wid' => $wid, ':wid2' => $wid]
            );
            $w['exam_count'] = (int)Database::value('SELECT COUNT(*) FROM exam_records WHERE worker_id = ?', [$wid]);
            $w['disease_count'] = (int)Database::value('SELECT COUNT(*) FROM diseases WHERE worker_id = ?', [$wid]);
            $w['training_count'] = (int)Database::value('SELECT COUNT(*) FROM training_participants WHERE worker_id = ?', [$wid]);
            $w['ppe_count'] = (int)Database::value('SELECT COUNT(*) FROM ppe_issues WHERE worker_id = ?', [$wid]);
        }
        unset($w);
        $this->view('workers/index', [
            'data' => $data,
            'q' => $q, 'status' => $status, 'postId' => $postId,
            'statusOptions' => Dict::workerStatus(),
            'postOptions' => \App\Models\Post::options(),
        ], '劳动者信息卡', 'workers');
    }

    /* ==================== 建档 / 编辑 ==================== */

    public function create(): void
    {
        $this->form(['id' => 0, 'archive_no' => ArchiveNo::next()]);
    }

    public function edit(): void
    {
        $id = (int)$this->get('id');
        $worker = Worker::detail($id);
        if (!$worker) {
            $this->flash('danger', '劳动者不存在。');
            $this->redirect('workers/index');
        }
        $this->form($worker);
    }

    /* ==================== 整卡查看（只读） ==================== */

    public function show(): void
    {
        $id = (int)$this->get('id');
        $worker = Worker::detail($id);
        if (!$worker) {
            $this->flash('danger', '劳动者不存在。');
            $this->redirect('workers/index');
        }
        $this->view('workers/view', [
            'worker' => $worker,
        ], '信息卡查看 - ' . $worker['name'], 'workers');
    }

    /* ==================== 个人资料全景 ==================== */

    public function files(): void
    {
        $id = (int)$this->get('id');
        $q = trim((string)$this->get('q'));
        if ($id <= 0 && $q !== '') {
            // 支持 workers/files?q=姓名（清单页“去上传”入口用）
            $rows = Database::rows('SELECT id FROM workers WHERE name = ? OR id_card = ? LIMIT 2', [$q, strtoupper($q)]);
            if (count($rows) === 1) {
                $id = (int)$rows[0]['id'];
            }
        }
        $range = (string)$this->get('range');
        if (!in_array($range, ['3y', '5y', 'all'], true)) {
            $range = '3y';
        }
        $archive = $id > 0 ? \App\Helpers\WorkerArchive::build($id, $range) : null;
        if ($archive === null) {
            $this->flash('danger', '劳动者不存在，请从列表或搜索进入。');
            $this->redirect('workers/index');
        }
        // 文件丢失标记（每页最多检测 200 个）
        $checked = 0;
        $checkFile = function (array &$docs) use (&$checked): void {
            foreach ($docs as &$dc) {
                if ($checked >= 200) {
                    return;
                }
                $checked++;
                $abs = \App\Helpers\Uploader::absPath((string)$dc['file_path']);
                $dc['file_missing'] = ($abs === null || !is_file($abs));
            }
        };
        foreach ($archive['allYears'] as &$yRow) {
            foreach ($yRow['cells'] as &$c) {
                $checkFile($c['docs']);
            }
            unset($c);
        }
        unset($yRow);
        foreach ($archive['once'] as &$o) {
            $checkFile($o['docs']);
        }
        unset($o);
        foreach ($archive['events'] as &$ev) {
            $checkFile($ev['docs']);
        }
        unset($ev);
        $checkFile($archive['others']);

        $this->log('view', 'worker_files', $id, '查看个人资料全景：' . $archive['header']['name']);
        $this->view('workers/files', [
            'a'         => $archive,
            'range'     => $range,
            'columns'   => \App\Helpers\WorkerArchive::yearColumns(),
        ], '个人资料全景 - ' . $archive['header']['name'], 'workers');
    }

    /** 导出个人资料包 zip：目录 姓名_身份证后4位/年度或一次性或事件/资料类型_标题.ext + 目录.csv */
    public function exportFiles(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $archive = \App\Helpers\WorkerArchive::build($id, 'all');
        if ($archive === null) {
            $this->done(false, '劳动者不存在。', 'workers/index');
        }
        if (!class_exists('ZipArchive')) {
            $this->done(false, '当前环境未启用 ZipArchive，无法导出资料包。', 'workers/files', ['id' => $id]);
        }

        $h = $archive['header'];
        $root = self::safeName($h['name'] . '_' . substr(str_replace('*', 'x', $h['id_card']), -4));
        $tmp = tempnam(sys_get_temp_dir(), 'ohas-export-');
        if ($tmp === false) {
            $this->done(false, '导出失败：无法创建临时文件（请检查系统临时目录权限与磁盘空间）。', 'workers/files', ['id' => $id]);
        }
        // 成功、失败或客户端中断下载时，都在脚本结束时清理临时包
        register_shutdown_function(static function () use ($tmp): void {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        });
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->done(false, '导出文件创建失败。', 'workers/files', ['id' => $id]);
        }

        $csv = [];
        $csv[] = ['姓名', $h['name']];
        $csv[] = ['档案号', $h['archive_no'] ?: '—'];
        $csv[] = ['身份证号', $h['id_card'] ?: '—'];   // 完整号仅出现在目录.csv 头部（附录 C #9）
        $csv[] = [];
        $csv[] = ['年度', '资料类型', '标题', '日期', '关联来源', '原文件名', 'zip 内路径'];

        $add = function (array $dc, string $dir, string $srcLabel) use ($zip, $root, &$csv): void {
            $abs = \App\Helpers\Uploader::absPath((string)$dc['file_path']);
            if ($abs === null || !is_file($abs)) {
                return;   // 文件丢失不进包
            }
            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION)) ?: 'dat';
            $base = self::safeName(\App\Helpers\DocTypes::nameOf((string)$dc['doc_type']) . '_' . $dc['title']);
            if (mb_strlen($base) > 80) {
                $base = mb_substr($base, 0, 80);
            }
            $path = $root . '/' . $dir . '/' . $base . '.' . $ext;
            $n = 2;
            while ($zip->locateName($path) !== false) {
                $path = $root . '/' . $dir . '/' . $base . '（' . $n . '）.' . $ext;
                $n++;
            }
            $zip->addFile($abs, $path);
            $csv[] = [
                $dir,
                (string)$dc['doc_type'] !== '' ? \App\Helpers\DocTypes::nameOf((string)$dc['doc_type']) : '未分类',
                $dc['title'],
                (string)$dc['doc_date'] ?: '—',
                $srcLabel,
                (string)$dc['original_name'] ?: '—',
                $path,
            ];
        };

        foreach ($archive['allYears'] as $y => $row) {
            foreach ($row['cells'] as $type => $cell) {
                foreach ($cell['docs'] ?? [] as $dc) {
                    $add($dc, (string)$y, '本人' . (($dc['link_type'] ?? '') === 'exam_record' ? '（体检记录）' : ''));
                }
                // 间接汇入的检测报告原件 → 间接参考子目录（附录 C #8）
                if (($cell['reports'] ?? []) && in_array($type, ['X_DETECT'], true)) {
                    foreach ($cell['reports'] as $r) {
                        foreach (\App\Helpers\DocLink::forLink('detection_report', (int)$r['id']) as $dc) {
                            $dc['doc_type'] = $dc['doc_type'] ?: 'U_DETECT_REPORT';
                            $add($dc, '间接参考', '检测报告 ' . $r['report_no'] . '（' . ($r['post_name'] ?: '按因素') . '）');
                        }
                    }
                }
            }
        }
        foreach ($archive['once'] as $item) {
            foreach ($item['docs'] as $dc) {
                $add($dc, '一次性', '本人');
            }
        }
        foreach ($archive['events'] as $item) {
            foreach ($item['docs'] as $dc) {
                $add($dc, '事件', $dc['from'] ?? '本人');
            }
        }
        foreach ($archive['others'] as $dc) {
            $add($dc, '其他资料', '本人');
        }

        // 目录.csv（公式注入防护：= + - @ 开头前加单引号）
        $csvText = "\xEF\xBB\xBF";
        foreach ($csv as $line) {
            $csvText .= implode(',', array_map(function ($v) {
                $v = (string)$v;
                if (preg_match('/^[=+\-@]/', $v)) {
                    $v = "'" . $v;
                }
                return \App\Helpers\Csv::cell($v);
            }, $line)) . "\r\n";
        }
        $zip->addFromString($root . '/目录.csv', $csvText);
        if (!$zip->close() || !is_file($tmp) || filesize($tmp) === 0) {
            $this->done(false, '导出失败：资料包写入未完成（请检查磁盘空间）。', 'workers/files', ['id' => $id]);
        }

        $this->log('export', 'worker_files', $id, '导出个人资料包：' . $h['name'] . '（' . (count($csv) - 5) . ' 份文件）');
        $name = '个人资料包_' . self::safeName($h['name']) . '_' . date('Ymd') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Length: ' . (string)filesize($tmp));
        header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($name));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /** 文件名安全化：去掉 \ / : * ? " < > | 与控制字符 */
    private static function safeName(string $s): string
    {
        $s = preg_replace('/[\\\\\/:*?"<>|\x00-\x1f\x7f]/u', '', $s) ?? '';
        $s = trim($s, " .");
        return $s !== '' ? $s : '未命名';
    }

    private function form(array $worker): void
    {
        if ($this->isPost()) {
            $this->verifyCsrf();
            $v = \App\Core\Validator::make($_POST, [
                'archive_no'     => 'max:40',
                'name'           => 'required|max:30',
                'gender'         => 'in:男,女',
                'birth_date'     => 'date',
                'native_place'   => 'max:60',
                'marital_status' => 'max:10',
                'education'      => 'max:20',
                'hobby'          => 'max:60',
                'work_start_date' => 'date',
                'id_card'        => 'idcard',
                'phone'          => 'phone',
                'status'         => 'required|in:在职,离岗,退休,离职',
                'entry_date'     => 'date|not_future',
                'leave_date'     => 'date|not_future|not_before:entry_date',
                'remark'         => 'max:500',
            ], [
                'archive_no' => '档案号', 'name' => '姓名', 'gender' => '性别', 'birth_date' => '出生日期',
                'native_place' => '籍贯', 'marital_status' => '婚姻状况', 'education' => '文化程度',
                'hobby' => '嗜好', 'work_start_date' => '参加工作时间', 'id_card' => '身份证号',
                'phone' => '联系电话', 'status' => '在职状态', 'entry_date' => '入职本单位时间',
                'leave_date' => '离岗/离职日期', 'remark' => '备注',
            ]);
            $v->fails();
            $errs = $v->errors();
            $postStatus = is_scalar($_POST['status'] ?? null) ? trim((string)$_POST['status']) : '';
            $postLeave  = is_scalar($_POST['leave_date'] ?? null) ? trim((string)$_POST['leave_date']) : '';
            if ($postStatus !== '在职' && $postLeave === '') {
                $errs['leave_date'] = '离岗/离职日期不能为空：非在职人员必须据此收尾职业史段。';
            }
            if ($errs) {
                $target = ((int)($worker['id'] ?? 0)) > 0 ? 'workers/edit' : 'workers/create';
                $params = ((int)($worker['id'] ?? 0)) > 0 ? ['id' => (int)$worker['id']] : [];
                $_SESSION['_errors'] = $errs;
                $_SESSION['_old'] = $_POST;
                $this->flash('danger', '提交内容有误，请按提示修正。');
                $this->redirect($target, $params);
            }
            $data = $v->validated();

            // 职务角色：复选 → 逗号分隔串；空 = 普通劳动者
            $roles = [];
            foreach ((array)($_POST['duty_role'] ?? []) as $role) {
                $role = trim((string)$role);
                if (in_array($role, ['主要负责人', '职业卫生管理人员'], true) && !in_array($role, $roles, true)) {
                    $roles[] = $role;
                }
            }
            $data['duty_role'] = implode(',', $roles);

            // 身份证号占用检查 + 自动推导性别/出生日期（简化录入）
            $idCard = strtoupper((string)$data['id_card']);
            $data['id_card'] = $idCard;   // 末位 x 统一大写入库，保证查重/搜索/导入更新能对上
            if ($idCard !== '' && Worker::idCardTaken($idCard, (int)($worker['id'] ?? 0))) {
                $target = ((int)($worker['id'] ?? 0)) > 0 ? 'workers/edit' : 'workers/create';
                $_SESSION['_errors'] = ['id_card' => '该身份证号已建档，请勿重复录入。'];
                $_SESSION['_old'] = $_POST;
                $this->flash('danger', '提交内容有误，请按提示修正。');
                $this->redirect($target, ((int)($worker['id'] ?? 0)) > 0 ? ['id' => (int)$worker['id']] : []);
            }
            // 档案号唯一（库中为 UNIQUE）：手工改号撞号时回到表单提示，而不是抛数据库异常
            if ($data['archive_no'] !== ''
                && \App\Core\Database::value(
                    'SELECT COUNT(*) FROM workers WHERE archive_no = ? AND id != ?',
                    [$data['archive_no'], (int)($worker['id'] ?? 0)]
                )) {
                $target = ((int)($worker['id'] ?? 0)) > 0 ? 'workers/edit' : 'workers/create';
                $_SESSION['_errors'] = ['archive_no' => '该档案号已被其他劳动者使用，请更换。'];
                $_SESSION['_old'] = $_POST;
                $this->flash('danger', '提交内容有误，请按提示修正。');
                $this->redirect($target, ((int)($worker['id'] ?? 0)) > 0 ? ['id' => (int)$worker['id']] : []);
            }
            if ($idCard !== '' && IdCard::valid($idCard)) {
                if ($data['birth_date'] === '') {
                    $data['birth_date'] = IdCard::birth($idCard) ?? '';
                }
                if ($data['gender'] === '') {
                    $data['gender'] = IdCard::gender($idCard) ?? '';
                }
            }
            $autoArchiveNo = ($data['archive_no'] === '');
            if ($autoArchiveNo) {
                $data['archive_no'] = ArchiveNo::next();
            }

            // 照片上传
            if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $up = Uploader::save($_FILES['photo'], 'photos', cfg('upload.photo_exts'), (int)cfg('upload.photo_max_bytes'), ['image/jpeg', 'image/png']);
                if (!$up['ok']) {
                    $target = ((int)($worker['id'] ?? 0)) > 0 ? 'workers/edit' : 'workers/create';
                    $_SESSION['_errors'] = ['photo' => $up['msg']];
                    $_SESSION['_old'] = $_POST;
                    $this->flash('danger', '照片上传失败：' . $up['msg']);
                    $this->redirect($target, ((int)($worker['id'] ?? 0)) > 0 ? ['id' => (int)$worker['id']] : []);
                }
                if ($up['path'] !== '') {
                    $abs = Uploader::absPath($up['path']);
                    if ($abs) {
                        Uploader::shrinkPhoto($abs);
                    }
                    if (!empty($worker['photo'])) {
                        Uploader::remove((string)$worker['photo']);
                    }
                    $data['photo'] = $up['path'];
                }
            }

            // 恢复在职时清空离岗日期，避免状态与日期互相矛盾
            if (($data['status'] ?? '') === '在职') {
                $data['leave_date'] = '';
            }
            if ((int)($worker['id'] ?? 0) > 0) {
                $id = (int)$worker['id'];
                $closed = 0;
                // 状态变更与职业史收尾同一事务：任何一步失败都不留下“已离岗但仍算在岗”的中间态
                Database::tx(function () use ($id, $data, &$closed): void {
                    Worker::update($id, $data);
                    if (($data['status'] ?? '') !== '在职') {
                        $closed = WorkerPost::closeAllOpen($id, (string)$data['leave_date']);
                    }
                });
                $this->log('update', 'worker', $id, '修改劳动者信息卡：' . $data['name'] . '（' . $data['archive_no'] . '）');
                $this->flash('success', '信息卡已更新。');
                if ($closed > 0) {
                    $this->flash('info', '状态改为「' . $data['status'] . '」，已把 ' . $closed . ' 段未结束职业史收尾至 ' . $data['leave_date'] . '，接害工龄与历史统计不再继续增长。');
                }
            } else {
                // 多人同时建档时自动档案号可能撞号（UNIQUE）：重新取号重试，最多 3 次
                $id = 0;
                for ($try = 0; $try < 3; $try++) {
                    try {
                        $id = Worker::insert($data);
                        break;
                    } catch (\PDOException $e) {
                        if ($autoArchiveNo && $try < 2 && stripos($e->getMessage(), 'UNIQUE') !== false) {
                            $data['archive_no'] = ArchiveNo::next();
                            continue;
                        }
                        throw $e;
                    }
                }
                $this->log('create', 'worker', $id, '新增劳动者信息卡：' . $data['name'] . '（' . $data['archive_no'] . '）');
                $this->flash('success', '建档成功，请继续补充职业史、既往病史等信息。');
            }
            $this->redirect('workers/edit', ['id' => $id]);
        }

        $isEdit = (int)($worker['id'] ?? 0) > 0;
        $view = $isEdit ? 'workers/form' : 'workers/form';
        if ($isEdit) {
            $worker = Worker::detail((int)$worker['id']);
        }
        $this->view($view, [
            'worker' => $worker,
            'statusOptions' => Dict::workerStatus(),
            'maritalOptions' => Dict::maritalStatus(),
            'educationOptions' => Dict::education(),
            'postOptions' => \App\Models\Post::options(),
        ], $isEdit ? '信息卡编辑 - ' . $worker['name'] : '新建劳动者信息卡', 'workers');
    }

    /* ==================== 职业史 ==================== */

    public function saveHistory(): void
    {
        $this->verifyCsrf();
        $workerId = (int)$this->post('worker_id');
        $rowId = (int)$this->post('row_id');
        if (!Worker::find($workerId)) {
            $this->json(['ok' => false, 'msg' => '劳动者不存在。']);
        }
        // 编辑时校验行归属：row_id 必须属于当前劳动者，防止跨人改写/改挂
        if ($rowId > 0) {
            $existRow = WorkerPost::find($rowId);
            if (!$existRow || (int)$existRow['worker_id'] !== $workerId) {
                $this->json(['ok' => false, 'msg' => '记录不存在或不属于该劳动者，已拒绝保存。']);
            }
        }
        $v = \App\Core\Validator::make($_POST, [
            'start_date' => 'required|date',
            'end_date' => 'date',
        ], ['start_date' => '开始日期', 'end_date' => '结束日期']);
        $v->fails();
        $mode = $this->post('mode') === 'external' ? 'external' : 'internal';
        if ($mode === 'external') {
            $v2 = \App\Core\Validator::make($_POST, [
                'company_name' => 'required|max:100',
                'post_name_manual' => 'required|max:60',
            ], ['company_name' => '工作单位', 'post_name_manual' => '工种']);
            $v2->fails();
            $errs = array_merge($v->errors(), $v2->errors());
        } else {
            $v2 = \App\Core\Validator::make($_POST, ['post_id' => 'required'], ['post_id' => '本单位岗位']);
            $v2->fails();
            $errs = array_merge($v->errors(), $v2->errors());
            // 岗位存在性：伪造/已删除/非数字的 post_id 不允许入库（外键违规 500 或空「本单位」行）
            $pidRaw = trim((string)$this->post('post_id'));
            $pidVal = $pidRaw === '' ? 0 : (int)$pidRaw;
            if ($pidVal <= 0 || !Database::value('SELECT id FROM posts WHERE id = ?', [$pidVal])) {
                $errs['post_id'] = '所选岗位不存在，请重新选择。';
            }
        }
        $end = $this->post('end_date');
        $workerRow = Worker::find($workerId);
        // 非在职人员补录职业史时，留空结束日期不能默认“至今”，否则工龄会一直增长
        if ($end === '' && ($workerRow['status'] ?? '') !== '在职' && trim((string)($workerRow['leave_date'] ?? '')) !== '') {
            $end = substr((string)$workerRow['leave_date'], 0, 10);
        }
        if ($end !== '' && $end < $this->post('start_date')) {
            $errs['end_date'] = '结束日期不能早于开始日期。';
        }
        if ($errs) {
            $this->json(['ok' => false, 'msg' => implode(' ', $errs)]);
        }
        $in = [
            'start_date' => $this->post('start_date'),
            'end_date' => $end,
            'remark' => mb_substr($this->post('remark'), 0, 200),
        ];
        if ($mode === 'internal') {
            $in['post_id'] = (int)$this->post('post_id');
            $in['company_name'] = \App\Models\Company::name();
        } else {
            $in['post_id'] = 0;
            $in['company_name'] = $this->post('company_name');
            $in['post_name'] = $this->post('post_name_manual');
            $in['hazards_snapshot'] = mb_substr($this->post('hazards_manual'), 0, 500);
            $in['protection_snapshot'] = mb_substr($this->post('protection_manual'), 0, 500);
        }
        // “至今”段默认收尾已有在岗段；兼岗可取消 close_previous 保留重叠
        $autoClose = $rowId === 0 && $end === '' && (string)$this->post('close_previous') !== '0';
        $closed = 0;
        $savedId = 0;
        Database::tx(function () use ($workerId, $in, $rowId, $autoClose, &$closed, &$savedId): void {
            $savedId = WorkerPost::saveRow($workerId, $in, $rowId > 0 ? $rowId : null);
            if ($autoClose) {
                $closed = WorkerPost::closeOpenBefore($workerId, $in['start_date'], $savedId);
            }
        });
        $row = WorkerPost::find($savedId);
        $this->log($rowId > 0 ? 'update' : 'create', 'worker_post', $savedId,
            '劳动者#' . $workerId . ' 职业史：' . $in['start_date'] . ' ~ ' . ($end !== '' ? $end : '至今') . ' ' . ($row['post_name'] ?? '') . ($closed > 0 ? '（自动收尾 ' . $closed . ' 段）' : ''));
        // 与本人其他职业史段时间重叠：兼岗是合法的，不拦截，但“忘了关闭上一段”是最常见的录入错误，给出提示
        $overlap = (int)Database::value(
            "SELECT COUNT(*) FROM worker_posts WHERE worker_id = ? AND id <> ?
                AND start_date <= COALESCE(NULLIF(?, ''), '9999-12-31')
                AND COALESCE(NULLIF(end_date, ''), '9999-12-31') >= ?",
            [$workerId, $savedId, $end, $in['start_date']]
        );
        $msg = $rowId > 0 ? '职业史已更新。' : '职业史已保存。';
        if ($closed > 0) {
            $msg .= '已自动将 ' . $closed . ' 段原在岗职业史收尾至 ' . date('Y-m-d', strtotime($in['start_date'] . ' -1 day')) . '（兼岗请取消自动收尾选项）。';
        }
        if ($overlap > 0) {
            $msg .= '注意：与已有 ' . $overlap . ' 段职业史时间重叠；如不是兼岗，请检查上一段是否漏填了结束日期（接害工龄按重叠合并计算，不会重复累加）。';
        }
        $this->json(['ok' => true, 'msg' => $msg]);
    }

    public function historyRow(): void
    {
        $id = (int)$this->get('id');
        $row = WorkerPost::find($id);
        if (!$row) {
            $this->json(['ok' => false, 'msg' => '记录不存在。']);
        }
        $this->json(['ok' => true, 'row' => $row]);
    }

    public function deleteHistory(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $workerId = (int)$this->post('worker_id');
        $row = WorkerPost::find($id);
        // 校验行归属：仅允许删除当前劳动者名下的职业史行，防止误删他人记录
        if (!$row || $workerId <= 0 || (int)$row['worker_id'] !== $workerId) {
            $this->json(['ok' => false, 'msg' => '记录不存在或不属于该劳动者，已拒绝删除。']);
        }
        WorkerPost::delete($id);
        $this->log('delete', 'worker_post', $id, '删除职业史记录');
        $this->json(['ok' => true, 'msg' => '已删除。']);
    }

    /* ==================== 既往病史 ==================== */

    public function saveIllness(): void
    {
        $this->verifyCsrf();
        $workerId = (int)$this->post('worker_id');
        $rowId = (int)$this->post('row_id');
        if (!Worker::find($workerId)) {
            $this->json(['ok' => false, 'msg' => '劳动者不存在。']);
        }
        // 编辑时校验行归属：row_id 必须属于当前劳动者，防止跨人改写
        if ($rowId > 0) {
            $existRow = WorkerIllness::find($rowId);
            if (!$existRow || (int)$existRow['worker_id'] !== $workerId) {
                $this->json(['ok' => false, 'msg' => '记录不存在或不属于该劳动者，已拒绝保存。']);
            }
        }
        $v = \App\Core\Validator::make($_POST, [
            'disease' => 'required|max:60',
            'diag_date' => 'date',
            'hospital' => 'max:60',
            'outcome' => 'max:60',
            'remark' => 'max:200',
        ], ['disease' => '疾病名称', 'diag_date' => '诊断时间', 'hospital' => '诊断医院', 'outcome' => '治疗结果', 'remark' => '备注']);
        if ($v->fails()) {
            $this->json(['ok' => false, 'msg' => implode(' ', $v->errors())]);
        }
        $data = $v->validated();
        if ($rowId > 0) {
            WorkerIllness::update($rowId, $data);
            $msg = '既往病史已更新。';
        } else {
            $data['worker_id'] = $workerId;
            $rowId = WorkerIllness::insert($data);
            $msg = '既往病史已保存。';
        }
        $this->log($rowId > 0 ? 'update' : 'create', 'worker_illness', $rowId, '劳动者#' . $workerId . ' 既往病史：' . $data['disease']);
        $this->json(['ok' => true, 'msg' => $msg]);
    }

    public function deleteIllness(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $workerId = (int)$this->post('worker_id');
        $row = WorkerIllness::find($id);
        // 校验行归属：仅允许删除当前劳动者名下的病史行
        if (!$row || $workerId <= 0 || (int)$row['worker_id'] !== $workerId) {
            $this->json(['ok' => false, 'msg' => '记录不存在或不属于该劳动者，已拒绝删除。']);
        }
        WorkerIllness::delete($id);
        $this->log('delete', 'worker_illness', $id, '删除既往病史');
        $this->json(['ok' => true, 'msg' => '已删除。']);
    }

    /* ==================== 职业病诊断 ==================== */

    public function saveDisease(): void
    {
        $this->verifyCsrf();
        $workerId = (int)$this->post('worker_id');
        $rowId = (int)$this->post('row_id');
        if (!Worker::find($workerId)) {
            $this->json(['ok' => false, 'msg' => '劳动者不存在。']);
        }
        // 编辑时校验行归属：row_id 必须属于当前劳动者，防止跨人改写
        if ($rowId > 0) {
            $existRow = Disease::find($rowId);
            if (!$existRow || (int)$existRow['worker_id'] !== $workerId) {
                $this->json(['ok' => false, 'msg' => '记录不存在或不属于该劳动者，已拒绝保存。']);
            }
        }
        $v = \App\Core\Validator::make($_POST, [
            'name' => 'required|max:60',
            'diag_date' => 'date',
            'hospital' => 'max:60',
            'level' => 'max:30',
            'handling' => 'max:200',
            'remark' => 'max:200',
        ], ['name' => '职业病名称', 'diag_date' => '诊断时间', 'hospital' => '诊断医院', 'level' => '诊断级别', 'handling' => '处理情况', 'remark' => '备注']);
        if ($v->fails()) {
            $this->json(['ok' => false, 'msg' => implode(' ', $v->errors())]);
        }
        $data = $v->validated();
        $data['is_suspected'] = $this->post('is_suspected') === '1' ? 1 : 0;
        if ($rowId > 0) {
            Disease::update($rowId, $data);
            $msg = '职业病诊断已更新。';
        } else {
            $data['worker_id'] = $workerId;
            $rowId = Disease::insert($data);
            $msg = '职业病诊断已保存。';
        }
        $this->log($rowId > 0 ? 'update' : 'create', 'disease', $rowId, '劳动者#' . $workerId . ' 职业病诊断：' . $data['name']);
        $this->json(['ok' => true, 'msg' => $msg]);
    }

    public function deleteDisease(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $workerId = (int)$this->post('worker_id');
        $row = Disease::find($id);
        // 校验行归属：仅允许删除当前劳动者名下的诊断行
        if (!$row || $workerId <= 0 || (int)$row['worker_id'] !== $workerId) {
            $this->json(['ok' => false, 'msg' => '记录不存在或不属于该劳动者，已拒绝删除。']);
        }
        Disease::delete($id);
        $this->log('delete', 'disease', $id, '删除职业病诊断');
        $this->json(['ok' => true, 'msg' => '已删除。']);
    }

    /* ==================== 删除 ==================== */

    public function delete(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $worker = Worker::find($id);
        if (!$worker) {
            $this->json(['ok' => false, 'msg' => '记录不存在。']);
        }
        $counts = [
            'exam' => (int)Database::value('SELECT COUNT(*) FROM exam_records WHERE worker_id = ?', [$id]),
            'disease' => (int)Database::value('SELECT COUNT(*) FROM diseases WHERE worker_id = ?', [$id]),
            'docs' => (int)Database::value(
                "SELECT COUNT(*) FROM documents WHERE (link_type = 'worker' AND link_id = ?)
                   OR (link_type = 'exam_record' AND link_id IN (SELECT id FROM exam_records WHERE worker_id = ?))",
                [$id, $id]
            ),
            'training' => (int)Database::value('SELECT COUNT(*) FROM training_participants WHERE worker_id = ?', [$id]),
            'ppe' => (int)Database::value('SELECT COUNT(*) FROM ppe_issues WHERE worker_id = ?', [$id]),
        ];
        // 体检结果与职业病登记属于需要长期保存的监护档案：物理删除会绕过保管年限设置，
        // 一律拒绝；请改状态为「离职/离岗/退休」保留历史档案。
        if ($counts['exam'] > 0 || $counts['disease'] > 0) {
            $this->json([
                'ok' => false,
                'msg' => '该劳动者名下有 ' . $counts['exam'] . ' 条体检记录、' . $counts['disease']
                    . ' 条职业病登记，属于长期保存的监护档案，不能物理删除。请将状态改为「离职/离岗/退休」以保留历史档案。',
            ]);
        }
        $related = $counts['docs'] + $counts['training'] + $counts['ppe'];
        if ((string)$this->post('confirmed') !== '1' && $related > 0) {
            $this->json([
                'ok' => false, 'msg' => 'confirm_docs', 'need_confirm' => true,
                'doc_count' => $counts['docs'], 'training_count' => $counts['training'], 'ppe_count' => $counts['ppe'],
            ]);
        }
        if ((string)$worker['photo'] !== '') {
            Uploader::remove((string)$worker['photo']);
        }
        Worker::delete($id);   // 职业史/既往病史/培训参训/劳保领用级联删除；文档由触发器解除关联
        $this->log('delete', 'worker', $id, '删除劳动者信息卡：' . $worker['name'] . '（' . $worker['archive_no'] . '）');
        $this->json([
            'ok' => true,
            'msg' => '已删除该劳动者、职业史、既往病史、' . $counts['training'] . ' 条参训记录和 '
                . $counts['ppe'] . ' 条劳保领用记录；' . $counts['docs'] . ' 份文档已解除关联并保留。',
        ]);
    }

    /* ==================== 表6-1 打印 ==================== */

    public function printCard(): void
    {
        $id = (int)$this->get('id');
        $worker = Worker::detail($id);
        if (!$worker) {
            $this->flash('danger', '劳动者不存在。');
            $this->redirect('workers/index');
        }
        $this->viewRaw('prints/worker_card', [
            'worker' => $worker,
            'company' => \App\Models\Company::row(),
        ]);
    }

    /* ==================== CSV 批量导入 ==================== */

    public function import(): void
    {
        $this->view('workers/import', [], 'CSV 批量导入', 'import');
    }

    public function importTemplate(): void
    {
        $headers = ['姓名', '性别', '出生日期', '籍贯', '婚姻状况', '文化程度', '嗜好', '身份证号', '联系电话', '参加工作时间', '入职本单位时间', '在职状态', '离岗日期', '备注'];
        $sample = ['张三', '男', '1990-01-01', '山东济南', '已婚', '高中', '吸烟', '', '13800000000', '2012-07-01', '2020-03-15', '在职', '', '示例行，导入前请删除'];
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"OHAS-import-template.csv\"; filename*=UTF-8''" . rawurlencode('OHAS-劳动者导入模板.csv'));
        header('Cache-Control: no-store');
        echo Csv::template($headers, $sample);
        exit;
    }

    public function importUpload(): void
    {
        $this->verifyCsrf();
        if (empty($_FILES['csv']) || $_FILES['csv']['error'] === UPLOAD_ERR_NO_FILE) {
            $this->flash('danger', '请选择 CSV 文件。');
            $this->redirect('workers/import');
        }
        $up = Uploader::save($_FILES['csv'], 'imports', ['csv'], 4 * 1024 * 1024, ['text/plain', 'text/csv', 'application/vnd.ms-excel', 'application/csv', 'application/octet-stream']);
        if (!$up['ok']) {
            $this->flash('danger', $up['msg']);
            $this->redirect('workers/import');
        }
        $abs = Uploader::absPath($up['path']);
        $raw = $abs !== null ? @file_get_contents($abs) : false;
        Uploader::remove($up['path']);   // 含身份证号的临时文件读完即删
        if ($raw === false) {
            $this->flash('danger', '读取上传文件失败，请重试。');
            $this->redirect('workers/import');
        }
        $text = Csv::normalize($raw);
        $rows = Csv::parse($text);
        if (count($rows) < 1) {
            $this->flash('danger', '文件为空或无法解析。');
            $this->redirect('workers/import');
        }

        $result = $this->validateImportRows(array_shift($rows), $rows);
        if ($result === null) {
            $this->redirect('workers/import');
        }
        $_SESSION['import_rows'] = $result['rows'];
        $_SESSION['import_summary'] = $result['summary'];
        $this->redirect('workers/importPreview');
    }

    /** @return null|array{rows:array,summary:array} */
    private function validateImportRows(array $header, array $dataRows): ?array
    {
        $map = [
            '姓名' => 'name', '性别' => 'gender', '出生日期' => 'birth_date', '籍贯' => 'native_place',
            '婚姻状况' => 'marital_status', '文化程度' => 'education', '嗜好' => 'hobby', '身份证号' => 'id_card',
            '联系电话' => 'phone', '参加工作时间' => 'work_start_date', '入职本单位时间' => 'entry_date',
            '在职状态' => 'status', '离岗日期' => 'leave_date', '备注' => 'remark',
        ];
        $colMap = [];
        foreach ($header as $i => $h) {
            $h = trim((string)$h, "\xEF\xBB\xBF \t");
            if (isset($map[$h])) {
                $colMap[$i] = $map[$h];
            }
        }
        if (!isset(array_flip($colMap)['name'])) {
            $this->flash('danger', '表头缺少「姓名」列，请使用系统提供的导入模板。');
            return null;
        }
        if (count($dataRows) > 1000) {
            $this->flash('danger', '单次导入不超过 1000 行，请分批上传。');
            return null;
        }

        $rows = [];
        $seen = [];
        $summary = ['new' => 0, 'update' => 0, 'error' => 0];
        foreach ($dataRows as $idx => $cells) {
            $line = $idx + 2;   // 含表头的行号
            $row = array_fill_keys(array_values($map), '');   // CSV 缺少的可选列按空值处理
            foreach ($colMap as $i => $field) {
                $row[$field] = trim((string)($cells[$i] ?? ''));
            }
            $errs = [];
            if ($row['name'] === '') {
                $errs[] = '姓名为空';
            } elseif (mb_strlen($row['name']) > 30) {
                $errs[] = '姓名长度不能超过 30 字';
            }
            $idCard = strtoupper($row['id_card']);
            $row['id_card'] = $idCard;   // 统一大写入库（末位 x → X），保证按身份证号去重/更新能对上
            if ($idCard !== '' && !IdCard::valid($idCard)) {
                $errs[] = '身份证号校验未通过';
            }
            if ($idCard !== '') {
                if (isset($seen[$idCard])) {
                    $errs[] = '身份证号在文件内重复（第' . $seen[$idCard] . '行）';
                } else {
                    $seen[$idCard] = $line;
                }
            }
            foreach (['birth_date', 'work_start_date', 'entry_date', 'leave_date'] as $df) {
                if ($row[$df] !== '') {
                    $norm = $this->normDate($row[$df]);
                    if ($norm === null) {
                        $errs[] = '日期「' . $row[$df] . '」格式不正确';
                    } else {
                        $row[$df] = $norm;
                    }
                }
            }
            if ($row['status'] !== '' && !in_array($row['status'], Dict::workerStatus(), true)) {
                $errs[] = '在职状态应为：' . implode('/', Dict::workerStatus());
            }
            if ($row['status'] !== '' && $row['status'] !== '在职' && $row['leave_date'] === '') {
                $errs[] = '非在职人员必须填写离岗日期（用于收尾职业史）';
            }
            if ($row['leave_date'] !== '' && $row['leave_date'] > date('Y-m-d')) {
                $errs[] = '离岗日期不能晚于今天';
            }
            // 与手工建档表单同一套字段级规则：枚举 / 长度 / 电话格式
            $vRow = \App\Core\Validator::make($row, [
                'name'           => 'max:30',
                'gender'         => 'in:男,女',
                'native_place'   => 'max:60',
                'marital_status' => 'max:10',
                'education'      => 'max:20',
                'hobby'          => 'max:60',
                'phone'          => 'phone',
                'remark'         => 'max:500',
            ], [
                'name' => '姓名', 'gender' => '性别', 'native_place' => '籍贯', 'marital_status' => '婚姻状况',
                'education' => '文化程度', 'hobby' => '嗜好', 'phone' => '联系电话', 'remark' => '备注',
            ]);
            if ($vRow->fails()) {
                $errs = array_merge($errs, array_values($vRow->errors()));
            }
            $action = 'error';
            if (!$errs) {
                if ($idCard !== '' && Worker::idCardTaken($idCard)) {
                    $action = 'update';
                } else {
                    $action = 'new';
                }
                // 身份证自动推导
                if ($idCard !== '' && $row['birth_date'] === '') {
                    $row['birth_date'] = IdCard::birth($idCard) ?? '';
                }
                if ($idCard !== '' && $row['gender'] === '') {
                    $row['gender'] = IdCard::gender($idCard) ?? '';
                }
                $summary[$action]++;
            } else {
                $summary['error']++;
            }
            $rows[] = [
                'line' => $line, 'name' => $row['name'], 'id_card' => $idCard,
                'action' => $action, 'errors' => $errs, 'data' => $errs ? [] : $row,
            ];
        }
        return ['rows' => $rows, 'summary' => $summary];
    }

    /** 支持 2026-01-02 / 2026/1/2 / 2026.1.2 → 2026-01-02 */
    private function normDate(string $s): ?string
    {
        if (preg_match('/^(\d{4})[\/\.\-](\d{1,2})[\/\.\-](\d{1,2})$/', trim($s), $m)) {
            $iso = sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
            return \App\Core\Validator::isDate($iso) ? $iso : null;
        }
        return null;
    }

    public function importPreview(): void
    {
        $rows = $_SESSION['import_rows'] ?? null;
        if (!is_array($rows)) {
            $this->flash('danger', '导入会话已过期，请重新上传文件。');
            $this->redirect('workers/import');
        }
        $this->view('workers/import_preview', [
            'rows' => $rows,
            'summary' => $_SESSION['import_summary'] ?? ['new' => 0, 'update' => 0, 'error' => 0],
        ], '导入预览确认', 'import');
    }

    public function importCommit(): void
    {
        $this->verifyCsrf();
        $rows = $_SESSION['import_rows'] ?? null;
        if (!is_array($rows)) {
            $this->flash('danger', '导入会话已过期，请重新上传文件。');
            $this->redirect('workers/import');
        }
        $results = [];
        $done = ['new' => 0, 'update' => 0, 'error' => 0];
        // 整批放进一个事务：1000 行不再逐行提交；单行失败只回滚该语句，不影响其它行
        $pdo = Database::pdo();
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }
        foreach ($rows as $r) {
            if ($r['action'] === 'error') {
                $results[] = ['line' => $r['line'], 'name' => $r['name'], 'action' => 'error', 'msg' => implode('；', $r['errors'])];
                $done['error']++;
                continue;
            }
            $data = $r['data'];
            try {
                if ($r['action'] === 'update') {
                    $exist = Database::row('SELECT id, archive_no FROM workers WHERE id_card = ?', [$r['id_card']]);
                    if ($exist === null) {
                        // 预览与提交之间该劳动者已被删除：不再按 id=0 空更新、误报成功
                        $results[] = ['line' => $r['line'], 'name' => $r['name'], 'action' => 'error',
                            'msg' => '劳动者已被删除，未更新（请重新导入）'];
                        $done['error']++;
                        continue;
                    }
                    $id = (int)$exist['id'];
                    $upd = array_filter($data, fn ($v) => $v !== '');
                    if (($upd['status'] ?? '') === '在职') {
                        $upd['leave_date'] = '';
                    }
                    Worker::update($id, $upd);
                    if (($upd['status'] ?? '') !== '在职' && !empty($upd['leave_date'])) {
                        WorkerPost::closeAllOpen($id, (string)$upd['leave_date']);
                    }
                    $results[] = ['line' => $r['line'], 'name' => $r['name'], 'action' => 'update', 'msg' => '已更新（档案号 ' . $exist['archive_no'] . '）'];
                } else {
                    $data['archive_no'] = ArchiveNo::next();
                    if (($data['status'] ?? '') === '') {
                        $data['status'] = '在职';   // 状态列留空：按默认「在职」，避免触发 CHECK 约束
                    }
                    $id = Worker::insert($data);
                    $results[] = ['line' => $r['line'], 'name' => $r['name'], 'action' => 'new', 'msg' => '已建档（档案号 ' . $data['archive_no'] . '）'];
                }
                $done[$r['action']]++;
            } catch (\Throwable $e) {
                $results[] = ['line' => $r['line'], 'name' => $r['name'], 'action' => 'error', 'msg' => '写入失败：' . mb_substr($e->getMessage(), 0, 120)];
                $done['error']++;
            }
        }
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->commit();
        }
        unset($_SESSION['import_rows'], $_SESSION['import_summary']);
        $this->log('import', 'worker', null, "CSV导入完成：新增 {$done['new']}，更新 {$done['update']}，失败 {$done['error']}");
        $_SESSION['import_result'] = ['results' => $results, 'done' => $done];
        $this->redirect('workers/importResult');
    }

    public function importResult(): void
    {
        $result = $_SESSION['import_result'] ?? null;
        if (!$result) {
            $this->redirect('workers/import');
        }
        $this->view('workers/import_result', [
            'results' => $result['results'],
            'done' => $result['done'],
        ], '导入结果报告', 'import');
    }
}
