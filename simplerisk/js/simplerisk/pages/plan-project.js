var planProject = {

    risks : [],

    init : function(){
          this.bindEvents();
    },

    refreshRiskCounts: function() {
        $('div.tab-pane.active div.project-block').each(function() {
            $('.project-block--header span.risk-count', $(this)).html(Number($('.risk', $(this)).length));
        });
    },

    refreshProjectPriorities: function() {
        $('div.tab-pane.active div:not(#no-sort).project-block').each(function(index) {
            $('.project-block--header div:first-child', $(this)).html(index + 1);
        });
    },

    refreshProjectCounts: function() {
        $('div.plan-projects nav.nav-tabs a.nav-link').each(function() {
            let $this = $(this);
            $('span.project-count', $this).html(Number($('div:not(#no-sort).project-block', $this.attr('data-bs-target')).length));
        });
    },

    bindEvents : function(){

        var self = this;

        $(document).on('click', '.view--risks', function(event) {
            event.preventDefault();
            $(this).parents('.project-block').find('.risks').fadeToggle('400');
            $(this).toggleClass('showing');
            $(this).hasClass('showing') ? $(this).text('Hide Risk') : $(this).text('View Risk') ;
        });

        $(document).on('click', '.project-block--delete', function(e) {e.stopPropagation(); confirm(_lang['AreYouSureYouWantToDeleteThisProject'], () => {
            let $this = $(this);
            var project_id = $this.closest('div.project-block--header').attr('data-project');

            $.ajax({
                url: BASE_URL + '/api/management/project/delete',
                type: 'POST',
                data: {delete_project: true, project_id : project_id},
                success : function (data){
                    showAlertsFromArray(data.status_message);

                    let deleted_project_block = $this.closest('div.project-block');
                    let unassigned_risks = $this.closest('div.tab-pane').find('#no-sort div.risks');

                    $('div.risks div.risk', deleted_project_block).each(function() {
                        $(this).detach().appendTo(unassigned_risks).attr('data-project', 0);
                    });

                    deleted_project_block.remove();

                    self.refreshProjectPriorities();
                    self.refreshRiskCounts();

                    // need to reload to reset project counts in the tab header
                    location.reload();
                },
                error: function(xhr,status,error){
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            });
        })});

        if(is_draggable > 0) {
        
            //Bind the dropable status tabs.
            self.droppableStatus();
        
            //Bind the draggable option for the risks
            self.draggableRisks();

            //Bind the droppable elements.
            self.droppableProjects();

            //Bind the sortable elements.
            self.sortableProjects();
        }
    },

    sortableProjects : function(){
        var self = this;
        $( ".sortable" ).sortable({
            items: 'div.project-block[id!=no-sort]',
            cursor: "move",
            handle: 'div.project-block--header > div:first-child',
            connectWith: 'nav.nav-tabs a.status',
            cursorAt: { top: 1, left: 1 },
            revert: "invalid",
            update : function(event, ui) {
                var order = []; 
                $('.sortable .project-block').each(function(){
                  var val = $('.project-block--header',this).attr('data-project');
                  order.push(val)
                });

                $.ajax({
                    url: BASE_URL + '/api/management/project/update_order',
                    type: 'POST',
                    data: {update_order: true, project_ids : order},
                    success : function (data){
                        showAlertsFromArray(data.status_message);
                        self.refreshProjectPriorities();
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this)){}
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                            setTimeout(function(){
                                location.reload();
                            }, 1500);
                        }
                    }
                });
            }
        }).disableSelection();
    },
    droppableProjects : function(){
        var self = this;

        $('.project-block').droppable({
            accept: function(e) {
                var source_project_id = e.attr('data-project');
                var target_project_id = $('.project-block--header', $(this)).attr('data-project');

                // Dropping it on the project it's currently in makes no sense
                return source_project_id !== target_project_id && e[0].nodeName.toUpperCase() === 'DIV' && e.hasClass('risk');
            },
            classes: {
                "ui-droppable-hover": "highlight",
            },
            tolerance: 'pointer',
            drop: function(event, ui){
                let $this = $(this);

                ui.draggable.fadeOut('400').delay('500').remove();

                var risk_id = $(ui.draggable[0]).attr('data-risk');
                var target_project_id = $('.project-block--header', this).attr('data-project');

                $.ajax({
                    url: BASE_URL + '/api/management/project/update',
                    type: 'POST',
                    data: {update_projects: true, risk_id : risk_id, project_id : target_project_id},
                    success : function (data){
                        showAlertsFromArray(data.status_message);
                        $this.find('.risks').append($(ui.helper).clone().css('top', '').css('left', '').css('opacity', '').attr('data-project', target_project_id));

                        self.draggableRisks();
                        self.refreshRiskCounts();
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this)){}
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                            setTimeout(function(){
                                location.reload();
                            }, 1500);
                        }
                    }
                });
            }
        });
    },

    draggableRisks : function(){
        $('.project-block-unassined .risk, .project-block .risk').draggable({
            revert: "invalid",
            handle: 'span.grippy',
        });
    },

    droppableStatus : function(){
        var self = this;
        $( "nav.nav-tabs a.status" ).droppable({
            accept: function(e) {
                // The currently active tab doesn't accept drops and even then, only 'div' tags with the correct class
                return !$(this).hasClass('active') && e[0].nodeName.toUpperCase() === 'DIV' && e.hasClass('project-block');
            },
            classes: {
                "ui-droppable-hover": "highlight",
            },
            tolerance: 'pointer',
            drop: function(event, ui){

                //Hide the droped elementa and remove it.
                ui.draggable.hide().remove();

                var project_id = $(ui.draggable.html()).attr('data-project');
                var status = $(this).attr('data-status');

                $.ajax({
                    url: BASE_URL + '/api/management/project/update_status',
                    type: 'POST',
                    data: {update_project_status: true, project_id : project_id, status:status},
                    success : function (data){
                        showAlertsFromArray(data.status_message);
                        setTimeout(function(){
                            location.reload();
                        }, 150)
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this)){}
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                            setTimeout(function(){
                                location.reload();
                            }, 1500);
                        }
                    }
                });
            }
        });
    },
};





$(function() {
    $('#project-new').on('submit', function(event) {
        event.preventDefault();

        let form_element = this;
        let trim_flag = false;

        // check if the trimmed value is empty for the required fields
        $(form_element).find("[required]").each(function() {
            // Remove the spaces from the begining and the end from the input value.
            let trimmed_value = $(this).val().trim();

            // Update the required field with the trimmed value
            $(this).val(trimmed_value);

            // Check if the trimmed value is empty
            if (!trimmed_value) {
                toastr.error(_lang["ThereAreRequiredFields"]);
                trim_flag = true;
                return;
            }
        });

        // if there are empty required field
        if (trim_flag) {
            return;
        }

        var form = new FormData($(this)[0]);
        $.ajax({
            url: BASE_URL + '/api/management/project/add',
            type: "POST",
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,

            success : function (data){
                showAlertsFromArray(data.status_message);
                $("#project-new")[0].reset();
                setTimeout(function(){
                    location.reload();
                }, 1500)
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
        $("#project--add").modal('hide');
        return false;
    });

    $('#project-edit').on('submit', function(event) {
        event.preventDefault();
        
        let form_element = this;
        let trim_flag = false;

        // check if the trimmed value is empty for the required fields
        $(form_element).find("[required]").each(function() {
            // Remove the spaces from the begining and the end from the input value.
            let trimmed_value = $(this).val().trim();

            // Update the required field with the trimmed value
            $(this).val(trimmed_value);

            // Check if the trimmed value is empty
            if (!trimmed_value) {
                toastr.error(_lang["ThereAreRequiredFields"]);
                trim_flag = true;
                return;
            }
        });

        // if there are empty required field
        if (trim_flag) {
            return;
        }

        var form = new FormData($(this)[0]);
        $.ajax({
            url: BASE_URL + '/api/management/project/edit',
            type: "POST",
            data: form,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            success : function (data){
                showAlertsFromArray(data.status_message);
                $("#project-edit")[0].reset();
                setTimeout(function(){
                    location.reload();
                }, 1500)
            },
            error: function(xhr,status,error){
                if(xhr.responseJSON && xhr.responseJSON.status_message){
                    showAlertsFromArray(xhr.responseJSON.status_message);
                }
            }
        });
        $("#project--edit").modal('hide');
        return false;
    });

    $(document).on('click', '.project-block--add', function() {
        
        // Clear all the input elements
        resetForm('#project--add form');

        // Open a add project modal
        $("#project--add").modal("show");

    });

    $(document).on('click', '.project-block--edit', function(event) {
        event.preventDefault();
        resetForm('#project--edit form');

        var project_id = $(this).attr('data-id');
        $.ajax({
            url: BASE_URL + '/api/management/project/detail?project_id=' + project_id,
            type: 'GET',
            success : function (res){
                var project = res.data;
                $("#project--edit [name=project_id]").val(project_id);
                $("#project--edit [name=name]").val(project.name);
                $("#project--edit [name=due_date]").val(project.due_date);
                
                // Show 'Unassigned' if the value of the following field is not assigned to the project
                $("#project--edit [name=consultant]").val(project.consultant || '');
                $("#project--edit [name=business_owner]").val(project.business_owner || '');
                $("#project--edit [name=data_classification]").val(project.data_classification || '');
                if(project.custom_values){
                    var custom_values = project.custom_values;
                    for (var i=0; i<custom_values.length; i++) {
                        var field_value = custom_values[i].value;
                        var element = $("#project--edit [name^='custom_field[" + custom_values[i].field_id + "]']");
                        if (field_value && custom_values[i].field_type == 'multidropdown' || custom_values[i].field_type == 'user_multidropdown') {
                            element.multiselect('select', field_value);
                        } else {
                            element.val(field_value ? field_value : '');
                        }
                    }
                }
                $("#project--edit").modal("show");
            }
        });
    });

    planProject.init();
});


/********
 * Below are the functions I started working on to replace the current design. It would support a column selector that's not currently added to the projects table
 * Currently it's setup to use data from the risk catalog because I don't have an endpoint that returns the projects, but still in a phase of working out how the basics work,
 * so it doesn't matter what data we use for it.
 * 
 * For grouping things into separate tbody
 * https://stackoverflow.com/questions/56592636/jquery-datatable-drag-and-drop-with-row-grouping
 */
function init_projects_datatable(table_el) {   
    $(table_el).DataTable({
        ajax: BASE_URL + '/api/admin/risk_catalog/datatable',
        bSort: true,
        paging: false,
        ordering: false,
        columns: [
            {
                data: 'group_name',
                visible: false
            },
            {
                data: 'number',
                render: function(data, type, row, meta) {
                return '<span class="grippy"></span>' + data;
                }
            },
            {
                data: 'name'
            },
            {
                data: 'description'
            },
            {
                data: 'function_name'
            },
            {
                data: 'actions'
            },
        ],
        
        drawCallback: function (settings) {
            let api = this.api();
            let rows = api.rows({ page: 'current' }).nodes();
            if ($(rows).parent().is("tbody")) {
              $(rows).unwrap();
            }
            let last = null;
            let group_index = -1;
            api.column(0, { page: 'current' }).data().each(function (group, i) {
            // console.log(group, i);
              if (last !== group) {
                // make previous group sortable
                if (last) {
                  $(`${table_el} > tbody[data-group='${group_index}']`).sortable({
                    items: "tr.data-row",
                    // containment: `${table_el} > tbody[data-group='${group_index}']`,
                    handle: '.grippy',
                    opacity: 0.75
                  });
                }
                group_index++;
                // add group-header before new group
                $(rows).eq(i).before(
                    `<tbody data-group='${group_index}'><tr class='group-header'><td colspan="3">${group}</td><td data-bs-toggle='collapse' data-target="tr[data-group='${group_index}']">TOGGLE</td></tr></tbody>`
                );
                last = group;
              }
              // modify row and append to tbody
              $(rows).eq(i).attr('data-group', group_index).addClass('data-row collapse').appendTo("tbody[data-group='" + group_index + "']");
            });
            // make last group also sortable
            $(`${table_el} > tbody[data-project='${group_index}']`).sortable({
                  items: "tr.data-row",
                // containment: `${table_el} > tbody[data-project='${group_index}']`,
                  handle: '.grippy',
                  opacity: 0.75
            });
            // make the tbody-elements sortable and disable selection in table
            $(table_el).sortable({
                items: "tbody",
                placeholder: "tbody-placeholder",
                handle: '.group-header > td:first-child',
                forcePlaceholderSize: true,
                opacity: 0.75,
                cursor: 'grabbing',
                placeholder: 'sortable-placeholder',
                // handle: '.draghandle',
                connectWith: 'div.plan-projects a.nav-link',
                cursorAt: { top: 1, left: 1 },
                // forcePlaceholderSize: true,
                // helper: 'clone',

                
            }).disableSelection();
          }
    });

            
}
function init_project_tabs() {
    $("div.plan-projects a.nav-link").droppable({
        classes: {
            "ui-droppable-hover": "highlight",
        },
        tolerance: "pointer",
        accept: function(e) {
            // console.log(e, );
        
            // The currently active tab doesn't accept drops and even then, only 'tbody' tags
            return !$(this).hasClass('active') && e[0].nodeName.toUpperCase() === 'TBODY';
        },
        drop: function (e, ui) {
            // console.log('asdasdasd');
        }
    });

}
