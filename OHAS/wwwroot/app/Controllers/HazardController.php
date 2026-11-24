<?php
/**
 * 危害因素字典：名称、类别、可能导致职业病、接触限值备注。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Dict;
use App\Models\Hazard;

class HazardController extends Controller
{
    public function index(): void
    {
        $q = (string)$this->get('q');
        $cat = (string)$this->get('category');
        $status = (string)$this->get('status');
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(name LIKE :q1 OR possible_disease LIKE :q2 OR cas_no LIKE :q3)';
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
            $params[':q3'] = "%{$q}%";
        }
        if ($cat !== '') {
            $where[] = 'category = :cat';
            $params[':cat'] = $cat;
        }
        if ($status !== '') {
            $where[] = 'status = :st';
            $params[':st'] = $status;
        }
        $page = max(1, (int)$this->get('page', 1));
        // 每页条数：20 / 30 / 50 可选，默认 20（字典条目多，全局默认 10 偏少）
        $size = (int)$this->get('ps', 20);
        if (!in_array($size, [20, 30, 50], true)) {
            $size = 20;
        }
        $order = "CASE category
                    WHEN '粉尘' THEN 1 WHEN '化学因素' THEN 2 WHEN '物理因素' THEN 3
                    WHEN '放射性因素' THEN 4 WHEN '生物因素' THEN 5 WHEN '其他因素' THEN 6 ELSE 9 END,
                  catalog_no IS NULL, catalog_no, name";
        $data = Hazard::paginate(implode(' AND ', $where), $params, $order, $page, $size);
        $this->view('hazards/index', [
            'data' => $data,
            'q' => $q, 'cat' => $cat, 'status' => $status, 'ps' => $size,
            'categories' => Dict::hazardCategories(),
        ], '危害因素字典', 'hazards');
    }

    public function create(): void
    {
        $this->form([
            'id' => 0, 'name' => '', 'category' => '',
            'catalog_no' => '', 'cas_no' => '', 'restriction' => '',
            'possible_disease' => '', 'limit_note' => '',
            'catalog_source' => '自定义', 'status' => '启用', 'remark' => '',
        ]);
    }

    public function edit(): void
    {
        $id = (int)$this->get('id');
        $hazard = Hazard::find($id);
        if (!$hazard) {
            $this->flash('danger', '记录不存在。');
            $this->redirect('hazards/index');
        }
        $this->form($hazard);
    }

    private function form(array $hazard): void
    {
        if ($this->isPost()) {
            $this->verifyCsrf();
            // 校验失败时回到来源页：编辑 → edit?id=，新增 → create（避免编辑中途被送到「新增」页）
            $editId = (int)($hazard['id'] ?? 0);
            $failTo = $editId > 0 ? 'hazards/edit' : 'hazards/create';
            $failParams = $editId > 0 ? ['id' => $editId] : [];
            $v = \App\Core\Validator::make($_POST, [
                'name' => 'required|max:60',
                'category' => 'required|max:20',
                'catalog_no' => 'int',
                'cas_no' => 'max:40',
                'restriction' => 'max:200',
                'possible_disease' => 'max:100',
                'limit_note' => 'max:200',
                'status' => 'required|in:启用,停用',
                'remark' => 'max:200',
            ], [
                'name' => '危害因素名称', 'category' => '类别', 'catalog_no' => '目录序号',
                'cas_no' => 'CAS 号', 'restriction' => '目录备注',
                'possible_disease' => '可能导致的职业病',
                'limit_note' => '接触限值备注', 'status' => '状态', 'remark' => '备注',
            ]);
            $data = $v->fails() ? null : $v->validated();
            if ($data === null) {
                $this->failBack($failTo, $v->errors(), $_POST, $failParams);
            }
            $data['catalog_no'] = ($data['catalog_no'] ?? '') === '' ? null : (int)$data['catalog_no'];
            // 同名同类别去重
            $dup = \App\Core\Database::row(
                'SELECT id FROM hazards WHERE name = ? AND category = ? AND id != ?',
                [$data['name'], $data['category'], (int)($hazard['id'] ?? 0)]
            );
            if ($dup) {
                $this->failBack($failTo, ['name' => '该类别下已存在同名危害因素。'], $_POST, $failParams);
            }
            if (!empty($hazard['id'])) {
                $data['catalog_source'] = (string)($hazard['catalog_source'] ?? '');
                Hazard::update((int)$hazard['id'], $data);
                $this->log('update', 'hazard', (int)$hazard['id'], '修改危害因素：' . $data['name']);
                $this->flash('success', '危害因素已更新。');
                // 停用不级联解除岗位配置，接害人数仍按配置照算；提示经办人核对引用范围
                if (($data['status'] ?? '') === '停用') {
                    $refs = Hazard::refCount((int)$hazard['id']);
                    if ($refs > 0) {
                        $this->flash('warning', '该危害因素仍被 ' . $refs . ' 处业务数据引用（岗位配置/检测明细/台账）；停用不会自动解除配置，相关岗位的接害人数仍会照算。请先核对并解除引用。');
                    }
                }
                $this->redirect('hazards/index');
            }
            $data['catalog_source'] = '自定义';
            $newId = Hazard::insert($data);
            $this->log('create', 'hazard', $newId, '新增危害因素：' . $data['name']);
            $this->flash('success', '危害因素已添加。');
            $this->redirect('hazards/index');
        }
        $this->view('hazards/form', [
            'hazard' => $hazard,
            'categories' => Dict::hazardCategories(),
        ], ((int)($hazard['id'] ?? 0)) > 0 ? '编辑危害因素' : '新增危害因素', 'hazards');
    }

    public function delete(): void
    {
        $this->verifyCsrf();
        $id = (int)$this->post('id');
        $hazard = Hazard::find($id);
        if (!$hazard) {
            $this->json(['ok' => false, 'msg' => '记录不存在。']);
        }
        if (Hazard::referenced($id)) {
            $this->json(['ok' => false, 'msg' => '该危害因素已被岗位配置、检测明细、设备材料化学品（表4-1）或防护用品发放（表2-6）引用，不能删除；如不再使用可将其「停用」。']);
        }
        Hazard::delete($id);
        $this->log('delete', 'hazard', $id, '删除危害因素：' . $hazard['name']);
        $this->json(['ok' => true, 'msg' => '已删除。']);
    }

    /** 从《职业病危害因素分类目录》同步/补充字典（不删除自建条目） */
    public function syncCatalog(): void
    {
        $this->verifyCsrf();
        $r = Hazard::syncCatalog();
        $this->log('update', 'hazard', null, "同步职业病危害因素分类目录：新增 {$r['inserted']} 条，更新 {$r['updated']} 条");
        $this->flash('success', "目录同步完成：新增 {$r['inserted']} 条，更新 {$r['updated']} 条。");
        $this->redirect('hazards/index');
    }
}
