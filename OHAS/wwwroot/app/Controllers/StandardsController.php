<?php
/**
 * 标准依据：系统引用的现行法律法规与技术标准清单（只读展示）。
 * 版本信息以国家卫生健康委等发布机关公开文本为准，标准更新后请同步维护基础数据。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

class StandardsController extends Controller
{
    public function index(): void
    {
        $this->view('system/standards', [], '标准依据', 'standards');
    }

    /** 《职业病分类和目录》（2024版）全文对照 */
    public function diseases(): void
    {
        $this->view('system/diseases', [], '职业病分类和目录', 'standards');
    }
}
