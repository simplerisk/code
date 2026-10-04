$.fn.extend({
    initAsAssociatedExceptionTreegrid: function(type=false) {

        // Can't initialize it twice
        if (this.data('initialized')) {
            this.treegrid("resize");
            return;
        }

        // .sr-tab-pane/.is-active, not Bootstrap's .tab-pane/.active --
        // management/partials/viewhtml.php's Associated Exceptions tabs
        // (this function's only caller) deliberately don't use Bootstrap's
        // tab classes, so header.php's sitewide 'tabs:logic' script (which
        // assumes one tab hierarchy per page) never touches them; see that
        // markup's own comment for the two page-breaking bugs this avoided.
        let tabs = this.parents('.sr-tab-pane');
        let activeTabs = this.parents('.sr-tab-pane.is-active');

        // Can't initialize if not all of the parent tabs(if there's any) active
        // because the treegrid doesn't properly initialize in the background
        if (tabs.length != activeTabs.length) {
            return;
        }

        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();

        this.treegrid({
            iconCls: 'icon-ok',
            animate: false,
            fitColumns: true,
            nowrap: true,
            url: BASE_URL + `/api/v2/associated-exceptions/tree?type=${type}&id=${risk_id}`,
            method: 'get',
            idField: 'value',
            treeField: 'name',
            scrollbarSize: 0,
            loadFilter: function(data) {
                return data.data;
            },
            onLoadSuccess: function(row, data){
                // fixTreeGridCollapsableColumn();
                // Refresh exception counts in the tabs
                var totalCount = 0;
                if((data && data.length))
                {
                    for(var i = 0; i < data.length; i++)
                    {
                        var parent = data[i];
                        if((parent.children && parent.children.length))
                        {
                            totalCount += parent.children.length;
                        }
                    }
                }
                
                $(`#${type}-exceptions-count`).text(totalCount);

                if (typeof wireActionButtons === 'function') {
                    wireActionButtons(type);
                }
            }
        });

        $(this).data('initialized', true);
    }
});

// Variable to be used to prevent the form from being submitted multiple times
var loading = false;

function addRisk($this){
    var tabContainer = $this.closest('.tab-data');

    // Check the sum of files the user wants to upload and
    // stop if it's over the max_upload_size
    if (!validFileUploadSize(tabContainer)) {
    	return false;
    }

    var getForm = $this.closest("form");
    // new FormData(form) already serializes every `file[]` input in the form
    // -- and the file-uploader widget's inputs (common.js) live inside it, so
    // there is nothing extra to collect. This used to also loop over
    // `input[type=file]` and re-append each File as `file[<j>]`, which
    // produced a body like
    //   file[]=<empty placeholder>, file[]=f2, file[]=f1, file[0]=f2, file[0]=f1
    // PHP's multipart parser resolves that to $_FILES['file'] =
    // [0=>f1, 1=>f2, 2=>f1] -- the explicit `file[0]` parts OVERWRITE the
    // empty placeholder slot at index 0 (verified against a live PHP 8.3
    // request, not inferred). The retired management/index.php handler
    // happened to survive that because it unconditionally skipped index 0;
    // addRisk() correctly skips only UPLOAD_ERR_NO_FILE slots, so the
    // duplicate parts would upload the last file twice. Dropping the loop
    // leaves $_FILES['file'] = [0=>UPLOAD_ERR_NO_FILE, 1=>f2, 2=>f1], which
    // addRisk() handles exactly right: one upload per selected file.
    var form = new FormData($(getForm)[0]);

    // Check valiation and stop if failed
    if(!checkAndSetValidation(tabContainer)) {
        return false;
    }
    $.blockUI({message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>', baseZ:'10001'});
    // POST /api/v2/risks (addRisk(), simplerisk/includes/api.php) is the one
    // maintained risk-creation path. This used to POST to
    // /management/index.php, whose inline handler was a near-duplicate of
    // addRisk(); that handler was retired when Submit Risk became a
    // client-rendered page, so the old URL now answers with an HTML page
    // shell instead of the JSON envelope this callback reads.
    //
    // The request is unchanged: still a FormData snapshot of the same form,
    // with contentType/processData false. No explicit CSRF token is added
    // here -- csrf-magic.js patches XMLHttpRequest.send (and therefore
    // jQuery's transport) to inject the token on every same-origin POST,
    // which is exactly how submit-risk.js reaches the same endpoint.
    //
    // addRisk()'s response carries data.risk_id, data.associate_test and a
    // status_message that is ALWAYS populated on success -- it has to be, since
    // most of that endpoint's callers are sessionless API-key integrations that
    // have no session alert to fall back on. In this browser flow it is a
    // second copy of a message set_alert() has also queued in the session, so
    // it is only rendered where a toast can actually be read: see the branches
    // in success: below.
    $.ajax({
        type: "POST",
        url: BASE_URL + "/api/v2/risks",
        data: form,
        async: true,
        cache: false,
        contentType: false,
        processData: false,
        success: function(data){
            var risk_id = data.data.risk_id;
            var associate_test = data.data.associate_test;
            if(associate_test == 1) {
                // addRisk() hands this flow the alert-ARRAY shape and CLEARS
                // the session copy, precisely because the form#edit-test
                // submit below reloads the page: rendering it here is the only
                // chance to show it, and a leftover session copy would replay
                // it on the page that submit lands on.
                if(data.status_message){
                    showAlertsFromArray(data.status_message);
                }
                $("#modal-new-risk").modal("hide");
                $("#associate_new_risk_id").val(risk_id);
                $('form#edit-test').submit();
                return;
            }

            // Embedded-modal "stay on the page" contract: a modal opts into
            // this behavior by carrying data-on-save="refresh-and-close" on
            // itself (or an ancestor of the submitted form) -- e.g.
            // includes/display.php's #review-risk-add-modal (Review Risk's
            // + Add Risk modal). risk.js stays page-agnostic here: it doesn't
            // know Review Risk exists, it just hides the modal and fires a
            // generic 'simplerisk:risk-created' event on document with the
            // new risk_id; any current or future page listens for that event
            // to close its own modal (if not already closed here), refresh
            // its grid, etc. This must be checked before the default
            // full-page redirect below, since that redirect is exactly what
            // an embedded-modal context needs to avoid.
            //
            // No toast on this branch or the redirect below: for both of them
            // addRisk() left its success message queued in the session, so
            // rendering data.status_message here would show it a second time
            // on the next page the user loads.
            var saveModal = getForm.closest('[data-on-save="refresh-and-close"]');
            if (saveModal.length) {
                saveModal.modal("hide");
                $(document).trigger('simplerisk:risk-created', { risk_id: risk_id });
                $this.prop('disabled', true);
                return;
            }

            window.onbeforeunload = null;
            window.location.href = BASE_URL + '/management/view.php?id=' + risk_id;

            $this.prop('disabled', true);
        },
        complete: function(){
            $.unblockUI();
        }
    })
    .fail(function(xhr, textStatus){
        if(!retryCSRF(xhr, this))
        {
            if(xhr.responseJSON && xhr.responseJSON.status_message){
                showAlertsFromArray(xhr.responseJSON.status_message);
            }
        }
        $this.removeAttr('disabled');
    });
  }
  
  /**
  * Process after ajax call
  * 
  * @param tabContainer
  * @param RSTabIndex(Risk Tab Index): 0: Details, 1: Mitigation, 2: Review
  */
  function callbackAfterRefreshTab(tabContainer, RSTabIndex){

        $('.collapsible', tabContainer).hide();
        switch(RSTabIndex) {
              default:
              case 0:
                var tabId = '#tab_details'
                break;
              case 1:
                var tabId = '#tab_mitigation'
                break;
              case 2:
                var tabId = '#tab_review'
                break;
        }
        var seletedTabEl = document.querySelector(tabId);
        var riskTab = new bootstrap.Tab(seletedTabEl);

        riskTab.show();

        // if datepicker element exists, build datepicker.
        if($( ".datepicker" , tabContainer).length){
            $( ".datepicker" , tabContainer).initAsDatePicker();
        }
        var tabIndex = tabContainer.index();
        var riskID = $('.risk-id', tabContainer).text().trim();
        var subject = $('input[name="subject"]', tabContainer).val();
        $('.tab-append .tab').eq(tabIndex).find("span")
            .empty()
            .append($('<b>').text('ID:' + riskID + ' '))
            .append(document.createTextNode(subject));
        
        // if file upload button exists, set the unique ID
        if($(".hidden-file-upload.active", tabContainer).length){
            $(".hidden-file-upload.active", tabContainer).attr('id', 'file-upload' + tabIndex);
            $("[for=file-upload]", tabContainer).attr('for', 'file-upload' + tabIndex);
        }

        setupAssetsAssetGroupsWidget($('select.assets-asset-groups-select', tabContainer), riskID);
        setupAssetsAssetGroupsViewWidget($('select.assets-asset-groups-select-disabled', tabContainer));

        /**
        * Set Risk Scoring Method dropdown and show/hide the sub views
        */
        handleSelection($("[name=scoring_method]", tabContainer).val(), tabContainer);
        
        /**
        * Build multiselect box
        */
        $(".multiselect", tabContainer).multiselect({enableFiltering: true, buttonWidth: '100%', enableCaseInsensitiveFiltering: true,});

        // destroy all WYSIWYG editors
        destroy_all_editors()

        // If there're template tabs we have to separately initialize the WYSIWYG editors
        if ($("#template_group_id").length > 0) {
            // We have to make sure the IDs are unique so we're appending the template's ID to the textarea's ID to make it unique
    
            $("[name='assessment']").each(function() {
                let template_group_id = $(this).closest('form').find('#template_group_id').val();
                $(this).attr('id', 'assessment_' + template_group_id);
                init_minimun_editor("#assessment_" + template_group_id);
            });
    
            $("[name='notes']").each(function() {
                let template_group_id = $(this).closest('form').find('#template_group_id').val();
                $(this).attr('id', 'notes_' + template_group_id);
                init_minimun_editor("#notes_" + template_group_id);
            });

            $("[name='current_solution']").each(function() {
                let template_group_id = $(this).closest('form').find('#template_group_id').val();
                $(this).attr('id', 'current_solution_' + template_group_id);
                init_minimun_editor("#current_solution_" + template_group_id);
            });

            $("[name='security_requirements']").each(function() {
                let template_group_id = $(this).closest('form').find('#template_group_id').val();
                $(this).attr('id', 'security_requirements_' + template_group_id);
                init_minimun_editor("#security_requirements_" + template_group_id);
            });

            $("[name='security_recommendations']").each(function() {
                let template_group_id = $(this).closest('form').find('#template_group_id').val();
                $(this).attr('id', 'security_recommendations_' + template_group_id);
                init_minimun_editor("#security_recommendations_" + template_group_id);
            });

            $("[name='comments']").each(function() {
                let template_group_id = $(this).closest('form').find('#template_group_id').val();
                $(this).attr('id', 'comments_' + template_group_id);
                init_minimun_editor("#comments_" + template_group_id);
            });
        } else {
            // init WYSIWYG editors
            init_minimun_editor("#assessment");
            init_minimun_editor("#notes");
            init_minimun_editor('#current_solution');
            init_minimun_editor('#security_requirements');
            init_minimun_editor('#security_recommendations');
            init_minimun_editor('#comments');
        }
    }
    
    
    // Thin wrapper over the shared implementation in
    // js/simplerisk/common.js. The selectize configuration used to be
    // duplicated here and in js/simplerisk/pages/governance.js; both now
    // delegate so the Define Control Frameworks redesign could adopt it
    // instead of adding a third copy. Every page that loads this file also
    // loads CUSTOM:common.js (management/index.php, management/view.php,
    // compliance/testing.php, compliance/view_test.php).
    function setupAssetsAssetGroupsWidget(select_tag, risk_id) {
        return setupAssetsAssetGroupsWidgetForRisk(select_tag, risk_id);
    }
    
    
    function setupAssetsAssetGroupsViewWidget(select_tag) {
        
        if (!select_tag.length)
            return;
        
        var select = select_tag.selectize({
            sortField: 'text',
            disabled: true,
            render: {
                item: function(item, escape) {
                    return '<div class="' + item.class + '">' + escape(item.text) + '</div>';
                }
            }
        });
        
        select[0].selectize.disable();
        select_tag.parent().find('.selectize-control div').removeClass('disabled');
    }    
  	/*
  	  	Check the sum of files the user wants to upload
		Displays an error message if it's over and returns false.
  	 */
  	function validFileUploadSize(container) {
  		// If both variable defined
  		if (typeof max_upload_size != "undefined" && typeof fileTooBigMessage != "undefined" && max_upload_size && fileTooBigMessage) {
			var filesSize = 0;

			// Sum the files' sizes
			$.each($(".file-uploader input[type=file].hidden-file-upload", container), function(i, obj) {
				$.each(obj.files, function(j, file){
					filesSize += file.size;
				})
			});

			// If the sum of the files' size went over the max
			// display an error message and stop
			if (filesSize > max_upload_size) {
				toastr.error(fileTooBigMessage);
				return false;
			}
  		}
  		return true;
  	}

    function showHelp(divId) {
        $("#divHelp").html($("#"+divId).html());
    };
    function hideHelp() {
        $("#divHelp").html("");
    }

$(document).ready(function(){

    $('body').on('click', '.save-risk-form', function (){
        addRisk($(this));
    })


    /********* Start Subject ***********/
    $('body').on('click', '.edit-subject-btn', function (e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        $('.edit-subject', tabContainer).removeClass('d-none');
        $('.edit-subject', tabContainer).show();
        $('.static-subject', tabContainer).hide();
    });

    $('body').on('click', '.cancel-edit-subject', function (e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        $('.edit-subject', tabContainer).hide();
        $('.static-subject', tabContainer).show();
    });

    function updateSubject($this){
        var tabContainer = $this.parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        
        var getForm = $this.parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);
        $.each($("input[type=file]", tabContainer), function(i, obj) {
            $.each(obj.files,function(j, file){
                form.append('file['+j+']', file);
            })
        });
        $('.risk-session').block({
            message: 'Processing',
            css: { border: '1px solid black', background: '#ffffff' }
        });
        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/management/risk/saveSubject?id=" + risk_id,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data){
                if($('.show-score').is(":visible")){
                    $('.overview-container', tabContainer).html(data.data);
                    $('.show-score').show();
                    $('.hide-score').hide();
                }else{
                    $('.overview-container', tabContainer).html(data.data);
                    $('.show-score').hide();
                    $('.hide-score').show();
                }
                if(data.status_message){
                    showAlertsFromArray(data.status_message);
                }
                // This legacy header only re-renders its own overview-container
                // above -- the Cards Details tab (risk-view-details.js) holds a
                // SEPARATE, independently-fetched copy of Subject in its own
                // read-mode cache (cachedViewData), which a Subject save here
                // never touches. Without this, the Details tab's General card
                // silently shows the pre-save Subject until a full page reload.
                // window.RiskViewDetails.render() is the same re-fetch hook
                // details.php's own inline <script> already calls after Change
                // Status/Close Risk/etc. re-render the whole details partial.
                if (window.RiskViewDetails && typeof window.RiskViewDetails.render === 'function') {
                    window.RiskViewDetails.render();
                }
            }
        })
        .fail(function(xhr, textStatus){
            if(!retryCSRF(xhr, this))
            {
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    }

    $('body').on('click', 'button[name=update_subject]', function(e){
        e.preventDefault();

        updateSubject($(this));
    });    
    /********* End Subject **********/
    
    $('body').on('click', ".add-comment-menu", function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var collapseEl = $(".comment-form", tabContainer).parents('.accordion-collapse')[0];

        function focusCommentText() {
            $(".comment-text", tabContainer).focus();
        }

        // Comments is a standard Bootstrap accordion (management/partials/
        // viewhtml.php: .accordion-button[data-bs-toggle=collapse] +
        // .accordion-collapse), already auto-wired by Bootstrap's own JS --
        // a raw .slideDown() on the collapse div here bypassed that
        // component entirely, so the button's own .collapsed class and
        // aria-expanded stayed stale (its chevron never rotated) even once
        // the body was visibly open, and nothing scrolled the section into
        // view. bootstrap.Collapse is the same API new bootstrap.Tab()
        // above already uses for an identical reason -- driving the real
        // component instead of faking its visual effect by hand.
        if (collapseEl.classList.contains('show')) {
            // Already open (e.g. a second click from the menu) -- .show()
            // itself is a safe no-op here, but 'shown.bs.collapse' won't
            // fire again to focus the textarea, so do that immediately.
            focusCommentText();
        } else {
            collapseEl.addEventListener('shown.bs.collapse', function onShown() {
                collapseEl.removeEventListener('shown.bs.collapse', onShown);
                focusCommentText();
            });
        }
        bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();

        // The trigger button's own position is stable throughout the
        // expand animation (only the body below it grows), so this can
        // scroll immediately rather than waiting on 'shown.bs.collapse'
        // the way focusing the textarea inside the body has to.
        collapseEl.closest('.accordion-item').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    
    /**** start details ****/
    // '.edit-risk' (the Actions-menu "Edit Risk" link, view_top_table(),
    // includes/display.php) used to share this selector -- removed because
    // this handler's own AJAX-partial-refresh target (.content-container)
    // predates the Cards redesign and no longer matches what's actually on
    // the page, so intercepting the click here only prevented the link's
    // real href (view.php?action=editdetail&id=X) from ever navigating.
    // risk-view-details.js now owns '.edit-risk' directly (its own
    // maybeAutoOpenFromDeepLink(), reading that same query param, plus a
    // direct handler for the same-page case -- see that file for both).
    // '[name=edit_details]' has no live matching markup today (its one
    // definition, view_risk_details() in includes/display.php, has zero
    // callers) but is left bound here rather than removed, since nothing in
    // this task depends on deleting it and a future re-introduction of that
    // markup would otherwise silently lose this handler.
    $('body').on('click', '[name=edit_details]', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        var $this = $(this);

        editDetailsRequest(risk_id, tabContainer);
    })
    
    function editDetailsRequest(risk_id, tabContainer){
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/v2/management/risk/editdetails?action=editdetail&id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 0);
                
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    }
    
    
    $('body').on('click', '.cancel-edit-details', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        var $this = $(this);
        
        cancelEditDetailsRequest(risk_id, tabContainer);
    })
    
    function cancelEditDetailsRequest(risk_id, tabContainer){
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/v2/management/risk/editdetails?id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 0);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    }
    
    function updateRisk($this){
        var tabContainer = $this.parents('.tab-data');

        // Check the sum of files the user wants to upload and
        // stop if it's over the max_upload_size
        if (!validFileUploadSize(tabContainer)) {
        	return false;
        }

        var risk_id = $('.risk-id', tabContainer).html();

        // Check valiation and stop if failed
        if(!checkAndSetValidation(tabContainer))
        {
            return false;
        }
        var getForm = $this.parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);
        var scoring_method = $("[name=scoring_method]", tabContainer).val();

        $.each($("select.multiselect", getForm), function(i, obj) {
            if(!form.has(obj.name)) form.append(obj.name, $(obj).val());
        });

        $.each($("input[type=file]", tabContainer), function(i, obj) {
            $.each(obj.files,function(j, file){
                form.append('file['+j+']', file);
            })
        });
        $('.content-container').block({
            message: 'Processing',
            css: { border: '1px solid black', background: '#ffffff'},
            baseZ:'10001'
        });

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/management/risk/saveDetails?id=" + risk_id,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data){
                $('.content-container').unblock();
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 0);
                getScoreByAction(tabContainer, scoring_method);

                if(data.status_message){
                    showAlertsFromArray(data.status_message);
                }
            }
        })
        .fail(function(xhr, textStatus){
            $('.content-container').unblock();
            if(!retryCSRF(xhr, this))
            {
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    }
    
    $('body').on('click', '.save-details', function(e){
        e.preventDefault();

        updateRisk($(this));
        
    });
    /*** end details tab ***/
    
    
    /**** start mitigation *****/
    // '.edit-mitigation' (the Actions-menu "Plan a Mitigation" link,
    // view_top_table(), includes/display.php) used to share this selector --
    // removed for the same reason '.edit-risk' was (see that handler's own
    // comment above): this handler's .content-container replacement target
    // predates the Cards redesign. risk-view-mitigation.js now owns
    // '.edit-mitigation' directly. '[name=edit_mitigation]' has no live
    // matching markup today (view_mitigation_details() in includes/
    // display.php has zero callers) but is left bound, same reasoning as
    // '[name=edit_details]' above.
    $('body').on('click', '[name=edit_mitigation]', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/v2/management/risk/editdetails?action=editmitigation&id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 1);
//                $('.tabs2', tabContainer).find('input, select, textarea').prop('disabled', false);

            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    })
    
    $('body').on('click', '.cancel-edit-mitigation', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/v2/management/risk/editdetails?id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 1);
//                $('.tabs2', tabContainer).find('input, select, textarea').prop('disabled', false);

            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    })

    function updateMitigation($this) {
        var tabContainer = $this.parents('.tab-data');

        // Check the sum of files the user wants to upload and
        // stop if it's over the max_upload_size
        if (!validFileUploadSize(tabContainer)) {
        	return false;
        }

        var risk_id = $('.risk-id', tabContainer).html();
        
        // Check valiation and stop if failed
        if(!checkAndSetValidation(tabContainer))
        {
            return false;
        }

        var getForm = $this.parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);
        $.each($("input[type=file]", tabContainer), function(i, obj) {
            $.each(obj.files,function(j, file){
                form.append('file['+j+']', file);
            })
        });

        $('.content-container').block({
            message: 'Processing',
            css: { border: '1px solid black', background: '#ffffff'},
            baseZ:'10001'
        });

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/management/risk/saveMitigation?id=" + risk_id,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(result){
                $('.content-container').unblock();
                var data = result.data;
                $('.content-container', tabContainer).html(data.html);
                $('.score--wrapper', tabContainer).html(data.score_wrapper_html);
                callbackAfterRefreshTab(tabContainer, 1);
                if(result.status_message){
                    showAlertsFromArray(result.status_message);
                }
                
                // Need to reload the page since the scoring history chart is not updating after mitigation update.
                location.reload();
                
            }
        })
        .fail(function(xhr, textStatus){
            if(!retryCSRF(xhr, this))
            {
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }

        });
    }
    
    $('body').on('click', '[name=update_mitigation]', function(e){
        e.preventDefault();
        updateMitigation($(this));
    });
    /****** end mitigation *******/

    /**** start review *****/
    $('body').on('click', '.perform-review', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/v2/management/risk/editdetails?action=editreview&id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 2);
//                $('.tabs3', tabContainer).find('input, select, textarea').prop('disabled', false);

            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    })
    
    $('body').on('click', '.cancel-edit-review', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/v2/management/risk/editdetails?id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 2);
//                $('.tabs3', tabContainer).find('input, select, textarea').prop('disabled', false);

            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    })
    
    function updateReview($this){
        var tabContainer = $this.parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        
        // Check valiation and stop if failed
        if(!checkAndSetValidation(tabContainer))
        {
            return false;
        }

        $('.save-review').prop('disabled', true);
        var getForm = $this.parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);
        $.each($("input[type=file]", tabContainer), function(i, obj) {
            $.each(obj.files,function(j, file){
                form.append('file['+j+']', file);
            })
        });
        
        $('.content-container').block({
            message: 'Processing',
            css: { border: '1px solid black', background: '#ffffff'},
            baseZ:'10001'
        });

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/management/risk/saveReview?id=" + risk_id,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data){
                $('.content-container').unblock();
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 2);
                if(data.status_message){
                    showAlertsFromArray(data.status_message);
                }
                $('.save-review').prop('disabled', false)
            }
        })
        .fail(function(xhr, textStatus){
            if(!retryCSRF(xhr, this))
            {
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
            $('.save-review').prop('disabled', false);
        });
    }
    
    $('body').on('click', '.save-review', function(e){
        e.preventDefault();
        updateReview($(this));
    });

    $('body').on('click', '[name=view_all_reviews], .view-all-reviews', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        if($('.current_review', tabContainer).is(":visible")){
            $('.all_reviews', tabContainer).show();
            $('.current_review', tabContainer).hide();
            $('.all_reviews_btn', tabContainer).html($('#lang_last_review').val());
        }else{
            $('.all_reviews', tabContainer).hide();
            $('.current_review', tabContainer).show();
            $('.all_reviews_btn', tabContainer).html($('#lang_all_reviews').val());
        }
    });

    $('body').on('click', '.view_all_reviews', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/v2/management/risk/view_all_reviews?id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    });
    /***** end review ******/
    
    /*************** start close risk ******************/
    // Close Risk as a real sr-modal (design-system.md #8's Form-in-modal
    // type) -- replaces the old .close-risk/.save-close-risk pair, which
    // fetched a legacy Reason-dropdown+note form and injected it into
    // .content-container, a target that predates the Cards redesign and no
    // longer holds tab content the same way (the same broken shape
    // .edit-risk/.edit-mitigation had -- see those fixes' own commits).
    //
    // Built lazily, once, on first open -- not up front on every page load --
    // matching this file's own established "build once, reuse the singleton"
    // shape (the Status inline editor above does the same). The Reason
    // dropdown's OPTIONS are extracted from closeriskHtmlForm()'s existing
    // GET response (management/partials/close.php) the exact same way the
    // Status editor already extracts its own <select> from a fetched
    // fragment -- reusing that server-side option list as-is rather than
    // building a second, parallel options endpoint for one dropdown.
    var $closeRiskModal = null;
    var closeRiskOptionsLoaded = false;

    function buildCloseRiskModal() {
        if ($closeRiskModal) {
            return $closeRiskModal;
        }

        var $reasonSelect = $('<select>', { 'class': 'form-select', name: 'close_reason' });
        var $noteTextarea = $('<textarea>', { 'class': 'form-control', name: 'note', rows: 3 });

        var $cancelBtn = $('<button>', { type: 'button', 'class': 'btn btn-dark', 'data-bs-dismiss': 'modal' }).text(_lang['Cancel']);
        var $saveBtn = $('<button>', { type: 'button', 'class': 'btn btn-submit close-risk-modal-save' }).text(_lang['Submit']);

        $closeRiskModal = $('<div>', { 'class': 'modal fade sr-modal', tabindex: '-1', 'aria-hidden': 'true' }).append(
            $('<div>', { 'class': 'modal-dialog modal-dialog-centered' }).append(
                $('<div>', { 'class': 'modal-content' }).append(
                    $('<div>', { 'class': 'modal-header' })
                        .append($('<span>', { 'class': 'sr-modal-icon' }).append($('<i>', { 'class': 'fa fa-lock', 'aria-hidden': 'true' })))
                        .append($('<h4>', { 'class': 'modal-title' }).text(_lang['CloseRisk']))
                        .append($('<button>', { type: 'button', 'class': 'btn-close', 'data-bs-dismiss': 'modal', 'aria-label': _lang['Cancel'] })),
                    // .sr-qcard-body is required by design-system.md #8's
                    // canonical shape (see the compliance.php #apply-common-
                    // test reference modal) -- without it the fields sat
                    // flush against the card's own edges with zero interior
                    // padding, reading as a bare white rectangle rather than
                    // a section card on the modal's grey canvas.
                    //
                    // Deliberately NO .sr-qcard-head here, unlike every
                    // reference implementation: at this modal's compact
                    // size (short header immediately followed by the card,
                    // almost no grey canvas visible between them) a second
                    // icon+bold-title+border-bottom bar directly under the
                    // real modal header read as nested modal chrome, not a
                    // section label -- confirmed live, twice (first with the
                    // head re-titled 'Reason' to de-duplicate the literal
                    // "Close Risk"/"Close Risk" text, which still looked
                    // like two modals stacked). Two fields with self-
                    // explanatory labels don't need a section grouping label
                    // to begin with; the plain .sr-qcard-body's padding/
                    // border alone is what this modal needed.
                    $('<div>', { 'class': 'modal-body' }).append(
                        $('<section>', { 'class': 'sr-qcard' })
                            .append(
                                $('<div>', { 'class': 'sr-qcard-body' }).append(
                                    $('<div>', { 'class': 'sr-qgrid' })
                                        .append(
                                            $('<div>', { 'class': 'sr-qfield sr-qfield--full' })
                                                .append($('<label>', { 'class': 'sr-qlabel' }).text(_lang['Reason']))
                                                .append($reasonSelect)
                                        )
                                        .append(
                                            $('<div>', { 'class': 'sr-qfield sr-qfield--full' })
                                                .append($('<label>', { 'class': 'sr-qlabel' }).text(_lang['CloseOutInformation']))
                                                .append($noteTextarea)
                                        )
                                )
                            )
                    ),
                    $('<div>', { 'class': 'modal-footer' }).append($cancelBtn).append($saveBtn)
                )
            )
        ).appendTo('body');

        $closeRiskModal.data('reasonSelect', $reasonSelect);
        $closeRiskModal.data('noteTextarea', $noteTextarea);

        $saveBtn.on('click', function (e) {
            e.preventDefault();
            var risk_id = $closeRiskModal.data('riskId');
            // NOT $('.tab-data').first() -- management/view.php also renders
            // a bare, permanently-hidden `<div class='tab-data hide'>`
            // template that sorts first in document order, so `.first()`
            // silently targets it instead of the real, visible container.
            // NOT $('#tab-content-container') either -- that id belongs to
            // the AI-recommendations accordion's OWN inner div (viewhtml.php),
            // not the Details/Mitigation/Review tab holder; it happens to
            // carry the bare 'tab-data' class too, which is what makes the
            // next mistake possible. It is ALSO only rendered when
            // $_ai_show_section is true (an existing ai_recommendations_risk
            // row for this risk id) -- for every risk with no AI
            // recommendation yet, which includes every freshly submitted
            // risk, that whole accordion branch is never emitted, so
            // $('#tab-content-container') resolves to an empty set and
            // .parents('.tab-data') off an empty set is empty too. The POST
            // still succeeds and the modal still closes, but
            // tabContainer.html(data.data) below silently no-ops on an empty
            // jQuery collection: nothing on the page updates, so the Status
            // pill is left showing its pre-close value. Confirmed live via
            // risk-view-record-header.spec.ts SCENARIO-2 against a throwaway
            // risk (no AI recommendation row).
            //
            // NOT $('.tab-data').not('.hide') either, even though it looks
            // like the obvious fix and DOES correctly update the status
            // pill -- it still matched TWO elements (that #tab-content-
            // container div AND the real outer wrapper it happens to sit
            // inside), in DOCUMENT order: [outer, inner]. jQuery's .html()
            // on a multi-element set reuses the parsed fragment's real,
            // script-bearing nodes for the LAST element and gives every
            // earlier element an inert clone -- so the OUTER container (the
            // one that actually survives) got the clone, and the embedded
            // `$(function(){ RiskViewDetails.render(); })` script (from
            // management/partials/details.php, only emitted when $isAjax is
            // true) only ran on the doomed INNER copy. Confirmed live: the
            // status pill updated correctly, but risk-view-details.js's
            // Cards never re-rendered -- General/Scoring silently vanished
            // after Close Risk, leaving only the AI Assistant/Associated
            // Exceptions/Comments/Audit Trail sections that render outside
            // the Cards mount.
            //
            // .overview-container (viewhtml.php) is the fix: it renders
            // UNCONDITIONALLY (view_top_table()'s Status pill/risk-id header
            // lives there, regardless of AI-recommendation state or
            // $display_risk), and it sits in a completely different branch
            // of the tree from the AI accordion's #tab-content-container, so
            // .parents('.tab-data') from it can never accidentally pick up
            // that conditional div the way #tab-content-container's own id
            // could. Every OTHER handler in this file reaches a SINGLE
            // element via `$this.parents('.tab-data')` from a click that
            // originates INSIDE the tree -- .parents() walks up from there,
            // so it never includes the starting element itself even when
            // that element also carries the class. This modal is
            // <body>-appended, outside that tree, so it starts from this
            // known, always-present descendant and asks for its ANCESTOR
            // with the class -- .parents(), not .closest() (closest() checks
            // the starting element FIRST, and would return that same element
            // straight back if it ever gained the class) -- yielding exactly
            // one match: the real outer wrapper, with its script-bearing
            // content intact.
            var tabContainer = $('.overview-container').parents('.tab-data');

            $saveBtn.prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: BASE_URL + '/api/v2/management/risk/closerisk?id=' + risk_id,
                data: $.param({ close_reason: $reasonSelect.val(), note: $noteTextarea.val() }),
                cache: false
            }).done(function (data) {
                bootstrap.Modal.getOrCreateInstance($closeRiskModal[0]).hide();
                tabContainer.html(data.data);
                callbackAfterRefreshTab(tabContainer);
                if (data.status_message) {
                    showAlertsFromArray(data.status_message);
                }
            }).fail(function (xhr) {
                if (!retryCSRF(xhr, this)) {
                    if (xhr.responseJSON && xhr.responseJSON.status_message) {
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    } else {
                        showAlertsFromArray([{ type: 'bad', text: _lang['RequestFailed'] }]);
                    }
                }
            }).always(function () {
                $saveBtn.prop('disabled', false);
            });
        });

        return $closeRiskModal;
    }

    $('body').on('click', '.close-risk', function (e) {
        e.preventDefault();
        var risk_id = $('.risk-id').first().html();
        var $modal = buildCloseRiskModal();
        $modal.data('riskId', risk_id);

        var $reasonSelect = $modal.data('reasonSelect');
        var $noteTextarea = $modal.data('noteTextarea');
        $noteTextarea.val('');

        if (!closeRiskOptionsLoaded) {
            $.ajax({
                type: 'GET',
                url: BASE_URL + '/api/v2/management/risk/closerisk?id=' + risk_id,
                success: function (data) {
                    closeRiskOptionsLoaded = true;
                    var $fetchedOptions = $('<div>').html(data.data).find('select[name="close_reason"] option');
                    $reasonSelect.empty().append($fetchedOptions);
                },
                error: function (xhr) {
                    if (xhr.responseJSON && xhr.responseJSON.status_message) {
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            });
        }

        bootstrap.Modal.getOrCreateInstance($modal[0]).show();
    })

    /*************** end close risk ******************/
    
    $('body').on('click', '.reopen-risk', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/management/risk/reopen?id=" + risk_id,
            success: function(data){
                if($('.show-score').is(":visible")){
                    $('.overview-container', tabContainer).html(data.data);
                    $('.show-score').show();
                    $('.hide-score').hide();
                }else{
                    $('.overview-container', tabContainer).html(data.data);
                    $('.show-score').hide();
                    $('.hide-score').show();
                }
            },
            error: function(xhr,status,error){
                if(!retryCSRF(xhr, this))
                {
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            }
        })
    })
    
    
    $('body').on('click', '.view_all_reviews', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
    })
    
    /********* Start change status **********/
    $('body').on('click', '.change-status', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        var $field = $(this).closest('.sr-risk-record-status');
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/v2/management/risk/changestatus?id=" + risk_id,
            success: function(data){
                // The fetched fragment (management/partials/changestatus.php)
                // is a full bootstrap-grid form -- its own label, <select>,
                // and "Update" submit button -- built for the legacy
                // below-content panel this used to open into. Reusing it
                // wholesale here would look nothing like Subject's compact
                // inline row, so only two pieces of it carry real state and
                // get kept: the populated <select> (options + the
                // permission-filtered "Closed" entry, already resolved
                // server-side) and the CSRF hidden input addCSRTToken()
                // injected into the form (includes/api.php). Everything
                // else -- the label, the grid divs, the old submit button --
                // is discarded and rebuilt to match Subject's own shape.
                // updateStatus() below still reads its FormData from
                // `$this.parents('form')`, so as long as both kept pieces
                // land inside a real <form>, submission behaves identically
                // to the discarded original markup.
                var $fetched = $('<div>').html(data.data);
                var $select = $fetched.find('select[name="status"]');
                var $csrfInput = $fetched.find('input[name="__csrf_magic"]');

                // 'd-flex align-items-center' matches Subject's own edit-row
                // wrapper (view_top_table(), includes/display.php) exactly,
                // so the fetched <select> (Bootstrap's .form-select, full
                // width by default) sizes against the icon buttons the same
                // way Subject's <input> does rather than stretching under them.
                var $form = $('<form>').addClass('d-flex align-items-center').append($select).append($csrfInput).append(
                    $('<div>').addClass('sr-inline-edit-actions')
                        .append(
                            $('<button>', { type: 'button', 'class': 'sr-row-action cancel-edit-status', title: _lang['Cancel'], 'aria-label': _lang['Cancel'] })
                                .append($('<i>', { 'class': 'fa fa-xmark', 'aria-hidden': 'true' }))
                        )
                        .append(
                            $('<button>', { type: 'button', 'class': 'sr-row-action', name: 'update_status', title: _lang['Save'], 'aria-label': _lang['Save'] })
                                .append($('<i>', { 'class': 'fa fa-check', 'aria-hidden': 'true' }))
                        )
                );

                $('.edit-status', $field).empty().append($form).removeClass('d-none');
                $('.static-status', $field).hide();
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    })

    $('body').on('click', '.cancel-edit-status', function (e) {
        e.preventDefault();
        var $field = $(this).closest('.sr-risk-record-status');
        $('.edit-status', $field).addClass('d-none').empty();
        $('.static-status', $field).show();
    });

    function updateStatus($this){
        var tabContainer = $this.parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        var action = $this.attr('name');
        
        var getForm = $this.parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/management/risk/updateStatus?id=" + risk_id,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data){
                tabContainer.html(data.data);
                callbackAfterRefreshTab(tabContainer);
                if(data.status_message){
                    showAlertsFromArray(data.status_message);
                }
            }
        })
        .fail(function(xhr, textStatus){
            if(!retryCSRF(xhr, this))
            {
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    }
    // 'button[name=update_status]' is the new inline icon Save (.change-
    // status' own handler above builds it); 'input[name=update_status]'
    // stays for anything else still rendering changestatus.php's raw
    // fragment as-is (its own unmodified "Update" submit input).
    $('body').on('click', 'button[name=update_status], input[name=update_status]', function(e){
        e.preventDefault();
        updateStatus($(this))
//        closeRisk($(this));
    })
    
    /*********** End change status ***********/
    
    
    /*********** start socre actions *************/
    function getScoreByAction(tabContainer, scoring_method){
        var risk_id = $('.risk-id', tabContainer).html();
        var visibleScoredetails = $('.hide-score', tabContainer).is(':visible');
        // Switching scoring method is a state-changing operation. Use the
        // dedicated POST endpoint so csrf-magic protects the request and the
        // server-side handler enforces modify_risks. The legacy GET-on-view
        // path remains gated for defence in depth but is no longer the
        // canonical entry point.
        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/management/risk/scoring-method",
            data: { id: risk_id, scoring_method: scoring_method },
            success: function(data){
                $('.score-overview-container', tabContainer).html(data.data);
                if(visibleScoredetails){
                    $('#score-container-accordion-body').addClass('show');
                    $('.scoredetails', tabContainer).show();
                    $('.show-score').hide();
                    $('.hide-score').show();
                }else{
                    $('#score-container-accordion-body').removeClass('show');
                    $('.scoredetails', tabContainer).hide();
                    $('.show-score').show();
                    $('.hide-score').hide();
                }
                /* Update risk scoring method in details tab */
                // If details tab is in Edit
                if($('.cancel-edit-details', tabContainer).length){
                    editDetailsRequest(risk_id, tabContainer);
                }
                // If details tab is in View
                else{
                    cancelEditDetailsRequest(risk_id, tabContainer);
                }
                
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    }
    
    /**** End js for view html *******/

    /****** start comment *******/
    $('body').on('click', '#tab-content-container .collapsible--toggle span', function(event) {
        event.preventDefault();
        var container = $(this).parents('.well');
        $(this).parents('.collapsible--toggle').next('.collapsible').slideToggle('400');
        $(this).find('i').toggleClass('fa-caret-right fa-caret-down');
        if($('.collapsible', container).is(':visible') && $('.add-comments', container).hasClass('rotate')){
            $('.add-comments', container).click()
        }
    });

    $('body').on('click', '#tab-content-container .add-comments', function(event) {
        event.preventDefault();
        var container = $(this).parents('.well');
        if(!$('.collapsible', container).is(':visible')){
            $(this).parents('.collapsible--toggle').next('.collapsible').slideDown('400');
            $(this).parent().find('span i').removeClass('fa-caret-right');
            $(this).parent().find('span i').addClass('fa-caret-down');
        }
        $(this).toggleClass('rotate');
        $('.comment-form', container).fadeToggle('100');
    });

    function saveComment($this){
        var tabContainer = $this.parents('.tab-data');
        if(!$(".comment-text", tabContainer).val()){
            $(".comment-text", tabContainer).focus();
            return;
        }
        
        var risk_id = $('.risk-id', tabContainer).html();
        
        var getForm = $this.parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);
        $('#comment').parents('.comment-wrapper').block({
            message: 'Processing',
            css: { border: '1px solid black', background: '#ffffff', color: '#000000' },
        });

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/management/risk/saveComment?id=" + risk_id,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data){
                $('.comments--list', tabContainer).html(data.data);
                $(".comment-text", tabContainer).val('')
                $(".comment-text", tabContainer).focus()
                if(data.status_message){
                    showAlertsFromArray(data.status_message);
                }
                $('#comment').parents('.comment-wrapper').unblock();
            }
        })
        .fail(function(xhr, textStatus){
            if(!retryCSRF(xhr, this))
            {
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }

        });
    }
    $('body').on('click', '.comment-submit', function(e){
        e.preventDefault();
        saveComment($(this))
    })
    
    /****** end comment *******/
    
    /**
    * When External Reference ID is changed, Control scoring.
    *
    * Moved to js/simplerisk/cve_lookup.js -- that file (not this one) is
    * loaded on BOTH the risk view page AND the standalone Submit Risk page
    * (management/index.php), which never loads risk.js at all. Binding it
    * here meant CVE lookup silently never fired on Submit Risk since that
    * page was rebuilt around the Cards engine (05d51d9c50). See
    * cve_lookup.js's own comment on this handler for the full story.
    */
    /******** End External Referenced ID event ***************/
    
    /**
    * Change Event of Risk Scoring Method
    * 
    */
    
    $('body').on('change', '[name=scoring_method]', function(e){
        e.preventDefault();
        var formContainer = $(this).parents('form');
        handleSelection($(this).val(), formContainer);
    })
    
    $('body').click(function(){
        $("#alert").fadeOut( "slow" );
    })
    
    /**
    * Show/Hide management review submit form
    * 
    */
    $('body').on('click', 'input[name=custom_date]', function(e){
        var form = $(this).parents('.tab-data');
        if($(this).val() == "no"){
            $(".nextreview", form).hide();
        }else{
            $(".nextreview", form).show();
        }
    })

    /**
    * click radio button in editing review of multi tabs
    *     
    */
    $('body').on('click', '.radio-buttons-holder input[type=radio]~label', function(){
        $(this).parent().find('input[type=radio]').click()
    })
    
    /**
    * events in clicking Score Using DREAD button of edit details page, muti tabs case
    */
    $('body').on('click', '[name=dreadSubmit]', function(e){
        e.preventDefault();
        var form = $(this).parents('form');
        popupdread(form);
    })
   
    /**
    * events in clicking Score Using OWASP button of edit details page, muti tabs case
    */
    $('body').on('click', '[name=owaspSubmit]', function(e){
        e.preventDefault();
        var form = $(this).parents('form');
        popupowasp(form);
    })
    
    /**
    * events in clicking Score Using Contributing Risk button of edit details page, muti tabs case
    */
    $('body').on('click', '[name=contributingRiskSubmit]', function(e){
        e.preventDefault();
        var form = $(this).parents('form');
        popupcontributingrisk(form);
    })
    
    /**
    * Show/Hide Project Name if Next Step is Consider for Project
    */
    $('body').on('change', '[name=next_step]', function(){
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        
        var getForm = $(this).parents('form', tabContainer);

        // If Next Step is Consider(value=2) for Project
        if($(this).val() == 2)
        {
            $(".project-holder", tabContainer).show();
        }
        else
        {
            $(".project-holder", tabContainer).hide();
        }
    })
    
    $('body').on('change', '[name=owner]', function(e){

        // Get the form of this tab to make sure this logic won't change values on the other tabs
        var form = $(this).closest('form');

        // If there's no Owner's Manager field displayed then there's nothing to do
        if (!$('[name=manager]', form).length) {
        	return;
        }

        // Get the id of the owner
        var ownerId = $(this).val();
        // If there's anything selected
        if (ownerId) {
            // reach out to the server and get the id of the owner's manager
            $.ajax({
                type: 'GET',
                url: BASE_URL + '/api/v2/user/manager',
                data: {
                    id: ownerId
                },
                success: function(res){
                    var data = res.data;
                    if(data.manager){
                        // If the owner has a manager then select it
                        $('[name=manager]', form)[0].selectize.setValue(data.manager);
                    } else {
                        // if the owner doesn't have a manager then clear the value(if there's any)
                    	$('[name=manager]', form)[0].selectize.clear();
                    }
                }
            });
        } else {
            // If there's no owner selected then clear the owner's manager field
            $('[name=manager]', form)[0].selectize.clear();
        }
    });

    /********* Start mark as unmitigation **********/
    $('body').on('click', '.mark-unmitigation', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/v2/management/risk/mark-unmitigation?id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    })
    $('body').on('click', '.save-unmitigation-risk', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        var action = $(this).attr('name');
        
        var getForm = $(this).parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/management/risk/saveMarkUnmitigation?id=" + risk_id,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data){
                tabContainer.html(data.data);
                callbackAfterRefreshTab(tabContainer);
                if(data.status_message){
                    showAlertsFromArray(data.status_message);
                }
            }
        })
        .fail(function(xhr, textStatus){
            if(!retryCSRF(xhr, this))
            {
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    });    
    /*********** End mark as unmitigation ***********/

    /********* Start mark as unreview **********/
    $('body').on('click', '.mark-unreview', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/v2/management/risk/mark-unreview?id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    });
    $('body').on('click', '.save-unreview-risk', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        var action = $(this).attr('name');
        
        var getForm = $(this).parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/management/risk/saveMarkUnreview?id=" + risk_id,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data){
                tabContainer.html(data.data);
                callbackAfterRefreshTab(tabContainer);
                if(data.status_message){
                    showAlertsFromArray(data.status_message);
                }
            }
        })
        .fail(function(xhr, textStatus){
            if(!retryCSRF(xhr, this))
            {
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    });    
    /*********** End mark as unreview ***********/

    /**************** Start get AI risk recommendations **********/

    function renderAIFairData(data) {
        var currency = typeof CURRENCY !== 'undefined' ? CURRENCY : '';

        function fmt(val) { return sanitizeHTML(String(val !== null && val !== undefined ? val : '')); }
        function fmtCurrency(val) {
            if (val === null || val === undefined) return '';
            var n = parseFloat(val);
            return isNaN(n) ? sanitizeHTML(String(val)) : currency + n.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
        }
        function fmtPct(val) {
            if (val === null || val === undefined) return '';
            var n = parseFloat(val);
            if (isNaN(n)) return sanitizeHTML(String(val));
            return n < 1.01 ? (n * 100).toFixed(1) + '%' : n.toFixed(1) + '%';
        }
        document.querySelector('.ai-recommendations-risk-details').innerHTML = sanitizeHTML(data.details || '');
        document.querySelector('.ai-recommendations-risk-mitigation').innerHTML = sanitizeHTML(data.mitigation || '');
        document.querySelector('.ai-recommendations-fair-risk-scenario').innerHTML = sanitizeHTML(data.risk_scenario || '');
        document.querySelector('.ai-recommendations-fair-assumptions').innerHTML = sanitizeHTML(data.assumptions || '');

        var cf = data.contact_frequency || {};
        document.querySelector('.ai-recommendations-fair-contact-frequency-min').innerHTML = "<strong>Minimum:&nbsp;</strong>"+fmt(cf.min);
        document.querySelector('.ai-recommendations-fair-contact-frequency-most-likely').innerHTML = "<strong>Most Likely:&nbsp;</strong>"+fmt(cf.most_likely);
        document.querySelector('.ai-recommendations-fair-contact-frequency-max').innerHTML = "<strong>Maximum:&nbsp;</strong>"+fmt(cf.max);
        document.querySelector('.ai-recommendations-fair-contact-frequency-confidence').innerHTML = "<strong>Confidence:&nbsp;</strong>"+fmt(cf.confidence);
        document.querySelector('.ai-recommendations-fair-contact-frequency-rationale').innerHTML = "<strong>Rationale:&nbsp;</strong>"+fmt(cf.rationale);

        var poa = data.probability_of_action || {};
        document.querySelector('.ai-recommendations-fair-probability-of-action-min').innerHTML = "<strong>Minimum:&nbsp;</strong>"+fmtPct(poa.min);
        document.querySelector('.ai-recommendations-fair-probability-of-action-most-likely').innerHTML = "<strong>Most Likely:&nbsp;</strong>"+fmtPct(poa.most_likely);
        document.querySelector('.ai-recommendations-fair-probability-of-action-max').innerHTML = "<strong>Maximum:&nbsp;</strong>"+fmtPct(poa.max);
        document.querySelector('.ai-recommendations-fair-probability-of-action-confidence').innerHTML = "<strong>Confidence:&nbsp;</strong>"+fmt(poa.confidence);
        document.querySelector('.ai-recommendations-fair-probability-of-action-rationale').innerHTML = "<strong>Rationale:&nbsp;</strong>"+fmt(poa.rationale);

        var tc = data.threat_capability || {};
        document.querySelector('.ai-recommendations-fair-threat-capability-min').innerHTML = "<strong>Minimum:&nbsp;</strong>"+fmtPct(tc.min);
        document.querySelector('.ai-recommendations-fair-threat-capability-most-likely').innerHTML = "<strong>Most Likely:&nbsp;</strong>"+fmtPct(tc.most_likely);
        document.querySelector('.ai-recommendations-fair-threat-capability-max').innerHTML = "<strong>Maximum:&nbsp;</strong>"+fmtPct(tc.max);
        document.querySelector('.ai-recommendations-fair-threat-capability-confidence').innerHTML = "<strong>Confidence:&nbsp;</strong>"+fmt(tc.confidence);
        document.querySelector('.ai-recommendations-fair-threat-capability-rationale').innerHTML = "<strong>Rationale:&nbsp;</strong>"+fmt(tc.rationale);

        var rs = data.resistance_strength || {};
        document.querySelector('.ai-recommendations-fair-resistance-strength-min').innerHTML = "<strong>Minimum:&nbsp;</strong>"+fmtPct(rs.min);
        document.querySelector('.ai-recommendations-fair-resistance-strength-most-likely').innerHTML = "<strong>Most Likely:&nbsp;</strong>"+fmtPct(rs.most_likely);
        document.querySelector('.ai-recommendations-fair-resistance-strength-max').innerHTML = "<strong>Maximum:&nbsp;</strong>"+fmtPct(rs.max);
        document.querySelector('.ai-recommendations-fair-resistance-strength-confidence').innerHTML = "<strong>Confidence:&nbsp;</strong>"+fmt(rs.confidence);
        document.querySelector('.ai-recommendations-fair-resistance-strength-rationale').innerHTML = "<strong>Rationale:&nbsp;</strong>"+fmt(rs.rationale);

        var tef = data.threat_event_frequency || {};
        document.querySelector('.ai-recommendations-fair-threat-event-frequency-min').innerHTML = "<strong>Minimum:&nbsp;</strong>"+fmt(tef.min);
        document.querySelector('.ai-recommendations-fair-threat-event-frequency-most-likely').innerHTML = "<strong>Most Likely:&nbsp;</strong>"+fmt(tef.most_likely);
        document.querySelector('.ai-recommendations-fair-threat-event-frequency-max').innerHTML = "<strong>Maximum:&nbsp;</strong>"+fmt(tef.max);
        if (document.querySelector('.ai-recommendations-fair-threat-event-frequency-confidence'))
            document.querySelector('.ai-recommendations-fair-threat-event-frequency-confidence').innerHTML = "<strong>Note:&nbsp;</strong>"+fmt(tef.note || 'PHP-computed from Contact Frequency \u00d7 Probability of Action');

        var vuln = data.vulnerability || {};
        document.querySelector('.ai-recommendations-fair-vulnerability-min').innerHTML = "<strong>Minimum:&nbsp;</strong>"+fmtPct(vuln.min);
        document.querySelector('.ai-recommendations-fair-vulnerability-most-likely').innerHTML = "<strong>Most Likely:&nbsp;</strong>"+fmtPct(vuln.most_likely);
        document.querySelector('.ai-recommendations-fair-vulnerability-max').innerHTML = "<strong>Maximum:&nbsp;</strong>"+fmtPct(vuln.max);
        document.querySelector('.ai-recommendations-fair-vulnerability-confidence').innerHTML = "<strong>Confidence:&nbsp;</strong>"+fmt(vuln.confidence);
        document.querySelector('.ai-recommendations-fair-vulnerability-rationale').innerHTML = "<strong>Rationale:&nbsp;</strong>"+fmt(vuln.rationale);

        var lef = data.loss_event_frequency || {};
        document.querySelector('.ai-recommendations-fair-loss-event-frequency-min').innerHTML = "<strong>Minimum:&nbsp;</strong>"+fmt(lef.min);
        document.querySelector('.ai-recommendations-fair-loss-event-frequency-most-likely').innerHTML = "<strong>Most Likely:&nbsp;</strong>"+fmt(lef.most_likely);
        document.querySelector('.ai-recommendations-fair-loss-event-frequency-max').innerHTML = "<strong>Maximum:&nbsp;</strong>"+fmt(lef.max);
        if (document.querySelector('.ai-recommendations-fair-loss-event-frequency-confidence'))
            document.querySelector('.ai-recommendations-fair-loss-event-frequency-confidence').innerHTML = "<strong>Note:&nbsp;</strong>"+fmt(lef.note || 'PHP-computed from TEF \u00d7 Vulnerability');

        var pl = data.primary_loss || {};
        document.querySelector('.ai-recommendations-fair-primary-loss-min').innerHTML = "<strong>Minimum:&nbsp;</strong>"+fmtCurrency(pl.min);
        document.querySelector('.ai-recommendations-fair-primary-loss-most-likely').innerHTML = "<strong>Most Likely:&nbsp;</strong>"+fmtCurrency(pl.most_likely);
        document.querySelector('.ai-recommendations-fair-primary-loss-max').innerHTML = "<strong>Maximum:&nbsp;</strong>"+fmtCurrency(pl.max);
        document.querySelector('.ai-recommendations-fair-primary-loss-confidence').innerHTML = "<strong>Confidence:&nbsp;</strong>"+fmt(pl.confidence);
        document.querySelector('.ai-recommendations-fair-primary-loss-rationale').innerHTML = "<strong>Rationale:&nbsp;</strong>"+fmt(pl.rationale);

        var slef = data.secondary_loss_event_frequency || {};
        document.querySelector('.ai-recommendations-fair-secondary-loss-event-frequency-min').innerHTML = "<strong>Minimum:&nbsp;</strong>"+fmtPct(slef.min);
        document.querySelector('.ai-recommendations-fair-secondary-loss-event-frequency-most-likely').innerHTML = "<strong>Most Likely:&nbsp;</strong>"+fmtPct(slef.most_likely);
        document.querySelector('.ai-recommendations-fair-secondary-loss-event-frequency-max').innerHTML = "<strong>Maximum:&nbsp;</strong>"+fmtPct(slef.max);
        document.querySelector('.ai-recommendations-fair-secondary-loss-event-frequency-confidence').innerHTML = "<strong>Confidence:&nbsp;</strong>"+fmt(slef.confidence);
        document.querySelector('.ai-recommendations-fair-secondary-loss-event-frequency-rationale').innerHTML = "<strong>Rationale:&nbsp;</strong>"+fmt(slef.rationale);

        var slm = data.secondary_loss_magnitude || {};
        ['productivity','response','replacement','competitive_advantage','fines_and_judgements','reputation'].forEach(function(cat) {
            var csscat = cat.replace(/_/g, '-');
            var c = slm[cat] || {};
            var sel = '.ai-recommendations-fair-secondary-loss-magnitude-' + csscat + '-';
            document.querySelector(sel + 'min').innerHTML = "<strong>Minimum:&nbsp;</strong>"+fmtCurrency(c.min);
            document.querySelector(sel + 'most-likely').innerHTML = "<strong>Most Likely:&nbsp;</strong>"+fmtCurrency(c.most_likely);
            document.querySelector(sel + 'max').innerHTML = "<strong>Maximum:&nbsp;</strong>"+fmtCurrency(c.max);
            document.querySelector(sel + 'confidence').innerHTML = "<strong>Confidence:&nbsp;</strong>"+fmt(c.confidence);
            document.querySelector(sel + 'rationale').innerHTML = "<strong>Rationale:&nbsp;</strong>"+fmt(c.rationale);
        });

        var sr = data.secondary_risk || {};
        document.querySelector('.ai-recommendations-fair-secondary-risk-min').innerHTML = "<strong>Minimum:&nbsp;</strong>"+fmtCurrency(sr.min);
        document.querySelector('.ai-recommendations-fair-secondary-risk-most-likely').innerHTML = "<strong>Most Likely:&nbsp;</strong>"+fmtCurrency(sr.most_likely);
        document.querySelector('.ai-recommendations-fair-secondary-risk-max').innerHTML = "<strong>Maximum:&nbsp;</strong>"+fmtCurrency(sr.max);

        var lm = data.loss_magnitude || {};
        document.querySelector('.ai-recommendations-fair-loss-magnitude-min').innerHTML = "<strong>Minimum:&nbsp;</strong>"+fmtCurrency(lm.min);
        document.querySelector('.ai-recommendations-fair-loss-magnitude-most-likely').innerHTML = "<strong>Most Likely:&nbsp;</strong>"+fmtCurrency(lm.most_likely);
        document.querySelector('.ai-recommendations-fair-loss-magnitude-max').innerHTML = "<strong>Maximum:&nbsp;</strong>"+fmtCurrency(lm.max);

        // Annual Loss Exposure — simulation percentile distribution
        var ale = data.annual_loss_exposure || {};
        var aleTable = document.getElementById('ai-fair-ale-table');
        var aleProcessing = document.getElementById('ai-fair-ale-processing');
        var aleIterations = document.querySelector('.ai-fair-ale-iterations');
        if (ale.median !== undefined) {
            // New simulation format: percentiles
            document.querySelector('.ai-fair-ale-p10').textContent = fmtCurrency(ale.p10);
            document.querySelector('.ai-fair-ale-p25').textContent = fmtCurrency(ale.p25);
            document.querySelector('.ai-fair-ale-median').textContent = fmtCurrency(ale.median);
            document.querySelector('.ai-fair-ale-mean').textContent = fmtCurrency(ale.mean);
            document.querySelector('.ai-fair-ale-p75').textContent = fmtCurrency(ale.p75);
            document.querySelector('.ai-fair-ale-p90').textContent = fmtCurrency(ale.p90);
            if (ale.iterations) aleIterations.textContent = ale.iterations.toLocaleString() + '-iteration Monte Carlo simulation';
            aleTable.classList.remove('d-none');
            if (ale.iterations) aleIterations.classList.remove('d-none');
            aleProcessing.classList.add('d-none');
        } else if (ale.min !== undefined) {
            // Legacy format fallback: show as simple list
            document.querySelector('.ai-fair-ale-p10').textContent = fmtCurrency(ale.min);
            document.querySelector('.ai-fair-ale-median').textContent = fmtCurrency(ale.average);
            document.querySelector('.ai-fair-ale-p90').textContent = fmtCurrency(ale.max);
            aleTable.classList.remove('d-none');
            aleProcessing.classList.add('d-none');
        } else {
            aleProcessing.classList.remove('d-none');
            aleTable.classList.add('d-none');
        }

        document.querySelector('.ai-recommendations-risk-last-updated').textContent = data.last_updated || '';
    }

    // Auto-load AI analysis when the page contains the inline accordion
    var aiAccordionBody = document.getElementById('ai-analysis-accordion-body');
    if (aiAccordionBody) {
        var aiRiskId = aiAccordionBody.getAttribute('data-risk-id');

        // .sr-state-pill (design-system.md §7), not raw Bootstrap .badge.bg-*
        // -- matches viewhtml.php's own server-rendered initial badge
        // (same family mapping, same fa-spinner fa-spin affordance for the
        // one actually-active state) so a poll-driven update doesn't revert
        // the badge to the old, off-palette treatment.
        function setAIStatusBadge(status) {
            var badge = document.getElementById('ai-analysis-status-badge');
            if (!badge) return;
            badge.classList.remove('d-none', 'sr-state-neutral', 'sr-state-warning', 'sr-state-info', 'sr-state-success', 'sr-state-danger', 'sr-state-pill-spinning');
            var map = {
                pending:     ['sr-state-warning', 'Pending'],
                processing:  ['sr-state-info',    'Processing'],
                in_progress: ['sr-state-info',    'Processing'],
                complete:    ['sr-state-success', 'Complete'],
                failed:      ['sr-state-danger',  'Failed'],
            };
            var entry = map[status] || ['sr-state-neutral', status];
            badge.classList.add(entry[0]);
            badge.textContent = '';
            var isProcessing = (status === 'processing' || status === 'in_progress');
            if (isProcessing) {
                badge.classList.add('sr-state-pill-spinning');
                var icon = document.createElement('i');
                icon.className = 'fa fa-spinner fa-spin';
                icon.setAttribute('aria-hidden', 'true');
                badge.appendChild(icon);
                badge.appendChild(document.createTextNode(entry[1]));
            } else {
                badge.textContent = entry[1];
            }
            badge.classList.remove('d-none');
        }

        function startAIPolling(risk_id) {
            var pollTimer = setInterval(function() {
                $.getJSON(BASE_URL + '/api/v2/ai/recommendations/risk?risk_id=' + risk_id, function(pollRes) {
                    if (!pollRes.data || (pollRes.data.status !== 'pending' && pollRes.data.status !== 'processing' && pollRes.data.status !== 'in_progress')) {
                        clearInterval(pollTimer);
                        var banner = document.getElementById('ai-analysis-status-banner');
                        if (pollRes.data && pollRes.data.status === 'complete') {
                            setAIStatusBadge('complete');
                            if (banner) banner.classList.add('d-none');
                            renderAIFairData(pollRes.data);
                        } else {
                            setAIStatusBadge('failed');
                            if (banner) {
                                banner.className = 'alert alert-danger mb-3';
                                banner.textContent = 'AI analysis failed. Click Refresh to try again.';
                                banner.classList.remove('d-none');
                            }
                        }
                    }
                });
            }, 6000);
        }

        function loadAIAnalysis(risk_id, refresh) {
            var url = BASE_URL + '/api/v2/ai/recommendations/risk?risk_id=' + risk_id;
            if (refresh) url += '&refresh=true';

            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    var data = res.data;
                    var banner = document.getElementById('ai-analysis-status-banner');
                    if (data.status === 'pending' || data.status === 'processing' || data.status === 'in_progress') {
                        setAIStatusBadge(data.status);
                        if (banner) {
                            banner.className = 'alert alert-info mb-3';
                            banner.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>AI analysis is being prepared in the background. This page will update automatically.';
                            banner.classList.remove('d-none');
                        }
                        startAIPolling(risk_id);
                        return;
                    }
                    if (data.status === 'complete') {
                        setAIStatusBadge('complete');
                        if (banner) banner.classList.add('d-none');
                        renderAIFairData(data);
                    } else if (data.status === 'failed') {
                        setAIStatusBadge('failed');
                        if (banner) {
                            banner.className = 'alert alert-danger mb-3';
                            banner.textContent = 'AI analysis failed. Click Refresh to try again.';
                            banner.classList.remove('d-none');
                        }
                    }
                }
            });
        }

        // Pre-populate badge from PHP-rendered status (visible before accordion opens)
        var initialStatus = aiAccordionBody.getAttribute('data-ai-status');
        if (initialStatus) {
            setAIStatusBadge(initialStatus);
        }

        // If the analysis is complete, populate the DOM now so data is ready when the
        // accordion opens.  For pending/in_progress, wait until the user opens the
        // accordion before starting the polling cycle (avoids background AJAX on load).
        if (initialStatus === 'complete') {
            loadAIAnalysis(aiRiskId, false);
        } else {
            $('#ai-analysis-accordion-body').one('show.bs.collapse', function() {
                loadAIAnalysis(aiRiskId, false);
            });
        }

        // Refresh button
        $('body').on('click', '.refresh-recommendations-risk', function(e) {
            e.preventDefault();
            var risk_id = $(this).attr('data-id');
            setAIStatusBadge('pending');
            var header = document.getElementById('ai-analysis-accordion-header');
            if (header) { header.scrollIntoView({behavior: 'smooth', block: 'start'}); }
            $.ajax({
                url: BASE_URL + '/api/v2/ai/recommendations/risk?risk_id=' + risk_id + '&refresh=true',
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    var data = res.data;
                    var banner = document.getElementById('ai-analysis-status-banner');
                    if (data.status === 'pending' || data.status === 'processing' || data.status === 'in_progress') {
                        setAIStatusBadge(data.status);
                        if (banner) {
                            banner.className = 'alert alert-info mb-3';
                            banner.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>AI analysis is being prepared in the background. This page will update automatically.';
                            banner.classList.remove('d-none');
                        }
                        startAIPolling(risk_id);
                        return;
                    }
                    if (data.status === 'complete') {
                        setAIStatusBadge('complete');
                        if (banner) banner.classList.add('d-none');
                        renderAIFairData(data);
                    } else if (data.status === 'failed') {
                        setAIStatusBadge('failed');
                        if (banner) {
                            banner.className = 'alert alert-danger mb-3';
                            banner.textContent = 'AI analysis failed. Click Refresh to try again.';
                            banner.classList.remove('d-none');
                        }
                    }
                }
            });
        });
    }

    /**************** End get AI risk recommendations **********/

    // If there're template tabs we have to separately initialize the WYSIWYG editors
    if ($("#template_group_id").length > 0) {
        // We have to make sure the IDs are unique so we're appending the template's ID to the textarea's ID to make it unique

        $("[name='assessment']").each(function() {
            let template_group_id = $(this).closest('form').find('#template_group_id').val();
            $(this).attr('id', 'assessment_' + template_group_id);
            init_minimun_editor("#assessment_" + template_group_id);
        });

        $("[name='notes']").each(function() {
            let template_group_id = $(this).closest('form').find('#template_group_id').val();
            $(this).attr('id', 'notes_' + template_group_id);
            init_minimun_editor("#notes_" + template_group_id);
        });

    } else {
        // init WYSIWYG editor
        init_minimun_editor("#tab-content-container [name=assessment]");
        init_minimun_editor("#tab-content-container [name=notes]");
    }
})
   