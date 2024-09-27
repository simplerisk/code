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
 * messager - EasyUI for jQuery
 * 
 * Dependencies:
 * 	linkbutton
 *  dialog
 *  progressbar
 */
(function($){
	function bindEvents(){
		$(document)._unbind('.messager')._bind('keydown.messager', function(e){
			if (e.keyCode == 27){	//ESC
				$('body').children('div.messager-window').children('div.messager-body').each(function(){
					$(this).dialog('close');
				});
			} else if (e.keyCode == 9){	//TAB
				var win = $('body').children('div.messager-window');
				if (!win.length){return}
				var buttons = win.find('.messager-input,.messager-button .l-btn');
				for(var i=0; i<buttons.length; i++){
					if ($(buttons[i]).is(':focus')){
						$(buttons[i>=buttons.length-1?0:i+1]).focus();
						return false;
					}
				}
			} else if (e.keyCode == 13){	// ENTER
				var input = $(e.target).closest('input.messager-input');
				if (input.length){
					var dlg = input.closest('.messager-body');
					closeDialog(dlg, input.val());
				}
			}
		});
	}

	function unbindEvents(){
		$(document)._unbind('.messager');
	}
	
	/**
	 * create the message window
	 */
	function createWindow(options){
		var opts = $.extend({}, $.messager.defaults, {
			modal: false,
			shadow: false,
			draggable: false,
			resizable: false,
			closed: true,
			// set the message window to the right bottom position
			style: {
				left: '',
				top: '',
				right: 0,
				zIndex: $.fn.window.defaults.zIndex++,
				bottom: -document.body.scrollTop-document.documentElement.scrollTop
			},
			title: '',
			width: 300,
			height: 150,
			minHeight: 0,
			showType: 'slide',
			showSpeed: 600,
			content: options.msg,
			timeout: 4000
		}, options);
		
		var dlg = $('<div class="messager-body"></div>').appendTo('body');
		dlg.dialog($.extend({}, opts, {
			noheader: (opts.title?false:true),
			openAnimation: (opts.showType),
			closeAnimation: (opts.showType=='show'?'hide':opts.showType),
			openDuration: opts.showSpeed,
			closeDuration: opts.showSpeed,
			onOpen: function(){
				dlg.dialog('dialog').hover(
						function(){
							if (opts.timer){clearTimeout(opts.timer);}
						},
						function(){
							closeMe();
						}
				);
				closeMe();
				function closeMe(){
					if (opts.timeout > 0){
						opts.timer = setTimeout(function(){
							if (dlg.length && dlg.data('dialog')){
								dlg.dialog('close');
							}
						}, opts.timeout);
					}
				}
				if (options.onOpen){
					options.onOpen.call(this);
				} else {
					opts.onOpen.call(this);
				}
			},
			onClose: function(){
				if (opts.timer){clearTimeout(opts.timer);}
				if (options.onClose){
					options.onClose.call(this);
				} else {
					opts.onClose.call(this);
				}
				dlg.dialog('destroy');
			}
		}));
		dlg.dialog('dialog').css(opts.style);
		dlg.dialog('open');
		return dlg;
	}
	
	/**
	 * create a dialog, when dialog is closed destroy it
	 */
	function createDialog(options){
		bindEvents();
		var dlg = $('<div class="messager-body"></div>').appendTo('body');
		dlg.dialog($.extend({}, options, {
			noheader: (options.title?false:true),
			onClose: function(){
				unbindEvents();
				if (options.onClose){
					options.onClose.call(this);
				}
				dlg.dialog('destroy');
				setPositions();
				// setTimeout(function(){
				// }, 100);
			}
		}));
		var win = dlg.dialog('dialog').addClass('messager-window');
		win.find('.dialog-button').addClass('messager-button').find('a:first').focus();
		return dlg;
	}
	function closeDialog(dlg, cbValue){
		var opts = dlg.dialog('options');
		dlg.dialog('close');
		opts.fn(cbValue);
	}

	function setPositions(){
		var top = 20 + document.body.scrollTop+document.documentElement.scrollTop;
		$('body>.messager-tip').each(function(){
			$(this).animate({top:top},200);
			top += $(this)._outerHeight()+10;
		});
	}

	$.messager = {
		show: function(options){
			return createWindow(options);
		},

		tip: function(msg){
			var opts = typeof msg == 'object' ? msg : {msg:msg};
			if (opts.timeout == null) {
				opts.timeout = 2000;
			}
			var top = 0;
			var lastTip = $('body>.messager-tip').last();
			if (lastTip.length) {
				top = parseInt(lastTip.css('top')) + lastTip._outerHeight();
			}
			var cls = opts.icon ? 'messager-icon messager-'+opts.icon : '';
			opts = $.extend({}, $.messager.defaults, {
				content: '<div class="' + cls + '"></div>'
						+ '<div style="white-space:nowrap">' + opts.msg + '</div>'
						+ '<div style="clear:both;"></div>',
				border: false,
				noheader: true,
				modal: false,
				title: null,
				width: 'auto',
				height: 'auto',
				minHeight: null,
				shadow: false,
				top: top,
				cls: 'messager-tip',
				bodyCls: 'f-row f-vcenter f-full'
			}, opts);
			var dlg = createDialog(opts);
			if (opts.timeout) {
				setTimeout(function(){
					if ($(dlg).closest('body').length){
						$(dlg).dialog('close');
					}
				}, opts.timeout);
			}
			setTimeout(function(){
				setPositions();
			}, 0);
			return dlg;
		},
		
		alert: function(title, msg, icon, fn) {
			var opts = typeof title == 'object' ? title : {title:title, msg:msg, icon:icon, fn:fn};
			var cls = opts.icon ? 'messager-icon messager-'+opts.icon : '';
			opts = $.extend({}, $.messager.defaults, {
				content: '<div class="' + cls + '"></div>'
						+ '<div>' + opts.msg + '</div>'
						+ '<div style="clear:both;"></div>'
			}, opts);
			if (!opts.buttons){
				opts.buttons = [{
					text: opts.ok,
					onClick: function(){closeDialog(dlg);}
				}]
			}

			var dlg = createDialog(opts);
			return dlg;
		},
		
		confirm: function(title, msg, fn) {
			var opts = typeof title == 'object' ? title : {title:title, msg:msg, fn:fn};
			opts = $.extend({}, $.messager.defaults, {
				content: '<div class="messager-icon messager-question"></div>'
						+ '<div>' + opts.msg + '</div>'
						+ '<div style="clear:both;"></div>'
			}, opts);
			if (!opts.buttons){
				opts.buttons = [{
					text: opts.ok,
					onClick: function(){closeDialog(dlg, true);}
				},{
					text: opts.cancel,
					onClick: function(){closeDialog(dlg, false);}
				}];
			}

			var dlg = createDialog(opts);
			return dlg;
		},
		
		prompt: function(title, msg, fn) {
			var opts = typeof title == 'object' ? title : {title:title, msg:msg, fn:fn};
			opts = $.extend({}, $.messager.defaults, {
				content: '<div class="messager-icon messager-question"></div>'
						+ '<div>' + opts.msg + '</div>'
						+ '<br>'
						+ '<div style="clear:both;"></div>'
						+ '<div><input class="messager-input" type="text"></div>'
			}, opts);
			if (!opts.buttons){
				opts.buttons = [{
					text: opts.ok,
					onClick: function(){closeDialog(dlg, dlg.find('.messager-input').val());}
				},{
					text: opts.cancel,
					onClick: function(){closeDialog(dlg);}
				}]
			}

			var dlg = createDialog(opts);
			dlg.find('.messager-input').focus();
			return dlg;
		},
		
		progress: function(options){
			var methods = {
				bar: function(){	// get the progress bar object
					return $('body>div.messager-window').find('div.messager-p-bar');
				},
				close: function(){	// close the progress window
					var dlg = $('body>div.messager-window>div.messager-body:has(div.messager-progress)');
					if (dlg.length){
						dlg.dialog('close');
					}
				}
			};
			
			if (typeof options == 'string'){
				var method = methods[options];
				return method();
			}

			options = options || {};
			var opts = $.extend({}, {
				title: '',
				minHeight: 0,
				content: undefined,
				msg: '',	// The message box body text
				text: undefined,	// The text to display in the progress bar
				interval: 300	// The length of time in milliseconds between each progress update
			}, options);

			var dlg = createDialog($.extend({}, $.messager.defaults, {
				content: '<div class="messager-progress"><div class="messager-p-msg">' + opts.msg + '</div><div class="messager-p-bar"></div></div>',
				closable: false,
				doSize: false
			}, opts, {
				onClose: function(){
					if (this.timer){
						clearInterval(this.timer);
					}
					if (options.onClose){
						options.onClose.call(this);
					} else {
						$.messager.defaults.onClose.call(this);
					}
				}				
			}));
			var bar = dlg.find('div.messager-p-bar');
			bar.progressbar({
				text: opts.text
			});
			dlg.dialog('resize');
			
			if (opts.interval){
				dlg[0].timer = setInterval(function(){
					var v = bar.progressbar('getValue');
					v += 10;
					if (v > 100) v = 0;
					bar.progressbar('setValue', v);
				}, opts.interval);
			}
			return dlg;
		}
	};
	
	$.messager.defaults = $.extend({}, $.fn.dialog.defaults, {
		ok: 'Ok',
		cancel: 'Cancel',
		width: 300,
		height: 'auto',
		minHeight: 150,
		modal: true,
		collapsible: false,
		minimizable: false,
		maximizable: false,
		resizable: false,
		fn: function(){}
	});
	
})(jQuery);
