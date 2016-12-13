function refreshFilelist($parent, currentButtonId){
    var files = $("input[type=file]", $parent);

    var filesHtml = "";
    var filesLength = 0;
    $(files).each(function() {
        if(!$(this)[0].files.length){
            return;
        }
        $(this).attr("id", "file-upload-"+filesLength)
        var name = $(this)[0].files[0].name;
        filesHtml += "<li >\
            <div class='file-name'>"+name+"</div>\
            <a href='#' class='remove-file' data-id='file-upload-"+filesLength+"'><i class='fa fa-remove'></i></a>\
        </li>";
        filesLength++;
    });
    $($parent).find('.file-list').html(filesHtml);
    var totalFilesLength = $('.exist-files > li', $parent).length + filesLength;
    if(totalFilesLength > 1){
        $msg = "<span class='file-count'>" + totalFilesLength + "</span> Files Added"; 
    }else{ 
        $msg = "<span class='file-count'>" + totalFilesLength + "</span> File Added"; 
    }
    $($parent).find('.file-count-html').html($msg);
    if(currentButtonId){
        $parent.prepend($('<input id="'+currentButtonId+'" name="file[]" class="hidden-file-upload hide active" type="file">'))
    }
    
}

$(document).ready(function(){
    $(document).on('click', '.exist-files .remove-file', function(event) {
        event.preventDefault();
        var $parent = $(this).parents('.file-uploader');
        var fileCount = Number($parent.find('.file-count').html()) - 1
        $parent.find('.file-count').html(fileCount)
        $(this).parent().remove();
    })
    $(document).on('click', '.file-list .remove-file', function(event) {
        event.preventDefault();
        var id = $(this).data('id');
        var $parent = $(this).parents('.file-uploader');
        $("#"+id, $parent).remove();
        refreshFilelist($parent)
    })
    $(document).on('change', '.hidden-file-upload.active', function(event) {
        event.preventDefault();

        var $parent = $(this).parents('.file-uploader');
        $(this).removeClass("active")
        var currentButtonId = $(this).attr('id');
        
        refreshFilelist($parent, currentButtonId)

    });
    
})