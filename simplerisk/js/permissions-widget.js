function check_indeterminate_checkboxes(_this) {

  	var checked = _this.prop("checked"), container = _this.parent(), siblings = container.siblings();

  	container.find('input[type="checkbox"]').prop({
    	indeterminate: false,
    	checked: checked
  	});

    function checkSiblings(el) {
    
        var parent = el.parent().parent(),
            all = true;
        
        el.siblings().each(function() {
        	let returnValue = all = ($(this).children('input[type="checkbox"]').prop("checked") === checked);
          	return returnValue;
        });
        
        if (all && checked) {
        
          	parent.children('input[type="checkbox"]').prop({
            	indeterminate: false,
            	checked: checked
          	});
        
          	checkSiblings(parent);
        
        } else if (all && !checked) {
        
          	parent.children('input[type="checkbox"]').prop("checked", checked);
          	parent.children('input[type="checkbox"]').prop("indeterminate", (parent.find('input[type="checkbox"]:checked').length > 0));
          	checkSiblings(parent);
        
        } else {
        
          	el.parents("li").children('input[type="checkbox"]').prop({
            	indeterminate: true,
            	checked: false
          	});
        }
  }

  checkSiblings(container);
}

function update_widget(permissions) {
    $(".permissions-widget input[type=checkbox]").each(function() {
    	$this = $(this);
    	$this.prop("checked", false);
    	$this.prop("readonly", false);
    	$this.prop("indeterminate", false);
    });
    
    if (permissions) {
        for(var id of permissions){
            $(".permissions-widget input.permission[value='" + id + "']").prop("checked", true);
        }
        $('.permissions-widget input[type="checkbox"].permission:checked').each(function() { check_indeterminate_checkboxes($(this)); });
    }
}

$(document).ready(function() {
	$('.permissions-widget input[type="checkbox"]').change(function(e) {
		check_indeterminate_checkboxes($(e.target));
	});

	$('.permissions-widget input[type="checkbox"].permission:checked').each(function() { check_indeterminate_checkboxes($(this)); });
});