/**
 * EasyUI for jQuery 1.10.19
 * 
 * Copyright (c) 2009-2024 www.jeasyui.com. All rights reserved.
 *
 * Licensed under the commercial license: http://www.jeasyui.com/license_commercial.php
 * To use it on other terms please contact us: info@jeasyui.com
 *
 */
/**
 * checkgroup - EasyUI for jQuery
 * 
 * Dependencies:
 * 	 checkbox
 * 
 */
(function($){
    var CHECKNAME_SERNO = 1;

    function buildGroup(target){
        var state = $.data(target, 'checkgroup');
		var opts = state.options;
        $(target).addClass('checkgroup').empty();
        var c = $('<div></div>').appendTo(target);
        if (opts.dir == 'h'){
            c.addClass('f-row');
            c.css('flex-wrap','wrap');
        } else {
            c.addClass('f-column');
        }
        var name = opts.name || ('checkname'+CHECKNAME_SERNO++);
        for(var i=0; i<opts.data.length; i++){
            var inner = $('<div class="checkgroup-item f-row f-vcenter f-noshrink"></div>').appendTo(c);
            if (opts.itemStyle){
                inner.css(opts.itemStyle);
            }
            var ck = $('<input>').attr('name',name).appendTo(inner);
            ck.checkbox($.extend({}, {
                labelWidth: opts.labelWidth,
                labelPosition: opts.labelPosition,
                labelAlign: opts.labelAlign
            }, opts.data[i], {
                checked: $.inArray(opts.data[i].value,opts.value)>=0,
                item: opts.data[i],
                onChange: function(){
                    var vv = [];
                    c.find('.checkbox-f').each(function(){
                        var copts = $(this).checkbox('options');
                        if (copts.checked){
                            vv.push(copts.item.value);
                        }
                    });
                    opts.value = vv;
                    opts.onChange.call(target,vv);
                }
            }));
            var state = ck.data('checkbox');
            if (state.options.labelWidth=='auto'){
                $(state.label).css('width','auto');
            }
        }
    }

    function setValue(target, value){
        var state = $.data(target, 'checkgroup');
		var opts = state.options;
        var onChange = opts.onChange;
        opts.onChange = function(){};
        var oldValue = $.extend([],opts.value).sort().join(',');
        $(target).find('.checkbox-f').each(function(){
            var copts = $(this).checkbox('options');
            if ($.inArray(copts.item.value,value)>=0){
                $(this).checkbox('check');
            } else {
                $(this).checkbox('uncheck');
            }
        });
        opts.onChange = onChange;
        var newValue = $.extend([],opts.value).sort().join(',');
        if (newValue != oldValue){
            opts.onChange.call(target, opts.value);
        }
    }

    $.fn.checkgroup = function(options, param){
        if (typeof options == 'string'){
			return $.fn.checkgroup.methods[options](this, param);
		}
		options = options || {};
        return this.each(function(){
            var state = $.data(this, 'checkgroup');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'checkgroup', {
					options: $.extend({}, $.fn.checkgroup.defaults, $.fn.checkgroup.parseOptions(this), options)
				});
			}
            buildGroup(this);
        });
    };

    $.fn.checkgroup.methods = {
        options: function(jq){
            return jq.data('checkgroup').options;
        },
        setValue: function(jq,value){
            return jq.each(function(){
                setValue(this, value);
            });
        },
        getValue: function(jq){
            return jq.checkgroup('options').value;
        }
    };

    $.fn.checkgroup.parseOptions = function(target){
		return $.extend({}, $.parser.parseOptions(target, [
			'dir','name','value','labelPosition','labelAlign',{labelWidth:'number'}
		]));
    };

    $.fn.checkgroup.defaults = {
        dir: 'h',	// 'h'(horizontal) or 'v'(vertical)
        name: null,
        value: [],
        labelWidth:'',
		labelPosition:'after',	// before,after
		labelAlign:'left',	    // left, right
        itemStyle: {height:30},
        onChange: function(value){}
    };
})(jQuery);
