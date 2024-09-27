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
 * combotreegrid - EasyUI for jQuery
 * 
 * Dependencies:
 *   combo
 *   treegrid
 * 
 */
(function($){
	function create(target){
		var state = $.data(target, 'combotreegrid');
		var opts = state.options;
		$(target).addClass('combotreegrid-f').combo($.extend({}, opts, {
			onShowPanel:function(){
				var p = $(this).combotreegrid('panel');
				var distance = p.outerHeight() - p.height();
				var minHeight = p._size('minHeight');
				var maxHeight = p._size('maxHeight');
				var dg = $(this).combotreegrid('grid');
				dg.treegrid('resize', {
					width: '100%',
					height: (isNaN(parseInt(opts.panelHeight)) ? 'auto' : '100%'),
					minHeight: (minHeight ? minHeight-distance : ''),
					maxHeight: (maxHeight ? maxHeight-distance : '')
				});
				var row = dg.treegrid('getSelected');
				if (row){
					dg.treegrid('scrollTo', row[opts.idField]);
				}
				opts.onShowPanel.call(this);
			}
		}));
		if (!state.grid){
			var panel = $(target).combo('panel');
			state.grid = $('<table></table>').appendTo(panel);
		}
		state.grid.treegrid($.extend({}, opts, {
			border: false,
			checkbox: opts.multiple,
			onLoadSuccess: function(row, data){
				var values = $(target).combotreegrid('getValues');
				if (opts.multiple){
					$.map($(this).treegrid('getCheckedNodes'), function(row){
						$.easyui.addArrayItem(values, row[opts.idField]);
					});
				}
				setValues(target, values);
				opts.onLoadSuccess.call(this, row, data);
				state.remainText = false;
			},
			onClickRow: function(row){
				if (opts.multiple){
					$(this).treegrid(row.checked?'uncheckNode':'checkNode', row[opts.idField]);
					$(this).treegrid('unselect', row[opts.idField]);
				} else {
					$(target).combo('hidePanel');
				}
				retrieveValues(target);
				opts.onClickRow.call(this, row);
			},
			onCheckNode: function(row, checked){
				retrieveValues(target);
				opts.onCheckNode.call(this, row, checked);
			}
		}));
	}

	function retrieveValues(target){
		var state = $.data(target, 'combotreegrid');
		var opts = state.options;
		var grid = state.grid;

		var vv = [];
		if (opts.multiple){
			vv = $.map(grid.treegrid('getCheckedNodes'), function(row){
				return row[opts.idField];
			});
		} else {
			var row = grid.treegrid('getSelected');
			if (row){
				vv.push(row[opts.idField]);
			}
		}
		vv = vv.concat(opts.unselectedValues);
		setValues(target, vv);
	}

	function setValues(target, values){
		var state = $.data(target, 'combotreegrid');
		var opts = state.options;
		var grid = state.grid;

		var topts = grid.datagrid('options');
		var onBeforeCheck = topts.onBeforeCheck;
		var onCheck = topts.onCheck;
		var onBeforeSelect = topts.onBeforeSelect;
		var onSelect = topts.onSelect;
		topts.onBeforeCheck = topts.onCheck = topts.onBeforeSelect = topts.onSelect = function(){};

		if (!$.isArray(values)){
			values = values.split(opts.separator);
		}
		if (!opts.multiple){
			values = values.length ? [values[0]] : [''];
		}		
		var vv = $.map(values, function(value){return String(value);});
		vv = $.grep(vv, function(v, index){
			return index === $.inArray(v, vv);
		});

		var selected = grid.treegrid('getSelected');
		if (selected){
			grid.treegrid('unselect', selected[opts.idField]);
		}
		$.map(grid.treegrid('getCheckedNodes'), function(row){
			if ($.inArray(String(row[opts.idField]), vv) == -1){
				grid.treegrid('uncheckNode', row[opts.idField]);
			}
		});

		var ss = [];
		opts.unselectedValues = [];
		$.map(vv, function(v){
			var row = grid.treegrid('find', v);
			if (row){
				if (opts.multiple){
					grid.treegrid('checkNode', v);
				} else {
					grid.treegrid('select', v);
				}
				ss.push(getText(row));
				// ss.push(row[opts.treeField]);
			} else {
				ss.push(findText(v, opts.mappingRows) || v);
				opts.unselectedValues.push(v);
			}
		});
		if (opts.multiple){
			$.map(grid.treegrid('getCheckedNodes'), function(row){
				var id = String(row[opts.idField]);
				if ($.inArray(id, vv) == -1){
					vv.push(id);
					ss.push(getText(row));
					// ss.push(row[opts.treeField]);
				}
			});
		}

		topts.onBeforeCheck = onBeforeCheck;
		topts.onCheck = onCheck;
		topts.onBeforeSelect = onBeforeSelect;
		topts.onSelect = onSelect;

		if (!state.remainText){
			var s = ss.join(opts.separator);
			if ($(target).combo('getText') != s){
				$(target).combo('setText', s);
			}
		}

		$(target).combo('setValues', vv);

		function findText(value, a){
			var item = $.easyui.getArrayItem(a, opts.idField, value);
			return item ? getText(item) : undefined;
			// return item ? item[opts.treeField] : undefined;
		}
		function getText(row){
			return row[opts.textField||''] || row[opts.treeField];
		}
	}

	function doQuery(target, q){
		var state = $.data(target, 'combotreegrid');
		var opts = state.options;
		var grid = state.grid;
		state.remainText = true;

		var qq = opts.multiple ? q.split(opts.separator) : [q];
		qq = $.grep(qq, function(q){return $.trim(q)!='';});
		grid.treegrid('clearSelections').treegrid('clearChecked').treegrid('highlightRow', -1);
		if (opts.mode == 'remote'){
			// $(target).combotreegrid('clear');
			_setValues(qq);
			grid.treegrid('load', $.extend({}, opts.queryParams, {q:q}));
		} else if (q){
			var data = grid.treegrid('getData');
			var vv = [];
			// var qq = opts.multiple ? q.split(opts.separator) : [q];
			$.map(qq, function(q){
				q = $.trim(q);
				if (q){
					var v = undefined;
					$.easyui.forEach(data, true, function(row){
						if (q.toLowerCase() == String(row[opts.treeField]).toLowerCase()){
							v = row[opts.idField];
							return false;
						} else if (opts.filter.call(target, q, row)){
							grid.treegrid('expandTo', row[opts.idField]);
							grid.treegrid('highlightRow', row[opts.idField]);
							return false;
						}
					});
					if (v == undefined){
						$.easyui.forEach(opts.mappingRows, false, function(row){
							if (q.toLowerCase() == String(row[opts.treeField])){
								v = row[opts.idField];
								return false;
							}
						})
					}
					if (v != undefined){
						vv.push(v);
					} else {
						vv.push(q);
					}
				}
			});
			// setValues(target, vv);
			_setValues(vv);
			state.remainText = false;
		}
		function _setValues(vv){
			if (!opts.reversed){
				$(target).combotreegrid('setValues', vv);
			}
		}
	}

	// function doEnter(target){
	// 	retrieveValues(target);
	// }
	function doEnter(target){
		var state = $.data(target, 'combotreegrid');
		var opts = state.options;
		var grid = state.grid;
		var tr = opts.finder.getTr(grid[0], null, 'highlight');
		state.remainText = false;
		if (tr.length){
			var id = tr.attr('node-id');
			if (opts.multiple){
				if (tr.hasClass('datagrid-row-selected')){
					grid.treegrid('uncheckNode', id);
				} else {
					grid.treegrid('checkNode', id);
				}
			} else {
				grid.treegrid('selectRow', id);
			}
		}
		var vv = [];
		if (opts.multiple){
			$.map(grid.treegrid('getCheckedNodes'), function(row){
				vv.push(row[opts.idField]);
			});
		} else {
			var row = grid.treegrid('getSelected');
			if (row){
				vv.push(row[opts.idField]);
			}
		}
		$.map(opts.unselectedValues, function(v){
			if ($.easyui.indexOfArray(opts.mappingRows, opts.idField, v) >= 0){
				$.easyui.addArrayItem(vv, v);
			}
		});
		$(target).combotreegrid('setValues', vv);
		if (!opts.multiple){
			$(target).combotreegrid('hidePanel');
		}
	}

	$.fn.combotreegrid = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.combotreegrid.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.combo(options, param);
			}
		}
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'combotreegrid');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'combotreegrid', {
					options: $.extend({}, $.fn.combotreegrid.defaults, $.fn.combotreegrid.parseOptions(this), options)
				});
			}
			create(this);
		});
	};

	$.fn.combotreegrid.methods = {
		options: function(jq){
			var copts = jq.combo('options');
			return $.extend($.data(jq[0], 'combotreegrid').options, {
				width: copts.width,
				height: copts.height,
				originalValue: copts.originalValue,
				disabled: copts.disabled,
				readonly: copts.readonly,
				editable: copts.editable
			});
		},
		grid: function(jq){
			return $.data(jq[0], 'combotreegrid').grid;
		},
		setValues: function(jq, values){
			return jq.each(function(){
				var opts = $(this).combotreegrid('options');
				if ($.isArray(values)){
					values = $.map(values, function(value){
						if (value && typeof value == 'object'){
							$.easyui.addArrayItem(opts.mappingRows, opts.idField, value);
							return value[opts.idField];
						} else {
							return value;
						}
					});
				}
				setValues(this, values);
			});
		},
		setValue: function(jq, value){
			return jq.each(function(){
				$(this).combotreegrid('setValues', $.isArray(value)?value:[value]);
			});
		},
		clear: function(jq){
			return jq.each(function(){
				$(this).combotreegrid('setValues', []);
			});
		},
		reset: function(jq){
			return jq.each(function(){
				var opts = $(this).combotreegrid('options');
				if (opts.multiple){
					$(this).combotreegrid('setValues', opts.originalValue);
				} else {
					$(this).combotreegrid('setValue', opts.originalValue);
				}
			});
		}
	};

	$.fn.combotreegrid.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.fn.combo.parseOptions(target), $.fn.treegrid.parseOptions(target), 
				$.parser.parseOptions(target, ['mode',{limitToGrid:'boolean'}]));
	};

	$.fn.combotreegrid.defaults = $.extend({}, $.fn.combo.defaults, $.fn.treegrid.defaults, {
		editable: false,
		singleSelect: true,
		limitToGrid: false,	// limit the inputed values to the listed grid rows
		unselectedValues: [],
		mappingRows: [],
		mode: 'local',	// or 'remote'
		textField: null,	// the text field to display.
		
		keyHandler: {
			up: function(e){},
			down: function(e){},
			left: function(e){},
			right: function(e){},
			enter: function(e){doEnter(this)},
			query: function(q,e){doQuery(this, q)}
		},
		inputEvents: $.extend({}, $.fn.combo.defaults.inputEvents, {
			blur: function(e){
				$.fn.combo.defaults.inputEvents.blur(e);
				var target = e.data.target;
				var opts = $(target).combotreegrid('options');
				if (opts.limitToGrid){
					doEnter(target);
				}
			}
		}),
		filter: function(q, row){
			var opts = $(this).combotreegrid('options');
			return (row[opts.treeField]||'').toLowerCase().indexOf(q.toLowerCase()) >= 0;
		}
	});
})(jQuery);
