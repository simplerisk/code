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
 * timepicker - EasyUI for jQuery
 * 
 * Dependencies:
 *   combo
 * 
 */
(function($){
	function createBox(target){
		var state = $.data(target, 'timepicker');
		var opts = state.options;
		$(target).addClass('timepicker-f').combo($.extend({}, opts, {
			onShowPanel:function(){
				bindEvents(this);
				setButtons(target);
				renderClockPanel(target, $(target).timepicker('getValue'));
			}
		}));
		$(target).timepicker('initValue', opts.value);

		function bindEvents(target){
			var opts = $(target).timepicker('options');
			var panel = $(target).combo('panel');
			panel._unbind('.timepicker')._bind('click.timepicker', function(e){
				if ($(e.target).hasClass('datebox-button-a')){
					var index = parseInt($(e.target).attr('datebox-button-index'));
					opts.buttons[index].handler.call(e.target, target);
				}
			});
		}

		function setButtons(target){
			var panel = $(target).combo('panel');
			if (panel.children('div.datebox-button').length){return}
			var button = $('<div class="datebox-button"><table cellspacing="0" cellpadding="0" style="width:100%"><tr></tr></table></div>').appendTo(panel);
			var tr = button.find('tr');
			for(var i=0; i<opts.buttons.length; i++){
				var td = $('<td></td>').appendTo(tr);
				var btn = opts.buttons[i];
				var t = $('<a class="datebox-button-a" href="javascript:;"></a>').html($.isFunction(btn.text) ? btn.text(target) : btn.text).appendTo(td);
				t.attr('datebox-button-index', i);
			}
			tr.find('td').css('width', (100/opts.buttons.length)+'%');
		}
	}

	function setValue(target, value){
		var opts = $(target).data('timepicker').options;
		renderClockPanel(target, value);
		opts.value = retrieveValue(target);
		$(target).combo('setValue', opts.value).combo('setText', opts.value);
	}

	function renderClockPanel(target, value){
		var opts = $(target).data('timepicker').options;
		if (value){
			var parts = value.split(' ');
			var hm = parts[0].split(':');
			opts.selectingHour = parseInt(hm[0],10);
			opts.selectingMinute = parseInt(hm[1],10);
			opts.selectingAmpm = parts[1];
		} else {
			opts.selectingHour = 12;
			opts.selectingMinute = 0;
			opts.selectingAmpm = opts.ampm[0];
		}
		createClockPanel(target);
	}

	function retrieveValue(target){
		var opts = $(target).data('timepicker').options;
		var h = opts.selectingHour;
		var m = opts.selectingMinute;
		var ampm = opts.selectingAmpm;
		if (!ampm){
			ampm = opts.ampm[0];
		}
		var v = (h<10?'0'+h:h)+':'+(m<10?'0'+m:m);
		if (!opts.hour24){
			v += ' '+ampm;
		}
		return v;
	}

	function createClockPanel(target){
		var opts = $(target).data('timepicker').options;
		var panel = $(target).combo('panel');
		var tpanel = panel.children('.timepicker-panel');
		if (!tpanel.length){
			var tpanel = $('<div class="timepicker-panel f-column"></div>').prependTo(panel);
		}
		tpanel.empty();
		if (opts.panelHeight != 'auto'){
			var height = panel.height() - panel.find('.datebox-button').outerHeight();
			tpanel._outerHeight(height);
		}
		createHeader(target);
		createClock(target);

		tpanel.off('.timepicker');
		tpanel.on('click.timepicker', '.title-hour', function(e){
			opts.selectingType = 'hour';
			createClockPanel(target);
		}).on('click.timepicker', '.title-minute', function(e){
			opts.selectingType = 'minute';
			createClockPanel(target);
		}).on('click.timepicker', '.title-am', function(e){
			opts.selectingAmpm = opts.ampm[0];
			createClockPanel(target);
		}).on('click.timepicker', '.title-pm', function(e){
			opts.selectingAmpm = opts.ampm[1];
			createClockPanel(target);
		}).on('click.timepicker', '.item', function(e){
			var value = parseInt($(this).text(), 10);
			if (opts.selectingType == 'hour'){
				opts.selectingHour = value;
			} else {
				opts.selectingMinute = value;
			}
			createClockPanel(target);
		});
	}

	function createHeader(target){
		var opts = $(target).data('timepicker').options;
		var panel = $(target).combo('panel');
		var tpanel = panel.find('.timepicker-panel');
		var hour = opts.selectingHour;
		var minute = opts.selectingMinute;
		$(
			'<div class="panel-header f-noshrink f-row f-content-center">' +
			'<div class="title title-hour">'+(hour<10?'0'+hour:hour)+'</div>' + 
			'<div class="sep">:</div>' + 
			'<div class="title title-minute">'+(minute<10?'0'+minute:minute)+'</div>' + 
			'<div class="ampm f-column">' +
			'<div class="title title-am">'+opts.ampm[0]+'</div>' + 
			'<div class="title title-pm">'+opts.ampm[1]+'</div>' + 
			'</div>' +
			'</div>'
		).appendTo(tpanel);
		var header = tpanel.find('.panel-header');
		if (opts.selectingType == 'hour'){
			header.find('.title-hour').addClass('title-selected');
		} else {
			header.find('.title-minute').addClass('title-selected');
		}
		if (opts.selectingAmpm == opts.ampm[0]){
			header.find('.title-am').addClass('title-selected');
		}
		if (opts.selectingAmpm == opts.ampm[1]){
			header.find('.title-pm').addClass('title-selected');
		}
		if (opts.hour24){
			header.find('.ampm').hide();
		}
	}

	function createClock(target){
		var opts = $(target).data('timepicker').options;
		var panel = $(target).combo('panel');
		var tpanel = panel.find('.timepicker-panel');
		var clockWrap = $(
			'<div class="clock-wrap f-full f-column f-content-center">' +
			'</div>'
		).appendTo(tpanel);
		var width = clockWrap.outerWidth();
		var height = clockWrap.outerHeight();
		var size = Math.min(width, height) - 20;
		var radius = size / 2;
		width = size;
		height = size;

		var value = opts.selectingType == 'hour' ? opts.selectingHour : opts.selectingMinute;
		var angular = value / (opts.selectingType == 'hour' ? 12 : 60) * 360;
		angular = parseFloat(angular).toFixed(4);
		var handStyle = {
			transform: 'rotate('+angular+'deg)',
		};
		if (opts.hour24 && opts.selectingType == 'hour'){
			if (value == 0){
				handStyle.top = opts.hourDistance[0]+'px';
			} else if (value <= 12){
				handStyle.top = opts.hourDistance[1]+'px';
			}
		}
		var style = {
			width: width+'px',
			height: height+'px',
			marginLeft: -width/2+'px',
			marginTop: -height/2+'px'
		};

		var clockData = [];
		clockData.push('<div class="clock">');
		clockData.push('<div class="center"></div>');
		clockData.push('<div class="hand">');
		clockData.push('<div class="drag"></div>');
		clockData.push('</div>');
		var data = getData();
		if (opts.hour24 && opts.selectingType == 'hour'){
			for(var i=0; i<data.length; i++){
				var itemValue = parseInt(data[i], 10);
				itemValue += 12;
				if (itemValue == 24){
					itemValue = '00';
				}
				var cls = 'item f-column f-content-center';
				if (itemValue == value){
					cls += ' item-selected';
				}
				var angular = itemValue / (opts.selectingType == 'hour' ? 12 : 60) * 360 * Math.PI / 180;
				var x = (radius - 20) * Math.sin(angular);
				var y = -(radius - 20) * Math.cos(angular);
				angular = parseFloat(angular).toFixed(4);
				x = parseFloat(x).toFixed(4);
				y = parseFloat(y).toFixed(4);
				var itemStyle = {
					transform: 'translate('+x+'px,'+y+'px)'
				};
				var itemStyle = 'transform:translate('+x+'px,'+y+'px)';
				clockData.push('<div class="'+cls+'" style="'+itemStyle+'">'+(itemValue)+'</div>');
			}
			radius -= opts.hourDistance[1]-opts.hourDistance[0];
		}
		for(var i=0; i<data.length; i++){
			var itemValue = data[i];
			var cls = 'item f-column f-content-center';
			if (itemValue == value){
				cls += ' item-selected';
			}
			var angular = itemValue / (opts.selectingType == 'hour' ? 12 : 60) * 360 * Math.PI / 180;
			var x = (radius - 20) * Math.sin(angular);
			var y = -(radius - 20) * Math.cos(angular);
			angular = parseFloat(angular).toFixed(4);
			x = parseFloat(x).toFixed(4);
			y = parseFloat(y).toFixed(4);
			var itemStyle = {
				transform: 'translate('+x+'px,'+y+'px)'
			};
			var itemStyle = 'transform:translate('+x+'px,'+y+'px)';
			clockData.push('<div class="'+cls+'" style="'+itemStyle+'">'+itemValue+'</div>');
		}
		clockData.push('</div>');

		clockWrap.html(clockData.join(''));
		clockWrap.find('.clock').css(style);
		clockWrap.find('.hand').css(handStyle);

		function getData(){
	        var data = [];
	        if (opts.selectingType == 'hour') {
	            for (var i = 0; i < 12; i++) {
	                data.push(String(i));
	            }
	            data[0] = '12';
	        } else {
	            for (var i = 0; i < 60; i += 5) {
	                data.push(i < 10 ? '0' + i : String(i));
	            }
	            data[0] = '00';
	        }
	        return data;
		}
	}


	$.fn.timepicker = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.timepicker.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.combo(options, param);
			}
		}
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'timepicker');
			if (state){
				$.extend(state.options, options);
			} else {
				$.data(this, 'timepicker', {
					options: $.extend({}, $.fn.timepicker.defaults, $.fn.timepicker.parseOptions(this), options)
				});
			}
			createBox(this);
		});
	};

	$.fn.timepicker.methods = {
		options: function(jq){
			var copts = jq.combo('options');
			return $.extend($.data(jq[0], 'timepicker').options, {
				width: copts.width,
				height: copts.height,
				originalValue: copts.originalValue,
				disabled: copts.disabled,
				readonly: copts.readonly
			});
		},
		initValue: function(jq, value){
			return jq.each(function(){
				var opts = $(this).timepicker('options');
				opts.value = value;
				renderClockPanel(this, value);
				if (value){
					opts.value = retrieveValue(this);
					$(this).combo('initValue',opts.value).combo('setText',opts.value);
				}
			});
		},
		setValue: function(jq, value){
			return jq.each(function(){
				setValue(this, value);
			});
		},
		reset: function(jq){
			return jq.each(function(){
				var opts = $(this).timepicker('options');
				$(this).timepicker('setValue', opts.originalValue);
			});
		}
	};

	$.fn.timepicker.parseOptions = function(target){
		return $.extend({}, $.fn.combo.parseOptions(target), $.parser.parseOptions(target, [
			{hour24:'boolean'}
		]));
	};

	$.fn.timepicker.defaults = $.extend({}, $.fn.combo.defaults, {
		closeText:'Close',
		okText:'Ok',
		buttons: [{
			text: function(target){return $(target).timepicker('options').okText;},
			handler: function(target){
				$(target).timepicker('setValue', retrieveValue(target));
				$(this).closest('div.combo-panel').panel('close');
			}
		}, {
			text: function(target){return $(target).timepicker('options').closeText;},
			handler: function(target){
				$(this).closest('div.combo-panel').panel('close');
			}
		}],
		editable: false,
    	ampm: ['am','pm'],
    	value: '',
    	selectingHour: 12,
    	selectingMinute: 0,
    	selectingType: 'hour',
    	hour24: false,
    	hourDistance: [20,50]
	});
})(jQuery);
