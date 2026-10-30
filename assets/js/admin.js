(function () {
	'use strict';
	var form = document.querySelector('.fdps-admin form');
	if (!form) { return; }
	function update() {
		var modes = Array.from(form.querySelectorAll('[name="fdps_settings[modes][]"]:checked')).map(function (field) { return field.value; });
		var enabled = form.querySelector('[name="fdps_settings[enabled]"]').checked;
		var mode = form.querySelector('[name="fdps_settings[modes][]"]');
		mode.setCustomValidity(enabled && !modes.length ? mode.closest('td').previousElementSibling.textContent.trim() : '');
		var weekday = form.querySelector('[name^="fdps_settings[weekdays]"]');
		var selectedDays = form.querySelectorAll('[name^="fdps_settings[weekdays]"]:checked').length;
		weekday.setCustomValidity(enabled && !selectedDays ? weekday.closest('td').previousElementSibling.textContent.trim() : '');
		var select = document.getElementById('fdps-default-mode');
		Array.from(select.options).forEach(function (option) { option.disabled = modes.indexOf(option.value) === -1; });
		if (modes.indexOf(select.value) === -1 && modes.length) { select.value = modes[0]; }
		document.getElementById('fdps-fixed-fee-row').hidden = document.getElementById('fdps-delivery-fee-type').value !== 'fixed';
		var lead = document.getElementById('fdps-lead-days');
		var advance = document.getElementById('fdps-advance-days');
		advance.min = lead.value || '0';
		if (Number(advance.value) < Number(lead.value)) { advance.value = lead.value; }
	}
	form.addEventListener('change', update);
	update();
}());
