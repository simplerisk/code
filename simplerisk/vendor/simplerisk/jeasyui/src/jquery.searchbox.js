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
 * searchbox - EasyUI for jQuery
 * 
 * Dependencies:
 *  textbox
 * 	menubutton
 * 
 */
(function($){
	
	function buildSearchBox(target){
		var state = $.data(target, 'searchbox');
		var opts = state.options;
		var icons = $.extend(true, [], opts.icons);
		icons.push({
			iconCls:'searchbox-button',
			handler:function(e){
				var t = $(e.data.target);
				var opts = t.searchbox('options');
				opts.searcher.call(e.data.target, t.searchbox('getValue'), t.searchbox('getName'));
			}
		});
		
		buildMenu();
		var menuItem = getSelectedItem();
		
		$(target).addClass('searchbox-f').textbox($.extend({}, opts, {
			icons: icons,
			buttonText: (menuItem ? menuItem.text : '')
		}));
		$(target).attr('searchboxName', $(target).attr('textboxName'));
		state.searchbox = $(target).next();
		state.searchbox.addClass('searchbox');
		
		attachMenuItem(menuItem);
		
		function buildMenu(){
			if (opts.menu){
				if (typeof opts.menu == 'string'){
					state.menu = $(opts.menu).menu();
				} else {
					if (!state.menu){
						state.menu = $('<div></div>').appendTo('body').menu();
					}
					state.menu.menu('clear').menu('appendItems', opts.menu);
				}
				// state.menu = $(opts.menu).menu();
				var menuOpts = state.menu.menu('options');
				var onClick = menuOpts.onClick;
				menuOpts.onClick = function(item){
					attachMenuItem(item);
					onClick.call(this, item);
				}
			} else {
				if (state.menu){state.menu.menu('destroy');}
				state.menu = null;
			}
		}
		function getSelectedItem(){
			if (state.menu){
				var item = state.menu.children('div.menu-item:first');
				state.menu.children('div.menu-item').each(function(){
					var itemOpts = $.extend({}, $.parser.parseOptions(this), {
						selected: ($(this).attr('selected') ? true : undefined)
					});
					if (itemOpts.selected) {
						item = $(this);
						return false;
					}
				});
				return state.menu.menu('getItem', item[0]);
			} else {
				return null;
			}
		}
		function attachMenuItem(item){
			if (!item){return;}
			$(target).textbox('button').menubutton({
				text:item.text,
				iconCls:(item.iconCls||null),
				menu:state.menu,
				menuAlign:opts.buttonAlign,
				duration:opts.duration,
				showEvent:opts.showEvent,
				hideEvent:opts.hideEvent,
				plain:false
			});
			state.searchbox.find('input.textbox-value').attr('name', item.name || item.text);
			$(target).searchbox('resize');
		}
	}
	
	$.fn.searchbox = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.searchbox.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.textbox(options, param);
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'searchbox');
			if (state){
				$.extend(state.options, options);
			} else {
				$.data(this, 'searchbox', {
					options: $.extend({}, $.fn.searchbox.defaults, $.fn.searchbox.parseOptions(this), options)
				});
			}
			buildSearchBox(this);
		});
	}
	
	$.fn.searchbox.methods = {
		options: function(jq){
			var opts = jq.textbox('options');
			return $.extend($.data(jq[0], 'searchbox').options, {
				width: opts.width,
				value: opts.value,
				originalValue: opts.originalValue,
				disabled: opts.disabled,
				readonly: opts.readonly
			});
		},
		menu: function(jq){
			return $.data(jq[0], 'searchbox').menu;
		},
		getName: function(jq){
			return $.data(jq[0], 'searchbox').searchbox.find('input.textbox-value').attr('name');
		},
		selectName: function(jq, name){
			return jq.each(function(){
				var menu = $.data(this, 'searchbox').menu;
				if (menu){
					menu.children('div.menu-item').each(function(){
						var item = menu.menu('getItem', this);
						if (item.name == name){
							// $(this).triggerHandler('click');
							$(this).trigger('click');
							return false;
						}
					});
				}
			});
		},
		destroy: function(jq){
			return jq.each(function(){
				var menu = $(this).searchbox('menu');
				if (menu){
					menu.menu('destroy');
				}
				$(this).textbox('destroy');
			});
		}
	};
	
	$.fn.searchbox.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.fn.textbox.parseOptions(target), $.parser.parseOptions(target, ['menu',{duration:'number'}]), {
			searcher: (t.attr('searcher') ? eval(t.attr('searcher')) : undefined)
		});
	};
	
	$.fn.searchbox.defaults = $.extend({}, $.fn.textbox.defaults, {
		inputEvents: $.extend({}, $.fn.textbox.defaults.inputEvents, {
			keydown: function(e){
				if (e.keyCode == 13){
					e.preventDefault();
					var t = $(e.data.target);
					var opts = t.searchbox('options');
					t.searchbox('setValue', $(this).val());
					opts.searcher.call(e.data.target, t.searchbox('getValue'), t.searchbox('getName'));
					return false;
				}
			}
		}),
		
		buttonAlign:'left',
		menu:null,
		duration: 100,
		showEvent: 'mouseenter',
		hideEvent: 'mouseleave',
		searcher:function(value,name){}
	});
})(jQuery);
