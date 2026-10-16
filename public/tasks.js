$(document).ready(function () {
    // Same cache as the report page, so an edit here shows up in the picker without a refetch.
    const CACHE_KEY = 'dr-wepro-tasks-v1';
    const STATUS = {
        new: { label: 'Mới', cls: 'st-new' },
        doing: { label: 'Đang làm', cls: 'st-doing' },
        on_hold: { label: 'Tạm hoãn', cls: 'st-hold' },
        completed: { label: 'Xong', cls: 'st-done' }
    };
    const SWAL_CLASS = {
        popup: 'dr-swal', title: 'dr-swal-title', htmlContainer: 'dr-swal-html',
        actions: 'dr-modal-actions', confirmButton: 'dr-modal-confirm', cancelButton: 'dr-modal-cancel',
        validationMessage: 'dr-modal-validation'
    };

    let tasks = [];
    let byId = new Map();
    let activeId = 0;
    // Pending edits per task id: { heading, en_name, description, task_percent, status, parent_id }.
    const dirty = new Map();

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
    }
    function csrf() { return $('input[name="csrf_token"]').val(); }
    function project() { return $('#tkProject').val() || ''; }
    function ajax(url, data) {
        return $.ajax({ url, method: 'POST', dataType: 'json', data: $.extend({ csrf_token: csrf(), project: project() }, data) });
    }
    function ajaxError(xhr) {
        if (xhr && xhr.status === 504) return 'WePRO trả dữ liệu quá lâu nên bị timeout (504). Thử lại.';
        return xhr && xhr.status ? 'Lỗi HTTP ' + xhr.status + '.' : 'Không gọi được server.';
    }
    function toast(icon, title) {
        Swal.fire({ icon, title, toast: true, position: 'top-end', showConfirmButton: false, timer: 2200, timerProgressBar: true });
    }

    function cacheRead() { try { return JSON.parse(localStorage.getItem(CACHE_KEY) || '{}') || {}; } catch (e) { return {}; } }
    function cacheGet() { const e = cacheRead()[project()]; return e && Array.isArray(e.tasks) ? e : null; }
    function cacheSet(list) {
        // Descriptions are loaded lazily and are not part of the shared list.
        const clean = list.map(t => { const c = $.extend({}, t); delete c.description_loaded; return c; });
        try { const all = cacheRead(); all[project()] = { tasks: clean, fetchedAt: new Date().toISOString() }; localStorage.setItem(CACHE_KEY, JSON.stringify(all)); } catch (e) { /* ignore */ }
    }
    function cacheClear() { try { const all = cacheRead(); delete all[project()]; localStorage.setItem(CACHE_KEY, JSON.stringify(all)); } catch (e) { /* ignore */ } }

    function step5(v) { return Math.min(100, Math.max(0, Math.round(Number(v) / 5) * 5)); }
    function byCode(a, b) { return String(a.short_code).localeCompare(String(b.short_code), undefined, { numeric: true }); }
    function parentLabel(id) {
        if (!id) return '— task gốc —';
        const p = byId.get(id);
        return p ? (p.short_code + '  ' + current(p, 'heading')) : '#' + id;
    }
    // Current value of a field = pending edit if any, else what WePRO has.
    function original(t, f) {
        return { heading: t.title, en_name: t.title_en || '', description: t.description_loaded, task_percent: step5(t.percent), status: t.status || '', parent_id: t.parent_id || 0 }[f];
    }
    function current(t, f) {
        const e = dirty.get(t.id);
        return e && e[f] !== undefined ? e[f] : original(t, f);
    }

    // ----- stats strip: counts double as status filters -----
    function renderStats() {
        const n = { all: tasks.length, doing: 0, new: 0, on_hold: 0, completed: 0 };
        tasks.forEach(t => { const s = current(t, 'status'); if (n[s] !== undefined) n[s]++; });
        Object.keys(n).forEach(k => $('#tkStats [data-status="' + k + '"] b').text(n[k]));
    }

    // ----- list -----
    const FIELD_LABEL = { heading: 'tên', en_name: 'tên EN', description: 'mô tả', task_percent: 'tiến độ', status: 'trạng thái', parent_id: 'task cha' };
    function dirtyChips(id) {
        const e = dirty.get(id);
        if (!e) return '';
        return '<span class="tk-chips">' + Object.keys(e).filter(k => FIELD_LABEL[k]).map(k => '<i>' + FIELD_LABEL[k] + '</i>').join('') + '</span>';
    }
    // Deadline warnings: red when past due, amber when due today; completed or on-hold tasks are never warned.
    const TODAY = (function () { const d = new Date(); return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-'); })();
    function dueState(t, status) {
        if (!t.due_date || status === 'completed' || status === 'on_hold') return '';
        if (t.due_date < TODAY) return 'late';
        if (t.due_date === TODAY) return 'today';
        return '';
    }
    function dueHtml(t, status) {
        const st = dueState(t, status);
        if (!st) return escapeHtml(t.due_date || '—');
        return '<b class="tk-due ' + st + '" title="' + (st === 'late' ? 'Quá hạn' : 'Hạn hôm nay') + '">' + escapeHtml(t.due_date) + '<i>!</i></b>';
    }
    function rowHtml(t, isChild, childCount) {
        const status = current(t, 'status');
        const st = STATUS[status] || { label: status || '—', cls: '' };
        const pct = step5(current(t, 'task_percent'));
        const due = dueState(t, status);
        return '<div class="tk-row' + (t.id === activeId ? ' is-active' : '') + (dirty.has(t.id) ? ' is-dirty' : '') + (due ? ' due-' + due : '') + '" data-id="' + t.id + '" data-status="' + escapeHtml(status) + '">'
            + '<input type="checkbox" class="tk-check" title="Tick để thao tác hàng loạt">'
            + '<span class="tk-code">'
            + (childCount ? '<button type="button" class="tk-fold" title="Gập / mở task con">▾</button>' : '')
            + escapeHtml(t.short_code)
            + (childCount ? '<span class="tk-kids">' + childCount + '</span>' : '')
            + '</span>'
            + '<span class="tk-title"><b contenteditable="true" spellcheck="false" title="Sửa tên ngay tại đây, Enter để xong">' + escapeHtml(current(t, 'heading')) + '</b><i class="tk-dot"></i>' + (current(t, 'en_name') ? '<span>' + escapeHtml(current(t, 'en_name')) + '</span>' : '')
            + '<span class="tk-meta-row">' + (t.start_date || t.due_date ? '<em title="Bắt đầu → hạn"><u>ngày</u>' + escapeHtml(t.start_date || '—') + ' → ' + dueHtml(t, status) + '</em>' : '')
            + (t.estimate_hours !== undefined ? '<em title="Ước lượng / đã log"><u>est</u>' + (Number(t.estimate_hours) || 0) + 'h' + (Number(t.logged_hours) ? ' · log ' + Number(t.logged_hours) + 'h' : '') + '</em>' : '') + '</span>'
            + dirtyChips(t.id) + '</span>'
            + '<span class="tk-pct"><span class="tk-bar' + (pct >= 100 ? ' done' : '') + '"><i style="width:' + pct + '%"></i></span><output>' + pct + '%</output></span>'
            + '<span class="tk-st ' + st.cls + '">' + escapeHtml(st.label) + '</span>'
            + '<button type="button" class="tk-clock" title="Logtime cho task này"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></button>'
            + '</div>';
    }
    function render() {
        byId = new Map(tasks.map(t => [t.id, t]));
        const children = new Map();
        tasks.forEach(t => {
            const pid = current(t, 'parent_id');
            if (pid && byId.has(pid)) {
                if (!children.has(pid)) children.set(pid, []);
                children.get(pid).push(t);
            }
        });
        const roots = tasks.filter(t => !(current(t, 'parent_id') && byId.has(current(t, 'parent_id')))).sort(byCode);
        const parents = roots.filter(t => children.has(t.id));
        const others = roots.filter(t => !children.has(t.id));
        let html = '';
        if (parents.length) {
            html += '<div class="tk-section">Task cha <span>' + parents.length + ' nhóm</span><button type="button" class="tk-btn sm ghost" id="tkFoldAll">Thu gọn hết</button></div>';
            parents.forEach(p => {
                const kids = children.get(p.id).sort(byCode);
                html += '<div class="tk-group" data-id="' + p.id + '">' + rowHtml(p, false, kids.length) + '<div class="tk-children">' + kids.map(c => rowHtml(c, true, 0)).join('') + '</div></div>';
            });
        }
        if (others.length) {
            html += '<div class="tk-section zone-loose">Task lẻ <span>' + others.length + ' task · không có task con</span></div><div class="tk-loose">';
            others.forEach(t => { html += rowHtml(t, false, 0); });
            html += '</div>';
        }
        if (!tasks.length) html = '<div class="tk-empty">Project này chưa có task nào đang mở.</div>';
        $('#tkList').html(html);
        renderStats();
        syncDirtyUi();
        syncBulk();
        applyFilter();
    }
    // Refresh one row in place after an edit; a parent change needs the full tree again.
    function refreshRow(id) {
        const t = byId.get(id);
        const $old = $('#tkList .tk-row[data-id="' + id + '"]');
        if (!t || !$old.length) return;
        const isChild = $old.closest('.tk-children').length > 0;
        const $new = $(rowHtml(t, isChild, Number($old.find('.tk-kids').text()) || 0));
        $new.find('.tk-check').prop('checked', $old.find('.tk-check').prop('checked'));
        $new.toggleClass('hidden', $old.hasClass('hidden')).toggleClass('is-saved', $old.hasClass('is-saved')).toggleClass('is-bad', $old.hasClass('is-bad'));
        $old.replaceWith($new);
        renderStats();
    }

    function load(force) {
        const cached = force ? null : cacheGet();
        if (cached) {
            tasks = cached.tasks;
            $('#tkNote').text(tasks.length + ' task · cache ' + new Date(cached.fetchedAt).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }));
            render();
            return;
        }
        $('#tkNote').text('Đang tải từ WePRO...');
        $('#tkList').html('<div class="tk-empty">Đang tải task từ WePRO, khoảng 10 giây...</div>');
        ajax('wepro-tasks.php', {}).done(function (res) {
            if (!res || !res.success) { $('#tkNote').text('Lỗi'); $('#tkList').html('<div class="tk-empty">' + escapeHtml((res && res.message) || 'Không lấy được task.') + '</div>'); return; }
            tasks = res.tasks || [];
            cacheSet(tasks);
            $('#tkNote').text(tasks.length + ' task · vừa tải');
            render();
        }).fail(function (xhr) { $('#tkNote').text('Lỗi'); $('#tkList').html('<div class="tk-empty">' + escapeHtml(ajaxError(xhr)) + '</div>'); });
    }

    // ----- filter: a matching parent shows its children, a matching child shows its parent -----
    // "Hôm nay" = today falls in start→due (or due is today); "Tuần này" = start→due overlaps Mon→Sun.
    function weekRange() {
        const d = new Date(); const day = (d.getDay() + 6) % 7;
        const mon = new Date(d); mon.setDate(d.getDate() - day);
        const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
        const f = x => [x.getFullYear(), String(x.getMonth() + 1).padStart(2, '0'), String(x.getDate()).padStart(2, '0')].join('-');
        return [f(mon), f(sun)];
    }
    function inWhen(t, when) {
        if (when === 'all') return true;
        const start = t.start_date || t.due_date; const due = t.due_date || t.start_date;
        if (!start) return false;
        const [a, b] = when === 'today' ? [TODAY, TODAY] : weekRange();
        return start <= b && due >= a;
    }
    function applyFilter() {
        const needle = ($('#tkSearch').val() || '').trim().toLowerCase();
        const status = $('#tkStats .is-active').data('status') || 'all';
        const when = $('#tkWhen .is-active').data('when') || 'all';
        const hit = $row => {
            if ($row.find('.tk-check').prop('checked') || $row.hasClass('is-dirty') || Number($row.data('id')) === activeId) return true;
            const text = ($row.find('.tk-code').text() + ' ' + $row.find('.tk-title').text()).toLowerCase();
            const t = byId.get(Number($row.data('id')));
            return !(needle !== '' && text.indexOf(needle) === -1) && !(status !== 'all' && $row.data('status') !== status) && (!t || inWhen(t, when));
        };
        $('#tkList .tk-loose .tk-row').each(function () { $(this).toggleClass('hidden', !hit($(this))); });
        $('#tkList .tk-group').each(function () {
            const $g = $(this);
            const $p = $g.children('.tk-row');
            const pHit = hit($p);
            let anyKid = false;
            $g.find('.tk-children .tk-row').each(function () { const h = pHit || hit($(this)); $(this).toggleClass('hidden', !h); if (h) anyKid = true; });
            $g.toggleClass('hidden', !pHit && !anyKid);
            if (needle !== '' && (pHit || anyKid)) $g.removeClass('is-folded');
        });
        $('#tkList .tk-section').each(function () {
            const $s = $(this);
            $s.toggleClass('hidden', !$s.nextUntil('.tk-section').find('.tk-row, .tk-group').addBack().filter('.tk-row, .tk-group').not('.hidden').length);
        });
    }
    $('#tkSearch').on('input', applyFilter);
    $('#tkWhen').on('click', 'button', function () { $('#tkWhen button').removeClass('is-active'); $(this).addClass('is-active'); applyFilter(); });
    $('#tkStats').on('click', '.tk-stat', function () { $('#tkStats .tk-stat').removeClass('is-active'); $(this).addClass('is-active'); applyFilter(); });
    $('#tkList').on('click', '.tk-fold', function (e) { e.stopPropagation(); $(this).closest('.tk-group').toggleClass('is-folded'); });
    $('#tkList').on('click', '#tkFoldAll', function (e) {
        e.stopPropagation();
        const fold = $(this).text() === 'Thu gọn hết';
        $('#tkList .tk-group').toggleClass('is-folded', fold);
        $(this).text(fold ? 'Mở rộng hết' : 'Thu gọn hết');
    });
    function confirmLose(what) {
        if (!dirty.size) return $.Deferred().resolve(true).promise();
        return Swal.fire({ icon: 'warning', title: 'Có ' + dirty.size + ' task chưa lưu', text: what + ' sẽ mất các sửa đó.', showCancelButton: true, confirmButtonText: 'Bỏ sửa', cancelButtonText: 'Ở lại', buttonsStyling: false, customClass: $.extend({}, SWAL_CLASS, { confirmButton: 'dr-modal-confirm tk-danger-confirm' }) }).then(r => r.isConfirmed);
    }
    $('#tkRefresh').on('click', function () { confirmLose('Tải lại').then(ok => { if (ok) { dirty.clear(); select(0); cacheClear(); load(true); } }); });
    $('#tkProject').on('change', function () { confirmLose('Đổi project').then(ok => { if (ok) { dirty.clear(); select(0); load(false); } }); });

    // ----- selection & editor -----
    function select(id) {
        activeId = id;
        $('#tkList .tk-row').removeClass('is-active').filter('[data-id="' + id + '"]').addClass('is-active');
        const t = byId.get(id);
        $('#edEmpty').toggleClass('hidden', !!t);
        $('#edBody, #edFoot').toggleClass('hidden', !t);
        $('#tkMain').toggleClass('show-aside', !!t);
        $('#edBack').toggleClass('hidden', !t);
        if (!t) { $('#edCode').text(''); return; }
        $('#edCode').text(t.short_code);
        $('#edVn').val(current(t, 'heading'));
        $('#edEn').val(current(t, 'en_name'));
        setPct(current(t, 'task_percent'), false);
        paintStatus(current(t, 'status'));
        $('#edParent').val(current(t, 'parent_id') ? parentLabel(current(t, 'parent_id')) : '');
        $('#edParentList').addClass('hidden');
        $('#edInfo').html(
            '<div><span>Ước lượng</span><b>' + (Number(t.estimate_hours) || 0) + 'h</b></div>'
            + '<div><span>Đã log</span><b>' + (Number(t.logged_hours) || 0) + 'h' + (Number(t.estimate_hours) ? ' <small>/ ' + Math.round((Number(t.logged_hours) || 0) / Number(t.estimate_hours) * 100) + '%</small>' : '') + '</b></div>'
            + '<div><span>Hạn</span><b>' + dueHtml(t, current(t, 'status')) + '</b></div>'
        );
        $('#edOpen').attr('href', t.url);

        $('#edMsg').addClass('hidden');
        if (t.description_loaded === undefined) {
            $('#edDesc').val('').prop('disabled', true);
            $('#edDescHint').text('đang tải...');
            ajax('wepro-task-create.php', { action: 'detail', task_id: id }).done(function (res) {
                if (activeId !== id) return;
                if (!res || !res.success) { $('#edDescHint').text(''); $('#edMsg').removeClass('hidden').text((res && res.message) || 'Không lấy được mô tả.'); return; }
                t.description_loaded = res.description || '';
                $('#edDesc').val(current(t, 'description')).prop('disabled', false);
                $('#edDescHint').text('');
                paintChanged(t);
            }).fail(function (xhr) { if (activeId === id) { $('#edDescHint').text(''); $('#edMsg').removeClass('hidden').text(ajaxError(xhr)); } });
        } else {
            $('#edDesc').val(current(t, 'description')).prop('disabled', false);
            $('#edDescHint').text('');
        }
        paintChanged(t);
        const row = $('#tkList .tk-row[data-id="' + id + '"]')[0];
        if (row) row.scrollIntoView({ block: 'nearest' });
    }
    function setPct(v, fire) {
        v = step5(v);
        $('#edRange').val(v); $('#edPct').val(v);
        if (fire) edit('task_percent', v);
    }
    function paintStatus(v) {
        $('.tk-stpick button').removeClass('is-active').filter('[data-v="' + v + '"]').addClass('is-active');
    }
    function paintChanged(t) {
        const e = dirty.get(t.id) || {};
        $('#edBody .tk-field').each(function () { $(this).toggleClass('is-changed', e[$(this).data('f')] !== undefined); });
        $('#edRevert, #edPreview').toggleClass('hidden', !dirty.has(t.id));
    }
    $('#tkList').on('click', '.tk-row', function (e) {
        if ($(e.target).is('input, button, select, [contenteditable]')) return;
        select(Number($(this).data('id')));
    });
    $('#edBack').on('click', function () { $('#tkMain').removeClass('show-aside'); });

    function editOn(id, f, value) {
        const t = byId.get(id);
        if (!t) return;
        const edits = dirty.get(t.id) || {};
        const orig = original(t, f);
        const same = (f === 'task_percent' || f === 'parent_id') ? Number(value) === Number(orig) : String(value) === String(orig);
        if (same) delete edits[f]; else edits[f] = value;
        if (Object.keys(edits).length) dirty.set(t.id, edits); else dirty.delete(t.id);
        $('#tkList .tk-row[data-id="' + t.id + '"]').toggleClass('is-dirty', dirty.has(t.id)).removeClass('is-saved is-bad');
        if (t.id === activeId) paintChanged(t);
        syncDirtyUi();
        if (f !== 'parent_id') refreshRow(t.id);
    }
    function edit(f, value) { editOn(activeId, f, value); }
    $('#edVn').on('input', function () { edit('heading', this.value); });
    $('#edEn').on('input', function () { edit('en_name', this.value); });
    $('#edDesc').on('input', function () { edit('description', this.value); });
    $('#edRange').on('input change', function () { setPct(this.value, true); });
    $('#edPct').on('change', function () { setPct(this.value, true); });
    $('.tk-field[data-f="task_percent"] .tk-ticks button').on('click', function () { setPct($(this).data('v'), true); });
    const AI_PCT = [[0, '0%', 'Chỉ thủ công'], [20, '1~20%', 'Một chút'], [40, '21~40%', 'Một phần'], [80, '41~80%', 'Phần lớn'], [100, '>80%', 'Gần như toàn bộ']];
    const AI_PURPOSE = [['Ideas/Planning', 'Lên ý tưởng / kế hoạch'], ['Coding', 'Viết code'], ['Translation', 'Dịch thuật'], ['Writing/Document', 'Viết / soạn tài liệu'], ['Testing', 'Kiểm thử'], ['Communication', 'Giao tiếp / email'], ['Analysis/Research', 'Phân tích / nghiên cứu'], ['Review', 'Review'], ['Other', 'Khác']];
    // WePRO refuses "completed" without this survey, so it is asked here and sent along with the save.
    function askAiUsage(titleNote, prev) {
        prev = prev || {};
        return Swal.fire({
            title: 'Hoàn thành task',
            html: '<div class="tk-ai">'
                + '<p class="tk-ai-note">' + escapeHtml(titleNote) + '</p>'
                + '<div class="tk-ai-label">AI đã giúp bạn bao nhiêu phần trăm? <b>*</b></div>'
                + '<div class="tk-ai-pct">' + AI_PCT.map(p => '<button type="button" data-v="' + p[0] + '"' + (String(prev.percentage) === String(p[0]) ? ' class="is-active"' : '') + '><b>' + p[1] + '</b><span>' + p[2] + '</span></button>').join('') + '</div>'
                + '<div class="tk-ai-label">AI đã hỗ trợ trong những phần nào? <b>*</b></div>'
                + '<div class="tk-ai-purpose">' + AI_PURPOSE.map(p => '<button type="button" data-v="' + escapeHtml(p[0]) + '"' + ((prev.purposes || []).indexOf(p[0]) !== -1 ? ' class="is-active"' : '') + '>' + escapeHtml(p[1]) + '</button>').join('') + '</div>'
                + '<div class="tk-ai-label">Ghi chú thêm <small id="aiCount">0/200</small></div>'
                + '<textarea id="aiNotes" maxlength="200" rows="3" placeholder="Ví dụ: dùng AI sinh test case">' + escapeHtml(prev.notes || '') + '</textarea>'
                + '</div>',
            buttonsStyling: false, customClass: $.extend({}, SWAL_CLASS, { popup: 'dr-swal dr-swal-wide' }), showCancelButton: true, confirmButtonText: 'Hoàn thành', cancelButtonText: 'Hủy',
            didOpen: function () {
                $(document).on('click.ai', '.tk-ai-pct button', function () { $('.tk-ai-pct button').removeClass('is-active'); $(this).addClass('is-active'); });
                $(document).on('click.ai', '.tk-ai-purpose button', function () { $(this).toggleClass('is-active'); });
                $(document).on('input.ai', '#aiNotes', function () { $('#aiCount').text(this.value.length + '/200'); });
                $('#aiCount').text(($('#aiNotes').val() || '').length + '/200');
            },
            willClose: function () { $(document).off('.ai'); },
            preConfirm: function () {
                const pct = $('.tk-ai-pct button.is-active').data('v');
                const purposes = $('.tk-ai-purpose button.is-active').map(function () { return $(this).data('v'); }).get();
                if (pct === undefined) { Swal.showValidationMessage('Chọn mức % AI đã giúp.'); return false; }
                if (!purposes.length) { Swal.showValidationMessage('Chọn ít nhất một phần AI đã hỗ trợ.'); return false; }
                return { percentage: Number(pct), purposes, notes: ($('#aiNotes').val() || '').trim() };
            }
        }).then(r => (r.isConfirmed ? r.value : null));
    }
    function setStatusOn(ids, v) {
        if (v !== 'completed') {
            ids.forEach(id => { editOn(id, 'status', v); const e = dirty.get(id); if (e) delete e.ai; });
            return $.Deferred().resolve(true).promise();
        }
        const first = byId.get(ids[0]);
        const note = ids.length === 1 ? first.short_code + '  ' + current(first, 'heading') : ids.length + ' task sẽ dùng chung câu trả lời này.';
        const prev = ids.length === 1 ? (dirty.get(ids[0]) || {}).ai : null;
        return askAiUsage(note, prev).then(function (ai) {
            if (!ai) return false;
            ids.forEach(id => { editOn(id, 'status', 'completed'); const e = dirty.get(id); if (e) e.ai = ai; });
            return true;
        });
    }
    $('.tk-stpick button').on('click', function () {
        const v = $(this).data('v');
        const t = byId.get(activeId);
        if (!t) return;
        setStatusOn([t.id], v).then(function (ok) { if (ok) paintStatus(v); else paintStatus(current(t, 'status')); paintChanged(t); });
    });
    // Parent combobox: type to filter root tasks, click or Enter to pick, × for none.
    function parentCandidates(q) {
        q = String(q || '').toLowerCase();
        return tasks.filter(t => t.id !== activeId && !current(t, 'parent_id') && (!q || (t.short_code + ' ' + current(t, 'heading')).toLowerCase().indexOf(q) !== -1)).sort(byCode).slice(0, 40);
    }
    function drawParentList(q) {
        const rows = parentCandidates(q);
        $('#edParentList').removeClass('hidden').html(rows.length
            ? rows.map((t, i) => '<button type="button" data-id="' + t.id + '"' + (i === 0 ? ' class="is-hot"' : '') + '><b>' + escapeHtml(t.short_code) + '</b>' + escapeHtml(current(t, 'heading')) + '</button>').join('')
            : '<div class="tk-combo-empty">Không có task cha khớp</div>');
    }
    function chooseParent(pid) {
        edit('parent_id', pid);
        render(); select(activeId);
    }
    $('#edParent').on('focus input', function () { drawParentList(this.value); })
        .on('keydown', function (e) {
            const $items = $('#edParentList button');
            const i = $items.index($items.filter('.is-hot'));
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); e.stopPropagation(); const n = e.key === 'ArrowDown' ? Math.min($items.length - 1, i + 1) : Math.max(0, i - 1); $items.removeClass('is-hot').eq(n).addClass('is-hot'); $items.eq(n)[0] && $items.eq(n)[0].scrollIntoView({ block: 'nearest' }); return; }
            if (e.key === 'Enter') { e.preventDefault(); const $hot = $items.filter('.is-hot'); if ($hot.length) chooseParent(Number($hot.data('id'))); return; }
            if (e.key === 'Escape') { $('#edParentList').addClass('hidden'); select(activeId); }
        })
        .on('blur', function () { setTimeout(function () { $('#edParentList').addClass('hidden'); const t = byId.get(activeId); if (t) $('#edParent').val(current(t, 'parent_id') ? parentLabel(current(t, 'parent_id')) : ''); }, 150); });
    $('#edParentList').on('mousedown', 'button', function (e) { e.preventDefault(); chooseParent(Number($(this).data('id'))); });
    $('#edParentClear').on('click', function () { if (activeId) chooseParent(0); });
    $('#edBody').on('click', '.tk-undo', function () {
        const f = $(this).closest('.tk-field').data('f');
        const t = byId.get(activeId);
        if (!t) return;
        edit(f, original(t, f));
        if (f === 'status') { const e = dirty.get(t.id); if (e) { delete e.ai; if (!Object.keys(e).length) dirty.delete(t.id); } }
        if (f === 'parent_id') render();
        select(activeId);
    });
    $('#edRevert').on('click', function () { revertTask(activeId); });

    // ----- inline edit on the list (same pending-change mechanism, saved with the Save button) -----
    $('#tkList').on('focus', '.tk-title b[contenteditable]', function () { $(this).data('before', $(this).text()); });
    $('#tkList').on('keydown', '.tk-title b[contenteditable]', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
        if (e.key === 'Escape') { e.preventDefault(); $(this).text($(this).data('before')); this.blur(); }
        e.stopPropagation();
    });
    $('#tkList').on('blur', '.tk-title b[contenteditable]', function () {
        const id = Number($(this).closest('.tk-row').data('id'));
        const v = $(this).text().replace(/\s+/g, ' ').trim();
        if (!v) { $(this).text($(this).data('before')); return; }
        editOn(id, 'heading', v);
        if (id === activeId) $('#edVn').val(v);
    });
    $('#tkList').on('click', '.tk-title b[contenteditable]', function (e) { e.stopPropagation(); });
    $('#tkList').on('click', '.tk-pct output', function (e) {
        e.stopPropagation();
        const $out = $(this); const $row = $out.closest('.tk-row'); const id = Number($row.data('id'));
        const $in = $('<input type="number" class="tk-pct-in" min="0" max="100" step="5">').val(step5(current(byId.get(id), 'task_percent')));
        $out.replaceWith($in); $in.trigger('focus').trigger('select');
        let done = false;
        const commit = function (apply) {
            if (done) return; done = true;
            if (apply) { editOn(id, 'task_percent', step5($in.val())); if (id === activeId) setPct(step5($in.val()), false); }
            else refreshRow(id);
        };
        $in.on('keydown', function (ev) { ev.stopPropagation(); if (ev.key === 'Enter') commit(true); if (ev.key === 'Escape') commit(false); })
           .on('blur', function () { commit(true); })
           .on('click', function (ev) { ev.stopPropagation(); });
    });
    $('#tkList').on('click', '.tk-st', function (e) {
        e.stopPropagation();
        const $badge = $(this); const $row = $badge.closest('.tk-row'); const id = Number($row.data('id'));
        if ($badge.is('select')) return;
        const cur = current(byId.get(id), 'status');
        const $sel = $('<select class="tk-st-in"></select>').html(Object.keys(STATUS).map(k => '<option value="' + k + '"' + (k === cur ? ' selected' : '') + '>' + STATUS[k].label + '</option>').join(''));
        $badge.replaceWith($sel); $sel.trigger('focus');
        $sel.on('click mousedown', function (ev) { ev.stopPropagation(); })
            .on('change', function () {
                const v = this.value;
                setStatusOn([id], v).then(function (ok) { if (!ok) refreshRow(id); else if (id === activeId) { paintStatus(v); paintChanged(byId.get(id)); } });
            })
            .on('blur', function () { if ($sel.closest('body').length && !Swal.isVisible()) refreshRow(id); })
            .on('keydown', function (ev) { ev.stopPropagation(); if (ev.key === 'Escape') refreshRow(id); });
    });

    // ----- parent picker -----
    function pickParent(excludeIds) {
        // Two levels only: a task that already has a parent cannot be one.
        const entries = tasks.filter(t => !current(t, 'parent_id') && excludeIds.indexOf(t.id) === -1).sort(byCode);
        return Swal.fire({
            title: 'Chọn task cha',
            html: '<div class="dr-wc"><div class="dr-wc-field"><div class="dr-wc-pbox"><input type="text" id="tpSearch" placeholder="Gõ mã hoặc tên task để tìm..." autocomplete="off"><div id="tpList" class="dr-wc-plist"></div></div><input type="hidden" id="tpVal" value=""></div></div>',
            buttonsStyling: false, customClass: SWAL_CLASS, showCancelButton: true, confirmButtonText: 'Chọn', cancelButtonText: 'Hủy',
            didOpen: function () {
                function draw(q) {
                    q = String(q || '').toLowerCase();
                    const rows = entries.filter(e => !q || (e.short_code + ' ' + current(e, 'heading')).toLowerCase().indexOf(q) !== -1).slice(0, 80);
                    let html = '<button type="button" class="dr-wc-popt" data-id="0">— Bỏ task cha (thành task gốc) —</button>';
                    html += rows.map(e => '<button type="button" class="dr-wc-popt" data-id="' + e.id + '"><b>' + escapeHtml(e.short_code) + '</b> ' + escapeHtml(current(e, 'heading')) + '</button>').join('');
                    if (!rows.length) html += '<div class="dr-wc-pempty">Không có task khớp</div>';
                    $('#tpList').html(html);
                }
                draw('');
                $(document).on('input.tp', '#tpSearch', function () { draw($(this).val()); });
                $(document).on('click.tp', '#tpList .dr-wc-popt', function () { $('#tpVal').val(String($(this).data('id'))); $('#tpList .dr-wc-popt').removeClass('is-active'); $(this).addClass('is-active'); });
                $(document).on('dblclick.tp', '#tpList .dr-wc-popt', function () { Swal.clickConfirm(); });
                $('#tpSearch').trigger('focus');
            },
            willClose: function () { $(document).off('.tp'); },
            preConfirm: function () { const v = $('#tpVal').val(); if (v === '') { Swal.showValidationMessage('Chọn một task cha.'); return false; } return Number(v); }
        }).then(r => (r.isConfirmed ? r.value : null));
    }

    // ----- bulk -----
    function tickedIds() { return $('#tkList .tk-check:checked').map(function () { return Number($(this).closest('.tk-row').data('id')); }).get(); }
    function syncBulk() {
        const n = tickedIds().length;
        $('#tkBulk').toggleClass('hidden', !n);
        $('#tkBulkCount').text(n + ' task đã tick');
        syncCreate();
    }
    function bulkEdit(f, value) {
        const ids = tickedIds();
        ids.forEach(id => editOn(id, f, value));
        if (f === 'parent_id') render();
        if (activeId) select(activeId);
        toast('success', 'Đã đặt cho ' + ids.length + ' task, bấm Lưu để gửi');
    }
    $('#tkList').on('change', '.tk-check', syncBulk);
    $('#tkCheckAll').on('change', function () { $('#tkList .tk-row').not('.hidden').find('.tk-check').prop('checked', this.checked); syncBulk(); });
    $('#tkBulkNone').on('click', function () { $('#tkList .tk-check').prop('checked', false); $('#tkCheckAll').prop('checked', false); syncBulk(); });
    $('#tkBulkParent').on('click', function () {
        const ids = tickedIds();
        pickParent(ids).then(function (pid) { if (pid !== null) bulkEdit('parent_id', pid); });
    });
    $('#tkBulkPercent').on('change', function () { if (this.value !== '') { bulkEdit('task_percent', step5(this.value)); this.value = ''; } });
    $('#tkBulkStatus').on('change', function () {
        const v = this.value; this.value = '';
        if (v === '') return;
        const ids = tickedIds();
        setStatusOn(ids, v).then(function (ok) { if (!ok) return; if (activeId) select(activeId); toast('success', 'Đã đặt cho ' + ids.length + ' task, bấm Lưu để gửi'); });
    });

    // ----- change preview -----
    function diffHtml(ids) {
        const fmt = (f, v) => {
            if (f === 'parent_id') return parentLabel(Number(v) || 0);
            if (f === 'task_percent') return (Number(v) || 0) + '%';
            if (f === 'status') return (STATUS[v] || {}).label || v || '—';
            return v === '' || v === undefined ? '<i>(trống)</i>' : escapeHtml(v);
        };
        return ids.map(function (id) {
            const t = byId.get(id); const e = dirty.get(id);
            if (!t || !e) return '';
            const rows = Object.keys(e).filter(k => FIELD_LABEL[k]).map(k =>
                '<tr><th>' + FIELD_LABEL[k] + '</th><td class="old">' + fmt(k, original(t, k)) + '</td><td class="arrow">→</td><td class="new">' + fmt(k, e[k]) + '</td></tr>'
                + (k === 'status' && e.ai ? '<tr><th>khảo sát AI</th><td colspan="3" class="new">' + e.ai.percentage + '% · ' + e.ai.purposes.map(escapeHtml).join(', ') + (e.ai.notes ? ' · “' + escapeHtml(e.ai.notes) + '”' : '') + '</td></tr>' : '')
            ).join('');
            return '<div class="tk-diff"><div class="tk-diff-head"><b>' + escapeHtml(t.short_code) + '</b> ' + escapeHtml(t.title) + '</div><table>' + rows + '</table></div>';
        }).join('');
    }
    function previewChanges(ids, confirmSave) {
        if (!ids.length) return;
        return Swal.fire({
            title: ids.length === 1 ? 'Thay đổi của task' : 'Xem lại ' + ids.length + ' task sẽ lưu',
            html: '<div class="tk-diff-wrap">' + diffHtml(ids) + '</div>',
            buttonsStyling: false, customClass: $.extend({}, SWAL_CLASS, { popup: 'dr-swal dr-swal-wide' }),
            showCancelButton: true, confirmButtonText: confirmSave ? 'Lưu lên WePRO' : 'Đóng', cancelButtonText: confirmSave ? 'Quay lại' : 'Hủy sửa task này',
            reverseButtons: !confirmSave
        }).then(function (r) {
            if (confirmSave) { if (r.isConfirmed) save(true); return; }
            if (r.dismiss === Swal.DismissReason.cancel && ids.length === 1) revertTask(ids[0]);
        });
    }
    function revertTask(id) {
        const had = dirty.get(id);
        dirty.delete(id);
        if (had && had.parent_id !== undefined) render(); else refreshRow(id);
        syncDirtyUi();
        if (id === activeId) select(id);
    }
    $('#tkList').on('click', '.tk-chips', function (e) { e.stopPropagation(); previewChanges([Number($(this).closest('.tk-row').data('id'))], false); });
    $('#edPreview').on('click', function () { if (dirty.has(activeId)) previewChanges([activeId], false); });
    $('#tkPreview').on('click', function () { previewChanges(Array.from(dirty.keys()), true); });

    // ----- save / discard -----
    const SAVE_LABEL = 'Lưu lên WePRO <span class="tk-kbd" style="color:#d4d4d8;border-color:#52525b;background:transparent">Ctrl+S</span>';
    function syncDirtyUi() {
        $('#tkSaveBar').toggleClass('hidden', !dirty.size);
        $('#tkDirtyCount').text(dirty.size + ' task có thay đổi');
    }
    $('#tkDiscard').on('click', function () {
        confirmLose('Hủy').then(ok => { if (!ok) return; dirty.clear(); render(); select(activeId); toast('info', 'Đã bỏ mọi thay đổi'); });
    });
    function save(confirmed) {
        if (!dirty.size || $('#tkSave').prop('disabled')) return;
        if (!confirmed) { previewChanges(Array.from(dirty.keys()), true); return; }
        const data = { action: 'update' };
        let i = 0;
        dirty.forEach(function (edits, id) {
            data['tasks[' + i + '][id]'] = id;
            Object.keys(edits).forEach(k => {
                if (k === 'ai') {
                    data['tasks[' + i + '][ai_percentage]'] = edits.ai.percentage;
                    data['tasks[' + i + '][ai_notes]'] = edits.ai.notes;
                    edits.ai.purposes.forEach((pu, j) => { data['tasks[' + i + '][ai_purpose][' + j + ']'] = pu; });
                } else {
                    data['tasks[' + i + '][' + k + ']'] = edits[k];
                }
            });
            i++;
        });
        $('#tkSave').prop('disabled', true).text('Đang lưu...');
        ajax('wepro-task-create.php', data).done(function (res) {
            $('#tkSave').prop('disabled', false).html(SAVE_LABEL);
            const results = (res && res.results) || [];
            if (!results.length) { Swal.fire({ icon: 'error', title: 'Không lưu được', text: (res && res.message) || 'WePRO không phản hồi.', customClass: SWAL_CLASS, buttonsStyling: false }); return; }
            const bad = [];
            let movedParent = false;
            results.forEach(function (r) {
                if (!r.success) { bad.push(r); $('#tkList .tk-row[data-id="' + r.id + '"]').addClass('is-bad'); return; }
                const t = byId.get(r.id);
                const edits = dirty.get(r.id) || {};
                if (edits.heading !== undefined) t.title = edits.heading;
                if (edits.en_name !== undefined) t.title_en = edits.en_name;
                if (edits.description !== undefined) t.description_loaded = edits.description;
                if (edits.task_percent !== undefined) t.percent = Number(edits.task_percent);
                if (edits.status !== undefined) t.status = edits.status;
                if (edits.parent_id !== undefined) { t.parent_id = Number(edits.parent_id) || 0; movedParent = true; }
                dirty.delete(r.id);
            });
            cacheSet(tasks);
            if (movedParent) render(); else results.forEach(r => refreshRow(r.id));
            results.forEach(r => { if (r.success) $('#tkList .tk-row[data-id="' + r.id + '"]').addClass('is-saved'); });
            syncDirtyUi();
            if (activeId) select(activeId);
            if (bad.length) {
                Swal.fire({ icon: 'warning', title: 'Lưu ' + (results.length - bad.length) + '/' + results.length + ' task', html: '<ul style="text-align:left;font-size:13px">' + bad.map(r => '<li>✕ ' + escapeHtml((byId.get(r.id) || {}).short_code || '#' + r.id) + ' — ' + escapeHtml(r.message) + '</li>').join('') + '</ul>', customClass: SWAL_CLASS, buttonsStyling: false });
            } else {
                toast('success', 'Đã lưu ' + results.length + ' task lên WePRO');
            }
        }).fail(function (xhr) {
            $('#tkSave').prop('disabled', false).html(SAVE_LABEL);
            Swal.fire({ icon: 'error', title: 'Không lưu được', text: ajaxError(xhr), customClass: SWAL_CLASS, buttonsStyling: false });
        });
    }
    $('#tkSave').on('click', function () { save(false); });

    // ----- create -----
    const creator = window.WeproCreate({
        createAjax: data => ajax('wepro-task-create.php', data),
        parentList: () => $.Deferred().resolve(tasks).promise(),
        swalClass: SWAL_CLASS,
        ajaxError,
        onCreated: function (ok, v) {
            ok.forEach(function (r) {
                tasks.push({ id: r.id, short_code: 'mới', full_code: '', title: r.heading, title_en: '', percent: 0, start_date: r.start_date || v.start_date, due_date: r.due_date || v.due_date, subtask_count: 0, parent_id: v.parent_id, status: 'new', url: r.url, timelog_url: r.timelog_url });
            });
            cacheSet(tasks);
            render();
            if (activeId) select(activeId);
        }
    });
    // Create is single-parent only: nothing ticked → free; one root ticked → child of it; otherwise off.
    function syncCreate() {
        const ticked = tickedIds();
        const oneRoot = ticked.length === 1 && !current(byId.get(ticked[0]), 'parent_id');
        const off = ticked.length > 1 || (ticked.length === 1 && !oneRoot);
        $('#tkCreate').prop('disabled', off).attr('title', off ? 'Chỉ tạo task khi không tick hoặc tick đúng một task cha' : (oneRoot ? 'Tạo task con dưới task đã tick' : 'Tạo một hoặc nhiều task'))
            .text(oneRoot ? '＋ Tạo task con' : '＋ Tạo task');
    }
    $('#tkCreate').on('click', function () {
        const ticked = tickedIds();
        const act = byId.get(activeId);
        creator.open({ parentId: ticked.length === 1 ? ticked[0] : (act && !current(act, 'parent_id') ? act.id : 0) });
    });

    // ----- logtime -----
    const timelog = window.WeproTimelog({
        ajax: data => ajax('wepro-timelog.php', data),
        project,
        baseUrl: () => { const m = String((tasks[0] || {}).url || '').match(/^(https?:\/\/[^/]+)/); return m ? m[1] : 'https://wepro.rcvn.work'; },
        swalClass: SWAL_CLASS,
        ajaxError,
        onLogged: function (date, rows) {
            rows.forEach(r => { const t = byId.get(r.id); if (t) { t.logged_hours = Math.round(((Number(t.logged_hours) || 0) + r.hours) * 10) / 10; refreshRow(r.id); } });
            cacheSet(tasks);
            if (activeId) select(activeId);
        }
    });
    function logRows(ids) {
        return ids.map(id => byId.get(id)).filter(Boolean).map(t => ({ id: t.id, code: t.short_code, title: current(t, 'heading'), type: 'Coding', memo: current(t, 'heading') }));
    }
    $('#tkList').on('click', '.tk-clock', function (e) { e.stopPropagation(); timelog.open(logRows([Number($(this).closest('.tk-row').data('id'))])); });
    $('#edLog').on('click', function () { if (activeId) timelog.open(logRows([activeId])); });
    $('#tkBulkLog').on('click', function () { const ids = tickedIds(); if (ids.length) timelog.open(logRows(ids), 'Logtime WePRO · ' + ids.length + ' task'); });

    // ----- keyboard -----
    $(document).on('keydown', function (e) {
        if (Swal.isVisible()) return;
        const typing = $(e.target).is('input, textarea, select, [contenteditable]');
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(false); return; }
        if (e.key === '/' && !typing) { e.preventDefault(); $('#tkSearch').trigger('focus'); return; }
        if (e.key === 'Escape' && $(e.target).is('#tkSearch')) { $('#tkSearch').val('').trigger('input').trigger('blur'); return; }
        if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && (!typing || $(e.target).is('#tkSearch'))) {
            const $rows = $('#tkList .tk-row').filter(function () {
                const $g = $(this).closest('.tk-group');
                return !$(this).hasClass('hidden') && !$g.hasClass('hidden') && !($(this).closest('.tk-children').length && $g.hasClass('is-folded'));
            });
            if (!$rows.length) return;
            e.preventDefault();
            let i = $rows.index($rows.filter('.is-active'));
            i = e.key === 'ArrowDown' ? Math.min($rows.length - 1, i + 1) : Math.max(0, i - 1);
            select(Number($rows.eq(i).data('id')));
        }
    });
    // ----- resizable editor width -----
    const SPLIT_KEY = 'dr-tasks-aside-width';
    const $main = $('#tkMain');
    function applyAside(w) { $main.css('--aside', Math.round(w) + 'px'); }
    try { const saved = Number(localStorage.getItem(SPLIT_KEY)); if (saved >= 300) applyAside(saved); } catch (e) { /* ignore */ }
    $('#tkSplit').on('mousedown', function (e) {
        e.preventDefault();
        const startX = e.clientX;
        const startW = $('.tk-aside').outerWidth();
        const maxW = Math.max(360, $main.width() - 480);
        $(this).addClass('is-dragging'); $('body').addClass('is-resizing');
        $(document).on('mousemove.split', function (ev) {
            applyAside(Math.min(maxW, Math.max(300, startW - (ev.clientX - startX))));
        }).on('mouseup.split', function () {
            $(document).off('.split');
            $('#tkSplit').removeClass('is-dragging'); $('body').removeClass('is-resizing');
            try { localStorage.setItem(SPLIT_KEY, String($('.tk-aside').outerWidth())); } catch (err) { /* ignore */ }
        });
    }).on('dblclick', function () {
        $main.css('--aside', '');
        try { localStorage.removeItem(SPLIT_KEY); } catch (err) { /* ignore */ }
    });

    window.addEventListener('beforeunload', function (e) { if (dirty.size) { e.preventDefault(); e.returnValue = ''; } });

    load(false);
});
