/**
* When a file is added, should call this method
* 
* @param $parent
* @param currentButtonId: button ID for input[type=file].active
*/
function refreshFilelist($parent, currentButtonId) {
    var files = $("input[type=file]", $parent);

    var filesHtml = "";
    var filesLength = 0;
    $(files).each(function () {
        if (!$(this)[0].files.length) {
            return;
        }
        $(this).attr("id", "file-upload-" + filesLength)
        var name = escapeHtml($(this)[0].files[0].name);

        filesHtml += "<li >\
            <div class='file-name float-start me-2'>"+ name + "</div>\
            <a href='#' class='remove-file float-start' data-id='file-upload-"+ filesLength + "'><i class='fa fa-times'></i></a>\
        </li>";
        filesLength++;
    });
    $parent.find('.file-list').html(filesHtml);
    var totalFilesLength = $('.exist-files > li', $parent).length + filesLength;
    if (totalFilesLength > 1) {
        $msg = "<span class='file-count'>" + totalFilesLength + "</span> Files Added";
    } else {
        $msg = "<span class='file-count'>" + totalFilesLength + "</span> File Added";
    }
    $parent.find('.file-count-html').html($msg);

    var name = $parent.find('.file_name').data('file');
    if (!name)
        name = "file";

    if (currentButtonId) {
        $parent.prepend($('<input id="' + currentButtonId + '" name="' + name + '[]" class="d-none hidden-file-upload active" type="file">'))
    }

}
/**
* HTMLSPECIALCHARS
* 
* @param text
*/
function escapeHtml(text) {
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };

    return text.replace(/[&<>"']/g, function (m) { return map[m]; });
}

/**
 * Build the DOM for a saved file in a .file-uploader's .exist-files list
 * (download link + remove button + hidden unique_names[] input), matching the
 * server-side render, so persisted files stay visible after an AJAX save and
 * their unique_name is in the DOM for a reconcile-on-submit.
 *
 * Lives here in common.js (not a page module) so any feature using the shared
 * .file-uploader engine can re-render its .exist-files without duplicating this.
 * Kept feature-agnostic: unique_names[] is the app-wide hidden-input convention
 * (see includes/functions.php + includes/compliance.php); the download endpoint
 * differs per feature, so the caller passes downloadPath (relative to BASE_URL).
 *
 * @param file {name, unique_name}
 * @param downloadPath endpoint path relative to BASE_URL, no leading slash
 *                     (e.g. 'assessments/download.php')
 * @returns jQuery <li>
 */
function renderSavedFileLi(file, downloadPath) {
    var $li = $('<li>', { id: file.unique_name, 'class': 'd-flex align-items-center' });

    var $nameDiv = $('<div>', { 'class': 'file-name float-start me-2' });
    $('<a>', {
        'class': 'text-info text-decoration-underline',
        href: BASE_URL + '/' + downloadPath + '?id=' + encodeURIComponent(file.unique_name),
        target: '_blank',
        text: file.name
    }).appendTo($nameDiv);
    $li.append($nameDiv);

    $('<a>', { href: '#', 'class': 'remove-file', 'data-id': file.unique_name })
        .append($('<i>', { 'class': 'fa fa-times' }))
        .appendTo($li);

    $('<input>', { type: 'hidden', name: 'unique_names[]', value: file.unique_name })
        .appendTo($li);

    return $li;
}

/**
* popup when click "Score Using DREAD"
* 
*/
function popupdread(parent) {
    parentOfScores = parent;
    my_window = window.open(BASE_URL + '/management/dread_rating.php', 'popupwindow', 'width=850,height=500,menu=0,status=0');
}

/**
* popup when click "Score Using OWASP"
* 
*/
function popupowasp(parent) {
    parentOfScores = parent;
    my_window = window.open(BASE_URL + '/management/owasp_rating.php', 'popupwindow', 'width=850,height=570,menu=0,status=0');
}

/**
* popup when click "Score Using Contributing Risk"
* 
*/
function popupcontributingrisk(parent) {
    parentOfScores = parent;
    my_window = window.open(BASE_URL + '/management/contributingrisk_rating.php', 'popupwindow', 'width=850,height=570,menu=0,status=0');
}

function closepopup() {
    if (false == my_window.closed) {
        my_window.close();
    }
    else {
        alert('Window already closed!');
    }
}

function alert(message) {
    var modal_container_id = "alert-modal";
    if (!$("#" + modal_container_id).length) {
        var modal_html = `
            <div class="modal fade" id="${modal_container_id}" tabindex="-1" aria-labelledby="setting_modallable" aria-hidden="true">
                <div class="modal-dialog modal-md modal-dialog-scrollable modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-body">
                            <div class="form-group text-center message-container">
                                <label class="message">${message}</label>
                            </div>
                            <div class="form-group text-center">
                                <button class="btn btn-submit ok" data-bs-dismiss="modal" aria-hidden="true">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
        $("body").append(modal_html);
    }

    //$("#" + modal_container_id+" .message").html(message);

    $("#" + modal_container_id).modal('show');
}

// function to check empty / trimmed empty value for the required fields
function checkAndSetValidation(container) {

    let issue_els = [];
    $("input, select, textarea", container).each(function () {
        if ($(this).prop('required') && (!$.trim($(this).val()) || (Array.isArray($(this).val()) && $(this).val().length == 0))) {
            
            issue_els.push($(this));

            if (!$.trim($(this).val())) {
                $(this).val('');
            }
        }
    });

    // If issue elements exist, stop progress
    if (issue_els.length > 0) {
        
        issue_els.reverse();
        for (let key in issue_els) {

            let issue_el = issue_els[key];

            // if the element is a multiselect
            if (issue_el.parent().hasClass("multiselect-native-select")) {

                issue_el.parent().find("button.multiselect").addClass("error");

            // if the element is a normal one
            } else {

                issue_el.addClass("error");

            }

            // if the element is the first required element, focus on it.
            if (key == issue_els.length - 1) {

                // if the element is a multiselect
                if (issue_el.parent().hasClass("multiselect-native-select")) {

                    issue_el.parent().find("button.multiselect").focus();

                // if the element is a normal one
                } else {

                    issue_el.focus();

                }
            }

            // We have to make sure that no html gets through to toastr as it's displaying what it gets 'as is';
            var escaped = $("<div/>").text(issue_el.attr("title")).html();
            var message = _lang['FieldRequired'].replace("{$field}", escaped);

            showAlertFromMessage(message, false)
        }

        return false;

    } else {

        return true;

    }
}

var loading = {
    show: function (el) {
        this.getID(el).style.display = 'block';
    },
    hide: function (el) {
        this.getID(el).style.display = 'none';
    },
    getID: function (el) {
        return document.getElementById(el);
    }
};

$(document).ready(function () {
    if (jQuery.ui !== undefined) {
        jQuery.ui.autocomplete.prototype._resizeMenu = function () {
            var ul = this.menu.element;
            ul.outerWidth(this.element.outerWidth());
        }
    }

    $(document).on('click', '.exist-files .remove-file', function (event) {
        event.preventDefault();
        var $parent = $(this).parents('.file-uploader');
        var fileCount = Number($parent.find('.file-count').html()) - 1
        $parent.find('.file-count').html(fileCount)
        $(this).parent().remove();
    })

    $(document).on('click', '.file-list .remove-file', function (event) {
        event.preventDefault();
        var id = $(this).data('id');
        var $parent = $(this).parents('.file-uploader');
        $("#" + id, $parent).remove();
        refreshFilelist($parent)
    })

    $(document).on('change', '.hidden-file-upload.active', function (event) {
        var $parent = $(this).parents('.file-uploader');
        $(this).removeClass("active")
        var currentButtonId = $(this).attr('id');

        refreshFilelist($parent, currentButtonId)

    });

    $('body').on('click', '.show-score-overtime', function (e) {
        e.preventDefault();
        var tabContainer = $(this).parents('.risk-session');
        $('.score-overtime-container', tabContainer).show();
        $('.hide-score-overtime', tabContainer).show();
        $('.show-score-overtime', tabContainer).hide();
    });

    $('body').on('click', '.hide-score-overtime', function (e) {
        e.preventDefault();
        var tabContainer = $(this).parents('.risk-session');
        $('.score-overtime-container', tabContainer).hide();
        $('.hide-score-overtime', tabContainer).hide();
        $('.show-score-overtime', tabContainer).show();
    });

    // Refresh the file numbers on page load
    $('input[type=file].active').each(function() {
        refreshFilelist($(this).closest(".file-uploader"));
    });
})

// A function to properly reset a form.
// Add logic for new widgets when needed
function resetForm(formEL, multiselect = true, selectize = false) {

    let $form = $(formEL);
    $form[0].reset();

    // if there are any multiselects, refresh them
    if (multiselect) {
        $form.find('select.multiselect').multiselect('refresh');
    }

    // if there are any selectizes, refresh them
    if (selectize) {
        $form.find('select.selectized').each(function () {
            $(this)[0].selectize.clear();
        });
    }
    
}

/**
* Helper function to show a confirm window and runs the callback function when the user clicks on the
* You can call it like this if you want to have the original click's context in the confirm popup's callback function
*	$(document).on('click', '#template-tabs button.remove-tab', function(e) {e.stopPropagation(); confirm(_lang['ConfirmDisableTabbedExperience'], () => {
*		console.log("The original click's context", this);
*	})});
*
* It works like this, because the arrow function doesn't have its own context, so the outer function's context will be used
*
* If you don't need the original click's context then you can use this form
*	$(document).on('click', '#disable-tabbed-experience', () => confirm(_lang['ConfirmDisableTabbedExperience'], () => {
*  		// do stuff that needs no context
*	}));
*
* @param String message The message to be displayed in the confirm window
* @param Function callback The function called when the user chooses to confirm the action
*/
function confirm(message, callback) {

	// Build the modal window. The message is inserted via .text() (NOT
	// interpolated into the template HTML), so a caller passing untrusted or
	// unescaped text cannot inject markup — confirm() is safe-by-default for
	// every call site. Callers therefore pass the raw message; do not pre-escape.
	let $modal = $(`
			<div class="modal fade" tabindex="-1" role="dialog">
		        <div class="modal-dialog modal-md modal-dialog-centered modal-dark">
		            <div class="modal-content">
		                <div class="modal-body">
		                    <div class="form-group text-center message-container">
		                        <label class="message"></label>
		                    </div>
		                    <div class="form-group text-center">
		                        <button class="btn btn-secondary" data-bs-dismiss="modal">${_lang['Cancel']}</button>
		                        <button class="btn btn-submit" data-bs-dismiss="modal">${_lang['Yes']}</button>
		                    </div>
		                </div>
		            </div>
		        </div>
		    </div>`);
	$modal.find('.message').text(message);
	let myModal = new bootstrap.Modal(
		$modal,
		{/* Could add configuration here to change how the modal popup behaves. For more information check https://getbootstrap.com/docs/5.3/components/modal/ */}
	);

	// Add the callback
	$(myModal._element).find(`.btn-submit`).on('click', callback);

	// Add the logic to clean up the popup once it's hidden	
	$(myModal._element).on('hidden.bs.modal', function() { 
		$(this).remove();
	}); 

	// Show it
	myModal.show();
}

// Function to sanitize HTML while keeping basic formatting tags
function sanitizeHTML(str) {
    // Create a new div element
    const div = document.createElement('div');

    // Set the HTML content
    div.innerHTML = str;

    // Only allow specific HTML tags
    const allowedTags = ['br', 'ul', 'ol', 'li', 'p', 'strong', 'em', 'h4'];

    // Remove any tags that aren't in our allowlist; strip all attributes from
    // tags that are allowed (prevents onclick, onerror, style, and other
    // attribute-based XSS vectors on otherwise-permitted elements).
    const allElements = div.getElementsByTagName('*');
    for (let i = allElements.length - 1; i >= 0; i--) {
        const element = allElements[i];
        if (!allowedTags.includes(element.tagName.toLowerCase())) {
            // Replace the element with its text content
            element.outerHTML = element.textContent;
        } else {
            // Strip every attribute from allowed elements
            while (element.attributes.length > 0) {
                element.removeAttribute(element.attributes[0].name);
            }
        }
    }

    return div.innerHTML;
}

/**
 * Assets + Asset Groups selectize widget -- the ONE implementation.
 *
 * Every "affected assets" / "Mapped Assets" field in the product is the same
 * <select class="assets-asset-groups-select" multiple> driven by the same
 * selectize configuration; only the endpoint that supplies (and pre-selects)
 * the options differs -- risk-scoped vs control-scoped. That configuration
 * used to be copy-pasted per page: js/simplerisk/pages/governance.js had one
 * copy, js/simplerisk/pages/risk.js a second, and the Define Control
 * Frameworks redesign (js/simplerisk/pages/governance-frameworks.js) shipped
 * with NEITHER -- its control modal rendered the "Mapped Assets" label with
 * an inert <select> under it, so a user could not map an asset to a control
 * from that page at all, and Clone silently dropped a control's asset
 * mappings. Rather than add a third copy, the two existing copies now
 * delegate here and the redesigned page calls it directly.
 *
 * `request` is the only thing a caller varies:
 *   { url: <absolute options endpoint>, data: <query params> }
 * Use the setupAssetsAssetGroupsWidgetFor* wrappers below rather than passing
 * a hand-built request -- the endpoint choice carries a permission difference
 * (/asset-group/options accepts asset|assessments|riskmanagement|im_incidents,
 * /asset-group/options_by_control accepts asset|governance), so pointing a
 * risk form at the control endpoint 400s for a risk-only user.
 *
 * @param {jQuery} select_tag the <select> to enhance; a no-op when empty
 * @param {{url: string, data: Object}} request options-endpoint call
 * @returns {jQuery|undefined} the selectized element, or undefined for a no-op
 */
function setupAssetsAssetGroupsSelectize(select_tag, request) {

    if (!select_tag || !select_tag.length) {
        return;
    }

    // Idempotence guard. The callers below re-run over "every
    // .assets-asset-groups-select in this form" whenever a row is added or
    // removed (rows have to be re-indexed for the assets_asset_groups[N][]
    // POST names), so an already-enhanced select gets visited again on every
    // subsequent row change. selectize() on an already-selectized element
    // builds a SECOND control next to the first; without this, adding three
    // asset rows leaves the first row showing three stacked pickers.
    if (select_tag[0].selectize) {
        return select_tag;
    }

    var select = select_tag.selectize({
        plugins: ['optgroup_columns', 'remove_button', 'restore_on_backspace'],
        delimiter: ',',
        create: function (input) {
            return { id: 'new_asset_' + input, name: input };
        },
        persist: false,
        valueField: 'id',
        labelField: 'name',
        searchField: 'name',
        sortField: 'name',
        optgroups: [
            { class: 'asset', name: 'Standard Assets' },
            { class: 'group', name: 'Asset Groups' }
        ],
        optgroupField: 'class',
        optgroupLabelField: 'name',
        optgroupValueField: 'class',
        preload: true,
        render: {
            item: function (item, escape) {
                return '<div class="' + item.class + '">' + escape(item.name) + '</div>';
            }
        },
        onInitialize: function () {
            select_tag.parent().find('.selectize-control div').block({ message: '<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>' });
        },
        load: function (query, callback) {
            if (query.length) return callback();
            $.ajax({
                url: request.url,
                data: request.data,
                type: 'GET',
                dataType: 'json',
                error: function () {
                    callback();
                },
                success: function (res) {
                    var data = res.data;
                    var control = select[0].selectize;
                    var selected_ids = [];
                    // Have to do it this way, because addition with simple addOption() will
                    // bug out when we deselect an option(it wouldn't be added back to the
                    // list of selectable items)
                    var len = data.length;
                    for (var i = 0; i < len; i++) {
                        var item = data[i];
                        item.id += '_' + item.class;
                        control.registerOption(item);
                        if (item.selected == '1') {
                            selected_ids.push(item.id);
                        }
                    }
                    if (selected_ids.length)
                        control.setValue(selected_ids);
                },
                complete: function () {
                    select_tag.parent().find('.selectize-control div').unblock({ message: null });
                }
            });
        }
    });

    return select;
}

/**
 * Control-scoped variant: the "Mapped Assets" rows inside the add/update
 * control modal. `control_maturity` is the maturity level of the ROW being
 * built -- get_assets_and_asset_groups_by_control_for_dropdown() uses the
 * (control, maturity) pair to decide which assets are already mapped to THAT
 * row, so passing the wrong one pre-selects another row's assets.
 *
 * @param {jQuery} select_tag
 * @param {number|string} control_id 0/undefined for a control that does not exist yet
 * @param {number|string} control_maturity the row's maturity level
 */
function setupAssetsAssetGroupsWidgetForControl(select_tag, control_id, control_maturity) {
    return setupAssetsAssetGroupsSelectize(select_tag, {
        url: BASE_URL + '/api/v2/asset-group/options_by_control',
        data: {
            control_id: control_id,
            control_maturity: control_maturity
        }
    });
}

/**
 * Risk-scoped variant: the "Affected Assets" field on the add/edit risk form
 * and the risk form embedded in the compliance test views.
 *
 * @param {jQuery} select_tag
 * @param {number|string} risk_id 0 for a risk that does not exist yet
 */
function setupAssetsAssetGroupsWidgetForRisk(select_tag, risk_id) {
    return setupAssetsAssetGroupsSelectize(select_tag, {
        url: BASE_URL + '/api/v2/asset-group/options',
        data: {
            // Giving a default value here because IE can't handle
            // function parameter default values...
            id: risk_id || 0,
            type: 'risk'
        }
    });
}