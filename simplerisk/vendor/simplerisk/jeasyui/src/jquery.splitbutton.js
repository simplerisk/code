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
 * splitbutton - EasyUI for jQuery
 * 
 * Dependencies:
 *   menubutton
 */
(function($){
	
	function init(target){
		var opts = $.data(target, 'splitbutton').options;
		$(target).menubutton(opts);
		$(target).addClass('s-btn');
	}
	
	$.fn.splitbutton = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.splitbutton.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.menubutton(options, param);
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'splitbutton');
			if (state){
				$.extend(state.options, options);
			} else {
				$.data(this, 'splitbutton', {
					options: $.extend({}, $.fn.splitbutton.defaults, $.fn.splitbutton.parseOptions(this), options)
				});
				// $(this).removeAttr('disabled');
				$(this)._propAttr('disabled', false);
			}
			init(this);
		});
	};
	
	$.fn.splitbutton.methods = {
		options: function(jq){
			var mopts = jq.menubutton('options');
			var sopts = $.data(jq[0], 'splitbutton').options;
			$.extend(sopts, {
				disabled: mopts.disabled,
				toggle: mopts.toggle,
				selected: mopts.selected
			});
			return sopts;
		}
	};
	
	$.fn.splitbutton.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.fn.linkbutton.parseOptions(target), 
				$.parser.parseOptions(target, ['menu',{plain:'boolean',duration:'number'}]));
	};
	
	$.fn.splitbutton.defaults = $.extend({}, $.fn.linkbutton.defaults, {
		plain: true,
		menu: null,
		duration: 100,
		cls: {
			btn1: 'm-btn-active s-btn-active',
			btn2: 'm-btn-plain-active s-btn-plain-active',
			arrow: 'm-btn-downarrow',
			trigger: 'm-btn-line'
		}
	});
})(jQuery);
