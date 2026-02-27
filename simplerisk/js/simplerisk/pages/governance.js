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

                // Set custom placeholders
                const filterRow = $('.datagrid-filter-row');

                filterRow.find('input[name="document_name"]').attr('placeholder', _lang['DocumentName']);
                filterRow.find('input[name="document_type"]').attr('placeholder', _lang['DocumentType']);
                filterRow.find('input[name="framework_names"]').attr('placeholder', _lang['ControlFrameworks']);
                filterRow.find('input[name="control_names"]').attr('placeholder', _lang['Controls']);
                filterRow.find('input[name="creation_date"]').attr('placeholder', _lang['CreationDate']);
                filterRow.find('input[name="approval_date"]').attr('placeholder', _lang['ApprovalDate']);
                filterRow.find('input[name="status"]').attr('placeholder', _lang['Status']);

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

        let _this = this;
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
            remoteFilter: true,
            onResize: function() {
                // After rendering the datagrid filter head row, reduce the editable filter inputs' width by 30px
                // so that could make the datagrid table filter head row have the same width as the datagrid table body
                $('.datagrid-htable .datagrid-filter-row .datagrid-filter', this).each((i, e) => {
                    $(e).css('width', (parseInt($(e).css('width'))-30) + 'px');
                });
            },
            loadFilter: function(data, parentId) {
                return data.data
            },
            onLoadSuccess: function(row, data){
                // Run the resize logic when the data is loaded
                $(_this).treegrid('resize');

                // fixTreeGridCollapsableColumn();

                // Set custom placeholders
                const filterRow = $('.datagrid-filter-row');

                filterRow.find('input[name="name"]').attr('placeholder', _lang['ExceptionName']);
                filterRow.find('input[name="exception_id"]').attr('placeholder', _lang['ID']);
                filterRow.find('input[name="description"]').attr('placeholder', _lang['Description']);
                filterRow.find('input[name="justification"]').attr('placeholder', _lang['Justification']);
                filterRow.find('input[name="next_review_date"]').attr('placeholder', _lang['NextReviewDate']);

                // Set the max length of the text inputs in the filter row
                filterRow.find('input[name="name"]').attr('maxlength', 100);
                filterRow.find('input[name="exception_id"]').attr('maxlength', 100);
                filterRow.find('input[name="description"]').attr('maxlength', 100);
                filterRow.find('input[name="justification"]').attr('maxlength', 100);
                filterRow.find('input[name="next_review_date"]').attr('maxlength', 100);

                // Refresh exception counts in the tabs
                var totalCount = 0;
                data = Array.isArray(data) ? data : data.rows;
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
        }).treegrid('enableFilter', [
            {
                field: 'status',
                type: 'select',
                options: {
                    name: 'status',
                    url: BASE_URL + '/api/v2/exceptions/status',
                    defaultOption: {value: '', name: _lang['All']},
                    onChange: function(value){
                        if (value == '') {
                            _this.treegrid('removeFilterRule', 'status_value');
                        } else {
                            _this.treegrid('addFilterRule', {
                                field: 'status_value',
                                op: 'equal',
                                value: value
                            });
                        }
                        _this.treegrid('doFilter');
                    }
                }
            },
            {
                field:'actions',
                type:'label'
            }
        ]);

        $(this).data('initialized', true);
    }
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

            // Event handler when clicking 
            // the edit framework button on Governance > Define Framework Controls page
            // and the framework name link on the Compliance > Initiate Audits page
            $("body").on("click", ".framework-block--edit, .framework-name", function() {

                event.preventDefault();
                resetForm('#framework--update form');
                var framework_id = $(this).data("id");

                $.ajax({
                    url: BASE_URL + '/api/governance/framework?framework_id=' + framework_id,
                    type: 'GET',
                    success : function (res){
                        var data = res.data;
                        $.ajax({
                            url: BASE_URL + '/api/governance/selected_parent_frameworks_dropdown?child_id=' + framework_id,
                            type: 'GET',
                            success : function (res){
                                $("#framework--update .parent_frameworks_container").html(res.data.html)
                            }
                        });
                        $("#framework--update [name=framework_id]").val(framework_id);
                        $("#framework--update [name=framework_name]").val(data.framework.name);
                        $("#framework--update [name=framework_description]").val(data.framework.description);
        
                        setEditorContent("update_framework_description", data.framework.description);
        
                        if(data.framework.custom_values){
                            var custom_values = data.framework.custom_values;
                            for (var i=0; i<custom_values.length; i++) {
                                var field_value = custom_values[i].value;
                                var element = $("#framework--update [name^='custom_field[" + custom_values[i].field_id + "]']");
                                if (field_value && custom_values[i].field_type == 'multidropdown' || custom_values[i].field_type == 'user_multidropdown') {
                                    element.multiselect('select', field_value);
                                } else {
                                    element.val(field_value ? field_value : '');
                                }
                            }
                        }

                        $("#framework--update").modal("show");

                    }
                });
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

          // Event handler when clicking
          // the edit control button on Governance > Define Framework Controls page and 
          // the control name link on the Compliance > Initiate Audits page
          $(document).on('click', '.control-block--edit, .control-name', function(event) {

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

                    setEditorContent("update_control_description", control.description);
                    setEditorContent("update_supplemental_guidance", control.supplemental_guidance);

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

                    setEditorContent("add_control_description", control.description);
                    setEditorContent("add_supplemental_guidance", control.supplemental_guidance);

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
              $("[name*=map_framework_id]").each(function(index){
                if(this != cur_select && $(this).val() == $(cur_select).val()){
                    $(cur_select).find("option:eq(0)").prop('selected', true);
                    showAlertFromMessage(_lang['ExistingMappings'], false);
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
              $("[name*=asset_maturity]", form).each(function(index){
                if(this != cur_select && $(this).val() == $(cur_select).val()){
                    $(cur_select).find("option:eq(0)").prop('selected', true);
                    showAlertFromMessage(_lang['ExistingMappings'], false);
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

    /**************** Start AI document create **********/
    $('body').on('click', '.ai-document--create', function(e){
        e.preventDefault();

        var createButton = $(this);
        var row = createButton.closest('tr');
        var deleteButton = row.find('.ai-document--delete');
        var queuedButton = row.find('.ai-document--queued');

        var document_type = createButton.attr('data-type');
        var document_name = createButton.attr('data-name');

        $.blockUI({
            message: '<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>',
            baseZ: '10001'
        });

        $.ajax({
            url: BASE_URL + '/api/v2/ai/document/create?document_type=' + document_type + '&document_name=' + document_name,
            type: 'GET',
            dataType: 'json',
            complete: function() {
                $.unblockUI();
            },
            success: function(res, textStatus, xhr) {
                var statusCode = xhr.status;

                if (statusCode === 202 || statusCode === 409) {
                    // Show Queued button
                    createButton.hide();
                    queuedButton.show().prop('disabled', true);

                    deleteButton.hide().prop('disabled', true);
                } else {
                    // Unexpected success, leave Install enabled
                    createButton.val('Install').prop('disabled', false).show();
                }

                if(res && res.status_message){
                    showAlertsFromArray(res.status_message, true);
                }
            },
            error: function(xhr) {
                createButton.val('Install').prop('disabled', false).show();

                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
    });
    /**************** End AI document create **********/

    /**************** Start AI document delete **********/
    $('body').on('click', '.ai-document--delete', function(e){
        e.preventDefault();

        var deleteButton = $(this);
        var row = deleteButton.closest('tr');
        var createButton = row.find('.ai-document--create');
        var queuedButton = row.find('.ai-document--queued');

        var document_id = deleteButton.attr('data-id');

        $.blockUI({
            message: '<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>',
            baseZ: '10001'
        });

        $.ajax({
            type: "DELETE",
            url: BASE_URL + "/api/v2/governance/documents?document_id=" + document_id,
            dataType: 'json',
            success: function(res){
                if(res && res.status_message){
                    showAlertsFromArray(res.status_message, true);
                }

                // Hide Delete and Queued, show Install
                deleteButton.hide();
                queuedButton.hide();
                createButton
                    .val('Install')
                    .prop('disabled', false)
                    .removeClass('hidden')
                    .show();
            },
            complete: function(){
                $.unblockUI();
            },
            error: function(res){
                if(res && res.status_message){
                    showAlertsFromArray(res.status_message);
                }
            }
        });
    });
    /**************** End AI document delete **********/

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
    $(obj).find("option").remove();
    if(unselected_classes.indexOf(-1) >= 0){
        var $option = $("<option/>", {
            value: "-1",
            text: _lang['Unassigned'],
        });
    } else {
        var $option = $("<option/>", {
            value: "-1",
            text: _lang['Unassigned'],
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
    // This can be used for the following pages
    // the Governance > Define Framework Controls page and 
    // the Compliance > Initiate Audits page
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

        // Variable for indicating where the update control form is submitted from
        // It can be either the Governance > Define Framework Controls page or the Compliance > Initiate Audits page
        // The value of the variable is used to determine whether to redraw the controls table or refresh the total page
        // If the page is the Governance > Define Framework Controls page, the value is "governance"
        // If the page is the Compliance > Initiate Audits page, the value is "audit_initiation"
        let where = $('[name=where]', this).val();

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

                // If the page is the Governance > Define Framework Controls page, we need to redraw the controls table
                if (where == 'governance') {
                    controlDatatable.ajax.reload(null, false);
                }

                // the response is received
                loading = false;

                // If the page is the Compliance > Initiate Audits page, we need to refresh the total page
                if (where == 'audit_initiation') {
                    location.reload();
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
    $(document).on('submit', '#update-framework-form', function(event) {

        event.preventDefault();

        // if not received ajax response, don't submit again
        if (loading) {
            return
        }

        // Check empty/trimmed empty valiation for the required fields 
        if (!checkAndSetValidation(this)) {
            return;
        }

        // Variable for indicating where the update control form is submitted from
        // It can be either the Governance > Define Framework Controls page or the Compliance > Initiate Audits page
        // The value of the variable is not used yet but it would be better to left it
        // If the page is the Governance > Define Framework Controls page, the value is "governance"
        // If the page is the Compliance > Initiate Audits page, the value is "audit_initiation"
        let where = $('[name=where]', this).val();

        var form = new FormData($("#update-framework-form")[0]);
        
        // the ajax request is sent
        loading = true;

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/governance/update_framework",
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(result){
                if(result.status_message){
                    showAlertsFromArray(result.status_message);
                }

                $('#framework--update').modal('hide');

                // the response is received
                loading = false;

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

            // the response is received
            loading = false;

        });
        
        return false;

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

/*****************************************************
 * Lazy-load mapped control frameworks (collapse view)
 *****************************************************/
(function ($) {

    const loadedFrameworks = {};

    function loadMappedFrameworks(controlId, $collapse) {
        if (!controlId || loadedFrameworks[controlId]) return;
        loadedFrameworks[controlId] = true;

        const $table = $collapse.find('table');
        const $placeholder = $collapse.find('.loading-placeholder');

        $.ajax({
            url: BASE_URL + '/api/v2/governance/controls/mapped-frameworks',
            type: 'GET',
            dataType: 'json',
            data: { control_id: controlId },
            success: function (res) {
                $placeholder.remove(); // remove loading text
                $table.removeClass('d-none');

                const $tbody = $table.find('tbody');
                $tbody.empty();

                if (!res || !Array.isArray(res.data) || !res.data.length) {
                    $tbody.append('<tr><td colspan="3" class="text-center text-muted">No mapped frameworks found.</td></tr>');
                    return;
                }

                // --- Add search input above table ---
                let $searchWrapper = $collapse.find('.table-search-wrapper');
                if (!$searchWrapper.length) {
                    $searchWrapper = $(`
                        <div class="mb-2 table-search-wrapper">
                            <input type="text" class="form-control form-control-sm mapped-framework-search" placeholder="Search frameworks or controls...">
                        </div>
                    `);
                    $table.before($searchWrapper);
                }
                $searchWrapper.removeClass('d-none');

                // Group by framework
                const grouped = {};
                res.data.forEach(row => {
                    const name = row.framework_name || 'Unknown Framework';
                    if (!grouped[name]) grouped[name] = [];
                    grouped[name].push(row);
                });

                for (const frameworkName in grouped) {
                    const rows = grouped[frameworkName];
                    // Framework header row
                    const $headerRow = $(`
                        <tr class="fw-bold table-primary framework-header">
                            <td colspan="3">${frameworkName}</td>
                        </tr>
                    `);
                    $tbody.append($headerRow);

                    rows.forEach(row => {
                        const $dataRow = $(`
                            <tr class="framework-row">
                                <td>${row.framework_name || ''}</td>
                                <td>${row.reference_name || ''}</td>
                                <td>${row.reference_text || ''}</td>
                            </tr>
                        `);
                        $tbody.append($dataRow);
                    });
                }

                // --- Search/filter logic ---
                $collapse.find('.mapped-framework-search').off('keyup').on('keyup', function () {
                    const query = $(this).val().toLowerCase();

                    // Track which frameworks have at least one visible row
                    const frameworkVisible = {};

                    $tbody.find('tr').each(function () {
                        const $tr = $(this);

                        if ($tr.hasClass('framework-header')) {
                            const frameworkName = $tr.text();
                            frameworkVisible[frameworkName] = false; // reset
                            return;
                        }

                        const $header = $tr.prevAll('.framework-header:first');
                        const frameworkName = $header.text();

                        const text = $tr.text().toLowerCase();
                        const match = text.includes(query);
                        $tr.toggle(match);

                        if (match) frameworkVisible[frameworkName] = true;
                    });

                    // Show/hide framework headers based on if they have visible rows
                    $tbody.find('.framework-header').each(function () {
                        const $header = $(this);
                        const frameworkName = $header.text();
                        $header.toggle(frameworkVisible[frameworkName]);
                    });
                });
            },
            error: function () {
                $placeholder.text('Failed to load mapped frameworks.');
                loadedFrameworks[controlId] = false; // allow retry
            }
        });
    }

    // Listen for collapse shown events
    $(document).on('shown.bs.collapse', '[id^="mapped-frameworks-collapse-"]', function () {
        const $collapse = $(this);
        const controlId = $collapse.prev().data('control-id'); // get from header div
        loadMappedFrameworks(controlId, $collapse);
    });

    // Smooth rotating caret
    $(document).on('show.bs.collapse', '[id^="mapped-frameworks-collapse-"]', function () {
        const $header = $(this).prev();
        $header.find('.collapse-caret').addClass('rotate');
    });

    $(document).on('hide.bs.collapse', '[id^="mapped-frameworks-collapse-"]', function () {
        const $header = $(this).prev();
        $header.find('.collapse-caret').removeClass('rotate');
    });

})(jQuery);