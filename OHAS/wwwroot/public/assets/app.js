/* ============================================================
   OHAS 职业健康档案系统 · 前端交互（原生 JS + Tabler 组件）
   依赖：assets/vendor/bootstrap.bundle.min.js（Bootstrap 5.3）
   注意：不要同时引入 tabler.min.js——它内嵌另一份 Bootstrap 5.3.6，
   双份数据接口（data-api）会让下拉菜单点击后立即被第二份关闭。
   ============================================================ */
(function () {
    'use strict';

    /* ---------- 工具 ---------- */
    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function csrfToken() {
        var el = $('input[name="_token"]');
        return el ? el.value : '';
    }

    /* ---------- 通用确认弹窗（Tabler Modal，替代 window.confirm） ---------- */
    var modalEl = $('#app-modal');
    var modalApi = null;

    function confirmDialog(opts, onOk) {
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            // 兜底：无弹窗环境退回原生确认
            if (window.confirm((opts.title || '确认操作') + '\n\n' + (opts.text || ''))) onOk();
            return;
        }
        modalApi = modalApi || bootstrap.Modal.getOrCreateInstance(modalEl);
        $('#app-modal-title').textContent = opts.title || '确认操作';
        $('#app-modal-text').textContent = opts.text || '';
        var okBtn = $('#app-modal-ok');
        okBtn.textContent = opts.okText || '确认';
        okBtn.className = 'btn w-100 ' + (opts.okClass || 'btn-danger');

        var handler = function () {
            cleanup();
            onOk();
        };
        var cancelHandler = function () { cleanup(); };
        function cleanup() {
            okBtn.removeEventListener('click', handler);
            modalEl.removeEventListener('hidden.bs.modal', cancelHandler);
        }
        okBtn.addEventListener('click', handler);
        modalEl.addEventListener('hidden.bs.modal', cancelHandler);
        modalApi.show();
    }

    /* ---------- 删除确认（列表页） ----------
       两种标记：data-del（#del-form，通用列表）与 data-record-del（#record-del-form，体检记录）。
       提交走 fetch：端点返回 JSON 时刷新页面，避免浏览器整页跳到裸 JSON；
       端点返回重定向（如 flash 跳转）时回退为表单原生提交，保持原有体验。
       data-docs 携带名下文档数时在确认文案中提示（文档保留、仅解除关联）；
       端点返回 need_confirm（服务端复核仍有文档）时二次确认后带 confirmed=1 重提。 */
    function postDelForm(form, onNeedConfirm) {
        var fd = new FormData(form);
        fetch(form.action, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.need_confirm) {
                    if (onNeedConfirm) onNeedConfirm(data);
                    return;
                }
                if (data && data.ok) {
                    if (data.msg) stashNotice(data.msg);
                    window.location.reload();
                } else if (data && data.msg) {
                    alert(data.msg);
                } else {
                    form.submit();   // 非 JSON 响应：按普通表单提交（端点会 flash+redirect）
                }
            })
            .catch(function () { form.submit(); });
    }
    document.addEventListener('click', function (e) {
        var del = e.target.closest('[data-del],[data-record-del]');
        if (!del) return;
        e.preventDefault();
        var form = del.hasAttribute('data-record-del') ? ($('#record-del-form') || $('#del-form')) : $('#del-form');
        if (!form) return;
        var docs = parseInt(del.dataset.docs || '0', 10) || 0;
        var exam = parseInt(del.dataset.exam || '0', 10) || 0;
        var disease = parseInt(del.dataset.disease || '0', 10) || 0;
        var training = parseInt(del.dataset.training || '0', 10) || 0;
        var ppe = parseInt(del.dataset.ppe || '0', 10) || 0;
        var text = '确定删除「' + (del.dataset.name || '该记录') + '」吗？此操作不可恢复。'
            + ((exam > 0 || disease > 0) ? '\n\n名下有 ' + exam + ' 条体检记录、' + disease + ' 条职业病登记，属于长期监护档案，不能物理删除；请将状态改为「离职/离岗/退休」。' : '')
            + ((docs + training + ppe) > 0 ? '\n\n将级联删除 ' + training + ' 条参训记录、' + ppe + ' 条劳保领用记录；' + docs + ' 份文档仅解除关联、仍保留在「分类文档」中。' : '');
        if (exam > 0 || disease > 0) { alert(text); return; }
        confirmDialog({
            title: '删除确认',
            text: text,
            okText: '确认删除'
        }, function () {
            var idInput = form.querySelector('input[name="id"]');
            if (idInput) idInput.value = del.dataset.del || del.dataset.recordDel;
            var hidden = form.querySelector('input[name="confirmed"]');
            if (hidden) hidden.value = '';
            postDelForm(form, function (data) {
                confirmDialog({
                    title: '名下有文档',
                    text: '该劳动者名下有 ' + (data.doc_count || 0) + ' 份文档、' + (data.training_count || 0) + ' 条参训记录、'
                        + (data.ppe_count || 0) + ' 条劳保领用记录。文档仅解除关联并保留，参训与领用记录将随信息卡删除。是否继续删除？',
                    okText: '仍然删除'
                }, function () {
                    var c = form.querySelector('input[name="confirmed"]');
                    if (!c) {
                        c = document.createElement('input');
                        c.type = 'hidden';
                        c.name = 'confirmed';
                        form.appendChild(c);
                    }
                    c.value = '1';
                    postDelForm(form);
                });
            });
        });
    });

    /* ---------- 文档就地改名 ----------
       [data-rename] 铅笔：fetch + CSRF，成功后原地更新文字。 */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-rename]');
        if (!btn) return;
        e.preventDefault();
        var title = window.prompt('文档标题（改名只改显示标题，不影响原文件名）：', btn.dataset.title || '');
        if (title === null) return;
        title = title.trim();
        if (!title) { alert('标题不能为空。'); return; }
        var fd = new FormData();
        fd.set('_token', csrfToken());
        fd.set('id', btn.dataset.rename);
        fd.set('title', title);
        fetch(url('documents/rename'), {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    btn.dataset.title = title;
                    var host = btn.closest('.list-group-item, td, .doc-cell');
                    if (host) {
                        var span = host.querySelector('.doc-title-text[data-doc-id="' + btn.dataset.rename + '"]')
                            || host.querySelector('.doc-title-text');
                        if (span) span.textContent = title;
                    }
                } else {
                    alert((data && data.msg) || '改名失败。');
                }
            })
            .catch(function () { alert('网络异常，请重试。'); });
    });

    /* ---------- 岗位危害配置动态行 ---------- */
    var phRows = $('#ph-rows');
    if (phRows) {
        var addBtn = $('#ph-add');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var first = $('.ph-row', phRows);
                var clone = first.cloneNode(true);
                // 危害因素选择器：先移除克隆行中的组件 DOM（事件监听不会被克隆）
                if (window.HazardPicker) window.HazardPicker.destroy(clone);
                $all('select, input', clone).forEach(function (el) { el.value = ''; });
                phRows.appendChild(clone);
                // 重新为克隆行构建选择器组件
                if (window.HazardPicker) window.HazardPicker.init(clone);
                updatePhWarn();
            });
        }
        phRows.addEventListener('click', function (e) {
            var btn = e.target.closest('.ph-del');
            if (!btn) return;
            var rows = $all('.ph-row', phRows);
            if (rows.length <= 1) {
                confirmDialog({ title: '无法移除', text: '至少保留一行配置（可为空行）。', okText: '知道了', okClass: 'btn-primary' }, function () {});
                return;
            }
            var row = btn.closest('.ph-row');
            if (window.HazardPicker) window.HazardPicker.destroy(row);   // 释放该行选择器实例
            row.remove();
            updatePhWarn();
        });
        function updatePhWarn() {
            var warn = $('#ph-warn');
            if (!warn) return;
            var filled = $all('.ph-row select[name="ph_hazard_id[]"]', phRows).some(function (s) { return s.value !== ''; });
            warn.hidden = filled;
        }
        updatePhWarn();
    }

    /* ---------- 劳动者表单：身份证自动推导 ---------- */
    var idCardInput = $('input[name="id_card"]');
    if (idCardInput) {
        var W = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        var C = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        idCardInput.addEventListener('blur', function () {
            var id = this.value.trim().toUpperCase();
            if (!/^\d{17}[\dX]$/.test(id)) return;
            var sum = 0;
            for (var i = 0; i < 17; i++) sum += (+id[i]) * W[i];
            if (C[sum % 11] !== id[17]) return; // 校验不通过交由服务端报错
            var birth = id.substr(6, 4) + '-' + id.substr(10, 2) + '-' + id.substr(12, 2);
            var birthInput = $('input[name="birth_date"]');
            if (birthInput && !birthInput.value) {
                birthInput.value = birth;
                birthInput.dispatchEvent(new Event('input'));
            }
            var genderSel = $('select[name="gender"]');
            if (genderSel && !genderSel.value) genderSelect(genderSel, (+id[16]) % 2 === 1 ? '男' : '女');
        });
    }
    function genderSelect(sel, v) {
        $all('option', sel).forEach(function (o) { if (o.value === v) o.selected = true; });
    }

    /* ---------- 劳动者表单：照片预览 ---------- */
    var photoInput = $('#photo-input');
    if (photoInput) {
        photoInput.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (!file) return;
            var box = $('#photo-box');
            if (!box) return;
            var reader = new FileReader();
            reader.onload = function () {
                box.innerHTML = '<img src="' + reader.result + '" alt="预览">';
            };
            reader.readAsDataURL(file);
        });
    }

    /* ---------- 子表单（fetch 提交） ---------- */
    function bindSubform(formSel, beforeSubmit) {
        var form = $(formSel);
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var msg = $('.subform-msg', form);
            var btn = form.querySelector('button[type="submit"]');
            if (beforeSubmit && beforeSubmit(form) === false) {
                if (msg) { msg.textContent = '请填写必填项。'; }
                return;
            }
            var fd = new FormData(form);
            fd.set('_token', csrfToken());
            fetch(form.dataset.endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        window.location.reload();
                    } else {
                        if (msg) msg.textContent = data.msg || '保存失败。';
                        if (btn) btn.disabled = false;
                    }
                })
                .catch(function () {
                    if (msg) msg.textContent = '网络异常，请重试。';
                    if (btn) btn.disabled = false;
                });
        });
    }

    /* 职业史：模式切换（本单位 / 外单位） */
    var historyForm = $('#history-form');
    if (historyForm) {
        function toggleMode() {
            var mode = ($('input[name="mode"]:checked', historyForm) || {}).value;
            var internal = mode === 'internal';
            $('#f-post', historyForm).hidden = !internal;
            $('#f-company', historyForm).hidden = internal;
            $('#f-postname', historyForm).hidden = internal;
            $('#f-hazards', historyForm).hidden = internal;
            $('#f-protection', historyForm).hidden = internal;
        }
        $all('input[name="mode"]', historyForm).forEach(function (r) { r.addEventListener('change', toggleMode); });
        toggleMode();

        bindSubform('#history-form', function (form) {
            var mode = ($('input[name="mode"]:checked', form) || {}).value;
            if (mode === 'internal' && !$('select[name="post_id"]', form).value) return false;
            if (mode === 'external' && !$('input[name="company_name"]', form).value) return false;
        });

        /* 编辑回填 */
        $all('.history-edit').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                fetch(this.href, { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.ok) { alert(data.msg || '读取失败'); return; }
                        var row = data.row;
                        var form = historyForm;
                        form.querySelector('[name="row_id"]').value = row.id;
                        var internal = row.post_id && +row.post_id > 0;
                        var radio = form.querySelector('input[name="mode"][value="' + (internal ? 'internal' : 'external') + '"]');
                        if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change')); }
                        if (internal) {
                            var sel = form.querySelector('select[name="post_id"]');
                            if (sel) sel.value = row.post_id;
                        } else {
                            form.querySelector('[name="company_name"]').value = row.company_name || '';
                            form.querySelector('[name="post_name_manual"]').value = row.post_name || '';
                            form.querySelector('[name="hazards_manual"]').value = row.hazards_snapshot || '';
                            form.querySelector('[name="protection_manual"]').value = row.protection_snapshot || '';
                        }
                        var startInput = form.querySelector('[name="start_date"]');
                        var endInput = form.querySelector('[name="end_date"]');
                        startInput.value = row.start_date || '';
                        endInput.value = row.end_date || '';
                        startInput.dispatchEvent(new Event('input'));
                        endInput.dispatchEvent(new Event('input'));
                        form.querySelector('[name="remark"]').value = row.row_remark || row.remark || '';
                        var cancel = $('.subform-cancel', form);
                        if (cancel) cancel.hidden = false;
                        var title = $('.subform-title', form);
                        if (title) title.firstChild.textContent = '编辑岗位经历 ';
                        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    })
                    .catch(function () { alert('读取职业史记录失败，请刷新页面（Ctrl+F5）后重试。'); });
            });
        });
        var cancelBtn = $('.subform-cancel', historyForm);
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                historyForm.reset();
                historyForm.querySelector('[name="row_id"]').value = '';
                this.hidden = true;
                var title = $('.subform-title', historyForm);
                if (title) title.firstChild.textContent = '添加岗位经历 ';
                var evt = new Event('change', { bubbles: true });
                var radio = historyForm.querySelector('input[name="mode"]');
                if (radio) radio.dispatchEvent(evt);
            });
        }

        /* 删除（职业史 / 病史 / 诊断行） */
        bindRowDelete('.history-del', 'workers/deleteHistory');
        bindRowDelete('.illness-del', 'workers/deleteIllness');
        bindRowDelete('.disease-del', 'workers/deleteDisease');
    }

    function bindRowDelete(selector, path) {
        $all(selector).forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                confirmDialog({
                    title: '删除确认',
                    text: '确定删除该条记录吗？此操作不可恢复。',
                    okText: '确认删除'
                }, function () {
                    var fd = new FormData();
                    fd.set('_token', csrfToken());
                    fd.set('id', a.dataset.id);
                    if (a.dataset.worker) fd.set('worker_id', a.dataset.worker);
                    fetch(url(path), { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.ok) window.location.reload();
                            else alert(data.msg || '删除失败');
                        });
                });
            });
        });
    }

    /* 既往病史 / 职业病诊断表单 */
    bindSubform('#illness-form');
    bindSubform('#disease-form');

    /* ---------- 检测明细子表单（检测报告详情页） ---------- */
    var itemForm = $('#item-form');
    if (itemForm) {
        /* 危害因素选择 → 带出字典接触限值（可手工修订） */
        var hazardSel = $('select[name="hazard_id"]', itemForm);
        if (hazardSel) {
            hazardSel.addEventListener('change', function () {
                var opt = hazardSel.options[hazardSel.selectedIndex];
                var limitInput = $('input[name="limit_value"]', itemForm);
                if (limitInput && opt && opt.dataset.limit) limitInput.value = opt.dataset.limit;
            });
        }
        /* 岗位选择 → 自动带出所属作业场所 */
        var postSel = $('select[name="post_id"]', itemForm);
        if (postSel) {
            postSel.addEventListener('change', function () {
                var opt = postSel.options[postSel.selectedIndex];
                var wpSel = $('select[name="workplace_id"]', itemForm);
                if (wpSel && opt && opt.dataset.workplace) {
                    $all('option', wpSel).forEach(function (o) { if (o.value === opt.dataset.workplace) o.selected = true; });
                }
            });
        }

        bindSubform('#item-form', function (form) {
            if (!$('select[name="hazard_id"]', form).value) return false;
        });

        /* 编辑回填 */
        $all('.item-edit').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                fetch(this.href, { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.ok) { alert(data.msg || '读取失败'); return; }
                        var row = data.row;
                        form2Select(itemForm, 'workplace_id', row.workplace_id);
                        form2Select(itemForm, 'post_id', row.post_id);
                        form2Select(itemForm, 'hazard_id', row.hazard_id);
                        form2Select(itemForm, 'verdict', row.verdict);
                        form2Select(itemForm, 'sample_type', row.sample_type);
                        itemForm.querySelector('[name="row_id"]').value = row.id;
                        itemForm.querySelector('[name="test_point"]').value = row.test_point || '';
                        itemForm.querySelector('[name="result"]').value = row.result || '';
                        itemForm.querySelector('[name="unit"]').value = row.unit || '';
                        itemForm.querySelector('[name="limit_value"]').value = row.limit_value || '';
                        itemForm.querySelector('[name="protection"]').value = row.protection || '';
                        itemForm.querySelector('[name="remark"]').value = row.remark || '';
                        var cancel = $('.subform-cancel', itemForm);
                        if (cancel) cancel.hidden = false;
                        var title = $('.subform-title', itemForm);
                        if (title) title.firstChild.textContent = '编辑检测明细 ';
                        itemForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
            });
        });
        var itemCancel = $('.subform-cancel', itemForm);
        if (itemCancel) {
            itemCancel.addEventListener('click', function () {
                itemForm.reset();
                itemForm.querySelector('[name="row_id"]').value = '';
                this.hidden = true;
                var title = $('.subform-title', itemForm);
                if (title) title.firstChild.textContent = '添加检测明细 ';
            });
        }
        /* 删除明细行 */
        bindRowDelete('.item-del', 'detections/itemDelete');
    }

    function form2Select(form, name, value) {
        var sel = form.querySelector('select[name="' + name + '"]');
        if (!sel) return;
        var v = value === null || value === undefined ? '' : String(value);
        $all('option', sel).forEach(function (o) { o.selected = (o.value === v); });
        // 通知选择器组件等监听者同步显示
        sel.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /* ---------- URL 助手（与后端 url() 保持一致） ---------- */
    function url(path) {
        var script = window.location.pathname;
        var base = script.replace(/\/index\.php.*$/, '') + '/';
        if (/index\.php$/.test(script)) {
            return base + 'index.php?r=' + path; // query 模式
        }
        // path 模式：script 形如 /xx/index.php
        return base + 'index.php/' + path;
    }

    /* ---------- 日期输入框：统一中文占位 ---------- */
    $all('input[type="date"]').forEach(function (input) {
        if (input.closest('.date-field')) return;
        var wrap = document.createElement('span');
        wrap.className = 'date-field';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var hint = document.createElement('span');
        hint.className = 'date-field-hint';
        hint.setAttribute('aria-hidden', 'true');
        hint.textContent = '请选择日期';
        wrap.appendChild(hint);

        var sync = function () {
            var empty = !input.value;
            input.classList.toggle('date-empty', empty);
            wrap.classList.toggle('has-value', !empty);
        };
        input.addEventListener('input', sync);
        input.addEventListener('change', sync);
        input.addEventListener('focus', sync);
        input.addEventListener('blur', sync);
        sync();
    });
    /* 表单重置后同步占位状态（日期 + 下拉选择器组件） */
    document.addEventListener('reset', function (e) {
        setTimeout(function () {
            $all('input[type="date"]', e.target).forEach(function (input) {
                input.dispatchEvent(new Event('input'));
            });
            $all('select', e.target).forEach(function (sel) {
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }, 0);
    });

    /* ---------- 表单防重复提交 ---------- */
    $all('form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            // 已被拦截（如 onsubmit="return confirm(...)" 点了取消）则不锁按钮
            if (e.defaultPrevented) return;
            var btn = form.querySelector('button[type="submit"]');
            if (btn) setTimeout(function () { btn.disabled = true; }, 0);
        });
    });
    // 浏览器「后退」从缓存恢复页面时，解除被锁的提交按钮
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        $all('form button[type="submit"][disabled]').forEach(function (b) { b.disabled = false; });
    });

    /* ---------- 删除成功提示（刷新后显示，避免提示丢失） ---------- */
    function stashNotice(msg) {
        try { sessionStorage.setItem('ohas_notice', msg); } catch (err) { /* 隐私模式忽略 */ }
    }
    (function showStashedNotice() {
        var msg = null;
        try {
            msg = sessionStorage.getItem('ohas_notice');
            sessionStorage.removeItem('ohas_notice');
        } catch (err) { return; }
        if (!msg) return;
        var host = $('.page-body .container-xl');
        if (!host) return;
        var box = document.createElement('div');
        box.className = 'alert alert-success alert-dismissible';
        box.setAttribute('role', 'alert');
        var icon = document.createElement('i');
        icon.className = 'ti ti-circle-check alert-icon';
        var text = document.createElement('div');
        text.textContent = msg;   // textContent：避免把服务端文案当 HTML
        var close = document.createElement('a');
        close.className = 'btn-close';
        close.setAttribute('data-bs-dismiss', 'alert');
        close.setAttribute('aria-label', '关闭');
        box.appendChild(icon); box.appendChild(text); box.appendChild(close);
        var header = $('.page-header', host);
        if (header && header.nextSibling) host.insertBefore(box, header.nextSibling);
        else host.insertBefore(box, host.firstChild);
    })();
})();
