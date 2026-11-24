<?php
/**
 * 表5-3 职业病患者一览表 / 表5-4 疑似职业病患者一览表。
 * 数据来自劳动者信息卡中的「职业病诊断」（diseases），此处按单位汇总成名单，
 * 不重复建表；新增、修改仍在对应劳动者信息卡内完成。
 * 体检结论为疑似职业病/职业病的记录可在体检记录页一键生成登记（diseases.exam_record_id 记录来源），
 * 本页顶部提示尚未生成登记的体检记录数，避免表5-2 与表5-3/5-4 对不上。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class PatientController extends Controller
{
    public function index(): void
    {
        $type = (string)$this->get('type') === 'suspected' ? 'suspected' : 'confirmed';
        $suspected = $type === 'suspected' ? 1 : 0;
        $q = (string)$this->get('q');

        $where = 'd.is_suspected = :s';
        $params = [':s' => $suspected];
        if ($q !== '') {
            $where .= ' AND (w.name LIKE :q1 OR w.archive_no LIKE :q2 OR d.name LIKE :q3)';
            $params[':q1'] = $params[':q2'] = $params[':q3'] = '%' . $q . '%';
        }

        $size = max(5, (int)cfg('page.size', 10));
        $total = (int)Database::value(
            "SELECT COUNT(*) FROM diseases d JOIN workers w ON w.id = d.worker_id WHERE {$where}",
            $params
        );
        $pages = max(1, (int)ceil($total / $size));
        $page = min(max(1, (int)$this->get('page', 1)), $pages);
        $rows = Database::rows(
            "SELECT d.*, er.exam_date AS src_exam_date, er.exam_type AS src_exam_type, w.name AS worker_name, w.archive_no, w.status AS worker_status,
                    (SELECT post_name FROM worker_posts wp WHERE wp.worker_id = w.id AND wp.end_date IS NULL AND wp.post_id IS NOT NULL ORDER BY wp.id DESC LIMIT 1) AS current_post,
                    " . \App\Helpers\DocLink::summarySql('disease', 'd.id') . "
             FROM diseases d JOIN workers w ON w.id = d.worker_id
             LEFT JOIN exam_records er ON er.id = d.exam_record_id
             WHERE {$where}
             ORDER BY d.diag_date DESC, d.id DESC
             LIMIT {$size} OFFSET " . (($page - 1) * $size),
            $params
        );

        $title = $suspected ? '疑似职业病患者一览表（表5-4）' : '职业病患者一览表（表5-3）';
        $this->view('patients/index', [
            'rows' => $rows,
            'type' => $type,
            'q' => $q,
            'pending' => \App\Models\ExamRecord::pendingDiseaseCount($suspected),
            'data' => ['total' => $total, 'page' => $page, 'pages' => $pages],
        ], $title, 'patients');
    }
}
