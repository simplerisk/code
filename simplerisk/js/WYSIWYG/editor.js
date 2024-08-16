
function init_default_editor(selector) {

	tinymce.init({
		// Agreeing to the open source license terms
		license_key: 'gpl',

	    selector: selector,
	    statusbar: false,
	    // Tip! To make TinyMCE leaner, only include the plugins you actually need.
        plugins: 'searchreplace directionality visualblocks visualchars image link table charmap advlist lists help charmap quickbars',

        menubar: 'file edit view insert format tools table help',

	    // We have put our custom insert token button last.
        toolbar: 'undo redo | bold italic underline strikethrough | fontselect fontsizeselect formatselect | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | hr charmap image link | ltr rtl',
        toolbar_mode: 'wrap',
        quickbars_insert_toolbar: false,
        contextmenu: 'link image table',
        height: 600,

  		color_default_background: '#FBEEB8', // Set the default background color to light yellow
  		color_default_foreground: '#E03E2D', // Set the default text color to red

        branding: false,  // Remove the "Powered by Tiny"
        elementpath: false,  // Stop showing the selected element TAG
        promotion: false, // Don't display the 'Upgrade' button

        // Need to set the URL options to prevent converting urls to relative urls that would make it not work in emails and such
        relative_urls : false,
        remove_script_host : false,
        convert_urls : true,

		// Turn off the option to allow selection of the target of the link, in the code it'll be set to _blank anyway.
		link_target_list: false,

	});
}

// init editor for modal
function init_minimun_editor(selector) {

    tinymce.init({
		// Agreeing to the open source license terms
		license_key: 'gpl',

        selector: selector,
        statusbar: false,
        // Tip! To make TinyMCE leaner, only include the plugins you actually need.
        plugins: 'searchreplace directionality visualblocks visualchars image link table charmap advlist lists help charmap quickbars',

        menubar: false,
        toolbar: 'undo redo bold italic underline forecolor backcolor link align bullist numlist',
        toolbar_mode: 'wrap',
        quickbars_insert_toolbar: false,
        contextmenu: 'link image table',
        height: 250,

  		color_default_background: '#FBEEB8', // Set the default background color to light yellow
  		color_default_foreground: '#E03E2D', // Set the default text color to red

        // Need to set the URL options to prevent converting urls to relative urls that would make it not work in emails and such
        relative_urls : false,
        remove_script_host : false,
        convert_urls : true,

		// Turn off the option to allow selection of the target of the link, in the code it'll be set to _blank anyway.
		link_target_list: false,

        setup: function (editor) {
            editor.on('change', function () {
                editor.save();
            });
        }
    });
}
// Bootstrap modal integration for Bootstrap 4 or below
$(document).on('focusin', function(e) {
  if ($(e.target).closest(".tox-tinymce, .tox-tinymce-aux, .moxman-window, .tam-assetmanager-root").length) {
    e.stopImmediatePropagation();
  }
});
