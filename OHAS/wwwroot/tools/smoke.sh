#!/usr/bin/env bash
# ============================================================
# OHAS 冒烟测试（v0.5.0 建，后续版本持续扩充）
# 用临时库起 php -S，curl 登录后逐页断言 HTTP 200 + 关键字，
# 并对 documents/quick 做一次带文件的 POST。
# 用法：bash tools/smoke.sh [端口]   （默认 8099）
# ============================================================
set -u
cd "$(dirname "$0")/.."

PORT="${1:-8099}"
BASE="http://127.0.0.1:${PORT}"
WORK="$(mktemp -d)"
DB="${WORK}/smoke.db"
COOKIE="${WORK}/cookie.txt"
PASS=0; FAIL=0

export OHAS_DB="${DB}"
PHP_BIN="${PHP_BIN:-php}"

echo "== 1) 准备临时库（schema + 管理员 + archive 种子） =="
touch "${DB}"
"${PHP_BIN}" tools/smoke_init.php || exit 1

echo "== 2) 启动服务 127.0.0.1:${PORT} =="
"${PHP_BIN}" -S "127.0.0.1:${PORT}" tools/dev-server.php >/dev/null 2>&1 &
SRV=$!
trap 'kill ${SRV} 2>/dev/null; rm -rf "${WORK}"' EXIT
for i in $(seq 1 30); do
  if curl -s -o /dev/null "${BASE}/index.php?r=auth/login" 2>/dev/null; then break; fi
  sleep 0.3
done

ok() { PASS=$((PASS+1)); echo "  [PASS] $1"; }
bad() { FAIL=$((FAIL+1)); echo "  [FAIL] $1"; }

# SQLite 查询（环境无 sqlite3 CLI 时用 php）
sq() {
  "${PHP_BIN}" -r '$p=new PDO("sqlite:".getenv("OHAS_DB")); echo $p->query($argv[1])->fetchColumn();' "$1"
}

# 请求页面：断言 200 且包含关键字
page() { # path keyword
  local body code
  body="$(curl -s -b "${COOKIE}" -w '\n%{http_code}' "${BASE}/index.php/${1}")"
  code="${body##*$'\n'}"
  if [ "${code}" = "200" ] && echo "${body}" | rg -q "${2}"; then ok "GET ${1} (含「${2}」)"; else bad "GET ${1} 期望 200/「${2}」，实际 ${code}"; fi
}

echo "== 3) 登录 =="
LOGIN_HTML="$(curl -s -c "${COOKIE}" "${BASE}/index.php/auth/login")"
TOKEN="$(echo "${LOGIN_HTML}" | rg -o 'name="_token" value="[^"]+"' | head -1 | sed 's/.*value="//; s/"$//')"
if [ -n "${TOKEN}" ]; then ok "登录页取到 CSRF 令牌"; else bad "登录页未取到 CSRF 令牌"; fi
LOGIN_CODE="$(curl -s -b "${COOKIE}" -c "${COOKIE}" -o /dev/null -w '%{http_code}' \
  -d "_token=${TOKEN}&username=admin&password=admin12345" "${BASE}/index.php/auth/login")"
# 登录成功会 302 → 跟随后访问工作台验证
DASH="$(curl -s -L -b "${COOKIE}" "${BASE}/index.php/dashboard/index")"
if echo "${DASH}" | rg -q "工作台"; then ok "登录成功（admin/admin12345）"; else bad "登录失败（HTTP ${LOGIN_CODE}）"; fi

echo "== 4) 页面冒烟 =="
"${PHP_BIN}" tools/make_sample_files.php "${WORK}" >/dev/null   # 样例文件（后续上传用）
WID="$(sq "SELECT id FROM workers WHERE name='张三' LIMIT 1")"
if [ -z "${WID}" ]; then WID="$(sq "SELECT MIN(id) FROM workers")"; fi
page "dashboard/index" "工作台"
page "workers/index" "劳动者"
page "documents/index" "分类文档"
page "ledgers/index?key=facilities" "防护设施"
page "ledgers/index?key=trainings" "培训"
page "system/backup" "上传环境"

# 旧功能页（S5：升级后显示不变）
RID="$(sq "SELECT MIN(id) FROM detection_reports")"
BID="$(sq "SELECT MIN(id) FROM exam_batches")"
if [ -n "${RID}" ] && [ "${RID}" != "0" ]; then page "detections/show?id=${RID}" "检测"; fi
if [ -n "${BID}" ] && [ "${BID}" != "0" ]; then page "exams/records?batch_id=${BID}" "体检"; fi

# v0.5.1：个人资料全景 / 全局搜索 / 目录打印 / 文档归类
page "workers/files?id=${WID}" "年度资料矩阵"
# 全局搜索命中单人 → 302 直达个人资料页（S1）
SRCH="$(curl -s -L -b "${COOKIE}" -w '\n%{http_code}' "${BASE}/index.php/search/index?q=%E5%BC%A0%E4%B8%89")"
SRCH_CODE="${SRCH##*$'\n'}"
if [ "${SRCH_CODE}" = "200" ] && echo "${SRCH}" | rg -q "个人资料全景"; then ok "search 命中单人直达个人资料页"; else bad "search 期望直达个人页，实际 ${SRCH_CODE}"; fi
page "prints/workerFiles?id=${WID}" "资料目录"
page "documents/index?doc_type=W_CONTRACT" "劳动合同"
page "documents/index?doc_type=__unclassified__" "未分类"

# v0.5.2：单位清单 / 缺失名单 / 清单打印 / 标不适用（S4/S6/S10）
page "archive/checklist" "单位资料清单"
page "archive/missing?item=C33" "缺失名单"
page "archive/missing?item=C29&format=csv" "对象,说明,去向"
page "prints/checklist" "资料归档清单"
TOKEN4="$(curl -s -b "${COOKIE}" "${BASE}/index.php/archive/checklist" | rg -o 'name="_token" value="[^"]+"' | head -1 | sed 's/.*value="//; s/"$//')"
MARK_CODE="$(curl -s -b "${COOKIE}" -o /dev/null -w '%{http_code}' -H 'Referer: '${BASE}'/index.php/archive/checklist' \
  -d "_token=${TOKEN4}&scope=unit&back=1&item_code=C01&reason=%E8%BF%91%E4%B8%89%E5%B9%B4%E6%97%A0%E5%BB%BA%E8%AE%BE%E9%A1%B9%E7%9B%AE" \
  "${BASE}/index.php/archive/mark")"
NA_AFTER="$(sq "SELECT COUNT(*) FROM archive_marks WHERE scope='unit' AND item_code='C01'")"
if [ "${MARK_CODE:0:1}" = "3" ] && [ "${NA_AFTER}" -ge 1 ]; then ok "archive/mark 标记 C01 不适用（HTTP ${MARK_CODE}）"; else bad "archive/mark HTTP ${MARK_CODE}，标记 ${NA_AFTER} 条"; fi
CL2="$(curl -s -b "${COOKIE}" "${BASE}/index.php/archive/checklist")"
if echo "${CL2}" | rg -q "近三年无建设项目"; then ok "清单页回显不适用原因"; else bad "清单页未回显不适用原因"; fi
UNMARK_CODE="$(curl -s -b "${COOKIE}" -o /dev/null -w '%{http_code}' -H 'Referer: '${BASE}'/index.php/archive/checklist' \
  -d "_token=${TOKEN4}&scope=unit&back=1&item_code=C01" "${BASE}/index.php/archive/unmark")"
NA_AFTER2="$(sq "SELECT COUNT(*) FROM archive_marks WHERE scope='unit' AND item_code='C01'")"
if [ "${UNMARK_CODE:0:1}" = "3" ] && [ "${NA_AFTER2}" = "0" ]; then ok "archive/unmark 取消后恢复（HTTP ${UNMARK_CODE}）"; else bad "unmark HTTP ${UNMARK_CODE}，剩余 ${NA_AFTER2} 条"; fi
# v0.5.5：白名单外（C03，自评表无合理缺项）标不适用应被拒绝
curl -s -b "${COOKIE}" -o /dev/null -H 'Referer: '${BASE}'/index.php/archive/checklist' \
  -d "_token=${TOKEN4}&scope=unit&back=1&item_code=C03&reason=%E6%B5%8B%E8%AF%95%E5%8E%9F%E5%9B%A0" "${BASE}/index.php/archive/mark"
NA_C03="$(sq "SELECT COUNT(*) FROM archive_marks WHERE scope='unit' AND item_code='C03'")"
if [ "${NA_C03}" = "0" ]; then ok "白名单外 C03 标不适用被拒绝"; else bad "C03 不应能标不适用，实际 ${NA_C03} 条"; fi
# v0.5.6：表2-2 分布表跨行（场所×因素；不重复按场所合并）
F22="$(curl -s -b "${COOKIE}" "${BASE}/index.php/prints/sheet?t=%E8%A1%A82-2")"
if echo "${F22}" | rg -q "接触人数（可重复）" && echo "${F22}" | rg -q "接触人数（不重复）" && echo "${F22}" | rg -q 'rowspan="[0-9]+"[^>]*>职业病危害因素分布情况'; then ok "表2-2 分布表含可重复/不重复两列与跨行标题"; else bad "表2-2 分布表结构不符"; fi
if echo "${F22}" | rg -q '<td rowspan="2">喷涂车间</td>' ; then ok "表2-2 场所名称按因素数跨行（喷涂车间 2 行）"; else bad "表2-2 场所名称未跨行"; fi

# 台账附件（S9）：先创建一条设施记录，再挂原件并回显
TOKEN3="$(curl -s -b "${COOKIE}" "${BASE}/index.php/ledgers/index?key=facilities" | rg -o 'name="_token" value="[^"]+"' | head -1 | sed 's/.*value="//; s/"$//')"
CREATE_CODE="$(curl -s -b "${COOKIE}" -o /dev/null -w '%{http_code}' \
  -F "_token=${TOKEN3}" -F "name=冲烟气净化器（冒烟）" -F "model=SMK-1" -F "status=在用" \
  "${BASE}/index.php/ledgers/create?key=facilities")"
LID="$(sq "SELECT MAX(id) FROM facilities")"
if [ -n "${LID}" ] && [ "${LID}" != "0" ] && { [ "${CREATE_CODE:0:1}" = "3" ] || [ "${CREATE_CODE}" = "200" ]; }; then
  ok "台账创建成功（HTTP ${CREATE_CODE}，设施#${LID}）"
else
  bad "台账创建失败（HTTP ${CREATE_CODE}，LID=${LID}）"
fi
ATT_CODE="$(curl -s -b "${COOKIE}" -o /dev/null -w '%{http_code}' \
  -F "_token=${TOKEN3}" -F "doc_type=U_FAC_PHOTO" -F "link_type=ledger_facilities" -F "link_id=${LID}" \
  -F "period_year=2026" -F "back=1" -F "files=@${WORK}/sample.png" "${BASE}/index.php/documents/quick")"
ATT_CNT="$(sq "SELECT COUNT(*) FROM documents WHERE link_type='ledger_facilities' AND link_id=${LID}")"
if [ "${ATT_CNT}" -ge 1 ]; then ok "台账附件上传成功（HTTP ${ATT_CODE}，设施#${LID} 原件 ${ATT_CNT} 份）"; else bad "台账附件上传失败（HTTP ${ATT_CODE}）"; fi
EDIT_PAGE="$(curl -s -b "${COOKIE}" "${BASE}/index.php/ledgers/edit?key=facilities&id=${LID}")"
if echo "${EDIT_PAGE}" | rg -q "报告原件"; then ok "台账编辑页回显原件区块"; else bad "台账编辑页未显示原件区块"; fi

# v0.5.3：三张系统扩充台账（表2-10/2-11/4-4，原补-1/2/3）页面 + 创建 + 挂附件
page "ledgers/index?key=drills" "应急演练"
page "ledgers/index?key=emergency_facilities" "应急设施"
page "ledgers/index?key=rectifications" "超标点整改"
DRILL_CODE="$(curl -s -b "${COOKIE}" -o /dev/null -w '%{http_code}' \
  -F "_token=${TOKEN3}" -F "drill_date=2026-06-20" -F "name=溶剂泄漏应急演练（冒烟）" -F "participants=12" -F "organizer=综合管理部" \
  "${BASE}/index.php/ledgers/create?key=drills")"
DID="$(sq "SELECT MAX(id) FROM drills")"
if [ -n "${DID}" ] && [ "${DID}" != "0" ] && { [ "${DRILL_CODE:0:1}" = "3" ] || [ "${DRILL_CODE}" = "200" ]; }; then
  ok "演练台账创建成功（HTTP ${DRILL_CODE}，演练#${DID}）"
else
  bad "演练台账创建失败（HTTP ${DRILL_CODE}，DID=${DID}）"
fi
DRILL_ATT="$(curl -s -b "${COOKIE}" -o /dev/null -w '%{http_code}' \
  -F "_token=${TOKEN3}" -F "doc_type=U_DRILL_FILE" -F "link_type=ledger_drills" -F "link_id=${DID}" \
  -F "period_year=2026" -F "back=1" -F "files=@${WORK}/sample.pdf" "${BASE}/index.php/documents/quick")"
DRILL_CNT="$(sq "SELECT COUNT(*) FROM documents WHERE link_type='ledger_drills' AND link_id=${DID}")"
if [ "${DRILL_CNT}" -ge 1 ]; then ok "演练记录附件上传成功（HTTP ${DRILL_ATT}）"; else bad "演练记录附件上传失败（HTTP ${DRILL_ATT}）"; fi
RECT_CODE="$(curl -s -b "${COOKIE}" -o /dev/null -w '%{http_code}' \
  -F "_token=${TOKEN3}" -F "item_desc=喷漆室操作位苯超标（冒烟）" -F "status=整改中" \
  "${BASE}/index.php/ledgers/create?key=rectifications")"
RID2="$(sq "SELECT MAX(id) FROM rectifications")"
if [ -n "${RID2}" ] && [ "${RID2}" != "0" ] && { [ "${RECT_CODE:0:1}" = "3" ] || [ "${RECT_CODE}" = "200" ]; }; then
  ok "整改台账创建成功（HTTP ${RECT_CODE}，整改#${RID2}）"
else
  bad "整改台账创建失败（HTTP ${RECT_CODE}，RID2=${RID2}）"
fi
# 系统扩充表样（表2-10/2-11/4-4）已进入打印中心并标注「系统扩充」（v0.5.10）
PRINTS_HTML="$(curl -s -b "${COOKIE}" "${BASE}/index.php/prints/index")"
if echo "${PRINTS_HTML}" | rg -q "系统扩充"; then ok "打印中心含系统扩充表样标注"; else bad "打印中心未见系统扩充表样标注"; fi
for T in "%E8%A1%A82-10" "%E8%A1%A82-11" "%E8%A1%A84-4"; do
  if echo "${PRINTS_HTML}" | rg -q "t=${T}"; then ok "打印中心含表样 t=${T}"; else bad "打印中心缺少表样 t=${T}"; fi
done
if echo "${PRINTS_HTML}" | rg -q "t=%E8%A1%A5|t=补"; then bad "打印中心仍出现补-N 表样打印链接"; else ok "打印中心不再使用补-N 编号"; fi
# 工作台有缺口的劳动者列表入口（v0.5.3）
if echo "${DASH}" | rg -q "去补录"; then ok "工作台含“有缺口的劳动者”列表入口"; else bad "工作台未见缺口人员列表入口"; fi

# v0.5.4：检测年度视图 / 到期提醒徽标 / 备份页存储占用
page "detections/yearView" "检测年度视图"
YV_HTML="$(curl -s -b "${COOKIE}" "${BASE}/index.php/detections/yearView")"
if echo "${YV_HTML}" | rg -q "喷漆工" && echo "${YV_HTML}" | rg -q "已归档（间接）|仅登记"; then ok "年度视图含岗位行与状态徽标"; else bad "年度视图缺少岗位行或状态徽标"; fi
page "detections/yearView?workplace_id=1&range=all" "年度视图"
if echo "${DASH}" | rg -q "已过期 [0-9]+ 份"; then ok "工作台含到期提醒徽标（种子含已到期合同）"; else bad "工作台未见到期提醒徽标"; fi
BK_HTML="$(curl -s -b "${COOKIE}" "${BASE}/index.php/system/backup")"
if echo "${BK_HTML}" | rg -q "存储占用" && echo "${BK_HTML}" | rg -q "上传文件数"; then ok "备份页显示存储占用（上传文件数/体积）"; else bad "备份页未显示存储占用"; fi

echo "== 5) documents/quick 带文件 POST =="
TOKEN2="$(curl -s -b "${COOKIE}" "${BASE}/index.php/workers/index" | rg -o 'name="_token" value="[^"]+"' | head -1 | sed 's/.*value="//; s/"$//')"
QUICK_CODE="$(curl -s -b "${COOKIE}" -o /dev/null -w '%{http_code}' \
  -F "_token=${TOKEN2}" -F "doc_type=W_CONTRACT" -F "link_type=worker" -F "link_id=${WID}" \
  -F "period_year=2026" -F "doc_date=2026-01-10" -F "back=1" \
  -F "files=@${WORK}/sample.pdf" "${BASE}/index.php/documents/quick")"
CNT="$(sq "SELECT COUNT(*) FROM documents WHERE doc_type='W_CONTRACT' AND link_id=${WID}")"
if [ "${QUICK_CODE:0:1}" = "3" ] || [ "${QUICK_CODE}" = "200" ]; then
  if [ "${CNT}" -ge 1 ]; then ok "quick 上传成功（HTTP ${QUICK_CODE}，W_CONTRACT 文档 ${CNT} 条）"; else bad "quick 返回 ${QUICK_CODE} 但数据库未新增 W_CONTRACT 文档"; fi
else
  bad "quick 上传 HTTP ${QUICK_CODE}"
fi

echo "== 6) 改名（AJAX） =="
DOCID="$(sq "SELECT id FROM documents WHERE doc_type='W_CONTRACT' AND link_id=${WID} ORDER BY id DESC LIMIT 1")"
REN="$(curl -s -b "${COOKIE}" -H 'X-Requested-With: XMLHttpRequest' -H 'Accept: application/json' \
  -d "_token=${TOKEN2}&id=${DOCID}&title=冒烟改名测试" "${BASE}/index.php/documents/rename")"
if echo "${REN}" | rg -q '"ok":true'; then ok "documents/rename 返回 ok"; else bad "documents/rename 返回异常：${REN}"; fi

echo "== 7) 导出资料包（S7）与审计日志（S8） =="
EXPORT_TOKEN="$(curl -s -b "${COOKIE}" "${BASE}/index.php/workers/index" | rg -o 'name="_token" value="[^"]+"' | head -1 | sed 's/.*value="//; s/"$//')"
EXPORT_CODE="$(curl -s -b "${COOKIE}" -o "${WORK}/export.zip" -w '%{http_code}' \
  -H 'Referer: '${BASE}'/index.php/workers/files?id='${WID} \
  -d "_token=${EXPORT_TOKEN}&id=${WID}" "${BASE}/index.php/workers/exportFiles")"
if [ "${EXPORT_CODE}" = "200" ] && [ -s "${WORK}/export.zip" ] && unzip -t "${WORK}/export.zip" >/dev/null 2>&1; then
  ok "exportFiles 下载 zip 且 unzip -t 通过（HTTP ${EXPORT_CODE}）"
else
  bad "exportFiles 异常（HTTP ${EXPORT_CODE}）"
fi
if unzip -l "${WORK}/export.zip" 2>/dev/null | rg -q "目录.csv"; then ok "zip 含 目录.csv"; else bad "zip 缺少 目录.csv"; fi
if unzip -l "${WORK}/export.zip" 2>/dev/null | rg -q "间接参考"; then ok "zip 含间接参考子目录（附录 C #8）"; else bad "zip 缺少间接参考子目录"; fi
# 下载一份个人文件（触发 download 日志）
curl -s -b "${COOKIE}" -o /dev/null "${BASE}/index.php/documents/download?id=${DOCID}"
AUDIT_VIEW="$(sq "SELECT COUNT(*) FROM logs WHERE action='view' AND object_type='worker_files' AND object_id=${WID}")"
AUDIT_EXPORT="$(sq "SELECT COUNT(*) FROM logs WHERE action='export' AND object_type='worker_files' AND object_id=${WID}")"
AUDIT_DL="$(sq "SELECT COUNT(*) FROM logs WHERE action='download' AND object_type='document' AND object_id=${DOCID}")"
if [ "${AUDIT_VIEW}" -ge 1 ] && [ "${AUDIT_EXPORT}" -ge 1 ]; then ok "审计：view/export 日志均在（S8）"; else bad "审计日志缺失（view=${AUDIT_VIEW} export=${AUDIT_EXPORT}）"; fi
if [ "${AUDIT_DL}" -ge 1 ]; then ok "审计：下载个人文件写日志（S8）"; else bad "下载日志缺失（download=${AUDIT_DL}）"; fi

echo
echo "----------"
echo "PASS ${PASS}  FAIL ${FAIL}"
[ "${FAIL}" -eq 0 ]
