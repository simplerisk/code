jQuery(document).ready(function($){
    var is_dragable = $("#is_dragable").val(); 
    $('#project-new').submit(function(event) {
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
    $('#project-edit').submit(function(event) {
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

    $(document).on('click', '.project-block--edit', function(event) {
        event.preventDefault();
        //$(this).parents('.project-block').fadeOut('400').delay('500').remove();
        var project_id = $(this).attr('data-id');
        $.ajax({
            url: BASE_URL + '/api/management/project/detail?project_id=' + project_id,
            type: 'GET',
            success : function (res){
                var project = res.data;
                $("#project--edit [name=project_id]").val(project_id);
                $("#project--edit [name=name]").val(project.name);
                $("#project--edit [name=due_date]").val(project.due_date);
                $("#project--edit [name=consultant]").val(project.consultant);
                $("#project--edit [name=business_owner]").val(project.business_owner);
                $("#project--edit [name=data_classification]").val(project.data_classification);
                if(project.custom_values){
                  var custom_values = project.custom_values;
                  for (var i=0; i<custom_values.length; i++) {
                    var field_id = custom_values[i].field_id;
                    var field_value = custom_values[i].value;
                    $("#project--edit [name='custom_field["+field_id+"]']").val(field_value);
                  }
                }
                $("#project--edit").modal();
            }
        });
    });

  var planProject = {

    //projects : getProjects(),
    risks : [],

    init : function(){
      // this.loadProjects();
      this.bindEvents();
    },

    bindEvents : function(){

      var self = this;

      $(document).on('click', '.view--risks', function(event) {
        event.preventDefault();
        $(this)
          .parents('.project-block')
          .find('.risks')
          .fadeToggle('400');

        $(this).toggleClass('showing');
        ($(this).hasClass('showing')) ? $(this).text('Hide Risk') : $(this).text('View Risk') ;
      });

      $(document).on('click', '.project-block--delete', function(event) {
        event.preventDefault();
        //$(this).parents('.project-block').fadeOut('400').delay('500').remove();
        var project_id = $(this).attr('data-id');
        var modal = $('#project--delete');
        $("[name='project_id']", modal).val(project_id);
        $(modal).modal('show');
      });

      $('#project-delete').submit(function (event){
        event.preventDefault();
        self.deleteProject(this);
      });

      if(is_dragable > 0) {
          //Bind the draggable option for the risks
          self.draggableRisks();

          //Bind the droppable elements.
          self.droppableProjects();

          //Bind the sortable elements.
          self.sortableProjects();

          //Bind the dropable status.
          self.droppableStatus();
      }

    },

    sortableProjects : function(){
        var self = this;
          $( ".sortable" ).sortable({
            items: 'div.project-block[id!=no-sort]',
            cursor: "move",
            start : function(event, ui) {            
                var start_pos = ui.item.index()+ 1;
                ui.item.data('start_pos', start_pos);
            },
            change : function(event, ui) {
                var start_pos = ui.item.data('start_pos');
                var index = ui.placeholder.index()+ 1;
                
                console.log(start_pos);
                console.log(index);
              
                if (start_pos < index) {
                    $('.sortable .project-block--priority:nth-child(' + index + ')').html(index-2);
                } else {
                    $('.sortable .project-block--priority:eq(' + (index + 1) + ')').html(index + 1);
                }
            },
            update : function(event, ui) {
                var index = ui.item.index()+ 1;
                $('.sortable .project-block--priority:nth-child(' + (index + 1) + ')').html(index);

                var order = []; 
                $('.sortable .project-block').each(function(){
                  var val = $('.project-block--header',this).attr('data-project');
                  order.push(val)
                })

                var project_id = $(ui.item.html()).attr('data-project');
                var priority = ui.item.index() + 1;
                //return false;

                $.ajax({
                    url: BASE_URL + '/api/management/project/update_order',
                    type: 'POST',
                    data: {update_order: true, project_ids : order},
                    success : function (data){
                        showAlertsFromArray(data.status_message);
                        setTimeout(function(){
                            location.reload();
                        }, 100)
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

          $( ".status" ).sortable({
              connectWith: ".sortable"
          });
    },


    droppableProjects : function(){

      var self = this;

      $('.project-block').droppable({
        accept : '.risk',
        drop: function(event, ui){
          ui.draggable.fadeOut('400').delay('500').remove();
          $(this).find('.risks').append('<div class="risk">'+ui.draggable.html()+'</div>');
          var risk_id = $(ui.draggable.html()).attr('data-risk');
          var project_id = $('.project-block--header', this).attr('data-project');

          $.ajax({
              url: BASE_URL + '/api/management/project/update',
              type: 'POST',
              data: {update_projects: true, risk_id : risk_id, project_id : project_id},
              success : function (data){
                  showAlertsFromArray(data.status_message);
                  setTimeout(function(){
                    location.reload();
                  }, 1500)
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

          //Bind the draggable option for the risks
          self.draggableRisks();
        }
      });
    },

    draggableRisks : function(){

      //If the risk is in a project block then its should not be cloned.
      $('.project-block-unassined .risk').draggable({
        revert: "invalid"
      });

      //If the risk is in a project block then its should not be cloned.
      $('.project-block .risk').draggable({
        revert: "invalid"
      });

    },
    droppableStatus : function(){
      var self = this;
      $( ".status" ).droppable({
        accept : '.project-block',
        hoverClass: "outline-state",
        tolerance: 'pointer',
        drop: function(event, ui){

          //Hide the droped elementa and remove it.
          ui.draggable.hide().remove();

          //Get the container for the project status
          var container = $($(this).attr('href'));

          //Append the new project block to the status container.
          container.append('<div class="project-block">'+ui.draggable.html()+'</div>');

          //Buind the dropable actions to the new product block.
          self.droppableProjects();
          self.draggableRisks();

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
                    }, 1500)
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

    deleteProject : function(form){
      var project_id = $(form).find("[name='project_id']").val();
      $.ajax({
        url: BASE_URL + '/api/management/project/delete',
        type: 'POST',
        data: {delete_project: true, project_id : project_id},
        success : function (data){
          $('#project--delete').modal('hide');
          showAlertsFromArray(data.status_message);
          setTimeout(function(){
            location.reload();
          }, 1500)
        },
          error: function(xhr,status,error){
            $('#project--delete').modal('hide');
            if(xhr.responseJSON && xhr.responseJSON.status_message){
                showAlertsFromArray(xhr.responseJSON.status_message);
            }
          }
        
      });
    },

    loadProjects : function(){

      var self = this;
      var projects = self.projects.active;
      var template = $('#project-template').html();

      i = 0, len = projects.length,
      fragment = '';

      for ( ; i < len; i++ ) {
        fragment += template
          .replace( /\{\{NAME\}\}/, projects[i].name )
          .replace( /\{\{PRIORITY\}\}/, projects[i].id );
      }

      $('#active-projects').html(fragment);
    }
  };


  planProject.init();
});


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
