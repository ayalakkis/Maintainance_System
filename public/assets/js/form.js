/**
 * Esolar Maintenance Tracker - form interactivity.
 */

document.addEventListener('DOMContentLoaded', function () {
    initInverterSizeToggle();
    initBatteryCapacityToggle();
    initCustomSalespersonToggle('salesperson_id', 'custom_salesperson_group');
    initCustomSalespersonToggle('action_modal_salesperson', 'action_modal_custom_group');
    initPrintButton();
    initDateRangeLinking();
    initCalendarMonthAutoSubmit();
    initActionModal();
    initDashboardSearch();
});

function initInverterSizeToggle() {
    var itemTypeSelect = document.getElementById('item_type_id');
    var inverterSizeGroup = document.getElementById('inverter_size_group');

    if (!itemTypeSelect || !inverterSizeGroup) {
        return;
    }

    function toggleInverterSize() {
        var selectedOption = itemTypeSelect.options[itemTypeSelect.selectedIndex];
        var isInverter = !!selectedOption && selectedOption.text === 'Inverter';
        inverterSizeGroup.classList.toggle('d-none', !isInverter);
    }

    itemTypeSelect.addEventListener('change', toggleInverterSize);
    toggleInverterSize();
}

function initBatteryCapacityToggle() {
    var itemTypeSelect = document.getElementById('item_type_id');
    var batteryGroup = document.getElementById('battery_capacity_group');

    if (!itemTypeSelect || !batteryGroup) {
        return;
    }

    function toggleBatteryCapacity() {
        var selectedOption = itemTypeSelect.options[itemTypeSelect.selectedIndex];
        var isBattery = !!selectedOption && (selectedOption.text === 'Acid Battery' || selectedOption.text === 'Lithium Battery');
        batteryGroup.classList.toggle('d-none', !isBattery);
    }

    itemTypeSelect.addEventListener('change', toggleBatteryCapacity);
    toggleBatteryCapacity();
}

/**
 * Shows/hides a custom-name text field next to a salesperson <select> when
 * its "Other" option is picked.
 */
function initCustomSalespersonToggle(selectId, groupId) {
    var salespersonSelect = document.getElementById(selectId);
    var customGroup = document.getElementById(groupId);

    if (!salespersonSelect || !customGroup) {
        return;
    }

    function toggleCustomSalesperson() {
        var isOther = salespersonSelect.value === 'other';
        customGroup.classList.toggle('d-none', !isOther);
    }

    salespersonSelect.addEventListener('change', toggleCustomSalesperson);
    toggleCustomSalesperson();
}

function initPrintButton() {
    var printButton = document.getElementById('print_btn');

    if (!printButton) {
        return;
    }

    printButton.addEventListener('click', function () {
        window.print();
    });
}

/**
 * On the Records archive filter, keeps the Date From / Date To inputs from
 * accepting an impossible range (from > to).
 */
function initDateRangeLinking() {
    var dateFrom = document.getElementById('date_from');
    var dateTo = document.getElementById('date_to');

    if (!dateFrom || !dateTo) {
        return;
    }

    dateFrom.addEventListener('change', function () {
        dateTo.min = dateFrom.value;
    });

    dateTo.addEventListener('change', function () {
        dateFrom.max = dateTo.value;
    });

    if (dateFrom.value) {
        dateTo.min = dateFrom.value;
    }
    if (dateTo.value) {
        dateFrom.max = dateTo.value;
    }
}

/**
 * The Calendar page's month picker jumps immediately on change instead of
 * requiring a separate Search click.
 */
function initCalendarMonthAutoSubmit() {
    var monthInput = document.getElementById('ym');

    if (!monthInput || !monthInput.form) {
        return;
    }

    monthInput.addEventListener('change', function () {
        monthInput.form.requestSubmit();
    });
}

/**
 * Returns today's date as a Y-m-d string in the browser's local timezone
 * (not toISOString(), which is UTC and can be off by a day near midnight).
 */
function todayLocalIsoDate() {
    var now = new Date();
    var month = String(now.getMonth() + 1).padStart(2, '0');
    var day = String(now.getDate()).padStart(2, '0');
    return now.getFullYear() + '-' + month + '-' + day;
}

/**
 * The Dashboard's per-row action buttons (Dispatch / Retrieve / Return) all
 * open the same modal, which always asks fresh for "who did this and when"
 * - it never carries over the salesperson from a previous action, since a
 * different person can easily handle each step. The button's data-action,
 * data-record-id, data-modal-title and data-submit-label attributes
 * configure the modal's hidden fields, title and submit button for whichever
 * action was clicked.
 */
function initActionModal() {
    var modalEl = document.getElementById('action_modal');

    if (!modalEl || !window.bootstrap) {
        return;
    }

    var modal = new bootstrap.Modal(modalEl);
    var titleEl = document.getElementById('action_modal_title');
    var actionInput = document.getElementById('action_modal_action');
    var recordIdInput = document.getElementById('action_modal_record_id');
    var salespersonSelect = document.getElementById('action_modal_salesperson');
    var customGroup = document.getElementById('action_modal_custom_group');
    var customNameInput = document.getElementById('action_modal_custom_name');
    var dateInput = document.getElementById('action_modal_date');
    var returnNote = document.getElementById('action_modal_return_note');
    var submitBtn = document.getElementById('action_modal_submit');

    document.querySelectorAll('[data-action-btn]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            actionInput.value = btn.dataset.action;
            recordIdInput.value = btn.dataset.recordId;
            titleEl.textContent = btn.dataset.modalTitle || '';
            submitBtn.textContent = btn.dataset.submitLabel || '';

            salespersonSelect.value = '';
            customNameInput.value = '';
            customGroup.classList.add('d-none');
            dateInput.value = todayLocalIsoDate();
            returnNote.classList.toggle('d-none', btn.dataset.action !== 'return');

            modal.show();
        });
    });
}

/**
 * The Dashboard's search box filters the already-rendered active-records
 * table client-side by owner name / phone, so staff don't have to leave
 * the page to find something in a short list.
 */
function initDashboardSearch() {
    var input = document.getElementById('dashboard_search');
    var table = document.getElementById('dashboard_table');

    if (!input || !table) {
        return;
    }

    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-search]'));
    var noResultsRow = document.getElementById('dashboard_no_results_row');

    input.addEventListener('input', function () {
        var query = input.value.trim().toLowerCase();
        var visibleCount = 0;

        rows.forEach(function (row) {
            var isMatch = query === '' || row.dataset.search.indexOf(query) !== -1;
            row.classList.toggle('d-none', !isMatch);
            if (isMatch) {
                visibleCount++;
            }
        });

        if (noResultsRow) {
            noResultsRow.classList.toggle('d-none', visibleCount !== 0);
        }
    });
}
