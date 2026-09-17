(function () {
	'use strict';

	function setAll(checked) {
		document.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
			checkbox.checked = checked;
		});
		updateSelectionCount();
	}

	function updateSelectionCount() {
		var count = document.querySelector('[data-send-to-e-reader-selection-count]');
		var checkboxes = document.querySelectorAll('input[type="checkbox"]');
		var selected = document.querySelectorAll('input[type="checkbox"]:checked').length;
		var downloadButtons = document.querySelectorAll('form button[type="submit"]');

		downloadButtons.forEach(function (button) {
			button.disabled = selected === 0;
		});

		if (!count) {
			return;
		}

		count.textContent = count.getAttribute('data-selected-template')
			.replace('%1$d', checkboxes.length)
			.replace('%2$d', selected);
	}

	function reverseList() {
		var items = Array.prototype.slice.call(document.querySelectorAll('li'));
		if (!items.length) {
			return;
		}

		var parent = items[0].parentNode;
		items.forEach(function (item) {
			parent.insertBefore(item, parent.firstChild);
		});
	}

	function moveItem(link, direction) {
		var item = link.closest('li');
		if (!item) {
			return;
		}

		var sibling = direction === 'up' ? item.previousElementSibling : item.nextElementSibling;
		if (!sibling) {
			return;
		}

		if (direction === 'up') {
			item.parentNode.insertBefore(item, sibling);
		} else {
			item.parentNode.insertBefore(sibling, item);
		}
	}

	document.addEventListener('click', function (event) {
		var link = event.target.closest('[data-send-to-e-reader-action]');
		if (!link) {
			return;
		}

		event.preventDefault();

		switch (link.getAttribute('data-send-to-e-reader-action')) {
			case 'select-all':
				setAll(true);
				break;
			case 'select-none':
				setAll(false);
				break;
			case 'reverse-list':
				reverseList();
				break;
			case 'move-up':
				moveItem(link, 'up');
				break;
			case 'move-down':
				moveItem(link, 'down');
				break;
		}
	});

	document.addEventListener('change', function (event) {
		if (!event.target.matches('input[type="checkbox"]')) {
			return;
		}

		updateSelectionCount();
	});

	document.addEventListener('submit', function (event) {
		if (event.target.matches('form') && !event.target.querySelector('input[type="checkbox"]:checked')) {
			event.preventDefault();
		}
	});

	updateSelectionCount();
}());
