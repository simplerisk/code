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
 * numberspinner - EasyUI for jQuery
 * 
 * Dependencies:
 * 	 spinner
 * 	 numberbox
 */
(function($){
	function create(target){
		$(target).addClass('numberspinner-f');
		var opts = $.data(target, 'numberspinner').options;
		$(target).numberbox($.extend({},opts,{doSize:false})).spinner(opts);
		$(target).numberbox('setValue', opts.value);
	}
	
	function doSpin(target, down){
		var opts = $.data(target, 'numberspinner').options;
		var v = parseFloat($(target).numberbox('getValue') || opts.value) || 0;
		if (down){
			v -= opts.increment;
		} else {
			v += opts.increment;
		}
		$(target).numberbox('setValue', v);
	}
	
	$.fn.numberspinner = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.numberspinner.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.numberbox(options, param);
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'numberspinner');
			if (state){
				$.extend(state.options, options);
			} else {
				$.data(this, 'numberspinner', {
					options: $.extend({}, $.fn.numberspinner.defaults, $.fn.numberspinner.parseOptions(this), options)
				});
			}
			create(this);
		});
	};
	
	$.fn.numberspinner.methods = {
		options: function(jq){
			var opts = jq.numberbox('options');
			return $.extend($.data(jq[0], 'numberspinner').options, {
				width: opts.width,
				value: opts.value,
				originalValue: opts.originalValue,
				disabled: opts.disabled,
				readonly: opts.readonly
			});
		}
	};
	
	$.fn.numberspinner.parseOptions = function(target){
		return $.extend({}, $.fn.spinner.parseOptions(target), $.fn.numberbox.parseOptions(target), {
		});
	};
	
	$.fn.numberspinner.defaults = $.extend({}, $.fn.spinner.defaults, $.fn.numberbox.defaults, {
		spin: function(down){doSpin(this, down);}
	});
})(jQuery);
