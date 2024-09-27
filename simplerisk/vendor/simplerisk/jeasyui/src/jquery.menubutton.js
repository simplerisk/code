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
 * menubutton - EasyUI for jQuery
 * 
 * Dependencies:
 *   linkbutton
 *   menu
 */
(function($){
	
	function init(target){
		var opts = $.data(target, 'menubutton').options;
		var btn = $(target);
		btn.linkbutton(opts);
		if (opts.hasDownArrow){
			btn.removeClass(opts.cls.btn1+' '+opts.cls.btn2).addClass('m-btn');
			btn.removeClass('m-btn-small m-btn-medium m-btn-large').addClass('m-btn-'+opts.size);
			var inner = btn.find('.l-btn-left');
			$('<span></span>').addClass(opts.cls.arrow).appendTo(inner);
			$('<span></span>').addClass('m-btn-line').appendTo(inner);
		}
		$(target).menubutton('resize');
		
		if (opts.menu){
			if (typeof opts.menu == 'string'){
				$(opts.menu).menu({duration:opts.duration});
			} else {
				if (!(opts.menu instanceof jQuery)){
					var items = opts.menu;
					opts.menu = $('<div></div>').appendTo('body').menu({duration:opts.duration});
					opts.menu.menu('appendItems', items);
				}
			}
			// $(opts.menu).menu({duration:opts.duration});
			var mopts = $(opts.menu).menu('options');
			var onShow = mopts.onShow;
			var onHide = mopts.onHide;
			$.extend(mopts, {
				onShow: function(){
					var mopts = $(this).menu('options');
					var btn = $(mopts.alignTo);
					var opts = btn.menubutton('options');
					btn.addClass((opts.plain==true) ? opts.cls.btn2 : opts.cls.btn1);
					onShow.call(this);
				},
				onHide: function(){
					var mopts = $(this).menu('options');
					var btn = $(mopts.alignTo);
					var opts = btn.menubutton('options');
					btn.removeClass((opts.plain==true) ? opts.cls.btn2 : opts.cls.btn1);
					onHide.call(this);
				}
			});
		}
	}
	
	function bindEvents(target){
		var opts = $.data(target, 'menubutton').options;
		var btn = $(target);
		var t = btn.find('.'+opts.cls.trigger);
		if (!t.length){t = btn}
		t._unbind('.menubutton');
		var timeout = null;
		t._bind(opts.showEvent+'.menubutton', function(){
			if (!isDisabled()){
				timeout = setTimeout(function(){
					showMenu(target);
				}, opts.duration);
				return false;
			}
		})._bind(opts.hideEvent+'.menubutton', function(){
			if (timeout){
				clearTimeout(timeout);
			}
			$(opts.menu).triggerHandler('mouseleave');
		});
		
		function isDisabled(){
			return $(target).linkbutton('options').disabled;
		}
	}
	
	function showMenu(target){
//		var opts = $.data(target, 'menubutton').options;
		var opts = $(target).menubutton('options');
		if (opts.disabled || !opts.menu){return}
		$('body>div.menu-top').menu('hide');
		var btn = $(target);
		var mm = $(opts.menu);
		if (mm.length){
			mm.menu('options').alignTo = btn;
			mm.menu('show', {alignTo:btn,align:opts.menuAlign});
		}
		btn.blur();
	}
	
	$.fn.menubutton = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.menubutton.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.linkbutton(options, param);
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'menubutton');
			if (state){
				$.extend(state.options, options);
			} else {
				$.data(this, 'menubutton', {
					options: $.extend({}, $.fn.menubutton.defaults, $.fn.menubutton.parseOptions(this), options)
				});
				// $(this).removeAttr('disabled');
				$(this)._propAttr('disabled', false);
			}
			
			init(this);
			bindEvents(this);
		});
	};
	
	$.fn.menubutton.methods = {
		options: function(jq){
			var bopts = jq.linkbutton('options');
			return $.extend($.data(jq[0], 'menubutton').options, {
				toggle: bopts.toggle,
				selected: bopts.selected,
				disabled: bopts.disabled
			});
		},
		destroy: function(jq){
			return jq.each(function(){
				var opts = $(this).menubutton('options');
				if (opts.menu){
					$(opts.menu).menu('destroy');
				}
				$(this).remove();
			});
		}
	};
	
	$.fn.menubutton.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.fn.linkbutton.parseOptions(target), $.parser.parseOptions(target, [
		    'menu',{plain:'boolean',hasDownArrow:'boolean',duration:'number'}
		]));
	};
	
	$.fn.menubutton.defaults = $.extend({}, $.fn.linkbutton.defaults, {
		plain: true,
		hasDownArrow: true,
		menu: null,
		menuAlign: 'left',	// the top level menu alignment
		duration: 100,
		showEvent: 'mouseenter',
		hideEvent: 'mouseleave',
		cls: {
			btn1: 'm-btn-active',
			btn2: 'm-btn-plain-active',
			arrow: 'm-btn-downarrow',
			trigger: 'm-btn'
		}
	});
})(jQuery);
