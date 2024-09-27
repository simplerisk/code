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
 * radiogroup - EasyUI for jQuery
 * 
 * Dependencies:
 * 	 radiobutton
 * 
 */
(function($){
    var RADIONAME_SERNO = 1;

    function buildGroup(target){
        var state = $.data(target, 'radiogroup');
		var opts = state.options;
        $(target).addClass('radiogroup').empty();
        var c = $('<div></div>').appendTo(target);
        if (opts.dir == 'h'){
            c.addClass('f-row');
            c.css('flex-wrap','wrap');
        } else {
            c.addClass('f-column');
        }
        var name = opts.name || ('radioname'+RADIONAME_SERNO++);
        for(var i=0; i<opts.data.length; i++){
            var inner = $('<div class="radiogroup-item f-row f-vcenter f-noshrink"></div>').appendTo(c);
            if (opts.itemStyle){
                inner.css(opts.itemStyle);
            }
            var rb = $('<input>').attr('name',name).appendTo(inner);
            rb.radiobutton($.extend({},{
                labelWidth: opts.labelWidth,
                labelPosition: opts.labelPosition,
                labelAlign: opts.labelAlign
            }, opts.data[i], {
                checked: opts.data[i].value == opts.value,
                item: opts.data[i],
                onChange: function(){
                    c.find('.radiobutton-f').each(function(){
                        var ropts = $(this).radiobutton('options');
                        if (ropts.checked){
                            opts.value = ropts.item.value;
                            opts.onChange.call(target,ropts.item.value);
                        }
                    });
                }
            }));
            var state = rb.data('radiobutton');
            if (state.options.labelWidth=='auto'){
                $(state.label).css('width','auto');
            }
        }
    }

    function setValue(target, value){
        $(target).find('.radiobutton-f').each(function(){
            var ropts = $(this).radiobutton('options');
            if (ropts.item.value == value){
                $(this).radiobutton('check');
            }
        });
    }

    $.fn.radiogroup = function(options, param){
        if (typeof options == 'string'){
			return $.fn.radiogroup.methods[options](this, param);
		}
		options = options || {};
        return this.each(function(){
            var state = $.data(this, 'radiogroup');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'radiogroup', {
					options: $.extend({}, $.fn.radiogroup.defaults, $.fn.radiogroup.parseOptions(this), options)
				});
			}
            buildGroup(this);
        });
    };

    $.fn.radiogroup.methods = {
        options: function(jq){
            return jq.data('radiogroup').options;
        },
        setValue: function(jq,value){
            return jq.each(function(){
                setValue(this, value);
            });
        },
        getValue: function(jq){
            return jq.radiogroup('options').value;
        }
    };

    $.fn.radiogroup.parseOptions = function(target){
		return $.extend({}, $.parser.parseOptions(target, [
			'dir','name','value','labelPosition','labelAlign',{labelWidth:'number'}
		]));
    };

    $.fn.radiogroup.defaults = {
        dir: 'h',	// 'h'(horizontal) or 'v'(vertical)
        name: null,
        value: null,
        labelWidth:'',
		labelPosition:'after',	// before,after
		labelAlign:'left',	    // left, right
        itemStyle: {height:30},
        onChange: function(value){}
    };
})(jQuery);
