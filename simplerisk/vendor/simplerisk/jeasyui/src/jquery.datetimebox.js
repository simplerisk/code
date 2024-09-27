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
 * datetimebox - EasyUI for jQuery
 * 
 * Dependencies:
 * 	 datebox
 *   timespinner
 * 
 */
(function($){
	function createBox(target){
		var state = $.data(target, 'datetimebox');
		var opts = state.options;
		
		$(target).datebox($.extend({}, opts, {
			onShowPanel:function(){
				var value = $(this).datetimebox('getValue');
				setValue(this, value, true);
				opts.onShowPanel.call(this);
			},
			formatter: $.fn.datebox.defaults.formatter,
			parser: $.fn.datebox.defaults.parser
		}));
		$(target).removeClass('datebox-f').addClass('datetimebox-f');
		
		// override the calendar onSelect event, don't close panel when selected
		$(target).datebox('calendar').calendar({
			onSelect:function(date){
				opts.onSelect.call(this.target, date);
			}
		});
		
		if (!state.spinner){
			var panel = $(target).datebox('panel');
			var p = $('<div style="padding:2px"><input></div>').insertAfter(panel.children('div.datebox-calendar-inner'));
			state.spinner = p.children('input');
		}
		state.spinner.timespinner({
			width: opts.spinnerWidth,
			showSeconds: opts.showSeconds,
			separator: opts.timeSeparator,
			hour12: opts.hour12
		});
		$(target).datetimebox('initValue', opts.value);
	}
	
	/**
	 * get current date, including time
	 */
	function getCurrentDate(target){
		var c = $(target).datetimebox('calendar');
		var t = $(target).datetimebox('spinner');
		var date = c.calendar('options').current;
		return new Date(date.getFullYear(), date.getMonth(), date.getDate(), t.timespinner('getHours'), t.timespinner('getMinutes'), t.timespinner('getSeconds'));
	}
	
	
	/**
	 * called when user inputs some value in text box
	 */
	function doQuery(target, q){
		setValue(target, q, true);
	}
	
	/**
	 * called when user press enter key
	 */
	function doEnter(target){
		var opts = $.data(target, 'datetimebox').options;
		var date = getCurrentDate(target);
		setValue(target, opts.formatter.call(target, date));
		$(target).combo('hidePanel');
	}
	
	/**
	 * set value, if remainText is assigned, don't change the text value
	 */
	function setValue(target, value, remainText){
		var opts = $.data(target, 'datetimebox').options;
		
		$(target).combo('setValue', value);
		if (!remainText){
			if (value){
				var date = opts.parser.call(target, value);
				$(target).combo('setText', opts.formatter.call(target, date));
				$(target).combo('setValue', opts.formatter.call(target, date));
			} else {
				$(target).combo('setText', value);
			}
		}
		var date = opts.parser.call(target, value);
		$(target).datetimebox('calendar').calendar('moveTo', date);
		$(target).datetimebox('spinner').timespinner('setValue', getTimeS(date));

		/**
		 * get the time formatted string such as '03:48:02'
		 */
		function getTimeS(date){
			function formatNumber(value){
				return (value < 10 ? '0' : '') + value;
			}
			
			var tt = [formatNumber(date.getHours()), formatNumber(date.getMinutes())];
			if (opts.showSeconds){
				tt.push(formatNumber(date.getSeconds()));
			}
			return tt.join($(target).datetimebox('spinner').timespinner('options').separator);
		}
	}
	
	$.fn.datetimebox = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.datetimebox.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.datebox(options, param);
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'datetimebox');
			if (state){
				$.extend(state.options, options);
			} else {
				$.data(this, 'datetimebox', {
					options: $.extend({}, $.fn.datetimebox.defaults, $.fn.datetimebox.parseOptions(this), options)
				});
			}
			createBox(this);
		});
	}
	
	$.fn.datetimebox.methods = {
		options: function(jq){
			var copts = jq.datebox('options');
			return $.extend($.data(jq[0], 'datetimebox').options, {
				originalValue: copts.originalValue,
				disabled: copts.disabled,
				readonly: copts.readonly
			});
		},
		cloneFrom: function(jq, from){
			return jq.each(function(){
				$(this).datebox('cloneFrom', from);
				$.data(this, 'datetimebox', {
					options: $.extend(true, {}, $(from).datetimebox('options')),
					spinner: $(from).datetimebox('spinner')
				});
				$(this).removeClass('datebox-f').addClass('datetimebox-f');
			});
		},
		spinner: function(jq){
			return $.data(jq[0], 'datetimebox').spinner;
		},
		initValue: function(jq, value){
			return jq.each(function(){
				var opts = $(this).datetimebox('options');
				var value = opts.value;
				if (value){
					var date = opts.parser.call(this, value);
					value = opts.formatter.call(this, date);
					$(this).datetimebox('calendar').calendar('moveTo', date);
				}
				$(this).combo('initValue', value).combo('setText', value);
			});
		},
		setValue: function(jq, value){
			return jq.each(function(){
				setValue(this, value);
			});
		},
		reset: function(jq){
			return jq.each(function(){
				var opts = $(this).datetimebox('options');
				$(this).datetimebox('setValue', opts.originalValue);
			});
		},
		setDate: function(jq, date){
			return jq.each(function(){
				var opts = $(this).datetimebox('options');
				$(this).datetimebox('calendar').calendar('moveTo', date);
				setValue(this, date ? opts.formatter.call(this, date) : '');
			});
		},
		getDate: function(jq){
			if (jq.datetimebox('getValue')){
				return jq.datetimebox('calendar').calendar('options').current;
			} else {
				return null;
			}
		}
	};
	
	$.fn.datetimebox.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.fn.datebox.parseOptions(target), $.parser.parseOptions(target, [
			'timeSeparator','spinnerWidth',{showSeconds:'boolean'}
		]));
	};
	
	$.fn.datetimebox.defaults = $.extend({}, $.fn.datebox.defaults, {
		spinnerWidth:'100%',
		showSeconds:true,
		timeSeparator:':',
		hour12:false,

		panelEvents: {
			mousedown: function(e){
				// e.preventDefault();
				// e.stopPropagation();
			}
		},
		keyHandler: {
			up:function(e){},
			down:function(e){},
			left: function(e){},
			right: function(e){},
			enter:function(e){doEnter(this)},
			query:function(q,e){doQuery(this, q);}
		},
		buttons:[{
			text: function(target){return $(target).datetimebox('options').currentText;},
			handler: function(target){
				var opts = $(target).datetimebox('options');
				setValue(target, opts.formatter.call(target, new Date()));
				$(target).datetimebox('hidePanel');
			}
		},{
			text: function(target){return $(target).datetimebox('options').okText;},
			handler: function(target){
				doEnter(target);
			}
		},{
			text: function(target){return $(target).datetimebox('options').closeText;},
			handler: function(target){
				$(target).datetimebox('hidePanel');
			}
		}],
		
		formatter:function(date){
			if (!date){return '';}
			return $.fn.datebox.defaults.formatter.call(this, date) + ' ' + $.fn.timespinner.defaults.formatter.call($(this).datetimebox('spinner')[0], date);
		},
		parser:function(s){
			s = $.trim(s);
			if (!s){return new Date();}
			var dt = s.split(' ');
			var date1 = $.fn.datebox.defaults.parser.call(this, dt[0]);
			if (dt.length < 2){
				return date1;
			}
			var date2 = $.fn.timespinner.defaults.parser.call($(this).datetimebox('spinner')[0], dt[1]+(dt[2]?' '+dt[2]:''));
			return new Date(date1.getFullYear(), date1.getMonth(), date1.getDate(), date2.getHours(), date2.getMinutes(), date2.getSeconds());
		}
	});
})(jQuery);
