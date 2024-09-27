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
 * switchbutton - EasyUI for jQuery
 */
(function($){
	var SWITCHBUTTON_SEARNO = 1;

	function init(target){
		var button = $(
				'<span class="switchbutton">' +
				'<span class="switchbutton-inner">' +
				'<span class="switchbutton-on"></span>' +
				'<span class="switchbutton-handle"></span>' +
				'<span class="switchbutton-off"></span>' +
				'<input class="switchbutton-value" type="checkbox" tabindex="-1">' +
				'</span>' +
				'</span>').insertAfter(target);
		var t = $(target);
		t.addClass('switchbutton-f').hide();
		var name = t.attr('name');
		if (name){
			t.removeAttr('name').attr('switchbuttonName', name);
			button.find('.switchbutton-value').attr('name', name);
		}
		button._bind('_resize', function(e,force){
			if ($(this).hasClass('easyui-fluid') || force){
				setSize(target);
			}
			return false;
		});
		return button;
	}
	
	function setSize(target, param){
		var state = $.data(target, 'switchbutton');
		var opts = state.options;
		var button = state.switchbutton;
		if (param){
			$.extend(opts, param);
		}
		var isVisible = button.is(':visible');
		if (!isVisible){
			button.appendTo('body');
		}
		button._size(opts);
		if (opts.label && opts.labelPosition){
			if (opts.labelPosition == 'top'){
				state.label._size({width:opts.labelWidth}, button);
			} else {
				state.label._size({width:opts.labelWidth,height:button.outerHeight()}, button);
				state.label.css('lineHeight', button.outerHeight()+'px');
			}
		}
		var w = button.width();
		var h = button.height();
		var w = button.outerWidth();
		var h = button.outerHeight();
		var handleWidth = parseInt(opts.handleWidth) || button.height();
		var innerWidth = w * 2 - handleWidth;
		button.find('.switchbutton-inner').css({
			width: innerWidth+'px',
			height: h+'px',
			lineHeight: h+'px'
		});
		button.find('.switchbutton-handle')._outerWidth(handleWidth)._outerHeight(h).css({
			marginLeft: -handleWidth/2+'px'
		});
		button.find('.switchbutton-on').css({
			width: (w - handleWidth/2)+'px',
			textIndent: (opts.reversed ? '' : '-')+handleWidth/2+'px'
		});
		button.find('.switchbutton-off').css({
			width: (w - handleWidth/2)+'px',
			textIndent: (opts.reversed ? '-' : '')+handleWidth/2+'px'
		});
		opts.marginWidth = w - handleWidth;
		checkButton(target, opts.checked, false);
		if (!isVisible){
			button.insertAfter(target);
		}
	}
	
	function createButton(target){
		var state = $.data(target, 'switchbutton');
		var opts = state.options;
		var button = state.switchbutton;
		var inner = button.find('.switchbutton-inner');
		var on = inner.find('.switchbutton-on').html(opts.onText);
		var off = inner.find('.switchbutton-off').html(opts.offText);
		var handle = inner.find('.switchbutton-handle').html(opts.handleText);
		if (opts.reversed){
			off.prependTo(inner);
			on.insertAfter(handle);
		} else {
			on.prependTo(inner);
			off.insertAfter(handle);
		}
		var inputId = '_easyui_switchbutton_' + (++SWITCHBUTTON_SEARNO);
		var svalue = button.find('.switchbutton-value')._propAttr('checked', opts.checked).attr('id', inputId);
		svalue._unbind('.switchbutton')._bind('change.switchbutton', function(e){
			return false;
		});
		button.removeClass('switchbutton-reversed').addClass(opts.reversed ? 'switchbutton-reversed' : '');

		if (opts.label){
			if (typeof opts.label == 'object'){
				state.label = $(opts.label);
				state.label.attr('for', inputId);
			} else {
				$(state.label).remove();
				state.label = $('<label class="textbox-label"></label>').html(opts.label);
				state.label.css('textAlign', opts.labelAlign).attr('for',inputId);
				if (opts.labelPosition == 'after'){
					state.label.insertAfter(button);
				} else {
					state.label.insertBefore(target);
				}
				state.label.removeClass('textbox-label-left textbox-label-right textbox-label-top');
				state.label.addClass('textbox-label-'+opts.labelPosition)
			}
		} else {
			$(state.label).remove();
		}
		
		checkButton(target, opts.checked);
		setReadonly(target, opts.readonly);
		setDisabled(target, opts.disabled);
		$(target).switchbutton('setValue', opts.value);
	}
	
	// function checkButton(target, checked, animate){
	// 	var state = $.data(target, 'switchbutton');
	// 	var opts = state.options;
	// 	opts.checked = checked;
	// 	var inner = state.switchbutton.find('.switchbutton-inner');
	// 	var labelOn = inner.find('.switchbutton-on');
	// 	var margin = opts.reversed ? (opts.checked?opts.marginWidth:0) : (opts.checked?0:opts.marginWidth);
	// 	var dir = labelOn.css('float').toLowerCase();
	// 	var css = {};
	// 	css['margin-'+dir] = -margin+'px';
	// 	animate ? inner.animate(css, 200) : inner.css(css);
	// 	var input = inner.find('.switchbutton-value');
	// 	var ck = input.is(':checked');
	// 	$(target).add(input)._propAttr('checked', opts.checked);
	// 	if (ck != opts.checked){
	// 		opts.onChange.call(target, opts.checked);
	// 		$(target).closest('form').trigger('_change', [target]);
	// 	}
	// }
	function checkButton(target, checked, animate){
		var state = $.data(target, 'switchbutton');
		var opts = state.options;
		var inner = state.switchbutton.find('.switchbutton-inner');
		var labelOn = inner.find('.switchbutton-on');
		var margin = opts.reversed ? (checked?opts.marginWidth:0) : (checked?0:opts.marginWidth);
		var dir = labelOn.css('float').toLowerCase();
		var css = {};
		css['margin-'+dir] = -margin+'px';
		animate ? inner.animate(css, 200) : inner.css(css);
		var input = inner.find('.switchbutton-value');
		$(target).add(input)._propAttr('checked', checked);

		if (opts.checked != checked){
			opts.checked = checked;
			opts.onChange.call(target, opts.checked);
			$(target).closest('form').trigger('_change', [target]);
		}
	}
	
	function setDisabled(target, disabled){
		var state = $.data(target, 'switchbutton');
		var opts = state.options;
		var button = state.switchbutton;
		var input = button.find('.switchbutton-value');
		if (disabled){
			opts.disabled = true;
			// $(target).add(input).attr('disabled', 'disabled');
			$(target).add(input)._propAttr('disabled', true);
			button.addClass('switchbutton-disabled');
			button.removeAttr('tabindex');
		} else {
			opts.disabled = false;
			// $(target).add(input).removeAttr('disabled');
			$(target).add(input)._propAttr('disabled', false);
			button.removeClass('switchbutton-disabled');
			button.attr('tabindex', $(target).attr('tabindex')||'');
		}
	}
	
	function setReadonly(target, mode){
		var state = $.data(target, 'switchbutton');
		var opts = state.options;
		opts.readonly = mode==undefined ? true : mode;
		state.switchbutton.removeClass('switchbutton-readonly').addClass(opts.readonly ? 'switchbutton-readonly' : '');
	}
	
	function bindEvents(target){
		var state = $.data(target, 'switchbutton');
		var opts = state.options;
		state.switchbutton._unbind('.switchbutton')._bind('click.switchbutton', function(){
			if (!opts.disabled && !opts.readonly){
				checkButton(target, opts.checked ? false : true, true);
			}
		})._bind('keydown.switchbutton', function(e){
			if (e.which == 13 || e.which == 32){
				if (!opts.disabled && !opts.readonly){
					checkButton(target, opts.checked ? false : true, true);
					return false;
				}
			}
		});
	}
	
	$.fn.switchbutton = function(options, param){
		if (typeof options == 'string'){
			return $.fn.switchbutton.methods[options](this, param);
		}
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'switchbutton');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'switchbutton', {
					options: $.extend({}, $.fn.switchbutton.defaults, $.fn.switchbutton.parseOptions(this), options),
					switchbutton: init(this)
				});
			}
			state.options.originalChecked = state.options.checked;
			createButton(this);
			setSize(this);
			bindEvents(this);
		});
	};
	
	$.fn.switchbutton.methods = {
		options: function(jq){
			var state = jq.data('switchbutton');
			return $.extend(state.options, {
				value: state.switchbutton.find('.switchbutton-value').val()
			});
		},
		resize: function(jq, param){
			return jq.each(function(){
				setSize(this, param);
			});
		},
		enable: function(jq){
			return jq.each(function(){
				setDisabled(this, false);
			});
		},
		disable: function(jq){
			return jq.each(function(){
				setDisabled(this, true);
			});
		},
		readonly: function(jq, mode){
			return jq.each(function(){
				setReadonly(this, mode);
			});
		},
		check: function(jq){
			return jq.each(function(){
				checkButton(this, true);
			});
		},
		uncheck: function(jq){
			return jq.each(function(){
				checkButton(this, false);
			});
		},
		clear: function(jq){
			return jq.each(function(){
				checkButton(this, false);
			});
		},
		reset: function(jq){
			return jq.each(function(){
				var opts = $(this).switchbutton('options');
				checkButton(this, opts.originalChecked);
			});
		},
		setValue: function(jq, value){
			return jq.each(function(){
				$(this).val(value);
				$.data(this, 'switchbutton').switchbutton.find('.switchbutton-value').val(value);
			});
		}
	};
	
	$.fn.switchbutton.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.parser.parseOptions(target, [
		     'onText','offText','handleText',{handleWidth:'number',reversed:'boolean'},
		     'label','labelPosition','labelAlign',{labelWidth:'number'}
		]), {
			value: (t.val() || undefined),
			checked: (t.attr('checked') ? true : undefined),
			disabled: (t.attr('disabled') ? true : undefined),
			readonly: (t.attr('readonly') ? true : undefined)
		});
	};
	
	$.fn.switchbutton.defaults = {
		handleWidth: 'auto',
		width: 60,
		height: 30,
		checked: false,
		disabled: false,
		readonly: false,
		reversed: false,
		onText: 'ON',
		offText: 'OFF',
		handleText: '',
		value: 'on',
		label:null,
		labelWidth:'auto',
		labelPosition:'before',	// before,after,top
		labelAlign:'left',	// left, right
		onChange: function(checked){}
	};
})(jQuery);
