-- =====================================================================
-- 职业健康档案系统（OHAS）SQLite 数据库结构
-- 对应《职业健康档案系统设计规划书》第五章 表9：共 30 张数据表
-- 命名约定：小写蛇形；主键 id 自增；日期 YYYY-MM-DD；审计字段 created_at/updated_at
-- =====================================================================

PRAGMA foreign_keys = ON;

-- 1 用人单位信息（单行记录，申报表基本信息项数据源，对应表2-2）
CREATE TABLE IF NOT EXISTS company (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT NOT NULL DEFAULT '',            -- 单位名称
    credit_code     TEXT DEFAULT '',                    -- 统一社会信用代码
    reg_address     TEXT DEFAULT '',                    -- 注册地址
    work_address    TEXT DEFAULT '',                    -- 工作场所地址
    legal_person    TEXT DEFAULT '',                    -- 法定代表人
    hygienist_name  TEXT DEFAULT '',                    -- 职业卫生管理负责人
    hygienist_phone TEXT DEFAULT '',                    -- 负责人联系方式
    industry        TEXT DEFAULT '',                    -- 行业分类
    scale           TEXT DEFAULT '',                    -- 企业规模
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);

-- 2 管理员账号（单管理员，密码 password_hash 存储）
CREATE TABLE IF NOT EXISTS users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    username        TEXT NOT NULL UNIQUE,
    password_hash   TEXT NOT NULL,
    real_name       TEXT DEFAULT '',
    role            TEXT NOT NULL DEFAULT 'admin',
    status          TEXT NOT NULL DEFAULT '启用',        -- 启用 / 停用（停用账号保留，日志仍可追溯）
    failed_attempts INTEGER NOT NULL DEFAULT 0,         -- 连续登录失败次数
    locked_until    TEXT DEFAULT NULL,                  -- 锁定截止时间（5次失败锁5分钟）
    last_login_at   TEXT DEFAULT NULL,
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);

-- 3 操作日志（仅追加，不可编辑删除）
CREATE TABLE IF NOT EXISTS logs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER DEFAULT NULL,
    username    TEXT DEFAULT '',
    action      TEXT NOT NULL,                          -- login/logout/create/update/delete/import/upload
    object_type TEXT DEFAULT '',                        -- 对象类型：worker/hazard/...
    object_id   INTEGER DEFAULT NULL,
    summary     TEXT DEFAULT '',                        -- 变更摘要
    ip          TEXT DEFAULT '',
    created_at  TEXT NOT NULL
);

-- 4 作业场所（表2-2）
CREATE TABLE IF NOT EXISTS workplaces (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,                          -- 场所名称
    location    TEXT DEFAULT '',                        -- 位置说明
    status      TEXT NOT NULL DEFAULT '启用' CHECK (status IN ('启用','停用')),
    remark      TEXT DEFAULT '',
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_workplaces_name ON workplaces(name);

-- 5 岗位（表4-2，隶属作业场所）
CREATE TABLE IF NOT EXISTS posts (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    workplace_id  INTEGER NOT NULL REFERENCES workplaces(id),
    name          TEXT NOT NULL,                        -- 岗位名称
    status        TEXT NOT NULL DEFAULT '启用' CHECK (status IN ('启用','停用')),
    remark        TEXT DEFAULT '',
    created_at    TEXT NOT NULL,
    updated_at    TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_posts_workplace ON posts(workplace_id);
-- v0.4.14：同场所岗位名唯一约束（原仅控制器先查后插）
CREATE UNIQUE INDEX IF NOT EXISTS uq_posts_workplace_name ON posts(workplace_id, name);

-- 6 危害因素字典（表2-2）
CREATE TABLE IF NOT EXISTS hazards (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT NOT NULL,                      -- 危害因素名称
    category        TEXT NOT NULL,                      -- 类别：粉尘/化学因素/物理因素/放射性因素/生物因素/其他因素
    catalog_no      INTEGER DEFAULT NULL,               -- 《职业病危害因素分类目录》类别内序号
    cas_no          TEXT DEFAULT '',                    -- CAS 号（目录所列）
    restriction     TEXT DEFAULT '',                    -- 目录备注：适用范围/限用条件
    possible_disease TEXT DEFAULT '',                   -- 可能导致的职业病
    limit_note      TEXT DEFAULT '',                    -- 接触限值备注
    catalog_source  TEXT DEFAULT '',                    -- 数据来源：目录版本 / 自定义
    status          TEXT NOT NULL DEFAULT '启用' CHECK (status IN ('启用','停用')),
    remark          TEXT DEFAULT '',
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_hazards_name ON hazards(name, category);

-- 7 岗位危害配置（表4-2）
CREATE TABLE IF NOT EXISTS post_hazards (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id               INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    hazard_id             INTEGER NOT NULL REFERENCES hazards(id),
    exposure_type         TEXT DEFAULT '',              -- 接触方式：定点作业/巡检作业
    source                TEXT DEFAULT '',              -- 危害来源（设备、工序等）
    engineering_controls  TEXT DEFAULT '',              -- 工程防护设施
    ppe                   TEXT DEFAULT '',              -- 个体防护用品
    created_at            TEXT NOT NULL,
    updated_at            TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_post_hazards_post ON post_hazards(post_id);
-- v0.4.14：岗位危害配置唯一约束（并发/重复提交不再产生重复行，表4-2 不再重复计数）
CREATE UNIQUE INDEX IF NOT EXISTS uq_post_hazards_post_hazard ON post_hazards(post_id, hazard_id);
CREATE INDEX IF NOT EXISTS idx_post_hazards_hazard ON post_hazards(hazard_id);

-- 8 劳动者信息卡（表6-1，核心实体）
CREATE TABLE IF NOT EXISTS workers (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    archive_no      TEXT UNIQUE,                        -- 档案号：年份+流水号自动生成，可手工修订
    name            TEXT NOT NULL,                      -- 姓名
    gender          TEXT DEFAULT '',                    -- 性别：男/女
    birth_date      TEXT DEFAULT '',                    -- 出生日期
    native_place    TEXT DEFAULT '',                    -- 籍贯
    marital_status  TEXT DEFAULT '',                    -- 婚姻状况
    education       TEXT DEFAULT '',                    -- 文化程度
    hobby           TEXT DEFAULT '',                    -- 嗜好
    work_start_date TEXT DEFAULT '',                    -- 参加工作时间
    id_card         TEXT DEFAULT '',                    -- 身份证号（唯一性校验键，导入去重依据）
    photo           TEXT DEFAULT '',                    -- 照片存储名
    phone           TEXT DEFAULT '',                    -- 联系电话（系统扩充字段）
    status          TEXT NOT NULL DEFAULT '在职' CHECK (status IN ('在职','离岗','退休','离职')),
    entry_date      TEXT DEFAULT '',                    -- 入职本单位时间
    remark          TEXT DEFAULT '',
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_workers_name ON workers(name);
CREATE INDEX IF NOT EXISTS idx_workers_id_card ON workers(id_card);
CREATE INDEX IF NOT EXISTS idx_workers_status ON workers(status);

-- 9 职业史（表6-1职业史区；post_id 空 = 外单位经历；含危害与防护快照）
CREATE TABLE IF NOT EXISTS worker_posts (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    worker_id           INTEGER NOT NULL REFERENCES workers(id) ON DELETE CASCADE,
    start_date          TEXT NOT NULL,                  -- 开始日期
    end_date            TEXT DEFAULT NULL,              -- 结束日期，空值表示至今（当前岗位）
    company_name        TEXT NOT NULL DEFAULT '',       -- 工作单位名称
    post_id             INTEGER DEFAULT NULL REFERENCES posts(id),
    post_name           TEXT DEFAULT '',                -- 工种/岗位名称快照
    hazards_snapshot    TEXT DEFAULT '',                -- 接触职业病危害因素名称清单快照
    protection_snapshot TEXT DEFAULT '',                -- 防护措施快照
    remark              TEXT DEFAULT '',
    created_at          TEXT NOT NULL,
    updated_at          TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_worker_posts_worker ON worker_posts(worker_id, end_date);
CREATE INDEX IF NOT EXISTS idx_worker_posts_post ON worker_posts(post_id);

-- 10 既往病史（表6-1既往病史区）
CREATE TABLE IF NOT EXISTS worker_illnesses (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    worker_id   INTEGER NOT NULL REFERENCES workers(id) ON DELETE CASCADE,
    disease     TEXT NOT NULL,                          -- 疾病名称
    diag_date   TEXT DEFAULT '',                        -- 诊断时间
    hospital    TEXT DEFAULT '',                        -- 诊断医院
    outcome     TEXT DEFAULT '',                        -- 治疗结果
    remark      TEXT DEFAULT '',
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_worker_illnesses_worker ON worker_illnesses(worker_id);

-- 11 职业病诊断（表6-1、表5-3、表5-4；疑似标记区分确诊与疑似）
CREATE TABLE IF NOT EXISTS diseases (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    worker_id   INTEGER NOT NULL REFERENCES workers(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,                          -- 职业病名称
    is_suspected INTEGER NOT NULL DEFAULT 0,            -- 1=疑似职业病，0=确诊
    diag_date   TEXT DEFAULT '',                        -- 诊断时间
    hospital    TEXT DEFAULT '',                        -- 诊断医院
    level       TEXT DEFAULT '',                        -- 诊断级别（如：轻度/中度/重度、壹期等）
    handling    TEXT DEFAULT '',                        -- 处理情况
    remark      TEXT DEFAULT '',
    exam_record_id INTEGER DEFAULT NULL,                -- 来源体检记录（由体检记录页「生成登记」写入；手工登记为空）
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_diseases_worker ON diseases(worker_id);
CREATE UNIQUE INDEX IF NOT EXISTS uq_diseases_exam_record ON diseases(exam_record_id) WHERE exam_record_id IS NOT NULL;

-- 12 检测报告（监测检测档案；阶段二启用）
CREATE TABLE IF NOT EXISTS detection_reports (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    report_no   TEXT NOT NULL,                          -- 报告编号
    agency      TEXT NOT NULL,                          -- 检测机构
    category    TEXT NOT NULL DEFAULT '定期检测',        -- 定期检测/日常监测/现状评价/控制效果评价
    test_date   TEXT DEFAULT '',                        -- 检测日期
    report_date TEXT DEFAULT '',                        -- 报告日期
    conclusion  TEXT DEFAULT '',                        -- 结论摘要
    remark      TEXT DEFAULT '',
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_detection_reports_no ON detection_reports(report_no);
-- v0.4.14：报告编号唯一约束（原为普通索引，先查后插可落重复）

-- 13 检测明细（表6-2 归集数据源；阶段二启用）
CREATE TABLE IF NOT EXISTS detection_items (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id     INTEGER NOT NULL REFERENCES detection_reports(id) ON DELETE CASCADE,
    workplace_id  INTEGER DEFAULT NULL REFERENCES workplaces(id),
    post_id       INTEGER DEFAULT NULL REFERENCES posts(id),
    hazard_id     INTEGER DEFAULT NULL REFERENCES hazards(id),
    test_point    TEXT DEFAULT '',                      -- 检测点名称
    result        TEXT DEFAULT '',                      -- 检测结果
    unit          TEXT DEFAULT '',                      -- 计量单位
    limit_value   TEXT DEFAULT '',                      -- 职业接触限值
    verdict       TEXT DEFAULT '',                      -- 判定结论：合格/超标
    protection    TEXT DEFAULT '',                      -- 防护措施
    remark        TEXT DEFAULT '',
    created_at    TEXT NOT NULL,
    updated_at    TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_detection_items_report ON detection_items(report_id);
CREATE INDEX IF NOT EXISTS idx_detection_items_post ON detection_items(post_id, hazard_id);
CREATE INDEX IF NOT EXISTS idx_detection_items_hazard ON detection_items(hazard_id);

-- 14 设备材料化学品（表4-1）
CREATE TABLE IF NOT EXISTS materials (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    type        TEXT NOT NULL DEFAULT '化学品',          -- 设备/材料/化学品
    name        TEXT NOT NULL,
    workplace_id INTEGER DEFAULT NULL REFERENCES workplaces(id),   -- 使用场所（v0.3.5 起关联）
    post_id     INTEGER DEFAULT NULL REFERENCES posts(id),         -- 使用岗位（v0.3.5 起关联）
    usage_place TEXT DEFAULT '',                        -- 使用车间岗位（补充说明；下拉无对应时手填）
    supplier    TEXT DEFAULT '',                        -- 生产供货单位
    remark      TEXT DEFAULT '',
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL
);
-- 14a 设备材料化学品 × 危害因素（v0.4.11：同一设备/材料/化学品可能产生多种危害因素）
CREATE TABLE IF NOT EXISTS material_hazards (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    material_id INTEGER NOT NULL REFERENCES materials(id) ON DELETE CASCADE,
    hazard_id   INTEGER NOT NULL REFERENCES hazards(id),
    created_at  TEXT NOT NULL,
    UNIQUE (material_id, hazard_id)
);

-- 表2-6 防护用品发放 × 防护的危害因素（多对多，v0.4.13）
CREATE TABLE IF NOT EXISTS ppe_issue_hazards (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    ppe_issue_id INTEGER NOT NULL REFERENCES ppe_issues(id) ON DELETE CASCADE,
    hazard_id    INTEGER NOT NULL REFERENCES hazards(id),
    created_at   TEXT NOT NULL,
    UNIQUE (ppe_issue_id, hazard_id)
);

-- 表2-7 警示标识 × 对应的危害因素（多对多，v0.4.13）
CREATE TABLE IF NOT EXISTS warning_sign_hazards (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    warning_sign_id INTEGER NOT NULL REFERENCES warning_signs(id) ON DELETE CASCADE,
    hazard_id       INTEGER NOT NULL REFERENCES hazards(id),
    created_at      TEXT NOT NULL,
    UNIQUE (warning_sign_id, hazard_id)
);

-- 15 体检批次（表5-1；阶段二启用）
CREATE TABLE IF NOT EXISTS exam_batches (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    exam_date     TEXT DEFAULT '',                      -- 检查日期
    agency        TEXT NOT NULL,                        -- 检查机构
    exam_type     TEXT NOT NULL DEFAULT '在岗期间',      -- 检查种类
    planned_count INTEGER NOT NULL DEFAULT 0,           -- 应检人数（实检人数由 exam_records 实时统计，不落库）
    remark        TEXT DEFAULT '',
    created_at    TEXT NOT NULL,
    updated_at    TEXT NOT NULL
);

-- 16 检查记录（表6-3；阶段二启用）
CREATE TABLE IF NOT EXISTS exam_records (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    worker_id         INTEGER NOT NULL REFERENCES workers(id) ON DELETE CASCADE,
    batch_id          INTEGER DEFAULT NULL REFERENCES exam_batches(id) ON DELETE SET NULL,
    exam_date         TEXT NOT NULL DEFAULT '',              -- 检查日期（必填：批次与记录均要求填写）
    exam_type         TEXT NOT NULL DEFAULT '在岗期间',  -- 检查种类
    conclusion        TEXT NOT NULL DEFAULT '',                -- 检查结论（GBZ 188-2025；空=待出结论，历史口径「未见异常」可并入「目前未见异常」）
    agency            TEXT DEFAULT '',                  -- 检查机构
    post_name         TEXT DEFAULT '',                  -- 受检时岗位快照
    hazards_snapshot  TEXT DEFAULT '',                  -- 受检时接害因素快照
    handling          TEXT DEFAULT '',                  -- 人员处理情况
    signed_at         TEXT DEFAULT '',                  -- 本人签字确认时间
    site_handling     TEXT DEFAULT '',                  -- 现场处理情况
    remark            TEXT DEFAULT '',
    created_at        TEXT NOT NULL,
    updated_at        TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_exam_records_worker ON exam_records(worker_id, exam_date);
CREATE INDEX IF NOT EXISTS idx_exam_records_batch ON exam_records(batch_id);
-- 同批次同人唯一（v0.4.9）：双开标签页 / 重放请求也不会产生重复待检记录
CREATE UNIQUE INDEX IF NOT EXISTS uq_exam_records_batch_worker ON exam_records(batch_id, worker_id) WHERE batch_id IS NOT NULL;
-- 体检记录被删除时，职业病登记保留，仅解除来源关联
CREATE TRIGGER IF NOT EXISTS trg_disease_unlink_exam AFTER DELETE ON exam_records
BEGIN
    UPDATE diseases SET exam_record_id = NULL WHERE exam_record_id = OLD.id;
END;

-- 17 培训记录（表3-1）
CREATE TABLE IF NOT EXISTS trainings (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    type          TEXT NOT NULL DEFAULT '入职培训',
    title         TEXT DEFAULT '',                      -- 培训内容
    hours         REAL DEFAULT 0,                       -- 学时
    department    TEXT DEFAULT '',                      -- 参加部门
    teacher       TEXT DEFAULT '',                      -- 授课人
    organizer     TEXT DEFAULT '',                      -- 组织部门
    trained_on    TEXT DEFAULT '',                      -- 实施日期
    remark        TEXT DEFAULT '',
    created_at    TEXT NOT NULL,
    updated_at    TEXT NOT NULL
);

-- 18 参训人员（表3-1）
CREATE TABLE IF NOT EXISTS training_participants (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    training_id INTEGER NOT NULL REFERENCES trainings(id) ON DELETE CASCADE,
    worker_id   INTEGER NOT NULL REFERENCES workers(id) ON DELETE CASCADE,
    score       TEXT DEFAULT '',                        -- 成绩
    attended    TEXT NOT NULL DEFAULT '是',              -- 是否签到
    remark      TEXT DEFAULT '',
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_training_participants_training ON training_participants(training_id);
-- 同场培训同人唯一（v0.4.9）：防重复签到
CREATE UNIQUE INDEX IF NOT EXISTS uq_training_participants ON training_participants(training_id, worker_id);

-- 19 三同时项目（表1-1）
CREATE TABLE IF NOT EXISTS projects (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,                        -- 项目名称
    type          TEXT NOT NULL DEFAULT '新建',          -- 新建/改建/扩建/技改/引进
    investment    TEXT DEFAULT '',                      -- 投资额（万元）
    start_date    TEXT DEFAULT '',
    end_date      TEXT DEFAULT '',
    main_hazards  TEXT DEFAULT '',                      -- 主要危害因素
    pre_review    TEXT DEFAULT '',                      -- 预评价审核情况
    design_review TEXT DEFAULT '',                      -- 设计审查情况
    completion    TEXT DEFAULT '',                      -- 竣工验收情况
    remark        TEXT DEFAULT '',
    created_at    TEXT NOT NULL,
    updated_at    TEXT NOT NULL
);

-- 20 防护设施台账（表2-4）
CREATE TABLE IF NOT EXISTS facilities (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,
    model         TEXT DEFAULT '',                      -- 规格型号
    purpose       TEXT DEFAULT '',                      -- 用途
    workplace_id  INTEGER DEFAULT NULL REFERENCES workplaces(id),
    installer     TEXT DEFAULT '',                      -- 安装单位
    accept_date   TEXT DEFAULT '',                      -- 验收日期
    status        TEXT NOT NULL DEFAULT '在用' CHECK (status IN ('在用','停用','检修','报废')),
    remark        TEXT DEFAULT '',
    created_at    TEXT NOT NULL,
    updated_at    TEXT NOT NULL
);

-- 21 设施维护记录（表2-5）
CREATE TABLE IF NOT EXISTS facility_maintains (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    facility_id INTEGER NOT NULL REFERENCES facilities(id) ON DELETE CASCADE,
    maintain_date TEXT DEFAULT '',
    reason      TEXT DEFAULT '',                        -- 检修原因
    department  TEXT DEFAULT '',
    cost        TEXT DEFAULT '',                        -- 费用
    effect      TEXT DEFAULT '',                        -- 检修效果
    accept_opinion TEXT DEFAULT '',                     -- 验收意见
    remark      TEXT DEFAULT '',
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_facility_maintains_facility ON facility_maintains(facility_id);

-- 22 防护用品发放（表2-6）
CREATE TABLE IF NOT EXISTS ppe_issues (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    worker_id   INTEGER NOT NULL REFERENCES workers(id) ON DELETE CASCADE,
    workplace_id INTEGER DEFAULT NULL REFERENCES workplaces(id),   -- 车间/场所（v0.3.5 起关联）
    post_id     INTEGER DEFAULT NULL REFERENCES posts(id),         -- 岗位（v0.3.5 起关联）
    department  TEXT DEFAULT '',                        -- 车间/部门（补充说明；下拉无对应时手填）
    item_name   TEXT NOT NULL,                          -- 用品名称
    model       TEXT DEFAULT '',                        -- 型号
    quantity    TEXT DEFAULT '',                        -- 数量
    issued_on   TEXT DEFAULT '',                        -- 领取日期
    remark      TEXT DEFAULT '',
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_ppe_issues_worker ON ppe_issues(worker_id);

-- 23 分类文档（11类；多态关联）。检测报告/体检批次/体检记录等的报告原件统一存放于此（link_type + link_id）
CREATE TABLE IF NOT EXISTS documents (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    category    TEXT NOT NULL,                          -- 文档分类（11类）
    title       TEXT NOT NULL,
    doc_no      TEXT DEFAULT '',                        -- 文档编号
    issuer      TEXT DEFAULT '',                        -- 出具/发文单位
    doc_date    TEXT DEFAULT '',
    expire_date TEXT DEFAULT NULL,                      -- 有效期至，驱动到期提醒
    file_path   TEXT NOT NULL,                          -- 上传文件存储名
    link_type   TEXT DEFAULT '',                        -- worker/workplace/project/detection_report/exam_batch/exam_record/空
    link_id     INTEGER DEFAULT NULL,
    remark      TEXT DEFAULT '',
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_documents_category ON documents(category, expire_date);
CREATE INDEX IF NOT EXISTS idx_documents_link ON documents(link_type, link_id);

-- 24 年度职业病防治计划实施检查（表2-1）
CREATE TABLE IF NOT EXISTS mgmt_plan_checks (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    plan_date     TEXT DEFAULT '',                      -- 计划制定日期
    plan_content  TEXT NOT NULL,                        -- 计划内容
    implementation TEXT DEFAULT '',                     -- 实施情况
    owner_name    TEXT DEFAULT '',                      -- 负责人
    remark        TEXT DEFAULT '',
    created_at    TEXT NOT NULL,
    updated_at    TEXT NOT NULL
);

-- 25 职业病防治经费（表2-3）
CREATE TABLE IF NOT EXISTS mgmt_budgets (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    purpose      TEXT NOT NULL,                         -- 经费项目
    work_content TEXT DEFAULT '',                       -- 工作内容
    amount       TEXT DEFAULT '',                       -- 金额（万元）
    owner_name   TEXT DEFAULT '',                       -- 负责人
    remark       TEXT DEFAULT '',
    created_at   TEXT NOT NULL,
    updated_at   TEXT NOT NULL
);

-- 26 工作场所警示标识（表2-7）
CREATE TABLE IF NOT EXISTS warning_signs (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    workplace_id INTEGER DEFAULT NULL REFERENCES workplaces(id),   -- 所在场所（v0.3.5 起关联）
    location     TEXT NOT NULL,                         -- 设置地点（具体位置说明；未选场所时手填）
    sign_type    TEXT DEFAULT '',                       -- 标识类别
    install_date TEXT DEFAULT '',                       -- 设置日期
    remark       TEXT DEFAULT '',
    created_at   TEXT NOT NULL,
    updated_at   TEXT NOT NULL
);

-- 27 职业卫生检查和处理记录（表2-8）
CREATE TABLE IF NOT EXISTS mgmt_inspections (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    check_date        TEXT DEFAULT '',                  -- 检查日期
    check_content     TEXT NOT NULL,                    -- 检查内容
    issue             TEXT DEFAULT '',                  -- 发现问题
    handle_measure    TEXT DEFAULT '',                  -- 处理措施
    responsible_person TEXT DEFAULT '',                 -- 责任人
    deadline          TEXT DEFAULT '',                  -- 整改期限
    status            TEXT NOT NULL DEFAULT '待整改' CHECK (status IN ('待整改','已整改')),
    remark            TEXT DEFAULT '',
    created_at        TEXT NOT NULL,
    updated_at        TEXT NOT NULL
);

-- 28 职业卫生监管意见和落实情况（表2-9）
CREATE TABLE IF NOT EXISTS mgmt_supervisions (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    opinion_date    TEXT DEFAULT '',                    -- 意见日期
    supervision_unit TEXT DEFAULT '',                   -- 监管部门
    opinion_content TEXT DEFAULT '',                    -- 监管意见
    rectification   TEXT DEFAULT '',                    -- 落实情况
    status          TEXT NOT NULL DEFAULT '待落实' CHECK (status IN ('待落实','已落实')),
    remark          TEXT DEFAULT '',
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);

-- 29 职业病危害事故报告与处理记录（表5-5）
CREATE TABLE IF NOT EXISTS health_accidents (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name       TEXT DEFAULT '',                 -- 单位名称
    legal_person       TEXT DEFAULT '',                 -- 法定代表人
    reporter           TEXT DEFAULT '',                 -- 报告人
    contact_phone      TEXT DEFAULT '',                 -- 联系电话
    accident_time      TEXT DEFAULT '',                 -- 事故发生时间
    workplace_id       INTEGER DEFAULT NULL REFERENCES workplaces(id),  -- 发生场所（v0.3.5 起关联）
    post_id            INTEGER DEFAULT NULL REFERENCES posts(id),       -- 发生岗位（v0.3.5 起关联）
    workplace_name     TEXT DEFAULT '',                 -- 发生场所（下拉无对应时手填）
    post_name          TEXT DEFAULT '',                 -- 发生岗位（下拉无对应时手填）
    work_content       TEXT DEFAULT '',                 -- 作业内容
    exposure_count     INTEGER NOT NULL DEFAULT 0,      -- 接触人数
    sick_count         INTEGER NOT NULL DEFAULT 0,      -- 发病人数
    hospital_count     INTEGER NOT NULL DEFAULT 0,      -- 住院人数
    death_count        INTEGER NOT NULL DEFAULT 0,      -- 死亡人数
    hazard_factor      TEXT DEFAULT '',                 -- 危害因素
    description        TEXT DEFAULT '',                 -- 事故经过
    preliminary_opinion TEXT DEFAULT '',                -- 初步处理意见
    report_time        TEXT DEFAULT '',                 -- 报告时间
    report_unit        TEXT DEFAULT '',                 -- 报告单位
    handler            TEXT DEFAULT '',                 -- 处理人
    remark             TEXT DEFAULT '',
    created_at         TEXT NOT NULL,
    updated_at         TEXT NOT NULL
);

-- v0.3.5 台账场所/岗位关联索引
CREATE INDEX IF NOT EXISTS idx_materials_workplace ON materials(workplace_id);
CREATE INDEX IF NOT EXISTS idx_materials_post ON materials(post_id);
CREATE INDEX IF NOT EXISTS idx_material_hazards_hazard ON material_hazards(hazard_id);
CREATE INDEX IF NOT EXISTS idx_ppe_issue_hazards_hazard ON ppe_issue_hazards(hazard_id);
CREATE INDEX IF NOT EXISTS idx_warning_sign_hazards_hazard ON warning_sign_hazards(hazard_id);
CREATE INDEX IF NOT EXISTS idx_ppe_issues_workplace ON ppe_issues(workplace_id);
CREATE INDEX IF NOT EXISTS idx_ppe_issues_post ON ppe_issues(post_id);
CREATE INDEX IF NOT EXISTS idx_warning_signs_workplace ON warning_signs(workplace_id);
CREATE INDEX IF NOT EXISTS idx_health_accidents_workplace ON health_accidents(workplace_id);
CREATE INDEX IF NOT EXISTS idx_health_accidents_post ON health_accidents(post_id);

-- 30 系统配置（键值对）
CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT DEFAULT ''
);
INSERT OR IGNORE INTO settings(key, value) VALUES
    ('schema_version', '3'),
    ('archive_prefix', '');                             -- 档案号前缀，如 'AB'

-- ===== 以下为 selftest 附加的最小种子数据（模拟 v0.4.14 用户库） =====
-- user_version 由 selftest 设为 14
INSERT INTO workplaces (name, location, status, created_at, updated_at) VALUES ('夹具车间', '1号厂房', '启用', '2026-01-05 09:00:00', '2026-01-05 09:00:00');
INSERT INTO posts (workplace_id, name, status, created_at, updated_at) VALUES (1, '夹具岗', '启用', '2026-01-05 09:00:00', '2026-01-05 09:00:00');
INSERT INTO workers (archive_no, name, gender, id_card, status, entry_date, created_at, updated_at) VALUES
    ('2026001', '测试甲', '男', '330106199001010011', '在职', '2023-03-01', '2026-01-05 09:00:00', '2026-01-05 09:00:00'),
    ('2026002', '测试乙', '女', '330106199202020028', '在职', '2024-06-01', '2026-01-05 09:00:00', '2026-01-05 09:00:00');
INSERT INTO worker_posts (worker_id, start_date, end_date, company_name, post_id, post_name, hazards_snapshot, created_at, updated_at) VALUES
    (1, '2023-03-01', NULL, '夹具单位', 1, '夹具岗', '噪声', '2026-01-05 09:00:00', '2026-01-05 09:00:00');
INSERT INTO exam_batches (exam_date, agency, exam_type, planned_count, created_at, updated_at) VALUES ('2024-07-10', '夹具体检机构', '在岗期间', 2, '2026-01-06 09:00:00', '2026-01-06 09:00:00');
INSERT INTO exam_records (worker_id, batch_id, exam_date, exam_type, conclusion, agency, created_at, updated_at) VALUES
    (1, 1, '2024-07-10', '在岗期间', '目前未见异常', '夹具体检机构', '2026-01-06 09:00:00', '2026-01-06 09:00:00');
INSERT INTO detection_reports (report_no, agency, category, test_date, report_date, created_at, updated_at) VALUES
    ('JC-2024-001', '夹具检测机构', '定期检测', '2024-05-20', '2024-06-01', '2026-01-07 09:00:00', '2026-01-07 09:00:00');
INSERT INTO projects (name, type, start_date, end_date, created_at, updated_at) VALUES
    ('夹具技改项目', '技改', '2024-03-01', '2025-02-01', '2026-01-08 09:00:00', '2026-01-08 09:00:00');
-- 文档：覆盖 §3.6 各回填分支 + 未分类残留（file_path 只需非空，文件不必存在）
INSERT INTO documents (category, title, doc_date, file_path, link_type, link_id, created_at, updated_at) VALUES
    ('体检报告', '个人体检报告 测试甲 2024-07-10', '2024-07-10', 'documents/202407/fix-exam.pdf', 'exam_record', 1, '2026-01-06 10:00:00', '2026-01-06 10:00:00'),
    ('体检报告', '2024-07-10 在岗期间体检批次报告', '2024-07-10', 'documents/202407/fix-batch.pdf', 'exam_batch', 1, '2026-01-06 10:00:00', '2026-01-06 10:00:00'),
    ('检测评价报告', '检测报告 JC-2024-001', '2024-06-01', 'documents/202406/fix-detect.pdf', 'detection_report', 1, '2026-01-07 10:00:00', '2026-01-07 10:00:00'),
    ('管理制度与操作规程', '职业卫生管理制度汇编', '2023-01-01', 'documents/202301/fix-rules.pdf', '', NULL, '2026-01-01 10:00:00', '2026-01-01 10:00:00'),
    ('应急预案', '职业病危害事故应急预案', '2023-06-01', 'documents/202306/fix-plan.pdf', '', NULL, '2026-01-01 10:00:00', '2026-01-01 10:00:00'),
    ('体检报告', '体检机构资质证书', '2025-01-01', 'documents/202501/fix-qual.jpg', '', NULL, '2026-01-02 10:00:00', '2026-01-02 10:00:00'),
    ('其他', '员工活动照片', '2025-05-01', 'documents/202505/fix-other.png', 'worker', 2, '2026-01-03 10:00:00', '2026-01-03 10:00:00');
