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
 * treegrid - EasyUI for jQuery
 * 
 * Dependencies:
 * 	 datagrid
 * 
 */
(function($){
	
	function buildGrid(target){
		var state = $.data(target, 'treegrid')
		var opts = state.options;
		$(target).datagrid($.extend({}, opts, {
			url: null,
			data: null,
			loader: function(){
				return false;
			},
			onBeforeLoad: function(){return false},
			onLoadSuccess: function(){},
			onResizeColumn: function(field, width){
				setRowHeight(target);
				opts.onResizeColumn.call(target, field, width);
			},
			onBeforeSortColumn: function(sort,order){
				if (opts.onBeforeSortColumn.call(target,sort,order) == false){return false}
			},
			onSortColumn: function(sort,order){
				opts.sortName = sort;
				opts.sortOrder = order;
				if (opts.remoteSort){
					request(target);
				} else {
					var data = $(target).treegrid('getData');
					loadData(target, null, data);
				}
				opts.onSortColumn.call(target, sort, order);
			},
			onClickCell:function(index,field){
				opts.onClickCell.call(target, field, find(target, index));
			},
			onDblClickCell:function(index,field){
				opts.onDblClickCell.call(target, field, find(target, index));
			},
			onRowContextMenu:function(e,index){
				opts.onContextMenu.call(target, e, find(target, index));
			}
		}));
		var dgOpts = $.data(target, 'datagrid').options;
		opts.columns = dgOpts.columns;
		opts.frozenColumns = dgOpts.frozenColumns;
		state.dc = $.data(target, 'datagrid').dc;
		if (opts.pagination){
			var pager = $(target).datagrid('getPager');
			pager.pagination({
				total:0,
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
	}
	
	function setRowHeight(target, idValue){
		var opts = $.data(target, 'datagrid').options;
		var dc = $.data(target, 'datagrid').dc;
		if (!dc.body1.is(':empty') && (!opts.nowrap || opts.autoRowHeight)){
			if (idValue != undefined){
				var children = getChildren(target, idValue);
				for(var i=0; i<children.length; i++){
					setHeight(children[i][opts.idField]);
				}
			}
		}
		$(target).datagrid('fixRowHeight', idValue);
		
		function setHeight(idValue){
			var tr1 = opts.finder.getTr(target, idValue, 'body', 1);
			var tr2 = opts.finder.getTr(target, idValue, 'body', 2);
			tr1.css('height', '');
			tr2.css('height', '');
			var height = Math.max(tr1.height(), tr2.height());
			tr1.css('height', height);
			tr2.css('height', height);
		}
	}
	
	function setRowNumbers(target){
		var dc = $.data(target, 'datagrid').dc;
		var opts = $.data(target, 'treegrid').options;
		if (!opts.rownumbers) return;
		dc.body1.find('div.datagrid-cell-rownumber').each(function(i){
			$(this).html(i+1);
		});
	}
	
	function hoverEventHandler(isOver){
		return function(e){
			$.fn.datagrid.defaults.rowEvents[isOver ? 'mouseover' : 'mouseout'](e);
			var tt = $(e.target);
			var fn = isOver ? 'addClass' : 'removeClass';
			if (tt.hasClass('tree-hit')){
				tt.hasClass('tree-expanded') ? tt[fn]('tree-expanded-hover') : tt[fn]('tree-collapsed-hover');
			}
		}
	}
	// function clickEventHandler(e){
	// 	var tt = $(e.target);
	// 	if (tt.hasClass('tree-hit')){
	// 		_action(toggle);
	// 	} else if (tt.hasClass('tree-checkbox')){
	// 		_action(checkNode);
	// 	} else {
	// 		$.fn.datagrid.defaults.rowEvents.click(e);
	// 	}
	// 	function _action(fn){
	// 		var tr = tt.closest('tr.datagrid-row');
	// 		var target = tr.closest('div.datagrid-view').children('.datagrid-f')[0];
	// 		fn(target, tr.attr('node-id'));			
	// 	}
	// }
	function clickEventHandler(e){
		var tt = $(e.target);
		var tr = tt.closest('tr.datagrid-row');
		if (!tr.length || !tr.parent().length){return;}
		var nodeId = tr.attr('node-id');
		var target = getTableTarget(tr);
		if (tt.hasClass('tree-hit')){
			toggle(target, nodeId);
		} else if (tt.hasClass('tree-checkbox')){
			checkNode(target, nodeId);
		} else {
			var opts = $(target).datagrid('options');
			if (!tt.parent().hasClass('datagrid-cell-check') && !opts.singleSelect && e.shiftKey){
				var rows = $(target).treegrid('getChildren');
				var idx1 = $.easyui.indexOfArray(rows, opts.idField, opts.lastSelectedIndex);
				var idx2 = $.easyui.indexOfArray(rows, opts.idField, nodeId);
				var from = Math.min(Math.max(idx1,0), idx2);
				var to = Math.max(idx1, idx2);
				var row = rows[idx2];
				var td = tt.closest('td[field]',tr);
				if (td.length){
					var field = td.attr('field');
					opts.onClickCell.call(target, nodeId, field, row[field]);
				}
				$(target).treegrid('clearSelections');
				for(var i=from; i<=to; i++){
					$(target).treegrid('selectRow', rows[i][opts.idField]);
				}
				opts.onClickRow.call(target, row);
			} else {
				$.fn.datagrid.defaults.rowEvents.click(e);
			}
		}
	}
	function getTableTarget(t){
		return $(t).closest('div.datagrid-view').children('.datagrid-f')[0];
	}

	function checkNode(target, idValue, checked, nocallback){
		var state = $.data(target, 'treegrid');
		var checkedRows = state.checkedRows;
		var opts = state.options;
		if (!opts.checkbox){return;}
		var row = find(target, idValue);
		if (!row.checkState){return;}
		var tr = opts.finder.getTr(target, idValue);
		var ck = tr.find('.tree-checkbox');
		if (checked == undefined){
			if (ck.hasClass('tree-checkbox1')){
				checked = false;
			} else if (ck.hasClass('tree-checkbox0')){
				checked = true;
			} else {
				if (row._checked == undefined){
					row._checked = ck.hasClass('tree-checkbox1');
				}
				checked = !row._checked;				
			}
		}
		row._checked = checked;
		if (checked){
			if (ck.hasClass('tree-checkbox1')){return;}
		} else {
			if (ck.hasClass('tree-checkbox0')){return;}
		}

		if (!nocallback){
			if (opts.onBeforeCheckNode.call(target, row, checked) == false){return;}			
		}
		if (opts.cascadeCheck){
			setChildCheckbox(target, row, checked);
			setParentCheckbox(target, row);
		} else {
			setCheckedFlag(target, row, checked?'1':'0');
		}
		if (!nocallback){
			opts.onCheckNode.call(target, row, checked);
		}
	}
	function setCheckedFlag(target, row, flag){
		var state = $.data(target, 'treegrid');
		var checkedRows = state.checkedRows;
		var opts = state.options;
		if (!row.checkState || flag == undefined){return;}
		var tr = opts.finder.getTr(target, row[opts.idField]);
		var ck = tr.find('.tree-checkbox');
		if (!ck.length){return;}
		row.checkState = ['unchecked','checked','indeterminate'][flag];
		row.checked = (row.checkState == 'checked');
		ck.removeClass('tree-checkbox0 tree-checkbox1 tree-checkbox2');
		ck.addClass('tree-checkbox' + flag);
		if (flag == 0){
			$.easyui.removeArrayItem(checkedRows, opts.idField, row[opts.idField]);
		} else {
			$.easyui.addArrayItem(checkedRows, opts.idField, row);
		}
	}
	function setChildCheckbox(target, row, checked){
		var flag = checked ? 1 : 0;
		setCheckedFlag(target, row, flag);
		$.easyui.forEach(row.children||[], true, function(r){
			setCheckedFlag(target, r, flag);
		});
	}
	function setParentCheckbox(target, row){
		var opts = $.data(target, 'treegrid').options;
		var prow = getParent(target, row[opts.idField]);
		if (prow){
			setCheckedFlag(target, prow, calcCheckFlag(prow));
			setParentCheckbox(target, prow);
		}
	}

	function calcCheckFlag(row){
		var len = 0;
		var c0 = 0;
		var c1 = 0;
		$.easyui.forEach(row.children||[], false, function(r){
			if (r.checkState){
				len ++;
				if (r.checkState == 'checked'){
					c1 ++;
				} else if (r.checkState == 'unchecked'){
					c0 ++;
				}
			}
		});
		if (len == 0){return undefined;}
		var flag = 0;
		if (c0 == len){
			flag = 0;
		} else if (c1 == len){
			flag = 1;
		} else {
			flag = 2;
		}
		return flag;
	}

	function adjustCheck(target, idValue){
		var opts = $.data(target, 'treegrid').options;
		if (!opts.checkbox){return;}
		var row = find(target, idValue);
		var tr = opts.finder.getTr(target, idValue);
		var ck = tr.find('.tree-checkbox');
		if (opts.view.hasCheckbox(target, row)){
			if (!ck.length){
				row.checkState = row.checkState || 'unchecked';
				$('<span class="tree-checkbox"></span>').insertBefore(tr.find('.tree-title'));
			}
			if (row.checkState == 'checked'){
				checkNode(target, idValue, true, true);
			} else if (row.checkState == 'unchecked'){
				checkNode(target, idValue, false, true);
			} else {
				var flag = calcCheckFlag(row);
				if (flag === 0){
					checkNode(target, idValue, false, true);
				} else if (flag === 1){
					checkNode(target, idValue, true, true);
				}
			}
		} else {
			ck.remove();
			row.checkState = undefined;
			row.checked = undefined;
			setParentCheckbox(target, row);
		}
	}
	
	/**
	 * create sub tree
	 * parentId: the node id value
	 */
	function createSubTree(target, parentId){
		var opts = $.data(target, 'treegrid').options;
		var tr1 = opts.finder.getTr(target, parentId, 'body', 1);
		var tr2 = opts.finder.getTr(target, parentId, 'body', 2);
		var colspan1 = $(target).datagrid('getColumnFields', true).length + (opts.rownumbers?1:0);
		var colspan2 = $(target).datagrid('getColumnFields', false).length;

		_create(tr1, colspan1);
		_create(tr2, colspan2);
		
		function _create(tr, colspan){
			$('<tr class="treegrid-tr-tree">' +
					'<td style="border:0px" colspan="' + colspan + '">' +
					'<div></div>' +
					'</td>' +
				'</tr>').insertAfter(tr);
		}
	}
	
	/**
	 * load data to specified node.
	 */
	function loadData(target, parentId, data, append, nocallback){
		var state = $.data(target, 'treegrid');
		var opts = state.options;
		var dc = state.dc;
		data = opts.loadFilter.call(target, data, parentId);
		
		var node = find(target, parentId);
		if (node){
			var node1 = opts.finder.getTr(target, parentId, 'body', 1);
			var node2 = opts.finder.getTr(target, parentId, 'body', 2);
			var cc1 = node1.next('tr.treegrid-tr-tree').children('td').children('div');
			var cc2 = node2.next('tr.treegrid-tr-tree').children('td').children('div');
			if (!append){node.children = [];}
		} else {
			var cc1 = dc.body1;
			var cc2 = dc.body2;
			if (!append){state.data = [];}
		}
		if (!append){
			cc1.empty();
			cc2.empty();
		}
		
		if (opts.view.onBeforeRender){
			opts.view.onBeforeRender.call(opts.view, target, parentId, data);
		}
		opts.view.render.call(opts.view, target, cc1, true);
		opts.view.render.call(opts.view, target, cc2, false);
		if (opts.showFooter){
			opts.view.renderFooter.call(opts.view, target, dc.footer1, true);
			opts.view.renderFooter.call(opts.view, target, dc.footer2, false);
		}
		if (opts.view.onAfterRender){
			opts.view.onAfterRender.call(opts.view, target);
		}
				
		// reset the pagination
		if (!parentId && opts.pagination){
			var total = $.data(target, 'treegrid').total;
			var pager = $(target).datagrid('getPager');
			var popts = pager.pagination('options');
			if (popts.total != data.total){
				pager.pagination('refresh',{pageNumber:opts.pageNumber,total:data.total});
				if (opts.pageNumber != popts.pageNumber && popts.pageNumber > 0){
					opts.pageNumber = popts.pageNumber;
					request(target);
				}
			}
		}
		
		setRowHeight(target);
		setRowNumbers(target);
		$(target).treegrid('showLines');
		$(target).treegrid('setSelectionState');
		$(target).treegrid('autoSizeColumn');

		if (!nocallback){
			opts.onLoadSuccess.call(target, node, data);			
		}
	}
	
	function request(target, parentId, params, append, callback){
		var opts = $.data(target, 'treegrid').options;
		var body = $(target).datagrid('getPanel').find('div.datagrid-body');
		
		if (parentId == undefined && opts.queryParams){opts.queryParams.id = undefined;}
		if (params) opts.queryParams = params;
		var param = $.extend({}, opts.queryParams);
		if (opts.pagination){
			$.extend(param, {
				page: opts.pageNumber,
				rows: opts.pageSize
			});
		}
		if (opts.sortName){
			$.extend(param, {
				sort: opts.sortName,
				order: opts.sortOrder
			});
		}
		
		var row = find(target, parentId);
		
		if (opts.onBeforeLoad.call(target, row, param) == false) return;
//		if (!opts.url) return;
		
		var folder = body.find('tr[node-id="' + parentId + '"] span.tree-folder');
		folder.addClass('tree-loading');
		$(target).treegrid('loading');
		var result = opts.loader.call(target, param, function(data){
			folder.removeClass('tree-loading');
			$(target).treegrid('loaded');
			loadData(target, parentId, data, append);
			if (callback) {
				callback();
			}
		}, function(){
			folder.removeClass('tree-loading');
			$(target).treegrid('loaded');
			opts.onLoadError.apply(target, arguments);
			if (callback){
				callback();
			}
		});
		if (result == false){
			folder.removeClass('tree-loading');
			$(target).treegrid('loaded');
		}
	}
	
	function getRoot(target){
		var roots = getRoots(target);
		return roots.length ? roots[0] : null;
	}
	
	function getRoots(target){
		return $.data(target, 'treegrid').data;
	}
	
	function getParent(target, idValue){
		var row = find(target, idValue);
		if (row._parentId){
			return find(target, row._parentId);
		} else {
			return null;
		}
	}
	
	function getChildren(target, parentId){
		var data = $.data(target, 'treegrid').data;
		if (parentId){
			var pnode = find(target, parentId);
			data = pnode ? (pnode.children||[]) : [];
		}
		var nodes = [];
		$.easyui.forEach(data, true, function(node){
			nodes.push(node);
		});
		return nodes;
	}
	
//	function getSelected(target){
//		var rows = getSelections(target);
//		if (rows.length){
//			return rows[0];
//		} else {
//			return null;
//		}
//	}
//	
//	function getSelections(target){
//		var rows = [];
//		var panel = $(target).datagrid('getPanel');
//		panel.find('div.datagrid-view2 div.datagrid-body tr.datagrid-row-selected').each(function(){
//			var id = $(this).attr('node-id');
//			rows.push(find(target, id));
//		});
//		return rows;
//	}
	
	function getLevel(target, idValue){
		// if (!idValue) return 0;
		var opts = $.data(target, 'treegrid').options;
		var tr = opts.finder.getTr(target, idValue);
		var node = tr.children('td[field="' + opts.treeField + '"]');
		return node.find('span.tree-indent,span.tree-hit').length;
	}
	
	function find(target, idValue){
		var state = $.data(target, 'treegrid');
		var opts = state.options;
		var result = null;
		$.easyui.forEach(state.data, true, function(node){
			if (node[opts.idField] == idValue){
				result = node;
				return false;
			}
		});
		return result;
	}
	
	function collapse(target, idValue){
		var opts = $.data(target, 'treegrid').options;
		var row = find(target, idValue);
		var tr = opts.finder.getTr(target, idValue);
		var hit = tr.find('span.tree-hit');
		
		if (hit.length == 0) return;	// is leaf
		if (hit.hasClass('tree-collapsed')) return;	// has collapsed
		if (opts.onBeforeCollapse.call(target, row) == false) return;
		
		hit.removeClass('tree-expanded tree-expanded-hover').addClass('tree-collapsed');
		hit.next().removeClass('tree-folder-open');
		row.state = 'closed';
		tr = tr.next('tr.treegrid-tr-tree');
		var cc = tr.children('td').children('div');
		if (opts.animate){
			cc.slideUp('normal', function(){
				$(target).treegrid('autoSizeColumn');
				setRowHeight(target, idValue);
				opts.onCollapse.call(target, row);
			});
		} else {
			cc.hide();
			$(target).treegrid('autoSizeColumn');
			setRowHeight(target, idValue);
			opts.onCollapse.call(target, row);
		}
	}
	
	function expand(target, idValue){
		var opts = $.data(target, 'treegrid').options;
		var tr = opts.finder.getTr(target, idValue);
		var hit = tr.find('span.tree-hit');
		var row = find(target, idValue);
		
		if (hit.length == 0) return;	// is leaf
		if (hit.hasClass('tree-expanded')) return;	// has expanded
		if (opts.onBeforeExpand.call(target, row) == false) return;
		
		hit.removeClass('tree-collapsed tree-collapsed-hover').addClass('tree-expanded');
		hit.next().addClass('tree-folder-open');
		var subtree = tr.next('tr.treegrid-tr-tree');
		if (subtree.length){
			var cc = subtree.children('td').children('div');
			_expand(cc);
		} else {
			createSubTree(target, row[opts.idField]);
			var subtree = tr.next('tr.treegrid-tr-tree');
			var cc = subtree.children('td').children('div');
			cc.hide();
			
//			var params = opts.queryParams || {};
			var params = $.extend({}, opts.queryParams || {});
			params.id = row[opts.idField];
			request(target, row[opts.idField], params, true, function(){
				if (cc.is(':empty')){
					subtree.remove();
				} else {
					_expand(cc);
				}
			});
		}
		
		function _expand(cc){
			row.state = 'open';
			if (opts.animate){
				cc.slideDown('normal', function(){
					$(target).treegrid('autoSizeColumn');
					setRowHeight(target, idValue);
					opts.onExpand.call(target, row);
				});
			} else {
				cc.show();
				$(target).treegrid('autoSizeColumn');
				setRowHeight(target, idValue);
				opts.onExpand.call(target, row);
			}
		}
	}
	
	function toggle(target, idValue){
		var opts = $.data(target, 'treegrid').options;
		var tr = opts.finder.getTr(target, idValue);
		var hit = tr.find('span.tree-hit');
		if (hit.hasClass('tree-expanded')){
			collapse(target, idValue);
		} else {
			expand(target, idValue);
		}
	}
	
	function collapseAll(target, idValue){
		var opts = $.data(target, 'treegrid').options;
		var nodes = getChildren(target, idValue);
		if (idValue){
			nodes.unshift(find(target, idValue));
		}
		for(var i=0; i<nodes.length; i++){
			collapse(target, nodes[i][opts.idField]);
		}
	}
	
	function expandAll(target, idValue){
		var opts = $.data(target, 'treegrid').options;
		var nodes = getChildren(target, idValue);
		if (idValue){
			nodes.unshift(find(target, idValue));
		}
		for(var i=0; i<nodes.length; i++){
			expand(target, nodes[i][opts.idField]);
		}
	}
	
	function expandTo(target, idValue){
		var opts = $.data(target, 'treegrid').options;
		var ids = [];
		var p = getParent(target, idValue);
		while(p){
			var id = p[opts.idField];
			ids.unshift(id);
			p = getParent(target, id);
		}
		for(var i=0; i<ids.length; i++){
			expand(target, ids[i]);
		}
	}
	
	function append(target, param){
		var state = $.data(target, 'treegrid');
		var opts = state.options;
		if (param.parent){
			var tr = opts.finder.getTr(target, param.parent);
			if (tr.next('tr.treegrid-tr-tree').length == 0){
				createSubTree(target, param.parent);
			}
			var cell = tr.children('td[field="' + opts.treeField + '"]').children('div.datagrid-cell');
			var nodeIcon = cell.children('span.tree-icon');
			if (nodeIcon.hasClass('tree-file')){
				nodeIcon.removeClass('tree-file').addClass('tree-folder tree-folder-open');
				var hit = $('<span class="tree-hit tree-expanded"></span>').insertBefore(nodeIcon);
				if (hit.prev().length){
					hit.prev().remove();
				}
			}
		}
		loadData(target, param.parent, param.data, state.data.length > 0, true);
	}
	
	function insert(target, param){
		var ref = param.before || param.after;
		var opts = $.data(target, 'treegrid').options;
		var pnode = getParent(target, ref);
		append(target, {
			parent: (pnode?pnode[opts.idField]:null),
			data: [param.data]
		});
		
		// adjust the sequence of nodes
		var pdata = pnode ? pnode.children : $(target).treegrid('getRoots');
		for(var i=0; i<pdata.length; i++){
			if (pdata[i][opts.idField] == ref){
				var lastNode = pdata[pdata.length-1];
				pdata.splice(param.before ? i : (i+1), 0, lastNode);
				pdata.splice(pdata.length-1, 1);
				break;
			}
		}
		
		_move(true);
		_move(false);
		setRowNumbers(target);
		$(target).treegrid('showLines');
		
		function _move(frozen){
			var serno = frozen?1:2;
			var tr = opts.finder.getTr(target, param.data[opts.idField], 'body', serno);
			var table = tr.closest('table.datagrid-btable');
			tr = tr.parent().children();
			var dest = opts.finder.getTr(target, ref, 'body', serno);
			if (param.before){
				tr.insertBefore(dest);
			} else {
				var sub = dest.next('tr.treegrid-tr-tree');
				tr.insertAfter(sub.length?sub:dest);
			}
			table.remove();
		}
	}
	
	/**
	 * remove the specified node
	 */
	function remove(target, idValue){
		var state = $.data(target, 'treegrid');
		var opts = state.options;
		var prow = getParent(target, idValue);
		$(target).datagrid('deleteRow', idValue);
		$.easyui.removeArrayItem(state.checkedRows, opts.idField, idValue);
		setRowNumbers(target);
		if (prow){
			adjustCheck(target, prow[opts.idField]);
		}
		state.total -= 1;
		$(target).datagrid('getPager').pagination('refresh', {total:state.total});
		$(target).treegrid('showLines');
	}
	
	function showLines(target){
		var t = $(target);
		var opts = t.treegrid('options');
		if (opts.lines){
			t.treegrid('getPanel').addClass('tree-lines');		
		} else {
			t.treegrid('getPanel').removeClass('tree-lines');		
			return;
		}
		
		t.treegrid('getPanel').find('span.tree-indent').removeClass('tree-line tree-join tree-joinbottom');
		t.treegrid('getPanel').find('div.datagrid-cell').removeClass('tree-node-last tree-root-first tree-root-one');
		
		var roots = t.treegrid('getRoots');
		if (roots.length > 1){
			_getCell(roots[0]).addClass('tree-root-first');
		} else if (roots.length == 1){
			_getCell(roots[0]).addClass('tree-root-one');
		}
		_join(roots);
		_line(roots);
		
		function _join(nodes){
			$.map(nodes, function(node){
				if (node.children && node.children.length){
					_join(node.children);
				} else {
					var cell = _getCell(node);
					cell.find('.tree-icon').prev().addClass('tree-join');
				}
			});
			if (nodes.length){
				var cell = _getCell(nodes[nodes.length-1]);
				cell.addClass('tree-node-last');
				cell.find('.tree-join').removeClass('tree-join').addClass('tree-joinbottom');
			}
		}
		function _line(nodes){
			$.map(nodes, function(node){
				if (node.children && node.children.length){
					_line(node.children);
				}
			});
			for(var i=0; i<nodes.length-1; i++){
				var node = nodes[i];
				var level = t.treegrid('getLevel', node[opts.idField]);
				var tr = opts.finder.getTr(target, node[opts.idField]);
				var cc = tr.next().find('tr.datagrid-row td[field="' + opts.treeField + '"] div.datagrid-cell');
				cc.find('span:eq('+(level-1)+')').addClass('tree-line');
			}
		}
		function _getCell(node){
			var tr = opts.finder.getTr(target, node[opts.idField]);
			var cell = tr.find('td[field="'+opts.treeField+'"] div.datagrid-cell');
			return cell;
		}
	}
	
	
	$.fn.treegrid = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.treegrid.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.datagrid(options, param);
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'treegrid');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'treegrid', {
					options: $.extend({}, $.fn.treegrid.defaults, $.fn.treegrid.parseOptions(this), options),
					data:[],
					checkedRows:[],
					tmpIds:[]
				});
			}
			
			buildGrid(this);
			
			if (state.options.data){
				$(this).treegrid('loadData', state.options.data);
			}
			
			request(this);
		});
	};
	
	$.fn.treegrid.methods = {
		options: function(jq){
			return $.data(jq[0], 'treegrid').options;
		},
		resize: function(jq, param){
			return jq.each(function(){
				$(this).datagrid('resize', param);
			});
		},
		fixRowHeight: function(jq, idValue){
			return jq.each(function(){
				setRowHeight(this, idValue);
			});
		},
		loadData: function(jq, data){
			return jq.each(function(){
				loadData(this, data.parent, data);
			});
		},
		load: function(jq, params){
			return jq.each(function(){
				$(this).treegrid('options').pageNumber = 1;
				$(this).treegrid('getPager').pagination({pageNumber:1});
				$(this).treegrid('reload', params);
			});
		},
		reload: function(jq, id){
			return jq.each(function(){
				var opts = $(this).treegrid('options');
//				var params = typeof id == 'object' ? id : $.extend({},opts.queryParams,{id:id});
				var params = {};
				if (typeof id == 'object'){
					params = id;
				} else {
					params = $.extend({}, opts.queryParams);
					params.id = id;
				}
				
//				var params = typeof id == 'object' ? id : {id:id};
				if (params.id){
					var node = $(this).treegrid('find', params.id);
					if (node.children){
						node.children.splice(0, node.children.length);
					}
//					var opts = $(this).treegrid('options');
					opts.queryParams = params;
					var tr = opts.finder.getTr(this, params.id);
					tr.next('tr.treegrid-tr-tree').remove();
					tr.find('span.tree-hit').removeClass('tree-expanded tree-expanded-hover').addClass('tree-collapsed');
					expand(this, params.id);
				} else {
					request(this, null, params);
				}
//				if (id){
//					var node = $(this).treegrid('find', id);
//					if (node.children){
//						node.children.splice(0, node.children.length);
//					}
//					var body = $(this).datagrid('getPanel').find('div.datagrid-body');
//					var tr = body.find('tr[node-id=' + id + ']');
//					tr.next('tr.treegrid-tr-tree').remove();
//					var hit = tr.find('span.tree-hit');
//					hit.removeClass('tree-expanded tree-expanded-hover').addClass('tree-collapsed');
//					expand(this, id);
//				} else {
//					request(this, null, {});
//				}
			});
		},
		reloadFooter: function(jq, footer){
			return jq.each(function(){
				var opts = $.data(this, 'treegrid').options;
				var dc = $.data(this, 'datagrid').dc;
				if (footer){
					$.data(this, 'treegrid').footer = footer;
				}
				if (opts.showFooter){
					opts.view.renderFooter.call(opts.view, this, dc.footer1, true);
					opts.view.renderFooter.call(opts.view, this, dc.footer2, false);
					if (opts.view.onAfterRender){
						opts.view.onAfterRender.call(opts.view, this);
					}
					$(this).treegrid('fixRowHeight');
				}
			});
		},
		getData: function(jq){
			return $.data(jq[0], 'treegrid').data;
		},
		getFooterRows: function(jq){
			return $.data(jq[0], 'treegrid').footer;
		},
		getRoot: function(jq){
			return getRoot(jq[0]);
		},
		getRoots: function(jq){
			return getRoots(jq[0]);
		},
		getParent: function(jq, id){
			return getParent(jq[0], id);
		},
		getChildren: function(jq, id){
			return getChildren(jq[0], id);
		},
		getLevel: function(jq, id){
			return getLevel(jq[0], id);
		},
		find: function(jq, id){
			return find(jq[0], id);
		},
		isLeaf: function(jq, id){
			var opts = $.data(jq[0], 'treegrid').options;
			var tr = opts.finder.getTr(jq[0], id);
			var hit = tr.find('span.tree-hit');
			return hit.length == 0;
		},
		select: function(jq, id){
			return jq.each(function(){
				$(this).datagrid('selectRow', id);
			});
		},
		unselect: function(jq, id){
			return jq.each(function(){
				$(this).datagrid('unselectRow', id);
			});
		},
		collapse: function(jq, id){
			return jq.each(function(){
				collapse(this, id);
			});
		},
		expand: function(jq, id){
			return jq.each(function(){
				expand(this, id);
			});
		},
		toggle: function(jq, id){
			return jq.each(function(){
				toggle(this, id);
			});
		},
		collapseAll: function(jq, id){
			return jq.each(function(){
				collapseAll(this, id);
			});
		},
		expandAll: function(jq, id){
			return jq.each(function(){
				expandAll(this, id);
			});
		},
		expandTo: function(jq, id){
			return jq.each(function(){
				expandTo(this, id);
			});
		},
		append: function(jq, param){
			return jq.each(function(){
				append(this, param);
			});
		},
		insert: function(jq, param){
			return jq.each(function(){
				insert(this, param);
			});
		},
		remove: function(jq, id){
			return jq.each(function(){
				remove(this, id);
			});
		},
		pop: function(jq, id){
			var row = jq.treegrid('find', id);
			jq.treegrid('remove', id);
			return row;
		},
		refresh: function(jq, id){
			return jq.each(function(){
				var opts = $.data(this, 'treegrid').options;
				opts.view.refreshRow.call(opts.view, this, id);
			});
		},
		update: function(jq, param){
			return jq.each(function(){
				var opts = $.data(this, 'treegrid').options;
				var row = param.row;
				opts.view.updateRow.call(opts.view, this, param.id, row);
				if (row.checked != undefined){
					row = find(this, param.id);
					$.extend(row, {
						checkState: row.checked ? 'checked' : (row.checked===false ? 'unchecked' : undefined)
					});
					adjustCheck(this, param.id);
				}
			});
		},
		beginEdit: function(jq, id){
			return jq.each(function(){
				$(this).datagrid('beginEdit', id);
				$(this).treegrid('fixRowHeight', id);
			});
		},
		endEdit: function(jq, id){
			return jq.each(function(){
				$(this).datagrid('endEdit', id);
			});
		},
		cancelEdit: function(jq, id){
			return jq.each(function(){
				$(this).datagrid('cancelEdit', id);
			});
		},
		showLines: function(jq){
			return jq.each(function(){
				showLines(this);
			});
		},
		setSelectionState: function(jq){
			return jq.each(function(){
				$(this).datagrid('setSelectionState');
				var state = $(this).data('treegrid');
				for(var i=0; i<state.tmpIds.length; i++){
					checkNode(this, state.tmpIds[i], true, true);
				}
				state.tmpIds = [];
			});
		},
		getCheckedNodes: function(jq, state){
			state = state || 'checked';
			var rows = [];
			$.easyui.forEach(jq.data('treegrid').checkedRows, false, function(row){
				if (row.checkState == state){
					rows.push(row);
				}
			});
			return rows;
		},
		checkNode: function(jq, id){
			return jq.each(function(){
				checkNode(this, id, true);
			});
		},
		uncheckNode: function(jq, id){
			return jq.each(function(){
				checkNode(this, id, false);
			});
		},
		clearChecked: function(jq){
			return jq.each(function(){
				var target = this;
				var opts = $(target).treegrid('options');
				$(target).datagrid('clearChecked');
				$.map($(target).treegrid('getCheckedNodes'), function(row){
					checkNode(target, row[opts.idField], false, true);
				});
			});
		}
	};
	
	$.fn.treegrid.parseOptions = function(target){
		return $.extend({}, $.fn.datagrid.parseOptions(target), $.parser.parseOptions(target,[
			'treeField',
			{checkbox:'boolean',cascadeCheck:'boolean',onlyLeafCheck:'boolean'},
			{animate:'boolean'}
		]));
	};
	
	var defaultView = $.extend({}, $.fn.datagrid.defaults.view, {
		render: function(target, container, frozen){
			var opts = $.data(target, 'treegrid').options;
			var fields = $(target).datagrid('getColumnFields', frozen);
			var rowIdPrefix = $.data(target, 'datagrid').rowIdPrefix;
			
			if (frozen){
				if (!(opts.rownumbers || (opts.frozenColumns && opts.frozenColumns.length))){
					return;
				}
			}
			
			var view = this;
			if (this.treeNodes && this.treeNodes.length){
				var table = getTreeData.call(this, frozen, this.treeLevel, this.treeNodes);
				$(container).append(table.join(''));
			}

			function getTreeData(frozen, depth, children){
				var pnode = $(target).treegrid('getParent', children[0][opts.idField]);
				var index = (pnode ? pnode.children.length : $(target).treegrid('getRoots').length) - children.length;
				
				var table = ['<table class="datagrid-btable" cellspacing="0" cellpadding="0" border="0"><tbody>'];
				for(var i=0; i<children.length; i++){
					var row = children[i];
					if (row.state != 'open' && row.state != 'closed'){
						row.state = 'open';
					}
					var css = opts.rowStyler ? opts.rowStyler.call(target, row) : '';
					var cs = this.getStyleValue(css);
					var cls = 'class="datagrid-row ' + (index++ % 2 && opts.striped ? 'datagrid-row-alt ' : ' ') + cs.c + '"';
					var style = cs.s ? 'style="' + cs.s + '"' : '';
					
					var rowId = rowIdPrefix + '-' + (frozen?1:2) + '-' + row[opts.idField];
					table.push('<tr id="' + rowId + '" node-id="' + row[opts.idField] + '" ' + cls + ' ' + style + '>');
					table = table.concat(view.renderRow.call(view, target, fields, frozen, depth, row));
					table.push('</tr>');
					
					if (row.children && row.children.length){
						var tt = getTreeData.call(this, frozen, depth+1, row.children);
						var v = row.state == 'closed' ? 'none' : 'block';
						
						table.push('<tr class="treegrid-tr-tree"><td style="border:0px" colspan=' + (fields.length + (opts.rownumbers?1:0)) + '><div style="display:' + v + '">');
						table = table.concat(tt);
						table.push('</div></td></tr>');
					}
				}
				table.push('</tbody></table>');
				return table;
			}
		},
		
		renderFooter: function(target, container, frozen){
			var opts = $.data(target, 'treegrid').options;
			var rows = $.data(target, 'treegrid').footer || [];
			var fields = $(target).datagrid('getColumnFields', frozen);
			
			var table = ['<table class="datagrid-ftable" cellspacing="0" cellpadding="0" border="0"><tbody>'];
			
			for(var i=0; i<rows.length; i++){
				var row = rows[i];
				row[opts.idField] = row[opts.idField] || ('foot-row-id'+i);
				
				table.push('<tr class="datagrid-row" node-id="' + row[opts.idField] + '">');
				table.push(this.renderRow.call(this, target, fields, frozen, 0, row));
				table.push('</tr>');
			}
			
			table.push('</tbody></table>');
			$(container).html(table.join(''));
		},
		
		renderRow: function(target, fields, frozen, depth, row){
			var state = $.data(target, 'treegrid');
			var opts = state.options;
			
			var cc = [];
			if (frozen && opts.rownumbers){
				cc.push('<td class="datagrid-td-rownumber"><div class="datagrid-cell-rownumber">0</div></td>');
			}
			for(var i=0; i<fields.length; i++){
				var field = fields[i];
				var col = $(target).datagrid('getColumnOption', field);
				if (col){
					var css = col.styler ? (col.styler(row[field], row)||'') : '';
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
					if (col.checkbox){
						cc.push('class="datagrid-cell-check ');
					} else {
						cc.push('class="datagrid-cell ' + col.cellClass);
					}
					if (field == opts.treeField){
						cc.push(' tree-node');
					}
					cc.push('">');
					
					if (col.checkbox){
						if (row.checked){
							cc.push('<input type="checkbox" checked="checked"');
						} else {
							cc.push('<input type="checkbox"');
						}
						cc.push(' name="' + field + '" value="' + (row[field]!=undefined ? row[field] : '') + '">');
					} else {
						var val = null;
						if (col.formatter){
							val = col.formatter(row[field], row);
						} else {
							val = row[field];
//							val = row[field] || '&nbsp;';
						}
						if (field == opts.treeField){
							for(var j=0; j<depth; j++){
								cc.push('<span class="tree-indent"></span>');
							}
							if (row.state == 'closed'){
								cc.push('<span class="tree-hit tree-collapsed"></span>');
								cc.push('<span class="tree-icon tree-folder ' + (row.iconCls?row.iconCls:'') + '"></span>');
							} else {
								if (row.children && row.children.length){
									cc.push('<span class="tree-hit tree-expanded"></span>');
									cc.push('<span class="tree-icon tree-folder tree-folder-open ' + (row.iconCls?row.iconCls:'') + '"></span>');
								} else {
									cc.push('<span class="tree-indent"></span>');
									cc.push('<span class="tree-icon tree-file ' + (row.iconCls?row.iconCls:'') + '"></span>');
								}
							}
							if (this.hasCheckbox(target, row)){
								var flag = 0;
								var crow = $.easyui.getArrayItem(state.checkedRows, opts.idField, row[opts.idField]);
								if (crow){
									flag = crow.checkState == 'checked' ? 1 : 2;
									row.checkState = crow.checkState;
									row.checked = crow.checked;
									$.easyui.addArrayItem(state.checkedRows, opts.idField, row);
								} else {
									var prow = $.easyui.getArrayItem(state.checkedRows, opts.idField, row._parentId);
									if (prow && prow.checkState == 'checked' && opts.cascadeCheck){
										flag = 1;
										row.checked = true;
										$.easyui.addArrayItem(state.checkedRows, opts.idField, row);
									} else if (row.checked){
										$.easyui.addArrayItem(state.tmpIds, row[opts.idField]);
									}
									row.checkState = flag ? 'checked' : 'unchecked';
								}
								cc.push('<span class="tree-checkbox tree-checkbox' + flag + '"></span>');
							} else {
								row.checkState = undefined;
								row.checked = undefined;
							}
							cc.push('<span class="tree-title">' + val + '</span>');
						} else {
							cc.push(val);
						}
					}
					
					cc.push('</div>');
					cc.push('</td>');
				}
			}
			return cc.join('');
		},
		hasCheckbox: function(target, row){
			var opts = $.data(target, 'treegrid').options;
			if (opts.checkbox){
				if ($.isFunction(opts.checkbox)){
					if (opts.checkbox.call(target, row)){
						return true;
					} else {
						return false;
					}
				} else if (opts.onlyLeafCheck){
					if (row.state == 'open' && !(row.children && row.children.length)){
						return true;
					}
				} else {
					return true;
				}
			}
			return false;
		},
		
		refreshRow: function(target, id){
			this.updateRow.call(this, target, id, {});
		},
		
		updateRow: function(target, id, row){
			var opts = $.data(target, 'treegrid').options;
			var rowData = $(target).treegrid('find', id);
			$.extend(rowData, row);
			var depth = $(target).treegrid('getLevel', id) - 1;
			var styleValue = opts.rowStyler ? opts.rowStyler.call(target, rowData) : '';
			var rowIdPrefix = $.data(target, 'datagrid').rowIdPrefix;
			var newId = rowData[opts.idField];
			
			function _update(frozen){
				var fields = $(target).treegrid('getColumnFields', frozen);
				var tr = opts.finder.getTr(target, id, 'body', (frozen?1:2));
				var rownumber = tr.find('div.datagrid-cell-rownumber').html();
				var checked = tr.find('div.datagrid-cell-check input[type=checkbox]').is(':checked');
				tr.html(this.renderRow(target, fields, frozen, depth, rowData));
				tr.attr('style', styleValue || '');
				tr.find('div.datagrid-cell-rownumber').html(rownumber);
				if (checked){
					tr.find('div.datagrid-cell-check input[type=checkbox]')._propAttr('checked', true);
				}
				if (newId != id){
					tr.attr('id', rowIdPrefix + '-' + (frozen?1:2) + '-' + newId);
					tr.attr('node-id', newId);
				}
			}
			
			_update.call(this, true);
			_update.call(this, false);
			$(target).treegrid('fixRowHeight', id);
		},
		
		deleteRow: function(target, id){
			var opts = $.data(target, 'treegrid').options;
			var tr = opts.finder.getTr(target, id);
			tr.next('tr.treegrid-tr-tree').remove();
			tr.remove();
			
			var pnode = del(id);
			if (pnode){
				if (pnode.children.length == 0){
					tr = opts.finder.getTr(target, pnode[opts.idField]);
					tr.next('tr.treegrid-tr-tree').remove();
					var cell = tr.children('td[field="' + opts.treeField + '"]').children('div.datagrid-cell');
					cell.find('.tree-icon').removeClass('tree-folder').addClass('tree-file');
					cell.find('.tree-hit').remove();
					$('<span class="tree-indent"></span>').prependTo(cell);
				}
			}
			this.setEmptyMsg(target);
			
			function del(id){
				var cc;
				var pnode = $(target).treegrid('getParent', id);
				if (pnode){
					cc = pnode.children;
				} else {
					cc = $(target).treegrid('getData');
				}
				for(var i=0; i<cc.length; i++){
					if (cc[i][opts.idField] == id){
						cc.splice(i, 1);
						break;
					}
				}
				return pnode;
			}
		},
		
		onBeforeRender: function(target, parentId, data){
			if ($.isArray(parentId)){
				data = {total:parentId.length, rows:parentId};
				parentId = null;
			}
			if (!data) return false;

			var state = $.data(target, 'treegrid');
			var opts = state.options;
			if (data.length == undefined){
				if (data.footer){
					state.footer = data.footer;
				}
				if (data.total){
					state.total = data.total;
				}
				data = this.transfer(target, parentId, data.rows);
			} else {
				function setParent(children, parentId){
					for(var i=0; i<children.length; i++){
						var row = children[i];
						row._parentId = parentId;
						if (row.children && row.children.length){
							setParent(row.children, row[opts.idField]);
						}
					}
				}
				setParent(data, parentId);
			}
			
			this.sort(target, data);
			this.treeNodes = data;
			this.treeLevel = $(target).treegrid('getLevel', parentId);
			
			var node = find(target, parentId);
			if (node){
				if (node.children){
					node.children = node.children.concat(data);
				} else {
					node.children = data;
				}
			} else {
				state.data = state.data.concat(data);
			}

		},
		
		sort: function(target, data){
			var opts = $.data(target, 'treegrid').options;
			if (!opts.remoteSort && opts.sortName){
				var names = opts.sortName.split(',');
				var orders = opts.sortOrder.split(',');
				_sort(data);
			}
			function _sort(rows){
				rows.sort(function(r1,r2){
					var r = 0;
					for(var i=0; i<names.length; i++){
						var sn = names[i];
						var so = orders[i];
						var col = $(target).treegrid('getColumnOption', sn);
						var sortFunc = col.sorter || function(a,b){
							return a==b ? 0 : (a>b?1:-1);
						};
						r = sortFunc(r1[sn], r2[sn]) * (so=='asc'?1:-1);
						if (r != 0){
							return r;
						}
					}
					return r;
				});
				for(var i=0; i<rows.length; i++){
					var children = rows[i].children;
					if (children && children.length){
						_sort(children);
					}
				}
			}
		},

		transfer: function(target, parentId, data){
			var opts = $.data(target, 'treegrid').options;
			var rows = $.extend([], data);
			var nodes = _pop(parentId, rows);	// top level nodes
			var toDo = $.extend([], nodes);
			while(toDo.length){
				var node = toDo.shift();
				var children = _pop(node[opts.idField], rows);
				if (children.length){
					if (node.children){
						node.children = node.children.concat(children);
					} else {
						node.children = children;
					}
					toDo = toDo.concat(children);
				}
			}
			return nodes;

			function _pop(parentId, rows){
				var rr = [];
				for(var i=0; i<rows.length; i++){
					var row = rows[i];
					if (row._parentId == parentId){
						rr.push(row);
						rows.splice(i, 1);
						i--;
					}
				}
				return rr;
			}
		}
	});
	
	$.fn.treegrid.defaults = $.extend({}, $.fn.datagrid.defaults, {
		treeField:null,
		checkbox: false,
		cascadeCheck: true,
		onlyLeafCheck: false,
		lines: false,
		animate: false,
		singleSelect: true,
		view: defaultView,
		rowEvents: $.extend({}, $.fn.datagrid.defaults.rowEvents, {
			mouseover: hoverEventHandler(true),
			mouseout: hoverEventHandler(false),
			click: clickEventHandler
		}),
		loader: function(param, success, error){
			var opts = $(this).treegrid('options');
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
		loadFilter: function(data, parentId){
			return data;
		},
		finder:{
			getTr:function(target, id, type, serno){
				type = type || 'body';
				serno = serno || 0;
				var dc = $.data(target, 'datagrid').dc;	// data container
				if (serno == 0){
					var opts = $.data(target, 'treegrid').options;
					var tr1 = opts.finder.getTr(target, id, type, 1);
					var tr2 = opts.finder.getTr(target, id, type, 2);
					return tr1.add(tr2);
				} else {
					if (type == 'body'){
						var tr = $('#' + $.data(target, 'datagrid').rowIdPrefix + '-' + serno + '-' + id);
						if (!tr.length){
							tr = (serno==1?dc.body1:dc.body2).find('tr[node-id="'+id+'"]');
						}
						return tr;
					} else if (type == 'footer'){
						return (serno==1?dc.footer1:dc.footer2).find('tr[node-id="'+id+'"]');
					} else if (type == 'selected'){
						return (serno==1?dc.body1:dc.body2).find('tr.datagrid-row-selected');
					} else if (type == 'highlight'){
						return (serno==1?dc.body1:dc.body2).find('tr.datagrid-row-over');
					} else if (type == 'checked'){
						return (serno==1?dc.body1:dc.body2).find('tr.datagrid-row-checked');
//						return (serno==1?dc.body1:dc.body2).find('tr.datagrid-row:has(div.datagrid-cell-check input:checked)');
					} else if (type == 'last'){
						return (serno==1?dc.body1:dc.body2).find('tr:last[node-id]');
					} else if (type == 'allbody'){
						return (serno==1?dc.body1:dc.body2).find('tr[node-id]');
					} else if (type == 'allfooter'){
						return (serno==1?dc.footer1:dc.footer2).find('tr[node-id]');
					}
				}
			},
			getRow:function(target, p){	// p can be the row id or tr object
				var id = (typeof p == 'object') ? p.attr('node-id') : p;
				return $(target).treegrid('find', id);
			},
			getRows:function(target){
				return $(target).treegrid('getChildren');
			}
		},
		
		onBeforeLoad: function(row, param){},
		onLoadSuccess: function(row, data){},
		onLoadError: function(){},
		onBeforeCollapse: function(row){},
		onCollapse: function(row){},
		onBeforeExpand: function(row){},
		onExpand: function(row){},
		onClickRow: function(row){},
		onDblClickRow: function(row){},
		onClickCell: function(field, row){},
		onDblClickCell: function(field, row){},
		onContextMenu: function(e, row){},
		onBeforeEdit: function(row){},
		onAfterEdit: function(row, changes){},
		onCancelEdit: function(row){},
		onBeforeCheckNode: function(row, checked){},
		onCheckNode: function(row, checked){}
	});
})(jQuery);
