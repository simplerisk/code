  

    function hideNextReview(){
    }
    function showNextReview(){
    }
    function showScoreDetails(){
    }
    
    function hideScoreDetails(){
    }
    
  function addRisk($this){
    var tabContainer = $this.parents('.tab-data');
    var getForm = $this.parent().parent().parent().parent();
    var div = getForm.parent().parent();
    var index = parseInt((div).attr('id').replace(/[A-Za-z$-]/g, ""));
    var form = new FormData($(getForm)[0]);
    $.each($("input[type=file]", tabContainer), function(i, obj) {
        $.each(obj.files, function(j, file){
            form.append('file['+j+']', file);
        })
    });
    $('#show-alert').html('');
    $.ajax({
        type: "POST",
        url: "index.php",
        data: form,
        async: true,
        cache: false,
        contentType: false,
        processData: false,
        success: function(data){
            var message = $(data).filter('#alert');
            var risk_id = $(data).filter('#risk_hid_id');

            $('#show-alert').html(message);
            if (message[0].innerText != 'The subject of a risk cannot be empty.'){
                if (isNaN(index)){
                    var subject = $('input[name="subject"]', getForm).val();
                    var subject = subject.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    $('#tab span:eq(0)').html('<b>ID:'+risk_id[0].innerText+' </b>'+subject);
                    //$('#tab span:eq(0)').html('<b>ID:'+risk_id[0].innerText+' </b>'+$('input[name="subject"]', getForm).val());
                } else {
                    var subject = $('input[name="subject"]', getForm).val();
                    var subject = subject.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    $('#tab'+index+' span:eq(0)').html('<b>ID:'+risk_id[0].innerText+' </b>'+subject);
                    //$('#tab'+index+' span:eq(0)').html('<b>ID:'+risk_id[0].innerText+' </b>'+$('input[name="subject"]', getForm).val());
                }
//                $('input, select, textarea', getForm).prop('disabled', true);
                
                $.ajax({
                    type: "GET",
                    url: "../api/management/risk/viewhtml?id=" + risk_id[0].innerText,
                    success: function(data){
                        tabContainer.html(data.data);

                        callbackAfterRefreshTab(tabContainer)
                    },
                    error: function(xhr,status,error){
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            $('#show-alert').html(xhr.responseJSON.status_message);
                        }
                    }
                })
                $this.prop('disabled', true);
            } else {
                $this.removeAttr('disabled');
            }
        }
    })
    .fail(function(xhr, textStatus){
        var obj = $('<div/>').html(xhr.responseText);
        var token = obj.find('input[name="__csrf_magic"]').val();
        if(token){
            $('input[name="__csrf_magic"]').val(token);
            addRisk($this);
        }else{
            if(xhr.responseJSON && xhr.responseJSON.status_message){
                $('#show-alert').html(xhr.responseJSON.status_message);
            }
        }
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
        var tabIndex = tabContainer.attr('id').replace(/[A-Za-z$-]/g, "");
        var riskID = $('.risk-id', tabContainer).html();
        var subject = $('input[name="subject"]', tabContainer).val();
        subject = subject.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        $('#tab'+tabIndex+' span:eq(0)').html('<b>ID:'+riskID+' </b>'+subject);
        
        // if file upload button exists, set the unique ID
        if($(".hidden-file-upload.active", tabContainer).length){
            $(".hidden-file-upload.active", tabContainer).attr('id', 'file-upload' + tabIndex)
            $("[for=file-upload]", tabContainer).attr('for', 'file-upload' + tabIndex)
        }
        
        /**
        * Build tab container
        * 
        */
        $(".assets", tabContainer)
          .bind( "keydown", function( event ) {
            if ( event.keyCode === $.ui.keyCode.TAB && $( this ).autocomplete( "instance" ).menu.active ) {
              event.preventDefault();
            }
          })
          .autocomplete({
                minLength: 0,
                source: function( request, response ) {
                // delegate back to autocomplete, but extract the last term
                response( $.ui.autocomplete.filter(
                availableAssets, extractLast( request.term ) ) );
              },
              focus: function() {
                // prevent value inserted on focus
                return false;
              },
              select: function( event, ui ) {
                var terms = split( this.value );
                // remove the current input
                terms.pop();
                // add the selected item
                terms.push( ui.item.value );
                // add placeholder to get the comma-and-space at the end
                terms.push( "" );
                this.value = terms.join( ", " );
                return false;
              }
          });  
          
        /**
        * Set background on focus of textarea
        */
        focus_add_css_class("#AffectedAssetsTitle", "#assets", tabContainer);
        focus_add_css_class("#RiskAssessmentTitle", "#assessment", tabContainer);
        focus_add_css_class("#NotesTitle", "#notes", tabContainer);
        focus_add_css_class("#SecurityRequirementsTitle", "#security_requirements", tabContainer);
        focus_add_css_class("#CurrentSolutionTitle", "#current_solution", tabContainer);
        focus_add_css_class("#SecurityRecommendationsTitle", "#security_recommendations", tabContainer);
  
        /**
        * Set Risk Scoring Method dropdown and show/hide the sub views
        */
        handleSelection($("[name=scoring_method]", tabContainer).val(), tabContainer)
  }
    
    /*
    * Function to add the css class for textarea title and make it popup.
    * Example usage:
    * focus_add_css_class("#foo", "#bar");
    */
    function focus_add_css_class(id_of_text_head, text_area_id, parent){
        look_for = "textarea" + text_area_id;
        console.log(look_for);
        if( !$(look_for, parent).length ){
            text_area_id = text_area_id.replace('#','');
            look_for = "textarea[name=" + text_area_id;
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
          +"<i class='fa fa-close'></i>"
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
  
  
$(document).ready(function(){
    /**
    * Open new risk
    * 
    */
    $("td.open-risk a").click(function(e){
        e.preventDefault();
        var riskID = $(this).parents('tr').data('id');
        var tabContainerID = addTabContainer();
        var tabContainer = $("#" + tabContainerID);
        $.ajax({
            type: "GET",
            url: "../api/management/risk/viewhtml?id=" + riskID,
            success: function(data){
                tabContainer.html(data.data);
                callbackAfterRefreshTab(tabContainer, 0);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
                }
            }
        })
    })
    $("td.open-mitigation a").click(function(e){
        e.preventDefault();
        var riskID = $(this).parents('tr').data('id');
        var tabContainerID = addTabContainer();
        var tabContainer = $("#" + tabContainerID);
        var value = $(this).html().toLowerCase();
        
        $.ajax({
            type: "GET",
            url: value=="yes" ? "../api/management/risk/viewhtml?id=" + riskID : "../api/management/risk/viewhtml?action=editmitigation&id=" + riskID,
            success: function(data){
                tabContainer.html(data.data);
                callbackAfterRefreshTab(tabContainer, 1);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
                }
            }
        })
    })
    $("td.open-review a").click(function(e){
        e.preventDefault();
        var riskID = $(this).parents('tr').data('id');
        var tabContainerID = addTabContainer();
        var tabContainer = $("#" + tabContainerID);
        var value = $(this).html().toLowerCase();

        $.ajax({
            type: "GET",
            url: value=="yes" ? "../api/management/risk/viewhtml?id=" + riskID : "../api/management/risk/viewhtml?action=editreview&id=" + riskID,
            success: function(data){
                tabContainer.html(data.data);
                callbackAfterRefreshTab(tabContainer, 2);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
                }
            }
        })
    })
    
    /**
    * RST tab evemts
    * 
    */
    $('.container-fluid').delegate('.tab-show', 'click', function(){
        $('#show-alert').html('');
        $('.form-tab').removeClass('selected');
        $(this).addClass('selected');
        var index = $('.tab-close', this).attr('data-id');
        index || (index = "");
        $('.tab-data').hide();
        $('#tab-container'+index+'').show();
    });

    $('.container-fluid').delegate('.tab-close', 'click', function(){
        var index = $(this).attr('data-id');
        var tabContainer = $("#tab-container" + index);
        if ($('div.container-fluid div.new').length > 1)
        {
            if (!checkEditable(tabContainer) || confirm($("#_delete_tab_alert").val()) ){
                $('#tab-container'+index+'').remove();
                $(this).parent().remove();
                $('.tab-show').first().addClass('selected');
                $('.tab-data').last().show();
            }
            return false;
        }
    });
    /*****************/
    
    
//    temp()
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
        $('#show-alert').html('');
        $.ajax({
            type: "POST",
            url: "../api/management/risk/saveSubject?id=" + risk_id,
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
                    $('#show-alert').html(data.status_message);
                }
            }
        })
        .fail(function(xhr, textStatus){
            var obj = $('<div/>').html(xhr.responseText);
            var token = obj.find('input[name="__csrf_magic"]').val();
            if(token){
                $('input[name="__csrf_magic"]').val(token);
                updateSubject($this);
            }else{
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
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
        $(".show", tabContainer).style.hide();

    })

    /**** start details ****/
    $('body').on('click', '[name=edit_details], .edit-risk', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.large-text', tabContainer).html();
        var $this = $(this);
        
        $.ajax({
            type: "GET",
            url: "../api/management/risk/editdetails?action=editdetail&id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 0);
                
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
                }
            }
        })
        
    })
    
    
    $('body').on('click', '.cancel-edit-details', function(e){
        e.preventDefault();
        var tabContainer = $(this).parents('.tab-data');
        var risk_id = $('.large-text', tabContainer).html();
        var $this = $(this);
        
        $.ajax({
            type: "GET",
            url: "../api/management/risk/editdetails?id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 0);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
                }
            }
        })
    })
    
    function updateRisk($this){
        var tabContainer = $this.parents('.tab-data');
        var risk_id = $('.large-text', tabContainer).html();
        
        var getForm = $this.parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);
        var scoring_method = $("[name=scoring_method]", tabContainer).val();

        $.each($("input[type=file]", tabContainer), function(i, obj) {
            $.each(obj.files,function(j, file){
                form.append('file['+j+']', file);
            })
        });
        $('#show-alert').html('');
        $.ajax({
            type: "POST",
            url: "../api/management/risk/saveDetails?id=" + risk_id,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 0);
                getScoreByAction(tabContainer, scoring_method);

                if(data.status_message){
                    $('#show-alert').html(data.status_message);
                }
            }
        })
        .fail(function(xhr, textStatus){
            var obj = $('<div/>').html(xhr.responseText);
            var token = obj.find('input[name="__csrf_magic"]').val();
            if(token){
                $('input[name="__csrf_magic"]').val(token);
                updateRisk($this);
            }else{
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
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
            url: "../api/management/risk/editdetails?action=editmitigation&id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 1);
//                $('.tabs2', tabContainer).find('input, select, textarea').prop('disabled', false);

            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
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
            url: "../api/management/risk/editdetails?id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 1);
//                $('.tabs2', tabContainer).find('input, select, textarea').prop('disabled', false);

            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
                }
            }
        })
    })

    function updateMitigation($this){
        var tabContainer = $this.parents('.tab-data');
        var risk_id = $('.large-text', tabContainer).html();
        
        var getForm = $this.parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);
        $.each($("input[type=file]", tabContainer), function(i, obj) {
            $.each(obj.files,function(j, file){
                form.append('file['+j+']', file);
            })
        });
        $('#show-alert').html('');
        $.ajax({
            type: "POST",
            url: "../api/management/risk/saveMitigation?id=" + risk_id,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 1);
                if(data.status_message){
                    $('#show-alert').html(data.status_message);
                }
            }
        })
        .fail(function(xhr, textStatus){
            var obj = $('<div/>').html(xhr.responseText);
            var token = obj.find('input[name="__csrf_magic"]').val();
            if(token){
                $('input[name="__csrf_magic"]').val(token);
                updateMitigation($this);
            }else{
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
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
            url: "../api/management/risk/editdetails?action=editreview&id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 2);
//                $('.tabs3', tabContainer).find('input, select, textarea').prop('disabled', false);

            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
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
            url: "../api/management/risk/editdetails?id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 2);
//                $('.tabs3', tabContainer).find('input, select, textarea').prop('disabled', false);

            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
                }
            }
        })
    })
    
    function updateReview($this){
        var tabContainer = $this.parents('.tab-data');
        var risk_id = $('.large-text', tabContainer).html();
        
        var getForm = $this.parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);
        $.each($("input[type=file]", tabContainer), function(i, obj) {
            $.each(obj.files,function(j, file){
                form.append('file['+j+']', file);
            })
        });
        $('#show-alert').html('');
        $.ajax({
            type: "POST",
            url: "../api/management/risk/saveReview?id=" + risk_id,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
                callbackAfterRefreshTab(tabContainer, 2);
                if(data.status_message){
                    $('#show-alert').html(data.status_message);
                }
            }
        })
        .fail(function(xhr, textStatus){
            var obj = $('<div/>').html(xhr.responseText);
            var token = obj.find('input[name="__csrf_magic"]').val();
            if(token){
                $('input[name="__csrf_magic"]').val(token);
                updateReview($this);
            }else{
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
                }
            }
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
            url: "../api/management/risk/view_all_reviews?id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
                }
            }
        })
    });
    /***** end review ******/
    
    /*************** start close risk ******************/
    function closeRisk($this){
        var tabContainer = $this.parents('.tab-data');
        var risk_id = $('.large-text', tabContainer).html();
        
        var getForm = $this.parents('form', tabContainer);
        var form = new FormData($(getForm)[0]);

        $.ajax({
            type: "POST",
            url: "../api/management/risk/closerisk?id=" + risk_id,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data){
                tabContainer.html(data.data);
                callbackAfterRefreshTab(tabContainer);
                if(data.status_message){
                    $('#show-alert').html(data.status_message);
                }
            }
        })
        .fail(function(xhr, textStatus){
            var obj = $('<div/>').html(xhr.responseText);
            var token = obj.find('input[name="__csrf_magic"]').val();
            if(token){
                $('input[name="__csrf_magic"]').val(token);
                closeRisk($this);
            }else{
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
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
            url: "../api/management/risk/closerisk?id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
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
            type: "GET",
            url: "../api/management/risk/reopen?id=" + risk_id,
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
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
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
            url: "../api/management/risk/changestatus?id=" + risk_id,
            success: function(data){
                $('.content-container', tabContainer).html(data.data);
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
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
            url: "../api/management/risk/updateStatus?id=" + risk_id,
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data){
                tabContainer.html(data.data);
                callbackAfterRefreshTab(tabContainer);
                if(data.status_message){
                    $('#show-alert').html(data.status_message);
                }
            }
        })
        .fail(function(xhr, textStatus){
            var obj = $('<div/>').html(xhr.responseText);
            var token = obj.find('input[name="__csrf_magic"]').val();
            if(token){
                $('input[name="__csrf_magic"]').val(token);
                updateStatus($this);
            }else{
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
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
            url: "../api/management/risk/scoreaction?id=" + risk_id + "&scoring_method=" + scoring_method,
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
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
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
            url: "../api/management/risk/saveScore?id=" + risk_id + "&action=" + action,
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
                    $('#show-alert').html(data.status_message);
                }
                
//                $.ajax({
//                    type: "GET",
//                    url: "../api/management/risk/overview?id=" + risk_id,
//                    success: function(data){
//                        if($('.show-score').is(":visible")){
//                            $('.overview-container', tabContainer).html(data.data);
//                            $('.show-score').show();
//                            $('.hide-score').hide();
//                        }else{
//                            $('.overview-container', tabContainer).html(data.data);
//                            $('.show-score').hide();
//                            $('.hide-score').show();
//                        }
//                    }
//                })
                
            }
        })
        .fail(function(xhr, textStatus){
            var obj = $('<div/>').html(xhr.responseText);
            var token = obj.find('input[name="__csrf_magic"]').val();
            if(token){
                $('input[name="__csrf_magic"]').val(token);
                updateScore($this);
            }else{
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
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

    $('body').on('click', '.collapsible--toggle span', function(event) {
        event.preventDefault();
        $(this).parents('.collapsible--toggle').next('.collapsible').slideToggle('400');
        $(this).find('i').toggleClass('fa-caret-right fa-caret-down');
    });

    $('body').on('click', '.add-comments', function(event) {
        event.preventDefault();
        $(this).parents('.collapsible--toggle').next('.collapsible').slideDown('400');
        $(this).toggleClass('rotate');
        var tabContainer = $(this).parents('.tab-data');
        $('.comment-form', tabContainer).fadeToggle('100');
        $(this).parent().find('span i').removeClass('fa-caret-right');
        $(this).parent().find('span i').addClass('fa-caret-down');
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

        $.ajax({
            type: "POST",
            url: "../api/management/risk/saveComment?id=" + risk_id,
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
                    $('#show-alert').html(data.status_message);
                }
            }
        })
        .fail(function(xhr, textStatus){
            var obj = $('<div/>').html(xhr.responseText);
            var token = obj.find('input[name="__csrf_magic"]').val();
            if(token){
                $('input[name="__csrf_magic"]').val(token);
                saveComment($this);
            }else{
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    $('#show-alert').html(xhr.responseJSON.status_message);
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
    
})



   
