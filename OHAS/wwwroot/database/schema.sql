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
-- 同场所岗位名唯一约束，防止并发/重复提交产生重复岗位
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
-- 岗位危害配置唯一约束，避免表4-2 重复计数
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
    duty_role       TEXT NOT NULL DEFAULT '',            -- 职务角色，逗号分隔：主要负责人,职业卫生管理人员；空=普通劳动者
    status          TEXT NOT NULL DEFAULT '在职' CHECK (status IN ('在职','离岗','退休','离职')),
    entry_date      TEXT DEFAULT '',                    -- 入职本单位时间
    leave_date      TEXT NOT NULL DEFAULT '',            -- 离岗/离职/退休日期：非在职状态必填，用于收尾职业史段
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

-- 12 检测报告（监测检测档案）
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
-- 报告编号唯一约束，防止重复报告

-- 13 检测明细（表6-2 归集数据源）
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
    sample_type   TEXT NOT NULL DEFAULT '',             -- 采样类型：TWA/STEL/MAC/峰（空=未指定）
    verdict       TEXT DEFAULT '',                      -- 判定结论：合格/超标
    matched_post_ids TEXT NOT NULL DEFAULT '',          -- 保存时冻结的可归集岗位id（,分隔；空=旧明细按当前配置兜底）
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
    workplace_id INTEGER DEFAULT NULL REFERENCES workplaces(id),   -- 使用场所（关联列）
    post_id     INTEGER DEFAULT NULL REFERENCES posts(id),         -- 使用岗位（关联列）
    usage_place TEXT DEFAULT '',                        -- 使用车间岗位（补充说明；下拉无对应时手填）
    supplier    TEXT DEFAULT '',                        -- 生产供货单位
    remark      TEXT DEFAULT '',
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL
);
-- 14a 设备材料化学品 × 危害因素（同一设备可对应多种危害因素）
CREATE TABLE IF NOT EXISTS material_hazards (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    material_id INTEGER NOT NULL REFERENCES materials(id) ON DELETE CASCADE,
    hazard_id   INTEGER NOT NULL REFERENCES hazards(id),
    created_at  TEXT NOT NULL,
    UNIQUE (material_id, hazard_id)
);

-- 表2-6 防护用品发放 × 防护的危害因素（多对多）
CREATE TABLE IF NOT EXISTS ppe_issue_hazards (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    ppe_issue_id INTEGER NOT NULL REFERENCES ppe_issues(id) ON DELETE CASCADE,
    hazard_id    INTEGER NOT NULL REFERENCES hazards(id),
    created_at   TEXT NOT NULL,
    UNIQUE (ppe_issue_id, hazard_id)
);

-- 表2-7 警示标识 × 对应的危害因素（多对多）
CREATE TABLE IF NOT EXISTS warning_sign_hazards (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    warning_sign_id INTEGER NOT NULL REFERENCES warning_signs(id) ON DELETE CASCADE,
    hazard_id       INTEGER NOT NULL REFERENCES hazards(id),
    created_at      TEXT NOT NULL,
    UNIQUE (warning_sign_id, hazard_id)
);

-- 15 体检批次（表5-1）
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

-- 16 检查记录（表6-3）
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
    notice_method     TEXT NOT NULL DEFAULT '',         -- 结果告知方式（书面/当面/电话等，告知书原件挂 W_EXAM_NOTICE）
    signed_at         TEXT DEFAULT '',                  -- 本人签字确认时间
    site_handling     TEXT DEFAULT '',                  -- 现场处理情况
    remark            TEXT DEFAULT '',
    created_at        TEXT NOT NULL,
    updated_at        TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_exam_records_worker ON exam_records(worker_id, exam_date);
CREATE INDEX IF NOT EXISTS idx_exam_records_batch ON exam_records(batch_id);
-- 同批次同人唯一：双开标签页 / 重放请求也不会产生重复待检记录
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
-- 同场培训同人唯一：防重复签到
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
    workplace_id INTEGER DEFAULT NULL REFERENCES workplaces(id),   -- 车间/场所（关联列）
    post_id     INTEGER DEFAULT NULL REFERENCES posts(id),         -- 岗位（关联列）
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
    category    TEXT NOT NULL,                          -- 文档分类（13类）
    title       TEXT NOT NULL,
    doc_no      TEXT DEFAULT '',                        -- 文档编号
    issuer      TEXT DEFAULT '',                        -- 出具/发文单位
    doc_date    TEXT DEFAULT '',
    expire_date TEXT DEFAULT NULL,                      -- 有效期至，驱动到期提醒
    file_path   TEXT NOT NULL,                          -- 上传文件存储名
    doc_type    TEXT NOT NULL DEFAULT '',               -- 资料类型编码（DocTypes）；空=未分类
    period_year INTEGER DEFAULT NULL,                   -- 资料所属年度；空时取 doc_date 的年份
    original_name TEXT NOT NULL DEFAULT '',             -- 上传时的原文件名（title 可改名，此列不变）
    file_hash   TEXT NOT NULL DEFAULT '',               -- sha1，仅用于同一对象下重复上传提示，不阻止上传
    link_type   TEXT DEFAULT '',                        -- worker/workplace/project/detection_report/exam_batch/exam_record/ledger_*/disease/空
    link_id     INTEGER DEFAULT NULL,
    remark      TEXT DEFAULT '',
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_documents_category ON documents(category, expire_date);
CREATE INDEX IF NOT EXISTS idx_documents_link ON documents(link_type, link_id);
CREATE INDEX IF NOT EXISTS idx_documents_type ON documents(doc_type, period_year);

-- 23a “不适用”标记：单位级（item_code=C01–C36）与个人级（item_code=资料类型编码+年度格）共用
CREATE TABLE IF NOT EXISTS archive_marks (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    scope       TEXT NOT NULL CHECK (scope IN ('unit','worker')),
    item_code   TEXT NOT NULL,
    worker_id   INTEGER DEFAULT NULL REFERENCES workers(id) ON DELETE CASCADE,
    period_year INTEGER DEFAULT NULL,
    reason      TEXT NOT NULL,
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_archive_marks
    ON archive_marks(scope, item_code, COALESCE(worker_id,0), COALESCE(period_year,0));

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
    workplace_id INTEGER DEFAULT NULL REFERENCES workplaces(id),   -- 所在场所（关联列）
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
    workplace_id       INTEGER DEFAULT NULL REFERENCES workplaces(id),  -- 发生场所（关联列）
    post_id            INTEGER DEFAULT NULL REFERENCES posts(id),       -- 发生岗位（关联列）
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

-- 29a 应急演练记录（表2-10；系统扩充表样，归属第 2 类管理档案）
CREATE TABLE IF NOT EXISTS drills (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    drill_date   TEXT DEFAULT '',                       -- 演练日期
    name         TEXT NOT NULL,                         -- 演练科目
    workplace_id INTEGER DEFAULT NULL REFERENCES workplaces(id),
    participants INTEGER NOT NULL DEFAULT 0,            -- 参加人数
    organizer    TEXT DEFAULT '',                       -- 组织部门
    summary      TEXT DEFAULT '',                       -- 演练总结
    remark       TEXT DEFAULT '',
    created_at   TEXT NOT NULL,
    updated_at   TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_drills_workplace ON drills(workplace_id);

-- 29b 应急设施台账（表2-11；系统扩充表样，归属第 2 类管理档案）
CREATE TABLE IF NOT EXISTS emergency_facilities (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    name         TEXT NOT NULL,                         -- 设施名称
    model        TEXT DEFAULT '',                       -- 规格型号
    workplace_id INTEGER DEFAULT NULL REFERENCES workplaces(id),
    quantity     TEXT DEFAULT '',                       -- 数量
    location     TEXT DEFAULT '',                       -- 存放位置
    check_date   TEXT DEFAULT '',                       -- 最近检查日期
    status       TEXT NOT NULL DEFAULT '在用' CHECK (status IN ('在用','停用','报废')),
    remark       TEXT DEFAULT '',
    created_at   TEXT NOT NULL,
    updated_at   TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_emergency_facilities_workplace ON emergency_facilities(workplace_id);

-- 29c 超标点整改台账（表4-4；系统扩充表样，归属第 4 类监测检测档案）
CREATE TABLE IF NOT EXISTS rectifications (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id        INTEGER DEFAULT NULL REFERENCES detection_reports(id),      -- 超标来源报告
    item_desc        TEXT NOT NULL,                     -- 超标点及危害因素
    plan             TEXT DEFAULT '',                   -- 整改方案
    done_date        TEXT DEFAULT '',                   -- 完成日期
    verify_report_id INTEGER DEFAULT NULL REFERENCES detection_reports(id),     -- 复测报告
    status           TEXT NOT NULL DEFAULT '整改中' CHECK (status IN ('整改中','已完成')),
    remark           TEXT DEFAULT '',
    created_at       TEXT NOT NULL,
    updated_at       TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_rectifications_report ON rectifications(report_id);
CREATE INDEX IF NOT EXISTS idx_rectifications_verify ON rectifications(verify_report_id);

-- 台账场所/岗位关联索引
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

-- 29 内部编制资料（六类档案官方目录项在线内容；外部报告仍只走 documents 附件）
CREATE TABLE IF NOT EXISTS archive_materials (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    group_key   TEXT NOT NULL,              -- 六类档案 key：three/management/training/monitoring/health/personal
    filing_seq  TEXT NOT NULL,              -- 官方目录项序号（同组内唯一）
    doc_type    TEXT NOT NULL DEFAULT '',   -- 对应 DocTypes 编码（可空：无精确类型的目录项）
    title       TEXT NOT NULL,
    doc_no      TEXT NOT NULL DEFAULT '',
    issuer      TEXT NOT NULL DEFAULT '',
    doc_date    TEXT NOT NULL DEFAULT '',
    body        TEXT NOT NULL DEFAULT '',   -- 结构化正文（纯文本 / 多段）
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL,
    UNIQUE (group_key, filing_seq)
);
CREATE INDEX IF NOT EXISTS idx_archive_materials_group ON archive_materials(group_key);

-- 30 系统配置（键值对）
CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT DEFAULT ''
);
-- 注：schema_version 不在此维护；库结构版本唯一来源为 Migrator::VERSION，安装时写入 installed.lock
INSERT OR IGNORE INTO settings(key, value) VALUES
    ('archive_prefix', ''),                             -- 档案号前缀，如 'AB'
    ('retention_exam_years', '');                       -- 体检档案保管年限提示（清单页用；空=不显示）
