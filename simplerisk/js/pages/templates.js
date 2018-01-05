function delete_field()
{
	var e = document.getElementById("custom_field_name");
	var field_id = e.options[e.selectedIndex].value;
	var formData = {field_id: field_id};

    $.ajax({
        type: "POST",
        url: BASE_URL + "/api/admin/fields/delete",
        data: formData,
        success: function(data){
        }
    })
    .success(function(){
        $("#custom_field_name option[value=" + field_id + "]").remove();
    })
    .fail(function(xhr, textStatus){
        var data = JSON.parse(xhr.responseText);
        alert(data.status_message);
    });
}
