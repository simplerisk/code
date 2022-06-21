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
            var modal = $('#document-delete-modal');
            $('.document_id', modal).val(document_id);
            $('.version', modal).val(version);
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

                    $('[name*="custom_field"]', modal).val("");
                    if(control.custom_values){
                      var custom_values = control.custom_values;
                      for (var i=0; i<custom_values.length; i++) {
                        var field_id = custom_values[i].field_id;
                        var field_value = custom_values[i].value;
                        $("[name='custom_field["+field_id+"]']", modal).val(field_value);
                        $('[name*="custom_field"].multiselect', modal).multiselect('select', field_value.split(','));
                        $('[name*="custom_field"].multiselect', modal).multiselect('refresh');
                      }
                    }

                    $(modal).modal('show');
                }
            });
          });
          
          $(document).on('click', '.control-block--clone', function(event) {
            event.preventDefault();
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
                        var field_id = custom_values[i].field_id;
                        var field_value = custom_values[i].value;
                        $("[name='custom_field["+field_id+"]']", modal).val(field_value);
                        $('[name*="custom_field"].multiselect', modal).multiselect('select', field_value.split(','));
                        $('[name*="custom_field"].multiselect', modal).multiselect('refresh');
                      }
                    }

                    $(modal).modal('show');
                    $(".mapping_framework_table tbody", modal).html(data.mapped_frameworks)
                }
            });
          });
          $(document).on('click', '.control-block--add-mapping', function(event) {
            event.preventDefault();
            var form = $(this).closest('form');
            // To get the html of the <tr> tag
            $(".mapping_framework_table tbody", form).append($("#add_mapping_row table tr:first-child").parent().html());
          });
          $(document).on('click', '.control-block--delete-mapping', function(event) {
            event.preventDefault();
            $(this).closest("tr").remove();
          });
          $('#control--add').on('shown.bs.modal', function () {
              $(".mapping_framework_table tbody", this).html("");
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
        }
    };
       

    controlObject.init();
  
    // Initiate Datatable of controls
    var pageLength = 10;
    controlDatatable = $("#active-controls").DataTable({
        scrollX: true,
        bFilter: false,
        bLengthChange: false,
        processing: true,
        serverSide: true,
        bSort: true,
        pagingType: "full_numbers",
        dom : "flrtip",
        pageLength: pageLength,
        dom : "flrti<'#view-all.view-all'>p",
        ajax: {
            url: BASE_URL + '/api/datatable/framework_controls',
            type: "POST",
            data: function(d){
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
            error: function(xhr,status,error){
                retryCSRF(xhr, this);
            },
            complete: function(response){
                if(response.status == 200){
                    $("#controls_count").html("("+ response.responseJSON.recordsTotal +")");
                    //rebuild_filter($("#filter_by_control_class"),response.responseJSON.classList);
                    //rebuild_filter($("#filter_by_control_phase"),response.responseJSON.phaseList);
                    //rebuild_filter($("#filter_by_control_family"),response.responseJSON.familyList);
                    //rebuild_filter($("#filter_by_control_owner"),response.responseJSON.ownerList);
                    //rebuild_filter($("#filter_by_control_priority"),response.responseJSON.priorityList);
                }
            }
        }
    });

    // Add paginate options
    controlDatatable.on('draw', function(e, settings){
        $('.paginate_button.first').html('<i class="fa fa-chevron-left"></i><i class="fa fa-chevron-left"></i>');
        $('.paginate_button.previous').html('<i class="fa fa-chevron-left"></i>');

        $('.paginate_button.last').html('<i class="fa fa-chevron-right"></i><i class="fa fa-chevron-right"></i>');
        $('.paginate_button.next').html('<i class="fa fa-chevron-right"></i>');
    });

    // Add all text to View All button on bottom
    $('.view-all').html("All");

    // View All
    $(".view-all").click(function(){
        var oSettings =  controlDatatable.settings();
        oSettings[0]._iDisplayLength = -1;
        controlDatatable.draw()
        $(this).addClass("current");
    })
    
    // Page event
    $("body").on("click", "span > .paginate_button", function(){
        var index = $(this).attr('aria-controls').replace("DataTables_Table_", "");

        var oSettings =  controlDatatable.settings();
        if(oSettings[0]._iDisplayLength == -1){
            $(this).parents(".dataTables_wrapper").find('.view-all').removeClass('current');
            oSettings[0]._iDisplayLength = pageLength;
            controlDatatable.draw()
        }
        
    })
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
        unselected_classes.push(v.value);
    });
    
//    var selected_classes = $(obj).val();
    var unassigned_label = $("#unassigned_label").val();
    $(obj).find("option").remove();
    if(unselected_classes.indexOf("-1") >= 0){
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

function getProjects(){
  return {

    "active" :  [

      { "id" : "1", "name" : "Lorem ipsum dolor sit amet, consectetur adipisicing elit. Nesciunt, autem!" },
      { "id" : "2", "name" : "Lorem ipsum dolor sit amet."},
      { "id" : "3", "name" : "Lorem ipsum dolor sit amet, consectetur adipisicing." },
      { "id" : "4", "name" : "Lorem ipsum dolor sit amet, consectetur adipisicing elit. Id quia illum inventore, aliquid. Ducimus, blanditiis alias, vel cumque recusandae dicta!"}

    ],
    "inactive" : [],
    "on-hold": [{ "id" : "5", "name" : "Lorem ipsum dolor sit amet, consectetur." }],
    "cancled":[]

  };
}

// Redraw Framework Controls table
function redrawFrameworkControl() {
    $("#active-controls").DataTable().draw();
}    


$(document).ready(function(){
    $('.container-fluid').delegate('.tab-show', 'click', function(){        
        $('.form-tab').removeClass('selected');
        $(this).addClass('selected');
        $('.tab-data').hide();
        $($(this).data('content')).show();
        $(".framework-table").treegrid('resize');
        document.location.hash = $(this).data('content').replace("-content", "");
    });
    
    // Add control form event
    $("#add-control-form").submit(function(){
        var form = new FormData($(this)[0]);
        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/governance/add_control",
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(result){
                var data = result.data;
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
        return false;
    });
    // Update control form event
    $("#update-control-form").submit(function(){
        var form = new FormData($(this)[0]);
        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/governance/update_control",
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success: function(result){
                var data = result.data;
                if(result.status_message){
                    showAlertsFromArray(result.status_message);
                }
                // var control_frameworks = $("#filter_by_control_framework").val();
                // $("#update-control-form [name*=map_framework_id]").each(function(index){
                //     var framework_id = $(this).val();
                //     if(control_frameworks.indexOf(framework_id) === -1){
                //         $("#filter_by_control_framework option[value="+framework_id+"]").prop("selected", true);
                //     }
                // });
                // $("#filter_by_control_framework").multiselect("rebuild");
                $('#control--update').modal('hide');
                controlDatatable.ajax.reload(null, false);
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
