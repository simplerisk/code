
function init_default_editor(selector, resizeable=false, height=600) {

	hugerte.init({
	    selector: selector,

        // Make the editor resizeable based on the parameter passed
        statusbar: resizeable ? true : false,
        resize: resizeable ? true : false,

	    // Tip! To make HugeRTE leaner, only include the plugins you actually need.
        plugins: 'searchreplace directionality visualblocks visualchars image link table charmap advlist lists help charmap quickbars',

        menubar: 'file edit view insert format tools table help',

	    // We have put our custom insert token button last.
        toolbar: 'undo redo | bold italic underline strikethrough | fontfamily fontsize blocks | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | hr charmap image link | ltr rtl',
        toolbar_mode: 'wrap',
        quickbars_insert_toolbar: false,
        contextmenu: 'link image table',
        height: height,

  		color_default_background: '#FBEEB8', // Set the default background color to light yellow
  		color_default_foreground: '#E03E2D', // Set the default text color to red

        branding: false,  // Remove the "Powered by HugeRTE"
        elementpath: false,  // Stop showing the selected element TAG
        promotion: false, // Don't display the 'Upgrade' button

        // Need to set the URL options to prevent converting urls to relative urls that would make it not work in emails and such
        relative_urls : false,
        remove_script_host : false,
        convert_urls : true,
        newline_behavior: 'invert',

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
        newline_behavior: 'invert',

		// Turn off the option to allow selection of the target of the link, in the code it'll be set to _blank anyway.
		link_target_list: false,

        setup: function (editor) {
            editor.on('change', function () {
                editor.save();
            });
        }
    });
}
// Compact editor for a field inside a modal's section card (design-system.md
// §8). init_minimun_editor() above is still "minimum" relative to the full
// editor, but in a half-width card column its 18-button toolbar wraps to five
// rows: 203px of chrome inside a 250px box, leaving ~47px to actually write in.
//
// This trims the toolbar to the formatting a test procedure actually needs --
// emphasis, lists, links -- which fits on one row at half width, and drops the
// height so several of these can sit in one modal without burying the fields
// below them. Same save-on-change wiring as the others, so callers using
// hugerte.triggerSave() / .save() are unaffected.
function init_compact_editor(selector, height = 150) {

    hugerte.init({
        selector: selector,
        statusbar: false,
        plugins: 'lists link autolink',
        menubar: false,
        // No separators: at half-width (a two-column card row) the pipes push
        // this onto a second toolbar row, which costs more height than the
        // grouping is worth.
        toolbar: 'bold italic underline bullist numlist link',
        toolbar_mode: 'wrap',
        quickbars_insert_toolbar: false,
        contextmenu: 'link',
        height: height,

        // Match the app's body copy so what you type looks like what renders
        // in the read-only procedure drawer.
        content_style: 'body{font-family:"Nunito Sans","Segoe UI",system-ui,-apple-system,sans-serif;font-size:13px;line-height:1.5;color:#3A3A3A;margin:8px 11px;} p{margin:0 0 6px;} ul,ol{margin:3px 0;padding-left:20px;}',

        // Need to set the URL options to prevent converting urls to relative urls that would make it not work in emails and such
        relative_urls : false,
        remove_script_host : false,
        convert_urls : true,
        newline_behavior: 'invert',

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
/**
 * The login system-use notice editor (Settings > Preferences > System).
 *
 * A DELIBERATELY SMALL toolbar: bold, italic, underline, lists and three named
 * sizes. No link button and no image button, because that value renders on the
 * login page, before anyone has authenticated -- a link there is a phishing
 * pivot sitting on the genuine login URL, and an image is a remote fetch fired
 * at every unauthenticated visitor.
 *
 * THE TOOLBAR IS NOT THE SECURITY CONTROL. It governs what this UI offers, not
 * what reaches the server: paste routinely carries markup no toolbar exposed,
 * and a request can be made without the UI at all. purify_html_login_notice()
 * (includes/functions.php) is the boundary, and it runs on save AND on render.
 * valid_elements below simply keeps the editor honest about what will survive,
 * so an admin is not shown formatting that silently disappears on save.
 *
 * The three sizes emit classes rather than inline styles. That keeps the style
 * attribute out of the server allowlist entirely and bounds the size, so no
 * setting an admin can make will overflow the login panel.
 */
function init_notice_editor(selector, height = 200) {

    hugerte.init({
        selector: selector,
        statusbar: false,
        menubar: false,
        plugins: 'lists',
        toolbar: 'bold italic underline | bullist numlist | styles',
        toolbar_mode: 'wrap',
        quickbars_insert_toolbar: false,
        contextmenu: false,
        height: height,

        // Mirrors the server allowlist exactly.
        valid_elements: 'p,br,b,strong,i,em,u,ul,ol,li,span[class]',
        valid_classes: { span: 'sr-notice-sm sr-notice-md sr-notice-lg' },

        // Labels through the L() global (header.php emits `_lang` for the keys
        // the page registers). These are the visible entries of the styles
        // dropdown, so a hardcoded English string here would be permanently
        // English in all 39 locales. L() falls back to the key name if the
        // page forgot to register them, which is visible rather than silent.
        style_formats: [
            { title: L('NoticeSizeSmall'), inline: 'span', classes: 'sr-notice-sm' },
            { title: L('NoticeSizeNormal'), inline: 'span', classes: 'sr-notice-md' },
            { title: L('NoticeSizeLarge'), inline: 'span', classes: 'sr-notice-lg' },
        ],
        style_formats_merge: false,

        // The notice renders on a dark panel; showing it on white in the editor
        // would misrepresent the contrast an admin is actually choosing.
        // The editor renders inside an iframe, which cannot reach the compiled
        // stylesheet -- so these values are necessarily a COPY. The source of
        // truth is $sr-notice-size-sm/md/lg in scss/modules/_auth.scss; change
        // them there and mirror here, or the preview stops matching the panel.
        content_style: 'body{font-family:"Nunito Sans","Segoe UI",system-ui,-apple-system,sans-serif;font-size:13px;line-height:1.55;color:#e9e9e9;background:#3A3A3A;margin:8px 11px;} p{margin:0 0 8px;} ul,ol{margin:3px 0;padding-left:20px;} .sr-notice-sm{font-size:11.5px;} .sr-notice-md{font-size:13px;} .sr-notice-lg{font-size:15.5px;}',

        setup: function (editor) {
            editor.on('change', function () {
                editor.save();
            });
        }
    });
}
