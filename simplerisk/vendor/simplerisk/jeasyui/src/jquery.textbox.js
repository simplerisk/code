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
 * textbox - EasyUI for jQuery
 * 
 * Dependencies:
 * 	 validatebox
 *   linkbutton
 * 
 */
(function($){
	var TEXTBOX_SERNO = 0;

	function init(target){
		$(target).addClass('textbox-f').hide();
		var span = $(
				'<span class="textbox">' +
				'<input class="textbox-text" autocomplete="off">' +
				'<input type="hidden" class="textbox-value">' +
				'</span>'
				).insertAfter(target);
		
		var name = $(target).attr('name');
		if (name){
			span.find('input.textbox-value').attr('name', name);
			$(target).removeAttr('name').attr('textboxName', name);
		}
		
		return span;
	}
	
	/**
	 * build textbox component
	 */
	function buildTextBox(target){
		var state = $.data(target, 'textbox');
		var opts = state.options;
		var tb = state.textbox;
		var inputId = '_easyui_textbox_input' + (++TEXTBOX_SERNO);
		
		tb.addClass(opts.cls);
		tb.find('.textbox-text').remove();
		if (opts.multiline){
			$('<textarea id="'+inputId+'" class="textbox-text" autocomplete="off"></textarea>').prependTo(tb);
		} else {
			$('<input id="'+inputId+'" type="'+opts.type+'" class="textbox-text" autocomplete="off">').prependTo(tb);
		}
		$('#'+inputId).attr('tabindex', $(target).attr('tabindex')||'').css('text-align', target.style.textAlign || '');

		tb.find('.textbox-addon').remove();
		var bb = opts.icons ? $.extend(true, [], opts.icons) : [];
		if (opts.iconCls){
			bb.push({
				iconCls: opts.iconCls,
				disabled: true
			});
		}
		if (bb.length){
			var bc = $('<span class="textbox-addon"></span>').prependTo(tb);
			bc.addClass('textbox-addon-'+opts.iconAlign);
			for(var i=0; i<bb.length; i++){
				bc.append('<a href="javascript:;" class="textbox-icon '+bb[i].iconCls+'" icon-index="'+i+'" tabindex="-1"></a>');
			}
		}
		
		tb.find('.textbox-button').remove();
		if (opts.buttonText || opts.buttonIcon){
			var btn = $('<a href="javascript:;" class="textbox-button"></a>').prependTo(tb);
			btn.addClass('textbox-button-'+opts.buttonAlign).linkbutton({
				text: opts.buttonText,
				iconCls: opts.buttonIcon,
				onClick: function(){
					var t = $(this).parent().prev();
					t.textbox('options').onClickButton.call(t[0]);
				}
			});
		}

		if (opts.label){
			if (typeof opts.label == 'object'){
				state.label = $(opts.label);
				state.label.attr('for', inputId);
			} else {
				$(state.label).remove();
				state.label = $('<label class="textbox-label"></label>').html(opts.label);
				state.label.css('textAlign', opts.labelAlign).attr('for',inputId);
				if (opts.labelPosition == 'after'){
					state.label.insertAfter(tb);
				} else {
					state.label.insertBefore(target);
				}
				state.label.removeClass('textbox-label-left textbox-label-right textbox-label-top');
				state.label.addClass('textbox-label-'+opts.labelPosition)
			}
		} else {
			$(state.label).remove();
		}
		
		validate(target);
		setDisabled(target, opts.disabled);
		setReadonly(target, opts.readonly);
	}
	
	function destroy(target){
		var state = $.data(target, 'textbox');
		var tb = state.textbox;
		tb.find('.textbox-text').validatebox('destroy');
		tb.remove();
		$(state.label).remove();
		$(target).remove();
	}
	
	function setSize(target, param){
		var state = $.data(target, 'textbox');
		var opts = state.options;
		var tb = state.textbox;
		var parent = tb.parent();	// the parent container
		if (param){
			if (typeof param == 'object'){
				$.extend(opts, param);
			} else {
				opts.width = param;
			}
		}
		if (isNaN(parseInt(opts.width))){
			var c = $(target).clone();
			c.css('visibility','hidden');
			c.insertAfter(target);
			opts.width = c.outerWidth();
			c.remove();
		}
		if (opts.autoSize){
			$(target).textbox('autoSize');
			opts.width = tb.css('width','').outerWidth();
			if (opts.labelPosition != 'top'){
				opts.width += $(state.label).outerWidth();
			}
		}

		var isVisible = tb.is(':visible');
		if (!isVisible){
			tb.appendTo('body');
		}
		
		var input = tb.find('.textbox-text');
		var btn = tb.find('.textbox-button');
		var addon = tb.find('.textbox-addon');
		var icons = addon.find('.textbox-icon');

		if (opts.height == 'auto'){
			input.css({
				margin:'',
				paddingTop:'',
				paddingBottom:'',
				height:'',
				lineHeight:''
			});
		}

		tb._size(opts, parent);
		if (opts.label && opts.labelPosition){
			if (opts.labelPosition == 'top'){
				state.label._size({width:opts.labelWidth=='auto'?tb.outerWidth():opts.labelWidth}, tb);
				if (opts.height != 'auto'){
					tb._size('height', tb.outerHeight()-state.label.outerHeight());
				}
			} else {
				state.label._size({width:opts.labelWidth,height:tb.outerHeight()}, tb);
				if (!opts.multiline){
					state.label.css('lineHeight', state.label.height()+'px');
				}
				tb._size('width', tb.outerWidth()-state.label.outerWidth());
			}
		}

		if (opts.buttonAlign == 'left' || opts.buttonAlign == 'right'){
			btn.linkbutton('resize', {height: tb.height()});
		} else {
			btn.linkbutton('resize', {width: '100%'});
		}
		var inputWidth = tb.width() - icons.length * opts.iconWidth - getButtonSize('left') - getButtonSize('right');
		var inputHeight = opts.height=='auto' ? input.outerHeight() : (tb.height() - getButtonSize('top') - getButtonSize('bottom'));
		addon.css(opts.iconAlign, getButtonSize(opts.iconAlign)+'px');
		addon.css('top', getButtonSize('top')+'px');
		icons.css({
			width: opts.iconWidth+'px',
			height: inputHeight+'px'
		});
		input.css({
			paddingLeft: (target.style.paddingLeft || ''),
			paddingRight: (target.style.paddingRight || ''),
			marginLeft: getInputMargin('left'),
			marginRight: getInputMargin('right'),
			marginTop: getButtonSize('top'),
			marginBottom: getButtonSize('bottom')
		});
		if (opts.multiline){
			input.css({
				paddingTop: (target.style.paddingTop || ''),
				paddingBottom: (target.style.paddingBottom || '')
			});
			input._outerHeight(inputHeight);
		} else {
			input.css({
				paddingTop: 0,
				paddingBottom: 0,
				height: inputHeight+'px',
				lineHeight: inputHeight+'px'
			});
		}
		input._outerWidth(inputWidth);

		opts.onResizing.call(target, opts.width, opts.height);
		if (!isVisible){
			tb.insertAfter(target);
		}
		opts.onResize.call(target, opts.width, opts.height);
		
		function getInputMargin(align){
			return (opts.iconAlign==align ? addon._outerWidth() : 0) + getButtonSize(align);
		}
		function getButtonSize(align){
			var w = 0;
			btn.filter('.textbox-button-'+align).each(function(){
				if (align == 'left' || align == 'right'){
					w += $(this).outerWidth();
				} else {
					w += $(this).outerHeight();
				}
			});
			return w;
		}
	}

	function autoSizeInput(target){
		var opts = $(target).textbox('options');
		var input = $(target).textbox('textbox');
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
			padding: input.css('padding'),
			whiteSpace: 'nowrap'
		});
		var width1 = _getWidth(input.val());
		var width2 = _getWidth(opts.prompt || '');
		tmp.remove();
		var width = Math.min(Math.max(width1,width2)+20, span.width());
		var width = Math.max(width1,width2);
		input._outerWidth(width);

		function _getWidth(val){
			var s = val.replace(/&/g, '&amp;').replace(/\s/g,' ').replace(/</g, '&lt;').replace(/>/g, '&gt;');
			tmp.html(s);
			return tmp.outerWidth();
		}
	}
	
	/**
	 * create validation on the textbox
	 */
	function validate(target){
		var opts = $(target).textbox('options');
		var input = $(target).textbox('textbox');
		input.validatebox($.extend({}, opts, {
			deltaX: function(position){
				return $(target).textbox('getTipX', position);
			},
			deltaY: function(position){
				return $(target).textbox('getTipY', position);
			},
			onBeforeValidate: function(){
				opts.onBeforeValidate.call(target);
				var box = $(this);
				if (!box.is(':focus')){
					if (box.val() !== opts.value){
						opts.oldInputValue = box.val();
						box.val(opts.value);
					}
				}
			},
			onValidate: function(valid){
				var box = $(this);
				if (opts.oldInputValue != undefined){
					box.val(opts.oldInputValue);
					opts.oldInputValue = undefined;
				}
				var tb = box.parent();
				if (valid){
					tb.removeClass('textbox-invalid');
				} else {
					tb.addClass('textbox-invalid');
				}
				opts.onValidate.call(target, valid);
			}
		}));
	}
	
	function bindEvents(target){
		var state = $.data(target, 'textbox');
		var opts = state.options;
		var tb = state.textbox;
		var input = tb.find('.textbox-text');
		input.attr('placeholder', opts.prompt);
		input._unbind('.textbox');
		$(state.label)._unbind('.textbox');
		if (!opts.disabled && !opts.readonly){
			if (state.label){
				$(state.label)._bind('click.textbox', function(e){
					// at the first time, select all the value.
					if (!opts.hasFocusMe){
						input.focus();
						$(target).textbox('setSelectionRange', {start:0, end:input.val().length});
					}
				});
			}
			input._bind('blur.textbox', function(e){
				if (!tb.hasClass('textbox-focused')){return;}
				opts.value = $(this).val();
				if (opts.value == ''){
					$(this).val(opts.prompt).addClass('textbox-prompt');
				} else {
					$(this).removeClass('textbox-prompt');
				}
				tb.removeClass('textbox-focused');
				tb.closest('.form-field').removeClass('form-field-focused');
			})._bind('focus.textbox', function(e){
				opts.hasFocusMe = true;		// set the focus flag
				if (tb.hasClass('textbox-focused')){return;}
				if ($(this).val() != opts.value){
					$(this).val(opts.value);
				}
				$(this).removeClass('textbox-prompt');
				tb.addClass('textbox-focused');
				tb.closest('.form-field').addClass('form-field-focused');
			});
			for(var event in opts.inputEvents){
				input._bind(event+'.textbox', {target:target}, opts.inputEvents[event]);
			}
		}
		
		var addon = tb.find('.textbox-addon');
		addon._unbind()._bind('click', {target:target}, function(e){
			var icon = $(e.target).closest('a.textbox-icon:not(.textbox-icon-disabled)');
			if (icon.length){
				var iconIndex = parseInt(icon.attr('icon-index'));
				var conf = opts.icons[iconIndex];
				if (conf && conf.handler){
					conf.handler.call(icon[0], e);
				}
				opts.onClickIcon.call(target, iconIndex);
			}
		});
		addon.find('.textbox-icon').each(function(index){
			var conf = opts.icons[index];
			var icon = $(this);
			if (!conf || conf.disabled || opts.disabled || opts.readonly){
				icon.addClass('textbox-icon-disabled');
			} else {
				icon.removeClass('textbox-icon-disabled');
			}
		});
		
		var btn = tb.find('.textbox-button');
		btn.linkbutton((opts.disabled || opts.readonly) ? 'disable' : 'enable');
		
		tb._unbind('.textbox')._bind('_resize.textbox', function(e, force){
			if ($(this).hasClass('easyui-fluid') || force){
				setSize(target);
			}
			return false;
		});
	}
	
	function setDisabled(target, disabled){
		var state = $.data(target, 'textbox');
		var opts = state.options;
		var tb = state.textbox;
		var input = tb.find('.textbox-text');
		var ss = $(target).add(tb.find('.textbox-value'));
		opts.disabled = disabled;
		if (opts.disabled){
			input.blur();
			input.validatebox('disable');
			tb.addClass('textbox-disabled');
			// ss.attr('disabled', 'disabled');
			ss._propAttr('disabled', true);
			$(state.label).addClass('textbox-label-disabled');
		} else {
			input.validatebox('enable');
			tb.removeClass('textbox-disabled');
			// ss.removeAttr('disabled');
			ss._propAttr('disabled', false);
			$(state.label).removeClass('textbox-label-disabled');
		}
	}

	function setReadonly(target, mode){
		var state = $.data(target, 'textbox');
		var opts = state.options;
		var tb = state.textbox;
		var input = tb.find('.textbox-text');
		opts.readonly = mode==undefined ? true : mode;
		if (opts.readonly){
			input.triggerHandler('blur.textbox');
		}
		input.validatebox('readonly', opts.readonly);
		if (opts.readonly){
			tb.addClass('textbox-readonly');
			$(state.label).addClass('textbox-label-readonly');
		} else {
			tb.removeClass('textbox-readonly');
			$(state.label).removeClass('textbox-label-readonly');
		}
	}

	function setEditable(target, mode){
		var state = $.data(target, 'textbox');
		var opts = state.options;
		var tb = state.textbox;
		var input = tb.find('.textbox-text');
		opts.editable = mode==undefined ? true : mode;
		input.validatebox('setEditable', opts.editable);
		setReadonly(target, opts.readonly);
	}
		
	$.fn.textbox = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.textbox.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.each(function(){
					var input = $(this).textbox('textbox');
					input.validatebox(options, param);
				});
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'textbox');
			if (state){
				$.extend(state.options, options);
				if (options.value != undefined){
					state.options.originalValue = options.value;
				}
			} else {
				state = $.data(this, 'textbox', {
					options: $.extend({}, $.fn.textbox.defaults, $.fn.textbox.parseOptions(this), options),
					textbox: init(this)
				});
				state.options.originalValue = state.options.value;
			}
			
			buildTextBox(this);
			bindEvents(this);
			if (state.options.doSize){
				setSize(this);
			}
			var value = state.options.value;
			state.options.value = '';
			$(this).textbox('initValue', value);
		});
	}
	
	$.fn.textbox.methods = {
		options: function(jq){
			return $.data(jq[0], 'textbox').options;
		},
		cloneFrom: function(jq, from){
			return jq.each(function(){
				var t = $(this);
				if (t.data('textbox')){return}
				if (!$(from).data('textbox')){
					$(from).textbox();
				}
				var opts = $.extend(true, {}, $(from).textbox('options'));
				var name = t.attr('name') || '';
				t.addClass('textbox-f').hide();
				t.removeAttr('name').attr('textboxName', name);
				var span = $(from).next().clone().insertAfter(t);
				var inputId = '_easyui_textbox_input' + (++TEXTBOX_SERNO);
				span.find('.textbox-value').attr('name', name);
				span.find('.textbox-text').attr('id', inputId);
				var label = $($(from).textbox('label')).clone();
				if (label.length){
					label.attr('for', inputId);
					if (opts.labelPosition == 'after'){
						label.insertAfter(t.next());
					} else {
						label.insertBefore(t);
					}
				}

				$.data(this, 'textbox', {
					options: opts,
					textbox: span,
					label: (label.length ? label : undefined)
				});
				var srcBtn = $(from).textbox('button');
				if (srcBtn.length){
					t.textbox('button').linkbutton($.extend(true, {}, srcBtn.linkbutton('options')));
				}

				bindEvents(this);
				validate(this);
			});
		},
		textbox: function(jq){
			return $.data(jq[0], 'textbox').textbox.find('.textbox-text');
		},
		button: function(jq){
			return $.data(jq[0], 'textbox').textbox.find('.textbox-button');
		},
		label: function(jq){
			return $.data(jq[0], 'textbox').label;
		},
		destroy: function(jq){
			return jq.each(function(){
				destroy(this);
			});
		},
		resize: function(jq, width){
			return jq.each(function(){
				setSize(this, width);
			});
		},
		autoSize: function(jq){
			return jq.each(function(){
				autoSizeInput(this);
			});
		},
		disable: function(jq){
			return jq.each(function(){
				setDisabled(this, true);
				bindEvents(this);
			});
		},
		enable: function(jq){
			return jq.each(function(){
				setDisabled(this, false);
				bindEvents(this);
			});
		},
		readonly: function(jq, mode){
			return jq.each(function(){
				setReadonly(this, mode);
				bindEvents(this);
			});
		},
		setEditable: function(jq, mode){
			return jq.each(function(){
				setEditable(this, mode);
				bindEvents(this);
			});
		},
		isValid: function(jq){
			return jq.textbox('textbox').validatebox('isValid');
		},
		clear: function(jq){
			return jq.each(function(){
				$(this).textbox('setValue', '');
			});
		},
		setText: function(jq, value){
			return jq.each(function(){
				var opts = $(this).textbox('options');
				var input = $(this).textbox('textbox');
				value = value == undefined ? '' : String(value);

				if ($(this).textbox('getText') != value){
					input.val(value);
				}
				opts.value = value;
				if (!input.is(':focus')){
					if (value){
						input.removeClass('textbox-prompt');
					} else {
						input.val(opts.prompt).addClass('textbox-prompt');
					}
				}
				if (opts.value){
					$(this).closest('.form-field').removeClass('form-field-empty');
				} else {
					$(this).closest('.form-field').addClass('form-field-empty');
				}
				$(this).textbox('validate');
				if (opts.autoSize){
					$(this).textbox('resize');
				}
			});
		},
		initValue: function(jq, value){
			return jq.each(function(){
				var state = $.data(this, 'textbox');
				// state.options.value = '';
				$(this).textbox('setText', value);
				state.textbox.find('.textbox-value').val(value);
				$(this).val(value);
			});
		},
		setValue: function(jq, value){
			return jq.each(function(){
				var opts = $.data(this, 'textbox').options;
				var oldValue = $(this).textbox('getValue');
				$(this).textbox('initValue', value);
				if (oldValue != value){
					opts.onChange.call(this, value, oldValue);
					$(this).closest('form').trigger('_change', [this]);
				}
			});
		},
		// setPureValue: function(jq, value){
		// 	return jq.each(function(){
		// 		var state = $.data(this, 'textbox');
		// 		var opts = state.options;
		// 		var oldValue = $(this).textbox('getValue');
		// 		state.textbox.find('.textbox-value').val(value);
		// 		$(this).val(value);
		// 		if (oldValue != value){
		// 			opts.onChange.call(this, value, oldValue);
		// 			$(this).closest('form').trigger('_change', [this]);
		// 		}
		// 	});
		// },
		getText: function(jq){
			var input = jq.textbox('textbox');
			if (input.is(':focus')){
				return input.val();
			} else {
				return jq.textbox('options').value;
			}
		},
		getValue: function(jq){
			return jq.data('textbox').textbox.find('.textbox-value').val();
		},
		reset: function(jq){
			return jq.each(function(){
				var opts = $(this).textbox('options');
				$(this).textbox('textbox').val(opts.originalValue);
				$(this).textbox('setValue', opts.originalValue);
			});
		},
		getIcon: function(jq, index){
			return jq.data('textbox').textbox.find('.textbox-icon:eq('+index+')');
		},
		// getTipX: function(jq, position){
		// 	var state = jq.data('textbox');
		// 	var opts = state.options;
		// 	var tb = state.textbox;
		// 	var input = tb.find('.textbox-text');
		// 	var iconWidth = tb.find('.textbox-addon')._outerWidth();
		// 	var btnWidth = tb.find('.textbox-button')._outerWidth();
		// 	var position = position || opts.tipPosition;
		// 	if (position == 'right'){
		// 		return (opts.iconAlign=='right' ? iconWidth : 0) + (opts.buttonAlign=='right' ? btnWidth : 0) + 1;
		// 	} else if (position == 'left'){
		// 		return (opts.iconAlign=='left' ? -iconWidth : 0) + (opts.buttonAlign=='left' ? -btnWidth : 0) - 1;
		// 	} else {
		// 		return iconWidth/2*(opts.iconAlign=='right'?1:-1)+btnWidth/2*(opts.buttonAlign=='right'?1:-1);
		// 	}
		// },
		getTipX: function(jq, position){
			var state = jq.data('textbox');
			var opts = state.options;
			var tb = state.textbox;
			var input = tb.find('.textbox-text');
			var position = position || opts.tipPosition;
			var p1 = tb.offset();
			var p2 = input.offset();
			var w1 = tb.outerWidth();
			var w2 = input.outerWidth();
			if (position == 'right'){
				return w1-w2-p2.left+p1.left;
			} else if (position == 'left'){
				return p1.left-p2.left;
			} else {
				return (w1-w2-p2.left+p1.left)/2 - (p2.left-p1.left)/2;
			}
		},
		getTipY: function(jq, position){
			var state = jq.data('textbox');
			var opts = state.options;
			var tb = state.textbox;
			var input = tb.find('.textbox-text');
			var position = position || opts.tipPosition;
			var p1 = tb.offset();
			var p2 = input.offset();
			var h1 = tb.outerHeight();
			var h2 = input.outerHeight();
			if (position == 'left' || position == 'right'){
				return (h1-h2-p2.top+p1.top)/2 - (p2.top-p1.top)/2;
			} else if (position == 'bottom'){
				return (h1-h2-p2.top+p1.top);
			} else {
				return (p1.top-p2.top);
			}
		},
		getSelectionStart: function(jq){
			return jq.textbox('getSelectionRange').start;
		},
		getSelectionRange: function(jq){
			var target = jq.textbox('textbox')[0];
			var start = 0;
			var end = 0;
			if (typeof target.selectionStart == 'number'){
				start = target.selectionStart;
				end = target.selectionEnd;
			} else if (target.createTextRange){
				var s = document.selection.createRange();
				var range = target.createTextRange();
				range.setEndPoint("EndToStart", s);
				start = range.text.length;
				end = start + s.text.length;
			}
			return {start:start,end:end};
		},
		setSelectionRange: function(jq, param){
			return jq.each(function(){
				var target = $(this).textbox('textbox')[0];
				var start = param.start;
				var end = param.end;
				if (target.setSelectionRange){
					target.setSelectionRange(start, end);
				} else if (target.createTextRange){
					var range = target.createTextRange();
					range.collapse();
					range.moveEnd('character', end);
					range.moveStart('character', start);
					range.select();
				}
			});
		},
		show: function(jq){
			return jq.each(function(){
				$(this).next().show();
				$($(this).textbox('label')).show();
			});
		},
		hide: function(jq){
			return jq.each(function(){
				$(this).next().hide();
				$($(this).textbox('label')).hide();
			});
		}
	}
	
	$.fn.textbox.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.fn.validatebox.parseOptions(target), 
			$.parser.parseOptions(target, [
			     'prompt','iconCls','iconAlign','buttonText','buttonIcon','buttonAlign',
			     'label','labelPosition','labelAlign','width','height',
			     {multiline:'boolean',iconWidth:'number',labelWidth:'number',autoSize:'boolean'}
		    ]), {
			value: (t.val() || undefined),
			type: (t.attr('type') ? t.attr('type') : undefined)
		});
	}
	
	$.fn.textbox.defaults = $.extend({}, $.fn.validatebox.defaults, {
		doSize:true,
		autoSize:false,
		width:'auto',
		// height:22,
		height:'auto',
		cls:null,
		prompt:'',
		value:'',
		type:'text',
		multiline:false,
		icons:[],	// {iconCls:'icon-clear',disabled:true,handler:function(e){}}
		iconCls:null,
		iconAlign:'right',	// 'left' or 'right'
		// iconWidth:18,
		iconWidth:26,
		buttonText:'',
		buttonIcon:null,
		buttonAlign:'right',
		label:null,
		labelWidth:'auto',
		labelPosition:'before',	// before,after,top
		labelAlign:'left',	// left, right
		inputEvents:{
			blur: function(e){
				var t = $(e.data.target);
				var opts = t.textbox('options');
				// t.textbox('setValue', opts.value);
				if (t.textbox('getValue') != opts.value){
					t.textbox('setValue', opts.value);
				}
			},
			keydown: function(e){
				if (e.keyCode == 13){
					var t = $(e.data.target);
					t.textbox('setValue', t.textbox('getText'));
				}
				if ($(e.data.target).textbox('options').autoSize){
					setTimeout(function(){
						$(e.data.target).textbox('resize');
					},0);
				}
			}
		},
		onChange: function(newValue, oldValue){},
		onResizing: function(width, height){},
		onResize: function(width, height){},
		onClickButton: function(){},
		onClickIcon: function(index){}
	});
})(jQuery);
