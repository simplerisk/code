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
 * datagrid - EasyUI for jQuery
 * 
 * Dependencies:
 *  panel
 * 	resizable
 * 	linkbutton
 * 	pagination
 * 
 */
(function($){
	var DATAGRID_SERNO = 0;

	/**
	 * Get the index of array item, return -1 when the item is not found.
	 */
	function indexOfArray(a,o){
		return $.easyui.indexOfArray(a,o);
		// for(var i=0,len=a.length; i<len; i++){
		// 	if (a[i] == o) return i;
		// }
		// return -1;
	}
	/**
	 * Remove array item, 'o' parameter can be item object or id field name.
	 * When 'o' parameter is the id field name, the 'id' parameter is valid.
	 */
	function removeArrayItem(a,o,id){
		$.easyui.removeArrayItem(a,o,id);
		// if (typeof o == 'string'){
		// 	for(var i=0,len=a.length; i<len; i++){
		// 		if (a[i][o] == id){
		// 			a.splice(i, 1);
		// 			return;
		// 		}
		// 	}
		// } else {
		// 	var index = indexOfArray(a,o);
		// 	if (index != -1){
		// 		a.splice(index, 1);
		// 	}
		// }
	}
	/**
	 * Add un-duplicate array item, 'o' parameter is the id field name, if the 'r' object is exists, deny the action.
	 */
	function addArrayItem(a,o,r){
		$.easyui.addArrayItem(a,o,r);
		// for(var i=0,len=a.length; i<len; i++){
		// 	if (a[i][o] == r[o]){return;}
		// }
		// a.push(r);
	}
	
	function getArguments(target, aa){
		return $.data(target, 'treegrid') ? aa.slice(1) : aa;
	}
	
	function createStyleSheet(target){
		var dgState = $.data(target, 'datagrid');
		var opts = dgState.options;
		var panel = dgState.panel;
		var dc = dgState.dc;
		
		var ss = null;
		if (opts.sharedStyleSheet){
			ss = typeof opts.sharedStyleSheet == 'boolean' ? 'head' : opts.sharedStyleSheet;
		} else {
			ss = panel.closest('div.datagrid-view');
			if (!ss.length){ss = dc.view};
		}
		
		var cc = $(ss);
		var state = $.data(cc[0], 'ss');
		if (!state){
			state = $.data(cc[0], 'ss', {
				cache: {},
				dirty: []
			});
		}
		return {
			add: function(lines){
				var ss = ['<style type="text/css" easyui="true">'];
				for(var i=0; i<lines.length; i++){
					state.cache[lines[i][0]] = {width: lines[i][1]};
				}
				var index = 0;
				for(var s in state.cache){
					var item = state.cache[s];
					item.index = index++;
					ss.push(s + '{width:' + item.width + '}');
				}
				ss.push('</style>');
				$(ss.join('\n')).appendTo(cc);
				cc.children('style[easyui]:not(:last)').remove();
//				setTimeout(function(){
//					cc.children('style[easyui]:not(:last)').remove();
//				}, 0);
			},
			getRule: function(index){
				var style = cc.children('style[easyui]:last')[0];
				var styleSheet = style.styleSheet ? style.styleSheet : (style.sheet || document.styleSheets[document.styleSheets.length-1]);
				var rules = styleSheet.cssRules || styleSheet.rules;
				return rules[index];
			},
			set: function(selector, width){
				var item = state.cache[selector];
				if (item){
					item.width = width;
					var rule = this.getRule(item.index);
					if (rule){
						rule.style['width'] = width;
					}
				}
			},
			remove: function(selector){
				var tmp = [];
				for(var s in state.cache){
					if (s.indexOf(selector) == -1){
						tmp.push([s, state.cache[s].width]);
					}
				}
				state.cache = {};
				this.add(tmp);
			},
			dirty: function(selector){
				if (selector){
					state.dirty.push(selector);
				}
			},
			clean: function(){
				for(var i=0; i<state.dirty.length; i++){
					this.remove(state.dirty[i]);
				}
				state.dirty = [];
			}
		}
	}
	
	
	function setSize(target, param) {
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var panel = state.panel;
		
		if (param){
			$.extend(opts, param);
		}
		
		if (opts.fit == true){
			var p = panel.panel('panel').parent();
			opts.width = p.width();
			opts.height = p.height();
		}
		
		panel.panel('resize', opts);
	}
	
	function setBodySize(target){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var dc = state.dc;
		var wrap = state.panel;
		if (!wrap.is(':visible')){
			return;
		}
		var innerWidth = wrap.width();
		var innerHeight = wrap.height();

		var view = dc.view;
		var view1 = dc.view1;
		var view2 = dc.view2;
		var header1 = view1.children('div.datagrid-header');
		var header2 = view2.children('div.datagrid-header');
		var table1 = header1.find('table');
		var table2 = header2.find('table');
		
		// set view width
		view.width(innerWidth);
		var headerInner = header1.children('div.datagrid-header-inner').show();
		view1.width(headerInner.find('table').width());
		if (!opts.showHeader) headerInner.hide();
		view2.width(innerWidth - view1._outerWidth());
		view1.children()._outerWidth(view1.width());
		view2.children()._outerWidth(view2.width());
		
		// set header height
		var all = header1.add(header2).add(table1).add(table2);
		all.css('height', '');
		var hh = Math.max(table1.height(), table2.height());
		all._outerHeight(hh);

		// set the position of empty message
		view.children('.datagrid-empty').css('top', hh+'px');

		// set body height
		dc.body1.add(dc.body2).children('table.datagrid-btable-frozen').css({
			position: 'absolute',
			top: dc.header2._outerHeight()
		});
		var frozenHeight = dc.body2.children('table.datagrid-btable-frozen')._outerHeight();
		var fixedHeight = frozenHeight + header2._outerHeight() + view2.children('.datagrid-footer')._outerHeight();
		wrap.children(':not(.datagrid-view,.datagrid-mask,.datagrid-mask-msg)').each(function(){
			fixedHeight += $(this)._outerHeight();
		});
		
		var distance = wrap.outerHeight() - wrap.height();
		var minHeight = wrap._size('minHeight') || '';
		var maxHeight = wrap._size('maxHeight') || '';
		view1.add(view2).children('div.datagrid-body').css({
			marginTop: frozenHeight,
			height: (isNaN(parseInt(opts.height)) ? '' : (innerHeight-fixedHeight)),
			minHeight: (minHeight ? minHeight-distance-fixedHeight : ''),
			maxHeight: (maxHeight ? maxHeight-distance-fixedHeight : '')
		});
		
		view.height(view2.height());
	}
	
	function fixRowHeight(target, index, forceFix){
		var rows = $.data(target, 'datagrid').data.rows;
		var opts = $.data(target, 'datagrid').options;
		var dc = $.data(target, 'datagrid').dc;
		var tmp = $('<tr class="datagrid-row" style="position:absolute;left:-999999px"></tr>').appendTo('body');
		var rowHeight = tmp.outerHeight();
		tmp.remove();

		if (!dc.body1.is(':empty') && (!opts.nowrap || opts.autoRowHeight || forceFix)){
			if (index != undefined){
				var tr1 = opts.finder.getTr(target, index, 'body', 1);
				var tr2 = opts.finder.getTr(target, index, 'body', 2);
				setHeight(tr1, tr2);
			} else {
				var tr1 = opts.finder.getTr(target, 0, 'allbody', 1);
				var tr2 = opts.finder.getTr(target, 0, 'allbody', 2);
				setHeight(tr1, tr2);
				if (opts.showFooter){
					var tr1 = opts.finder.getTr(target, 0, 'allfooter', 1);
					var tr2 = opts.finder.getTr(target, 0, 'allfooter', 2);
					setHeight(tr1, tr2);
				}
			}
		}
		
		setBodySize(target);
		if (opts.height == 'auto'){
			var body1 = dc.body1.parent();
			var body2 = dc.body2;
			var csize = getContentSize(body2);
			var height = csize.height;
			if (csize.width > body2.width()){
				height += 18;
			}
			height -= parseInt(body2.css('marginTop')) || 0;
			body1.height(height);
			body2.height(height);
			dc.view.height(dc.view2.height());
		}
		dc.body2.triggerHandler('scroll');
		
		// set body row or footer row height
		function setHeight(trs1, trs2){
			for(var i=0; i<trs2.length; i++){
				var tr1 = $(trs1[i]);
				var tr2 = $(trs2[i]);
				// tr1.css('height', 'auto');
				// tr2.css('height', 'auto');
				tr1.css('height', '');
				tr2.css('height', '');
				var height = Math.max(tr1.outerHeight(), tr2.outerHeight());
				if (height != rowHeight){
					height = Math.max(height, rowHeight) + 1;
					tr1.css('height', height);
					tr2.css('height', height);
				}
			}
		}
		// get content size of a container(div)
		function getContentSize(cc){
			var width = 0;
			var height = 0;
			$(cc).children().each(function(){
				var c = $(this);
				if (c.is(':visible')){
					height += c._outerHeight();
					if (width < c._outerWidth()){
						width = c._outerWidth();
					}
				}
			});
			return {width:width,height:height};
		}
	}
	
	function freezeRow(target, index){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var dc = state.dc;
		if (!dc.body2.children('table.datagrid-btable-frozen').length){
			dc.body1.add(dc.body2).prepend('<table class="datagrid-btable datagrid-btable-frozen" cellspacing="0" cellpadding="0"></table>');
		}
		moveTr(true);
		moveTr(false);
		setBodySize(target);
		function moveTr(frozen){
			var serno = frozen ? 1 : 2;
			var tr = opts.finder.getTr(target, index, 'body', serno);
			(frozen ? dc.body1 : dc.body2).children('table.datagrid-btable-frozen').append(tr);
		}
	}
	
	/**
	 * wrap and return the grid object, fields and columns
	 */
	function wrapGrid(target, rownumbers) {
		function getColumns(){
			var frozenColumns = [];
			var columns = [];
			$(target).children('thead').each(function(){
				var opt = $.parser.parseOptions(this, [{frozen:'boolean'}]);
				$(this).find('tr').each(function(){
					var cols = [];
					$(this).find('th').each(function(){
						var th = $(this);
						var col = $.extend({}, $.parser.parseOptions(this, [
    						'id','field','align','halign','order','width',
    						{sortable:'boolean',checkbox:'boolean',resizable:'boolean',fixed:'boolean'},
    						{rowspan:'number',colspan:'number'}
    					]), {
    						title: (th.html() || undefined),
    						hidden: (th.attr('hidden') ? true : undefined),
    						hformatter: (th.attr('hformatter') ? eval(th.attr('hformatter')) : undefined),
    						hstyler: (th.attr('hstyler') ? eval(th.attr('hstyler')) : undefined),
    						formatter: (th.attr('formatter') ? eval(th.attr('formatter')) : undefined),
    						styler: (th.attr('styler') ? eval(th.attr('styler')) : undefined),
    						sorter: (th.attr('sorter') ? eval(th.attr('sorter')) : undefined)
    					});
						if (col.width && String(col.width).indexOf('%')==-1){
							col.width = parseInt(col.width);
						}
//    					if (!col.align) col.align = 'left';
    					if (th.attr('editor')){
    						var s = $.trim(th.attr('editor'));
    						if (s.substr(0,1) == '{'){
    							col.editor = eval('(' + s + ')');
    						} else {
    							col.editor = s;
    						}
    					}
    					
    					cols.push(col);
					});
					
					opt.frozen ? frozenColumns.push(cols) : columns.push(cols);
				});
			});
			return [frozenColumns, columns];
		}
		
		var panel = $(
				'<div class="datagrid-wrap">' +
					'<div class="datagrid-view">' +
						'<div class="datagrid-view1">' +
							'<div class="datagrid-header">' +
								'<div class="datagrid-header-inner"></div>' +
							'</div>' +
							'<div class="datagrid-body">' +
								'<div class="datagrid-body-inner"></div>' +
							'</div>' +
							'<div class="datagrid-footer">' +
								'<div class="datagrid-footer-inner"></div>' +
							'</div>' +
						'</div>' +
						'<div class="datagrid-view2">' +
							'<div class="datagrid-header">' +
								'<div class="datagrid-header-inner"></div>' +
							'</div>' +
							'<div class="datagrid-body"></div>' +
							'<div class="datagrid-footer">' +
								'<div class="datagrid-footer-inner"></div>' +
							'</div>' +
						'</div>' +
//						'<div class="datagrid-resize-proxy"></div>' +
					'</div>' +
				'</div>'
		).insertAfter(target);
		
		panel.panel({
			doSize:false,
			cls:'datagrid'
		});
		
		$(target).addClass('datagrid-f').hide().appendTo(panel.children('div.datagrid-view'));
		
		var cc = getColumns();
		var view = panel.children('div.datagrid-view');
		var view1 = view.children('div.datagrid-view1');
		var view2 = view.children('div.datagrid-view2');
		
		return {
			panel: panel,
			frozenColumns: cc[0],
			columns: cc[1],
			dc: {	// some data container
				view: view,
				view1: view1,
				view2: view2,
				header1: view1.children('div.datagrid-header').children('div.datagrid-header-inner'),
				header2: view2.children('div.datagrid-header').children('div.datagrid-header-inner'),
				body1: view1.children('div.datagrid-body').children('div.datagrid-body-inner'),
				body2: view2.children('div.datagrid-body'),
				footer1: view1.children('div.datagrid-footer').children('div.datagrid-footer-inner'),
				footer2: view2.children('div.datagrid-footer').children('div.datagrid-footer-inner')
			}
		};
	}
	
	function buildGrid(target){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var dc = state.dc;
		var panel = state.panel;
		
		state.ss = $(target).datagrid('createStyleSheet');
		
		panel.panel($.extend({}, opts, {
			id: null,
			doSize: false,
			onResize: function(width, height){
				if ($.data(target, 'datagrid')){
					setBodySize(target);
					$(target).datagrid('fitColumns');
					opts.onResize.call(panel, width, height);
				}
			},
			onExpand: function(){
				if ($.data(target, 'datagrid')){
					$(target).datagrid('fixRowHeight').datagrid('fitColumns');
					opts.onExpand.call(panel);
				}
			}
		}));
		
		var prefixId = $(target).attr('id')||'';
		if (prefixId){prefixId += '_'}
		state.rowIdPrefix = prefixId + 'datagrid-row-r' + (++DATAGRID_SERNO);
		state.cellClassPrefix = prefixId + 'datagrid-cell-c' + DATAGRID_SERNO;
		createColumnHeader(dc.header1, opts.frozenColumns, true);
		createColumnHeader(dc.header2, opts.columns, false);
		createColumnStyle();
		
		dc.header1.add(dc.header2).css('display', opts.showHeader ? 'block' : 'none');
		dc.footer1.add(dc.footer2).css('display', opts.showFooter ? 'block' : 'none');
		
		if (opts.toolbar) {
			if ($.isArray(opts.toolbar)){
				$('div.datagrid-toolbar', panel).remove();
				var tb = $('<div class="datagrid-toolbar"><table cellspacing="0" cellpadding="0"><tr></tr></table></div>').prependTo(panel);
				var tr = tb.find('tr');
				for(var i=0; i<opts.toolbar.length; i++) {
					var btn = opts.toolbar[i];
					if (btn == '-') {
						$('<td><div class="datagrid-btn-separator"></div></td>').appendTo(tr);
					} else {
						var td = $('<td></td>').appendTo(tr);
						btn.type = btn.type || 'linkbutton';
						btn.plain = btn.plain || true;
						var tool = $('<a href="javascript:;"></a>').appendTo(td);
						tool[0].onclick = eval(btn.handler || function(){});
						tool[btn.type](btn);
						if (btn.onInit){
							btn.onInit.call(tool[0]);
						}

						// var tool = $('<a href="javascript:;"></a>').appendTo(td);
						// tool[0].onclick = eval(btn.handler || function(){});
						// tool.linkbutton($.extend({}, btn, {
						// 	plain:true
						// }));
					}
				}
			} else {
				$(opts.toolbar).addClass('datagrid-toolbar').prependTo(panel);
				$(opts.toolbar).show();
			}
		} else {
			$('div.datagrid-toolbar', panel).remove();
		}
		
		$('div.datagrid-pager', panel).remove();
		if (opts.pagination) {
			var pager = $('<div class="datagrid-pager"></div>');
			if (opts.pagePosition == 'bottom'){
				pager.appendTo(panel);
			} else if (opts.pagePosition == 'top'){
				pager.addClass('datagrid-pager-top').prependTo(panel);
			} else {
				var ptop = $('<div class="datagrid-pager datagrid-pager-top"></div>').prependTo(panel);
				pager.appendTo(panel);
				pager = pager.add(ptop);
			}
			pager.pagination({
				total:0,
				// total:(opts.pageNumber*opts.pageSize),
				pageNumber:opts.pageNumber,
				pageSize:opts.pageSize,
				pageList:opts.pageList,
				onSelectPage: function(pageNum, pageSize){
					// save the page state
					opts.pageNumber = pageNum || 1;
					opts.pageSize = pageSize;
					pager.pagination('refresh',{
						pageNumber:pageNum,
						pageSize:pageSize
					});
					
					request(target);	// request new page data
				}
			});
			opts.pageSize = pager.pagination('options').pageSize;	// repare the pageSize value
		}
		
		function createColumnHeader(container, columns, frozen){
			if (!columns) return;
			$(container).show();
			$(container).empty();

			var tmp = $('<div class="datagrid-cell" style="position:absolute;left:-99999px"></div>').appendTo('body');
			tmp._outerWidth(99);
			var deltaWidth = 100 - parseInt(tmp[0].style.width);
			tmp.remove();

			var names = [];
			var orders = [];
			var hiddenFields = [];
			if (opts.sortName){
				names = opts.sortName.split(',');
				orders = opts.sortOrder.split(',');
			}
			var t = $('<table class="datagrid-htable" border="0" cellspacing="0" cellpadding="0"><tbody></tbody></table>').appendTo(container);
			for(var i=0; i<columns.length; i++) {
				var tr = $('<tr class="datagrid-header-row"></tr>').appendTo($('tbody', t));
				var cols = columns[i];
				for(var j=0; j<cols.length; j++){
					var col = cols[j];
					
					var attr = '';
					if (col.rowspan){
						attr += 'rowspan="' + col.rowspan + '" ';
					}
					if (col.colspan){
						attr += 'colspan="' + col.colspan + '" ';
						if (!col.id){
							col.id = ['datagrid-td-group' + DATAGRID_SERNO, i, j].join('-');
						}
					}
					if (col.id){
						attr += 'id="' + col.id + '"';
					}
					var css = col.hstyler ? col.hstyler(col.title, col) : '';
					if (typeof css == 'string'){
						var tdstyle = css;
						var tdclass = '';
					} else {
						css = css || {};
						var tdstyle = css['style'] || '';
						var tdclass = css['class'] || '';
					}
					var td = $('<td ' + attr + ' class="' + tdclass + '" style="' + tdstyle + '"' + '></td>').appendTo(tr);
					
					if (col.checkbox){
						td.attr('field', col.field);
						$('<div class="datagrid-header-check"></div>').html('<input type="checkbox">').appendTo(td);
					} else if (col.field){
						td.attr('field', col.field);
						td.append('<div class="datagrid-cell"><span></span><span class="datagrid-sort-icon"></span></div>');
						td.find('span:first').html(col.hformatter ? col.hformatter(col.title, col) : col.title);
						var cell = td.find('div.datagrid-cell');
						var pos = indexOfArray(names, col.field);
						if (pos >= 0){
							cell.addClass('datagrid-sort-' + orders[pos]);
						}
						if (col.sortable){
							cell.addClass('datagrid-sort');
						}
						if (col.resizable == false){
							cell.attr('resizable', 'false');
						}
						if (col.width){
							var value = $.parser.parseValue('width',col.width,dc.view,opts.scrollbarSize+(opts.rownumbers?opts.rownumberWidth:0));
							col.deltaWidth = deltaWidth;
							col.boxWidth = value - deltaWidth;
						} else {
							col.auto = true;
						}
						cell.css('text-align', (col.halign || col.align || ''));
						
						// define the cell class
						col.cellClass = state.cellClassPrefix + '-' + col.field.replace(/[\.|\s]/g,'-');
						cell.addClass(col.cellClass);
					} else {
						$('<div class="datagrid-cell-group"></div>').html(col.hformatter ? col.hformatter(col.title, col) : col.title).appendTo(td);
					}
					
					if (col.hidden){
						td.hide();
						hiddenFields.push(col.field);
					}
				}
				
			}
			if (frozen && opts.rownumbers){
				var td = $('<td rowspan="'+opts.frozenColumns.length+'"><div class="datagrid-header-rownumber"></div></td>');
				if ($('tr',t).length == 0){
					td.wrap('<tr class="datagrid-header-row"></tr>').parent().appendTo($('tbody',t));
				} else {
					td.prependTo($('tr:first', t));
				}
			}
			for(var i=0; i<hiddenFields.length; i++){
				fixColumnSpan(target, hiddenFields[i], -1);
			}
		}
		
		function createColumnStyle(){
			var lines = [['.datagrid-header-rownumber', (opts.rownumberWidth-1)+'px'], ['.datagrid-cell-rownumber', (opts.rownumberWidth-1)+'px']];
			var fields = getColumnFields(target,true).concat(getColumnFields(target));
			for(var i=0; i<fields.length; i++){
				var col = getColumnOption(target, fields[i]);
				if (col && !col.checkbox){
					lines.push(['.'+col.cellClass, col.boxWidth ? col.boxWidth + 'px' : 'auto']);
				}
			}
			state.ss.add(lines);
			state.ss.dirty(state.cellSelectorPrefix);	// mark the old selector as dirty that will be removed
			state.cellSelectorPrefix = '.' + state.cellClassPrefix;
		}
	}
	
	/**
	 * bind the datagrid events
	 */
	function bindEvents(target) {
		var state = $.data(target, 'datagrid');
		var panel = state.panel;
		var opts = state.options;
		var dc = state.dc;
		
		var header = dc.header1.add(dc.header2);
		header._unbind('.datagrid');
		for(var event in opts.headerEvents){
			header._bind(event+'.datagrid', opts.headerEvents[event]);
		}

		var cells = header.find('div.datagrid-cell');
		var resizeHandle = opts.resizeHandle == 'right' ? 'e' : (opts.resizeHandle == 'left' ? 'w' : 'e,w');
		cells.each(function(){
			$(this).resizable({
				handles:resizeHandle,
				edge:opts.resizeEdge,
				disabled:($(this).attr('resizable') ? $(this).attr('resizable')=='false' : false),
				minWidth:25,
				onStartResize: function(e){
					state.resizing = true;
					header.css('cursor', $('body').css('cursor'));
					if (!state.proxy){
						state.proxy = $('<div class="datagrid-resize-proxy"></div>').appendTo(dc.view);
					}
					if (e.data.dir == 'e'){
						e.data.deltaEdge = $(this)._outerWidth() - (e.pageX - $(this).offset().left);
					} else {
						e.data.deltaEdge = $(this).offset().left - e.pageX - 1;
					}
					state.proxy.css({
						left:e.pageX - $(panel).offset().left - 1 + e.data.deltaEdge,
						display:'none'
					});
					setTimeout(function(){
						if (state.proxy) state.proxy.show();
					}, 500);
				},
				onResize: function(e){
					state.proxy.css({
						left:e.pageX - $(panel).offset().left - 1 + e.data.deltaEdge,
						display:'block'
					});
					return false;
				},
				onStopResize: function(e){
					header.css('cursor', '');
					$(this).css('height','');
					var field = $(this).parent().attr('field');
					var col = getColumnOption(target, field);
					col.width = $(this)._outerWidth() + 1;
					col.boxWidth = col.width - col.deltaWidth;
					col.auto = undefined;
					$(this).css('width', '');
					$(target).datagrid('fixColumnSize', field);
					state.proxy.remove();
					state.proxy = null;
					if ($(this).parents('div:first.datagrid-header').parent().hasClass('datagrid-view1')){
						setBodySize(target);
					}
					$(target).datagrid('fitColumns');
					opts.onResizeColumn.call(target, field, col.width);
					setTimeout(function(){
						state.resizing = false;
					}, 0);
				}
			});
		});
		
		var bb = dc.body1.add(dc.body2);
		bb._unbind();
		for(var event in opts.rowEvents){
			bb._bind(event, opts.rowEvents[event]);
		}
		dc.body1._bind('mousewheel DOMMouseScroll MozMousePixelScroll', function(e){
			e.preventDefault();
			var e1 = e.originalEvent || window.event;
			var delta = e1.wheelDelta || e1.detail*(-1);
			if ('deltaY' in e1){
				delta = e1.deltaY * -1;
			}
			var dg = $(e.target).closest('div.datagrid-view').children('.datagrid-f');
			var dc = dg.data('datagrid').dc;
			dc.body2.scrollTop(dc.body2.scrollTop() - delta);
		});
		dc.body2._bind('scroll', function(){
			var b1 = dc.view1.children('div.datagrid-body');
			var stv = $(this).scrollTop();
			$(this).scrollTop(stv);
			b1.scrollTop(stv);
			// b1.scrollTop($(this).scrollTop());
			var c1 = dc.body1.children(':first');
			var c2 = dc.body2.children(':first');
			if (c1.length && c2.length){
				var top1 = c1.offset().top;
				var top2 = c2.offset().top;
				if (top1 != top2){
					b1.scrollTop(b1.scrollTop()+top1-top2);
				}
			}
			
			dc.view2.children('div.datagrid-header,div.datagrid-footer')._scrollLeft($(this)._scrollLeft());
			dc.body2.children('table.datagrid-btable-frozen').css('left', -$(this)._scrollLeft());
		});
	}

	function headerOverEventHandler(isOver){
		return function(e){
			var td = $(e.target).closest('td[field]');
			if (td.length){
				var target = getTableTarget(td);
				if (!$(target).data('datagrid').resizing && isOver){
					td.addClass('datagrid-header-over');
				} else {
					td.removeClass('datagrid-header-over');
				}
			}
		}
	}
	function headerClickEventHandler(e){
		var target = getTableTarget(e.target);
		var opts = $(target).datagrid('options');
		var ck = $(e.target).closest('input[type=checkbox]');
		if (ck.length){
			if (opts.singleSelect && opts.selectOnCheck){return false;}
			if (ck.is(':checked')){
				checkAll(target);
			} else {
				uncheckAll(target);
			}
			e.stopPropagation();
		} else {
			var cell = $(e.target).closest('.datagrid-cell');
			if (cell.length){
				var p1 = cell.offset().left + 5;
				var p2 = cell.offset().left + cell._outerWidth() - 5;
				if (e.pageX < p2 && e.pageX > p1){
					sortGrid(target, cell.parent().attr('field'));
				}
			}
		}
	}
	function headerDblclickEventHandler(e){
		var target = getTableTarget(e.target);
		var opts = $(target).datagrid('options');
		var cell = $(e.target).closest('.datagrid-cell');
		if (cell.length){
			var p1 = cell.offset().left + 5;
			var p2 = cell.offset().left + cell._outerWidth() - 5;
			var cond = opts.resizeHandle == 'right' ? (e.pageX > p2) : (opts.resizeHandle == 'left' ? (e.pageX < p1) : (e.pageX < p1 || e.pageX > p2));
			if (cond){
				var field = cell.parent().attr('field');
				var col = getColumnOption(target, field);
				if (col.resizable == false) return;
				$(target).datagrid('autoSizeColumn', field);
				col.auto = false;
			}
		}
	}
	function headerMenuEventHandler(e){
		var target = getTableTarget(e.target);
		var opts = $(target).datagrid('options');
		var td = $(e.target).closest('td[field]');
		opts.onHeaderContextMenu.call(target, e, td.attr('field'));
	}
	
	function hoverEventHandler(isOver){
		return function(e){
			var tr = getClosestTr(e.target);
			if (!tr){return}
			var target = getTableTarget(tr);
			if ($.data(target, 'datagrid').resizing){return}
			var index = getTrIndex(tr);
			if (isOver){
				highlightRow(target, index);
				// $(target).datagrid('highlightRow', index);
			} else {
				var opts = $.data(target, 'datagrid').options;
				opts.finder.getTr(target, index).removeClass('datagrid-row-over');
			}
		}
	}
	function clickEventHandler(e){
		var tr = getClosestTr(e.target);
		if (!tr){return}
		var target = getTableTarget(tr);
		var opts = $.data(target, 'datagrid').options;
		var index = getTrIndex(tr);
		var tt = $(e.target);
		if (tt.parent().hasClass('datagrid-cell-check')){	// click the checkbox
			if (opts.singleSelect && opts.selectOnCheck){
//				if (!opts.checkOnSelect) {
//					uncheckAll(target, true);
//				}
				tt._propAttr('checked', !tt.is(':checked'));
				checkRow(target, index);
			} else {
				if (tt.is(':checked')){
					tt._propAttr('checked', false);
					checkRow(target, index);
				} else {
					tt._propAttr('checked', true);
					uncheckRow(target, index);
				}
			}
		} else {
			var row = opts.finder.getRow(target, index);
			var td = tt.closest('td[field]',tr);
			if (td.length){
				var field = td.attr('field');
				opts.onClickCell.call(target, index, field, row[field]);
			}
			
			if (opts.singleSelect == true){
				selectRow(target, index);
			} else {
				if (opts.ctrlSelect){
					if (e.metaKey || e.ctrlKey){
						if (tr.hasClass('datagrid-row-selected')){
							unselectRow(target, index);
						} else {
							selectRow(target, index);
						}
					} else if (e.shiftKey){
						$(target).datagrid('clearSelections');
						var fromIndex = Math.min(opts.lastSelectedIndex||0, index);
						var toIndex = Math.max(opts.lastSelectedIndex||0, index);
						for(var i=fromIndex; i<=toIndex; i++){
							selectRow(target, i);
						}
					} else {
						$(target).datagrid('clearSelections');
						selectRow(target, index);
						opts.lastSelectedIndex = index;
					}
				} else {
					if (tr.hasClass('datagrid-row-selected')){
						unselectRow(target, index);
					} else {
						selectRow(target, index);
					}
				}
			}
			opts.onClickRow.apply(target, getArguments(target, [index, row]));
		}
	}
	function dblclickEventHandler(e){
		var tr = getClosestTr(e.target);
		if (!tr){return}
		var target = getTableTarget(tr);
		var opts = $.data(target, 'datagrid').options;
		var index = getTrIndex(tr);
		var row = opts.finder.getRow(target, index);
		var td = $(e.target).closest('td[field]',tr);
		if (td.length){
			var field = td.attr('field');
			opts.onDblClickCell.call(target, index, field, row[field]);
		}
		opts.onDblClickRow.apply(target, getArguments(target, [index, row]));
	}
	function contextmenuEventHandler(e){
		var tr = getClosestTr(e.target);
		if (tr){
			var target = getTableTarget(tr);
			var opts = $.data(target, 'datagrid').options;
			var index = getTrIndex(tr);
			var row = opts.finder.getRow(target, index);
			opts.onRowContextMenu.call(target, e, index, row);
		} else {
			var body = getClosestTr(e.target, '.datagrid-body');
			if (body){
				var target = getTableTarget(body);
				var opts = $.data(target, 'datagrid').options;
				opts.onRowContextMenu.call(target, e, -1, null);
			}
		}
	}
	function getTableTarget(t){
		return $(t).closest('div.datagrid-view').children('.datagrid-f')[0];
	}
	function getClosestTr(t, selector){
		var tr = $(t).closest(selector||'tr.datagrid-row');
		if (tr.length && tr.parent().length){
			return tr;
		} else {
			return undefined;
		}
	}
	function getTrIndex(tr){
		if (tr.attr('datagrid-row-index')){
			return parseInt(tr.attr('datagrid-row-index'));
		} else {
			return tr.attr('node-id');
		}
	}
	
	function sortGrid(target, param){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		param = param || {};
		var sparam = {sortName: opts.sortName, sortOrder:opts.sortOrder};
		if (typeof param == 'object'){
			$.extend(sparam, param);
		}
		var names = [];
		var orders = [];
		if (sparam.sortName){
			names = sparam.sortName.split(',');
			orders = sparam.sortOrder.split(',');
		}
		if (typeof param == 'string'){
			var field = param;
			var col = getColumnOption(target, field);
			if (!col.sortable || state.resizing){return}
			var originalOrder = col.order || 'asc';
			var pos = indexOfArray(names, field);
			if (pos >= 0){
				var nextOrder = orders[pos] == 'asc' ? 'desc' : 'asc';
				if (opts.multiSort && nextOrder == originalOrder){
					names.splice(pos,1);
					orders.splice(pos,1);
				} else {
					orders[pos] = nextOrder;
				}
			} else {
				if (opts.multiSort){
					names.push(field);
					orders.push(originalOrder);
				} else {
					names = [field];
					orders = [originalOrder];
				}
			}
			sparam.sortName = names.join(',');
			sparam.sortOrder = orders.join(',');
		}
		
		if (opts.onBeforeSortColumn.call(target, sparam.sortName, sparam.sortOrder) == false){return}
		$.extend(opts, sparam);

		var dc = state.dc;
		var header = dc.header1.add(dc.header2);
		header.find('div.datagrid-cell').removeClass('datagrid-sort-asc datagrid-sort-desc');
		for(var i=0; i<names.length; i++){
			var col = getColumnOption(target, names[i]);
			header.find('div.'+col.cellClass).addClass('datagrid-sort-'+orders[i]);
		}
		if (opts.remoteSort){
			request(target);
		} else {
			loadData(target, $(target).datagrid('getData'));
		}
		
		opts.onSortColumn.call(target, opts.sortName, opts.sortOrder);
	}

	/**
	 * fix the colspan value of header group cells
	 */
	function fixColumnSpan(target, field, delta){
		fixSpan(true);
		fixSpan(false);

		function fixSpan(frozen){
			var aa = getColumnLayout(target, frozen);
			if (aa.length){
				var fields = aa[aa.length-1];
				var colIndex = indexOfArray(fields, field);
				if (colIndex >= 0){
					for(var rowIndex=0; rowIndex<aa.length-1; rowIndex++){
						var td = $('#'+aa[rowIndex][colIndex]);
						var colspan = parseInt(td.attr('colspan') || 1) + (delta || 0);
						td.attr('colspan', colspan);
						if (colspan){
							td.show();
						} else {
							td.hide();
						}
					}
				}
			}
		}
	}
	
	/**
	 * expand the columns to fit the grid width
	 */
	function fitColumns(target){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var dc = state.dc;
		var header = dc.view2.children('div.datagrid-header');
		var headerInner = header.children('div.datagrid-header-inner');
		
		dc.body2.css('overflow-x', '');
		
		setGroupWidth();
		setPercentWidth();
		fillWidth();
		setGroupWidth(true);
		
		headerInner.show();
		if (header.width() >= header.find('table').width()){
			dc.body2.css('overflow-x', 'hidden');
		}
		if (!opts.showHeader){headerInner.hide();}
		
		function fillWidth(){
			if (!opts.fitColumns){return;}
			if (!state.leftWidth){state.leftWidth = 0;}
			
			var fieldWidths = 0;
			var cc = [];
			var fields = getColumnFields(target, false);
			for(var i=0; i<fields.length; i++){
				var col = getColumnOption(target, fields[i]);
				if (canResize(col)){
					fieldWidths += col.width;
					cc.push({field:col.field, col:col, addingWidth:0});
				}
			}
			if (!fieldWidths){return;}
			cc[cc.length-1].addingWidth -= state.leftWidth;
			
			headerInner.show();
			var leftWidth = header.width() - header.find('table').width() - opts.scrollbarSize + state.leftWidth;
			var rate = leftWidth / fieldWidths;
			if (!opts.showHeader){headerInner.hide();}
			for(var i=0; i<cc.length; i++){
				var c = cc[i];
				var width = parseInt(c.col.width * rate);
				c.addingWidth += width;
				leftWidth -= width;
			}
			cc[cc.length-1].addingWidth += leftWidth;
			for(var i=0; i<cc.length; i++){
				var c = cc[i];
				if (c.col.boxWidth + c.addingWidth > 0){
					c.col.boxWidth += c.addingWidth;
					c.col.width += c.addingWidth;
				}
			}
			state.leftWidth = leftWidth;
			$(target).datagrid('fixColumnSize');
		}
		function setPercentWidth(){
			var changed = false;
			var fields = getColumnFields(target, true).concat(getColumnFields(target, false));
			$.map(fields, function(field){
				var col = getColumnOption(target, field);
				if (String(col.width||'').indexOf('%') >= 0){
					var width = $.parser.parseValue('width',col.width,dc.view,opts.scrollbarSize+(opts.rownumbers?opts.rownumberWidth:0)) - col.deltaWidth;
					if (width > 0){
						col.boxWidth = width;
						changed = true;
					}
				}
			});
			if (changed){
				$(target).datagrid('fixColumnSize');
			}
		}
		function setGroupWidth(fit){
			var groups = dc.header1.add(dc.header2).find('.datagrid-cell-group');
			if (groups.length){
				groups.each(function(){
					$(this)._outerWidth(fit ? $(this).parent().width() : 10);
				});
				if (fit){
					setBodySize(target);
				}
			}
		}
		function canResize(col){
			if (String(col.width||'').indexOf('%') >= 0){return false;}
			if (!col.hidden && !col.checkbox && !col.auto && !col.fixed){return true;}
		}
	}
	
	/**
	 * adjusts the column width to fit the contents.
	 */
	function autoSizeColumn(target, field){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var dc = state.dc;
		var tmp = $('<div class="datagrid-cell" style="position:absolute;left:-9999px"></div>').appendTo('body');
		if (field){
			setSize(field);
			$(target).datagrid('fitColumns');
		} else {
			var canFitColumns = false;
			var fields = getColumnFields(target,true).concat(getColumnFields(target,false));
			for(var i=0; i<fields.length; i++){
				var field = fields[i];
				var col = getColumnOption(target, field);
				if (col.auto){
					setSize(field);
					canFitColumns = true;
				}
			}
			if (canFitColumns){
				$(target).datagrid('fitColumns');
			}
		}
		tmp.remove();
		
		function setSize(field){
			var headerCell = dc.view.find('div.datagrid-header td[field="' + field + '"] div.datagrid-cell');
			headerCell.css('width', '');
			var col = $(target).datagrid('getColumnOption', field);
			col.width = undefined;
			col.boxWidth = undefined;
			col.auto = true;
			$(target).datagrid('fixColumnSize', field);
			var width = Math.max(getWidth('header'), getWidth('allbody'), getWidth('allfooter'))+1;
			headerCell._outerWidth(width-1);
			col.width = width;
			col.boxWidth = parseInt(headerCell[0].style.width);
			col.deltaWidth = width-col.boxWidth;
			headerCell.css('width', '');
			$(target).datagrid('fixColumnSize', field);
			opts.onResizeColumn.call(target, field, col.width);
			
			// get cell width of specified type(body or footer)
			function getWidth(type){
				var width = 0;
				if (type == 'header'){
					width = getCellWidth(headerCell);
				} else {
					opts.finder.getTr(target,0,type).find('td[field="' + field + '"] div.datagrid-cell').each(function(){
						var w = getCellWidth($(this));
						if (width < w){
							width = w;
						}
					});
				}
				return width;
				
				function getCellWidth(cell){
					return cell.is(':visible') ? cell._outerWidth() : tmp.html(cell.html())._outerWidth();
				}
			}
		}
	}
	
	
	/**
	 * fix column size for the specified field
	 */
	function fixColumnSize(target, field){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var dc = state.dc;
		var table = dc.view.find('table.datagrid-btable,table.datagrid-ftable');
		table.css('table-layout','fixed');
		if (field) {
			fix(field);
		} else {
			var ff = getColumnFields(target, true).concat(getColumnFields(target, false));	// get all fields
			for(var i=0; i<ff.length; i++){
				fix(ff[i]);
			}
		}
		table.css('table-layout','');
		fixMergedSize(target);
		fixRowHeight(target);
		fixEditableSize(target);

		function fix(field){
			var col = getColumnOption(target, field);
			if (col.cellClass){
				state.ss.set('.'+col.cellClass, col.boxWidth ? col.boxWidth + 'px' : 'auto');
			}
		}
	}
	
	function fixMergedSize(target, tds){
		var dc = $.data(target, 'datagrid').dc;
		tds = tds || dc.view.find('td.datagrid-td-merged');
		tds.each(function(){
			var td = $(this);
			var colspan = td.attr('colspan') || 1;
			if (colspan > 1){
				var col = getColumnOption(target, td.attr('field'));
				var width = col.boxWidth + col.deltaWidth - 1;
				for(var i=1; i<colspan; i++){
					td = td.next();
					col = getColumnOption(target, td.attr('field'));
					width += col.boxWidth + col.deltaWidth;
				}
				$(this).children('div.datagrid-cell')._outerWidth(width);
			}
		});
	}
	
	function fixEditableSize(target){
		var dc = $.data(target, 'datagrid').dc;
		dc.view.find('div.datagrid-editable').each(function(){
			var cell = $(this);
			var field = cell.parent().attr('field');
			var col = $(target).datagrid('getColumnOption', field);
			cell._outerWidth(col.boxWidth+col.deltaWidth-1);
			var ed = $.data(this, 'datagrid.editor');
			if (ed.actions.resize) {
				ed.actions.resize(ed.target, cell.width());
			}
		});
	}
	
	function getColumnOption(target, field){
		function find(columns){
			if (columns) {
				for(var i=0; i<columns.length; i++){
					var cc = columns[i];
					for(var j=0; j<cc.length; j++){
						var c = cc[j];
						if (c.field == field){
							return c;
						}
					}
				}
			}
			return null;
		}
		
		var opts = $.data(target, 'datagrid').options;
		var col = find(opts.columns);
		if (!col){
			col = find(opts.frozenColumns);
		}
		return col;
	}
	
	function getColumnLayout(target, frozen){
		var opts = $.data(target, 'datagrid').options;
		var columns = frozen ? opts.frozenColumns : opts.columns;
		
		var aa = [];
		var count = getCount();	// the fields count
		for(var i=0; i<columns.length; i++){
			aa[i] = new Array(count);
		}
		for(var rowIndex=0; rowIndex<columns.length; rowIndex++){
			$.map(columns[rowIndex], function(col){
				var colIndex = getIndex(aa[rowIndex]);	// get the column index
				if (colIndex >= 0){
					var value = col.field || col.id || '';
					for(var c=0; c<(col.colspan||1); c++){
						for(var r=0; r<(col.rowspan||1); r++){
							aa[rowIndex + r][colIndex] = value;
						}
						colIndex++;
					}
				}
			});
		}
		return aa;
		
		function getCount(){
			var count = 0;
			$.map(columns[0]||[], function(col){
				count += col.colspan || 1;
			});
			return count;
		}
		function getIndex(a){
			for(var i=0; i<a.length; i++){
				if (a[i] == undefined){return i;}
			}
			return -1;
		}
	}

	/**
	 * get column fields which will be show in row
	 */
	function getColumnFields(target, frozen){
		var aa = getColumnLayout(target, frozen);
		return aa.length ? aa[aa.length-1] : aa;
	}
	
	/**
	 * load data to the grid
	 */
	function loadData(target, data){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var dc = state.dc;
		data = opts.loadFilter.call(target, data);
		if ($.isArray(data)){
			data = {
				total: data.length,
				rows: data
			};
		}
		data.total = parseInt(data.total);
		state.data = data;
		if (data.footer){
			state.footer = data.footer;
		}
		
		if (!opts.remoteSort && opts.sortName){
			var names = opts.sortName.split(',');
			var orders = opts.sortOrder.split(',');
			data.rows.sort(function(r1,r2){
				var r = 0;
				for(var i=0; i<names.length; i++){
					var sn = names[i];
					var so = orders[i];
					var col = getColumnOption(target, sn);
					var sortFunc = col.sorter || function(a,b){
						return a==b ? 0 : (a>b?1:-1);
					};
					r = sortFunc(r1[sn], r2[sn], r1, r2) * (so=='asc'?1:-1);
					if (r != 0){
						return r;
					}
				}
				return r;
			});
		}
		
		// render datagrid view
		if (opts.view.onBeforeRender){
			opts.view.onBeforeRender.call(opts.view, target, data.rows);
		}
		opts.view.render.call(opts.view, target, dc.body2, false);
		opts.view.render.call(opts.view, target, dc.body1, true);
		if (opts.showFooter){
			opts.view.renderFooter.call(opts.view, target, dc.footer2, false);
			opts.view.renderFooter.call(opts.view, target, dc.footer1, true);
		}
		if (opts.view.onAfterRender){
			opts.view.onAfterRender.call(opts.view, target);
		}
		
		state.ss.clean();
		
//		opts.onLoadSuccess.call(target, data);
		
		var pager = $(target).datagrid('getPager');
		if (pager.length){
			var popts = pager.pagination('options');
			if (popts.total != data.total){
				pager.pagination('refresh',{pageNumber:opts.pageNumber,total:data.total});
				if (opts.pageNumber != popts.pageNumber && popts.pageNumber > 0){
					opts.pageNumber = popts.pageNumber;
					request(target);
				}
			}
		}
		
		fixRowHeight(target);
		dc.body2.triggerHandler('scroll');
		
		$(target).datagrid('setSelectionState');
		$(target).datagrid('autoSizeColumn');
		
		opts.onLoadSuccess.call(target, data);
	}
	
	/**
	 * set row selection that previously selected
	 */
	function setSelectionState(target){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var dc = state.dc;
		dc.header1.add(dc.header2).find('input[type=checkbox]')._propAttr('checked', false);
		if (opts.idField){
			var isTreeGrid = $.data(target, 'treegrid') ? true : false;
			var onSelect = opts.onSelect;
			var onCheck = opts.onCheck;
			opts.onSelect = opts.onCheck = function(){};
			var rows = opts.finder.getRows(target);
			for(var i=0; i<rows.length; i++){
				var row = rows[i];
				// var index = isTreeGrid ? row[opts.idField] : i;
				var index = isTreeGrid ? row[opts.idField] : $(target).datagrid('getRowIndex', row[opts.idField]);
				if (contains(state.selectedRows, row)){
					selectRow(target, index, true, true);
				}
				if (contains(state.checkedRows, row)){
					checkRow(target, index, true);
				}
			}
			opts.onSelect = onSelect;
			opts.onCheck = onCheck;
		}
		function contains(a,r){
			for(var i=0; i<a.length; i++){
				if (a[i][opts.idField] == r[opts.idField]){
					a[i] = r;
					return true;
				}
			}
			return false;
		}
	}
	
	/**
	 * Return the index of specified row or -1 if not found.
	 * row: id value or row record
	 */
	function getRowIndex(target, row){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var rows = state.data.rows;
		if (typeof row == 'object'){
			return indexOfArray(rows, row);
		} else {
			for(var i=0; i<rows.length; i++){
				if (rows[i][opts.idField] == row){
					return i;
				}
			}
			return -1;
		}
	}
	
	function getSelectedRows(target){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var data = state.data;
		
		if (opts.idField){
			return state.selectedRows;
		} else {
			var rows = [];
			opts.finder.getTr(target, '', 'selected', 2).each(function(){
				rows.push(opts.finder.getRow(target, $(this)));
			});
			return rows;
		}
	}
	
	function getCheckedRows(target){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		if (opts.idField){
			return state.checkedRows;
		} else {
			var rows = [];
			opts.finder.getTr(target, '', 'checked', 2).each(function(){
				rows.push(opts.finder.getRow(target, $(this)));
			});
			return rows;
		}
	}
	
	function scrollTo(target, index){
		var state = $.data(target, 'datagrid');
		var dc = state.dc;
		var opts = state.options;
		var tr = opts.finder.getTr(target, index);
		if (tr.length){
			if (tr.closest('table').hasClass('datagrid-btable-frozen')){return;}
			var headerHeight = dc.view2.children('div.datagrid-header')._outerHeight();
			var body2 = dc.body2;
			var scrollbarSize = opts.scrollbarSize;
			if (body2[0].offsetHeight && body2[0].clientHeight && body2[0].offsetHeight<=body2[0].clientHeight){
				scrollbarSize = 0;
			}
			var frozenHeight = body2.outerHeight(true) - body2.outerHeight();
			// var top = tr.position().top - headerHeight - frozenHeight;
			var top = tr.offset().top - dc.view2.offset().top - headerHeight - frozenHeight;
			if (top < 0){
				body2.scrollTop(body2.scrollTop() + top);
			} else if (top + tr._outerHeight() > body2.height() - scrollbarSize){
				body2.scrollTop(body2.scrollTop() + top + tr._outerHeight() - body2.height() + scrollbarSize);
			}
		}
	}
	
	function highlightRow(target, index){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		opts.finder.getTr(target, state.highlightIndex).removeClass('datagrid-row-over');
		opts.finder.getTr(target, index).addClass('datagrid-row-over');
		state.highlightIndex = index;
//		scrollTo(target, index);
	}
	
	/**
	 * select a row, the row index start with 0
	 */
	function selectRow(target, index, notCheck, notScroll){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var row = opts.finder.getRow(target, index);
		if (!row){return;}
		
		var tr = opts.finder.getTr(target, index);
		if (tr.hasClass('datagrid-row-selected')){return}
		if (opts.onBeforeSelect.apply(target, getArguments(target, [index, row])) == false){return}
		if (opts.singleSelect){
			unselectAll(target, true);
			state.selectedRows = [];
		}
		if (!notCheck && opts.checkOnSelect){
			checkRow(target, index, true);	// don't select the row again
		}
		
		if (opts.idField){
			addArrayItem(state.selectedRows, opts.idField, row);
		}
		tr.addClass('datagrid-row-selected');
		if (state.selectingData){
			state.selectingData.push(row);
		}
		opts.onSelect.apply(target, getArguments(target, [index, row]));
		if (!notScroll && opts.scrollOnSelect){
			scrollTo(target, index);
		}
	}
	/**
	 * unselect a row
	 */
	function unselectRow(target, index, notCheck){
		var state = $.data(target, 'datagrid');
		var dc = state.dc;
		var opts = state.options;
		var row = opts.finder.getRow(target, index);
		if (!row){return;}
		
		var tr = opts.finder.getTr(target, index);
		if (!tr.hasClass('datagrid-row-selected')){return}
		if (opts.onBeforeUnselect.apply(target, getArguments(target, [index, row])) == false){return}
		if (!notCheck && opts.checkOnSelect){
			uncheckRow(target, index, true);	// don't unselect the row again
		}
		tr.removeClass('datagrid-row-selected');
		if (opts.idField){
			removeArrayItem(state.selectedRows, opts.idField, row[opts.idField]);
		}
		if (state.selectingData){
			state.selectingData.push(row);
		}
		opts.onUnselect.apply(target, getArguments(target, [index, row]));
	}
	/**
	 * select all rows on current page
	 */
	function selectAll(target, notCheck){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var isTreeGrid = $.data(target, 'treegrid') ? true : false;
		var scrollOnSelect = opts.scrollOnSelect;
		opts.scrollOnSelect = false;
		state.selectingData = [];
		if (!notCheck && opts.checkOnSelect){
			checkAll(target, true);	// don't select rows again
		}
		var rows = opts.finder.getRows(target);
		for(var i=0; i<rows.length; i++){
			var index = isTreeGrid ? rows[i][opts.idField] : $(target).datagrid('getRowIndex', rows[i]);
			selectRow(target,index);
		}
		var srows = state.selectingData;
		state.selectingData = null;
		opts.scrollOnSelect = scrollOnSelect;
		opts.onSelectAll.call(target, srows);
	}
	// function selectAll(target, notCheck){
	// 	var state = $.data(target, 'datagrid');
	// 	var opts = state.options;
	// 	var rows = opts.finder.getRows(target);
	// 	var selectedRows = $.data(target, 'datagrid').selectedRows;
		
	// 	if (!notCheck && opts.checkOnSelect){
	// 		checkAll(target, true);	// don't select rows again
	// 	}
	// 	opts.finder.getTr(target, '', 'allbody').addClass('datagrid-row-selected');
	// 	if (opts.idField){
	// 		for(var index=0; index<rows.length; index++){
	// 			addArrayItem(selectedRows, opts.idField, rows[index]);
	// 		}
	// 	}
	// 	opts.onSelectAll.call(target, rows);
	// }

	/**
	 * unselect all rows on current page
	 */
	function unselectAll(target, notCheck){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var isTreeGrid = $.data(target, 'treegrid') ? true : false;
		state.selectingData = [];
		if (!notCheck && opts.checkOnSelect){
			uncheckAll(target, true);	// don't unselect rows again
		}
		var rows = opts.finder.getRows(target);
		for(var i=0; i<rows.length; i++){
			var index = isTreeGrid ? rows[i][opts.idField] : $(target).datagrid('getRowIndex', rows[i]);
			unselectRow(target,index);
		}
		var srows = state.selectingData;
		state.selectingData = null;
		opts.onUnselectAll.call(target, srows);
	}
	// function unselectAll(target, notCheck){
	// 	var state = $.data(target, 'datagrid');
	// 	var opts = state.options;
	// 	var rows = opts.finder.getRows(target);
	// 	var selectedRows = $.data(target, 'datagrid').selectedRows;
		
	// 	if (!notCheck && opts.checkOnSelect){
	// 		uncheckAll(target, true);	// don't unselect rows again
	// 	}
	// 	opts.finder.getTr(target, '', 'selected').removeClass('datagrid-row-selected');
	// 	if (opts.idField){
	// 		for(var index=0; index<rows.length; index++){
	// 			removeArrayItem(selectedRows, opts.idField, rows[index][opts.idField]);
	// 		}
	// 	}
	// 	opts.onUnselectAll.call(target, rows);
	// }

	function setHeaderCheckStatus(target){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var crows = [];
		var rows = opts.finder.getRows(target);
		for(var i=0; i<rows.length; i++){
			var index = getRowIndex(target, rows[i]);
			if (opts.onBeforeCheck.apply(target, getArguments(target, [index, rows[i]])) != false){
				crows.push(rows[i]);
			}
		}
		var trs = opts.finder.getTr(target, '', 'checked', 2);
		var checked = trs.length == crows.length;
		var dc = state.dc;
		dc.header1.add(dc.header2).find('input[type=checkbox]')._propAttr('checked', checked);
	}
	
	/**
	 * check a row, the row index start with 0
	 */
	function checkRow(target, index, notSelect){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var row = opts.finder.getRow(target, index);
		if (!row){return;}

		var tr = opts.finder.getTr(target, index);
		var ck = tr.find('.datagrid-cell-check input[type=checkbox]');
		if (ck.is(':checked')){return}
		if (opts.onBeforeCheck.apply(target, getArguments(target, [index, row])) == false){return}
		if (opts.singleSelect && opts.selectOnCheck){
			uncheckAll(target, true);
			state.checkedRows = [];
		}
		if (!notSelect && opts.selectOnCheck){
			selectRow(target, index, true);	// don't check the row again
		}
		tr.addClass('datagrid-row-checked');
		ck._propAttr('checked', true);
		if (!opts.notSetHeaderCheck){
			setHeaderCheckStatus(target);
		}
		if (opts.idField){
			addArrayItem(state.checkedRows, opts.idField, row);
		}
		if (state.checkingData){
			state.checkingData.push(row);
		}
		opts.onCheck.apply(target, getArguments(target, [index, row]));
	}
	/**
	 * uncheck a row
	 */
	function uncheckRow(target, index, notSelect){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var row = opts.finder.getRow(target, index);
		if (!row){return;}

		var tr = opts.finder.getTr(target, index);
		var ck = tr.find('div.datagrid-cell-check input[type=checkbox]');
		if (!ck.is(':checked')){return}
		if (opts.onBeforeUncheck.apply(target, getArguments(target, [index, row])) == false){return}
		if (!notSelect && opts.selectOnCheck){
			unselectRow(target, index, true);	// don't uncheck the row again
		}
		tr.removeClass('datagrid-row-checked');
		ck._propAttr('checked', false);
		var dc = state.dc;
		var header = dc.header1.add(dc.header2);
		header.find('input[type=checkbox]')._propAttr('checked', false);
		if (opts.idField){
			removeArrayItem(state.checkedRows, opts.idField, row[opts.idField]);
		}
		if (state.checkingData){
			state.checkingData.push(row);
		}
		opts.onUncheck.apply(target, getArguments(target, [index, row]));
	}
	/**
	 * check all checkbox on current page
	 */
	function checkAll(target, notSelect){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var isTreeGrid = $.data(target, 'treegrid') ? true : false;
		var scrollOnSelect = opts.scrollOnSelect;
		opts.scrollOnSelect = false;
		opts.notSetHeaderCheck = true;
		state.checkingData = [];
		if (!notSelect && opts.selectOnCheck){
			selectAll(target, true);	// don't check rows again
		}
		var rows = opts.finder.getRows(target);
		for(var i=0; i<rows.length; i++){
			var index = isTreeGrid ? rows[i][opts.idField] : $(target).datagrid('getRowIndex', rows[i]);
			checkRow(target, index);
		}
		setHeaderCheckStatus(target);
		var crows = state.checkingData;
		state.checkingData = null;
		opts.scrollOnSelect = scrollOnSelect;
		opts.notSetHeaderCheck = false;
		opts.onCheckAll.call(target, crows);
	}
	// function checkAll(target, notSelect){
	// 	var state = $.data(target, 'datagrid');
	// 	var opts = state.options;
	// 	var rows = opts.finder.getRows(target);
	// 	if (!notSelect && opts.selectOnCheck){
	// 		selectAll(target, true);	// don't check rows again
	// 	}
	// 	var dc = state.dc;
	// 	var hck = dc.header1.add(dc.header2).find('input[type=checkbox]');
	// 	var bck = opts.finder.getTr(target, '', 'allbody').addClass('datagrid-row-checked').find('div.datagrid-cell-check input[type=checkbox]');
	// 	hck.add(bck)._propAttr('checked', true);
	// 	if (opts.idField){
	// 		for(var i=0; i<rows.length; i++){
	// 			addArrayItem(state.checkedRows, opts.idField, rows[i]);
	// 		}
	// 	}
	// 	opts.onCheckAll.call(target, rows);
	// }

	/**
	 * uncheck all checkbox on current page
	 */
	function uncheckAll(target, notSelect){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var isTreeGrid = $.data(target, 'treegrid') ? true : false;
		state.checkingData = [];
		if (!notSelect && opts.selectOnCheck){
			unselectAll(target, true);	// don't uncheck rows again
		}
		var rows = opts.finder.getRows(target);
		for(var i=0; i<rows.length; i++){
			var index = isTreeGrid ? rows[i][opts.idField] : $(target).datagrid('getRowIndex', rows[i]);
			uncheckRow(target, index);
		}
		var crows = state.checkingData;
		state.checkingData = null;
		opts.onUncheckAll.call(target, crows);
	}
	// function uncheckAll(target, notSelect){
	// 	var state = $.data(target, 'datagrid');
	// 	var opts = state.options;
	// 	var rows = opts.finder.getRows(target);
	// 	if (!notSelect && opts.selectOnCheck){
	// 		unselectAll(target, true);	// don't uncheck rows again
	// 	}
	// 	var dc = state.dc;
	// 	var hck = dc.header1.add(dc.header2).find('input[type=checkbox]');
	// 	var bck = opts.finder.getTr(target, '', 'checked').removeClass('datagrid-row-checked').find('div.datagrid-cell-check input[type=checkbox]');
	// 	hck.add(bck)._propAttr('checked', false);
	// 	if (opts.idField){
	// 		for(var i=0; i<rows.length; i++){
	// 			removeArrayItem(state.checkedRows, opts.idField, rows[i][opts.idField]);
	// 		}
	// 	}
	// 	opts.onUncheckAll.call(target, rows);
	// }
	
	
	/**
	 * Begin edit a row
	 */
	function beginEdit(target, index){
		var opts = $.data(target, 'datagrid').options;
		var tr = opts.finder.getTr(target, index);
		var row = opts.finder.getRow(target, index);
		if (tr.hasClass('datagrid-row-editing')) return;
		if (opts.onBeforeEdit.apply(target, getArguments(target, [index, row])) == false){return}
		
		tr.addClass('datagrid-row-editing');
		createEditor(target, index);
		fixEditableSize(target);
		
		tr.find('div.datagrid-editable').each(function(){
			var field = $(this).parent().attr('field');
			var ed = $.data(this, 'datagrid.editor');
			ed.actions.setValue(ed.target, row[field]);
		});
		validateRow(target, index);	// validate the row data
		opts.onBeginEdit.apply(target, getArguments(target, [index, row]));
	}
	
	/**
	 * Stop edit a row.
	 * index: the row index.
	 * cancel: if true, restore the row data.
	 */
	function endEdit(target, index, cancel){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var updatedRows = state.updatedRows;
		var insertedRows = state.insertedRows;
		
		var tr = opts.finder.getTr(target, index);
		var row = opts.finder.getRow(target, index);
		if (!tr.hasClass('datagrid-row-editing')) {
			return;
		}
		
		if (!cancel){
			if (!validateRow(target, index)) return;	// invalid row data
			
			var changed = false;
			var changes = {};
			tr.find('div.datagrid-editable').each(function(){
				var field = $(this).parent().attr('field');
				var ed = $.data(this, 'datagrid.editor');
				var t = $(ed.target);
				var input = t.data('textbox') ? t.textbox('textbox') : t;
				if (input.is(':focus')){
					input.triggerHandler('blur');					
				}
				var value = ed.actions.getValue(ed.target);
				if (row[field] !== value){
					row[field] = value;
					changed = true;
					changes[field] = value;
				}
			});
			if (changed){
				if (indexOfArray(insertedRows, row) == -1){
					if (indexOfArray(updatedRows, row) == -1){
						updatedRows.push(row);
					}
				}
			}
			opts.onEndEdit.apply(target, getArguments(target, [index, row, changes]));
		}
		
		tr.removeClass('datagrid-row-editing');
		
		destroyEditor(target, index);
		$(target).datagrid('refreshRow', index);
		
		if (!cancel){
			opts.onAfterEdit.apply(target, getArguments(target, [index, row, changes]));
		} else {
			opts.onCancelEdit.apply(target, getArguments(target, [index, row]));
		}
	}
	
	/**
	 * get the specified row editors
	 */
	function getEditors(target, index){
		var opts = $.data(target, 'datagrid').options;
		var tr = opts.finder.getTr(target, index);
		var editors = [];
		tr.children('td').each(function(){
			var cell = $(this).find('div.datagrid-editable');
			if (cell.length){
				var ed = $.data(cell[0], 'datagrid.editor');
				editors.push(ed);
			}
		});
		return editors;
	}
	
	/**
	 * get the cell editor
	 * param contains two parameters: index and field
	 */
	function getEditor(target, param){
		var editors = getEditors(target, param.index!=undefined ? param.index : param.id);
		for(var i=0; i<editors.length; i++){
			if (editors[i].field == param.field){
				return editors[i];
			}
		}
		return null;
	}
	
	/**
	 * create the row editor and adjust the row height.
	 */
	function createEditor(target, index){
		var opts = $.data(target, 'datagrid').options;
		var tr = opts.finder.getTr(target, index);
		tr.children('td').each(function(){
			var cell = $(this).find('div.datagrid-cell');
			var field = $(this).attr('field');
			
			var col = getColumnOption(target, field);
			if (col && col.editor){
				// get edit type and options
				var edittype,editoptions;
				if (typeof col.editor == 'string'){
					edittype = col.editor;
				} else {
					edittype = col.editor.type;
					editoptions = col.editor.options;
				}
				
				// get the specified editor
				var editor = opts.editors[edittype];
				if (editor){
					var oldHtml = cell.html();
					var width = cell._outerWidth();
					cell.addClass('datagrid-editable');
					cell._outerWidth(width);
					cell.html('<table border="0" cellspacing="0" cellpadding="1"><tr><td></td></tr></table>');
					cell.children('table')._bind('click dblclick contextmenu',function(e){
						e.stopPropagation();
					});
					$.data(cell[0], 'datagrid.editor', {
						actions: editor,
						//target: editor.init(cell.find('td'), editoptions),
						target: editor.init(cell.find('td'), $.extend({height:opts.editorHeight},editoptions)),
						field: field,
						type: edittype,
						oldHtml: oldHtml
					});
				}
			}
		});
		fixRowHeight(target, index, true);
	}
	
	/**
	 * destroy the row editor and restore the row height.
	 */
	function destroyEditor(target, index){
		var opts = $.data(target, 'datagrid').options;
		var tr = opts.finder.getTr(target, index);
		tr.children('td').each(function(){
			var cell = $(this).find('div.datagrid-editable');
			if (cell.length){
				var ed = $.data(cell[0], 'datagrid.editor');
				if (ed.actions.destroy) {
					ed.actions.destroy(ed.target);
				}
				cell.html(ed.oldHtml);
				$.removeData(cell[0], 'datagrid.editor');
				
				cell.removeClass('datagrid-editable');
				cell.css('width','');
			}
		});
	}
	
	/**
	 * Validate while editing, if valid return true.
	 */
	function validateRow(target, index){
		var tr = $.data(target, 'datagrid').options.finder.getTr(target, index);
		if (!tr.hasClass('datagrid-row-editing')){
			return true;
		}
		
		var vbox = tr.find('.validatebox-text');
		vbox.validatebox('validate');
		vbox.trigger('mouseleave');
		var invalidbox = tr.find('.validatebox-invalid');
		return invalidbox.length == 0;
	}
	
	/**
	 * Get changed rows, if state parameter is not assigned, return all changed.
	 * state: inserted,deleted,updated
	 */
	function getChanges(target, state){
		var insertedRows = $.data(target, 'datagrid').insertedRows;
		var deletedRows = $.data(target, 'datagrid').deletedRows;
		var updatedRows = $.data(target, 'datagrid').updatedRows;
		
		if (!state){
			var rows = [];
			rows = rows.concat(insertedRows);
			rows = rows.concat(deletedRows);
			rows = rows.concat(updatedRows);
			return rows;
		} else if (state == 'inserted'){
			return insertedRows;
		} else if (state == 'deleted'){
			return deletedRows;
		} else if (state == 'updated'){
			return updatedRows;
		}
		
		return [];
	}
	
	function deleteRow(target, index){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var data = state.data;
		var insertedRows = state.insertedRows;
		var deletedRows = state.deletedRows;
		
		$(target).datagrid('cancelEdit', index);
		
		var row = opts.finder.getRow(target, index);
		if (indexOfArray(insertedRows, row) >= 0){
			removeArrayItem(insertedRows, row);
		} else {
			deletedRows.push(row);
		}
		removeArrayItem(state.selectedRows, opts.idField, row[opts.idField]);
		removeArrayItem(state.checkedRows, opts.idField, row[opts.idField]);
		
		opts.view.deleteRow.call(opts.view, target, index);
		if (opts.height == 'auto'){
			fixRowHeight(target);	// adjust the row height
		}
		$(target).datagrid('getPager').pagination('refresh', {total:data.total});
	}
	
	function insertRow(target, param){
		var data = $.data(target, 'datagrid').data;
		var view = $.data(target, 'datagrid').options.view;
		var insertedRows = $.data(target, 'datagrid').insertedRows;
		view.insertRow.call(view, target, param.index, param.row);
		insertedRows.push(param.row);
		$(target).datagrid('getPager').pagination('refresh', {total:data.total});
	}
	
	function appendRow(target, row){
		var data = $.data(target, 'datagrid').data;
		var view = $.data(target, 'datagrid').options.view;
		var insertedRows = $.data(target, 'datagrid').insertedRows;
		view.insertRow.call(view, target, null, row);
		insertedRows.push(row);
		$(target).datagrid('getPager').pagination('refresh', {total:data.total});
	}

	function updateRow(target, param){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var row = opts.finder.getRow(target, param.index);
		var updated = false;
		param.row = param.row || {};
		for(var field in param.row){
			if (row[field] !== param.row[field]){
				updated = true;
				break;
			}
		}
		if (updated){
			if (indexOfArray(state.insertedRows, row) == -1){
				if (indexOfArray(state.updatedRows, row) == -1){
					state.updatedRows.push(row);
				}
			}
			opts.view.updateRow.call(opts.view, target, param.index, param.row);
		}
	}
	
	function initChanges(target){
		var state = $.data(target, 'datagrid');
		var data = state.data;
		var rows = data.rows;
		var originalRows = [];
		for(var i=0; i<rows.length; i++){
			originalRows.push($.extend({}, rows[i]));
		}
		state.originalRows = originalRows;
		state.updatedRows = [];
		state.insertedRows = [];
		state.deletedRows = [];
	}
	
	function acceptChanges(target){
		var data = $.data(target, 'datagrid').data;
		var ok = true;
		for(var i=0,len=data.rows.length; i<len; i++){
			if (validateRow(target, i)){
//				endEdit(target, i, false);
				$(target).datagrid('endEdit', i);
			} else {
				ok = false;
			}
		}
		if (ok){
			initChanges(target);
		}
	}
	
	function rejectChanges(target){
		var state = $.data(target, 'datagrid');
		var opts = state.options;
		var originalRows = state.originalRows;
		var insertedRows = state.insertedRows;
		var deletedRows = state.deletedRows;
		var selectedRows = state.selectedRows;
		var checkedRows = state.checkedRows;
		var data = state.data;
		
		function getIds(a){
			var ids = [];
			for(var i=0; i<a.length; i++){
				ids.push(a[i][opts.idField]);
			}
			return ids;
		}
		function doSelect(ids, action){
			for(var i=0; i<ids.length; i++){
				var index = getRowIndex(target, ids[i]);
				if (index >= 0){
					(action=='s'?selectRow:checkRow)(target, index, true);
				}
			}
		}
		
		for(var i=0; i<data.rows.length; i++){
//			endEdit(target, i, true);
			$(target).datagrid('cancelEdit', i);
		}
		
		var selectedIds = getIds(selectedRows);
		var checkedIds = getIds(checkedRows);
		selectedRows.splice(0, selectedRows.length);
		checkedRows.splice(0, checkedRows.length);
		
		data.total += deletedRows.length - insertedRows.length;
		data.rows = originalRows;
		loadData(target, data);
		
		doSelect(selectedIds, 's');
		doSelect(checkedIds, 'c');
		
		initChanges(target);
	}
	
	/**
	 * request remote data
	 */
	function request(target, params, cb){
		var opts = $.data(target, 'datagrid').options;
		
		if (params) opts.queryParams = params;
		
		var param = $.extend({}, opts.queryParams);
		if (opts.pagination){
			$.extend(param, {
				page: opts.pageNumber||1,
				rows: opts.pageSize
			});
		}
		if (opts.sortName && opts.remoteSort){
			$.extend(param, {
				sort: opts.sortName,
				order: opts.sortOrder
			});
		}
		
		if (opts.onBeforeLoad.call(target, param) == false){
			opts.view.setEmptyMsg(target);
			return;
		}
		
		$(target).datagrid('loading');
		var result = opts.loader.call(target, param, function(data){
			$(target).datagrid('loaded');
			$(target).datagrid('loadData', data);
			if (cb){
				cb();
			}
		}, function(){
			$(target).datagrid('loaded');
			opts.onLoadError.apply(target, arguments);
		});
		if (result == false){
			$(target).datagrid('loaded');
			opts.view.setEmptyMsg(target);
		}
	}
	
	function mergeCells(target, param){
		var opts = $.data(target, 'datagrid').options;
		
		param.type = param.type || 'body';
		param.rowspan = param.rowspan || 1;
		param.colspan = param.colspan || 1;
		
		if (param.rowspan == 1 && param.colspan == 1){return;}
		
		var tr = opts.finder.getTr(target, (param.index!=undefined ? param.index : param.id), param.type);
		if (!tr.length){return;}
		var td = tr.find('td[field="'+param.field+'"]');
		td.attr('rowspan', param.rowspan).attr('colspan', param.colspan);
		td.addClass('datagrid-td-merged');
		_hidecell(td.next(), param.colspan-1);
		for(var i=1; i<param.rowspan; i++){
			tr = tr.next();
			if (!tr.length){break;}
			_hidecell(tr.find('td[field="'+param.field+'"]'), param.colspan);
		}
		
		fixMergedSize(target, td);
		
		function _hidecell(td, count){
			for(var i=0; i<count; i++){
				td.hide();
				td = td.next();
			}
		}
	}
	
	$.fn.datagrid = function(options, param){
		if (typeof options == 'string'){
			return $.fn.datagrid.methods[options](this, param);
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'datagrid');
			var opts;
			if (state) {
				opts = $.extend(state.options, options);
				state.options = opts;
			} else {
				opts = $.extend({}, $.extend({},$.fn.datagrid.defaults,{queryParams:{}}), $.fn.datagrid.parseOptions(this), options);
				$(this).css('width', '').css('height', '');
				
				var wrapResult = wrapGrid(this, opts.rownumbers);
				if (!opts.columns) opts.columns = wrapResult.columns;
				if (!opts.frozenColumns) opts.frozenColumns = wrapResult.frozenColumns;
				opts.columns = $.extend(true, [], opts.columns);
				opts.frozenColumns = $.extend(true, [], opts.frozenColumns);
				opts.view = $.extend({}, opts.view);
				$.data(this, 'datagrid', {
					options: opts,
					panel: wrapResult.panel,
					dc: wrapResult.dc,
					ss: null,
					selectedRows: [],
					checkedRows: [],
					data: {total:0,rows:[]},
					originalRows: [],
					updatedRows: [],
					insertedRows: [],
					deletedRows: []
				});
			}
			
			buildGrid(this);
			bindEvents(this);
			setSize(this);
			
			if (opts.data){
				$(this).datagrid('loadData', opts.data);
			} else {
				var data = $.fn.datagrid.parseData(this);
				if (data.total > 0){
					$(this).datagrid('loadData', data);
				} else {
					// opts.view.setEmptyMsg(this);
					$(this).datagrid('autoSizeColumn');
				}
			}
			
			request(this);
		});
	};
	
	function getDefaultEditors(names){
		var editors = {};
		$.map(names, function(name){
			editors[name] = getEditorConf(name);
		});
		return editors;
		
		function getEditorConf(name){
			function isA(target){
				return $.data($(target)[0], name) != undefined;
			}
			return {
				init: function(container, options){
					var input = $('<input type="text" class="datagrid-editable-input">').appendTo(container);
					if (input[name] && name != 'text'){
						return input[name](options);
					} else {
						return input;
					}
				},
				destroy: function(target){
					if (isA(target, name)){
						$(target)[name]('destroy');
					}
				},
				getValue: function(target){
					if (isA(target, name)){
						var opts = $(target)[name]('options');
						if (opts.multiple){
							return $(target)[name]('getValues').join(opts.separator);
						} else {
							return $(target)[name]('getValue');
						}
					} else {
						return $(target).val();
					}
				},
				setValue: function(target, value){
					if (isA(target, name)){
						var opts = $(target)[name]('options');
						if (opts.multiple){
							if (value){
								$(target)[name]('setValues', value.split(opts.separator));
							} else {
								$(target)[name]('clear');
							}
						} else {
							$(target)[name]('setValue', value);
						}
					} else {
						$(target).val(value);
					}
				},
				resize: function(target, width){
					if (isA(target, name)){
						$(target)[name]('resize', width);
					} else {
						$(target)._size({
							width: width,
							height: $.fn.datagrid.defaults.editorHeight
						});
					}
				}
			}
		}
	}
	
	var editors = $.extend({}, 
			getDefaultEditors([
				'text','textbox','passwordbox','filebox','numberbox','numberspinner',
				'combobox','combotree','combogrid','combotreegrid','datebox','datetimebox',
				'timespinner','datetimespinner'
			]), {
		textarea: {
			init: function(container, options){
				var input = $('<textarea class="datagrid-editable-input"></textarea>').appendTo(container);
				input.css('vertical-align','middle')._outerHeight(options.height);
				return input;
			},
			getValue: function(target){
				return $(target).val();
			},
			setValue: function(target, value){
				$(target).val(value);
			},
			resize: function(target, width){
				$(target)._outerWidth(width);
			}
		},
		checkbox: {
			init: function(container, options){
				var input = $('<input type="checkbox">').appendTo(container);
				input.val(options.on);
				input.attr('offval', options.off);
				return input;
			},
			getValue: function(target){
				if ($(target).is(':checked')){
					return $(target).val();
				} else {
					return $(target).attr('offval');
				}
			},
			setValue: function(target, value){
				var checked = false;
				if ($(target).val() == value){
					checked = true;
				}
				$(target)._propAttr('checked', checked);
			}
		},
		validatebox: {
			init: function(container, options){
				var input = $('<input type="text" class="datagrid-editable-input">').appendTo(container);
				input.validatebox(options);
				return input;
			},
			destroy: function(target){
				$(target).validatebox('destroy');
			},
			getValue: function(target){
				return $(target).val();
			},
			setValue: function(target, value){
				$(target).val(value);
			},
			resize: function(target, width){
				$(target)._outerWidth(width)._outerHeight($.fn.datagrid.defaults.editorHeight);
			}
		}
	});
	
	
	$.fn.datagrid.methods = {
		options: function(jq){
			var gopts = $.data(jq[0], 'datagrid').options;
			var popts = $.data(jq[0], 'datagrid').panel.panel('options');
			var opts = $.extend(gopts, {
				width: popts.width,
				height: popts.height,
				closed: popts.closed,
				collapsed: popts.collapsed,
				minimized: popts.minimized,
				maximized: popts.maximized
			});
//			var pager = jq.datagrid('getPager');
//			if (pager.length){
//				var pagerOpts = pager.pagination('options');
//				$.extend(opts, {
//					pageNumber: pagerOpts.pageNumber,
//					pageSize: pagerOpts.pageSize
//				});
//			}
			return opts;
		},
		setSelectionState: function(jq){
			return jq.each(function(){
				setSelectionState(this);
			});
		},
		createStyleSheet: function(jq){
			return createStyleSheet(jq[0]);
		},
		getPanel: function(jq){
			return $.data(jq[0], 'datagrid').panel;
		},
		getPager: function(jq){
			return $.data(jq[0], 'datagrid').panel.children('div.datagrid-pager');
		},
		getColumnFields: function(jq, frozen){
			return getColumnFields(jq[0], frozen);
		},
		getColumnOption: function(jq, field){
			return getColumnOption(jq[0], field);
		},
		resize: function(jq, param){
			return jq.each(function(){
				setSize(this, param);
			});
		},
		load: function(jq, params){
			return jq.each(function(){
				var opts = $(this).datagrid('options');
				if (typeof params == 'string'){
					opts.url = params;
					params = null;
				}
				opts.pageNumber = 1;
				var pager = $(this).datagrid('getPager');
//				pager.pagination({pageNumber:1});
				pager.pagination('refresh', {pageNumber:1});
				request(this, params);
			});
		},
		reload: function(jq, params){
			return jq.each(function(){
				var opts = $(this).datagrid('options');
				if (typeof params == 'string'){
					opts.url = params;
					params = null;
				}
				request(this, params);
			});
		},
		reloadFooter: function(jq, footer){
			return jq.each(function(){
				var opts = $.data(this, 'datagrid').options;
				var dc = $.data(this, 'datagrid').dc;
				if (footer){
					$.data(this, 'datagrid').footer = footer;
				}
				if (opts.showFooter){
					opts.view.renderFooter.call(opts.view, this, dc.footer2, false);
					opts.view.renderFooter.call(opts.view, this, dc.footer1, true);
					if (opts.view.onAfterRender){
						opts.view.onAfterRender.call(opts.view, this);
					}
					$(this).datagrid('fixRowHeight');
				}
			});
		},
		loading: function(jq){
			return jq.each(function(){
				var opts = $.data(this, 'datagrid').options;
				$(this).datagrid('getPager').pagination('loading');
				if (opts.loadMsg){
					var panel = $(this).datagrid('getPanel');
					if (!panel.children('div.datagrid-mask').length){
						$('<div class="datagrid-mask" style="display:block"></div>').appendTo(panel);
						var msg = $('<div class="datagrid-mask-msg" style="display:block;left:50%"></div>').html(opts.loadMsg).appendTo(panel);
//						msg.css('marginLeft', -msg.outerWidth()/2);
						msg._outerHeight(40);
						msg.css({
							marginLeft: (-msg.outerWidth()/2),
							lineHeight: (msg.height()+'px')
						});
					}
				}
			});
		},
		loaded: function(jq){
			return jq.each(function(){
				$(this).datagrid('getPager').pagination('loaded');
				var panel = $(this).datagrid('getPanel');
				panel.children('div.datagrid-mask-msg').remove();
				panel.children('div.datagrid-mask').remove();
			});
		},
		fitColumns: function(jq){
			return jq.each(function(){
				fitColumns(this);
			});
		},
		fixColumnSize: function(jq, field){
			return jq.each(function(){
				fixColumnSize(this, field);
			});
		},
		fixRowHeight: function(jq, index){
			return jq.each(function(){
				fixRowHeight(this, index);
			});
		},
		freezeRow: function(jq, index){
			return jq.each(function(){
				freezeRow(this, index);
			});
		},
		autoSizeColumn: function(jq, field){	// adjusts the column width to fit the contents.
			return jq.each(function(){
				autoSizeColumn(this, field);
			});
		},
		loadData: function(jq, data){
			return jq.each(function(){
				loadData(this, data);
				initChanges(this);
			});
		},
		getData: function(jq){
			return $.data(jq[0], 'datagrid').data;
		},
		getRows: function(jq){
			return $.data(jq[0], 'datagrid').data.rows;
		},
		getFooterRows: function(jq){
			return $.data(jq[0], 'datagrid').footer;
		},
		getRowIndex: function(jq, id){	// id or row record
			return getRowIndex(jq[0], id);
		},
		getChecked: function(jq){
			return getCheckedRows(jq[0]);
		},
		getSelected: function(jq){
			var rows = getSelectedRows(jq[0]);
			return rows.length>0 ? rows[0] : null;
		},
		getSelections: function(jq){
			return getSelectedRows(jq[0]);
		},
		clearSelections: function(jq){
			return jq.each(function(){
				var state = $.data(this, 'datagrid');
				var selectedRows = state.selectedRows;
				var checkedRows = state.checkedRows;
				selectedRows.splice(0, selectedRows.length);
				unselectAll(this);
				if (state.options.checkOnSelect){
					checkedRows.splice(0, checkedRows.length);
				}
			});
		},
		clearChecked: function(jq){
			return jq.each(function(){
				var state = $.data(this, 'datagrid');
				var selectedRows = state.selectedRows;
				var checkedRows = state.checkedRows;
				checkedRows.splice(0, checkedRows.length);
				uncheckAll(this);
				if (state.options.selectOnCheck){
					selectedRows.splice(0, selectedRows.length);
				}
			});
		},
		scrollTo: function(jq, index){
			return jq.each(function(){
				scrollTo(this, index);
			});
		},
		highlightRow: function(jq, index){
			return jq.each(function(){
				highlightRow(this, index);
				scrollTo(this, index);
			});
		},
		selectAll: function(jq){
			return jq.each(function(){
				selectAll(this);
			});
		},
		unselectAll: function(jq){
			return jq.each(function(){
				unselectAll(this);
			});
		},
		selectRow: function(jq, index){
			return jq.each(function(){
				selectRow(this, index);
			});
		},
		selectRecord: function(jq, id){
			return jq.each(function(){
				var opts = $.data(this, 'datagrid').options;
				if (opts.idField){
					var index = getRowIndex(this, id);
					if (index >= 0){
						$(this).datagrid('selectRow', index);
					}
				}
			});
		},
		unselectRow: function(jq, index){
			return jq.each(function(){
				unselectRow(this, index);
			});
		},
		checkRow: function(jq, index){
			return jq.each(function(){
				checkRow(this, index);
			});
		},
		uncheckRow: function(jq, index){
			return jq.each(function(){
				uncheckRow(this, index);
			});
		},
		checkAll: function(jq){
			return jq.each(function(){
				checkAll(this);
			});
		},
		uncheckAll: function(jq){
			return jq.each(function(){
				uncheckAll(this);
			});
		},
		beginEdit: function(jq, index){
			return jq.each(function(){
				beginEdit(this, index);
			});
		},
		endEdit: function(jq, index){
			return jq.each(function(){
				endEdit(this, index, false);
			});
		},
		cancelEdit: function(jq, index){
			return jq.each(function(){
				endEdit(this, index, true);
			});
		},
		getEditors: function(jq, index){
			return getEditors(jq[0], index);
		},
		getEditor: function(jq, param){	// param: {index:0, field:'name'}
			return getEditor(jq[0], param);
		},
		refreshRow: function(jq, index){
			return jq.each(function(){
				var opts = $.data(this, 'datagrid').options;
				opts.view.refreshRow.call(opts.view, this, index);
			});
		},
		validateRow: function(jq, index){
			return validateRow(jq[0], index);
		},
		updateRow: function(jq, param){	// param: {index:1,row:{code:'code1',name:'name1'}}
			return jq.each(function(){
				updateRow(this, param);
				// var opts = $.data(this, 'datagrid').options;
				// opts.view.updateRow.call(opts.view, this, param.index, param.row);
			});
		},
		appendRow: function(jq, row){
			return jq.each(function(){
				appendRow(this, row);
			});
		},
		insertRow: function(jq, param){
			return jq.each(function(){
				insertRow(this, param);
			});
		},
		deleteRow: function(jq, index){
			return jq.each(function(){
				deleteRow(this, index);
			});
		},
		getChanges: function(jq, state){
			return getChanges(jq[0], state);	// state: inserted,deleted,updated
		},
		acceptChanges: function(jq){
			return jq.each(function(){
				acceptChanges(this);
			});
		},
		rejectChanges: function(jq){
			return jq.each(function(){
				rejectChanges(this);
			});
		},
		mergeCells: function(jq, param){
			return jq.each(function(){
				mergeCells(this, param);
			});
		},
		showColumn: function(jq, field){
			return jq.each(function(){
				var col = $(this).datagrid('getColumnOption', field);
				if (col.hidden){
					col.hidden = false;
					$(this).datagrid('getPanel').find('td[field="' + field + '"]').show();
					fixColumnSpan(this, field, 1);
					$(this).datagrid('fitColumns');
				}
			});
		},
		hideColumn: function(jq, field){
			return jq.each(function(){
				var col = $(this).datagrid('getColumnOption', field);
				if (!col.hidden){
					col.hidden = true;
					$(this).datagrid('getPanel').find('td[field="' + field + '"]').hide();
					fixColumnSpan(this, field, -1);
					$(this).datagrid('fitColumns');
				}
			});
		},
		sort: function(jq, param){
			return jq.each(function(){
				sortGrid(this, param);
			});
		},
		gotoPage: function(jq, param){
			return jq.each(function(){
				var target = this;
				var page, cb;
				if (typeof param == 'object'){
					page = param.page;
					cb = param.callback;
				} else {
					page = param;
				}
				$(target).datagrid('options').pageNumber = page;
				$(target).datagrid('getPager').pagination('refresh', {
					pageNumber: page
				});
				request(target, null, function(){
					if (cb){
						cb.call(target, page);
					}
				});
			});
		}
	};
	
	$.fn.datagrid.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.fn.panel.parseOptions(target), $.parser.parseOptions(target, [
			'url','toolbar','idField','sortName','sortOrder','pagePosition','resizeHandle',
			{sharedStyleSheet:'boolean',fitColumns:'boolean',autoRowHeight:'boolean',striped:'boolean',nowrap:'boolean'},
			{rownumbers:'boolean',singleSelect:'boolean',ctrlSelect:'boolean',checkOnSelect:'boolean',selectOnCheck:'boolean'},
			{pagination:'boolean',pageSize:'number',pageNumber:'number'},
			{multiSort:'boolean',remoteSort:'boolean',showHeader:'boolean',showFooter:'boolean'},
			{scrollbarSize:'number',scrollOnSelect:'boolean'}
		]), {
			pageList: (t.attr('pageList') ? eval(t.attr('pageList')) : undefined),
			loadMsg: (t.attr('loadMsg')!=undefined ? t.attr('loadMsg') : undefined),
			rowStyler: (t.attr('rowStyler') ? eval(t.attr('rowStyler')) : undefined)
		});
	};
	
	$.fn.datagrid.parseData = function(target){
		var t = $(target);
		var data = {
			total:0,
			rows:[]
		};
		var fields = t.datagrid('getColumnFields',true).concat(t.datagrid('getColumnFields',false));
		t.find('tbody tr').each(function(){
			data.total++;
			var row = {};
			$.extend(row, $.parser.parseOptions(this,['iconCls','state']));
			for(var i=0; i<fields.length; i++){
				row[fields[i]] = $(this).find('td:eq('+i+')').html();
			}
			data.rows.push(row);
		});
		return data;
	};
	
	var defaultView = {
		render: function(target, container, frozen){
			var rows = $(target).datagrid('getRows');
			$(container).empty().html(this.renderTable(target, 0, rows, frozen));
		},
		
		renderFooter: function(target, container, frozen){
			var opts = $.data(target, 'datagrid').options;
			var rows = $.data(target, 'datagrid').footer || [];
			var fields = $(target).datagrid('getColumnFields', frozen);
			var table = ['<table class="datagrid-ftable" cellspacing="0" cellpadding="0" border="0"><tbody>'];
			
			for(var i=0; i<rows.length; i++){
				table.push('<tr class="datagrid-row" datagrid-row-index="' + i + '">');
				table.push(this.renderRow.call(this, target, fields, frozen, i, rows[i]));
				table.push('</tr>');
			}
			
			table.push('</tbody></table>');
			$(container).html(table.join(''));
		},

		renderTable: function(target, index, rows, frozen){
			var state = $.data(target, 'datagrid');
			var opts = state.options;

			if (frozen){
				if (!(opts.rownumbers || (opts.frozenColumns && opts.frozenColumns.length))){
					return '';
				}
			}
			
			var fields = $(target).datagrid('getColumnFields', frozen);
			var table = ['<table class="datagrid-btable" cellspacing="0" cellpadding="0" border="0"><tbody>'];
			for(var i=0; i<rows.length; i++){
				var row = rows[i];
				// get the class and style attributes for this row
				var css = opts.rowStyler ? opts.rowStyler.call(target, index, row) : '';
				var cs = this.getStyleValue(css);				
				var cls = 'class="datagrid-row ' + (index % 2 && opts.striped ? 'datagrid-row-alt ' : ' ') + cs.c + '"';
				var style = cs.s ? 'style="' + cs.s + '"' : '';
				var rowId = state.rowIdPrefix + '-' + (frozen?1:2) + '-' + index;
				table.push('<tr id="' + rowId + '" datagrid-row-index="' + index + '" ' + cls + ' ' + style + '>');
				table.push(this.renderRow.call(this, target, fields, frozen, index, row));
				table.push('</tr>');

				index++;
			}
			table.push('</tbody></table>');
			return table.join('');
		},
		
		renderRow: function(target, fields, frozen, rowIndex, rowData){
			var opts = $.data(target, 'datagrid').options;
			
			var cc = [];
			if (frozen && opts.rownumbers){
				var rownumber = rowIndex + 1;
				if (opts.pagination){
					rownumber += (opts.pageNumber-1)*opts.pageSize;
				}
				cc.push('<td class="datagrid-td-rownumber"><div class="datagrid-cell-rownumber">'+rownumber+'</div></td>');
			}
			for(var i=0; i<fields.length; i++){
				var field = fields[i];
				var col = $(target).datagrid('getColumnOption', field);
				if (col){
					var value = rowData[field];	// the field value
					var css = col.styler ? (col.styler.call(target, value, rowData, rowIndex)||'') : '';
					var cs = this.getStyleValue(css);
					var cls = cs.c ? 'class="' + cs.c + '"' : '';
					var style = col.hidden ? 'style="display:none;' + cs.s + '"' : (cs.s ? 'style="' + cs.s + '"' : '');
					
					cc.push('<td field="' + field + '" ' + cls + ' ' + style + '>');
					
					var style = '';
					if (!col.checkbox){
						if (col.align){style += 'text-align:' + col.align + ';'}
						if (!opts.nowrap){
							style += 'white-space:normal;height:auto;';
						} else if (opts.autoRowHeight){
							style += 'height:auto;';
						}
					}
					
					cc.push('<div style="' + style + '" ');
					cc.push(col.checkbox ? 'class="datagrid-cell-check"' : 'class="datagrid-cell ' + col.cellClass + '"');
					cc.push('>');
					
					if (col.checkbox){
						cc.push('<input type="checkbox" ' + (rowData.checked ? 'checked="checked"' : ''));
						cc.push(' name="' + field + '" value="' + (value!=undefined ? value : '') + '">');
					} else if (col.formatter){
						cc.push(col.formatter(value, rowData, rowIndex));
					} else {
						cc.push(value);
					}
					
					cc.push('</div>');
					cc.push('</td>');
				}
			}
			return cc.join('');
		},

		getStyleValue: function(css){
			var classValue = '';
			var styleValue = '';
			if (typeof css == 'string'){
				styleValue = css;
			} else if (css){
				classValue = css['class'] || '';
				styleValue = css['style'] || '';
			}
			return {c:classValue, s:styleValue};
		},
		
		refreshRow: function(target, rowIndex){
			this.updateRow.call(this, target, rowIndex, {});
		},
		
		updateRow: function(target, rowIndex, row){
			var opts = $.data(target, 'datagrid').options;
			var rowData = opts.finder.getRow(target, rowIndex);

			$.extend(rowData, row);
			var cs = _getRowStyle.call(this, rowIndex);
			var style = cs.s;
			var cls = 'datagrid-row ' + (rowIndex % 2 && opts.striped ? 'datagrid-row-alt ' : ' ') + cs.c;
			
			function _getRowStyle(rowIndex){
				var css = opts.rowStyler ? opts.rowStyler.call(target, rowIndex, rowData) : '';
				return this.getStyleValue(css);
			}
			function _update(frozen){
				var tr = opts.finder.getTr(target, rowIndex, 'body', (frozen?1:2));
				if (!tr.length){return;}
				var fields = $(target).datagrid('getColumnFields', frozen);
				var checked = tr.find('div.datagrid-cell-check input[type=checkbox]').is(':checked');
				tr.html(this.renderRow.call(this, target, fields, frozen, rowIndex, rowData));
				var cls12 = (tr.hasClass('datagrid-row-checked') ? ' datagrid-row-checked' : '') +
							(tr.hasClass('datagrid-row-selected') ? ' datagrid-row-selected' : '');
				tr.attr('style', style).attr('class', cls + cls12);
				if (checked){
					tr.find('div.datagrid-cell-check input[type=checkbox]')._propAttr('checked', true);
				}
			}
			
			_update.call(this, true);
			_update.call(this, false);
			$(target).datagrid('fixRowHeight', rowIndex);
		},
		
		insertRow: function(target, index, row){
			var state = $.data(target, 'datagrid');
			var opts = state.options;
			var dc = state.dc;
			var data = state.data;
			
			if (index == undefined || index == null) index = data.rows.length;
			if (index > data.rows.length) index = data.rows.length;
			
			function _incIndex(frozen){
				var serno = frozen?1:2;
				for(var i=data.rows.length-1; i>=index; i--){
					var tr = opts.finder.getTr(target, i, 'body', serno);
					tr.attr('datagrid-row-index', i+1);
					tr.attr('id', state.rowIdPrefix + '-' + serno + '-' + (i+1));
					if (frozen && opts.rownumbers){
						var rownumber = i+2;
						if (opts.pagination){
							rownumber += (opts.pageNumber-1)*opts.pageSize;
						}
						tr.find('div.datagrid-cell-rownumber').html(rownumber);
					}
					if (opts.striped){
						tr.removeClass('datagrid-row-alt').addClass((i+1)%2 ? 'datagrid-row-alt' : '');
					}
				}
			}
			
			function _insert(frozen){
				var serno = frozen?1:2;
				var fields = $(target).datagrid('getColumnFields', frozen);
				var rowId = state.rowIdPrefix + '-' + serno + '-' + index;
				var tr = '<tr id="' + rowId + '" class="datagrid-row" datagrid-row-index="' + index + '"></tr>';
//				var tr = '<tr id="' + rowId + '" class="datagrid-row" datagrid-row-index="' + index + '">' + this.renderRow.call(this, target, fields, frozen, index, row) + '</tr>';
				if (index >= data.rows.length){	// append new row
					if (data.rows.length){	// not empty
						opts.finder.getTr(target, '', 'last', serno).after(tr);
					} else {
						var cc = frozen ? dc.body1 : dc.body2;
						cc.html('<table class="datagrid-btable" cellspacing="0" cellpadding="0" border="0"><tbody>' + tr + '</tbody></table>');
					}
				} else {	// insert new row
					opts.finder.getTr(target, index+1, 'body', serno).before(tr);
				}
			}
			
			_incIndex.call(this, true);
			_incIndex.call(this, false);
			_insert.call(this, true);
			_insert.call(this, false);
			
			data.total += 1;
			data.rows.splice(index, 0, row);
			
			this.setEmptyMsg(target);
			this.refreshRow.call(this, target, index);
		},
		
		deleteRow: function(target, index){
			var state = $.data(target, 'datagrid');
			var opts = state.options;
			var data = state.data;
			
			function _decIndex(frozen){
				var serno = frozen?1:2;
				for(var i=index+1; i<data.rows.length; i++){
					var tr = opts.finder.getTr(target, i, 'body', serno);
					tr.attr('datagrid-row-index', i-1);
					tr.attr('id', state.rowIdPrefix + '-' + serno + '-' + (i-1));
					if (frozen && opts.rownumbers){
						var rownumber = i;
						if (opts.pagination){
							rownumber += (opts.pageNumber-1)*opts.pageSize;
						}
						tr.find('div.datagrid-cell-rownumber').html(rownumber);
					}
					if (opts.striped){
						tr.removeClass('datagrid-row-alt').addClass((i-1)%2 ? 'datagrid-row-alt' : '');
					}
				}
			}
			
			opts.finder.getTr(target, index).remove();
			_decIndex.call(this, true);
			_decIndex.call(this, false);
			
			data.total -= 1;
			data.rows.splice(index,1);

			this.setEmptyMsg(target);
		},
		
		onBeforeRender: function(target, rows){},
		onAfterRender: function(target){
			var state = $.data(target, 'datagrid');
			var opts = state.options;
			if (opts.showFooter){
				var footer = $(target).datagrid('getPanel').find('div.datagrid-footer');
				footer.find('div.datagrid-cell-rownumber,div.datagrid-cell-check').css('visibility', 'hidden');
			}
			this.setEmptyMsg(target);
		},
		setEmptyMsg: function(target){
			var state = $.data(target, 'datagrid');
			var opts = state.options;
			var isEmpty = opts.finder.getRows(target).length == 0;
			if (isEmpty){
				this.renderEmptyRow(target);
			}
			if (opts.emptyMsg){
				state.dc.view.children('.datagrid-empty').remove();
				if (isEmpty){
					var h = state.dc.header2.parent().outerHeight();
					var d = $('<div class="datagrid-empty"></div>').appendTo(state.dc.view);
					d.html(opts.emptyMsg).css('top', h+'px');
				}
			}
		},
		renderEmptyRow: function(target){
			var opts = $(target).datagrid('options');
			var cols = $.map($(target).datagrid('getColumnFields'), function(field){
				return $(target).datagrid('getColumnOption', field);
			});
			$.map(cols, function(col){
				col.formatter1 = col.formatter;
				col.styler1 = col.styler;
				col.formatter = col.styler = undefined;
			});
			var rowStyler = opts.rowStyler;
			opts.rowStyler = function(){};

			var body2 = $.data(target, 'datagrid').dc.body2;
			body2.html(this.renderTable(target, 0, [{}], false));
			body2.find('tbody *').css({
				height: 1,
				borderColor: 'transparent',
				background: 'transparent'
			});
			var tr = body2.find('.datagrid-row');
			tr.removeClass('datagrid-row').removeAttr('datagrid-row-index');
			tr.find('.datagrid-cell,.datagrid-cell-check').empty();
        	
			$.map(cols, function(col){
				col.formatter = col.formatter1;
				col.styler = col.styler1;
				col.formatter1 = col.styler1 = undefined;
			});
			opts.rowStyler = rowStyler;
		}
	};
	
	$.fn.datagrid.defaults = $.extend({}, $.fn.panel.defaults, {
		sharedStyleSheet: false,
		frozenColumns: undefined,
		columns: undefined,
		fitColumns: false,
		resizeHandle: 'right',	// left,right,both
		resizeEdge: 5,
		autoRowHeight: true,
		toolbar: null,
		striped: false,
		method: 'post',
		nowrap: true,
		idField: null,
		url: null,
		data: null,
		loadMsg: 'Processing, please wait ...',
		emptyMsg: '',
		rownumbers: false,
		singleSelect: false,
		ctrlSelect: false,	// only allows multi-selection when ctrl+click is used
		selectOnCheck: true,
		checkOnSelect: true,
		pagination: false,
		pagePosition: 'bottom',	// top,bottom,both
		pageNumber: 1,
		pageSize: 10,
		pageList: [10,20,30,40,50],
		queryParams: {},
		sortName: null,
		sortOrder: 'asc',
		multiSort: false,
		remoteSort: true,
		showHeader: true,
		showFooter: false,
		scrollOnSelect: true,
		scrollbarSize: 18,
		rownumberWidth: 30,	// the column width of rownumbers
		// editorHeight: 24,	// the default height of editors
		editorHeight: 31,	// the default height of editors
		headerEvents: {
			mouseover: headerOverEventHandler(true),
			mouseout: headerOverEventHandler(false),
			click: headerClickEventHandler,
			dblclick: headerDblclickEventHandler,
			contextmenu: headerMenuEventHandler
		},
		rowEvents: {
			mouseover: hoverEventHandler(true),
			mouseout: hoverEventHandler(false),
			click: clickEventHandler,
			dblclick: dblclickEventHandler,
			contextmenu: contextmenuEventHandler
		},
		rowStyler: function(rowIndex, rowData){},	// return style such as 'background:red'
		loader: function(param, success, error){
			var opts = $(this).datagrid('options');
			if (!opts.url) return false;
			$.ajax({
				type: opts.method,
				url: opts.url,
				data: param,
				dataType: 'json',
				success: function(data){
					success(data);
				},
				error: function(){
					error.apply(this, arguments);
				}
			});
		},
		loadFilter: function(data){
			return data;
		},
		
		editors: editors,
		finder:{
			getTr:function(target, index, type, serno){
				type = type || 'body';
				serno = serno || 0;
				var state = $.data(target, 'datagrid');
				var dc = state.dc;	// data container
				var opts = state.options;
				if (serno == 0){
					var tr1 = opts.finder.getTr(target, index, type, 1);
					var tr2 = opts.finder.getTr(target, index, type, 2);
					return tr1.add(tr2);
				} else {
					if (type == 'body'){
						var tr = $('#' + state.rowIdPrefix + '-' + serno + '-' + index);
						if (!tr.length){
							tr = (serno==1?dc.body1:dc.body2).find('>table>tbody>tr[datagrid-row-index='+index+']');
						}
						return tr;
					} else if (type == 'footer'){
						return (serno==1?dc.footer1:dc.footer2).find('>table>tbody>tr[datagrid-row-index='+index+']');
					} else if (type == 'selected'){
						return (serno==1?dc.body1:dc.body2).find('>table>tbody>tr.datagrid-row-selected');
					} else if (type == 'highlight'){
						return (serno==1?dc.body1:dc.body2).find('>table>tbody>tr.datagrid-row-over');
					} else if (type == 'checked'){
						return (serno==1?dc.body1:dc.body2).find('>table>tbody>tr.datagrid-row-checked');
					} else if (type == 'editing'){
						return (serno==1?dc.body1:dc.body2).find('>table>tbody>tr.datagrid-row-editing');
					} else if (type == 'last'){
						return (serno==1?dc.body1:dc.body2).find('>table>tbody>tr[datagrid-row-index]:last');
					} else if (type == 'allbody'){
						return (serno==1?dc.body1:dc.body2).find('>table>tbody>tr[datagrid-row-index]');
					} else if (type == 'allfooter'){
						return (serno==1?dc.footer1:dc.footer2).find('>table>tbody>tr[datagrid-row-index]');
					}
				}
			},
			getRow:function(target, p){	// p can be row index or tr object
				var index = (typeof p == 'object') ? p.attr('datagrid-row-index') : p;
				return $.data(target, 'datagrid').data.rows[parseInt(index)];
			},
			getRows:function(target){
				return $(target).datagrid('getRows');
			}
		},
		view: defaultView,
		
		onBeforeLoad: function(param){},
		onLoadSuccess: function(){},
		onLoadError: function(){},
		onClickRow: function(rowIndex, rowData){},
		onDblClickRow: function(rowIndex, rowData){},
		onClickCell: function(rowIndex, field, value){},
		onDblClickCell: function(rowIndex, field, value){},
		onBeforeSortColumn: function(sort, order){},
		onSortColumn: function(sort, order){},
		onResizeColumn: function(field, width){},
		onBeforeSelect: function(rowIndex, rowData){},
		onSelect: function(rowIndex, rowData){},
		onBeforeUnselect: function(rowIndex, rowData){},
		onUnselect: function(rowIndex, rowData){},
		onSelectAll: function(rows){},
		onUnselectAll: function(rows){},
		onBeforeCheck: function(rowIndex, rowData){},
		onCheck: function(rowIndex, rowData){},
		onBeforeUncheck: function(rowIndex, rowData){},
		onUncheck: function(rowIndex, rowData){},
		onCheckAll: function(rows){},
		onUncheckAll: function(rows){},
		onBeforeEdit: function(rowIndex, rowData){},
		onBeginEdit: function(rowIndex, rowData){},
		onEndEdit: function(rowIndex, rowData, changes){},
		onAfterEdit: function(rowIndex, rowData, changes){},
		onCancelEdit: function(rowIndex, rowData){},
		onHeaderContextMenu: function(e, field){},
		onRowContextMenu: function(e, rowIndex, rowData){}
	});
})(jQuery);
