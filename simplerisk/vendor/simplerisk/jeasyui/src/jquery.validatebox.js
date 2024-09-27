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
 * validatebox - EasyUI for jQuery
 * 
 * Dependencies:
 * 	 tooltip
 * 
 */
(function($){
	
	function init(target){
		$(target).addClass('validatebox-text');
	}
	
	/**
	 * destroy the box, including it's tip object.
	 */
	function destroyBox(target){
		var state = $.data(target, 'validatebox');
		state.validating = false;
		if (state.vtimer){
			clearTimeout(state.vtimer);
		}
		if (state.ftimer){
			clearTimeout(state.ftimer);
		}
		$(target).tooltip('destroy');
		$(target)._unbind();
		$(target).remove();
	}
	
	function bindEvents(target){
		var opts = $.data(target, 'validatebox').options;
		$(target)._unbind('.validatebox');
		if (opts.novalidate || opts.disabled){return;}
		for(var event in opts.events){
			$(target)._bind(event+'.validatebox', {target:target}, opts.events[event]);
		}
	}
	
	function focusEventHandler(e){
		var target = e.data.target;
		var state = $.data(target, 'validatebox');
		var opts = state.options;
		if ($(target).attr('readonly')){return;}
		state.validating = true;
		state.value = opts.val(target);
		// state.value = undefined;
		(function f(){
			if (!$(target).is(':visible')){
				state.validating = false;
			}
			if (state.validating){
				var value = opts.val(target);
				if (state.value != value){	// when the value changed, validate it
					state.value = value;
					if (state.vtimer){
						clearTimeout(state.vtimer);
					}
					state.vtimer = setTimeout(function(){
						$(target).validatebox('validate');
					}, opts.delay);
				} else if (state.message) {
					opts.err(target, state.message);
				}
				state.ftimer = setTimeout(f, opts.interval);
			}
		})();
	}
	function blurEventHandler(e){
		var target = e.data.target;
		var state = $.data(target, 'validatebox');
		var opts = state.options;
		state.validating = false;
		if (state.vtimer){
			clearTimeout(state.vtimer);
			state.vtimer = undefined;
		}
		if (state.ftimer){
			clearTimeout(state.ftimer);
			state.ftimer = undefined;
		}
		if (opts.validateOnBlur){
			setTimeout(function(){
				$(target).validatebox('validate');			
			},0);
		}
		opts.err(target, state.message, 'hide');
	}
	function mouseenterEventHandler(e){
		var target = e.data.target;
		var state = $.data(target, 'validatebox');
		state.options.err(target, state.message, 'show');
	}
	function mouseleaveEventHandler(e){
		var target = e.data.target;
		var state = $.data(target, 'validatebox');
		if (!state.validating){
			state.options.err(target, state.message, 'hide');			
		}
	}

	function handleError(target, message, action){
		var state = $.data(target, 'validatebox');
		var opts = state.options;
		var t = $(target);
		if (action == 'hide' || !message){
			t.tooltip('hide');
		} else {
			if ((t.is(':focus') && state.validating) || action=='show'){
				t.tooltip($.extend({}, opts.tipOptions, {
					content: message,
					position: opts.tipPosition,
					deltaX: opts.deltaX,
					deltaY: opts.deltaY
				})).tooltip('show');
			}
		}
	}
	
	/**
	 * do validate action
	 */
	function validate(target){
		var state = $.data(target, 'validatebox');
		var opts = state.options;
		var box = $(target);

		opts.onBeforeValidate.call(target);
		var result = _validate();
		result ? box.removeClass('validatebox-invalid') : box.addClass('validatebox-invalid');
		opts.err(target, state.message);
		opts.onValidate.call(target, result);
		return result;
		
		function setTipMessage(msg){
			state.message = msg;
		}
		function doValidate(vtype, vparam){
			var value = opts.val(target);	// the value to be validated
			var result = /([a-zA-Z_]+)(.*)/.exec(vtype);
			var rule = opts.rules[result[1]];
			if (rule && value){
				var param = vparam || opts.validParams || eval(result[2]);
				if (!rule['validator'].call(target, value, param)){					
					var message = rule['message'];
					if (param){
						for(var i=0; i<param.length; i++){
							message = message.replace(new RegExp("\\{" + i + "\\}", "g"), param[i]);
						}
					}
					setTipMessage(opts.invalidMessage || message);
					return false;
				}
			}
			return true;
		}
		function _validate(){
			setTipMessage('');
			if (!opts._validateOnCreate){
				setTimeout(function(){
					opts._validateOnCreate = true;
				},0);
				return true;
			}
			if (opts.novalidate || opts.disabled){return true}	// do not need to do validation
			if (opts.required){
				if (opts.val(target) == ''){
					setTipMessage(opts.missingMessage);
					return false;
				}
			}
			if (opts.validType){
				if ($.isArray(opts.validType)){
					for(var i=0; i<opts.validType.length; i++){
						if (!doValidate(opts.validType[i])){return false;}
					}
				} else if (typeof opts.validType == 'string'){
					if (!doValidate(opts.validType)){return false;};
				} else {
					for(var vtype in opts.validType){
						var vparam = opts.validType[vtype];
						if (!doValidate(vtype, vparam)){return false;}
					}
				}
			}
			
			return true;
		}
		
	}
	
	function setDisabled(target, disabled){
		var opts = $.data(target, 'validatebox').options;
		if (disabled != undefined){opts.disabled = disabled}
		if (opts.disabled){
			// $(target).addClass('validatebox-disabled').attr('disabled', 'disabled');
			$(target).addClass('validatebox-disabled')._propAttr('disabled', true);
		} else {
			// $(target).removeClass('validatebox-disabled').removeAttr('disabled');
			$(target).removeClass('validatebox-disabled')._propAttr('disabled', false);
		}
	}

	function setReadonly(target, mode){
		var opts = $.data(target, 'validatebox').options;
		opts.readonly = mode==undefined ? true : mode;
		if (opts.readonly || !opts.editable){
			$(target).triggerHandler('blur.validatebox');
			// $(target).addClass('validatebox-readonly').attr('readonly', 'readonly');
			$(target).addClass('validatebox-readonly')._propAttr('readonly', true);
		} else {
			// $(target).removeClass('validatebox-readonly').removeAttr('readonly');
			$(target).removeClass('validatebox-readonly')._propAttr('readonly', false);
		}
	}

	function setEditable(target, mode){
		var opts = $.data(target, 'validatebox').options;
		opts.editable = mode==undefined ? true : mode;
		setReadonly(target, opts.readonly);
	}
	
	$.fn.validatebox = function(options, param){
		if (typeof options == 'string'){
			return $.fn.validatebox.methods[options](this, param);
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'validatebox');
			if (state){
				$.extend(state.options, options);
			} else {
				init(this);
				state = $.data(this, 'validatebox', {
					options: $.extend({}, $.fn.validatebox.defaults, $.fn.validatebox.parseOptions(this), options)
				});
			}
			state.options._validateOnCreate = state.options.validateOnCreate;
			setDisabled(this, state.options.disabled);
			setReadonly(this, state.options.readonly);
			bindEvents(this);
			validate(this);
		});
	};
	
	$.fn.validatebox.methods = {
		options: function(jq){
			return $.data(jq[0], 'validatebox').options;
		},
		destroy: function(jq){
			return jq.each(function(){
				destroyBox(this);
			});
		},
		validate: function(jq){
			return jq.each(function(){
				validate(this);
			});
		},
		isValid: function(jq){
			return validate(jq[0]);
		},
		enableValidation: function(jq){
			return jq.each(function(){
				$(this).validatebox('options').novalidate = false;
				bindEvents(this);
				validate(this);
			});
		},
		disableValidation: function(jq){
			return jq.each(function(){
				$(this).validatebox('options').novalidate = true;
				bindEvents(this);
				validate(this);
			});
		},
		resetValidation: function(jq){
			return jq.each(function(){
				var opts = $(this).validatebox('options');
				opts._validateOnCreate = opts.validateOnCreate;
				validate(this);
			});
		},
		enable: function(jq){
			return jq.each(function(){
				setDisabled(this, false);
				bindEvents(this);
				validate(this);
			});
		},
		disable: function(jq){
			return jq.each(function(){
				setDisabled(this, true);
				bindEvents(this);
				validate(this);
			});
		},
		readonly: function(jq, mode){
			return jq.each(function(){
				setReadonly(this, mode);
				bindEvents(this);
				validate(this);
			});
		},
		setEditable: function(jq, mode){
			return jq.each(function(){
				setEditable(this, mode);
				bindEvents(this);
				validate(this);
			});
		}
	};
	
	$.fn.validatebox.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.parser.parseOptions(target, [
		    'validType','missingMessage','invalidMessage','tipPosition',
		    {delay:'number',interval:'number',deltaX:'number'},
		    {editable:'boolean',validateOnCreate:'boolean',validateOnBlur:'boolean'}
		]), {
			required: (t.attr('required') ? true : undefined),
			disabled: (t.attr('disabled') ? true : undefined),
			readonly: (t.attr('readonly') ? true : undefined),
			novalidate: (t.attr('novalidate') != undefined ? true : undefined)
		});
	};
	
	$.fn.validatebox.defaults = {
		required: false,
		validType: null,
		validParams: null,	// []
		delay: 200,	// delay to validate from the last inputting value.
		interval: 200,	// validating interval
		missingMessage: 'This field is required.',
		invalidMessage: null,
		tipPosition: 'right',	// Possible values: 'left','right'.
		deltaX: 0,
		deltaY: 0,
		novalidate: false,
		editable: true,
		disabled: false,
		readonly: false,
		validateOnCreate: true,	// Defines whether to validate after creating the component
		validateOnBlur: false,	// Defines whether to validate when losing focus

		events: {
			focus: focusEventHandler,
			blur: blurEventHandler,
			mouseenter: mouseenterEventHandler,
			mouseleave: mouseleaveEventHandler,
			click: function(e){
				var t = $(e.data.target);
				if (t.attr('type') == 'checkbox' || t.attr('type') == 'radio'){
					t.focus().validatebox('validate');
				}
			}
		},

		// the function to retrieve current value to be validated
		val: function(target){
			return $(target).val();
		},
		// the error handler to show/hide error message, 'action' may be 'show' or 'hide'
		err: function(target, message, action){
			handleError(target, message, action);
		},
		
		tipOptions: {	// the options to create tooltip
			showEvent: 'none',
			hideEvent: 'none',
			showDelay: 0,
			hideDelay: 0,
			zIndex: '',
			onShow: function(){
				$(this).tooltip('tip').css({
					color: '#000',
					borderColor: '#CC9933',
					backgroundColor: '#FFFFCC'
				});
			},
			onHide: function(){
				$(this).tooltip('destroy');
			}
		},
		
		rules: {
			email:{
				validator: function(value){
					return /^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i.test(value);
				},
				message: 'Please enter a valid email address.'
			},
			url: {
				validator: function(value){
					return /^(https?|ftp):\/\/(((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:)*@)?(((\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5]))|((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?)(:\d*)?)(\/((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)+(\/(([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)*)*)?)?(\?((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|[\uE000-\uF8FF]|\/|\?)*)?(\#((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|\/|\?)*)?$/i.test(value);
				},
				message: 'Please enter a valid URL.'
			},
			length: {
				validator: function(value, param){
					var len = $.trim(value).length;
					return len >= param[0] && len <= param[1]
				},
				message: 'Please enter a value between {0} and {1}.'
			},
			remote: {
				validator: function(value, param){
					var data = {};
					data[param[1]] = value;
					var response = $.ajax({
						url:param[0],
						dataType:'json',
						data:data,
						async:false,
						cache:false,
						type:'post'
					}).responseText;
					return response.replace(/\s/g, '') == 'true';
				},
				message: 'Please fix this field.'
			}
		},
		
		onBeforeValidate: function(){},
		onValidate: function(valid){}	// fires when validation completes
	};
})(jQuery);
