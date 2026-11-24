<?php
/**
 * 原件（documents）与业务记录的关联：唯一的“原件归属”入口。
 *
 * 检测报告、体检批次、体检记录、劳动者、作业场所、建设项目、各台账记录、职业病登记的原件，
 * 一律存放在 documents，通过 link_type + link_id 指向业务记录；
 * 业务页面只展示、不再各自保存文件字段。
 *
 * link_type 包含：
 *   静态类（worker/workplace/project/detection_report/exam_batch/exam_record）
 *   + disease（职业病/疑似职业病登记）
 *   + 台账动态类 ledger_<key>（LedgerCatalog 每张台账一个；projects 沿用已有 project）。
 * 所有校验、检索、label/url/format 一律走 types()；TYPES 常量保留兼容既有调用。
 */
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

class DocLink
{
    /** 可关联的对象类型（静态 6 类；兼容保留，新代码请用 types()） */
    public const TYPES = [
        'worker'           => '劳动者',
        'workplace'        => '作业场所',
        'project'          => '建设项目',
        'detection_report' => '检测报告',
        'exam_batch'       => '体检批次',
        'exam_record'      => '体检记录（个人）',
    ];

    /** 全部可关联类型：静态 6 类 + disease + 台账动态类 */
    public static function types(): array
    {
        $types = self::TYPES;
        $types['disease'] = '职业病登记';
        $types['archive_material'] = '档案目录项资料';
        foreach (LedgerCatalog::all() as $key => $cfg) {
            $types[self::typeForLedger($key)] = $cfg['title'];
        }
        return $types;
    }

    /** 台账 key → link_type：projects 沿用 project，其余 ledger_<key> */
    public static function typeForLedger(string $key): string
    {
        return $key === 'projects' ? 'project' : 'ledger_' . $key;
    }

    /** link_type → 台账 key（非台账类型返回 null） */
    public static function ledgerOfType(string $type): ?string
    {
        if (!str_starts_with($type, 'ledger_')) {
            return null;
        }
        $key = substr($type, 7);
        return LedgerCatalog::get($key) !== null ? $key : null;
    }

    /** 从业务页上传时预选的文档分类 */
    public static function defaultCategory(string $type): string
    {
        // 优先用资料类型的默认类别
        $code = DocTypes::defaultForLink($type);
        if ($code !== '' && DocTypes::categoryOf($code) !== '') {
            return DocTypes::categoryOf($code);
        }
        return match ($type) {
            'detection_report'       => '检测评价报告',
            'exam_batch', 'exam_record' => '体检报告',
            'project'                => '三同时资料',
            'disease'                => '诊断证明',
            default                  => '',
        };
    }

    /** 关联对象是否存在 */
    public static function exists(string $type, int $id): bool
    {
        return self::label($type, $id) !== '';
    }

    /** 关联对象的可读名称（不存在返回空串） */
    public static function label(string $type, int $id): string
    {
        $ledgerKey = self::ledgerOfType($type);
        if ($ledgerKey !== null) {
            $cfg = LedgerCatalog::get($ledgerKey);
            $row = Database::row('SELECT * FROM ' . $cfg['table'] . ' WHERE id = ?', [$id]);
            return $row ? self::formatLedger($cfg, $row) : '';
        }
        $r = match ($type) {
            'worker'           => Database::row('SELECT name, archive_no FROM workers WHERE id = ?', [$id]),
            'workplace'        => Database::row('SELECT name FROM workplaces WHERE id = ?', [$id]),
            'project'          => Database::row('SELECT name FROM projects WHERE id = ?', [$id]),
            'detection_report' => Database::row('SELECT report_no, agency, test_date FROM detection_reports WHERE id = ?', [$id]),
            'exam_batch'       => Database::row('SELECT exam_date, exam_type, agency FROM exam_batches WHERE id = ?', [$id]),
            'exam_record'      => Database::row(
                'SELECT r.exam_date, r.exam_type, w.name FROM exam_records r JOIN workers w ON w.id = r.worker_id WHERE r.id = ?',
                [$id]
            ),
            'disease'          => Database::row(
                'SELECT d.name, d.is_suspected, d.diag_date, w.name AS worker_name FROM diseases d JOIN workers w ON w.id = d.worker_id WHERE d.id = ?',
                [$id]
            ),
            'archive_material' => Database::row('SELECT title, group_key, filing_seq FROM archive_materials WHERE id = ?', [$id]),
            default => null,
        };
        return $r ? self::format($type, $r) : '';
    }

    /** 关联对象的页面地址 */
    public static function url(string $type, int $id): string
    {
        $ledgerKey = self::ledgerOfType($type);
        if ($ledgerKey !== null) {
            return url('ledgers/edit', ['key' => $ledgerKey, 'id' => $id]);
        }
        return match ($type) {
            'worker'           => url('workers/show', ['id' => $id]),
            'workplace'        => url('workplaces/edit', ['id' => $id]),
            'project'          => url('ledgers/edit', ['key' => 'projects', 'id' => $id]),
            'detection_report' => url('detections/show', ['id' => $id]),
            'exam_batch'       => url('exams/records', ['batch_id' => $id]),
            'exam_record'      => url('exams/recordEdit', ['id' => $id]),
            'disease'          => url('workers/show', ['id' => (int)Database::value('SELECT worker_id FROM diseases WHERE id = ?', [$id])]),
            'archive_material' => (function () use ($id) {
                $m = Database::row('SELECT group_key, filing_seq FROM archive_materials WHERE id = ?', [$id]);
                return $m ? \App\Helpers\ArchiveCatalog::filingItemUrl((string)$m['group_key'], (string)$m['filing_seq']) : '';
            })(),
            default            => '',
        };
    }

    /** 选择器检索：返回 [['id'=>1,'label'=>'…'], …]，空关键字返回最近 30 条 */
    public static function search(string $type, string $q, int $limit = 30): array
    {
        $like = '%' . $q . '%';
        $ledgerKey = self::ledgerOfType($type);
        if ($ledgerKey !== null) {
            $cfg = LedgerCatalog::get($ledgerKey);
            $parts = [];
            $params = [];
            foreach ($cfg['list'] as $col) {
                $parts[] = "CAST({$col} AS TEXT) LIKE ?";
                $params[] = $like;
            }
            $rows = Database::rows(
                'SELECT * FROM ' . $cfg['table']
                . ' WHERE ' . ($parts ? implode(' OR ', $parts) : '1=1')
                . ' ORDER BY id DESC LIMIT ' . $limit,
                $params
            );
            return array_map(fn ($r) => ['id' => (int)$r['id'], 'label' => self::formatLedger($cfg, $r)], $rows);
        }
        $rows = match ($type) {
            'worker' => Database::rows(
                'SELECT id, name, archive_no FROM workers WHERE (name LIKE ? OR archive_no LIKE ?) ORDER BY id DESC LIMIT ' . $limit,
                [$like, $like]
            ),
            'workplace' => Database::rows('SELECT id, name FROM workplaces WHERE name LIKE ? ORDER BY name LIMIT ' . $limit, [$like]),
            'project' => Database::rows('SELECT id, name FROM projects WHERE name LIKE ? ORDER BY id DESC LIMIT ' . $limit, [$like]),
            'detection_report' => Database::rows(
                'SELECT id, report_no, agency, test_date FROM detection_reports
                 WHERE (report_no LIKE ? OR agency LIKE ?) ORDER BY test_date DESC, id DESC LIMIT ' . $limit,
                [$like, $like]
            ),
            'exam_batch' => Database::rows(
                'SELECT id, exam_date, exam_type, agency FROM exam_batches
                 WHERE (agency LIKE ? OR exam_date LIKE ?) ORDER BY exam_date DESC, id DESC LIMIT ' . $limit,
                [$like, $like]
            ),
            'exam_record' => Database::rows(
                'SELECT r.id, r.exam_date, r.exam_type, w.name FROM exam_records r JOIN workers w ON w.id = r.worker_id
                 WHERE (w.name LIKE ? OR w.archive_no LIKE ?) ORDER BY r.exam_date DESC, r.id DESC LIMIT ' . $limit,
                [$like, $like]
            ),
            'disease' => Database::rows(
                'SELECT d.id, d.name, d.is_suspected, d.diag_date, w.name AS worker_name FROM diseases d
                 JOIN workers w ON w.id = d.worker_id
                 WHERE (d.name LIKE ? OR w.name LIKE ?) ORDER BY d.id DESC LIMIT ' . $limit,
                [$like, $like]
            ),
            default => [],
        };
        return array_map(fn ($r) => ['id' => (int)$r['id'], 'label' => self::format($type, $r)], $rows);
    }

    /** 某条业务记录名下的全部原件 */
    public static function forLink(string $type, int $id): array
    {
        return Database::rows(
            'SELECT * FROM documents WHERE link_type = ? AND link_id = ? ORDER BY id DESC',
            [$type, $id]
        );
    }

    /**
     * 列表页用的原件摘要列：doc_count（份数）、doc_id（最近一份，用于快捷查看）。
     * $idExpr 为业务记录主键的 SQL 表达式，如 'r.id'。
     */
    public static function summarySql(string $type, string $idExpr): string
    {
        $w = "d.link_type = '" . $type . "' AND d.link_id = " . $idExpr;
        return "(SELECT COUNT(*) FROM documents d WHERE {$w}) AS doc_count, "
             . "(SELECT MAX(d.id) FROM documents d WHERE {$w}) AS doc_id";
    }

    /** 给已取出的单行补上 doc_count / doc_id */
    public static function withSummary(array $row, string $type): array
    {
        $r = Database::row(
            'SELECT COUNT(*) AS c, MAX(id) AS m FROM documents WHERE link_type = ? AND link_id = ?',
            [$type, (int)$row['id']]
        );
        $row['doc_count'] = (int)($r['c'] ?? 0);
        $row['doc_id'] = $r['m'] !== null ? (int)$r['m'] : 0;
        return $row;
    }

    /** 业务页上传入口：预选分类与关联对象，保存后回到该业务页 */
    public static function uploadUrl(string $type, int $id): string
    {
        $code = DocTypes::defaultForLink($type);
        return url('documents/create', [
            'category'  => self::defaultCategory($type),
            'link_type' => $type,
            'link_id'   => $id,
            'back'      => 1,
        ] + ($code !== '' ? ['doc_type' => $code] : []));
    }

    /** 台账记录的可读名称：list 首列 + 日期列（有则拼） */
    private static function formatLedger(array $cfg, array $r): string
    {
        $first = (string)($r[$cfg['list'][0] ?? 'id'] ?? '');
        // 找一个日期列拼进标签
        foreach ($cfg['fields'] as $f) {
            if (($f['type'] ?? '') === 'date' && !empty($r[$f['name']])) {
                return trim($first . ' · ' . $r[$f['name']]);
            }
        }
        return $first !== '' ? $first : ('#' . $r['id']);
    }

    private static function format(string $type, array $r): string
    {
        return match ($type) {
            'worker'           => trim($r['name'] . (($r['archive_no'] ?? '') !== '' ? '（' . $r['archive_no'] . '）' : '')),
            'workplace', 'project' => (string)$r['name'],
            'detection_report' => trim($r['report_no'] . ' · ' . $r['agency'] . (($r['test_date'] ?? '') !== '' ? ' · ' . $r['test_date'] : '')),
            'exam_batch'       => trim(($r['exam_date'] ?: '未填日期') . ' · ' . $r['exam_type'] . ' · ' . $r['agency']),
            'exam_record'      => trim(($r['exam_date'] ?: '未填日期') . ' ' . $r['name'] . ' · ' . $r['exam_type']),
            'disease'          => trim(($r['worker_name'] ?? '') . ' · ' . ((int)($r['is_suspected'] ?? 0) === 1 ? '疑似' : '') . $r['name']
                . (($r['diag_date'] ?? '') !== '' ? ' · ' . $r['diag_date'] : '')),
            'archive_material' => (string)($r['title'] ?? ''),
            default            => '',
        };
    }
}
