<?php
/**
 * 危害因素检测管理：
 *  - 检测报告（监测检测档案目录第 8~9 项）：定期检测 / 日常监测 / 现状评价 / 控制效果评价，
 *    报告原件（PDF/扫描件）归档；
 *  - 检测明细：逐条登记检测点结果与限值判定（合格/超标），限值口径
 *    GBZ 2.1-2019（含第2号修改单）与 GBZ 2.2-2007；
 *  - 表6-2：按人自动归集到信息卡查看页，并支持按人打印；
 *  - 检测结果查询：跨报告按场所/岗位/危害因素/判定筛选。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Helpers\Dict;
use App\Helpers\DocLink;
use App\Models\DetectionItem;
use App\Models\DetectionReport;
use App\Models\Hazard;

class DetectionController extends Controller
{
    /* ==================== 检测报告 ==================== */

    public function index(): void
    {
        $year     = (string)$this->get('year');
        $category = (string)$this->get('category');
        $q        = (string)$this->get('q');
        $page     = max(1, (int)$this->get('page', 1));
        $data = DetectionReport::search($year, $category, $q, $page, (int)cfg('page.size'));
        $this->view('detections/index', [
            'data'      => $data,
            'year'      => $year, 'category' => $category, 'q' => $q,
            'years'     => DetectionReport::years(),
            'categories' => Dict::detectionCategories(),
            'stats'     => DetectionReport::yearStats(date('Y')),
        ], '危害因素检测报告', 'detections');
    }

    public function create(): void
    {
        $this->form([
            'id' => 0, 'report_no' => '', 'agency' => '', 'category' => '定期检测',
            'test_date' => '', 'report_date' => '', 'conclusion' => '', 'file_path' => '',
            'remark' => '',
        ]);
    }

    public function edit(): void
    {
        $id = (int)$this->get('id');
        $report = DetectionReport::find($id);
        if (!$report) {
            $this->flash('danger', '检测报告不存在。');
            $this->redirect('detections/index');
        }
        $this->form($report);
    }

    private function form(array $report): void
    {
        if ($this->isPost()) {
            $this->verifyCsrf();
            $isEdit = (int)($report['id'] ?? 0) > 0;
            $back = $isEdit ? 'detections/edit' : 'detections/create';
            $params = $isEdit ? ['id' => (int)$report['id']] : [];
            $v = Validator::make($_POST, [
                'report_no'   => 'required|max:60',
                'agency'      => 'required|max:100',
                'category'    => 'required|in:' . implode(',', Dict::detectionCategories()),
                'test_date'   => 'required|date|not_future',
                'report_date' => 'date|not_future|not_before:test_date',
                'conclusion'  => 'max:500',
                'remark'      => 'max:500',
            ], [
                'report_no' => '报告编号', 'agency' => '检测机构', 'category' => '报告类别',
                'test_date' => '检测日期', 'report_date' => '报告日期',
                'conclusion' => '结论摘要', 'remark' => '备注',
            ]);
            if ($v->fails()) {
                $this->failBack($back, $v->errors(), $_POST, $params);
            }
            $data = $v->validated();

            // 报告编号唯一性
            $dupWhere = 'report_no = ?';
            $dupParams = [$data['report_no']];
            if ($isEdit) {
                $dupWhere .= ' AND id != ?';
                $dupParams[] = (int)$report['id'];
            }
            if ((int)Database::value("SELECT COUNT(*) FROM detection_reports WHERE {$dupWhere}", $dupParams) > 0) {
                $this->failBack($back, ['report_no' => '该报告编号已存在。'], $_POST, $params);
            }

            if ($isEdit) {
                DetectionReport::update((int)$report['id'], $data);
                $this->log('update', 'detection_report', (int)$report['id'], '修改检测报告：' . $data['report_no']);
                $this->flash('success', '检测报告已更新。');
                $this->redirect('detections/show', ['id' => (int)$report['id']]);
            }
            // 并发/双开窗口同时提交时，由 uq_detection_reports_no 唯一索引兜底
            try {
                $newId = DetectionReport::insert($data);
            } catch (\PDOException $e) {
                if (stripos($e->getMessage(), 'UNIQUE') !== false) {
                    $this->failBack($back, ['report_no' => '该报告编号已存在。'], $_POST, $params);
                }
                throw $e;
            }
            $this->log('create', 'detection_report', $newId, '登记检测报告：' . $data['report_no'] . ' ' . $data['agency']);
            $this->flash('success', '检测报告已登记，可在下方上传报告原件并录入检测明细（每行一个检测点）。');
            $this->redirect('detections/show', ['id' => $newId]);
        }
        $this->view('detections/form', [
            'report'     => $report,
            'categories' => Dict::detectionCategories(),
        ], ((int)($report['id'] ?? 0)) > 0 ? '编辑检测报告' : '登记检测报告', 'detections');
    }

    public function delete(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $report = DetectionReport::find($id);
        if (!$report) {
            $this->json(['ok' => false, 'msg' => '检测报告不存在。']);
        }
        // 被“超标点整改”引用（来源报告/复测报告）时不能删——外键受限，直接删会落到 500。
        $refs = (int)Database::value(
            'SELECT COUNT(*) FROM rectifications WHERE report_id = ? OR verify_report_id = ?',
            [$id, $id]
        );
        if ($refs > 0) {
            $this->json(['ok' => false, 'msg' => '该检测报告已被 ' . $refs . ' 条“超标点整改”记录引用（来源报告或复测报告），请先到“超标点整改”删除或改选这些记录，再删除报告。']);
        }
        DetectionReport::delete($id);   // 明细随报告级联删除；名下原件由触发器解除关联，仍保留在分类文档
        $this->log('delete', 'detection_report', $id, '删除检测报告：' . $report['report_no']);
        $this->json(['ok' => true, 'msg' => '检测报告及其明细已删除。']);
    }

    /* ==================== 报告详情与明细 ==================== */

    public function show(): void
    {
        $id = (int)$this->get('id');
        $report = DetectionReport::find($id);
        if (!$report) {
            $this->flash('danger', '检测报告不存在。');
            $this->redirect('detections/index');
        }
        $report = DetectionReport::withCount($report);
        $items = DetectionItem::forReport($id);
        // 下拉只列启用项，但本报告明细已引用的停用场所/岗位/危害因素一并带回，
        // 避免编辑回填时无选项、保存后静默丢失关联
        $extraWpIds = [];
        $extraPostIds = [];
        $extraHazIds = [];
        foreach ($items as $it) {
            if (!empty($it['workplace_id'])) { $extraWpIds[(int)$it['workplace_id']] = 1; }
            if (!empty($it['post_id'])) { $extraPostIds[(int)$it['post_id']] = 1; }
            if (!empty($it['hazard_id'])) { $extraHazIds[(int)$it['hazard_id']] = 1; }
        }
        $workplaces = Database::rows("SELECT * FROM workplaces WHERE status = '启用' ORDER BY name");
        $posts = Database::rows(
            "SELECT p.*, w.name AS workplace_name FROM posts p
             JOIN workplaces w ON w.id = p.workplace_id
             WHERE p.status = '启用' ORDER BY w.name, p.name"
        );
        $hazards = Hazard::groupedActive();
        $haveWp = [];
        foreach ($workplaces as $w) { $haveWp[(int)$w['id']] = true; }
        foreach (array_keys($extraWpIds) as $wid) {
            if (!isset($haveWp[$wid])) {
                $w = Database::row('SELECT * FROM workplaces WHERE id = ?', [$wid]);
                if ($w) { $w['name'] .= '（已停用）'; $workplaces[] = $w; }
            }
        }
        $havePost = [];
        foreach ($posts as $p) { $havePost[(int)$p['id']] = true; }
        foreach (array_keys($extraPostIds) as $pid) {
            if (!isset($havePost[$pid])) {
                $p = Database::row(
                    'SELECT p.*, w.name AS workplace_name FROM posts p JOIN workplaces w ON w.id = p.workplace_id WHERE p.id = ?',
                    [$pid]
                );
                if ($p) { $p['name'] .= '（已停用）'; $posts[] = $p; }
            }
        }
        $extraHazRows = [];
        foreach (array_keys($extraHazIds) as $hid) {
            $h = Database::row('SELECT * FROM hazards WHERE id = ?', [$hid]);
            if ($h) { $extraHazRows[] = $h; }
        }
        if ($extraHazRows) {
            // groupedActive 结构：[分类 => [行, ...]]；停用项补入其分类（不存在则初始化）
            $knownIds = [];
            foreach ($hazards as $list) {
                foreach ($list as $h) { $knownIds[(int)$h['id']] = true; }
            }
            foreach ($extraHazRows as $h) {
                if (!isset($knownIds[(int)$h['id']])) {
                    $h['name'] .= '（已停用）';
                    $hazards[$h['category']] = $hazards[$h['category']] ?? [];
                    $hazards[$h['category']][] = $h;
                }
            }
        }
        $this->view('detections/show', [
            'report'     => $report,
            'docs'       => DocLink::forLink('detection_report', $id),
            'items'      => $items,
            'rectify'    => DetectionItem::rectifyCandidates($id),   // 超标点 → 整改草稿候选
            'workplaces' => $workplaces,
            'posts'      => $posts,
            'hazards'    => $hazards,
        ], '检测明细 - ' . $report['report_no'], 'detections');
    }

    /**
     * 从超标明细一键生成整改记录草稿。
     * 只新增、不改旧记录：同报告下文字相同的整改记录已存在则跳过，可重复点击。
     */
    public function genRectify(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('report_id');
        $report = DetectionReport::find($id);
        if (!$report) {
            $this->flash('danger', '检测报告不存在。');
            $this->redirect('detections/index');
        }
        $res = DetectionItem::generateRectifications($id);
        if ($res['total'] === 0) {
            $this->flash('warning', '本报告没有判定为“超标”的明细，无需生成整改记录。');
            $this->redirect('detections/show', ['id' => $id]);
        }
        $added = $res['added'];
        $skipped = $res['skipped'];
        $this->log('create', 'rectification', $id, '从超标明细生成整改草稿：' . $report['report_no'] . ' 新增 ' . $added . ' 条，已存在 ' . $skipped . ' 条');
        if ($added === 0) {
            $this->flash('info', '本报告的超标点已全部有整改记录（' . $skipped . ' 条），未新增。');
        } else {
            $this->flash('success', '已生成 ' . $added . ' 条整改记录草稿' . ($skipped > 0 ? '（另有 ' . $skipped . ' 条已存在，已跳过）' : '') . '，请到“超标点整改”补全整改方案。');
        }
        $this->redirect('ledgers/index', ['key' => 'rectifications']);
    }

    /** AJAX：取单条明细（编辑回填） */
    public function itemRow(): void
    {
        $id = (int)$this->get('id');
        $item = DetectionItem::find($id);
        if (!$item) {
            $this->json(['ok' => false, 'msg' => '明细不存在。']);
        }
        $this->json(['ok' => true, 'row' => $item]);
    }

    /** 保存明细（详情页子表单，AJAX） */
    public function itemSave(): void
    {
        $this->verifyCsrf();
        $reportId = (int)$this->post('report_id');
        $report = DetectionReport::find($reportId);
        if (!$report) {
            $this->json(['ok' => false, 'msg' => '检测报告不存在。']);
        }
        $rowId = (int)$this->post('row_id');
        $isEdit = $rowId > 0;
        if ($isEdit) {
            $exist = DetectionItem::find($rowId);
            if (!$exist || (int)$exist['report_id'] !== $reportId) {
                $this->json(['ok' => false, 'msg' => '明细不存在或不属于该报告。']);
            }
        }
        $v = Validator::make($_POST, [
            'hazard_id'  => 'required|int',
            'test_point' => 'max:100',
            'result'     => 'max:100',
            'unit'       => 'max:30',
            'limit_value' => 'max:100',
            'sample_type' => 'max:20',
            'verdict'    => 'max:10',
            'protection' => 'max:200',
            'remark'     => 'max:200',
        ], [
            'hazard_id' => '危害因素', 'test_point' => '检测点', 'result' => '检测结果',
            'unit' => '计量单位', 'limit_value' => '职业接触限值', 'sample_type' => '采样类型', 'verdict' => '判定结论',
            'protection' => '防护措施', 'remark' => '备注',
        ]);
        if ($v->fails()) {
            $this->json(['ok' => false, 'msg' => implode(' ', $v->errors())]);
        }
        $data = $v->validated();
        $postId = (int)$this->post('post_id');
        $workplaceId = (int)$this->post('workplace_id');
        $hazardId = (int)$data['hazard_id'];

        if (!in_array($data['verdict'], array_merge([''], DetectionItem::VERDICTS), true)) {
            $this->json(['ok' => false, 'msg' => '判定结论应为「合格」或「超标」。']);
        }
        if (!in_array($data['sample_type'], array_merge([''], DetectionItem::SAMPLE_TYPES), true)) {
            $this->json(['ok' => false, 'msg' => '采样类型应为 TWA / STEL / MAC / 峰，或留空表示未指定。']);
        }
        // 已填检测结果/限值却未判定 → 后续合格率会把“未判定”误算成合格
        if (($data['result'] !== '' || $data['limit_value'] !== '') && $data['verdict'] === '') {
            $this->json(['ok' => false, 'msg' => '已填检测结果或接触限值时，必须选择判定结论「合格」或「超标」；结果尚未出具请留空。']);
        }
        // 关联校验：场所 / 岗位至少其一；选了岗位则场所自动对齐
        if ($postId === 0 && $workplaceId === 0) {
            $this->json(['ok' => false, 'msg' => '请至少关联一个作业场所或岗位（关联岗位后才能按人归集表6-2）。']);
        }
        if ($postId > 0) {
            $post = Database::row('SELECT id, workplace_id FROM posts WHERE id = ?', [$postId]);
            if (!$post) {
                $this->json(['ok' => false, 'msg' => '所选岗位不存在。']);
            }
            // 场所未选或与岗位所属场所不一致时，一律对齐为岗位所属场所（保证按人归集口径正确）
            $workplaceId = (int)$post['workplace_id'];
        } elseif ($workplaceId > 0 && !Database::value('SELECT id FROM workplaces WHERE id = ?', [$workplaceId])) {
            $this->json(['ok' => false, 'msg' => '所选作业场所不存在。']);
        }
        if (!Database::value('SELECT id FROM hazards WHERE id = ?', [$hazardId])) {
            $this->json(['ok' => false, 'msg' => '所选危害因素不存在。']);
        }

        $data['report_id'] = $reportId;
        $data['post_id'] = $postId > 0 ? $postId : null;
        $data['workplace_id'] = $workplaceId > 0 ? $workplaceId : null;
        $data['hazard_id'] = $hazardId;
        // 保存时冻结当前可归集岗位，后续岗位危害配置增删不会改写历史检测归集结果
        $data['matched_post_ids'] = DetectionItem::snapshotPostIds($postId, $workplaceId > 0 ? $workplaceId : null, $hazardId);

        if ($isEdit) {
            DetectionItem::update($rowId, $data);
            $this->log('update', 'detection_item', $rowId, '修改检测明细：' . $report['report_no']);
        } else {
            $newId = DetectionItem::insert($data);
            $this->log('create', 'detection_item', $newId, '登记检测明细：' . $report['report_no']);
        }
        $this->json(['ok' => true, 'msg' => '检测明细已保存。']);
    }

    public function itemDelete(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $item = DetectionItem::find($id);
        if (!$item) {
            $this->json(['ok' => false, 'msg' => '明细不存在。']);
        }
        DetectionItem::delete($id);
        $this->log('delete', 'detection_item', $id, '删除检测明细：报告#' . $item['report_id']);
        $this->json(['ok' => true, 'msg' => '已删除。']);
    }

    /* ==================== 跨报告结果查询 ==================== */

    public function items(): void
    {
        $filters = [
            'year'         => (string)$this->get('year'),
            'workplace_id' => (int)$this->get('workplace_id'),
            'post_id'      => (int)$this->get('post_id'),
            'hazard_id'    => (int)$this->get('hazard_id'),
            'verdict'      => (string)$this->get('verdict'),
            'q'            => (string)$this->get('q'),
        ];
        $page = max(1, (int)$this->get('page', 1));
        $data = DetectionItem::search($filters, $page, (int)cfg('page.size'));
        $this->view('detections/items', [
            'data'       => $data,
            'filters'    => $filters,
            'years'      => DetectionReport::years(),
            'workplaces' => Database::rows('SELECT * FROM workplaces ORDER BY name'),
            'posts'      => Database::rows(
                'SELECT p.*, w.name AS workplace_name FROM posts p
                 JOIN workplaces w ON w.id = p.workplace_id ORDER BY w.name, p.name'
            ),
            'hazards'    => Hazard::groupedActive(),
        ], '检测结果查询', 'detection_items');
    }

    /* ==================== 表6-2 打印 ==================== */

    public function printItems(): void
    {
        $workerId = (int)$this->get('worker_id');
        $worker = \App\Models\Worker::detail($workerId);
        if (!$worker) {
            $this->flash('danger', '劳动者不存在。');
            $this->redirect('workers/index');
        }
        $this->viewRaw('prints/detection_items', [
            'worker'  => $worker,
            'company' => \App\Models\Company::row(),
        ]);
    }

    /* ==================== 年度视图：按 作业场所 → 岗位 → 年度 ==================== */

    /**
     * 检测报告年度视图（与个人页检测列对称）：
     * 行 = 岗位（按场所分组，含“未指定岗位”的按场所检测点），列 = 年度；
     * 格子状态：有报告且已传原件 = 已归档（间接）；有报告无原件 = 仅登记；
     * 配置了危害因素的岗位无当年报告 = 待归档；未配危害且无报告的岗位不显示。
     */
    public function yearView(): void
    {
        $workplaceId = (int)$this->get('workplace_id');
        $range = (string)$this->get('range');
        if (!in_array($range, ['3y', '5y', 'all'], true)) {
            $range = '3y';
        }
        $asOfYear = (int)date('Y');

        // 检测明细（含报告、场所、岗位、因素、判定）
        $where = '';
        $params = [];
        if ($workplaceId > 0) {
            $where = ' WHERE di.workplace_id = ?';
            $params[] = $workplaceId;
        }
        $items = Database::rows(
            'SELECT di.id, di.report_id, di.post_id, di.workplace_id, di.hazard_id, di.verdict, di.matched_post_ids,
                    dr.report_no, dr.test_date, dr.category,
                    p.name AS post_name, wpl.name AS workplace_name
             FROM detection_items di
             JOIN detection_reports dr ON dr.id = di.report_id
             LEFT JOIN posts p ON p.id = di.post_id
             LEFT JOIN workplaces wpl ON wpl.id = di.workplace_id'
            . $where . ' ORDER BY dr.test_date DESC, di.id',
            $params
        );

        // 各报告已挂原件数
        $docCounts = [];
        foreach (Database::rows(
            "SELECT link_id, COUNT(*) AS c FROM documents WHERE link_type = 'detection_report' GROUP BY link_id"
        ) as $r) {
            $docCounts[(int)$r['link_id']] = (int)$r['c'];
        }

        // 年份轴：报告中出现的年份（近 N 年内；all 时取全部年份）
        $yearSet = [];
        foreach ($items as $it) {
            $y = (int)substr((string)$it['test_date'], 0, 4);
            if ($y > 0) {
                $yearSet[$y] = true;
            }
        }
        $yearSet[$asOfYear] = true;   // 当年恒在列（便于看出“今年还没测”）
        if ($range !== 'all') {
            $minY = $asOfYear - ($range === '3y' ? 2 : 4);
            $yearSet = array_filter($yearSet, fn ($y) => $y >= $minY);
        }
        $years = array_keys($yearSet);
        rsort($years);   // 新年份在左，与列表页年份筛选一致

        // 格子归集：workplace → post → year → [报告（去重，合并因素与超标数）]
        $cells = [];
        // 场所 → 危害因素 → 配置了该因素的岗位。检测点只指明「场所 + 因素」（未指明岗位）时，
        // 同时记入该场所内配置了该因素的岗位——与个人页（DetectionItem::matchedItemIds）同一口径，
        // 否则同一份报告在个人页算“已汇入”、在年度视图里这些岗位却显示“待检测”。
        $postsByWpHazard = [];
        foreach (Database::rows('SELECT p.id, p.workplace_id, ph.hazard_id FROM posts p JOIN post_hazards ph ON ph.post_id = p.id') as $r) {
            $postsByWpHazard[(int)$r['workplace_id']][(int)$r['hazard_id']][] = (int)$r['id'];
        }
        $credit = function (int $wpKey, int $poKey, int $y, array $it) use (&$cells, $docCounts): void {
            $rid = (int)$it['report_id'];
            $cell = &$cells[$wpKey][$poKey][$y];
            if (!isset($cell['reports'][$rid])) {
                $cell['reports'][$rid] = [
                    'id' => $rid, 'report_no' => (string)$it['report_no'],
                    'test_date' => (string)$it['test_date'], 'category' => (string)$it['category'],
                    'hazards' => [], 'exceeds' => 0,
                    'has_doc' => ($docCounts[$rid] ?? 0) > 0,
                ];
            }
            if ((string)$it['verdict'] === '超标') {
                $cell['reports'][$rid]['exceeds']++;
            }
        };
        foreach ($items as $it) {
            $y = (int)substr((string)$it['test_date'], 0, 4);
            if ($y <= 0 || ($range !== 'all' && $y < $asOfYear - ($range === '3y' ? 2 : 4))) {
                continue;
            }
            $wpKey = (int)$it['workplace_id'];
            $poKey = (int)$it['post_id'];
            $credit($wpKey, $poKey, $y, $it);
            if ($poKey === 0 && $wpKey > 0) {
                // 与个人页同口径：明细保存时冻结了 matched_post_ids 就按快照归集（不随岗位配置漂移），
                // 旧明细快照为空才回退到当前「场所 + 因素」配置
                $snap = array_values(array_filter(
                    array_map('intval', explode(',', (string)($it['matched_post_ids'] ?? ''))),
                    fn (int $v): bool => $v > 0
                ));
                if ($snap) {
                    foreach ($snap as $pid) {
                        $credit($wpKey, $pid, $y, $it);
                    }
                } elseif ((int)$it['hazard_id'] > 0) {
                    foreach ($postsByWpHazard[$wpKey][(int)$it['hazard_id']] ?? [] as $pid) {
                        $credit($wpKey, $pid, $y, $it);
                    }
                }
            }
        }

        // 岗位清单：配置了危害因素的岗位 ∪ 出现在检测明细里的岗位（限场所）
        $postSql = 'SELECT p.id, p.name, p.workplace_id, p.status,
                    (SELECT COUNT(*) FROM post_hazards ph WHERE ph.post_id = p.id) AS hazard_count,
                    w.name AS workplace_name
                    FROM posts p JOIN workplaces w ON w.id = p.workplace_id';
        $postArgs = [];
        if ($workplaceId > 0) {
            $postSql .= ' WHERE p.workplace_id = ?';
            $postArgs[] = $workplaceId;
        }
        $postSql .= ' ORDER BY w.name, p.name';
        $posts = Database::rows($postSql, $postArgs);

        $rows = [];         // [wpKey => ['name'=>, 'posts'=> [poKey => row]]]
        foreach ($posts as $p) {
            $poKey = (int)$p['id'];
            $wpKey = (int)$p['workplace_id'];
            $hasCell = isset($cells[$wpKey][$poKey]);
            if ((int)$p['hazard_count'] === 0 && !$hasCell) {
                continue;   // 未配危害且从未检测：不显示
            }
            $rows[$wpKey]['name'] = (string)$p['workplace_name'];
            $rows[$wpKey]['posts'][$poKey] = [
                'id' => $poKey,
                'name' => (string)$p['name'],
                'hazard_count' => (int)$p['hazard_count'],
                'stopped' => $p['status'] !== '启用',
                'needs' => (int)$p['hazard_count'] > 0 && $p['status'] === '启用',   // 停用岗位不需要检测，不计“待检测”
            ];
        }
        // “未指定岗位”的检测点（post_id 为空，按场所归组）
        foreach ($cells as $wpKey => $byPost) {
            if (!isset($byPost[0])) {
                continue;
            }
            $wpName = (string)Database::value('SELECT name FROM workplaces WHERE id = ?', [$wpKey]);
            $rows[$wpKey]['name'] = $wpName ?: ('#' . $wpKey);
            $rows[$wpKey]['posts'][0] = ['id' => 0, 'name' => '（未指定岗位，按场所）', 'hazard_count' => 0, 'stopped' => false, 'needs' => false];
        }
        ksort($rows);
        foreach ($rows as &$r) {
            ksort($r['posts']);
        }
        unset($r);

        // 汇总：各状态格子计数（仅计数，无得分；todo 只数配置了危害因素的岗位）
        // 同时把格子状态预计算好传给视图（避免视图反向调用控制器私有方法）
        $summary = ['done_indirect' => 0, 'reg_only' => 0, 'todo' => 0];
        $states = [];   // wpKey -> poKey -> year -> ['state','reports','n']
        foreach ($rows as $wpKey => $r) {
            foreach ($r['posts'] as $poKey => $po) {
                foreach ($years as $y) {
                    $st = $this->yearCellState($cells, (int)$wpKey, (int)$poKey, (int)$po['hazard_count'], $y);
                    $states[$wpKey][$poKey][$y] = $st;
                    if ($st['state'] === 'todo' && empty($po['needs'])) {
                        continue;   // 未配危害 / 已停用的岗位、“未指定岗位”行不数 todo
                    }
                    $summary[$st['state']]++;
                }
            }
        }

        $this->view('detections/year_view', [
            'rows'        => $rows,
            'states'      => $states,
            'years'       => $years,
            'range'       => $range,
            'workplaceId' => $workplaceId,
            'workplaces'  => Database::rows('SELECT id, name FROM workplaces ORDER BY name'),
            'summary'     => $summary,
        ], '检测年度视图', 'detections');
    }

    /** 年度格子状态（供视图与汇总共用）：有报告有原件=done_indirect；有报告无原件=reg_only；配置危害岗位无报告=todo */
    private function yearCellState(array $cells, int $wpKey, int $poKey, int $hazardCount, int $year): array
    {
        $reports = $cells[$wpKey][$poKey][$year]['reports'] ?? [];
        $withDoc = array_values(array_filter($reports, fn ($r) => !empty($r['has_doc'])));
        return [
            'state' => \App\Models\DetectionItem::reportState($reports),
            'reports' => $reports,
            'n' => count($withDoc) ?: count($reports),
        ];
    }
}
