/**
 * EasyUI for jQuery 1.10.19
 * 
 * Copyright (c) 2009-2024 www.jeasyui.com. All rights reserved.
 *
 * Licensed under the commercial license: http://www.jeasyui.com/license_commercial.php
 * To use it on other terms please contact us: info@jeasyui.com
 *
 */
(function($){
	var itemIndex = 1;

	function init(target){
		$(target).addClass('sidemenu');
	}

	function setSize(target, param){
		var opts = $(target).sidemenu('options');
		if (param){
			$.extend(opts, {
				width: param.width,
				height: param.height
			});
		}
		$(target)._size(opts);
		$(target).find('.accordion').accordion('resize');
	}

	function buildTree(target, container, data){
		var opts = $(target).sidemenu('options');
		var tt = $('<ul class="sidemenu-tree"></ul>').appendTo(container);
		tt.tree({
			data: data,
			animate: opts.animate,
			onBeforeSelect: function(node){
				if (node.children){
					return false;
				}
			},
			onSelect: function(node){
				selectItem(target, node.id, true);
			},
			onExpand: function(node){
				syncItemState(target, node);
			},
			onCollapse: function(node){
				syncItemState(target, node);
			},
			onClick: function(node){
				if (node.children){
					if (node.state == 'open'){
						$(node.target).addClass('tree-node-nonleaf-collapsed');
					} else {
						$(node.target).removeClass('tree-node-nonleaf-collapsed');
					}
					$(this).tree('toggle', node.target);
				}
			}
		});
		tt._unbind('.sidemenu')._bind('mouseleave.sidemenu', function(){
			$(container).trigger('mouseleave')
		});
		selectItem(target, opts.selectedItemId);
	}

	function buildTooltip(target, header, data){
		var opts = $(target).sidemenu('options');
		$(header).tooltip({
			content: $('<div></div>'),
			position: opts.floatMenuPosition,
			valign: 'top',
			data: data,
			onUpdate: function(content){
				var topts = $(this).tooltip('options');
				var data = topts.data;
				content.accordion({
					width: opts.floatMenuWidth,
					multiple: false
				}).accordion('add', {
					title: data.text,
					// iconCls: data.iconCls,
					collapsed: false,
					collapsible: false
				});
				buildTree(target, content.accordion('panels')[0], data.children);
			},
			onShow: function(){
				var t = $(this);
				var tip = t.tooltip('tip').addClass('sidemenu-tooltip');
				tip.children('.tooltip-content').addClass('sidemenu');
				tip.find('.accordion').accordion('resize');
				tip.add(tip.find('ul.tree'))._unbind('.sidemenu')._bind('mouseover.sidemenu', function(){
					t.tooltip('show');
				})._bind('mouseleave.sidemenu', function(){
					t.tooltip('hide');
				});
				t.tooltip('reposition');
			},
			onPosition: function(left,top){
				var tip = $(this).tooltip('tip');
				if (!opts.collapsed){
					tip.css({left:-999999});
				} else {
					if (top + tip.outerHeight() > $(window)._outerHeight() + $(document).scrollTop()){
						top = $(window)._outerHeight() + $(document).scrollTop() - tip.outerHeight();
						tip.css('top',top);
					}
				}
			}
		});
	}

	function forTrees(target, callback){
		$(target).find('.sidemenu-tree').each(function(){
			callback($(this));
		});
		$(target).find('.tooltip-f').each(function(){
			var tip = $(this).tooltip('tip');
			if (tip){
				tip.find('.sidemenu-tree').each(function(){
					callback($(this));
				});
				$(this).tooltip('reposition');
			}
		});
	}

	function selectItem(target, itemId, triggered){
		var selectedNode = null;
		var opts = $(target).sidemenu('options');
		forTrees(target, function(t){
			t.find('div.tree-node-selected').removeClass('tree-node-selected');
			var node = t.tree('find', itemId);
			if (node){
				$(node.target).addClass('tree-node-selected');
				opts.selectedItemId = node.id;
				t.trigger('mouseleave.sidemenu');
				selectedNode = node;
				// opts.onSelect.call(target, node);
			}
		});
		if (triggered && selectedNode){
			opts.onSelect.call(target, selectedNode);
		}
	}

	function syncItemState(target, item){
		forTrees(target, function(t){
			var node = t.tree('find', item.id);
			if (node){
				var topts = t.tree('options');
				var animate = topts.animate;
				topts.animate = false;
				t.tree(item.state=='open'?'expand':'collapse', node.target);
				topts.animate = animate;
			}
		});
	}

	function loadData(target){
		var opts = $(target).sidemenu('options');
		$(target).empty();
		if (opts.data){
			$.easyui.forEach(opts.data, true, function(node){
				if (!node.id){
					node.id = '_easyui_sidemenu_'+(itemIndex++);
				}
				if (!node.iconCls){
					node.iconCls = 'sidemenu-default-icon';
				}
				if (node.children){
					node.nodeCls = 'tree-node-nonleaf';
					if (!node.state){
						node.state = 'closed';
					}
					if (node.state == 'open'){
						node.nodeCls = 'tree-node-nonleaf';
					} else {
						node.nodeCls = 'tree-node-nonleaf tree-node-nonleaf-collapsed';
					}
				}
			})
			var acc = $('<div></div>').appendTo(target);
			acc.accordion({
				fit: opts.height=='auto'?false:true,
				border: opts.border,
				multiple: opts.multiple
			});
			var data = opts.data;
			for(var i=0; i<data.length; i++){
				acc.accordion('add', {
					title: data[i].text,
					selected: data[i].state=='open',
					iconCls: data[i].iconCls,
					onBeforeExpand: function(){
						return !opts.collapsed;
					}
				});
				var ap = acc.accordion('panels')[i];
				buildTree(target, ap, data[i].children);
				buildTooltip(target, ap.panel('header'), data[i]);
			}
		}
	}

	function setCollapsed(target, collapsed){
		var opts = $(target).sidemenu('options');
		opts.collapsed = collapsed;
		var acc = $(target).find('.accordion');
		var panels = acc.accordion('panels');
		acc.accordion('options').animate = false;
		if (opts.collapsed){
			$(target).addClass('sidemenu-collapsed');
			for(var i=0; i<panels.length; i++){
				var panel = panels[i];
				if (panel.panel('options').collapsed){
					opts.data[i].state = 'closed';
				} else {
					opts.data[i].state = 'open';
					acc.accordion('unselect', i);
				}
				var header = panel.panel('header');
				header.find('.panel-title').html('');
				header.find('.panel-tool').hide();
			}
		} else {
			$(target).removeClass('sidemenu-collapsed');
			for(var i=0; i<panels.length; i++){
				var panel = panels[i];
				if (opts.data[i].state == 'open'){
					acc.accordion('select', i);
				}
				var header = panel.panel('header');
				header.find('.panel-title').html(panel.panel('options').title);
				header.find('.panel-tool').show();
			}
		}
		acc.accordion('options').animate = opts.animate;
	}

	function destroyMenu(target){
		$(target).find('.tooltip-f').each(function(){
			$(this).tooltip('destroy')
		});
		$(target).remove();
	}

	$.fn.sidemenu = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.sidemenu.methods[options];
			return method(this, param);
		}

		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'sidemenu');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'sidemenu', {
					options: $.extend({}, $.fn.sidemenu.defaults, $.fn.sidemenu.parseOptions(this), options)
				});
				init(this);
			}
			setSize(this);
			loadData(this);
			setCollapsed(this, state.options.collapsed);
		});
	};

	$.fn.sidemenu.methods = {
		options: function(jq){
			return jq.data('sidemenu').options;
		},
		resize: function(jq, param){
			return jq.each(function(){
				setSize(this, param);
			});
		},
		collapse: function(jq){
			return jq.each(function(){
				setCollapsed(this, true)
			});
		},
		expand: function(jq){
			return jq.each(function(){
				setCollapsed(this, false);
			});
		},
		destroy: function(jq){
			return jq.each(function(){
				destroyMenu(this);
			});
		}
	};

	$.fn.sidemenu.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.parser.parseOptions(target, [
			'width','height'
		]));
	};

	$.fn.sidemenu.defaults = {
		width: 200,
		height: 'auto',
		border: true,
		animate: true,
		multiple: true,
		collapsed: false,
		data: null,
		floatMenuWidth: 200,
		floatMenuPosition: 'right',
		onSelect: function(item){}
	};

})(jQuery);
