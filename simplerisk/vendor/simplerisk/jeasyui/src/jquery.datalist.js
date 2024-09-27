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
 * datalist - EasyUI for jQuery
 * 
 * Dependencies:
 *   datagrid
 * 
 */
(function($){
	function create(target){
		var opts = $.data(target, 'datalist').options;
		$(target).datagrid($.extend({}, opts, {
			cls: 'datalist'+(opts.lines?' datalist-lines':''),
			frozenColumns: (opts.frozenColumns && opts.frozenColumns.length) ? opts.frozenColumns : (opts.checkbox ? [[{field:'_ck',checkbox:true}]] : undefined),
			columns: (opts.columns && opts.columns.length) ? opts.columns : [[
			     {field:opts.textField,width:'100%',
			     	formatter:function(value,row,index){
			     		return opts.textFormatter ? opts.textFormatter(value,row,index) : value;
			     	}
			     }
			]]
		}));
	}

    var listview = $.extend({}, $.fn.datagrid.defaults.view, {
		render: function(target, container, frozen){
			var state = $.data(target, 'datagrid');
			var opts = state.options;
			if (opts.groupField){
				var g = this.groupRows(target, state.data.rows);
				this.groups = g.groups;
				state.data.rows = g.rows;

				var table = [];
				for(var i=0; i<g.groups.length; i++){
					table.push(this.renderGroup.call(this, target, i, g.groups[i], frozen));
				}
				$(container).html(table.join(''));
			} else {
				$(container).html(this.renderTable(target, 0, state.data.rows, frozen));
			}
		},
		renderGroup: function(target, groupIndex, group, frozen){
			var state = $.data(target, 'datagrid');
			var opts = state.options;
			var fields = $(target).datagrid('getColumnFields', frozen);
			
			var table = [];
			table.push('<div class="datagrid-group" group-index=' + groupIndex + '>');
			if (!frozen){
				table.push('<span class="datagrid-group-title">');
				table.push(opts.groupFormatter.call(target, group.value, group.rows));
				table.push('</span>');
			}
			table.push('</div>');

			table.push(this.renderTable(target, group.startIndex, group.rows, frozen));
			return table.join('');
		},
		groupRows: function(target, rows){
			var state = $.data(target, 'datagrid');
			var opts = state.options;
			
			var groups = [];
			for(var i=0; i<rows.length; i++){
				var row = rows[i];
				var group = getGroup(row[opts.groupField]);
				if (!group){
					group = {
						value: row[opts.groupField],
						rows: [row]
					};
					groups.push(group);
				} else {
					group.rows.push(row);
				}
			}
			
			var index = 0;
			var rows = [];
			for(var i=0; i<groups.length; i++){
				var group = groups[i];
				group.startIndex = index;
				index += group.rows.length;
				rows = rows.concat(group.rows);
			}

			return {
				groups: groups,
				rows: rows
			};
			
			function getGroup(value){
				for(var i=0; i<groups.length; i++){
					var group = groups[i];
					if (group.value == value){
						return group;
					}
				}
				return null;
			}
		}
    });
	
	$.fn.datalist = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.datalist.methods[options];
			if (method){
				return method(this, param);
			} else {
				return this.datagrid(options, param);
			}
		}
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'datalist');
			if (state){
				$.extend(state.options, options);
			} else {
				var opts = $.extend({}, $.fn.datalist.defaults, $.fn.datalist.parseOptions(this), options);
				opts.columns = $.extend(true, [], opts.columns);
				state = $.data(this, 'datalist', {
					options: opts
				});
			}
			create(this);
			if (!state.options.data){
				var data = $.fn.datalist.parseData(this);
				if (data.total){
					$(this).datalist('loadData', data);
				}
			}
		});
	}
	
	$.fn.datalist.methods = {
		options: function(jq){
			return $.data(jq[0], 'datalist').options;
		}
	}
	
	$.fn.datalist.parseOptions = function(target){
		return $.extend({}, $.fn.datagrid.parseOptions(target), $.parser.parseOptions(target, [
		    'valueField','textField','groupField',{checkbox:'boolean',lines:'boolean'}
		]));
	}

	$.fn.datalist.parseData = function(target){
		var opts = $.data(target, 'datalist').options;
		var data = {
			total:0,
			rows:[]
		};
		$(target).children().each(function(){
			var itemOpts = $.parser.parseOptions(this, ['value','group']);
			var row = {};
			var html = $(this).html();
			row[opts.valueField] = itemOpts.value != undefined ? itemOpts.value : html;
			row[opts.textField] = html;
			if (opts.groupField){
				row[opts.groupField] = itemOpts.group;				
			}
			data.total ++;
			data.rows.push(row);
		});
		return data;
	};
	
	$.fn.datalist.defaults = $.extend({}, $.fn.datagrid.defaults, {
		fitColumns: true,
		singleSelect: true,
		showHeader: false,
		checkbox: false,
		lines: false,
		valueField: 'value',
		textField: 'text',
		groupField: '',
		view: listview,
		textFormatter: function(value,row){return value},
		groupFormatter: function(fvalue,rows){return fvalue}
	});
})(jQuery);
