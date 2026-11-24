<?php
/**
 * 职业健康检查管理：
 *  - 体检批次（表5-1）：每年集中体检一次登记，含机构 / 种类 / 应检人数 / 批次报告
 *  - 检查记录（表6-3）：逐人登记检查结论与处理情况，自动关联信息卡
 *  - 应检名单：按在岗接害人员批量生成待检记录
 *  - 表6-3 打印：按人输出历次检查记录
 * 检查种类与结论口径依据 GBZ 188-2025（2026-08-01 施行，代替 GBZ 188-2014）。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\Dict;
use App\Helpers\DocLink;
use App\Models\ExamBatch;
use App\Models\ExamRecord;
use App\Models\Worker;

class ExamController extends Controller
{
    /* ==================== 体检批次 ==================== */

    public function index(): void
    {
        $year = (string)$this->get('year');
        $type = (string)$this->get('type');
        $q    = (string)$this->get('q');
        $page = max(1, (int)$this->get('page', 1));
        $data = ExamBatch::search($year, $type, $q, $page, (int)cfg('page.size'));
        $this->view('exams/index', [
            'data'     => $data,
            'year'     => $year, 'type' => $type, 'q' => $q,
            'years'    => ExamBatch::years(),
            'types'    => Dict::examTypes(),
            'yearStats' => ExamBatch::yearStats(date('Y')),
        ], '在职体检批次', 'exams');
    }

    public function create(): void
    {
        $this->form([
            'id' => 0, 'exam_date' => '', 'agency' => '', 'exam_type' => '在岗期间',
            'planned_count' => 0, 'remark' => '',
        ]);
    }

    public function edit(): void
    {
        $id = (int)$this->get('id');
        $batch = ExamBatch::find($id);
        if (!$batch) {
            $this->flash('danger', '批次不存在。');
            $this->redirect('exams/index');
        }
        $this->form($batch);
    }

    private function form(array $batch): void
    {
        if ($this->isPost()) {
            $this->verifyCsrf();
            $isEdit = (int)($batch['id'] ?? 0) > 0;
            $back = $isEdit ? 'exams/edit' : 'exams/create';
            $params = $isEdit ? ['id' => (int)$batch['id']] : [];
            $v = \App\Core\Validator::make($_POST, [
                'exam_date'     => 'required|date',
                'agency'        => 'required|max:100',
                'exam_type'     => 'required|in:' . implode(',', Dict::examTypes()),
                'planned_count' => 'int',
                'remark'        => 'max:500',
            ], [
                'exam_date' => '检查日期', 'agency' => '检查机构', 'exam_type' => '检查种类',
                'planned_count' => '应检人数', 'remark' => '备注',
            ]);
            if ($v->fails()) {
                $this->failBack($back, $v->errors(), $_POST, $params);
            }
            $data = $v->validated();
            $data['planned_count'] = (int)$data['planned_count'];

            if ($isEdit) {
                ExamBatch::update((int)$batch['id'], $data);
                $this->log('update', 'exam_batch', (int)$batch['id'], '修改体检批次：' . $data['exam_date'] . ' ' . $data['agency']);
                $this->flash('success', '批次已更新。');
            } else {
                $newId = ExamBatch::insert($data);
                $this->log('create', 'exam_batch', $newId, '新增体检批次：' . $data['exam_date'] . ' ' . $data['agency']);
                $this->flash('success', '批次已创建，可继续生成应检名单或逐人登记检查记录。');
            }
            $this->redirect('exams/index');
        }
        $this->view('exams/form', [
            'batch' => $batch,
            'types' => Dict::examTypes(),
        ], ((int)($batch['id'] ?? 0)) > 0 ? '编辑体检批次' : '新增体检批次', 'exams');
    }

    public function delete(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $batch = ExamBatch::find($id);
        if (!$batch) {
            $this->done(false, '批次不存在。', 'exams/index');
        }
        ExamBatch::delete($id);   // 批次内检查记录保留（batch_id 置空），个人体检时间线不受影响
        $this->log('delete', 'exam_batch', $id, '删除体检批次：' . $batch['exam_date'] . ' ' . $batch['agency']);
        $this->done(true, '批次已删除；其下检查记录已保留为独立记录。', 'exams/index');
    }

    /* ==================== 检查记录 ==================== */

    public function records(): void
    {
        $batchId = (int)$this->get('batch_id');
        $q = (string)$this->get('q');
        $conclusion = (string)$this->get('conclusion');
        $page = max(1, (int)$this->get('page', 1));
        $abnormal = (string)$this->get('abnormal') === '1';
        $dispRaw = (string)$this->get('disposition');
        $disposition = in_array($dispRaw, ['register', 'pending', 'notice', 'nonwritten', 'notice_doc', 'transfer', 'closed'], true) ? $dispRaw : '';
        $data = ExamRecord::search($batchId, $q, $conclusion, $page, (int)cfg('page.size'), $abnormal, $disposition);
        $batch = $batchId > 0 ? ExamBatch::find($batchId) : null;
        if ($batch !== null) {
            $batch = ExamBatch::withCount($batch);
        }
        $this->view('exams/records', [
            'data' => $data,
            'batch' => $batch,
            'batchId' => $batchId, 'q' => $q, 'conclusion' => $conclusion, 'abnormal' => $abnormal,
            'disposition' => $disposition,
            'dispositionStats' => $abnormal ? ExamRecord::dispositionStats() : null,
            'batchDocs' => $batchId > 0 ? DocLink::forLink('exam_batch', $batchId) : [],
            'conclusions' => array_merge(['待出结论'], Dict::examConclusions()),
        ], $batch ? '检查记录 - ' . $batch['exam_date'] . ' ' . $batch['agency']
            : ($abnormal ? '职业健康检查异常结果登记' : '历次职业健康检查记录'), 'exam_records');
    }

    public function recordCreate(): void
    {
        $batchId = (int)$this->get('batch_id');
        $batch = $batchId > 0 ? ExamBatch::find($batchId) : null;
        $this->recordForm([
            'id' => 0,
            'batch_id' => $batchId,
            'worker_id' => (int)$this->get('worker_id'),
            'exam_date' => $batch['exam_date'] ?? date('Y-m-d'),
            'exam_type' => $batch['exam_type'] ?? '在岗期间',
            'agency' => $batch['agency'] ?? '',
            'conclusion' => '', 'post_name' => '', 'hazards_snapshot' => '',
            'handling' => '', 'signed_at' => '', 'site_handling' => '',
            'remark' => '',
        ]);
    }

    public function recordEdit(): void
    {
        $id = (int)$this->get('id');
        $record = ExamRecord::find($id);
        if (!$record) {
            $this->flash('danger', '记录不存在。');
            $this->back();
        }
        $this->recordForm($record);
    }

    private function recordForm(array $record): void
    {
        if ($this->isPost()) {
            $this->verifyCsrf();
            $isEdit = (int)($record['id'] ?? 0) > 0;
            $back = $isEdit ? 'exams/recordEdit' : 'exams/recordCreate';
            $params = $isEdit ? ['id' => (int)$record['id']] : [];
            // 编辑态一律锁定为原记录的劳动者：下拉 disabled 不随表单提交，
            // 客户端伪造 worker_id 也不采纳——否则已生成的职业病登记（diseases.worker_id）
            // 不会跟随变更，两边会脱钩
            if ($isEdit) {
                $_POST['worker_id'] = (string)$record['worker_id'];
            }
            $v = \App\Core\Validator::make($_POST, [
                'worker_id'       => 'required|int',
                'exam_date'       => 'required|date|not_future',
                'exam_type'       => 'required|in:' . implode(',', Dict::examTypes()),
                'conclusion'      => 'max:20',
                'agency'          => 'max:100',
                'post_name'       => 'max:60',
                'hazards_snapshot' => 'max:500',
                'handling'        => 'max:500',
                'notice_method'   => 'max:20',
                'signed_at'       => 'date|not_future|not_before:exam_date',
                'site_handling'   => 'max:500',
                'remark'          => 'max:500',
            ], [
                'worker_id' => '受检劳动者', 'exam_date' => '检查日期', 'exam_type' => '检查种类',
                'conclusion' => '检查结论', 'agency' => '检查机构', 'post_name' => '受检时岗位',
                'hazards_snapshot' => '受检时接触危害因素', 'handling' => '人员处理情况',
                'signed_at' => '本人签字确认日期', 'site_handling' => '现场处理情况', 'remark' => '备注',
            ]);
            if ($v->fails()) {
                $this->failBack($back, $v->errors(), $_POST, $params);
            }
            $data = $v->validated();
            $workerId = (int)$data['worker_id'];
            if (!Worker::find($workerId)) {
                $this->failBack($back, ['worker_id' => '受检劳动者不存在。'], $_POST, $params);
            }
            if (!in_array($data['conclusion'], array_merge([''], Dict::examConclusions()), true)) {
                $this->failBack($back, ['conclusion' => '检查结论取值不正确。'], $_POST, $params);
            }
            // batch_id 为可空外键：无效或未指定时存 NULL（存 0 会触发外键约束）
            $batchId = (int)$this->post('batch_id');
            if ($batchId > 0 && !ExamBatch::find($batchId)) {
                $batchId = 0;
            }
            $data['batch_id'] = $batchId > 0 ? $batchId : null;
            // 同批次同人防重复（应通过编辑既有记录更新结果）
            if ($data['batch_id'] > 0) {
                $dupWhere = 'batch_id = ? AND worker_id = ?';
                $dupParams = [$data['batch_id'], $workerId];
                if ($isEdit) {
                    $dupWhere .= ' AND id != ?';
                    $dupParams[] = (int)$record['id'];
                }
                if ((int)Database::value("SELECT COUNT(*) FROM exam_records WHERE {$dupWhere}", $dupParams) > 0) {
                    $this->failBack($back, ['worker_id' => '该劳动者在本批次已有检查记录，请直接编辑原记录。'], $_POST, $params);
                }
            }
            // 未填快照时自动带出职业史快照（按检查日期取「受检当时」的岗位，而非当前岗位）
            if ($data['post_name'] === '' && $data['hazards_snapshot'] === '') {
                $snap = ExamRecord::snapshotFor($workerId, $data['exam_date']);
                $data['post_name'] = $snap['post_name'];
                $data['hazards_snapshot'] = $snap['hazards_snapshot'];
            }

            if ($isEdit) {
                $kind = ExamRecord::diseaseKind($data['conclusion']);
                $linked = null;
                $synced = 0;
                $needsReg = false;
                $kindCleared = false;
                Database::tx(function () use ($record, $data, $kind, &$linked, &$synced, &$needsReg, &$kindCleared): void {
                    ExamRecord::update((int)$record['id'], $data);
                    $linked = Database::row('SELECT id, is_suspected FROM diseases WHERE exam_record_id = ?', [(int)$record['id']]);
                    if ($linked && $kind !== null && $kind !== (int)$linked['is_suspected']) {
                        \App\Models\Disease::update((int)$linked['id'], ['is_suspected' => $kind]);
                        $synced = 1;
                    } elseif ($linked && $kind === null) {
                        $kindCleared = true;
                    } elseif (!$linked && $kind !== null) {
                        $needsReg = true;
                    }
                });
                $this->log('update', 'exam_record', (int)$record['id'], '修改检查记录：劳动者#' . $workerId . ' ' . $data['exam_date'] . ($synced ? '（同步登记性质）' : ''));
                $this->flash('success', '检查记录已更新。');
                if ($synced > 0) {
                    $this->flash('info', '体检结论变化已同步到关联的职业病登记性质，请核对病名与诊断信息。');
                }
                if ($needsReg) {
                    $this->flash('warning', '体检结论为疑似职业病/职业病，请点击「生成表5-3/5-4 登记」完成闭环前置条件。');
                }
                if ($kindCleared) {
                    $this->flash('warning', '体检结论已不再是疑似职业病/职业病；历史登记作为监护档案保留，请到信息卡人工核对或归档。');
                }
            } else {
                $newId = ExamRecord::insert($data);
                $this->log('create', 'exam_record', $newId, '登记检查记录：劳动者#' . $workerId . ' ' . $data['exam_date']);
                $this->flash('success', '检查记录已登记，并已关联到该劳动者信息卡。');
            }
            $this->redirect('exams/records', $batchId > 0 ? ['batch_id' => $batchId] : []);
        }
        // 单条登记可选全部人员：检查种类可现场切换（离岗时/随访/诊断/复查），
        // 且非在职但有接害史的人员也可能需要补登历史体检；不应在下拉里凭空消失。
        $workers = Database::rows(
            "SELECT w.id, w.name, w.archive_no, w.gender, w.status,
                    (SELECT post_name FROM worker_posts wp WHERE wp.worker_id = w.id AND wp.end_date IS NULL ORDER BY wp.id DESC LIMIT 1) AS current_post
             FROM workers w ORDER BY w.id ASC"
        );
        $this->view('exams/record_form', [
            'record' => $record,
            'workers' => $workers,
            'types' => Dict::examTypes(),
            'conclusions' => Dict::examConclusions(),
            'docs' => ((int)($record['id'] ?? 0)) > 0 ? DocLink::forLink('exam_record', (int)$record['id']) : [],
        ], ((int)($record['id'] ?? 0)) > 0 ? '编辑检查记录' : '登记检查记录', 'exam_records');
    }

    public function recordDelete(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $record = ExamRecord::find($id);
        if (!$record) {
            $this->done(false, '记录不存在。', 'exams/records');
        }
        ExamRecord::delete($id);   // 名下原件由触发器解除关联，仍保留在分类文档
        $this->log('delete', 'exam_record', $id, '删除检查记录：劳动者#' . $record['worker_id'] . ' ' . $record['exam_date']);
        $this->done(true, '已删除。', 'exams/records');
    }

    /**
     * 由体检记录生成职业病 / 疑似职业病登记（表5-3 / 表5-4）。
     * 结论「疑似职业病」→ 疑似；「职业病」→ 确诊。登记写入 diseases 并记录来源 exam_record_id，
     * 一条体检记录只对应一条登记；病名、诊断机构等需人工确认后保存。
     */
    public function diseaseRegister(): void
    {
        $id = (int)($this->isPost() ? $this->post('id') : $this->get('id'));
        $record = ExamRecord::find($id);
        if (!$record) {
            $this->flash('danger', '检查记录不存在。');
            $this->redirect('exams/records');
        }
        $worker = Worker::find((int)$record['worker_id']);
        $kind = ExamRecord::diseaseKind($record['conclusion']);
        $recUrl = ['batch_id' => (int)$record['batch_id']];
        $recUrl = $recUrl['batch_id'] > 0 ? $recUrl : [];
        if (!$worker) {
            $this->flash('danger', '劳动者不存在。');
            $this->redirect('exams/records', $recUrl);
        }
        if ($kind === null) {
            $this->flash('warning', '只有检查结论为「疑似职业病」或「职业病」的记录才能生成登记；请先在检查记录中修改结论。');
            $this->redirect('exams/records', $recUrl);
        }
        $existing = Database::row('SELECT id FROM diseases WHERE exam_record_id = ?', [$id]);
        if ($existing) {
            $this->flash('info', '该检查记录已生成过登记，可在名单页或劳动者信息卡中维护。');
            $this->redirect('patients/index', ['type' => $kind === 1 ? 'suspected' : 'confirmed']);
        }

        if ($this->isPost()) {
            $this->verifyCsrf();
            $back = 'exams/diseaseRegister';
            $v = \App\Core\Validator::make($_POST, [
                'name' => 'required|max:60',
                'diag_date' => 'date',
                'hospital' => 'max:60',
                'level' => 'max:30',
                'handling' => 'max:200',
                'remark' => 'max:200',
            ], ['name' => '职业病名称', 'diag_date' => '诊断时间', 'hospital' => '诊断机构', 'level' => '诊断级别', 'handling' => '处理情况', 'remark' => '备注']);
            if ($v->fails()) {
                $this->failBack($back, $v->errors(), $_POST, ['id' => $id]);
            }
            $data = $v->validated();
            $data['worker_id'] = (int)$record['worker_id'];
            $data['is_suspected'] = $kind;
            $data['exam_record_id'] = $id;
            // 并发生成时可能撞 uq_diseases_exam_record 唯一索引：转成友好提示而不是 500
            try {
                $newId = \App\Models\Disease::insert($data);
            } catch (\PDOException $e) {
                $this->flash('warning', '该检查记录已生成过登记，请直接在名单页或劳动者信息卡中维护。');
                $this->redirect('patients/index', ['type' => $kind === 1 ? 'suspected' : 'confirmed']);
            }
            $this->log('create', 'disease', $newId, '体检记录#' . $id . ' 生成' . ($kind === 1 ? '疑似职业病' : '职业病') . '登记：' . $data['name']);
            $this->flash('success', '已生成' . ($kind === 1 ? '疑似职业病（表5-4）' : '职业病患者（表5-3）') . '登记，并同步显示在劳动者信息卡「职业病诊断」中。');
            $this->redirect('patients/index', ['type' => $kind === 1 ? 'suspected' : 'confirmed']);
        }

        $this->view('exams/disease_register', [
            'record' => $record,
            'worker' => $worker,
            'kind' => $kind,
        ], $kind === 1 ? '生成疑似职业病登记（表5-4）' : '生成职业病登记（表5-3）', 'exam_records');
    }

    /**
     * AJAX：选择受检人后带出岗位与接害因素快照。
     * exam_date 可选：带上时按「受检当时」取职业史，否则取当前岗位。
     */
    public function workerSnapshot(): void
    {
        $workerId = (int)$this->get('worker_id');
        $worker = Worker::find($workerId);
        if (!$worker) {
            $this->json(['ok' => false, 'msg' => '劳动者不存在。']);
        }
        $snap = ExamRecord::snapshotFor($workerId, $this->get('exam_date'));
        $this->json([
            'ok' => true,
            'name' => $worker['name'],
            'archive_no' => $worker['archive_no'],
            'post_name' => $snap['post_name'],
            'hazards' => $snap['hazards_snapshot'],
        ]);
    }

    /* ==================== 应检名单 ==================== */

    public function pick(): void
    {
        $batchId = (int)$this->get('batch_id');
        $batch = ExamBatch::find($batchId);
        if (!$batch) {
            $this->flash('danger', '批次不存在。');
            $this->redirect('exams/index');
        }
        $q = (string)$this->get('q');
        // 离岗时/离岗后随访/职业病诊断/复查按批次种类放开；其他种类仍在职或有接害史
        $where = ExamRecord::candidateWhere((string)($batch['exam_type'] ?? ''));
        $sql = "SELECT w.id, w.name, w.archive_no, w.gender, w.status,
                       (SELECT post_name FROM worker_posts wp WHERE wp.worker_id = w.id AND wp.end_date IS NULL AND wp.post_id IS NOT NULL ORDER BY wp.id DESC LIMIT 1) AS current_post,
                       (SELECT hazards_snapshot FROM worker_posts wp WHERE wp.worker_id = w.id AND wp.end_date IS NULL AND wp.post_id IS NOT NULL ORDER BY wp.id DESC LIMIT 1) AS current_hazards
                FROM workers w
                WHERE {$where}";
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (w.name LIKE ? OR w.archive_no LIKE ? OR w.id_card LIKE ?)';
            $params = ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%'];
        }
        $sql .= ' ORDER BY w.id ASC';
        $rows = Database::rows($sql, $params);
        $existing = array_flip(ExamRecord::workerIdsOfBatch($batchId));
        $this->view('exams/pick', [
            'batch' => $batch,
            'rows' => $rows,
            'existing' => $existing,
            'q' => $q,
        ], '生成应检名单 - ' . $batch['exam_date'] . ' ' . $batch['agency'], 'exams');
    }

    public function pickCreate(): void
    {
        $this->verifyCsrf();
        $batchId = (int)$this->post('batch_id');
        $batch = ExamBatch::find($batchId);
        if (!$batch) {
            $this->json(['ok' => false, 'msg' => '批次不存在。']);
        }
        if (trim((string)$batch['exam_date']) === '') {
            // 批次无检查日期会复制出一批无日期记录，时间线归入「未填日期」且被年度统计漏掉
            $this->json(['ok' => false, 'msg' => '该批次未填写检查日期，请先编辑批次补上日期再生成名单。']);
        }
        $ids = array_map('intval', $this->postArray('worker_ids'));
        $ids = array_values(array_unique(array_filter($ids, fn ($i) => $i > 0)));
        if (!$ids) {
            $this->json(['ok' => false, 'msg' => '请至少勾选一名劳动者。']);
        }
        $created = 0;
        $skipped = 0;
        // 事务包裹：已有名单的读取放进事务（快照读），配合 (batch_id, worker_id)
        // 唯一索引，双开标签页 / 重放请求也不会产生重复记录；批量中途失败整体回滚
        try {
            Database::tx(function () use ($ids, $batchId, $batch, &$created, &$skipped): void {
                $existing = array_flip(ExamRecord::workerIdsOfBatch($batchId));
                foreach ($ids as $wid) {
                    if (isset($existing[$wid])) {
                        $skipped++;
                        continue;
                    }
                    $worker = Worker::find($wid);
                    if (!$worker || !ExamRecord::canExamine($worker, (string)$batch['exam_type'])) {
                        $skipped++;
                        continue;
                    }
                    $snap = ExamRecord::snapshotFor($wid, (string)$batch['exam_date']);
                    ExamRecord::insert([
                        'worker_id' => $wid,
                        'batch_id' => $batchId,
                        'exam_date' => (string)$batch['exam_date'],
                        'exam_type' => (string)$batch['exam_type'],
                        'conclusion' => '',
                        'agency' => (string)$batch['agency'],
                        'post_name' => $snap['post_name'],
                        'hazards_snapshot' => $snap['hazards_snapshot'],
                    ]);
                    $created++;
                }
            });
        } catch (\PDOException $e) {
            // 唯一索引冲突：另一请求已生成同名记录，提示后由前端刷新页面重新核对
            $this->json(['ok' => false, 'msg' => '部分劳动者已在其他请求中生成了记录，请刷新页面后重新核对名单。']);
        }
        $this->log('create', 'exam_record', null, '批次#' . $batchId . ' 批量生成应检名单 ' . $created . ' 人' . ($skipped ? '，跳过 ' . $skipped . ' 人' : ''));
        // 服务端闪存：跳转后提示不丢
        $msg = '已生成 ' . $created . ' 条待检记录（结论待出）';
        if ($skipped > 0) {
            $msg .= '，跳过 ' . $skipped . ' 人（已登记或不符合该检查种类受检范围）';
        }
        $msg .= '，可在检查记录列表中逐人登记结果。';
        if ($created > 0) {
            $this->flash('success', $msg);
        } else {
            $this->flash('warning', '未生成新记录：' . ($skipped > 0 ? '勾选的 ' . $skipped . ' 人都已登记或不符合该检查种类受检范围。' : '请勾选需要生成记录的劳动者。'));
        }
        $planned = (int)($batch['planned_count'] ?? 0);
        if ($planned > 0) {
            $registered = (int)Database::value('SELECT COUNT(*) FROM exam_records WHERE batch_id = ?', [$batchId]);
            if ($registered > $planned) {
                $this->flash('warning', '该批次已有 ' . $registered . ' 条检查记录，超过手工填写的应检人数 ' . $planned . '；请核对名单并回填应检人数，避免完成率口径失真。');
            }
        }
        $this->json([
            'ok' => true,
            'msg' => $msg,
            'redirect' => url('exams/records', ['batch_id' => $batchId]),
        ]);
    }

    /* ==================== 表6-3 打印 ==================== */

    public function printRecords(): void
    {
        $workerId = (int)$this->get('worker_id');
        $worker = \App\Models\Worker::detail($workerId);
        if (!$worker) {
            $this->flash('danger', '劳动者不存在。');
            $this->redirect('workers/index');
        }
        $this->viewRaw('prints/exam_records', [
            'worker' => $worker,
            'company' => \App\Models\Company::row(),
        ]);
    }
}
