<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Worker extends Model
{
    protected static string $table = 'workers';

    /**
     * 列表检索：关键字（姓名/档案号/身份证号）+ 状态 + 当前岗位筛选。
     */
    public static function search(string $q, string $status, int $postId, int $page, int $size): array
    {
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            // 注意：PDO 原生预处理下同一命名参数不可复用，使用 q1/q2/q3
            $where[] = '(name LIKE :q1 OR archive_no LIKE :q2 OR id_card LIKE :q3)';
            $params[':q1'] = '%' . $q . '%';
            $params[':q2'] = '%' . $q . '%';
            $params[':q3'] = '%' . $q . '%';
        }
        if ($status !== '') {
            $where[] = 'status = :st';
            $params[':st'] = $status;
        }
        if ($postId > 0) {
            $where[] = 'id IN (SELECT worker_id FROM worker_posts WHERE post_id = :pid AND end_date IS NULL)';
            $params[':pid'] = $postId;
        }
        $w = implode(' AND ', $where);
        $result = static::paginate($w, $params, 'id DESC', $page, $size);
        // 附带当前岗位名
        foreach ($result['rows'] as &$row) {
            $row['current_post'] = Database::value(
                'SELECT post_name FROM worker_posts WHERE worker_id = ? AND post_id IS NOT NULL AND end_date IS NULL ORDER BY id DESC LIMIT 1',
                [$row['id']]
            ) ?? '';
        }
        return $result;
    }

    /** 详情：基本信息 + 三张子表 */
    public static function detail(int $id): ?array
    {
        $worker = self::find($id);
        if ($worker === null) {
            return null;
        }
        $worker['history'] = Database::rows(
            'SELECT * FROM worker_posts WHERE worker_id = ? ORDER BY start_date ASC, id ASC',
            [$id]
        );
        $worker['illnesses'] = Database::rows(
            'SELECT * FROM worker_illnesses WHERE worker_id = ? ORDER BY diag_date ASC, id ASC',
            [$id]
        );
        $worker['diseases'] = Database::rows(
            'SELECT * FROM diseases WHERE worker_id = ? ORDER BY diag_date ASC, id ASC',
            [$id]
        );
        // 工作场所危害因素检测结果（表6-2），按岗位/危害+检测日期区间归集
        $worker['detections'] = \App\Models\DetectionItem::forWorker($id);
        // 历次职业健康检查（表6-3），按年分组近年在前
        $worker['exams'] = \App\Models\ExamRecord::forWorkerGrouped($id);
        $worker['exams_total'] = array_sum(array_map('count', $worker['exams']));
        return $worker;
    }

    /** 在岗且当前岗位配置了危害因素的人数（接害人数口径，唯一来源 Exposure） */
    public static function exposedCount(): int
    {
        return \App\Helpers\Exposure::currentCount();
    }

    /** 身份证号是否已被他人占用 */
    public static function idCardTaken(string $idCard, int $exceptId = 0): bool
    {
        if ($idCard === '') {
            return false;
        }
        return (bool)Database::value(
            'SELECT COUNT(*) FROM workers WHERE id_card = ? AND id != ?',
            [$idCard, $exceptId]
        );
    }
}
