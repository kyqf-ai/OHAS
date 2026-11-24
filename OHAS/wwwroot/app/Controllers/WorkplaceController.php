<?php
/**
 * 作业场所：名称与位置说明。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Workplace;

class WorkplaceController extends Controller
{
    public function index(): void
    {
        $q = (string)$this->get('q');
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(name LIKE :q1 OR location LIKE :q2)';
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }
        $page = max(1, (int)$this->get('page', 1));
        $data = Workplace::paginate(implode(' AND ', $where), $params, 'name', $page, cfg('page.size'));
        foreach ($data['rows'] as &$row) {
            $row['post_count'] = (int)\App\Core\Database::value(
                'SELECT COUNT(*) FROM posts WHERE workplace_id = ?',
                [$row['id']]
            );
        }
        $this->view('workplaces/index', ['data' => $data, 'q' => $q], '作业场所', 'workplaces');
    }

    public function create(): void
    {
        $this->form(['id' => 0, 'name' => '', 'location' => '', 'status' => '启用', 'remark' => '']);
    }

    public function edit(): void
    {
        $id = (int)$this->get('id');
        $wp = Workplace::find($id);
        if (!$wp) {
            $this->flash('danger', '记录不存在。');
            $this->redirect('workplaces/index');
        }
        $this->form($wp);
    }

    private function form(array $wp): void
    {
        if ($this->isPost()) {
            $this->verifyCsrf();
            // 校验失败时回到来源页：编辑 → edit?id=，新增 → create（避免编辑中途被送到「新增」页）
            $editId = (int)($wp['id'] ?? 0);
            $failTo = $editId > 0 ? 'workplaces/edit' : 'workplaces/create';
            $failParams = $editId > 0 ? ['id' => $editId] : [];
            $v = \App\Core\Validator::make($_POST, [
                'name' => 'required|max:60',
                'location' => 'max:200',
                'status' => 'required|in:启用,停用',
                'remark' => 'max:200',
            ], ['name' => '场所名称', 'location' => '位置说明', 'status' => '状态', 'remark' => '备注']);
            if ($v->fails()) {
                $this->failBack($failTo, $v->errors(), $_POST, $failParams);
            }
            $data = $v->validated();
            $dup = \App\Core\Database::row(
                'SELECT id FROM workplaces WHERE name = ? AND id != ?',
                [$data['name'], (int)($wp['id'] ?? 0)]
            );
            if ($dup) {
                $this->failBack($failTo, ['name' => '该场所名称已存在。'], $_POST, $failParams);
            }
            if ((int)($wp['id'] ?? 0) > 0) {
                Workplace::update((int)$wp['id'], $data);
                $this->log('update', 'workplace', (int)$wp['id'], '修改作业场所：' . $data['name']);
                $this->flash('success', '作业场所已更新。');
            } else {
                $newId = Workplace::insert($data);
                $this->log('create', 'workplace', $newId, '新增作业场所：' . $data['name']);
                $this->flash('success', '作业场所已添加。');
            }
            $this->redirect('workplaces/index');
        }
        $this->view('workplaces/form', ['wp' => $wp], ((int)($wp['id'] ?? 0)) > 0 ? '编辑作业场所' : '新增作业场所', 'workplaces');
    }

    public function delete(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $wp = Workplace::find($id);
        if (!$wp) {
            $this->json(['ok' => false, 'msg' => '记录不存在。']);
        }
        if (Workplace::referenced($id)) {
            $this->json(['ok' => false, 'msg' => '该场所下已建立岗位，或已被检测记录、防护设施、台账（设备材料、防护用品、警示标识、事故报告）引用，不能删除；如不再使用可将其「停用」。']);
        }
        Workplace::delete($id);
        $this->log('delete', 'workplace', $id, '删除作业场所：' . $wp['name']);
        $this->json(['ok' => true, 'msg' => '已删除。']);
    }
}
