import DataTables, { Context, Api, Dom } from 'datatables.net';
export { default } from 'datatables.net';

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
declare class RowReorder {
    /** RowReorder default options */
    static defaults: Defaults;
    static version: string;
    private c;
    private s;
    private dom;
    /**
     * RowReorder constructor
     *
     * @param dt
     * @param opts
     * @returns
     */
    constructor(dt: Context | Api, opts?: Partial<Defaults>);
    /**
     * Initialise the RowReorder instance
     */
    private _init;
    /**
     * Cache the measurements that RowReorder needs in the mouse move handler
     * to attempt to speed things up, rather than reading from the DOM.
     */
    private _cachePositions;
    /**
     * Clone a row so it can be floated around the screen
     *
     * @param target Node to be cloned
     */
    private _clone;
    /**
     * Update the cloned item's position in the document
     *
     * @param e Event giving the mouse's position
     */
    private _clonePosition;
    /**
     * Emit an event on the DataTable for listeners
     *
     * @param name Event name
     * @param args Event arguments
     */
    private _emitEvent;
    /**
     * Get pageX/Y position from an event, regardless of if it is a mouse or
     * touch event.
     *
     * @param e Event
     * @param pos X or Y (must be a capital)
     */
    private _eventToPage;
    /**
     * Mouse down event handler. Read initial positions and add event handlers
     * for the move.
     *
     * @param e      Mouse event
     * @param target TR element that is to be moved
     */
    private _mouseDown;
    /**
     * Mouse move event handler - move the cloned row and shuffle the table's
     * rows if required.
     *
     * @param e Mouse event
     */
    private _mouseMove;
    /**
     * Mouse up event handler - release the event handlers and perform the
     * table updates
     *
     * @param e Mouse event
     */
    private _mouseUp;
    /**
     * Moves the current target into the given position within the table
     * and caches the new positions
     *
     * @param insertPoint Position
     */
    private _moveTargetIntoPosition;
    /**
     * Removes the cloned elements, event handlers, scrolling intervals, etc
     */
    private _cleanupDragging;
    /**
     * Move the window and DataTables scrolling during a drag to scroll new
     * content into view.
     *
     * This matches the `_shiftScroll` method used in AutoFill, but only
     * horizontal scrolling is considered here.
     *
     * @param e Mouse move event object
     */
    private _shiftScroll;
    /**
     * Calculates the current area of the table body and returns it as a rectangle
     */
    private _calcBodyArea;
    /**
     * Calculates the current area of the cloned parent element and returns it
     * as a rectangle
     */
    private _calcCloneParentArea;
    /**
     * Returns whether the given rectangles intersect or not
     */
    private _rectanglesIntersect;
    /**
     * Calculates the index of the row which lays under the given Y position or
     * returns -1 if no such row
     *
     * @param insertPoint Position
     */
    private _calcRowIndexByPos;
    /**
     * Handles key up events and cancels the dragging if ESC key is pressed
     *
     * @param e Mouse move event object
     */
    private _keyup;
    /**
     * Cancels the dragging, moves target back into its original position
     * and cleans up the dragging
     */
    private _cancel;
}

declare module 'datatables.net' {
    interface Config {
        /**
         * RowReorder extension options
         */
        rowReorder?: boolean | ConfigRowReorder;
    }
    interface Defaults {
        /**
         * RowReorder extension options
         */
        rowReorder?: boolean | ConfigRowReorder;
    }
    interface Api<T> {
        /**
         * RowReorder methods container
         *
         * @returns Api for chaining with the additional RowReorder methods
         */
        rowReorder: ApiRowReorderMethods<T>;
    }
    interface Context {
        rowreorder: RowReorder;
    }
    interface DataTablesStatic {
        /**
         * RowReorder class
         */
        RowReorder: typeof RowReorder;
    }
}
interface ConfigRowReorder {
    /**
     * Configure the data point that will be used for the reordering data
     */
    dataSrc?: string;
    /**
     * Attach an Editor instance for database updating
     */
    editor?: any;
    /**
     * Enable / disable RowReorder's user interaction
     */
    enable?: boolean;
    /**
     * Set the options for the Editor form when submitting data
     */
    formOptions?: any;
    /**
     * Define the selector used to pick the elements that will start a drag
     */
    selector?: string;
    /**
     * Horizontal position control of the row being dragged
     */
    snapX?: number | boolean;
    /**
     * Control automatic of data when a row is dropped
     */
    update?: boolean;
}
interface ApiRowReorderMethods<T> extends Api<T> {
    /**
     * Disable the end user's ability to click and drag to reorder rows.
     *
     * @returns DataTables API instance
     */
    disable(): Api<T>;
    /**
     * Enable, or optionally disable, the end user's ability to click and drag to reorder rows.
     *
     * @param enable that can be used to indicate if row reordering should be enabled or disabled.
     * @returns DataTables API instance
     */
    enable(enable?: boolean): Api<T>;
}
interface Defaults {
    /**
     * Data point in the host row's data source object for where to get and
     * set the data to reorder. This will normally also be the sorting
     * column.
     */
    dataSrc: number;
    /**
     * Editor instance that will be used to perform the update
     */
    editor: any;
    /**
     * Enable / disable RowReorder's user interaction
     */
    enable: boolean;
    /**
     * Form options to pass to Editor when submitting a change in the row
     * order. See the Editor `from-options` object for details of the
     * options available.
     */
    formOptions: any;
    /**
     * Drag handle selector. This defines the element that when dragged will
     * reorder a row.
     */
    selector: string;
    /**
     * Optionally lock the dragged row's x-position. This can be `true` to
     * fix the position match the host table's, `false` to allow free
     * movement of the row, or a number to define an offset from the host
     * table.
     */
    snapX: boolean | number;
    /**
     * Update the table's data on drop
     */
    update: boolean;
    /**
     * Selector for children of the drag handle selector that mouseDown
     * events will be passed through to and drag will not activate
     */
    excludedChildren: 'a';
    /**
     * Enable / disable the canceling of the drag & drop interaction
     */
    cancelable: boolean;
}
interface Settings {
    bodyArea: {
        bottom: number;
        left: number;
        right: number;
        top: number;
    };
    /** Scroll body top cache */
    bodyTop: number;
    /** DataTables' API instance */
    dt: Api;
    /** Data fetch function */
    getDataFn: Function;
    /** Pixel positions for row insertion calculation */
    middles: number[];
    /** Cached dimension information for use in the mouse move event handler */
    scroll: {
        windowHeight: number;
        windowWidth: number;
        dtTop: number | null;
        dtLeft: number | null;
        dtHeight: number | null;
        dtWidth: number | null;
        windowVert?: number;
        dtVert?: number;
    };
    /** Interval object used for smooth scrolling */
    scrollInterval: any;
    /** Data set function */
    setDataFn: Function;
    /** Mouse down information */
    start: {
        top: number;
        left: number;
        offsetTop: number;
        offsetLeft: number;
        nodes: HTMLElement[];
        rowIndex: number;
    };
    /** Window height cached value */
    windowHeight: number;
    /** Document outer height cached value */
    documentOuterHeight: number;
    /** DOM clone outer height cached value */
    domCloneOuterHeight: number;
    /** Flag used for signing if the drop is enabled or not */
    dropAllowed: boolean;
    /** Position of the drop */
    lastInsert: number;
}
interface InternalDom {
    clone: Dom | null;
    cloneParent: Dom | null;
    dtScroll: Dom;
    target: Dom;
}
interface Diff {
    node: HTMLElement;
    oldData: any;
    newData: any;
    newPosition: number;
    oldPosition: number;
}
interface Area {
    left: number;
    top: number;
    right: number;
    bottom: number;
}

export type { Area, Defaults, Diff, InternalDom, Settings };
