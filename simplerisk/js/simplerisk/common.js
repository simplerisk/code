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
* popup when click "Score Using CVSS"
* 
* @param parent
*/
function popupcvss(parent) {
    parentOfScores = parent;

    var cve_id = $("#reference_id", parent).val();
    var pattern = /cve\-\d{4}-\d{4}/i;

    // If the field is a CVE ID
    if (cve_id !== undefined && cve_id.match(pattern)) {
        my_window = window.open(BASE_URL + '/management/cvss_rating.php?cve_id=' + cve_id, 'popupwindow', 'width=850,height=680,menu=0,status=0');
    }
    else my_window = window.open(BASE_URL + '/management/cvss_rating.php', 'popupwindow', 'width=850,height=680,menu=0,status=0');

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
    })

    $('body').on('click', '.hide-score-overtime', function (e) {
        e.preventDefault();
        var tabContainer = $(this).parents('.risk-session');
        $('.score-overtime-container', tabContainer).hide();
        $('.hide-score-overtime', tabContainer).hide();
        $('.show-score-overtime', tabContainer).show();
    })
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

	// Create the modal window
	let myModal = new bootstrap.Modal(
		$(`
			<div class="modal fade" tabindex="-1" role="dialog">
		        <div class="modal-dialog modal-md modal-dialog-centered modal-dark">
		            <div class="modal-content">
		                <div class="modal-body">
		                    <div class="form-group text-center message-container">
		                        <label class="message">${message}</label>
		                    </div>
		                    <div class="form-group text-center">
		                        <button class="btn btn-secondary" data-bs-dismiss="modal">${_lang['Cancel']}</button>
		                        <button class="btn btn-submit" data-bs-dismiss="modal">${_lang['Yes']}</button>
		                    </div>
		                </div>
		            </div>
		        </div>
		    </div>`
		),
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
    const allowedTags = ['br', 'ul', 'li', 'p', 'strong', 'em'];

    // Remove any tags that aren't in our allowlist
    const allElements = div.getElementsByTagName('*');
    for (let i = allElements.length - 1; i >= 0; i--) {
        const element = allElements[i];
        if (!allowedTags.includes(element.tagName.toLowerCase())) {
            // Replace the element with its text content
            element.outerHTML = element.textContent;
        }
    }

    return div.innerHTML;
}