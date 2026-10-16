// "Tạo task WePRO" modal, one or many tasks under an optional parent.
// env: { createAjax(data) → jqXHR, parentList() → promise of cached tasks, onCreated(okResults, form),
//        swalClass, ajaxError(xhr) }
window.WeproCreate = function (env) {
    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
    }
    function dateToValue(date) {
        return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
    }
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(() => true, () => copyViaTextarea(text));
        }
        return $.Deferred().resolve(copyViaTextarea(text)).promise();
    }
    function copyViaTextarea(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none;';
        document.body.appendChild(ta);
        ta.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        document.body.removeChild(ta);
        return ok;
    }

    // Flat list of root tasks for the searchable parent picker: WePRO nests one level only.
    function parentEntries(tasks) {
        const byCode = (a, b) => String(a.short_code).localeCompare(String(b.short_code), undefined, { numeric: true });
        const children = new Map();
        tasks.filter(t => t.parent_id).forEach(t => children.set(t.parent_id, (children.get(t.parent_id) || 0) + 1));
        return tasks.filter(t => !t.parent_id).sort(byCode).map(p => ({ id: p.id, code: p.short_code, title: p.title, children: children.get(p.id) || 0 }));
    }

    // Status / sprint options arrive with the WePRO form; rows rendered before that get filled in later.
    let formOpts = { statuses: [], sprints: [] };
    function statusOptions(selected) {
        const has = selected !== undefined && selected !== null && selected !== '';
        return formOpts.statuses.map(o => '<option value="' + escapeHtml(o.value) + '"' + (has ? (String(o.value) === String(selected) ? ' selected' : '') : (/Coding/i.test(o.label) ? ' selected' : '')) + '>' + escapeHtml(o.label) + '</option>').join('');
    }
    function sprintOptions(selected) {
        return '<option value="">— Sprint —</option>' + formOpts.sprints.map(o => '<option value="' + escapeHtml(o.value) + '"' + (String(o.value) === String(selected || '') ? ' selected' : '') + '>' + escapeHtml(o.label) + '</option>').join('');
    }
    function rowHtml(item) {
        item = item || {};
        const today = dateToValue(new Date());
        return '<div class="dr-wc-row">'
            + '<div class="dr-wc-row-l1">'
            + '<input type="text" class="dr-wc-row-vn" placeholder="Tên task (VN) *" value="' + escapeHtml(item.heading || '') + '">'
            + '<input type="text" class="dr-wc-row-en" placeholder="Tên EN" value="' + escapeHtml(item.en_name || '') + '">'
            + '<span class="dr-wc-row-btns"><button type="button" class="dr-wc-row-desc-toggle" title="Mô tả task">✎</button><button type="button" class="dr-wc-row-del" title="Xóa dòng">×</button></span>'
            + '</div>'
            + '<div class="dr-wc-row-l2">'
            + '<label><span>Point</span><input type="number" class="dr-wc-row-pt" min="0" step="1" value="' + (item.storypoint || 1) + '"></label>'
            + '<label><span>Est (h)</span><input type="number" class="dr-wc-row-est" min="0" step="0.5" value="' + (item.estimate_hours || 0) + '"></label>'
            + '<label><span>Bắt đầu</span><input type="date" class="dr-wc-row-start" value="' + escapeHtml(item.start_date || today) + '"></label>'
            + '<label><span>Hạn</span><input type="date" class="dr-wc-row-due" value="' + escapeHtml(item.due_date || today) + '"></label>'
            + '<label><span>Trạng thái</span><select class="dr-wc-row-status">' + statusOptions(item.status) + '</select></label>'
            + '<label><span>Sprint</span><select class="dr-wc-row-sprint">' + sprintOptions(item.sprint_id) + '</select></label>'
            + '</div>'
            + '<textarea class="dr-wc-row-desc' + (item.description ? '' : ' hidden') + '" rows="2" placeholder="Mô tả task (tùy chọn)">' + escapeHtml(item.description || '') + '</textarea>'
            + '</div>';
    }

    // Paste format, one task per line. Lines starting with # are comments; a leading "- " or "1. " is tolerated.
    const PASTE_FORMAT = 'Tên VN | Tên EN | story point | ngày bắt đầu | ngày hạn | ước lượng giờ | mô tả (tùy chọn)';
    function parsePaste(text) {
        const dateOk = v => /^\d{4}-\d{2}-\d{2}$/.test(v) ? v : '';
        return String(text || '').split('\n').map(l => l.trim()).filter(l => l && !l.startsWith('#')).map(function (line) {
            const parts = line.replace(/^(?:[-*•]|\d+[.)])\s+/, '').split('|').map(x => x.trim());
            return { heading: parts[0] || '', en_name: parts[1] || '', storypoint: Number(parts[2]) > 0 ? Number(parts[2]) : 1, start_date: dateOk(parts[3] || ''), due_date: dateOk(parts[4] || ''), estimate_hours: Number(parts[5]) >= 0 ? Number(parts[5]) || 0 : 0, description: parts.slice(6).join(' | ').trim() };
        }).filter(it => it.heading);
    }
    function aiPrompt() {
        const today = dateToValue(new Date());
        return 'Hãy chia công việc sau thành danh sách task để tôi tạo trên WePRO.\n'
            + 'Xuất ra DUY NHẤT các dòng theo đúng định dạng, không giải thích, không đánh số, không markdown:\n'
            + PASTE_FORMAT + '\n'
            + 'Quy tắc:\n'
            + '- Tên VN: ngắn gọn, có tiền tố loại trong ngoặc vuông, ví dụ [Backoffice] Sửa màn hình đơn hàng.\n'
            + '- Tên EN: dịch sang tiếng Anh cùng tiền tố; để trống nếu không cần.\n'
            + '- story point: số nguyên 1-8 theo độ phức tạp.\n'
            + '- ngày: định dạng YYYY-MM-DD, hôm nay là ' + today + '; các task nối tiếp nhau, bỏ qua thứ 7 và chủ nhật.\n'
            + '- ước lượng giờ: số giờ dự kiến, bước 0.5, ví dụ 1.5.\n'
            + '- mô tả: một câu nêu rõ cần làm gì, có thể để trống.\n'
            + '- Mỗi dòng một task, ngăn cách bằng dấu |, giữ đúng 7 cột kể cả khi để trống.\n'
            + 'Ví dụ:\n'
            + '[Backoffice] Sửa màn hình đơn hàng | [Backoffice] Fix order screen | 2 | ' + today + ' | ' + today + ' | 3 | Sửa lỗi hiển thị tổng tiền khi có giảm giá\n'
            + '[API] Thêm filter theo trạng thái | [API] Add status filter | 1 | ' + today + ' | ' + today + ' | 1.5 |\n\n'
            + 'Công việc cần chia:\n';
    }

    // opts.parentId preselects the parent.
    function open(opts) {
        opts = opts || {};
        const today = dateToValue(new Date());
        let form = null;
        let entries = [];
        function collectItems() {
            if ($('#wcMode').val() === 'bulk') {
                return $('#wcRows .dr-wc-row').map(function () {
                    const heading = $(this).find('.dr-wc-row-vn').val().trim();
                    return heading ? { heading, en_name: $(this).find('.dr-wc-row-en').val().trim(), storypoint: Number($(this).find('.dr-wc-row-pt').val()) || 1, start_date: $(this).find('.dr-wc-row-start').val() || '', due_date: $(this).find('.dr-wc-row-due').val() || '', estimate_hours: Number($(this).find('.dr-wc-row-est').val()) || 0, status: $(this).find('.dr-wc-row-status').val() || '', sprint_id: $(this).find('.dr-wc-row-sprint').val() || '', description: $(this).find('.dr-wc-row-desc').val().trim() } : null;
                }).get();
            }
            const heading = $('#wcHeading').val().trim();
            return heading ? [{ heading, en_name: $('#wcEn').val().trim(), storypoint: Number($('#wcPoint').val()) || 1, estimate_hours: Number($('#wcEstimate').val()) || 0, description: $('#wcDesc').val().trim() }] : [];
        }
        function updateCount() {
            const n = collectItems().length;
            $(Swal.getConfirmButton()).text(n > 1 ? 'Tạo ' + n + ' task' : 'Tạo task');
            $('#wcBulkCount').text(n ? n + ' task sẽ được tạo' : 'Điền tên VN cho từng dòng, dòng trống bị bỏ qua');
        }
        function setParent(id) {
            const e = entries.find(x => x.id === id);
            $('#wcParent').val(e ? e.id : 0);
            $('#wcParentPicked').text(e ? e.code + '  ' + e.title : 'Không có task cha (task gốc)').toggleClass('is-set', !!e);
            $('#wcParentList').prop('hidden', true);
            $('.dr-wc-parent').toggleClass('is-set', !!e);
        }
        function renderParentList(needle) {
            needle = String(needle || '').toLowerCase();
            const rows = entries.filter(e => !needle || (e.code + ' ' + e.title).toLowerCase().indexOf(needle) !== -1).slice(0, 60);
            let html = '<button type="button" class="dr-wc-popt" data-id="0">— Không có task cha (task gốc) —</button>';
            html += rows.map(e => '<button type="button" class="dr-wc-popt" data-id="' + e.id + '"><b>' + escapeHtml(e.code) + '</b> ' + escapeHtml(e.title) + (e.children ? ' <small>· ' + e.children + ' con</small>' : '') + '</button>').join('');
            if (!rows.length) html += '<div class="dr-wc-pempty">Không có task khớp</div>';
            $('#wcParentList').html(html).prop('hidden', false);
        }
        Swal.fire({
            title: 'Tạo task WePRO',
            html: '<p class="dr-modal-note" id="wcNote">Đang lấy form từ WePRO...</p>'
                + '<div id="wcBody" class="dr-wc hidden">'
                + '<div class="dr-wc-tabs"><button type="button" class="dr-chip is-active" data-mode="single">Một task</button><button type="button" class="dr-chip" data-mode="bulk">Nhiều task</button><input type="hidden" id="wcMode" value="single"></div>'
                + '<div id="wcPasteWrap" class="hidden">'
                + '<details class="dr-wc-paste"><summary>📋 Dán danh sách từ AI / bảng</summary>'
                + '<textarea id="wcPaste" rows="5" placeholder="' + escapeHtml(PASTE_FORMAT) + '\n[Backoffice] Sửa màn hình đơn hàng | [Backoffice] Fix order screen | 2 | ' + today + ' | ' + today + '"></textarea>'
                + '<div class="dr-wc-paste-foot"><button type="button" id="wcPasteApply" class="dr-chip">⬇ Đổ vào bảng</button><button type="button" id="wcCopyPrompt" class="dr-chip">✦ Copy prompt cho AI</button><span class="dr-wc-hint" id="wcPasteCount"></span></div>'
                + '</details>'
                + '</div>'
                + '<div class="dr-wc-field dr-wc-parent"><span>⤷ Task cha <small>· các task tạo ra sẽ nằm dưới task này</small></span>'
                + '<button type="button" id="wcParentPicked" class="dr-wc-pbtn">Không có task cha (task gốc)</button>'
                + '<input type="hidden" id="wcParent" value="0">'
                + '<div id="wcParentBox" class="dr-wc-pbox" hidden><input type="text" id="wcParentSearch" placeholder="Gõ mã hoặc tên task để tìm..." autocomplete="off"><div id="wcParentList" class="dr-wc-plist" hidden></div></div>'
                + '</div>'
                + '<div class="dr-wc-pane" data-pane="single">'
                + '<label class="dr-wc-field"><span>Tên task (VN) *</span><input type="text" id="wcHeading" placeholder="VD: [Backoffice] Sửa màn hình đơn hàng"></label>'
                + '<label class="dr-wc-field"><span>Tên EN (tùy chọn)</span><input type="text" id="wcEn" placeholder="VD: [Backoffice] Fix order screen"></label>'
                + '<div class="dr-wc-grid"><label class="dr-wc-field"><span>Story point</span><input type="number" id="wcPoint" min="0" step="1" value="1"></label>'
                + '<label class="dr-wc-field"><span>Ước lượng (giờ)</span><input type="number" id="wcEstimate" min="0" step="0.5" value="0"></label></div>'
                + '<label class="dr-wc-field"><span>Mô tả (tùy chọn)</span><textarea id="wcDesc" rows="3" placeholder="Cần làm gì, lưu ý gì..."></textarea></label>'
                + '</div>'
                + '<div class="dr-wc-pane hidden" data-pane="bulk">'
                + '<div id="wcRows">' + rowHtml() + rowHtml() + '</div>'
                + '<div class="dr-wc-rows-foot"><button type="button" id="wcAddRow" class="dr-chip">＋ Thêm dòng</button><span class="dr-wc-hint" id="wcBulkCount">Điền tên VN cho từng dòng, dòng trống bị bỏ qua</span></div>'
                + '</div>'
                + '<div class="dr-wc-grid" id="wcCommonMeta"><label class="dr-wc-field"><span>Trạng thái</span><select id="wcStatus"></select></label>'
                + '<label class="dr-wc-field"><span>Sprint</span><select id="wcSprint"><option value="">— Không —</option></select></label></div>'
                + '<div class="dr-wc-grid" id="wcCommonDates"><label class="dr-wc-field"><span>Bắt đầu</span><input type="date" id="wcStart" value="' + today + '"></label>'
                + '<label class="dr-wc-field"><span>Hạn</span><input type="date" id="wcDue" value="' + today + '"></label></div>'
                + '</div>',
            buttonsStyling: false,
            customClass: $.extend({}, env.swalClass, { popup: 'dr-swal dr-swal-wide' }),
            showCancelButton: true,
            confirmButtonText: 'Tạo task',
            cancelButtonText: 'Hủy',
            didOpen: function () {
                Swal.getConfirmButton().disabled = true;
                $('.dr-wc-tabs .dr-chip').on('click', function () {
                    const mode = $(this).data('mode');
                    $('.dr-wc-tabs .dr-chip').removeClass('is-active'); $(this).addClass('is-active');
                    $('#wcMode').val(mode);
                    $('.dr-wc-pane').addClass('hidden').filter('[data-pane="' + mode + '"]').removeClass('hidden');
                    $('#wcCommonDates, #wcCommonMeta').toggleClass('hidden', mode === 'bulk');
                    $('#wcPasteWrap').toggleClass('hidden', mode !== 'bulk');
                    updateCount();
                });
                $(document).on('input.wc', '#wcHeading, #wcRows input', updateCount);
                $('#wcAddRow').on('click', function () {
                    $('#wcRows').append(rowHtml());
                    $('#wcRows .dr-wc-row-vn').last().trigger('focus');
                });
                $(document).on('input.wc', '#wcPaste', function () {
                    const n = parsePaste($(this).val()).length;
                    $('#wcPasteCount').text(n ? 'Nhận ra ' + n + ' task' : '');
                });
                $('#wcPasteApply').on('click', function () {
                    const items = parsePaste($('#wcPaste').val());
                    if (!items.length) { $('#wcPasteCount').text('Không nhận ra dòng nào. Mỗi dòng: ' + PASTE_FORMAT); return; }
                    // Replace blank rows, append after filled ones.
                    const hasFilled = $('#wcRows .dr-wc-row-vn').toArray().some(el => $(el).val().trim());
                    if (!hasFilled) $('#wcRows').empty();
                    $('#wcRows').append(items.map(rowHtml).join(''));
                    $('#wcPaste').val('');
                    $('#wcPasteCount').text('Đã đổ ' + items.length + ' task vào bảng');
                    updateCount();
                });
                $('#wcCopyPrompt').on('click', function () {
                    const $btn = $(this);
                    copyToClipboard(aiPrompt()).then(function (ok) {
                        if (ok) {
                            $btn.text('✓ Đã copy'); setTimeout(() => $btn.text('✦ Copy prompt cho AI'), 1800);
                            $('#wcPasteCount').text('Đã copy prompt, dán cho AI rồi dán kết quả vào ô trên.');
                        } else {
                            $('#wcPaste').val(aiPrompt()).trigger('select');
                            $('#wcPasteCount').text('Trình duyệt không cho copy tự động, prompt đã đặt vào ô trên, Ctrl+C để copy.');
                        }
                    });
                });
                $(document).on('click.wc', '.dr-wc-row-desc-toggle', function () {
                    const $ta = $(this).closest('.dr-wc-row').find('.dr-wc-row-desc').toggleClass('hidden');
                    if (!$ta.hasClass('hidden')) $ta.trigger('focus');
                });
                $(document).on('click.wc', '.dr-wc-row-del', function () {
                    if ($('#wcRows .dr-wc-row').length > 1) $(this).closest('.dr-wc-row').remove(); else $(this).closest('.dr-wc-row').find('input[type=text]').val('');
                    updateCount();
                });
                // Enter in a repeater row adds the next one, like a spreadsheet.
                $(document).on('keydown.wc', '#wcRows .dr-wc-row-vn, #wcRows .dr-wc-row-en', function (e) {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    const $row = $(this).closest('.dr-wc-row');
                    if ($row.is(':last-child')) $('#wcAddRow').trigger('click'); else $row.next().find('.dr-wc-row-vn').trigger('focus');
                });
                $('#wcParentPicked').on('click', function () {
                    const open = $('#wcParentBox').prop('hidden');
                    $('#wcParentBox').prop('hidden', !open);
                    if (open) { renderParentList(''); $('#wcParentSearch').val('').trigger('focus'); }
                });
                $(document).on('input.wc', '#wcParentSearch', function () { renderParentList($(this).val()); });
                $(document).on('click.wc', '.dr-wc-popt', function () { setParent(Number($(this).data('id'))); $('#wcParentBox').prop('hidden', true); });
                $.when(env.createAjax({ action: 'form' }), env.parentList()).done(function (formRes, parents) {
                    const res = Array.isArray(formRes) ? formRes[0] : formRes;
                    if (!res || !res.success) { $('#wcNote').text((res && res.message) || 'Không lấy được form WePRO.'); return; }
                    form = res;
                    entries = parentEntries(parents || []);
                    formOpts = { statuses: res.statuses || [], sprints: res.sprints || [] };
                    $('#wcStatus').html(statusOptions());
                    $('#wcSprint').append(res.sprints.map(o => '<option value="' + escapeHtml(o.value) + '">' + escapeHtml(o.label) + '</option>').join(''));
                    $('#wcRows .dr-wc-row-status').each(function () { $(this).html(statusOptions($(this).val())); });
                    $('#wcRows .dr-wc-row-sprint').each(function () { $(this).html(sprintOptions($(this).val())); });
                    if (opts.parentId) setParent(Number(opts.parentId));
                    $('#wcNote').text('Tạo trong project ' + (res.project_label || '') + '. Người làm: bạn.');
                    $('#wcBody').removeClass('hidden');
                    $('#wcHeading').trigger('focus');
                    Swal.getConfirmButton().disabled = false;
                }).fail(function (xhr) { $('#wcNote').text(env.ajaxError(xhr)); });
            },
            willClose: function () { $(document).off('.wc'); },
            preConfirm: function () {
                const items = collectItems();
                if (!items.length) { Swal.showValidationMessage('Nhập ít nhất một tên task.'); return false; }
                if ($('#wcMode').val() === 'bulk') {
                    const bad = items.find(it => !it.start_date || !it.due_date || it.start_date > it.due_date);
                    if (bad) { Swal.showValidationMessage('Dòng "' + bad.heading + '": thiếu ngày hoặc bắt đầu sau hạn.'); return false; }
                } else if (!$('#wcStart').val() || !$('#wcDue').val() || $('#wcStart').val() > $('#wcDue').val()) { Swal.showValidationMessage('Ngày bắt đầu phải nhỏ hơn hoặc bằng hạn.'); return false; }
                return {
                    items,
                    parent_id: Number($('#wcParent').val()) || 0,
                    status: $('#wcStatus').val(),
                    sprint_id: $('#wcSprint').val(),
                    start_date: $('#wcStart').val(),
                    due_date: $('#wcDue').val(),
                    user_id: form ? form.me : 0
                };
            }
        }).then(function (result) {
            if (result.isConfirmed) submit(result.value);
        });
    }

    function submit(v) {
        Swal.fire({ title: 'Đang tạo ' + v.items.length + ' task trên WePRO...', allowOutsideClick: false, showConfirmButton: false, customClass: env.swalClass, didOpen: () => Swal.showLoading() });
        const data = { action: 'store', parent_id: v.parent_id, priority: 'medium', status: v.status, sprint_id: v.sprint_id, start_date: v.start_date, due_date: v.due_date, user_id: v.user_id };
        v.items.forEach(function (it, i) {
            data['tasks[' + i + '][heading]'] = it.heading;
            data['tasks[' + i + '][en_name]'] = it.en_name || '';
            data['tasks[' + i + '][storypoint]'] = it.storypoint || 1;
            data['tasks[' + i + '][estimate_hours]'] = it.estimate_hours || 0;
            if (it.description) data['tasks[' + i + '][description]'] = it.description;
            if (it.status !== undefined && it.status !== '') data['tasks[' + i + '][status]'] = it.status;
            if (it.sprint_id) data['tasks[' + i + '][sprint_id]'] = it.sprint_id;
            if (it.start_date) data['tasks[' + i + '][start_date]'] = it.start_date;
            if (it.due_date) data['tasks[' + i + '][due_date]'] = it.due_date;
        });
        env.createAjax(data)
            .done(function (res) {
                const results = (res && res.results) || [];
                const ok = results.filter(r => r.success);
                const bad = results.filter(r => !r.success);
                if (ok.length) env.onCreated(ok, v);
                const list = ok.map(r => '<li>✓ <a href="' + escapeHtml(r.url) + '" target="_blank" rel="noopener noreferrer">#' + r.id + '</a> ' + escapeHtml(r.heading) + '</li>').join('')
                    + bad.map(r => '<li class="is-bad">✕ ' + escapeHtml(r.heading || '(trống)') + ' — ' + escapeHtml(r.message) + '</li>').join('');
                Swal.fire({
                    icon: bad.length ? (ok.length ? 'warning' : 'error') : 'success',
                    title: ok.length ? 'Đã tạo ' + ok.length + '/' + results.length + ' task' : 'Không tạo được task',
                    html: '<ul class="dr-wc-results">' + list + '</ul>' + (!results.length && res && res.message ? '<p class="dr-modal-note">' + escapeHtml(res.message) + '</p>' : ''),
                    buttonsStyling: false, customClass: env.swalClass
                });
            })
            .fail(function (xhr) {
                Swal.fire({ icon: 'error', title: 'Không tạo được task', text: env.ajaxError(xhr), buttonsStyling: false, customClass: env.swalClass });
            });
    }

    return { open };
};
