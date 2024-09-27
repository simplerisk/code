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
 * drawer - EasyUI for jQuery
 * 
 * Dependencies:
 * 	 dialog
 * 
 */
(function($){

	function buildDrawer(target){
		var opts = $.data(target, 'drawer').options;
		$(target).dialog($.extend({}, opts, {
			cls: 'drawer f-column window-shadow layout-panel layout-collapsed layout-panel-'+opts.region,
			bodyCls: 'f-full',
			collapsed: false,
			top: 0,
			left: 'auto',
			right: 'auto',
			onResize: function(w,h){
				if (opts.collapsed) {
					var width = $(target).dialog('dialog').width();
					$(target).dialog('dialog').css({
						display: '',
						left: opts.region == 'east' ? 'auto' : -width,
						right: opts.region == 'east' ? -width : 'auto'
					});
				}
				opts.onResize.call(this, w, h);
			}
		}));
		$(target).dialog('header').find('.panel-tool-collapse').addClass('layout-button-'+(opts.region=='east'?'right':'left'))._unbind()._bind('click', function(){
			collapseDrawer(target);
		});
		var width = $(target).dialog('dialog').width();
		$(target).dialog('dialog').css({
			display: '',
			left: opts.region=='east' ? 'auto' : -width,
			right: opts.region=='east' ? -width : 'auto'
		});
		var mask = $(target).data('window').mask;
		$(mask).addClass('drawer-mask').hide()._unbind()._bind('click', function(){
			collapseDrawer(target);
		});
	}
	function expandDrawer(target){
		var opts = $.data(target, 'drawer').options;
		if (opts.onBeforeExpand.call(target) == false) return;
		var width = $(target).dialog('dialog').width();
		var mask = $(target).data('window').mask;
		$(mask).show();
		$(target).show().css({display:''}).dialog('dialog').animate({
			left: opts.region=='east' ? 'auto' : 0,
			right: opts.region=='east' ? 0 : 'auto'
		}, function(){
			$(this).removeClass('layout-collapsed');
			opts.collapsed = false;
			opts.onExpand.call(target);
		});
	}
	function collapseDrawer(target){
		var opts = $.data(target, 'drawer').options;
		if (opts.onBeforeCollapse.call(target) == false) return;
		var width = $(target).dialog('dialog').width();
		$(target).show().css({display:''}).dialog('dialog').animate({
			left: opts.region=='east' ? 'auto' : -width,
			right: opts.region=='east' ? -width : 'auto'
		}, function(){
			$(this).addClass('layout-collapsed');
			var mask = $(target).data('window').mask;
			$(mask).hide();
			opts.collapsed = true;
			opts.onCollapse.call(this);
		});
	}

	$.fn.drawer = function(options, param) {
		if (typeof options == 'string'){
			var method = $.fn.drawer.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.dialog(options, param);
			}
		}

		options = options || {};
		this.each(function(){
			var state = $.data(this, 'drawer');
			if (state){
				$.extend(state.options, options);
			} else {
				var opts = $.extend({}, $.fn.drawer.defaults, $.fn.drawer.parseOptions(this), options);
				$.data(this, 'drawer', {
					options: opts
				});
			}
			buildDrawer(this);
		});
	}

	$.fn.drawer.methods = {
		options: function(jq){
			var opts = $.data(jq[0], 'drawer').options;
			return $.extend(jq.dialog('options'), {
				region: opts.region,
				collapsed: opts.collapsed
			});
		},
		expand: function(jq){
			return jq.each(function(){
				expandDrawer(this);
			});
		},
		collapse: function(jq){
			return jq.each(function(){
				collapseDrawer(this);
			});
		}
	}

	$.fn.drawer.parseOptions = function(target){
		return $.extend({}, $.fn.dialog.parseOptions(target), $.parser.parseOptions(target,['region']));
	};

	$.fn.drawer.defaults = $.extend({}, $.fn.dialog.defaults, {
		border: false,
		region: 'east',
		title: null,
		shadow: false,
		fixed: true,
		collapsed: true,
		closable: false,
		modal: true,
		draggable: false
	});
})(jQuery);
