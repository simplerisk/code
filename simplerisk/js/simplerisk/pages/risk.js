$.fn.extend({
    initAsAssociatedExceptionTreegrid: function(type=false) {

        // Can't initialize it twice
        if (this.data('initialized')) {
            this.treegrid("resize");
            return;
        }

        let tabs = this.parents('.tab-pane');
        let activeTabs = this.parents('.tab-pane.active');

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
            loadFilter: function(data, parentId) {
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

var current_tab_close_object;

// Variable to be used to prevent the form from being submitted multiple times
var loading = false;

function close_current_tab(index)
{
    $('#tab-container'+index+'').remove();
    current_tab_close_object.parent().remove();
    $('.tab-show').first().addClass('selected');
    $('.tab-data').first().show();
}

function addRisk($this){
    var tabContainer = $this.closest('.tab-data');

    // Check the sum of files the user wants to upload and
    // stop if it's over the max_upload_size
    if (!validFileUploadSize(tabContainer)) {
    	return false;
    }

    var getForm = $this.closest("form");
    var index = tabContainer.index();
    var form = new FormData($(getForm)[0]);
    $.each($("input[type=file]", tabContainer), function(i, obj) {
        $.each(obj.files, function(j, file){
            form.append('file['+j+']', file);
        })
    });
    
    // Check valiation and stop if failed
    if(!checkAndSetValidation(tabContainer)) {
        return false;
    }
    $.blockUI({message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>', baseZ:'10001'});
    $.ajax({
        type: "POST",
        url: BASE_URL + "/management/index.php",
        data: form,
        async: true,
        cache: false,
        contentType: false,
        processData: false,
        success: function(data){
            if(data.status_message){
                showAlertsFromArray(data.status_message);
            }

            var risk_id = data.data.risk_id;
            var associate_test = data.data.associate_test;
            if(associate_test == 1) {
                $("#modal-new-risk").modal("hide");
                $("#associate_new_risk_id").val(risk_id);
                $('form#edit-test').submit();
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
    
    
    function setupAssetsAssetGroupsWidget(select_tag, risk_id) {

        // Giving a default value here because IE can't handle
        // function parameter default values...
        risk_id = risk_id || 0;
        
        if (!select_tag.length)
            return;

        var select = select_tag.selectize({
            sortField: 'text',
            plugins: ['optgroup_columns', 'remove_button', 'restore_on_backspace'],
            delimiter: ',',
            create: function (input){
                return { id:'new_asset_' + input, name:input };
            },
            persist: false,
            valueField: 'id',
            labelField: 'name',
            searchField: 'name',
            sortField: 'name',
            optgroups: [
                {class: 'asset', name: 'Standard Assets'},
                {class: 'group', name: 'Asset Groups'}
            ],
            optgroupField: 'class',
            optgroupLabelField: 'name',
            optgroupValueField: 'class',
            preload: true,
            render: {
                item: function(item, escape) {
                    return '<div class="' + item.class + '">' + escape(item.name) + '</div>';
                }
            },
            onInitialize: function() {
                select_tag.parent().find('.selectize-control div').block({message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>'});
            },
            load: function(query, callback) {
                if (query.length) return callback();
                $.ajax({
                    url: BASE_URL + '/api/asset-group/options',
                    type: 'GET',
                    dataType: 'json',
                    data: {id: risk_id, type: 'risk'},
                    error: function() {
                        callback();
                    },
                    success: function(res) {
                        var data = res.data;
                        var control = select[0].selectize;
                        var selected_ids = [];
                        // Have to do it this way, because addition with simple addOption() will
                        // bug out when we deselect an option(it wouldn't be added back to the
                        // list of selectable items)
                        len = data.length;
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
                    complete: function() {
                        select_tag.parent().find('.selectize-control div').unblock({message:null});
                    }
                });
            }
        });        
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
            url: BASE_URL + "/api/management/risk/saveSubject?id=" + risk_id,
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
        $commentsContainer = $(".comment-form", tabContainer).parents('.accordion-collapse');
        $commentsContainer.slideDown('400');
        $(".comment-text", tabContainer).focus();
    });
    
    $('body').on('click', '.show-score', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        $('.scoredetails', tabContainer).show();
        $('.hide-score', tabContainer).show();
        $('.show-score', tabContainer).hide();
        return false;
    })

    $('body').on('click', '.hide-score', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        $('.scoredetails', tabContainer).hide();
        $('.updatescore', tabContainer).hide();
        $('.hide-score', tabContainer).hide();
        $('.show-score', tabContainer).show();
        return false;
    })

    $('body').on('click', '.update-score', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        $(".scoredetails", tabContainer).hide();
        $(".updatescore", tabContainer).show();

    });

    $('body').on('click', '.cancel-update', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        $('#score-container-accordion-body').addClass('show');
        $(".scoredetails", tabContainer).show();
        $(".updatescore", tabContainer).hide();

    })

    /**** start details ****/
    $('body').on('click', '[name=edit_details], .edit-risk', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        var $this = $(this);
        
        editDetailsRequest(risk_id, tabContainer);
    })
    
    function editDetailsRequest(risk_id, tabContainer){
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/management/risk/editdetails?action=editdetail&id=" + risk_id,
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
            url: BASE_URL + "/api/management/risk/editdetails?id=" + risk_id,
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
            url: BASE_URL + "/api/management/risk/saveDetails?id=" + risk_id,
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
    $('body').on('click', '[name=edit_mitigation], .edit-mitigation', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/management/risk/editdetails?action=editmitigation&id=" + risk_id,
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
            url: BASE_URL + "/api/management/risk/editdetails?id=" + risk_id,
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
            url: BASE_URL + "/api/management/risk/saveMitigation?id=" + risk_id,
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
            url: BASE_URL + "/api/management/risk/editdetails?action=editreview&id=" + risk_id,
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
    
    $('body').on('click', '.cancel-edit-review ', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/management/risk/editdetails?id=" + risk_id,
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
            url: BASE_URL + "/api/management/risk/saveReview?id=" + risk_id,
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
            url: BASE_URL + "/api/management/risk/view_all_reviews?id=" + risk_id,
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
    function closeRisk($this){
        var tabContainer = $this.parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();

        tabContainer.block({
            message: 'Processing',
            css: { border: '1px solid black', background: '#ffffff'},
            baseZ:'10001'
        });

        
        var getForm = $this.parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);
        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/management/risk/closerisk?id=" + risk_id,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data){
                tabContainer.unblock();
                tabContainer.html(data.data);
                callbackAfterRefreshTab(tabContainer);
                if(data.status_message){
                    showAlertsFromArray(data.status_message);
                }
            }
        })
        .fail(function(xhr, textStatus){
            tabContainer.unblock();
            if(!retryCSRF(xhr, this))
            {
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    }
    $('body').on('click', '.save-close-risk', function(e){
        e.preventDefault();
        closeRisk($(this));
    })
    
    $('body').on('click', '.close-risk', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/management/risk/closerisk?id=" + risk_id,
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

    /*************** end close risk ******************/
    
    $('body').on('click', '.reopen-risk', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/management/risk/reopen?id=" + risk_id,
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
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/management/risk/changestatus?id=" + risk_id,
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
    function updateStatus($this){
        var tabContainer = $this.parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        var action = $this.attr('name');
        
        var getForm = $this.parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/management/risk/updateStatus?id=" + risk_id,
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
    $('body').on('click', 'input[name=update_status]', function(e){
        e.preventDefault();
        updateStatus($(this))
//        closeRisk($(this));
    })
    
    /*********** End change status ***********/
    
    
    /*********** start socre actions *************/
    function getScoreByAction(tabContainer, scoring_method){
        var risk_id = $('.risk-id', tabContainer).html();
        var visibleScoredetails = $('.hide-score', tabContainer).is(':visible');
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/management/risk/scoreaction?id=" + risk_id + "&scoring_method=" + scoring_method,
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
    
    $('body').on('click', '.score-action', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var scoring_method = $(this).data('method');
        getScoreByAction(tabContainer, scoring_method);
    })
    
    function updateScore($this){

        // Prevent the form from being submitted multiple times
        if (loading) {
            return;
        }

        // Set loading to true to prevent multiple submissions
        loading = true;

        var tabContainer = $this.parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        var action = $this.attr('name');
        
        var getForm = $this.parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);
        var visibleScoredetails = $('.hide-score', tabContainer).is(':visible');

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/management/risk/saveScore?id=" + risk_id + "&action=" + action,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data){
                $('.score-overview-container', tabContainer).html(data.data);
//                $('.scoredetails', tabContainer).css('display', 'block');
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
                
                if(data.status_message){
                    showAlertsFromArray(data.status_message);
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

                // Reset loading to false after the request is complete
                loading = false;

            }
        })
        .fail(function(xhr, textStatus){
            if(!retryCSRF(xhr, this))
            {
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }

            // Reset loading to false if the request fails
            loading = false;

        });
    }
    $('body').on('click', '.updatescore button[type=submit]', function(e){
        e.preventDefault();
        updateScore($(this))
//        closeRisk($(this));
    })
    
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
            url: BASE_URL + "/api/management/risk/saveComment?id=" + risk_id,
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
    */
    
    $('body').on('keyup', 'input[name=reference_id]', function(e){
        e.preventDefault();
        var formContainer = $(this).parents('form');
        check_cve_id('reference_id', formContainer);
    })
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
    
    /**
    * Event when click plus button on review formn
    */
    $('body').on('click', '.project-holder .set-project', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.risk-id', tabContainer).html();
        var project_id = $("#project_name", tabContainer).val();
        if(project_id !== ""){
            $.ajax({
                type: "POST",
                url: BASE_URL + "/api/management/risk/setProjectToRisk?id=" + risk_id,
                data: {
                    project_id: project_id
                },
                success: function(data){
                    
                    if(data.status_message){                    
                        showAlertsFromArray(data.status_message);
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
                url: BASE_URL + '/api/user/manager',
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
            url: BASE_URL + "/api/management/risk/mark-unmitigation?id=" + risk_id,
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
            url: BASE_URL + "/api/management/risk/saveMarkUnmitigation?id=" + risk_id,
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
            url: BASE_URL + "/api/management/risk/mark-unreview?id=" + risk_id,
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
            url: BASE_URL + "/api/management/risk/saveMarkUnreview?id=" + risk_id,
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

        function setAIStatusBadge(status) {
            var badge = document.getElementById('ai-analysis-status-badge');
            if (!badge) return;
            badge.classList.remove('d-none', 'bg-secondary', 'bg-info', 'bg-success', 'bg-danger', 'bg-warning');
            var map = {
                pending:     ['bg-secondary', 'Pending'],
                processing:  ['bg-info',      'Processing'],
                in_progress: ['bg-info',      'Processing'],
                complete:    ['bg-success',   'Complete'],
                failed:      ['bg-danger',    'Failed'],
            };
            var entry = map[status] || ['bg-warning', status];
            badge.classList.add(entry[0]);
            badge.textContent = entry[1];
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
   