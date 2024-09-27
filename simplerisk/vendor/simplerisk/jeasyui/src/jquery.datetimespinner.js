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
 * datetimespinner - EasyUI for jQuery
 * 
 * Dependencies:
 *   timespinner
 * 
 */
(function($){
	function create(target){
		var opts = $.data(target, 'datetimespinner').options;
		$(target).addClass('datetimespinner-f').timespinner(opts);
	}
	
	$.fn.datetimespinner = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.datetimespinner.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.timespinner(options, param);
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'datetimespinner');
			if (state){
				$.extend(state.options, options);
			} else {
				$.data(this, 'datetimespinner', {
					options: $.extend({}, $.fn.datetimespinner.defaults, $.fn.datetimespinner.parseOptions(this), options)
				});
			}
			create(this);
		});
	};
	
	$.fn.datetimespinner.methods = {
		options: function(jq){
			var opts = jq.timespinner('options');
			return $.extend($.data(jq[0], 'datetimespinner').options, {
				width: opts.width,
				value: opts.value,
				originalValue: opts.originalValue,
				disabled: opts.disabled,
				readonly: opts.readonly
			});
		}
	};
	
	$.fn.datetimespinner.parseOptions = function(target){
		return $.extend({}, $.fn.timespinner.parseOptions(target), $.parser.parseOptions(target, [
		]));
	};
	
	$.fn.datetimespinner.defaults = $.extend({}, $.fn.timespinner.defaults, {
		formatter:function(date){
			if (!date){return '';}
			return $.fn.datebox.defaults.formatter.call(this, date) + ' ' + $.fn.timespinner.defaults.formatter.call(this, date);
		},
		parser:function(s){
			s = $.trim(s);
			if (!s){return null;}
			var dt = s.split(' ');
			var date1 = $.fn.datebox.defaults.parser.call(this, dt[0]);
			if (dt.length < 2){
				return date1;
			}
			var date2 = $.fn.timespinner.defaults.parser.call(this, dt[1]+(dt[2]?' '+dt[2]:''));
			return new Date(date1.getFullYear(), date1.getMonth(), date1.getDate(), date2.getHours(), date2.getMinutes(), date2.getSeconds());
		},
		selections:[[0,2],[3,5],[6,10],[11,13],[14,16],[17,19],[20,22]]
	});
})(jQuery);
