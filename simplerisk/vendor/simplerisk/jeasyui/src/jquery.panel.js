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
 * panel - EasyUI for jQuery
 * 
 */
(function($){
	$.fn._remove = function(){
		return this.each(function(){
			$(this).remove();
			try{
				this.outerHTML = '';
			} catch(err){}
		});
	}
	function removeNode(node){
		node._remove();
	}
	
	function setSize(target, param){
		var state = $.data(target, 'panel');
		var opts = state.options;
		var panel = state.panel;
		var pheader = panel.children('.panel-header');
		var pbody = panel.children('.panel-body');
		var pfooter = panel.children('.panel-footer');
		var isHorizontal = (opts.halign=='left' || opts.halign=='right');
		
		if (param){
			$.extend(opts, {
				width: param.width,
				height: param.height,
				minWidth: param.minWidth,
				maxWidth: param.maxWidth,
				minHeight: param.minHeight,
				maxHeight: param.maxHeight,
				left: param.left,
				top: param.top
			});
			opts.hasResized = false;
		}

		var oldWidth = panel.outerWidth();
		var oldHeight = panel.outerHeight();
		panel._size(opts);
		var newWidth = panel.outerWidth();
		var newHeight = panel.outerHeight();
		if (opts.hasResized && (oldWidth == newWidth && oldHeight == newHeight)){
			return;
		}
		opts.hasResized = true;
		// pheader.add(pbody)._outerWidth(panel.width());
		if (!isHorizontal){
			pheader._outerWidth(panel.width());
		}
		pbody._outerWidth(panel.width());
		if (!isNaN(parseInt(opts.height))){
			if (isHorizontal){
				if (opts.header){
					var headerWidth = $(opts.header)._outerWidth();
				} else {
					pheader.css('width','');
					var headerWidth = pheader._outerWidth();
				}
				var ptitle = pheader.find('.panel-title');
				headerWidth += Math.min(ptitle._outerWidth(),ptitle._outerHeight());
				var headerHeight = panel.height();
				pheader._outerWidth(headerWidth)._outerHeight(headerHeight);
				ptitle._outerWidth(pheader.height());
				pbody._outerWidth(panel.width()-headerWidth-pfooter._outerWidth())._outerHeight(headerHeight);
				pfooter._outerHeight(headerHeight);
				// pbody.css({left:'',right:''}).css(opts.halign, (pheader.position()[opts.halign]+headerWidth)+'px');
				pbody.css({left:'',right:''});
				if (pheader.length){
					pbody.css(opts.halign, (pheader.position()[opts.halign]+headerWidth)+'px');
				}
				opts.panelCssWidth = panel.css('width');
				if (opts.collapsed){
					panel._outerWidth(headerWidth+pfooter._outerWidth());
				}
			} else {
				// pheader.css('height','');
				pbody._outerHeight(panel.height() - pheader._outerHeight() - pfooter._outerHeight());
			}
		} else {
			pbody.css('height', '');
			var min = $.parser.parseValue('minHeight', opts.minHeight, panel.parent());
			var max = $.parser.parseValue('maxHeight', opts.maxHeight, panel.parent());
			var distance = pheader._outerHeight() + pfooter._outerHeight() + panel._outerHeight() - panel.height();
			pbody._size('minHeight', min ? (min - distance) : '');
			pbody._size('maxHeight', max ? (max - distance) : '');
		}
		panel.css({
			height: (isHorizontal?undefined:''),
			minHeight: '',
			maxHeight: '',
			left: opts.left,
			top: opts.top
		});

		opts.onResize.apply(target, [opts.width, opts.height]);
		
		$(target).panel('doLayout');
//		$(target).find('>div:visible,>form>div:visible').triggerHandler('_resize');
	}
	
	function movePanel(target, param){
		var state = $.data(target, 'panel');
		var opts = state.options;
		var panel = state.panel;
		if (param){
			if (param.left != null) opts.left = param.left;
			if (param.top != null) opts.top = param.top;
		}
		panel.css({
			left: opts.left,
			top: opts.top
		});
		panel.find('.tooltip-f').each(function(){
			$(this).tooltip('reposition');
		});
		opts.onMove.apply(target, [opts.left, opts.top]);
	}
	
	function wrapPanel(target){
		$(target).addClass('panel-body')._size('clear');
		var panel = $('<div class="panel"></div>').insertBefore(target);
		panel[0].appendChild(target);
		panel._bind('_resize', function(e, force){
			if ($(this).hasClass('easyui-fluid') || force){
				setSize(target,{});
			}
			return false;
		});
		
		return panel;
	}
	
	function createPanel(target){
		var state = $.data(target, 'panel');
		var opts = state.options;
		var panel = state.panel;
		panel.css(opts.style);
		panel.addClass(opts.cls);
		panel.removeClass('panel-hleft panel-hright').addClass('panel-h'+opts.halign);
		
		_addHeader();
		_addFooter();
		
		var header = $(target).panel('header');
		var body = $(target).panel('body');
		var footer = $(target).siblings('.panel-footer');
		if (opts.border){
			header.removeClass('panel-header-noborder');
			body.removeClass('panel-body-noborder');
			footer.removeClass('panel-footer-noborder');
		} else {
			header.addClass('panel-header-noborder');
			body.addClass('panel-body-noborder');
			footer.addClass('panel-footer-noborder');
		}
		header.addClass(opts.headerCls);
		body.addClass(opts.bodyCls);
		
		$(target).attr('id', opts.id || '');
		if (opts.content){
			$(target).panel('clear');
			$(target).html(opts.content);
			$.parser.parse($(target));
		}
		
		function _addHeader(){
			if (opts.noheader || (!opts.title && !opts.header)){
				removeNode(panel.children('.panel-header'));
				panel.children('.panel-body').addClass('panel-body-noheader');
			} else {
				if (opts.header){
					$(opts.header).addClass('panel-header').prependTo(panel);
				} else {
					var header = panel.children('.panel-header');
					if (!header.length){
						header = $('<div class="panel-header"></div>').prependTo(panel);
					}
					if (!$.isArray(opts.tools)){
						header.find('div.panel-tool .panel-tool-a').appendTo(opts.tools);
					}
					header.empty();
					var htitle = $('<div class="panel-title"></div>').html(opts.title).appendTo(header);
					if (opts.iconCls){
						htitle.addClass('panel-with-icon');
						$('<div class="panel-icon"></div>').addClass(opts.iconCls).appendTo(header);
					}
					if (opts.halign=='left' || opts.halign=='right'){
						htitle.addClass('panel-title-'+opts.titleDirection);
					}
					var tool = $('<div class="panel-tool"></div>').appendTo(header);
					tool._bind('click', function(e){
						e.stopPropagation();
					});
					
					if (opts.tools){
						if ($.isArray(opts.tools)){
							$.map(opts.tools, function(t){
								_buildTool(tool, t.iconCls, eval(t.handler));
							});
						} else {
							$(opts.tools).children().each(function(){
								$(this).addClass($(this).attr('iconCls')).addClass('panel-tool-a').appendTo(tool);
							});
						}
					}
					if (opts.collapsible){
						_buildTool(tool, 'panel-tool-collapse', function(){
							if (opts.collapsed == true){
								expandPanel(target, true);
							} else {
								collapsePanel(target, true);
							}
						});
					}
					if (opts.minimizable){
						_buildTool(tool, 'panel-tool-min', function(){
							minimizePanel(target);
						});
					}
					if (opts.maximizable){
						_buildTool(tool, 'panel-tool-max', function(){
							if (opts.maximized == true){
								restorePanel(target);
							} else {
								maximizePanel(target);
							}
						});
					}
					if (opts.closable){
						_buildTool(tool, 'panel-tool-close', function(){
							closePanel(target);
						});
					}
					
				}
				panel.children('div.panel-body').removeClass('panel-body-noheader');
			}
		}
		function _buildTool(c, icon, handler){
			var a = $('<a href="javascript:;"></a>').addClass(icon).appendTo(c);
			a._bind('click', handler);
		}
		function _addFooter(){
			if (opts.footer){
				$(opts.footer).addClass('panel-footer').appendTo(panel);
				$(target).addClass('panel-body-nobottom');
			} else {
				panel.children('.panel-footer').remove();
				$(target).removeClass('panel-body-nobottom');
			}
		}
	}
	
	/**
	 * load content from remote site if the href attribute is defined
	 */
	function loadData(target, params){
		var state = $.data(target, 'panel');
		var opts = state.options;
		if (param){opts.queryParams = params}
		if (!opts.href){return;}
		if (!state.isLoaded || !opts.cache){
			var param = $.extend({}, opts.queryParams);
			if (opts.onBeforeLoad.call(target, param) == false){return}
			state.isLoaded = false;
			// $(target).panel('clear');
			if (opts.loadingMessage){
				$(target).panel('clear');
				$(target).html($('<div class="panel-loading"></div>').html(opts.loadingMessage));
			}
			opts.loader.call(target, param, function(data){
				var content = opts.extractor.call(target, data);
				$(target).panel('clear');
				$(target).html(content);
				$.parser.parse($(target));
				opts.onLoad.apply(target, arguments);
				state.isLoaded = true;
			}, function(){
				opts.onLoadError.apply(target, arguments);
			});
		}
	}
	
	/**
	 * clear the panel content.
	 */
	function clearPanel(target){
		var t = $(target);
		t.find('.combo-f').each(function(){
			$(this).combo('destroy');
		});
		t.find('.m-btn').each(function(){
			$(this).menubutton('destroy');
		});
		t.find('.s-btn').each(function(){
			$(this).splitbutton('destroy');
		});
		t.find('.tooltip-f').each(function(){
			$(this).tooltip('destroy');
		});
		t.children('div').each(function(){
			$(this)._size('unfit');
		});
		t.empty();
	}
	
	function doLayout(target){
		$(target).panel('doLayout', true);
//		$(target).find('div.panel:visible,div.accordion:visible,div.tabs-container:visible,div.layout:visible').each(function(){
//			$(this).triggerHandler('_resize', [true]);
//		});
	}
	
	function openPanel(target, forceOpen){
		var state = $.data(target, 'panel');
		var opts = state.options;
		var panel = state.panel;
		
		if (forceOpen != true){
			if (opts.onBeforeOpen.call(target) == false) return;
		}
		panel.stop(true, true);
		if ($.isFunction(opts.openAnimation)){
			opts.openAnimation.call(target, cb);
		} else {
			switch(opts.openAnimation){
			case 'slide':
				panel.slideDown(opts.openDuration, cb);
				break;
			case 'fade':
				panel.fadeIn(opts.openDuration, cb);
				break;
			case 'show':
				panel.show(opts.openDuration, cb);
				break;
			default:
				panel.show();
				cb();
			}
		}
		
		function cb(){
			opts.closed = false;
			opts.minimized = false;
			var tool = panel.children('.panel-header').find('a.panel-tool-restore');
			if (tool.length){
				opts.maximized = true;
			}
			opts.onOpen.call(target);
			
			if (opts.maximized == true) {
				opts.maximized = false;
				maximizePanel(target);
			}
			if (opts.collapsed == true) {
				opts.collapsed = false;
				collapsePanel(target);
			}
			
			if (!opts.collapsed){
				if (opts.href && (!state.isLoaded || !opts.cache)){
					loadData(target);
					doLayout(target);
					opts.doneLayout = true;
				}
			}
			if (!opts.doneLayout){
				opts.doneLayout = true;
				doLayout(target);
			}
		}
	}
	
	function closePanel(target, forceClose){
		var state = $.data(target, 'panel');
		var opts = state.options;
		var panel = state.panel;
		
		if (forceClose != true){
			if (opts.onBeforeClose.call(target) == false) return;
		}
		panel.find('.tooltip-f').each(function(){
			$(this).tooltip('hide');
		});
		panel.stop(true, true);
		panel._size('unfit');
		
		if ($.isFunction(opts.closeAnimation)){
			opts.closeAnimation.call(target, cb);
		} else {
			switch(opts.closeAnimation){
			case 'slide':
				panel.slideUp(opts.closeDuration, cb);
				break;
			case 'fade':
				panel.fadeOut(opts.closeDuration, cb);
				break;
			case 'hide':
				panel.hide(opts.closeDuration, cb);
				break;
			default:
				panel.hide();
				cb();
			}
		}
		
		function cb(){
			opts.closed = true;
			opts.onClose.call(target);
		}
	}
	
	function destroyPanel(target, forceDestroy){
		var state = $.data(target, 'panel');
		var opts = state.options;
		var panel = state.panel;
		
		if (forceDestroy != true){
			if (opts.onBeforeDestroy.call(target) == false) return;
		}
		$(target).panel('clear').panel('clear', 'footer');
		removeNode(panel);
		opts.onDestroy.call(target);
	}
	
	function collapsePanel(target, animate){
		var opts = $.data(target, 'panel').options;
		var panel = $.data(target, 'panel').panel;
		var body = panel.children('.panel-body');
		var header = panel.children('.panel-header');
		var tool = header.find('a.panel-tool-collapse');
		
		if (opts.collapsed == true) return;
		
		body.stop(true, true);	// stop animation
		if (opts.onBeforeCollapse.call(target) == false) return;
		
		tool.addClass('panel-tool-expand');
		if (animate == true){
			if (opts.halign=='left' || opts.halign=='right'){
				panel.animate({width:header._outerWidth()+panel.children('.panel-footer')._outerWidth()}, function(){
					cb();
				});
			} else {
				body.slideUp('normal', function(){
					cb();
				});
			}
		} else {
			if (opts.halign=='left' || opts.halign=='right'){
				panel._outerWidth(header._outerWidth()+panel.children('.panel-footer')._outerWidth());
			}
			cb();
		}

		function cb(){
			body.hide();
			opts.collapsed = true;
			opts.onCollapse.call(target);
		}
	}
	
	function expandPanel(target, animate){
		var opts = $.data(target, 'panel').options;
		var panel = $.data(target, 'panel').panel;
		var body = panel.children('.panel-body');
		var tool = panel.children('.panel-header').find('a.panel-tool-collapse');
		
		if (opts.collapsed == false) return;
		
		body.stop(true, true);	// stop animation
		if (opts.onBeforeExpand.call(target) == false) return;
		
		tool.removeClass('panel-tool-expand');
		if (animate == true){
			if (opts.halign=='left' || opts.halign=='right'){
				body.show();
				panel.animate({width:opts.panelCssWidth}, function(){
					cb();
				});
			} else {
				body.slideDown('normal', function(){
					cb();
				});
			}
		} else {
			if (opts.halign=='left' || opts.halign=='right'){
				panel.css('width',opts.panelCssWidth);
			}
			cb();
		}

		function cb(){
			body.show();
			opts.collapsed = false;
			opts.onExpand.call(target);
			loadData(target);
			doLayout(target);
		}
	}
	
	function maximizePanel(target){
		var opts = $.data(target, 'panel').options;
		var panel = $.data(target, 'panel').panel;
		var tool = panel.children('.panel-header').find('a.panel-tool-max');
		
		if (opts.maximized == true) return;
		
		tool.addClass('panel-tool-restore');
		
		if (!$.data(target, 'panel').original){
			$.data(target, 'panel').original = {
				width: opts.width,
				height: opts.height,
				left: opts.left,
				top: opts.top,
				fit: opts.fit
			};
		}
		opts.left = 0;
		opts.top = 0;
		opts.fit = true;
		setSize(target);
		opts.minimized = false;
		opts.maximized = true;
		opts.onMaximize.call(target);
	}
	
	function minimizePanel(target){
		var opts = $.data(target, 'panel').options;
		var panel = $.data(target, 'panel').panel;
		panel._size('unfit');
		panel.hide();
		opts.minimized = true;
		opts.maximized = false;
		opts.onMinimize.call(target);
	}
	
	function restorePanel(target){
		var opts = $.data(target, 'panel').options;
		var panel = $.data(target, 'panel').panel;
		var tool = panel.children('.panel-header').find('a.panel-tool-max');
		
		if (opts.maximized == false) return;
		
		panel.show();
		tool.removeClass('panel-tool-restore');
		$.extend(opts, $.data(target, 'panel').original);
//		var original = $.data(target, 'panel').original;
//		opts.width = original.width;
//		opts.height = original.height;
//		opts.left = original.left;
//		opts.top = original.top;
//		opts.fit = original.fit;
		setSize(target);
		opts.minimized = false;
		opts.maximized = false;
		$.data(target, 'panel').original = null;
		opts.onRestore.call(target);
	}
	
	
	function setTitle(target, title){
		$.data(target, 'panel').options.title = title;
		$(target).panel('header').find('div.panel-title').html(title);
	}
	
	var resizeTimer = null;
	$(window)._unbind('.panel')._bind('resize.panel', function(){
		if (resizeTimer){
			clearTimeout(resizeTimer);
		}
		resizeTimer = setTimeout(function(){
			var layout = $('body.layout');
			if (layout.length){
				layout.layout('resize');
//				$('body').children('.easyui-fluid:visible').trigger('_resize');
				$('body').children('.easyui-fluid:visible').each(function(){
					$(this).triggerHandler('_resize');
				});
			} else {
				$('body').panel('doLayout');
//				$('body').children('div.panel:visible,div.accordion:visible,div.tabs-container:visible,div.layout:visible').triggerHandler('_resize');
			}
			resizeTimer = null;
		}, 100);
	});
	
	$.fn.panel = function(options, param){
		if (typeof options == 'string'){
			return $.fn.panel.methods[options](this, param);
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'panel');
			var opts;
			if (state){
				opts = $.extend(state.options, options);
				state.isLoaded = false;
			} else {
				opts = $.extend({}, $.fn.panel.defaults, $.fn.panel.parseOptions(this), options);
				$(this).attr('title', '');
				state = $.data(this, 'panel', {
					options: opts,
					panel: wrapPanel(this),
					isLoaded: false
				});
			}
			
			createPanel(this);
			
			$(this).show();
			if (opts.doSize == true){
				state.panel.css('display','block');
				setSize(this);
			}
			if (opts.closed == true || opts.minimized == true){
				state.panel.hide();
			} else {
				openPanel(this);
			}
		});
	};
	
	$.fn.panel.methods = {
		options: function(jq){
			return $.data(jq[0], 'panel').options;
		},
		panel: function(jq){
			return $.data(jq[0], 'panel').panel;
		},
		header: function(jq){
			return $.data(jq[0], 'panel').panel.children('.panel-header');
		},
		footer: function(jq){
			return jq.panel('panel').children('.panel-footer');
		},
		body: function(jq){
			return $.data(jq[0], 'panel').panel.children('.panel-body');
		},
		setTitle: function(jq, title){
			return jq.each(function(){
				setTitle(this, title);
			});
		},
		open: function(jq, forceOpen){
			return jq.each(function(){
				openPanel(this, forceOpen);
			});
		},
		close: function(jq, forceClose){
			return jq.each(function(){
				closePanel(this, forceClose);
			});
		},
		destroy: function(jq, forceDestroy){
			return jq.each(function(){
				destroyPanel(this, forceDestroy);
			});
		},
		clear: function(jq, type){
			return jq.each(function(){
				clearPanel(type=='footer' ? $(this).panel('footer') : this);
				// clearPanel(this);
			});
		},
		refresh: function(jq, href){
			return jq.each(function(){
				var state = $.data(this, 'panel');
				state.isLoaded = false;
				if (href){
					if (typeof href == 'string'){
						state.options.href = href;
					} else {
						state.options.queryParams = href;
					}
				}
				loadData(this);
			});
		},
		resize: function(jq, param){
			return jq.each(function(){
				setSize(this, param||{});
			});
		},
		doLayout: function(jq, all){
			return jq.each(function(){
				_layout(this, 'body');
				_layout($(this).siblings('.panel-footer')[0], 'footer');

				function _layout(target, type){
					if (!target){return}
					var isBody = target == $('body')[0];
					var s = $(target).find('div.panel:visible,div.accordion:visible,div.tabs-container:visible,div.layout:visible,.easyui-fluid:visible').filter(function(index, el){
						var p = $(el).parents('.panel-'+type+':first');
						return isBody ? p.length==0 : p[0]==target;
					});
//					s.trigger('_resize', [all||false]);
					s.each(function(){
						$(this).triggerHandler('_resize', [all||false]);
					});
				}
			});
		},
		move: function(jq, param){
			return jq.each(function(){
				movePanel(this, param);
			});
		},
		maximize: function(jq){
			return jq.each(function(){
				maximizePanel(this);
			});
		},
		minimize: function(jq){
			return jq.each(function(){
				minimizePanel(this);
			});
		},
		restore: function(jq){
			return jq.each(function(){
				restorePanel(this);
			});
		},
		collapse: function(jq, animate){
			return jq.each(function(){
				collapsePanel(this, animate);
			});
		},
		expand: function(jq, animate){
			return jq.each(function(){
				expandPanel(this, animate);
			});
		}
	};
	
	$.fn.panel.parseOptions = function(target){
		var t = $(target);
		var hh = t.children('.panel-header,header');
		var ff = t.children('.panel-footer,footer');
		return $.extend({}, $.parser.parseOptions(target, ['id','width','height','left','top',
		        'title','iconCls','cls','headerCls','bodyCls','tools','href','method','header','footer','halign','titleDirection',
		        {cache:'boolean',fit:'boolean',border:'boolean',noheader:'boolean'},
		        {collapsible:'boolean',minimizable:'boolean',maximizable:'boolean'},
		        {closable:'boolean',collapsed:'boolean',minimized:'boolean',maximized:'boolean',closed:'boolean'},
		        'openAnimation','closeAnimation',
		        {openDuration:'number',closeDuration:'number'},
		]), {
			loadingMessage: (t.attr('loadingMessage')!=undefined ? t.attr('loadingMessage') : undefined),
			header: (hh.length ? hh.removeClass('panel-header') : undefined),
			footer: (ff.length ? ff.removeClass('panel-footer') : undefined)
		});
	};
	
	$.fn.panel.defaults = {
		id: null,
		title: null,
		iconCls: null,
		width: 'auto',
		height: 'auto',
		left: null,
		top: null,
		cls: null,
		headerCls: null,
		bodyCls: null,
		style: {},
		href: null,
		cache: true,
		fit: false,
		border: true,
		doSize: true,	// true to set size and do layout
		noheader: false,
		content: null,	// the body content if specified
		halign: 'top',	// the header alignment: 'top','left','right'
		titleDirection: 'down',	// up,down
		
		collapsible: false,
		minimizable: false,
		maximizable: false,
		closable: false,
		collapsed: false,
		minimized: false,
		maximized: false,
		closed: false,
		
		openAnimation: false,
		openDuration: 400,
		closeAnimation: false,
		closeDuration: 400,
		
		// custom tools, every tool can contain two properties: iconCls and handler
		// iconCls is a icon CSS class
		// handler is a function, which will be run when tool button is clicked
		tools: null,
		footer: null,
		header: null,
		
		queryParams: {},
		method: 'get',
		href: null,
		loadingMessage: 'Loading...',
		loader: function(param, success, error){
			var opts = $(this).panel('options');
			if (!opts.href){return false}
			$.ajax({
				type: opts.method,
				url: opts.href,
				cache: false,
				data: param,
				dataType: 'html',
				success: function(data){
					success(data);
				},
				error: function(){
					error.apply(this, arguments);
				}
			});
		},
		extractor: function(data){	// define how to extract the content from ajax response, return extracted data
			var pattern = /<body[^>]*>((.|[\n\r])*)<\/body>/im;
			var matches = pattern.exec(data);
			if (matches){
				return matches[1];	// only extract body content
			} else {
				return data;
			}
		},
		
		onBeforeLoad: function(param){},
		onLoad: function(){},
		onLoadError: function(){},
		onBeforeOpen: function(){},
		onOpen: function(){},
		onBeforeClose: function(){},
		onClose: function(){},
		onBeforeDestroy: function(){},
		onDestroy: function(){},
		onResize: function(width,height){},
		onMove: function(left,top){},
		onMaximize: function(){},
		onRestore: function(){},
		onMinimize: function(){},
		onBeforeCollapse: function(){},
		onBeforeExpand: function(){},
		onCollapse: function(){},
		onExpand: function(){}
	};
})(jQuery);
