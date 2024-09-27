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
 * pagination - EasyUI for jQuery
 * 
 * Dependencies:
 * 	linkbutton
 * 
 */
(function($){
	function buildToolbar(target){
		var state = $.data(target, 'pagination');
		var opts = state.options;
		var bb = state.bb = {};	// the buttons;
		
		if (opts.buttons && !$.isArray(opts.buttons)){
			$(opts.buttons).insertAfter(target);	// prevent the buttons from cleaning
		}
		var pager = $(target).addClass('pagination').html('<table cellspacing="0" cellpadding="0" border="0"><tr></tr></table>');
		var tr = pager.find('tr');
		
		var aa = $.extend([], opts.layout);
		if (!opts.showPageList){removeArrayItem(aa, 'list');}
		if (!opts.showPageInfo){removeArrayItem(aa, 'info');}
		if (!opts.showRefresh){removeArrayItem(aa, 'refresh');}
		if (aa[0] == 'sep'){aa.shift();}
		if (aa[aa.length-1] == 'sep'){aa.pop();}
		
		for(var index=0; index<aa.length; index++){
			var item = aa[index];
			if (item == 'list'){
				var ps = $('<select class="pagination-page-list"></select>');
				ps._bind('change', function(){
					opts.pageSize = parseInt($(this).val());
					opts.onChangePageSize.call(target, opts.pageSize);
					selectPage(target, opts.pageNumber);
				});
				for(var i=0; i<opts.pageList.length; i++) {
					$('<option></option>').text(opts.pageList[i]).appendTo(ps);
				}
				$('<td></td>').append(ps).appendTo(tr);
			} else if (item == 'sep'){
				$('<td><div class="pagination-btn-separator"></div></td>').appendTo(tr);
			} else if (item == 'first'){
				bb.first = createButton('first');
			} else if (item == 'prev'){
				bb.prev = createButton('prev');
			} else if (item == 'next'){
				bb.next = createButton('next');
			} else if (item == 'last'){
				bb.last = createButton('last');
			} else if (item == 'manual'){
				$('<span style="padding-left:6px;"></span>').html(opts.beforePageText).appendTo(tr).wrap('<td></td>');
				bb.num = $('<input class="pagination-num" type="text" value="1" size="2">').appendTo(tr).wrap('<td></td>');
				bb.num._unbind('.pagination')._bind('keydown.pagination', function(e){
					if (e.keyCode == 13){
						var pageNumber = parseInt($(this).val()) || 1;
						selectPage(target, pageNumber);
						return false;
					}
				});
				bb.after = $('<span style="padding-right:6px;"></span>').appendTo(tr).wrap('<td></td>');
			} else if (item == 'refresh'){
				bb.refresh = createButton('refresh');
			} else if (item == 'links'){
				$('<td class="pagination-links"></td>').appendTo(tr);
			} else if (item == 'info'){
				if (index == aa.length-1){
					$('<div class="pagination-info"></div>').appendTo(pager);
					//$('<div style="clear:both;"></div>').appendTo(pager);
				} else {
					$('<td><div class="pagination-info"></div></td>').appendTo(tr);
				}
			}
		}
		if (opts.buttons){
			$('<td><div class="pagination-btn-separator"></div></td>').appendTo(tr);
			if ($.isArray(opts.buttons)){
				for(var i=0; i<opts.buttons.length; i++){
					var btn = opts.buttons[i];
					if (btn == '-') {
						$('<td><div class="pagination-btn-separator"></div></td>').appendTo(tr);
					} else {
						var td = $('<td></td>').appendTo(tr);
						var a = $('<a href="javascript:;"></a>').appendTo(td);
						a[0].onclick = eval(btn.handler || function(){});
						a.linkbutton($.extend({}, btn, {
							plain:true
						}));
					}
				}
			} else {
				var td = $('<td></td>').appendTo(tr);
				$(opts.buttons).appendTo(td).show();
			}
		}
		$('<div style="clear:both;"></div>').appendTo(pager);
		
		function createButton(name){
			var btn = opts.nav[name];
			var a = $('<a href="javascript:;"></a>').appendTo(tr);
			a.wrap('<td></td>');
			a.linkbutton({
				iconCls: btn.iconCls,
				plain: true
			})._unbind('.pagination')._bind('click.pagination', function(){
				btn.handler.call(target);
			});
			return a;
		}
		function removeArrayItem(aa, item){
			var index = $.inArray(item, aa);
			if (index >= 0){
				aa.splice(index, 1);
			}
			return aa;
		}
	}
	
	function selectPage(target, page){
		var opts = $.data(target, 'pagination').options;
		if (opts.onBeforeSelectPage.call(target, page, opts.pageSize) == false){
			refreshData(target);
			return;
		}
		refreshData(target, {pageNumber:page});
		opts.onSelectPage.call(target, opts.pageNumber, opts.pageSize);
	}
	
	function refreshData(target, param){
		var state = $.data(target, 'pagination');
		var opts = state.options;
		var bb = state.bb;
		
		$.extend(opts, param||{});
		
		var ps = $(target).find('select.pagination-page-list');
		if (ps.length){
			ps.val(opts.pageSize+'');
			opts.pageSize = parseInt(ps.val());
		}
		
		var pageCount = Math.ceil(opts.total/opts.pageSize) || 1;
		if (opts.pageNumber < 1){opts.pageNumber = 1;}
		if (opts.pageNumber > pageCount){opts.pageNumber = pageCount}
		if (opts.total == 0){
			opts.pageNumber = 0;
			pageCount = 0;
		}
		
		if (bb.num) {bb.num.val(opts.pageNumber);}
		if (bb.after) {bb.after.html(opts.afterPageText.replace(/{pages}/, pageCount));}
		
		var td = $(target).find('td.pagination-links');
		if (td.length){
			td.empty();
			var listBegin = opts.pageNumber - Math.floor(opts.links/2);
			if (listBegin < 1) {listBegin = 1;}
			var listEnd = listBegin + opts.links - 1;
			if (listEnd > pageCount) {listEnd = pageCount;}
			listBegin = listEnd - opts.links + 1;
			if (listBegin < 1) {listBegin = 1;}
			for(var i=listBegin; i<=listEnd; i++){
				var a = $('<a class="pagination-link" href="javascript:;"></a>').appendTo(td);
				a.linkbutton({
					plain:true,
					text:i
				});
				if (i == opts.pageNumber){
					a.linkbutton('select');
				} else {
					a._unbind('.pagination')._bind('click.pagination', {pageNumber:i}, function(e){
						selectPage(target, e.data.pageNumber);
					});
				}
			}
		}
		
		var pinfo = opts.displayMsg;
		pinfo = pinfo.replace(/{from}/, opts.total==0 ? 0 : opts.pageSize*(opts.pageNumber-1)+1);
		pinfo = pinfo.replace(/{to}/, Math.min(opts.pageSize*(opts.pageNumber), opts.total));
		pinfo = pinfo.replace(/{total}/, opts.total);
		
		$(target).find('div.pagination-info').html(pinfo);
		
//		bb.first.add(bb.prev).linkbutton({disabled: (opts.pageNumber == 1)});
//		bb.next.add(bb.last).linkbutton({disabled: (opts.pageNumber == pageCount)});
		
		if (bb.first){bb.first.linkbutton({disabled: ((!opts.total) || opts.pageNumber == 1)})}
		if (bb.prev){bb.prev.linkbutton({disabled: ((!opts.total) || opts.pageNumber == 1)})}
		if (bb.next){bb.next.linkbutton({disabled: (opts.pageNumber == pageCount)})}
		if (bb.last){bb.last.linkbutton({disabled: (opts.pageNumber == pageCount)})}
		
		setLoadStatus(target, opts.loading);
	}
	
	function setLoadStatus(target, loading){
		var state = $.data(target, 'pagination');
		var opts = state.options;
		opts.loading = loading;
		if (opts.showRefresh && state.bb.refresh){
			state.bb.refresh.linkbutton({
				iconCls:(opts.loading ? 'pagination-loading' : 'pagination-load')
			});
		}
	}
	
	$.fn.pagination = function(options, param) {
		if (typeof options == 'string'){
			return $.fn.pagination.methods[options](this, param);
		}
		
		options = options || {};
		return this.each(function(){
			var opts;
			var state = $.data(this, 'pagination');
			if (state) {
				opts = $.extend(state.options, options);
			} else {
				opts = $.extend({}, $.fn.pagination.defaults, $.fn.pagination.parseOptions(this), options);
				$.data(this, 'pagination', {
					options: opts
				});
			}
			
			buildToolbar(this);
			refreshData(this);
			
		});
	};
	
	$.fn.pagination.methods = {
		options: function(jq){
			return $.data(jq[0], 'pagination').options;
		},
		loading: function(jq){
			return jq.each(function(){
				setLoadStatus(this, true);
			});
		},
		loaded: function(jq){
			return jq.each(function(){
				setLoadStatus(this, false);
			});
		},
		refresh: function(jq, options){
			return jq.each(function(){
				refreshData(this, options);
			});
		},
		select: function(jq, page){
			return jq.each(function(){
				selectPage(this, page);
			});
		}
	};
	
	$.fn.pagination.parseOptions = function(target){
		var t = $(target);
		return $.extend({},
				$.parser.parseOptions(target, [
					{total:'number',pageSize:'number',pageNumber:'number',links:'number'},
					{loading:'boolean',showPageList:'boolean',showPageInfo:'boolean',showRefresh:'boolean'}
				]), {
			pageList: (t.attr('pageList') ? eval(t.attr('pageList')) : undefined)
		});
	};
	
	$.fn.pagination.defaults = {
		total: 1,
		pageSize: 10,
		pageNumber: 1,
		pageList: [10,20,30,50],
		loading: false,
		buttons: null,
		showPageList: true,
		showPageInfo: true,
		showRefresh: true,
		links: 10,
		layout: ['list','sep','first','prev','sep','manual','sep','next','last','sep','refresh','info'],
		
		onBeforeSelectPage: function(pageNumber, pageSize){},
		onSelectPage: function(pageNumber, pageSize){},
		onBeforeRefresh: function(pageNumber, pageSize){},
		onRefresh: function(pageNumber, pageSize){},
		onChangePageSize: function(pageSize){},
		
		beforePageText: 'Page',
		afterPageText: 'of {pages}',
		displayMsg: 'Displaying {from} to {to} of {total} items',
		
		nav: {
			first: {
				iconCls: 'pagination-first',
				handler: function(){
					var opts = $(this).pagination('options');
					if (opts.pageNumber > 1){$(this).pagination('select', 1)}
				}
			},
			prev: {
				iconCls: 'pagination-prev',
				handler: function(){
					var opts = $(this).pagination('options');
					if (opts.pageNumber > 1){$(this).pagination('select', opts.pageNumber - 1)}
				}
			},
			next: {
				iconCls: 'pagination-next',
				handler: function(){
					var opts = $(this).pagination('options');
					var pageCount = Math.ceil(opts.total/opts.pageSize);
					if (opts.pageNumber < pageCount){$(this).pagination('select', opts.pageNumber + 1)}
				}
			},
			last: {
				iconCls: 'pagination-last',
				handler: function(){
					var opts = $(this).pagination('options');
					var pageCount = Math.ceil(opts.total/opts.pageSize);
					if (opts.pageNumber < pageCount){$(this).pagination('select', pageCount)}
				}
			},
			refresh: {
				iconCls: 'pagination-refresh',
				handler: function(){
					var opts = $(this).pagination('options');
					if (opts.onBeforeRefresh.call(this, opts.pageNumber, opts.pageSize) != false){
						$(this).pagination('select', opts.pageNumber);
						opts.onRefresh.call(this, opts.pageNumber, opts.pageSize);
					}
				}
			}
		}
	};
})(jQuery);
