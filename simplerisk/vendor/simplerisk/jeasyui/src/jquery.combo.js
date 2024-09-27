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
 * combo - EasyUI for jQuery
 * 
 * Dependencies:
 *   panel
 *   textbox
 * 
 */
(function($){
	$(function(){
		$(document)._unbind('.combo')._bind('mousedown.combo mousewheel.combo', function(e){
			var p = $(e.target).closest('span.combo,div.combo-p,div.menu');
			if (p.length){
				hideInnerPanel(p);
				return;
			}
			$('body>div.combo-p>div.combo-panel:visible').panel('close');
		});
	});
	
	/**
	 * create the combo component.
	 */
	function buildCombo(target){
		var state = $.data(target, 'combo');
		var opts = state.options;
		if (!state.panel){
			state.panel = $('<div class="combo-panel"></div>').appendTo('html>body');
			state.panel.panel({
				minWidth: opts.panelMinWidth,
				maxWidth: opts.panelMaxWidth,
				minHeight: opts.panelMinHeight,
				maxHeight: opts.panelMaxHeight,
				doSize:false,
				closed:true,
				cls:'combo-p',
				style:{
					position:'absolute',
					zIndex:10
				},
				onOpen:function(){
					var target = $(this).panel('options').comboTarget;
					var state = $.data(target, 'combo');
					if (state){
						state.options.onShowPanel.call(target);
					}
				},
				onBeforeClose:function(){
					hideInnerPanel($(this).parent());
				},
				onClose:function(){
					var target = $(this).panel('options').comboTarget;
					var state = $(target).data('combo');
					// var state = $.data(target, 'combo');
					if (state){
						state.options.onHidePanel.call(target);
					}
				}
			});
		}
		
		var icons = $.extend(true, [], opts.icons);
		if (opts.hasDownArrow){
			icons.push({
				iconCls: 'combo-arrow',
				handler: function(e){
					togglePanel(e.data.target);
				}
			});
		}
		$(target).addClass('combo-f').textbox($.extend({}, opts, {
			icons: icons,
			onChange: function(){}
		}));
		$(target).attr('comboName', $(target).attr('textboxName'));
		state.combo = $(target).next();
		state.combo.addClass('combo');

		state.panel._unbind('.combo');
		for(var event in opts.panelEvents){
			state.panel._bind(event+'.combo', {target:target}, opts.panelEvents[event]);
		}
	}
	
	function destroy(target){
		var state = $.data(target, 'combo');
		var opts = state.options;
		var p = state.panel;
		if (p.is(':visible')){p.panel('close')}
		if (!opts.cloned){p.panel('destroy')}
		$(target).textbox('destroy');
	}
	
	// function togglePanel(target){
	// 	var panel = $.data(target, 'combo').panel;
	// 	if (panel.is(':visible')){
	// 		hidePanel(target);
	// 	} else {
	// 		var p = $(target).closest('div.combo-panel');	// the parent combo panel
	// 		$('div.combo-panel:visible').not(panel).not(p).panel('close');
	// 		$(target).combo('showPanel');
	// 	}
	// 	$(target).combo('textbox').focus();
	// }
	function togglePanel(target){
		var panel = $.data(target, 'combo').panel;
		if (panel.is(':visible')){
			var comboTarget = panel.combo('combo');
			hidePanel(comboTarget);
			if (comboTarget != target){
				$(target).combo('showPanel');
			}
		} else {
			var p = $(target).closest('div.combo-p').children('.combo-panel');	// the parent combo panel
			$('div.combo-panel:visible').not(panel).not(p).panel('close');
			$(target).combo('showPanel');
		}
		$(target).combo('textbox').focus();
	}
	
	/**
	 * hide inner drop-down panels of a specified container
	 */
	function hideInnerPanel(container){
		$(container).find('.combo-f').each(function(){
			var p = $(this).combo('panel');
			if (p.is(':visible')){
				p.panel('close');
			}
		});
	}

	/**
	 * The click event handler on input box
	 */
	function inputClickHandler(e){
		var target = e.data.target;
		var state = $.data(target, 'combo');
		var opts = state.options;
		if (!opts.editable){
			togglePanel(target);
		} else {
			var p = $(target).closest('div.combo-p').children('.combo-panel');	// the parent combo panel
			// $('div.combo-panel:visible').not(panel).not(p).panel('close');
			$('div.combo-panel:visible').not(p).each(function(){
				var comboTarget = $(this).combo('combo');
				if (comboTarget != target){
					hidePanel(comboTarget);
				}
			});
		}
	}
	
	/** 
	 * The key event handler on input box
	 */
	function inputEventHandler(e){
		var target = e.data.target;
		var t = $(target);
		var state = t.data('combo');
		var opts = t.combo('options');
		state.panel.panel('options').comboTarget = target;
		
		switch(e.keyCode){
		case 38:	// up
			opts.keyHandler.up.call(target, e);
			break;
		case 40:	// down
			opts.keyHandler.down.call(target, e);
			break;
		case 37:	// left
			opts.keyHandler.left.call(target, e);
			break;
		case 39:	// right
			opts.keyHandler.right.call(target, e);
			break;
		case 13:	// enter
			e.preventDefault();
			opts.keyHandler.enter.call(target, e);
			return false;
		case 9:		// tab
		case 27:	// esc
			hidePanel(target);
			break;
		default:
			if (opts.editable){
				if (state.timer){
					clearTimeout(state.timer);
				}
				state.timer = setTimeout(function(){
					var q = t.combo('getText');
					if (state.previousText != q){
						state.previousText = q;
						t.combo('showPanel');
						opts.keyHandler.query.call(target, q, e);
						t.combo('validate');
					}
				}, opts.delay);
			}
		}
	}

	function blurEventHandler(e){
		var target = e.data.target;
		var state = $(target).data('combo');
		if (state.timer){
			clearTimeout(state.timer);
		}
	}
	
	/**
	 * show the drop down panel.
	 */
	function showPanel(target){
		var state = $.data(target, 'combo');
		var combo = state.combo;
		var panel = state.panel;
		var opts = $(target).combo('options');
		var palOpts = panel.panel('options');
		
		palOpts.comboTarget = target;	// store the target combo element
		if (palOpts.closed){
			panel.panel('panel').show().css({
				zIndex: ($.fn.menu ? $.fn.menu.defaults.zIndex++ : ($.fn.window ? $.fn.window.defaults.zIndex++ : 99)),
				left: -999999
			});
			panel.panel('resize', {
				width: (opts.panelWidth ? opts.panelWidth : combo._outerWidth()),
				height: opts.panelHeight
			});
			panel.panel('panel').hide();
			panel.panel('open');
		}
		
		// (function(){
		// 	if (palOpts.comboTarget == target && panel.is(':visible')){
		// 		panel.panel('move', {
		// 			left:getLeft(),
		// 			top:getTop()
		// 		});
		// 		setTimeout(arguments.callee, 200);
		// 	}
		// })();
		(function f(){
			if (palOpts.comboTarget == target && panel.is(':visible')){
				panel.panel('move', {
					left:getLeft(),
					top:getTop()
				});
				setTimeout(f, 200);
			}
		})();
		
		function getLeft(){
			var left = combo.offset().left;
			if (opts.panelAlign == 'right'){
				left += combo._outerWidth() - panel._outerWidth();
			}
			if (left + panel._outerWidth() > $(window)._outerWidth() + $(document).scrollLeft()){
				left = $(window)._outerWidth() + $(document).scrollLeft() - panel._outerWidth();
			}
			if (left < 0){
				left = 0;
			}
			return left;
		}
		function getTop(){
			if (opts.panelValign == 'top'){
				var top = combo.offset().top - panel._outerHeight();
			} else if (opts.panelValign == 'bottom'){
				var top = combo.offset().top + combo._outerHeight();
			} else {
				var top = combo.offset().top + combo._outerHeight();
				if (top + panel._outerHeight() > $(window)._outerHeight() + $(document).scrollTop()){
					top = combo.offset().top - panel._outerHeight();
				}
				if (top < $(document).scrollTop()){
					top = combo.offset().top + combo._outerHeight();
				}
			}
			return top;
		}
	}
	
	/**
	 * hide the drop down panel.
	 */
	function hidePanel(target){
		var panel = $.data(target, 'combo').panel;
		panel.panel('close');
	}
	
//	function clear(target){
//		var state = $.data(target, 'combo');
//		var opts = state.options;
//		var combo = state.combo;
//		$(target).textbox('clear');
//		if (opts.multiple){
//			combo.find('.textbox-value').remove();
//		} else {
//			combo.find('.textbox-value').val('');
//		}
//	}
	
	function setText(target, text){
		var state = $.data(target, 'combo');
		var oldText = $(target).textbox('getText');
		if (oldText != text){
			$(target).textbox('setText', text);
		}
		state.previousText = text;
	}
	
	function getValues(target){
		var state = $.data(target, 'combo');
		var opts = state.options;
		// var combo = state.combo;
		var combo = $(target).next();
		var values = [];
		combo.find('.textbox-value').each(function(){
			values.push($(this).val());
		});
		if (opts.multivalue){
			return values;
		} else {
			return values.length ? values[0].split(opts.separator) : values;
		}
	}
	
	function setValues(target, values){
		var state = $.data(target, 'combo');
		var combo = state.combo;
		// var opts = state.options;
		var opts = $(target).combo('options');
		if (!$.isArray(values)){values = values.split(opts.separator)}
		
		var oldValues = getValues(target);
		combo.find('.textbox-value').remove();
		if (values.length){
			if (opts.multivalue){
				for(var i=0; i<values.length; i++){
					_appendValue(values[i]);
				}
			} else {
				_appendValue(values.join(opts.separator));
			}
		}

		function _appendValue(value){
			var name = $(target).attr('textboxName') || '';
			var input = $('<input type="hidden" class="textbox-value">').appendTo(combo);
			input.attr('name', name);
			if (opts.disabled){
				input.attr('disabled', 'disabled');
			}
			input.val(value);
		}
		
		// var changed = (function(){
		// 	if (oldValues.length != values.length){return true;}
		// 	var a1 = $.extend(true, [], oldValues);
		// 	var a2 = $.extend(true, [], values);
		// 	a1.sort();
		// 	a2.sort();
		// 	for(var i=0; i<a1.length; i++){
		// 		if (a1[i] != a2[i]){return true;}
		// 	}
		// 	return false;
		// })();
		var changed = (function(){
			if (opts.onChange == $.parser.emptyFn){return false;}
			if (oldValues.length != values.length){return true;}
			for(var i=0; i<values.length; i++){
				if (values[i] != oldValues[i]){return true;}
			}
			return false;
		})();

		if (changed){
			$(target).val(values.join(opts.separator));
			if (opts.multiple){
				opts.onChange.call(target, values, oldValues);
			} else {
				opts.onChange.call(target, values[0], oldValues[0]);
			}
			$(target).closest('form').trigger('_change', [target]);
		}
	}
	
	function getValue(target){
		var values = getValues(target);
		return values[0];
	}
	
	function setValue(target, value){
		setValues(target, [value]);
	}
	
	/**
	 * set the initialized value
	 */
	function initValue(target){
		var opts = $.data(target, 'combo').options;
		var onChange = opts.onChange;
		opts.onChange = $.parser.emptyFn;
		if (opts.multiple){
			setValues(target, opts.value ? opts.value : []);
		} else {
			setValue(target, opts.value);	// set initialize value
		}
		opts.onChange = onChange;
	}
	
	$.fn.combo = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.combo.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.textbox(options, param);
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'combo');
			if (state){
				$.extend(state.options, options);
				if (options.value != undefined){
					state.options.originalValue = options.value;
				}
			} else {
				state = $.data(this, 'combo', {
					options: $.extend({}, $.fn.combo.defaults, $.fn.combo.parseOptions(this), options),
					previousText: ''
				});
				if (state.options.multiple && state.options.value == ''){
					state.options.originalValue = [];
				} else {
					state.options.originalValue = state.options.value;
				}
			}
			
			buildCombo(this);
			initValue(this);
		});
	};
	
	$.fn.combo.methods = {
		options: function(jq){
			var opts = jq.textbox('options');
			return $.extend($.data(jq[0], 'combo').options, {
				width: opts.width,
				height: opts.height,
				disabled: opts.disabled,
				readonly: opts.readonly,
				editable: opts.editable
			});
		},
		cloneFrom: function(jq, from){
			return jq.each(function(){
				$(this).textbox('cloneFrom', from);
				$.data(this, 'combo', {
					options: $.extend(true, {cloned:true}, $(from).combo('options')),
					combo: $(this).next(),
					panel: $(from).combo('panel')
				});
				$(this).addClass('combo-f').attr('comboName', $(this).attr('textboxName'));
			});
		},
		combo: function(jq){
			return jq.closest('.combo-panel').panel('options').comboTarget;
		},
		panel: function(jq){
			return $.data(jq[0], 'combo').panel;
		},
		destroy: function(jq){
			return jq.each(function(){
				destroy(this);
			});
		},
		showPanel: function(jq){
			return jq.each(function(){
				showPanel(this);
			});
		},
		hidePanel: function(jq){
			return jq.each(function(){
				hidePanel(this);
			});
		},
		clear: function(jq){
			return jq.each(function(){
//				clear(this);
				$(this).textbox('setText', '');
				var opts = $.data(this, 'combo').options;
				if (opts.multiple){
					$(this).combo('setValues', []);
				} else {
					$(this).combo('setValue', '');
				}
			});
		},
		reset: function(jq){
			return jq.each(function(){
				var opts = $.data(this, 'combo').options;
				if (opts.multiple){
					$(this).combo('setValues', opts.originalValue);
				} else {
					$(this).combo('setValue', opts.originalValue);
				}
			});
		},
		setText: function(jq, text){
			return jq.each(function(){
				setText(this, text);
			});
		},
		getValues: function(jq){
			return getValues(jq[0]);
		},
		setValues: function(jq, values){
			return jq.each(function(){
				setValues(this, values);
			});
		},
		getValue: function(jq){
			return getValue(jq[0]);
		},
		setValue: function(jq, value){
			return jq.each(function(){
				setValue(this, value);
			});
		}
	};
	
	$.fn.combo.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.fn.textbox.parseOptions(target), $.parser.parseOptions(target, [
			'separator','panelAlign',
			{panelWidth:'number',hasDownArrow:'boolean',delay:'number',reversed:'boolean',multivalue:'boolean',selectOnNavigation:'boolean'},
			{panelMinWidth:'number',panelMaxWidth:'number',panelMinHeight:'number',panelMaxHeight:'number'}
		]), {
			panelHeight: (t.attr('panelHeight')=='auto' ? 'auto' : parseInt(t.attr('panelHeight')) || undefined),
			multiple: (t.attr('multiple') ? true : undefined)
		});
	};
	
	// Inherited from $.fn.textbox.defaults
	$.fn.combo.defaults = $.extend({}, $.fn.textbox.defaults, {
		inputEvents: {
			click: inputClickHandler,
			keydown: inputEventHandler,
			paste: inputEventHandler,
			drop: inputEventHandler,
			blur: blurEventHandler
		},
		panelEvents: {
			mousedown: function(e){
				e.preventDefault();
				e.stopPropagation();
			}
		},
		
		panelWidth: null,
		panelHeight: 300,
		panelMinWidth: null,
		panelMaxWidth: null,
		panelMinHeight: null,
		panelMaxHeight: null,
		panelAlign: 'left',
		panelValign: 'auto',
		reversed: false,
		multiple: false,
		multivalue: true,
		selectOnNavigation: true,
		separator: ',',
		hasDownArrow: true,
		delay: 200,	// delay to do searching from the last key input event.
		
		keyHandler: {
			up: function(e){},
			down: function(e){},
			left: function(e){},
			right: function(e){},
			enter: function(e){},
			query: function(q,e){}
		},
		
		onShowPanel: function(){},
		onHidePanel: function(){},
		onChange: function(newValue, oldValue){}
	});
})(jQuery);
