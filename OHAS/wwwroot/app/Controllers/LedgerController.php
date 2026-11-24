<?php
/**
 * 辅助台账：按《职业卫生档案管理规范》规范表样维护日常管理记录。
 * 台账字段与列表列由 LedgerCatalog 配置驱动，一套控制器覆盖多张表。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Helpers\LedgerCatalog;
use App\Helpers\LedgerPlace;
use App\Models\LedgerHazard;

class LedgerController extends Controller
{
    /** 台账列表（未指定 key 时回到六类档案） */
    public function index(): void
    {
        $key = (string)$this->get('key');
        if ($key === '') {
            // 台账目录与六类档案是同一份清单，统一从六类档案进入
            $this->redirect('archive/index');
        }
        $cfg = LedgerCatalog::get($key);
        if ($cfg === null) {
            $this->flash('danger', '台账类型不存在。');
            $this->redirect('ledgers/index');
        }

        $q = (string)$this->get('q');
        $place = LedgerPlace::config($cfg);
        $names = LedgerPlace::names();
        $hazField = self::hazardsField($cfg);   // 多选危害因素字段（表2-6 / 2-7 / 4-1）
        $conds = [];
        $params = [];
        if ($q !== '' && !empty($cfg['search'])) {
            $parts = [];
            foreach ($cfg['search'] as $col) {
                $parts[] = $col . ' LIKE ?';
                $params[] = '%' . $q . '%';
            }
            // 已关联场所/岗位的记录，按场所名、岗位名也能搜到
            if ($place !== null) {
                $parts[] = $place['workplace'] . ' IN (SELECT id FROM workplaces WHERE name LIKE ?)';
                $params[] = '%' . $q . '%';
                if (!empty($place['post'])) {
                    $parts[] = $place['post'] . ' IN (SELECT id FROM posts WHERE name LIKE ?)';
                    $params[] = '%' . $q . '%';
                }
            }
            // 多选危害因素台账：按危害因素名称也能搜到（关联表由 LedgerHazard 按台账表名映射）
            if ($hazField !== null) {
                $parts[] = LedgerHazard::searchExists($cfg['table']);
                $params[] = '%' . $q . '%';
            }
            $conds[] = '(' . implode(' OR ', $parts) . ')';
        }
        // 按作业场所联查（含该场所下岗位的记录）
        $wpFilter = $place !== null ? (int)$this->get('workplace_id') : 0;
        if ($wpFilter > 0) {
            $sub = $place['workplace'] . ' = ?';
            $params[] = $wpFilter;
            if (!empty($place['post'])) {
                $sub .= ' OR ' . $place['post'] . ' IN (SELECT id FROM posts WHERE workplace_id = ?)';
                $params[] = $wpFilter;
            }
            $conds[] = '(' . $sub . ')';
        }
        $where = $conds ? implode(' AND ', $conds) : '1=1';

        $size = max(5, (int)cfg('page.size', 10));
        $total = (int)Database::value('SELECT COUNT(*) FROM ' . $cfg['table'] . ' WHERE ' . $where, $params);
        $pages = max(1, (int)ceil($total / $size));
        $page = min(max(1, (int)$this->get('page', 1)), $pages);
        $offset = ($page - 1) * $size;
        $rows = Database::rows(
            'SELECT * FROM ' . $cfg['table'] . ' WHERE ' . $where . ' ORDER BY ' . $cfg['order']
                . ' LIMIT ' . $size . ' OFFSET ' . $offset,
            $params
        );

        // 原件摘要列（台账记录可挂附件）
        $linkType = \App\Helpers\DocLink::typeForLedger($key);
        foreach ($rows as &$r) {
            $r = \App\Helpers\DocLink::withSummary($r, $linkType);
        }
        unset($r);

        // 把多选危害因素并入行数据（列表列翻译用）
        if ($hazField !== null) {
            $hazards = LedgerHazard::hazardsBy($cfg['table'], array_column($rows, 'id'));
            foreach ($rows as &$r) {
                $list = $hazards[(int)$r['id']] ?? [];
                $r[$hazField['name']] = array_column($list, 'id');
                $r[$hazField['name'] . '_names'] = implode('、', array_column($list, 'name'));
            }
            unset($r);
        }

        $this->view('ledgers/index', [
            'key'     => $key,
            'cfg'     => $cfg,
            'rows'    => $rows,
            'linkType' => \App\Helpers\DocLink::typeForLedger($key),
            'q'       => $q,
            // 列表页的选项含已停用关联项：否则危害因素停用后列表里会显示成数字 id
            'options' => $this->allOptions($cfg, [], true),
            'names'   => $names,
            'wpFilter' => $wpFilter,
            'data'    => ['total' => $total, 'page' => $page, 'pages' => $pages],
        ], $cfg['title'], 'ledgers_' . $cfg['group']);
    }

    public function create(): void
    {
        $key = (string)$this->get('key');
        $cfg = LedgerCatalog::get($key);
        if ($cfg === null) {
            $this->flash('danger', '台账类型不存在。');
            $this->redirect('ledgers/index');
        }
        $row = ['id' => 0];
        foreach ($cfg['fields'] as $f) {
            // 多选危害因素默认空数组；单选下拉默认首项
            $row[$f['name']] = ($f['type'] ?? '') === 'hazards' ? []
                : (!empty($f['options']) ? $f['options'][0] : '');
        }
        // 事故报告表的单位名称、法定代表人直接取自单位信息，避免重复手录
        if ($key === 'accidents') {
            $co = \App\Models\Company::row();
            $row['company_name'] = (string)($co['name'] ?? '');
            $row['legal_person'] = (string)($co['legal_person'] ?? '');
        }
        $this->form($key, $cfg, $row, 'create');
    }

    public function edit(): void
    {
        $key = (string)$this->get('key');
        $cfg = LedgerCatalog::get($key);
        $id = (int)$this->get('id');
        $row = $cfg ? Database::row('SELECT * FROM ' . $cfg['table'] . ' WHERE id = ?', [$id]) : null;
        if ($cfg === null || !$row) {
            $this->flash('danger', '记录不存在。');
            $this->redirect('ledgers/index');
        }
        // 回显已关联的多选危害因素
        $hazField = self::hazardsField($cfg);
        if ($hazField !== null) {
            $row[$hazField['name']] = LedgerHazard::idsOf($cfg['table'], $id);
        }
        $this->form($key, $cfg, $row, 'edit');
    }

    /** 台账编辑页的“原件”区块数据 */
    private function docBlock(string $key, int $id): array
    {
        $linkType = \App\Helpers\DocLink::typeForLedger($key);
        return [
            'docs'      => \App\Helpers\DocLink::forLink($linkType, $id),
            'linkType'  => $linkType,
            'linkId'    => $id,
        ];
    }

    private function form(string $key, array $cfg, array $row, string $mode): void
    {
        $options = $this->allOptions($cfg, $row);
        $hazField = self::hazardsField($cfg);   // 多选危害因素字段（表2-6 / 2-7 / 4-1）
        if ($this->isPost()) {
            $this->verifyCsrf();
            $v = Validator::make($_POST, LedgerCatalog::rules($cfg), LedgerCatalog::labels($cfg));
            $errors = $v->fails() ? $v->errors() : [];

            // 多选危害因素：数组读取 + 去重 + 逐项存在性校验 + 数量上限
            $hazIds = [];
            if ($hazField !== null) {
                foreach ($this->postArray($hazField['name']) as $hid) {
                    $hid = (int)$hid;
                    if ($hid > 0 && !in_array($hid, $hazIds, true)) {
                        $hazIds[] = $hid;
                    }
                }
                if (count($hazIds) > LedgerHazard::MAX_HAZARDS) {
                    $errors[$hazField['name']] = $hazField['label'] . '最多选择 ' . LedgerHazard::MAX_HAZARDS . ' 项。';
                }
                foreach ($hazIds as $hid) {
                    if (!Database::value('SELECT id FROM hazards WHERE id = ?', [$hid])) {
                        $errors[$hazField['name']] = $hazField['label'] . '选择无效。';
                        break;
                    }
                }
            }

            // 外键选项有效性校验
            foreach ($cfg['fields'] as $f) {
                if (empty($f['from']) || ($f['type'] ?? '') === 'hazards') {
                    continue;
                }
                $val = (string)$this->post($f['name']);
                if ($val !== '' && !isset($options[$f['name']][$val])) {
                    $errors[$f['name']] = $f['label'] . ' 选择无效。';
                }
            }

            // 场所/岗位关联：只选岗位时自动补场所；岗位与场所不匹配、"场所与文本至少填一项"在此校验
            $place = LedgerPlace::config($cfg);
            $placeIds = [null, null];
            $names = [];
            if ($place !== null) {
                $names = LedgerPlace::names();
                [$wid, $pid, $placeErrors] = LedgerPlace::resolve($cfg, $_POST, $names);
                $placeIds = [$wid, $pid];
                $errors = $placeErrors + $errors;
                if (!empty($place['require']) && $wid === null && trim((string)$this->post($place['text'])) === '') {
                    $errors[$place['text']] = $place['require_msg'] ?? '请选择场所，或手填位置说明。';
                }
            }

            $backParams = ['key' => $key] + ($mode === 'edit' ? ['id' => (int)$row['id']] : []);
            if ($errors) {
                $this->failBack('ledgers/' . $mode, $errors, $_POST, $backParams);
            }

            $data = [];
            foreach ($cfg['fields'] as $f) {
                if (($f['type'] ?? '') === 'hazards') {
                    continue;   // 多选危害因素不落在主表（关联表单独保存）
                }
                $val = (string)$this->post($f['name']);
                if (($f['type'] ?? '') === 'number') {
                    $data[$f['name']] = $val === '' ? 0 : (isset($f['step']) ? (float)$val : (int)$val);
                } elseif (!empty($f['from'])) {
                    // 外键下拉：未选存 NULL（存空字符串会触发外键约束错误）
                    $data[$f['name']] = $val === '' ? null : (int)$val;
                } else {
                    $data[$f['name']] = $val;
                }
            }
            if ($place !== null) {
                [$wid, $pid] = $placeIds;
                $data[$place['workplace']] = $wid;
                if (!empty($place['post'])) {
                    $data[$place['post']] = $pid;
                }
                $wn = $wid !== null ? ($names['workplace'][(string)$wid] ?? '') : '';
                $pn = $pid !== null ? ($names['post'][(string)$pid] ?? '') : '';
                if (!empty($place['post_text'])) {
                    // 场所、岗位各一列文本：已关联就不再重复存名称
                    $data[$place['text']] = LedgerPlace::cleanText((string)$data[$place['text']], [$wn]);
                    $data[$place['post_text']] = LedgerPlace::cleanText((string)$data[$place['post_text']], [$pn]);
                } else {
                    $data[$place['text']] = LedgerPlace::cleanText((string)$data[$place['text']], [$wn, $pn, $wn . $pn]);
                }
                // 警示标识的设置地点为必填：只选了场所、没写具体位置时，用场所名称填充
                if (!empty($place['require']) && $data[$place['text']] === '' && $wn !== '') {
                    $data[$place['text']] = $wn;
                }
            }

            $newId = 0;
            Database::tx(function () use ($cfg, $data, $mode, $row, $hazField, $hazIds, &$newId): void {
                if ($mode === 'edit') {
                    $data['updated_at'] = now_str();
                    $sets = implode(', ', array_map(fn ($c) => $c . ' = ?', array_keys($data)));
                    Database::run(
                        'UPDATE ' . $cfg['table'] . ' SET ' . $sets . ' WHERE id = ?',
                        array_merge(array_values($data), [(int)$row['id']])
                    );
                    $newId = (int)$row['id'];
                } else {
                    $data['created_at'] = now_str();
                    $data['updated_at'] = now_str();
                    $cols = array_keys($data);
                    Database::run(
                        'INSERT INTO ' . $cfg['table'] . ' (' . implode(',', $cols) . ') VALUES ('
                            . implode(',', array_fill(0, count($cols), '?')) . ')',
                        array_values($data)
                    );
                    $newId = Database::lastId();
                }
                // 危害因素多选关联与主表同事务保存（失败整体回滚，不留半截数据）
                if ($hazField !== null) {
                    LedgerHazard::save($cfg['table'], $newId, $hazIds);
                }
            });
            if ($mode === 'edit') {
                $this->log('update', 'ledger_' . $key, (int)$row['id'], '修改台账：' . $cfg['title']);
                $this->flash('success', '台账记录已更新。');
            } else {
                $this->log('create', 'ledger_' . $key, $newId, '新增台账：' . $cfg['title']);
                $this->flash('success', '台账记录已添加。');
            }
            $this->redirect('ledgers/index', ['key' => $key]);
        }

        // 危害因素多选下拉（459 条目录，分组带元数据）
        $hazardGroups = null;
        if ($hazField !== null) {
            $hazardGroups = \App\Models\Hazard::groupedActive();
            $selIds = is_array($row[$hazField['name']] ?? null) ? $row[$hazField['name']] : [];
            if ($selIds && $mode === 'edit') {
                // 已停用但当前已选的因素：保留在选项里（名称后缀「已停用」），避免回显丢失
                $ph = implode(',', array_fill(0, count($selIds), '?'));
                $stopped = Database::rows(
                    "SELECT id, name, category FROM hazards WHERE status <> '启用' AND id IN ({$ph}) ORDER BY category, name",
                    array_map('intval', $selIds)
                );
                if ($stopped) {
                    $group = [];
                    foreach ($stopped as $s) {
                        $group[] = ['id' => (int)$s['id'], 'name' => $s['name'] . '（已停用）',
                            'possible_disease' => '', 'cas_no' => '', 'catalog_no' => ''];
                    }
                    $hazardGroups['已停用'] = $group;
                }
            }
        }

        $this->view('ledgers/form', [
            'key'     => $key,
            'cfg'     => $cfg,
            'row'     => $row,
            'mode'    => $mode,
            'options' => $options,
            'names'   => LedgerPlace::names(),
            'hazardGroups' => $hazardGroups,
        ] + ($mode === 'edit' ? $this->docBlock($key, (int)$row['id']) : []), ($mode === 'edit' ? '编辑' : '新增') . $cfg['title'], 'ledgers_' . $cfg['group']);
    }

    public function delete(): void
    {
        $this->verifyCsrf();
        $key = (string)$this->post('key');
        $cfg = LedgerCatalog::get($key);
        $id = (int)$this->post('id');
        if ($cfg === null) {
            $this->done(false, '台账类型不存在。', 'ledgers/index');
        }
        if (!Database::row('SELECT id FROM ' . $cfg['table'] . ' WHERE id = ?', [$id])) {
            $this->done(false, '记录不存在或已被删除。', 'ledgers/index', ['key' => $key]);
        }
        Database::run('DELETE FROM ' . $cfg['table'] . ' WHERE id = ?', [$id]);
        $this->log('delete', 'ledger_' . $key, $id, '删除台账：' . $cfg['title']);
        $this->done(true, '记录已删除。', 'ledgers/index', ['key' => $key]);
    }

    /** 培训签到表（表3-1 附页） */
    public function roster(): void
    {
        $trainingId = (int)$this->get('training_id');
        $training = Database::row('SELECT * FROM trainings WHERE id = ?', [$trainingId]);
        if (!$training) {
            $this->flash('danger', '培训记录不存在。');
            $this->redirect('ledgers/index', ['key' => 'trainings']);
        }
        $rows = Database::rows(
            'SELECT tp.*, w.name AS worker_name, w.archive_no, w.gender, w.status
             FROM training_participants tp JOIN workers w ON w.id = tp.worker_id
             WHERE tp.training_id = ? ORDER BY tp.id ASC',
            [$trainingId]
        );
        $workers = Database::rows(
            "SELECT id, name, archive_no FROM workers WHERE status = '在职' ORDER BY name ASC"
        );
        $this->view('ledgers/roster', [
            'training' => $training,
            'rows'     => $rows,
            'workers'  => $workers,
        ], '培训签到表', 'ledgers_training');
    }

    public function rosterAdd(): void
    {
        $this->verifyCsrf();
        $trainingId = (int)$this->post('training_id');
        $workerId = (int)$this->post('worker_id');
        $worker = Database::row('SELECT id, status FROM workers WHERE id = ?', [$workerId]);
        if (!Database::value('SELECT id FROM trainings WHERE id = ?', [$trainingId])
            || !$worker) {
            $this->flash('danger', '培训或劳动者不存在。');
            $this->redirect('ledgers/roster', ['training_id' => $trainingId]);
        }
        // 「仅在职」不能只靠前端下拉：服务端同样校验状态
        if ($worker['status'] !== '在职') {
            $this->flash('warning', '该劳动者已不在职（' . $worker['status'] . '），如需补录历史培训请先调整人员状态。');
            $this->redirect('ledgers/roster', ['training_id' => $trainingId]);
        }
        $score = mb_substr(trim((string)$this->post('score')), 0, 20);
        $remark = mb_substr(trim((string)$this->post('remark')), 0, 200);
        $exists = Database::value(
            'SELECT id FROM training_participants WHERE training_id = ? AND worker_id = ?',
            [$trainingId, $workerId]
        );
        if ($exists) {
            $this->flash('warning', '该劳动者已在签到表中。');
            $this->redirect('ledgers/roster', ['training_id' => $trainingId]);
        }
        try {
            Database::run(
                'INSERT INTO training_participants (training_id, worker_id, score, attended, remark, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?)',
                [
                    $trainingId,
                    $workerId,
                    $score,
                    $this->post('attended') === '否' ? '否' : '是',
                    $remark,
                    now_str(),
                    now_str(),
                ]
            );
        } catch (\PDOException $e) {
            // 撞 (training_id, worker_id) 唯一索引：双开窗口重复提交
            $this->flash('warning', '该劳动者已在签到表中。');
            $this->redirect('ledgers/roster', ['training_id' => $trainingId]);
        }
        $this->log('create', 'training_participant', $trainingId, '新增培训签到人员');
        $this->flash('success', '已加入签到表。');
        $this->redirect('ledgers/roster', ['training_id' => $trainingId]);
    }

    public function rosterDelete(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $trainingId = (int)$this->post('training_id');
        // 按行号与所属培训双重限定，避免误删其它签到表的行
        Database::run('DELETE FROM training_participants WHERE id = ? AND training_id = ?', [$id, $trainingId]);
        $this->log('delete', 'training_participant', $id, '删除培训签到人员');
        $this->flash('success', '已从签到表移除。');
        $this->redirect('ledgers/roster', ['training_id' => $trainingId]);
    }

    /** 台账整表打印：统一走规范表样打印，旧地址自动跳转 */
    public function printAll(): void
    {
        $key = (string)$this->get('key');
        $cfg = LedgerCatalog::get($key);
        if ($cfg === null) {
            $this->redirect('ledgers/index');
        }
        // 配置里的 archive 即规范表号（如「表2-4」）→ 直接跳转统一表样打印
        if (preg_match('/(表\d+-\d+)/u', (string)($cfg['archive'] ?? ''), $m)) {
            $this->redirect('prints/sheet', ['t' => $m[1]]);
        }
        $this->redirect('prints/index');
    }

    /** 台账配置中的多选危害因素字段（type = hazards）；无则返回 null（表2-6 / 2-7 / 4-1 使用） */
    protected static function hazardsField(array $cfg): ?array
    {
        foreach ($cfg['fields'] as $f) {
            if (($f['type'] ?? '') === 'hazards') {
                return $f;
            }
        }
        return null;
    }

    /**
     * 各外键字段的可选值（id => 显示名）。
     * $row 为编辑中的记录（用于保留已停用关联项）；$withStopped = true 时全部列出已停用项
     * （列表页翻译用，否则危害因素停用后列表里会显示成数字 id）。
     */
    private function allOptions(array $cfg, array $row = [], bool $withStopped = false): array
    {
        $out = [];
        foreach ($cfg['fields'] as $f) {
            $out[$f['name']] = $this->optionsFor($f, $row[$f['name']] ?? null, $withStopped);
        }
        return $out;
    }

    private function optionsFor(array $field, $current = null, bool $withStopped = false): array
    {
        if (!empty($field['options'])) {
            $out = [];
            foreach ($field['options'] as $o) {
                $out[$o] = $o;
            }
            return $out;
        }
        $out = [];
        switch ($field['from'] ?? '') {
            case 'workers':
                foreach (Database::rows('SELECT id, name, archive_no FROM workers ORDER BY name ASC') as $r) {
                    $out[(string)$r['id']] = $r['name'] . ($r['archive_no'] ? '（' . $r['archive_no'] . '）' : '');
                }
                break;
            case 'hazards':
                foreach (Database::rows("SELECT id, name, status FROM hazards ORDER BY category, name") as $r) {
                    if ($r['status'] !== '启用') {
                        // 列表翻译需要全部项；表单只在编辑回显时保留已停用关联项
                        if (!$withStopped && (string)$current !== (string)$r['id']) {
                            continue;
                        }
                        $out[(string)$r['id']] = $r['name'] . '（已停用）';
                        continue;
                    }
                    $out[(string)$r['id']] = $r['name'];
                }
                break;
            case 'workplaces':
                foreach (Database::rows('SELECT id, name FROM workplaces ORDER BY name ASC') as $r) {
                    $out[(string)$r['id']] = $r['name'];
                }
                break;
            case 'posts':
                // 岗位带上所属场所，如“铸造车间 · 熔炼岗”
                $out = LedgerPlace::names()['post_full'];
                break;
            case 'facilities':
                foreach (Database::rows('SELECT id, name FROM facilities ORDER BY name ASC') as $r) {
                    $out[(string)$r['id']] = $r['name'];
                }
                break;
            case 'detection_reports':
                // 超标点整改台账（系统扩充表样表4-4，原补-3）：超标来源报告 / 复测报告下拉
                foreach (Database::rows("SELECT id, report_no, category, test_date FROM detection_reports ORDER BY test_date DESC, id DESC") as $r) {
                    $out[(string)$r['id']] = trim(($r['report_no'] !== '' ? $r['report_no'] : '未编号报告')
                        . '（' . ($r['category'] ?: '检测报告') . ' ' . ($r['test_date'] ?: '未填日期') . '）');
                }
                break;
        }
        return $out;
    }
}
