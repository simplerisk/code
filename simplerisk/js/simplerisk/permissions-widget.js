
// update the checked property and the indeterminate property for all checkboxes when changing the checked property for any checkbox.
function check_indeterminate_checkboxes(_this) {

	var checked = _this.prop("checked");

	// li element that contains the clicked checkbox
	var container = _this.parent();

	// When clicking a checkbox, set the 'checked' property of it and its all children to be the same.
	container.find('input[type="checkbox"]').prop({
		indeterminate: false,
		checked: checked
	});

	// el is a li element that contains a checkbox
	// check the sibling checkboxes.
	function checkSiblings(el) {
		// when el is the highest level of li element, then it is div.permissions-widget element and when el is a child level, then it is the parent li element of el.
		var parent = el.parent().parent();

		var all = true;

		// check if all the checkbox siblings have the same value as the variable 'checked'.
		// if 'all' is true, then all the siblings have the same value as the variable 'checked'
		// else if 'all' is false, then at least one of the siblings has the different value from the variable 'checked'.
		el.siblings().each(function () {
			let returnValue = all = ($(this).children('input[type="checkbox"]').prop("checked") === checked);
			return returnValue;
		});

		// in case that all the checkbox siblings are checked
		if (all && checked) {

			// set the parent checkbox to be checked since all the checkbox siblings are checked.
			parent.children('input[type="checkbox"]').prop({
				indeterminate: false,
				checked: checked
			});

			// check parent checkbox's siblings.
			checkSiblings(parent);

			// in case that all the checkbox siblings are not checked(maybe some are indeterminate)
		} else if (all && !checked) {

			// set the parent checkbox to be unchecked since all the checkbox siblings are unchecked.
			parent.children('input[type="checkbox"]').prop("checked", checked);

			// set the parent checkbox to be indeterminate if at least one child checkbox is checked. 
			parent.children('input[type="checkbox"]').prop("indeterminate", (parent.find('input[type="checkbox"]:checked').length > 0));

			// check parent checkbox's siblings.
			checkSiblings(parent);

			// in case that there are checked siblings and also unchecked siblings 
		} else {

			// set 'indeterminate' to be true and 'checked' to be false for the parent checkbox
			el.parents("li").children('input[type="checkbox"]').prop({
				indeterminate: true,
				checked: false
			});
		}
	}

	checkSiblings(container);
}

function update_widget(permissions) {
	$(".permissions-widget input[type=checkbox]").prop("checked", false);
	$(".permissions-widget input[type=checkbox]").prop("indeterminate", false);
	make_checkboxes_editable();

	if (permissions) {
		for (var id of permissions) {
			let permission_checkbox = $(".permissions-widget input.permission[value='" + id + "']");
			permission_checkbox.prop("checked", true);
			check_indeterminate_checkboxes(permission_checkbox);
		}
	}
}

function make_checkboxes_readonly() {
	$(".permissions-widget input[type=checkbox]").prop("readonly", true);
	$(".permissions-widget").on('click', 'input[type=checkbox]', function () { return false; });
}

function make_checkboxes_editable() {
	$(".permissions-widget input[type=checkbox]").prop("readonly", false);
	$(".permissions-widget").off('click', 'input[type=checkbox]');
}

$(document).ready(function () {
	$('.permissions-widget input[type="checkbox"]').change(function (e) {
		check_indeterminate_checkboxes($(e.target));
	});

	$('.permissions-widget input[type="checkbox"].permission:checked').each(function () { check_indeterminate_checkboxes($(this)); });
});