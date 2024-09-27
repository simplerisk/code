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
 * tagbox - EasyUI for jQuery
 * 
 * Dependencies:
 * 	 combobox
 * 
 */
(function($){
	function create(target){
		var state = $.data(target, 'tagbox');
		var opts = state.options;
		$(target).addClass('tagbox-f').combobox($.extend({}, opts, {
			cls: 'tagbox',
			reversed: true,
			onChange: function(newValue, oldValue){
				buildTag();
				$(this).combobox('hidePanel');
				opts.onChange.call(target, newValue, oldValue);
			},
			onResizing: function(width, height){
				var input = $(this).combobox('textbox');
				var tb = $(this).data('textbox').textbox;
				var tbWidth = tb.outerWidth();
				tb.css({
					height: '',
					paddingLeft: input.css('marginLeft'),
					paddingRight: input.css('marginRight')
				});
				input.css('margin', 0);
				// tb._size({width: opts.width}, $(this).parent());
				tb._outerWidth(tbWidth);
				autoSizeInput(target);
				resizeLabel(this);
				opts.onResizing.call(target, width, height);
			},
			onLoadSuccess: function(data){
				buildTag();
				opts.onLoadSuccess.call(target, data);
			}
		}));
		buildTag();
		autoSizeInput(target);


		function buildTag(){
			$(target).next().find('.tagbox-label').remove();
			var input = $(target).tagbox('textbox');
			var ss = [];
			$.map($(target).tagbox('getValues'), function(value, index){
				var row = opts.finder.getRow(target, value);
				var text = opts.tagFormatter.call(target, value, row);
				var cs = {};
				var css = opts.tagStyler.call(target, value, row) || '';
				if (typeof css == 'string'){
					cs = {s:css};
				} else {
					cs = {c:css['class']||'',s:css['style']||''};
				}
				var label = $('<span class="tagbox-label"></span>').insertBefore(input).html(text);
				label.attr('tagbox-index', index);
				label.attr('style', cs.s).addClass(cs.c);
				$('<a href="javascript:;" class="tagbox-remove"></a>').appendTo(label);
			});
			resizeLabel(target);
			$(target).combobox('setText', '');//.combobox('resize');
		}
	}

	function resizeLabel(target, label){
		var span = $(target).next();
		var labels = label ? $(label) : span.find('.tagbox-label');
		if (labels.length){
			var input = $(target).tagbox('textbox');
			var first = $(labels[0]);
			var margin = first.outerHeight(true) - first.outerHeight();
			var height = input.outerHeight() - margin*2;
			labels.css({
				height: height+'px',
				lineHeight: height+'px'
			});
			var addon = span.find('.textbox-addon').css('height', '100%');
			addon.find('.textbox-icon').css('height', '100%');
			span.find('.textbox-button').linkbutton('resize', {height:'100%'});
		}
	}

	function bindEvents(target){
		var span = $(target).next();
		span._unbind('.tagbox')._bind('click.tagbox', function(e){
			var opts = $(target).tagbox('options');
			if (opts.disabled || opts.readonly){return;}
			if ($(e.target).hasClass('tagbox-remove')){
				var index = parseInt($(e.target).parent().attr('tagbox-index'));
				var values = $(target).tagbox('getValues');
				if (opts.onBeforeRemoveTag.call(target, values[index]) == false){
					return;
				}
				opts.onRemoveTag.call(target, values[index]);
				values.splice(index, 1);
				$(target).tagbox('setValues', values);
			} else {
				var label = $(e.target).closest('.tagbox-label');
				if (label.length){
					var index = parseInt(label.attr('tagbox-index'));
					var values = $(target).tagbox('getValues');
					opts.onClickTag.call(target, values[index]);
				}
			}
			$(this).find('.textbox-text').focus();
		})._bind('keyup.tagbox', function(e){
			autoSizeInput(target);
		})._bind('mouseover.tagbox', function(e){
			if ($(e.target).closest('.textbox-button,.textbox-addon,.tagbox-label').length){
				$(this).triggerHandler('mouseleave');
			} else {
				$(this).find('.textbox-text').triggerHandler('mouseenter');
			}
		})._bind('mouseleave.tagbox', function(e){
			$(this).find('.textbox-text').triggerHandler('mouseleave');
		});
	}

	function autoSizeInput(target){
		var opts = $(target).tagbox('options');
		var input = $(target).tagbox('textbox');
		var span = $(target).next();
		var tmp = $('<span></span>').appendTo('body');
		tmp.attr('style', input.attr('style'));
		tmp.css({
			position: 'absolute',
			top: -9999,
			left: -9999,
			width: 'auto',
			fontFamily: input.css('fontFamily'),
			fontSize: input.css('fontSize'),
			fontWeight: input.css('fontWeight'),
			whiteSpace: 'nowrap'
		});
		var width1 = _getWidth(input.val());
		var width2 = _getWidth(opts.prompt || '');
		tmp.remove();
		var width = Math.min(Math.max(width1,width2)+20, span.width());
		input._outerWidth(width);
		span.find('.textbox-button').linkbutton('resize', {height:'100%'});

		function _getWidth(val){
			var s = val.replace(/&/g, '&amp;').replace(/\s/g,' ').replace(/</g, '&lt;').replace(/>/g, '&gt;');
			tmp.html(s);
			return tmp.outerWidth();
		}
	}

	function doEnter(target){
		var t = $(target);
		var opts = t.tagbox('options');
		// if (!$(target).tagbox('isValid')){return;}
		if (opts.limitToList){
			var panel = t.tagbox('panel');
			var item = panel.children('div.combobox-item-hover');
			if (item.length){
				item.removeClass('combobox-item-hover');
				var row = opts.finder.getRow(target, item);
				var value = row[opts.valueField];
				$(target).tagbox(item.hasClass('combobox-item-selected')?'unselect':'select', value);
			}
			$(target).tagbox('hidePanel');
		} else {
			var v = $.trim($(target).tagbox('getText'));
			if (v !== ''){
				var values = $(target).tagbox('getValues');
				values.push(v);
				$(target).tagbox('setValues', values);
			}
		}
	}

	function setValues(target, values){
		$(target).combobox('setText', '');
		autoSizeInput(target);
		$(target).combobox('setValues', values);
		$(target).combobox('setText', '');
		$(target).tagbox('validate');
	}

	$.fn.tagbox = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.tagbox.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.combobox(options, param);
			}
		}

		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'tagbox');
			if (state){
				$.extend(state.options, options);
			} else {
				$.data(this, 'tagbox', {
					options: $.extend({}, $.fn.tagbox.defaults, $.fn.tagbox.parseOptions(this), options)
				});
			}
			create(this);
			bindEvents(this);
		});
	};

	$.fn.tagbox.methods = {
		options: function(jq){
			var copts = jq.combobox('options');
			return $.extend($.data(jq[0], 'tagbox').options, {
				width: copts.width,
				height: copts.height,
				originalValue: copts.originalValue,
				disabled: copts.disabled,
				readonly: copts.readonly
			});
		},
		setValues: function(jq, values){
			return jq.each(function(){
				setValues(this, values);
			});
		},
		reset: function(jq){
			return jq.each(function(){
				$(this).combobox('reset').combobox('setText', '');
			});
		}
	};

	$.fn.tagbox.parseOptions = function(target){
		return $.extend({}, $.fn.combobox.parseOptions(target), $.parser.parseOptions(target,[
		]));
	};

	$.fn.tagbox.defaults = $.extend({}, $.fn.combobox.defaults, {
		hasDownArrow: false,
		multiple: true,
		reversed: true,
		selectOnNavigation: false,
		tipOptions: $.extend({}, $.fn.textbox.defaults.tipOptions, {
			showDelay: 200
		}),
		val: function(target){
			var vv = $(target).parent().prev().tagbox('getValues');
			if ($(target).is(':focus')){
				vv.push($(target).val());
			}
			return vv.join(',');
		},
		inputEvents: $.extend({}, $.fn.combo.defaults.inputEvents, {
			blur: function(e){
				var target = e.data.target;
				var opts = $(target).tagbox('options');
				if (opts.limitToList){
					doEnter(target);
				}
			}
		}),
		keyHandler: $.extend({}, $.fn.combobox.defaults.keyHandler, {
			enter: function(e){doEnter(this);},
			query: function(q,e){
				var opts = $(this).tagbox('options');
				if (opts.limitToList){
					$.fn.combobox.defaults.keyHandler.query.call(this, q, e);
				} else {
					$(this).combobox('hidePanel');
				}
			}
		}),
		tagFormatter: function(value,row){
			var opts = $(this).tagbox('options');
			return row ? row[opts.textField] : value;
		},
		tagStyler: function(value,row){return ''},
		onClickTag: function(value){},
		onBeforeRemoveTag: function(value){},
		onRemoveTag: function(value){}
	});
})(jQuery);
