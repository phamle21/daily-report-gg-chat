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
            return /(^|:)(w-|min-w-|max-w-|flex-|dr-col-)/.test(name);
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


    // ===== COMBOBOX (input tự do + menu gợi ý dùng lại style .dr-select-menu) =====
    const COMBO_SOURCES = {
        workType: () => $('#workTypeList option').map(function () { return $(this).attr('value'); }).get()
    };

    function comboOptions($input) {
        const source = COMBO_SOURCES[$input.data('combo')];
        return source ? source() : [];
    }

    function renderComboMenu($root) {
        const $input = $root.find('input');
        const $menu = $root.data('floatingMenu');
        const typed = String($input.val() || '').trim().toLowerCase();
        const all = comboOptions($input);
        // Lọc theo những gì đang gõ; không khớp gì thì vẫn cho xem toàn bộ danh sách.
        const matched = typed ? all.filter(option => option.toLowerCase().includes(typed)) : all;
        const options = matched.length ? matched : all;
        $menu.empty();
        options.forEach(function (option) {
            $('<button type="button" class="dr-select-option"></button>')
                .text(option)
                .toggleClass('is-selected', option.toLowerCase() === typed)
                .appendTo($menu);
        });
    }

    function closeCombos(except) {
        $('.dr-combo.is-open').each(function () {
            if (except && this === except) return;
            const $root = $(this);
            $root.removeClass('is-open');
            hideFloating($root.data('floatingMenu') || $());
        });
    }

    function openCombo($root) {
        const $menu = $root.data('floatingMenu');
        const rect = $root.find('input')[0].getBoundingClientRect();
        renderComboMenu($root);
        $menu.appendTo(document.body).css({ left: rect.left, top: rect.bottom + 6, width: Math.max(rect.width, 150) });
        $root.addClass('is-open');
        showFloating($menu);
    }

    function enhanceCombo(input) {
        if (input.dataset.comboReady === 'true') return;
        input.dataset.comboReady = 'true';
        const layoutClasses = Array.from(input.classList).filter(name => /(^|:)(w-|min-w-|max-w-|flex-|dr-col-)/.test(name));
        const $root = $('<div class="dr-combo"></div>').addClass(layoutClasses.join(' '));
        const $toggle = $('<button type="button" class="dr-combo-toggle" tabindex="-1" aria-label="Xem gợi ý">⌄</button>');
        const $menu = $('<div class="dr-select-menu hidden" role="listbox" popover="manual"></div>');
        $(input).wrap($root).after($toggle);
        $(input).closest('.dr-combo').data('floatingMenu', $menu);
    }

    $('input[data-combo]').each(function () { enhanceCombo(this); });

    $(document).on('focus click', '.dr-combo input', function (event) {
        event.stopPropagation();
        const $root = $(this).closest('.dr-combo');
        closeCustomSelects();
        closeCombos($root[0]);
        openCombo($root);
    });
    $(document).on('input', '.dr-combo input', function () {
        const $root = $(this).closest('.dr-combo');
        if ($root.hasClass('is-open')) renderComboMenu($root);
    });
    $(document).on('click', '.dr-combo-toggle', function (event) {
        event.stopPropagation();
        const $root = $(this).closest('.dr-combo');
        if ($root.hasClass('is-open')) { closeCombos(); return; }
        closeCombos();
        openCombo($root);
        $root.find('input').focus();
    });
    $(document).on('keydown', '.dr-combo input', function (event) {
        if (event.key === 'Escape') closeCombos();
    });
    $(document).on('click', function () { closeCombos(); });
    $(window).on('resize scroll', function () { closeCombos(); });

    // Chọn gợi ý: menu đã được đưa ra body nên tìm ngược về combo đang mở.
    $(document).on('mousedown', '.dr-select-menu .dr-select-option', function (event) {
        const $root = $('.dr-combo.is-open');
        if (!$root.length || !$.contains($root.data('floatingMenu')[0], this)) return;
        event.preventDefault();
        $root.find('input').val($(this).text()).trigger('change');
        closeCombos();
    });

    // ===== TEXTAREA TỰ CAO THEO NỘI DUNG =====
    function autoGrow(element) {
        if (!element) return;
        element.style.height = 'auto';
        element.style.height = Math.min(element.scrollHeight, 220) + 'px';
    }
    $(document).on('input change', 'textarea.dr-input', function () { autoGrow(this); });
    function autoGrowAll() { $('textarea.dr-input').each(function () { autoGrow(this); }); }

    // ===== ĐẾM KÝ TỰ Ô TỰ ĐÁNH GIÁ =====
    $('#dailyResult').on('input', function () { $('#dailyResultCount').text(this.value.length); }).trigger('input');

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
        $calendar.find('.dr-calendar-title').text(`Tháng ${month + 1}, ${year}`);
        const $grid = $calendar.find('.dr-calendar-grid').empty();
        ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'].forEach(day => $grid.append($('<div class="dr-calendar-weekday"></div>').text(day)));
        for (let index = 0; index < 42; index += 1) {
            const date = new Date(gridStart);
            date.setDate(gridStart.getDate() + index);
            const value = dateToValue(date);
            $('<button type="button" class="dr-calendar-day"></button>')
                .text(date.getDate())
                .attr('data-date', value)
                .toggleClass('is-outside', date.getMonth() !== month)
                .toggleClass('is-today', value === todayValue)
                .toggleClass('is-selected', value === input.value)
                .appendTo($grid);
        }
    }

    function enhanceDateInput(input) {
        if (input.dataset.customDate === 'true' || input.closest('.swal2-container')) return;
        input.dataset.customDate = 'true';
        input.dataset.wasRequired = input.required ? 'true' : 'false';
        input.required = false;
        const layoutClasses = Array.from(input.classList).filter(name => /(^|:)(w-|min-w-|max-w-|flex-|dr-col-)/.test(name));
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
                if (node.matches('input[data-combo]')) enhanceCombo(node);
                node.querySelectorAll('input[data-combo]').forEach(enhanceCombo);
                if (node.matches('textarea.dr-input')) autoGrow(node);
                node.querySelectorAll('textarea.dr-input').forEach(autoGrow);
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
            <label class="mb-1 block">
                <span class="mb-1 block text-[11px] font-medium text-zinc-600">Webhook Slack</span>
                <input name="projects[slack_webhook][]" class="project-slack-webhook h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs" placeholder="https://hooks.slack.com/services/...">
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
    const TASK_STATUSES = ['Chưa bắt đầu', 'Đang thực hiện', 'Chờ review', 'Tạm hoãn', 'Hoàn thành'];
    const DEFAULT_TASK_STATUS = 'Đang thực hiện';

    function taskTodayHtml(idx) {
        let options = '';
        // Đổ từ 100% xuống 0% để mức hay chọn nhất nằm ngay đầu danh sách.
        for (let i = 100; i >= 0; i -= 10) {
            options += `<option value="${i}">${i}%</option>`;
        }
        const statusOptions = TASK_STATUSES
            .map(status => `<option value="${status}"${status === DEFAULT_TASK_STATUS ? ' selected' : ''}>${status}</option>`)
            .join('');

        return `<div class="task-today-item task-sortable dr-taskrow" data-idx="${idx}">
            <button type="button" class="task-drag-handle dr-taskrow-drag" title="Kéo để sắp xếp" aria-label="Kéo để sắp xếp">⋮⋮</button>
            <input type="text" name="tasks_today[${idx}][issue_no]" class="dr-input dr-col-issue" aria-label="Issue" placeholder="Issue" />
            <textarea name="tasks_today[${idx}][content]" rows="1" class="dr-input dr-col-title" aria-label="Task" placeholder="Mô tả công việc..." required></textarea>
            <input type="text" name="tasks_today[${idx}][work_type]" data-combo="workType" class="dr-input dr-col-type" aria-label="Loại công việc" placeholder="Coding" autocomplete="off" />
            <select name="tasks_today[${idx}][status]" class="dr-input dr-col-status" aria-label="Trạng thái">${statusOptions}</select>
            <select name="tasks_today[${idx}][progress]" class="dr-input dr-col-progress" aria-label="Tiến độ" required>
                <option value="">--%</option>${options}
            </select>
            <input type="date" name="tasks_today[${idx}][estimate]" class="dr-input dr-col-date" aria-label="Ngày dự kiến hoàn thành" />
            <button type="button" class="remove-task dr-taskrow-remove" title="Xóa task" aria-label="Xóa task">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>`;
    }

    function syncSubmitRequirements() {
        const needsTasks = $('#submitGoogleChat, #submitSlack').is(':checked');
        $('#tasks-today-list [name*="[content]"], #tasks-today-list select[name*="[progress]"]').prop('required', needsTasks);
    }
    $('#submitGoogleChat, #submitSlack').on('change', syncSubmitRequirements);

    function updateTodayTaskState($item) {
        syncSubmitRequirements();
        const $estimate = $item.find('input[name*="[estimate]"]');
        // Estimate date is optional and unconstrained; only keep the custom date control in sync.
        if ($estimate[0]) syncDateControl($estimate[0]);
    }


    function splitIssueFromTitle(value) {
        let rest = String(value || '').trim();
        const parts = [];
        let match;
        while ((match = rest.match(/^\[([^\]]*)\]\s*/))) {
            const label = match[1].trim();
            if (label) parts.push(label);
            rest = rest.slice(match[0].length);
        }

        // A title made of nothing but brackets stays the task content.
        return rest.trim() ? { issue: parts.join(' '), content: rest.trim() } : { issue: '', content: String(value || '').trim() };
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
            const parts = line.trim().split('|').map(part => part.trim());
            const content = parts.shift() || '';
            let work_type = '';
            let status = '';
            let progress = '';
            let estimate = '';
            parts.forEach(function (part) {
                if (!part) return;
                const asDate = normalizeDate(part);
                if (asDate) {
                    estimate = asDate;
                    return;
                }
                if (/^\d{1,3}%?$/.test(part)) {
                    progress = normalizeProgress(part);
                    return;
                }
                const asStatus = TASK_STATUSES.find(item => item.toLowerCase() === part.toLowerCase());
                if (asStatus) {
                    status = asStatus;
                    return;
                }
                work_type = part;
            });

            return { work_type, content, status, progress, estimate };
        }).filter(task => task.content);
    }

    function getEmptyTodayItem() {
        let $emptyItem = $();
        $('#tasks-today-list .task-today-item').each(function () {
            const $item = $(this);
            const content = $item.find('[name*="[content]"]').val().trim();
            const progress = $item.find('select[name*="[progress]"]').val();
            const estimate = $item.find('input[name*="[estimate]"]').val();
            const issue = $item.find('input[name*="[issue_no]"]').val().trim();
            if (!content && !progress && !estimate && !issue) {
                $emptyItem = $item;
                return false;
            }
        });

        return $emptyItem;
    }

    function getTomorrowDate() {
        const date = new Date(($('#reportDate').val() || dateToValue(new Date())) + 'T00:00:00');
        date.setDate(date.getDate() + 1);
        return [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0')
        ].join('-');
    }

    function getAiTaskPrompt() {
        const project = $('#project').val() || '';
        const reportDate = formatDisplayDate($('#reportDate').val());
        return `Tổng hợp công việc của tôi cho dự án ${project} trong ngày ${reportDate} thành danh sách task báo cáo.

Nguồn tổng hợp: chính cuộc trò chuyện này — gồm trao đổi với agent, phân tích, nghiên cứu, thống nhất phương án và việc đã thực hiện, kể cả phần chưa có commit. Dùng thêm ghi chú bổ sung ở cuối prompt nếu có.

Kết quả tôi sẽ dán thẳng vào ô "Nhập nhiều dòng" của form daily report, nên chỉ trả plain text, mỗi task một dòng theo cấu trúc:
Tiêu đề task | Loại | Trạng thái | tiến độ% | ngày dự kiến

- Tiêu đề bắt đầu bằng issue/ticket hoặc tên feature trong ngoặc vuông nếu có, ví dụ [Issue] [999] Sửa lỗi thêm sản phẩm hoặc [Feature] Màn hình quản lý; bỏ hẳn ngoặc vuông nếu không có, không tự bịa.
- Loại chọn phù hợp nhất (Coding, Fix bug, Feature, Testing, Review, Research, Discussion, Design, Detail Design, Meeting, Deploy...); nếu không chắc thì dùng Coding.
- Trạng thái chọn đúng một trong: Chưa bắt đầu, Đang thực hiện, Chờ review, Tạm hoãn, Hoàn thành; nếu không chắc thì dùng Đang thực hiện.
- Viết ngắn, gộp ý trùng.
- Mô tả đúng việc thực tế: chỉ trao đổi/phân tích thì ghi rõ, không viết thành đã code, sửa xong hay triển khai.
- Tiến độ từ 0–100%, theo bước 10%; dựa trên kết quả trao đổi, ước lượng thận trọng nếu chưa có số cụ thể. Không dùng việc có/chưa có commit để kết luận hoàn thành.
- Task đã hoàn thành: ... | Hoàn thành | 100% (bỏ ngày dự kiến). Một task phân tích/trao đổi có thể hoàn thành nếu đã đạt mục tiêu của chính task đó.
- Task chưa xong: tiến độ dưới 100%, kèm ngày dự kiến YYYY-MM-DD nếu ước lượng được; không chắc thì bỏ trống, ví dụ ${getTomorrowDate()}.
- Chỉ tính công việc thuộc dự án ${project} và phát sinh trong ngày ${reportDate}; bỏ qua phần ngoài phạm vi này.
- Không thêm tiêu đề, bullet, bảng, code block hoặc giải thích; không dùng dấu | bên trong nội dung task. Nếu không có thông tin công việc thì không tạo task.

Ghi chú bổ sung (nếu có):
[Dán thêm thông tin tại đây hoặc bỏ trống]`;
    }

    function addTodayTask(task) {
        let $item = getEmptyTodayItem();
        if (!$item.length) {
            $('#tasks-today-list').append(taskTodayHtml(taskTodayIdx++));
            $item = $('#tasks-today-list .task-today-item').last();
        }

        const split = splitIssueFromTitle(task.content);
        const issue = String(task.issue_no || task.issue || split.issue || '').trim();
        $item.find('input[name*="[work_type]"]').val(task.work_type || 'Coding');
        $item.find('input[name*="[issue_no]"]').val(issue);
        $item.find('[name*="[content]"]').val(split.content);
        $item.find('select[name*="[status]"]').val(TASK_STATUSES.includes(task.status) ? task.status : DEFAULT_TASK_STATUS);
        $item.find('select[name*="[progress]"]').val(task.progress);
        $item.find('input[name*="[estimate]"]').val(task.estimate);
        autoGrow($item.find('[name*="[content]"]')[0]);
        updateTodayTaskState($item);
    }

    // ===== TASK NGÀY MAI =====
    function taskTomorrowHtml(idx) {
        return `<div class="task-tomorrow-item task-sortable dr-taskrow" data-idx="${idx}">
            <button type="button" class="task-drag-handle dr-taskrow-drag" title="Kéo để sắp xếp" aria-label="Kéo để sắp xếp">⋮⋮</button>
            <select name="tasks_tomorrow[${idx}][type]" class="dr-input dr-col-type" aria-label="Loại kế hoạch">
                <option value="new">New</option>
                <option value="continue">Continue</option>
            </select>
            <textarea name="tasks_tomorrow[${idx}][content]" rows="1" class="dr-input dr-col-title" aria-label="Nội dung kế hoạch" placeholder="Nội dung task..."></textarea>
            <button type="button" class="remove-task dr-taskrow-remove" title="Xóa task" aria-label="Xóa task">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>`;
    }

    // ===== INIT =====
    let taskTodayIdx = 0, taskTomorrowIdx = 0;
    $('#tasks-today-list').append(taskTodayHtml(taskTodayIdx++));
    updateTodayTaskState($('#tasks-today-list .task-today-item').last());
    $('#add-task-today').click(function () {
        const html = taskTodayHtml(taskTodayIdx++);
        $('#tasks-today-list').append(html);
        const $lastItem = $('#tasks-today-list .task-today-item').last();
        updateTodayTaskState($lastItem);
        $lastItem.find('[name*="[content]"]').focus();
    });
    $('#bulk-task-today').click(function () {
        Swal.fire({
            title: 'Nhập nhiều task',
            html: `<textarea id="bulkTaskTodayText" class="dr-modal-field" spellcheck="false" placeholder="[Issue] [999] Bug khi thêm mới sản phẩm | Coding | Đang thực hiện | 50%
[Feature] Quản lý sản phẩm | Detail Design | Chờ review | 60% | 2026-09-20
Task C | Hoàn thành | 100%"></textarea>`,
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
            scheduleDraftSave();
        });
    });
    $('#ai-task-prompt').click(function () {
        const prompt = getAiTaskPrompt();
        Swal.fire({
            title: 'Prompt AI tạo multi tasks',
            html: `<p class="dr-modal-note">Prompt này đã gắn sẵn dự án <b>${escapeHtml($('#project').val() || '')}</b> và ngày <b>${escapeHtml(formatDisplayDate($('#reportDate').val()))}</b>.
                <br>Copy prompt → dán vào AI đang làm việc cùng bạn (Claude, ChatGPT, agent trong IDE...) → nó đọc lại cuộc trò chuyện hôm nay và trả về danh sách task.
                <br>Chép kết quả đó dán vào nút <b>Nhập nhiều dòng</b>, form sẽ tự tách thành từng dòng task.</p>
                <textarea id="aiTaskPromptText" class="dr-modal-field" readonly>${escapeHtml(prompt)}</textarea>`,
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
        const taskContent = $item.find('[name*="[content]"]').val().trim();
        const result = await confirmDelete('Xóa task?', taskContent ? `“${taskContent}” sẽ bị xóa khỏi báo cáo.` : 'Dòng task này sẽ bị xóa khỏi báo cáo.');
        if (!result.isConfirmed) return;
        $item.fadeOut(200, function () {
            $(this).remove();
            if ($parent.children().length === 0) {
                if ($parent.attr('id') === 'tasks-today-list') {
                    $parent.append(taskTodayHtml(taskTodayIdx++));
                    updateTodayTaskState($parent.children().last());
                } else {
                    $parent.append(taskTomorrowHtml(taskTomorrowIdx++));
                }
            }
            scheduleDraftSave();
        });
    });

    $(document).on('change', '.task-today-item select[name*="[progress]"]', function () {
        updateTodayTaskState($(this).closest('.task-today-item'));
    });

    $(document).on('change', '.task-today-item select[name*="[status]"]', function () {
        const $item = $(this).closest('.task-today-item');
        if ($(this).val() === 'Hoàn thành') {
            $item.find('select[name*="[progress]"]').val('100');
        }
        updateTodayTaskState($item);
    });

    $(document).on('input change', '.task-today-item input[name*="[estimate]"]', function () {
        updateTodayTaskState($(this).closest('.task-today-item'));
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
                work_type: $(this).find('input[name*="[work_type]"]').val(),
                issue_no: $(this).find('input[name*="[issue_no]"]').val(),
                content: $(this).find('[name*="[content]"]').val(),
                status: $(this).find('select[name*="[status]"]').val(),
                progress: $(this).find('select[name*="[progress]"]').val(),
                estimate: $(this).find('input[name*="[estimate]"]').val()
            });
        });
        $('#tasks-tomorrow-list .task-tomorrow-item').each(function () {
            tomorrowTasks.push({
                type: $(this).find('select[name*="[type]"]').val(),
                content: $(this).find('[name*="[content]"]').val()
            });
        });
        return {
            savedAt: new Date().toISOString(),
            submitGoogleChat: $('#submitGoogleChat').prop('checked'),
            submitSlack: $('#submitSlack').prop('checked'),
            submitGoogleForm: $('#submitGoogleForm').prop('checked'),
            reportDate: $('#reportDate').val(),
            dailyResult: $('#dailyResult').val(),
            project: $('#project').val(),
            todayTasks,
            tomorrowTasks,
            quality: $('#quality').val(),
            spirit: $('#spirit').val(),
            note: $('#dailyReportForm textarea[name="note"]').val(),
        };
    }

    function hasDraftContent(draft) {
        return draft.todayTasks.some(task => String(task.content || '').trim()) ||
            draft.tomorrowTasks.some(task => String(task.content || '').trim()) ||
            String(draft.note || draft.dailyResult || '').trim();
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
        $('#submitGoogleChat').prop('checked', draft.submitGoogleChat ?? (draft.destination ? draft.destination === 'google_chat' : true));
        $('#submitSlack').prop('checked', draft.submitSlack ?? (draft.destination === 'slack'));
        $('#submitGoogleForm').prop('checked', draft.submitGoogleForm ?? true);
        $('#reportDate').val(draft.reportDate || $('#reportDate')[0].defaultValue).trigger('change');
        syncDateControl($('#reportDate')[0]);
        $('#dailyResult').val(draft.dailyResult || '');
        $('#tasks-today-list, #tasks-tomorrow-list').empty();
        draft.todayTasks.forEach(function (task) { addTodayTask(task); });
        if (!draft.todayTasks.length) {
            $('#tasks-today-list').append(taskTodayHtml(taskTodayIdx++));
        }
        (draft.tomorrowTasks || []).forEach(function (task) {
            $('#tasks-tomorrow-list').append(taskTomorrowHtml(taskTomorrowIdx++));
            const $item = $('#tasks-tomorrow-list .task-tomorrow-item').last();
            $item.find('select[name*="[type]"]').val(task.type || 'new');
            $item.find('[name*="[content]"]').val(task.content || '');
        });
        if (!(draft.tomorrowTasks || []).length) {
            $('#tasks-tomorrow-list').append(taskTomorrowHtml(taskTomorrowIdx++));
        }
        if ($('#project option[value="' + CSS.escape(draft.project || '') + '"]').length) $('#project').val(draft.project);
        $('#dailyReportForm textarea[name="note"]').val(draft.note || '');
        $('#quality-list .quality-btn[data-value="' + (draft.quality || 3) + '"]').trigger('click');
        $('#spirit-list .react-emoji[data-value="' + (draft.spirit || 3) + '"]').trigger('click');
        updateProjectLogo();
        autoGrowAll();
        updateDraftStatus(draft.savedAt || new Date().toISOString());
    }

    try {
        const storedDraft = JSON.parse(localStorage.getItem(draftKey) || 'null');
        if (storedDraft && hasDraftContent(storedDraft)) restoreDraft(storedDraft);
    } catch (error) {
        localStorage.removeItem(draftKey);
    }

    syncSubmitRequirements();

    $('#dailyReportForm').on('input change', 'input, select, textarea', scheduleDraftSave);
    $('#quality-list, #spirit-list').on('click', 'button', scheduleDraftSave);

    $('#clearDraft').on('click', async function () {
        const result = await confirmDelete('Xóa bản nháp?', 'Bản nháp đang lưu trên trình duyệt sẽ không thể khôi phục.');
        if (!result.isConfirmed) return;
        localStorage.removeItem(draftKey);
        $('#draftStatus').text('Bản nháp đã được xóa');
        $(this).addClass('hidden');
    });

    const QUALITY_LABELS = { 1: 'Kém', 2: 'Trung bình', 3: 'Khá', 4: 'Tốt', 5: 'Xuất sắc' };
    const SPIRIT_LABELS = { 1: 'Rất không tốt', 2: 'Không tốt', 3: 'Bình thường', 4: 'Tốt', 5: 'Rất tốt' };
    const GC_QUALITY_LABELS = {
        1: '❌ Kém – Không hoàn thành',
        2: '⚠️ Trung bình – Hoàn thành nhưng còn lỗi',
        3: '✅ Khá – Hoàn thành đúng yêu cầu',
        4: '🌟 Tốt – Hoàn thành vượt yêu cầu',
        5: '🏆 Xuất sắc – Kết quả nổi bật'
    };
    const GC_SPIRIT_LABELS = { 1: '😣 Rất không tốt', 2: '😕 Không tốt', 3: '😐 Bình thường', 4: '🙂 Tốt', 5: '😄 Rất tốt' };

    function draftTodayTasks(draft) {
        return draft.todayTasks.filter(task => String(task.content || '').trim()).map(function (task) {
            const split = splitIssueFromTitle(task.content);
            return {
                issue: String(task.issue_no || '').trim() || split.issue,
                content: split.content,
                work_type: task.work_type || 'Coding',
                status: TASK_STATUSES.includes(task.status) ? task.status : DEFAULT_TASK_STATUS,
                progress: task.progress,
                estimate: task.estimate
            };
        });
    }

    // Mirrors formatSlackReport() in slack-report.php, including the em-space indent.
    function slackPreviewText(draft) {
        const indent = '  ◦ ';
        const tasks = draftTodayTasks(draft).map(function (task) {
            const lines = [
                `• *Issue:* \`${task.issue || 'Không có'}\``,
                `${indent}*Công việc:* ${task.content.replace(/\r\n/g, '\n').replace(/\n/g, '\n\u2003\u2003\u2003')}`,
                `${indent}*Loại:* ${task.work_type}`,
                `${indent}*Trạng thái:* ${task.status}`,
                `${indent}*Tiến độ:* ${task.progress === '' ? 'Chưa cập nhật' : task.progress + '%'}`
            ];
            if (task.estimate) lines.push(`${indent}*Ngày dự kiến:* ${task.estimate}`);
            return lines.join('\n');
        }).join('\n\n');
        return [
            '*BÁO CÁO CÔNG VIỆC HẰNG NGÀY*',
            '',
            `*Người báo cáo:* \`${$('#dailyReportForm').attr('data-reporter') || ''}\``,
            `*Ngày:* \`${(draft.reportDate || '').replaceAll('-', '/')}\``,
            '',
            '*1. CÔNG VIỆC TRONG NGÀY*',
            '',
            tasks || '(Chưa có task)',
            '',
            '*2. TỰ ĐÁNH GIÁ KẾT QUẢ & CHẤT LƯỢNG*',
            '',
            `*Kết quả hôm nay:* ${draft.dailyResult || 'Không có.'}`,
            '',
            `*Đánh giá:* ${QUALITY_LABELS[draft.quality] || 'Khá'}`,
            '',
            '*3. CẢM XÚC & TINH THẦN*',
            '',
            `*Mức độ:* ${SPIRIT_LABELS[draft.spirit] || 'Bình thường'}`,
            '',
            '*4. CHIA SẺ THÊM*',
            '',
            draft.note || 'Không có.'
        ].join('\n');
    }

    // Renders Slack mrkdwn (*bold* and `code`) so the preview looks like the posted message.
    function slackMrkdwnToHtml(text) {
        return escapeHtml(text)
            .replace(/`([^`\n]+)`/g, '<code class="rounded bg-zinc-100 px-1 py-0.5 text-[12px] text-rose-600">$1</code>')
            .replace(/\*([^*\n]+)\*/g, '<strong class="text-zinc-900">$1</strong>');
    }

    function slackPreviewHtml(draft) {
        return `<div class="rounded-lg border border-zinc-200 bg-white p-3"><div class="whitespace-pre-wrap break-words text-left text-[13px] leading-relaxed text-zinc-700">${slackMrkdwnToHtml(slackPreviewText(draft))}</div></div>`;
    }

    // Mirrors formatTasks() + the cardV2 payload in send-webhook.php.
    function googleChatPreviewHtml(draft) {
        const multiline = value => escapeHtml(value).replace(/\r\n/g, '\n').replace(/\n/g, '<br>&nbsp;&nbsp;');
        const today = draftTodayTasks(draft).map(function (task) {
            const issue = task.issue ? `<strong>[${escapeHtml(task.issue)}]</strong> ` : '';
            const meta = [escapeHtml(task.work_type), escapeHtml(task.status)];
            meta.push(task.progress === '' ? 'chưa cập nhật' : escapeHtml(task.progress) + '%');
            if (task.estimate) meta.push('dự kiến ' + task.estimate.split('-').slice(1).reverse().join('/'));
            return `<li>- ${issue}${multiline(task.content)} <span class="text-zinc-400">· ${meta.join(' · ')}</span></li>`;
        }).join('');
        const tomorrow = draft.tomorrowTasks.filter(task => String(task.content || '').trim()).map(function (task) {
            return `<li>- <strong>[${task.type === 'continue' ? 'Continue' : 'New'}]</strong> ${multiline(task.content)}</li>`;
        }).join('');
        const section = (title, body) => `<p class="mt-3 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">${title}</p>${body}`;
        const reporter = $('#dailyReportForm').attr('data-reporter') || '';
        return `<div class="rounded-lg border border-zinc-200 bg-white p-3 text-left text-[13px] text-zinc-700">
            <div class="border-b border-zinc-100 pb-2"><div class="font-semibold text-zinc-950">📋 Daily Report · ${escapeHtml(draft.project)}</div><div class="mt-0.5 text-xs text-zinc-500">${escapeHtml(formatDisplayDate(draft.reportDate))} (GMT+7)${reporter ? ' · ' + escapeHtml(reporter) : ''}</div></div>
            ${section('📝 Task hôm nay', `<ul class="mt-1 space-y-1">${today || '<li class="text-zinc-400">Chưa có task</li>'}</ul>`)}
            ${tomorrow ? section('📅 Kế hoạch tiếp theo', `<ul class="mt-1 space-y-1">${tomorrow}</ul>`) : ''}
            ${draft.dailyResult ? section('🎯 Kết quả hôm nay', `<p class="mt-1 whitespace-pre-wrap">${escapeHtml(draft.dailyResult)}</p>`) : ''}
            <div class="mt-3 grid grid-cols-2 gap-3 border-t border-zinc-100 pt-2">
                <div><div class="text-[11px] text-zinc-500">Chất lượng</div><div>${escapeHtml(GC_QUALITY_LABELS[draft.quality] || GC_QUALITY_LABELS[3])}</div></div>
                <div><div class="text-[11px] text-zinc-500">Tinh thần</div><div>${escapeHtml(GC_SPIRIT_LABELS[draft.spirit] || GC_SPIRIT_LABELS[3])}</div></div>
            </div>
            ${draft.note ? section('🗒️ Chia sẻ thêm', `<p class="mt-1 whitespace-pre-wrap">${escapeHtml(draft.note)}</p>`) : ''}
        </div>`;
    }

    function previewHtml() {
        const draft = collectDraft();
        const channels = [
            { key: 'slack', label: 'Slack', on: draft.submitSlack, body: slackPreviewHtml(draft) },
            { key: 'google_chat', label: 'Google Chat', on: draft.submitGoogleChat, body: googleChatPreviewHtml(draft) }
        ];
        // Open on a channel that will actually be sent, otherwise fall back to Slack.
        const activeKey = (channels.find(channel => channel.on) || channels[0]).key;
        const tabs = channels.map(function (channel) {
            const active = channel.key === activeKey;
            const state = active
                ? 'border-zinc-900 bg-zinc-900 text-white'
                : 'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50';
            const mark = channel.on ? '' : ' <span class="opacity-60">(không gửi)</span>';
            return `<button type="button" class="preview-tab rounded-md border px-3 py-1.5 text-xs font-medium ${state}" data-channel="${channel.key}">${channel.label}${mark}</button>`;
        }).join('');
        const panels = channels.map(function (channel) {
            return `<div class="preview-panel${channel.key === activeKey ? '' : ' hidden'}" data-channel="${channel.key}">${channel.body}</div>`;
        }).join('');
        const sent = [draft.submitGoogleForm && 'RCNV logtime', draft.submitGoogleChat && 'Google Chat', draft.submitSlack && 'Slack']
            .filter(Boolean).join(', ') || 'Chưa chọn';
        return `<div class="text-left">
            <div class="mb-3 flex gap-2">${tabs}</div>
            ${panels}
            <p class="mt-3 text-xs text-zinc-500">Submit kèm: ${escapeHtml(sent)}</p>
        </div>`;
    }

    $(document).on('click', '.preview-tab', function () {
        const key = $(this).data('channel');
        $('.preview-tab')
            .removeClass('border-zinc-900 bg-zinc-900 text-white')
            .addClass('border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50');
        $(this)
            .removeClass('border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50')
            .addClass('border-zinc-900 bg-zinc-900 text-white');
        $('.preview-panel').addClass('hidden').filter('[data-channel="' + key + '"]').removeClass('hidden');
    });

    $('#previewReport').on('click', function () {
        Swal.fire({ title: 'Xem trước báo cáo', html: previewHtml(), confirmButtonText: 'Đóng', width: 720 });
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

        if (!/^\d{4}-\d{2}-\d{2}$/.test($('#reportDate').val() || '')) {
            Swal.fire({ icon: 'warning', text: 'Vui lòng chọn ngày báo cáo.' });
            return;
        }
        const needsTasks = $('#submitGoogleChat, #submitSlack').is(':checked');
        if (needsTasks && !collectDraft().todayTasks.some(task => String(task.content || '').trim())) {
            Swal.fire({ icon: 'warning', title: 'Cảnh báo', text: 'Cần ít nhất 1 task hôm nay!', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            return;
        }

        if (!$('#submitGoogleForm, #submitGoogleChat, #submitSlack').is(':checked')) {
            Swal.fire({ icon: 'warning', text: 'Chọn ít nhất một mục trong Submit kèm.' });
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
        if ($('#submitSlack').prop('checked') && !($('#dailyReportForm').attr('data-reporter') || '').trim()) {
            Swal.fire({ icon: 'warning', text: 'Vui lòng nhập và lưu người báo cáo trong Thiết lập.' });
            setSettingsOpen(true);
            $('#reporterSetting').trigger('focus');
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
                const labels = {logtime: 'RCNV logtime', google_chat: 'Google Chat', slack: 'Slack'};
                const resultText = res.statuses ? Object.entries(res.statuses).filter(([, status]) => status !== 'skipped').map(([channel, status]) => `${labels[channel]}: ${status === 'ok' ? 'Thành công' : 'Thất bại'}`).join(' · ') : res.message;
                if (res.partial) {
                    // Retry only failed channels to avoid duplicate reports or logtime.
                    for (const [channel, id] of Object.entries({logtime: 'submitGoogleForm', google_chat: 'submitGoogleChat', slack: 'submitSlack'})) {
                        if (res.statuses[channel] === 'ok') $('#' + id).prop('checked', false);
                    }
                    syncSubmitRequirements();
                    saveDraft();
                    Swal.fire({ icon: 'warning', title: 'Đã gửi một phần', text: resultText + '. Đã bỏ chọn mục thành công; bạn có thể gửi lại mục lỗi.' });
                    return;
                }
                if (res.success) {
                    localStorage.removeItem(draftKey);
                    $('#draftStatus').text('Đã gửi · bản nháp đã được xóa');
                    $('#clearDraft').addClass('hidden');
                    Swal.fire({
                        icon: 'success',
                        title: 'Đã gửi báo cáo',
                        text: resultText,
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
                        text: resultText || 'Có lỗi xảy ra!',
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
                    $('#dailyReportForm').attr('data-reporter', $('#reporterSetting').val().trim());
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
