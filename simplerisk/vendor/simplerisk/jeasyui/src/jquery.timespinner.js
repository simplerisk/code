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
 * timespinner - EasyUI for jQuery
 * 
 * Dependencies:
 *   spinner
 * 
 */
(function($){
	function create(target){
		var opts = $.data(target, 'timespinner').options;
		$(target).addClass('timespinner-f').spinner(opts);
		var initValue = opts.formatter.call(target, opts.parser.call(target, opts.value));
		$(target).timespinner('initValue', initValue);
	}
	
	function clickHandler(e){
		var target = e.data.target;
		var opts = $.data(target, 'timespinner').options;
		var start = $(target).timespinner('getSelectionStart');
		for(var i=0; i<opts.selections.length; i++){
			var range = opts.selections[i];
			if (start >= range[0] && start <= range[1]){
				highlight(target, i);
				return;
			}
		}
	}
	
	/**
	 * highlight the hours or minutes or seconds.
	 */
	function highlight(target, index){
		var opts = $.data(target, 'timespinner').options;
		if (index != undefined){
			opts.highlight = index;
		}
		var range = opts.selections[opts.highlight];
		if (range){
			var tb = $(target).timespinner('textbox');
			$(target).timespinner('setSelectionRange', {start:range[0],end:range[1]});
			tb.focus();
		}
	}
	
	function setValue(target, value){
		var opts = $.data(target, 'timespinner').options;
		var value = opts.parser.call(target, value);
		var text = opts.formatter.call(target, value);
		$(target).spinner('setValue', text);
	}
	
	function doSpin(target, down){
		var opts = $.data(target, 'timespinner').options;
		var s = $(target).timespinner('getValue');
		var range = opts.selections[opts.highlight];
		var s1 = s.substring(0, range[0]);
		var s2 = s.substring(range[0], range[1]);
		var s3 = s.substring(range[1]);
		if (s2 == opts.ampm[0]){
			s2 = opts.ampm[1];
		} else if (s2 == opts.ampm[1]){
			s2 = opts.ampm[0];
		} else {
			s2 = parseInt(s2,10)||0;
			if (opts.selections.length-4 == opts.highlight && opts.hour12){
				if (s2 == 12){
					s2 = 0;
				} else if (s2 == 11 && !down){
					var tmp = s3.replace(opts.ampm[0],opts.ampm[1]);
					if (s3 != tmp){
						s3 = tmp
					} else {
						s3 = s3.replace(opts.ampm[1],opts.ampm[0]);
					}
				}
			}
			s2 = s2 + opts.increment*(down?-1:1);
			// s2 = (parseInt(s2,10)||0) + opts.increment*(down?-1:1);
		}
		var v = s1 + s2 + s3;
		$(target).timespinner('setValue', v);
		highlight(target);
	}
	
	$.fn.timespinner = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.timespinner.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.spinner(options, param);
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'timespinner');
			if (state){
				$.extend(state.options, options);
			} else {
				$.data(this, 'timespinner', {
					options: $.extend({}, $.fn.timespinner.defaults, $.fn.timespinner.parseOptions(this), options)
				});
			}
			create(this);
		});
	};
	
	$.fn.timespinner.methods = {
		options: function(jq){
			var opts = jq.data('spinner') ? jq.spinner('options') : {};
			return $.extend($.data(jq[0], 'timespinner').options, {
				width: opts.width,
				value: opts.value,
				originalValue: opts.originalValue,
				disabled: opts.disabled,
				readonly: opts.readonly
			});
		},
		setValue: function(jq, value){
			return jq.each(function(){
				setValue(this, value);
			});
		},
		// getHours: function(jq){
		// 	var opts = $.data(jq[0], 'timespinner').options;
		// 	var vv = jq.timespinner('getValue').split(opts.separator);
		// 	return parseInt(vv[0], 10);
		// },
		// getMinutes: function(jq){
		// 	var opts = $.data(jq[0], 'timespinner').options;
		// 	var vv = jq.timespinner('getValue').split(opts.separator);
		// 	return parseInt(vv[1], 10);
		// },
		// getSeconds: function(jq){
		// 	var opts = $.data(jq[0], 'timespinner').options;
		// 	var vv = jq.timespinner('getValue').split(opts.separator);
		// 	return parseInt(vv[2], 10) || 0;
		// },
		getHours: function(jq){
			var opts = $.data(jq[0], 'timespinner').options;
			var date = opts.parser.call(jq[0], jq.timespinner('getValue'));
			return date ? date.getHours() : null;
		},
		getMinutes: function(jq){
			var opts = $.data(jq[0], 'timespinner').options;
			var date = opts.parser.call(jq[0], jq.timespinner('getValue'));
			return date ? date.getMinutes() : null;
		},
		getSeconds: function(jq){
			var opts = $.data(jq[0], 'timespinner').options;
			var date = opts.parser.call(jq[0], jq.timespinner('getValue'));
			return date ? date.getSeconds() : null;
		}
	};
	
	$.fn.timespinner.parseOptions = function(target){
		return $.extend({}, $.fn.spinner.parseOptions(target), $.parser.parseOptions(target,[
			'separator',{hour12:'boolean',showSeconds:'boolean',highlight:'number'}
		]));
	};
	
	$.fn.timespinner.defaults = $.extend({}, $.fn.spinner.defaults, {
		inputEvents: $.extend({}, $.fn.spinner.defaults.inputEvents, {
			click: function(e){
				clickHandler.call(this, e);
			},
			blur: function(e){
				var t = $(e.data.target);
				t.timespinner('setValue', t.timespinner('getText'));
			},
			keydown: function(e){
				if (e.keyCode == 13){
					var t = $(e.data.target);
					t.timespinner('setValue', t.timespinner('getText'));
				}
			}
		}),
		formatter: function(date){
			if (!date){return '';}
			var opts = $(this).timespinner('options');
			var hour = date.getHours();
			var minute = date.getMinutes();
			var second = date.getSeconds();
			var ampm = '';
			if (opts.hour12){
				ampm = hour >= 12 ? opts.ampm[1] : opts.ampm[0];
				hour = hour % 12;
				if (hour == 0){
					hour = 12;
				}
			}
			var tt = [formatN(hour), formatN(minute)];
			if (opts.showSeconds){
				tt.push(formatN(second));
			}
			var s = tt.join(opts.separator) + ' ' + ampm;
			return $.trim(s);
			
			function formatN(value){
				return (value < 10 ? '0' : '') + value;
			}
		},
		parser: function(s){
			var opts = $(this).timespinner('options');
			var date = parseD(s);
			if (date){
				var min = parseD(opts.min);
				var max = parseD(opts.max);
				if (min && min > date){date = min;}
				if (max && max < date){date = max;}
			}
			return date;
			
			function parseD(s){
				if (!s){return null;}
				var ss = s.split(' ');
				var tt = ss[0].split(opts.separator);
				var hour = parseInt(tt[0], 10) || 0;
				var minute = parseInt(tt[1], 10) || 0;
				var second = parseInt(tt[2], 10) || 0;
				if (opts.hour12){
					var ampm = ss[1];
					if (ampm == opts.ampm[1] && hour < 12){
						hour += 12;
					} else if (ampm == opts.ampm[0] && hour == 12){
						hour -= 12;
					}
				}
				return new Date(1900,0,0,hour,minute,second);
			}
		},
		selections:[[0,2],[3,5],[6,8],[9,11]],
		separator: ':',
		showSeconds: false,
		highlight: 0,	// The field to highlight initially, 0 = hours, 1 = minutes, ...
		hour12: false,
		ampm: ['AM','PM'],
		spin: function(down){doSpin(this, down);}
	});
})(jQuery);
