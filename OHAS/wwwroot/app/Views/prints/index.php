<?php
/**
 * 表样打印中心：23 张规范表样 + 3 张系统扩充表样统一入口
 */
use App\Helpers\PrintSpec;
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
        <a href="<?= url('archive/index') ?>" class="text-secondary"><i class="ti ti-arrow-left me-1"></i>返回档案总览</a>
        <div class="text-secondary mt-1">六类职业卫生档案 · 23 张规范表样 + 3 张系统扩充表样 · 统一版式打印</div>
    </div>
    <div class="text-secondary">
        <i class="ti ti-info-circle me-1"></i>打印时选择对应纸张方向（页内已内置提示），打印预览效果即为实际输出
    </div>
</div>

<?php foreach (PrintSpec::groups() as $gno => $gname): ?>
    <?php $groupTables = array_filter($tables, fn ($sp) => PrintSpec::groupNo($sp['no']) === $gno); ?>
    <?php if (!$groupTables) continue; ?>
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title"><?= e($gname) ?></h3>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th style="width: 84px;">表号</th>
                    <th>表样名称</th>
                    <th>数据来源 / 说明</th>
                    <th class="text-center" style="width: 80px;">记录数</th>
                    <th class="text-center" style="width: 90px;">纸张</th>
                    <th class="text-end" style="width: 150px;">操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($groupTables as $no => $sp): ?>
                    <tr>
                        <td class="text-secondary"><?= e($no) ?></td>
                        <td class="text-body">
                            <?= e($sp['title']) ?>
                            <?php if (!empty($sp['extended'])): ?>
                                <span class="badge bg-secondary-lt ms-1" title="非《职业卫生档案管理规范》附件表样">系统扩充</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-secondary"><?= e($sp['intro'] ?? '') ?></td>
                        <td class="text-center"><?= (int)$sp['count'] ?></td>
                        <td class="text-center">
                            <span class="badge <?= $sp['orient'] === 'landscape' ? 'bg-orange-lt' : 'bg-blue-lt' ?>">
                                A4 <?= $sp['orient'] === 'landscape' ? '横向' : '纵向' ?>
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            <?php if ($sp['kind'] === 'person'): ?>
                                <a class="btn btn-sm" href="<?= e($sp['url']) ?>">
                                    按人打印<i class="ti ti-chevron-right ms-1"></i>
                                </a>
                            <?php elseif (in_array($no, ['表2-6', '表4-3'], true) && !empty($yearOptions[$no])): ?>
                                <!-- 年度表：默认最近一个有数据的年份，可切换年份后打印 -->
                                <form method="get" action="<?= url('prints/sheet') ?>" class="d-inline-flex gap-1 align-items-center" target="_blank">
                                    <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="prints/sheet"><?php endif; ?>
                                    <input type="hidden" name="t" value="<?= e($no) ?>">
                                    <select class="form-select form-select-sm" name="year" style="width: auto;">
                                        <?php $cur = $yearOptions[$no][count($yearOptions[$no]) - 1]; ?>
                                        <?php foreach (array_reverse($yearOptions[$no]) as $y): ?>
                                            <option value="<?= e($y) ?>"><?= e($y) ?> 年</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-primary" type="submit" title="按所选年度打印">
                                        <i class="ti ti-printer me-1"></i>打印
                                    </button>
                                </form>
                            <?php else: ?>
                                <a class="btn btn-sm btn-primary" href="<?= e($sp['url']) ?>" target="_blank" title="整表打印">
                                    <i class="ti ti-printer me-1"></i>打印
                                </a>
                                <?php if ($sp['kind'] === 'form'): ?>
                                    <span class="text-secondary small ms-1" title="表单类表样一记录一页">
                                        <i class="ti ti-file-text"></i> 一记录一页
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<div class="card">
    <div class="card-body text-secondary">
        <div class="mb-1"><i class="ti ti-printer me-1"></i><b>打印质量说明</b></div>
        <div class="small">
            全部表样以《职业卫生档案管理规范》所附 Word 样表为版式基准（表头结构、栏目名称、签字栏 1:1 还原），并按表6-1 的统一质量标准执行：
            A4 页边距 12mm、宋体正文、黑色实线表格线、固定列宽（mm 级对齐）、表头灰底加粗、数据不足时自动补空白行供手工填写；
            表单类表样（表1-1 / 2-5 / 2-8 / 2-9 / 3-1 / 5-5）按记录逐份分页打印，长表跨页自动重复表头；
            打印预览与实际输出一致，可另存 PDF 归档电子件。
        </div>
    </div>
</div>
