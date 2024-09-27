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
 * tooltip - EasyUI for jQuery
 * 
 */
(function($){
	function init(target){
		$(target).addClass('tooltip-f');
	}
	
	function bindEvents(target){
		var opts = $.data(target, 'tooltip').options;
		$(target)._unbind('.tooltip')._bind(opts.showEvent+'.tooltip', function(e){
//			showTip(target, e);
			$(target).tooltip('show', e);
		})._bind(opts.hideEvent+'.tooltip', function(e){
//			hideTip(target, e);
			$(target).tooltip('hide', e);
		})._bind('mousemove.tooltip', function(e){
			if (opts.trackMouse){
				opts.trackMouseX = e.pageX;
				opts.trackMouseY = e.pageY;
				$(target).tooltip('reposition');
			}
		});
	}
	
	function clearTimeouts(target){
		var state = $.data(target, 'tooltip');
		if (state.showTimer){
			clearTimeout(state.showTimer);
			state.showTimer = null;
		}
		if (state.hideTimer){
			clearTimeout(state.hideTimer);
			state.hideTimer = null;
		}
	}
	
	function reposition(target){
		var state = $.data(target, 'tooltip');
		if (!state || !state.tip){return}
		var opts = state.options;
		var tip = state.tip;
		var pos = {left:-100000,top:-100000};
		
		if ($(target).is(':visible')){
			pos = getPosition(opts.position);
			if (opts.position == 'top' && pos.top < 0){
				pos = getPosition('bottom');
			} else if ((opts.position == 'bottom') && (pos.top + tip._outerHeight() > $(window)._outerHeight() + $(document).scrollTop())){
				pos = getPosition('top');
			}
			if (pos.left < 0){
				if (opts.position == 'left'){
					pos = getPosition('right');
				} else {
					$(target).tooltip('arrow').css('left', tip._outerWidth()/2 + pos.left);
					pos.left = 0;
				}
			} else if (pos.left + tip._outerWidth() > $(window)._outerWidth() + $(document)._scrollLeft()){
				if (opts.position == 'right'){
					pos = getPosition('left');
				} else {
					var left = pos.left;
					pos.left = $(window)._outerWidth() + $(document)._scrollLeft() - tip._outerWidth();
					$(target).tooltip('arrow').css('left', tip._outerWidth()/2 - (pos.left-left));
				}
			}
		}
		
		tip.css({
			left: pos.left,
			top: pos.top,
			zIndex: (opts.zIndex!=undefined ? opts.zIndex : ($.fn.window ? $.fn.window.defaults.zIndex++ : ''))
		});
		opts.onPosition.call(target, pos.left, pos.top);
		
		function getPosition(position){
			opts.position = position || 'bottom';
			tip.removeClass('tooltip-top tooltip-bottom tooltip-left tooltip-right').addClass('tooltip-'+opts.position);
			var left,top;
			var deltaX = $.isFunction(opts.deltaX) ? opts.deltaX.call(target, opts.position) : opts.deltaX;
			var deltaY = $.isFunction(opts.deltaY) ? opts.deltaY.call(target, opts.position) : opts.deltaY;
			if (opts.trackMouse){
				t = $();
				left = opts.trackMouseX + deltaX;
				top = opts.trackMouseY + deltaY;
			} else {
				var t = $(target);
				left = t.offset().left + deltaX;
				top = t.offset().top + deltaY;
			}
			switch(opts.position){
			case 'right':
				left += t._outerWidth() + 12 + (opts.trackMouse?12:0);
				if (opts.valign == 'middle'){
					top -= (tip._outerHeight() - t._outerHeight()) / 2;
				}
				break;
			case 'left':
				left -= tip._outerWidth() + 12 + (opts.trackMouse?12:0);
				if (opts.valign == 'middle'){
					top -= (tip._outerHeight() - t._outerHeight()) / 2;
				}
				break;
			case 'top':
				left -= (tip._outerWidth() - t._outerWidth()) / 2;
				top -= tip._outerHeight() + 12 + (opts.trackMouse?12:0);
				break;
			case 'bottom':
				left -= (tip._outerWidth() - t._outerWidth()) / 2;
				top += t._outerHeight() + 12 + (opts.trackMouse?12:0);
				break;
			}
			return {
				left: left,
				top: top
			}
		}
	}

	function showTip(target, e){
		var state = $.data(target, 'tooltip');
		var opts = state.options;
		var tip = state.tip;
		if (!tip){
			tip = $(
				'<div tabindex="-1" class="tooltip">' +
					'<div class="tooltip-content"></div>' +
					'<div class="tooltip-arrow-outer"></div>' +
					'<div class="tooltip-arrow"></div>' +
				'</div>'
			).appendTo('body');
			state.tip = tip;
			updateTip(target);
		}
//		tip.removeClass('tooltip-top tooltip-bottom tooltip-left tooltip-right').addClass('tooltip-'+opts.position);
		
		clearTimeouts(target);
		
		state.showTimer = setTimeout(function(){
			$(target).tooltip('reposition');
			tip.show();
			opts.onShow.call(target, e);
			
			var arrowOuter = tip.children('.tooltip-arrow-outer');
			var arrow = tip.children('.tooltip-arrow');
			var bc = 'border-'+opts.position+'-color';
			arrowOuter.add(arrow).css({
				borderTopColor:'',
				borderBottomColor:'',
				borderLeftColor:'',
				borderRightColor:''
			});
			arrowOuter.css(bc, tip.css(bc));
			arrow.css(bc, tip.css('backgroundColor'));
		}, opts.showDelay);
	}
	
	function hideTip(target, e){
		var state = $.data(target, 'tooltip');
		if (state && state.tip){
			clearTimeouts(target);
			state.hideTimer = setTimeout(function(){
				state.tip.hide();
				state.options.onHide.call(target, e);
			}, state.options.hideDelay);
		}
	}
	
	function updateTip(target, content){
		var state = $.data(target, 'tooltip');
		var opts = state.options;
		if (content){opts.content = content;}
		if (!state.tip){return;}
		
		var cc = typeof opts.content == 'function' ? opts.content.call(target) : opts.content;
		state.tip.children('.tooltip-content').html(cc);
		opts.onUpdate.call(target, cc);
	}
	
	function destroyTip(target){
		var state = $.data(target, 'tooltip');
		if (state){
			clearTimeouts(target);
			var opts = state.options;
			if (state.tip){state.tip.remove();}
			if (opts._title){
				$(target).attr('title', opts._title);
			}
			$.removeData(target, 'tooltip');
			$(target)._unbind('.tooltip').removeClass('tooltip-f');
			opts.onDestroy.call(target);
		}
	}
	
	$.fn.tooltip = function(options, param){
		if (typeof options == 'string'){
			return $.fn.tooltip.methods[options](this, param);
		}
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'tooltip');
			if (state){
				$.extend(state.options, options);
			} else {
				$.data(this, 'tooltip', {
					options: $.extend({}, $.fn.tooltip.defaults, $.fn.tooltip.parseOptions(this), options)
				});
				init(this);
			}
			bindEvents(this);
			updateTip(this);
		});
	};
	
	$.fn.tooltip.methods = {
		options: function(jq){
			return $.data(jq[0], 'tooltip').options;
		},
		tip: function(jq){
			return $.data(jq[0], 'tooltip').tip;
		},
		arrow: function(jq){
			return jq.tooltip('tip').children('.tooltip-arrow-outer,.tooltip-arrow');
		},
		show: function(jq, e){
			return jq.each(function(){
				showTip(this, e);
			});
		},
		hide: function(jq, e){
			return jq.each(function(){
				hideTip(this, e);
			});
		},
		update: function(jq, content){
			return jq.each(function(){
				updateTip(this, content);
			});
		},
		reposition: function(jq){
			return jq.each(function(){
				reposition(this);
			});
		},
		destroy: function(jq){
			return jq.each(function(){
				destroyTip(this);
			});
		}
	};
	
	$.fn.tooltip.parseOptions = function(target){
		var t = $(target);
		var opts = $.extend({}, $.parser.parseOptions(target, [
			'position','showEvent','hideEvent','content',
			{trackMouse:'boolean',deltaX:'number',deltaY:'number',showDelay:'number',hideDelay:'number'}
		]), {
			_title: t.attr('title')
		});
		t.attr('title', '');
		if (!opts.content){
			opts.content = opts._title;
		}
		return opts;
	};
	
	$.fn.tooltip.defaults = {
		position: 'bottom',	// possible values are: 'left','right','top','bottom'
		valign: 'middle',	// possible values are: 'middle','top'
		content: null,
		trackMouse: false,
		deltaX: 0,
		deltaY: 0,
		showEvent: 'mouseenter',
		hideEvent: 'mouseleave',
		showDelay: 200,
		hideDelay: 100,
		
		onShow: function(e){},
		onHide: function(e){},
		onUpdate: function(content){},
		onPosition: function(left,top){},
		onDestroy: function(){}
	};
})(jQuery);
