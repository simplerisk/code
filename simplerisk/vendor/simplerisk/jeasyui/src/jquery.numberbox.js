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
 * numberbox - EasyUI for jQuery
 * 
 * Dependencies:
 * 	 textbox
 * 
 */
(function($){
	function buildNumberBox(target){
		var state = $.data(target, 'numberbox');
		var opts = state.options;
		$(target).addClass('numberbox-f').textbox(opts);
		$(target).textbox('textbox').css({imeMode:"disabled"});
		$(target).attr('numberboxName', $(target).attr('textboxName'));
		state.numberbox = $(target).next();
		state.numberbox.addClass('numberbox');
		
		var initValue = opts.parser.call(target, opts.value);
		var initText = opts.formatter.call(target, initValue);
		$(target).numberbox('initValue', initValue).numberbox('setText', initText);
	}
	
	function setValue(target, value){
		var state = $.data(target, 'numberbox');
		var opts = state.options;
		opts.value = parseFloat(value);
		var value = opts.parser.call(target, value);
		var text = opts.formatter.call(target, value);
		opts.value = value;
		$(target).textbox('setText', text).textbox('setValue', value);
		text = opts.formatter.call(target, $(target).textbox('getValue'));
		$(target).textbox('setText', text);
	}
	
	$.fn.numberbox = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.numberbox.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.textbox(options, param);
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'numberbox');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'numberbox', {
					options: $.extend({}, $.fn.numberbox.defaults, $.fn.numberbox.parseOptions(this), options)
				});
			}
			buildNumberBox(this);
		});
	};
	
	$.fn.numberbox.methods = {
		options: function(jq){
			var opts = jq.data('textbox') ? jq.textbox('options') : {};
			return $.extend($.data(jq[0], 'numberbox').options, {
				width: opts.width,
				originalValue: opts.originalValue,
				disabled: opts.disabled,
				readonly: opts.readonly
			});
		},
		cloneFrom: function(jq, from){
			return jq.each(function(){
				$(this).textbox('cloneFrom', from);
				$.data(this, 'numberbox', {
					options: $.extend(true, {}, $(from).numberbox('options'))
				});
				$(this).addClass('numberbox-f');
			});
		},
		fix: function(jq){
			return jq.each(function(){
				var opts = $(this).numberbox('options');
				opts.value = null;
				var value = opts.parser.call(this, $(this).numberbox('getText'));
				$(this).numberbox('setValue', value);
			});
		},
		setValue: function(jq, value){
			return jq.each(function(){
				setValue(this, value);
			});
		},
		clear: function(jq){
			return jq.each(function(){
				$(this).textbox('clear');
				$(this).numberbox('options').value = '';
			});
		},
		reset: function(jq){
			return jq.each(function(){
				$(this).textbox('reset');
				$(this).numberbox('setValue', $(this).numberbox('getValue'));
			});
		}
	};
	
	$.fn.numberbox.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.fn.textbox.parseOptions(target), $.parser.parseOptions(target, [
			'decimalSeparator','groupSeparator','suffix',
			{min:'number',max:'number',precision:'number'}
		]), {
			prefix: (t.attr('prefix') ? t.attr('prefix') : undefined)
		});
	};
	
	// Inherited from $.fn.textbox.defaults
	$.fn.numberbox.defaults = $.extend({}, $.fn.textbox.defaults, {
		inputEvents: {
			keypress:function(e){
				var target = e.data.target;
				var opts = $(target).numberbox('options');
				return opts.filter.call(target, e);
			},
			blur:function(e){
				$(e.data.target).numberbox('fix');
			},
			keydown: function(e){
				if (e.keyCode == 13){
					$(e.data.target).numberbox('fix');
				}
			}
		},
		min: null,
		max: null,
		precision: 0,
		decimalSeparator: '.',
		groupSeparator: '',
		prefix: '',
		suffix: '',
		
		filter: function(e){
			var opts = $(this).numberbox('options');
			var s = $(this).numberbox('getText');
			if (e.metaKey || e.ctrlKey){
				return true;
			}
			if ($.inArray(String(e.which), ['46','8','13','0']) >= 0){	// DELETE BACKSPACE ENTER
				return true;
			}
			var tmp = $('<span></span>');
			tmp.html(String.fromCharCode(e.which));
			var c = tmp.text();
			tmp.remove();
			if (!c){
				return true;
			}
			if (c == '-' && opts.min != null && opts.min >= 0){
				return false;
			}
			if (c == '-' || c == opts.decimalSeparator){
				return (s.indexOf(c) == -1) ? true : false;
			} else if (c == opts.groupSeparator){
				return true;
			} else if ('0123456789'.indexOf(c) >= 0){
				return true;
			} else {
				return false;
			}
		},
		formatter: function(value){
			if (!value) return value;
			
			value = value + '';
			var opts = $(this).numberbox('options');
			var s1 = value, s2 = '';
			var dpos = value.indexOf('.');
			if (dpos >= 0){
				s1 = value.substring(0, dpos);
				s2 = value.substring(dpos+1, value.length);
			}
			if (opts.groupSeparator){
				var p = /(\d+)(\d{3})/;
				while(p.test(s1)){
					s1 = s1.replace(p, '$1' + opts.groupSeparator + '$2');
				}
			}
			if (s2){
				return opts.prefix + s1 + opts.decimalSeparator + s2 + opts.suffix;
			} else {
				return opts.prefix + s1 + opts.suffix;
			}
		},
		parser: function(s){
			s = s + '';
			var opts = $(this).numberbox('options');
			// if (parseFloat(s) != s){
			// 	if (opts.prefix) s = $.trim(s.replace(new RegExp('\\'+$.trim(opts.prefix),'g'), ''));
			// 	if (opts.suffix) s = $.trim(s.replace(new RegExp('\\'+$.trim(opts.suffix),'g'), ''));
			// 	if (opts.groupSeparator) s = $.trim(s.replace(new RegExp('\\'+opts.groupSeparator,'g'), ''));
			// 	if (opts.decimalSeparator) s = $.trim(s.replace(new RegExp('\\'+opts.decimalSeparator,'g'), '.'));
			// 	s = s.replace(/\s/g,'');
			// }
			if (opts.prefix) s = $.trim(s.replace(new RegExp('\\'+$.trim(opts.prefix),'g'), ''));
			if (opts.suffix) s = $.trim(s.replace(new RegExp('\\'+$.trim(opts.suffix),'g'), ''));
			if (parseFloat(s) != opts.value){
				if (opts.groupSeparator) s = $.trim(s.replace(new RegExp('\\'+opts.groupSeparator,'g'), ''));
				if (opts.decimalSeparator) s = $.trim(s.replace(new RegExp('\\'+opts.decimalSeparator,'g'), '.'));
				s = s.replace(/\s/g,'');
			}
			var val = parseFloat(s).toFixed(opts.precision);
			if (isNaN(val)) {
				val = '';
			} else if (typeof(opts.min) == 'number' && val < opts.min) {
				val = opts.min.toFixed(opts.precision);
			} else if (typeof(opts.max) == 'number' && val > opts.max) {
				val = opts.max.toFixed(opts.precision);
			}
			return val;
		}
	});
})(jQuery);
