$(document).ready(function(){
    // Event for responsibility checkbox in role_management page
    $("body").on("change", ".checklist input[type=checkbox]", function(){
        var $child_checkboxes = $(this).parent().next().find("li input[type=checkbox]");
        if($child_checkboxes.length){
            $child_checkboxes.prop("checked", $(this).is(":checked"))
        }
    })
    
    $("#role").change(function(){
        // If role is unselected, uncheck all responsibilities
        if(!$(this).val())
        {
            $(".checklist input[type=checkbox]").prop("checked", false);

        }
        // If administrator.
        else if($(this).val() == 1)
        {
            $(".checklist input[type=checkbox]").prop("checked", true);
        }
        else
        {
            $.ajax({
                type: "GET",
                url: BASE_URL + "/api/role_responsibilities/get_responsibilities",
                data: {
                    role_id: $(this).val()
                },
                success: function(data){
                    // Uncheck all checkboxes
                    $(".checklist input[type=checkbox]").prop("checked", false);
                    
                    // Check all for responsibilites
                    var responsibility_names = data.data;
                    for(var key in responsibility_names){
                        $(".checklist input[name='responsibilities["+responsibility_names[key]+"]']").prop("checked", true)
                    }
                },
                error: function(xhr,status,error){
                    if(xhr.responseJSON && xhr.responseJSON.status_message){
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            })
        }
    })
})

function checkAll(bx) {
    if(bx.checked){
        $(bx).parents('table').find('input[type=checkbox]').prop('checked', true);
    }else{
        $(bx).parents('table').find('input[type=checkbox]').prop('checked', false);
    }
    
}
