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
 * combotree - EasyUI for jQuery
 * 
 * Dependencies:
 *   combo
 * 	 tree
 * 
 */
(function($){
	/**
	 * create the combotree component.
	 */
	function create(target){
		var state = $.data(target, 'combotree');
		var opts = state.options;
		var tree = state.tree;
		
		$(target).addClass('combotree-f');
		$(target).combo($.extend({}, opts, {
			onShowPanel: function(){
				if (opts.editable){
					tree.tree('doFilter', '');					
				}
				opts.onShowPanel.call(this);
			}
		}));
		var panel = $(target).combo('panel');
		if (!tree){
			tree = $('<ul></ul>').appendTo(panel);
			state.tree = tree;
		}
		
		tree.tree($.extend({}, opts, {
			checkbox: opts.multiple,
			onLoadSuccess: function(node, data){
				var values = $(target).combotree('getValues');
				if (opts.multiple){
					$.map(tree.tree('getChecked'), function(node){
						$.easyui.addArrayItem(values, node.id);
					});
				}
				setValues(target, values, state.remainText);				
				opts.onLoadSuccess.call(this, node, data);
			},
			onClick: function(node){
				if (opts.multiple){
					$(this).tree(node.checked ? 'uncheck' : 'check', node.target);
				} else {
					$(target).combo('hidePanel');
				}
				state.remainText = false;
				retrieveValues(target);
				opts.onClick.call(this, node);
			},
			onCheck: function(node, checked){
				state.remainText = false;
				retrieveValues(target);
				opts.onCheck.call(this, node, checked);
			}
		}));
	}
	
	/**
	 * retrieve values from tree panel.
	 */
	function retrieveValues(target){
		var state = $.data(target, 'combotree');
		var opts = state.options;
		var tree = state.tree;
		var vv = [];
		if (opts.multiple){
			vv = $.map(tree.tree('getChecked'), function(node){
				return node.id;
			});
		} else {
			var node = tree.tree('getSelected');
			if (node){
				vv.push(node.id);
			}
		}
		vv = vv.concat(opts.unselectedValues);
		setValues(target, vv, state.remainText);
	}
	
	function setValues(target, values, remainText){
		var state = $.data(target, 'combotree');
		var opts = state.options;
		var tree = state.tree;
		var topts = tree.tree('options');

		var onBeforeCheck = topts.onBeforeCheck;
		var onCheck = topts.onCheck;
		var onBeforeSelect = topts.onBeforeSelect;
		var onSelect = topts.onSelect;
		topts.onBeforeCheck = topts.onCheck = topts.onBeforeSelect = topts.onSelect = function(){};
		// topts.onBeforeCheck = topts.onCheck = topts.onSelect = function(){};
		
		if (!$.isArray(values)){
			values = values.split(opts.separator);
		}
		if (!opts.multiple){
			values = values.length ? [values[0]] : [''];
		}
		var vv = $.map(values, function(value){return String(value);});

		tree.find('div.tree-node-selected').removeClass('tree-node-selected');
		$.map(tree.tree('getChecked'), function(node){
			if ($.inArray(String(node.id), vv) == -1){
				tree.tree('uncheck', node.target);
			}
		});

		var ss = [];
		opts.unselectedValues = [];
		$.map(vv, function(v){
			var node = tree.tree('find', v);
			if (node){
				tree.tree('check', node.target).tree('select', node.target);
				// ss.push(node.text);
				ss.push(getText(node));
			} else {
				ss.push(findText(v, opts.mappingRows) || v);
				opts.unselectedValues.push(v);
			}
		});
		if (opts.multiple){
			$.map(tree.tree('getChecked'), function(node){
				var id = String(node.id);
				if ($.inArray(id, vv) == -1){
					vv.push(id);
					// ss.push(node.text);
					ss.push(getText(node));
				}
			});
		}

		topts.onBeforeCheck = onBeforeCheck;
		topts.onCheck = onCheck;
		topts.onBeforeSelect = onBeforeSelect;
		topts.onSelect = onSelect;

		if (!remainText){
			var s = ss.join(opts.separator);
			if ($(target).combo('getText') != s){
				$(target).combo('setText', s);
			}
		}
		$(target).combo('setValues', vv);
		
		function findText(value, a){
			var item = $.easyui.getArrayItem(a, 'id', value);
			// return item ? item.text : undefined;
			return item ? getText(item) : undefined;
		}
		function getText(node){
			return node[opts.textField||''] || node.text;
		}
	}

	function doQuery(target, q){
		var state = $.data(target, 'combotree');
		var opts = state.options;
		var tree = state.tree;
		state.remainText = true;
		tree.tree('doFilter', opts.multiple ? q.split(opts.separator) : q);
	}

	function doEnter(target){
		var state = $.data(target, 'combotree');
		state.remainText = false;
		$(target).combotree('setValues', $(target).combotree('getValues'));
		$(target).combotree('hidePanel');
	}
	
	$.fn.combotree = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.combotree.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.combo(options, param);
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'combotree');
			if (state){
				$.extend(state.options, options);
			} else {
				$.data(this, 'combotree', {
					options: $.extend({}, $.fn.combotree.defaults, $.fn.combotree.parseOptions(this), options)
				});
			}
			create(this);
		});
	};
	
	
	$.fn.combotree.methods = {
		options: function(jq){
			var copts = jq.combo('options');
			return $.extend($.data(jq[0], 'combotree').options, {
				width: copts.width,
				height: copts.height,
				originalValue: copts.originalValue,
				disabled: copts.disabled,
				readonly: copts.readonly,
				editable: copts.editable
			});
		},
		clone: function(jq, container){
			var t = jq.combo('clone', container);
			t.data('combotree', {
				options: $.extend(true, {}, jq.combotree('options')),
				tree: jq.combotree('tree')
			});
			return t;
		},
		tree: function(jq){
			return $.data(jq[0], 'combotree').tree;
		},
		loadData: function(jq, data){
			return jq.each(function(){
				var opts = $.data(this, 'combotree').options;
				opts.data = data;
				var tree = $.data(this, 'combotree').tree;
				tree.tree('loadData', data);
			});
		},
		reload: function(jq, url){
			return jq.each(function(){
				var opts = $.data(this, 'combotree').options;
				var tree = $.data(this, 'combotree').tree;
				if (url) opts.url = url;
				tree.tree({url:opts.url});
			});
		},
		setValues: function(jq, values){
			return jq.each(function(){
				var opts = $(this).combotree('options');
				if ($.isArray(values)){
					values = $.map(values, function(value){
						if (value && typeof value == 'object'){
							$.easyui.addArrayItem(opts.mappingRows, 'id', value);
							return value.id;
						} else {
							return value;
						}
					})
				}
				setValues(this, values);
			});
		},
		setValue: function(jq, value){
			return jq.each(function(){
				$(this).combotree('setValues', $.isArray(value)?value:[value]);
			});
		},
		clear: function(jq){
			return jq.each(function(){
				$(this).combotree('setValues', []);
			});
		},
		reset: function(jq){
			return jq.each(function(){
				var opts = $(this).combotree('options');
				if (opts.multiple){
					$(this).combotree('setValues', opts.originalValue);
				} else {
					$(this).combotree('setValue', opts.originalValue);
				}
			});
		}
	};
	
	$.fn.combotree.parseOptions = function(target){
		return $.extend({}, $.fn.combo.parseOptions(target), $.fn.tree.parseOptions(target));
	};
	
	$.fn.combotree.defaults = $.extend({}, $.fn.combo.defaults, $.fn.tree.defaults, {
		editable: false,
		textField: null,	// the text field to display.
		unselectedValues: [],
		mappingRows: [],
		keyHandler: {
			up: function(e){},
			down: function(e){},
			left: function(e){},
			right: function(e){},
			enter: function(e){doEnter(this)},
			query: function(q,e){doQuery(this, q)}
		}
	});
})(jQuery);
