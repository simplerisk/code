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
 * passwordbox - EasyUI for jQuery
 * 
 * Dependencies:
 *  textbox
 * 
 */
(function($){
	function buildBox(target){
		var state = $.data(target, 'passwordbox');
		var opts = state.options;
		var icons = $.extend(true, [], opts.icons);
		if (opts.showEye){
			icons.push({
				iconCls: 'passwordbox-open',
				handler: function(e){
					opts.revealed = !opts.revealed;
					setValue(target);
				}
			});
		}
		$(target).addClass('passwordbox-f').textbox($.extend({}, opts, {
			icons: icons
		}));
		setValue(target);
	}

	// convert to password char
	function convert(target, value, all){
		var state = $(target).data('passwordbox');
		var t = $(target);
		var opts = t.passwordbox('options');
		if (opts.revealed){
			t.textbox('setValue', value);
			return;
		}
		state.converting = true;
		var pchar = unescape(opts.passwordChar);
		var cc = value.split('');
		var vv = t.passwordbox('getValue').split('');
		for(var i=0; i<cc.length; i++){
			var c = cc[i];
			if (c != vv[i]){
				if (c != pchar){
					vv.splice(i, 0, c);
				}				
			}
		}
		var pos = t.passwordbox('getSelectionStart');
		if (cc.length < vv.length){
			vv.splice(pos, vv.length-cc.length, '');
		}
		for(var i=0; i<cc.length; i++){
			if (all || i != pos-1){
				cc[i] = pchar;
			}
		}

		t.textbox('setValue', vv.join(''));
		t.textbox('setText', cc.join(''));
		t.textbox('setSelectionRange', {start:pos,end:pos});
		// state.converting = false;
		setTimeout(function(){
			state.converting = false;
		},0);
	}

	function setValue(target, value){
		var t = $(target);
		var opts = t.passwordbox('options');
		var icon = t.next().find('.passwordbox-open');
		var pchar = unescape(opts.passwordChar);
		value = value==undefined ? t.textbox('getValue') : value;
		t.textbox('setValue', value);
		t.textbox('setText', opts.revealed ? value : value.replace(/./ig, pchar));
		opts.revealed ? icon.addClass('passwordbox-close') : icon.removeClass('passwordbox-close');
	}

	function focusHandler(e){
		var target = e.data.target;
		var t = $(e.data.target);
		var state = t.data('passwordbox');
		var opts = t.data('passwordbox').options;
		state.checking = true;
		state.value = t.passwordbox('getText');
		(function f(){
			if (state.checking){
				var value = t.passwordbox('getText');
				if (state.value != value){
					state.value = value;
					if (state.lastTimer){
						clearTimeout(state.lastTimer);
						state.lastTimer = undefined;
					}
					convert(target, value);
					state.lastTimer = setTimeout(function(){
						convert(target, t.passwordbox('getText'), true);
						state.lastTimer = undefined;
					}, opts.lastDelay);
				}
				setTimeout(f, opts.checkInterval);
			}
		})();
	}

	function blurHandler(e){
		var target = e.data.target;
		var state = $(target).data('passwordbox');
		state.checking = false;
		if (state.lastTimer){
			clearTimeout(state.lastTimer);
			state.lastTimer = undefined;
		}
		setValue(target);
	}


	$.fn.passwordbox = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.passwordbox.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.textbox(options, param);
			}
		}
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'passwordbox');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'passwordbox', {
					options: $.extend({}, $.fn.passwordbox.defaults, $.fn.passwordbox.parseOptions(this), options)
				});
			}
			buildBox(this);
		});
	};

	$.fn.passwordbox.methods = {
		options: function(jq){
			return $.data(jq[0], 'passwordbox').options;
		},
		setValue: function(jq, value){
			return jq.each(function(){
				setValue(this, value);
			});
		},
		clear: function(jq){
			return jq.each(function(){
				setValue(this, '');
			});
		},
		reset: function(jq){
			return jq.each(function(){
				$(this).textbox('reset');
				setValue(this);
			});
		},
		showPassword: function(jq){
			return jq.each(function(){
				var opts = $(this).passwordbox('options');
				opts.revealed = true;
				setValue(this);
			});
		},
		hidePassword: function(jq){
			return jq.each(function(){
				var opts = $(this).passwordbox('options');
				opts.revealed = false;
				setValue(this);
			});
		}
	};

	$.fn.passwordbox.parseOptions = function(target){
		return $.extend({}, $.fn.textbox.parseOptions(target), $.parser.parseOptions(target, [
			'passwordChar',{checkInterval:'number',lastDelay:'number',revealed:'boolean',showEye:'boolean'}
		]));
	};

	$.fn.passwordbox.defaults = $.extend({}, $.fn.textbox.defaults, {
		passwordChar: '%u25CF',
		checkInterval: 200,
		lastDelay: 500,
		revealed: false,
		showEye: true,
		inputEvents: {
			focus: focusHandler,
			blur: blurHandler,
			keydown: function(e){
				var state = $(e.data.target).data('passwordbox');
				return !state.converting;
			}
		},
		val: function(target){
			return $(target).parent().prev().passwordbox('getValue');
		}
	});

})(jQuery);
