<?php
/**
 * 单位资料清单：36 项资料按六个官方档案盒分组。
 * 每行：状态徽标、资料名称、自评项编号、现状文字、操作按钮（上传/查看/去台账/缺失名单/打印/标不适用）。
 * 顶部另附《四川省用人单位职业卫生管理自评表》19 项 → 六类档案盒的分配总览（补充层）。
 */
use App\Helpers\ArchiveCatalog;
use App\Helpers\ArchiveChecklist;
use App\Helpers\WorkerArchive;

$badge = fn (string $state) => WorkerArchive::badge($state);
$stats = $checklist['stats'];
$items = $checklist['items'];
$retentionOn = trim((string)$retention) !== '';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <?php foreach (['done' => '已归档', 'partial' => '部分', 'todo' => '待归档', 'na' => '不适用'] as $st => $lbl): ?>
            <span class="badge <?= $badge($st)[1] ?> fs-5"><?= $lbl ?> <?= (int)$stats[$st] ?> 项</span>
        <?php endforeach; ?>
        <span class="badge bg-azure-lt fs-5">按需 <?= (int)$stats['event'] ?> 项</span>
        <a class="ms-1" href="<?= url('archive/clues') ?>" title="体检禁忌证/疑似/复查未归档材料的人员名单"><span class="badge bg-orange-lt fs-5"><i class="ti ti-user-search me-1"></i>事件线索名单</span></a>
        <a class="ms-1" href="<?= url('archive/expiring') ?>" title="已过期/即将到期的原件名单"><span class="badge bg-yellow-lt fs-5"><i class="ti ti-calendar-time me-1"></i>到期资料名单</span></a>
        <?php if ((int)$stats['pending'] > 0): ?><span class="badge bg-secondary-lt fs-5">待台账 <?= (int)$stats['pending'] ?> 项</span><?php endif; ?>
        <a class="ms-2" href="<?= url('archive/checklist') ?>#gap-workers">有缺口的劳动者 <?= (int)$gapCount ?> 人</a>
    </div>
    <div class="d-flex gap-2">
        <a class="btn" href="<?= url('prints/checklist') ?>" target="_blank"><i class="ti ti-printer me-1"></i>打印清单（A4 横向）</a>
    </div>
</div>

<?php
// 自评表 19 项 → 六类档案盒分配总览（补充层；主线为各档案盒的官方目录）
$saRows = ArchiveChecklist::selfAssessmentMap();
$saByGroup = [];
foreach ($saRows as $sa) { $saByGroup[$sa[3]][] = $sa; }
$saGroupNames = ArchiveChecklist::GROUPS;
?>
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">自评表 19 项 → 六类档案盒分配</h3>
        <div class="card-actions text-secondary">补充对照：19 项按类别归入六个档案盒，★为关键项</div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table">
            <thead><tr><th style="width:56px;">项号</th><th>自评项目</th><th style="width:150px;">归入档案盒</th><th>印证材料 / 对应清单项</th></tr></thead>
            <tbody>
            <?php foreach ($saRows as $sa): [$no, $title, $key, $group, $mat, $codes] = $sa; ?>
                <tr>
                    <td class="text-center"><?= e($no) ?><?= $key ? ' <span class="text-red">★</span>' : '' ?></td>
                    <td><?= e($title) ?></td>
                    <td class="small"><?= $group === 'all' ? '六类（总纲）' : e($saGroupNames[$group] ?? $group) ?></td>
                    <td class="text-secondary small"><?= e($mat) ?>
                        <?php if ($codes): ?> · <span class="font-monospace"><?= e(implode(' ', $codes)) ?></span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach (ArchiveChecklist::GROUPS as $gk => $gTitle): ?>
    <?php $groupItems = array_filter($items, fn ($it) => $it['group'] === $gk); if (!$groupItems) continue; ?>
<div class="card mb-3">
    <div class="card-header"><h3 class="card-title"><?= e($gTitle) ?></h3></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <th style="width: 64px;">编号</th>
                    <th>资料名称</th>
                    <th style="width: 60px;">自评项</th>
                    <th style="width: 150px;">现状</th>
                    <th style="width: 300px;">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($groupItems as $it): [$lbl, $cls] = $badge($it['state']); ?>
                <tr>
                    <td class="font-monospace"><?= e($it['code']) ?></td>
                    <td>
                        <span class="badge <?= $cls ?> me-1"><?= e($lbl) ?></span><?= e($it['name']) ?>
                        <?php if ($it['state'] === 'na'): ?>
                            <div class="text-secondary small">不适用原因：<?= e($it['na_reason']) ?>
                                <form class="d-inline" method="post" action="<?= url('archive/unmark') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="scope" value="unit">
                                    <input type="hidden" name="item_code" value="<?= e($it['code']) ?>">
                                    <button class="btn btn-sm btn-link p-0 ms-1" type="submit">取消标记</button>
                                </form>
                            </div>
                        <?php endif; ?>
                        <?php if (in_array($it['code'], ['C27', 'C28'], true) && $retentionOn): ?>
                            <div class="text-secondary small">按配置保管 <?= e($retention) ?> 年</div>
                        <?php endif; ?>
                    </td>
                    <td class="text-secondary"><?= e($it['self']) ?></td>
                    <td>
                        <?php foreach ($it['parts'] as $p): ?>
                            <div class="small <?= !empty($p['pending']) ? 'text-secondary' : (!empty($p['optional']) ? 'text-secondary' : '') ?>">
                                <?= e($p['label']) ?>：<?= e($p['display']) ?><?= !empty($p['hint']) ? '（' . e($p['hint']) . '）' : '' ?>
                                <?php if (!empty($p['optional'])): ?><span class="text-secondary">·参考</span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <td class="text-nowrap">
                        <?php
                        // 清单项 → 官方目录项资料管理页（若存在映射）
                        $filing = ArchiveCatalog::filingByCheck($it['code']);
                        if ($filing !== null): ?>
                        <a class="btn btn-sm py-0 me-1" href="<?= e(ArchiveCatalog::filingTargetUrl($filing['group'], $filing['seq'])) ?>"
                           title="进入官方目录项资料管理"><i class="ti ti-folder"></i> 资料管理</a>
                        <?php endif; ?>
                        <?php foreach ($it['parts'] as $p): if (!empty($p['pending'])) continue; $r = $p['raw']; ?>
                            <?php switch ($r['kind']):
                                case 'doc':
                                    if (empty($r['per_record']) && empty($r['link'])): ?>
                                        <button class="btn btn-sm btn-primary py-0 me-1" data-bs-toggle="modal" data-bs-target="#unit-upload-modal"
                                                data-doc-type="<?= e($r['types'][0]) ?>" data-label="<?= e($r['label'] ?? $it['name']) ?>"><i class="ti ti-upload"></i> 上传</button>
                                    <?php elseif (empty($r['per_record'])): ?>
                                        <a class="btn btn-sm py-0 me-1" href="<?= url('documents/index', ['doc_type' => $r['types'][0]]) ?>"><i class="ti ti-folder"></i> 查看</a>
                                    <?php else: ?>
                                        <a class="btn btn-sm py-0 me-1" href="<?= url('archive/missing', ['item' => $it['code']]) ?>"><i class="ti ti-list-check"></i> 缺失名单</a>
                                    <?php endif; ?>
                                    <?php break;
                                case 'ledger': ?>
                                    <a class="btn btn-sm py-0 me-1" href="<?= url('ledgers/index', ['key' => $r['key']]) ?>"><i class="ti ti-notebook"></i> 去台账</a>
                                    <?php break;
                                case 'workers':
                                case 'worker_years': ?>
                                    <a class="btn btn-sm py-0 me-1" href="<?= url('archive/missing', ['item' => $it['code']]) ?>"><i class="ti ti-users"></i> 缺失名单</a>
                                    <?php break;
                                case 'system': ?>
                                    <a class="btn btn-sm py-0 me-1" href="<?= url('prints/sheet', ['t' => $r['print']]) ?>" target="_blank"><i class="ti ti-printer"></i> 打印</a>
                                    <?php break;
                            endswitch; ?>
                        <?php endforeach; ?>
                        <?php if ($it['state'] !== 'na' && $it['na_allowed']): ?>
                            <button class="btn btn-sm py-0 text-secondary" data-bs-toggle="modal" data-bs-target="#unit-na-modal"
                                    data-item-code="<?= e($it['code']) ?>" data-label="<?= e($it['name']) ?>"
                                    data-reasons="<?= e(json_encode($it['na_allowed'], JSON_UNESCAPED_UNICODE)) ?>" title="标合理缺项（不适用）"><i class="ti ti-ban"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<a id="gap-workers"></a>
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-user-search me-1"></i>有缺口的劳动者（前 10 人）</h3>
        <div class="card-actions text-secondary">共 <?= (int)$gapCount ?> 人</div>
    </div>
    <?php if (!$gapWorkers): ?>
        <div class="card-body text-secondary">所有劳动者的适用资料均已归档或标记不适用。</div>
    <?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($gapWorkers as $gid => $g): ?>
        <div class="list-group-item d-flex align-items-center gap-2">
            <a class="text-body fw-bold" href="<?= url('workers/files', ['id' => $gid]) ?>"><?= e($g['name']) ?></a>
            <span class="font-monospace text-secondary"><?= e($g['archive_no']) ?></span>
            <span class="badge bg-red-lt ms-auto"><?= (int)$g['gaps'] ?> 项缺口</span>
            <a class="btn btn-sm btn-primary" href="<?= url('workers/files', ['id' => $gid]) ?>">去个人页补录</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php /* 单位级快速上传模态框 */ ?>
<div class="modal modal-blur fade" id="unit-upload-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= url('documents/quick') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="doc_type" value="">
                <input type="hidden" name="link_type" value="">
                <input type="hidden" name="link_id" value="">
                <input type="hidden" name="period_year" value="<?= (int)date('Y') ?>">
                <input type="hidden" name="back" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">上传：<span id="uum-label">资料</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">文件（可多选）</label>
                        <input type="file" class="form-control" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                        <div class="form-hint">支持 PDF / 图片 / Word / Excel，单个不超过 20MB。</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">标题（留空自动生成）</label>
                        <input type="text" class="form-control" name="title" placeholder="留空按资料类型生成">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">文档日期</label>
                            <input type="date" class="form-control" name="doc_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">所属年度</label>
                            <input type="number" class="form-control" name="period_year_view" value="<?= (int)date('Y') ?>" disabled>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-upload me-1"></i>上传</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php /* 单位级标不适用模态框 */ ?>
<div class="modal modal-blur fade" id="unit-na-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= url('archive/mark') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="scope" value="unit">
                <input type="hidden" name="back" value="1">
                <input type="hidden" name="item_code" value="">
                <div class="modal-header">
                    <h5 class="modal-title">标记“不适用”：<span id="una-label"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label required">原因（必填）</label>
                    <div class="mb-2 d-flex flex-wrap gap-1" id="una-quick-reasons"></div>
                    <textarea class="form-control" name="reason" rows="2" required placeholder="如：近三年无建设项目"></textarea>
                    <div class="form-hint mt-1">仅自评表规定有“合理缺项”的项可标记（第5、8、18项）；标记后该项不再计入待归档。</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-secondary"><i class="ti ti-ban me-1"></i>标记不适用</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var um = document.getElementById('unit-upload-modal');
    if (um) {
        um.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) return;
            var f = um.querySelector('form');
            f.querySelector('[name="doc_type"]').value = btn.getAttribute('data-doc-type') || '';
            document.getElementById('uum-label').textContent = btn.getAttribute('data-label') || '资料';
        });
    }
    var nm = document.getElementById('unit-na-modal');
    if (nm) {
        nm.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) return;
            nm.querySelector('[name="item_code"]').value = btn.getAttribute('data-item-code') || '';
            document.getElementById('una-label').textContent = btn.getAttribute('data-label') || '';
            var box = document.getElementById('una-quick-reasons'), rs = [];
            try { rs = JSON.parse(btn.getAttribute('data-reasons') || '[]'); } catch (x) {}
            box.innerHTML = '';
            rs.forEach(function (t) {
                var b = document.createElement('button');
                b.type = 'button'; b.className = 'btn btn-sm'; b.setAttribute('data-reason', t); b.textContent = t;
                box.appendChild(b);
            });
            nm.querySelector('[name="reason"]').value = rs.length === 1 ? rs[0] : '';
        });
        nm.addEventListener('click', function (e) {
            var b = e.target.closest('#una-quick-reasons [data-reason]');
            if (!b) return;
            nm.querySelector('[name="reason"]').value = b.getAttribute('data-reason');
        });
    }
})();
</script>
