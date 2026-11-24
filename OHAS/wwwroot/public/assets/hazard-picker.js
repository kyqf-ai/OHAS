/* ============================================================
   OHAS 危害因素选择器（HazardPicker）
   ------------------------------------------------------------
   针对内置《职业病危害因素分类目录》（459 条）的增强下拉组件：
     · 类别多级筛选（全部 / 粉尘 / 化学因素 / 物理因素 / 放射性 / 生物 / 其他）
     · 关键字即时匹配（名称 / CAS 号 / 可能职业病 / 目录备注，忽略大小写与空格）
     · 键盘导航（↑↓ 移动、Enter 选中、Esc 关闭）、点击外部关闭
     · 多选模式（data-hazard-multi）：条目带勾选框，点击/Enter 切换选中，
       面板保持展开可连续勾选，按钮显示「、」连接的已选名称（表4-1 使用）
   渐进增强：基于页面中原生 <select data-hazard-picker> 构建，
   原生 select 隐藏但保留（名称/值/data-* 均不变；多选为 <select multiple>），
   服务端提交、限值联动（data-limit）、动态行克隆等既有逻辑全部兼容。
   依赖：无（原生 JS）；样式：app.css 中的 .hzpick-* 规则。
   ============================================================ */
(function () {
    'use strict';

    var SEL_ATTR = 'data-hazard-picker';

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    /** 归一化：去空格、转小写（用于宽松匹配，如 "游离sio2" 命中 "游离SiO2"） */
    function norm(s) {
        return String(s == null ? '' : s).replace(/\s+/g, '').toLowerCase();
    }

    /** 类别 → 徽标配色（与危害因素字典页一致） */
    var CAT_COLOR = {
        '粉尘': 'hz-cat-dust',
        '化学因素': 'hz-cat-chem',
        '物理因素': 'hz-cat-phys',
        '放射性因素': 'hz-cat-rad',
        '生物因素': 'hz-cat-bio',
        '其他因素': 'hz-cat-other'
    };

    var registry = [];   // 已增强实例，便于全局互斥展开

    /* ---------------- 实例 ---------------- */
    function Picker(select) {
        this.select = select;
        this.items = [];          // {value, name, cat, disease, limit, note, no, hay}
        this.activeCat = '';      // '' = 全部
        this.keyword = '';
        this.filtered = [];
        this.activeIdx = -1;
        this.openState = false;
        this.multi = select.hasAttribute('data-hazard-multi') || select.multiple;
        this.readOnlyHint = select.getAttribute('data-placeholder') || '';

        this._collect();
        this._build();
    }

    /* 从原生 select 收集数据：optgroup=类别，option 文本=名称，meta 走 data-* */
    Picker.prototype._collect = function () {
        var self = this;
        var placeholder = '';
        this.options = {};        // value -> option（多选勾选状态以此为准）
        $all('option', this.select).forEach(function (opt) {
            var v = opt.value;
            if (v === '') { placeholder = opt.textContent.trim() || placeholder; return; }
            self.options[v] = opt;
            var grp = opt.closest('optgroup');
            self.items.push({
                value: v,
                name: opt.textContent.trim(),
                cat: grp ? grp.getAttribute('label') || '' : '',
                disease: opt.getAttribute('data-disease') || '',
                limit: opt.getAttribute('data-limit') || '',
                note: opt.getAttribute('data-note') || '',
                cas: opt.getAttribute('data-cas') || '',
                no: opt.getAttribute('data-no') || ''
            });
        });
        this.placeholder = placeholder || (this.multi ? '— 可多选 —' : '— 请选择 —');
        this.items.forEach(function (it) {
            it.hay = norm(it.name) + '|' + norm(it.cat) + '|' +
                     norm(it.disease) + '|' + norm(it.limit) + '|' + norm(it.note) + '|' + norm(it.cas);
        });
        // 类别统计（按出现顺序）
        this.cats = [];
        var seen = {};
        this.items.forEach(function (it) {
            if (it.cat && !seen[it.cat]) { seen[it.cat] = true; self.cats.push(it.cat); }
        });
    };

    /* 生成组件 DOM：按钮 + 下拉面板 */
    Picker.prototype._build = function () {
        var self = this;
        var wrap = document.createElement('div');
        wrap.className = 'hzpick';
        this.select.parentNode.insertBefore(wrap, this.select);
        wrap.appendChild(this.select);
        this.select.classList.add('hzpick-select');
        this.wrap = wrap;

        // 触发按钮
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'hzpick-btn' + (this.multi ? ' hzpick-btn-multi' : '');
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.setAttribute('aria-expanded', 'false');
        btn.innerHTML =
            '<span class="hzpick-value"></span>' +
            '<span class="hzpick-caret"><i class="ti ti-chevron-down"></i></span>' +
            '<span class="hzpick-clear" role="button" title="清除全部选择"><i class="ti ti-x"></i></span>';
        wrap.appendChild(btn);
        this.btn = btn;
        this.valueEl = $('.hzpick-value', btn);

        // 下拉面板
        var panel = document.createElement('div');
        panel.className = 'hzpick-panel';
        panel.hidden = true;
        panel.innerHTML =
            '<div class="hzpick-search">' +
              '<span class="hzpick-search-icon"><i class="ti ti-search"></i></span>' +
              '<input type="text" class="hzpick-input" placeholder="输入名称 / CAS 号 / 职业病关键字筛选" autocomplete="off">' +
              '<button type="button" class="hzpick-kwclear" title="清空关键字"><i class="ti ti-x"></i></button>' +
            '</div>' +
            '<div class="hzpick-cats" role="tablist"></div>' +
            '<div class="hzpick-list" role="listbox"></div>' +
            '<div class="hzpick-empty" hidden>没有匹配的危害因素</div>' +
            '<div class="hzpick-foot"><span class="hzpick-count"></span>' +
            (this.multi ? '<span class="hzpick-multi-tip">点击条目勾选 / 取消，可连续多选</span>' : '支持名称 / CAS 号 / 职业病检索') + '</div>';
        wrap.appendChild(panel);
        this.panel = panel;
        this.input = $('.hzpick-input', panel);
        this.kwclear = $('.hzpick-kwclear', panel);
        this.catsEl = $('.hzpick-cats', panel);
        this.listEl = $('.hzpick-list', panel);
        this.emptyEl = $('.hzpick-empty', panel);
        this.countEl = $('.hzpick-count', panel);

        this._renderCats();
        this._sync();

        /* ---- 事件 ---- */
        btn.addEventListener('click', function (e) {
            if (e.target.closest('.hzpick-clear')) {
                self._clearAll();
                return;
            }
            if (self.select.disabled) return;
            self.openState ? self.close() : self.open();
        });
        this.input.addEventListener('input', function () {
            self.keyword = self.input.value.trim();
            self.kwclear.hidden = self.keyword === '';
            self._filter();
        });
        this.input.addEventListener('keydown', function (e) { self._keys(e); });
        this.kwclear.addEventListener('click', function () {
            self.input.value = ''; self.keyword = '';
            self.kwclear.hidden = true;
            self._filter();
            self.input.focus();
        });
        this.listEl.addEventListener('click', function (e) {
            var item = e.target.closest('.hzpick-item');
            if (item) {
                if (self.multi) self._toggle(item.getAttribute('data-value'), item);
                else self._pick(item.getAttribute('data-value'));
            }
        });
        this.listEl.addEventListener('mouseover', function (e) {
            var item = e.target.closest('.hzpick-item');
            if (!item) return;
            var idx = self.filtered.findIndex(function (it) { return it.value === item.getAttribute('data-value'); });
            if (idx >= 0) self._activate(idx, false);
        });
        this.catsEl.addEventListener('click', function (e) {
            var chip = e.target.closest('.hzpick-cat');
            if (!chip) return;
            self.activeCat = chip.getAttribute('data-cat');
            $all('.hzpick-cat', self.catsEl).forEach(function (c) {
                c.classList.toggle('active', c === chip);
            });
            self._filter();
        });
        // 空态内“在全部类别中搜索”：一键重置类别筛选
        this.emptyEl.addEventListener('click', function (e) {
            if (!e.target.closest('.hzpick-empty-reset')) return;
            self.activeCat = '';
            self.keyword = '';
            self.input.value = '';
            self.kwclear.hidden = true;
            $all('.hzpick-cat', self.catsEl).forEach(function (c, i) {
                c.classList.toggle('active', i === 0);
            });
            self._filter();
            self.input.focus();
        });
        // 原生 select 值被外部修改（编辑回填 / 表单 reset / 行克隆清空）→ 同步显示
        this.select.addEventListener('change', function () { self._sync(); });
    };

    Picker.prototype._renderCats = function () {
        var self = this;
        var frag = document.createDocumentFragment();
        var mk = function (cat, label, count) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'hzpick-cat' + (self.activeCat === cat ? ' active' : '');
            b.setAttribute('data-cat', cat);
            b.innerHTML = label + ' <span class="hzpick-cat-n">' + count + '</span>';
            frag.appendChild(b);
        };
        mk('', '全部', this.items.length);
        this.cats.forEach(function (cat) {
            var n = self.items.filter(function (it) { return it.cat === cat; }).length;
            mk(cat, cat, n);
        });
        this.catsEl.innerHTML = '';
        this.catsEl.appendChild(frag);
    };

    /* 过滤 + 渲染列表 */
    Picker.prototype._filter = function () {
        var self = this;
        var kw = norm(this.keyword);
        var kwRaw = this.keyword.toLowerCase();
        this.filtered = this.items.filter(function (it) {
            if (self.activeCat && it.cat !== self.activeCat) return false;
            if (!kw) return true;
            return it.hay.indexOf(kw) !== -1 ||
                   (kwRaw && it.name.toLowerCase().indexOf(kwRaw) !== -1);
        });
        this._renderList();
    };

    Picker.prototype._renderList = function () {
        var self = this;
        var frag = document.createDocumentFragment();
        var kw = norm(this.keyword);

        this.filtered.forEach(function (it) {
            var el = document.createElement('div');
            el.className = 'hzpick-item';
            if (self.multi && self.options[it.value] && self.options[it.value].selected) {
                el.className += ' selected';
                el.setAttribute('aria-selected', 'true');
            }
            el.setAttribute('role', 'option');
            el.setAttribute('data-value', it.value);

            var nameHtml = escapeHtml(it.name);
            if (kw) {
                var plain = it.name.toLowerCase();
                var normName = norm(it.name);
                var pos = normName.indexOf(kw);
                if (pos !== -1) {
                    // 归一化命中：按原始文本区间近似加亮（去空格后逐字符映射）
                    nameHtml = highlightMapped(it.name, normName, pos, kw.length);
                } else if (plain.indexOf(self.keyword.toLowerCase()) !== -1) {
                    nameHtml = highlightPlain(it.name, self.keyword);
                }
            }

            var meta = [];
            if (it.disease) meta.push('<span class="hzpick-dz">' + escapeHtml(it.disease) + '</span>');
            if (it.no) meta.push('<span class="hzpick-no">目录 ' + escapeHtml(it.no) + '</span>');
            if (it.note) meta.push('<span class="hzpick-note">' + escapeHtml(it.note) + '</span>');

            el.innerHTML =
                (self.multi ? '<span class="hzpick-check" aria-hidden="true"><i class="ti ti-check"></i></span>' : '') +
                '<div class="hzpick-item-main">' +
                    '<span class="hzpick-item-name">' + nameHtml + '</span>' +
                    (meta.length ? '<span class="hzpick-item-meta">' + meta.join('') + '</span>' : '') +
                '</div>' +
                '<span class="hzpick-badge ' + (CAT_COLOR[it.cat] || '') + '">' + escapeHtml(it.cat) + '</span>';
            frag.appendChild(el);
        });

        this.listEl.innerHTML = '';
        this.listEl.appendChild(frag);

        // 空态提示：区分“类别内无匹配”与“全局无匹配”，并提供一键重置
        if (this.filtered.length === 0) {
            if (this.activeCat && this.keyword) {
                this.emptyEl.innerHTML = '「' + escapeHtml(this.activeCat) + '」类别下没有匹配“' +
                    escapeHtml(this.keyword) + '”的条目<br>' +
                    '<button type="button" class="hzpick-empty-reset">在全部类别中搜索</button>';
            } else if (this.keyword) {
                this.emptyEl.textContent = '没有匹配“' + this.keyword + '”的危害因素，试试其他关键字';
            } else {
                this.emptyEl.textContent = '该类别下暂无可选项';
            }
        }
        this.emptyEl.hidden = this.filtered.length > 0;
        var countText = '匹配 ' + this.filtered.length + ' / ' + this.items.length + ' 项 · ';
        if (this.multi) {
            countText = '已选 ' + this._selectedValues().length + ' 项 · ' + countText;
        }
        this.countEl.textContent = countText;
        this._activate(this.filtered.length ? 0 : -1, false);
    };

    /* 高亮辅助：归一化文本命中 → 映射回原文位置 */
    function highlightMapped(raw, normText, pos, len) {
        var map = [];      // 原文索引 → 归一化索引
        var n = '';
        for (var i = 0; i < raw.length; i++) {
            if (/\s/.test(raw[i])) continue;
            map.push(i);
            n += raw[i];
        }
        var s = map[pos];
        var e = map[Math.min(pos + len, map.length) - 1];
        if (s === undefined || e === undefined) return escapeHtml(raw);
        return escapeHtml(raw.slice(0, s)) + '<b>' + escapeHtml(raw.slice(s, e + 1)) + '</b>' + escapeHtml(raw.slice(e + 1));
    }
    function highlightPlain(raw, kw) {
        var idx = raw.toLowerCase().indexOf(kw.toLowerCase());
        if (idx === -1) return escapeHtml(raw);
        return escapeHtml(raw.slice(0, idx)) + '<b>' + escapeHtml(raw.slice(idx, idx + kw.length)) + '</b>' + escapeHtml(raw.slice(idx + kw.length));
    }
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    Picker.prototype._activate = function (idx, scroll) {
        this.activeIdx = idx;
        $all('.hzpick-item', this.listEl).forEach(function (el, i) {
            el.classList.toggle('active', i === idx);
            if (i === idx) el.setAttribute('aria-selected', 'true');
            else el.removeAttribute('aria-selected');
        });
        if (scroll && idx >= 0) {
            var el = this.listEl.children[idx];
            if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
        }
    };

    Picker.prototype._keys = function (e) {
        if (!this.openState) return;
        var n = this.filtered.length;
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (n) this._activate((this.activeIdx + 1) % n, true);
                break;
            case 'ArrowUp':
                e.preventDefault();
                if (n) this._activate((this.activeIdx - 1 + n) % n, true);
                break;
            case 'Enter':
                e.preventDefault();
                if (this.activeIdx >= 0 && this.filtered[this.activeIdx]) {
                    if (this.multi) {
                        var v = this.filtered[this.activeIdx].value;
                        var el = this.listEl.querySelector('.hzpick-item[data-value="' + v + '"]');
                        this._toggle(v, el);
                    } else {
                        this._pick(this.filtered[this.activeIdx].value);
                    }
                }
                break;
            case ' ':
                // 多选模式空格键同样切换勾选（与复选习惯一致）
                if (this.multi && this.activeIdx >= 0 && this.filtered[this.activeIdx]) {
                    e.preventDefault();
                    var v2 = this.filtered[this.activeIdx].value;
                    var el2 = this.listEl.querySelector('.hzpick-item[data-value="' + v2 + '"]');
                    this._toggle(v2, el2);
                }
                break;
            case 'Escape':
                e.preventDefault();
                this.close();
                break;
            case 'Tab':
                this.close();
                break;
        }
    };

    Picker.prototype._pick = function (value) {
        this.select.value = value;
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
        this.close();
    };

    /* 多选：切换一条勾选（面板保持展开，可连续勾选） */
    Picker.prototype._toggle = function (value, itemEl) {
        var opt = this.options[value];
        if (!opt) return;
        opt.selected = !opt.selected;
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
        if (itemEl) {
            itemEl.classList.toggle('selected', opt.selected);
            if (opt.selected) itemEl.setAttribute('aria-selected', 'true');
            else itemEl.removeAttribute('aria-selected');
        }
        var sel = this._selectedValues();
        var countText = '已选 ' + sel.length + ' 项 · 匹配 ' + this.filtered.length + ' / ' + this.items.length + ' 项 · ';
        this.countEl.textContent = countText;
        this._sync();
    };

    /* 多选：清除全部勾选 */
    Picker.prototype._clearAll = function () {
        var self = this;
        if (!this.multi) {
            this._pick('');
            return;
        }
        var changed = false;
        Object.keys(this.options).forEach(function (v) {
            if (self.options[v].selected) { self.options[v].selected = false; changed = true; }
        });
        if (changed) this.select.dispatchEvent(new Event('change', { bubbles: true }));
        this._filter();   // 重绘列表勾选态 + 计数
        this._sync();
    };

    /* 多选：当前已选值（保持选项顺序） */
    Picker.prototype._selectedValues = function () {
        var out = [];
        for (var i = 0; i < this.items.length; i++) {
            var it = this.items[i];
            var opt = this.options[it.value];
            if (opt && opt.selected) out.push(it);
        }
        return out;
    };

    Picker.prototype.open = function () {
        var self = this;
        registry.forEach(function (p) { if (p !== self) p.close(); });
        this._filter();
        this.openState = true;
        this.panel.hidden = false;
        this.btn.setAttribute('aria-expanded', 'true');
        this.wrap.classList.add('open');
        // 视口下方放不下时向上展开
        var r = this.btn.getBoundingClientRect();
        var below = window.innerHeight - r.bottom;
        this.panel.classList.toggle('dropup', below < 280 && r.top > 300);
        setTimeout(function () {
            self.input.focus();
            self.input.select();
        }, 0);
    };

    Picker.prototype.close = function () {
        this.openState = false;
        this.panel.hidden = true;
        this.btn.setAttribute('aria-expanded', 'false');
        this.wrap.classList.remove('open');
    };

    /* 依据原生 select 当前值刷新按钮显示 */
    Picker.prototype._sync = function () {
        if (this.multi) {
            var sel = this._selectedValues();
            if (sel.length) {
                var names = sel.map(function (it) { return it.name; });
                this.valueEl.textContent = names.join('、');
                this.valueEl.title = names.join('、');
                this.wrap.classList.add('has-value');
                this.btn.setAttribute('data-cat', sel[0].cat || '');
            } else {
                this.valueEl.textContent = this.readOnlyHint || this.placeholder;
                this.valueEl.title = '';
                this.wrap.classList.remove('has-value');
                this.btn.removeAttribute('data-cat');
            }
            return;
        }
        var v = this.select.value;
        var it = null;
        for (var i = 0; i < this.items.length; i++) {
            if (this.items[i].value === v) { it = this.items[i]; break; }
        }
        if (it) {
            this.valueEl.textContent = it.name;
            this.valueEl.title = it.disease ? it.name + '（' + it.disease + '）' : it.name;
            this.wrap.classList.add('has-value');
            if (it.cat) this.btn.setAttribute('data-cat', it.cat);
            else this.btn.removeAttribute('data-cat');
        } else {
            this.valueEl.textContent = this.readOnlyHint || this.placeholder;
            this.valueEl.title = '';
            this.wrap.classList.remove('has-value');
            this.btn.removeAttribute('data-cat');
        }
    };

    /* ---------------- 对外 API ---------------- */
    window.HazardPicker = {
        /** 扫描 root（默认 document）内未被增强的 select 并构建组件 */
        init: function (root) {
            $all('select[' + SEL_ATTR + ']', root || document).forEach(function (sel) {
                if (sel.closest('.hzpick')) return;   // 已增强
                if (!sel.options.length) return;
                registry.push(new Picker(sel));
            });
        },
        /** 移除 root 内组件 DOM（原生 select 保留，可重新 init） */
        destroy: function (root) {
            $all('.hzpick', root || document).forEach(function (wrap) {
                if (!wrap.parentNode) return;
                var sel = $('select', wrap);
                if (sel) {
                    sel.classList.remove('hzpick-select');
                    wrap.parentNode.insertBefore(sel, wrap);
                }
                wrap.parentNode.removeChild(wrap);
                registry = registry.filter(function (p) { return p.select !== sel; });
            });
        },
        /** 销毁并重建 root 内组件（动态行克隆后调用） */
        rebuild: function (root) {
            this.destroy(root);
            this.init(root);
        }
    };

    /* 点击组件外部：关闭 */
    document.addEventListener('click', function (e) {
        if (!e.target.closest || !e.target.closest('.hzpick')) {
            registry.forEach(function (p) { p.close(); });
        }
    });

    /* 页面加载完成后自动增强 */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { window.HazardPicker.init(); });
    } else {
        window.HazardPicker.init();
    }
})();
