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
 * checkbox - EasyUI for jQuery
 * 
 * Dependencies:
 * 	 none
 * 
 */
(function($){
	var CHECKBOX_SEARNO = 1;

	function init(target){
		var checkbox = $(
			'<span class="checkbox inputbox">' +
			'<span class="checkbox-inner">' +
			'<svg xml:space="preserve" focusable="false" version="1.1" viewBox="0 0 24 24"><path d="M4.1,12.7 9,17.6 20.3,6.3" fill="none" stroke="white"></path></svg>' +
			'</span>' +
			'<input type="checkbox" class="checkbox-value">' +
			'</span>'
		).insertAfter(target);
		var t = $(target);
		t.addClass('checkbox-f').hide();
		var name = t.attr('name');
		if (name){
			t.removeAttr('name').attr('checkboxName', name);
			checkbox.find('.checkbox-value').attr('name', name);
		}
		return checkbox;
	}
	
	function buildBox(target){
		var state = $.data(target, 'checkbox');
		var opts = state.options;
		var checkbox = state.checkbox;
		var inputId = '_easyui_checkbox_' + (++CHECKBOX_SEARNO);
		var cvalue = checkbox.find('.checkbox-value').attr('id', inputId);
		cvalue._unbind('.checkbox')._bind('change.checkbox', function(e){
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
					state.label.insertAfter(checkbox);
				} else {
					state.label.insertBefore(target);
				}
				state.label.removeClass('textbox-label-left textbox-label-right textbox-label-top');
				state.label.addClass('textbox-label-'+opts.labelPosition)
			}
		} else {
			$(state.label).remove();
		}
		$(target).checkbox('setValue', opts.value);
		setChecked(target, opts.checked);
		setReadonly(target, opts.readonly);
		setDisabled(target, opts.disabled);
	}

	function bindEvents(target){
		var state = $.data(target, 'checkbox');
		var opts = state.options;
		var button = state.checkbox;
		button._unbind('.checkbox')._bind('click.checkbox', function(){
			if (!opts.disabled && !opts.readonly){
				setChecked(target, !opts.checked);
			}
		});
	}

	function setSize(target){
		var state = $.data(target, 'checkbox');
		var opts = state.options;
		var button = state.checkbox;
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
		var state = $.data(target, 'checkbox');
		var opts = state.options;
		var checkbox = state.checkbox;
		checkbox.find('.checkbox-value')._propAttr('checked', checked);
		var inner = checkbox.find('.checkbox-inner').css('display', checked?'':'none');
		if (checked){
			// inner.addClass('checkbox-checked');
			checkbox.addClass('checkbox-checked');
			$(state.label).addClass('textbox-label-checked');
		} else {
			// inner.removeClass('checkbox-checked');
			checkbox.removeClass('checkbox-checked');
			$(state.label).removeClass('textbox-label-checked');
		}
		if (opts.checked != checked){
			opts.checked = checked;
			opts.onChange.call(target, checked);
			$(target).closest('form').trigger('_change', [target]);
		}
	}
	
	function setReadonly(target, mode){
		var state = $.data(target, 'checkbox');
		var opts = state.options;
		opts.readonly = mode==undefined ? true : mode;
		if (opts.readonly){
			state.checkbox.addClass('checkbox-readonly');
			$(state.label).addClass('textbox-label-readonly');
		} else {
			state.checkbox.removeClass('checkbox-readonly');
			$(state.label).removeClass('textbox-label-readonly');
		}
	}

	function setDisabled(target, disabled){
		var state = $.data(target, 'checkbox');
		var opts = state.options;
		var button = state.checkbox;
		var rv = button.find('.checkbox-value');
		opts.disabled = disabled;
		if (disabled){
			$(target).add(rv)._propAttr('disabled', true);
			button.addClass('checkbox-disabled');
			$(state.label).addClass('textbox-label-disabled');
		} else {
			$(target).add(rv)._propAttr('disabled', false);
			button.removeClass('checkbox-disabled');
			$(state.label).removeClass('textbox-label-disabled');
		}
	}

	$.fn.checkbox = function(options, param){
		if (typeof options == 'string'){
			return $.fn.checkbox.methods[options](this, param);
		}
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'checkbox');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'checkbox', {
					options: $.extend({}, $.fn.checkbox.defaults, $.fn.checkbox.parseOptions(this), options),
					checkbox: init(this)
				});
			}
			state.options.originalChecked = state.options.checked;
			buildBox(this);
			bindEvents(this);
			setSize(this);
		});
	};

	$.fn.checkbox.methods = {
		options: function(jq){
			var state = jq.data('checkbox');
			return $.extend(state.options, {
				value: state.checkbox.find('.checkbox-value').val()
			});
		},
		setValue: function(jq, value){
			return jq.each(function(){
				$(this).val(value);
				$.data(this, 'checkbox').checkbox.find('.checkbox-value').val(value);
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
				var opts = $(this).checkbox('options');
				setChecked(this, opts.originalChecked);
			});
		}

	};

	$.fn.checkbox.parseOptions = function(target){
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

	$.fn.checkbox.defaults = {
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
