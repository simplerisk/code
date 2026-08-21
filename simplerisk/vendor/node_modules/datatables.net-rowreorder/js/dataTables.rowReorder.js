/*! RowReorder 2.0.0 for DataTables
 * Copyright (c) SpryMedia Ltd - datatables.net/license
 */

(function(factory){
	if (typeof define === 'function' && define.amd) {
		// AMD
		define(['datatables.net'], function (dt) {
			return factory(window, document, dt);
		});
	}
	else if (typeof exports === 'object') {
		// CommonJS
		var cjsRequires = function (root) {
			if (! root.DataTable) {
				require('datatables.net')(root);
			}
		};

		if (typeof window === 'undefined') {
			module.exports = function (root) {
				if (! root) {
					// CommonJS environments without a window global must pass a
					// root. This will give an error otherwise
					root = window;
				}

				cjsRequires(root);
				return factory(root, root.document, root.DataTable);
			};
		}
		else {
			cjsRequires(window);
			module.exports = factory(window, window.document, window.DataTable);
		}
	}
	else {
		// Browser
		factory(window, document, window.DataTable);
	}
}(function(window, document, DataTable) {
'use strict';

var Dom = DataTable.Dom;
var Api = DataTable.Api;
var util = DataTable.util;

// Sanity check that we are using DataTables
if (!DataTable || !DataTable.versionCheck('3')) {
    throw 'DataTables RowReorder requires DataTables 3 or newer';
}
/**
 * RowReorder provides the ability in DataTables to click and drag rows to
 * reorder them. When a row is dropped the data for the rows effected will be
 * updated to reflect the change. Normally this data point should also be the
 * column being sorted upon in the DataTable but this does not need to be the
 * case. RowReorder implements a "data swap" method - so the rows being
 * reordered take the value of the data point from the row that used to occupy
 * the row's new position.
 *
 * Initialisation is done by either:
 *
 * * `rowReorder` parameter in the DataTable initialisation object
 * * `new DataTable.RowReorder( table, opts )` after DataTables
 *   initialisation.
 */
class RowReorder {
    /**
     * RowReorder constructor
     *
     * @param dt
     * @param opts
     * @returns
     */
    constructor(dt, opts = {}) {
        let defaults = DataTable.defaults.rowReorder;
        // User and defaults configuration object
        this.c = util.object.assign({}, util.is.plainObject(defaults) ? defaults : {}, RowReorder.defaults, opts);
        // Internal settings
        this.s = {
            bodyArea: {
                bottom: 0,
                left: 0,
                right: 0,
                top: 0
            },
            bodyTop: 0,
            dt: new DataTable.Api(dt),
            getDataFn: DataTable.util.get(this.c.dataSrc),
            middles: [],
            scroll: {
                windowHeight: 0,
                windowWidth: 0,
                dtTop: null,
                dtLeft: null,
                dtHeight: null,
                dtWidth: null
            },
            scrollInterval: null,
            setDataFn: DataTable.util.set(this.c.dataSrc),
            start: {
                top: 0,
                left: 0,
                offsetTop: 0,
                offsetLeft: 0,
                nodes: [],
                rowIndex: 0
            },
            windowHeight: 0,
            documentOuterHeight: 0,
            domCloneOuterHeight: 0,
            dropAllowed: true,
            lastInsert: 0
        };
        // DOM items
        this.dom = {
            clone: null,
            cloneParent: null,
            dtScroll: Dom
                .s(this.s.dt.table().container())
                .find('div.dt-scroll-body'),
            target: new Dom()
        };
        // Check if row reorder has already been initialised on this table
        var settings = this.s.dt.settings()[0];
        var existing = settings.rowreorder;
        if (existing) {
            return;
        }
        if (!this.dom.dtScroll.count()) {
            this.dom.dtScroll = Dom.s(this.s.dt.table().body());
        }
        settings.rowreorder = this;
        this._init();
    }
    /**
     * Initialise the RowReorder instance
     */
    _init() {
        var that = this;
        var dt = this.s.dt;
        var table = Dom.s(dt.table().node());
        // Need to be able to calculate the row positions relative to the table
        if (table.css('position') === 'static') {
            table.css('position', 'relative');
        }
        // listen for mouse down on the target column - we have to implement
        // this rather than using HTML5 drag and drop as drag and drop doesn't
        // appear to work on table rows at this time. Also mobile browsers are
        // not supported.
        // Use `table().container()` rather than just the table node for IE8 -
        // otherwise it only works once...
        Dom.s(dt.table().container()).on('mousedown.rowReorder touchstart.rowReorder', this.c.selector, function (e) {
            if (!that.c.enable) {
                return;
            }
            // Ignore excluded children of the selector
            if (Dom.s(e.target).filter(that.c.excludedChildren).count()) {
                return true;
            }
            var tr = Dom.s(this).closest('tr');
            var row = dt.row(tr);
            // Double check that it is a DataTable row
            if (row.any()) {
                that._emitEvent('pre-row-reorder', [
                    {
                        node: row.node(),
                        index: row.index()
                    }
                ]);
                that._mouseDown(e, tr);
                return false;
            }
        });
        dt.on('destroy.rowReorder', function () {
            Dom.s(dt.table().container()).off('.rowReorder');
            dt.off('.rowReorder');
        });
        this._keyup = this._keyup.bind(this);
    }
    /**
     * Cache the measurements that RowReorder needs in the mouse move handler
     * to attempt to speed things up, rather than reading from the DOM.
     */
    _cachePositions() {
        var dt = this.s.dt;
        // Frustratingly, if we add `position:relative` to the tbody, the
        // position is still relatively to the parent. So we need to adjust
        // for that
        var headerHeight = Dom
            .s(dt.table().node())
            .find('thead')
            .height('outer');
        // Need to pass the nodes through DOM to get them in document order,
        // not what DataTables thinks it is, since we have been altering the
        // order
        var nodes = Dom.s(dt.rows({ page: 'current' }).nodes().toArray());
        var middles = nodes.mapTo(node => {
            var top = Dom.s(node).position().top - headerHeight;
            return (top + top + Dom.s(node).height('outer')) / 2;
        });
        this.s.middles = middles;
        this.s.bodyTop = Dom.s(dt.table().body()).offset().top;
        this.s.windowHeight = Dom.w.height();
        this.s.documentOuterHeight = document.documentElement.offsetHeight;
        this.s.bodyArea = this._calcBodyArea();
    }
    /**
     * Clone a row so it can be floated around the screen
     *
     * @param target Node to be cloned
     */
    _clone(target) {
        var dt = this.s.dt;
        var clone = Dom
            .s(dt.table().node())
            .clone(false)
            .classAdd('dt-rowReorder-float')
            .append(Dom.c('tbody').append(target.clone(true)));
        // Match the table and column widths - read all sizes before setting
        // to reduce reflows
        var tableWidth = target.width('outer');
        var tableHeight = target.height('outer');
        var scrollBody = Dom.s(this.s.dt.table().node()).parent();
        var scrollWidth = scrollBody.width();
        var scrollLeft = scrollBody.scrollLeft();
        var sizes = target.children().mapTo(el => {
            return Dom.s(el).width();
        });
        clone
            .width(tableWidth)
            .height(tableHeight)
            .find('tr')
            .children()
            .each(function (el, i) {
            el.style.width = sizes[i] + 'px';
        });
        var cloneParent = Dom
            .c('div')
            .classAdd('dt-rowReorder-float-parent')
            .width(scrollWidth)
            .append(clone)
            .appendTo('body')
            .scrollLeft(scrollLeft);
        // Insert into the document to have it floating around
        this.dom.clone = clone;
        this.dom.cloneParent = cloneParent;
        this.s.domCloneOuterHeight = clone.height('outer');
    }
    /**
     * Update the cloned item's position in the document
     *
     * @param e Event giving the mouse's position
     */
    _clonePosition(e) {
        var start = this.s.start;
        var topDiff = this._eventToPage(e, 'Y') - start.top;
        var leftDiff = this._eventToPage(e, 'X') - start.left;
        var snap = this.c.snapX;
        var left;
        var top = topDiff + start.offsetTop;
        if (!this.dom.cloneParent) {
            return;
        }
        if (snap === true) {
            left = start.offsetLeft;
        }
        else if (typeof snap === 'number') {
            left = start.offsetLeft + snap;
        }
        else {
            left =
                leftDiff + start.offsetLeft + this.dom.cloneParent.scrollLeft();
        }
        if (top < 0) {
            top = 0;
        }
        else if (top + this.s.domCloneOuterHeight >
            this.s.documentOuterHeight) {
            top = this.s.documentOuterHeight - this.s.domCloneOuterHeight;
        }
        this.dom.cloneParent.css({
            top: top + 'px',
            left: left + 'px'
        });
    }
    /**
     * Emit an event on the DataTable for listeners
     *
     * @param name Event name
     * @param args Event arguments
     */
    _emitEvent(name, args) {
        return this.s.dt.trigger(name, args);
    }
    /**
     * Get pageX/Y position from an event, regardless of if it is a mouse or
     * touch event.
     *
     * @param e Event
     * @param pos X or Y (must be a capital)
     */
    _eventToPage(e, pos) {
        if (e.type.indexOf('touch') !== -1) {
            return e.touches[0]['page' + pos];
        }
        return e['page' + pos];
    }
    /**
     * Mouse down event handler. Read initial positions and add event handlers
     * for the move.
     *
     * @param e      Mouse event
     * @param target TR element that is to be moved
     */
    _mouseDown(e, target) {
        var that = this;
        var dt = this.s.dt;
        var start = this.s.start;
        var cancelable = this.c.cancelable;
        var offset = target.offset();
        start.top = this._eventToPage(e, 'Y');
        start.left = this._eventToPage(e, 'X');
        start.offsetTop = offset.top;
        start.offsetLeft = offset.left;
        start.nodes = Dom
            .s(dt.rows({ page: 'current' }).nodes().toArray())
            .get();
        this._cachePositions();
        this._clone(target);
        this._clonePosition(e);
        var bodyY = this._eventToPage(e, 'Y') - this.s.bodyTop;
        start.rowIndex = this._calcRowIndexByPos(bodyY);
        this.dom.target = target;
        target.classAdd('dt-rowReorder-moving');
        Dom.s(document)
            .on('mouseup.rowReorder touchend.rowReorder', function (e) {
            that._mouseUp(e);
        })
            .on('mousemove.rowReorder touchmove.rowReorder', function (e) {
            that._mouseMove(e);
        });
        // Check if window is x-scrolling - if not, disable it for the duration
        // of the drag
        if (window.innerWidth === document.body.clientWidth) {
            Dom.s(document.body).classAdd('dt-rowReorder-noOverflow');
        }
        // Cache scrolling information so mouse move doesn't need to read.
        // This assumes that the window and DT scroller will not change size
        // during an row drag, which I think is a fair assumption
        var scrollWrapper = this.dom.dtScroll;
        this.s.scroll = {
            windowHeight: Dom.w.height(),
            windowWidth: Dom.w.width(),
            dtTop: scrollWrapper.count() ? scrollWrapper.offset().top : null,
            dtLeft: scrollWrapper.count() ? scrollWrapper.offset().left : null,
            dtHeight: scrollWrapper.count()
                ? scrollWrapper.height('outer')
                : null,
            dtWidth: scrollWrapper.count() ? scrollWrapper.width('outer') : null
        };
        // Add keyup handler if dragging is cancelable
        if (cancelable) {
            Dom.s(document).on('keyup', this._keyup);
        }
    }
    /**
     * Mouse move event handler - move the cloned row and shuffle the table's
     * rows if required.
     *
     * @param e Mouse event
     */
    _mouseMove(e) {
        var _a;
        this._clonePosition(e);
        var start = this.s.start;
        var cancelable = this.c.cancelable;
        if (cancelable) {
            var bodyArea = this.s.bodyArea;
            var cloneArea = this._calcCloneParentArea();
            this.s.dropAllowed = this._rectanglesIntersect(bodyArea, cloneArea);
            (_a = this.dom.cloneParent) === null || _a === void 0 ? void 0 : _a.classToggle('drop-not-allowed', !this.s.dropAllowed);
        }
        // Transform the mouse position into a position in the table's body
        var bodyY = this._eventToPage(e, 'Y') - this.s.bodyTop;
        var middles = this.s.middles;
        var insertPoint = null;
        // Determine where the row should be inserted based on the mouse
        // position
        for (var i = 0, ien = middles.length; i < ien; i++) {
            if (bodyY < middles[i]) {
                insertPoint = i;
                break;
            }
        }
        if (insertPoint === null) {
            insertPoint = middles.length;
        }
        if (cancelable) {
            if (!this.s.dropAllowed) {
                // Move the row back to its original position because the drop
                // is not allowed
                insertPoint =
                    start.rowIndex > this.s.lastInsert
                        ? start.rowIndex + 1
                        : start.rowIndex;
            }
            this.dom.target.classToggle('dt-rowReorder-moving', this.s.dropAllowed);
        }
        this._moveTargetIntoPosition(insertPoint);
        this._shiftScroll(e);
    }
    /**
     * Mouse up event handler - release the event handlers and perform the
     * table updates
     *
     * @param e Mouse event
     */
    _mouseUp(e) {
        var that = this;
        var dt = this.s.dt;
        var i, ien;
        var dataSrc = this.c.dataSrc;
        var dropAllowed = this.s.dropAllowed;
        if (!dropAllowed) {
            that._cancel();
            return;
        }
        // Calculate the difference
        var startNodes = this.s.start.nodes;
        var endNodes = Dom
            .s(dt.rows({ page: 'current' }).nodes().toArray())
            .sort()
            .get();
        var idDiff = {};
        var fullDiff = [];
        var diffNodes = [];
        var getDataFn = this.s.getDataFn;
        var setDataFn = this.s.setDataFn;
        for (i = 0, ien = startNodes.length; i < ien; i++) {
            if (startNodes[i] !== endNodes[i]) {
                var id = dt.row(endNodes[i]).id();
                var endRowData = dt.row(endNodes[i]).data();
                var startRowData = dt.row(startNodes[i]).data();
                if (id) {
                    idDiff[id] = getDataFn(startRowData);
                }
                fullDiff.push({
                    node: endNodes[i],
                    oldData: getDataFn(endRowData),
                    newData: getDataFn(startRowData),
                    newPosition: i,
                    oldPosition: startNodes.indexOf(endNodes[i])
                });
                diffNodes.push(endNodes[i]);
            }
        }
        // Create event args
        var eventArgs = [
            fullDiff,
            {
                dataSrc: dataSrc,
                nodes: diffNodes,
                values: idDiff,
                triggerRow: dt.row(this.dom.target),
                originalEvent: e
            }
        ];
        // Emit event
        var eventResult = this._emitEvent('row-reorder', eventArgs);
        if (eventResult.includes(false)) {
            that._cancel();
            return;
        }
        // Remove cloned elements, handlers, etc
        this._cleanupDragging();
        var update = function () {
            if (that.c.update) {
                for (i = 0, ien = fullDiff.length; i < ien; i++) {
                    var row = dt.row(fullDiff[i].node);
                    var rowData = row.data();
                    setDataFn(rowData, fullDiff[i].newData);
                    // Invalidate the cell that has the same data source as the dataSrc
                    dt.columns().every(function () {
                        if (this.dataSrc() === dataSrc) {
                            dt.cell(fullDiff[i].node, this.index()).invalidate('data');
                        }
                    });
                }
                // Trigger row reordered event
                that._emitEvent('row-reordered', eventArgs);
                dt.draw(false);
            }
        };
        // Editor interface
        if (this.c.editor) {
            // Disable user interaction while Editor is submitting
            this.c.enable = false;
            this.c.editor
                .edit(diffNodes, false, util.object.assign({ submit: 'changed' }, this.c.formOptions))
                .multiSet(dataSrc, idDiff)
                .one('preSubmitCancelled.rowReorder', function () {
                that.c.enable = true;
                that.c.editor.off('.rowReorder');
                dt.draw(false);
            })
                .one('submitUnsuccessful.rowReorder', function () {
                dt.draw(false);
            })
                .one('submitSuccess.rowReorder', function () {
                update();
            })
                .one('submitComplete', function () {
                that.c.enable = true;
                that.c.editor.off('.rowReorder');
            })
                .submit();
        }
        else {
            update();
        }
    }
    /**
     * Moves the current target into the given position within the table
     * and caches the new positions
     *
     * @param insertPoint Position
     */
    _moveTargetIntoPosition(insertPoint) {
        var dt = this.s.dt;
        // Perform the DOM shuffle if it has changed from last time
        if (this.s.lastInsert === null || this.s.lastInsert !== insertPoint) {
            var nodes = Dom
                .s(dt.rows({ page: 'current' }).nodes().toArray())
                .get();
            var insertPlacement = '';
            if (insertPoint > this.s.lastInsert) {
                this.dom.target.insertAfter(nodes[insertPoint - 1]);
                insertPlacement = 'after';
            }
            else {
                this.dom.target.insertBefore(nodes[insertPoint]);
                insertPlacement = 'before';
            }
            this._cachePositions();
            this.s.lastInsert = insertPoint;
            this._emitEvent('row-reorder-changed', [
                {
                    insertPlacement,
                    insertPoint,
                    row: dt.row(this.dom.target)
                }
            ]);
        }
    }
    /**
     * Removes the cloned elements, event handlers, scrolling intervals, etc
     */
    _cleanupDragging() {
        var cancelable = this.c.cancelable;
        this.dom.clone.remove();
        this.dom.cloneParent.remove();
        this.dom.clone = null;
        this.dom.cloneParent = null;
        this.dom.target.classRemove('dt-rowReorder-moving');
        //this.dom.target = null;
        Dom.s(document).off('.rowReorder');
        Dom.s(document.body).classRemove('dt-rowReorder-noOverflow');
        clearInterval(this.s.scrollInterval);
        this.s.scrollInterval = null;
        if (cancelable) {
            Dom.s(document).off('keyup', this._keyup);
        }
    }
    /**
     * Move the window and DataTables scrolling during a drag to scroll new
     * content into view.
     *
     * This matches the `_shiftScroll` method used in AutoFill, but only
     * horizontal scrolling is considered here.
     *
     * @param e Mouse move event object
     */
    _shiftScroll(e) {
        var that = this;
        var scroll = this.s.scroll;
        var runInterval = false;
        var scrollSpeed = 5;
        var buffer = 65;
        var windowY = e.pageY - document.body.scrollTop, windowVert, dtVert;
        // Window calculations - based on the mouse position in the window,
        // regardless of scrolling
        if (windowY < Dom.w.scrollTop() + buffer) {
            windowVert = scrollSpeed * -1;
        }
        else if (windowY > scroll.windowHeight + Dom.w.scrollTop() - buffer) {
            windowVert = scrollSpeed;
        }
        // DataTables scrolling calculations - based on the table's position in
        // the document and the mouse position on the page
        if (scroll.dtTop !== null && e.pageY < scroll.dtTop + buffer) {
            dtVert = scrollSpeed * -1;
        }
        else if (scroll.dtTop !== null &&
            e.pageY > scroll.dtTop + scroll.dtHeight - buffer) {
            dtVert = scrollSpeed;
        }
        // This is where it gets interesting. We want to continue scrolling
        // without requiring a mouse move, so we need an interval to be
        // triggered. The interval should continue until it is no longer needed,
        // but it must also use the latest scroll commands (for example consider
        // that the mouse might move from scrolling up to scrolling left, all
        // with the same interval running. We use the `scroll` object to "pass"
        // this information to the interval. Can't use local variables as they
        // wouldn't be the ones that are used by an already existing interval!
        if (windowVert || dtVert) {
            scroll.windowVert = windowVert;
            scroll.dtVert = dtVert;
            runInterval = true;
        }
        else if (this.s.scrollInterval) {
            // Don't need to scroll - remove any existing timer
            clearInterval(this.s.scrollInterval);
            this.s.scrollInterval = null;
        }
        // If we need to run the interval to scroll and there is no existing
        // interval (if there is an existing one, it will continue to run)
        if (!this.s.scrollInterval && runInterval) {
            this.s.scrollInterval = setInterval(function () {
                // Don't need to worry about setting scroll <0 or beyond the
                // scroll bound as the browser will just reject that.
                if (scroll.windowVert) {
                    var top = Dom.w.scrollTop();
                    Dom.w.scrollTop(top + scroll.windowVert);
                    if (top !== Dom.w.scrollTop() && that.dom.cloneParent) {
                        var move = parseFloat(that.dom.cloneParent.css('top'));
                        that.dom.cloneParent.css('top', move + scroll.windowVert + 'px');
                    }
                }
                // DataTables scrolling
                if (scroll.dtVert) {
                    var scroller = that.dom.dtScroll.get(0);
                    if (scroll.dtVert) {
                        scroller.scrollTop += scroll.dtVert;
                    }
                }
            }, 20);
        }
    }
    /**
     * Calculates the current area of the table body and returns it as a rectangle
     */
    _calcBodyArea() {
        let dt = this.s.dt;
        let body = Dom.s(dt.table().body());
        let offset = body.offset();
        let area = {
            left: offset.left,
            top: offset.top,
            right: offset.left + body.width(),
            bottom: offset.top + body.height()
        };
        return area;
    }
    /**
     * Calculates the current area of the cloned parent element and returns it
     * as a rectangle
     */
    _calcCloneParentArea() {
        let cloneParent = this.dom.cloneParent;
        if (!cloneParent) {
            return {
                left: 0,
                top: 0,
                right: 0,
                bottom: 0
            };
        }
        let offset = cloneParent.offset();
        return {
            left: offset.left,
            top: offset.top,
            right: offset.left + cloneParent.width(),
            bottom: offset.top + cloneParent.height()
        };
    }
    /**
     * Returns whether the given rectangles intersect or not
     */
    _rectanglesIntersect(a, b) {
        var noOverlap = a.left >= b.right ||
            b.left >= a.right ||
            a.top >= b.bottom ||
            b.top >= a.bottom;
        return !noOverlap;
    }
    /**
     * Calculates the index of the row which lays under the given Y position or
     * returns -1 if no such row
     *
     * @param insertPoint Position
     */
    _calcRowIndexByPos(bodyY) {
        // Determine where the row is located based on the mouse position
        var dt = this.s.dt;
        var nodes = Dom.s(dt.rows({ page: 'current' }).nodes().toArray()).get();
        var rowIndex = -1;
        var headerHeight = Dom
            .s(dt.table().node())
            .find('thead')
            .height('outer');
        nodes.forEach((node, i) => {
            var top = Dom.s(node).position().top - headerHeight;
            var bottom = top + Dom.s(node).height('outer');
            if (bodyY >= top && bodyY <= bottom) {
                rowIndex = i;
            }
        });
        return rowIndex;
    }
    /**
     * Handles key up events and cancels the dragging if ESC key is pressed
     *
     * @param e Mouse move event object
     */
    _keyup(e) {
        var cancelable = this.c.cancelable;
        if (cancelable && e.which === 27) {
            // ESC key is up
            e.preventDefault();
            this._cancel();
        }
    }
    /**
     * Cancels the dragging, moves target back into its original position
     * and cleans up the dragging
     */
    _cancel() {
        var start = this.s.start;
        var insertPoint = start.rowIndex > this.s.lastInsert
            ? start.rowIndex + 1
            : start.rowIndex;
        this._moveTargetIntoPosition(insertPoint);
        this._cleanupDragging();
        // Emit event
        this._emitEvent('row-reorder-canceled', [this.s.start.rowIndex]);
    }
}
/** RowReorder default options */
RowReorder.defaults = {
    dataSrc: 0,
    editor: null,
    enable: true,
    formOptions: {},
    selector: 'td:first-child',
    snapX: false,
    update: true,
    excludedChildren: 'a',
    cancelable: false
};
RowReorder.version = '2.0.0';


// Doesn't do anything - work around for a bug in DT... Not documented
Api.register('rowReorder()', function () {
    return this;
});
Api.register('rowReorder.enable()', function (toggle) {
    if (toggle === undefined) {
        toggle = true;
    }
    return this.iterator('table', function (ctx) {
        if (ctx.rowreorder) {
            ctx.rowreorder.c.enable = toggle;
        }
    });
});
Api.register('rowReorder.disable()', function () {
    return this.iterator('table', function (ctx) {
        if (ctx.rowreorder) {
            ctx.rowreorder.c.enable = false;
        }
    });
});
DataTable.RowReorder = RowReorder;
// Attach a listener to the document which listens for DataTables initialisation
// events so we can automatically initialise
Dom.s(document).on('init.dt.dtr', function (e, settings, json) {
    if (e.namespace !== 'dt') {
        return;
    }
    var init = settings.init.rowReorder;
    var defaults = DataTable.defaults.rowReorder;
    if (init || defaults) {
        let opts = {};
        if (util.is.plainObject(defaults)) {
            util.object.assign(opts, defaults);
        }
        if (util.is.plainObject(init)) {
            util.object.assign(opts, init);
        }
        if (init !== false) {
            let dt = new DataTable.Api(settings);
            new RowReorder(dt, opts);
        }
    }
});


return DataTable;
}));
