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
 * tree - EasyUI for jQuery
 * 
 * Dependencies:
 * 	 draggable
 *   droppable
 *   
 * Node is a javascript object which contains following properties:
 * 1 id: An identity value bind to the node.
 * 2 text: Text to be showed.
 * 3 checked: Indicate whether the node is checked selected.
 * 3 attributes: Custom attributes bind to the node.
 * 4 target: Target DOM object.
 */
(function($){
	/**
	 * wrap the <ul> tag as a tree and then return it.
	 */
	function wrapTree(target){
		var tree = $(target);
		tree.addClass('tree');
		return tree;
	}
	
	function bindTreeEvents(target){
		var opts = $.data(target, 'tree').options;
		$(target)._unbind()._bind('mouseover', function(e){
			var tt = $(e.target);
			var node = tt.closest('div.tree-node');
			if (!node.length){return;}
			node.addClass('tree-node-hover');
			if (tt.hasClass('tree-hit')){
				if (tt.hasClass('tree-expanded')){
					tt.addClass('tree-expanded-hover');
				} else {
					tt.addClass('tree-collapsed-hover');
				}
			}
			e.stopPropagation();
		})._bind('mouseout', function(e){
			var tt = $(e.target);
			var node = tt.closest('div.tree-node');
			if (!node.length){return;}
			node.removeClass('tree-node-hover');
			if (tt.hasClass('tree-hit')){
				if (tt.hasClass('tree-expanded')){
					tt.removeClass('tree-expanded-hover');
				} else {
					tt.removeClass('tree-collapsed-hover');
				}
			}
			e.stopPropagation();
		})._bind('click', function(e){
			var tt = $(e.target);
			var node = tt.closest('div.tree-node');
			if (!node.length){return;}
			if (tt.hasClass('tree-hit')){
				toggleNode(target, node[0]);
				return false;
			} else if (tt.hasClass('tree-checkbox')){
				// checkNode(target, node[0], !tt.hasClass('tree-checkbox1'));
				checkNode(target, node[0]);
				return false;
			} else {
				selectNode(target, node[0]);
				opts.onClick.call(target, getNode(target, node[0]));
			}
			e.stopPropagation();
		})._bind('dblclick', function(e){
			var node = $(e.target).closest('div.tree-node');
			if (!node.length){return;}
			selectNode(target, node[0]);
			opts.onDblClick.call(target, getNode(target, node[0]));
			e.stopPropagation();
		})._bind('contextmenu', function(e){
			var node = $(e.target).closest('div.tree-node');
			if (!node.length){return;}
			opts.onContextMenu.call(target, e, getNode(target, node[0]));
			e.stopPropagation();
		});
	}
	
	function disableDnd(target){
		var opts = $.data(target, 'tree').options;
		opts.dnd = false;
		var nodes = $(target).find('div.tree-node');
		nodes.draggable('disable');
		nodes.css('cursor', 'pointer');
	}
	
	function enableDnd(target){
		var state = $.data(target, 'tree');
		var opts = state.options;
		var tree = state.tree;
		state.disabledNodes = [];
		opts.dnd = true;
		
		tree.find('div.tree-node').draggable({
			disabled: false,
			revert: true,
			cursor: 'pointer',
			proxy: function(source){
				var p = $('<div class="tree-node-proxy"></div>').appendTo('body');
				p.html('<span class="tree-dnd-icon tree-dnd-no">&nbsp;</span>'+$(source).find('.tree-title').html());
				p.hide();
				return p;
			},
			deltaX: 15,
			deltaY: 15,
			onBeforeDrag: function(e){
				if (opts.onBeforeDrag.call(target, getNode(target, this)) == false){return false}
				if ($(e.target).hasClass('tree-hit') || $(e.target).hasClass('tree-checkbox')){return false;}
				if (e.which != 1){return false;}
//				$(this).next('ul').find('div.tree-node').droppable({accept:'no-accept'});	// the child node can't be dropped
				var indent = $(this).find('span.tree-indent');
				if (indent.length){
					e.data.offsetWidth -= indent.length*indent.width();
				}
			},
			onStartDrag: function(e){
				// disable the droppable of child nodes
				$(this).next('ul').find('div.tree-node').each(function(){
					$(this).droppable('disable');
					state.disabledNodes.push(this);
				});
				$(this).draggable('proxy').css({
					left:-10000,
					top:-10000
				});
				opts.onStartDrag.call(target, getNode(target, this));
				var node = getNode(target, this);
				if (node.id == undefined){
					node.id = 'easyui_tree_node_id_temp';
					updateNode(target, node);
				}
				state.draggingNodeId = node.id;	// store the dragging node id
			},
			onDrag: function(e){
				var x1=e.pageX,y1=e.pageY,x2=e.data.startX,y2=e.data.startY;
				var d = Math.sqrt((x1-x2)*(x1-x2)+(y1-y2)*(y1-y2));
				if (d>3){	// when drag a little distance, show the proxy object
					$(this).draggable('proxy').show();
				}
				this.pageY = e.pageY;
			},
			onStopDrag: function(){
//				$(this).next('ul').find('div.tree-node').droppable({accept:'div.tree-node'}); // restore the accept property of child nodes
				for(var i=0; i<state.disabledNodes.length; i++){
					$(state.disabledNodes[i]).droppable('enable');
				}
				state.disabledNodes = [];
				// get the source node
				var node = findNode(target, state.draggingNodeId);
				if (node && node.id == 'easyui_tree_node_id_temp'){
					node.id = '';
					updateNode(target, node);
				}
				opts.onStopDrag.call(target, node);
			}
		}).droppable({
			accept:'div.tree-node',
			onDragEnter: function(e, source){
				if (opts.onDragEnter.call(target, this, getSourceData(source)) == false){
					allowDrop(source, false);
					$(this).removeClass('tree-node-append tree-node-top tree-node-bottom');
					$(this).droppable('disable');
					state.disabledNodes.push(this);
				}
			},
			onDragOver: function(e, source){
				if ($(this).droppable('options').disabled){return}
				var pageY = source.pageY;
				var top = $(this).offset().top;
				var bottom = top + $(this).outerHeight();
				
				allowDrop(source, true);
				$(this).removeClass('tree-node-append tree-node-top tree-node-bottom');
				if (pageY > top + (bottom - top) / 2){
					if (bottom - pageY < 5){
						$(this).addClass('tree-node-bottom');
					} else {
						$(this).addClass('tree-node-append');
					}
				} else {
					if (pageY - top < 5){
						$(this).addClass('tree-node-top');
					} else {
						$(this).addClass('tree-node-append');
					}
				}
				if (opts.onDragOver.call(target, this, getSourceData(source)) == false){
					allowDrop(source, false);
					$(this).removeClass('tree-node-append tree-node-top tree-node-bottom');
					$(this).droppable('disable');
					state.disabledNodes.push(this);
				}
			},
			onDragLeave: function(e, source){
				allowDrop(source, false);
				$(this).removeClass('tree-node-append tree-node-top tree-node-bottom');
				opts.onDragLeave.call(target, this, getSourceData(source));
			},
			onDrop: function(e, source){
				var dest = this;
				var action, point;
				if ($(this).hasClass('tree-node-append')){
					action = append;
					point = 'append';
				} else {
					action = insert;
					point = $(this).hasClass('tree-node-top') ? 'top' : 'bottom';
				}
				
				if (opts.onBeforeDrop.call(target, dest, getSourceData(source), point) == false){
					$(this).removeClass('tree-node-append tree-node-top tree-node-bottom');
					return;
				}
				action(source, dest, point);
				$(this).removeClass('tree-node-append tree-node-top tree-node-bottom');
			}
		});
		
		function getSourceData(source, pop){
			return $(source).closest('ul.tree').tree(pop?'pop':'getData', source);
		}
		
		function allowDrop(source, allowed){
			var icon = $(source).draggable('proxy').find('span.tree-dnd-icon');
			icon.removeClass('tree-dnd-yes tree-dnd-no').addClass(allowed ? 'tree-dnd-yes' : 'tree-dnd-no');
		}
		
		function append(source, dest){
			if (getNode(target, dest).state == 'closed'){
				expandNode(target, dest, function(){
					doAppend();
				});
			} else {
				doAppend();
			}
			
			function doAppend(){
				var node = getSourceData(source, true);
				$(target).tree('append', {
					parent: dest,
					data: [node]
				});
				opts.onDrop.call(target, dest, node, 'append');
			}
		}
		
		function insert(source, dest, point){
			var param = {};
			if (point == 'top'){
				param.before = dest;
			} else {
				param.after = dest;
			}
			
			var node = getSourceData(source, true);
			param.data = node;
			$(target).tree('insert', param);
			opts.onDrop.call(target, dest, node, point);
		}
	}

	function checkNode(target, nodeEl, checked, nocallback){
		var state = $.data(target, 'tree');
		var opts = state.options;
		if (!opts.checkbox) {return;}
		
		var nodedata = getNode(target, nodeEl);
		if (!nodedata.checkState){return;}
		var ck = $(nodeEl).find('.tree-checkbox');
		if (checked == undefined){
			if (ck.hasClass('tree-checkbox1')){
				checked = false;
			} else if (ck.hasClass('tree-checkbox0')){
				checked = true;
			} else {
				if (nodedata._checked == undefined){
					nodedata._checked = $(nodeEl).find('.tree-checkbox').hasClass('tree-checkbox1');
				}
				checked = !nodedata._checked;				
			}
		}
		nodedata._checked = checked;
		if (checked){
			if (ck.hasClass('tree-checkbox1')){return;}
		} else {
			if (ck.hasClass('tree-checkbox0')){return;}
		}

		if (!nocallback){
			if (opts.onBeforeCheck.call(target, nodedata, checked) == false){return;}			
		}
		if (opts.cascadeCheck){
			setChildCheckbox(target, nodedata, checked);
			setParentCheckbox(target, nodedata);
		} else {
			setCheckedFlag(target, nodedata, checked?'1':'0');
		}
		if (!nocallback){
			opts.onCheck.call(target, nodedata, checked);			
		}
	}
	function setChildCheckbox(target, nodedata, checked){
		var opts = $.data(target, 'tree').options;
		var flag = checked ? 1 : 0;
		setCheckedFlag(target, nodedata, flag);
		if (opts.deepCheck){
			$.easyui.forEach(nodedata.children||[], true, function(n){
				setCheckedFlag(target, n, flag);
			});
		} else {
			var nodes = [];
			if (nodedata.children && nodedata.children.length){
				nodes.push(nodedata);
			}
			$.easyui.forEach(nodedata.children||[], true, function(n){
				if (!n.hidden){
					setCheckedFlag(target, n, flag);
					if (n.children && n.children.length){
						nodes.push(n);
					}
				}
			});
			for(var i=nodes.length-1; i>=0; i--){
				var node = nodes[i];
				setCheckedFlag(target, node, calcCheckFlag(node))
			}
		}
	}
	function setCheckedFlag(target, nodedata, flag){
		var opts = $.data(target, 'tree').options;
		if (!nodedata.checkState || flag==undefined){return;}
		if (nodedata.hidden && !opts.deepCheck){return;}
		var ck = $('#'+nodedata.domId).find('.tree-checkbox');
		nodedata.checkState = ['unchecked','checked','indeterminate'][flag];
		nodedata.checked = (nodedata.checkState=='checked');
		ck.removeClass('tree-checkbox0 tree-checkbox1 tree-checkbox2');
		ck.addClass('tree-checkbox' + flag);
	}
	function setParentCheckbox(target, nodedata){
		var pd = getParentNode(target, $('#'+nodedata.domId)[0]);
		if (pd){
			setCheckedFlag(target, pd, calcCheckFlag(pd));
			setParentCheckbox(target, pd);
		}
	}

	function calcCheckFlag(row){
		var c0 = 0;
		var c1 = 0;
		var len = 0;
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
	
	/**
	 * when remove node, adjust its parent node check status.
	 */
	function adjustCheck(target, nodeEl){
		var opts = $.data(target, 'tree').options;
		if (!opts.checkbox){return;}
		var node = $(nodeEl);
		var ck = node.find('.tree-checkbox');
		var nodedata = getNode(target, nodeEl);
		if (opts.view.hasCheckbox(target, nodedata)){
			if (!ck.length){
				nodedata.checkState = nodedata.checkState || 'unchecked';
				$('<span class="tree-checkbox"></span>').insertBefore(node.find('.tree-title'));
			}
			if (nodedata.checkState == 'checked'){
				checkNode(target, nodeEl, true, true);
			} else if (nodedata.checkState == 'unchecked'){
				checkNode(target, nodeEl, false, true);
			} else {
				var flag = calcCheckFlag(nodedata);
				if (flag === 0){
					checkNode(target, nodeEl, false, true);
				} else if (flag === 1){
					checkNode(target, nodeEl, true, true);
				}
			}
		} else {
			ck.remove();
			nodedata.checkState = undefined;
			nodedata.checked = undefined;
			setParentCheckbox(target, nodedata);
		}
	}
	
	/**
	 * load tree data to <ul> tag
	 * ul: the <ul> dom element
	 * data: array, the tree node data
	 * append: defines if to append data
	 */
	function loadData(target, ul, data, append, nocallback){
		var state = $.data(target, 'tree');
		var opts = state.options;
		var parent = $(ul).prevAll('div.tree-node:first');
		data = opts.loadFilter.call(target, data, parent[0]);
		
		var pnode = findNodeBy(target, 'domId', parent.attr('id'));
		if (!append){
			pnode ? pnode.children = data : state.data = data;
			$(ul).empty();
		} else {
			if (pnode){
				pnode.children ? pnode.children = pnode.children.concat(data) : pnode.children = data;
			} else {
				state.data = state.data.concat(data);
			}
		}
		
		opts.view.render.call(opts.view, target, ul, data);
		
		if (opts.dnd){enableDnd(target);}
		if (pnode){updateNode(target, pnode);}
		
		for(var i=0; i<state.tmpIds.length; i++){
			checkNode(target, $('#'+state.tmpIds[i])[0], true, true);
		}
		state.tmpIds = [];
		
		setTimeout(function(){
			showLines(target, target);
		}, 0);
		
		if (!nocallback){
			opts.onLoadSuccess.call(target, pnode, data);			
		}
	}
	
	/**
	 * draw tree lines
	 */
	function showLines(target, ul, called){
		var opts = $.data(target, 'tree').options;
		if (opts.lines){
			$(target).addClass('tree-lines');
		} else {
			$(target).removeClass('tree-lines');
			return;
		}
		
		if (!called){
			called = true;
			$(target).find('span.tree-indent').removeClass('tree-line tree-join tree-joinbottom');
			$(target).find('div.tree-node').removeClass('tree-node-last tree-root-first tree-root-one');
			var roots = $(target).tree('getRoots');
			if (roots.length > 1){
				$(roots[0].target).addClass('tree-root-first');
			} else if (roots.length == 1){
				$(roots[0].target).addClass('tree-root-one');
			}
		}
		$(ul).children('li').each(function(){
			var node = $(this).children('div.tree-node');
			var ul = node.next('ul');
			if (ul.length){
				if ($(this).next().length){
					_line(node);
				}
				showLines(target, ul, called);
			} else {
				_join(node);
			}
		});
		var lastNode = $(ul).children('li:last').children('div.tree-node').addClass('tree-node-last');
		lastNode.children('span.tree-join').removeClass('tree-join').addClass('tree-joinbottom');
		
		function _join(node, hasNext){
			var icon = node.find('span.tree-icon');
			icon.prev('span.tree-indent').addClass('tree-join');
		}
		
		function _line(node){
			var depth = node.find('span.tree-indent, span.tree-hit').length;
			node.next().find('div.tree-node').each(function(){
				$(this).children('span:eq('+(depth-1)+')').addClass('tree-line');
			});
		}
	}
	
	/**
	 * request remote data and then load nodes in the <ul> tag.
	 * ul: the <ul> dom element
	 * param: request parameter
	 */
	function request(target, ul, param, callback){
		var opts = $.data(target, 'tree').options;
		
		param = $.extend({}, opts.queryParams, param||{});
//		param = param || {};
		
		var nodedata = null;
		if (target != ul){
			var node = $(ul).prev();
			nodedata = getNode(target, node[0]);
		}

		if (opts.onBeforeLoad.call(target, nodedata, param) == false) return;
		
		var folder = $(ul).prev().children('span.tree-folder');
		folder.addClass('tree-loading');
		var result = opts.loader.call(target, param, function(data){
			folder.removeClass('tree-loading');
			loadData(target, ul, data);
			if (callback){
				callback();
			}
		}, function(){
			folder.removeClass('tree-loading');
			opts.onLoadError.apply(target, arguments);
			if (callback){
				callback();
			}
		});
		if (result == false){
			folder.removeClass('tree-loading');
		}
	}
	
	function expandNode(target, nodeEl, callback){
		var opts = $.data(target, 'tree').options;
		
		var hit = $(nodeEl).children('span.tree-hit');
		if (hit.length == 0) return;	// is a leaf node
		if (hit.hasClass('tree-expanded')) return;	// has expanded
		
		var node = getNode(target, nodeEl);
		if (opts.onBeforeExpand.call(target, node) == false) return;
		
		hit.removeClass('tree-collapsed tree-collapsed-hover').addClass('tree-expanded');
		hit.next().addClass('tree-folder-open');
		var ul = $(nodeEl).next();
		if (ul.length){
			if (opts.animate){
				ul.slideDown('normal', function(){
					node.state = 'open';
					opts.onExpand.call(target, node);
					if (callback) callback();
				});
			} else {
				ul.css('display','block');
				node.state = 'open';
				opts.onExpand.call(target, node);
				if (callback) callback();
			}
		} else {
			var subul = $('<ul style="display:none"></ul>').insertAfter(nodeEl);
			// request children nodes data
			request(target, subul[0], {id:node.id}, function(){
				if (subul.is(':empty')){
					subul.remove();	// if load children data fail, remove the children node container
				}
				if (opts.animate){
					subul.slideDown('normal', function(){
						node.state = 'open';
						opts.onExpand.call(target, node);
						if (callback) callback();
					});
				} else {
					subul.css('display','block');
					node.state = 'open';
					opts.onExpand.call(target, node);
					if (callback) callback();
				}
			});
		}
	}
	
	function collapseNode(target, nodeEl){
		var opts = $.data(target, 'tree').options;
		
		var hit = $(nodeEl).children('span.tree-hit');
		if (hit.length == 0) return;	// is a leaf node
		if (hit.hasClass('tree-collapsed')) return;	// has collapsed
		
		var node = getNode(target, nodeEl);
		if (opts.onBeforeCollapse.call(target, node) == false) return;
		
		hit.removeClass('tree-expanded tree-expanded-hover').addClass('tree-collapsed');
		hit.next().removeClass('tree-folder-open');
		var ul = $(nodeEl).next();
		if (opts.animate){
			ul.slideUp('normal', function(){
				node.state = 'closed';
				opts.onCollapse.call(target, node);
			});
		} else {
			ul.css('display','none');
			node.state = 'closed';
			opts.onCollapse.call(target, node);
		}
	}
	
	function toggleNode(target, nodeEl){
		var hit = $(nodeEl).children('span.tree-hit');
		if (hit.length == 0) return;	// is a leaf node
		
		if (hit.hasClass('tree-expanded')){
			collapseNode(target, nodeEl);
		} else {
			expandNode(target, nodeEl);
		}
	}
	
	function expandAllNode(target, nodeEl){
		var nodes = getChildren(target, nodeEl);
		if (nodeEl){
			nodes.unshift(getNode(target, nodeEl));
		}
		for(var i=0; i<nodes.length; i++){
			expandNode(target, nodes[i].target);
		}
	}
	
	function expandToNode(target, nodeEl){
		var nodes = [];
		var p = getParentNode(target, nodeEl);
		while(p){
			nodes.unshift(p);
			p = getParentNode(target, p.target);
		}
		for(var i=0; i<nodes.length; i++){
			expandNode(target, nodes[i].target);
		}
	}
	
	function scrollToNode(target, nodeEl){
		var c = $(target).parent();
		while(c[0].tagName != 'BODY' && c.css('overflow-y') != 'auto'){
			c = c.parent();
		}
		var n = $(nodeEl);
		var ntop = n.offset().top;
		if (c[0].tagName != 'BODY'){
			var ctop = c.offset().top;
			if (ntop < ctop){
				c.scrollTop(c.scrollTop() + ntop - ctop);
			} else if (ntop + n.outerHeight() > ctop + c.outerHeight() - 18){
				c.scrollTop(c.scrollTop() + ntop + n.outerHeight() - ctop - c.outerHeight() + 18);
			}
		} else {
			c.scrollTop(ntop);
		}
	}
	
	function collapseAllNode(target, nodeEl){
		var nodes = getChildren(target, nodeEl);
		if (nodeEl){
			nodes.unshift(getNode(target, nodeEl));
		}
		for(var i=0; i<nodes.length; i++){
			collapseNode(target, nodes[i].target);
		}
	}
	
	
	/**
	 * Append nodes to tree.
	 * The param parameter has two properties:
	 * 1 parent: DOM object, the parent node to append to.
	 * 2 data: array, the nodes data.
	 */
	function appendNodes(target, param){
		var node = $(param.parent);
		var data = param.data;
		if (!data){return}
		data = $.isArray(data) ? data : [data];
		if (!data.length){return}
		
		var ul;
		if (node.length == 0){
			ul = $(target);
		} else {
			// ensure the node is a folder node
			if (isLeaf(target, node[0])){
				var nodeIcon = node.find('span.tree-icon');
				nodeIcon.removeClass('tree-file').addClass('tree-folder tree-folder-open');
				var hit = $('<span class="tree-hit tree-expanded"></span>').insertBefore(nodeIcon);
				if (hit.prev().length){
					hit.prev().remove();
				}
			}
			
			ul = node.next();
			if (!ul.length){
				ul = $('<ul></ul>').insertAfter(node);
			}
		}
		
		loadData(target, ul[0], data, true, true);
		
		// adjustCheck(target, ul.prev());
	}
	
	/**
	 * insert node to before or after specified node
	 * param has the following properties:
	 * before: DOM object, the node to insert before
	 * after: DOM object, the node to insert after
	 * data: object, the node data 
	 */
	function insertNode(target, param){
		var ref = param.before || param.after;
		var pnode = getParentNode(target, ref);
		var data = param.data;
		if (!data){return}
		data = $.isArray(data) ? data : [data];
		if (!data.length){return}
		
		appendNodes(target, {
			parent: (pnode ? pnode.target : null),
			data: data
		});
		
		//adjust the sequence of nodes
		var pdata = pnode ? pnode.children : $(target).tree('getRoots');
		for(var i=0; i<pdata.length; i++){
			if (pdata[i].domId == $(ref).attr('id')){
				for(var j=data.length-1; j>=0; j--){
					pdata.splice((param.before ? i : (i+1)), 0, data[j]);
				}
				pdata.splice(pdata.length-data.length, data.length);
				break;
			}
		}
		
		var li = $();
		for(var i=0; i<data.length; i++){
			li = li.add($('#'+data[i].domId).parent());
		}
		
		if (param.before){
			li.insertBefore($(ref).parent());
		} else {
			li.insertAfter($(ref).parent());
		}
	}
	
	/**
	 * Remove node from tree. 
	 * nodeEl: DOM object, indicate the node to be removed.
	 */
	function removeNode(target, nodeEl){
		var parent = del(nodeEl);
		$(nodeEl).parent().remove();
		if (parent){
			if (!parent.children || !parent.children.length){
				var node = $(parent.target);
				node.find('.tree-icon').removeClass('tree-folder').addClass('tree-file');
				node.find('.tree-hit').remove();
				$('<span class="tree-indent"></span>').prependTo(node);
				node.next().remove();
			}
			updateNode(target, parent);
		}
		
		showLines(target, target);
		
		function del(nodeEl){
			var id = $(nodeEl).attr('id');
			var parent = getParentNode(target, nodeEl);
			var cc = parent ? parent.children : $.data(target, 'tree').data;
			for(var i=0; i<cc.length; i++){
				if (cc[i].domId == id){
					cc.splice(i, 1);
					break;
				}
			}
			return parent;
		}
	}
	
	function updateNode(target, param){
		var opts = $.data(target, 'tree').options;
		var node = $(param.target);
		var data = getNode(target, param.target);
		if (data.iconCls){
			node.find('.tree-icon').removeClass(data.iconCls);
		}
		$.extend(data, param);
		node.find('.tree-title').html(opts.formatter.call(target, data));
		if (data.iconCls){
			node.find('.tree-icon').addClass(data.iconCls);
		}
		adjustCheck(target, param.target);
	}
	
	/**
	 * get the first root node of a specified node, if no root node exists, return null.
	 */
	function getRootNode(target, nodeEl){
		if (nodeEl){
			var p = getParentNode(target, nodeEl);
			while(p){
				nodeEl = p.target;
				p = getParentNode(target, nodeEl);
			}
			return getNode(target, nodeEl);
		} else {
			var roots = getRootNodes(target);
			return roots.length ? roots[0] : null;
		}
	}
	
	/**
	 * get the root nodes.
	 */
	function getRootNodes(target){
		var nodes = $.data(target, 'tree').data;
		for(var i=0; i<nodes.length; i++){
			attachProperties(nodes[i]);
		}
		return nodes;
	}
	
	/**
	 * get all child nodes corresponding to specified node
	 * nodeEl: the node DOM element
	 */
	function getChildren(target, nodeEl){
		var nodes = [];
		var n = getNode(target, nodeEl);
		var data = n ? (n.children||[]) : $.data(target, 'tree').data;
		$.easyui.forEach(data, true, function(node){
			nodes.push(attachProperties(node));				
		});
		return nodes;
	}
	
	/**
	 * get the parent node
	 * nodeEl: DOM object, from which to search it's parent node 
	 */
	function getParentNode(target, nodeEl){
		var p = $(nodeEl).closest('ul').prevAll('div.tree-node:first');
		return getNode(target, p[0]);
	}
	
	/**
	 * get the specified state nodes
	 * the state available values are: 'checked','unchecked','indeterminate', default is 'checked'.
	 */
	function getCheckedNode(target, state){
		state = state || 'checked';
		if (!$.isArray(state)){state = [state];}
		var nodes = [];
		$.easyui.forEach($.data(target, 'tree').data, true, function(n){
			if (n.checkState && $.easyui.indexOfArray(state, n.checkState) != -1){
				nodes.push(attachProperties(n));
			}
		});
		return nodes;
	}
	
	/**
	 * Get the selected node data which contains following properties: id,text,attributes,target
	 */
	function getSelectedNode(target){
		var node = $(target).find('div.tree-node-selected');
		return node.length ? getNode(target, node[0]) : null;
	}
	
	/**
	 * get specified node data, include its children data
	 */
	function getData(target, nodeEl){
		var data = getNode(target, nodeEl);
		if (data && data.children){
			$.easyui.forEach(data.children, true, function(node){
				attachProperties(node);
			});
		}
		return data;
	}
	
	/**
	 * get the specified node
	 */
	function getNode(target, nodeEl){
		return findNodeBy(target, 'domId', $(nodeEl).attr('id'));
	}
	
	// function findNode(target, id){
	// 	return findNodeBy(target, 'id', id);
	// }
	function findNode(target, param){
		if ($.isFunction(param)){
			var fn = param;
		} else {
			var param = typeof param == 'object' ? param : {id:param};
			var fn = function(node){
				for(var p in param){
					if (node[p] != param[p]){
						return false;
					}
				}
				return true;
			};
		}
		var result = null;
		var data = $.data(target, 'tree').data;
		$.easyui.forEach(data, true, function(node){
			if (fn.call(target, node) == true){
				result = attachProperties(node);
				return false;
			}
		});
		return result;
	}
	
	// function findNodeBy(target, param, value){
	// 	var data = $.data(target, 'tree').data;
	// 	var result = null;
	// 	$.easyui.forEach(data, true, function(node){
	// 		if (node[param] == value){
	// 			result = attachProperties(node);
	// 			return false;
	// 		}
	// 	});
	// 	return result;
	// }
	function findNodeBy(target, field, value){
		var param = {};
		param[field] = value;
		return findNode(target, param);
	}
	
	function attachProperties(node){
		node.target = $('#'+node.domId)[0];
		return node;
	}
	
	/**
	 * select the specified node.
	 * nodeEl: DOM object, indicate the node to be selected.
	 */
	function selectNode(target, nodeEl){
		var opts = $.data(target, 'tree').options;
		var node = getNode(target, nodeEl);
		if (opts.onBeforeSelect.call(target, node) == false) return;
		$(target).find('div.tree-node-selected').removeClass('tree-node-selected');
		$(nodeEl).addClass('tree-node-selected');
		opts.onSelect.call(target, node);
	}
	
	/**
	 * Check if the specified node is leaf.
	 * nodeEl: DOM object, indicate the node to be checked.
	 */
	function isLeaf(target, nodeEl){
		return $(nodeEl).children('span.tree-hit').length == 0;
	}
	
	function beginEdit(target, nodeEl){
		var opts = $.data(target, 'tree').options;
		var node = getNode(target, nodeEl);
		
		if (opts.onBeforeEdit.call(target, node) == false) return;
		
		$(nodeEl).css('position', 'relative');
		var nt = $(nodeEl).find('.tree-title');
		var width = nt.outerWidth();
		nt.empty();
		var editor = $('<input class="tree-editor">').appendTo(nt);
		editor.val(node.text).focus();
		editor.width(width + 20);
		editor._outerHeight(opts.editorHeight);
		editor._bind('click', function(e){
			return false;
		})._bind('mousedown', function(e){
			e.stopPropagation();
		})._bind('mousemove', function(e){
			e.stopPropagation();
		})._bind('keydown', function(e){
			if (e.keyCode == 13){	// enter
				endEdit(target, nodeEl);
				return false;
			} else if (e.keyCode == 27){	// esc
				cancelEdit(target, nodeEl);
				return false;
			}
		})._bind('blur', function(e){
			e.stopPropagation();
			endEdit(target, nodeEl);
		});
	}
	
	function endEdit(target, nodeEl){
		var opts = $.data(target, 'tree').options;
		$(nodeEl).css('position', '');
		var editor = $(nodeEl).find('input.tree-editor');
		var val = editor.val();
		editor.remove();
		var node = getNode(target, nodeEl);
		node.text = val;
		updateNode(target, node);
		opts.onAfterEdit.call(target, node);
	}
	
	function cancelEdit(target, nodeEl){
		var opts = $.data(target, 'tree').options;
		$(nodeEl).css('position', '');
		$(nodeEl).find('input.tree-editor').remove();
		var node = getNode(target, nodeEl);
		updateNode(target, node);
		opts.onCancelEdit.call(target, node);
	}
	
	function doFilter(target, q){
		var state = $.data(target, 'tree');
		var opts = state.options;
		var ids = {};
		$.easyui.forEach(state.data, true, function(node){
			if (opts.filter.call(target, q, node)){
				$('#'+node.domId).removeClass('tree-node-hidden');
				ids[node.domId] = 1;
				node.hidden = false;
			} else {
				$('#'+node.domId).addClass('tree-node-hidden');
				node.hidden = true;
			}
		});
		for(var id in ids){
			showParents(id);
		}

		function showParents(domId){
			var p = $(target).tree('getParent', $('#'+domId)[0]);
			while (p){
				$(p.target).removeClass('tree-node-hidden');
				p.hidden = false;
				p = $(target).tree('getParent', p.target);
			}
		}
	}

	$.fn.tree = function(options, param){
		if (typeof options == 'string'){
			return $.fn.tree.methods[options](this, param);
		}
		
		var options = options || {};
		return this.each(function(){
			var state = $.data(this, 'tree');
			var opts;
			if (state){
				opts = $.extend(state.options, options);
				state.options = opts;
			} else {
				opts = $.extend({}, $.fn.tree.defaults, $.fn.tree.parseOptions(this), options);
				$.data(this, 'tree', {
					options: opts,
					tree: wrapTree(this),
					data: [],
					tmpIds: []
				});
				var data = $.fn.tree.parseData(this);
				if (data.length){
					loadData(this, this, data);
				}
			}
			bindTreeEvents(this);
			
			if (opts.data){
				loadData(this, this, $.extend(true,[],opts.data));
			}
			request(this, this);
		});
	};
	
	$.fn.tree.methods = {
		options: function(jq){
			return $.data(jq[0], 'tree').options;
		},
		loadData: function(jq, data){
			return jq.each(function(){
				loadData(this, this, data);
			});
		},
		getNode: function(jq, nodeEl){	// get the single node
			return getNode(jq[0], nodeEl);
		},
		getData: function(jq, nodeEl){	// get the specified node data, include its children
			return getData(jq[0], nodeEl);
		},
		reload: function(jq, nodeEl){
			return jq.each(function(){
				if (nodeEl){
					var node = $(nodeEl);
					var hit = node.children('span.tree-hit');
					hit.removeClass('tree-expanded tree-expanded-hover').addClass('tree-collapsed');
					node.next().remove();
					expandNode(this, nodeEl);
				} else {
					$(this).empty();
					request(this, this);
				}
			});
		},
		getRoot: function(jq, nodeEl){	// if specify 'nodeEl', return its top parent node, otherwise return the first root node.
			return getRootNode(jq[0], nodeEl);
		},
		getRoots: function(jq){
			return getRootNodes(jq[0]);
		},
		getParent: function(jq, nodeEl){
			return getParentNode(jq[0], nodeEl);
		},
		getChildren: function(jq, nodeEl){
			return getChildren(jq[0], nodeEl);
		},
		getChecked: function(jq, state){	// the state available values are: 'checked','unchecked','indeterminate', default is 'checked'.
			return getCheckedNode(jq[0], state);
		},
		getSelected: function(jq){
			return getSelectedNode(jq[0]);
		},
		isLeaf: function(jq, nodeEl){
			return isLeaf(jq[0], nodeEl);
		},
		find: function(jq, id){
			return findNode(jq[0], id);
		},
		findBy: function(jq, param){
			return findNodeBy(jq[0], param.field, param.value);
		},
		select: function(jq, nodeEl){
			return jq.each(function(){
				selectNode(this, nodeEl);
			});
		},
		check: function(jq, nodeEl){
			return jq.each(function(){
				checkNode(this, nodeEl, true);
			});
		},
		uncheck: function(jq, nodeEl){
			return jq.each(function(){
				checkNode(this, nodeEl, false);
			});
		},
		collapse: function(jq, nodeEl){
			return jq.each(function(){
				collapseNode(this, nodeEl);
			});
		},
		expand: function(jq, nodeEl){
			return jq.each(function(){
				expandNode(this, nodeEl);
			});
		},
		collapseAll: function(jq, nodeEl){
			return jq.each(function(){
				collapseAllNode(this, nodeEl);
			});
		},
		expandAll: function(jq, nodeEl){
			return jq.each(function(){
				expandAllNode(this, nodeEl);
			});
		},
		expandTo: function(jq, nodeEl){
			return jq.each(function(){
				expandToNode(this, nodeEl);
			});
		},
		scrollTo: function(jq, nodeEl){
			return jq.each(function(){
				scrollToNode(this, nodeEl);
			});
		},
		toggle: function(jq, nodeEl){
			return jq.each(function(){
				toggleNode(this, nodeEl);
			});
		},
		append: function(jq, param){
			return jq.each(function(){
				appendNodes(this, param);
			});
		},
		insert: function(jq, param){
			return jq.each(function(){
				insertNode(this, param);
			});
		},
		remove: function(jq, nodeEl){
			return jq.each(function(){
				removeNode(this, nodeEl);
			});
		},
		pop: function(jq, nodeEl){
			var node = jq.tree('getData', nodeEl);
			jq.tree('remove', nodeEl);
			return node;
		},
		update: function(jq, param){
			return jq.each(function(){
				updateNode(this, $.extend({}, param, {
					checkState: param.checked ? 'checked' : (param.checked===false ? 'unchecked' : undefined)
				}));
			});
		},
		enableDnd: function(jq){
			return jq.each(function(){
				enableDnd(this);
			});
		},
		disableDnd: function(jq){
			return jq.each(function(){
				disableDnd(this);
			});
		},
		beginEdit: function(jq, nodeEl){
			return jq.each(function(){
				beginEdit(this, nodeEl);
			});
		},
		endEdit: function(jq, nodeEl){
			return jq.each(function(){
				endEdit(this, nodeEl);
			});
		},
		cancelEdit: function(jq, nodeEl){
			return jq.each(function(){
				cancelEdit(this, nodeEl);
			});
		},
		doFilter: function(jq, q){
			return jq.each(function(){
				doFilter(this, q);
			});
		}
	};
	
	$.fn.tree.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.parser.parseOptions(target, [
			'url','method',
			{checkbox:'boolean',cascadeCheck:'boolean',onlyLeafCheck:'boolean'},
			{animate:'boolean',lines:'boolean',dnd:'boolean'}
		]));
	};
	
	$.fn.tree.parseData = function(target){
		var data = [];
		_parseNode(data, $(target));
		return data;
		
		function _parseNode(aa, tree){
			tree.children('li').each(function(){
				var node = $(this);
				var item = $.extend({}, $.parser.parseOptions(this, ['id','iconCls','state']), {
					checked: (node.attr('checked') ? true : undefined)
				});
				item.text = node.children('span').html();
				if (!item.text){
					item.text = node.html();
				}
				
				var subTree = node.children('ul');
				if (subTree.length){
					item.children = [];
					_parseNode(item.children, subTree);
				}
				aa.push(item);
			});
		}
	};
	
	var nodeIndex = 1;
	var defaultView = {
		render: function(target, ul, data) {
			var state = $.data(target, 'tree');
			var opts = state.options;
			var pnode = $(ul).prev('.tree-node');
			var pdata = pnode.length ? $(target).tree('getNode', pnode[0]) : null;
			var depth = pnode.find('span.tree-indent, span.tree-hit').length;
			var prefixId = $(target).attr('id')||'';
			var cc = getTreeData.call(this, depth, data);
			$(ul).append(cc.join(''));
			
			function getTreeData(depth, children){
				var cc = [];
				for(var i=0; i<children.length; i++){
					var item = children[i];
					if (item.state != 'open' && item.state != 'closed'){
						item.state = 'open';
					}
					item.domId = prefixId + '_easyui_tree_' + nodeIndex++;
					
					cc.push('<li>');
					cc.push('<div id="' + item.domId + '" class="tree-node' + (item.nodeCls?' '+item.nodeCls:'') + '">');
					for(var j=0; j<depth; j++){
						cc.push('<span class="tree-indent"></span>');
					}
					if (item.state == 'closed'){
						cc.push('<span class="tree-hit tree-collapsed"></span>');
						cc.push('<span class="tree-icon tree-folder ' + (item.iconCls?item.iconCls:'') + '"></span>');
					} else {
						if (item.children && item.children.length){
							cc.push('<span class="tree-hit tree-expanded"></span>');
							cc.push('<span class="tree-icon tree-folder tree-folder-open ' + (item.iconCls?item.iconCls:'') + '"></span>');
						} else {
							cc.push('<span class="tree-indent"></span>');
							cc.push('<span class="tree-icon tree-file ' + (item.iconCls?item.iconCls:'') + '"></span>');
						}
					}
					if (this.hasCheckbox(target, item)){
						var flag = 0;
						if (pdata && pdata.checkState=='checked' && opts.cascadeCheck){
							flag = 1;
							item.checked = true;
						} else if (item.checked){
							$.easyui.addArrayItem(state.tmpIds, item.domId);
						}
						item.checkState = flag ? 'checked' : 'unchecked';
						cc.push('<span class="tree-checkbox tree-checkbox' + flag + '"></span>');
					} else {
						item.checkState = undefined;
						item.checked = undefined;
					}
					cc.push('<span class="tree-title">' + opts.formatter.call(target, item) + '</span>');
					cc.push('</div>');
					
					if (item.children && item.children.length){
						var tmp = getTreeData.call(this, depth+1, item.children);
						cc.push('<ul style="display:' + (item.state=='closed'?'none':'block') + '">');
						cc = cc.concat(tmp);
						cc.push('</ul>');
					}
					cc.push('</li>');
				}
				return cc;
			}
		},
		hasCheckbox: function(target, item){
			var state = $.data(target, 'tree');
			var opts = state.options;
			if (opts.checkbox){
				if ($.isFunction(opts.checkbox)){
					if (opts.checkbox.call(target, item)){
						return true;
					} else {
						return false;
					}
				} else if (opts.onlyLeafCheck){
					if (item.state == 'open' && !(item.children && item.children.length)){
						return true;
					}
				} else {
					return true;
				}
			}
			return false;
		}
	};
	
	$.fn.tree.defaults = {
		url: null,
		method: 'post',
		animate: false,
		checkbox: false,
		cascadeCheck: true,
		onlyLeafCheck: false,
		lines: false,
		dnd: false,
		editorHeight: 26,
		data: null,
		queryParams: {},
		formatter: function(node){
			return node.text;
		},
		filter: function(q, node){
			var qq = [];
			$.map($.isArray(q) ? q : [q], function(q){
				q = $.trim(q);
				if (q){
					qq.push(q);
				}
			});
			for(var i=0; i<qq.length; i++){
				var index = node.text.toLowerCase().indexOf(qq[i].toLowerCase());
				if (index >= 0){
					return true;
				}
			}
			return !qq.length;
		},
		loader: function(param, success, error){
			var opts = $(this).tree('options');
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
		loadFilter: function(data, parent){
			return data;
		},
		view: defaultView,
		
		onBeforeLoad: function(node, param){},
		onLoadSuccess: function(node, data){},
		onLoadError: function(){},
		onClick: function(node){},	// node: id,text,checked,attributes,target
		onDblClick: function(node){},	// node: id,text,checked,attributes,target
		onBeforeExpand: function(node){},
		onExpand: function(node){},
		onBeforeCollapse: function(node){},
		onCollapse: function(node){},
		onBeforeCheck: function(node, checked){},
		onCheck: function(node, checked){},
		onBeforeSelect: function(node){},
		onSelect: function(node){},
		onContextMenu: function(e, node){},
		onBeforeDrag: function(node){},	// return false to deny drag
		onStartDrag: function(node){},
		onStopDrag: function(node){},
		onDragEnter: function(target, source){},	// return false to deny drop
		onDragOver: function(target, source){},	// return false to deny drop
		onDragLeave: function(target, source){},
		onBeforeDrop: function(target, source, point){},
		onDrop: function(target, source, point){},	// point:'append','top','bottom'
		onBeforeEdit: function(node){},
		onAfterEdit: function(node){},
		onCancelEdit: function(node){}
	};
})(jQuery);
