// "Logtime WePRO" modal, same form as on the report page.
// env: { ajax(data) → jqXHR to wepro-timelog.php, project() → name, baseUrl() → WePRO origin,
//        swalClass, ajaxError(xhr), onLogged(date, rows) }
window.WeproTimelog = function (env) {
    const LOGGED_KEY = 'dr-wepro-logged-v2';
    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
    }
    function formatHours(h) { return String(Math.round(h * 100) / 100).replace('.', ',') + 'h'; }
    function loggedRead() { try { return JSON.parse(localStorage.getItem(LOGGED_KEY) || '[]') || []; } catch (e) { return []; } }
    function loggedHas(date, taskId) { return loggedRead().some(e => e.d === date && e.t === taskId); }
    function loggedAdd(date, rows) {
        try {
            const all = loggedRead();
            rows.forEach(r => { all.push({ d: date, p: env.project(), t: r.id, h: r.hours }); });
            localStorage.setItem(LOGGED_KEY, JSON.stringify(all.slice(-1000)));
        } catch (e) { /* ignore */ }
    }
    function timelogsUrl() { return env.baseUrl() + '/account/timelogs'; }

    function activityOptions(activities, type) {
        const wanted = String(type || 'Coding').toLowerCase().replace(/[\s_-]/g, '');
        const selected = activities.find(a => a.label.toLowerCase().replace(/[\s_-]/g, '') === wanted)
            || activities.find(a => a.label === 'Coding') || activities[0];
        return activities.map(a => '<option value="' + escapeHtml(a.value) + '"' + (a === selected ? ' selected' : '') + '>' + escapeHtml(a.label) + '</option>').join('');
    }
    function rowHtml(row, activities, withCheck) {
        return '<div class="dr-wlog-row" data-id="' + row.id + '">'
            + (withCheck ? '<input type="checkbox" class="dr-wlog-check" checked>' : '')
            + '<div class="dr-wlog-body">'
            + '<div class="dr-wlog-code">' + escapeHtml(row.code) + (row.title ? ' · ' + escapeHtml(row.title) : '') + '</div>'
            + '<div class="dr-wlog-fields">'
            + '<select class="dr-wlog-activity">' + activityOptions(activities, row.type) + '</select>'
            + '<input type="text" class="dr-wlog-memo" value="' + escapeHtml(row.memo || '') + '" placeholder="Ghi chú">'
            + '<input type="number" class="dr-wlog-hours" min="0.5" max="12" step="0.5" placeholder="Giờ">'
            + '</div></div></div>';
    }
    function collect($scope) {
        return $scope.find('.dr-wlog-row').filter(function () {
            const $c = $(this).find('.dr-wlog-check');
            return !$c.length || $c.prop('checked');
        }).map(function () {
            return { id: Number($(this).data('id')), activity: $(this).find('.dr-wlog-activity').val(), memo: $(this).find('.dr-wlog-memo').val(), hours: Number($(this).find('.dr-wlog-hours').val()) };
        }).get();
    }
    function updateTotal() {
        const total = collect($('#weproLogList')).reduce((sum, r) => sum + (r.hours > 0 ? r.hours : 0), 0);
        $('#weproLogTotal').text('Tổng: ' + formatHours(total));
        const $btn = $(Swal.getConfirmButton());
        if ($btn.length) $btn.text(total > 0 ? 'Log ' + formatHours(total) + ' lên WePRO' : 'Log lên WePRO');
    }

    // rows: [{ id, code, title, type, memo }]
    function open(rows, title) {
        const date = new Date().toISOString().slice(0, 10);
        const bulk = rows.length > 1;
        Swal.fire({
            title: title || (bulk ? 'Logtime WePRO hàng loạt' : 'Logtime WePRO · ' + rows[0].code),
            html: '<p class="dr-modal-note" id="weproLogNote">Đang lấy form từ WePRO...</p>'
                + '<div class="dr-wlog-date"><label>Ngày log</label><input type="date" id="weproLogDate" class="dr-wepro-search" value="' + escapeHtml(date) + '"></div>'
                + '<div id="weproLogList" class="dr-wepro-list"></div>'
                + '<p class="dr-wlog-total" id="weproLogTotal"></p>',
            buttonsStyling: false, customClass: env.swalClass, showCancelButton: true,
            confirmButtonText: 'Log lên WePRO', cancelButtonText: 'Hủy',
            didOpen: function () {
                Swal.getConfirmButton().disabled = true;
                $(document).on('input.wlog change.wlog', '#weproLogList .dr-wlog-hours, #weproLogList .dr-wlog-check', updateTotal);
                env.ajax({ action: 'form', 'task_ids[]': rows[0].id }).done(function (res) {
                    if (!res || !res.success) { $('#weproLogNote').text((res && res.message) || 'Không lấy được form WePRO.'); return; }
                    $('#weproLogNote').text(bulk ? 'Tick task cần log, điền activity / ghi chú / giờ.' : 'Điền activity, ghi chú và số giờ.');
                    $('#weproLogList').html(rows.map(r => rowHtml(r, res.activities, bulk)).join(''));
                    $('#weproLogList .dr-wlog-hours').first().trigger('focus');
                    updateTotal();
                    Swal.getConfirmButton().disabled = false;
                }).fail(function (xhr) { $('#weproLogNote').text(env.ajaxError(xhr)); });
            },
            willClose: function () { $(document).off('.wlog'); },
            preConfirm: function () {
                const logDate = $('#weproLogDate').val();
                const picked = collect($('#weproLogList'));
                if (!logDate) { Swal.showValidationMessage('Chọn ngày log.'); return false; }
                if (!picked.length) { Swal.showValidationMessage('Chọn ít nhất 1 task.'); return false; }
                const missing = picked.find(r => !(r.hours > 0));
                if (missing) { Swal.showValidationMessage('Task ' + missing.id + ' chưa có số giờ.'); return false; }
                return { date: logDate, rows: picked };
            }
        }).then(function (result) {
            if (result.isConfirmed) submit(result.value.date, result.value.rows);
        });
    }

    async function submit(date, rows) {
        const dup = rows.filter(r => loggedHas(date, r.id));
        if (dup.length) {
            const ask = await Swal.fire({
                icon: 'warning', title: 'Đã log trước đó',
                html: '<p class="dr-modal-note">' + dup.length + ' task đã được log ngày <b>' + escapeHtml(date) + '</b> từ app này. Kiểm tra tại <a href="' + escapeHtml(timelogsUrl()) + '" target="_blank" rel="noopener noreferrer">WePRO → Timelogs</a> trước khi log thêm.</p>',
                buttonsStyling: false, customClass: env.swalClass, showCancelButton: true, confirmButtonText: 'Vẫn log', cancelButtonText: 'Hủy'
            });
            if (!ask.isConfirmed) return;
        }
        Swal.fire({ title: 'Đang log lên WePRO...', allowOutsideClick: false, showConfirmButton: false, customClass: env.swalClass, didOpen: () => Swal.showLoading() });
        const data = { action: 'store', date: date };
        rows.forEach(function (r, i) {
            data['task_ids[' + i + ']'] = r.id; data['activities[' + i + ']'] = r.activity; data['memos[' + i + ']'] = r.memo; data['hours[' + i + ']'] = r.hours;
        });
        env.ajax(data).done(function (res) {
            if (!res || !res.success) { Swal.fire({ icon: 'error', title: 'Log thất bại', text: (res && res.message) || 'WePRO không phản hồi.', buttonsStyling: false, customClass: env.swalClass }); return; }
            loggedAdd(date, rows);
            if (env.onLogged) env.onLogged(date, rows);
            Swal.fire({ icon: 'success', title: 'Đã log ' + rows.length + ' task', html: '<p class="dr-modal-note">' + escapeHtml(res.message) + ' <a href="' + escapeHtml(timelogsUrl()) + '" target="_blank" rel="noopener noreferrer">Xem trên WePRO</a></p>', buttonsStyling: false, customClass: env.swalClass });
        }).fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'Log thất bại', text: env.ajaxError(xhr), buttonsStyling: false, customClass: env.swalClass });
        });
    }

    return { open };
};
