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
 * combogrid - EasyUI for jQuery
 * 
 * Dependencies:
 *   combo
 *   datagrid
 * 
 */
(function($){
	/**
	 * create this component.
	 */
	function create(target){
		var state = $.data(target, 'combogrid');
		var opts = state.options;
		var grid = state.grid;
		
		$(target).addClass('combogrid-f').combo($.extend({}, opts, {
			onShowPanel: function(){
				setValues(this, $(this).combogrid('getValues'), true);
				var p = $(this).combogrid('panel');
				var distance = p.outerHeight() - p.height();
				var minHeight = p._size('minHeight');
				var maxHeight = p._size('maxHeight');
				var dg = $(this).combogrid('grid');
				dg.datagrid('resize', {
					width: '100%',
					height: (isNaN(parseInt(opts.panelHeight)) ? 'auto' : '100%'),
					minHeight: (minHeight ? minHeight-distance : ''),
					maxHeight: (maxHeight ? maxHeight-distance : '')
				});
				var row = dg.datagrid('getSelected');
				if (row){
					dg.datagrid('scrollTo', dg.datagrid('getRowIndex', row));
				}
				opts.onShowPanel.call(this);
			}
		}));
		var panel = $(target).combo('panel');
		if (!grid){
			grid = $('<table></table>').appendTo(panel);
			state.grid = grid;
		}
		grid.datagrid($.extend({}, opts, {
			border: false,
			singleSelect: (!opts.multiple),
			onLoadSuccess: onLoadSuccess,
			onClickRow: onClickRow,
			onSelect: handleEvent('onSelect'),
			onUnselect: handleEvent('onUnselect'),
			onSelectAll: handleEvent('onSelectAll'),
			onUnselectAll: handleEvent('onUnselectAll')
		}));

		function getComboTarget(dg){
			return $(dg).closest('.combo-panel').panel('options').comboTarget || target;
		}
		function onLoadSuccess(data){
			var comboTarget = getComboTarget(this);
			var state = $(comboTarget).data('combogrid');
			var opts = state.options;
			var values = $(comboTarget).combo('getValues');
			setValues(comboTarget, values, state.remainText);			
			opts.onLoadSuccess.call(this, data);
		}
		function onClickRow(index, row){
			var comboTarget = getComboTarget(this);
			var state = $(comboTarget).data('combogrid');
			var opts = state.options;
			state.remainText = false;
			retrieveValues.call(this);
			if (!opts.multiple){
				$(comboTarget).combo('hidePanel');
			}
			opts.onClickRow.call(this, index, row);
		}
		function handleEvent(event){
			return function(index, row){
				var comboTarget = getComboTarget(this);
				var opts = $(comboTarget).combogrid('options');
				if (event == 'onUnselectAll'){
					if (opts.multiple){
						retrieveValues.call(this);
					}
				} else {
					retrieveValues.call(this);					
				}
				opts[event].call(this, index, row);
			}
		}
		
		/**
		 * retrieve values from datagrid panel.
		 */
		function retrieveValues(){
			var dg = $(this);
			var comboTarget = getComboTarget(dg);
			var state = $(comboTarget).data('combogrid');
			var opts = state.options;
			var vv = $.map(dg.datagrid('getSelections'), function(row){
				return row[opts.idField];
			});
			vv = vv.concat(opts.unselectedValues);

			// don't scroll the datagrid when setting selected records
			var body2 = dg.data('datagrid').dc.body2;
			var scrollTop = body2.scrollTop();
			setValues(comboTarget, vv, state.remainText);
			body2.scrollTop(scrollTop);
		}
	}
	
	function nav(target, dir){
		var state = $.data(target, 'combogrid');
		var opts = state.options;
		var grid = state.grid;
		var rowCount = grid.datagrid('getRows').length;
		if (!rowCount){return}
		
		var tr = opts.finder.getTr(grid[0], null, 'highlight');
		if (!tr.length){
			tr = opts.finder.getTr(grid[0], null, 'selected');;
		}
		var index;
		if (!tr.length){
			index = (dir == 'next' ? 0 : rowCount-1);
		} else {
			var index = parseInt(tr.attr('datagrid-row-index'));
			index += (dir == 'next' ? 1 : -1);
			if (index < 0) {index = rowCount - 1}
			if (index >= rowCount) {index = 0}
		}
		
		grid.datagrid('highlightRow', index);
		if (opts.selectOnNavigation){
			state.remainText = false;
			grid.datagrid('selectRow', index);
		}
	}
	
	/**
	 * set combogrid values
	 */
	function setValues(target, values, remainText){
		var state = $.data(target, 'combogrid');
		var opts = state.options;
		var grid = state.grid;
		
		var oldValues = $(target).combo('getValues');
		var cOpts = $(target).combo('options');
		var onChange = cOpts.onChange;
		cOpts.onChange = function(){};	// prevent from triggering onChange event
		var gOpts = grid.datagrid('options');
		var onSelect = gOpts.onSelect;
		var onUnselect = gOpts.onUnselect;
		var onUnselectAll = gOpts.onUnselectAll;
		gOpts.onSelect = gOpts.onUnselect = gOpts.onUnselectAll = function(){};
		
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

		var selectedRows = $.grep(grid.datagrid('getSelections'), function(row, index){
			return $.inArray(String(row[opts.idField]), vv) >= 0;
		});
		grid.datagrid('clearSelections');
		grid.data('datagrid').selectedRows = selectedRows;

		var ss = [];
		opts.unselectedValues = [];
		$.map(vv, function(v){
			var index = grid.datagrid('getRowIndex', v);
			if (index >= 0){
				grid.datagrid('selectRow', index);
			} else {
				if ($.easyui.indexOfArray(selectedRows, opts.idField, v) == -1){
					opts.unselectedValues.push(v);
				}
			}
			ss.push(findText(v, grid.datagrid('getRows')) ||
					findText(v, selectedRows) ||
					findText(v, opts.mappingRows) ||
					v
			);
		});

		$(target).combo('setValues', oldValues);
		cOpts.onChange = onChange;	// restore to trigger onChange event
		gOpts.onSelect = onSelect;
		gOpts.onUnselect = onUnselect;
		gOpts.onUnselectAll = onUnselectAll;
		
		if (!remainText){
			var s = ss.join(opts.separator);
			if ($(target).combo('getText') != s){
				$(target).combo('setText', s);
			}
		}
		$(target).combo('setValues', values);
		
		function findText(value, a){
			var item = $.easyui.getArrayItem(a, opts.idField, value);
			return item ? item[opts.textField] : undefined;
		}
	}
	
	/**
	 * do the query action
	 */
	function doQuery(target, q){
		var state = $.data(target, 'combogrid');
		var opts = state.options;
		var grid = state.grid;
		state.remainText = true;

		var qq = opts.multiple ? q.split(opts.separator) : [q];
		qq = $.grep(qq, function(q){return $.trim(q)!='';});
		if (opts.mode == 'remote'){
			_setValues(qq);
			grid.datagrid('load', $.extend({}, opts.queryParams, {q:q}));
		} else {
			grid.datagrid('highlightRow', -1);
			var rows = grid.datagrid('getRows');
			var vv = [];
			$.map(qq, function(q){
				q = $.trim(q);
				var value = q;
				_addRowValue(opts.mappingRows, q);
				_addRowValue(grid.datagrid('getSelections'), q);
				var index = _addRowValue(rows, q);
				if (index >= 0){
					if (opts.reversed){
						grid.datagrid('highlightRow', index);
					}
				} else {
					$.map(rows, function(row, i){
						if (opts.filter.call(target, q, row)){
							grid.datagrid('highlightRow', i);
						}
					});
				}
			});
			_setValues(vv);
		}
		function _addRowValue(rows, q){
			for(var i=0; i<rows.length; i++){
				var row = rows[i];
				if ((row[opts.textField]||'').toLowerCase() == q.toLowerCase()){
					vv.push(row[opts.idField]);
					return i;
				}
			}
			return -1;
		}
		function _setValues(vv){
			if (!opts.reversed){
				setValues(target, vv, true);
			}
		}
	}
	
	function doEnter(target){
		var state = $.data(target, 'combogrid');
		var opts = state.options;
		var grid = state.grid;
		var tr = opts.finder.getTr(grid[0], null, 'highlight');
		state.remainText = false;
		if (tr.length){
			var index = parseInt(tr.attr('datagrid-row-index'));
			if (opts.multiple){
				if (tr.hasClass('datagrid-row-selected')){
					grid.datagrid('unselectRow', index);
				} else {
					grid.datagrid('selectRow', index);
				}
			} else {
				grid.datagrid('selectRow', index);
			}
		}
		var vv = [];
		$.map(grid.datagrid('getSelections'), function(row){
			vv.push(row[opts.idField]);
		});
		$.map(opts.unselectedValues, function(v){
			if ($.easyui.indexOfArray(opts.mappingRows, opts.idField, v) >= 0){
				$.easyui.addArrayItem(vv, v);
			}
		});
		$(target).combogrid('setValues', vv);
		if (!opts.multiple){
			$(target).combogrid('hidePanel');
		}
	}
	
	$.fn.combogrid = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.combogrid.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.combo(options, param);
//				return $.fn.combo.methods[options](this, param);
			}
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'combogrid');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'combogrid', {
					options: $.extend({}, $.fn.combogrid.defaults, $.fn.combogrid.parseOptions(this), options)
				});
			}
			
			create(this);
		});
	};
	
	$.fn.combogrid.methods = {
		options: function(jq){
			var copts = jq.combo('options');
			return $.extend($.data(jq[0], 'combogrid').options, {
				width: copts.width,
				height: copts.height,
				originalValue: copts.originalValue,
				disabled: copts.disabled,
				readonly: copts.readonly,
				editable: copts.editable
			});
		},
		cloneFrom: function(jq, from){
			return jq.each(function(){
				$(this).combo('cloneFrom', from);
				$.data(this, 'combogrid', {
					options: $.extend(true, {cloned:true}, $(from).combogrid('options')),
					combo: $(this).next(),
					panel: $(from).combo('panel'),
					grid: $(from).combogrid('grid')
				});
			});
		},
		// get the datagrid object.
		grid: function(jq){
			return $.data(jq[0], 'combogrid').grid;
		},
		setValues: function(jq, values){
			return jq.each(function(){
				var opts = $(this).combogrid('options');
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
				$(this).combogrid('setValues', $.isArray(value)?value:[value]);
			});
		},
		clear: function(jq){
			return jq.each(function(){
				$(this).combogrid('setValues', []);
			});
		},
		reset: function(jq){
			return jq.each(function(){
				var opts = $(this).combogrid('options');
				if (opts.multiple){
					$(this).combogrid('setValues', opts.originalValue);
				} else {
					$(this).combogrid('setValue', opts.originalValue);
				}
			});
		}
	};
	
	$.fn.combogrid.parseOptions = function(target){
		var t = $(target);
		return $.extend({}, $.fn.combo.parseOptions(target), $.fn.datagrid.parseOptions(target), 
				$.parser.parseOptions(target, ['idField','textField','mode']));
	};
	
	$.fn.combogrid.defaults = $.extend({}, $.fn.combo.defaults, $.fn.datagrid.defaults, {
		// height:22,
		loadMsg: null,
		idField: null,
		textField: null,	// the text field to display.
		unselectedValues: [],
		mappingRows: [],
		mode: 'local',	// or 'remote'
		
		keyHandler: {
			up: function(e){nav(this, 'prev');e.preventDefault()},
			down: function(e){nav(this, 'next');e.preventDefault()},
			left: function(e){},
			right: function(e){},
			enter: function(e){doEnter(this)},
			query: function(q,e){doQuery(this, q)}
		},
		inputEvents: $.extend({}, $.fn.combo.defaults.inputEvents, {
			blur: function(e){
				$.fn.combo.defaults.inputEvents.blur(e);
				var target = e.data.target;
				var opts = $(target).combogrid('options');
				if (opts.reversed){
					$(target).combogrid('setValues', $(target).combogrid('getValues'));
				}
			}
		}),
		panelEvents: {
			mousedown: function(e){
			}
		},
		filter: function(q, row){
			var opts = $(this).combogrid('options');
			return (row[opts.textField]||'').toLowerCase().indexOf(q.toLowerCase()) >= 0;
		}
	});
})(jQuery);
