<?php
/**
 * 全局搜索：顶栏输入姓名 / 身份证号 / 档案号。
 * 规则：
 *  - 18 位且通过校验的身份证号 → 精确匹配；
 *  - 其余复用 Worker::search 口径（姓名 / 档案号 / 身份证号模糊）；
 *  - 命中 1 人 → 302 到 workers/files（个人资料全景页）；
 *  - 多人 → 列表（身份证号脱敏）；
 *  - 无结果 → 提示并给“新建信息卡”入口。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\IdCard;
use App\Models\Worker;

class SearchController extends Controller
{
    public function index(): void
    {
        $q = trim((string)$this->get('q'));
        if ($q === '') {
            $this->view('search/index', [
                'q' => '', 'rows' => [], 'exact' => false,
            ], '全局搜索', 'workers');
            return;
        }

        $rows = [];
        $exact = false;
        $upper = strtoupper($q);
        if (preg_match('/^\d{17}[\dX]$/', $upper) && IdCard::valid($upper)) {
            $exact = true;
            $rows = Database::rows(
                'SELECT id, archive_no, name, gender, status, id_card,
                        (SELECT post_name FROM worker_posts wp WHERE wp.worker_id = workers.id AND wp.end_date IS NULL AND wp.post_id IS NOT NULL ORDER BY wp.id DESC LIMIT 1) AS current_post
                 FROM workers WHERE id_card = ?',
                [$upper]
            );
        }
        if (!$rows) {
            $exact = false;
            $result = Worker::search($q, '', 0, 1, 100);
            $rows = $result['rows'];
        }

        if (count($rows) === 1) {
            $this->redirect('workers/files', ['id' => (int)$rows[0]['id']]);
        }

        $this->view('search/index', [
            'q' => $q,
            'rows' => $rows,
            'exact' => $exact,
        ], '全局搜索', 'workers');
    }
}
