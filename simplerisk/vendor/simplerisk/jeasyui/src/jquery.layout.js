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
 * layout - EasyUI for jQuery
 * 
 * Dependencies:
 *   resizable
 *   panel
 */
(function($){
	var resizing = false;	// indicate if the region panel is being resized
	
	function setSize(container, param){
		var state = $.data(container, 'layout');
		var opts = state.options;
		var panels = state.panels;
		var cc = $(container);
		
		if (param){
			$.extend(opts, {
				width: param.width,
				height: param.height
			});
		}
		if (container.tagName.toLowerCase() == 'body'){
			// opts.fit = true;
			// cc._size(opts, $('body'))._size('clear');
			cc._size('fit');
		} else {
			cc._size(opts);
		}
		
		var cpos = {
			top:0,
			left:0,
			width:cc.width(),
			height:cc.height()
		};
		
		setVSize(isVisible(panels.expandNorth) ? panels.expandNorth : panels.north, 'n');
		setVSize(isVisible(panels.expandSouth) ? panels.expandSouth : panels.south, 's');
		setHSize(isVisible(panels.expandEast) ? panels.expandEast : panels.east, 'e');
		setHSize(isVisible(panels.expandWest) ? panels.expandWest : panels.west, 'w');
		
		panels.center.panel('resize', cpos);
		
		function setVSize(pp, type){
			if (!pp.length || !isVisible(pp)){return}
			var opts = pp.panel('options');
			pp.panel('resize', {
				width: cc.width(),
				height: opts.height
			});
			var height = pp.panel('panel').outerHeight();
			pp.panel('move', {
				left: 0,
				top: (type=='n' ? 0 : cc.height()-height)
			});
			cpos.height -= height;
			if (type == 'n'){
				cpos.top += height;
				if (!opts.split && opts.border){cpos.top--;}
			}
			if (!opts.split && opts.border){
				cpos.height++;
			}
		}
		function setHSize(pp, type){
			if (!pp.length || !isVisible(pp)){return}
			var opts = pp.panel('options');
			pp.panel('resize', {
				width: opts.width,
				height: cpos.height
			});
			var width = pp.panel('panel').outerWidth();
			pp.panel('move', {
				left: (type=='e' ? cc.width()-width : 0),
				top: cpos.top
			});
			cpos.width -= width;
			if (type == 'w'){
				cpos.left += width;
				if (!opts.split && opts.border){cpos.left--;}
			}
			if (!opts.split && opts.border){cpos.width++;}
		}
	}
	
	/**
	 * initialize and wrap the layout
	 */
	function init(container){
		var cc = $(container);
		
		cc.addClass('layout');
		
		function _add(el){
			var popts = $.fn.layout.parsePanelOptions(el);
			if ('north,south,east,west,center'.indexOf(popts.region) >= 0){
				addPanel(container, popts, el);
			}
		}

		var opts = cc.layout('options');
		var onAdd = opts.onAdd;
		opts.onAdd = function(){};
		cc.find('>div,>form>div').each(function(){
			_add(this);
		});
		opts.onAdd = onAdd;
		
		cc.append('<div class="layout-split-proxy-h"></div><div class="layout-split-proxy-v"></div>');
		
		cc._bind('_resize', function(e,force){
			if ($(this).hasClass('easyui-fluid') || force){
				setSize(container);
			}
			return false;
		});
	}
	
	/**
	 * Add a new region panel on specified element
	 */
	function addPanel(container, param, el){
		param.region = param.region || 'center';
		var panels = $.data(container, 'layout').panels;
		var cc = $(container);
		var dir = param.region;
		
		if (panels[dir].length) return;	// the region panel is already exists
		
		var pp = $(el);
		if (!pp.length){
			pp = $('<div></div>').appendTo(cc);	// the predefined panel isn't exists, create a new panel instead
		}
		
		var popts = $.extend({}, $.fn.layout.paneldefaults, {
			width: (pp.length ? parseInt(pp[0].style.width) || pp.outerWidth() : 'auto'),
			height: (pp.length ? parseInt(pp[0].style.height) || pp.outerHeight() : 'auto'),
			doSize: false,
			collapsible: true,
			onOpen: function(){
				var tool = $(this).panel('header').children('div.panel-tool');
				tool.children('a.panel-tool-collapse').hide();	// hide the old collapse button
				
				var buttonDir = {north:'up',south:'down',east:'right',west:'left'};
				if (!buttonDir[dir]) return;
				
				var iconCls = 'layout-button-' + buttonDir[dir];
				// add collapse tool to panel header
				var t = tool.children('a.' + iconCls);
				if (!t.length){
					t = $('<a href="javascript:;"></a>').addClass(iconCls).appendTo(tool);
					t._bind('click', {dir:dir}, function(e){
						collapsePanel(container, e.data.dir);
						return false;
					});
				}
				$(this).panel('options').collapsible ? t.show() : t.hide();
			}
		}, param, {
			cls: ((param.cls||'') + ' layout-panel layout-panel-' + dir),
			bodyCls: ((param.bodyCls||'') + ' layout-body')
		});
		
		pp.panel(popts);	// create region panel
		panels[dir] = pp;
		
		var handles = {north:'s',south:'n',east:'w',west:'e'};
		var panel = pp.panel('panel');
		if (pp.panel('options').split){
			panel.addClass('layout-split-' + dir);
		}
		panel.resizable($.extend({}, {
			handles: (handles[dir]||''),
			disabled: (!pp.panel('options').split),
			onStartResize: function(e){
				resizing = true;
				
				if (dir == 'north' || dir == 'south'){
					var proxy = $('>div.layout-split-proxy-v', container);
				} else {
					var proxy = $('>div.layout-split-proxy-h', container);
				}
				var top=0,left=0,width=0,height=0;
				var pos = {display: 'block'};
				if (dir == 'north'){
					pos.top = parseInt(panel.css('top')) + panel.outerHeight() - proxy.height();
					pos.left = parseInt(panel.css('left'));
					pos.width = panel.outerWidth();
					pos.height = proxy.height();
				} else if (dir == 'south'){
					pos.top = parseInt(panel.css('top'));
					pos.left = parseInt(panel.css('left'));
					pos.width = panel.outerWidth();
					pos.height = proxy.height();
				} else if (dir == 'east'){
					pos.top = parseInt(panel.css('top')) || 0;
					pos.left = parseInt(panel.css('left')) || 0;
					pos.width = proxy.width();
					pos.height = panel.outerHeight();
				} else if (dir == 'west'){
					pos.top = parseInt(panel.css('top')) || 0;
					pos.left = panel.outerWidth() - proxy.width();
					pos.width = proxy.width();
					pos.height = panel.outerHeight();
				}
				proxy.css(pos);
				
				$('<div class="layout-mask"></div>').css({
					left:0,
					top:0,
					width:cc.width(),
					height:cc.height()
				}).appendTo(cc);
			},
			onResize: function(e){
				if (dir == 'north' || dir == 'south'){
					var maxHeight = _getMaxSize(this);
					$(this).resizable('options').maxHeight = maxHeight;
					var proxy = $('>div.layout-split-proxy-v', container);
					var top = dir=='north' ? e.data.height-proxy.height() : $(container).height()-e.data.height;
					proxy.css('top', top);
				} else {
					var maxWidth = _getMaxSize(this);
					$(this).resizable('options').maxWidth = maxWidth;
					var proxy = $('>div.layout-split-proxy-h', container);
					var left = dir=='west' ? e.data.width-proxy.width() : $(container).width()-e.data.width;
					proxy.css('left', left);
				}
				return false;
			},
			onStopResize: function(e){
				cc.children('div.layout-split-proxy-v,div.layout-split-proxy-h').hide();
				pp.panel('resize',e.data);
				
				setSize(container);
				resizing = false;
				
				cc.find('>div.layout-mask').remove();
			}
		}, param));

		cc.layout('options').onAdd.call(container, dir);

		function _getMaxSize(p){
			var expandP = 'expand' + dir.substring(0,1).toUpperCase() + dir.substring(1);

			var pcenter = panels['center'];
			var minSizeName = (dir=='north'||dir=='south')?'minHeight':'minWidth';
			var maxSizeName = (dir=='north'||dir=='south')?'maxHeight':'maxWidth';
			var outerName = (dir=='north'||dir=='south')?'_outerHeight':'_outerWidth';
			var pmaxSize = $.parser.parseValue(maxSizeName,panels[dir].panel('options')[maxSizeName],$(container));
			var cminSize = $.parser.parseValue(minSizeName,pcenter.panel('options')[minSizeName],$(container));
			var maxSize = pcenter.panel('panel')[outerName]()-cminSize;
			if (isVisible(panels[expandP])){
				maxSize += panels[expandP][outerName]()-1;
			} else {
				maxSize += $(p)[outerName]();
			}
			if (maxSize>pmaxSize){
				maxSize = pmaxSize;
			}
			return maxSize;
		}
	}
	
	/**
	 * remove a region panel
	 */
	function removePanel(container, region){
		var panels = $.data(container, 'layout').panels;
		if (panels[region].length){
			panels[region].panel('destroy');
			panels[region] = $();
			var expandP = 'expand' + region.substring(0,1).toUpperCase() + region.substring(1);
			if (panels[expandP]){
				panels[expandP].panel('destroy');
				panels[expandP] = undefined;
			}
			$(container).layout('options').onRemove.call(container, region);
		}
	}
	
	function collapsePanel(container, region, animateSpeed){
		if (animateSpeed == undefined){animateSpeed = 'normal';}
		var state = $.data(container, 'layout');
		var panels = state.panels;
		
		var p = panels[region];
		var popts = p.panel('options');
		if (popts.onBeforeCollapse.call(p) == false) return;
		
		// expand panel name: expandNorth, expandSouth, expandWest, expandEast
		var expandP = 'expand' + region.substring(0,1).toUpperCase() + region.substring(1);
		if (!panels[expandP]){
			panels[expandP] = createExpandPanel(region);
			var ep = panels[expandP].panel('panel');
			if (!popts.expandMode){
				ep.css('cursor', 'default');
			} else {
				ep._bind('click', function(){
					if (popts.expandMode == 'dock'){
						expandPanel(container, region);
					} else {
						p.panel('expand',false).panel('open');
						var copts = getOption();
						p.panel('resize', copts.collapse);
						p.panel('panel')._unbind('.layout')._bind('mouseleave.layout', {region:region}, function(e){
							var that = this;
							state.collapseTimer = setTimeout(function(){
								$(that).stop(true,true);
								if (resizing == true){return;}
								if ($('body>div.combo-p>div.combo-panel:visible').length){return;}
								collapsePanel(container, e.data.region);
							}, state.options.collapseDelay);
						});
						p.panel('panel').animate(copts.expand, function(){
							$(container).layout('options').onExpand.call(container, region);
						});						
					}
					
					return false;
				});				
			}
		}
		
		var copts = getOption();
		if (!isVisible(panels[expandP])){
			panels.center.panel('resize', copts.resizeC);
		}
		p.panel('panel').animate(copts.collapse, animateSpeed, function(){
			p.panel('collapse',false).panel('close');
			panels[expandP].panel('open').panel('resize', copts.expandP);
			
			$(this)._unbind('.layout');
			$(container).layout('options').onCollapse.call(container, region);
		});
		
		/**
		 * create expand panel
		 */
		function createExpandPanel(dir){
			var iconMap = {
				'east':'left',
				'west':'right',
				'north':'down',
				'south':'up'
			};
			var isns = (popts.region=='north' || popts.region=='south');
			var icon = 'layout-button-' + iconMap[dir];			
			var p = $('<div></div>').appendTo(container);
			p.panel($.extend({}, $.fn.layout.paneldefaults, {
				cls: ('layout-expand layout-expand-' + dir),
				title: '&nbsp;',
				titleDirection: popts.titleDirection,
				iconCls: (popts.hideCollapsedContent ? null : popts.iconCls),
				closed: true,
				minWidth: 0,
				minHeight: 0,
				doSize: false,
				region: popts.region,
				collapsedSize: popts.collapsedSize,
				noheader: (!isns && popts.hideExpandTool),
				tools: ((isns && popts.hideExpandTool) ? null : [{
					iconCls: icon,
					handler:function(){
						expandPanel(container, region);
						return false;
					}
				}]),
				onResize: function(){
					var ptitle = $(this).children('.layout-expand-title');
					if (ptitle.length){
						var icon = $(this).children('.panel-icon');
						var iconHeight = icon.length>0 ? (icon._outerHeight()+2) : 0;
						ptitle._outerWidth($(this).height() - iconHeight);
						var left = ($(this).width()-Math.min(ptitle._outerWidth(),ptitle._outerHeight()))/2;
						var top = Math.max(ptitle._outerWidth(),ptitle._outerHeight());
						if (ptitle.hasClass('layout-expand-title-down')){
							left += Math.min(ptitle._outerWidth(),ptitle._outerHeight());
							top = 0;
						}
						top += iconHeight;
						ptitle.css({
							left: (left+'px'),
							top: (top+'px')
						});
					}
				}
			}));
			if (!popts.hideCollapsedContent){
				var title = typeof popts.collapsedContent=='function' ? popts.collapsedContent.call(p[0],popts.title) : popts.collapsedContent;
				isns ? p.panel('setTitle', title) : p.html(title);
			}
			p.panel('panel').hover(
				function(){$(this).addClass('layout-expand-over');},
				function(){$(this).removeClass('layout-expand-over');}
			);
			return p;
		}
		
		/**
		 * get collapse option:{
		 *   resizeC:{},
		 *   expand:{},
		 *   expandP:{},	// the expand holder panel
		 *   collapse:{}
		 * }
		 */
		function getOption(){
			var cc = $(container);
			var copts = panels.center.panel('options');
			var csize = popts.collapsedSize;
			
			if (region == 'east'){
				var pwidth = p.panel('panel')._outerWidth();
				var cwidth = copts.width + pwidth - csize;
				if (popts.split || !popts.border){cwidth++;}
				return {
					resizeC:{
						width: cwidth
					},
					expand:{
						left: cc.width() - pwidth
					},
					expandP:{
						top: copts.top,
						left: cc.width() - csize,
						width: csize,
						height: copts.height
					},
					collapse:{
						left: cc.width(),
						top: copts.top,
						height: copts.height
					}
				};
			} else if (region == 'west'){
				var pwidth = p.panel('panel')._outerWidth();
				var cwidth = copts.width + pwidth - csize;
				if (popts.split || !popts.border){cwidth++;}
				return {
					resizeC:{
						width: cwidth,
						left: csize - 1
					},
					expand:{
						left: 0
					},
					expandP:{
						left: 0,
						top: copts.top,
						width: csize,
						height: copts.height
					},
					collapse:{
						left: -pwidth,
						top: copts.top,
						height: copts.height
					}
				};
			} else if (region == 'north'){
				var pheight = p.panel('panel')._outerHeight();
				var hh = copts.height;
				if (!isVisible(panels.expandNorth)){
					hh += pheight - csize + ((popts.split || !popts.border)?1:0);
				}
				panels.east.add(panels.west).add(panels.expandEast).add(panels.expandWest).panel('resize', {
					top: csize - 1,
					height: hh
				});
				
				return {
					resizeC:{
						top: csize - 1,
						height: hh
					},
					expand:{
						top:0
					},
					expandP:{
						top: 0,
						left: 0,
						width: cc.width(),
						height: csize
					},
					collapse:{
						top: -pheight,
						width: cc.width()
					}
				};
			} else if (region == 'south'){
				var pheight = p.panel('panel')._outerHeight();
				var hh = copts.height;
				if (!isVisible(panels.expandSouth)){
					hh += pheight - csize + ((popts.split || !popts.border)?1:0);
				}
				panels.east.add(panels.west).add(panels.expandEast).add(panels.expandWest).panel('resize', {
					height: hh
				});
				
				return {
					resizeC:{
						height: hh
					},
					expand:{
						top: cc.height()-pheight
					},
					expandP:{
						top: cc.height() - csize,
						left: 0,
						width: cc.width(),
						height: csize
					},
					collapse:{
						top: cc.height(),
						width: cc.width()
					}
				};
			}
		}
	}
	
	function expandPanel(container, region){
		var panels = $.data(container, 'layout').panels;
		
		var p = panels[region];
		var popts = p.panel('options');
		if (popts.onBeforeExpand.call(p) == false){return;}
		
		var expandP = 'expand' + region.substring(0,1).toUpperCase() + region.substring(1);
		if (panels[expandP]){
			panels[expandP].panel('close');
			p.panel('panel').stop(true,true);
			p.panel('expand',false).panel('open');
			var eopts = getOption();
			p.panel('resize', eopts.collapse);
			p.panel('panel').animate(eopts.expand, function(){
				setSize(container);
				$(container).layout('options').onExpand.call(container, region);
			});
		}
		
		/**
		 * get expand option: {
		 *   collapse:{},
		 *   expand:{}
		 * }
		 */
		function getOption(){
			var cc = $(container);
			var copts = panels.center.panel('options');
			
			if (region == 'east' && panels.expandEast){
				return {
					collapse:{
						left: cc.width(),
						top: copts.top,
						height: copts.height
					},
					expand:{
						left: cc.width() - p.panel('panel')._outerWidth()
					}
				};
			} else if (region == 'west' && panels.expandWest){
				return {
					collapse:{
						left: -p.panel('panel')._outerWidth(),
						top: copts.top,
						height: copts.height
					},
					expand:{
						left: 0
					}
				};
			} else if (region == 'north' && panels.expandNorth){
				return {
					collapse:{
						top: -p.panel('panel')._outerHeight(),
						width: cc.width()
					},
					expand:{
						top: 0
					}
				};
			} else if (region == 'south' && panels.expandSouth){
				return {
					collapse:{
						top: cc.height(),
						width: cc.width()
					},
					expand:{
						top: cc.height()-p.panel('panel')._outerHeight()
					}
				};
			}
		}
	}
		
	function isVisible(pp){
		if (!pp) return false;
		if (pp.length){
			return pp.panel('panel').is(':visible');
		} else {
			return false;
		}
	}
	
	function initCollapse(container){
		var state = $.data(container, 'layout');
		var opts = state.options;
		var panels = state.panels;
		var onCollapse = opts.onCollapse;
		opts.onCollapse = function(){};
		_collapse('east');
		_collapse('west');
		_collapse('north');
		_collapse('south');
		opts.onCollapse = onCollapse;
		
		function _collapse(region){
			var p = panels[region];
			if (p.length && p.panel('options').collapsed){
				collapsePanel(container, region, 0);
			}
		}
	}
	
	function setSplit(container, region, isSplit){
		var p = $(container).layout('panel', region);
		p.panel('options').split = isSplit;
		var cls = 'layout-split-' + region;
		var panel = p.panel('panel').removeClass(cls);
		if (isSplit){panel.addClass(cls);}
		panel.resizable({disabled:(!isSplit)});
		setSize(container);
	}
	
	$.fn.layout = function(options, param){
		if (typeof options == 'string'){
			return $.fn.layout.methods[options](this, param);
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'layout');
			if (state){
				$.extend(state.options, options);
			} else {
				var opts = $.extend({}, $.fn.layout.defaults, $.fn.layout.parseOptions(this), options);
				$.data(this, 'layout', {
					options: opts,
					panels: {center:$(), north:$(), south:$(), east:$(), west:$()}
				});
				init(this);
//				bindEvents(this);
			}
			setSize(this);
			initCollapse(this);
		});
	};
	
	$.fn.layout.methods = {
		options: function(jq){
			return $.data(jq[0], 'layout').options;
		},
		resize: function(jq, param){
			return jq.each(function(){
				setSize(this, param);
			});
		},
		panel: function(jq, region){
			return $.data(jq[0], 'layout').panels[region];
		},
		collapse: function(jq, region){
			return jq.each(function(){
				collapsePanel(this, region);
			});
		},
		expand: function(jq, region){
			return jq.each(function(){
				expandPanel(this, region);
			});
		},
		add: function(jq, options){
			return jq.each(function(){
				addPanel(this, options);
				setSize(this);
				if ($(this).layout('panel', options.region).panel('options').collapsed){
					collapsePanel(this, options.region, 0);
				}
			});
		},
		remove: function(jq, region){
			return jq.each(function(){
				removePanel(this, region);
				setSize(this);
			});
		},
		split: function(jq, region){
			return jq.each(function(){
				setSplit(this, region, true);
			});
		},
		unsplit: function(jq, region){
			return jq.each(function(){
				setSplit(this, region, false);
			});
		},
		stopCollapsing: function(jq){
			return jq.each(function(){
				clearTimeout($(this).data('layout').collapseTimer);
			});
		}
	};
	
	$.fn.layout.parseOptions = function(target){
		return $.extend({}, $.parser.parseOptions(target,[{fit:'boolean'}]));
	};
	
	$.fn.layout.defaults = {
		fit: false,
		onExpand: function(region){},
		onCollapse: function(region){},
		onAdd: function(region){},
		onRemove: function(region){}
	};
	
	$.fn.layout.parsePanelOptions = function(target){
		var t = $(target);
		return $.extend({}, $.fn.panel.parseOptions(target), 
				$.parser.parseOptions(target, [
					'region',{split:'boolean',collpasedSize:'number',minWidth:'number',minHeight:'number',maxWidth:'number',maxHeight:'number'}
				]));
	};
	
	$.fn.layout.paneldefaults = $.extend({}, $.fn.panel.defaults, {
		region:null,	// possible values are: 'north','south','east','west','center'
		split:false,
		collapseDelay: 100,
		collapsedSize:32,
		expandMode:'float',	// possible values are: 'float','dock',null
		hideExpandTool:false,
		hideCollapsedContent:true,
		collapsedContent: function(title){
			var p = $(this);
			var opts = p.panel('options');
			if (opts.region == 'north' || opts.region == 'south'){
				return title;
			}
			var cc = [];
			if (opts.iconCls){
				cc.push('<div class="panel-icon '+opts.iconCls+'"></div>');
			}
			cc.push('<div class="panel-title layout-expand-title');
			cc.push(' layout-expand-title-'+opts.titleDirection);
			cc.push(opts.iconCls ? ' layout-expand-with-icon' : '');
			cc.push('">');
			cc.push(title);
			cc.push('</div>');
			return cc.join('');
		},
		minWidth:10,
		minHeight:10,
		maxWidth:10000,
		maxHeight:10000
	});
})(jQuery);
