var current_tab_close_object;
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
    loading.show('load');
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

            $.ajax({
                type: "GET",
                url: BASE_URL + "/api/management/risk/viewhtml?id=" + risk_id,
                success: function(data){
                    tabContainer.html(data.data);

                    callbackAfterRefreshTab(tabContainer)
                },
                error: function(xhr,status,error){
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            });
            $this.prop('disabled', true);
        },
        complete: function(){
            loading.hide('load');
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
        $(".risk-details", tabContainer ).tabs({
            activate:function(event,ui){
              if(ui.newPanel.selector== "#tabs1"){
                $(".tab_details", tabContainer).addClass("tabList");
                $(".tab_mitigation", tabContainer).removeClass("tabList");
                $(".tab_review", tabContainer).removeClass("tabList");
              } else if(ui.newPanel.selector== "#tabs2"){
                $(".tab_mitigation", tabContainer).addClass("tabList");
                $(".tab_review", tabContainer).removeClass("tabList");
                $(".tab_details", tabContainer).removeClass("tabList");
              }else{
                $(".tab_review", tabContainer).addClass("tabList");
                $(".tab_mitigation", tabContainer).removeClass("tabList");
                $(".tab_details", tabContainer).removeClass("tabList");

              }

            },
            active: RSTabIndex
            
        });

        // if datepicker element exists, build datepicker.
        if($( ".datepicker" , tabContainer).length){
            $( ".datepicker" , tabContainer).datepicker();
        }
        var tabIndex = tabContainer.index();
        var riskID = $('.risk-id', tabContainer).html();
        var subject = $('input[name="subject"]', tabContainer).val();
        subject = subject.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        $('.tab-append .tab').eq(tabIndex).find("span").html('<b>ID:'+riskID+' </b>'+subject);
        
        // if file upload button exists, set the unique ID
        if($(".hidden-file-upload.active", tabContainer).length){
            $(".hidden-file-upload.active", tabContainer).attr('id', 'file-upload' + tabIndex);
            $("[for=file-upload]", tabContainer).attr('for', 'file-upload' + tabIndex);
        }

        setupAssetsAssetGroupsWidget($('select.assets-asset-groups-select', tabContainer), riskID);
        setupAssetsAssetGroupsViewWidget($('select.assets-asset-groups-select-disabled', tabContainer));

        /**
        * Set background on focus of textarea
        */
        focus_add_css_class("#RiskAssessmentTitle", "#assessment", tabContainer);
        focus_add_css_class("#NotesTitle", "#notes", tabContainer);
        focus_add_css_class("#SecurityRequirementsTitle", "#security_requirements", tabContainer);
        focus_add_css_class("#CurrentSolutionTitle", "#current_solution", tabContainer);
        focus_add_css_class("#SecurityRecommendationsTitle", "#security_recommendations", tabContainer);
  
        /**
        * Set Risk Scoring Method dropdown and show/hide the sub views
        */
        handleSelection($("[name=scoring_method]", tabContainer).val(), tabContainer);
        
        /**
        * Build multiselect box
        */
        $(".multiselect", tabContainer).multiselect({enableFiltering: true, buttonWidth: '100%', enableCaseInsensitiveFiltering: true,});
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
                if (risk_id != 0)
                    select_tag.parent().find('.selectize-control div').block({message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>'});
            },
            load: function(query, callback) {
                if (query.length) return callback();
                $.ajax({
                    url: BASE_URL + '/api/asset-group/options?risk_id=' + risk_id,
                    type: 'GET',
                    dataType: 'json',
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
    * Function to add the css class for textarea title and make it popup.
    * Example usage:
    * focus_add_css_class("#foo", "#bar");
    */
    function focus_add_css_class(id_of_text_head, text_area_id, parent){
        // If enable_popup setting is false, disable popup
        if($("#enable_popup").val() != 1){
            $("textarea").removeClass("enable-popup");
            return;
        }else{
            $("textarea").addClass("enable-popup");
        }
        
        var look_for = "textarea" + text_area_id;
        if( !$(look_for, parent).length ){
            text_area_id = text_area_id.replace('#','');
            look_for = "textarea[name=" + text_area_id + "]";
        }
        $(look_for, parent).focusin(function() {
            $(id_of_text_head, parent).addClass("affected-assets-title");
            $('.ui-autocomplete').addClass("popup-ui-complete")
        });
        $(look_for, parent).focusout(function() {
            $(id_of_text_head, parent).removeClass("affected-assets-title");
            $('.ui-autocomplete').removeClass("popup-ui-complete")
        });
    }

  
/**
* Add empty container
* 
*/
function addTabContainer(){
    $('.tab-show button').show();
    var num_tabs = $("div.container-fluid div.new").length + 1;

    $('.tab-show').removeClass('selected');
    $("div.tab-append").append(
        "<div class='tab new tab-show form-tab selected' id='tab"+num_tabs+"'><div><span>New Risk ("+num_tabs+")</span></div>"
        +"<button class='close tab-close' aria-label='Close' data-id='"+num_tabs+"'>"
        +"<i class='fa fa-times'></i>"
        +"</button>"
        +"</div>"
    );
    $('.tab-data').css({'display':'none'});
    var tabContainerID = 'tab-container' + num_tabs;
    $("#tab-content-container").append(
        "<div class='tab-data' id='tab-container"+num_tabs+"'>&nbsp;</div>"
    );

    return tabContainerID;

}

  /**
  * Check edit status
  * 
  */
  
  function checkEditable(tabContainer){
      if($("[name=control_number]:enabled", tabContainer).length){
          return true;
      }
      if($("[name=planning_date]:enabled", tabContainer).length){
          return true;
      }
      if($("[name=review]:enabled", tabContainer).length){
          return true;
      }
      return false;
  }
  
  function showScoreDetails() {
  }

  function hideScoreDetails() {
  }

  function updateScore() {
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
  
$(document).ready(function(){
    if(jQuery.ui !== undefined){
        jQuery.ui.autocomplete.prototype._resizeMenu = function () {
            var ul = this.menu.element;
            ul.outerWidth(this.element.outerWidth());
        }                
    }

    if($.blockUI !== undefined){
        $.blockUI.defaults.css = {
            padding: 0,
            margin: 0,
            width: '30%',
            top: '40%',
            left: '35%',
            textAlign: 'center',
            cursor: 'wait'
        };
    }
    $('body').on("click", ".error", function(e){
        $(this).removeClass("error")
    });

    /**
    * Open new risk
    * 
    */
    $('body').on("click", "td.open-risk a", function(e){
        e.preventDefault();
        var riskID = $(this).parents('tr').data('id');
        if(!riskID){
            riskID = $(this).parent().data('id');
        }
        var tabContainerID = addTabContainer();
        var tabContainer = $("#" + tabContainerID);

        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/management/risk/viewhtml?id=" + riskID,
            success: function(data){
                tabContainer.html(data.data);
                
                callbackAfterRefreshTab(tabContainer, 0);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    })
    $('body').on("click", "td.open-mitigation a", function(e){
        e.preventDefault();
        var riskID = $(this).parents('tr').data('id');
        if(!riskID){
            riskID = $(this).parent().data('id');
        }
        var tabContainerID = addTabContainer();
        var tabContainer = $("#" + tabContainerID);
        var value = $(this).html().toLowerCase();
        
        $.ajax({
            type: "GET",
            url: value=="yes" ? (BASE_URL + "/api/management/risk/viewhtml?id=" + riskID) : (BASE_URL + "/api/management/risk/viewhtml?action=editmitigation&id=" + riskID),
            success: function(data){
                tabContainer.html(data.data);
                callbackAfterRefreshTab(tabContainer, 1);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    })
    $('body').on("click", "td.open-review a", function(e){
        e.preventDefault();
        var riskID = $(this).parents('tr').data('id');
        if(!riskID){
            riskID = $(this).parent().data('id');
        }
        var tabContainerID = addTabContainer();
        var tabContainer = $("#" + tabContainerID);
        var value = $(this).html().toLowerCase();

        $.ajax({
            type: "GET",
            url: value=="yes" ? (BASE_URL + "/api/management/risk/viewhtml?id=" + riskID) : (BASE_URL + "/api/management/risk/viewhtml?action=editreview&id=" + riskID),
            success: function(data){
                tabContainer.html(data.data);
                callbackAfterRefreshTab(tabContainer, 2);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        })
    })
    
    /**
    * RST tab evemts
    * 
    */
    $('.container-fluid').delegate('.tab-show', 'click', function(){        
        $('.form-tab').removeClass('selected');
        $(this).addClass('selected');
        var index = $('.tab-close', this).attr('data-id');
        index || (index = "");
        $('.tab-data').hide();
        $('#tab-container'+index+'').show();
    });

    $('.container-fluid').delegate('.tab-close', 'click', function(){
        current_tab_close_object = $(this);
        
        var index = $(this).attr('data-id');
        var tabContainer = $("#tab-container" + index);
        if ($('div.container-fluid div.new').length > 1)
        {
            if (!checkEditable(tabContainer) || confirm($("#_delete_tab_alert").val(), "close_current_tab('"+index+"')") ){
                close_current_tab(index)
            }
            return false;
        }
    });
    
    
    /*****************/
    
    
    $('#tab-content-container').delegate('.save-risk-form', 'click', function (){
        addRisk($(this));
    })


    /********* Start Subject ***********/
    $('body').on('click', '.edit-subject-btn', function (e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
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
        var risk_id = $('.large-text', tabContainer).html();
        
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
        $commentsContainer = $(".comment-form", tabContainer).parents('.well');
        $commentsContainer.find(".collapsible--toggle").next('.collapsible').slideDown('400');
        $commentsContainer.find(".add-comments").addClass('rotate');
        $(".comment-form", tabContainer).show();
        $commentsContainer.find(".add-comments").parent().find('span i').removeClass('fa-caret-right');
        $commentsContainer.find(".add-comments").parent().find('span i').addClass('fa-caret-down');
        $(".comment-text", tabContainer).focus();
    })
    
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

    $('body').on('click', '.updateScore', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        $(".scoredetails", tabContainer).hide();
        $(".updatescore", tabContainer).show();
        $(".show", tabContainer).hide();

    })

    /**** start details ****/
    $('body').on('click', '[name=edit_details], .edit-risk', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.large-text', tabContainer).html();
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
        var risk_id = $('.large-text', tabContainer).html();
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

        var risk_id = $('.large-text', tabContainer).html();

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
        var risk_id = $('.large-text', tabContainer).html();
        
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
        var risk_id = $('.large-text', tabContainer).html();
        
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

    function updateMitigation($this){
        var tabContainer = $this.parents('.tab-data');

        // Check the sum of files the user wants to upload and
        // stop if it's over the max_upload_size
        if (!validFileUploadSize(tabContainer)) {
        	return false;
        }

        var risk_id = $('.large-text', tabContainer).html();
        
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
        var risk_id = $('.large-text', tabContainer).html();
        
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
        var risk_id = $('.large-text', tabContainer).html();
        
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
        var risk_id = $('.large-text', tabContainer).html();
        
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
        var risk_id = $('.large-text', tabContainer).html();
        
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
        var risk_id = $('.large-text', tabContainer).html();

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
        var risk_id = $('.large-text', tabContainer).html();
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
        var risk_id = $('.large-text', tabContainer).html();
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
        var risk_id = $('.large-text', tabContainer).html();
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
        var risk_id = $('.large-text', tabContainer).html();
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
        var risk_id = $('.large-text', tabContainer).html();
        var visibleScoredetails = $('.hide-score', tabContainer).is(':visible');
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/management/risk/scoreaction?id=" + risk_id + "&scoring_method=" + scoring_method,
            success: function(data){
                $('.score-overview-container', tabContainer).html(data.data);
                if(visibleScoredetails){
                    $('.scoredetails', tabContainer).show();
                    $('.show-score').hide();
                    $('.hide-score').show();
                }else{
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
        var tabContainer = $this.parents('.tab-data');
        var risk_id = $('.large-text', tabContainer).html();
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
                    $('.scoredetails', tabContainer).show();
                    $('.show-score').hide();
                    $('.hide-score').show();
                }else{
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
        
        var risk_id = $('.large-text', tabContainer).html();
        
        var getForm = $this.parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);
        $('#comment').parents('.well').block({
            message: 'Processing',
            css: { border: '1px solid black', background: '#ffffff' }
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
                $('#comment').parents('.well').unblock();
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
    * events in clicking Score Using CVSS button of edit details page, muti tabs case
    */
    $('body').on('click', '[name=cvssSubmit]', function(e){
        e.preventDefault();
        var form = $(this).parents('form');
        popupcvss(form);
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
        var risk_id = $('.large-text', tabContainer).html();
        
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
        var risk_id = $('.large-text', tabContainer).html();
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
    
    /**
    * Event when change risk owner
    */
    $('body').on('change', '[name=owner]', function(e){
        var form = $(this).closest("form");
        $.ajax({
            type: "GET",
            url: BASE_URL + "/api/user/manager",
            data: {
                id: $(this).val()
            },
            success: function(res){
                var data = res.data;
                if(data.manager){
                    $("[name=manager]", form).val(data.manager)
                }
            }
        })
    })

    /********* Start mark as unmitigation **********/
    $('body').on('click', '.mark-unmitigation', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.large-text', tabContainer).html();
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
        var risk_id = $('.large-text', tabContainer).html();
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
        var risk_id = $('.large-text', tabContainer).html();
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
        var risk_id = $('.large-text', tabContainer).html();
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
   
})



    