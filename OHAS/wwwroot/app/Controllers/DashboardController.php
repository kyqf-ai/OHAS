<?php
/**
 * 工作台：概览卡片 + 数据准备向导 + 最近建档。
 * 汇总基础数据概览与各业务模块的本年统计卡片。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Company;
use App\Models\Worker;

class DashboardController extends Controller
{
    public function index(): void
    {
        $stats = [
            'workers'        => Worker::count(),
            'workers_onjob'  => Worker::count("status = '在职'"),
            'exposed'        => Worker::exposedCount(),
            'reports_year'   => (int)Database::value("SELECT COUNT(*) FROM detection_reports WHERE test_date LIKE ?", [date('Y') . '%']),
            'detect_exceed'  => (int)Database::value(
                "SELECT COUNT(*) FROM detection_items di JOIN detection_reports dr ON dr.id = di.report_id
                 WHERE dr.test_date LIKE ? AND di.verdict = '超标'",
                [date('Y') . '%']
            ),
            // 按今年体检年份统计，与相邻卡片年度口径一致
            'exam_abnormal'  => \App\Models\ExamRecord::abnormalCount(date('Y')),
            'exam_batches'   => (int)Database::value("SELECT COUNT(*) FROM exam_batches WHERE exam_date LIKE ?", [date('Y') . '%']),
            // 实检口径：已出结论的检查记录（待出结论不计入，与体检模块统计一致）
            'exam_records'   => (int)Database::value("SELECT COUNT(*) FROM exam_records WHERE exam_date LIKE ? AND TRIM(COALESCE(conclusion,'')) <> ''", [date('Y') . '%']),
            'exam_pending'   => (int)Database::value("SELECT COUNT(*) FROM exam_records WHERE exam_date LIKE ? AND TRIM(COALESCE(conclusion,'')) = ''", [date('Y') . '%']),
            'documents'      => (int)Database::value('SELECT COUNT(*) FROM documents'),
            'workplaces'     => (int)Database::value("SELECT COUNT(*) FROM workplaces WHERE status='启用'"),
            'posts'          => (int)Database::value("SELECT COUNT(*) FROM posts WHERE status='启用'"),
            'hazards'        => (int)Database::value("SELECT COUNT(*) FROM hazards WHERE status='启用'"),
            'posts_no_hazard' => (int)Database::value(
                "SELECT COUNT(*) FROM posts p WHERE p.status='启用'
                 AND NOT EXISTS (SELECT 1 FROM post_hazards ph WHERE ph.post_id = p.id)"
            ),
            'workers_no_history' => (int)Database::value(
                "SELECT COUNT(*) FROM workers w WHERE w.status='在职'
                 AND NOT EXISTS (SELECT 1 FROM worker_posts wp WHERE wp.worker_id = w.id)"
            ),
        ];

        // 数据准备向导（对应规划书 9.2 建议顺序）
        $company = Company::row();
        $checklist = [
            ['done' => trim((string)$company['name']) !== '', 'label' => '录入单位信息', 'url' => url('company/edit'), 'hint' => '名称、地址、负责人等（可稍后补全）'],
            ['done' => $stats['hazards'] > 0,            'label' => '维护危害因素字典', 'url' => url('hazards/index'), 'hint' => '已按《职业病危害因素分类目录》内置459条，可按实际启用/停用或增补'],
            ['done' => $stats['workplaces'] > 0,         'label' => '建立作业场所', 'url' => url('workplaces/index'), 'hint' => '车间、区域及其位置说明'],
            ['done' => $stats['posts'] > 0,              'label' => '建立岗位并配置危害', 'url' => url('posts/index'), 'hint' => '先场所、次岗位、再危害配置'],
            ['done' => $stats['workers'] > 0,            'label' => '劳动者建档', 'url' => url('workers/index'), 'hint' => '支持CSV批量导入基本信息'],
        ];

        $recent = Database::rows(
            'SELECT w.id, w.name, w.gender, w.archive_no, w.status, w.entry_date, w.created_at,
                    (SELECT post_name FROM worker_posts wp WHERE wp.worker_id = w.id AND wp.end_date IS NULL ORDER BY wp.id DESC LIMIT 1) AS current_post
             FROM workers w ORDER BY w.id DESC LIMIT 8'
        );

        // 体检异常提醒与卡片统一走 ExamRecord 的异常全量口径（表5-2）
        $abnormalRecent = \App\Models\ExamRecord::abnormalRecent(5);
        // 与「今年体检异常」卡片同年同口径；未闭环含待登记 / 待处置 / 待告知
        $dispositionStats = \App\Models\ExamRecord::dispositionStats(date('Y'));

        // 资料归档卡片：36 项状态项数 + 有缺口的劳动者人数，并给出前 10 人列表入口
        $checklistStats = \App\Helpers\ArchiveChecklist::stats();
        $gapWorkers = \App\Helpers\WorkerArchive::gapWorkers();

        // 到期提醒：阈值统一读取 config.expiry.soon_days（与资料清单同一口径）
        $today = date('Y-m-d');
        $soonDays = max(1, (int)cfg('expiry.soon_days', 30));
        $expireStats = [
            'expired' => (int)Database::value("SELECT COUNT(*) FROM documents WHERE expire_date IS NOT NULL AND expire_date <> '' AND expire_date < ?", [$today]),
            'soon'    => (int)Database::value("SELECT COUNT(*) FROM documents WHERE expire_date IS NOT NULL AND expire_date <> '' AND expire_date >= ? AND expire_date <= ?", [$today, date('Y-m-d', strtotime($today . ' +' . $soonDays . ' days'))]),
        ];
        $expireStats['soon_days'] = $soonDays;

        $this->view('dashboard/index', [
            'stats'     => $stats,
            'checklist' => $checklist,
            'recent'    => $recent,
            'company'   => $company,
            'abnormalRecent' => $abnormalRecent,
            'dispositionStats' => $dispositionStats,
            'checklistStats' => $checklistStats,
            'gapCount'  => count($gapWorkers),
            'gapWorkers' => array_slice($gapWorkers, 0, 10, true),
            'expireStats' => $expireStats,
        ], '工作台', 'dashboard');
    }
}
