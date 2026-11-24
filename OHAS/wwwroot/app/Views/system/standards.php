<?php
/** 标准依据：系统数据口径引用的现行法律法规、部门规章与技术标准 */
$groups = [
    [
        'title' => '一、法律法规与部门规章',
        'items' => [
            [
                'name' => '《中华人民共和国职业病防治法》',
                'no' => '2018年修正版（现行）',
                'date' => '2018-12-29 修正',
                'status' => '现行',
                'effect' => '系统合法性总纲：第33条职业健康监护档案、第35条上岗前/在岗/离岗职业健康检查等义务来源',
            ],
            [
                'name' => '《工作场所职业卫生管理规定》',
                'no' => '国家卫生健康委员会令第5号',
                'date' => '2021-02-01 施行（代替原安监总局令第47号）',
                'status' => '现行',
                'effect' => '用人单位职业卫生管理的部门规章依据，承继原47号令档案与监护管理要求',
            ],
            [
                'name' => '《职业健康检查管理办法》',
                'no' => '国家卫生计生委令第9号公布，2019年修正',
                'date' => '2015-05-01 施行',
                'status' => '现行',
                'effect' => '体检机构资质、检查类别与报告管理依据（委托职业健康检查机构时对照）',
            ],
            [
                'name' => '《职业卫生档案管理规范》（附六类档案样表）',
                'no' => '安监总厅安健〔2013〕101号 印发',
                'date' => '2013-12-31 印发',
                'status' => '现行',
                'effect' => '本系统档案体系与23个样表（表6-1/6-2/6-3 等）的直接来源；其制定所引47号/49号令已由卫生健康部门规章承继',
            ],
            [
                'name' => '《职业病分类和目录》',
                'no' => '国卫职健发〔2024〕39号（四部门联合印发）',
                'date' => '2025-08-01 施行（同时废止2013年版）',
                'status' => '现行',
                'effect' => '法定职业病范围：12大类135种（含4项开放性条款），新增职业性肌肉骨骼疾病、职业性精神和行为障碍两类；职业病诊断模块病种口径',
            ],
            [
                'name' => '《职业病危害因素分类目录》',
                'no' => '国卫疾控发〔2015〕92号（四部门联合印发）',
                'date' => '2015-11-17 印发并施行',
                'status' => '现行',
                'effect' => '危害因素字典的法定口径：粉尘/化学/物理/放射性/生物/其他六类共459条（含各类开放性条款），字典按目录条目、CAS号与适用范围建库；151 条「可能职业病」按《职业病分类和目录》（2024版）病种及 GBZ 57/GBZ 237/GBZ 18/GBZ 22/GBZ 35 等诊断标准预填',
            ],
        ],
    ],
    [
        'title' => '二、强制性国家职业卫生标准',
        'items' => [
            [
                'name' => 'GBZ 188-2025《职业健康监护技术规范》',
                'no' => '代替 GBZ 188-2014',
                'date' => '2025-08-20 发布，2026-08-01 施行',
                'status' => '现行',
                'effect' => '在职体检模块核心依据：四类法定检查（上岗前/在岗期间/离岗时/应急健康检查）、检查结论四类口径（目前未见异常/疑似职业病/职业禁忌证/其他疾病或异常）、目标疾病与监护周期',
            ],
            [
                'name' => 'GBZ 2.1-2019《工作场所有害因素职业接触限值 第1部分：化学有害因素》',
                'no' => '含第1号修改单（2022-11-08）、第2号修改单 GBZ 2.1-2019/XG2-2024',
                'date' => '2020-04-01 施行；第2号修改单 2025-05-01 施行（表1化学因素增至359项）',
                'status' => '现行',
                'effect' => '危害因素字典化学因素与粉尘接触限值（PC-TWA/PC-STEL/MAC）判定依据',
            ],
            [
                'name' => 'GBZ 2.2-2007《工作场所有害因素职业接触限值 第2部分：物理因素》',
                'no' => '现行版',
                'date' => '2007-11-01 施行',
                'status' => '现行',
                'effect' => '物理因素接触限值依据（如噪声 85 dB(A) 8小时等效声级、高温 WBGT 指数、手传振动等）',
            ],
            [
                'name' => 'GB 18871《电离辐射防护与电离辐射源安全基本标准》',
                'no' => '2002-10-08 发布',
                'date' => '2003-04-01 施行',
                'status' => '现行',
                'effect' => '放射性危害因素（电离辐射）职业照射剂量限值依据（放射工作人员档案另按放射卫生系列标准管理）',
            ],
        ],
    ],
    [
        'title' => '三、推荐性国家职业卫生标准（相关）',
        'items' => [
            [
                'name' => 'GBZ/T 229.1/.2/.3-2025《职业病危害作业分级》第1~3部分',
                'no' => '分别代替 GBZ/T 229.1/.2/.3-2010（生产性粉尘/化学物/高温作业）',
                'date' => '2025-08-20 发布，2026-02-01 施行',
                'status' => '现行',
                'effect' => '岗位危害作业分级口径（检测评价模块与分级管理引用）',
            ],
            [
                'name' => 'GBZ/T 340/341/342-2025（粉尘危害预防控制、爆破炮烟、呼吸性粉尘分级管理）',
                'no' => 'GBZ/T 340-2025、GBZ/T 341-2025、GBZ/T 342-2025',
                'date' => '2025-08-20 发布，2026-02-01 施行',
                'status' => '现行',
                'effect' => '粉尘危害预防控制与分级管理配套标准',
            ],
            [
                'name' => 'GBZ/T 224-2016《职业卫生名词术语》',
                'no' => '代替 GBZ/T 224-2010',
                'date' => '2016-06-01 施行',
                'status' => '现行',
                'effect' => '系统术语口径（职业健康监护、职业禁忌证、接触限值等名词定义）',
            ],
            [
                'name' => 'WS/T 880—2025《用人单位职业健康信息系统基本功能标准》',
                'no' => '卫生行业推荐性标准',
                'date' => '2025-12-12 发布，2026-06-01 施行',
                'status' => '现行',
                'effect' => '系统功能设计参考：系统管理、数据采集与管理、共享协同、职业健康管理（基础信息/综合管理/工作场所与作业/健康管理）及安全要求；本系统档案栏目与台账按其功能框架组织',
            ],
            [
                'name' => 'GBZ/T 192 系列 / GBZ/T 300 系列（工作场所粉尘与有毒物质测定）',
                'no' => '现行有效版本',
                'date' => '系列标准（部分分号已更新至2025版）',
                'status' => '现行',
                'effect' => '检测报告模块的检测方法标准',
            ],
        ],
    ],
];
?>
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <span class="avatar bg-green-lt"><i class="ti ti-book fs-2"></i></span>
        <div class="col">
            <div class="h3 mb-1">系统引用标准清单（现行有效）</div>
            <div class="text-secondary">
                本系统危害因素字典、体检种类与结论、样表口径均以上述现行版本为准；
                国家标准发布更新后，请及时在「四、监测检测档案 → 危害因素字典」中同步维护，并核对本页清单。
            </div>
        </div>
        <a class="btn" href="<?= url('standards/diseases') ?>"><i class="ti ti-virus me-1"></i>职业病分类和目录全文</a>
    </div>
</div>

<?php foreach ($groups as $g): ?>
<div class="card mb-3">
    <div class="card-header"><h3 class="card-title"><?= e($g['title']) ?></h3></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr><th class="w-1">状态</th><th style="min-width:260px">名称 / 编号</th><th style="min-width:220px">施行时间</th><th>在系统中的作用</th></tr>
            </thead>
            <tbody>
                <?php foreach ($g['items'] as $it): ?>
                <tr>
                    <td><span class="badge bg-green-lt"><?= e($it['status']) ?></span></td>
                    <td>
                        <div class="strong"><?= e($it['name']) ?></div>
                        <div class="text-secondary small"><?= e($it['no']) ?></div>
                    </td>
                    <td class="small"><?= e($it['date']) ?></td>
                    <td class="text-secondary small" style="min-width:280px"><?= e($it['effect']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<div class="card">
    <div class="card-header"><h3 class="card-title">使用提示</h3></div>
    <div class="card-body">
        <ul class="list-unstyled lh-lg">
            <li><i class="ti ti-check text-green me-1"></i>体检模块结论口径已按 <b>GBZ 188-2025</b> 执行（四类判定，不再使用「复查」结论）</li>
            <li><i class="ti ti-check text-green me-1"></i>检测模块判定结论（合格/超标）按 <b>GBZ 2.1-2019（含第2号修改单）</b>与 <b>GBZ 2.2-2007</b> 现行职业接触限值执行；报告分类（定期检测/日常监测/现状评价/控制效果评价）对应监测检测档案目录第 8~9 项</li>
            <li><i class="ti ti-check text-green me-1"></i>危害因素字典限值请按 <b>GBZ 2.1-2019（含第2号修改单）</b>与 <b>GBZ 2.2-2007</b> 现行文本核对维护</li>
            <li><i class="ti ti-check text-green me-1"></i>职业病诊断登记的病种应在 <b>《职业病分类和目录》（2024版，12类135种）</b> 范围内，可在本页查看 <a href="<?= url('standards/diseases') ?>">目录全文</a></li>
            <li><i class="ti ti-check text-green me-1"></i>六类档案栏目严格按 <b>《职业卫生档案管理规范》</b>所列六类档案的 23 张表样组织（表样编号 表1-1 ～ 表6-3），表2-2、4-2、5-2~5-4、5-6 由业务数据自动汇总</li>
            <li><i class="ti ti-check text-green me-1"></i>档案与数据管理功能框架参考 <b>WS/T 880—2025</b>（2026-06-01 施行）</li>
            <li><i class="ti ti-alert-triangle text-yellow me-1"></i>打印表样版式依据《职业卫生档案管理规范》（安监总厅安健〔2013〕101号）所附样表；个别注释已按新标准口径同步（如表6-3 结论口径）</li>
        </ul>
    </div>
</div>
