(function () {
	'use strict';
	if (typeof fdpsCheckout === 'undefined') { return; }
	var config = fdpsCheckout;
	var calendars = new WeakMap();
	var radioGroups = new WeakMap();
	var boundMethods = new WeakSet();
	var pendingMethod = null;
	var updating = false;
	var lastMethod = null;
	var locale = config.locale || document.documentElement.lang || 'en';
	var weekStart = Number(config.weekStartsOn || 0) % 7;
	var monthFormat = new Intl.DateTimeFormat(locale, {month: 'long', year: 'numeric'});
	var dateFormat = new Intl.DateTimeFormat(locale, {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'});
	var weekdayFormat = new Intl.DateTimeFormat(locale, {weekday: 'short'});

	function element(tag, className, text) {
		var node = document.createElement(tag);
		if (className) { node.className = className; }
		if (typeof text !== 'undefined') { node.textContent = text; }
		return node;
	}

	function parseDate(value) {
		var parts = value.split('-').map(Number);
		return new Date(parts[0], parts[1] - 1, parts[2], 12);
	}
	function isoDate(date) {
		return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
	}
	function allowedDate(value) {
		if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) { return false; }
		var date = parseDate(value);
		return isoDate(date) === value && value >= config.minDate && value <= config.maxDate &&
			config.weekdays.indexOf(date.getDay()) !== -1 && config.holidays.indexOf(value) === -1;
	}
	function writeValue(field, value) {
		var prototype = field.tagName === 'SELECT' ? window.HTMLSelectElement.prototype : window.HTMLInputElement.prototype;
		Object.getOwnPropertyDescriptor(prototype, 'value').set.call(field, value);
		field.dispatchEvent(new Event('input', {bubbles: true}));
		field.dispatchEvent(new Event('change', {bubbles: true}));
	}

	function updateBlockMethod(value) {
		value = value || config.defaultMode;
		if (!updating && value === lastMethod) { pendingMethod = null; return; }
		pendingMethod = value;
		if (updating || !window.wc || !window.wc.blocksCheckout || !window.wc.blocksCheckout.extensionCartUpdate) { return; }
		var method = pendingMethod;
		pendingMethod = null;
		updating = true;
		window.wc.blocksCheckout.extensionCartUpdate({namespace: 'fdps', data: {method: method}, overwriteDirtyCustomerData: false})
			.then(function () {
				lastMethod = method;
				if (window.wp && window.wp.data) {
					window.wp.data.dispatch('core/notices').removeNotice('fdps-update-error', 'wc/checkout');
				}
			})
			.catch(function () {
				lastMethod = null;
				if (window.wp && window.wp.data) {
					window.wp.data.dispatch('core/notices').createErrorNotice(config.updateError, {id: 'fdps-update-error', context: 'wc/checkout'});
				}
			})
			.finally(function () {
				updating = false;
				if (pendingMethod !== null) { updateBlockMethod(pendingMethod); }
			});
	}

	/* Keep the API-owned field in place; enhance its presentation without replacing its data flow. */
	function hideSource(field) {
		var container = field.closest('.wc-blocks-components-select__container, .wc-block-components-select__container');
		if (container) {
			container.hidden = true;
			container.classList.add('fdps-native-hidden');
			return container.parentElement;
		}
		field.classList.add('fdps-native-hidden');
		var row = field.closest('.form-row');
		if (row) {
			row.querySelectorAll('label').forEach(function (label) {
				if (label.htmlFor === field.id) { label.classList.add('fdps-native-hidden'); }
			});
		}
		return field.parentElement;
	}

	function enhanceBlockRadios(field) {
		var state = radioGroups.get(field);
		if (!state) {
			var group = element('fieldset', 'fdps-method-group');
			group.appendChild(element('legend', '', config.methodLabel));
			var options = element('div', 'fdps-method-options');
			group.appendChild(options);
			Array.from(field.options).forEach(function (option) {
				if (['delivery', 'pickup'].indexOf(option.value) === -1) { return; }
				var label = element('label', 'fdps-method-option');
				var input = element('input');
				input.type = 'radio';
				input.name = field.id + '-presentation';
				input.value = option.value;
				input.addEventListener('change', function () { if (input.checked) { writeValue(field, input.value); } });
				label.appendChild(input);
				label.appendChild(element('span', '', option.textContent));
				options.appendChild(label);
			});
			hideSource(field).appendChild(group);
			state = {group: group};
			radioGroups.set(field, state);
		}
		state.group.querySelectorAll('input').forEach(function (input) {
			input.checked = input.value === field.value;
		});
		var invalid = field.getAttribute('aria-invalid') === 'true' ? 'true' : 'false';
		if (state.group.getAttribute('aria-invalid') !== invalid) { state.group.setAttribute('aria-invalid', invalid); }
	}

	function calendarAllowed(state, value) {
		if (!allowedDate(value)) { return false; }
		return state.field.tagName !== 'SELECT' || Array.from(state.field.options).some(function (option) { return option.value === value; });
	}

	function focusDate(state, value) {
		if (!calendarAllowed(state, value)) { return; }
		var date = parseDate(value);
		state.view = new Date(date.getFullYear(), date.getMonth(), 1, 12);
		renderCalendar(state, value);
		var target = state.days.querySelector('[data-date="' + value + '"]');
		if (target) { target.focus(); }
	}

	function moveFocus(state, start, step) {
		var date = parseDate(start);
		for (var attempt = 0; attempt <= 730; attempt++) {
			date.setDate(date.getDate() + step);
			var value = isoDate(date);
			if (value < config.minDate || value > config.maxDate) { return; }
			if (calendarAllowed(state, value)) { focusDate(state, value); return; }
		}
	}

	function renderCalendar(state, focusValue) {
		state.title.textContent = monthFormat.format(state.view);
		state.days.replaceChildren();
		var first = new Date(state.view.getFullYear(), state.view.getMonth(), 1, 12);
		var last = new Date(state.view.getFullYear(), state.view.getMonth() + 1, 0, 12);
		var previousLast = new Date(first.getFullYear(), first.getMonth(), 0, 12);
		var nextFirst = new Date(first.getFullYear(), first.getMonth() + 1, 1, 12);
		state.previous.disabled = isoDate(previousLast) < config.minDate;
		state.next.disabled = isoDate(nextFirst) > config.maxDate;
		var offset = (first.getDay() - weekStart + 7) % 7;
		for (var blank = 0; blank < offset; blank++) {
			var spacer = element('span', 'fdps-day-spacer');
			spacer.setAttribute('aria-hidden', 'true');
			state.days.appendChild(spacer);
		}
		var candidates = [];
		for (var day = 1; day <= last.getDate(); day++) {
			(function (date) {
				var value = isoDate(date);
				var holiday = config.holidays.indexOf(value) !== -1;
				var available = calendarAllowed(state, value);
				var selected = value === state.field.value;
				var button = element('button', 'fdps-calendar-day', String(date.getDate()));
				button.type = 'button';
				button.dataset.date = value;
				button.disabled = !available;
				button.tabIndex = -1;
				button.setAttribute('aria-label', dateFormat.format(date) + ' — ' + (holiday ? config.holidayLabel : available ? selected ? config.selectedLabel : config.availableLabel : config.closedLegend));
				button.setAttribute('aria-pressed', selected ? 'true' : 'false');
				if (holiday) { button.classList.add('fdps-holiday'); }
				if (selected) { button.classList.add('fdps-selected'); }
				if (value === config.today) { button.classList.add('fdps-today'); }
				button.addEventListener('click', function () {
					if (!calendarAllowed(state, value)) { return; }
					writeValue(state.field, value);
					state.value = state.field.value;
					state.selected.textContent = dateFormat.format(date);
					focusDate(state, value);
				});
				button.addEventListener('keydown', function (event) {
					var steps = {ArrowRight: 1, ArrowLeft: -1, ArrowDown: 7, ArrowUp: -7};
					if (Object.prototype.hasOwnProperty.call(steps, event.key)) {
						event.preventDefault(); moveFocus(state, value, steps[event.key]);
					} else if (event.key === 'Home' || event.key === 'End') {
						event.preventDefault();
						var weekday = (date.getDay() - weekStart + 7) % 7;
						var target = new Date(date);
						target.setDate(target.getDate() + (event.key === 'Home' ? -weekday : 6 - weekday));
						if (calendarAllowed(state, isoDate(target))) { focusDate(state, isoDate(target)); }
					} else if (event.key === 'PageUp' || event.key === 'PageDown') {
						event.preventDefault();
						var navigation = event.key === 'PageUp' ? state.previous : state.next;
						if (!navigation.disabled) { navigation.click(); var firstAvailable = state.days.querySelector('button:not(:disabled)'); if (firstAvailable) { firstAvailable.focus(); } }
					}
				});
				if (available) { candidates.push(button); }
				state.days.appendChild(button);
			})(new Date(first.getFullYear(), first.getMonth(), day, 12));
		}
		var active = candidates.find(function (button) { return button.dataset.date === (focusValue || state.field.value); }) || candidates[0];
		if (active) { active.tabIndex = 0; }
	}

	function enhanceCalendar(field) {
		var state = calendars.get(field);
		if (!state) {
			var calendar = element('fieldset', 'fdps-calendar');
			calendar.appendChild(element('legend', '', config.dateLabel + (config.required ? ' *' : '')));
			var selected = element('p', 'fdps-calendar-selection');
			selected.setAttribute('aria-live', 'polite');
			calendar.appendChild(selected);
			var header = element('div', 'fdps-calendar-header');
			var previous = element('button', 'fdps-calendar-nav', '‹');
			previous.type = 'button'; previous.setAttribute('aria-label', config.previousMonth);
			var title = element('span', 'fdps-calendar-title');
			title.id = field.id + '-calendar-month';
			title.setAttribute('aria-live', 'polite');
			var next = element('button', 'fdps-calendar-nav', '›');
			next.type = 'button'; next.setAttribute('aria-label', config.nextMonth);
			header.append(previous, title, next);
			calendar.appendChild(header);
			var weekdays = element('div', 'fdps-calendar-weekdays');
			weekdays.setAttribute('aria-hidden', 'true');
			for (var index = 0; index < 7; index++) {
				weekdays.appendChild(element('span', '', weekdayFormat.format(new Date(2026, 0, 4 + (weekStart + index) % 7, 12))));
			}
			calendar.appendChild(weekdays);
			var days = element('div', 'fdps-calendar-days');
			days.setAttribute('role', 'group');
			days.setAttribute('aria-labelledby', title.id);
			calendar.appendChild(days);
			var legend = element('div', 'fdps-calendar-legend');
			legend.appendChild(element('span', 'fdps-legend-holiday', config.holidayLegend));
			legend.appendChild(element('span', 'fdps-legend-closed', config.closedLegend));
			calendar.appendChild(legend);
			if (!config.required) {
				var clear = element('button', 'fdps-clear-date', config.clearDate);
				clear.type = 'button';
				clear.addEventListener('click', function () { writeValue(field, ''); });
				calendar.appendChild(clear);
			}
			hideSource(field).appendChild(calendar);
			var initial = allowedDate(field.value) ? parseDate(field.value) : parseDate(config.minDate);
			state = {field: field, calendar: calendar, selected: selected, days: days, title: title, previous: previous, next: next, view: new Date(initial.getFullYear(), initial.getMonth(), 1, 12), value: null};
			previous.addEventListener('click', function () { state.view = new Date(state.view.getFullYear(), state.view.getMonth() - 1, 1, 12); renderCalendar(state); });
			next.addEventListener('click', function () { state.view = new Date(state.view.getFullYear(), state.view.getMonth() + 1, 1, 12); renderCalendar(state); });
			field.addEventListener('change', function () { syncCalendar(state); });
			calendars.set(field, state);
		}
		syncCalendar(state);
	}

	function syncCalendar(state) {
		var invalid = state.field.getAttribute('aria-invalid') === 'true';
		if (state.calendar.getAttribute('aria-invalid') !== String(invalid)) { state.calendar.setAttribute('aria-invalid', String(invalid)); }
		if (state.value === state.field.value) { return; }
		state.value = state.field.value;
		state.selected.textContent = allowedDate(state.value) ? dateFormat.format(parseDate(state.value)) : config.chooseDate;
		if (allowedDate(state.value)) {
			var date = parseDate(state.value);
			state.view = new Date(date.getFullYear(), date.getMonth(), 1, 12);
		}
		renderCalendar(state);
	}

	function configureFields() {
		document.querySelectorAll('#fdps_date, select[id*="fdps-date"]').forEach(enhanceCalendar);
		document.querySelectorAll('#fdps_method, input[type="radio"][name="fdps_method"], select[id*="fdps-method"]').forEach(function (field) {
			var classic = field.id === 'fdps_method' || field.name === 'fdps_method';
			if (!boundMethods.has(field)) {
				boundMethods.add(field);
				field.addEventListener('change', function () {
					if (field.type === 'radio' && !field.checked) { return; }
					if (classic && window.jQuery) { window.jQuery(document.body).trigger('update_checkout'); }
					if (!classic) { updateBlockMethod(field.value); }
				});
				if (!classic) { updateBlockMethod(field.value); }
			}
			if (!classic && config.methodStyle === 'radio') { enhanceBlockRadios(field); }
			if (classic && field.type === 'radio') {
				field.parentElement.setAttribute('role', 'radiogroup');
				field.parentElement.setAttribute('aria-label', config.methodLabel);
			}
		});
		if (pendingMethod !== null && !updating) { updateBlockMethod(pendingMethod); }
	}
	var scheduled = false;
	function schedule() {
		if (scheduled) { return; }
		scheduled = true;
		window.requestAnimationFrame(function () { scheduled = false; configureFields(); });
	}
	document.addEventListener('DOMContentLoaded', configureFields);
	configureFields();
	new MutationObserver(schedule).observe(document.body, {childList: true, subtree: true, attributes: true, attributeFilter: ['aria-invalid']});
}());
