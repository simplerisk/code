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
 * maskedbox - EasyUI for jQuery
 * 
 * Dependencies:
 * 	 textbox
 * 
 */
(function($){
	function buildMaskedBox(target){
		var state = $(target).data('maskedbox');
		var opts = state.options;
		$(target).textbox(opts);
		$(target).maskedbox('initValue', opts.value);
	}

	function parseValue(target, value){
		var opts = $(target).maskedbox('options');
		var tt = (value || $(target).maskedbox('getText') || '').split('');
		var vv = [];
		for(var i=0; i<opts.mask.length; i++){
			if (opts.masks[opts.mask[i]]){
				var t = tt[i];
				vv.push(t!=opts.promptChar ? t : ' ');
			}
		}
		return vv.join('');
	}

	function formatValue(target, value){
		var opts = $(target).maskedbox('options');
		var cc = value.split('');
		var tt = [];
		for(var i=0; i<opts.mask.length; i++){
			var m = opts.mask[i];
			var r = opts.masks[m];
			if (r){
				var c = cc.shift();
				if (c != undefined){
					var d = new RegExp(r, 'i');
					if (d.test(c)){
						tt.push(c);
						continue;
					}
				}
				tt.push(opts.promptChar);
			} else {
				tt.push(m);
			}
		}
		return tt.join('');
	}


	function insertChar(target, c){
		var opts = $(target).maskedbox('options');
		var range = $(target).maskedbox('getSelectionRange');
		var start = seekNext(target, range.start);
		var end = seekNext(target, range.end);
		if (start != -1){
			var r = new RegExp(opts.masks[opts.mask[start]], 'i');
			if (r.test(c)){
				var vv = parseValue(target).split('');
				var startOffset = start - getInputOffset(target, start);
				var endOffset = end - getInputOffset(target, end);
				vv.splice(startOffset, endOffset-startOffset, c);
				$(target).maskedbox('setValue', formatValue(target, vv.join('')));
				start = seekNext(target, ++start);
				$(target).maskedbox('setSelectionRange', {start:start,end:start});
			}
		}
	}

	function deleteChar(target,backspace){
		var opts = $(target).maskedbox('options');
		var vv = parseValue(target).split('');
		var range = $(target).maskedbox('getSelectionRange');
		if (range.start == range.end){
			if (backspace){
				var start = seekPrev(target, range.start);
			} else {
				var start = seekNext(target, range.start);
			}
			var startOffset = start - getInputOffset(target, start);
			if (startOffset >= 0){
				vv.splice(startOffset, 1);
			}
		} else {
			var start = seekNext(target, range.start);
			var end = seekPrev(target, range.end);
			var startOffset = start - getInputOffset(target, start);
			var endOffset = end - getInputOffset(target, end);
			vv.splice(startOffset, endOffset-startOffset+1);
		}
		$(target).maskedbox('setValue', formatValue(target, vv.join('')));
		$(target).maskedbox('setSelectionRange', {start:start,end:start});
	}

	function getInputOffset(target,pos){
		var opts = $(target).maskedbox('options');
		var offset = 0;
		if (pos >= opts.mask.length){
			pos--;
		}
		for(var i=pos; i>=0; i--){
			if (opts.masks[opts.mask[i]] == undefined){
				offset++;
			}
		}
		return offset;
	}

	function seekNext(target, pos){
		var opts = $(target).maskedbox('options');
		var m = opts.mask[pos];
		var r = opts.masks[m];
		while(pos < opts.mask.length && !r){
			pos++;
			m = opts.mask[pos];
			r = opts.masks[m];
		}
		return pos;
	}

	function seekPrev(target, pos){
		var opts = $(target).maskedbox('options');
		var m = opts.mask[--pos];
		var r = opts.masks[m];
		while(pos>=0 && !r){
			pos--;
			m = opts.mask[pos];
			r = opts.masks[m];
		}
		return pos<0 ? 0 : pos;
	}

	function keydownEventHandler(e){
		if (e.metaKey || e.ctrlKey){
			return;
		}
		var target = e.data.target;
		var opts = $(target).maskedbox('options');
		var keyCodes = [9,13,35,36,37,39];
		if ($.inArray(e.keyCode, keyCodes) != -1){
			return true;
		}
		if (e.keyCode >= 96 && e.keyCode <= 105){
			e.keyCode -= 48;
		}
		var c = String.fromCharCode(e.keyCode);
		if (e.keyCode >= 65 && e.keyCode <= 90 && !e.shiftKey){
			c = c.toLowerCase();
		} else if (e.keyCode == 189){
			c = '-';
		} else if (e.keyCode == 187){
			c = '+';
		} else if (e.keyCode == 190){
			c = '.';
		}
		if (e.keyCode == 8){	// backspace
			deleteChar(target,true);
		} else if (e.keyCode == 46){	// del
			deleteChar(target,false);
		} else {
			insertChar(target, c);
		}
		
		return false;
	}

	$.extend($.fn.textbox.methods, {
		inputMask: function(jq, param){
			return jq.each(function(){
				var target = this;
				var opts = $.extend({}, $.fn.maskedbox.defaults, param);
				$.data(target, 'maskedbox', {
					options: opts
				});
				var input = $(target).textbox('textbox');
				input._unbind('.maskedbox');
				for(var event in opts.inputEvents){
					input._bind(event+'.maskedbox', {target:target}, opts.inputEvents[event]);
				}
			});
		}
	});

	$.fn.maskedbox = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.maskedbox.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.textbox(options, param);
			}
		}
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'maskedbox');
			if (state){
				$.extend(state.options, options);
			} else {
				$.data(this, 'maskedbox', {
					options: $.extend({}, $.fn.maskedbox.defaults, $.fn.maskedbox.parseOptions(this), options)
				});
			}
			buildMaskedBox(this);
		});
	};

	$.fn.maskedbox.methods = {
		options: function(jq){
			var opts = jq.textbox('options');
			return $.extend($.data(jq[0], 'maskedbox').options, {
				width: opts.width,
				value: opts.value,
				originalValue: opts.originalValue,
				disabled: opts.disabled,
				readonly: opts.readonly
			});
		},
		initValue: function(jq, value){
			return jq.each(function(){
				value = formatValue(this, parseValue(this, value));
				$(this).textbox('initValue', value);
			});
		},
		setValue: function(jq, value){
			return jq.each(function(){
				value = formatValue(this, parseValue(this, value));
				$(this).textbox('setValue', value);
			});
		}

	};

	$.fn.maskedbox.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.fn.textbox.parseOptions(target), $.parser.parseOptions(target, ['mask','promptChar']), {
		});
	};

	$.fn.maskedbox.defaults = $.extend({}, $.fn.textbox.defaults, {
		mask: '',
		promptChar: '_',
		masks: {
			'9': '[0-9]',
			'a': '[a-zA-Z]',
			'*': '[0-9a-zA-Z]'
		},
		inputEvents: {
			keydown: keydownEventHandler
		}
	});

})(jQuery);
