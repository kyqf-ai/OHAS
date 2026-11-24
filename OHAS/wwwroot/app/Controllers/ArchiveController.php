<?php
/**
 * 六类职业卫生档案：按《职业卫生档案管理规范》所列六类档案（一～六）组织栏目。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\ArchiveCatalog;

class ArchiveController extends Controller
{
    /** 六类档案总览 */
    public function index(): void
    {
        $this->view('archive/index', [
            'overview' => ArchiveCatalog::overview(),
        ], '六类档案总览', 'archive');
    }

    /** 单个档案类别详情（规范表样清单与数据入口） */
    public function show(): void
    {
        $key = (string)$this->get('group');
        $group = ArchiveCatalog::group($key);
        if ($group === null) {
            $this->flash('danger', '档案类别不存在。');
            $this->redirect('archive/index');
        }
        // 本类档案对应的原件类别及已上传份数（原件按类别归入所属档案类别）
        $docs = [];
        foreach ($group['docs'] ?? [] as $cat) {
            $docs[$cat] = (int)Database::value('SELECT COUNT(*) FROM documents WHERE category = ?', [$cat]);
        }
        // 该档案盒的官方目录项（48项目录中属于本类者）及状态
        $filing = [];
        foreach (ArchiveCatalog::filingCatalog()[$key] ?? [] as $entry) {
            $filing[] = ['entry' => $entry, 'status' => ArchiveCatalog::filingStatus($entry, $key)];
        }
        $this->view('archive/show', [
            'stat'       => ArchiveCatalog::groupStat($group),
            'docs'       => $docs,
            'filing'     => $filing,
            'filingStat' => ArchiveCatalog::filingStat($key),
        ], $group['title'], 'archive');
    }

    /**
     * 官方目录项资料管理页（二级菜单落点）。
     * 外部报告（mode=external）→ 仅附件上传；内部编制（mode=internal）→ 结构化在线编辑 + 附件 + 打印。
     */
    public function material(): void
    {
        $gk = (string)$this->get('group');
        $seq = (string)$this->get('seq');
        $group = ArchiveCatalog::group($gk);
        $entry = ArchiveCatalog::filingItem($gk, $seq);
        if ($group === null || $entry === null) {
            $this->flash('danger', '目录项不存在。');
            $this->redirect('archive/index');
        }
        [$filingSeq, $name, $kind, $key, $self, $checkCode] = $entry;
        $mode = ArchiveCatalog::filingMode($entry);
        $status = ArchiveCatalog::filingStatus($entry, $gk);

        // 规范表样项：资料管理入口即其数据录入页（带 filing 参数以回显目录项标题）
        if ($mode === 'form') {
            $this->redirectUrl(ArchiveCatalog::filingTargetUrl($gk, $seq));
        }

        // 外部报告类也要有一条「目录项容器」记录，用于把原件挂到本项并支持返回
        $material = Database::row('SELECT * FROM archive_materials WHERE group_key = ? AND filing_seq = ?', [$gk, $seq]);
        if ($material === null) {
            $now = now_str();
            Database::run(
                'INSERT OR IGNORE INTO archive_materials (group_key, filing_seq, doc_type, title, created_at, updated_at) VALUES (?,?,?,?,?,?)',
                [$gk, $seq, implode('、', ArchiveCatalog::filingDocTypes($entry)), $name, $now, $now]
            );
            $material = Database::row('SELECT * FROM archive_materials WHERE group_key = ? AND filing_seq = ?', [$gk, $seq]);
        }
        // 本目录项名下的原件：优先取挂到容器的附件，兼容旧数据按 doc_type 匹配
        $docs = \App\Helpers\DocLink::forLink('archive_material', (int)$material['id']);
        if (!$docs) {
            $docs = ArchiveCatalog::filingDocs($entry, $gk);
        }

        $this->view('archive/material', [
            'group'      => $group,
            'entry'      => $entry,
            'mode'       => $mode,
            'material'   => $material ?? [],
            'docs'       => $docs,
            'status'     => $status,
            'docTypes'   => ArchiveCatalog::filingDocTypes($entry),
            'checkCode'  => $checkCode,
        ], $name, 'archive_material');
    }

    /** 保存内部编制资料（结构化字段 + 大文本正文） */
    public function materialSave(): void
    {
        $this->verifyCsrf();
        $gk = (string)$this->post('group');
        $seq = (string)$this->post('seq');
        $group = ArchiveCatalog::group($gk);
        $entry = ArchiveCatalog::filingItem($gk, $seq);
        if ($group === null || $entry === null || ArchiveCatalog::filingMode($entry) !== 'internal') {
            $this->done(false, '目录项不可在线编辑。', 'archive/index');
        }

        $title = trim((string)$this->post('title'));
        $body = (string)$this->postRaw('body');
        $errors = [];
        if ($title === '') {
            $errors[] = '标题必填。';
        } elseif (mb_strlen($title) > 120) {
            $errors[] = '标题不能超过 120 字。';
        }
        if (mb_strlen($body) > 200000) {
            $errors[] = '正文过长（上限 20 万字）。';
        }
        $docDate = (string)$this->post('doc_date');
        if ($docDate !== '' && !\App\Core\Validator::isDate($docDate)) {
            $errors[] = '日期格式不正确。';
        }
        if ($errors) {
            $this->flash('danger', implode(' ', $errors));
            $this->redirectUrl(ArchiveCatalog::filingItemUrl($gk, $seq));
        }

        $now = now_str();
        $data = [
            'group_key'  => $gk,
            'filing_seq' => $seq,
            'doc_type'   => mb_substr(trim((string)$this->post('doc_type')), 0, 200),
            'title'      => $title,
            'doc_no'     => mb_substr(trim((string)$this->post('doc_no')), 0, 80),
            'issuer'     => mb_substr(trim((string)$this->post('issuer')), 0, 120),
            'doc_date'   => $docDate,
            'body'       => $body,
        ];
        $existing = Database::row('SELECT id FROM archive_materials WHERE group_key = ? AND filing_seq = ?', [$gk, $seq]);
        try {
            if ($existing) {
                $sets = implode(', ', array_map(fn ($c) => $c . ' = ?', array_keys($data)));
                Database::run(
                    'UPDATE archive_materials SET ' . $sets . ', updated_at = ? WHERE id = ?',
                    array_merge(array_values($data), [$now, (int)$existing['id']])
                );
                $id = (int)$existing['id'];
                $this->log('update', 'archive_material', $id, '更新内部资料：' . $title);
            } else {
                $cols = array_keys($data);
                Database::run(
                    'INSERT INTO archive_materials (' . implode(',', $cols) . ', created_at, updated_at) VALUES ('
                        . implode(',', array_fill(0, count($cols), '?')) . ', ?, ?)',
                    array_merge(array_values($data), [$now, $now])
                );
                $id = Database::lastId();
                $this->log('create', 'archive_material', $id, '新建内部资料：' . $title);
            }
        } catch (\PDOException $e) {
            @error_log('[' . now_str() . '] archive/materialSave: ' . $e->getMessage() . "\n", 3, OHAS_ROOT . '/storage/logs/app-error.log');
            $this->flash('danger', '保存失败，请稍后重试。');
            $this->redirectUrl(ArchiveCatalog::filingItemUrl($gk, $seq));
        }
        $this->flash('success', '资料已保存。');
        $this->redirectUrl(ArchiveCatalog::filingItemUrl($gk, $seq));
    }

    /** 删除内部编制资料（不影响已上传附件） */
    public function materialDelete(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $row = Database::row('SELECT * FROM archive_materials WHERE id = ?', [$id]);
        if ($row === null) {
            $this->done(false, '资料不存在。', 'archive/index');
        }
        Database::run('DELETE FROM archive_materials WHERE id = ?', [$id]);
        $this->log('delete', 'archive_material', $id, '删除内部资料：' . (string)$row['title']);
        $this->done(true, '资料已删除（附件保留）。', 'archive/material', ['group' => $row['group_key'], 'seq' => $row['filing_seq']]);
    }

    /** 事件线索人员名单：体检禁忌证/疑似/复查未归档材料的可点击明细 */
    public function clues(): void
    {
        $rows = \App\Helpers\WorkerArchive::eventClueWorkers();
        $this->view('archive/event_clues', ['rows' => $rows], '事件线索人员名单', 'checklist');
    }

    /** 到期资料名单：已过期 / 即将到期的原件明细 */
    public function expiring(): void
    {
        $soonDays = max(1, (int)cfg('expiry.soon_days', 30));
        $rows = \App\Helpers\ArchiveChecklist::expiringDocs();
        $this->view('archive/expiring_docs', ['rows' => $rows, 'soonDays' => $soonDays], '到期资料名单', 'checklist');
    }

    /** 保留原地址以兼容旧链接；打印入口已统一在六类档案总览与各栏目页 */
    public function forms(): void
    {
        $this->redirect('archive/index');
    }

    /* ==================== 单位资料清单 ==================== */

    /** 36 项资料清单 */
    public function checklist(): void
    {
        $checklist = \App\Helpers\ArchiveChecklist::all();
        $gaps = \App\Helpers\WorkerArchive::gapWorkers();
        $this->view('archive/checklist', [
            'checklist'     => $checklist,
            'groups'        => \App\Helpers\ArchiveChecklist::GROUPS,
            'gapCount'      => count($gaps),
            'gapWorkers'    => array_slice($gaps, 0, 10, true),
            'retention'     => \App\Models\Setting::get('retention_exam_years', ''),
        ], '单位资料清单', 'checklist');
    }

    /** 某项的缺失名单（含逐人上传入口；format=csv 导出） */
    public function missing(): void
    {
        $code = strtoupper(trim((string)$this->get('item')));
        $item = \App\Helpers\ArchiveChecklist::item($code);
        if ($item === null) {
            $this->flash('danger', '清单项不存在。');
            $this->redirect('archive/checklist');
        }
        $rows = \App\Helpers\ArchiveChecklist::missing($code);
        if ((string)$this->get('format') === 'csv') {
            $lines = [['对象', '说明', '去向']];
            foreach ($rows as $r) {
                $lines[] = [$r['label'], $r['sub'], $r['url'] ? $r['url'] : '页面内上传'];
            }
            $csv = "\xEF\xBB\xBF";
            foreach ($lines as $line) {
                $csv .= implode(',', array_map(function ($v) {
                    $v = (string)$v;
                    if (preg_match('/^[=+\-@]/', $v)) {
                        $v = "'" . $v;
                    }
                    return \App\Helpers\Csv::cell($v);
                }, $line)) . "\r\n";
            }
            $name = '待归档名单_' . $code . '_' . date('Ymd') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($name));
            echo $csv;
            $this->log('export', 'archive_missing', null, '导出待归档名单：' . $code . '（' . count($rows) . ' 行）');
            exit;
        }
        $this->view('archive/missing', [
            'item' => $item,
            'rows' => $rows,
        ], '缺失名单 - ' . $item['name'], 'checklist');
    }

    /* ==================== “不适用”标记 ==================== */

    /** 标记“不适用”：scope=unit（item_code=C01–C36）或 scope=worker（item_code=资料类型，可选年度） */
    public function mark(): void
    {
        $this->verifyCsrf();
        $scope = (string)$this->post('scope') === 'worker' ? 'worker' : 'unit';
        $itemCode = trim((string)$this->post('item_code'));
        $reason = trim((string)$this->post('reason'));
        $workerId = (int)$this->post('worker_id');
        $year = (int)$this->post('period_year');
        $back = (string)$this->post('back') === '1';

        $errors = [];
        if ($itemCode === '') {
            $errors[] = '缺少清单项/资料类型。';
        }
        if (mb_strlen($reason) < 2) {
            $errors[] = '原因必填（至少 2 个字）。';
        } elseif (mb_strlen($reason) > 200) {
            $errors[] = '原因不能超过 200 个字。';
        }
        if ($scope === 'unit' || $year <= 0) {
            $year = 0;   // 单位级标记不分年度：带年度会与不带年度的标记并存、互不覆盖
        } elseif ($year < 1990 || $year > 2100) {
            $errors[] = '年度不合法。';
        }
        if ($scope === 'unit' && $itemCode !== '' && !\App\Helpers\ArchiveChecklist::naAllowed($itemCode)) {
            $errors[] = '该项不属于自评表“合理缺项”，不能标记为不适用。';
        }
        if ($scope === 'worker') {
            if ($workerId <= 0 || !Database::value('SELECT id FROM workers WHERE id = ?', [$workerId])) {
                $errors[] = '劳动者不存在。';
            }
            if ($itemCode !== '' && \App\Helpers\DocTypes::get($itemCode) === null && !in_array($itemCode, ['X_DETECT'], true)) {
                $errors[] = '资料类型无效。';
            }
        }
        if ($errors) {
            if ($back) {
                $this->flash('danger', implode(' ', $errors));
                $this->back();
            }
            $this->done(false, implode(' ', $errors), 'archive/checklist');
        }

        $now = now_str();
        try {
            Database::run(
                'INSERT INTO archive_marks (scope, item_code, worker_id, period_year, reason, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?)
                 ON CONFLICT(scope, item_code, COALESCE(worker_id,0), COALESCE(period_year,0))
                 DO UPDATE SET reason = excluded.reason, updated_at = excluded.updated_at',
                [$scope, $itemCode, $scope === 'worker' ? $workerId : null, $year > 0 ? $year : null, $reason, $now, $now]
            );
        } catch (\PDOException $e) {
            @error_log('[' . now_str() . '] archive/mark: ' . $e->getMessage() . "\n", 3, OHAS_ROOT . '/storage/logs/app-error.log');
            if ($back) {
                $this->flash('danger', '标记失败，请稍后重试。');
                $this->back();
            }
            $this->done(false, '标记失败，请稍后重试。', 'archive/checklist');
        }
        $this->log('create', 'archive_mark', null, '标记不适用：' . ($scope === 'worker' ? '个人 ' . $itemCode . ($year > 0 ? '/' . $year : '') : '单位 ' . $itemCode) . '，原因：' . $reason);
        $msg = '已标记为不适用。';
        if ($back) {
            $this->flash('success', $msg);
            $this->back();
        }
        $this->done(true, $msg, 'archive/checklist');
    }

    /** 取消“不适用” */
    public function unmark(): void
    {
        $this->verifyCsrf();
        $scope = (string)$this->post('scope') === 'worker' ? 'worker' : 'unit';
        $itemCode = trim((string)$this->post('item_code'));
        $workerId = (int)$this->post('worker_id');
        $year = (int)$this->post('period_year');
        $back = (string)$this->post('back') === '1';
        $w = ['scope = ?', 'item_code = ?'];
        $params = [$scope, $itemCode];
        if ($scope === 'worker') {
            $w[] = 'worker_id = ?';
            $params[] = $workerId;
            if ($year > 0) {
                $w[] = 'period_year = ?';
                $params[] = $year;
            } else {
                $w[] = 'period_year IS NULL';
            }
        }
        $n = Database::run('DELETE FROM archive_marks WHERE ' . implode(' AND ', $w), $params)->rowCount();
        $this->log('delete', 'archive_mark', null, '取消不适用标记：' . ($scope === 'worker' ? '个人 ' . $itemCode : '单位 ' . $itemCode));
        $msg = $n > 0 ? '已取消不适用标记。' : '该标记已不存在。';
        if ($back) {
            $this->flash($n > 0 ? 'success' : 'info', $msg);
            $this->back();
        }
        $this->done(true, $msg, 'archive/checklist');
    }
}
