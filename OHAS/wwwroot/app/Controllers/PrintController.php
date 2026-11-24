<?php
/**
 * 规范表样打印中心
 * ------------------------------------------------------------
 * 23 张规范表样的统一打印入口，版式规范见 App\Helpers\PrintSpec，
 * 样式统一引用 public/assets/print.css（与已验收的表6-1 同等质量）。
 *
 *   prints/index            打印中心（全部表样 + 记录数 + 方向提示）
 *   prints/sheet?t=表2-1    任意表样打印（列表类 / 表单类自动分发）
 *   prints/sheet?t=表2-8&id=3  表单类指定记录（不传 id 逐份全部打印）
 *
 * 数据归集在本控制器完成，视图只做排版：保证打印口径与业务页一致。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\Dict;
use App\Helpers\LedgerPlace;
use App\Helpers\PrintSpec;

class PrintController extends Controller
{
    /* ==================== 打印中心 ==================== */

    public function index(): void
    {
        $tables = [];
        foreach (PrintSpec::catalog() as $no => $spec) {
            $spec['count'] = $this->rowCount($no);
            $spec['url'] = in_array($spec['kind'], ['list', 'form'], true)
                ? url('prints/sheet', ['t' => $no])
                : url($spec['route'] ?? 'archive/index');
            $tables[$no] = $spec;
        }
        // 年度表（表2-6 / 表4-3）：提供年份选择，默认最近一个有数据的年份
        $yearOptions = [
            '表2-6' => array_map(
                fn ($r) => (string)$r['y'],
                Database::rows('SELECT DISTINCT substr(issued_on,1,4) y FROM ppe_issues WHERE issued_on <> "" ORDER BY y ASC')
            ),
            '表4-3' => array_map(
                fn ($r) => (string)$r['y'],
                Database::rows("SELECT DISTINCT substr(test_date,1,4) y FROM detection_reports WHERE test_date IS NOT NULL AND test_date <> '' ORDER BY y ASC")
            ),
        ];
        $this->view('prints/index', [
            'tables'  => $tables,
            'yearOptions' => $yearOptions,
            'company' => \App\Models\Company::name(),
        ], '表样打印中心', 'prints');
    }

    /* ==================== 清单打印 ==================== */

    /** 单位资料清单打印：A4 横向；状态用文字不用颜色（黑白打印可读） */
    public function checklist(): void
    {
        $checklist = \App\Helpers\ArchiveChecklist::all();
        $this->viewRaw('prints/checklist', [
            'checklist' => $checklist,
            'groups'    => \App\Helpers\ArchiveChecklist::GROUPS,
            'company'   => \App\Models\Company::name(),
        ]);
    }

    /** 内部编制资料在线打印（按目录项容器记录） */
    public function archiveMaterial(): void
    {
        $id = (int)$this->get('id');
        $m = Database::row('SELECT * FROM archive_materials WHERE id = ?', [$id]);
        if ($m === null || trim((string)$m['body']) === '') {
            $this->flash('danger', '资料不存在或正文为空。');
            $this->redirect('archive/index');
        }
        $entry = \App\Helpers\ArchiveCatalog::filingItem((string)$m['group_key'], (string)$m['filing_seq']);
        $group = \App\Helpers\ArchiveCatalog::group((string)$m['group_key']);
        $this->viewRaw('prints/archive_material', [
            'material' => $m,
            'entry'    => $entry ?? [],
            'group'    => $group ?? [],
            'company'  => \App\Models\Company::name(),
            'no'       => '内部资料',
            'backUrl'  => \App\Helpers\ArchiveCatalog::filingItemUrl((string)$m['group_key'], (string)$m['filing_seq']),
            'backLabel'=> '返回资料管理',
        ]);
        $this->log('print', 'archive_material', $id, '打印内部资料：' . (string)$m['title']);
    }

    /* ==================== 个人资料目录打印 ==================== */

    /** 个人资料目录：A4 纵向；表头含完整身份证号，页脚注明含个人信息 */
    public function workerFiles(): void
    {
        $id = (int)$this->get('id');
        $archive = \App\Helpers\WorkerArchive::build($id, 'all');
        if ($archive === null) {
            $this->flash('danger', '劳动者不存在。');
            $this->redirect('workers/index');
        }
        $this->log('print', 'worker_files', $id, '打印个人资料目录：' . $archive['header']['name']);
        $this->viewRaw('prints/worker_files', [
            'a'         => $archive,
            'company'   => \App\Models\Company::name(),
        ]);
    }

    /* ==================== 统一分发 ==================== */

    public function sheet(): void
    {
        $no = trim((string)$this->get('t'));
        $spec = PrintSpec::get($no);
        if ($spec === null) {
            $this->flash('danger', '表样编号不存在，请从打印中心进入。');
            $this->redirect('prints/index');
        }
        switch ($spec['kind']) {
            case 'list':
                $this->renderList($no, $spec);
                break;
            case 'form':
                $this->renderForm($no, $spec);
                break;
            default: // person：转回对应业务页
                $this->redirect($spec['route'] ?? 'archive/index');
        }
    }

    /* ==================== 列表类表样 ==================== */

    private function renderList(string $no, array $spec): void
    {
        $company = \App\Models\Company::name();
        $rows = [];
        $metaLeft = '单位名称：' . ($company ?: '＿＿＿＿＿＿');
        $metaRight = '共 0 条记录';
        $groups = null; // 表4-1 行组
        $year = (string)$this->get('year', '');

        switch ($no) {
            case '表2-1':
                $rows = array_map(fn ($r) => [
                    $r['plan_date'] ?: '', $r['plan_content'] ?: '', $r['implementation'] ?: '',
                    $r['owner_name'] ?: '', $r['remark'] ?: '',
                ], Database::rows('SELECT * FROM mgmt_plan_checks ORDER BY plan_date ASC, id ASC'));
                $metaRight = '填表日期：' . date('Y-m-d');
                break;

            case '表2-3':
                $rows = array_map(fn ($r) => [
                    $r['purpose'] ?: '', $r['work_content'] ?: '', $r['amount'] ?? '',
                    $r['owner_name'] ?: '', $r['remark'] ?: '',
                ], Database::rows('SELECT * FROM mgmt_budgets ORDER BY id ASC'));
                $metaRight = '年度：' . date('Y');
                break;

            case '表2-4':
                $rows = array_map(fn ($r) => [
                    $r['name'] ?: '', $r['model'] ?: '', $r['workplace'] ?: '', $r['purpose'] ?: '',
                    $r['installer'] ?: '', $r['accept_date'] ?: '', $r['status'] ?: '',
                ], Database::rows(
                    'SELECT f.*, w.name AS workplace FROM facilities f
                     LEFT JOIN workplaces w ON w.id = f.workplace_id
                     ORDER BY w.name ASC, f.id ASC'
                ));
                $metaRight = '填表日期：' . date('Y-m-d');
                break;

            case '表2-6':
                $names = LedgerPlace::names();
                $ppeCfg = \App\Helpers\LedgerCatalog::get('ppe_issues');
                // 年度表：按领取年份过滤，默认取最近一个有数据的年份
                $years = Database::rows('SELECT DISTINCT substr(issued_on,1,4) y FROM ppe_issues WHERE issued_on <> "" ORDER BY y ASC');
                if ($year === '' && $years) {
                    $year = (string)end($years)['y'];
                }
                $rows = [];
                $sql = 'SELECT p.*, w.name AS worker_name
                     FROM ppe_issues p
                     LEFT JOIN workers w ON w.id = p.worker_id';
                $params = [];
                if ($year !== '') {
                    $sql .= ' WHERE substr(p.issued_on,1,4) = ?';
                    $params[] = $year;
                }
                $sql .= ' ORDER BY p.issued_on ASC, p.id ASC';
                $ppeList = Database::rows($sql, $params);
                // 多选危害因素：同一件防护用品可对应多种危害因素
                $ppeHaz = \App\Models\LedgerHazard::namesBy('ppe_issues', array_column($ppeList, 'id'));
                foreach ($ppeList as $r) {
                    // 与业务列表页同口径：关联「场所 · 岗位」+ 补充说明文本
                    $place = $ppeCfg !== null
                        ? (string)LedgerPlace::display($ppeCfg, $r, 'department', $names)
                        : trim((string)$r['department']);
                    $rows[] = [
                        $place, $ppeHaz[(int)$r['id']] ?? '', $r['item_name'] ?: '',
                        $r['model'] ?: '', $r['quantity'] ?? '', $r['worker_name'] ?: '',
                        $r['issued_on'] ?: '', $r['remark'] ?: '',
                    ];
                }
                $metaRight = '年度：' . ($year !== '' ? $year : date('Y'));
                break;

            case '表2-7':
                $rows = [];
                $signList = Database::rows(
                    'SELECT s.*, w.name AS workplace
                     FROM warning_signs s
                     LEFT JOIN workplaces w ON w.id = s.workplace_id
                     ORDER BY w.name ASC, s.location ASC, s.id ASC'
                );
                // 多选危害因素：同一块标识可对应多种危害因素
                $signHaz = \App\Models\LedgerHazard::namesBy('warning_signs', array_column($signList, 'id'));
                foreach ($signList as $r) {
                    $content = trim(($signHaz[(int)$r['id']] ?? '') . ($r['remark'] ? '：' . $r['remark'] : ''), '：');
                    $rows[] = [
                        $r['workplace'] ?: '', $r['sign_type'] ?: '', $r['location'] ?: '',
                        $content, '', $r['install_date'] ?: '', '',
                    ];
                }
                $metaRight = '填表日期：' . date('Y-m-d');
                break;

            case '表2-10':
                $rows = [];
                foreach (Database::rows('SELECT * FROM drills ORDER BY drill_date ASC, id ASC') as $r) {
                    $rows[] = [
                        $r['drill_date'] ?: '', $r['name'] ?: '',
                        (string)($r['participants'] ?? ''), $r['organizer'] ?: '', $r['summary'] ?: '',
                    ];
                }
                $metaRight = '填表日期：' . date('Y-m-d');
                break;

            case '表2-11':
                $rows = [];
                foreach (Database::rows('SELECT * FROM emergency_facilities ORDER BY id ASC') as $r) {
                    $rows[] = [
                        $r['name'] ?: '', $r['model'] ?: '', (string)($r['quantity'] ?? ''),
                        $r['location'] ?: '', $r['check_date'] ?: '', $r['remark'] ?: '',
                    ];
                }
                $metaRight = '填表日期：' . date('Y-m-d');
                break;

            case '表4-1':
                $names = LedgerPlace::names();
                $groups = [];
                $rows = [];
                foreach (['设备', '材料', '化学品'] as $type) {
                    $list = Database::rows(
                        'SELECT m.* FROM materials m WHERE m.type = ? ORDER BY m.id ASC',
                        [$type]
                    );
                    if (!$list) {
                        continue;
                    }
                    // 多选危害因素：同一设备/材料/化学品可能产生多种危害因素
                    $hazards = \App\Models\LedgerHazard::hazardsBy('materials', array_column($list, 'id'));
                    $groups[] = ['label' => $type, 'count' => count($list)];
                    foreach ($list as $r) {
                        $place = trim($this->placeText($r, $names));
                        $rows[] = [
                            $r['name'] ?: '',
                            implode('、', array_column($hazards[(int)$r['id']] ?? [], 'name')),
                            $place ?: '',
                            $r['supplier'] ?: '', $r['remark'] ?: '',
                        ];
                    }
                }
                $metaRight = '填表日期：' . date('Y-m-d');
                break;

            case '表4-2':
                $rows = [];
                foreach (Database::rows(
                    'SELECT ph.*, p.name AS post_name, w.name AS workplace, h.name AS hazard_name
                     FROM post_hazards ph
                     JOIN posts p ON p.id = ph.post_id
                     JOIN workplaces w ON w.id = p.workplace_id
                     JOIN hazards h ON h.id = ph.hazard_id
                     ORDER BY w.name ASC, p.name ASC, h.name ASC'
                ) as $r) {
                    [$total, $female] = $this->postExposure((int)$r['post_id']);
                    $rows[] = [
                        $r['workplace'] ?: '', $r['post_name'] ?: '', $r['hazard_name'] ?: '',
                        $r['source'] ?: '', $r['exposure_type'] ?: '',
                        (string)$total, (string)$female,
                        $r['engineering_controls'] ?: '', $r['ppe'] ?: '',
                    ];
                }
                $metaRight = '填表日期：' . date('Y-m-d');
                break;

            case '表4-3':
                $rows = $this->detectionSummary($year);
                $metaRight = '年度：' . ($year !== '' ? $year : date('Y'));
                break;

            case '表4-4':
                $rows = [];
                foreach (Database::rows('SELECT * FROM rectifications ORDER BY id ASC') as $r) {
                    $src = (int)($r['report_id'] ?? 0) > 0
                        ? (string)Database::value('SELECT report_no FROM detection_reports WHERE id = ?', [$r['report_id']]) : '';
                    $verify = (int)($r['verify_report_id'] ?? 0) > 0
                        ? (string)Database::value('SELECT report_no FROM detection_reports WHERE id = ?', [$r['verify_report_id']]) : '';
                    $rows[] = [
                        $src, $r['item_desc'] ?: '', $r['plan'] ?: '', $r['done_date'] ?: '',
                        $r['status'] ?: '', $verify,
                    ];
                }
                $metaRight = '填表日期：' . date('Y-m-d');
                break;

            case '表5-1':
                $rows = [];
                // 结论列与字典对齐（GBZ 188-2025：含「职业病」、无「复查」）
                $cats = Dict::examConclusions();
                foreach (Database::rows('SELECT * FROM exam_batches ORDER BY exam_date ASC, id ASC') as $b) {
                    $stats = Database::rows(
                        'SELECT conclusion, COUNT(*) c FROM exam_records WHERE batch_id = ? GROUP BY conclusion',
                        [$b['id']]
                    );
                    $byC = [];
                    foreach ($stats as $s) {
                        $c = trim((string)$s['conclusion']);
                        // 历史口径归并：旧「未见异常」并入「目前未见异常」；「复查」不再是结论，并入「其他疾病或异常」
                        if ($c === '未见异常') {
                            $c = '目前未见异常';
                        } elseif ($c === '复查') {
                            $c = '其他疾病或异常';
                        }
                        if ($c === '') {
                            continue;   // 待出结论不计入结论列，也不计入实检
                        }
                        $byC[$c] = ($byC[$c] ?? 0) + (int)$s['c'];
                    }
                    $actual = array_sum($byC);   // 实检 = 已出结论之和，与批次列表口径一致
                    $row = [
                        $b['exam_date'] ?: '', $b['agency'] ?: '', $b['exam_type'] ?: '',
                        (string)$b['planned_count'], (string)$actual,
                    ];
                    foreach ($cats as $c) {
                        $row[] = isset($byC[$c]) && $byC[$c] > 0 ? (string)$byC[$c] : '';
                    }
                    $row[] = $b['remark'] ?: '';
                    $rows[] = $row;
                }
                $metaRight = '填表日期：' . date('Y-m-d');
                break;

            case '表5-2':
                $rows = [];
                foreach (Database::rows(
                    'SELECT er.*, w.name AS worker_name, w.gender, w.birth_date, w.id_card
                     FROM exam_records er JOIN workers w ON w.id = er.worker_id
                     WHERE ' . \App\Models\ExamRecord::abnormalWhere('er.conclusion') . '
                     ORDER BY er.exam_date ASC, er.id ASC'
                ) as $r) {
                    $age = $this->ageAt($r['birth_date'], $r['exam_date']);
                    $possible = $this->possibleDisease((int)$r['worker_id'], (string)$r['hazards_snapshot']);
                    $rows[] = [
                        $r['worker_name'] ?: '', $this->genderOf($r), $age,
                        $r['post_name'] ?: '', $r['hazards_snapshot'] ?: '', $possible,
                        trim(($r['conclusion'] ?: '') . ($r['handling'] && $r['handling'] !== '—' ? '；' . $r['handling'] : '')),
                        $r['site_handling'] ?: '',
                    ];
                }
                $metaRight = '填表日期：' . date('Y-m-d');
                break;

            case '表5-3':
            case '表5-4':
                $suspected = $no === '表5-4' ? 1 : 0;
                $rows = [];
                foreach (Database::rows(
                    'SELECT d.*, w.name AS worker_name, w.gender, w.birth_date, w.work_start_date, w.status AS worker_status,
                            er.exam_date AS src_exam_date, er.agency AS src_agency, er.exam_type AS src_exam_type,
                            er.post_name AS src_post_name
                     FROM diseases d JOIN workers w ON w.id = d.worker_id
                     LEFT JOIN exam_records er ON er.id = d.exam_record_id
                     WHERE d.is_suspected = ?
                     ORDER BY d.diag_date ASC, d.id ASC',
                    [$suspected]
                ) as $r) {
                    // 车间、岗位：优先用来源体检记录的「受检时岗位快照」；
                    // 手工登记（无来源记录）按诊断/体检日期取当时在岗的本单位职业史，
                    // 不再一律取「当前岗位」（换岗/离岗后打印会出错）
                    $asOf = $r['src_exam_date'] ?: $r['diag_date'];
                    $post = trim((string)($r['src_post_name'] ?? ''));
                    if ($post === '') {
                        $seg = \App\Models\WorkerPost::atDate((int)$r['worker_id'], $asOf);
                        $post = $seg !== null ? trim($seg['workplace_name'] . '·' . $seg['post_name'], '·') : '';
                    }
                    // 接害工龄：职业史中接害各段时长之和
                    $expYears = \App\Models\WorkerPost::exposureYears((int)$r['worker_id'], $asOf);
                    $seniority = $expYears === null ? ''
                        : ($expYears >= 1
                            ? (fmod($expYears, 1.0) < 0.05 ? (string)(int)$expYears : (string)$expYears) . ' 年'
                            : '不足1年');
                    if ($no === '表5-3') {
                        $rows[] = [
                            $r['worker_name'] ?: '', $r['gender'] ?: '', $r['birth_date'] ?: '',
                            $seniority, $post ?: '', $r['name'] ?: '', $r['hospital'] ?: '',
                            $r['diag_date'] ?: '', $r['handling'] ?: '',
                        ];
                    } else {
                        $age = $this->ageAt($r['birth_date'], $r['src_exam_date'] ?: $r['diag_date']);
                        $rows[] = [
                            $r['worker_name'] ?: '', $r['gender'] ?: '', $age, $post ?: '',
                            $seniority, $r['name'] ?: '', $r['src_agency'] ?: ($r['hospital'] ?: ''),
                            $r['src_exam_date'] ?: ($r['diag_date'] ?: ''), $r['handling'] ?: '',
                        ];
                    }
                }
                $metaRight = '填表日期：' . date('Y-m-d');
                break;

            case '表5-6':
                $rows = [];
                foreach (Database::rows(
                    'SELECT w.*,
                            (SELECT wp2.post_name FROM worker_posts wp2 WHERE wp2.worker_id = w.id AND wp2.end_date IS NULL AND wp2.post_id IS NOT NULL ORDER BY wp2.id DESC LIMIT 1) AS cur_post,
                            (SELECT w2.name FROM worker_posts wp2 JOIN posts p2 ON p2.id = wp2.post_id JOIN workplaces w2 ON w2.id = p2.workplace_id
                              WHERE wp2.worker_id = w.id AND wp2.end_date IS NULL AND wp2.post_id IS NOT NULL ORDER BY wp2.id DESC LIMIT 1) AS cur_workplace,
                            (SELECT MAX(wp2.end_date) FROM worker_posts wp2 WHERE wp2.worker_id = w.id AND wp2.end_date IS NOT NULL) AS last_end,
                            (SELECT COUNT(*) FROM documents d WHERE d.doc_type = \'W_COPY_SIGN\'
                              AND d.link_type = \'worker\' AND d.link_id = w.id) AS copy_sign_docs
                     FROM workers w ORDER BY w.archive_no ASC'
                ) as $w) {
                    $dept = $w['cur_workplace'] ?: '';
                    $left = in_array($w['status'], ['离岗', '退休', '离职'], true);
                    $rows[] = [
                        $dept, $w['archive_no'] ?: '', $w['name'] ?: '', $w['gender'] ?: '',
                        substr((string)$w['created_at'], 0, 10),
                        $left ? ($w['last_end'] ?: '') : '',
                        $left ? ((int)$w['copy_sign_docs'] > 0 ? '已提供' : '未提供') : '',
                        $left && (int)$w['copy_sign_docs'] > 0 ? '见签收件' : '',
                        trim(($w['status'] !== '在职' ? $w['status'] : '') . ($w['remark'] ? '；' . $w['remark'] : ''), '；'),
                    ];
                }
                $metaRight = '在册 ' . count($rows) . ' 人 · 填表日期：' . date('Y-m-d');
                break;
        }

        // 首列表头为「序号」的表样：统一自动补序号（与 Word 表样一致）
        $firstHead = $spec['heads'][0][0]['t'] ?? '';
        if ($firstHead === '序号') {
            foreach ($rows as $i => &$r) {
                array_unshift($r, (string)($i + 1));
            }
            unset($r);
        }

        $metaRight = $metaRight === '共 0 条记录' ? ('共 ' . count($rows) . ' 条记录') : $metaRight;
        $this->viewRaw('prints/table_list', [
            'spec'      => $spec,
            'rows'      => $rows,
            'groups'    => $groups,
            'metaLeft'  => $metaLeft,
            'metaRight' => $metaRight,
            'company'   => $company,
            'backUrl'   => url('prints/index'),
        ]);
    }

    /* ==================== 表单类表样 ==================== */

    private function renderForm(string $no, array $spec): void
    {
        $id = (int)$this->get('id', 0);
        $company = \App\Models\Company::name();
        $tpl = null;
        $data = [];

        switch ($no) {
            case '表1-1':
                $data = $this->formDataProjects($id);
                $tpl = 'prints/form_project';
                break;
            case '表2-2':
                $data = $this->formDataDeclaration();
                $tpl = 'prints/form_declaration';
                break;
            case '表2-5':
                $data = $this->formDataMaintains($id);
                $tpl = 'prints/form_maintain';
                break;
            case '表2-8':
                $data = $this->formDataInspections($id);
                $tpl = 'prints/form_inspection';
                break;
            case '表2-9':
                $data = $this->formDataSupervisions($id);
                $tpl = 'prints/form_supervision';
                break;
            case '表3-1':
                $data = $this->formDataTrainings($id);
                $tpl = 'prints/form_training';
                break;
            case '表5-5':
                $data = $this->formDataAccidents($id);
                $tpl = 'prints/form_accident';
                break;
        }

        if ($tpl === null) {
            $this->flash('danger', '表样配置缺失。');
            $this->redirect('prints/index');
        }
        // 指定了 id 却查不到记录时必须明确报错，避免静默打印空白表
        if (empty($data['records']) && $id > 0) {
            $this->flash('danger', '指定的记录（id=' . $id . '）不存在，无法打印。');
            $this->redirect('prints/index');
        }
        // 未指定 id 且无记录：打印一张空白表单（规范表样可先打印手工填写、事后补录）
        if (empty($data['records'])) {
            $data['records'] = [$this->blankRecord($no)];
        }
        $data['spec'] = $spec;
        $data['company'] = $company;
        $data['backUrl'] = url('prints/index');
        $data['multiSheet'] = count($data['records']) > 1;
        $this->viewRaw($tpl, $data);
    }

    /** 空白表单记录（无数据时打印空表）：按表样补齐视图所需字段 */
    private function blankRecord(string $no): array
    {
        $tables = [
            '表1-1' => ['table' => 'projects', 'extra' => []],
            '表2-5' => ['table' => 'facility_maintains', 'extra' => ['facility_name' => '', 'facility_model' => '', 'workplace' => '']],
            '表2-8' => ['table' => 'mgmt_inspections', 'extra' => []],
            '表2-9' => ['table' => 'mgmt_supervisions', 'extra' => []],
            '表5-5' => ['table' => 'health_accidents', 'extra' => ['workplace' => '', 'post' => '']],
        ];
        if ($no === '表3-1') {
            return array_merge([
                'type' => '', 'title' => '', 'hours' => '', 'department' => '', 'teacher' => '',
                'organizer' => '', 'trained_on' => '', 'remark' => '', 'participants' => [],
            ]);
        }
        $cfg = $tables[$no] ?? null;
        if ($cfg === null) {
            return [];
        }
        $row = [];
        foreach (Database::rows('PRAGMA table_info(' . $cfg['table'] . ')') as $col) {
            $row[$col['name']] = '';
        }
        return $row + $cfg['extra'];
    }

    /* ==================== 表单数据归集 ==================== */

    /** 表1-1 三同时：一项目一页 */
    private function formDataProjects(int $id): array
    {
        $where = $id > 0 ? 'WHERE id = ' . $id : '';
        return ['records' => Database::rows('SELECT * FROM projects ' . $where . ' ORDER BY start_date ASC, id ASC')];
    }

    /** 表2-2 申报表：单位 + 场所危害分布汇总 */
    private function formDataDeclaration(): array
    {
        $company = \App\Models\Company::row();
        // 各作业场所：危害因素（在用岗位配置）+ 接触人数（可重复 = 按岗位计；不重复 = 按人计）
        $places = [];
        foreach (Database::rows('SELECT id, name FROM workplaces WHERE status = "启用" ORDER BY name ASC') as $wp) {
            $hazards = Database::rows(
                'SELECT h.id, h.name FROM hazards h
                 WHERE h.id IN (SELECT ph.hazard_id FROM post_hazards ph JOIN posts p ON p.id = ph.post_id
                                WHERE p.workplace_id = ? AND p.status = \'启用\')
                 ORDER BY h.name ASC',
                [$wp['id']]
            );
            if (!$hazards) {
                continue;
            }
            // 口径（与工作台 exposedCount() 一致）：在职 + 本单位在岗职业史 + 岗位「启用」且有接害配置。
            // 表样“接触人数（可重复）”按【场所×因素】计：接触该因素的不同劳动者人数（同一人接触多种因素各计一次）；
            // “接触人数（不重复）”按【场所】计：该场所接害劳动者去重人数。
            $items = [];
            $sumDup = 0;
            foreach ($hazards as $hz) {
                $n = \App\Helpers\Exposure::workplaceHazardCount((int)$wp['id'], (int)$hz['id']);
                $items[] = ['id' => (int)$hz['id'], 'name' => (string)$hz['name'], 'cnt' => $n];
                $sumDup += $n;
            }
            $uniq = \App\Helpers\Exposure::workplaceCount((int)$wp['id']);
            $places[] = ['name' => $wp['name'], 'items' => $items, 'uniq' => $uniq, 'sum_dup' => $sumDup];
        }
        $totalWorkers = (int)Database::value("SELECT COUNT(*) FROM workers WHERE status = '在职'");
        $femaleWorkers = (int)Database::value("SELECT COUNT(*) FROM workers WHERE status = '在职' AND gender = '女'");
        // 在岗接害人数：限在职 + 本单位在岗职业史 + 所在岗位有接害配置
        // （否则人员改离岗/退休/离职后这里不会减少，会出现「接触人数 > 在岗总人数」的自相矛盾）
        $exposedWorkers = \App\Helpers\Exposure::currentCount();
        // 顶部种类数与下方明细同源：只数启用场所/启用岗位的危害，避免停用岗位虚增
        $kindSet = [];
        foreach ($places as $pl) {
            foreach ($pl['items'] as $it) {
                $kindSet[(int)($it['id'] ?? 0)] = true;
            }
        }
        unset($kindSet[0]);
        $hazardKinds = count($kindSet);
        $occupational = (int)Database::value('SELECT COUNT(*) FROM diseases WHERE is_suspected = 0');
        $sumDupAll = array_sum(array_column($places, 'sum_dup'));
        return [
            'records' => [['company' => $company ?: []]],
            'places'   => $places,
            'stats'    => [
                'total_workers'   => $totalWorkers,
                'female_workers'  => $femaleWorkers,
                'exposed_workers' => $exposedWorkers,
                'hazard_kinds'    => $hazardKinds,
                'occupational'    => $occupational,
                'sum_dup'         => $sumDupAll,
            ],
        ];
    }

    /** 表2-5 维护检修：一记录一页 */
    private function formDataMaintains(int $id): array
    {
        $sql = 'SELECT m.*, f.name AS facility_name, f.model AS facility_model, w.name AS workplace
                FROM facility_maintains m
                JOIN facilities f ON f.id = m.facility_id
                LEFT JOIN workplaces w ON w.id = f.workplace_id ';
        $sql .= $id > 0 ? 'WHERE m.id = ' . $id : '';
        return ['records' => Database::rows($sql . ' ORDER BY m.maintain_date ASC, m.id ASC')];
    }

    /** 表2-8 检查处理：一记录一页 */
    private function formDataInspections(int $id): array
    {
        $where = $id > 0 ? 'WHERE id = ' . $id : '';
        return ['records' => Database::rows('SELECT * FROM mgmt_inspections ' . $where . ' ORDER BY check_date ASC, id ASC')];
    }

    /** 表2-9 监管意见：一记录一页 */
    private function formDataSupervisions(int $id): array
    {
        $where = $id > 0 ? 'WHERE id = ' . $id : '';
        return ['records' => Database::rows('SELECT * FROM mgmt_supervisions ' . $where . ' ORDER BY opinion_date ASC, id ASC')];
    }

    /** 表3-1 培训：一次培训一页（培训信息 + 签到表） */
    private function formDataTrainings(int $id): array
    {
        $where = $id > 0 ? 'WHERE t.id = ' . $id : '';
        $records = Database::rows(
            'SELECT t.* FROM trainings t ' . $where . ' ORDER BY t.trained_on ASC, t.id ASC'
        );
        foreach ($records as &$t) {
            $t['participants'] = Database::rows(
                'SELECT tp.*, w.name AS worker_name, w.gender, w.status AS worker_status,
                        (SELECT wp2.post_name FROM worker_posts wp2 WHERE wp2.worker_id = w.id AND wp2.end_date IS NULL AND wp2.post_id IS NOT NULL ORDER BY wp2.id DESC LIMIT 1) AS cur_post,
                        (SELECT w2.name FROM worker_posts wp2 JOIN posts p2 ON p2.id = wp2.post_id JOIN workplaces w2 ON w2.id = p2.workplace_id
                          WHERE wp2.worker_id = w.id AND wp2.end_date IS NULL ORDER BY wp2.id DESC LIMIT 1) AS cur_workplace
                 FROM training_participants tp JOIN workers w ON w.id = tp.worker_id
                 WHERE tp.training_id = ? ORDER BY tp.id ASC',
                [$t['id']]
            );
        }
        unset($t);
        return ['records' => $records];
    }

    /** 表5-5 事故报告：一事故一页 */
    private function formDataAccidents(int $id): array
    {
        $sql = 'SELECT a.*, w.name AS workplace, p.name AS post
                FROM health_accidents a
                LEFT JOIN workplaces w ON w.id = a.workplace_id
                LEFT JOIN posts p ON p.id = a.post_id ';
        $sql .= $id > 0 ? 'WHERE a.id = ' . $id : '';
        return ['records' => Database::rows($sql . ' ORDER BY a.accident_time ASC, a.id ASC')];
    }

    /* ==================== 统计辅助 ==================== */

    /** 打印中心记录数（映射唯一来源：PrintSpec::countSqlOf） */
    private function rowCount(string $no): int
    {
        $sql = \App\Helpers\PrintSpec::countSqlOf($no);
        return $sql !== '' ? (int)Database::value($sql) : 0;
    }

    /** 台账记录的“场所 · 岗位”合并文本：关联列优先，文本补充；与列表页 LedgerPlace::display 同口径 */
    private function placeText(array $row, array $names): string
    {
        $wid = (string)($row['workplace_id'] ?? '');
        $pid = (string)($row['post_id'] ?? '');
        $parts = [];
        $text = trim((string)($row['department'] ?? $row['usage_place'] ?? $row['location'] ?? ''));
        if ($pid !== '' && isset($names['post_full'][$pid])) {
            // 关联岗位时也要保留补充文本，避免丢失具体使用位置
            return $text !== '' ? $names['post_full'][$pid] . '（' . $text . '）' : $names['post_full'][$pid];
        }
        if ($wid !== '' && isset($names['workplace'][$wid])) {
            $parts[] = $names['workplace'][$wid];
        }
        if ($text !== '' && !in_array($text, $parts, true)) {
            $parts[] = $text;
        }
        return implode('（', array_filter($parts)) . (count($parts) > 1 ? '）' : '');
    }

    /** 岗位当前接害人数 [总, 女]（与表2-2 接触人数同口径：Exposure） */
    private function postExposure(int $postId): array
    {
        return \App\Helpers\Exposure::postCurrentCount($postId);
    }

    /**
     * 表4-3 检测汇总：按 车间+危害因素 聚合明细。
     * $year：按检测年份过滤（空 = 自动取最近一个有数据的年份）；
     * 合格率与检测模块一致：合格点数 = 总点数 − 超标点数（未判定按合格计）。
     */
    private function detectionSummary(string $year = ''): array
    {
        if ($year === '') {
            $year = (string)(Database::value(
                "SELECT substr(test_date,1,4) FROM detection_reports
                 WHERE test_date IS NOT NULL AND test_date <> '' ORDER BY test_date DESC LIMIT 1"
            ) ?? '');
        }
        $rows = [];
        $items = Database::rows(
            'SELECT di.*, w.name AS workplace, h.name AS hazard, dr.category, dr.agency, dr.report_no
             FROM detection_items di
             JOIN detection_reports dr ON dr.id = di.report_id
             LEFT JOIN workplaces w ON w.id = di.workplace_id
             LEFT JOIN hazards h ON h.id = di.hazard_id'
            . ($year !== '' ? ' WHERE substr(dr.test_date,1,4) = ?' : '')
            . ' ORDER BY w.name ASC, h.name ASC, di.id ASC',
            $year !== '' ? [$year] : []
        );
        $agg = [];
        foreach ($items as $it) {
            $key = ($it['workplace'] ?: '未指定车间') . '|' . ($it['hazard'] ?: '未指定因素');
            if (!isset($agg[$key])) {
                $agg[$key] = ['workplace' => $it['workplace'] ?: '未指定车间', 'hazard' => $it['hazard'] ?: '未指定因素',
                    'cnt' => 0, 'exceed' => 0, 'undetermined' => 0, 'vals' => [], 'unit' => '', 'limit' => '', 'cats' => [], 'agencies' => []];
            }
            $a = &$agg[$key];
            $a['cnt']++;
            if ($it['verdict'] === '超标') {
                $a['exceed']++;
            } elseif (trim((string)($it['verdict'] ?? '')) === '') {
                $a['undetermined']++;
            }
            if (preg_match('/^(-?\d+(?:\.\d+)?)\s*(.*)$/', trim((string)$it['result']), $m)) {
                $a['vals'][] = (float)$m[1];
                if ($m[2] !== '' && $a['unit'] === '') {
                    $a['unit'] = $m[2];
                }
            }
            if ($it['limit_value'] !== '' && $a['limit'] === '') {
                $a['limit'] = $it['limit_value'];
            }
            if ($it['category'] && !in_array($it['category'], $a['cats'], true)) {
                $a['cats'][] = $it['category'];
            }
            if ($it['agency'] && !in_array($it['agency'], $a['agencies'], true)) {
                $a['agencies'][] = $it['agency'];
            }
            unset($a);
        }
        foreach ($agg as $a) {
            $range = '—';
            if ($a['vals']) {
                $min = min($a['vals']);
                $max = max($a['vals']);
                $range = ($min === $max ? (string)$min : $min . '～' . $max) . ($a['unit'] ? ' ' . $a['unit'] : '');
            }
            // 合格率与报告列表统一：未判定从分母剔除，并在点数后单独标示
            $rate = \App\Models\DetectionReport::passPercent((int)$a['cnt'], (int)$a['exceed'], (int)$a['undetermined']);
            $rows[] = [
                $a['workplace'], $a['hazard'], implode('、', $a['cats']) ?: '—',
                (string)$a['cnt'] . ($a['undetermined'] > 0 ? '（未判定 ' . $a['undetermined'] . '）' : ''), $range, (string)$rate,
                $a['limit'] ?: '—', implode('、', $a['agencies']) ?: '—',
            ];
        }
        return $rows;
    }

    /** 出生日期 → 检查时年龄（周岁文本） */
    private function ageAt(?string $birth, ?string $at): string
    {
        if (!$birth || strlen($birth) < 10 || !$at) {
            return '';
        }
        $b = date_create(substr($birth, 0, 10));
        $a = date_create(substr($at, 0, 10));
        if (!$b || !$a) {
            return '';
        }
        $age = (int)date_diff($b, $a)->y;
        return $age > 0 ? (string)$age : '';
    }

    private function genderOf(array $r): string
    {
        return (string)($r['gender'] ?? '');
    }

    /**
     * 可能导致的职业病（去重）：
     * 优先按体检记录的接害因素快照匹配危害字典（历史接触也能对应），
     * 快照为空时回退到现岗位危害配置。
     * 快照按「、」拆分后精确匹配，避免把「苯」误配到只接触「二甲苯」的人；
     * 已停用因素也参与匹配（历史接触仍可能致病）。
     */
    private function possibleDisease(int $workerId, string $snapshot = ''): string
    {
        $out = [];
        $hazards = Database::rows(
            'SELECT name, possible_disease FROM hazards
             WHERE TRIM(COALESCE(possible_disease, "")) <> ""'
        );
        $match = function (array $names) use ($hazards, &$out): void {
            foreach ($hazards as $h) {
                if ($h['name'] !== '' && in_array($h['name'], $names, true)) {
                    $out[] = $h['possible_disease'];
                }
            }
        };
        $names = array_values(array_filter(array_map('trim', explode('、', trim($snapshot)))));
        if ($names) {
            $match($names);
        }
        if (!$out) {
            foreach (Database::rows(
                'SELECT DISTINCT h.possible_disease
                 FROM worker_posts wp
                 JOIN post_hazards ph ON ph.post_id = wp.post_id
                 JOIN hazards h ON h.id = ph.hazard_id
                 WHERE wp.worker_id = ? AND wp.end_date IS NULL AND TRIM(COALESCE(h.possible_disease,"")) <> ""',
                [$workerId]
            ) as $r) {
                $out[] = $r['possible_disease'];
            }
        }
        $out = array_values(array_unique(array_filter($out)));
        return implode('、', $out);
    }
}
