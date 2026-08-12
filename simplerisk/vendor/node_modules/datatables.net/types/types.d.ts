/*! DataTables 3.0.1
 * Copyright (c) SpryMedia Ltd - datatables.net/license
 */

type Primitive = bigint | boolean | null | number | string | symbol | undefined;
type PlainObject = Record<string, Primitive>;
type GetFunction = (data: any, type?: string, row?: any, meta?: any) => any;
type SetFunction = (data: any, type?: string, row?: any, meta?: any) => any;
type JSONValue = Primitive | JSON$1 | JSONArray;
interface JSONArray extends Array<JSONValue> {
}
interface JSON$1 {
    [key: string]: JSONValue;
}

type EventHandler = {
    (this: HTMLElement, event: any, ...args: any): any;
    guid?: number;
};

type AttributeTypes = string | number | boolean | null;
type DomSelector$1 = string | Node | Element | HTMLElement | Document | Array<DomSelector$1> | null | JQuery | Dom;
type TDimensionInclude = 'outer' | 'inner' | 'content' | 'withBorder' | 'withPadding' | 'withMargin';
declare function create$3<R extends HTMLElement = HTMLElement>(name: string): Dom<R>;
declare function select<R extends HTMLElement = HTMLElement>(selector: DomSelector$1): Dom<R>;
/**
 * `Dom` is a class that provides a chaining UI for simple DOM manipulation and
 * selection.
 */
declare class Dom<T extends HTMLElement = HTMLElement> implements ArrayLike<T> {
    /**
     * Create a new element and wrap in a `Dom` instance (alias of `create`)
     *
     * @param name Element name to create
     * @returns Dom instance for manipulating the new element
     */
    static c: typeof create$3;
    /**
     * Create a new element and wrap in a `Dom` instance (alias of `c`)
     *
     * @param name Element name to create
     * @returns Dom instance for manipulating the new element
     */
    static create: typeof create$3;
    /**
     * Select items from the document and wrap in a `Dom` instance (alias of
     * `select`)
     *
     * @param selector Items to select
     * @returns Dom instance for manipulating the selected items
     */
    static s: typeof select;
    /**
     * Select items from the document and wrap in a `Dom` instance (alias of
     * `s`)
     *
     * @param selector Items to select
     * @returns Dom instance for manipulating the selected items
     */
    static select: typeof select;
    /**
     * Flag to indicate if transitions (animations) should be allowed. Set to
     * false to disable and have it jump to the end.
     */
    static transitions: boolean;
    /**
     * Window object methods
     */
    static w: {
        height(): number;
        off(name: string, cb?: EventHandler | null): void;
        on(name: string, cb: EventHandler): void;
        one(name: string, cb: EventHandler): void;
        scrollLeft(set?: number): number;
        scrollTop(set?: number): number;
        width(): number;
    };
    /** Index access for the elements in the result set of this instance */
    [n: number]: T;
    /** Number of elements in the array */
    length: number;
    /** Flag to indicate that this is a Dom instance */
    private _isDom;
    /**
     * `Dom` is used for selection and manipulation of the DOM elements in a
     * chaining interface.
     *
     * @param selector
     */
    constructor(selector?: DomSelector$1);
    /**
     * Add an element (or multiple) to the instance. Will ensure uniqueness.
     *
     * @param selector Element(s) to add
     * @param sort Indicate if the element should be added in document order.
     * @returns Self for chaining
     */
    add(selector: DomSelector$1, sort?: boolean): this;
    /**
     * Insert the given content to each item in the result set.
     *
     * Limit your result set to a single item!
     *
     * @param content The content to append
     * @returns Self for chaining
     */
    append<T extends Node>(content: T | Dom | string | null | Array<T | Dom | string | null>): this;
    /**
     * Append the current data set items to the element from the selector
     *
     * @param selector
     */
    appendTo(selector: DomSelector$1 | Dom): this;
    /**
     * Get an attribute's value from the first item in the result set. Can be
     * null.
     *
     * @param name Attribute name
     * @returns Read value
     */
    attr(name: string): string;
    /**
     * Set an attribute's value for all items in the result set.
     *
     * @param name Attribute name
     * @param value Value to give the attribute
     * @returns Self for chaining
     */
    attr(name: string, value: AttributeTypes): this;
    /**
     * Set multiple attributes of the elements in the result set
     *
     * @param attributes Plain object of attributes to be assigned
     */
    attr(attributes: Record<string, AttributeTypes>): this;
    /**
     * Remove an attribute on each element in the result set
     *
     * @param attr Attribute to remove
     * @returns Self for chaining
     */
    attrRemove(attr: string): this;
    /**
     * Blur on the target elements
     *
     * @returns Self for chaining
     */
    blur(): this;
    /**
     * Get the child from all elements in the result set
     *
     * @param selector Query string that the child much match to be selected
     * @returns New Dom instance with children as the result set
     */
    children<R extends HTMLElement = HTMLElement>(selector?: string): Dom<R>;
    /**
     * Add one or more class names to the result set
     *
     * @param name Class name(s) to set
     * @returns Self for chaining
     */
    classAdd(name?: string | string[] | null): this;
    /**
     * Check if the first element in the result set has the given class
     *
     * @param name Class name to check for
     * @returns Self for chaining
     */
    classHas(name: string): boolean;
    /**
     * Remove the given class(s) from all elements in the result set
     *
     * @param name Class name to remove
     * @returns Self for chaining
     */
    classRemove(name?: string | string[] | null): this;
    /**
     * Toggle a class on all elements in the result set
     *
     * @param name Class name(s) to toggle - space separated
     * @param toggle Toggle on or off
     * @returns Self for chaining
     */
    classToggle(name: string | string[], toggle?: boolean): this;
    /**
     * Clone the nodes in the result set and return a new instance
     *
     * @param deep Include the subtree (`true`) or not (`false` - default)
     * @returns New Dom instance with new elements
     */
    clone(deep?: boolean): Dom<T>;
    /**
     * Find the closest ancestor for each element in the result set
     *
     * @param selector
     * @returns New Dom instance when the matching ancestors
     */
    closest<R extends HTMLElement = T>(selector: string | HTMLElement | Element): Dom<R>;
    /**
     * Determine if the result set contains the element specified. Shorthand for
     * .find().count()
     *
     * @param input Element / selector to look for
     * @returns true if it does contain, false otherwise
     */
    contains(input: Dom | string | HTMLElement | Element | null): boolean;
    /**
     * Get the number of elements in the current result set
     *
     * @returns Number of elements
     */
    count(): number;
    /**
     * Get a CSS computed value (first item in the result set only)
     *
     * @param rule The CSS property to get
     * @returns Read value
     */
    css(rule: string): string;
    /**
     * Set a CSS value
     *
     * @param rule CSS property to set
     * @param value Value to set it to
     * @returns Self for chaining
     */
    css(rule: string, value: string): this;
    /**
     * Set multiple CSS properties for all items in the result set
     *
     * @param rules Plain object of rules to assign to the elements
     */
    css(rules: Record<string, string>): this;
    /**
     * Get all the data attributes for an element
     *
     * @returns Read values
     */
    data(): Record<string, AttributeTypes>;
    /**
     * Get a data attribute's value from the first item in the result set. Can
     * be `null`. Please be aware that this uses the element's `dataset`
     * property, and so will do name conversion from dashed (in the element's
     * attribute) to camelCase in Javascript.
     *
     * @param name Data value name
     * @returns Read value
     */
    data<T = AttributeTypes>(name: string): T;
    /**
     * Set a data attribute's value for all items in the result set.
     *
     * @param name Data value name
     * @param value Value to give the data attribute
     * @returns Self for chaining
     */
    data(name: string, value: AttributeTypes): this;
    /**
     * Set multiple data attributes of the elements in the result set
     *
     * @param attributes Plain object of data attributes to be assigned
     */
    data(attributes: Record<string, AttributeTypes>): this;
    /**
     * Remove the elements in the result set from the document. Does not remove
     * event listeners.
     *
     * @returns Self for chaining
     */
    detach(): this;
    /**
     * Remove the child elements from each element in the result set from the
     * document. Does not remove event listeners.
     *
     * @returns Self for chaining
     */
    detachChildren(): this;
    /**
     * Iterate over each item in the result set and perform an action
     *
     * @param callback Callback function
     * @returns Self for chaining
     */
    each(callback: (el: T, idx: number) => void): this;
    /**
     * Inverse iteration over each item in the result set and perform an action
     *
     * @param callback Callback function
     * @returns Self for chaining
     */
    eachReverse(callback: (el: T, idx: number) => void): this;
    /**
     * Remove all children
     *
     * @returns Self for chaining
     */
    empty(): this;
    /**
     * Get a new Dom instance with just a specific element from the result set
     *
     * @param idx The element to use
     * @returns New Dom instance
     */
    eq(idx: number): Dom<T>;
    /**
     * Get all elements in the result set
     */
    get<R = T>(): R[];
    /**
     * Get a specific element from the result set
     *
     * @param idx Element index
     */
    get<R = T>(idx: number): R;
    /**
     * Call focus on the target elements
     *
     * @returns Self for chaining
     */
    focus(): this;
    /**
     * Reduce the result set based on a given filter, which can be a CSS
     * selector, an element or array of elements.
     *
     * @param filter Optional selector or function that the result set element
     *   would need to match to be selected.
     * @returns New Dom instance containing the filters elements
     */
    filter(filter?: string | HTMLElement | HTMLElement[] | ArrayLike<HTMLElement> | ((el: T) => boolean)): Dom<T>;
    /**
     * Get all matching descendants
     *
     * @param input Elements to find
     * @returns A new Dom instance with all matching elements
     */
    find<R extends HTMLElement = T>(input: Dom | string | HTMLElement | Element | null): Dom<R>;
    /**
     * Get the last element in the result set
     *
     * @returns New instance with just the selected item
     */
    first(): Dom<HTMLElement>;
    /**
     * Get the height for the first element in the result set. Whether this is
     * the inner or outer height depends on the box model for the element.
     *
     * @returns Element's height
     */
    height(): number;
    /**
     * Get the height of the first element in the result set, with specific
     * parts included in the result.
     *
     * @param include Parts of the box model to include
     * @returns Element's height
     */
    height(include: TDimensionInclude): number;
    /**
     * Set the height for all elements in the result set,
     *
     * @param set Value to set as the height. As a number it will be treated as
     *   a pixel value, while as a string, it must have a CSS unit already on
     *   it.
     * @returns Self
     */
    height(set: number | string): this;
    /**
     * Hide an element by setting it to `display: none`
     *
     * @returns Self for chaining
     */
    hide(): this;
    /**
     * Get the HTML from the first element in the result set
     */
    html(): string;
    /**
     * Set the HTML for all elements in the result set
     * @param data Value to set as the HTML
     * @returns Self for chaining
     */
    html(data: string): this;
    /**
     * Boolean return check on if an item in the result set matches the selector
     * given. Only one need match.
     *
     * @param selector Selector to match against
     * @returns Boolean true if there is a match
     */
    is(selector: string | HTMLElement | HTMLElement[] | ArrayLike<HTMLElement> | ((el: T) => boolean)): boolean;
    /**
     * Determine if the first element in the result set is in the document or
     * not
     *
     * @returns true if is, false if detached
     */
    isAttached(): boolean;
    /**
     * Determine if the first element in the result set is visible or not.
     *
     * @returns Visibility flag
     */
    isVisible(): boolean;
    /**
     * Get the index of an element from among its siblings
     *
     * @returns Element index
     */
    index(): number;
    /**
     * Insert each element in the result set after a target node
     *
     * @param target Element after which the insert should happen
     * @returns Self for chaining
     */
    insertAfter(target: Element | Element[] | Dom): this;
    /**
     * Insert each element in the result set before a target node
     *
     * @param target Element before which the insert should happen
     * @returns Self for chaining
     */
    insertBefore(target: Element | Element[] | Dom): this;
    /**
     * Get the last element in the result set
     *
     * @returns New instance with just the selected item
     */
    last(): Dom<HTMLElement>;
    /**
     * Create a new Dom instance based on the results from a callback function
     * which is executed per element in the result set.
     *
     * @param fn Function to get the elements to add to the new instance
     * @returns New Dom instance with the results from the callback
     */
    map<R extends HTMLElement = T>(fn: (el: T) => R | R[] | null): Dom<R>;
    /**
     * Create an array of any data type based on a function returning a value
     * from each element in the result set.
     *
     * @param fn Mapping function
     * @returns Array of returned objects.
     */
    mapTo<R>(fn: (el: T, idx: number) => R): R[];
    /**
     * Remove all events attached to this element
     *
     * @returns Self for chaining.
     */
    off(): this;
    /**
     * Remove all events attached to this element that match the given event
     * name or any of the namespaces (if given).
     *
     * @param name Event name. This can optionally include period separated
     *   namespaces. Multiple events can be removed by space separation of the
     *   names.
     * @returns Self for chaining.
     */
    off(name: string): this;
    /**
     * Remove all events attached to this element that match the given event
     * name or any of the namespaces (if given) and the event handler
     *
     * @param name Event name. This can optionally include period separated
     *   namespaces. Multiple events can be removed by space separation of the
     *   names.
     * @param handler Callback to remove
     * @returns Self for chaining.
     */
    off(name: string, handler: Function): this;
    /**
     * Remove all delegated events attached to this element that match the given
     * event name or any of the namespaces (if given), the delegate selector and
     * (optionally) the event handler
     *
     * @param name Event name. This can optionally include period separated
     *   namespaces. Multiple events can be removed by space separation of the
     *   names.
     * @param selector CSS style selector to use to match elements from the
     *   parent.
     * @param handler Callback to remove
     * @returns Self for chaining.
     */
    off(name: string, selector: string, handler?: Function): this;
    /**
     * Get the offset of the first element in the result set. The offset is the
     * coordinates of the element relative to the document.
     *
     * @returns Object with top and left offset
     */
    offset(): {
        top: number;
        left: number;
    };
    /**
     * Get the offset parents of the elements in the result set.
     *
     * Departure from jQuery - it won't go up to `html`
     *
     * @returns Instance with the result set as the offset parents
     */
    offsetParent(): Dom<HTMLElement>;
    /**
     * Add an event listener to all elements in the result set.
     *
     * @param name Event name. This can optionally include period separated
     *   namespaces. Multiple events can be added by space separation of the
     *   names.
     * @param handler Callback when the event happens.
     * @return Self for chaining
     */
    on(name: string, handler: EventHandler): this;
    /**
     * Add a delegated event listener to all elements in the result set.
     *
     * @param name Event name. This can optionally include period separated
     *   namespaces. Multiple events can be added by space separation of the
     *   names.
     * @param selector CSS style selector to use to match elements from the
     *   parent.
     * @param handler Callback when the event happens.
     * @return Self for chaining
     */
    on(name: string, selector: string, handler: EventHandler): this;
    /**
     * Add a one-time event listener to all elements in the result set.
     *
     * @param name Event name. This can optionally include period separated
     *   namespaces. Multiple events can be added by space separation of the
     *   names.
     * @param handler Callback when the event happens.
     * @return Self for chaining
     */
    one(name: string, handler: EventHandler): this;
    /**
     * Add a one-time event listener to all elements in the result set.
     *
     * @param name Event name. This can optionally include period separated
     *   namespaces. Multiple events can be added by space separation of the
     *   names.
     * @param selector CSS style selector to use to match elements from the
     *   parent.
     * @param handler Callback when the event happens.
     * @return Self for chaining
     */
    one(name: string, selector: string, handler: EventHandler): this;
    /**
     * Get the parent element for each element in the result set
     *
     * @param filter Optional selector that the parent element would need to
     *   match to be selected.
     * @returns New Dom instance containing the parent elements
     */
    parent(filter?: string): Dom<HTMLElement>;
    /**
     * Get the position of the first element in the result set. The position is
     * the coordinates relative to the offset parent.
     *
     * @returns Object with top and left position coordinates
     */
    position(): {
        top: number;
        left: number;
    };
    /**
     * Prepend the given content to each item in the result set.
     *
     * You should limit your result set to a single item!
     *
     * @param content Item(s) to prepend
     * @returns Self for chaining
     */
    prepend(content: Element | Dom | string): this;
    /**
     * Append the current data set items to the element from the selector
     *
     * @param selector Select item to insert result sets into
     * @returns Self for chaining
     */
    prependTo(selector: DomSelector$1 | Dom): this;
    /**
     * Get an property value from the first item in the result set. Can be
     * undefined. Note this is not the same as an attribute, although they could
     * be!
     *
     * @param name Property name
     * @returns Read value
     */
    prop(name: string): AttributeTypes;
    /**
     * Set an property value for all items in the result set.
     *
     * @param name Property name
     * @param value Value to give the property
     * @returns Self for chaining
     */
    prop(name: string, value: AttributeTypes): this;
    /**
     * Remove a property from all elements in the result set
     *
     * @param name Property name to remove
     * @returns Self for chaining
     */
    propRemove(name: string): this;
    /**
     * Removed all nodes in the result set from the document
     *
     * @returns Self for chaining
     */
    remove(): this;
    /**
     * Replace the elements in the result set with those given.
     *
     * @param replacer Element(s) to insert in place of the originals
     * @returns Self
     */
    replaceWith(replacer: Element | Dom): this;
    /**
     * Get the scrollLeft property of the first element in the result set
     *
     * @returns Current scroll left value
     */
    scrollLeft(): number;
    /**
     * Set the scrollLeft property for all elements in the result set
     *
     * @param val Value to set
     * @returns Self for chaining
     */
    scrollLeft(val: number): this;
    /**
     * Get the scrollTop property of the first element in the result set
     *
     * @returns Current scroll top value
     */
    scrollTop(): number;
    /**
     * Set the scrollTop property for all elements in the result set
     *
     * @param val Value to set
     * @returns Self for chaining
     */
    scrollTop(val: number): this;
    /**
     * Get the siblings of all elements in the result set
     *
     * @returns New Dom instance containing the sibling elements
     */
    siblings(): Dom<T>;
    /**
     * Set the elements in the result set to display as blocks
     *
     * @returns Self for chaining
     * @todo Could be smarter with hide, since some elements might have been a
     *   grid or flex before being hidden.
     */
    show(): this;
    /**
     * Sort the DOM elements into document order.
     *
     * This is normally not needed as elements selected with a DOM selector are
     * automatically sorted in document order. However, in the case of elements
     * being added as an array, their order will be retained. In such as case
     * you might wish to sort them in document order.
     */
    sort(): this;
    /**
     * Get the text content for the first item in the result set
     *
     * @returns Text content of the element
     */
    text(): string;
    /**
     * Set the text content for the items in the result set
     *
     * @param txt Text value to set
     * @returns Self for chaining
     */
    text(txt: string): this;
    /**
     * Perform a CSS transition - i.e. an animation. Note this isn't nearly as
     * comprehensive as an animation library, nor is it meant to be. It is for
     * simple transitions such as fading in only.
     *
     * To set up something like a fade in, do `dom.css({opacity:
     * 0}).transition({opacity: 1})`.
     *
     * @param css CSS properties to transition
     * @param duration Transition duration
     * @param ease CSS easing function name
     * @param cb Callback function
     * @returns Self for chaining
     */
    transition(css: Record<string, string>, duration?: number | null, ease?: string | null, cb?: Function): this;
    /**
     * Trigger an event on all of the elements in the result set. A different
     * event object is created per element.
     *
     * @param name Event name. This can optionally include period separated
     *   namespaces. Multiple events can be added by space separation of the
     *   names.
     * @param bubbles If the event should bubble up the DOM. Default, true.
     * @param args Arguments to pass to the event handlers (after the event
     *   object, which is always the first parameter).
     * @param props An object of key/value pairs which should be added to the
     *   event object that is created and fired for the events.
     * @param returnEvent If not set or `false` the return array contains
     *   booleans.
     * @returns An array of do default results from the event `true` indicates
     *  that the default action should happen, `false` means default was
     *  prevented.
     */
    trigger(name: string, bubbles?: boolean, args?: any[] | null, props?: PlainObject | null, returnEvent?: false): boolean[];
    /**
     * Trigger an event on all of the elements in the result set. A different
     * event object is created per element.
     *
     * @param name Event name. This can optionally include period separated
     *   namespaces. Multiple events can be added by space separation of the
     *   names.
     * @param bubbles If the event should bubble up the DOM. Default, true.
     * @param args Arguments to pass to the event handlers (after the event
     *   object, which is always the first parameter).
     * @param props An object of key/value pairs which should be added to the
     *   event object that is created and fired for the events.
     * @param returnEvent When set to `true` the return will be an array of
     *  Event objects.
     * @returns An array of Event objects for further processing.
     */
    trigger(name: string, bubbles: boolean, args: any[] | null, props: PlainObject | null, returnEvent: true): Event[];
    /**
     * Get the value from the first item in the result set
     *
     * @returns Current value
     */
    val(): string;
    /**
     * Set the value for all elements in the result set
     *
     * @param value Value to set
     * @returns Self for chaining
     */
    val(value: string | number): this;
    /**
     * Get the content width for the first element in the result set (i.e. no
     * padding, border or margin), regardless of the box model type.
     *
     * @returns Element's width
     */
    width(): number;
    /**
     * Get the width of the first element in the result set, with specific parts
     * included in the result.
     *
     * @param include Parts of the box model to include
     * @returns Element's width
     */
    width(include: TDimensionInclude): number;
    /**
     * Set the width for all elements in the result set,
     *
     * @param set Value to set as the width. As a number it will be treated as a
     *   pixel value, while as a string, it must have a CSS unit already on it.
     * @returns Self
     */
    width(set: number | string): this;
}

type HttpMethod = 'get' | 'GET' | 'post' | 'POST' | 'put' | 'PUT' | 'delete' | 'DELETE';
type AjaxComplete = (xhr: XMLHttpRequest, status: string) => void;
type AjaxError = (xhr: XMLHttpRequest, errorState: string, status: string) => void;
type AjaxSuccess = (json: any) => void;
interface AjaxOptions {
    beforeSend?: (xhr: XMLHttpRequest, options: AjaxOptions) => void | false;
    cache?: boolean;
    complete?: AjaxComplete | AjaxComplete[];
    contentType?: string | false;
    data?: Record<string, any> | string | any[] | FormData;
    dataType?: 'json' | 'text';
    deleteBody?: boolean;
    error?: AjaxError | AjaxError[];
    headers?: Record<string, string>;
    method?: HttpMethod;
    password?: string;
    success?: AjaxSuccess | AjaxSuccess[];
    submitAs?: 'json' | 'http';
    traditional?: boolean;
    type?: HttpMethod;
    url: string;
    username?: string;
}
/**
 * Trigger an Ajax call to the server based on the configuration parameters
 * passed in.
 *
 * @param optionsIn Ajax options
 * @returns The XHR request
 */
declare function ajax(optionsIn: AjaxOptions): XMLHttpRequest;
declare namespace ajax {
    var defaults: AjaxOptions;
    var serialize: (obj: any, traditional?: boolean) => string;
}

/**
 * Internal settings object used for individual columns. Instances are held in
 * the setting object's `columns` array and contains all the information that
 * DataTables needs about each individual column.
 *
 * Note that this object is related to the column defaults but this one is the
 * internal data store for DataTables's cache of columns. It should NOT be
 * manipulated outside of DataTables. Any configuration should be done through
 * the initialisation options.
 */
declare class Settings {
    _isArrayHost: boolean;
    /**
     * Flag to indicate if HTML5 data attributes should be used as the data
     * source for filtering or sorting. True is either are.
     */
    attrSrc: boolean;
    ariaTitle: string | null;
    autoTitle: boolean;
    cellType: string;
    /**
     * The class to apply to all cells in the table's `tbody`` for the column
     */
    className: string | null;
    colEl: Dom<HTMLTableColElement>;
    /**
     * When DataTables calculates the column widths to assign to each column, it
     * finds the longest string in each column and then constructs a temporary
     * table and reads the widths from that. The problem with this is that "mmm"
     * is much wider then "iiii", but the latter is a longer string - thus the
     * calculation can go wrong (doing it properly and putting it into an DOM
     * object and measuring that is horribly(!) slow). Thus as a "work around"
     * we provide this option. It will append its value to the text that is
     * found to be the longest string for the column - i.e. padding.
     */
    contentPadding: string | null;
    /**
     * Developer definable function that is called whenever a cell is created
     * (Ajax source, etc) or processed for input (DOM source). This can be used
     * as a compliment to `render` allowing you to modify the DOM element (add
     * background colour for example) when the element is available.
     */
    createdCell: (cell: HTMLTableCellElement, cellData: any, rowData: any, rowIndex: number, colIdx: number) => void;
    /**
     * Property to read the value for the cells in the column from the data
     * source array / object. If null, then the default content is used, if a
     * function is given then the return from the function is used.
     */
    data: any;
    /**
     * Function to get data from a cell in a column. You should <b>never</b>
     * access data directly through `data` internally in DataTables - always use
     * the method attached to this property. It allows mData to function as
     * required. This function is automatically assigned by the column
     * initialisation method
     */
    dataGet: (rowData: any, type: string | undefined, meta: any) => any;
    /**
     * Function to set data for a cell in the column. You should <b>never</b>
     * set the data directly to `data` internally in DataTables - always use
     * this method. It allows mData to function as required. This function is
     * automatically assigned by the column initialisation method
     */
    dataSet: (rowData: any, val: any, meta: any) => any;
    /**
     * Allows a default value to be given for a column's data, and will be used
     * whenever a null data source is encountered (this can be because mData is
     * set to null, or because the data source itself is null).
     */
    defaultContent: string | null;
    /**
     * Define which field(s) this column should trigger editing on (requires
     * Editor, but is referenced by a number of extensions, hence why it is
     * here).
     */
    editField: string | string[];
    footer: string;
    /**
     * Column index.
     */
    idx: number;
    /**
     * Name for the column, allowing reference to the column by name as well as
     * by index (needs a lookup to work by name).
     */
    name: string | null;
    /**
     * Sorting enablement flag.
     */
    orderable: boolean;
    /**
     * A list of the columns that sorting should occur on when this column is
     * sorted. That this property is an array allows multi-column sorting to be
     * defined for a column (for example first name / last name columns would
     * benefit from this). The values are integers pointing to the columns to be
     * sorted on (typically it will be a single integer pointing at itself, but
     * that doesn't need to be the case).
     */
    orderData: number[];
    /**
     * Custom sorting data type - defines which of the available plug-ins in
     * afnSortData the custom sorting will use - if any is defined.
     */
    orderDataType: string;
    /**
     * Class to be applied to the header element when sorting on this column
     */
    orderingClass: string | null;
    /**
     * Define the sorting directions that are applied to the column, in sequence
     * as the column is repeatedly sorted upon - i.e. the first value is used as
     * the sorting direction when the column if first sorted (clicked on). Sort
     * it again (click again) and it will move on to the next index. Repeat
     * until loop.
     */
    orderSequence: string[];
    /**
     * Partner property to mData which is used (only when defined) to get the
     * data - i.e. it is basically the same as mData, but without the 'set'
     * option, and also the data fed to it is the result from mData. This is the
     * rendering method to match the data method of mData.
     */
    render: any;
    renderer: GetFunction | null;
    /** Set by Responsive to tell if it has hidden a column or not */
    responsiveVisible: boolean;
    /**
     * Flag to indicate if the column is searchable, and thus should be included
     * in the filtering or not.
     */
    searchable: boolean;
    setter: SetFunction | null;
    /**
     * Title of the column - what is seen in the TH element (nTh).
     */
    title: string | null;
    /**
     * Column sorting and filtering type
     */
    type: string | null;
    /**
     * Store for manual type assignment using the `column.type` option. This
     * is held in store so we can manipulate the column's `type` property.
     */
    typeManual: string | null;
    /**
     * Flag to indicate if the column is currently visible in the table or not
     */
    visible: boolean;
    /** Cached longest strings from a column */
    wideStrings: string[] | null;
    /**
     * Width of the column
     */
    width: string | null;
    /**
     * Width of the column when it was first "encountered"
     */
    widthOrig: string | null;
}

declare const _default$3: {
    container: string;
    empty: {
        row: string;
    };
    info: {
        container: string;
    };
    layout: {
        row: string;
        cell: string;
        tableRow: string;
        tableCell: string;
        start: string;
        end: string;
        full: string;
    };
    length: {
        container: string;
        select: string;
    };
    order: {
        canAsc: string;
        canDesc: string;
        isAsc: string;
        isDesc: string;
        none: string;
        position: string;
    };
    processing: {
        container: string;
    };
    scrolling: {
        body: string;
        container: string;
        footer: {
            self: string;
            inner: string;
        };
        header: {
            self: string;
            inner: string;
        };
    };
    search: {
        container: string;
        input: string;
    };
    table: string;
    tbody: {
        cell: string;
        row: string;
    };
    thead: {
        cell: string;
        row: string;
    };
    tfoot: {
        cell: string;
        row: string;
    };
    paging: {
        active: string;
        button: string;
        container: string;
        disabled: string;
        nav: string;
    };
};

interface IFeatureDivOptions {
    /** Class name for the div */
    className: string;
    /** ID to give the div */
    id: string;
    /** HTML content for the div (cannot be used as well as textContent) */
    html: string;
    /** Text content for the div (cannot be used as well as innerHTML) */
    text: string;
}

interface IFeatureInfoOptions {
    /** Information display callback */
    callback: (settings: Context, start: number, end: number, max: number, total: number, pre: string) => string;
    /** Empty table text */
    empty: string;
    /** Information string postfix */
    postfix: string;
    /** Appended to the info string when searching is active */
    search: string;
    /** Table summary information display string */
    text: string;
}

interface IFeaturePagingOptions {
    /** Set the maximum number of paging number buttons */
    buttons: number;
    /** Paging button display options */
    type: 'numbers' | 'simple' | 'simple_numbers' | 'full' | 'full_numbers' | 'first_last_numbers';
    /** Display the buttons in the pager (default true) */
    numbers: boolean;
    /** Display the first and last buttons in the pager (default true) */
    firstLast: boolean;
    /** Display the previous and next buttons in the pager (default true) */
    previousNext: boolean;
    /** Include the extreme page numbers, if separated by ellipsis, or not */
    boundaryNumbers: boolean;
}

interface IFeaturePageLengthOptions {
    /** Text for page length control */
    menu: Array<number | {
        label: string;
        value: number;
    }>;
    /** Text for page length control */
    text: string;
}

interface IFeatureSearchOptions {
    /** Columns the search should apply to */
    columns: ColumnSelector;
    /** Placeholder for the input element */
    placeholder: string;
    /** Show the processing icon when searching */
    processing: boolean;
    /** Text for search control */
    text: string;
}

type SearchInput<T = any> = string | RegExp | ((data: string | null, rowData: T, rowIdx: number, columnIdx: number[] | number) => boolean);
interface SearchOptions {
    /**
     * Start the matching from the start of a word (true), or anywhere in the
     * string to search (false).
     */
    boundary: boolean;
    /**
     * Flag to whether or not the filtering should be case-insensitive
     */
    caseInsensitive: boolean;
    /**
     * This option modifies the search to perform an exact match (string based)
     * on the values in the table
     */
    exact: boolean;
    /**
     * Flag to indicate if the search term should be interpreted as a
     * regular expression (true) or not (false) and therefore and special
     * regex characters escaped.
     */
    regex: boolean;
    /**
     * Flag to indicate if DataTables should only trigger a search when
     * the return key is pressed.
     */
    return: boolean;
    /**
     * Flag to indicate if DataTables is to use its smart filtering or not.
     */
    smart: boolean;
}
/**
 * Internal object
 */
interface SearchObject extends SearchOptions {
    /**
     * List of columns that should be included in the search.
     */
    columns: number[] | null;
    /**
     * Applied search term
     */
    search: SearchInput;
}
/**
 * Create a new search options object
 *
 * @param parts Values to assign, otherwise the defaults will be used
 * @returns New object
 */
declare function create$2(parts?: Partial<SearchOptions>): SearchObject;

/** State object */
interface State {
    childRows?: string[];
    columns: Array<{
        name: string | null;
        search: SearchObject;
        visible: boolean;
    }>;
    length: number;
    order: Array<Array<string | number>>;
    search: SearchObject;
    searchGroups: SearchObject[];
    start: number;
    time: number;
}
/** State that can be loaded - every parameter is optional */
interface StateLoad {
    childRows?: string[];
    columns?: Array<{
        name?: string | null;
        search?: SearchObject;
        visible?: boolean;
    }>;
    length?: number;
    order?: Array<Array<string | number>>;
    search?: SearchObject;
    searchGroups?: SearchObject[];
    start?: number;
    time?: number;
}

type DeepPartial<T> = T extends (...args: any[]) => any ? T : T extends (infer U)[] ? Array<DeepPartial<U>> : T extends ReadonlyArray<infer U> ? ReadonlyArray<DeepPartial<U>> : T extends object ? {
    [K in keyof T]?: DeepPartial<T[K]>;
} : T;
/**
 * Execution scope for the callbacks
 */
interface DataTableDom extends Dom<HTMLTableElement> {
    /**
     * Get a DataTable API instance for the table
     */
    api(): Api;
}
interface Feature {
    /** A simple `<div>` that can contain your own content */
    div?: Partial<IFeatureDivOptions>;
    /** Table information display */
    info?: Partial<IFeatureInfoOptions>;
    /** Paging length control */
    pageLength?: Partial<IFeaturePageLengthOptions>;
    /** Pagination buttons */
    paging?: Partial<IFeaturePagingOptions>;
    /** Global search input */
    search?: Partial<IFeatureSearchOptions>;
}
type LayoutNumber = '' | '1' | '2' | '3' | '4' | '5' | '6' | '7' | '8' | '9';
type LayoutSide = 'top' | 'bottom';
type LayoutEdge = 'Start' | 'End';
type LayoutKeys = `${LayoutSide}${LayoutNumber}${LayoutEdge}` | `${LayoutSide}${LayoutNumber}`;
type LayoutFeatures = keyof Feature | Feature | Array<keyof Feature> | Feature[];
type LayoutElement = {
    /** Class to apply to the CELL in the layout grid */
    className?: string;
    /** ID to apply to the CELL in the layout grid */
    id?: string;
    /** Class to apply to the ROW in the layout grid */
    rowClass?: string;
    /** ID to apply to the ROW in the layout grid */
    rowId?: string;
    /** List of features to show in this cell */
    features: LayoutFeatures;
};
type LayoutComponent = LayoutElement | LayoutFeatures | (() => HTMLElement) | HTMLElement | JQuery<HTMLElement> | Dom | null;
type Layout = Partial<Record<LayoutKeys, LayoutComponent>>;
type ColumnRenderFunction = (this: DataTableDom, data: any, type: any, row: any, meta: CellMeta) => any;
type FunctionColumnCreatedCell = (this: DataTableDom, cell: HTMLTableCellElement, cellData: any, rowData: any, row: number, col: number) => void;
interface CellMeta {
    row: number;
    col: number;
    settings: Context;
}
interface OrderFixed {
    /**
     * Two-element array:
     *
     * * 0: Column index to order upon.
     * * 1: Direction so order to apply ("asc" for ascending order or "desc" for
     *   descending order).
     */
    pre?: any[];
    /**
     * Two-element array:
     *
     * * 0: Column index to order upon.
     * * 1: Direction so order to apply ("asc" for ascending order or "desc" for
     *   descending order).
     */
    post?: any[];
}
interface FunctionColumnData {
    (row: any, type: 'set', s: any, meta: CellMeta): void;
    (row: any, type: 'display' | 'sort' | 'filter' | 'type', s: undefined, meta: CellMeta): any;
}
interface ObjectColumnData {
    _: string | number | FunctionColumnData;
    filter?: string | number | FunctionColumnData;
    display?: string | number | FunctionColumnData;
    type?: string | number | FunctionColumnData;
    sort?: string | number | FunctionColumnData;
}
interface ObjectColumnRender {
    _?: string | number | ColumnRenderFunction;
    filter?: string | number | ColumnRenderFunction;
    display?: string | number | ColumnRenderFunction;
    type?: string | number | ColumnRenderFunction;
    sort?: string | number | ColumnRenderFunction;
}
interface AjaxData {
    draw?: number;
    start?: number;
    length?: number;
    order?: AjaxDataOrder[];
    columns?: AjaxDataColumn[];
    search?: AjaxDataSearch;
}
interface AjaxDataSearch {
    value: string;
    regex: boolean;
    fixed: {
        name: string;
        term: string;
    }[];
    groups: {
        columns: number[];
        term: string;
    }[];
    groupsFixed: {
        name: string;
        columns: number[];
        term: string;
    }[];
}
interface AjaxDataOrder {
    column: number;
    dir: string;
}
interface AjaxDataColumnSearch {
    value: string;
    regex: boolean;
    fixed: {
        name: string;
        term: string;
    }[];
}
interface AjaxDataColumn {
    data: string | number;
    name: string | null;
    searchable: boolean;
    orderable: boolean;
    search: AjaxDataColumnSearch;
}
type FunctionAjax = (this: DataTableDom, data: object, callback: (data: any) => void, settings: Context) => void;
interface OrderIdx {
    idx: number;
    dir: 'asc' | 'desc';
}
interface OrderName {
    name: string;
    dir: 'asc' | 'desc';
}
type OrderArray = [number, 'asc' | 'desc' | ''];
type OrderCombined = OrderIdx | OrderName | OrderArray;
type Order = OrderCombined | OrderCombined[];
type OrderColumn = [number, string, number?];
interface OrderState extends OrderColumn {
    _idx?: number;
}
interface ConfigRenderer {
    header?: string;
    pageButton?: string;
}
type FunctionCreateRow = (this: DataTableDom, row: HTMLTableRowElement, data: any[] | object, dataIndex: number, cells: HTMLTableCellElement[]) => void;
type FunctionDrawCallback = (this: DataTableDom, settings: Context) => void;
type FunctionFooterCallback = (this: DataTableDom, tr: HTMLTableRowElement, data: any[], start: number, end: number, display: any[]) => void;
type FunctionFormatNumber = (formatNumber: number, ctx: Context) => string;
type FunctionHeaderCallback = (this: DataTableDom, tr: HTMLTableRowElement, data: any[], start: number, end: number, display: any[]) => void;
type FunctionInfoCallback = (this: DataTableDom, settings: Context, start: number, end: number, max: number, total: number, pre: string) => string;
type FunctionInitComplete = (this: DataTableDom, settings: Context, json: object) => void;
type FunctionPreDrawCallback = (this: DataTableDom, settings: Context) => void;
type FunctionRowCallback = (this: DataTableDom, row: HTMLTableRowElement, data: any[] | object, index: number) => void;
type FunctionStateLoadCallback = (this: DataTableDom, settings: Context, callback: (state: State) => void) => undefined | null | object;
type FunctionStateLoaded = (this: DataTableDom, settings: Context, data: object) => void;
type FunctionStateLoadParams = (this: DataTableDom, settings: Context, data: object) => void;
type FunctionStateSaveCallback = (this: DataTableDom, settings: Context, data: object) => void;
type FunctionStateSaveParams = (this: DataTableDom, settings: Context, data: object) => void;

interface ConfigColumnDefs extends Options$1 {
    /**
     * Target column(s). Either this or `target` must be specified.
     */
    targets?: string | number | Array<number | string>;
    /**
     * Single column target. Either this or `targets` must be specified.
     */
    target?: string | number;
}
interface Defaults$1 {
    /**
     * Set the column's aria-label title. Since: 1.10.25
     */
    ariaTitle: string;
    /**
     * Cell type to be created for a column. th/td
     */
    cellType: string;
    /**
     * Class to assign to each cell in the column.
     */
    className: string;
    /**
     * Add padding to the text content used when calculating the optimal with
     * for a table.
     */
    contentPadding: string;
    /**
     * Cell created callback to allow DOM manipulation.
     */
    createdCell: FunctionColumnCreatedCell | null;
    /**
     * Class to assign to each cell in the column.
     */
    data: number | string | ObjectColumnData | FunctionColumnData | null;
    /**
     * Set default, static, content for a column.
     */
    defaultContent: string | null;
    /**
     * Text to display in the table's footer for this column.
     */
    footer: string | null;
    /**
     * Set a descriptive name for a column.
     */
    name: string;
    /**
     * Enable or disable ordering on this column.
     */
    orderable: boolean;
    /**
     * Define multiple column ordering as the default order for a column.
     */
    orderData: number | number[] | null;
    /**
     * Live DOM sorting type assignment.
     */
    orderDataType: string;
    /**
     * Order direction application sequence.
     */
    orderSequence: Array<'asc' | 'desc' | ''>;
    /**
     * Render (process) the data for use in the table.
     */
    render: number | string | ObjectColumnData | ColumnRenderFunction | ObjectColumnRender | null;
    search: SearchOptions | null;
    /**
     * Enable or disable filtering on the data in this column.
     */
    searchable: boolean;
    /**
     * Set the column title.
     */
    title: string | null;
    /**
     * Set the column type - used for filtering and sorting string processing.
     */
    type: string | null;
    /**
     * Enable or disable the display of this column.
     */
    visible: boolean;
    /**
     * Column width assignment.
     */
    width: string | null;
}
interface Options$1 extends Partial<Defaults$1> {
}

interface Defaults {
    /**
     * Load data for the table's content from an Ajax source.
     */
    ajax: null | string | DtAjaxOptions | FunctionAjax;
    /**
     * Feature control DataTables' smart column width handling.
     */
    autoWidth: boolean;
    /**
     * Set a `caption` for the table. This can be used to describe the contents
     * of the table to the end user. A caption tag can also be read from HTML.
     */
    caption: string;
    /**
     * Classes that DataTables assigns to the various components and features
     * that it adds to the HTML table. See also `DataTable.ext.classes`.
     */
    classes: Partial<typeof _default$3>;
    /**
     * Defaults for column configuration options
     */
    column: Defaults$1;
    /**
     * Assign a column definition to one or more columns.
     */
    columnDefs: ConfigColumnDefs[] | null;
    /**
     * Data to use as the display data for the table.
     */
    columns: Defaults$1[] | null;
    /**
     * Callback for whenever a TR element is created for the table's body.
     */
    createdRow: FunctionCreateRow | null;
    /**
     * Data to use as the display data for the table.
     */
    data: any[] | null;
    /**
     * Delay the loading of server-side data until second draw
     */
    deferLoading: number | number[] | null;
    /**
     * Feature control deferred rendering for additional speed of
     * initialisation.
     */
    deferRender: boolean;
    /**
     * Destroy any existing table matching the selector and replace with the new
     * options.
     */
    destroy: boolean;
    /**
     * Initial paging start point.
     */
    displayStart: number;
    /**
     * Define the table control elements to appear on the page and in what order.
     *
     * @deprecated Use `layout` instead
     */
    dom: string | null;
    /**
     * Function that is called every time DataTables performs a draw.
     *
     * @deprecated Use `on.draw` or `draw` event
     */
    drawCallback: FunctionDrawCallback | null;
    /**
     * Footer display callback function.
     */
    footerCallback: FunctionFooterCallback | null;
    /**
     * Number formatting callback function.
     */
    formatNumber: FunctionFormatNumber | null;
    /**
     * Header display callback function.
     */
    headerCallback: FunctionHeaderCallback | null;
    /**
     * Feature control table information display field.
     *
     * @deprecated Use the `paging` feature options
     */
    info: boolean;
    /**
     * Table summary information display callback.
     *
     * @deprecated Use the `callback` option for the `info` feature
     */
    infoCallback: FunctionInfoCallback | null;
    /**
     * Initialisation complete callback.
     *
     * @deprecated Use `init` event
     */
    initComplete: FunctionInitComplete | null;
    /**
     * Language configuration object
     */
    language: ConfigLanguage;
    /**
     * Table and control layout. This replaces the legacy `dom` option.
     */
    layout: Layout;
    /**
     * Feature control the end user's ability to change the paging display
     * length of the table.
     *
     * @deprecated Use the `pageLength` feature options
     */
    lengthChange: boolean;
    /**
     * Change the options in the page length select list.
     */
    lengthMenu: Array<number | {
        label: string;
        value: number;
    }> | [number[], string[]];
    /**
     * Add event listeners during the DataTables startup
     */
    on: {
        [name: string]: (this: HTMLElement, e: Event, ...args: any[]) => void;
    };
    /**
     * Initial order (sort) to apply to the table.
     */
    order: Order | Order[];
    /**
     * Control which cell the order event handler will be applied to in a
     * column.
     *
     * @deprecated Use titleRow
     */
    orderCellsTop: boolean | null;
    /**
     * Highlight the columns being ordered in the table's body.
     */
    orderClasses: boolean;
    /**
     * Reverse the initial data order when `desc` ordering
     */
    orderDescReverse: boolean;
    /**
     * Ordering to always be applied to the table.
     */
    orderFixed: Order | Order[] | {
        pre: Order | Order[];
        post: Order | Order[];
    };
    /**
     * Multiple column ordering ability control.
     */
    orderMulti: boolean;
    /**
     * Feature control ordering (sorting) abilities in DataTables.
     */
    ordering: boolean | {
        /**
         * Control the showing of the ordering icons in the table
         * header.
         */
        indicators: boolean;
        /**
         * Control the addition of a click event handler on the table
         * headers to activate ordering.
         */
        handler: boolean;
    };
    /**
     * Change the initial page length (number of rows per page).
     */
    pageLength: number;
    /**
     * Enable or disable table pagination.
     */
    paging: boolean;
    /**
     * Pagination button display options.
     *
     * @deprecated Use the `paging` feature options
     */
    pagingType: string;
    /**
     * Pre-draw callback.
     */
    preDrawCallback: FunctionPreDrawCallback | null;
    /**
     * Feature control the processing indicator.
     */
    processing: boolean;
    /**
     * Display component renderer types.
     */
    renderer: null | string | ConfigRenderer;
    /**
     * Retrieve an existing DataTables instance.
     */
    retrieve: boolean;
    /**
     * Row draw callback.
     */
    rowCallback: FunctionRowCallback | null;
    /**
     * Data property name that DataTables will use to set <tr> element DOM IDs.
     */
    rowId: string;
    /**
     * Allow the table to reduce in height when a limited number of rows are
     * shown.
     */
    scrollCollapse: boolean;
    /**
     * Horizontal scrolling.
     */
    scrollX: string | boolean;
    /**
     * Vertical scrolling.
     */
    scrollY: string | false;
    /**
     * Set an initial filter in DataTables and / or filtering options.
     */
    search: Partial<SearchOptions>;
    /**
     * Define an initial search for individual columns.
     */
    searchCols: Partial<SearchOptions>[];
    /**
     * Set a throttle frequency for searching.
     */
    searchDelay: number;
    /**
     * Feature control search (filtering) abilities
     */
    searching: boolean;
    /**
     * Set the HTTP method that is used to make the Ajax call for server-side
     * processing or Ajax sourced data.
     *
     * @deprecated The functionality provided by this parameter has now been
     * superseded by that provided through `ajax`, which should be used instead.
     */
    serverMethod: string;
    /**
     * Feature control DataTables' server-side processing mode.
     */
    serverSide: boolean;
    /**
     * Saved state validity duration.
     */
    stateDuration: number;
    /**
     * Callback that defines where and how a saved state should be loaded.
     */
    stateLoadCallback: FunctionStateLoadCallback | null;
    /**
     * State loaded callback.
     *
     * @deprecated Use `stateLoaded` event
     */
    stateLoaded: FunctionStateLoaded | null;
    /**
     * State loaded - data manipulation callback.
     *
     * @deprecated Use `stateLoadParams` event
     */
    stateLoadParams: FunctionStateLoadParams | null;
    /**
     * State saving - restore table state on page reload.
     */
    stateSave: boolean;
    /**
     * Callback that defines how the table state is stored and where.
     */
    stateSaveCallback: FunctionStateSaveCallback | null;
    /**
     * State save - data manipulation callback.
     *
     * @deprecated Use `stateSaveParams` event
     */
    stateSaveParams: FunctionStateSaveParams | null;
    /**
     * Tab index control for keyboard navigation.
     */
    tabIndex: number;
    /** Specify which row is the title row in the header. */
    titleRow: null | number | boolean;
    /** Ability to enable / disable auto type detection. */
    typeDetect: boolean;
}
type LanguageOption = string | {
    [key: string | number]: LanguageOption;
};
interface ConfigLanguage {
    [key: string]: LanguageOption | any;
    /**
     * Remote language loading
     */
    ajax: null | string | DtAjaxOptions | ((settings: Context, callback: (language: JSON) => void) => void);
    /**
     * Strings that are used for WAI-ARIA labels and controls only.
     */
    aria: {
        /**
         * Language string used for WAI-ARIA column orderable label.
         */
        orderable: string;
        /**
         * Language string used for WAI-ARIA column label to alter column's
         * ordering.
         */
        orderableRemove: string;
        /**
         * Language string used for WAI-ARIA column label to alter column's
         * ordering.
         */
        orderableReverse: string;
        paginate: {
            /** WAI-ARIA label for the first pagination button. */
            first: string;
            /** WAI-ARIA label for the last pagination button. */
            last: string;
            /** WAI-ARIA label for the next pagination button. */
            next: string;
            /** WAI-ARIA label for the number pagination buttons. */
            number: string;
            /** WAI-ARIA label for the previous pagination button. */
            previous: string;
        };
    };
    /**
     * Decimal place character.
     */
    decimal: string;
    /**
     * Table has no records string.
     */
    emptyTable: string;
    /**
     * Replacement pluralisation for table data type.
     */
    entries: LanguageOption;
    /**
     * Table summary information display string.
     */
    info: string;
    /**
     * Table summary information string used when the table is empty of records.
     */
    infoEmpty: string;
    /**
     * Appended string to the summary information when the table is filtered.
     */
    infoFiltered: string;
    /**
     * String to append to all other summary information strings.
     */
    infoPostFix: string;
    /**
     * Page length options
     */
    lengthLabels: {
        [key: string | number]: string;
    };
    /**
     * Page length options string.
     */
    lengthMenu: string;
    /**
     * Loading information display string - shown when Ajax loading data.
     */
    loadingRecords: string;
    /** Pagination parameters */
    paginate: {
        /**
         * Label and character for first page button («)
         */
        first: string;
        /**
         * Last page button (»)
         */
        last: string;
        /**
         * Next page button (›)
         */
        next: string;
        /**
         * Previous page button (‹)
         */
        previous: string;
    };
    /**
     * Processing indicator string.
     */
    processing: string;
    /**
     * Search input label
     */
    search: string;
    /**
     * Assign a `placeholder` attribute to the search `input` element
     */
    searchPlaceholder: string;
    /**
     * Thousands separator.
     */
    thousands: string;
    /**
     * URL, Ajax options  from which to get a JSON language file
     *
     * @deprecated Prefer `language.ajax` option.
     */
    url: string;
    /**
     * Table empty as a result of filtering string.
     */
    zeroRecords: string;
}
interface Options extends DeepPartial<Defaults> {
}

/**
 * Structure used to store information about each individual row in DataTables
 */
interface Row {
    /** List of classes for removal */
    addedClasses: string[];
    /** Array of cells for each row */
    cells: Array<TableCellElement>;
    /**
     * Data object from the original data source for the row. This is either
     * an array if using the traditional form of DataTables, or an object if
     * using mData options. The exact type will depend on the passed in
     * data from the data source, or will be an array if using DOM a data
     * source.
     */
    data: any;
    /** Details rows (a single Dom instance with `tr` elements) */
    details: undefined | Dom;
    /** Indicate if the row details should be shown */
    detailsShow: undefined | boolean;
    /** Cached display value */
    displayData: Array<any> | null;
    /**
     * Index in the data array. This saves an indexOf lookup when we have the
     * object, but want to know the index
     */
    idx: number;
    /**
     * Sorting data cache - this array is ostensibly the same length as the
     * number of columns (although each index is generated only as it is
     * needed), and holds the data that is used for sorting each column in the
     * row. We do this cache generation at the start of the sort in order that
     * the formatting of the sort data need be done only once for each cell per
     * sort. This array should not be read from or written to by anything other
     * than the master sorting methods.
     */
    orderCache: unknown[] | null;
    /**
     * Per cell filtering data cache. As per the sort data cache, used to
     * increase the performance of the filtering in DataTables
     */
    searchCellCache: string[] | null;
    /**
     * Filtering data cache. This is the same as the cell filtering cache, but
     * in this case a string rather than an array. This is easily computed with
     * a join on `dataSearch` array, but is provided as a cache so the join
     * isn't needed on every search (memory traded for performance)
     */
    searchRowCache: string | null;
    /**
     * Denote if the original data source was from the DOM, or the data source
     * object. This is used for invalidating data, so DataTables can
     * automatically read data from the original source, unless uninstructed
     * otherwise.
     */
    src: 'dom' | 'data';
    /** TR element for the row */
    tr: TableRowElement | null;
}
/**
 * `td` / `th` element, extended with a DataTables' specific properties
 */
interface TableCellElement extends HTMLTableCellElement {
    /**
     * Reverse lookup to cell data index
     */
    _DT_CellIndex?: {
        column: number;
        row: number;
    };
}
/**
 * `tr` element, extended with a DataTables' specific properties
 */
interface TableRowElement extends HTMLTableRowElement {
    /**
     * Reverse lookup to data index
     */
    _DT_RowIndex?: number;
}
/**
 * Create a new object that is a row model
 *
 * @param parts Values to assign, otherwise the defaults will be used
 * @returns New object
 */
declare function create$1(parts?: Partial<Row>): Row;

interface IScroll {
    /**
     * When the table is shorter in height than sScrollY, collapse the
     * table container down to the height of the table (when true).
     */
    collapse: boolean | null;
    /**
     * Width of the scrollbar for the web-browser's platform. Calculated
     * during table initialisation.
     */
    barWidth: number;
    /**
     * Viewport width for horizontal scrolling. Horizontal scrolling is
     * disabled if an empty string.
     */
    x: string;
    /**
     * Width to expand the table to when using x-scrolling. Typically you
     * should not need to use this.
     *  @deprecated
     */
    xInner: string;
    /**
     * Viewport height for vertical scrolling. Vertical scrolling is disabled
     * if an empty string.
     */
    y: string | number;
}
interface ISortItem {
    src: number | string;
    col: number;
    dir: string;
    index: number;
    type: string;
    formatter?: Function;
    sorter?: Function;
}
interface HeaderStructureCell {
    cell: HTMLElement;
    unique: boolean;
}
interface HeaderStructure$1 extends Array<HeaderStructureCell> {
    row: HTMLElement;
}
type AjaxDataSrc = string | ((data: any) => any[]);
type FunctionAjaxData = (data: AjaxData, settings: Context) => string | object;
type AjaxFunction = (d: JSON$1, cb: (json: JSON$1) => void, ctx: Context) => void;
interface DtAjaxOptions extends AjaxOptions {
    /**
     * Add or modify data submitted to the server upon an Ajax request.
     */
    data?: object | FunctionAjaxData;
    /**
     * Data property or manipulation method for table data.
     */
    dataSrc?: AjaxDataSrc | {
        /** Mapping for `data` property */
        data: AjaxDataSrc;
        /** Mapping for `draw` property */
        draw: AjaxDataSrc;
        /** Mapping for `recordsTotal` property */
        recordsTotal: AjaxDataSrc;
        /** Mapping for `recordsFiltered` property */
        recordsFiltered: AjaxDataSrc;
    };
    /** Format to submit the data parameters as in the Ajax request */
    submitAs?: 'http' | 'json';
}
interface Features {
    /**
     * Flag to say if DataTables should automatically try to calculate the
     * optimum table and columns widths (true) or not (false).
     */
    autoWidth: boolean;
    /**
     * Delay the creation of TR and TD elements until they are actually needed
     * by a driven page draw. This can give a significant speed increase for
     * Ajax source and JavaScript source data, but makes no difference at all
     * for DOM and server-side processing tables.
     */
    deferRender: boolean;
    /**
     * Used only for compatibility with DT1
     * @deprecated
     */
    info: boolean;
    /**
     * Used only for compatibility with DT1
     * @deprecated
     */
    lengthChange: boolean;
    /**
     * Apply a class to the columns which are being sorted to provide a
     * visual highlight or not. This can slow things down when enabled since
     * there is a lot of DOM interaction.
     */
    orderClasses: boolean;
    /**
     * Multi-column sorting
     */
    orderMulti: boolean;
    /**
     * Sorting enablement flag.
     */
    ordering: boolean;
    /**
     * Pagination enabled or not. Note that if this is disabled then length
     * changing must also be disabled.
     */
    paging: boolean;
    /**
     * Processing indicator enable flag whenever DataTables is enacting a user
     * request - typically an Ajax request for server-side processing.
     */
    processing: boolean;
    /**
     * Enable filtering on the table or not. Note that if this is disabled then
     * there is no filtering at all on the table.
     */
    searching: boolean;
    /**
     * Server-side processing enabled flag - when enabled DataTables will
     * get all data from the server for every draw - there is no filtering,
     * sorting or paging done on the client-side.
     */
    serverSide: boolean;
    /**
     * State saving enablement flag.
     */
    stateSave: boolean;
}
/**
 * DataTables settings class. This holds all the information needed for a
 * given table, including configuration and data. Devs do not interact with
 * this class directly and it is considered to be private - its properties
 * are NOT a public API. The `DataTable` or `DataTable.Api` instances are to
 * be used instead.
 */
interface Context {
    ajax: null | string | DtAjaxOptions | AjaxFunction;
    /** Data submitted as part of the last Ajax request */
    ajaxData: AjaxData | string;
    /** Note if draw should be blocked while getting data */
    ajaxDataGet: boolean;
    api: any;
    /** Browser support parameters */
    browser: {
        /** Browser scrollbar width */
        barWidth: number;
        /**
         * Determine if the vertical scrollbar is on the right or left of the
         * scrolling container - needed for rtl language layout, although not
         * all browsers move the scrollbar (Safari).
         */
        scrollbarLeft: boolean;
    };
    callbacks: {
        /**
         * Destroy callback functions - for plug-ins to attach themselves to the
         * destroy so they can clean up markup and events.
         */
        destroy: Function[];
        /** Array of callback functions for draw callback functions */
        draw: FunctionDrawCallback[];
        /** Callback function for the footer on each draw. */
        footer: Function[];
        /** Callback functions for the header on each draw. */
        header: Function[];
        /** Callback functions for when the table has been initialised. */
        init: Function[];
        /**
         * Callback functions for just before the table is redrawn. A return of
         * false will be used to cancel the draw.
         */
        preDraw: Function[];
        /** Callback functions array for every time a row is inserted (i.e. on a
         * draw). */
        row: Function[];
        /** Array of callback functions for row created function */
        rowCreated: Function[];
        /**
         * Callbacks for modifying the settings that have been stored for state
         * saving prior to using the stored values to restore the state.
         */
        stateLoadParams: Function[];
        /**
         * Callbacks for operating on the settings object once the saved state
         * has been loaded
         */
        stateLoaded: Function[];
        /**
         * Callbacks for modifying the settings to be stored for state saving,
         * prior to saving state.
         */
        stateSaveParams: Function[];
    };
    caption: string;
    captionNode: HTMLTableCaptionElement | null;
    /** The classes to use for the table */
    classes: any;
    colgroup: Dom;
    /** Store information about each column that is in use */
    columns: Settings[];
    /** Keep a record of the last size of the container, so we can skip
     * duplicates */
    containerWidth: number;
    /** Row data information */
    data: (Row | null)[];
    /** Delay loading of data */
    deferLoading: boolean;
    /** Whether the table is currently being destroyed */
    destroying: boolean;
    /** If restoring a table - we should restore its width */
    destroyWidth: number;
    /** Array of indexes which are in the current display (after filtering etc)
     * */
    display: number[];
    /** Array of indexes for display - no filtering */
    displayMaster: number[];
    /** Paging start point - display index */
    displayStart: number;
    displayStartInit: number;
    /** Indicate if a redraw is being done - useful for Ajax */
    doingDraw: boolean;
    /** Dictate the positioning of DataTables' control elements */
    dom: null | string;
    /** Counter for the draws that the table does. Also used as a tracker for
     * server-side processing */
    drawCount: number;
    /** Draw index (iDraw) of the last error when parsing the returned data */
    drawError: number;
    drawHold: boolean | undefined;
    fastData: (row: number, column: number, type: string) => any;
    /** Feature enablement. */
    features: Features;
    /** Store information about the table's footer */
    footer: HeaderStructure$1[];
    /** Format numbers for display. */
    formatNumber: FunctionFormatNumber;
    /** Store information about the table's header */
    header: HeaderStructure$1[];
    /** Map of row ids to data indexes */
    ids: Record<string, Row>;
    infoEl: Dom;
    /** Initialisation object that is used for the table */
    init: Options;
    initDone: boolean;
    /** Indicate if all required information has been read in */
    initialised: boolean;
    /** The DataTables object for this table */
    instance: DataTableDom;
    /** The last jQuery XHR object that was used for server-side data gathering.
     * */
    jqXHR: XMLHttpRequest;
    /** JSON returned from the server in the last Ajax request */
    json: JSON$1;
    /** Language information for the table. */
    language: ConfigLanguage;
    /** Last applied sort */
    lastOrder: any[];
    layout: Layout;
    /**
     * List of options that can be used for the user selectable length menu.
     * @deprecated
     */
    lengthMenu: any[];
    loadingState: boolean;
    /** Sorting that is applied to the table. */
    order: OrderState[];
    /**
     * Indicate that if multiple rows are in the header and there is more than
     * one unique cell per column.
     * @deprecated Replaced by titleRow
     */
    orderCellsTop: null | boolean;
    /** Reverse the initial order of the data set on desc ordering */
    orderDescReverse: boolean;
    /** Sorting that is always applied to the table. */
    orderFixed: any;
    /** Default ordering listener */
    orderHandler: boolean;
    /** Show / hide ordering indicators in headers */
    orderIndicators: boolean;
    /** Paging display length */
    pageLength: number;
    /**
     * Number of paging controls on the page.
     * @deprecated
     */
    pagingControls: number;
    /**
     * Which type of pagination should be used.
     * @deprecated
     */
    pagingType: string;
    /**
     * Server-side processing - records in the current display set (after
     * filtering).
     */
    recordsDisplay: number;
    /**
     * Server-side processing - records in the result set (before filtering).
     */
    recordsTotal: number;
    renderer: any;
    /** ResizeObserver for the container div */
    resizeObserver: ResizeObserver | null;
    reszEvt: boolean;
    /** Data location where to store a row's id */
    rowId: string;
    /** Function used to get a row's id from the row's data */
    rowIdFn: GetFunction;
    rowReadObject: boolean;
    /** Scrolling settings for a table. */
    scroll: IScroll;
    scrollBarVis: boolean;
    /** DIV container for the body scrolling table if scrolling */
    scrollBody: Dom;
    /** Store for default api searches */
    searches: {
        [name: string]: SearchObject;
    };
    /** Initialisation search options - legacy config support only */
    searchCols: SearchOptions[];
    /** Search delay (in mS) */
    searchDelay: number;
    /** Store for named searches */
    searchesFixed: {
        [columns: string]: {
            [name: string]: SearchObject;
        };
    };
    /** DIV container for the footer scrolling table if scrolling */
    scrollFoot: Dom;
    /** DIV container for the footer scrolling table if scrolling */
    scrollHead: Dom;
    /**
     * Send the XHR HTTP method.
     * @deprecated
     */
    serverMethod: HttpMethod | null;
    sortDetails: ISortItem[];
    /** The state duration (for `stateSave`) in seconds. */
    stateDuration: number;
    stateLoadCallback: (ctx: Context) => Partial<State>;
    /** State that was loaded. Useful for back reference */
    stateLoaded: StateLoad | null;
    stateSaveCallback: (ctx: Context, data: any) => void;
    /** State that was saved. Useful for back reference */
    stateSaved: State | null;
    /** The TABLE node for the main table */
    table: HTMLElement;
    /** tabindex attribute value for keyboard navigation. */
    tabIndex: number;
    /** Cache the table ID for quick access */
    tableId: string;
    /** Cache the wrapper node (contains all DataTables controlled elements) */
    tableWrapper: Element;
    /** Permanent ref to the tbody element */
    tbody: HTMLElement;
    /** Permanent ref to the tfoot element - if it exists */
    tfoot: HTMLElement;
    /** Permanent ref to the thead element */
    thead: HTMLElement;
    /** Title row indicator */
    titleRow: any;
    /** Allow auto type detection */
    typeDetect: boolean;
    /** Unique identifier for each instance of the DataTables object. */
    unique: string;
    /**
     * Flag for draw callback to check if filtering was done.
     * @deprecated
     */
    wasFiltered: boolean;
    /**
     * Flag for draw callback to check if sorting was done.
     * @deprecated
     */
    wasOrdered: boolean;
    /** Window resize handler for older browsers */
    windowResizeCb: () => void;
}
/**
 * Create a new context object
 *
 * @param parts Values to assign, otherwise the defaults will be used
 * @returns New object
 */
declare function create(parts?: Partial<Context>): Context;

interface BrowserInfo {
    barWidth: number;
    scrollbarLeft: boolean;
}

declare const features: any;
declare const legacy: any[];

type RegisterCallback<T> = (settings: Context, options: T) => HTMLElement | Dom | null;
/**
 * Create a new feature that can be used for layout
 *
 * @param name The name of the new feature.
 * @param cb A function that will create the elements and event listeners for
 * the feature being added.
 * @param legacyChar
 */
declare function register$1<T>(name: string, cb: RegisterCallback<T>, legacyChar?: string): void;

/**
 * Compute what number buttons to show in the paging control
 *
 * @param page Current page
 * @param pages Total number of pages
 * @param buttons Target number of number buttons
 * @param addFirstLast Indicate if page 1 and end should be included
 * @returns Buttons to show
 */
declare function pagingNumbers(page: number, pages: number, buttons: number, addFirstLast: boolean): (string | number)[];
declare const _default$2: {
    simple: () => string[];
    full: () => string[];
    numbers: () => string[];
    simple_numbers: () => string[];
    full_numbers: () => string[];
    first_last: () => string[];
    first_last_numbers: () => string[];
    _numbers: typeof pagingNumbers;
    numbers_length: number;
};

interface ILayoutRow {
    id?: string;
    className?: string;
    full?: ILayoutCell;
    start?: ILayoutCell;
    end?: ILayoutCell;
    rowNum?: number;
}
interface ILayoutCell {
    id?: string;
    className?: string;
    items: Array<any>;
    contents: Array<Element>;
    table?: boolean;
}

type IRendererHeader = (ctx: Context, cell: Dom, classNames: typeof _default$3) => void;
interface IPagingButton {
    display: Element;
    clicker: Element;
}
type IRendererFooter = (ctx: Context, cell: Dom, classNames: typeof _default$3) => void;
type IRendererLayout = (ctx: Context, container: Dom, items: ILayoutRow) => void;
type IRendererPagingButton = (ctx: Context, button: string | number, content: string, active: boolean, disabled: boolean) => IPagingButton;
type IRendererPagingContainer = (ctx: Context, buttons: Element[]) => Element | Element[];
interface IRendererCollection<T> {
    [specific: string]: T;
    _: T;
}
interface IRenderers {
    footer: IRendererCollection<IRendererFooter>;
    header: IRendererCollection<IRendererHeader>;
    layout: IRendererCollection<IRendererLayout>;
    pagingButton: IRendererCollection<IRendererPagingButton>;
    pagingContainer: IRendererCollection<IRendererPagingContainer>;
}
declare function displayRowCells(items: ILayoutRow, fn: (position: 'start' | 'end' | 'full', cell: ILayoutCell) => void): void;

type DataTypeDetectFn = (data: any, context: Context) => boolean | string | null;
type DataTypeDetectInitFn = (context: Context, col: Settings, index: number) => boolean | string | null;
type DataTypeDetect = DataTypeDetectFn | {
    oneOf?: DataTypeDetectFn;
    allOf: DataTypeDetectFn;
    init?: DataTypeDetectInitFn;
};
type DataTypeSortFn = (dataA: any, dataB: any) => number;
type DataTypeSortPreFn = (data: any, settings: Context) => any;
type DataTypeOrder = {
    asc?: DataTypeSortFn;
    desc?: DataTypeSortFn;
    pre?: DataTypeSortPreFn;
};
type DataTypeSearchFn = (input: any) => any;
interface DataType {
    className: string;
    detect: DataTypeDetect;
    order: DataTypeOrder;
    render: GetFunction;
    search: DataTypeSearchFn;
}
interface TypeStore {
    /** Automatic column class assignment */
    className: Record<string, string>;
    /** Type detection functions. */
    detect: DataTypeDetect[];
    /** Automatic renderer assignment */
    render: Record<string, any>;
    /** Type based search formatting. */
    search: Record<string, DataTypeSearchFn>;
    /** Type based ordering. */
    order: Record<string, DataTypeSortFn | DataTypeSortPreFn | undefined>;
}
declare const store: TypeStore;
declare function register(name: string): DataType;
declare function register(name: string, prop: 'className', className: string): void;
declare function register(name: string, prop: 'detect', detect: DataTypeDetect): void;
declare function register(name: string, prop: 'order', order: DataTypeOrder): void;
declare function register(name: string, prop: 'render', render: any): void;
declare function register(name: string, prop: 'search', search: DataTypeSearchFn): void;
declare function register(name: string, type: Partial<DataType>): void;

interface ExtOrder {
    [name: string]: ((settings: Context, colIdx: number, visIdx: number) => unknown[]) | undefined;
}
interface ExtButtons {
    [name: string]: any;
}
interface Ext {
    builder: string;
    /**
     * Buttons. For use with the Buttons extension for DataTables. This is
     * defined here so other extensions can define buttons regardless of load
     * order. It is _not_ used by DataTables core.
     */
    buttons: ExtButtons;
    /**
     * ColumnControl buttons and content
     */
    ccContent: Record<string, any>;
    /**
     * Element class names
     */
    classes: typeof _default$3;
    /**
     * Error reporting.
     *
     * How should DataTables report an error. Can take the value 'alert',
     * 'throw', 'none' or a function.
     */
    errMode: 'alert' | 'throw' | 'none' | ((ctx: Context, tn: number | undefined, msg: string) => void);
    /** HTML entity escaping */
    escape: {
        /** When reading data-* attributes for initialisation options */
        attributes: boolean;
    };
    /**
     * Legacy so v1 plug-ins don't throw js errors on load
     */
    feature: typeof legacy;
    /**
     * Feature plug-ins.
     *
     * This is an object of callbacks which provide the features for DataTables
     * to be initialised via the `layout` option.
     */
    features: typeof features;
    /**
     * Row searching.
     *
     * This method of searching is complimentary to the default type based
     * searching, and a lot more comprehensive as it allows you complete control
     * over the searching logic. Each element in this array is a function
     * (parameters described below) that is called for every row in the table,
     * and your logic decides if it should be included in the searching data set
     * or not.
     */
    search: Array<(ctx: Context, cells: string[] | null, rowIdx: number, data: any, displayIdx: number) => boolean>;
    /**
     * Selector extensions
     *
     * The `selector` option can be used to extend the options available for the
     * selector modifier options (`selector-modifier` object data type) that
     * each of the three built in selector types offer (row, column and cell +
     * their plural counterparts). For example the Select extension uses this
     * mechanism to provide an option to select only rows, columns and cells
     * that have been marked as selected by the end user (`{selected: true}`),
     * which can be used in conjunction with the existing built in selector
     * options.
     */
    selector: Record<string, Array<any>>;
    settings: Context[];
    /**
     * Legacy configuration options. Enable and disable legacy options that
     * are available in DataTables.
     */
    legacy: {};
    /**
     * Pagination plug-in methods.
     *
     * Each entry in this object is a function and defines which buttons should
     * be shown by the pagination rendering method that is used for the table.
     * The renderer addresses how the buttons are displayed in the document,
     * while the functions here tell it what buttons to display. This is done by
     * returning an array of button descriptions (what each button will do).
     */
    pager: typeof _default$2;
    renderer: IRenderers;
    /**
     * Rendering helper function exposed for use by the styling integrations.
     */
    rendererDisplayRowCells: typeof displayRowCells;
    /**
     * Ordering plug-ins - custom data source
     *
     * The extension options for ordering of data available here is
     * complimentary to the default type based ordering that DataTables
     * typically uses. It allows much greater control over the data that is
     * being used to order a column, but is necessarily therefore more complex.
     */
    order: ExtOrder;
    /**
     * Type based plug-ins.
     *
     * Each column in DataTables has a type assigned to it, either by automatic
     * detection or by direct assignment using the `type` option for the column.
     * The type of a column will effect how it is ordering and search (plug-ins
     * can also make use of the column type if required).
     */
    type: typeof store;
    /**
     * Unique DataTables instance counter
     */
    _unique: number;
    version: string;
}
/**
 * DataTables extensions
 *
 * This namespace acts as a collection area for plug-ins that can be used to
 * extend DataTables capabilities. Indeed many of the build in methods
 * use this method to provide their own capabilities (sorting methods for
 * example).
 *
 * Note that this namespace is aliased to `jQuery.fn.dataTableExt` for legacy
 * reasons
 */
declare const ext: Ext;

type DateTimeRenderer = (d: any, type: string) => any;
type NumberRenderer = {
    display: (d: unknown) => unknown;
};
type TextRenderer = {
    display: (d: any) => string;
    filter: (d: any) => string;
};
/**
 * Register a date / time format for DataTables to use.
 *
 * @param format The date / time format to detect data in. Please refer to the
 *   Moment.js or Luxon document for the full list of tokens, depending on which
 *   of the two libraries you are using.
 * @param locale The locale to pass to Moment.js / Luxon.
 */
declare function datetime(format: string, locale?: string): void;

declare const _default$1: {
    Column: typeof Settings;
    Row: typeof create$1;
    Search: typeof create$2;
    Settings: typeof create;
};

/**
 * Flatten an array
 *
 * Surprisingly this is faster than [].concat.apply
 * https://jsperf.com/flatten-an-array-loop-vs-reduce/2
 *
 * @param out Array to write to
 * @param val Source array, or single value
 * @returns Flattened array
 */
declare function flatten(out: any[], val: any): any[];
declare function intersection(a1: any[], a2: any[]): any[];
/**
 * Pluck items from an array of objects, or from a nested array of objects
 *
 * @param a Array to get values from
 * @param prop Property to read values from
 * @param prop2 Inner property to get values from if a 2D array
 * @returns Array of read values
 */
declare function pluck(a: any[], prop: string | number, prop2?: string | number): any[];
/**
 * Basically the same as _pluck, but rather than looping over the source array we use `order`
 * as the indexes to pick from the source array
 *
 * @param a Array to get values from
 * @param order Indexes to pick
 * @param prop Property to read values from
 * @param prop2 Inner property to get values from if a 2D array
 * @returns Array of read values
 */
declare function pluckOrder(a: any[], order: number[], prop: string, prop2?: string | number): any[];
/**
 * Create an array with a list of numbers, in sequence starting from 0 for the length given
 *
 * @param len Array size
 * @returns Array with the sequence of numbers
 */ declare function range(len: number): number[];
/**
 * Create an array with a list of numbers, in sequence from the start to the end numbers given.
 *
 * @param start Start number (inclusive)
 * @param end End number (not inclusive)
 */
declare function range(start: number, end: number): number[];
/**
 * Remove all falsy values from an array
 *
 * @param a Source array
 * @returns A new array, with empty values removed
 */
declare function removeEmpty(a: any[]): any[];
/**
 * Join data from an array, but only for specific columns.
 *
 * Performance testing for this available here:
 * https://jsperf.app/vejijo/2/preview.
 *
 * @param src Data source array to pick from
 * @param use Indexes we want from the array
 * @returns Joined string
 */
declare function selectiveJoin(src: string[], use: number[] | number): string;
/**
 * Find the unique elements in a source array.
 *
 * @param src Source array
 * @return Array of unique items
 */
declare function unique(src: any[]): any[];

declare const array_flatten: typeof flatten;
declare const array_intersection: typeof intersection;
declare const array_pluck: typeof pluck;
declare const array_pluckOrder: typeof pluckOrder;
declare const array_range: typeof range;
declare const array_removeEmpty: typeof removeEmpty;
declare const array_selectiveJoin: typeof selectiveJoin;
declare const array_unique: typeof unique;
declare namespace array {
  export {
    array_flatten as flatten,
    array_intersection as intersection,
    array_pluck as pluck,
    array_pluckOrder as pluckOrder,
    array_range as range,
    array_removeEmpty as removeEmpty,
    array_selectiveJoin as selectiveJoin,
    array_unique as unique,
  };
}

/**
 * Get integer value
 *
 * @param s Value
 * @returns Int, or null if not a number
 */
declare function intVal(s: any): number | null;
declare function numToDecimal<T>(num: T, decimalPoint: string): string | T;

declare const conv_intVal: typeof intVal;
declare const conv_numToDecimal: typeof numToDecimal;
declare namespace conv {
  export {
    conv_intVal as intVal,
    conv_numToDecimal as numToDecimal,
  };
}

/**
 * Create a function that will read data a common data point from different (but same structure)
 * data objects. This is primarily used to get data for a specific cell in a single column, but it
 * can also be used in other places, such as when using JSON notation.
 *
 * @param dataPoint The data point to get
 * @returns Function to get a data point's value from a source.
 */
declare function get(dataPoint: string | number | null | GetFunction | PlainObject): GetFunction;
/**
 * Write a value into an existing data store
 *
 * @param dataPoint The data point to write to
 */
declare function set(dataPoint: string | number | null | GetFunction | PlainObject): SetFunction;

declare const data_get: typeof get;
declare const data_set: typeof set;
declare namespace data {
  export {
    data_get as get,
    data_set as set,
  };
}

/**
 * Set the libraries that DataTables uses, or the global objects.
 * Note that the arguments can be either way around (legacy support)
 * and the second is optional. See docs.
 */
declare function export_default(arg1: any, arg2?: any): any;

declare function arrayLike(test: any): any;
/**
 * Determine if the input is a Dom instance
 *
 * @param input Value to check
 * @returns true if it is a Dom instance, false otherwise
 */
declare function dom<T = Dom>(input: unknown): input is T;
/**
 * Determine if the input is an HTML element
 *
 * @param input Value to check
 * @returns true if an HTML element was passed in
 */
declare function element<T = HTMLElement>(input: unknown): input is T;
/**
 * Check if a value is empty or not. Note that a string with `-` is considered
 * empty
 *
 * @param d Value to check
 * @returns `true` if empty, `false` otherwise
 */
declare function empty<T>(d: T): boolean;
/**
 * Check if a string is HTML. Note that a string without HTML in it can be
 * considered to be HTML still!
 *
 * @todo Can we drop this?
 * @param d
 * @returns
 */
declare function html<T>(d: T): boolean;
/**
 * Is a string a number surrounded by HTML?
 *
 * @param d Value to check
 * @param decimalPoint Decimal place character
 * @param formatted Consider formatted numbers
 * @param allowEmpty Allow empty to be considered as a number
 * @returns True if a number, null otherwise
 */
declare function htmlNum(d: any, decimalPoint: string, formatted: boolean, allowEmpty: boolean): true | null;
/**
 * Determine if an input is a jQuery instance
 *
 * @param input Value to check
 * @returns true if it is a jQuery instance, false otherwise
 */
declare function jquery(input: any): any;
/**
 * Check if a given value is numeric, taking into account if it might be
 * formatted or uses a decimal point that is not a period.
 *
 * @param d Value to check
 * @param decimalPoint DP character
 * @param formatted Allow the number to be formatted or not
 * @param allowEmpty Allow an empty value to be considered a number
 * @returns `true` if numeric
 */
declare function num(d: any, decimalPoint?: string, formatted?: boolean, allowEmpty?: boolean): boolean;
/**
 * Determine if a value is a plain object or not
 *
 * @param value Value to check
 * @returns true if is a plain object, otherwise false
 */
declare function plainObject<T = Record<string, any>>(value: unknown): value is T;

declare const is_arrayLike: typeof arrayLike;
declare const is_dom: typeof dom;
declare const is_element: typeof element;
declare const is_empty: typeof empty;
declare const is_html: typeof html;
declare const is_htmlNum: typeof htmlNum;
declare const is_jquery: typeof jquery;
declare const is_num: typeof num;
declare const is_plainObject: typeof plainObject;
declare namespace is {
  export {
    is_arrayLike as arrayLike,
    is_dom as dom,
    is_element as element,
    is_empty as empty,
    is_html as html,
    is_htmlNum as htmlNum,
    is_jquery as jquery,
    is_num as num,
    is_plainObject as plainObject,
  };
}

/**
 * Object iteration function, executing a callback for each key in the object
 *
 * @param input Input object
 * @param fn Function to execute
 */
declare function each<T>(input: Record<string, T> | null | undefined, fn: (key: string, val: T, counter: number) => void): void;
/**
 * Merge the contents of two or more objects into the first object.
 *
 * @param out Object to be assigned the properties
 * @param inputs Objects to take the values from
 * @returns The `output`, just for convenience - output === the return.
 */
declare function assign<T>(out: Record<string, any>, ...inputs: Array<Record<string, any>>): T;
/**
 * Deep merge the contents of two or more objects into the first object. This
 * breaks references for both objects and array.
 *
 * @param out Object to be assigned the properties
 * @param inputs Objects to take the values from
 * @returns The `output`, just for convenience - output === the return.
 */
declare function assignDeep<T>(out: Record<string, any>, ...inputs: Array<Record<string, any> | undefined>): T;
/**
 * Deep merge objects, but shallow copy arrays. The reason we need to do this,
 * is that we don't want to deep copy array init values (such as aaSorting)
 * since the dev wouldn't be able to override them, but we do want to deep copy
 * arrays.
 *
 * @param out Object to extend
 * @param extender Object from which the properties will be applied to out
 * @param breakRefs If true, then arrays will be sliced to take an independent
 *   copy with the exception of the `data` or `aaData` parameters if they are
 *   present. This is so you can pass in a collection to DataTables and have
 *   that used as your data source without breaking the references
 * @returns out Reference, just for convenience - out === the return.
 * @todo This doesn't take account of arrays inside the deep copied objects.
 */
declare function assignDeepObjects<T>(out: Record<string, any>, extender: Record<string, any>, breakRefs?: boolean): T;
/**
 * Map entries to an array
 *
 * @param obj In object
 * @param fn Map transform function. Same signature as `each`
 * @returns Result
 */
declare function map<TResult, TObj>(obj: Record<string, TObj>, fn: (key: string, val: TObj) => TResult): TResult[];

declare const object_assign: typeof assign;
declare const object_assignDeep: typeof assignDeep;
declare const object_assignDeepObjects: typeof assignDeepObjects;
declare const object_each: typeof each;
declare const object_map: typeof map;
declare namespace object {
  export {
    object_assign as assign,
    object_assignDeep as assignDeep,
    object_assignDeepObjects as assignDeepObjects,
    object_each as each,
    object_map as map,
  };
}

declare const reFormattedNumeric: RegExp;
declare const reHtml: RegExp;
declare const reRegexCharacters: RegExp;
declare const reDate: RegExp;
declare const reNewLines: RegExp;
declare const isoTimezone: RegExp;

declare const regex_isoTimezone: typeof isoTimezone;
declare const regex_reDate: typeof reDate;
declare const regex_reFormattedNumeric: typeof reFormattedNumeric;
declare const regex_reHtml: typeof reHtml;
declare const regex_reNewLines: typeof reNewLines;
declare const regex_reRegexCharacters: typeof reRegexCharacters;
declare namespace regex {
  export {
    regex_isoTimezone as isoTimezone,
    regex_reDate as reDate,
    regex_reFormattedNumeric as reFormattedNumeric,
    regex_reHtml as reHtml,
    regex_reNewLines as reNewLines,
    regex_reRegexCharacters as reRegexCharacters,
  };
}

type TStripHtml = <T>(val: T, replacement?: string) => T;
type TEscapeHtml = <T>(val: T) => T;
type TNormalize = <T>(val: T, both?: boolean) => T;
/**
 * Escape regular expression characters in a string
 *
 * @param val String to escape
 * @returns String with regex characters escaped
 */
declare function escapeRegex(val: string): string;
/**
 * Escape HTML entities in a string or string[]. No action on other types
 *
 * @param mixed Value to escape HTML entities in
 * @returns The escaped string (or original if not a string)
 */
declare function escapeHtml<T>(mixed: T): T;
/**
 * Set the function to use for HTML entity encoding from a string
 *
 * @param mixed HTML escape function
 */
declare function escapeHtml<T = TEscapeHtml>(mixed: T): void;
/**
 * Normalise a string by removing diacritic characters (used for search and
 * sort)
 *
 * @param mixed
 * @param both Flag to indicate if both the original and normalised should be
 *   included in the return.
 * @returns Normalised value
 */
declare function normalize(mixed: string, both?: boolean): string;
/**
 * Set the function to use for string normalisation
 *
 * @param fn Normalisation function
 */
declare function normalize<T = TNormalize>(fn: T): void;
/**
 * Strip HTML from a string, if a string is given, otherwise no action
 *
 * @param mixed Value to remove HTML from
 * @param replacement
 * @returns The stripped value (or original if not a string)
 */
declare function stripHtml<T>(mixed: T, replacement?: string): T;
/**
 * Set the function to use for stripping HTML from a string
 *
 * @param fn HTML strip function
 */
declare function stripHtml<T = TStripHtml>(fn: T): void;

declare const string_escapeHtml: typeof escapeHtml;
declare const string_escapeRegex: typeof escapeRegex;
declare const string_normalize: typeof normalize;
declare const string_stripHtml: typeof stripHtml;
declare namespace string {
  export {
    string_escapeHtml as escapeHtml,
    string_escapeRegex as escapeRegex,
    string_normalize as normalize,
    string_stripHtml as stripHtml,
  };
}

/**
 * Debounce a function
 *
 * @param fn Function to be called
 * @param freq Call frequency in mS
 * @returns Wrapped function
 */
declare function debounce(fn: () => void, freq?: number): () => void;
declare function debounce<T1>(fn: (a1: T1) => void, freq?: number): (a1: T1) => void;
declare function debounce<T1, T2>(fn: (a1: T1, a2: T2) => void, freq?: number): (a1: T1, a2: T2) => void;
declare function debounce<T1, T2, T3>(fn: (a1: T1, a2: T2, a3: T3) => void, timeout?: number): (a1: T1, a2: T2, a3: T3) => void;
/**
 * Throttle the calls to a function. Arguments and context are maintained
 * for the throttled function.
 *
 * @param fn Function to be called
 * @param freq Call frequency in mS
 * @returns Wrapped function
 */
declare function throttle(fn: () => void, freq?: number): () => void;
declare function throttle<T1>(fn: (a1: T1) => void, freq?: number): (a1: T1) => void;
declare function throttle<T1, T2>(fn: (a1: T1, a2: T2) => void, freq?: number): (a1: T1, a2: T2) => void;
declare function throttle<T1, T2, T3>(fn: (a1: T1, a2: T2, a3: T3) => void, freq?: number): (a1: T1, a2: T2, a3: T3) => void;

declare const timer_debounce: typeof debounce;
declare const timer_throttle: typeof throttle;
declare namespace timer {
  export {
    timer_debounce as debounce,
    timer_throttle as throttle,
  };
}

/**
 * Provide a common method for plug-ins to check the version of DataTables being
 * used, in order to ensure compatibility.
 *
 * @param version1 Version string to check for, in the format "X.Y.Z". Note that
 *   the formats "X" and "X.Y" are also acceptable.
 * @param version2 As above, but optional. If not given the current DataTables
 *   version will be used.
 * @returns true if this version of DataTables is greater or equal to the
 *   required version, or false if this version of DataTales is not suitable
 */
declare function check(version1: string, version2?: string): boolean;

declare const version_check: typeof check;
declare namespace version {
  export {
    version_check as check,
  };
}

declare const _default: {
    ajax: typeof ajax;
    array: typeof array;
    conv: typeof conv;
    data: typeof data;
    /** @see timer.debounce */
    debounce: typeof debounce;
    /** @see string.normalize */
    diacritics: typeof normalize;
    /** @see string.escapeHtml */
    escapeHtml: typeof escapeHtml;
    /** @see string.escapeRegex */
    escapeRegex: typeof escapeRegex;
    external: typeof export_default;
    /** @see data.get */
    get: typeof get;
    is: typeof is;
    object: typeof object;
    regex: typeof regex;
    /** @see data.set */
    set: typeof set;
    string: typeof string;
    /** @see string.stripHtml */
    stripHtml: typeof stripHtml;
    /** @see timer.throttle */
    throttle: typeof throttle;
    timer: typeof timer;
    /** @see array.unique */
    unique: typeof unique;
    version: typeof version;
};

/**
 * CommonJS factory function pass through. This will check if the arguments
 * given are a window object or a jQuery object. If so they are set accordingly.
 *
 * @param root Window
 * @param jq jQuery
 * @returns Indicator
 */
declare function factory(root: Window & typeof globalThis, jq: any): boolean;

type Unpacked<T> = T extends (infer U)[] ? U : T extends (...args: any[]) => infer U ? U : T extends Promise<infer U> ? U : T;
type DomSelector = string | Node | HTMLElement | JQuery | Dom;
type InstSelector = DomSelector | Context | Api | InstSelector[];
type RowIdx = number;
type RowSelector<T> = RowIdx | string | Node | Dom | JQuery | ((idx: RowIdx, data: T, node: Node | null) => boolean) | RowSelector<T>[] | Api<number> | null;
type ColumnIdx = number;
type ColumnSelector = ColumnIdx | string | Node | JQuery | ((idx: ColumnIdx, data: any, node: Node) => boolean) | ColumnSelector[] | Api<number> | null;
type CellIdx = {
    row: number;
    column: number;
};
type CellSelector = CellIdx | string | Node | JQuery | ((idx: CellIdx, data: any, node: Node | null) => boolean) | CellSelector[];
type TableSelector = undefined | number | string | JQuery | TableSelector[];
type CellIdxWithVisible = {
    row: number;
    column: number;
    columnVisible: number;
};
type HeaderStructure = {
    cell: HTMLElement;
    colspan: number;
    rowspan: number;
    title: string;
};
interface DataTableEvent<T = any> extends Event {
    dt: Api<T>;
}
interface ApiConstructor {
    new (content: InstSelector, data?: any): Api;
    (content: InstSelector, data?: any): Api;
    register<T extends Function = Function>(name: string | string[], fn: T): void;
    registerPlural<T extends Function = any>(pluralName: string, singleName: string, fn: T): void;
}
interface Api<T = any> extends ApiScopeable<T, Api> {
}
declare const Api: ApiConstructor;
interface ApiScopeable<T, S> {
    /**
     * @internal
     */
    _newClass: string;
    /**
     * API should be array-like
     */
    [key: number]: T;
    /**
     * Get jquery object
     *
     * @param selector jQuery selector to perform on the nodes inside the
     * table's tbody tag.
     * @param modifier Option used to specify how the content's of the selected
     * columns should be ordered, and if paging or filtering in the table should
     * be taken into account.
     * @returns JQuery object with the matched elements in it's results set
     */
    $(selector: string | Node | Node[] | JQuery, modifier?: SelectorModifier): JQuery;
    /**
     * Ajax Methods
     */
    ajax: ApiAjax;
    /**
     * Get a boolean value to indicate if there are any entries in the API
     * instance's result set (i.e. any data, selected rows, etc).
     *
     * @returns true if there are one or more items in the result set, false
     * otherwise.
     */
    any(): boolean;
    /**
     * Table caption control methods
     */
    caption: ApiCaption;
    /**
     * Cell (single) selector and methods
     */
    cell: ApiCell<T>;
    /**
     * Cells (multiple) selector and methods
     */
    cells: ApiCells<T>;
    /**
     * Clear the table of all data.
     *
     * @returns DataTables Api instance.
     */
    clear(this: S): Api<T>;
    /**
     * Column Methods / object
     */
    column: ApiColumn<T>;
    /**
     * Columns Methods / object
     */
    columns: ApiColumns<T>;
    /**
     * Concatenate two or more API instances together
     *
     * @param a API instance to concatenate to the initial instance.
     * @param b Additional API instance(s) to concatenate to the initial
     * instance.
     * @returns New API instance with the values from all passed in instances
     * concatenated into its result set.
     */
    concat(a: object, ...b: object[]): Api<any>;
    /**
     * The table setting objects that are manipulated by this API instance
     *
     * @private
     */
    context: Context[];
    /**
     * Get the number of entries in an API instance's result set, regardless of
     * multi-table grouping (e.g. any data, selected rows, etc). Since: 1.10.8
     *
     * @returns The number of items in the API instance's result set
     */
    count(): number;
    /**
     * Get the data for the whole table.
     *
     * @returns DataTables Api instance with the data for each row in the result
     * set
     */
    data(this: S): Api<T>;
    /**
     * Destroy the DataTables in the current context.
     *
     * @param remove Completely remove the table from the DOM (true) or leave it
     * in the DOM in its original plain un-enhanced HTML state (default, false).
     * @returns DataTables Api instance
     */
    destroy(this: S, remove?: boolean): Api<T>;
    /**
     * Redraw the DataTables in the current context, optionally updating
     * ordering, searching and paging as required.
     *
     * @param paging This parameter is used to determine what kind of draw
     * DataTables will perform.
     * @returns DataTables Api instance
     */
    draw(this: S, paging?: boolean | string): Api<T>;
    /**
     * Iterate over the contents of the API result set.
     *
     * @param fn Callback function which is called for each item in the API
     * instance result set. The callback is called with three parameters
     * @returns Original API instance that was used. For chaining.
     */
    each(fn: (value: T, index: number, dt: Api<T>) => void): Api<T>;
    /**
     * Reduce an Api instance to a single context and result set.
     *
     * @param idx Index to select
     * @returns New DataTables API instance with the context and result set
     * containing the table and data for the index specified, or null if no
     * matching index was available.
     */
    eq(this: S, idx: number): Api<T>;
    /**
     * Show an error message to the end user / developer through the DataTables
     * logging settings.
     *
     * @param msg Error message to show
     */
    error(this: S, msg: string): Api<T>;
    /**
     * Iterate over the result set of an API instance and test each item,
     * creating a new instance from those items which pass.
     *
     * @param fn Callback function which is called for each item in the API
     * instance result set. The callback is called with three parameters.
     * @returns New API instance with the values from the result set which
     * passed the test in the callback.
     */
    filter(fn: (value: any, index: number, dt: Api<any>) => boolean): Api<T>;
    /**
     * Flatten a 2D array structured API instance to a 1D array structure.
     *
     * @returns New API instance with the 2D array values reduced to a 1D array.
     */
    flatten(): Api<Unpacked<T>>;
    /**
     * Get the underlying data from a DataTable instance.
     *
     * @param idx Data index to get
     */
    get(idx: number): T;
    /**
     * Get a language object
     *
     * @param token The language token to lookup from the language object.
     * @param def The default value to use if the DataTables initialisation has
     * not specified a value. This can be a string for simple cases, or an
     * object for plurals.
     * @param resolve As `false` it indicates that an object should be returned.
     *
     * @returns i18n object
     */
    i18n(this: S, token: string, def: object | string, resolve: false): Record<string, any>;
    /**
     * Look up a language token that was defined in the DataTables' language
     * initialisation object.
     *
     * @param token The language token to lookup from the language object.
     * @param def The default value to use if the DataTables initialisation has
     * not specified a value. This can be a string for simple cases, or an
     * object for plurals.
     * @param numeric If handling numeric output, the number to be presented
     * should be given in this parameter.
     *
     * @returns Resulting internationalised string.
     */
    i18n(this: S, token: string, def: object | string, numeric?: number | string): string;
    /**
     * Determine if an API result set contains a given value.
     *
     * @param find Value to look for
     */
    includes(find: any): boolean;
    /**
     * Find the first instance of a value in the API instance's result set.
     *
     * @param value Value to find in the instance's result set.
     * @returns The index of the item in the result set, or -1 if not found.
     */
    indexOf(value: any): number;
    /**
     * Get the initialisation options used for the table. Since: DataTables
     * 1.10.6
     *
     * @returns Configuration object
     */
    init(): any;
    /**
     * Create a new instance of the DataTables API at the correct level of
     * nesting for the current context.
     *
     * @param context DataTables that are referred to
     * @param data Data for the instance to hold
     * @param newClass Override class target - internal.
     */
    inst<R = Api>(context: InstSelector, data?: T | null, newClass?: string): R;
    /**
     * Iterate over a result set of table, row, column or cell indexes
     *
     * @param type Iterator type
     * @param callback Callback function that is executed on each iteration. For
     * the parameters passed to the function, please refer to the documentation
     * above. As of this is executed in the scope of an API instance which has
     * its context set to only the table in question.
     * @param returns Indicate if the callback function will return values or
     * not. If set to true a new API instance will be returns with the return
     * values from the callback function in its result set. If not set, or false
     * the original instance will be returned for chaining, if no values are
     * returned by the callback method.
     * @returns Original API instance if the callback returns no result (i.e.
     * undefined) or a new API instance with the result set being the results
     * from the callback, in order of execution.
     */
    iterator<R extends Api = Api<any>>(type: 'table', callback: IteratorTable, returns?: boolean): R;
    iterator<R extends Api = Api<any>>(type: 'cell', callback: IteratorCell, returns?: boolean): R;
    iterator<R extends Api = Api<any>>(type: 'column-rows', callback: IteratorColumnRows, returns?: boolean): R;
    iterator<R extends Api = Api<any>>(type: 'column', callback: IteratorColumn, returns?: boolean): R;
    iterator<R extends Api = Api<any>>(type: 'columns', callback: IteratorColumns, returns?: boolean): R;
    iterator<R extends Api = Api<any>>(type: 'row', callback: IteratorRow, returns?: boolean): R;
    iterator<R extends Api = Api<any>>(type: 'rows', callback: IteratorRows, returns?: boolean): R;
    iterator<R extends Api = Api<any>>(type: 'every', callback: IteratorEvery, returns?: boolean): R;
    /**
     * Iterate over a result set of table, row, column or cell indexes
     *
     * @param flatten If true the result set of the returned API instance will
     * be a 1D array (i.e. flattened into a single array). If false (or not
     * specified) each result will be concatenated to the instance's result set.
     * Note that this is only relevant if you are returning arrays from the
     * callback.
     * @param type Iterator type
     * @param callback Callback function that is executed on each iteration. For
     * the parameters passed to the function, please refer to the documentation
     * above. As of this is executed in the scope of an API instance which has
     * its context set to only the table in question.
     * @param returns Indicate if the callback function will return values or
     * not. If set to true a new API instance will be returns with the return
     * values from the callback function in its result set. If not set, or false
     * the original instance will be returned for chaining, if no values are
     * returned by the callback method.
     * @returns Original API instance if the callback returns no result (i.e.
     * undefined) or a new API instance with the result set being the results
     * from the callback, in order of execution.
     */
    iterator<R extends Api = Api<any>>(flatten: boolean, type: 'table', callback: IteratorTable, returns?: boolean): R;
    iterator<R extends Api = Api<any>>(flatten: boolean, type: 'cell', callback: IteratorCell, returns?: boolean): R;
    iterator<R extends Api = Api<any>>(flatten: boolean, type: 'column-rows', callback: IteratorColumnRows, returns?: boolean): R;
    iterator<R extends Api = Api<any>>(flatten: boolean, type: 'column', callback: IteratorColumn, returns?: boolean): R;
    iterator<R extends Api = Api<any>>(flatten: boolean, type: 'columns', callback: IteratorColumns, returns?: boolean): R;
    iterator<R extends Api = Api<any>>(flatten: boolean, type: 'row', callback: IteratorRow, returns?: boolean): R;
    iterator<R extends Api = Api<any>>(flatten: boolean, type: 'rows', callback: IteratorRows, returns?: boolean): R;
    /**
     * Join the elements in the result set into a string.
     *
     * @param separator The string that will be used to separate each element of
     * the result set.
     * @returns Contents of the instance's result set joined together as a
     * single string.
     */
    join(separator: string): string;
    /**
     * Find the last instance of a value in the API instance's result set.
     *
     * @param value Value to find in the instance's result set.
     * @returns The index of the item in the result set, or -1 if not found.
     */
    lastIndexOf(value: any): number;
    /**
     * Number of elements in an API instance's result set.
     */
    length: number;
    /**
     * Iterate over the result set of an API instance, creating a new API
     * instance from the values returned by the callback.
     *
     * @param fn Callback function which is called for each item in the API
     * instance result set. The callback is called with three parameters.
     * @returns New API instance with the values in the result set as those
     * returned by the callback.
     */
    map(fn: (value: any, index: number, dt: Api<any>) => any): Api<any>;
    /**
     * Remove event listeners that have previously been added with on().
     *
     * @param event Event name to remove.
     * @param callback Specific callback function to remove if you want to
     * unbind a single event listener.
     * @returns DataTables Api instance
     */
    off(event: string, callback?: (this: HTMLElement, e: DataTableEvent, ...args: any[]) => void): Api<T>;
    /**
     * Remove event handlers from selected elements
     *
     * @param event Event name to remove.
     * @param selector Element selector
     * @param callback Specific callback function to remove if you want to
     * unbind a single event listener.
     * @returns DataTables Api instance
     */
    off(event: string, selector: string, callback?: (this: HTMLElement, e: DataTableEvent, ...args: any[]) => void): Api<T>;
    /**
     * Table events listener.
     *
     * @param event Event to listen for.
     * @param callback Event handler.
     * @returns DataTables Api instance
     */
    on(event: string, callback: (this: HTMLElement, e: DataTableEvent, ...args: any[]) => void): Api<T>;
    /**
     * Listen for events from selected elements
     *
     * @param event Event to listen for.
     * @param selector Element selector
     * @param callback Event handler.
     * @returns DataTables Api instance
     */
    on(event: string, selector: string, callback: (this: HTMLElement, e: DataTableEvent, ...args: any[]) => void): Api<T>;
    /**
     * Listen for a table event once and then remove the listener.
     *
     * @param event Event to listen for.
     * @param callback Event handler.
     * Listen for events from tables and fire a callback when they occur
     * @returns DataTables Api instance
     */
    one(event: string, callback: (this: HTMLElement, e: DataTableEvent, ...args: any[]) => void): Api<T>;
    /**
     * Listen for events from a selected element and trigger only once then
     * remove the listener.
     *
     * @param event Event to listen for.
     * @param selector Element selector
     * @param callback Event handler.
     * @returns DataTables Api instance
     */
    one(event: string, selector: string, callback: (this: HTMLElement, e: DataTableEvent, ...args: any[]) => void): Api<T>;
    /**
     * Order Methods / object
     */
    order: ApiOrder;
    /**
     * Page Methods / object
     */
    page: ApiPage;
    /**
     * Iterate over the result set of an API instance, creating a new API
     * instance from the values retrieved from the original elements.
     *
     * @param property object property name to use from the element in the
     * original result set for the new result set.
     * @returns New API instance with the values in the result retrieved from
     * the source object properties defined by the property being plucked.
     */
    pluck(property: number | string): Api<any>;
    /**
     * Remove the last item from an API instance's result set.
     *
     * @returns Item removed form the result set (was previously the last item
     * in the result set).
     */
    pop(): T;
    /**
     * Show / hide the processing indicator for the table
     *
     * @param show Flag to indicate if it should show or not
     */
    processing(this: S, show: boolean): Api<any>;
    /**
     * Add one or more items to the end of an API instance's result set.
     *
     * @param value_1 Item to add to the API instance's result set.
     * @param value_2 Additional items to add.
     * @returns The length of the modified API instance
     */
    push(value_1: any, ...value_2: any[]): number;
    /**
     * Determine if the DataTable is ready or not
     */
    ready(this: S): boolean;
    /**
     * Execute a function when the DataTable becomes ready (or immediately if it
     * already is)
     *
     * @param fn Function to execute
     */
    ready(this: S, fn: (this: Api<T>) => void): Api<T>;
    /**
     * Apply a callback function against an accumulator and each element in the
     * Api's result set (left-to-right).
     *
     * @param fn Callback function which is called for each item in the API
     * instance result set. The callback is called with four parameters.
     * @param initialValue Value to use as the first argument of the first call
     * to the fn callback.
     * @returns Result from the final call to the fn callback function.
     */
    reduce(fn: (current: T, value: T, index: number, dt: Api<any>) => T): T;
    reduce(fn: (current: T, value: T, index: number, dt: Api<any>) => T, initialValue: T): T;
    reduce<U>(fn: (current: U, value: T, index: number, dt: Api<any>) => U, initialValue: U): U;
    /**
     * Apply a callback function against and accumulator and each element in the
     * Api's result set (right-to-left).
     *
     * @param fn Callback function which is called for each item in the API
     * instance result set. The callback is called with four parameters.
     * @param initialValue Value to use as the first argument of the first call
     * to the fn callback.
     * @returns Result from the final call to the fn callback function.
     */
    reduceRight(fn: (current: T, value: T, index: number, dt: Api<any>) => T): T;
    reduceRight(fn: (current: T, value: T, index: number, dt: Api<any>) => T, initialValue: T): T;
    reduceRight<U>(fn: (current: U, value: T, index: number, dt: Api<any>) => U, initialValue: U): U;
    /**
     * Reverse the result set of the API instance and return the original array.
     *
     * @returns The original API instance with the result set in reversed order.
     */
    reverse(): Api<T>;
    /**
     * Row Methods / object
     */
    row: ApiRow<T>;
    /**
     * Rows Methods / object
     */
    rows: ApiRows<T>;
    /**
     * Search Methods / object
     */
    search: ApiSearch<T>;
    /**
     * Obtain the table's settings object
     *
     * @returns DataTables API instance with the settings objects for the tables
     * in the context in the result set
     */
    settings(): Api<Context>;
    /**
     * @ignore Internal
     */
    selector: ApiSelector<T>;
    /**
     * Remove the first item from an API instance's result set.
     *
     * @returns Item removed form the result set (was previously the first item
     * in the result set).
     */
    shift(): T;
    /**
     * Create an independent copy of the API instance.
     *
     * @returns DataTables API instance
     */
    slice(): Api<T>;
    /**
     * Sort the elements of the API instance's result set.
     *
     * @param fn This is a standard JavaScript sort comparison function. It
     * accepts two parameters.
     * @returns The original API instance with the result set sorted as defined
     * by the sorting conditions used.
     */
    sort(fn?: (value1: any, value2: any) => number): Api<T>;
    /**
     * Modify the contents of an Api instance's result set, adding or removing
     * items from it as required.
     *
     * @param index Index at which to start modifying the Api instance's result
     * set.
     * @param howMany Number of elements to remove from the result set.
     * @param value_1 Item to add to the result set at the index specified by
     * the first parameter.
     * @param value_2 Additional items to add.
     * @returns An array of the items which were removed. If no elements were
     * removed, an empty array is returned.
     */
    splice(index: number, howMany: number, value_1?: any, ...value_2: any[]): T[];
    /**
     * State methods / object
     */
    state: ApiState<T>;
    /**
     * Select a table based on a selector from the API's context
     *
     * @param tableSelector Table selector.
     * @returns DataTables API instance with selected table in its context.
     */
    table(this: S, tableSelector?: TableSelector): ApiTableMethods<T>;
    /**
     * Select tables based on the given selector
     *
     * @param tableSelector Table selector.
     * @returns DataTables API instance with all tables in the current context.
     */
    tables(this: S, tableSelector?: TableSelector): ApiTablesMethods<T>;
    /**
     * Convert the API instance to a jQuery object, with the objects from the
     * instance's result set in the jQuery result set.
     *
     * @returns jQuery object which contains the values from the API instance's
     * result set.
     */
    to$(): JQuery;
    /**
     * Convert the API instance to a DataTables Dom object, with the objects
     * from the instance's result set in the Dom result set.
     *
     * @returns DataTables Dom object
     */
    toDom(): Dom;
    /**
     * Create a native JavaScript array object from an API instance.
     *
     * @returns JavaScript array which contains the values from the API
     * instance's result set.
     */
    toArray(): T[];
    /**
     * Convert the API instance to a jQuery object, with the objects from the
     * instance's result set in the jQuery result set.
     *
     * @returns jQuery object which contains the values from the API instance's
     * result set.
     */
    toJQuery(): JQuery;
    /**
     * Trigger a DataTables related event.
     *
     * @param name The event name.
     * @param args An array of the arguments to send to the event.
     * @param bubbles Indicate if the event should bubble up the document in the
     *   same way that DOM events usually do, or not. There is a performance
     *   impact for bubbling events.
     * @returns Api instance with `defaultPrevented` for each item in the result
     * set
     */
    trigger(this: S, name: string, args?: any[], bubbles?: boolean): Api;
    /**
     * Create a new API instance containing only the unique items from the
     * elements in an instance's result set.
     *
     * @returns New Api instance which contains the unique items from the
     * original instance's result set, in its own result set.
     */
    unique(): Api<any>;
    /**
     * Add one or more items to the start of an API instance's result set.
     *
     * @param value_1 Item to add to the API instance's result set.
     * @param value_2 Additional items to add.
     * @returns The length of the modified API instance
     */
    unshift(value_1: any, ...value_2: any[]): number;
}
interface ApiCaption {
    /**
     * Get the contents of the `caption` element for the table.
     */
    (this: Api): string;
    /**
     * Set the contents of the `-tag caption` element. If the table doesn't have
     * a `-tag caption` element, one will be created automatically.
     *
     * @param set The value to show in the table's `caption` tag.
     * @param side `top` or `bottom` to set where the table will be shown on the
     *   table. If not given the previous value will be used (can also be set in
     *   CSS).
     * @returns DataTables API instance for chaining.
     */
    (this: Api, set: string, side?: 'top' | 'bottom'): Api;
    /**
     * Get the HTML caption node for the table
     */
    node(this: Api): HTMLElement | null;
}
interface ApiSelector<T = any> {
    /** Row selector used in this instance (if any) */
    rows: RowSelector<T> | undefined;
    /** Column selector used in this instance (if any) */
    cols: ColumnSelector | undefined;
    /** Options modifier used in this instance (if any) */
    opts: SelectorModifier | undefined;
}
interface SelectorModifier {
    /**
     * The order in which the resolved columns should be returned in.
     *
     * * `implied` - the order given in the selector (default)
     * * `index` - column index order
     */
    columnOrder?: 'index' | 'implied';
    /**
     * The order modifier provides the ability to control which order the rows
     * are processed in. Can be one of 'current', 'applied', 'index',
     * 'original', or the column index that you want the order to be applied
     * from.
     */
    order?: 'current' | 'applied' | 'index' | 'original' | number;
    /**
     * The search modifier provides the ability to govern which rows are used by
     * the selector using the search options that are applied to the table.
     * Values: 'none', 'applied', 'removed'
     */
    search?: 'none' | 'applied' | 'removed';
    /**
     * The searchPlaceholder modifier provides the ability to provide
     * informational text for an input control when it has no value.
     */
    searchPlaceholder?: string;
    /**
     * The page modifier allows you to control if the selector should consider
     * all data in the table, regardless of paging, or if only the rows in the
     * currently disabled page should be used. Values: 'all', 'current'
     */
    page?: 'all' | 'current';
    /**
     * @deprecated Use `search` instead
     * @ignore
     */
    filter?: 'none' | 'applied' | 'removed';
    /**
     * Used by the Select extension to indicate if only selected items should be
     * included.
     */
    selected?: boolean;
}
interface ApiSearch<T> extends Api<T> {
    /**
     * Get current search
     *
     * @returns The currently applied global search. This may be an empty string
     * if no search is applied.
     */
    (this: Api): SearchInput<T>;
    /**
     * Set the global search to use on the table. Note this doesn't actually
     * perform the search.
     *
     * @param input Search string to apply to the table.
     * @param regex Treat as a regular expression (true) or not (default,
     * false).
     * @param smart Perform smart search.
     * @param caseInsen Do case-insensitive matching (default, true) or not
     * (false).
     * @returns DataTables API instance
     */
    (this: Api, input: SearchInput<T>, regex?: boolean, smart?: boolean, caseInsen?: boolean): Api<any>;
    /**
     * Set the global search to use on the table. Note this doesn't actually
     * perform the search.
     *
     * @param input Search string to apply to the table.
     * @param options Configuration options for how the search should be
     * performed
     * @returns DataTables API instance
     */
    (this: Api, input: SearchInput<T>, options: Partial<SearchOptions>): Api<any>;
    /**
     * Get a list of the names of searches applied to the table.
     *
     * @returns API instance containing the fixed search terms
     */
    fixed(this: Api): Api<string>;
    /**
     * Get the search term used for the given name.
     *
     * @param name Fixed search term to get.
     * @returns The search term for the name given or undefined if not set.
     */
    fixed(this: Api, name: string): SearchInput<T> | undefined;
    /**
     * Set a search term to apply to the table, using a name to uniquely
     * identify it.
     *
     * @param name Name to give the fixed search term
     * @param search The search term to apply to the table or `null` to delete
     *   an existing search term by the given name.
     * @returns API for chaining
     */
    fixed(this: Api, name: string, search: SearchInput<T> | null): Api<T>;
}
interface ApiPageInfo {
    page: number;
    pages: number;
    start: number;
    end: number;
    length: number;
    recordsTotal: number;
    recordsDisplay: number;
    serverSide: boolean;
}
/**
 * "table" - loop over the context's (i.e. the tables) for the instance
 *
 * @param settings Table settings object
 * @param counter Loop counter
 */
type IteratorTable = (this: Api, settings: Context, counter: number) => any;
/**
 * "cell" - loop over each table and cell in the result set
 *
 * @param settings Table settings object
 * @param rowIndex Row index
 * @param columnIndex Column index
 * @param tableCounter Table counter (outer)
 * @param cellCounter Cell counter (inner)
 */
type IteratorCell = (this: Api, settings: Context, rowIndex: number, columnIndex: number, tableCounter: number, cellCounter: number) => any;
/**
 * "columns" - loop over each item in the result set
 *
 * @param settings Table settings object
 * @param resultItem Result set item
 * @param counter Loop counter
 */
type IteratorColumns = (this: Api, settings: Context, resultItem: any, counter: number) => any;
/**
 * "column" - loop over each table and column in the result set
 *
 * @param settings Table settings object
 * @param columnIndex Column index
 * @param tableCounter Table counter (outer)
 * @param columnCounter Column counter (inner)
 */
type IteratorColumn = (this: Api, settings: Context, columnIndex: number, tableCounter: number, columnCounter: number) => any;
/**
 * "column-rows" - loop over each table, column and row in the result set
 * applying selector-modifier.
 *
 * @param settings Table settings object
 * @param columnIndex Column index
 * @param tableCounter Table counter (outer)
 * @param columnCounter Column counter (inner)
 * @param rowIndexes Row indexes
 */
type IteratorColumnRows = (this: Api, settings: Context, columnIndex: number, tableCounter: number, columnCounter: number, rowIndexes: number[]) => any;
/**
 * "row" - loop over each table and row in the result set
 *
 * @param settings Table settings object
 * @param rowIndex Row index
 * @param tableCounter Table counter (outer)
 * @param rowCounter Row counter (inner)
 */
type IteratorRow = (this: Api, settings: Context, rowIndex: number, tableCounter: number, rowCounter: number) => any;
/**
 * "rows" - loop over each item in the result set
 *
 * @param settings Table settings object
 * @param resultItem Result set item
 * @param counter Loop counter
 */
type IteratorRows = (this: Api, settings: Context, resultItem: any, counter: number) => any;
/**
 * "every" - loop over selected items
 *
 * @param settings Table settings object
 * @param index Data value (number or cell index)
 * @param tableCounter Table counter (outer)
 * @param counter Counter (inner)
 */
type IteratorEvery = (this: Api, settings: Context, index: any, tableCounter: number, counter: number) => any;
interface ApiAjax extends Api<any> {
    /**
     * Get the latest JSON data obtained from the last Ajax request DataTables
     * made
     *
     * @returns JSON object that was last loaded/
     */
    json(this: Api): object;
    /**
     * Get the data submitted by DataTables to the server in the last Ajax
     * request
     *
     * @returns object containing the data submitted by DataTables
     */
    params(this: Api): object;
    /**
     * Reload the table data from the Ajax data source.
     *
     * @param callback Function which is executed when the data as been reloaded
     * and the table fully redrawn.
     * @param resetPaging Reset (default action or true) or hold the current
     * paging position (false).
     * @returns DataTables Api
     */
    reload(this: Api, callback?: (json: any) => void, resetPaging?: boolean): Api<any>;
    /**
     * Reload the table data from the Ajax data source
     *
     * @returns URL set as the Ajax data source for the table.
     */
    url(this: Api): string | undefined;
    /**
     * Reload the table data from the Ajax data source
     *
     * @param url URL to set to be the Ajax data source for the table.
     * @returns DataTables Api instance for chaining or further ajax.url()
     * methods
     */
    url(this: Api, url: string): AjaxMethods;
}
interface AjaxMethods extends Api<any> {
    /**
     * Reload the table data from the Ajax data source.
     *
     * @param callback Function which is executed when the data as been reloaded
     * and the table fully redrawn.
     * @param resetPaging Reset (default action or true) or hold the current
     * paging position (false).
     * @returns DataTables Api instance
     */
    load(this: AjaxMethods, callback?: (json: any) => void, resetPaging?: boolean): Api<any>;
}
interface ApiPage extends Api<any> {
    /**
     * Get the current page of the table.
     *
     * @returns Currently displayed page number
     */
    (this: Api): number;
    /**
     * Set the current page of the table.
     *
     * @param page Index or 'first', 'next', 'previous', 'last'
     * @returns DataTables API instance
     */
    (this: Api, page: number | string): Api<any>;
    /**
     * Get paging information about the table
     *
     * @returns Object with information about the table's paging state.
     */
    info(this: Api): ApiPageInfo;
    /**
     * Get the table's page length.
     *
     * @returns Current page length.
     */
    len(this: Api): number;
    /**
     * Set the table's page length.
     *
     * @param length Page length to set. use -1 to show all records.
     * @returns DataTables API instance.
     */
    len(this: Api, length: number): Api<any>;
}
interface ApiOrder extends Api<any> {
    /**
     * Get the ordering applied to the table.
     *
     * @returns Array of arrays containing information about the currently
     * applied sort. This 2D array is the same format as the array used for
     * setting the order to apply to the table
     */
    (): OrderArray[];
    /**
     * Set the ordering applied to the table.
     *
     * @param order Order Model
     * @returns DataTables Api instance
     */
    (order?: Order | Order[]): Api<any>;
    (order: Order, ...args: Order[]): Api<any>;
    /**
     * Get the fixed ordering that is applied to the table. If there is more
     * than one table in the API's context, the ordering of the first table will
     * be returned only (use table() if you require the ordering of a different
     * table in the API's context).
     * @returns object describing the ordering that is applied to the table
     */
    fixed(): OrderFixed;
    /**
     * Set the table's fixed ordering. Note this doesn't actually perform the
     * order, but rather queues it up - use draw() to perform the ordering.
     *
     * @param order Used to indicate whether the ordering should be performed
     * before or after the users own ordering.
     * @returns DataTables Api instance
     */
    fixed(order: OrderFixed): Api<any>;
    /**
     * Add an ordering listener to an element, for a given column.
     *
     * @param node HTML element to attach to
     * @param column Column index(es)
     * @param callback Callback function
     * @returns DataTables API instance with the current order in the result set
     */
    listener(this: Api, node: HTMLElement, column: number | number[] | (() => number[]), callback: () => void): Api<any>;
}
interface ApiState<T> extends Api<T> {
    /**
     * Get the last saved state of the table
     *
     * @returns State saved object
     */
    (this: Api): State;
    /**
     * Set the table state from a state object
     *
     * @returns API instance, for chaining
     */
    (this: Api, set: State, ignoreTime?: boolean): Api;
    /**
     * Clear the saved state of the table.
     *
     * @returns API instance, for chaining
     */
    clear(this: Api): Api<any>;
    /**
     * Get the table state that was loaded during initialisation.
     *
     * @returns State saved object. See state() for the object format.
     */
    loaded(this: Api): StateLoad | null;
    /**
     * Trigger a state save.
     *
     * @returns API instance, for chaining
     */
    save(this: Api): Api<T>;
}
interface ApiCell<T> {
    /**
     * Select the cell found by a cell selector
     *
     * @param cellSelector Cell selector.
     * @param modifier Option used to specify how the cells should be ordered,
     * and if paging or filtering
     * @returns DataTables API instance with selected cell
     */
    (cellSelector: CellSelector, modifier?: SelectorModifier | null): ApiCellMethods<T>;
    /**
     * Select the cell found by a cell selector
     *
     * @param rowSelector Row selector.
     * @param columnSelector Column selector.
     * @param modifier Option used to specify how the cells should be ordered,
     * and if paging or filtering
     * @returns DataTables API instance with selected cell
     */
    (rowSelector: RowSelector<T>, columnSelector: ColumnSelector, modifier?: SelectorModifier | null): ApiCellMethods<T>;
}
interface ApiCellMethods<T = any> extends Omit<ApiScopeable<T, ApiCellMethods<T>>, 'render' | 'select'> {
    /**
     * Get data for the selected cell
     *
     * @returns the data from the cell
     */
    data(): any;
    /**
     * Get data for the selected cell
     *
     * @param data Value to assign to the data for the cell
     * @returns DataTables Api instance
     */
    data(data: any): Api<T>;
    /**
     * Get index information about the selected cell
     *
     * @returns Object with index information for the selected cell.
     */
    index(): CellIdxWithVisible;
    /**
     * Invalidate the data held in DataTables for the selected cell
     *
     * @param source Data source to read the new data from.
     * @returns DataTables API instance with selected cell references in the
     * result set
     */
    invalidate(source?: string): Api<T>;
    /**
     * Get the DOM element for the selected cell
     *
     * @returns The TD / TH cell the selector resolved to
     */
    node(): HTMLTableCellElement;
    /**
     * Get data for the selected cell
     *
     * @param type Data type to get. This can be one of: 'display', 'filter',
     * 'sort', 'type'
     * @returns Rendered data for the requested type
     */
    render(type: string): any;
}
interface ApiCells<T> {
    /**
     * Select all cells
     *
     * @param modifier Option used to specify how the cells should be ordered,
     * and if paging or filtering
     * @returns DataTables API instance with selected cells
     */
    (this: Api<T>, modifier?: SelectorModifier): ApiCellsMethods<T>;
    /**
     * Select cells found by a cell selector
     *
     * @param cellSelector Cell selector.
     * @param modifier Option used to specify how the cells should be ordered,
     * and if paging or filtering
     * @returns DataTables API instance with selected cells
     */
    (this: Api<T>, cellSelector: CellSelector, modifier?: SelectorModifier): ApiCellsMethods<T>;
    /**
     * Select cells found by both row and column selectors
     *
     * @param rowSelector Row selector.
     * @param columnSelector Column selector.
     * @param modifier Option used to specify how the cells should be ordered,
     * and if paging or filtering
     * @returns DataTables API instance with selected cells
     */
    (this: Api<T>, rowSelector: RowSelector<T>, columnSelector: ColumnSelector, modifier?: SelectorModifier): ApiCellsMethods<T>;
}
interface ApiCellsMethods<T = any> extends Omit<ApiScopeable<T, ApiCellsMethods<T>>, 'data' | 'render' | 'select'> {
    /**
     * Get data for the selected cells
     *
     * @returns DataTables API instance with data for each cell in the selected
     * columns in the result set. This is a 1D array with each entry being the
     * data for the cells from the selected column.
     */
    data(this: ApiCellsMethods<T>): Api<Array<T>>;
    /**
     * Iterate over each selected cell, with the function context set to be the
     * cell in question. Since: DataTables 1.10.6
     *
     * @param fn Function to execute for every cell selected.
     */
    every(this: ApiCellsMethods<T>, fn: (this: ApiCellMethods<T>, cellRowIdx: number, cellColIdx: number, tableLoop: number, cellLoop: number) => void): Api<any>;
    /**
     * Get index information about the selected cells
     */
    indexes(this: ApiCellsMethods<T>): Api<CellIdxWithVisible>;
    /**
     * Invalidate the data held in DataTables for the selected cells
     *
     * @param source Data source to read the new data from.
     * @returns DataTables API instance with selected cell references in the
     * result set
     */
    invalidate(this: ApiCellsMethods<T>, source?: string): Api<T>;
    /**
     * Get the DOM elements for the selected cells
     */
    nodes(this: ApiCellsMethods<T>): Api<HTMLTableCellElement>;
    /**
     * Get data for the selected cell
     *
     * @param type Data type to get. This can be one of: 'display', 'filter',
     * 'sort', 'type'
     * @returns Rendered data for the requested type
     */
    render(this: ApiCellsMethods<T>, type: string): any;
    /**
     * Get the title text for a column
     *
     * @param row Indicate which row in the header the title should be read from
     *  when working with multi-row headers.
     * @return Column title
     */
    title(this: ApiCellsMethods<T>, row?: number): string;
    /**
     * Set the title text for a column
     *
     * @param title Title to set
     * @param row Indicate which row in the header the title should be read from
     *  when working with multi-row headers.
     * @return DataTables API instance for chaining
     */
    title(this: ApiCellsMethods<T>, title: string, row?: number): Api<T>;
    /**
     * Get the column's data type (auto detected or configured).
     *
     * @return The column's data type.
     */
    type(this: ApiCellsMethods<T>): string;
    /**
     * Compute the width of a column as it is shown.
     *
     * @return The width of the column in pixels or `null` if there is no data
     *   in the table.
     */
    width(this: ApiCellsMethods<T>): number | null;
}
interface ApiColumn<T> {
    /**
     * Select the column found by a column selector
     *
     * @param columnSelector Column selector.
     * @param modifier Option used to specify how the cells should be ordered,
     * and if paging or filtering in the table should be taken into account.
     */
    (columnSelector: ColumnSelector, modifier?: SelectorModifier | null): ApiColumnMethods<T>;
    /**
     * Convert from the input column index type to that required.
     *
     * @param type The type on conversion that should take place: 'fromVisible',
     * 'toData', 'fromData', 'toVisible'
     * @param index The index to be converted
     * @returns Calculated column index
     */
    index(type: string, index: number): number | null;
}
interface ApiColumnMethods<T = any> extends Omit<ApiScopeable<T, ApiColumnMethods<T>>, 'init' | 'data' | 'order' | 'render' | 'search'> {
    /**
     * Get the data for the cells in the selected column.
     *
     * @returns DataTables API instance with data for each cell in the selected
     * columns in the result set. This is a 1D array with each entry being the
     * data for the cells from the selected column.
     */
    data(this: ApiColumnMethods<T>): Api<Array<any>>;
    /**
     * Get the data source property for the selected column.
     *
     * @returns the data source property
     */
    dataSrc(this: ApiColumnMethods<T>): number | string | (() => string);
    /**
     * Get the footer th / td cell for the selected column.
     *
     * @param row Indicate which row in the footer the cell should be read from
     *  when working with multi-row footers.
     * @returns HTML element for the footer of the column
     */
    footer(this: ApiColumnMethods<T>, row?: number): HTMLElement;
    /**
     * Get the header th / td cell for a column.
     *
     * @param row Indicate which row in the header the cell should be read from
     *  when working with multi-row headers.
     * @returns HTML element for the header of the column
     */
    header(this: ApiColumnMethods<T>, row?: number): HTMLElement;
    /**
     * Get the column index of the selected column.
     *
     * @param type Specify if you want to get the column data index (default) or
     * the visible index (visible).
     * @returns The column index for the selected column.
     */
    index(this: ApiColumnMethods<T>, type?: string): number;
    /**
     * Get the initialisation object used for the selected column.
     *
     * @returns Column configuration object
     */
    init(this: ApiColumnMethods<T>): Settings;
    /**
     * Get the name for the selected column (set by `columns.name`).
     *
     * @returns Column name or null if not set.
     */
    name(this: ApiColumnMethods<T>): string | null;
    /**
     * Obtain the th / td nodes for the selected column
     *
     * @returns DataTables API instance with each cell's node from the selected
     * columns in the result set. This is a 1D array with each entry being the
     * node for the cells from the selected column.
     */
    nodes(this: ApiColumnMethods<T>): Api<Array<HTMLTableCellElement>>;
    /**
     * Order the table, in the direction specified, by the column selected by
     * the column() selector.
     *
     * @param direction Direction of sort to apply to the selected column - desc
     * (descending) or asc (ascending).
     * @returns DataTables API instance
     */
    order(this: ApiColumnMethods<T>, direction: string): Api<any>;
    /**
     * Get the orderable state for the column (from `columns.orderable`).
     */
    orderable(this: ApiColumnMethods<T>): boolean;
    /**
     * Get a list of the column ordering directions (from
     * `columns.orderSequence`).
     */
    orderable(this: ApiColumnMethods<T>, directions: true): Api<string>;
    /**
     * Get rendered data for the selected column.
     *
     * @param type Data type to get. Typically `display`, `filter`, `sort` or
     *   `type` although can be anything that the rendering functions expect.
     */
    render(this: ApiColumnMethods<T>, type?: string): Api<T>;
    /**
     * Search methods and properties
     */
    search: ApiColumnSearch<T>;
    /**
     * Get the title text for the selected column
     *
     * @param row Indicate which row in the header the title should be read from
     *  when working with multi-row headers.
     * @return Column titles in API instance's data set
     */
    title(this: ApiColumnMethods<T>, row?: number): string;
    /**
     * Set the title text for the selected column
     *
     * @param title Title to set
     * @param row Indicate which row in the header the title should be read from
     *  when working with multi-row headers.
     * @return DataTables API instance for chaining
     */
    title(this: ApiColumnMethods<T>, title: string, row?: number): Api<T>;
    /**
     * Get the data type for the selected column (auto detected or configured).
     *
     * @return DataTables API instance with column types in its data set
     */
    type(this: ApiColumnMethods<T>): string;
    /**
     * Get the visibility of the selected column.
     *
     * @returns true if the column is visible, false if it is not.
     */
    visible(this: ApiColumnMethods<T>): boolean;
    /**
     * Set the visibility of the selected column.
     *
     * @param show Specify if the column should be visible (true) or not
     * (false).
     * @param redrawCalculations Indicate if DataTables should recalculate the
     * column layout (true - default) or not (false).
     * @returns DataTables API instance with selected column in the result set.
     */
    visible(this: ApiColumnMethods<T>, show: boolean, redrawCalculations?: boolean): Api<any>;
    /**
     * Compute the width of the selected column as they are shown.
     *
     * @return Api instance with the width of each column in pixels or `null` if
     *   there is no data in the table.
     */
    width(this: ApiColumnMethods<T>): number | null;
}
interface ApiColumnSearch<T> {
    /**
     * Get the currently applied column search.
     *
     * @returns the currently applied column search.
     */
    (): string;
    /**
     * Set the search term for the matched column.
     *
     * @param input Search apply.
     * @param regex Treat as a regular expression (true) or not (default,
     * false).
     * @param smart Perform smart search.
     * @param caseInsen Do case-insensitive matching (default, true) or not
     * (false).
     * @returns DataTables API instance
     */
    (input: SearchInput<T>, regex?: boolean, smart?: boolean, caseInsen?: boolean): Api<any>;
    /**
     * Set the search term for the matched column.
     *
     * @param input Search to apply.
     * @param options Search configuration options
     * @returns DataTables API instance
     */
    (input: SearchInput<T>, options: Partial<SearchOptions>): Api<any>;
    /**
     * Get a list of the names of searches applied to the column
     *
     * @returns API instance containing the column's fixed search terms
     */
    fixed(): Api<string>;
    /**
     * Get the search term for the column used for the given name.
     *
     * @param name Fixed search term to get.
     * @returns The search term for the name given or undefined if not set.
     */
    fixed(name: string): SearchInput<T> | undefined;
    /**
     * Set a search term to apply to the column, using a name to uniquely
     * identify it.
     *
     * @param name Name to give the fixed search term
     * @param search The search term to apply to the column or `null` to delete
     *   an existing search term by the given name.
     * @returns API for chaining
     */
    fixed(name: string, search: SearchInput<T> | null): Api<T>;
}
interface ApiColumns<T> extends Api<T> {
    /**
     * Select all columns
     *
     * @param modifier Option used to specify how the cells should be ordered,
     * and if paging or filtering in the table should be taken into account.
     * @returns DataTables API instance with selected columns in the result set.
     */
    (modifier?: SelectorModifier): ApiColumnsMethods<T>;
    /**
     * Select columns found by a cell selector
     *
     * @param columnSelector Column selector.
     * @param modifier Option used to specify how the cells should be ordered,
     * and if paging or filtering in the table should be taken into account.
     * @returns DataTables API instance with selected columns
     */
    (columnSelector?: ColumnSelector, modifier?: SelectorModifier): ApiColumnsMethods<T>;
    /**
     * Recalculate the column widths for layout.
     *
     * @returns DataTables API instance.
     */
    adjust(this: Api): Api<T>;
}
interface ApiColumnsMethods<T = any> extends Omit<ApiScopeable<T, ApiColumnsMethods<T>>, 'init' | 'data' | 'order' | 'render' | 'search'> {
    /**
     * Obtain the data for the columns from the selector
     *
     * @returns DataTables API instance with data for each cell in the selected
     * columns in the result set. This is a 2D array with the top level array
     * entries for each column matched by the columns() selector.
     */
    data(this: ApiColumnsMethods<T>): Api<Array<Array<any>>>;
    /**
     * Get the data source property for the selected columns.
     *
     * @returns API instance with the result set containing the data source
     * parameters for the selected columns as configured by
     */
    dataSrc(this: ApiColumnsMethods<T>): Api<any>;
    /**
     * Iterate over each selected column, with the function context set to be
     * the column in question. Since: DataTables 1.10.6
     *
     * @param fn Function to execute for every column selected.
     * @returns DataTables API instance of the selected columns.
     */
    every(this: ApiColumnsMethods<T>, fn: (this: ApiColumnMethods<T>, colIdx: number, tableLoop: number, colLoop: number) => void): Api<any>;
    /**
     * Get the footer th / td cell for the selected columns.
     *
     * @param row Indicate which row in the footer the cell should be read from
     *  when working with multi-row footers.
     * @returns HTML element for the footer of the columns
     */
    footer(this: ApiColumnsMethods<T>, row?: number): Api<HTMLElement>;
    /**
     * Get the header th / td cell for a columns.
     *
     * @param row Indicate which row in the header the cell should be read from
     *  when working with multi-row headers.
     * @returns HTML element for the header of the columns
     */
    header(this: ApiColumnsMethods<T>, row?: number): Api<HTMLElement>;
    /**
     * Get the column indexes of the selected columns.
     *
     * @param type Specify if you want to get the column data index (default) or
     * the visible index (visible).
     * @returns DataTables API instance with selected columns' indexes in the
     * result set.
     */
    indexes(this: ApiColumnsMethods<T>, type?: string): Api<number>;
    /**
     * Get the initialisation objects used for the selected columns.
     *
     * @returns Api instance of column configuration objects
     */
    init(this: ApiColumnsMethods<T>): Api<Settings>;
    /**
     * Get the names for the selected columns (set by `columns.name`).
     *
     * @returns Column names (each entry can be null if not set).
     */
    names(this: ApiColumnsMethods<T>): Api<string | null>;
    /**
     * Obtain the th / td nodes for the selected columns
     *
     * @returns DataTables API instance with each cell's node from the selected
     * columns in the result set. This is a 2D array with the top level array
     * entries for each column matched by the columns() selector.
     */
    nodes(this: ApiColumnsMethods<T>): Api<Array<HTMLTableCellElement>>;
    /**
     * Order the table, in the direction specified, by the columns selected by
     * the column() selector.
     *
     * @param direction Direction of sort to apply to the selected columns -
     * desc (descending) or asc (ascending).
     * @returns DataTables API instance
     */
    order(this: ApiColumnsMethods<T>, direction: string): Api<any>;
    /**
     * Get the orderable state for the selected columns (from
     * `columns.orderable`).
     */
    orderable(this: ApiColumnsMethods<T>): Api<boolean>;
    /**
     * Get a list of the column ordering directions (from
     * `columns.orderSequence`).
     */
    orderable(this: ApiColumnsMethods<T>, directions: true): Api<string>;
    /**
     * Get rendered data for the selected columns.
     * @param type Data type to get. Typically `display`, `filter`, `sort` or
     *   `type` although can be anything that the rendering functions expect.
     */
    render(this: ApiColumnsMethods<T>, type?: string): Api<Array<T>>;
    /**
     * Search methods and properties
     */
    search: ApiColumnsSearch<T>;
    /**
     * Get the title text for the selected columns
     *
     * @param row Indicate which row in the header the title should be read from
     *  when working with multi-row headers.
     * @return Column titles in API instance's data set
     */
    titles(this: ApiColumnsMethods<T>, row?: number): Api<string>;
    /**
     * Set the title text for the selected columns
     *
     * @param title Title to set
     * @param row Indicate which row in the header the title should be read from
     *  when working with multi-row headers.
     * @return DataTables API instance for chaining
     */
    titles(this: ApiColumnsMethods<T>, title: string, row?: number): Api<T>;
    /**
     * Get the data type for the selected columns (auto detected or configured).
     *
     * @return DataTables API instance with column types in its data set
     */
    types(this: ApiColumnsMethods<T>): Api<string>;
    /**
     * Get the visibility of the selected columns.
     *
     * @returns true if the columns is visible, false if it is not.
     */
    visible(this: ApiColumnsMethods<T>): Api<boolean>;
    /**
     * Set the visibility of the selected columns.
     *
     * @param show Specify if the columns should be visible (true) or not
     * (false).
     * @param redrawCalculations Indicate if DataTables should recalculate the
     * columns layout (true - default) or not (false).
     * @returns DataTables API instance with selected columns in the result set.
     */
    visible(this: ApiColumnsMethods<T>, show: boolean, redrawCalculations?: boolean): Api<any>;
    /**
     * Compute the width of the selected columns as they are shown.
     *
     * @return Api instance with the width of each column in pixels or `null` if
     *   there is no data in the table.
     */
    widths(this: ApiColumnsMethods<T>): Api<number | null>;
}
interface ApiColumnsSearch<T> {
    /**
     * Get the currently applied columns search.
     *
     * @returns the currently applied columns search.
     */
    (): Api<SearchInput<T>[]>;
    /**
     * Set the search term for the columns from the selector. Note this doesn't
     * actually perform the search.
     *
     * @param input Search to apply to the selected columns.
     * @param regex Treat as a regular expression (true) or not (default,
     * false).
     * @param smart Perform smart search (default, true) or not (false).
     * @param caseInsen Do case-insensitive matching (default, true) or not
     * (false).
     * @returns DataTables Api instance.
     */
    (input: SearchInput<T>, regex?: boolean, smart?: boolean, caseInsen?: boolean): Api<any>;
    /**
     * Set the search term for the matched columns.
     *
     * @param input Search to apply.
     * @param options Search configuration options
     * @returns DataTables API instance
     */
    (input: SearchInput<T>, options: Partial<SearchOptions>): Api<any>;
    /**
     * Get a list of the names of searches applied to the matched columns
     *
     * @returns API instance containing the column's fixed search terms
     */
    fixed(): Api<string[]>;
    /**
     * Get the search term for the matched columns used for the given name.
     *
     * @param name Fixed search term to get.
     * @returns The search term for the name given or undefined if not set.
     */
    fixed(name: string): Api<SearchInput<T> | undefined>;
    /**
     * Set a search term to apply to the matched columns, using a name to
     * uniquely identify it.
     *
     * @param name Name to give the fixed search term
     * @param search The search term to apply to the column or `null` to delete
     *   an existing search term by the given name.
     * @returns API for chaining
     */
    fixed(name: string, search: SearchInput<T> | null): Api<T>;
}
interface ApiRowChildMethods<T> {
    /**
     * Get the child row(s) that have been set for a parent row
     *
     * @returns Query object with the child rows for the parent row in its
     * result set, or undefined if there are no child rows set for the parent
     * yet.
     */
    (): Dom;
    /**
     * Set the data to show in the child row(s). Note that calling this method
     * will replace any child rows which are already attached to the parent row.
     *
     * @param data The data to be shown in the child row can be given in
     * multiple different ways. Can be set to boolean `false` to hide an
     * existing child row, or boolean `true` to show an existing child row.
     * @param className Class name that is added to the td cell node(s) of the
     * child row(s). As of 1.10.1 it is also added to the tr row node of the
     * child row(s).
     * @returns DataTables Api instance
     */
    (data: (boolean | string | Node | JQuery) | Array<string | number | JQuery>, className?: string): RowChildMethods<T>;
    /**
     * Hide the child row(s) of a parent row
     *
     * @returns DataTables API instance.
     */
    hide(): Api<any>;
    /**
     * Check if the child rows of a parent row are visible
     *
     * @returns boolean indicating whether the child rows are visible.
     */
    isShown(): boolean;
    /**
     * Remove child row(s) from display and release any allocated memory
     *
     * @returns DataTables API instance.
     */
    remove(): Api<any>;
    /**
     * Show the child row(s) of a parent row
     *
     * @returns DataTables API instance.
     */
    show(): Api<any>;
}
interface RowChildMethods<T> extends Api<T> {
    /**
     * Hide the child row(s) of a parent row
     *
     * @returns DataTables API instance.
     */
    hide(): Api<any>;
    /**
     * Remove child row(s) from display and release any allocated memory
     *
     * @returns DataTables API instance.
     */
    remove(): Api<any>;
    /**
     * Make newly defined child rows visible
     *
     * @returns DataTables API instance.
     */
    show(): Api<any>;
}
interface ApiRow<T> {
    /**
     * Select a row found by a row selector
     *
     * @param rowSelector Row selector.
     * @param modifier Option used to specify how the cells should be ordered,
     * and if paging or filtering in the table should be taken into account.
     * @returns DataTables API instance with selected row in the result set
     */
    (this: Api, rowSelector: RowSelector<T>, modifier?: SelectorModifier): ApiRowMethods<T>;
    /**
     * Add a new row to the table using the given data
     *
     * @param data Data to use for the new row. This may be an array, object or
     * JavaScript object instance, but must be in the same format as the other
     * data in the table+
     * @returns DataTables API instance with the newly added row in its result
     * set.
     */
    add(data: any[] | Record<string, any> | JQuery | HTMLElement): ApiRowMethods<T>;
}
interface ApiRowMethods<T = any> extends Omit<ApiScopeable<T, ApiRowMethods<T>>, 'data' | 'select'> {
    /**
     * Order Methods / object
     */
    child: ApiRowChildMethods<T>;
    /**
     * Get the data for the selected row
     *
     * @returns Data source object for the data source of the row.
     */
    data(this: ApiRowMethods<T>): T;
    /**
     * Set the data for the selected row
     *
     * @param d Data to use for the row.
     * @returns DataTables API instance with the row retrieved by the selector
     * in the result set.
     */
    data(this: ApiRowMethods<T>, d: any[] | object): Api<T>;
    /**
     * Get the id of the selected row. Since: 1.10.8
     *
     * @param hash true - Append a hash (#) to the start of the row id. This can
     * be useful for then using the id as a selector false - Do not modify the
     * id value.
     * @returns Row id. If the row does not have an id available 'undefined'
     * will be returned.
     */
    id(this: ApiRowMethods<T>, hash?: boolean): string;
    /**
     * Get the row index of the row column.
     *
     * @returns Row index
     */
    index(this: ApiRowMethods<T>): number;
    /**
     * Obtain the th / td nodes for the selected row(s)
     *
     * @param source Data source to read the new data from. Values: 'auto',
     * 'data', 'dom'
     */
    invalidate(this: ApiRowMethods<T>, source?: string): Api<Array<any>>;
    /**
     * Obtain the tr node for the selected row
     *
     * @returns tr element of the selected row or null if the element is not yet
     * available
     */
    node(this: ApiRowMethods<T>): HTMLTableRowElement | null;
    /**
     * Delete the selected row from the DataTable.
     *
     * @returns DataTables API instance with removed row reference in the result
     * set
     */
    remove(this: ApiRowMethods<T>): Api<T>;
}
interface ApiRows<T> {
    /**
     * Select all rows
     *
     * @param modifier Option used to specify how the cells should be ordered,
     * and if paging or filtering in the table should be taken into account.
     * @returns DataTables API instance with selected rows
     */
    (this: Api, modifier?: SelectorModifier): ApiRowsMethods<T>;
    /**
     * Select rows found by a row selector
     *
     * @param rowSelector Row selector.
     * @param modifier Option used to specify how the cells should be ordered,
     * and if paging or filtering in the table should be taken into account.
     * @returns DataTables API instance with selected rows in the result set
     */
    (this: Api, rowSelector?: RowSelector<T>, modifier?: SelectorModifier): ApiRowsMethods<T>;
    /**
     * Add new rows to the table using the data given
     *
     * @param data Array of data elements, with each one describing a new row to
     * be added to the table
     * @returns DataTables API instance with the newly added rows in its result
     * set.
     */
    add(data: T[]): ApiRowsMethods<T>;
}
interface ApiRowsMethods<T = any> extends Omit<ApiScopeable<T, ApiRowsMethods<T>>, 'select'> {
    /**
     * Get the data for the selected rows
     *
     * @returns DataTables API instance with data for each row from the selector
     * in the result set.
     */
    data(this: ApiRowsMethods<T>): Api<T>;
    /**
     * Iterate over each selected row, with the function context set to be the
     * row in question. Since: DataTables 1.10.6
     *
     * @param fn Function to execute for every row selected.
     * @returns DataTables API instance of the selected rows.
     */
    every(this: ApiRowsMethods<T>, fn: (this: ApiRowMethods<T>, rowIdx: number, tableLoop: number, rowLoop: number) => void): Api<any>;
    /**
     * Get the ids of the selected rows. Since: 1.10.8
     *
     * @param hash true - Append a hash (#) to the start of each row id. This
     * can be useful for then using the ids as selectors false - Do not modify
     * the id value.
     * @returns Api instance with the selected rows in its result set. If a row
     * does not have an id available 'undefined' will be returned as the value.
     */
    ids(this: ApiRowsMethods<T>, hash?: boolean): Api<any>;
    /**
     * Get the row indexes of the selected rows.
     *
     * @returns DataTables API instance with selected row indexes in the result
     * set.
     */
    indexes(this: ApiRowsMethods<T>): Api<number>;
    /**
     * Obtain the th / td nodes for the selected row(s)
     *
     * @param source Data source to read the new data from. Values: 'auto',
     * 'data', 'dom'
     */
    invalidate(this: ApiRowsMethods<T>, source?: string): Api<Array<number>>;
    /**
     * Obtain the tr nodes for the selected rows
     *
     * @returns DataTables API instance with each row's node from the selected
     * rows in the result set.
     */
    nodes(this: ApiRowsMethods<T>): Api<HTMLTableRowElement>;
    /**
     * Delete the selected rows from the DataTable.
     *
     * @returns DataTables API instance with references for the removed rows in
     * the result set
     */
    remove(this: ApiRowsMethods<T>): Api<Array<any>>;
}
interface ApiTableFooterMethods<T> extends Api<T> {
    /**
     * Get the tfoot node for the table in the API's context
     *
     * @returns HTML tbody node.
     */
    (this: ApiTableMethods<T>): HTMLTableSectionElement;
    /**
     * Get an array that represents the structure of the footer
     *
     * @param selector Column selector
     */
    structure(this: Api<T>, selector?: ColumnSelector): HeaderStructure[][];
}
interface ApiTableHeaderMethods<T> extends Api<T> {
    /**
     * Get the thead node for the table in the API's context
     *
     * @returns HTML thead node.
     */
    (this: ApiTableMethods<T>): HTMLTableSectionElement;
    /**
     * Get an array that represents the structure of the header
     *
     * @param selector Column selector
     */
    structure(this: Api<T>, selector?: ColumnSelector): HeaderStructure[][];
}
interface ApiTableMethods<T> extends ApiScopeable<T, ApiTableMethods<T>> {
    /**
     * Table footer information
     */
    footer: ApiTableFooterMethods<T>;
    /**
     * Table header information
     */
    header: ApiTableHeaderMethods<T>;
    /**
     * Get the tbody node for the table in the API's context
     *
     * @returns HTML tfoot node.
     */
    body(this: ApiTableMethods<T>): HTMLTableSectionElement;
    /**
     * Get the div container node for the table in the API's context
     *
     * @returns HTML div node.
     */
    container(this: ApiTableMethods<T>): HTMLDivElement;
    /**
     * Get the table node for the table in the API's context
     *
     * @returns HTML table node for the selected table.
     */
    node(this: ApiTableMethods<T>): HTMLTableElement;
}
interface ApiTablesMethods<T> extends ApiScopeable<T, ApiTablesMethods<T>> {
    /**
     * Get the tfoot nodes for the tables in the API's context
     *
     * @returns Array of HTML tfoot nodes for all table in the API's context
     */
    footer(this: ApiTablesMethods<T>): Api<Array<HTMLTableSectionElement>>;
    /**
     * Get the thead nodes for the tables in the API's context
     *
     * @returns Array of HTML thead nodes for all table in the API's context
     */
    header(this: ApiTablesMethods<T>): Api<Array<HTMLTableSectionElement>>;
    /**
     * Get the tbody nodes for the tables in the API's context
     *
     * @returns Array of HTML tbody nodes for all table in the API's context
     */
    body(this: ApiTablesMethods<T>): Api<Array<HTMLTableSectionElement>>;
    /**
     * Get the div container nodes for the tables in the API's context
     *
     * @returns Array of HTML div nodes for all table in the API's context
     */
    containers(this: ApiTablesMethods<T>): Api<Array<HTMLDivElement>>;
    /**
     * Iterate over each selected table, with the function context set to be the
     * table in question. Since: DataTables 1.10.6
     *
     * @param fn Function to execute for every table selected.
     */
    every(this: ApiTablesMethods<T>, fn: (this: ApiTableMethods<T>, tableIndex: number) => void): Api<any>;
    /**
     * Get the table nodes for the tables in the API's context
     *
     * @returns Array of HTML table nodes for all table in the API's context
     */
    nodes(this: ApiTablesMethods<T>): Api<Array<HTMLTableElement>>;
}
interface DataTablesStatic {
    /**
     * Create a new DataTable
     *
     * @param selector The table to make a DataTable (can be a string that
     *   selects multiple tables).
     * @param options Configuration options for the DataTable
     */
    new <T = any>(selector: string | HTMLElement, options?: Options): Api<T>;
    /**
     * Get DataTable API instance
     *
     * @param table Selector string for table
     */
    Api: ApiStatic;
    /**
     * DataTable's DOM library.
     */
    Dom: typeof Dom;
    /**
     * DataTable's Ajax library
     */
    ajax: typeof ajax;
    /**
     * Register a date / time format for DataTables to use.
     *
     * @param format The date / time format to detect data in. Please refer to
     *   the Moment.js or Luxon document for the full list of tokens, depending
     *   on which of the two libraries you are using.
     * @param locale The locale to pass to Moment.js / Luxon.
     */
    datetime: typeof datetime;
    /**
     * Default Settings
     */
    defaults: Defaults;
    /**
     * Default Settings
     */
    ext: typeof ext;
    /**
     * CommonJS factory function pass through. This will check if the arguments
     * given are a window object or a jQuery object. If so they are set
     * accordingly.
     *
     * @ignore
     */
    factory: typeof factory;
    /** Feature control namespace */
    feature: {
        /**
         * Create a new feature that can be used for layout
         *
         * @param name The name of the new feature.
         * @param cb A function that will create the elements and event
         * listeners for the feature being added.
         */
        register: typeof register$1;
    };
    /**
     * Check if a table node is a DataTable already or not.
     *
     * @param table The table to check.
     * @returns true the given table is a DataTable, false otherwise
     */
    isDataTable(table: string | Node | JQuery | Api<any>): boolean;
    /**
     * Set the DataTables Plus license key.
     *
     * @param key Your DataTables Plus license key
     */
    key(key: string): void;
    /**
     * The models that DataTables uses for data storage.
     *
     * @ignore
     */
    models: typeof _default$1;
    /**
     * Validate a "Plus" extensions
     *
     * @param date The release date of the software. ISO8601 date only format.
     * @param software Optional the extension name
     */
    plus(date: string, software?: string): boolean;
    /**
     * Helpers for `columns.render`.
     *
     * The options defined here can be used with the `columns.render`
     * initialisation option to provide a display renderer.
     */
    render: DataTablesStaticRender;
    /**
     * Context store - one for each DataTable.
     *
     * @ignore
     */
    settings: Context[];
    /**
     * Get all DataTable tables that have been initialised as API instances
     *
     * @param options
     * @returns Array or DataTables API instance containing all matching
     * DataTables
     */
    tables(options: {
        /**
         * Get only visible tables (true) or all tables regardless
         * of visibility (false).
         */
        visible?: boolean;
        /**
         * Make the return an API instance
         */
        api: true;
    }): Api<any>;
    /**
     * Get all DataTable tables that have been initialised - as HTML elements
     *
     * @param options As a boolean value this options is used to indicate if you
     * want all tables on the page should be returned (false), or visible tables
     * only (true).
     * @returns Array of HTML table Elements
     */
    tables(options?: {
        /**
         * Get only visible tables (true) or all tables regardless
         * of visibility (false).
         */
        visible?: boolean;
        /**
         * Make the return an array of elements
         */
        api?: false;
    }): HTMLElement[];
    /**
     * Get all DataTable tables that have been initialised - as HTML elements
     *
     * @param visible Indicate if you want all tables on the page should be
     * returned (false), or visible tables only (true).
     * @returns Array of HTML table Elements
     */
    tables(visible: boolean): HTMLElement[];
    /**
     * Get the data type definition object for a specific registered data type.
     *
     * @param name Data type name to get the definition of
     */
    type: typeof register;
    /**
     * Get the names of the registered data types DataTables can work with.
     */
    types(): string[];
    /**
     * Get the libraries that DataTables uses, or the global objects.
     *
     * @param type The library needed
     */
    use(type: 'jq' | 'lib' | 'win' | 'datetime' | 'luxon' | 'moment'): any;
    /**
     * Set the libraries that DataTables uses, or the global objects, with
     * automatic detection of what the library is. Used for module loading
     * environments.
     */
    use(library: any): void;
    /**
     * Set the libraries that DataTables uses, or the global objects, explicity
     * stating what library is to be considered. Used for module loading
     * environments.
     *
     * @param type Indicate the library that is being loaded.
     * @param library The library (e.g. Moment or Luxon)
     */
    use(type: 'jq' | 'lib' | 'win' | 'datetime' | 'luxon' | 'moment', library: any): void;
    /**
     * Utility functions that DataTables makes use of internally and are exposed
     * for use by extensions.
     */
    util: typeof _default;
    /**
     * Version number compatibility check function
     *
     * @param version Version string
     * @returns true if this version of DataTables is greater or equal to the
     *   required version, or false if this version of DataTables is not
     *   suitable
     */
    versionCheck: typeof check;
    /**
     * DataTables version
     */
    version: string;
    /**
     * Browser capabilities or options
     *
     * @ignore
     */
    __browser: BrowserInfo;
}
type ApiStaticRegisterFn<T> = (this: Api<T>, ...args: any[]) => any;
interface IColumnControlContent {
    [name: string]: any;
}
interface ApiStatic {
    /**
     * Create a new API instance to an existing DataTable. Note that this
     * does not create a new DataTable.
     */
    new (selector: InstSelector): Api<any>;
    register<T extends Function = Function>(name: string | string[], fn: T): void;
    registerPlural<T extends Function = Function>(pluralName: string, singleName: string, fn: T): void;
}
interface DataTablesStaticExtButtons {
}
interface DataTablesStaticRender {
    /**
     * Format an ISO8061 date in auto locale detected format
     */
    date(): DateTimeRenderer;
    /**
     * Format an ISO8061 date value using the specified format
     * @param to Display format
     * @param locale Locale
     */
    date(to: string, locale?: string): DateTimeRenderer;
    /**
     * Format a date value
     * @param from Data format
     * @param to Display format
     * @param locale Locale
     * @param def Default value if empty
     */
    date(from?: string, to?: string, locale?: string, def?: string): DateTimeRenderer;
    /**
     * Format an ISO8061 datetime in auto locale detected format
     */
    datetime(): DateTimeRenderer;
    /**
     * Format an ISO8061 datetime value using the specified format
     * @param to Display format
     * @param locale Locale
     */
    datetime(to: string, locale?: string): DateTimeRenderer;
    /**
     * Format a datetime value
     * @param from Data format
     * @param to Display format
     * @param locale Locale
     * @param def Default value if empty
     */
    datetime(from?: string, to?: string, locale?: string, def?: string): DateTimeRenderer;
    /**
     * Render a number with auto-detected locale thousands and decimal
     */
    number(): any;
    /**
     * Will format numeric data (defined by `columns.data`) for display,
     * retaining the original unformatted data for sorting and filtering.
     *
     * @param thousands Thousands grouping separator. `null` for auto locale
     * @param decimal Decimal point indicator. `null` for auto locale
     * @param precision Integer number of decimal points to show.
     * @param prefix Prefix (optional).
     * @param postfix Postfix (/suffix) (optional).
     */
    number(thousands: string | null, decimal: string | null, precision: number, prefix?: string, postfix?: string): NumberRenderer;
    /**
     * Escape HTML to help prevent XSS attacks. It has no optional parameters.
     */
    text(): TextRenderer;
    /**
     * Format an ISO8061 date in auto locale detected format
     */
    time(): DateTimeRenderer;
    /**
     * Format an ISO8061 time value using the specified format
     * @param to Display format
     * @param locale Locale
     */
    time(to: string, locale?: string): DateTimeRenderer;
    /**
     * Format a time value
     * @param from Data format
     * @param to Display format
     * @param locale Locale
     * @param def Default value if empty
     */
    time(from?: string, to?: string, locale?: string, def?: string): DateTimeRenderer;
}
interface ExtTypeSettings {
    /**
     * Type detection functions for plug-in development.
     *
     * @deprecated Use `DataTable.type()`
     */
    detect: DataTypeDetect[];
    /**
     * Type based ordering functions for plug-in development.
     *
     * @deprecated Use `DataTable.type()`
     */
    order: any;
    /**
     * Type based search formatting for plug-in development.
     *
     * @deprecated Use `DataTable.type()`
     */
    search: any;
}
interface JQueryDataTables extends JQuery {
    /**
     * Returns DataTables API instance
     * Usage:
     * $( selector ).dataTable().api();
     */
    api(): Api<any>;
}
declare global {
    interface JQueryDataTableApi extends DataTablesStatic {
        <T = any>(opts?: Options): Api<T>;
    }
    interface JQueryDataTableJq extends DataTablesStatic {
        (opts?: Options): JQueryDataTables;
    }
    interface JQuery {
        /**
         * Create a new DataTable, returning a DataTables API instance.
         * @param opts Configuration settings
         */
        DataTable: JQueryDataTableApi;
        /**
         * Create a new DataTable, returning a jQuery object, extended
         * with an `api()` method which can be used to access the
         * DataTables API.
         * @param opts Configuration settings
         */
        dataTable: JQueryDataTableJq;
    }
}

declare const DataTable$1: DataTablesStatic;

/*!
 * Type definitions for DataTables
 *
 * DataTables exports a range of types, its static API as the default and also
 * the following type / value combinations which can be used with ESM imports:
 *
 * * Api - The DataTables Api
 * * DataTable - Same as the default
 * * Dom - DataTable's Dom library
 * * util - DataTable's utility library
 */



// Merge the Static interface with the Constructor value
interface DataTable extends DataTablesStatic {}
declare const DataTable: typeof DataTable$1;

export { Api, Settings as ColumnContext, DataTable, Dom, DataTable as default, _default as util };
export type { AjaxMethods, AjaxOptions, ApiAjax, ApiCaption, ApiCell, ApiCellMethods, ApiCells, ApiCellsMethods, ApiColumn, ApiColumnMethods, ApiColumnSearch, ApiColumns, ApiColumnsMethods, ApiColumnsSearch, ApiConstructor, ApiOrder, ApiPage, ApiPageInfo, ApiRow, ApiRowChildMethods, ApiRowMethods, ApiRows, ApiRowsMethods, ApiScopeable, ApiSearch, ApiSelector, ApiState, ApiStatic, ApiStaticRegisterFn, ApiTableFooterMethods, ApiTableHeaderMethods, ApiTableMethods, ApiTablesMethods, CellIdx, CellIdxWithVisible, CellMeta, CellSelector, ColumnIdx, ColumnRenderFunction, ColumnSelector, Options$1 as ColumnsConfig, Context, DataTableEvent, DataTablesStatic, DataTablesStaticExtButtons, DataTablesStaticRender, DataType, DataTypeDetect, Defaults, DomSelector, Ext, ExtButtons, ExtTypeSettings, HeaderStructure, IColumnControlContent, InstSelector, ConfigLanguage as Language, Options, RowChildMethods, Row as RowContext, RowIdx, RowSelector, SelectorModifier, Context as Settings, State, StateLoad, TableSelector };
