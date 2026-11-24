<?php
/**
 * 单位信息（单行表单，对应表2-2基本信息项）。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Company;

class CompanyController extends Controller
{
    public function index(): void
    {
        $this->redirect('company/edit');
    }

    public function edit(): void
    {
        $company = Company::row();
        if ($this->isPost()) {
            $this->verifyCsrf();
            $v = \App\Core\Validator::make($_POST, [
                'name'            => 'required|max:100',
                'credit_code'     => 'max:50',
                'reg_address'     => 'max:200',
                'work_address'    => 'max:200',
                'legal_person'    => 'max:50',
                'hygienist_name'  => 'max:50',
                'hygienist_phone' => 'max:50',
                'industry'        => 'max:50',
                'scale'           => 'max:50',
            ], [
                'name' => '单位名称', 'credit_code' => '统一社会信用代码', 'reg_address' => '注册地址',
                'work_address' => '工作场所地址', 'legal_person' => '法定代表人',
                'hygienist_name' => '职业卫生管理负责人', 'hygienist_phone' => '负责人联系方式',
                'industry' => '行业分类', 'scale' => '企业规模',
            ]);
            if ($v->fails()) {
                $this->failBack('company/edit', $v->errors(), $_POST);
            }
            $data = $v->validated();
            Company::save($data);
            $this->log('update', 'company', 1, '更新单位信息');
            $this->flash('success', '单位信息已保存。');
            $this->redirect('company/edit');
        }
        $this->view('company/edit', ['company' => $company], '单位信息', 'company');
    }
}
