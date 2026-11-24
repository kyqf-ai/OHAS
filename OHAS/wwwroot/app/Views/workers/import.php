<div class="card">
    <div class="card-header">
        <h3 class="card-title">CSV 批量导入劳动者基本信息</h3>
        <div class="card-actions">
            <a class="btn btn-sm" href="<?= url('workers/index') ?>"><i class="ti ti-arrow-left me-1"></i>返回列表</a>
        </div>
    </div>
    <div class="card-body">
        <ul class="steps steps-counter mb-4">
            <li class="step-item">下载导入模板，按列填写基本信息（职业史请建档后逐人补充）</li>
            <li class="step-item">上传 CSV 文件（支持 UTF-8 与 GBK 编码，单次 ≤1000 行）</li>
            <li class="step-item">预览确认：系统按身份证号判断「新增 / 更新」，并列出错误行</li>
            <li class="step-item">确认导入，查看逐行结果报告</li>
        </ul>
        <form method="post" action="<?= url('workers/importUpload') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3 align-items-end">
                <div class="col-md-6 col-lg-5">
                    <label class="form-label required">CSV 文件</label>
                    <input type="file" class="form-control" name="csv" accept=".csv,text/csv" required>
                    <div class="form-hint">仅支持 .csv 文件，≤4MB；Excel 请「另存为 CSV (逗号分隔)」</div>
                </div>
                <div class="col-auto d-flex gap-2">
                    <a class="btn" href="<?= url('workers/importTemplate') ?>"><i class="ti ti-download me-1"></i>下载导入模板</a>
                    <button class="btn btn-primary" type="submit"><i class="ti ti-upload me-1"></i>上传并预览</button>
                </div>
            </div>
        </form>
    </div>
    <div class="card-footer pt-0">
        <div class="alert alert-info mb-0" role="alert">
            <i class="ti ti-info-circle alert-icon"></i>
            <div>
                <b>导入规则</b>
                <ul class="mb-0 mt-1">
                    <li>以<b>身份证号</b>为唯一性校验键：库中已存在则<b>更新</b>其基本信息（空单元格不覆盖），不存在则<b>新增建档</b>并自动生成档案号。</li>
                    <li>身份证号为空或校验未通过的行将列为错误行，不会写入。</li>
                    <li>性别、出生日期留空时可由 18 位身份证号自动推导；在职状态留空默认「在职」。</li>
                    <li>日期支持 2026-01-02 / 2026/1/2 / 2026.1.2 三种写法，将统一规范为 YYYY-MM-DD。</li>
                    <li>模板中的示例行（张三）请在导入前删除，或在预览中确认后跳过。</li>
                </ul>
            </div>
        </div>
    </div>
</div>
