$(document).ready(function () {
    const draftKey = 'daily-report-draft-v1';
    let draftTimer = null;

    function setSettingsOpen(open) {
        const $drawer = $('#settingsDrawer');
        if (open) {
            $drawer.prop('hidden', false);
            requestAnimationFrame(function () { $drawer.addClass('drawer-open'); });
        } else {
            $drawer.removeClass('drawer-open');
            window.setTimeout(function () {
                if (!$drawer.hasClass('drawer-open')) $drawer.prop('hidden', true);
            }, 210);
        }
        $drawer.attr('aria-hidden', open ? 'false' : 'true');
        $('#settingsBackdrop').prop('hidden', !open);
        $('#openSettings').attr('aria-expanded', open ? 'true' : 'false');
        $('body').toggleClass('overflow-hidden', open);
        if (open) $('#closeSettings').trigger('focus');
    }

    $('#openSettings').on('click', function () { setSettingsOpen(true); });
    $('#closeSettings, #settingsBackdrop').on('click', function () { setSettingsOpen(false); });
    $(document).on('keydown', function (event) {
        if (event.key === 'Escape') setSettingsOpen(false);
    });

    $('#toggleGoogleFormSettings').on('click', function () {
        const expanded = $(this).attr('aria-expanded') === 'true';
        $(this).attr('aria-expanded', expanded ? 'false' : 'true');
        $('#googleFormSettingsBody').toggleClass('hidden', expanded);
        $('#googleFormSettingsChevron').toggleClass('rotate-180', !expanded);
    });

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

    function confirmDelete(title, text) {
        return Swal.fire({
            icon: 'warning',
            title,
            text,
            showCancelButton: true,
            focusCancel: true,
            confirmButtonText: 'Xóa',
            cancelButtonText: 'Hủy',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#78716c',
            reverseButtons: true
        });
    }

    // ===== CUSTOM SELECT =====
    function showFloating($element) {
        $element.removeClass('hidden');
        const element = $element[0];
        if (element && typeof element.showPopover === 'function' && !element.matches(':popover-open')) element.showPopover();
    }

    function hideFloating($element) {
        const element = $element[0];
        if (element && typeof element.hidePopover === 'function' && element.matches(':popover-open')) element.hidePopover();
        $element.addClass('hidden');
    }

    function rebuildCustomSelect(select) {
        const $select = $(select);
        const $root = $select.closest('.dr-select');
        if (!$root.length) return;
        const selected = select.options[select.selectedIndex];
        $root.find('.dr-select-label').text(selected ? selected.text : 'Chọn');
        const $menu = ($root.data('floatingMenu') || $root.find('.dr-select-menu')).empty();
        Array.from(select.options).forEach(function (option, index) {
            const $option = $('<button type="button" class="dr-select-option" role="option"></button>')
                .text(option.text)
                .attr('data-index', index)
                .attr('aria-selected', option.selected ? 'true' : 'false')
                .toggleClass('is-selected', option.selected)
                .prop('disabled', option.disabled);
            $menu.append($option);
        });
    }

    function enhanceSelect(select) {
        if (select.dataset.customSelect === 'true' || select.closest('.swal2-container') || select.classList.contains('swal2-select')) return;
        select.dataset.customSelect = 'true';
        const layoutClasses = Array.from(select.classList).filter(function (name) {
            return /(^|:)(w-|min-w-|max-w-|flex-)/.test(name);
        });
        const $root = $('<div class="dr-select"></div>').addClass(layoutClasses.join(' '));
        const $trigger = $('<button type="button" class="dr-select-trigger" aria-haspopup="listbox" aria-expanded="false"><span class="dr-select-label truncate"></span><span class="dr-select-caret" aria-hidden="true">⌄</span></button>');
        const $menu = $('<div class="dr-select-menu hidden" role="listbox" popover="manual"></div>');
        $(select).wrap($root).addClass('dr-select-native').after($trigger, $menu);
        $(select).closest('.dr-select').data('floatingMenu', $menu);
        rebuildCustomSelect(select);
    }

    function closeCustomSelects(except) {
        $('.dr-select.is-open').each(function () {
            if (except && this === except) return;
            const $root = $(this);
            $root.removeClass('is-open');
            hideFloating($root.data('floatingMenu') || $());
            $(this).find('.dr-select-trigger').attr('aria-expanded', 'false');
        });
    }

    $('select').each(function () { enhanceSelect(this); });
    $(document).on('click', '.dr-select-trigger', function (event) {
        event.stopPropagation();
        const $root = $(this).closest('.dr-select');
        const willOpen = !$root.hasClass('is-open');
        closeCustomSelects($root[0]);
        $root.toggleClass('is-open', willOpen);
        const $menu = $root.data('floatingMenu');
        if (willOpen) {
            const rect = this.getBoundingClientRect();
            $menu.appendTo(document.body).css({ left: rect.left, top: rect.bottom + 6, width: rect.width }).data('selectRoot', $root);
            showFloating($menu);
        } else {
            hideFloating($menu);
        }
        $(this).attr('aria-expanded', willOpen ? 'true' : 'false');
    });
    $(document).on('click', '.dr-select-option', function () {
        const $root = $(this).closest('.dr-select').length ? $(this).closest('.dr-select') : $(this).closest('.dr-select-menu').data('selectRoot');
        const select = $root.find('select')[0];
        select.selectedIndex = Number($(this).attr('data-index'));
        $(select).trigger('change');
        rebuildCustomSelect(select);
        closeCustomSelects();
        $root.find('.dr-select-trigger').trigger('focus');
    });
    $(document).on('click', function () { closeCustomSelects(); });
    $(document).on('keydown', '.dr-select-trigger', function (event) {
        const select = $(this).closest('.dr-select').find('select')[0];
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            select.selectedIndex = Math.max(0, Math.min(select.options.length - 1, select.selectedIndex + step));
            $(select).trigger('change');
            rebuildCustomSelect(select);
        }
    });

    function formatDisplayDate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return 'Chọn ngày';
        const parts = value.split('-');
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    function dateToValue(date) {
        return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
    }

    function syncDateControl(input) {
        const $root = $(input).closest('.dr-date');
        if (!$root.length) return;
        $root.find('.dr-date-label').text(formatDisplayDate(input.value)).toggleClass('dr-date-placeholder', !input.value);
        $root.find('.dr-date-trigger').prop('disabled', input.disabled);
    }

    function renderCalendar($root) {
        const input = $root.find('input[type="date"]')[0];
        const $calendar = $root.data('floatingCalendar') || $root.find('.dr-calendar');
        const view = $root.data('viewDate');
        const year = view.getFullYear();
        const month = view.getMonth();
        const firstDay = new Date(year, month, 1);
        const gridStart = new Date(year, month, 1 - firstDay.getDay());
        const todayValue = dateToValue(new Date());
        const disallowPast = String(input.name || '').includes('tasks_today') && String(input.name || '').includes('[estimate]');
        $calendar.find('.dr-calendar-title').text(`Tháng ${month + 1}, ${year}`);
        const $grid = $calendar.find('.dr-calendar-grid').empty();
        ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'].forEach(day => $grid.append($('<div class="dr-calendar-weekday"></div>').text(day)));
        for (let index = 0; index < 42; index += 1) {
            const date = new Date(gridStart);
            date.setDate(gridStart.getDate() + index);
            const value = dateToValue(date);
            const isPast = disallowPast && value < todayValue;
            $('<button type="button" class="dr-calendar-day"></button>')
                .text(date.getDate())
                .attr('data-date', value)
                .toggleClass('is-outside', date.getMonth() !== month)
                .toggleClass('is-today', value === todayValue)
                .toggleClass('is-selected', value === input.value)
                .toggleClass('cursor-not-allowed opacity-30', isPast)
                .prop('disabled', isPast)
                .appendTo($grid);
        }
    }

    function enhanceDateInput(input) {
        if (input.dataset.customDate === 'true' || input.closest('.swal2-container')) return;
        input.dataset.customDate = 'true';
        input.dataset.wasRequired = input.required ? 'true' : 'false';
        input.required = false;
        const layoutClasses = Array.from(input.classList).filter(name => /(^|:)(w-|min-w-|max-w-|flex-)/.test(name));
        const $root = $('<div class="dr-date"></div>').addClass(layoutClasses.join(' '));
        const $trigger = $('<button type="button" class="dr-date-trigger" aria-haspopup="dialog" aria-expanded="false"><span class="dr-date-label"></span><span aria-hidden="true">▣</span></button>');
        const calendarIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 10h18"></path></svg>';
        $trigger.find('span:last').html(calendarIcon);
        const $calendar = $('<div class="dr-calendar hidden" role="dialog" aria-label="Chọn ngày" popover="manual"><div class="dr-calendar-head"><button type="button" class="dr-calendar-nav dr-calendar-prev" aria-label="Tháng trước">‹</button><div class="dr-calendar-title"></div><button type="button" class="dr-calendar-nav dr-calendar-next" aria-label="Tháng sau">›</button></div><div class="dr-calendar-grid"></div><div class="dr-calendar-footer"><button type="button" class="dr-calendar-action dr-calendar-clear">Xóa ngày</button><button type="button" class="dr-calendar-action dr-calendar-today">Hôm nay</button></div></div>');
        $(input).wrap($root).addClass('dr-date-native').after($trigger, $calendar);
        const selected = input.value ? new Date(`${input.value}T00:00:00`) : new Date();
        $(input).closest('.dr-date').data('viewDate', new Date(selected.getFullYear(), selected.getMonth(), 1)).data('floatingCalendar', $calendar);
        syncDateControl(input);
    }

    function closeDatePickers(except) {
        $('.dr-date.is-open').each(function () {
            if (except && this === except) return;
            const $root = $(this);
            $root.removeClass('is-open');
            hideFloating($root.data('floatingCalendar') || $());
            $(this).find('.dr-date-trigger').attr('aria-expanded', 'false');
        });
    }

    $('input[type="date"]').each(function () { enhanceDateInput(this); });
    $(document).on('click', '.dr-date-trigger', function (event) {
        event.stopPropagation();
        const $root = $(this).closest('.dr-date');
        const willOpen = !$root.hasClass('is-open');
        closeCustomSelects();
        closeDatePickers($root[0]);
        $root.toggleClass('is-open', willOpen);
        $(this).attr('aria-expanded', willOpen ? 'true' : 'false');
        const $calendar = $root.data('floatingCalendar');
        if (willOpen) {
            const rect = this.getBoundingClientRect();
            const left = Math.max(8, Math.min(window.innerWidth - 300, rect.right - 292));
            const spaceBelow = window.innerHeight - rect.bottom;
            const top = spaceBelow >= 360 ? rect.bottom + 6 : Math.max(8, rect.top - 350);
            $calendar.appendTo(document.body).css({ left, top }).data('dateRoot', $root);
            showFloating($calendar);
            renderCalendar($root);
        } else {
            hideFloating($calendar);
        }
    });
    $(document).on('click', '.dr-calendar', event => event.stopPropagation());
    $(document).on('click', '.dr-calendar-prev, .dr-calendar-next', function () {
        const $root = $(this).closest('.dr-date').length ? $(this).closest('.dr-date') : $(this).closest('.dr-calendar').data('dateRoot');
        const view = $root.data('viewDate');
        view.setMonth(view.getMonth() + ($(this).hasClass('dr-calendar-next') ? 1 : -1));
        $root.data('viewDate', view);
        renderCalendar($root);
    });
    $(document).on('click', '.dr-calendar-day, .dr-calendar-today, .dr-calendar-clear', function () {
        const $root = $(this).closest('.dr-date').length ? $(this).closest('.dr-date') : $(this).closest('.dr-calendar').data('dateRoot');
        const input = $root.find('input[type="date"]')[0];
        input.value = $(this).hasClass('dr-calendar-clear') ? '' : ($(this).hasClass('dr-calendar-today') ? dateToValue(new Date()) : $(this).attr('data-date'));
        $(input).trigger('change');
        syncDateControl(input);
        closeDatePickers();
        $root.find('.dr-date-trigger').trigger('focus');
    });
    $(document).on('click', function () { closeDatePickers(); });
    $(window).on('resize scroll', function () { closeCustomSelects(); closeDatePickers(); });
    $('#settingsDrawer').on('scroll', function () { closeCustomSelects(); closeDatePickers(); });

    const selectObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.target instanceof HTMLSelectElement) rebuildCustomSelect(mutation.target);
            mutation.addedNodes.forEach(function (node) {
                if (!(node instanceof Element)) return;
                if (node.matches('select')) enhanceSelect(node);
                node.querySelectorAll('select').forEach(enhanceSelect);
                if (node.matches('input[type="date"]')) enhanceDateInput(node);
                node.querySelectorAll('input[type="date"]').forEach(enhanceDateInput);
            });
        });
    });
    selectObserver.observe(document.body, { childList: true, subtree: true });

    function projectSettingHtml(name, webhook, avatar) {
        return `<div class="project-setting overflow-hidden rounded-lg border border-sky-200 bg-white" data-project="${escapeHtml(name)}">
            <button type="button" class="toggle-project flex w-full items-center justify-between gap-2 bg-sky-50 px-3 py-2 text-left" aria-expanded="true">
                <span class="project-summary min-w-0 truncate text-xs font-semibold text-sky-950">📁 ${escapeHtml(name || 'Project mới')}</span>
                <span class="project-chevron rotate-180 text-sky-500 transition-transform">⌄</span>
            </button>
            <div class="project-setting-body p-2.5">
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
            </div>
        </div>`;
    }

    $(document).on('click', '.toggle-project', function () {
        const $button = $(this);
        const expanded = $button.attr('aria-expanded') === 'true';
        $button.attr('aria-expanded', expanded ? 'false' : 'true');
        $button.siblings('.project-setting-body').toggleClass('hidden', expanded);
        $button.find('.project-chevron').toggleClass('rotate-180', !expanded);
    });

    $(document).on('input', '.project-name', function () {
        const name = $(this).val().trim() || 'Project mới';
        $(this).closest('.project-setting').find('.project-summary').text('📁 ' + name);
    });

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

    $(document).on('click', '.remove-project', async function () {
        if ($('#projectSettingsList .project-setting').length <= 1) {
            Swal.fire({ icon: 'warning', title: 'Cần ít nhất 1 project', toast: true, position: 'top-end', showConfirmButton: false, timer: 1800 });
            return;
        }
        const $project = $(this).closest('.project-setting');
        const projectName = $project.find('.project-name').val().trim() || 'project này';
        const result = await confirmDelete('Xóa project?', `Project “${projectName}” và cấu hình webhook/logo sẽ bị xóa sau khi bạn lưu thiết lập.`);
        if (!result.isConfirmed) return;
        $project.remove();
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

        return `<div class="task-today-item task-sortable animate-slide-up" data-idx="${idx}" style="animation: fadeIn 0.3s ease-out both;">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <button type="button" class="task-drag-handle hidden h-9 w-7 flex-shrink-0 items-center justify-center text-zinc-400 hover:text-zinc-700 sm:flex" title="Kéo để sắp xếp" aria-label="Kéo để sắp xếp">⋮⋮</button>
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

    function updateTodayEstimateState($item) {
        const progress = $item.find('select[name*="[progress]"]').val();
        const $estimate = $item.find('input[name*="[estimate]"]');

        if (progress === '100') {
            $estimate.val('').prop('required', false).prop('disabled', true).addClass('bg-zinc-100 text-zinc-400');
            if ($estimate[0]) syncDateControl($estimate[0]);
            return;
        }

        $estimate.prop('disabled', false).toggleClass('bg-zinc-100 text-zinc-400', false);
        // Custom calendar is validated by validateTodayTasks to avoid focusing the hidden native input.
        $estimate.prop('required', false);
        if ($estimate[0]) syncDateControl($estimate[0]);
    }

    function validateTodayTasks() {
        let isValid = true;
        let firstInvalid = null;
        let invalidReason = 'missing';
        const todayValue = dateToValue(new Date());

        $('#tasks-today-list .task-today-item').each(function () {
            const $item = $(this);
            const content = $item.find('input[name*="[content]"]').val().trim();
            const progress = $item.find('select[name*="[progress]"]').val();
            const $estimate = $item.find('input[name*="[estimate]"]');

            updateTodayEstimateState($item);
            if (content && progress !== '' && Number(progress) < 100 && !$estimate.val()) {
                isValid = false;
                if (!firstInvalid) {
                    firstInvalid = $estimate;
                    invalidReason = 'missing';
                }
            } else if (content && Number(progress) < 100 && $estimate.val() && $estimate.val() < todayValue) {
                isValid = false;
                if (!firstInvalid) {
                    firstInvalid = $estimate;
                    invalidReason = 'past';
                }
            }
        });

        if (!isValid) {
            Swal.fire({
                icon: 'warning',
                title: invalidReason === 'past' ? 'Ngày dự kiến không hợp lệ' : 'Cần ngày dự kiến',
                text: invalidReason === 'past' ? 'Ngày dự kiến hoàn thành không được nhỏ hơn hôm nay.' : 'Task hôm nay chưa đạt 100% phải có ngày dự kiến.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2500
            });
            firstInvalid.focus();
        }

        return isValid;
    }

    function normalizeProgress(value) {
        const match = String(value || '').match(/\d+/);
        if (!match) {
            return '';
        }

        const progress = Math.max(0, Math.min(100, parseInt(match[0], 10)));
        return String(Math.floor(progress / 10) * 10);
    }

    function normalizeDate(value) {
        const text = String(value || '').trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(text)) {
            return text;
        }

        const match = text.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$/);
        if (!match) {
            return '';
        }

        const day = match[1].padStart(2, '0');
        const month = match[2].padStart(2, '0');
        return `${match[3]}-${month}-${day}`;
    }

    function parseBulkTodayTasks(text) {
        return String(text || '').split(/\r?\n/).map(function (line) {
            const parts = line.split('|').map(part => part.trim());
            const content = parts.shift() || '';
            const progress = normalizeProgress(parts[0] || '');
            const estimate = progress === '100' ? '' : normalizeDate(parts[1] || '');

            return { content, progress, estimate };
        }).filter(task => task.content);
    }

    function getEmptyTodayItem() {
        let $emptyItem = $();
        $('#tasks-today-list .task-today-item').each(function () {
            const $item = $(this);
            const content = $item.find('input[name*="[content]"]').val().trim();
            const progress = $item.find('select[name*="[progress]"]').val();
            const estimate = $item.find('input[name*="[estimate]"]').val();
            if (!content && !progress && !estimate) {
                $emptyItem = $item;
                return false;
            }
        });

        return $emptyItem;
    }

    function getTomorrowDate() {
        const date = new Date();
        date.setDate(date.getDate() + 1);
        return [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0')
        ].join('-');
    }

    function getAiTaskPrompt() {
        return `Bạn là trợ lý tổng hợp daily report.

Dựa vào danh sách commit/task/log công việc trong ngày bên dưới, hãy tổng hợp thành các task ngắn gọn để tôi copy vào form multi tasks.

Yêu cầu output:
- Chỉ xuất plain text, không giải thích.
- Mỗi task nằm trên một dòng.
- Format mỗi dòng:
  Nội dung task | tiến độ% | ngày dự kiến
- Nếu task đã hoàn thành thì dùng 100% và KHÔNG thêm ngày dự kiến.
- Nếu task chưa hoàn thành hoặc còn follow-up thì tiến độ phải nhỏ hơn 100% và PHẢI có ngày dự kiến dạng YYYY-MM-DD.
- Nội dung task viết ngắn, rõ, bắt đầu bằng issue/ticket nếu có.
- Gộp các commit/task trùng nội dung thành một dòng.
- Không bịa thêm task ngoài dữ liệu được cung cấp.
- Nếu không rõ tiến độ, hãy ước lượng hợp lý:
  - Hoàn tất/fixed/done/merged: 100%
  - Đang làm/in progress/partial: 50-80%
  - Mới bắt đầu/research/debug: 20-40%

Ngày dự kiến mặc định nếu cần: ${getTomorrowDate()}

Dữ liệu hôm nay:
[PASTE COMMITS/TASKS/NOTES Ở ĐÂY]`;
    }

    function addTodayTask(task) {
        let $item = getEmptyTodayItem();
        if (!$item.length) {
            $('#tasks-today-list').append(taskTodayHtml(taskTodayIdx++));
            $item = $('#tasks-today-list .task-today-item').last();
        }

        $item.find('input[name*="[content]"]').val(task.content);
        $item.find('select[name*="[progress]"]').val(task.progress);
        $item.find('input[name*="[estimate]"]').val(task.estimate);
        updateTodayEstimateState($item);
    }

    // ===== TASK NGÀY MAI =====
    function taskTomorrowHtml(idx) {
        return `<div class="task-tomorrow-item task-sortable animate-slide-up" data-idx="${idx}" style="animation: fadeIn 0.3s ease-out both;">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <button type="button" class="task-drag-handle hidden h-9 w-7 flex-shrink-0 items-center justify-center text-zinc-400 hover:text-zinc-700 sm:flex" title="Kéo để sắp xếp" aria-label="Kéo để sắp xếp">⋮⋮</button>
                <div class="flex w-full gap-2 sm:w-auto">
                    <select name="tasks_tomorrow[${idx}][type]" class="h-9 w-32 flex-shrink-0 cursor-pointer rounded-md border border-zinc-200 bg-white px-2 text-sm text-zinc-950 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                        <option value="new">New</option>
                        <option value="continue">Continue</option>
                    </select>
                </div>
                <input type="text" name="tasks_tomorrow[${idx}][content]" class="h-9 min-w-0 flex-1 rounded-md border border-zinc-200 bg-white px-3 text-sm text-zinc-950 shadow-sm placeholder:text-zinc-400 transition-colors focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="Nội dung task..." />
                <div class="flex w-full gap-2 sm:w-auto">
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
    updateTodayEstimateState($('#tasks-today-list .task-today-item').last());
    $('#add-task-today').click(function () {
        const html = taskTodayHtml(taskTodayIdx++);
        $('#tasks-today-list').append(html);
        const $lastItem = $('#tasks-today-list .task-today-item').last();
        updateTodayEstimateState($lastItem);
        const $lastInput = $lastItem.find('input[type="text"]');
        $lastInput.focus();
    });
    $('#bulk-task-today').click(function () {
        Swal.fire({
            title: 'Nhập nhiều task',
            html: `<textarea id="bulkTaskTodayText" class="dr-modal-field" spellcheck="false" placeholder="Task A | 100%
Task B | 70% | 2026-06-03
Task C"></textarea>`,
            buttonsStyling: false,
            customClass: {
                popup: 'dr-swal',
                title: 'dr-swal-title',
                htmlContainer: 'dr-swal-html',
                actions: 'dr-modal-actions',
                confirmButton: 'dr-modal-confirm',
                cancelButton: 'dr-modal-cancel',
                validationMessage: 'dr-modal-validation'
            },
            showCancelButton: true,
            confirmButtonText: 'Thêm vào report',
            cancelButtonText: 'Hủy',
            didOpen: function () {
                $('#bulkTaskTodayText').trigger('focus');
            },
            preConfirm: function () {
                const value = $('#bulkTaskTodayText').val();
                const tasks = parseBulkTodayTasks(value);
                if (!tasks.length) {
                    Swal.showValidationMessage('Nhập ít nhất 1 task.');
                    return false;
                }

                return tasks;
            }
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            result.value.forEach(addTodayTask);
            validateTodayTasks();
            scheduleDraftSave();
        });
    });
    $('#ai-task-prompt').click(function () {
        const prompt = getAiTaskPrompt();
        Swal.fire({
            title: 'Prompt AI tạo multi tasks',
            html: `<textarea id="aiTaskPromptText" class="dr-modal-field" readonly>${escapeHtml(prompt)}</textarea>`,
            buttonsStyling: false,
            customClass: {
                popup: 'dr-swal',
                title: 'dr-swal-title',
                htmlContainer: 'dr-swal-html',
                actions: 'dr-modal-actions',
                confirmButton: 'dr-modal-confirm',
                cancelButton: 'dr-modal-cancel',
                validationMessage: 'dr-modal-validation'
            },
            showCancelButton: true,
            confirmButtonText: 'Copy prompt',
            cancelButtonText: 'Đóng',
            didOpen: function () {
                $('#aiTaskPromptText').trigger('select');
            },
            preConfirm: function () {
                const value = $('#aiTaskPromptText').val();
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(value).then(function () {
                        return true;
                    }).catch(function () {
                        return false;
                    });
                }

                $('#aiTaskPromptText').trigger('select');
                return document.execCommand('copy');
            }
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            Swal.fire({
                icon: result.value ? 'success' : 'info',
                title: result.value ? 'Đã copy prompt' : 'Hãy copy thủ công',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1800
            });
        });
    });
    $('#add-task-tomorrow').click(function () {
        const html = taskTomorrowHtml(taskTomorrowIdx++);
        $('#tasks-tomorrow-list').append(html);
        const $lastInput = $('#tasks-tomorrow-list .task-tomorrow-item').last().find('input[type="text"]');
        $lastInput.focus();
    });

    // ===== SORT TASKS =====
    if (window.Sortable) {
        ['tasks-today-list', 'tasks-tomorrow-list'].forEach(function (id) {
            const list = document.getElementById(id);
            if (!list) return;
            Sortable.create(list, {
                draggable: '.task-sortable',
                handle: '.task-drag-handle',
                animation: 180,
                easing: 'cubic-bezier(0.2, 0, 0, 1)',
                ghostClass: 'task-sort-ghost',
                chosenClass: 'task-sort-chosen',
                dragClass: 'task-sort-drag',
                fallbackClass: 'task-sort-drag',
                forceFallback: true,
                fallbackOnBody: true,
                fallbackTolerance: 4,
                scroll: true,
                scrollSensitivity: 70,
                scrollSpeed: 12,
                onStart: function () { closeCustomSelects(); closeDatePickers(); },
                onEnd: function (event) {
                    if (event.oldIndex !== event.newIndex) scheduleDraftSave();
                }
            });
        });
    }

    // ===== REMOVE TASK =====
    $(document).on('click', '.remove-task', async function () {
        const $item = $(this).closest('.task-today-item, .task-tomorrow-item');
        const $parent = $item.closest('#tasks-today-list, #tasks-tomorrow-list');
        const taskContent = $item.find('input[name*="[content]"]').val().trim();
        const result = await confirmDelete('Xóa task?', taskContent ? `“${taskContent}” sẽ bị xóa khỏi báo cáo.` : 'Dòng task này sẽ bị xóa khỏi báo cáo.');
        if (!result.isConfirmed) return;
        $item.fadeOut(200, function () {
            $(this).remove();
            if ($parent.children().length === 0) {
                if ($parent.attr('id') === 'tasks-today-list') {
                    $parent.append(taskTodayHtml(taskTodayIdx++));
                    updateTodayEstimateState($parent.children().last());
                } else {
                    $parent.append(taskTomorrowHtml(taskTomorrowIdx++));
                }
            }
            scheduleDraftSave();
        });
    });

    $(document).on('change', '.task-today-item select[name*="[progress]"]', function () {
        updateTodayEstimateState($(this).closest('.task-today-item'));
    });

    $(document).on('input change', '.task-today-item input[name*="[estimate]"]', function () {
        updateTodayEstimateState($(this).closest('.task-today-item'));
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

    // ===== DRAFT & PREVIEW =====
    function collectDraft() {
        const todayTasks = [];
        const tomorrowTasks = [];
        $('#tasks-today-list .task-today-item').each(function () {
            todayTasks.push({
                content: $(this).find('input[name*="[content]"]').val(),
                progress: $(this).find('select[name*="[progress]"]').val(),
                estimate: $(this).find('input[name*="[estimate]"]').val()
            });
        });
        $('#tasks-tomorrow-list .task-tomorrow-item').each(function () {
            tomorrowTasks.push({
                type: $(this).find('select[name*="[type]"]').val(),
                content: $(this).find('input[name*="[content]"]').val()
            });
        });
        return {
            savedAt: new Date().toISOString(),
            project: $('#project').val(),
            todayTasks,
            tomorrowTasks,
            quality: $('#quality').val(),
            spirit: $('#spirit').val(),
            note: $('#dailyReportForm textarea[name="note"]').val(),
            submitGoogleForm: $('#submitGoogleForm').prop('checked')
        };
    }

    function hasDraftContent(draft) {
        return draft.todayTasks.some(task => String(task.content || '').trim()) ||
            draft.tomorrowTasks.some(task => String(task.content || '').trim()) ||
            String(draft.note || '').trim();
    }

    function updateDraftStatus(savedAt) {
        const time = new Date(savedAt).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
        $('#draftStatus').text(`Đã lưu bản nháp lúc ${time}`);
        $('#clearDraft').removeClass('hidden');
    }

    function saveDraft() {
        const draft = collectDraft();
        if (!hasDraftContent(draft)) return;
        localStorage.setItem(draftKey, JSON.stringify(draft));
        updateDraftStatus(draft.savedAt);
    }

    function scheduleDraftSave() {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 450);
    }

    function restoreDraft(draft) {
        if (!draft || !Array.isArray(draft.todayTasks)) return;
        $('#tasks-today-list, #tasks-tomorrow-list').empty();
        draft.todayTasks.forEach(function (task) { addTodayTask(task); });
        if (!draft.todayTasks.length) {
            $('#tasks-today-list').append(taskTodayHtml(taskTodayIdx++));
        }
        (draft.tomorrowTasks || []).forEach(function (task) {
            $('#tasks-tomorrow-list').append(taskTomorrowHtml(taskTomorrowIdx++));
            const $item = $('#tasks-tomorrow-list .task-tomorrow-item').last();
            $item.find('select[name*="[type]"]').val(task.type || 'new');
            $item.find('input[name*="[content]"]').val(task.content || '');
        });
        if (!(draft.tomorrowTasks || []).length) {
            $('#tasks-tomorrow-list').append(taskTomorrowHtml(taskTomorrowIdx++));
        }
        if ($('#project option[value="' + CSS.escape(draft.project || '') + '"]').length) $('#project').val(draft.project);
        $('#dailyReportForm textarea[name="note"]').val(draft.note || '');
        $('#submitGoogleForm').prop('checked', Boolean(draft.submitGoogleForm));
        $('#quality-list .quality-btn[data-value="' + (draft.quality || 3) + '"]').trigger('click');
        $('#spirit-list .react-emoji[data-value="' + (draft.spirit || 3) + '"]').trigger('click');
        updateProjectLogo();
        updateDraftStatus(draft.savedAt || new Date().toISOString());
    }

    try {
        const storedDraft = JSON.parse(localStorage.getItem(draftKey) || 'null');
        if (storedDraft && hasDraftContent(storedDraft)) restoreDraft(storedDraft);
    } catch (error) {
        localStorage.removeItem(draftKey);
    }

    $('#dailyReportForm').on('input change', 'input, select, textarea', scheduleDraftSave);
    $('#quality-list, #spirit-list').on('click', 'button', scheduleDraftSave);

    $('#clearDraft').on('click', async function () {
        const result = await confirmDelete('Xóa bản nháp?', 'Bản nháp đang lưu trên trình duyệt sẽ không thể khôi phục.');
        if (!result.isConfirmed) return;
        localStorage.removeItem(draftKey);
        $('#draftStatus').text('Bản nháp đã được xóa');
        $(this).addClass('hidden');
    });

    function previewHtml() {
        const draft = collectDraft();
        const today = draft.todayTasks.filter(task => String(task.content || '').trim()).map(function (task) {
            const extra = task.progress ? ` <strong>${escapeHtml(task.progress)}%</strong>${task.estimate ? ` · ${escapeHtml(task.estimate)}` : ''}` : '';
            return `<li class="rounded-md bg-zinc-50 px-3 py-2">${escapeHtml(task.content)}${extra}</li>`;
        }).join('');
        const tomorrow = draft.tomorrowTasks.filter(task => String(task.content || '').trim()).map(function (task) {
            return `<li class="rounded-md bg-zinc-50 px-3 py-2"><span class="text-zinc-500">[${task.type === 'continue' ? 'Continue' : 'New'}]</span> ${escapeHtml(task.content)}</li>`;
        }).join('');
        return `<div class="text-left text-sm text-zinc-700">
            <div class="mb-4 rounded-lg border border-zinc-200 p-3"><div class="font-semibold text-zinc-950">📋 Daily Report · ${escapeHtml(draft.project)}</div><div class="mt-1 text-xs text-zinc-500">${escapeHtml(new Date().toLocaleString('vi-VN'))}</div></div>
            <h3 class="mb-2 font-semibold text-zinc-900">📝 Task hôm nay</h3><ul class="mb-4 space-y-2">${today || '<li class="text-zinc-400">Chưa có task</li>'}</ul>
            <h3 class="mb-2 font-semibold text-zinc-900">📅 Task ngày mai</h3><ul class="mb-4 space-y-2">${tomorrow || '<li class="text-zinc-400">Chưa có kế hoạch</li>'}</ul>
            <div class="grid grid-cols-2 gap-2 rounded-lg border border-zinc-200 p-3"><span>Chất lượng: <strong>${escapeHtml(draft.quality)}/5</strong></span><span>Tinh thần: <strong>${escapeHtml(draft.spirit)}/5</strong></span></div>
            ${draft.note ? `<div class="mt-3 rounded-lg border border-zinc-200 p-3"><strong>Ghi chú:</strong> ${escapeHtml(draft.note)}</div>` : ''}
            <p class="mt-3 text-xs text-zinc-500">Google Form: ${draft.submitGoogleForm ? 'Có gửi kèm' : 'Không gửi'}</p>
        </div>`;
    }

    $('#previewReport').on('click', function () {
        Swal.fire({ title: 'Xem trước báo cáo', html: previewHtml(), confirmButtonText: 'Đóng', width: 680 });
    });

    $(document).on('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            $('#dailyReportForm').trigger('submit');
        }
    });

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
        if (!validateTodayTasks()) {
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
                    localStorage.removeItem(draftKey);
                    $('#draftStatus').text('Đã gửi · bản nháp đã được xóa');
                    $('#clearDraft').addClass('hidden');
                    Swal.fire({
                        icon: 'success',
                        title: 'Đã gửi báo cáo',
                        text: res.form_status === 'failed' ? 'Google Chat thành công, Google Form thất bại.' : 'Google Chat và dữ liệu liên quan đã xử lý xong.',
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

    // ===== SUBMIT GOOGLE FORM THEO KHOẢNG NGÀY =====
    function getDateRange(startValue, endValue) {
        const dates = [];
        const current = new Date(`${startValue}T00:00:00`);
        const end = new Date(`${endValue}T00:00:00`);

        while (current <= end) {
            dates.push([
                current.getFullYear(),
                String(current.getMonth() + 1).padStart(2, '0'),
                String(current.getDate()).padStart(2, '0')
            ].join('-'));
            current.setDate(current.getDate() + 1);
        }

        return dates;
    }

    $('#googleFormRangeForm').submit(async function (e) {
        e.preventDefault();

        const startDate = $('#googleFormStartDate').val();
        const endDate = $('#googleFormEndDate').val();
        if (!startDate || !endDate || startDate > endDate) {
            Swal.fire({ icon: 'warning', title: 'Khoảng ngày không hợp lệ', text: 'Ngày bắt đầu phải nhỏ hơn hoặc bằng ngày kết thúc.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            return;
        }

        const dates = getDateRange(startDate, endDate);
        if (dates.length > 31) {
            Swal.fire({ icon: 'warning', title: 'Khoảng ngày quá dài', text: 'Mỗi lần chỉ submit tối đa 31 ngày.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            return;
        }

        const confirmation = await Swal.fire({
            icon: 'question',
            title: `Submit Google Form cho ${dates.length} ngày?`,
            text: 'Thao tác này chỉ gửi form chấm công, không gửi report Google Chat.',
            showCancelButton: true,
            confirmButtonText: 'Submit',
            cancelButtonText: 'Hủy'
        });
        if (!confirmation.isConfirmed) {
            return;
        }

        const $button = $('#submitGoogleFormRangeBtn');
        const $text = $('#submitGoogleFormRangeText');
        $button.prop('disabled', true).addClass('opacity-70 cursor-not-allowed');
        const succeeded = [];
        const failed = [];

        for (let index = 0; index < dates.length; index += 1) {
            const date = dates[index];
            $text.text(`Đang submit ${index + 1}/${dates.length}...`);
            try {
                const response = await $.ajax({
                    url: 'submit-google-form.php',
                    method: 'POST',
                    data: { date, csrf_token: $('#googleFormRangeForm input[name="csrf_token"]').val() },
                    dataType: 'json'
                });
                if (response.success) {
                    succeeded.push(date);
                } else {
                    failed.push({ date, message: response.message || 'Có lỗi xảy ra' });
                }
            } catch (error) {
                failed.push({ date, message: 'Không kết nối được máy chủ' });
            }
        }

        $button.prop('disabled', false).removeClass('opacity-70 cursor-not-allowed');
        $text.text('Submit các ngày');

        if (failed.length === 0) {
            Swal.fire({ icon: 'success', title: 'Submit hoàn tất', text: `Đã submit thành công ${succeeded.length}/${dates.length} ngày.` });
            return;
        }

        const failedDates = failed.map(item => `${item.date.split('-').reverse().join('/')}: ${item.message}`).join('\n');
        Swal.fire({
            icon: 'warning',
            title: `Hoàn tất ${succeeded.length}/${dates.length} ngày`,
            html: `<p class="mb-2 text-sm">Các ngày chưa submit được:</p><pre class="whitespace-pre-wrap text-left text-xs">${escapeHtml(failedDates)}</pre>`
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
                    setSettingsOpen(false);
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
