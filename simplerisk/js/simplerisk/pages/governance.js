$.fn.extend({
	initAsFrameworkTreegrid: function(status, editable) {
		// Can't initialize it twice
		if (this.data('initialized')) {
			return;
		}

		let tabs = this.parents('.tab-pane');
		let activeTabs = this.parents('.tab-pane.active');

		// Can't initialize if not all of the parent tabs(if there's any) active
		// because the treegrid doesn't properly initialize in the background
		if (tabs.length != activeTabs.length) {
			return;
		}

        this.treegrid({
            dropAccept:status == 2 ? 'nope' : '',
            animate: true,
            collapsible: false,
            fitColumns: true,
            url: BASE_URL + '/api/v2/governance/frameworks/treegrid?status=' + status,
            method: 'get',
            async: false,
            idField: 'value',
            treeField: 'name',
            scrollbarSize: 0,
            onLoadSuccess: function(row, data){
				if (editable) {
                	$(this).treegrid('enableDnd', row?row.value:null);
                }
    			if (status==1) {
                    $('#active-frameworks-count').html(data.totalCount);
                    // $('#frameworks-count').html(data.totalCount + parseInt($('#inactive-frameworks-count').html()));
                    // Add the status as a class so the tab headers can decide whether to accept the drop or not
                    // it's to be able to only accept drops that 'make sense' meaning no drops with the status the
                    // framework already has
                    $('#active-frameworks .datagrid-row.droppable').addClass(''+status);
			    } else {
                    $('#inactive-frameworks-count').html(data.totalCount);
                    // $('#frameworks-count').html(data.totalCount + parseInt($('#active-frameworks-count').html()));
                    // Add the status as a class so the tab headers can decide whether to accept the drop or not
                    // it's to be able to only accept drops that 'make sense' meaning no drops with the status the
                    // framework already has
                    $('#inactive-frameworks .datagrid-row.droppable').addClass(''+status);
		    	}
		    	
                //fixTreeGridCollapsableColumn();
            },
            onStopDrag: function(row){
                var tag = document.elementFromPoint(mouseX - window.pageXOffset, mouseY - window.pageYOffset);
                
                if($(tag).hasClass('nav-link')){
                    var data_status = $(tag).data('status');
                    if (data_status == status) { // Don't accept dropping a framework on its current status' tab
                        return;
                    }

                    var framework_id = row.value;
                    $.ajax({
                        url: BASE_URL + '/api/governance/update_framework_status',
                        type: 'POST',
                        data: {framework_id : framework_id, status:data_status},
                        success : function (data){
                            setTimeout(function(){
                                location.reload();
                            }, 100)
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                            }
                        }
                    });
                }
            },
            onBeforeDrop: function(targetRow, sourceRow,point){
                // Don't let it drop 'between' the rows as it's confusing
                return point=='append';
            },
            onDrop: function(targetRow, sourceRow){
                var parent = targetRow ? targetRow.value : 0;
                var framework_id = sourceRow.value;
                  $.ajax({
                    url: BASE_URL + '/api/governance/update_framework_parent',
                    type: 'POST',
                    data: {parent : parent, framework_id:framework_id},
                    success: function(data){
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }
                        $('.framework-table-' + status).treegrid('reload');
                    },
                    error: function(xhr,status,error) {
                        if(!retryCSRF(xhr, this)) {
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                                setTimeout(function(){
                                    location.reload();
                                }, 100);
                            }
                        }
                    }
                });
            }
        });
        $(this).data('initialized', true);
	},

    initAsDocumentProgramTreegrid: function(type=false) {

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

        let _this = this;
        
        this.treegrid({
            iconCls: 'icon-ok',
            animate: true,
            collapsible: false,
            fitColumns: true,
            url: BASE_URL + (type === 'document-hierarchy' ? '/api/governance/documents?type=' : `/api/governance/tabular_documents?type=${type}`),
            method: 'get',
            idField: 'id',
            treeField: 'document_name',
            remoteFilter: true,
            scrollbarSize: 0,
            onResize: function() {
                // After rendering the datagrid filter head row, reduce the editable filter inputs' width by 30px
                // so that could make the datagrid table filter head row have the same width as the datagrid table body
                $('.datagrid-htable .datagrid-filter-row .datagrid-filter', this).each((i, e) => {
                    $(e).css('width', (parseInt($(e).css('width'))-30) + 'px');
                });
            },
            onLoadSuccess: function(){
                // Run the resize logic when the data is loaded
                $(_this).treegrid('resize');
            },
            onCollapse: function() {
                // Run the resize logic when the data is loaded
                $(_this).treegrid('resize');
            },
            onExpand: function() {
                // Run the resize logic when the data is loaded
                $(_this).treegrid('resize');
            },
        }).treegrid('enableFilter', [{
            field:'actions',
            type:'label'
        }]);

        $(this).data('initialized', true);
    },
    
    initAsExceptionTreegrid: function(type=false) {
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

        this.treegrid({
            iconCls: 'icon-ok',
            animate: false,
            fitColumns: true,
            nowrap: true,
            url: BASE_URL + `/api/exceptions/tree?type=${type}`,
            method: 'get',
            idField: 'value',
            treeField: 'name',
            scrollbarSize: 0,
            loadFilter: function(data, parentId) {
                return data.data;
            },
            onLoadSuccess: function(row, data){
                fixTreeGridCollapsableColumn();
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
    },
});



var controlDatatable;
jQuery(document).ready(function($){

    var controlObject = {


        init : function(){
            this.bindEvents();
        },

        bindEvents : function(){

          var self = this;

          $(document).on('click', '.document--delete', function(event) {
            event.preventDefault();
            var document_id = $(this).data('id');
            var version = $(this).data('version');
            var document_type = $(this).data('type');
            var modal = $('#document-delete-modal');
            $('.document_id', modal).val(document_id);
            $('.version', modal).val(version);
            $('.document_type', modal).val(document_type);
            $(modal).modal('show');
          });

          $(document).on('click', '.framework-block--delete', function(event) {
            event.preventDefault();
            //$(this).parents('.framework-block').fadeOut('400').delay('500').remove();
            var framework_id = $(this).attr('data-id');
            var modal = $('#framework--delete');
            $('.delete-id', modal).val(framework_id);
            $(modal).modal('show');
          });

          $(document).on('click', '.control-block--delete', function(event) {
            event.preventDefault();
            var control_id = $(this).attr('data-id');
            var modal = $('#control--delete');
            $('.delete-id', modal).val(control_id);
            $(modal).modal('show');
          });

          $(document).on('click', '.control-block--edit', function(event) {

            event.preventDefault();
            resetForm('#control--update form');
            var control_id  = $(this).attr('data-id');
            $.ajax({
                url: BASE_URL + '/api/governance/control?control_id=' + control_id,
                type: 'GET',
                dataType: 'json',
                success : function (res){
                    var data = res.data;
                    var control = data.control;
                    
                    var modal = $('#control--update');
                    $('.control_id', modal).val(control_id);
                    $('[name=short_name]', modal).val(control.short_name);
                    $('[name=long_name]', modal).val(control.long_name);
                    $('[name=description]', modal).val(control.description);
                    $('[name=supplemental_guidance]', modal).val(control.supplemental_guidance);
                    
                    $("#frameworks").multiselect('deselectAll', false);
                    $.each(control.framework_ids.split(","), function(i,e){
                        $("#frameworks option[value='" + e + "']").prop("selected", true);
                    });
                    $("#frameworks").multiselect('refresh');

                    if(control.control_type_ids != null) control_type_ids = control.control_type_ids;
                    else control_type_ids = "";
                    
                    $('[name=control_class]', modal).val(Number(control.control_class) ? control.control_class : "");
                    $('[name=control_phase]', modal).val(Number(control.control_phase) ? control.control_phase : "");
                    $('[name=control_owner]', modal).val(Number(control.control_owner) ? control.control_owner : "");
                    $('[name=control_number]', modal).val(control.control_number);
                    $('[name=control_current_maturity]', modal).val(Number(control.control_maturity) ? control.control_maturity : 0);
                    $('[name=control_desired_maturity]', modal).val(Number(control.desired_maturity) ? control.desired_maturity : 0);
                    $('[name=control_priority]', modal).val(Number(control.control_priority) ? control.control_priority : "");
                    $('[name="control_type[]"]', modal).multiselect('deselectAll', false);
                    $('[name="control_type[]"]', modal).multiselect('select', control_type_ids.split(','));
                    $('[name="control_type[]"]', modal).multiselect('refresh');
                    $('[name=control_status]', modal).val(Number(control.control_status) ? control.control_status : 0);
                    $('[name=family]', modal).val(Number(control.family) ? control.family : "");
                    $('[name=mitigation_percent]', modal).val(Number(control.mitigation_percent) ? control.mitigation_percent : "");
                    $(".mapping_framework_table tbody", modal).html(data.mapped_frameworks);

                    let mapping_framework_count = $(".mapping_framework_table tbody tr").length;
                    // Show the mandatory fields when there are mapping frameworks
                    if (mapping_framework_count) {
                        $(".mapping_framework_table thead .mapping-framework-required-mark").removeClass('d-none');
                    }

                    $(".mapping_asset_table tbody", modal).html(data.mapped_assets);
                    $('.mapping_asset_table select.assets-asset-groups-select', modal).each(function(index, element){
                        $(element).attr('name', 'assets_asset_groups[' + index + '][]');
                        setupAssetsAssetGroupsWidget($(element),control_id, control.mapped_maturity[index]);
                    });

                    let mapping_asset_count = $(".mapping_asset_table tbody tr").length;
                    // Show the mandatory fields when there are mapping assets
                    if (mapping_asset_count) {
                        $(".mapping_asset_table thead .mapping-asset-required-mark").removeClass('d-none');
                    }

                    $('[name*="custom_field"]', modal).val("");
                    if(control.custom_values){
                      var custom_values = control.custom_values;
                      for (var i=0; i<custom_values.length; i++) {
                        var field_value = custom_values[i].value;
                    	var element = $("[name^='custom_field[" + custom_values[i].field_id + "]']", modal);
                        if (field_value && custom_values[i].field_type == 'multidropdown' || custom_values[i].field_type == 'user_multidropdown') {
                            element.multiselect('select', field_value);
                        } else {
                            element.val(field_value ? field_value : '');
                        }
                      }
                    }

                    tinyMCE.get("update_control_description").setContent(control.description);
                    tinyMCE.get("update_supplemental_guidance").setContent(control.supplemental_guidance);

                    $(modal).modal('show');
                }
            });
          });
          
          $(document).on('click', '.control-block--clone', function(event) {
            event.preventDefault();
			resetForm('#control--add form');
            var control_id  = $(this).attr('data-id');
            $.ajax({
                url: BASE_URL + '/api/governance/control?control_id=' + control_id,
                type: 'GET',
                dataType: 'json',
                success : function (res){
                    var data = res.data;
                    var control = data.control;

                    if(control.control_type_ids != null) control_type_ids = control.control_type_ids;
                    else control_type_ids = "";
                    
                    var modal = $('#control--add');
                    $('[name=short_name]', modal).val(control.short_name);
                    $('[name=long_name]', modal).val(control.long_name);
                    $('[name=description]', modal).val(control.description);
                    $('[name=supplemental_guidance]', modal).val(control.supplemental_guidance);
                    $('[name=control_class]', modal).val(Number(control.control_class) ? control.control_class : "");
                    $('[name=control_phase]', modal).val(Number(control.control_phase) ? control.control_phase : "");
                    $('[name=control_owner]', modal).val(Number(control.control_owner) ? control.control_owner : "");
                    $('[name=control_number]', modal).val(control.control_number);
                    $('[name=control_current_maturity]', modal).val(Number(control.control_maturity) ? control.control_maturity : "");
                    $('[name=control_desired_maturity]', modal).val(Number(control.desired_maturity) ? control.desired_maturity : "");
                    $('[name=control_priority]', modal).val(Number(control.control_priority) ? control.control_priority : "");
                    $('[name="control_type[]"]', modal).multiselect('deselectAll', false);
                    $('[name="control_type[]"]', modal).multiselect('select', control_type_ids.split(','));
                    $('[name="control_type[]"]', modal).multiselect('refresh');
                    $('[name=control_status]', modal).val(Number(control.control_status) ? control.control_status : 0);
                    $('[name=family]', modal).val(Number(control.family) ? control.family : "");
                    $('[name=mitigation_percent]', modal).val(Number(control.mitigation_percent) ? control.mitigation_percent : "");

                    $('[name*="custom_field"]', modal).val("");
                    if(control.custom_values){
                      var custom_values = control.custom_values;
                      for (var i=0; i<custom_values.length; i++) {
                        var field_value = custom_values[i].value;
                    	var element = $("[name^='custom_field[" + custom_values[i].field_id + "]']", modal);
                        if (field_value && custom_values[i].field_type == 'multidropdown' || custom_values[i].field_type == 'user_multidropdown') {
                            element.multiselect('select', field_value);
                        } else {
                            element.val(field_value ? field_value : '');
                        }
                      }
                    }

                    tinyMCE.get("add_control_description").setContent(control.description);
                    tinyMCE.get("add_supplemental_guidance").setContent(control.supplemental_guidance);

                    $(modal).modal('show');
                    $(".mapping_framework_table tbody", modal).html(data.mapped_frameworks)
                    $(".mapping_asset_table tbody", modal).html(data.mapped_assets);
                    $('.mapping_asset_table select.assets-asset-groups-select', modal).each(function(index, element){
                        $(element).attr('name', 'assets_asset_groups[' + index + '][]');
                        setupAssetsAssetGroupsWidget($(element),control_id, control.mapped_maturity[index]);
                    });
                }
            });
          });
          $(document).on('click', '.control-block--add-mapping', function(event) {

            event.preventDefault();

            var form = $(this).closest('form');
            // To get the html of the <tr> tag
            $(".mapping_framework_table tbody", form).append($("#add_mapping_row table tr:first-child").parent().html());

            // Show the mandatory fields when there are mapping frameworks
            $(".mapping_framework_table thead .mapping-framework-required-mark", form).removeClass('d-none');

          });
          $(document).on('click', '.control-block--delete-mapping', function(event) {

            event.preventDefault();

            $(this).closest("tr").remove();

            let mapping_framework_count = $(".mapping_framework_table tbody tr").length;

            // Hide the mandatory fields when there are no mapping frameworks
            if (!mapping_framework_count) {
              $(".mapping_framework_table thead .mapping-framework-required-mark").addClass('d-none');
            }

          });
          $('#control--add').on('shown.bs.modal', function () {
              $(".mapping_framework_table tbody", this).html("");
              $(".mapping_asset_table tbody", this).html("");
              $('[name="control_type[]"]').multiselect('refresh');
          });
          $('#control--add').on('hidden.bs.modal', function () {
                $("#filter_by_control_framework").multiselect("rebuild");
                $("#add-control-form")[0].reset();
                $('#add-control-form [name="control_type[]"]').multiselect('deselectAll', false);
                $('#add-control-form [name="control_type[]"]').multiselect('select', [1]);
                $('#add-control-form [name="control_type[]"]').multiselect('refresh');
                $('#add-control-form [name*="custom_field"]').val("");
                $('#add-control-form [name*="custom_field"].multiselect').multiselect('refresh');
          });
          $(document).on('change', '[name*=map_framework_id]', function(event) {
              var cur_select = this;
              if(!$(cur_select).val()) return true;
              var existing_mappings = $("#existing_mappings").val();
              $("[name*=map_framework_id]").each(function(index){
                if(this != cur_select && $(this).val() == $(cur_select).val()){
                    $(cur_select).find("option:eq(0)").prop('selected', true);
                    showAlertFromMessage(existing_mappings, false);
                    return false;
                }
              });
          });
          $('#control--add, #control--update').on('hidden.bs.modal', function () {
              $("[name*=map_framework_id]").each(function(index){
                  $(this).find("option:eq(0)").prop('selected', true);
              });
          })
          $(document).on('click', '.control-block--add-asset', function(event) {

            event.preventDefault();

            var form = $(this).closest('form');
            var appended_row = $($("#add_asset_row table tr:first-child").parent().html()).appendTo($(".mapping_asset_table tbody", form));

            $('.mapping_asset_table select.assets-asset-groups-select', form).each(function(index, element){
                $(element).attr('name', 'assets_asset_groups[' + index + '][]');
            });
            // var appended_row = $(".mapping_asset_table tbody", form).append($("#add_asset_row table tr:first-child").parent().html());
            setupAssetsAssetGroupsWidget($("select.assets-asset-groups-select", appended_row));

            // Show the mandatory fields when there are mapping assets
            $(".mapping_asset_table thead .mapping-asset-required-mark", form).removeClass('d-none');

          });
          $(document).on('click', '.control-block--delete-asset', function(event) {

            event.preventDefault();

            var form = $(this).closest('form');
            $(this).closest("tr").remove();
            $('.mapping_asset_table select.assets-asset-groups-select', form).each(function(index, element){
                $(element).attr('name', 'assets_asset_groups[' + index + '][]');
            });

            let mapping_asset_count = $(".mapping_asset_table tbody tr").length;

            // Hide the mandatory fields when there are no mapping assets
            if (!mapping_asset_count) {
              $(".mapping_asset_table thead .mapping-asset-required-mark").addClass('d-none');
            }
            
          });
          $(document).on('change', '[name*=asset_maturity]', function(event) {
              var cur_select = this;
              if(!$(cur_select).val()) return true;
              var form = $(this).closest('form');
              var existing_mappings = $("#existing_mappings").val();
              $("[name*=asset_maturity]", form).each(function(index){
                if(this != cur_select && $(this).val() == $(cur_select).val()){
                    $(cur_select).find("option:eq(0)").prop('selected', true);
                    showAlertFromMessage(existing_mappings, false);
                    return false;
                }
              });
          });
        }
    };

    controlObject.init();
  
    // Initiate Datatable of controls
    var pageLength = 10;
    if ($.fn.DataTable && $("#active-controls").length) {
        controlDatatable = $("#active-controls").DataTable({
            scrollX: true,
            bSort: true,
            ajax: {
                url: BASE_URL + '/api/datatable/framework_controls',
                type: "POST",
                data: function (d) {
                    d.control_class = $("#filter_by_control_class").val();
                    d.control_phase = $("#filter_by_control_phase").val();
                    d.control_family = $("#filter_by_control_family").val();
                    d.control_owner = $("#filter_by_control_owner").val();
                    d.control_framework = $("#filter_by_control_framework").val();
                    d.control_priority = $("#filter_by_control_priority").val();
                    d.control_type = $("#filter_by_control_type").val();
                    d.control_status = $("#filter_by_control_status").val();
                    d.control_text = $("#filter_by_control_text").val();
                },
                error: function (xhr, status, error) {
                    retryCSRF(xhr, this);
                },
                complete: function (response) {
                    if (response.status == 200) {
                        $("#controls_count").html(parseInt(response.responseJSON.recordsFiltered));
                        rebuild_filter($("#filter_by_control_class"), response.responseJSON.classList);
                        rebuild_filter($("#filter_by_control_phase"), response.responseJSON.phaseList);
                        rebuild_filter($("#filter_by_control_family"), response.responseJSON.familyList);
                        rebuild_filter($("#filter_by_control_owner"), response.responseJSON.ownerList);
                        rebuild_filter($("#filter_by_control_priority"), response.responseJSON.priorityList);
                    }
                }
            }
        });

        controlDatatable.on('draw', function (e, settings) {
            $('#delete-controls-btn').attr('disabled', true);
        });
    }

    /**************** Start get AI document create **********/

    $('body').on('click', '.ai-document--create', function(e){
        e.preventDefault();
        var document_type  = $(this).attr('data-type');
        var document_name  = $(this).attr('data-name');

        var createButton = $(this); // Reference to the clicked button
        var deleteButton = $('input.ai-document--delete[data-type="' + document_type + '"][data-name="' + document_name + '"]');

        // Show spinner before API call
        $.blockUI({
            message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>',
            baseZ:'10001'
        });

        $.ajax({
            url: BASE_URL + '/api/v2/ai/document/create?document_type=' + document_type + '&document_name=' + document_name,
            type: 'GET',
            dataType: 'json',
            success : function (res){
                // If there are any status messages, show them
                if(res && res.status_message){
                    showAlertsFromArray(res.status_message, true);
                }

                // Get the document id that was created
                var data = res.data;
                document_id = data.document_id;

                // Hide the create button and disable it
                createButton.hide().prop('disabled', true);

                // Show the delete button and enable it
                deleteButton.show().prop('disabled', false).attr('data-id', document_id);
            },
            complete: function(){
                $.unblockUI();
            },
            error: function(res) {
                // If there are any status messages, show them
                if(res && res.status_message){
                    showAlertsFromArray(res.status_message);
                }
            }
        });
    });

    /**************** End get AI document create **********/

    /**************** Start get AI document delete **********/

    $('body').on('click', '.ai-document--delete', function(e){
        e.preventDefault();
        var document_id  = $(this).attr('data-id');
        var document_type = $(this).attr('data-type');
        var document_name  = $(this).attr('data-name');

        var deleteButton = $(this); // Reference to the clicked button
        var createButton = $('input.ai-document--create[data-type="' + document_type + '"][data-name="' + document_name + '"]');

        // Show spinner before API call
        $.blockUI({
            message:'<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>',
            baseZ:'10001'
        });

        $.ajax({
            type: "DELETE",
            url: BASE_URL + "/api/v2/governance/documents?document_id=" + document_id,
            dataType: 'json',
            success : function (res){
                // If there are any status messages, show them
                if(res && res.status_message){
                    showAlertsFromArray(res.status_message, true);
                }

                // Hide the delete button and disable it
                deleteButton.hide().prop('disabled', true);

                // Show the create button and enable it
                createButton.show().prop('disabled', false);
            },
            complete: function(){
                $.unblockUI();
            },
            error: function(res) {
                // If there are any status messages, show them
                if(res && res.status_message){
                    showAlertsFromArray(res.status_message);
                }
            }
        });
    });

    /**************** End get AI document delete **********/
});

function rebuild_filters()
{
    $.ajax({
        type: "GET",
        url: BASE_URL + "/api/governance/rebuild_control_filters?control_framework=" + $("#filter_by_control_framework").val(),
        success: function(result){
            var data = result.data;
            rebuild_filter($("#filter_by_control_class"),data.classList);
            rebuild_filter($("#filter_by_control_phase"),data.phaseList);
            rebuild_filter($("#filter_by_control_family"),data.familyList);
            rebuild_filter($("#filter_by_control_owner"),data.ownerList);
            rebuild_filter($("#filter_by_control_priority"),data.priorityList);
            controlDatatable.draw();
        }
    });
}

function rebuild_filter(obj,new_options){
    var unselected_classes = [];
    $(obj).find('option').not(':selected').each(function(k, v){
        unselected_classes.push(parseInt(v.value));
    });
    
//    var selected_classes = $(obj).val();
    var unassigned_label = $("#unassigned_label").val();
    $(obj).find("option").remove();
    if(unselected_classes.indexOf(-1) >= 0){
        var $option = $("<option/>", {
            value: "-1",
            text: unassigned_label,
        });
    } else {
        var $option = $("<option/>", {
            value: "-1",
            text: unassigned_label,
            selected: true,
        });
    }
    $(obj).append($option);
    $.each(new_options, function(key, item) {
        if(unselected_classes.indexOf(item.value) >= 0){
            var $option = $("<option/>", {
                value: item.value,
                text: item.name,
            });
        } else {
            var $option = $("<option/>", {
                value: item.value,
                text: item.name,
                selected: true,
            });
        }
        $(obj).append($option);
    });
    $(obj).multiselect('rebuild');
    return true;
}

// Redraw Framework Controls table
function redrawFrameworkControl() {
    $("#active-controls").DataTable().draw();
}    

$(function(){

    // the variable which is used for preventing the form from double submitting
    var loading = false;

    // Add control form event
    $(document).on('submit', '#add-control-form', function(event) {
		event.preventDefault();

        // if not received ajax response, don't submit again
        if (loading) {
            return
        }
        // Check empty/trimmed empty valiation for the required fields 
        if (!checkAndSetValidation(this)) {
            return;
        }
        
        var form = new FormData($("#add-control-form")[0]);

        // the ajax request is sent
        loading = true;

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/governance/add_control",
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(result){
                if(result.status_message){
                    showAlertsFromArray(result.status_message);
                }
                var control_frameworks = $("#filter_by_control_framework").val();
                $("#add-control-form [name*=map_framework_id]").each(function(index){
                    var framework_id = $(this).val();
                    if(control_frameworks.indexOf(framework_id) === -1){
                        var framework_name = $(this).find("option:selected").text();
                        $('#filter_by_control_framework').append($('<option>').val(framework_id).text(framework_name).attr("selected",true));
                    }
                });
                $("#filter_by_control_framework").multiselect("rebuild");
                $("#add-control-form")[0].reset();
                $('#add-control-form [name="control_type[]"]').multiselect('deselectAll', false);
                $('#add-control-form [name="control_type[]"]').multiselect('select', [1]);
                $('#add-control-form [name="control_type[]"]').multiselect('refresh');
                $('#control--add').modal('hide');
                $('#add-control-form [name*="custom_field"]').val("");
                $('#add-control-form [name*="custom_field"].multiselect').multiselect('refresh');
                controlDatatable.ajax.reload(null, false);

                // the response is received
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

            // the response is received
            loading = false;
        });
        return false;
    });
    // Update control form event
    $(document).on('submit', '#update-control-form', function(event) {
		event.preventDefault();

        // if not received ajax response, don't submit again
        if (loading) {
            return
        }

        // Check empty/trimmed empty valiation for the required fields 
        if (!checkAndSetValidation(this)) {
            return;
        }

        var form = new FormData($("#update-control-form")[0]);
        
        // the ajax request is sent
        loading = true;

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/governance/update_control",
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(result){
                if(result.status_message){
                    showAlertsFromArray(result.status_message);
                }

                $('#control--update').modal('hide');
                controlDatatable.ajax.reload(null, false);

                // the response is received
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

            // the response is received
            loading = false;

        });
        
        return false;
    });

    // Add framework form event
    $(document).on('submit', '#framework-create-form', function(event) {

		event.preventDefault();

        // Check empty/trimmed empty valiation for the required fields 
        if (!checkAndSetValidation(this)) {
            return;
        }

        // Add the submit button's name and value as a hidden field 
        // since the name and value of the submit button aren't included in the form data when triggering the submit event programmatically
        const hiddenInput = $('<input>')
            .attr('type', 'hidden')
            .attr('name', 'add_framework') // The submit button's name
            .attr('value', 'true'); // The submit button's value

        $(this).append(hiddenInput);

        // Submit the form using the native Javascript submit method not using jQuery submit method 
        // to prevent the form from submitting infinitely
        this.submit();
    });

    // Add framework form event
    $(document).on('submit', '#framework-update-form', function(event) {

		event.preventDefault();

        // Check empty/trimmed empty valiation for the required fields 
        if (!checkAndSetValidation(this)) {
            return;
        }

        // Add the submit button's name and value as a hidden field 
        // since the name and value of the submit button aren't included in the form data when triggering the submit event programmatically
        const hiddenInput = $('<input>')
            .attr('type', 'hidden')
            .attr('name', 'update_framework') // The submit button's name
            .attr('value', 'true'); // The submit button's value

        $(this).append(hiddenInput);

        // Submit the form using the native Javascript submit method not using jQuery submit method 
        // to prevent the form from submitting infinitely
        this.submit();
    });

    // timer identifier
    var typingTimer;                
    // time in ms (5 seconds)
    var doneTypingInterval = 1000;  

    // Search filter event
    $('#filter_by_control_text').keyup(function(){
        clearTimeout(typingTimer);
        typingTimer = setTimeout(redrawFrameworkControl, doneTypingInterval);
    });

    
});
//Function to give some margin to the text-spans in the collapsable column to
//force a reflow in case a text is overflowing
function fixTreeGridCollapsableColumn() {
    $(".datagrid .datagrid-row>td:first-child>div").each(function() {
        if ($(this)[0].scrollWidth >  $(this).innerWidth()) {
            var indentCount = $(this).find('.tree-indent, .tree-hit').length;
            $(this).find('.tree-title').css('margin-right', (indentCount * 7) + 'px');
        };
    });
}

function setupAssetsAssetGroupsWidget(select_tag, control_id, control_maturity) {

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
                url: BASE_URL + '/api/asset-group/options_by_control' ,
                data: { 
                        "control_id": control_id, 
                        "control_maturity": control_maturity, 
                    },
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






