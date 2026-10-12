/* Add a token through an ordinary keyboard-accessible button. */
document.addEventListener('DOMContentLoaded', function () {
	var settings = document.querySelector('.cud-settings');
	if (!settings) {
		return;
	}
	var template = settings.querySelector('#template');
	settings.querySelectorAll('.cud-token').forEach(function (button) {
		button.addEventListener('click', function () {
			var separator = template.value && !template.value.endsWith('/') ? '/' : '';
			template.value += separator + button.dataset.token;
			template.dispatchEvent(new Event('input', { bubbles: true }));
			template.focus();
		});
	});
});
