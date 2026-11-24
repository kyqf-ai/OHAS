<?php
/**
 * 事件线索人员名单：工作台/资料清单「事件线索 X 人」的明细页。
 * 只列线索（体检禁忌证/疑似/复查），不判缺失；点人进入个人页补录对应事件资料。
 */
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
        <a href="<?= url('archive/checklist') ?>" class="text-secondary"><i class="ti ti-arrow-left me-1"></i>返回资料清单</a>
        <div class="text-secondary mt-1">事件类资料线索：职业禁忌证调岗 / 复查材料 / 职业病诊断 / 离岗档案复印件签收（按异常年份与材料年份匹配）</div>
    </div>
    <span class="badge bg-secondary-lt fs-5"><?= count($rows) ?> 人</span>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
            <tr><th>档案号</th><th>姓名</th><th>待补事件资料</th><th class="w-1"></th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="4" class="text-center text-secondary py-5">暂无事件线索</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td class="font-monospace"><?= e($r['archive_no']) ?></td>
                    <td><?= e($r['name']) ?></td>
                    <td><?= e($r['labels']) ?></td>
                    <td class="text-end"><a class="btn btn-sm" href="<?= url('workers/files', ['id' => (int)$r['id']]) ?>">查看个人档案 <i class="ti ti-chevron-right ms-1"></i></a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="text-secondary small mt-3">
    <i class="ti ti-info-circle me-1"></i>本页只列线索，不判定缺失——事件类资料按实际发生情况归档，见资料清单 C29–C32。
</div>
