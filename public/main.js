$(document).ready(function () {
    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function projectSettingHtml(name, webhook, avatar) {
        return `<div class="project-setting rounded-md border border-zinc-200 bg-zinc-50 p-2" data-project="${escapeHtml(name)}">
            <div class="mb-1 grid grid-cols-[1fr_auto] gap-2">
                <label class="block">
                    <span class="mb-1 block text-[11px] font-medium text-zinc-600">Tên project</span>
                    <input name="projects[name][]" class="project-name h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="VD: Internal" value="${escapeHtml(name)}">
                </label>
                <button type="button" class="remove-project mt-5 h-8 rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-500 hover:border-red-200 hover:bg-red-50 hover:text-red-600">Xóa</button>
            </div>
            <label class="mb-1 block">
                <span class="mb-1 block text-[11px] font-medium text-zinc-600">Webhook Google Chat</span>
                <input name="projects[webhook][]" class="project-webhook h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="https://chat.googleapis.com/..." value="${escapeHtml(webhook)}">
            </label>
            <label class="block">
                <span class="mb-1 block text-[11px] font-medium text-zinc-600">Logo/avatar URL</span>
                <input name="projects[avatar][]" class="project-avatar h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="https://..." value="${escapeHtml(avatar)}">
            </label>
        </div>`;
    }

    function collectProjects() {
        const projects = [];
        $('#projectSettingsList .project-setting').each(function () {
            const name = $(this).find('.project-name').val().trim();
            if (!name) return;
            projects.push({
                name,
                webhook: $(this).find('.project-webhook').val().trim(),
                avatar: $(this).find('.project-avatar').val().trim()
            });
        });
        return projects;
    }

    function syncProjectSelects() {
        const projects = collectProjects();
        const currentProject = $('#project').val();
        const currentDefault = $('#defaultProjectSelect').val();
        const options = projects.map(function (project) {
            return `<option value="${escapeHtml(project.name)}">${escapeHtml(project.name)}</option>`;
        }).join('');

        $('#project, #defaultProjectSelect').html(options);
        $('#project').val(projects.some(p => p.name === currentProject) ? currentProject : (projects[0]?.name || ''));
        $('#defaultProjectSelect').val(projects.some(p => p.name === currentDefault) ? currentDefault : $('#project').val());

        const logos = {};
        projects.forEach(function (project) {
            logos[project.name] = project.avatar;
        });
        $('#project').data('logos', logos);
        updateProjectLogo();
    }

    function updateProjectLogo() {
        const project = $('#project').val() || '';
        const logos = $('#project').data('logos') || {};
        const logo = logos[project] || '';
        $('#projectLogoFallback').text(project.slice(0, 2).toUpperCase());
        if (logo) {
            $('#projectLogo').attr('src', logo).removeClass('hidden');
            $('#projectLogoFallback').addClass('hidden');
        } else {
            $('#projectLogo').attr('src', '').addClass('hidden');
            $('#projectLogoFallback').removeClass('hidden');
        }
    }

    const initialLogos = $('#project').attr('data-logos');
    if (initialLogos) {
        try {
            $('#project').data('logos', JSON.parse(initialLogos));
        } catch (e) {
            $('#project').data('logos', {});
        }
    }

    $('#addProject').click(function () {
        $('#projectSettingsList').append(projectSettingHtml('', '', ''));
        $('#projectSettingsList .project-setting').last().find('.project-name').focus();
    });

    $(document).on('click', '.remove-project', function () {
        if ($('#projectSettingsList .project-setting').length <= 1) {
            Swal.fire({ icon: 'warning', title: 'Cần ít nhất 1 project', toast: true, position: 'top-end', showConfirmButton: false, timer: 1800 });
            return;
        }
        $(this).closest('.project-setting').remove();
        syncProjectSelects();
    });

    $(document).on('input', '.project-name, .project-avatar', syncProjectSelects);
    $('#project').on('change', updateProjectLogo);
    updateProjectLogo();

    // ===== TASK HÔM NAY =====
    function taskTodayHtml(idx) {
        let options = '';
        for (let i = 0; i <= 100; i += 10) {
            options += `<option value="${i}">${i}%</option>`;
        }

        return `<div class="task-today-item animate-slide-up" data-idx="${idx}" style="animation: fadeIn 0.3s ease-out both;">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input type="text" name="tasks_today[${idx}][content]" class="h-9 min-w-0 flex-1 rounded-md border border-zinc-200 bg-white px-3 text-sm text-zinc-950 shadow-sm placeholder:text-zinc-400 transition-colors focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="Nội dung task..." required/>
                <div class="flex w-full gap-2 sm:w-auto">
                    <select name="tasks_today[${idx}][progress]" class="h-9 w-24 flex-shrink-0 cursor-pointer rounded-md border border-zinc-200 bg-white px-2 text-sm text-zinc-950 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" required>
                        <option value="">--%</option>${options}
                    </select>
                    <input type="date" name="tasks_today[${idx}][estimate]" class="h-9 flex-1 cursor-pointer rounded-md border border-zinc-200 bg-white px-3 text-sm text-zinc-950 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 sm:w-40" />
                    <button type="button" class="remove-task flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md border border-zinc-200 bg-white text-zinc-400 shadow-sm transition-colors hover:border-red-200 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" title="Xóa">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>
        </div>`;
    }

    // ===== TASK NGÀY MAI =====
    function taskTomorrowHtml(idx) {
        return `<div class="task-tomorrow-item animate-slide-up" data-idx="${idx}" style="animation: fadeIn 0.3s ease-out both;">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input type="text" name="tasks_tomorrow[${idx}][content]" class="h-9 min-w-0 flex-1 rounded-md border border-zinc-200 bg-white px-3 text-sm text-zinc-950 shadow-sm placeholder:text-zinc-400 transition-colors focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="Nội dung task..." />
                <div class="flex w-full gap-2 sm:w-auto">
                    <span class="flex h-9 flex-shrink-0 items-center rounded-md border border-zinc-200 bg-zinc-50 px-3 text-xs text-zinc-500">Không tiến độ</span>
                    <button type="button" class="remove-task flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md border border-zinc-200 bg-white text-zinc-400 shadow-sm transition-colors hover:border-red-200 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" title="Xóa">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>
        </div>`;
    }

    // ===== INIT =====
    let taskTodayIdx = 0, taskTomorrowIdx = 0;
    $('#tasks-today-list').append(taskTodayHtml(taskTodayIdx++));
    $('#add-task-today').click(function () {
        const html = taskTodayHtml(taskTodayIdx++);
        $('#tasks-today-list').append(html);
        const $lastInput = $('#tasks-today-list .task-today-item').last().find('input[type="text"]');
        $lastInput.focus();
    });
    $('#add-task-tomorrow').click(function () {
        const html = taskTomorrowHtml(taskTomorrowIdx++);
        $('#tasks-tomorrow-list').append(html);
        const $lastInput = $('#tasks-tomorrow-list .task-tomorrow-item').last().find('input[type="text"]');
        $lastInput.focus();
    });

    // ===== REMOVE TASK =====
    $(document).on('click', '.remove-task', function () {
        const $item = $(this).closest('.task-today-item, .task-tomorrow-item');
        const $parent = $item.closest('#tasks-today-list, #tasks-tomorrow-list');
        $item.fadeOut(200, function () {
            $(this).remove();
            if ($parent.children().length === 0) {
                if ($parent.attr('id') === 'tasks-today-list') {
                    $parent.append(taskTodayHtml(taskTodayIdx++));
                } else {
                    $parent.append(taskTomorrowHtml(taskTomorrowIdx++));
                }
            }
        });
    });

    // ===== Chất lượng =====
    $('#quality-list .quality-btn').click(function () {
        const val = $(this).data('value');
        const colors = {
            1: { bg: '#fff1f2', border: '#fecdd3', text: '#be123c' },
            2: { bg: '#fff7ed', border: '#fed7aa', text: '#c2410c' },
            3: { bg: '#fafafa', border: '#18181b', text: '#18181b' },
            4: { bg: '#f0fdf4', border: '#bbf7d0', text: '#15803d' },
            5: { bg: '#fffbeb', border: '#facc15', text: '#854d0e' }
        };
        const c = colors[val];
        $('#quality-list .quality-btn').css({
            'background-color': '#fafafa',
            'border-color': '#e4e4e7',
            'color': '#52525b'
        });
        $(this).css({
            'background-color': c.bg,
            'border-color': c.border,
            'color': c.text
        });
        $('#quality').val(val);
    });

    // ===== Tinh thần =====
    $('#spirit-list .react-emoji').click(function () {
        const val = $(this).data('value');
        const colors = {
            1: { bg: '#fff1f2', border: '#fb7185' },
            2: { bg: '#fff7ed', border: '#fb923c' },
            3: { bg: '#fafafa', border: '#18181b' },
            4: { bg: '#f0fdf4', border: '#4ade80' },
            5: { bg: '#fffbeb', border: '#facc15' }
        };
        const c = colors[val];
        $('#spirit-list .react-emoji').css({
            'background-color': '#fafafa',
            'border-color': '#e4e4e7'
        });
        $(this).css({
            'background-color': c.bg,
            'border-color': c.border
        });
        $('#spirit').val(val);
    });

    // ===== Đặt mặc định =====
    const defaultQuality = $('#quality-list .quality-btn[data-default="true"]');
    defaultQuality.addClass('selected').css({
        'background-color': '#fafafa',
        'border-color': '#18181b',
        'color': '#18181b'
    });
    $('#quality').val(defaultQuality.data('value'));

    const defaultSpirit = $('#spirit-list .react-emoji[data-value="3"]');
    defaultSpirit.addClass('selected').css({
        'background-color': '#fafafa',
        'border-color': '#18181b'
    });
    $('#spirit').val(3);

    // ===== SUBMIT FORM =====
    $('#dailyReportForm').submit(function (e) {
        e.preventDefault();

        if ($('#tasks-today-list .task-today-item').length === 0) {
            Swal.fire({ icon: 'warning', title: 'Cảnh báo', text: 'Cần ít nhất 1 task hôm nay!', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            return;
        }

        if (!$('#spirit').val()) {
            Swal.fire({ icon: 'warning', title: 'Vui lòng chọn mức tinh thần!', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            return;
        }
        if (!$('#quality').val()) {
            Swal.fire({ icon: 'warning', title: 'Vui lòng chọn chất lượng!', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            return;
        }

        $('#submitBtn').prop('disabled', true).addClass('opacity-70 cursor-not-allowed');
        $('#loadingIcon').removeClass('hidden');
        $('#submitText').text('Đang gửi...');

        $.ajax({
            url: 'send-webhook.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                $('#submitBtn').prop('disabled', false).removeClass('opacity-70 cursor-not-allowed');
                $('#loadingIcon').addClass('hidden');
                $('#submitText').text('Gửi báo cáo');
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Thành công!',
                        text: 'Báo cáo đã được gửi!',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi!',
                        text: res.message || 'Có lỗi xảy ra!',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    });
                }
            },
            error: function (xhr, status, error) {
                $('#submitBtn').prop('disabled', false).removeClass('opacity-70 cursor-not-allowed');
                $('#loadingIcon').addClass('hidden');
                $('#submitText').text('Gửi báo cáo');
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi!',
                    text: 'Không thể gửi báo cáo. Vui lòng thử lại!',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });
                console.error('Error:', xhr, status, error);
            }
        });
    });

    // ===== SAVE SETTINGS =====
    $('#settingsForm').submit(function (e) {
        e.preventDefault();
        syncProjectSelects();

        $('#saveSettingsBtn').prop('disabled', true).addClass('opacity-70 cursor-not-allowed').text('Đang lưu...');

        $.ajax({
            url: 'save-config.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                $('#saveSettingsBtn').prop('disabled', false).removeClass('opacity-70 cursor-not-allowed').text('Lưu thiết lập');
                if (res.success) {
                    syncProjectSelects();
                    Swal.fire({ icon: 'success', title: 'Đã lưu thiết lập', toast: true, position: 'top-end', showConfirmButton: false, timer: 1800 });
                } else {
                    Swal.fire({ icon: 'error', title: 'Không lưu được', text: res.message || 'Có lỗi xảy ra', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                }
            },
            error: function () {
                $('#saveSettingsBtn').prop('disabled', false).removeClass('opacity-70 cursor-not-allowed').text('Lưu thiết lập');
                Swal.fire({ icon: 'error', title: 'Không lưu được', text: 'Vui lòng kiểm tra quyền ghi file config', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            }
        });
    });

});
