$(document).ready(function () {
    const draftKey = 'daily-report-draft-v1';
    let draftTimer = null;

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

    // http://daily-report.local is not a secure context, so navigator.clipboard is undefined there.
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
        if (input.dataset.customDate === 'true' || (input.closest('.swal2-container') && input.dataset.allowCustom !== 'true')) return;
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

    // Switching project invalidates the WePRO tasks already pulled into the form.
    let lastProject = $('#project').val() || '';
    $('#project').on('change', async function () {
        const next = $(this).val() || '';
        if (next === lastProject) return;

        const hasContent = $('#tasks-today-list .task-today-item, #tasks-tomorrow-list .task-tomorrow-item')
            .toArray().some(el => String($(el).find('[name*="[content]"]').val() || '').trim());
        if (hasContent) {
            const result = await Swal.fire({
                title: 'Đổi project?',
                html: '<p class="dr-modal-note">Toàn bộ task hôm nay, task ngày mai và thông tin WePRO đang gắn sẽ bị xóa để nhập lại theo project <b>' + escapeHtml(next) + '</b>.</p>',
                icon: 'warning',
                buttonsStyling: false,
                customClass: {
                    popup: 'dr-swal',
                    title: 'dr-swal-title',
                    htmlContainer: 'dr-swal-html',
                    actions: 'dr-modal-actions',
                    confirmButton: 'dr-modal-confirm',
                    cancelButton: 'dr-modal-cancel'
                },
                showCancelButton: true,
                confirmButtonText: 'Xóa và đổi project',
                cancelButtonText: 'Giữ nguyên'
            });
            if (!result.isConfirmed) {
                $(this).val(lastProject);
                updateProjectLogo();
                return;
            }
            $('#tasks-today-list, #tasks-tomorrow-list').empty();
            $('#tasks-today-list').append(taskTodayHtml(taskTodayIdx++));
            $('#tasks-tomorrow-list').append(taskTomorrowHtml(taskTomorrowIdx++));
            scheduleDraftSave();
        }
        lastProject = next;
        updateProjectLogo();
        scheduleDraftSave();
    });
    updateProjectLogo();

    // ===== TASK HÔM NAY =====
    let weproTasks = [];
    const TASK_STATUSES = ['Chưa bắt đầu', 'Đang thực hiện', 'Chờ review', 'Tạm hoãn', 'Hoàn thành'];
    const DEFAULT_TASK_STATUS = 'Đang thực hiện';

    function taskTodayHtml(idx) {
        let options = '';
        // Đổ từ 100% xuống 0% để mức hay chọn nhất nằm ngay đầu danh sách.
        for (let i = 100; i >= 0; i -= 10) {
            options += `<option value="${i}">${i}%</option>`;
        }

        return `<div class="task-today-item task-sortable dr-taskrow" data-idx="${idx}">
            <button type="button" class="task-drag-handle dr-taskrow-drag" title="Kéo để sắp xếp" aria-label="Kéo để sắp xếp">⋮⋮</button>
            <input type="text" name="tasks_today[${idx}][issue_no]" class="dr-input dr-col-issue" aria-label="Issue" placeholder="Issue" />
            <textarea name="tasks_today[${idx}][content]" rows="1" class="dr-input dr-col-title" aria-label="Task" placeholder="Mô tả công việc..." required></textarea>
            <select name="tasks_today[${idx}][work_type]" class="dr-input dr-col-type" aria-label="Activity">${workTypeOptionsHtml('')}</select>
            <select name="tasks_today[${idx}][progress]" class="dr-input dr-col-progress" aria-label="Tiến độ" required>
                <option value="">--%</option>${options}
            </select>
            <input type="number" name="tasks_today[${idx}][hours]" class="dr-input dr-col-hours" min="0" max="24" step="0.5" aria-label="Giờ log WePRO" placeholder="Giờ" />
            <button type="button" class="remove-task dr-taskrow-remove" title="Xóa task" aria-label="Xóa task">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <div class="dr-taskrow-wepro hidden">
                <a class="wepro-timelog" href="#" target="_blank" rel="noopener noreferrer">⏱ Logtime WePRO</a>
                <span class="dr-taskrow-wepro-code"></span>
            </div>
        </div>`;
    }

    function syncSubmitRequirements() {
        const needsTasks = $('#submitGoogleChat, #submitSlack').is(':checked');
        $('#tasks-today-list [name*="[content]"], #tasks-today-list select[name*="[progress]"]').prop('required', needsTasks);
    }
    $('#submitGoogleChat, #submitSlack').on('change', syncSubmitRequirements);

    // "Chỉ lưu lịch sử" and the three send switches exclude each other.
    $('#historyOnly').on('change', function () {
        if (this.checked) $('#submitGoogleForm, #submitGoogleChat, #submitSlack').prop('checked', false).trigger('change');
    });
    $('#submitGoogleForm, #submitGoogleChat, #submitSlack').on('change', function () {
        if (this.checked && $('#historyOnly').prop('checked')) $('#historyOnly').prop('checked', false).trigger('change');
    });

    function updateTodayTaskState() {
        syncSubmitRequirements();
    }

    // The report sends no status of its own; it follows the progress.
    function statusFromProgress(progress) {
        if (progress === '' || progress === undefined || progress === null) return DEFAULT_TASK_STATUS;
        const p = Number(progress);
        return p >= 100 ? 'Hoàn thành' : p <= 0 ? 'Chưa bắt đầu' : DEFAULT_TASK_STATUS;
    }

    // ===== ACTIVITY (cột Loại) =====
    // WePRO only exposes its activity list inside a task's timelog form, so it is fetched once
    // through any known task and cached; until then the built-in work type list stands in.
    const WEPRO_ACTIVITY_KEY = 'dr-wepro-activities-v1';
    // WePRO's own dropdown order; also the list used until WePRO's is fetched.
    const ACTIVITY_ORDER = ['Analytic', 'Research', 'Design', 'Communicate', 'Coding', 'Self-Check', 'Cross-Test', 'Fixbug-Cross',
        'Leader-Test', 'Fixbug-Leader', 'Fixbug-BSE', 'Deploy-Server', 'Fixbug-Released', 'Support', 'Other Meeting', 'Other Task'];
    function workTypeLabels() {
        let labels = ACTIVITY_ORDER;
        try {
            const cached = JSON.parse(localStorage.getItem(WEPRO_ACTIVITY_KEY) || '[]');
            if (Array.isArray(cached) && cached.length) labels = cached.map(a => a.label);
        } catch (e) { /* ignore */ }
        const rank = l => { const i = ACTIVITY_ORDER.indexOf(l); return i === -1 ? ACTIVITY_ORDER.length : i; };
        return labels.slice().sort((a, b) => rank(a) - rank(b));
    }

    // Starts empty so the activity is a conscious pick. A value outside the list (old drafts,
    // bulk paste) is kept as an extra option, not dropped.
    function workTypeOptionsHtml(selected) {
        const labels = workTypeLabels();
        const list = selected && !labels.includes(selected) ? labels.concat(selected) : labels;
        return '<option value="">— Chọn —</option>'
            + list.map(l => '<option value="' + escapeHtml(l) + '"' + (l === selected ? ' selected' : '') + '>' + escapeHtml(l) + '</option>').join('');
    }
    $(document).on('change', 'select[name*="[work_type]"]', function () { $(this).closest('.dr-select').removeClass('is-invalid'); });

    function weproActivitiesStore(activities) {
        if (!Array.isArray(activities) || !activities.length) return;
        try { localStorage.setItem(WEPRO_ACTIVITY_KEY, JSON.stringify(activities)); } catch (e) { /* ignore */ }
        $('#tasks-today-list select[name*="[work_type]"]').each(function () { $(this).html(workTypeOptionsHtml($(this).val())); });
    }

    let weproActivitiesLoading = false;
    function weproEnsureActivities() {
        try { if (JSON.parse(localStorage.getItem(WEPRO_ACTIVITY_KEY) || '[]').length) return; } catch (e) { /* refetch */ }
        if (weproActivitiesLoading) return;
        const cached = weproCacheGet($('#project').val() || '');
        const id = Number($('#tasks-today-list .dr-taskrow').map(function () { return $(this).data('weproId'); }).get()[0])
            || (cached && cached.tasks[0] ? Number(cached.tasks[0].id) : 0);
        if (!id) return;
        weproActivitiesLoading = true;
        weproTimelogAjax({ action: 'form', 'task_ids[]': id })
            .done(function (res) { if (res && res.success) weproActivitiesStore(res.activities); })
            .always(function () { weproActivitiesLoading = false; });
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

    function getEmptyTodayItem() {
        let $emptyItem = $();
        $('#tasks-today-list .task-today-item').each(function () {
            const $item = $(this);
            const content = $item.find('[name*="[content]"]').val().trim();
            const progress = $item.find('select[name*="[progress]"]').val();
            const hours = $item.find('input[name*="[hours]"]').val();
            const issue = $item.find('input[name*="[issue_no]"]').val().trim();
            if (!content && !progress && !hours && !issue) {
                $emptyItem = $item;
                return false;
            }
        });

        return $emptyItem;
    }

    function addTodayTask(task) {
        let $item = getEmptyTodayItem();
        if (!$item.length) {
            $('#tasks-today-list').append(taskTodayHtml(taskTodayIdx++));
            $item = $('#tasks-today-list .task-today-item').last();
        }

        const split = splitIssueFromTitle(task.content);
        const issue = String(task.issue_no || task.issue || split.issue || '').trim();
        $item.find('select[name*="[work_type]"]').html(workTypeOptionsHtml(task.work_type || ''));
        $item.find('input[name*="[issue_no]"]').val(issue);
        $item.find('[name*="[content]"]').val(split.content);
        $item.find('select[name*="[progress]"]').val(task.progress);
        $item.find('input[name*="[hours]"]').val(task.hours || '');
        autoGrow($item.find('[name*="[content]"]')[0]);
        setWeproLink($item, task.wepro_id, task.wepro_timelog_url, task.wepro_code);
        updateTodayTaskState($item);
    }

    // Rows typed by hand have no WePRO task behind them, so the line stays hidden.
    function setWeproLink($item, weproId, timelogUrl, code) {
        const $line = $item.find('.dr-taskrow-wepro');
        if (!weproId || !timelogUrl) {
            $line.addClass('hidden');
            $item.removeData('weproId').removeData('weproTimelogUrl').removeData('weproCode');
            return;
        }
        $item.data('weproId', weproId).data('weproTimelogUrl', timelogUrl).data('weproCode', code || '');
        $line.find('.wepro-timelog').attr('href', timelogUrl);
        $line.find('.dr-taskrow-wepro-code').text(code ? '· ' + code : '');
        $line.removeClass('hidden');
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
    // ===== WEPRO: chọn task từ WePRO =====
    function weproCsrf() {
        return $('input[name="csrf_token"]').first().val();
    }

    // A 504 here means the WePRO fetch outran nginx, not that the endpoint is missing.
    function weproAjaxError(xhr) {
        if (xhr && xhr.status === 504) {
            return 'WePRO trả dữ liệu quá lâu nên bị timeout (504). Thử lại, hoặc tắt "Lấy cả task con" trong Thiết lập để nhẹ hơn.';
        }
        if (xhr && xhr.status) {
            return 'Gọi wepro-tasks.php lỗi HTTP ' + xhr.status + '.';
        }
        return 'Không gọi được wepro-tasks.php (mất kết nối).';
    }

    const WEPRO_CACHE_KEY = 'dr-wepro-tasks-v1';

    // Cached per project: the WePRO fetch runs ~30s, far too slow to repeat on every open.
    function weproCacheRead() {
        try {
            return JSON.parse(localStorage.getItem(WEPRO_CACHE_KEY) || '{}') || {};
        } catch (e) {
            return {};
        }
    }

    function weproCacheGet(project) {
        const entry = weproCacheRead()[project];
        return entry && Array.isArray(entry.tasks) ? entry : null;
    }

    function weproCacheSet(project, tasks) {
        try {
            const all = weproCacheRead();
            all[project] = { tasks, fetchedAt: new Date().toISOString() };
            localStorage.setItem(WEPRO_CACHE_KEY, JSON.stringify(all));
        } catch (e) {
            // A full or blocked localStorage only costs us the cache, not the feature.
        }
    }

    function weproCacheClear(project) {
        try {
            const all = weproCacheRead();
            delete all[project];
            localStorage.setItem(WEPRO_CACHE_KEY, JSON.stringify(all));
        } catch (e) { /* ignore */ }
    }

    function fetchWeproTasks() {
        return $.ajax({
            url: 'wepro-tasks.php',
            method: 'POST',
            dataType: 'json',
            data: { csrf_token: weproCsrf(), project: $('#project').val() || '' }
        });
    }

    function weproFetchedLabel(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return ' · cập nhật ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    const WEPRO_STATUS = {
        new: { label: 'Mới', cls: 'st-new' },
        doing: { label: 'Đang làm', cls: 'st-doing' },
        on_hold: { label: 'Tạm hoãn', cls: 'st-hold' },
        completed: { label: 'Xong', cls: 'st-done' }
    };
    function weproInReportIds() {
        return $('#tasks-today-list .dr-taskrow').map(function () { return Number($(this).data('weproId')) || 0; }).get().filter(Boolean);
    }
    function weproTaskItemHtml(task, index, isChild, hasChildren, inReport) {
        const en = task.title_en && task.title_en !== task.title
            ? '<span class="dr-wepro-en">' + escapeHtml(task.title_en) + '</span>'
            : '';
        const st = WEPRO_STATUS[task.status] || null;
        const pct = Math.max(0, Math.min(100, Number(task.percent) || 0));
        const pctCls = pct >= 100 ? 'is-done' : pct > 0 ? 'is-doing' : '';
        const shortUrl = task.url ? String(task.url).replace(/^https?:\/\//, '') : '';
        return '<label class="dr-wepro-item' + (isChild ? ' is-child' : '') + (hasChildren ? ' is-parent' : '') + (inReport ? ' in-report' : '') + '" data-idx="' + index + '" data-status="' + escapeHtml(task.status || '') + '">'
            + '<input type="checkbox" class="dr-wepro-check" value="' + index + '">'
            + '<span class="dr-wepro-body">'
            + '<span class="dr-wepro-meta">'
            + '<span class="dr-wepro-code">' + escapeHtml(task.short_code) + '</span>'
            + (st ? '<span class="dr-wepro-status ' + st.cls + '">' + st.label + '</span>' : '')
            + (hasChildren ? '<span class="dr-wepro-badge">' + (task.subtask_count || '') + ' task con</span>' : '')
            + (inReport ? '<span class="dr-wepro-inreport">✓ đã có trong report</span>' : '')
            + '</span>'
            + '<span class="dr-wepro-title">' + escapeHtml(task.title) + '</span>'
            + en
            + (task.url ? '<a class="dr-wepro-link" href="' + escapeHtml(task.url) + '" target="_blank" rel="noopener noreferrer" title="Mở task trên WePRO">' + escapeHtml(shortUrl) + ' ↗</a>' : '')
            + '</span>'
            + '<span class="dr-wepro-pct ' + pctCls + '" title="Tiến độ ' + pct + '%"><span class="dr-wepro-pct-bar"><i style="width:' + pct + '%"></i></span><b>' + pct + '%</b></span>'
            + '<span class="dr-wepro-side">'
            + (hasChildren ? '<button type="button" class="dr-wepro-fold" aria-expanded="true" title="Thu gọn task con">▾</button>' : '')
            + '</span>'
            + '</label>';
    }

    // Only some parent-child links are discoverable, so anything unmapped stays in a flat group.
    function weproTaskListHtml(tasks) {
        const indexOf = new Map();
        tasks.forEach(function (task, i) { indexOf.set(task.id, i); });

        const childrenOf = new Map();
        tasks.forEach(function (task) {
            if (task.parent_id && indexOf.has(task.parent_id)) {
                if (!childrenOf.has(task.parent_id)) childrenOf.set(task.parent_id, []);
                childrenOf.get(task.parent_id).push(task);
            }
        });

        const byCode = (a, b) => String(a.short_code).localeCompare(String(b.short_code));
        const parents = tasks.filter(t => childrenOf.has(t.id)).sort(byCode);
        const rest = tasks
            .filter(t => !childrenOf.has(t.id) && !(t.parent_id && indexOf.has(t.parent_id)))
            .sort(byCode);

        const inReport = weproInReportIds();
        let html = '';
        if (parents.length) {
            html += '<div class="dr-wepro-group-label">Task cha và task con <span>' + parents.length + ' nhóm</span></div>';
            parents.forEach(function (parent) {
                html += weproTaskItemHtml(parent, indexOf.get(parent.id), false, true, inReport.includes(parent.id));
                html += '<div class="dr-wepro-children">';
                childrenOf.get(parent.id).sort(byCode).forEach(function (child) {
                    html += weproTaskItemHtml(child, indexOf.get(child.id), true, false, inReport.includes(child.id));
                });
                html += '</div>';
            });
        }
        if (rest.length) {
            html += '<div class="dr-wepro-group-label">Task lẻ <span>' + rest.length + '</span></div>';
            rest.forEach(function (task) {
                html += weproTaskItemHtml(task, indexOf.get(task.id), false, false, inReport.includes(task.id));
            });
        }
        return html;
    }

    // WePRO percent is free-form; the report select only offers steps of 10.
    function roundToStep(percent) {
        return String(Math.min(100, Math.max(0, Math.round(Number(percent) / 10) * 10)));
    }

    function weproRenderList(tasks, fetchedAt) {
        weproTasks = tasks;
        $('#weproPickNote').text('Tick task muốn đưa vào report (' + tasks.length + ' task)' + weproFetchedLabel(fetchedAt));
        $('#weproPickList').html(weproTaskListHtml(tasks));
        $('#weproPickTools').removeClass('hidden');
        Swal.getConfirmButton().disabled = false;
        weproSyncPickCount();
        weproEnsureActivities();
    }

    function weproLoadInto(force) {
        const project = $('#project').val() || '';
        const cached = force ? null : weproCacheGet(project);
        if (cached) {
            weproRenderList(cached.tasks, cached.fetchedAt);
            return;
        }
        Swal.getConfirmButton().disabled = true;
        $('#weproPickTools').addClass('hidden');
        $('#weproPickList').empty();
        $('#weproPickNote').text('Đang tải task từ WePRO, khoảng 10 giây...');
        fetchWeproTasks()
            .done(function (res) {
                if (!res || !res.success) {
                    $('#weproPickNote').text((res && res.message) || 'Không lấy được task từ WePRO.');
                    return;
                }
                if (!res.tasks.length) {
                    $('#weproPickNote').text('WePRO không trả về task nào đang mở.');
                    return;
                }
                weproCacheSet(project, res.tasks);
                weproRenderList(res.tasks, new Date().toISOString());
            })
            .fail(function (xhr) {
                $('#weproPickNote').text(weproAjaxError(xhr));
            });
    }

    $(document).on('click', '#weproRefreshBtn', function () {
        weproCacheClear($('#project').val() || '');
        weproLoadInto(true);
    });

    function weproApplyFilter() {
        const needle = ($('#weproPickSearch').val() || '').trim().toLowerCase();
        const status = $('#weproPickStatus .is-active').data('status') || 'all';
        const $items = $('#weproPickList .dr-wepro-item');
        $items.each(function () {
            // A ticked task stays visible so a filter never hides the current selection.
            const ticked = $(this).find('.dr-wepro-check').prop('checked');
            const textMiss = needle !== '' && $(this).text().toLowerCase().indexOf(needle) === -1;
            const statusMiss = status !== 'all' && $(this).data('status') !== status;
            $(this).toggleClass('hidden', !ticked && (textMiss || statusMiss));
        });
        // A parent and its children stay together: a matching parent shows all its children,
        // a matching child shows its parent. Either way the group is unfolded so the hit is visible.
        $('#weproPickList .dr-wepro-children').each(function () {
            const $parent = $(this).prev('.dr-wepro-item');
            const anyVisible = $(this).find('.dr-wepro-item').not('.hidden').length > 0;
            if (needle !== '' && !$parent.hasClass('hidden')) {
                $(this).find('.dr-wepro-item').removeClass('hidden');
            } else if (anyVisible) {
                $parent.removeClass('hidden');
            }
            if (needle !== '' && (anyVisible || !$parent.hasClass('hidden'))) {
                weproSetFolded($parent, false);
            }
        });
        weproSyncPickCount();
    }
    $(document).on('input', '#weproPickSearch', weproApplyFilter);
    $(document).on('click', '#weproPickStatus .dr-chip', function () {
        $('#weproPickStatus .dr-chip').removeClass('is-active'); $(this).addClass('is-active');
        weproApplyFilter();
    });
    $(document).on('change', '#weproPickList .dr-wepro-check', function () {
        $(this).closest('.dr-wepro-item').toggleClass('is-checked', this.checked);
        weproSyncPickCount();
    });
    $(document).on('click', '#weproPickAll', function () {
        $('#weproPickList .dr-wepro-item').not('.hidden').find('.dr-wepro-check').prop('checked', true).trigger('change');
    });
    $(document).on('click', '#weproPickNone', function () {
        $('#weproPickList .dr-wepro-check:checked').prop('checked', false).trigger('change');
    });
    function weproSyncPickCount() {
        const n = $('#weproPickList .dr-wepro-check:checked').length;
        const $btn = $(Swal.getConfirmButton());
        if ($btn.length && $('#weproPickList').length) $btn.text(n ? 'Thêm ' + n + ' task vào report' : 'Thêm vào report');
        $('#weproPickCount').text(n ? n + ' đã chọn' : '');
    }

    function weproSetFolded($parent, folded) {
        $parent.next('.dr-wepro-children').toggleClass('is-folded', folded);
        $parent.find('.dr-wepro-fold').attr('aria-expanded', String(!folded)).text(folded ? '▸' : '▾');
    }

    // The link sits inside the <label>: open it without toggling the checkbox.
    $(document).on('click', '.dr-wepro-link', function (event) { event.stopPropagation(); });

    // The button sits inside the <label>, so stop the click from toggling the checkbox.
    $(document).on('click', '.dr-wepro-fold', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const $parent = $(this).closest('.dr-wepro-item');
        weproSetFolded($parent, !$parent.next('.dr-wepro-children').hasClass('is-folded'));
    });

    // The create form replaces the picker (one Swal at a time); closing it reopens the picker,
    // reloaded from WePRO when something was created.
    let weproCreator = null;
    $(document).on('click', '#weproCreateBtn', function () {
        weproCreator = weproCreator || window.WeproCreate({
            createAjax: data => $.ajax({ url: 'wepro-task-create.php', method: 'POST', dataType: 'json', data: $.extend({ csrf_token: weproCsrf(), project: $('#project').val() || '' }, data) }),
            parentList: () => $.Deferred().resolve(weproTasks).promise(),
            swalClass: WEPRO_SWAL_CLASS,
            ajaxError: xhr => xhr && xhr.status ? 'Gọi wepro-task-create.php lỗi HTTP ' + xhr.status + '.' : 'Không gọi được wepro-task-create.php (mất kết nối).',
            onCreated: function () {}
        });
        weproCreator.open({
            onClose: function (created) {
                if (created.length) weproCacheClear($('#project').val() || '');
                weproOpenPicker(created.length > 0);
            }
        });
    });

    $('#wepro-pick-task').click(function () { weproOpenPicker(false); });
    function weproOpenPicker(force) {
        Swal.fire({
            title: 'Chọn task từ WePRO',
            html: '<p class="dr-modal-note" id="weproPickNote">Đang tải...</p>'
                + '<div id="weproPickTools" class="dr-wepro-tools hidden">'
                + '<div class="dr-wepro-tools-row">'
                + '<input type="text" id="weproPickSearch" class="dr-wepro-search" placeholder="🔍 Lọc theo mã hoặc tên task..." autocomplete="off">'
                + '<button type="button" id="weproCreateBtn" class="dr-action" title="Tạo task mới trên WePRO">＋ Tạo task</button>'
                + '<button type="button" id="weproRefreshBtn" class="dr-action" title="Tải lại từ WePRO">⟳ Làm mới</button>'
                + '</div>'
                + '<div class="dr-wepro-tools-row">'
                + '<span id="weproPickStatus" class="dr-wepro-chips">'
                + '<button type="button" class="dr-chip is-active" data-status="all">Tất cả</button>'
                + '<button type="button" class="dr-chip st-doing" data-status="doing">Đang làm</button>'
                + '<button type="button" class="dr-chip st-new" data-status="new">Mới</button>'
                + '<button type="button" class="dr-chip st-hold" data-status="on_hold">Tạm hoãn</button>'
                + '</span>'
                + '<span class="dr-wepro-tools-right"><span id="weproPickCount" class="dr-wepro-count"></span>'
                + '<button type="button" id="weproPickAll" class="dr-wepro-mini">Chọn hết đang hiện</button>'
                + '<button type="button" id="weproPickNone" class="dr-wepro-mini">Bỏ chọn</button></span>'
                + '</div>'
                + '</div>'
                + '<div id="weproPickList" class="dr-wepro-list"></div>',
            buttonsStyling: false,
            customClass: {
                popup: 'dr-swal dr-swal-wide',
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
                weproLoadInto(force);
            },
            preConfirm: function () {
                const picked = $('#weproPickList .dr-wepro-check:checked').map(function () {
                    return weproTasks[Number(this.value)];
                }).get();
                if (!picked.length) {
                    Swal.showValidationMessage('Chọn ít nhất 1 task.');
                    return false;
                }
                return picked;
            }
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }
            result.value.forEach(function (task) {
                addTodayTask({
                    wepro_id: task.id,
                    wepro_timelog_url: task.timelog_url,
                    wepro_code: task.full_code,
                    issue_no: task.short_code,
                    content: task.title,
                    progress: roundToStep(task.percent)
                });
            });
            scheduleDraftSave();
        });
    }

    // ===== UI: progress strip, optional cards, more-menu, compact columns, status =====
    function syncProgress() {
        const hasTask = $('#tasks-today-list [name*="[content]"]').toArray().some(el => String($(el).val() || '').trim());
        const reviewed = !!$('#quality').val() && !!$('#spirit').val();
        const sending = $('#submitGoogleForm, #submitGoogleChat, #submitSlack, #historyOnly').is(':checked');
        $('#formProgress [data-target="card-tasks"]').toggleClass('is-done', hasTask);
        $('#formProgress [data-target="card-review"]').toggleClass('is-done', reviewed);
        $('#formProgress [data-target="card-send"]').toggleClass('is-done', sending);
    }
    $('#formProgress').on('click', '.dr-progress-step', function () {
        const target = document.getElementById($(this).data('target'));
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    $(document).on('input change', '#tasks-today-list, #submitGoogleForm, #submitGoogleChat, #submitSlack, #historyOnly', syncProgress);
    $('#quality-list, #spirit-list').on('click', 'button', syncProgress);

    // Optional cards start hidden; a chip opens one, the × in its head closes it again.
    function showOptional(id, show) {
        $('#' + id).toggleClass('hidden', !show);
        $('.dr-chip[data-optional="' + id + '"]').toggleClass('hidden', show);
        $('#optionalBar').toggleClass('hidden', $('.dr-chip').not('.hidden').length === 0);
    }
    $('#optionalBar').on('click', '.dr-chip', function () {
        const id = $(this).data('optional');
        showOptional(id, true);
        const $card = $('#' + id);
        $card[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        $card.find('textarea, input[type="text"]').first().trigger('focus');
    });
    $('.dr-optional .dr-card-head').append('<button type="button" class="dr-card-close" title="Ẩn phần này" aria-label="Ẩn phần này">×</button>');
    $(document).on('click', '.dr-card-close', function () {
        showOptional($(this).closest('.dr-optional').attr('id'), false);
    });
    function syncOptionalCards() {
        const hasPlan = $('#tasks-tomorrow-list [name*="[content]"]').toArray().some(el => String($(el).val() || '').trim());
        const hasNote = String($('#dailyReportForm textarea[name="note"]').val() || '').trim() !== '';
        if (hasPlan) showOptional('card-plan', true);
        if (hasNote) showOptional('card-share', true);
    }

    function syncSubmitLabel() {
        if ($('#submitBtn').prop('disabled')) return;
        if ($('#historyOnly').prop('checked')) { $('#submitText').text('Lưu vào lịch sử (không gửi)'); return; }
        const targets = [
            $('#submitGoogleForm').prop('checked') && 'Logtime',
            $('#submitGoogleChat').prop('checked') && 'Chat',
            $('#submitSlack').prop('checked') && 'Slack'
        ].filter(Boolean);
        $('#submitText').text(targets.length ? 'Gửi báo cáo → ' + targets.join(' + ') : 'Chọn nơi gửi trước');
    }
    $('#submitGoogleForm, #submitGoogleChat, #submitSlack, #historyOnly').on('change', syncSubmitLabel);

    let weproStatusLast = null;
    function setWeproStatus(state, text, title) {
        $('#weproStatus').removeClass('hidden ok err wait off noproj').addClass(state).attr('title', title || text)
            .find('.dr-status-text').text('WePRO: ' + text);
    }
    // WePRO buttons and the per-task timelog line only show when this project can actually call WePRO.
    function setWeproFeatures(usable) {
        $('body').toggleClass('dr-wepro-off', !usable);
        $('#wepro-pick-task').toggleClass('hidden', !usable);
        syncWeproButtons();
    }
    function syncWeproButtons() {
        const any = $('#tasks-today-list .dr-taskrow').toArray().some(el => $(el).data('weproId'));
        $('#wepro-log-bulk, #wepro-progress-bulk').toggleClass('hidden', !any || $('body').hasClass('dr-wepro-off'));
    }
    new MutationObserver(syncWeproButtons).observe(document.getElementById('tasks-today-list'), { childList: true, subtree: true });
    // Checked per project: the login is shared, the WePRO Project ID is not.
    function checkWeproStatus() {
        const project = $('#project').val() || '';
        setWeproStatus('wait', 'đang kiểm tra...', 'Đang kiểm tra kết nối WePRO cho ' + project);
        return $.ajax({ url: 'wepro-timelog.php', method: 'POST', dataType: 'json', data: { csrf_token: weproCsrf(), action: 'ping', project } })
            .done(function (res) {
                weproStatusLast = res || null;
                setWeproFeatures(!!(res && res.success));
                if (res && res.disabled) { setWeproStatus('off', 'tắt', 'WePRO đang tắt trong Thiết lập. Bấm để xem.'); return; }
                if (res && res.success) { setWeproStatus('ok', project, 'Đã kết nối, project ' + project + ' có WePRO ID ' + res.info.project_id); weproEnsureActivities(); return; }
                if (res && res.login_ok === null) { setWeproStatus('noproj', project + ' thiếu ID', res.message); return; }
                setWeproStatus('err', 'lỗi', (res && res.message) || 'WePRO chưa kết nối. Bấm để xem chi tiết.');
            })
            .fail(function () { weproStatusLast = null; setWeproFeatures(false); setWeproStatus('err', 'lỗi', 'Không gọi được máy chủ. Bấm để xem chi tiết.'); });
    }
    $('#project').on('change', function () { checkWeproStatus(); });

    function weproStatusRow(label, value, ok) {
        return '<div class="dr-ws-row"><span class="dr-ws-label">' + label + '</span><span class="dr-ws-value' + (ok === true ? ' is-ok' : ok === false ? ' is-bad' : '') + '">' + value + '</span></div>';
    }
    $('#weproStatus').on('click', function () {
        const res = weproStatusLast;
        const project = $('#project').val() || '';
        let rows = '';
        if (!res || res.disabled) {
            rows = weproStatusRow('Trạng thái', res && res.disabled ? 'WePRO đang tắt trong Thiết lập' : 'Chưa kiểm tra được (máy chủ không phản hồi)', false);
        } else {
            const i = res.info || {};
            rows += res.login_ok === null
                ? weproStatusRow('Đăng nhập', 'Chưa kiểm tra — cần WePRO Project ID')
                : weproStatusRow('Đăng nhập', escapeHtml(res.login_ok ? 'OK' : 'Lỗi') + ' — ' + escapeHtml(res.login_ok ? 'cookie còn hạn' : res.message), !!res.login_ok);
            rows += weproStatusRow('Project ' + escapeHtml(project), res.project_ok ? 'WePRO Project ID ' + escapeHtml(i.project_id) : 'Chưa có WePRO Project ID → Thiết lập → Project', !!res.project_ok);
            rows += weproStatusRow('Địa chỉ', escapeHtml(i.base_url || ''));
            rows += weproStatusRow('Basic auth', escapeHtml(i.basic_user || '(trống)') + (i.basic_pass_set ? ' · mật khẩu đã lưu' : ' · chưa có mật khẩu'), !!i.basic_user && !!i.basic_pass_set);
            rows += weproStatusRow('Cookie', i.cookie_name ? escapeHtml(i.cookie_name) + ' · ' + i.cookie_length + ' ký tự' : 'Chưa dán cookie', !!i.cookie_name);
            rows += weproStatusRow('Lấy cả task con', i.include_subtasks ? 'Bật (chậm hơn)' : 'Tắt');
            rows += weproStatusRow('Phản hồi', 'HTTP ' + i.http_code + ' · ' + i.latency_ms + ' ms · ' + new Date(i.checked_at).toLocaleTimeString('vi-VN'));
        }
        Swal.fire({
            title: 'Kết nối WePRO',
            html: '<div class="dr-ws">' + rows + '</div>',
            buttonsStyling: false,
            customClass: WEPRO_SWAL_CLASS,
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Mở Thiết lập',
            denyButtonText: '⟳ Kiểm tra lại',
            cancelButtonText: 'Đóng'
        }).then(function (r) {
            if (r.isConfirmed) window.location.href = 'settings.php?step=' + (res && res.login_ok === null ? 2 : 3);
            if (r.isDenied) checkWeproStatus().always(function () { $('#weproStatus').trigger('click'); });
        });
    });

    // ===== WEPRO: log giờ =====
    const WEPRO_LOGGED_KEY = 'dr-wepro-logged-v2';
    const WEPRO_SWAL_CLASS = {
        popup: 'dr-swal', title: 'dr-swal-title', htmlContainer: 'dr-swal-html',
        actions: 'dr-modal-actions', confirmButton: 'dr-modal-confirm', cancelButton: 'dr-modal-cancel',
        denyButton: 'dr-modal-cancel', validationMessage: 'dr-modal-validation'
    };

    // Remembers successful logs {date, project, task, hours}: a second log asks first,
    // and the per-project daily total shows on the form.
    function weproLoggedRead() {
        try { return JSON.parse(localStorage.getItem(WEPRO_LOGGED_KEY) || '[]') || []; } catch (e) { return []; }
    }
    function weproLoggedHas(date, taskId) {
        return weproLoggedRead().some(e => e.d === date && e.t === taskId);
    }
    function weproLoggedAdd(date, project, rows) {
        try {
            const all = weproLoggedRead();
            rows.forEach(function (r) { all.push({ d: date, p: project, t: r.id, h: r.hours }); });
            localStorage.setItem(WEPRO_LOGGED_KEY, JSON.stringify(all.slice(-1000)));
        } catch (e) { /* localStorage unavailable */ }
    }
    function weproLoggedTotal(date, project) {
        return weproLoggedRead().filter(e => e.d === date && e.p === project).reduce((sum, e) => sum + Number(e.h || 0), 0);
    }
    function formatHours(h) {
        return String(Math.round(h * 100) / 100).replace('.', ',') + 'h';
    }
    function weproRenderLoggedTotal() {
        const date = $('#reportDate').val() || '';
        const project = $('#project').val() || '';
        const total = weproLoggedTotal(date, project);
        $('#weproLoggedTotal').text(total > 0 ? 'Đã log ' + formatHours(total) + ' WePRO ↗' : '').attr('href', weproTimelogBaseUrl()).toggleClass('hidden', total <= 0);
    }
    $('#project, #reportDate').on('change', weproRenderLoggedTotal);
    weproRenderLoggedTotal();

    // ===== Google Form bù nhiều ngày =====
    function getDateRange(start, end) {
        const dates = [];
        const cursor = new Date(start + 'T00:00:00');
        const last = new Date(end + 'T00:00:00');
        while (cursor <= last) {
            dates.push(dateToValue(cursor));
            cursor.setDate(cursor.getDate() + 1);
        }
        return dates;
    }
    $('#googleFormRangeBtn').on('click', async function () {
        const startTime = $(this).attr('data-start-time');
        const endTime = $(this).attr('data-end-time');
        const todayDate = new Date();
        const today = dateToValue(todayDate);
        function shift(days) { const d = new Date(todayDate); d.setDate(d.getDate() + days); return dateToValue(d); }
        const presets = [
            { label: 'Hôm nay', start: today, end: today },
            { label: 'Hôm qua', start: shift(-1), end: shift(-1) },
            { label: '7 ngày qua', start: shift(-6), end: today },
            { label: 'Tháng này', start: dateToValue(new Date(todayDate.getFullYear(), todayDate.getMonth(), 1)), end: today }
        ];
        function gfDates() {
            const start = $('#gfStart').val(), end = $('#gfEnd').val();
            if (!start || !end || start > end) return null;
            const skipWeekend = $('#gfSkipWeekend').prop('checked');
            return getDateRange(start, end).filter(d => !skipWeekend || ![0, 6].includes(new Date(d + 'T00:00:00').getDay()));
        }
        function gfSummary() {
            const dates = gfDates();
            const $sum = $('#gfSummary');
            if (!dates) { $sum.removeClass('ok').text('Chọn khoảng ngày hợp lệ.'); return; }
            if (!dates.length) { $sum.removeClass('ok').text('Không có ngày nào để nộp.'); return; }
            $sum.toggleClass('ok', dates.length <= 31).text('Sẽ nộp ' + dates.length + ' ngày' + (dates.length > 31 ? ' — tối đa 31 ngày mỗi lần.' : '.'));
            $(Swal.getConfirmButton()).text(dates.length ? 'Nộp ' + dates.length + ' ngày' : 'Nộp');
        }
        const picked = await Swal.fire({
            title: 'Logtime RCVN bù',
            html: '<p class="dr-modal-note">Nộp logtime RCVN (Google Form) cho những ngày đã quên. Không gửi báo cáo.</p>'
                + '<div class="dr-gf-presets">' + presets.map((p, i) => '<button type="button" class="dr-chip dr-gf-preset" data-i="' + i + '">' + p.label + '</button>').join('') + '</div>'
                + '<div class="dr-gf-range">'
                + '<label class="dr-gf-field"><span>Từ ngày</span><input type="date" id="gfStart" data-allow-custom="true" value="' + today + '"></label>'
                + '<label class="dr-gf-field"><span>Đến ngày</span><input type="date" id="gfEnd" data-allow-custom="true" value="' + today + '"></label>'
                + '</div>'
                + '<div class="dr-gf-range">'
                + '<label class="dr-gf-field"><span>Giờ bắt đầu</span><input type="time" id="gfStartTime" value="' + escapeHtml(startTime) + '" required></label>'
                + '<label class="dr-gf-field"><span>Giờ kết thúc</span><input type="time" id="gfEndTime" value="' + escapeHtml(endTime) + '" required></label>'
                + '</div>'
                + '<label class="dr-gf-skip"><input type="checkbox" id="gfSkipWeekend" checked> Bỏ qua thứ 7, chủ nhật</label>'
                + '<p class="dr-gf-summary" id="gfSummary"></p>',
            buttonsStyling: false,
            customClass: WEPRO_SWAL_CLASS,
            showCancelButton: true,
            confirmButtonText: 'Nộp',
            cancelButtonText: 'Hủy',
            didOpen: function () {
                $('#gfStart, #gfEnd').each(function () { enhanceDateInput(this); });
                $('#gfStart, #gfEnd, #gfSkipWeekend').on('change', gfSummary);
                $('.dr-gf-preset').on('click', function () {
                    const p = presets[Number($(this).data('i'))];
                    $('#gfStart').val(p.start); $('#gfEnd').val(p.end);
                    syncDateControl($('#gfStart')[0]); syncDateControl($('#gfEnd')[0]);
                    $('.dr-gf-preset').removeClass('is-active'); $(this).addClass('is-active');
                    gfSummary();
                });
                $('.dr-gf-preset').first().addClass('is-active');
                gfSummary();
            },
            willClose: function () { closeDatePickers(); },
            preConfirm: function () {
                const dates = gfDates();
                if (!dates) { Swal.showValidationMessage('Ngày bắt đầu phải nhỏ hơn hoặc bằng ngày kết thúc.'); return false; }
                if (!dates.length) { Swal.showValidationMessage('Không có ngày nào để nộp.'); return false; }
                if (dates.length > 31) { Swal.showValidationMessage('Mỗi lần tối đa 31 ngày.'); return false; }
                const startTime = $('#gfStartTime').val(), endTime = $('#gfEndTime').val();
                if (!startTime || !endTime || startTime >= endTime) { Swal.showValidationMessage('Giờ kết thúc phải sau giờ bắt đầu.'); return false; }
                return { dates, startTime, endTime };
            }
        });
        if (!picked.isConfirmed) return;
        const { dates, startTime: selectedStartTime, endTime: selectedEndTime } = picked.value;
        Swal.fire({ title: 'Đang nộp...', allowOutsideClick: false, showConfirmButton: false, customClass: WEPRO_SWAL_CLASS, didOpen: () => Swal.showLoading() });
        const succeeded = [];
        const failed = [];
        for (let i = 0; i < dates.length; i += 1) {
            Swal.update({ title: 'Đang nộp ' + (i + 1) + '/' + dates.length + '...' });
            Swal.showLoading();
            try {
                const res = await $.ajax({ url: 'submit-google-form.php', method: 'POST', dataType: 'json', data: { date: dates[i], start_time: selectedStartTime, end_time: selectedEndTime, csrf_token: weproCsrf() } });
                if (res.success) succeeded.push(dates[i]); else failed.push({ date: dates[i], message: res.message || 'Có lỗi xảy ra' });
            } catch (err) {
                failed.push({ date: dates[i], message: 'Không kết nối được máy chủ' });
            }
        }
        if (!failed.length) {
            Swal.fire({ icon: 'success', title: 'Nộp xong', text: 'Đã nộp ' + succeeded.length + '/' + dates.length + ' ngày.', buttonsStyling: false, customClass: WEPRO_SWAL_CLASS });
            return;
        }
        const list = failed.map(f => f.date.split('-').reverse().join('/') + ': ' + f.message).join('\n');
        Swal.fire({ icon: 'warning', title: 'Xong ' + succeeded.length + '/' + dates.length + ' ngày', html: '<p class="dr-modal-note">Các ngày chưa nộp được:</p><pre class="whitespace-pre-wrap text-left text-xs">' + escapeHtml(list) + '</pre>', buttonsStyling: false, customClass: WEPRO_SWAL_CLASS });
    });


    function weproTimelogBaseUrl() {
        const url = String($('#tasks-today-list .dr-taskrow').first().data('weproTimelogUrl') || '');
        const m = url.match(/^(https?:\/\/[^/]+)/);
        return (m ? m[1] : 'https://wepro.rcvn.work') + '/account/timelogs';
    }

    function weproTimelogAjax(data) {
        return $.ajax({ url: 'wepro-timelog.php', method: 'POST', dataType: 'json', data: $.extend({ csrf_token: weproCsrf() }, data) });
    }

    // Rows carrying a WePRO id; the memo defaults to the task text as typed, no type tag.
    function weproLoggableRows() {
        return $('#tasks-today-list .dr-taskrow').map(function () {
            const $row = $(this);
            const id = $row.data('weproId');
            if (!id) return null;
            const type = String($row.find('select[name*="[work_type]"]').val() || '').trim();
            const content = String($row.find('textarea[name*="[content]"]').val() || '').trim();
            return {
                id: Number(id),
                code: String($row.data('weproCode') || $row.find('input[name*="[issue_no]"]').val() || ''),
                type: type,
                memo: content,
                hours: Number($row.find('input[name*="[hours]"]').val()) || ''
            };
        }).get();
    }

    function weproActivityOptions(activities, type) {
        const wanted = String(type || '').toLowerCase().replace(/[\s_-]/g, '');
        const selected = wanted ? activities.find(a => a.label.toLowerCase().replace(/[\s_-]/g, '') === wanted) : null;
        return '<option value="">— Chọn activity —</option>' + activities.map(a => '<option value="' + escapeHtml(a.value) + '"' + (a === selected ? ' selected' : '') + '>' + escapeHtml(a.label) + '</option>').join('');
    }

    function weproLogRowHtml(row, activities, withCheck) {
        return '<div class="dr-wlog-row" data-id="' + row.id + '">'
            + (withCheck ? '<input type="checkbox" class="dr-wlog-check" checked>' : '')
            + '<div class="dr-wlog-body">'
            + '<div class="dr-wlog-code">' + escapeHtml(row.code) + '</div>'
            + '<div class="dr-wlog-fields">'
            + '<select class="dr-wlog-activity">' + weproActivityOptions(activities, row.type) + '</select>'
            + '<input type="text" class="dr-wlog-memo" value="' + escapeHtml(row.memo) + '" placeholder="Ghi chú">'
            + '<input type="number" class="dr-wlog-hours" min="0.5" max="12" step="0.5" placeholder="Giờ" value="' + escapeHtml(row.hours) + '">'
            + '</div></div></div>';
    }

    function weproCollectLogRows($scope) {
        return $scope.find('.dr-wlog-row').filter(function () {
            const $c = $(this).find('.dr-wlog-check');
            return !$c.length || $c.prop('checked');
        }).map(function () {
            return {
                id: Number($(this).data('id')),
                activity: $(this).find('.dr-wlog-activity').val(),
                memo: $(this).find('.dr-wlog-memo').val(),
                hours: Number($(this).find('.dr-wlog-hours').val())
            };
        }).get();
    }

    // Sum of ticked rows, shown under the list and on the confirm button.
    function weproUpdateLogTotal() {
        const total = weproCollectLogRows($('#weproLogList')).reduce((sum, r) => sum + (r.hours > 0 ? r.hours : 0), 0);
        $('#weproLogTotal').text('Tổng: ' + formatHours(total));
        const $btn = $(Swal.getConfirmButton());
        if ($btn.length) $btn.text(total > 0 ? 'Log ' + formatHours(total) + ' lên WePRO' : 'Log lên WePRO');
    }
    $(document).on('input change', '#weproLogList .dr-wlog-hours, #weproLogList .dr-wlog-check', weproUpdateLogTotal);
    $(document).on('change', '#weproLogList .dr-wlog-activity', function () { $(this).removeClass('is-invalid'); });

    function weproOpenLogModal(rows, title) {
        const date = $('#reportDate').val() || new Date().toISOString().slice(0, 10);
        const bulk = rows.length > 1;
        Swal.fire({
            title: title,
            html: '<p class="dr-modal-note" id="weproLogNote">Đang lấy form từ WePRO...</p>'
                + '<div class="dr-wlog-date"><label>Ngày log</label><input type="date" id="weproLogDate" class="dr-wepro-search" value="' + escapeHtml(date) + '"></div>'
                + '<div id="weproLogList" class="dr-wepro-list"></div>'
                + '<p class="dr-wlog-total" id="weproLogTotal"></p>',
            buttonsStyling: false,
            customClass: WEPRO_SWAL_CLASS,
            showCancelButton: true,
            confirmButtonText: 'Log lên WePRO',
            cancelButtonText: 'Hủy',
            didOpen: function () {
                Swal.getConfirmButton().disabled = true;
                weproTimelogAjax({ action: 'form', 'task_ids[]': rows[0].id })
                    .done(function (res) {
                        if (!res || !res.success) {
                            $('#weproLogNote').text((res && res.message) || 'Không lấy được form WePRO.');
                            return;
                        }
                        weproActivitiesStore(res.activities);
                        $('#weproLogNote').text(bulk ? 'Tick task cần log, điền activity / ghi chú / giờ.' : 'Điền activity, ghi chú và số giờ.');
                        $('#weproLogList').html(rows.map(r => weproLogRowHtml(r, res.activities, bulk)).join(''));
                        $('#weproLogList .dr-wlog-hours').first().trigger('focus');
                        weproUpdateLogTotal();
                        Swal.getConfirmButton().disabled = false;
                    })
                    .fail(function (xhr) { $('#weproLogNote').text(weproAjaxError(xhr)); });
            },
            preConfirm: function () {
                const logDate = $('#weproLogDate').val();
                const picked = weproCollectLogRows($('#weproLogList'));
                if (!logDate) { Swal.showValidationMessage('Chọn ngày log.'); return false; }
                if (!picked.length) { Swal.showValidationMessage('Chọn ít nhất 1 task.'); return false; }
                const noActivity = picked.filter(r => !r.activity);
                noActivity.forEach(r => $('#weproLogList .dr-wlog-row[data-id="' + r.id + '"] .dr-wlog-activity').addClass('is-invalid'));
                if (noActivity.length) { Swal.showValidationMessage('Chọn activity cho ' + noActivity.length + ' task.'); return false; }
                const missing = picked.find(r => !(r.hours > 0));
                if (missing) { Swal.showValidationMessage('Task ' + missing.id + ' chưa có số giờ.'); return false; }
                return { date: logDate, rows: picked };
            }
        }).then(function (result) {
            if (!result.isConfirmed) return;
            weproSubmitLog(result.value.date, result.value.rows);
        });
    }

    async function weproSubmitLog(date, rows) {
        const dup = rows.filter(r => weproLoggedHas(date, r.id));
        if (dup.length) {
            const ask = await Swal.fire({
                icon: 'warning',
                title: 'Đã log trước đó',
                html: '<p class="dr-modal-note">' + dup.length + ' task đã được log ngày <b>' + escapeHtml(date) + '</b> từ app này. '
                    + 'Kiểm tra tại <a href="' + escapeHtml(weproTimelogBaseUrl()) + '" target="_blank" rel="noopener noreferrer">WePRO → Timelogs</a> trước khi log thêm.</p>',
                buttonsStyling: false,
                customClass: WEPRO_SWAL_CLASS,
                showCancelButton: true,
                confirmButtonText: 'Vẫn log',
                cancelButtonText: 'Hủy'
            });
            if (!ask.isConfirmed) return;
        }
        Swal.fire({ title: 'Đang log lên WePRO...', allowOutsideClick: false, showConfirmButton: false, customClass: WEPRO_SWAL_CLASS, didOpen: () => Swal.showLoading() });
        const data = { action: 'store', date: date };
        rows.forEach(function (r, i) {
            data['task_ids[' + i + ']'] = r.id;
            data['activities[' + i + ']'] = r.activity;
            data['memos[' + i + ']'] = r.memo;
            data['hours[' + i + ']'] = r.hours;
        });
        weproTimelogAjax(data)
            .done(function (res) {
                if (!res || !res.success) {
                    Swal.fire({ icon: 'error', title: 'Log thất bại', text: (res && res.message) || 'WePRO không phản hồi.', buttonsStyling: false, customClass: WEPRO_SWAL_CLASS });
                    return;
                }
                weproLoggedAdd(date, $('#project').val() || '', rows);
                weproRenderLoggedTotal();
                Swal.fire({
                    icon: 'success',
                    title: 'Đã log ' + rows.length + ' task',
                    html: '<p class="dr-modal-note">' + escapeHtml(res.message) + ' <a href="' + escapeHtml(weproTimelogBaseUrl()) + '" target="_blank" rel="noopener noreferrer">Xem trên WePRO</a></p>',
                    buttonsStyling: false,
                    customClass: WEPRO_SWAL_CLASS
                });
            })
            .fail(function (xhr) {
                Swal.fire({ icon: 'error', title: 'Log thất bại', text: weproAjaxError(xhr), buttonsStyling: false, customClass: WEPRO_SWAL_CLASS });
            });
    }

    // Left click opens the in-app log form; the href still works for open-in-new-tab.
    $(document).on('click', '.wepro-timelog', function (event) {
        event.preventDefault();
        const $row = $(this).closest('.dr-taskrow');
        const row = weproLoggableRows().find(r => r.id === Number($row.data('weproId')));
        if (!row) return;
        weproOpenLogModal([row], 'Logtime WePRO · ' + row.code);
    });

    $('#wepro-log-bulk').click(function () {
        const rows = weproLoggableRows();
        if (!rows.length) {
            Swal.fire({ icon: 'info', title: 'Chưa có task WePRO', text: 'Thêm task bằng "Chọn task từ WePRO" trước.', buttonsStyling: false, customClass: WEPRO_SWAL_CLASS });
            return;
        }
        weproOpenLogModal(rows, 'Logtime WePRO hàng loạt');
    });

    // ===== WEPRO: cập nhật % và trạng thái task =====
    // Values start from the report but stay editable. Status goes out only when it differs from
    // WePRO's; moving to "completed" needs WePRO's AI-usage survey, asked inline on that row.
    const WEPRO_STATUS_PICK = [['new', 'Mới', 'st-new'], ['doing', 'Đang làm', 'st-doing'], ['on_hold', 'Tạm hoãn', 'st-hold'], ['completed', 'Xong', 'st-done']];
    const WEPRO_AI_PCT = [['0', '0%'], ['20', '1~20%'], ['40', '21~40%'], ['80', '41~80%'], ['100', '>80%']];
    const WEPRO_AI_PURPOSE = [['Ideas/Planning', 'Ý tưởng / kế hoạch'], ['Coding', 'Viết code'], ['Translation', 'Dịch thuật'], ['Writing/Document', 'Viết tài liệu'], ['Testing', 'Kiểm thử'], ['Communication', 'Giao tiếp'], ['Analysis/Research', 'Phân tích / nghiên cứu'], ['Review', 'Review'], ['Other', 'Khác']];

    function weproProgRowHtml(r) {
        const before = (r.beforePct === null ? '?' : r.beforePct + '%') + (r.beforeSt && WEPRO_STATUS[r.beforeSt] ? ' · ' + WEPRO_STATUS[r.beforeSt].label : '');
        return '<div class="dr-wlog-row dr-prog-row" data-id="' + r.id + '">'
            + '<input type="checkbox" class="dr-wlog-check">'
            + '<div class="dr-wlog-body">'
            + '<div class="dr-wlog-code">' + escapeHtml(r.code) + ' · WePRO hiện tại: ' + escapeHtml(before) + '</div>'
            + '<div class="dr-prog-title">' + escapeHtml(r.title) + '</div>'
            + '<div class="dr-prog-line"><span>Tiến độ</span><input type="range" class="dr-prog-range" min="0" max="100" step="5" value="' + r.pct + '"><input type="number" class="dr-prog-pct" min="0" max="100" step="5" value="' + r.pct + '"></div>'
            + '<div class="dr-prog-line"><span>Trạng thái</span><span class="dr-wepro-chips dr-prog-st">'
            + WEPRO_STATUS_PICK.map(s => '<button type="button" class="dr-chip ' + s[2] + (s[0] === r.st ? ' is-active' : '') + '" data-v="' + s[0] + '">' + s[1] + '</button>').join('')
            + '</span></div>'
            + '<div class="dr-prog-ai hidden">'
            + '<div class="dr-prog-ai-label">WePRO bắt khảo sát khi hoàn thành: AI giúp bao nhiêu? *</div>'
            + '<span class="dr-wepro-chips dr-prog-aipct">' + WEPRO_AI_PCT.map(p => '<button type="button" class="dr-chip" data-v="' + p[0] + '">' + p[1] + '</button>').join('') + '</span>'
            + '<div class="dr-prog-aipur-wrap"><div class="dr-prog-ai-label">AI hỗ trợ phần nào? *</div>'
            + '<span class="dr-wepro-chips dr-prog-aipur">' + WEPRO_AI_PURPOSE.map(p => '<button type="button" class="dr-chip" data-v="' + escapeHtml(p[0]) + '">' + escapeHtml(p[1]) + '</button>').join('') + '</span></div>'
            + '<input type="text" class="dr-wepro-search dr-prog-ainote" maxlength="200" placeholder="Ghi chú (tùy chọn)">'
            + '</div>'
            + '<span class="dr-prog-msg"></span>'
            + '</div></div>';
    }

    function weproProgRead($row) {
        return {
            pct: Math.max(0, Math.min(100, Number($row.find('.dr-prog-pct').val()) || 0)),
            st: $row.find('.dr-prog-st .is-active').data('v') || '',
            aiPct: $row.find('.dr-prog-aipct .is-active').data('v'),
            aiPurposes: $row.find('.dr-prog-aipur .is-active').map(function () { return $(this).data('v'); }).get(),
            aiNotes: $row.find('.dr-prog-ainote').val().trim()
        };
    }

    // Ticks the row when it differs from WePRO, and shows the survey only for a fresh completion.
    function weproProgSync($row, r) {
        const v = weproProgRead($row);
        const changed = v.pct !== r.beforePct || v.st !== r.beforeSt;
        $row.find('.dr-wlog-check').prop('checked', changed);
        $row.find('.dr-prog-ai').toggleClass('hidden', !(v.st === 'completed' && r.beforeSt !== 'completed'));
        $row.css('border-color', '');
    }

    $('#wepro-progress-bulk').click(function () {
        const project = $('#project').val() || '';
        const cached = weproCacheGet(project);
        const known = new Map(((cached && cached.tasks) || []).map(t => [Number(t.id), t]));
        const rows = $('#tasks-today-list .dr-taskrow').map(function () {
            const id = Number($(this).data('weproId'));
            if (!id) return null;
            const t = known.get(id);
            const progress = $(this).find('select[name*="[progress]"]').val();
            const pct = progress === '' ? (t ? Number(t.percent) || 0 : 0) : Number(progress);
            return {
                id,
                code: String($(this).data('weproCode') || $(this).find('input[name*="[issue_no]"]').val() || '#' + id),
                title: String($(this).find('[name*="[content]"]').val() || '').trim(),
                beforePct: t ? Number(t.percent) || 0 : null,
                beforeSt: t ? t.status || '' : '',
                pct,
                st: pct >= 100 ? 'completed' : pct <= 0 ? 'new' : 'doing'
            };
        }).get();
        if (!rows.length) {
            Swal.fire({ icon: 'info', title: 'Chưa có task WePRO', text: 'Thêm task bằng "Chọn task từ WePRO" trước.', buttonsStyling: false, customClass: WEPRO_SWAL_CLASS });
            return;
        }
        const byId = new Map(rows.map(r => [r.id, r]));
        Swal.fire({
            title: 'Cập nhật task WePRO',
            html: '<p class="dr-modal-note">Sửa % và trạng thái, tick task cần cập nhật. Dòng không đổi so với WePRO để trống tick.</p>'
                + '<div id="weproProgList" class="dr-wepro-list">' + rows.map(weproProgRowHtml).join('') + '</div>',
            buttonsStyling: false,
            customClass: $.extend({}, WEPRO_SWAL_CLASS, { popup: 'dr-swal dr-swal-wide' }),
            showCancelButton: true,
            confirmButtonText: 'Cập nhật lên WePRO',
            cancelButtonText: 'Đóng',
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading(),
            didOpen: function () {
                const $list = $('#weproProgList');
                $list.find('.dr-prog-row').each(function () { weproProgSync($(this), byId.get(Number($(this).data('id')))); });
                const rowOf = el => { const $row = $(el).closest('.dr-prog-row'); return [$row, byId.get(Number($row.data('id')))]; };
                $list.on('input', '.dr-prog-range', function () { const [$row, r] = rowOf(this); $row.find('.dr-prog-pct').val(this.value); weproProgSync($row, r); });
                $list.on('input change', '.dr-prog-pct', function () { const [$row, r] = rowOf(this); $row.find('.dr-prog-range').val(this.value); weproProgSync($row, r); });
                $list.on('click', '.dr-prog-st .dr-chip', function () {
                    const [$row, r] = rowOf(this);
                    $(this).addClass('is-active').siblings().removeClass('is-active');
                    if ($(this).data('v') === 'completed') $row.find('.dr-prog-pct, .dr-prog-range').val(100);
                    weproProgSync($row, r);
                });
                // Like WePRO's own dialog: at 0% AI there is nothing to ask about purposes.
                $list.on('click', '.dr-prog-aipct .dr-chip', function () {
                    $(this).addClass('is-active').siblings().removeClass('is-active');
                    $(this).closest('.dr-prog-row').css('border-color', '').find('.dr-prog-aipur-wrap').toggleClass('hidden', Number($(this).data('v')) === 0);
                });
                $list.on('click', '.dr-prog-aipur .dr-chip', function () { $(this).toggleClass('is-active'); $(this).closest('.dr-prog-row').css('border-color', ''); });
            },
            preConfirm: function () {
                const picked = [];
                let missingAi = 0;
                $('#weproProgList .dr-prog-row').each(function () {
                    if (!$(this).find('.dr-wlog-check').prop('checked')) return;
                    const r = byId.get(Number($(this).data('id')));
                    const v = weproProgRead($(this));
                    const completing = v.st === 'completed' && r.beforeSt !== 'completed';
                    if (Number(v.aiPct) === 0) v.aiPurposes = [];
                    if (completing && (v.aiPct === undefined || (Number(v.aiPct) !== 0 && !v.aiPurposes.length))) { missingAi++; $(this).css('border-color', '#dc2626'); }
                    picked.push($.extend({ id: r.id, completing, sendStatus: v.st !== r.beforeSt }, v));
                });
                if (!picked.length) { Swal.showValidationMessage('Tick ít nhất 1 task.'); return false; }
                if (missingAi) { Swal.showValidationMessage(missingAi + ' task chọn Xong còn thiếu khảo sát AI.'); return false; }
                const data = { csrf_token: weproCsrf(), project, action: 'update' };
                picked.forEach(function (p, i) {
                    data['tasks[' + i + '][id]'] = p.id;
                    data['tasks[' + i + '][task_percent]'] = p.pct;
                    if (p.sendStatus) data['tasks[' + i + '][status]'] = p.st;
                    if (p.completing) {
                        data['tasks[' + i + '][ai_percentage]'] = p.aiPct;
                        data['tasks[' + i + '][ai_notes]'] = p.aiNotes;
                        p.aiPurposes.forEach((pu, j) => { data['tasks[' + i + '][ai_purpose][' + j + ']'] = pu; });
                    }
                });
                // Failures keep the dialog open; rows that went through take WePRO's new values,
                // so they untick and a retry skips them.
                return new Promise(function (resolve) {
                    $.ajax({ url: 'wepro-task-create.php', method: 'POST', dataType: 'json', data })
                        .done(function (res) {
                            const results = (res && res.results) || [];
                            const all = cached ? cached.tasks : null;
                            results.forEach(function (res1) {
                                const $row = $('#weproProgList .dr-prog-row[data-id="' + res1.id + '"]');
                                $row.find('.dr-prog-msg').text(res1.success ? '✓ đã cập nhật' : '✕ ' + res1.message).css('color', res1.success ? '#047857' : '#dc2626');
                                if (!res1.success) { $row.css('border-color', '#dc2626'); return; }
                                const p = picked.find(x => x.id === Number(res1.id));
                                const r = byId.get(Number(res1.id));
                                r.beforePct = p.pct;
                                r.beforeSt = p.st;
                                weproProgSync($row, r);
                                const t = all && all.find(x => Number(x.id) === r.id);
                                if (t) { t.percent = p.pct; t.status = p.st; }
                            });
                            if (all) weproCacheSet(project, all);
                            const bad = results.filter(x => !x.success).length;
                            if (!results.length || bad) {
                                Swal.showValidationMessage(results.length ? bad + ' task cập nhật lỗi, xem từng dòng rồi thử lại.' : escapeHtml((res && res.message) || 'WePRO không phản hồi.'));
                                resolve(false);
                                return;
                            }
                            resolve(results.length);
                        })
                        .fail(function (xhr) {
                            Swal.showValidationMessage(escapeHtml(xhr && xhr.status ? 'Gọi wepro-task-create.php lỗi HTTP ' + xhr.status + '.' : 'Mất kết nối.'));
                            resolve(false);
                        });
                });
            }
        }).then(function (result) {
            if (result.isConfirmed) Swal.fire({ icon: 'success', title: 'Đã cập nhật ' + result.value + ' task trên WePRO', toast: true, position: 'top-end', showConfirmButton: false, timer: 2200 });
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
        $('#quality-list .quality-btn').removeClass('is-selected').css({
            'background-color': '#fafafa',
            'border-color': '#e4e4e7',
            'color': '#52525b'
        });
        $(this).addClass('is-selected').css({
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
        $('#spirit-list .react-emoji').removeClass('is-selected').css({
            'background-color': '#fafafa',
            'border-color': '#e4e4e7'
        });
        $(this).addClass('is-selected').css({
            'background-color': c.bg,
            'border-color': c.border
        });
        $('#spirit').val(val);
    });

    // ===== DRAFT & PREVIEW =====
    function collectDraft() {
        const todayTasks = [];
        const tomorrowTasks = [];
        $('#tasks-today-list .task-today-item').each(function () {
            todayTasks.push({
                work_type: $(this).find('select[name*="[work_type]"]').val(),
                issue_no: $(this).find('input[name*="[issue_no]"]').val(),
                content: $(this).find('[name*="[content]"]').val(),
                progress: $(this).find('select[name*="[progress]"]').val(),
                hours: $(this).find('input[name*="[hours]"]').val(),
                wepro_id: $(this).data('weproId') || '',
                wepro_timelog_url: $(this).data('weproTimelogUrl') || '',
                wepro_code: $(this).data('weproCode') || ''
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
            historyOnly: $('#historyOnly').prop('checked'),
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

    // Always persist, even when empty: otherwise switching project (which clears the form)
    // leaves the previous project's draft behind and it comes back on reload.
    function saveDraft() {
        const draft = collectDraft();
        localStorage.setItem(draftKey, JSON.stringify(draft));
        if (hasDraftContent(draft)) updateDraftStatus(draft.savedAt);
    }

    function scheduleDraftSave() {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 450);
    }

    function restoreDraft(draft) {
        if (!draft || !Array.isArray(draft.todayTasks)) return;
        $('#submitGoogleChat').prop('checked', draft.submitGoogleChat ?? (draft.destination ? draft.destination === 'google_chat' : false));
        $('#submitSlack').prop('checked', draft.submitSlack ?? (draft.destination === 'slack'));
        $('#submitGoogleForm').prop('checked', draft.submitGoogleForm ?? true);
        $('#historyOnly').prop('checked', !!draft.historyOnly);
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
        if ($('#project option[value="' + CSS.escape(draft.project || '') + '"]').length) {
            // .val() does not refresh the custom select label, and the change guard must
            // learn the restored project or the next switch compares against the default.
            $('#project').val(draft.project);
            rebuildCustomSelect($('#project')[0]);
            lastProject = draft.project;
        }
        $('#dailyReportForm textarea[name="note"]').val(draft.note || '');
        if (draft.quality) $('#quality-list .quality-btn[data-value="' + draft.quality + '"]').trigger('click');
        if (draft.spirit) $('#spirit-list .react-emoji[data-value="' + draft.spirit + '"]').trigger('click');
        updateProjectLogo();
        autoGrowAll();
        if (hasDraftContent(draft)) updateDraftStatus(draft.savedAt || new Date().toISOString());
        syncOptionalCards();
    }

    try {
        const storedDraft = JSON.parse(localStorage.getItem(draftKey) || 'null');
        if (storedDraft) restoreDraft(storedDraft);
    } catch (error) {
        localStorage.removeItem(draftKey);
    }

    syncSubmitRequirements();

    $('#dailyReportForm').on('input change', 'input, select, textarea', scheduleDraftSave);
    $('#quality-list, #spirit-list').on('click', 'button', scheduleDraftSave);

    $('#clear-tasks-today').on('click', async function () {
        const count = $('#tasks-today-list .dr-taskrow').filter(function () {
            return String($(this).find('[name*="[content]"]').val() || '').trim() !== '';
        }).length;
        if (!count) return;
        const result = await confirmDelete('Xóa hết ' + count + ' task hôm nay?', 'Danh sách task sẽ trống để bạn nhập lại. Phần đánh giá và kế hoạch giữ nguyên.');
        if (!result.isConfirmed) return;
        $('#tasks-today-list').empty().append(taskTodayHtml(taskTodayIdx++));
        syncProgress();
        scheduleDraftSave();
        $('#tasks-today-list textarea[name*="[content]"]').first().trigger('focus');
    });

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
                status: statusFromProgress(task.progress),
                progress: task.progress
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
        const sent = draft.historyOnly ? 'Chỉ lưu lịch sử' : ([draft.submitGoogleForm && 'RCNV logtime', draft.submitGoogleChat && 'Google Chat', draft.submitSlack && 'Slack']
            .filter(Boolean).join(', ') || 'Chưa chọn');
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
        const $noActivity = $('#tasks-today-list .task-today-item').filter(function () {
            return $(this).find('[name*="[content]"]').val().trim() && !$(this).find('select[name*="[work_type]"]').val();
        }).find('select[name*="[work_type]"]').closest('.dr-select');
        $noActivity.addClass('is-invalid');
        if ($noActivity.length) {
            Swal.fire({ icon: 'warning', title: 'Chọn Activity cho ' + $noActivity.length + ' task', toast: true, position: 'top-end', showConfirmButton: false, timer: 2500 });
            $noActivity[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        if (!$('#submitGoogleForm, #submitGoogleChat, #submitSlack, #historyOnly').is(':checked')) {
            Swal.fire({ icon: 'warning', text: 'Chọn ít nhất một nơi gửi, hoặc bật "Chỉ lưu lịch sử".' });
            return;
        }
        if (!$('#spirit').val()) {
            Swal.fire({ icon: 'warning', title: 'Vui lòng chọn mức tinh thần!', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            document.getElementById('card-review').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        if (!$('#quality').val()) {
            Swal.fire({ icon: 'warning', title: 'Vui lòng chọn chất lượng!', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            return;
        }
        if ($('#submitSlack').prop('checked') && !($('#dailyReportForm').attr('data-reporter') || '').trim()) {
            Swal.fire({ icon: 'warning', text: 'Vui lòng nhập và lưu tên người báo cáo trong Thiết lập.', confirmButtonText: 'Mở Thiết lập' })
                .then(function () { window.location.href = 'settings.php'; });
            return;
        }
        $('#submitBtn').prop('disabled', true).addClass('opacity-70 cursor-not-allowed');
        $('#loadingIcon').removeClass('hidden');
        $('#submitText').text($('#historyOnly').prop('checked') ? 'Đang lưu...' : 'Đang gửi...');

        $.ajax({
            url: 'send-webhook.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                $('#submitBtn').prop('disabled', false).removeClass('opacity-70 cursor-not-allowed');
                $('#loadingIcon').addClass('hidden');
                syncSubmitLabel();
                const labels = {logtime: 'RCNV logtime', google_chat: 'Google Chat', slack: 'Slack', history: 'Lịch sử'};
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
                syncSubmitLabel();
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

    syncProgress();
    syncSubmitLabel();
    checkWeproStatus();
});
