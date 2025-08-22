
function init_default_editor(selector) {

	hugerte.init({
	    selector: selector,
	    statusbar: false,
	    // Tip! To make HugeRTE leaner, only include the plugins you actually need.
        plugins: 'searchreplace directionality visualblocks visualchars image link table charmap advlist lists help charmap quickbars',

        menubar: 'file edit view insert format tools table help',

	    // We have put our custom insert token button last.
        toolbar: 'undo redo | bold italic underline strikethrough | fontfamily fontsize blocks | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | hr charmap image link | ltr rtl',
        toolbar_mode: 'wrap',
        quickbars_insert_toolbar: false,
        contextmenu: 'link image table',
        height: 600,

  		color_default_background: '#FBEEB8', // Set the default background color to light yellow
  		color_default_foreground: '#E03E2D', // Set the default text color to red

        branding: false,  // Remove the "Powered by HugeRTE"
        elementpath: false,  // Stop showing the selected element TAG
        promotion: false, // Don't display the 'Upgrade' button

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

// init editor for modal
function init_minimun_editor(selector) {

    hugerte.init({
        selector: selector,
        statusbar: false,
        // Tip! To make HugeRTE leaner, only include the plugins you actually need.
        plugins: 'searchreplace directionality visualblocks visualchars image link table charmap advlist lists help charmap quickbars',

        menubar: false,
        toolbar: 'undo redo bold italic underline fontfamily fontsize blocks forecolor backcolor link align bullist numlist',
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
// Prevent Bootstrap dialog from blocking focusin
// This code is required because Bootstrap blocks all focusin calls from elements outside the dialog. For a working example, try this TinyMCE fiddle: https://fiddle.tiny.cloud/TPhaab/0
document.addEventListener('focusin', (e) => {
    if (e.target.closest(".tox-hugerte-aux, .moxman-window, .tam-assetmanager-root") !== null) {
        e.stopImmediatePropagation();
    }
});