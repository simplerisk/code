jQuery(document).ready(function($){

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

      $('#project--delete').submit(function (event){
        event.preventDefault();
        self.deleteProject(this);
      });

      $('#project--new').submit(function(event) {
        event.preventDefault();

        self.createProject(this);
      });

      //Bind the draggable option for the risks
      self.draggableRisks();

      //Bind the droppable elements.
      self.droppableProjects();

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
                url: 'plan-projects.php',
                type: 'POST',
                data: {update_order: true, project_ids : order},
                success : function (data){
                    showAlertsFromArray(data.status_message);
                    setTimeout(function(){
                        location.reload();
                    }, 1500)
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this)){}
                }
            });
        }
      });

      $( ".status" ).sortable({
          connectWith: ".sortable"
      });

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
                url: 'plan-projects.php',
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
                }
            
          });
        }
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
              url: 'plan-projects.php',
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

    deleteProject : function(form){
      var project_id = $(form).find("[name='project_id']").val();
      $.ajax({
        url: 'plan-projects.php',
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
              if(!retryCSRF(xhr, this)){}
          }
        
      });
    },

    createProject : function(form){
      var self = this;

      var el = $(form).find('#project--name');
      var projectName = el.val();
      var newProject = {
        'id' : +new Date(),
        'name' : projectName,
        'status': '1'
      };

      $.ajax({
        url: 'plan-projects.php',
        type: 'POST',
        data: {add_project: true, new_project : projectName},
        success : function (data){
          location.reload();
        }
      });

      el.val('');
      $("#project--add").modal('hide');
      self.loadProjects();
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
