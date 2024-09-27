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
 * radiobutton - EasyUI for jQuery
 * 
 * Dependencies:
 * 	 none
 * 
 */
(function($){
	var RADIOBUTTON_SERNO = 1;

	function init(target){
		var button = $(
			'<span class="radiobutton inputbox">' +
			'<span class="radiobutton-inner" style="display:none"></span>' +
			'<input type="radio" class="radiobutton-value">' +
			'</span>'
		).insertAfter(target);
		var t = $(target);
		t.addClass('radiobutton-f').hide();
		var name = t.attr('name');
		if (name){
			t.removeAttr('name').attr('radiobuttonName', name);
			button.find('.radiobutton-value').attr('name', name);
		}
		return button;
	}

	function buildButton(target){
		var state = $.data(target, 'radiobutton');
		var opts = state.options;
		var button = state.radiobutton;
		var inputId = '_easyui_radiobutton_' + (++RADIOBUTTON_SERNO);
		var rvalue = button.find('.radiobutton-value').attr('id', inputId);
		rvalue._unbind('.radiobutton')._bind('change.radiobutton', function(e){
			return false;
		});

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
		$(target).radiobutton('setValue', opts.value);
		setChecked(target, opts.checked);
		setReadonly(target, opts.readonly);
		setDisabled(target, opts.disabled);
	}

	function bindEvents(target){
		var state = $.data(target, 'radiobutton');
		var opts = state.options;
		var button = state.radiobutton;
		button._unbind('.radiobutton')._bind('click.radiobutton', function(){
			if (!opts.disabled && !opts.readonly){
				setChecked(target, true);
			}
		});
	}

	function setSize(target){
		var state = $.data(target, 'radiobutton');
		var opts = state.options;
		var button = state.radiobutton;
		button._size(opts, button.parent());
		if (opts.label && opts.labelPosition){
			if (opts.labelPosition == 'top'){
				state.label._size({width:opts.labelWidth}, button);
			} else {
				state.label._size({width:opts.labelWidth,height:button.outerHeight()}, button);
				state.label.css('lineHeight', button.outerHeight()+'px');
			}
		}
	}

	function setChecked(target, checked){
		if (checked){
			var f = $(target).closest('form');
			var name = $(target).attr('radiobuttonName');
			f.find('.radiobutton-f[radiobuttonName="'+name+'"]').each(function(){
				if (this != target){
					_checked(this, false);
				}
			});
			_checked(target, true);
		} else {
			_checked(target, false);
		}

		function _checked(b,c){
			var state = $(b).data('radiobutton');
			var opts = state.options;
			var button = state.radiobutton;
			button.find('.radiobutton-inner').css('display', c?'':'none');
			button.find('.radiobutton-value')._propAttr('checked', c);
			if (c){
				button.addClass('radiobutton-checked');
				$(state.label).addClass('textbox-label-checked');
			} else {
				button.removeClass('radiobutton-checked');
				$(state.label).removeClass('textbox-label-checked');
			}
			if (opts.checked != c){
				opts.checked = c;
				opts.onChange.call($(b)[0], c);
				$(b).closest('form').trigger('_change', [$(b)[0]]);
			}
		}
	}

	function setDisabled(target, disabled){
		var state = $.data(target, 'radiobutton');
		var opts = state.options;
		var button = state.radiobutton;
		var rv = button.find('.radiobutton-value');
		opts.disabled = disabled;
		if (disabled){
			$(target).add(rv)._propAttr('disabled', true);
			button.addClass('radiobutton-disabled');
			$(state.label).addClass('textbox-label-disabled');
		} else {
			$(target).add(rv)._propAttr('disabled', false);
			button.removeClass('radiobutton-disabled');
			$(state.label).removeClass('textbox-label-disabled');
		}
	}

	function setReadonly(target, mode){
		var state = $.data(target, 'radiobutton');
		var opts = state.options;
		opts.readonly = mode==undefined ? true : mode;
		if (opts.readonly){
			state.radiobutton.addClass('radiobutton-readonly');
			$(state.label).addClass('textbox-label-readonly');
		} else {
			state.radiobutton.removeClass('radiobutton-readonly');
			$(state.label).removeClass('textbox-label-readonly');
		}
	}

	$.fn.radiobutton = function(options, param){
		if (typeof options == 'string'){
			return $.fn.radiobutton.methods[options](this, param);
		}
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'radiobutton');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'radiobutton', {
					options: $.extend({}, $.fn.radiobutton.defaults, $.fn.radiobutton.parseOptions(this), options),
					radiobutton: init(this)
				});
			}
			state.options.originalChecked = state.options.checked;
			buildButton(this);
			bindEvents(this);
			setSize(this);
		});
	};

	$.fn.radiobutton.methods = {
		options: function(jq){
			var state = jq.data('radiobutton');
			return $.extend(state.options, {
				value: state.radiobutton.find('.radiobutton-value').val()
			});
		},
		setValue: function(jq, value){
			return jq.each(function(){
				$(this).val(value);
				$.data(this, 'radiobutton').radiobutton.find('.radiobutton-value').val(value);
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
				setChecked(this, true);
			});
		},
		uncheck: function(jq){
			return jq.each(function(){
				setChecked(this, false);
			});
		},
		clear: function(jq){
			return jq.each(function(){
				setChecked(this, false);
			});
		},
		reset: function(jq){
			return jq.each(function(){
				var opts = $(this).radiobutton('options');
				setChecked(this, opts.originalChecked);
			});
		}
	};

	$.fn.radiobutton.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.parser.parseOptions(target, [
			'label','labelPosition','labelAlign',{labelWidth:'number'}
		]), {
			value: (t.val() || undefined),
			checked: (t.attr('checked') ? true : undefined),
			disabled: (t.attr('disabled') ? true : undefined),
			readonly: (t.attr('readonly') ? true : undefined)
		});
	};

	$.fn.radiobutton.defaults = {
		width: 20,
		height: 20,
		value: null,
		disabled: false,
		readonly: false,
		checked: false,
		label:null,
		labelWidth:'auto',
		labelPosition:'before',	// before,after,top
		labelAlign:'left',	// left, right
		onChange: function(checked){}
	};
})(jQuery);
