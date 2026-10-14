$(document).ready(function () {
    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
        });
    }
    function toast(icon, title, text) {
        Swal.fire({ icon, title, text, toast: true, position: 'top-end', showConfirmButton: false, timer: icon === 'success' ? 1800 : 3200 });
    }
    function csrf() {
        return $('#settingsForm input[name="csrf_token"]').val();
    }

    // ===== Wizard =====
    const STEP_KEY = 'dr-settings-step';
    function showStep(n) {
        n = String(n);
        $('.st-panel').removeClass('is-active').filter('[data-panel="' + n + '"]').addClass('is-active');
        $('.st-step').removeClass('is-active').filter('[data-step="' + n + '"]').addClass('is-active');
        try { sessionStorage.setItem(STEP_KEY, n); } catch (e) { /* ignore */ }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    $('.st-step').on('click', function () { showStep($(this).data('step')); });
    $('.prev-step').on('click', function () { showStep($(this).data('prev')); });
    $('.next-step').on('click', function () {
        const next = $(this).data('next');
        saveSettings().then(function (ok) { if (ok) showStep(next); });
    });
    const firstRun = $('#settingsForm').data('first-run') === 1;
    let initial = '1';
    try { initial = sessionStorage.getItem(STEP_KEY) || '1'; } catch (e) { /* ignore */ }
    const urlStep = new URLSearchParams(window.location.search).get('step');
    showStep(firstRun ? '1' : (urlStep || initial));

    function markStepDone(n, done) {
        const $step = $('.st-step[data-step="' + n + '"]');
        $step.toggleClass('is-done', done);
        $step.find('.st-step-num').text(done ? '✓' : String(n));
    }

    // ===== Projects =====
    function projectSettingHtml() {
        return `<div class="st-project project-setting" data-project="">
            <button type="button" class="st-project-head toggle-project" aria-expanded="true">
                <span class="project-summary truncate">📁 Project mới</span>
                <span class="project-chevron rotate-180 transition-transform">⌄</span>
            </button>
            <div class="st-project-body project-setting-body">
                <div class="st-grid-2">
                    <div class="st-field"><label class="st-label">Tên project</label><input name="projects[name][]" class="project-name st-input" placeholder="VD: JRR" value=""></div>
                    <div class="st-field"><label class="st-label">WePRO Project ID</label><input name="projects[wepro_project_id][]" class="project-wepro-id st-input" placeholder="VD: 366" value=""><p class="st-help">Số trong link WePRO: /account/projects/<b>366</b></p></div>
                </div>
                <div class="st-field"><label class="st-label">Webhook Google Chat</label><input name="projects[webhook][]" class="project-webhook st-input" placeholder="https://chat.googleapis.com/..." value=""></div>
                <div class="st-field"><label class="st-label">Webhook Slack</label><input name="projects[slack_webhook][]" class="project-slack-webhook st-input" placeholder="https://hooks.slack.com/services/..." value=""></div>
                <div class="st-field"><label class="st-label">Logo (link ảnh)</label><input name="projects[avatar][]" class="project-avatar st-input" placeholder="https://..." value=""></div>
                <button type="button" class="st-btn remove-project" style="height:36px;color:#be123c;">Xóa project này</button>
            </div>
        </div>`;
    }

    $(document).on('click', '.toggle-project', function () {
        const expanded = $(this).attr('aria-expanded') === 'true';
        $(this).attr('aria-expanded', expanded ? 'false' : 'true');
        $(this).siblings('.project-setting-body').toggleClass('hidden', expanded);
        $(this).find('.project-chevron').toggleClass('rotate-180', !expanded);
    });
    $(document).on('input', '.project-name', function () {
        $(this).closest('.project-setting').find('.project-summary').text('📁 ' + ($(this).val().trim() || 'Project mới'));
        syncDefaultSelect();
    });
    $('#addProject').on('click', function () {
        $('#projectSettingsList').append(projectSettingHtml());
        $('#projectSettingsList .project-name').last().trigger('focus');
    });
    $(document).on('click', '.remove-project', async function () {
        if ($('#projectSettingsList .project-setting').length <= 1) {
            toast('warning', 'Cần ít nhất 1 project');
            return;
        }
        const name = $(this).closest('.project-setting').find('.project-name').val().trim() || 'project này';
        const result = await Swal.fire({ icon: 'warning', title: 'Xóa ' + name + '?', showCancelButton: true, confirmButtonText: 'Xóa', cancelButtonText: 'Hủy' });
        if (!result.isConfirmed) return;
        $(this).closest('.project-setting').remove();
        syncDefaultSelect();
    });

    function syncDefaultSelect() {
        const current = $('#defaultProjectSelect').val();
        const names = $('#projectSettingsList .project-name').map(function () { return $(this).val().trim(); }).get().filter(Boolean);
        $('#defaultProjectSelect').html(names.map(n => '<option value="' + escapeHtml(n) + '">' + escapeHtml(n) + '</option>').join(''));
        $('#defaultProjectSelect').val(names.includes(current) ? current : names[0] || '');
    }
    // Open the first project when it is the only one so a new user sees the fields right away.
    if ($('#projectSettingsList .project-setting').length === 1) {
        $('#projectSettingsList .toggle-project').first().trigger('click');
    }

    // ===== Save =====
    function saveSettings() {
        syncDefaultSelect();
        const $buttons = $('#settingsForm .save-step, #settingsForm .next-step').prop('disabled', true);
        return $.ajax({ url: 'save-config.php', method: 'POST', data: $('#settingsForm').serialize(), dataType: 'json' })
            .then(function (res) {
                if (!res.success) {
                    toast('error', 'Không lưu được', res.message || 'Có lỗi xảy ra');
                    return false;
                }
                $('#settingsForm').data('first-run', 0);
                markStepDone(1, $('#reporterSetting').val().trim() !== '');
                markStepDone(2, $('#projectSettingsList .project-name').filter(function () { return $(this).val().trim() !== ''; }).length > 0);
                const weproOn = $('input[name="wepro_enabled"]').prop('checked');
                const cookieTyped = $('textarea[name="wepro_remember_cookie"]').val().trim() !== '';
                const cookieStored = $('textarea[name="wepro_remember_cookie"]').attr('placeholder').indexOf('đã lưu') !== -1;
                markStepDone(3, !weproOn || cookieTyped || cookieStored);
                if (cookieTyped) $('textarea[name="wepro_remember_cookie"]').val('').attr('placeholder', '(đã lưu, để trống nếu giữ nguyên)');
                if ($('input[name="wepro_basic_pass"]').val() !== '') $('input[name="wepro_basic_pass"]').val('').attr('placeholder', '(đã lưu, để trống nếu giữ nguyên)');
                toast('success', 'Đã lưu thiết lập');
                return true;
            }, function () {
                toast('error', 'Không lưu được', 'Kiểm tra quyền ghi file config');
                return false;
            })
            .always(function () { $buttons.prop('disabled', false); });
    }
    $('#settingsForm').on('submit', function (e) {
        e.preventDefault();
        const finish = $(document.activeElement).data('finish') === 1;
        saveSettings().then(function (ok) {
            if (ok && finish) window.location.href = 'index.php';
        });
    });

    // ===== WePRO test =====
    $('#weproTestBtn').on('click', function () {
        const $btn = $(this).prop('disabled', true);
        const $out = $('#weproTestResult').removeClass('hidden ok err').addClass('wait').text('Đang kiểm tra, có thể mất tới 30 giây...');
        const project = $('#defaultProjectSelect').val() || '';
        $.ajax({ url: 'wepro-tasks.php', method: 'POST', dataType: 'json', data: { csrf_token: csrf(), project } })
            .done(function (res) {
                if (res && res.success) {
                    $out.removeClass('wait').addClass('ok').text('Kết nối OK. Đọc được ' + res.tasks.length + '/' + res.total + ' task của project ' + project + '.');
                } else {
                    $out.removeClass('wait').addClass('err').text('Lỗi: ' + ((res && res.message) || 'không rõ'));
                }
            })
            .fail(function (xhr) {
                $out.removeClass('wait').addClass('err').text(xhr.status === 504
                    ? 'WePRO trả dữ liệu quá lâu (504). Thử tắt "Lấy cả task con".'
                    : 'Không gọi được máy chủ (HTTP ' + xhr.status + ').');
            })
            .always(function () { $btn.prop('disabled', false); });
    });

});
