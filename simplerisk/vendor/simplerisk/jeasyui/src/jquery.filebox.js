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
 * filebox - EasyUI for jQuery
 * 
 * Dependencies:
 *  textbox
 * 
 */
(function($){
	var FILE_INDEX = 0;
	function buildFileBox(target){
		var state = $.data(target, 'filebox');
		var opts = state.options;
		opts.fileboxId = 'filebox_file_id_' + (++FILE_INDEX);
		$(target).addClass('filebox-f').textbox(opts);
		$(target).textbox('textbox').attr('readonly','readonly');
		state.filebox = $(target).next().addClass('filebox');
		var file = resetFile(target);
		var btn = $(target).filebox('button');
		if (btn.length){
			$('<label class="filebox-label" for="'+opts.fileboxId+'"></label>').appendTo(btn);
			if (btn.linkbutton('options').disabled){
				// file.attr('disabled', 'disabled');
				file._propAttr('disabled', true);
			} else {
				// file.removeAttr('disabled');
				file._propAttr('disabled', false);
			}			
		}
	}

	function resetFile(target){
		var state = $.data(target, 'filebox');
		var opts = state.options;
		state.filebox.find('.textbox-value').remove();
		opts.oldValue = "";
		var file = $('<input type="file" class="textbox-value">').appendTo(state.filebox);
		file.attr('id', opts.fileboxId).attr('name', $(target).attr('textboxName')||'');
		file.attr('accept', opts.accept);
		file.attr('capture', opts.capture);
		if (opts.multiple){
			file.attr('multiple', 'multiple');
		}
		file.change(function(){
			var value = this.value;
			if (this.files){
				value = $.map(this.files, function(file){
					return file.name;
				}).join(opts.separator);
			}
			$(target).filebox('setText', value);
			opts.onChange.call(target, value, opts.oldValue);
			opts.oldValue = value;
		});
		return file;
	}
	
	$.fn.filebox = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.filebox.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.textbox(options, param);
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'filebox');
			if (state){
				$.extend(state.options, options);
			} else {
				$.data(this, 'filebox', {
					options: $.extend({}, $.fn.filebox.defaults, $.fn.filebox.parseOptions(this), options)
				});
			}
			buildFileBox(this);
		});
	};
	
	$.fn.filebox.methods = {
		options: function(jq){
			var opts = jq.textbox('options');
			return $.extend($.data(jq[0], 'filebox').options, {
				width: opts.width,
				value: opts.value,
				originalValue: opts.originalValue,
				disabled: opts.disabled,
				readonly: opts.readonly
			});
		},
		clear: function(jq){
			return jq.each(function(){
				$(this).textbox('clear');
				resetFile(this);
			});
		},
		reset: function(jq){
			return jq.each(function(){
				$(this).filebox('clear');
			});
		},
		setValue: function(jq){
			return jq;
		},
		setValues: function(jq){
			return jq;
		},
		files: function(jq){
			return jq.next().find('.textbox-value')[0].files;
		}
	};
	
	$.fn.filebox.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.fn.textbox.parseOptions(target), $.parser.parseOptions(target, ['accept','capture','separator']), {
			multiple: (t.attr('multiple') ? true : undefined)
		});
	};
	
	$.fn.filebox.defaults = $.extend({}, $.fn.textbox.defaults, {
		buttonIcon: null,
		buttonText: 'Choose File',
		buttonAlign: 'right',
		inputEvents: {},
		accept: '',
		capture: '',
		separator: ',',
		multiple: false
	});
	
	
})(jQuery);
