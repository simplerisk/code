
function init_default_editor(selector) {

	tinymce.init({
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

        // Need to set the URL options to prevent converting urls to relative urls that would make it not work in emails and such
        relative_urls : false,
        remove_script_host : false,
        convert_urls : true
	});
}