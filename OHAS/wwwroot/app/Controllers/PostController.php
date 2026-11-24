<?php
/**
 * 岗位管理：基本信息 + 岗位危害配置（规划书链路一的起点）。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\Dict;
use App\Models\Post;

class PostController extends Controller
{
    public function index(): void
    {
        $q = (string)$this->get('q');
        $all = Post::listWithMeta();
        $rows = [];
        foreach ($all as $row) {
            if ($q !== '' && stripos($row['name'] . $row['workplace_name'], $q) === false) {
                continue;
            }
            $rows[] = $row;
        }
        $this->view('posts/index', ['rows' => $rows, 'q' => $q], '岗位设置', 'posts');
    }

    public function create(): void
    {
        $this->form(['id' => 0, 'name' => '', 'workplace_id' => 0, 'status' => '启用', 'remark' => ''], [], 'create');
    }

    public function edit(): void
    {
        $id = (int)$this->get('id');
        $post = Post::find($id);
        if (!$post) {
            $this->flash('danger', '记录不存在。');
            $this->redirect('posts/index');
        }
        $this->form($post, Post::hazards($id), 'edit');
    }

    private function form(array $post, array $hazards, string $mode): void
    {
        if ($this->isPost()) {
            $this->verifyCsrf();
            // 校验失败时回到来源页：编辑 → edit?id=，新增 → create（避免编辑中途被送到「新增」页）
            $editId = (int)($post['id'] ?? 0);
            $failTo = $editId > 0 ? 'posts/edit' : 'posts/create';
            $failParams = $editId > 0 ? ['id' => $editId] : [];
            $v = \App\Core\Validator::make($_POST, [
                'name' => 'required|max:60',
                'workplace_id' => 'required',
                'status' => 'required|in:启用,停用',
                'remark' => 'max:200',
            ], ['name' => '岗位名称', 'workplace_id' => '所属作业场所', 'status' => '状态', 'remark' => '备注']);
            if ($v->fails()) {
                $this->failBack($failTo, $v->errors(), $_POST, $failParams);
            }
            $workplaceId = (int)$v->validated()['workplace_id'];
            if (!Database::value('SELECT id FROM workplaces WHERE id = ?', [$workplaceId])) {
                $this->failBack($failTo, ['workplace_id' => '所属作业场所不存在。'], $_POST, $failParams);
            }
            $dup = Database::row(
                'SELECT id FROM posts WHERE name = ? AND workplace_id = ? AND id != ?',
                [$v->validated()['name'], $workplaceId, (int)($post['id'] ?? 0)]
            );
            if ($dup) {
                $this->failBack($failTo, ['name' => '该场所下已存在同名岗位。'], $_POST, $failParams);
            }
            // 危害配置行
            $phRows = $this->buildPhRows();
            foreach ($phRows as $i => $r) {
                if (!Database::value('SELECT id FROM hazards WHERE id = ?', [(int)$r['hazard_id']])) {
                    $this->failBack($failTo, ['hazards' => '第' . ($i + 1) . '行危害因素不存在。'], $_POST, $failParams);
                }
            }
            $name = $v->validated()['name'];
            // 同场所同名岗位由 uq_posts_workplace_name 唯一索引兜底，撞索引时回显友好提示
            try {
                $pid = Database::tx(function () use ($post, $v, $workplaceId, $phRows, $mode): int {
                    $data = $v->validated();
                    $data['workplace_id'] = $workplaceId;
                    if ($mode === 'edit') {
                        Post::update((int)$post['id'], $data);
                        $pid = (int)$post['id'];
                    } else {
                        $pid = Post::insert($data);
                    }
                    Post::saveHazards($pid, $phRows);
                    // 在岗职业史快照同步为当前危害配置；已结束段仍保留历史快照
                    \App\Models\WorkerPost::refreshOpenSnapshots($pid);
                    return $pid;
                });
            } catch (\PDOException $e) {
                if (stripos($e->getMessage(), 'UNIQUE') !== false) {
                    $this->failBack($failTo, ['name' => '该场所下已存在同名岗位。'], $_POST, $failParams);
                }
                throw $e;
            }
            if ($mode === 'edit') {
                $this->log('update', 'post', (int)$post['id'], '修改岗位：' . $name);
                $this->flash('success', '岗位已更新。');
                if ($v->validated()['status'] === '停用') {
                    $onjob = (int)Database::value(
                        'SELECT COUNT(DISTINCT wp.worker_id) FROM worker_posts wp JOIN workers w ON w.id = wp.worker_id
                          WHERE wp.post_id = ? AND (wp.end_date IS NULL OR TRIM(wp.end_date) = \'\') AND w.status = \'在职\'',
                        [$pid]
                    );
                    if ($onjob > 0) {
                        $this->flash('warning', '该岗位仍有 ' . $onjob . ' 名在职人员处于在岗职业史段；停用后各项“当前接害人数”会静默记为 0。请先办理调岗/离岗并收尾职业史。');
                    }
                }
            } else {
                $this->log('create', 'post', $pid, '新增岗位：' . $name);
                $this->flash('success', '岗位已添加，请检查危害因素配置。');
            }
            $this->redirect('posts/index');
        }
        $this->view('posts/form', [
            'post' => $post,
            'hazards' => $hazards,
            'mode' => $mode,
            'workplaces' => \App\Models\Workplace::options(),
            'hazardGroups' => \App\Models\Hazard::groupedActive(),
            'exposureTypes' => Dict::exposureTypes(),
        ], $mode === 'edit' ? '编辑岗位' : '新增岗位', 'posts');
    }

    /** 从表单读取危害配置二维数组 */
    private function buildPhRows(): array
    {
        $rows = [];
        $ids = $this->postArray('ph_hazard_id');
        $types = $this->postArray('ph_exposure_type');
        $sources = $this->postArray('ph_source');
        $engs = $this->postArray('ph_engineering_controls');
        $ppes = $this->postArray('ph_ppe');
        // 同一危害因素重复提交只保留一条，避免表4-2 重复计数
        $seenHazard = [];
        foreach ($ids as $i => $hid) {
            if ((string)$hid === '') {
                continue;
            }
            if (in_array((int)$hid, $seenHazard, true)) {
                continue;
            }
            $seenHazard[] = (int)$hid;
            $rows[] = [
                'hazard_id' => (int)$hid,
                'exposure_type' => $types[$i] ?? '',
                'source' => $sources[$i] ?? '',
                'engineering_controls' => $engs[$i] ?? '',
                'ppe' => $ppes[$i] ?? '',
            ];
        }
        return $rows;
    }

    public function delete(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $post = Post::find($id);
        if (!$post) {
            $this->json(['ok' => false, 'msg' => '记录不存在。']);
        }
        if (Post::referenced($id)) {
            $this->json(['ok' => false, 'msg' => '该岗位已被职业史、检测明细或台账（设备材料、防护用品、事故报告）引用，不能删除；如不再使用可将其「停用」。']);
        }
        Post::delete($id);   // 岗位危害配置级联删除
        $this->log('delete', 'post', $id, '删除岗位：' . $post['name']);
        $this->json(['ok' => true, 'msg' => '已删除。']);
    }
}
