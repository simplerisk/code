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
 * spinner - EasyUI for jQuery
 * 
 * Dependencies:
 *   textbox
 * 
 */
(function($){
	function buildSpinner(target){
		var state = $.data(target, 'spinner');
		var opts = state.options;
		var icons = $.extend(true, [], opts.icons);
		if (opts.spinAlign == 'left' || opts.spinAlign == 'right'){
			opts.spinArrow = true;
			opts.iconAlign = opts.spinAlign;
			var arrow = {
				// iconCls:'spinner-arrow',
				iconCls:'spinner-button-updown',
				handler:function(e){
					var spin = $(e.target).closest('.spinner-button-top,.spinner-button-bottom');
					doSpin(e.data.target, spin.hasClass('spinner-button-bottom'));
					// var spin = $(e.target).closest('.spinner-arrow-up,.spinner-arrow-down');
					// doSpin(e.data.target, spin.hasClass('spinner-arrow-down'));
				}
			};
			if (opts.spinAlign == 'left'){
				icons.unshift(arrow);
			} else {
				icons.push(arrow);
			}
		} else {
			opts.spinArrow = false;
			if (opts.spinAlign == 'vertical'){
				if (opts.buttonAlign != 'top'){
					opts.buttonAlign = 'bottom';
				}
				opts.clsLeft = 'textbox-button-bottom';
				opts.clsRight = 'textbox-button-top';
			} else {
				opts.clsLeft = 'textbox-button-left';
				opts.clsRight = 'textbox-button-right';
			}
		}

		$(target).addClass('spinner-f').textbox($.extend({}, opts, {
			icons: icons,
			doSize: false,
			onResize: function(width, height){
				if (!opts.spinArrow){
					var span = $(this).next();
					var btn = span.find('.textbox-button:not(.spinner-button)');
					if (btn.length){
						var btnWidth = btn.outerWidth();
						var btnHeight = btn.outerHeight();
						var btnLeft = span.find('.spinner-button.'+opts.clsLeft);
						var btnRight = span.find('.spinner-button.'+opts.clsRight);
						if (opts.buttonAlign == 'right'){
							btnRight.css('marginRight', btnWidth+'px');
						} else if (opts.buttonAlign == 'left'){
							btnLeft.css('marginLeft', btnWidth+'px');
						} else if (opts.buttonAlign == 'top'){
							btnRight.css('marginTop', btnHeight+'px');
						} else {
							btnLeft.css('marginBottom', btnHeight+'px');
						}
					}
				}
				opts.onResize.call(this, width, height);
			}
		}));
		$(target).attr('spinnerName', $(target).attr('textboxName'));
		state.spinner = $(target).next();
		state.spinner.addClass('spinner');

		if (opts.spinArrow){
			// var arrowIcon = state.spinner.find('.spinner-arrow');
			// arrowIcon.append('<a href="javascript:;" class="spinner-arrow-up" tabindex="-1"></a>');
			// arrowIcon.append('<a href="javascript:;" class="spinner-arrow-down" tabindex="-1"></a>');
			
			var arrowIcon = state.spinner.find('.spinner-button-updown');
			arrowIcon.append(
				'<span class="spinner-arrow spinner-button-top">' +
					'<span class="spinner-arrow-up"></span>' +
				'</span>' +
				'<span class="spinner-arrow spinner-button-bottom">' +
					'<span class="spinner-arrow-down"></span>' +
				'</span>'
			);
		} else {
			var btnLeft = $('<a href="javascript:;" class="textbox-button spinner-button" tabindex="-1"></a>').addClass(opts.clsLeft).appendTo(state.spinner);
			var btnRight = $('<a href="javascript:;" class="textbox-button spinner-button" tabindex="-1"></a>').addClass(opts.clsRight).appendTo(state.spinner);
			btnLeft.linkbutton({
				iconCls: opts.reversed ? 'spinner-button-up' : 'spinner-button-down',
				onClick: function(){doSpin(target, !opts.reversed);}
			});
			btnRight.linkbutton({
				iconCls: opts.reversed ? 'spinner-button-down' : 'spinner-button-up',
				onClick: function(){doSpin(target, opts.reversed);}
			});
			if (opts.disabled){$(target).spinner('disable');}
			if (opts.readonly){$(target).spinner('readonly');}
		}
		$(target).spinner('resize');
	}

	function doSpin(target, down){
		var opts = $(target).spinner('options');
		opts.spin.call(target, down);
		opts[down ? 'onSpinDown' : 'onSpinUp'].call(target);
		$(target).spinner('validate');
	}
	
	$.fn.spinner = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.spinner.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.textbox(options, param);
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'spinner');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'spinner', {
					options: $.extend({}, $.fn.spinner.defaults, $.fn.spinner.parseOptions(this), options)
				});
			}
			buildSpinner(this);
		});
	};
	
	$.fn.spinner.methods = {
		options: function(jq){
			var opts = jq.textbox('options');
			return $.extend($.data(jq[0], 'spinner').options, {
				width: opts.width,
				value: opts.value,
				originalValue: opts.originalValue,
				disabled: opts.disabled,
				readonly: opts.readonly
			});
		}
	};
	
	$.fn.spinner.parseOptions = function(target){
		return $.extend({}, $.fn.textbox.parseOptions(target), $.parser.parseOptions(target, [
			'min','max','spinAlign',{increment:'number',reversed:'boolean'}
		]));
	};
	
	$.fn.spinner.defaults = $.extend({}, $.fn.textbox.defaults, {
		min: null,
		max: null,
		increment: 1,
		spinAlign: 'right',	// possible values: 'left','right','horizontal','vertical'
		reversed: false,
		spin: function(down){},	// the function to implement the spin button clicking
		onSpinUp: function(){},
		onSpinDown: function(){}
	});
})(jQuery);
