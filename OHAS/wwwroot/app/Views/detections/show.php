<?php
/**
 * 检测报告详情：报告信息头 + 检测明细表（行内编辑/删除）+ 明细登记子表单。
 * 明细保存走 AJAX（app.js bindSubform / itemEdit / bindRowDelete）。
 */
$r = $report;
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= e($r['report_no']) ?><span class="badge bg-blue-lt ms-2"><?= e($r['category']) ?></span></h3>
        <div class="card-subtitle">
            <?= e($r['agency']) ?>
            <span class="ms-2">检测日期 <span class="font-monospace"><?= d($r['test_date']) ?></span></span>
            <span class="ms-2">报告日期 <span class="font-monospace"><?= d($r['report_date']) ?></span></span>
        </div>
        <div class="card-actions">
            <a class="btn btn-sm" href="<?= url('detections/edit', ['id' => $r['id']]) ?>"><i class="ti ti-pencil me-1"></i>编辑报告</a>
            <a class="btn btn-sm" href="<?= url('detections/index') ?>"><i class="ti ti-arrow-left me-1"></i>返回列表</a>
        </div>
    </div>
    <?php if ((string)$r['conclusion'] !== ''): ?>
    <div class="card-body py-2">
        <span class="text-secondary">结论摘要：</span><?= e($r['conclusion']) ?>
    </div>
    <?php endif; ?>
</div>

<?php $linkType = 'detection_report'; $linkId = (int)$r['id']; include OHAS_ROOT . '/app/Views/partials/doc_list.php'; ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            检测明细
            <span class="badge bg-blue-lt ms-2"><?= count($items) ?> 条</span>
            <?php if ((int)$r['exceed_count'] > 0): ?><span class="badge bg-red-lt ms-1">超标 <?= (int)$r['exceed_count'] ?> 点</span><?php endif; ?>
        </h3>
        <div class="card-subtitle">每行一个检测点；关联岗位的明细将按检测日期自动归集到接害人员信息卡（表6-2）</div>
        <?php
        $rcTotal = count($rectify ?? []);
        $rcNew = 0;
        foreach (($rectify ?? []) as $rc) { if (!$rc['exists']) { $rcNew++; } }
        if ($rcTotal > 0):
        ?>
        <div class="card-actions">
            <?php if ($rcNew > 0): ?>
            <form method="post" action="<?= url('detections/genRectify') ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="ti ti-tool me-1"></i>生成整改记录草稿（<?= $rcNew ?> 条）
                </button>
            </form>
            <?php else: ?>
            <a class="btn btn-sm" href="<?= url('ledgers/index', ['key' => 'rectifications']) ?>">
                <i class="ti ti-circle-check me-1"></i>超标点已有整改记录（<?= $rcTotal ?> 条）
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php if (!$items): ?>
        <div class="empty empty-sm">
            <div class="empty-icon"><i class="ti ti-point-dots"></i></div>
            <p class="empty-title">尚未录入明细</p>
            <p class="empty-subtitle text-secondary">在下方表单按检测报告逐条录入检测点结果与限值判定。</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr>
                    <th>作业场所</th><th>岗位</th><th>危害因素</th><th>检测点</th>
                    <th>检测结果</th><th>接触限值</th><th class="w-1">判定</th><th>防护措施</th><th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): [$vl, $vc] = \App\Models\DetectionItem::verdictBadge($it['verdict']); ?>
                <tr>
                    <td><?= e($it['workplace_name'] ?: '—') ?></td>
                    <td><?php if ($it['post_name']): ?><?= e($it['post_name']) ?><?php else: ?><span class="text-secondary">未指定</span><?php endif; ?></td>
                    <td><?= e($it['hazard_name'] ?: '—') ?></td>
                    <td class="text-secondary"><?= e($it['test_point'] ?: '—') ?></td>
                    <td class="font-monospace"><?= e($it['result'] ?: '—') ?><?= $it['unit'] !== '' ? ' <span class="text-secondary">' . e($it['unit']) . '</span>' : '' ?>
                        <?php if (($it['sample_type'] ?? '') !== ''): ?><div class="small text-secondary"><?= e($it['sample_type']) ?></div><?php endif; ?></td>
                    <td class="text-secondary small"><?= e($it['limit_value'] ?: '—') ?></td>
                    <td><span class="badge <?= $vc ?>"><?= e($vl) ?></span></td>
                    <td class="text-secondary small"><?= e($it['protection'] ?: '—') ?></td>
                    <td class="text-nowrap text-secondary">
                        <a class="item-edit text-body me-2" href="<?= url('detections/itemRow', ['id' => $it['id']]) ?>"><i class="ti ti-pencil"></i></a>
                        <a class="item-del text-danger" href="#" data-id="<?= (int)$it['id'] ?>"><i class="ti ti-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <form class="subform m-3" id="item-form" data-endpoint="<?= url('detections/itemSave') ?>">
        <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
        <input type="hidden" name="row_id" value="">
        <div class="subform-title">添加检测明细
            <button type="button" class="btn btn-sm btn-outline-secondary subform-cancel" hidden><i class="ti ti-x me-1"></i>取消编辑</button>
        </div>
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="mb-3">
                    <label class="form-label">作业场所</label>
                    <select class="form-select" name="workplace_id">
                        <option value="">— 可选 —</option>
                        <?php foreach ($workplaces as $wp): ?>
                            <option value="<?= (int)$wp['id'] ?>"><?= e($wp['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="mb-3">
                    <label class="form-label">岗位</label>
                    <select class="form-select" name="post_id">
                        <option value="">— 可选（推荐选择） —</option>
                        <?php foreach ($posts as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" data-workplace="<?= (int)$p['workplace_id'] ?>"><?= e($p['workplace_name']) ?> · <?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">选择岗位后将自动带出作业场所；按人归集表6-2 以岗位为主口径</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="mb-3">
                    <label class="form-label required">危害因素</label>
                    <select class="form-select" name="hazard_id" required data-hazard-picker>
                        <option value="">— 请选择 —</option>
                        <?php foreach ($hazards as $cat => $group): ?>
                            <optgroup label="<?= e($cat) ?>">
                                <?php foreach ($group as $h): ?>
                                    <option value="<?= (int)$h['id'] ?>" data-limit="<?= e($h['limit_note']) ?>" data-disease="<?= e($h['possible_disease']) ?>"<?= (string)$h['cas_no'] !== '' ? ' data-cas="' . e($h['cas_no']) . '"' : '' ?><?= $h['catalog_no'] ? ' data-no="' . (int)$h['catalog_no'] . '"' : '' ?>>
                                        <?= e($h['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">选择后将带出字典中的接触限值备注，可手工修订；支持关键字检索</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="mb-3">
                    <label class="form-label">检测点</label>
                    <input type="text" class="form-control" name="test_point" placeholder="如：打磨工位 1# 砂轮机东侧">
                </div>
            </div>
            <div class="w-100"></div>
            <div class="col-6 col-sm-4 col-xl-3">
                <div class="mb-3">
                    <label class="form-label">检测结果</label>
                    <input type="text" class="form-control" name="result" placeholder="如：0.68">
                </div>
            </div>
            <div class="col-6 col-sm-4 col-xl-2">
                <div class="mb-3">
                    <label class="form-label">计量单位</label>
                    <input type="text" class="form-control" name="unit" placeholder="mg/m³ / dB(A)">
                </div>
            </div>
            <div class="col-6 col-sm-4 col-xl-2">
                <div class="mb-3">
                    <label class="form-label">采样类型</label>
                    <select class="form-select" name="sample_type">
                        <option value="">未指定</option>
                        <option value="TWA">TWA 时间加权平均</option>
                        <option value="STEL">STEL 短时接触</option>
                        <option value="MAC">MAC 最高容许浓度</option>
                        <option value="峰">峰 / 峰值</option>
                    </select>
                    <div class="form-hint">限值备注含多类型时须声明口径，避免表6-2 错配</div>
                </div>
            </div>
            <div class="col-12 col-sm-4 col-xl-4">
                <div class="mb-3">
                    <label class="form-label">职业接触限值</label>
                    <input type="text" class="form-control" name="limit_value" placeholder="如：PC-TWA 1 mg/m³（GBZ 2.1-2019）">
                </div>
            </div>
            <div class="col-6 col-sm-4 col-xl-3">
                <div class="mb-3">
                    <label class="form-label">判定结论</label>
                    <select class="form-select" name="verdict">
                        <option value="">未判定</option>
                        <option value="合格">合格</option>
                        <option value="超标">超标</option>
                    </select>
                </div>
            </div>
            <div class="w-100"></div>
            <div class="col-12 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label">防护措施</label>
                    <input type="text" class="form-control" name="protection" placeholder="如：布袋除尘器 + KN95 防尘口罩">
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4">
                <div class="mb-3">
                    <label class="form-label">备注</label>
                    <input type="text" class="form-control" name="remark">
                </div>
            </div>
        </div>
        <div class="subform-msg"></div>
        <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>保存检测明细</button>
    </form>
</div>
