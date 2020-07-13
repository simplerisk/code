function sortOptions(select) {
    var options = $(select).find('option');
    var arr = options.map(function(_, o) {
        return {
            t: $(o).text(),
            v: o.value
        };
    }).get();
    arr.sort(function(o1, o2) {
        return o1.t > o2.t ? 1 : o1.t < o2.t ? -1 : 0;
    });
    options.each(function(i, o) {
        o.value = arr[i].v;
        $(o).text(arr[i].t);
    });
}

function addOptions(select, data) {
    for (let i = 0, len = data.length; i < len; ++i) {
        let o = data[i];
        select.append($("<option title='" + o.name + "' value='" + (o.id === undefined ? o.value : o.id) + "'>" + o.name + "</option>"));
    }
}
            
$(document).ready(function(){
	$('.select-list-arrows .btnLeft').click(function (e) {
	    var selectedOpts = $(this).parent().parent().find('.select-list-selected select option:selected');
	    if (selectedOpts.length == 0) {
	        //alert("Nothing to move.");
	        e.preventDefault();
	    }
	
	    var target = $(this).parent().parent().find('.select-list-available select');
	    target.append($(selectedOpts).clone());
	    sortOptions(target);
	
	    $(selectedOpts).remove();
	    e.preventDefault();
	});
	
	$('.select-list-arrows .btnAllLeft').click(function (e) {
	    var selectedOpts = $(this).parent().parent().find('.select-list-selected select option');
	    if (selectedOpts.length == 0) {
	        //alert("Nothing to move.");
	        e.preventDefault();
	    }
	
	    var target = $(this).parent().parent().find('.select-list-available select');
	    target.append($(selectedOpts).clone());
	    sortOptions(target);
	
	    $(selectedOpts).remove();
	    e.preventDefault();
	});
	
	$('.select-list-arrows .btnRight').click(function (e) {
	    var selectedOpts = $(this).parent().parent().find('.select-list-available select option:selected');
	    if (selectedOpts.length == 0) {
	        //alert("Nothing to move.");
	        e.preventDefault();
	    }
	
	    var target = $(this).parent().parent().find('.select-list-selected select');
	    target.append($(selectedOpts).clone());
	    sortOptions(target);
	
	    $(selectedOpts).remove();
	    e.preventDefault();
	});
	
	$('.select-list-arrows .btnAllRight').click(function (e) {
	    var selectedOpts = $(this).parent().parent().find('.select-list-available select option');
	    if (selectedOpts.length == 0) {
	        //alert("Nothing to move.");
	        e.preventDefault();
	    }
	
	    var target = $(this).parent().parent().find('.select-list-selected select');
	    target.append($(selectedOpts).clone());
	    sortOptions(target);
	
	    $(selectedOpts).remove();
	    e.preventDefault();
	});
});