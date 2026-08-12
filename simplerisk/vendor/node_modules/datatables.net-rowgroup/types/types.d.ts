import DataTables, { Api, Context, ApiRowsMethods, Dom } from 'datatables.net';
export { default } from 'datatables.net';

declare class RowGroup {
    static defaults: Defaults;
    static version: string;
    /**
     * Get/set the grouping data source - need to call draw after this is
     * executed as a setter
     *
     * @returns string~RowGroup
     */
    dataSrc(val: DataSrc): DataSrc | this;
    /**
     * Disable - need to call draw after this is executed
     *
     * @returns RowGroup
     */
    disable(): this;
    /**
     * Enable - need to call draw after this is executed
     *
     * @returns RowGroup
     */
    enable(flag?: boolean): this;
    /**
     * Get enabled flag
     * @returns boolean
     */
    enabled(): boolean;
    private c;
    private s;
    constructor(dt: Api | Context, opts?: Config);
    private _init;
    /**
     * Adjust column span when column visibility changes
     */
    private _adjustColspan;
    /**
     * Get the number of columns that a grouping row should span
     */
    private _colspan;
    /**
     * Update function that is called whenever we need to draw the grouping
     * rows. This is basically a bootstrap for the self iterative _group and
     * _groupDisplay methods
     */
    private _draw;
    /**
     * Get the grouping information from a data set (index) of rows
     *
     * @param level Nesting level
     * @param rows API of the rows to consider for this group
     * @returns Nested grouping information
     */
    private _group;
    /**
     * Row group display - insert the rows into the document
     *
     * @param level Nesting level
     * @param groups Takes the nested array from `_group`
     */
    private _groupDisplay;
    /**
     * Take a rendered value from an end user and make it suitable for display
     * as a row, by wrapping it in a row, or detecting that it is a row.
     *
     * @param display Display value
     * @param className Class to add to the row
     * @param group level
     */
    private _rowWrap;
}

type DataSrc = string | number | Array<number | string>;
declare module 'datatables.net' {
    interface Options {
        /**
         * RowGroup extension options
         */
        rowGroup?: boolean | Config;
    }
    interface Defaults {
        rowGroup: Defaults;
    }
    interface Api<T> {
        /**
         * RowGroup methods container
         *
         * @returns Api for chaining with the additional RowGroup methods
         */
        rowGroup(): ApiRowGroup<T>;
    }
    interface Context {
        rowGroup: RowGroup;
    }
    interface DataTablesStatic {
        /**
         * RowGroup class
         */
        RowGroup: typeof RowGroup;
    }
}
interface Defaults {
    /**
     * Set the class name to be used for the grouping rows
     */
    className: string;
    /**
     * Set the data point to use as the grouping data source
     */
    dataSrc: DataSrc;
    /**
     * Text to show for rows which have `null`, `undefined` or empty string group data
     *
     * @since 1.0.2
     */
    emptyDataGroup: string;
    /**
     * Provides the ability to disable row grouping at initialisation
     */
    enable: boolean;
    /**
     * Set the class name to be used for the grouping end rows
     */
    endClassName: string;
    /**
     * Provide a function that can be used to control the data shown in the end grouping row
     */
    endRender: null | ((rows: ApiRowsMethods<any>, group: string, level: number) => string | HTMLElement | Dom);
    /**
     * Set the class name to be used for the start grouping rows
     */
    startClassName: string;
    /**
     * Provide a function that can be used to control the data shown in the start grouping row
     */
    startRender: null | ((rows: ApiRowsMethods<any>, group: string, level: number) => string | HTMLElement | Dom);
}
interface Config extends Partial<Defaults> {
}
interface ApiRowGroup<T> extends Api<T> {
    /**
     * Get the data source for the row grouping
     *
     * @returns Data source property
     */
    dataSrc(): DataSrc;
    /**
     * Set the data source for the row grouping
     *
     * @param prop Data source property
     * @returns DataTables Api instance
     */
    dataSrc(prop: DataSrc): Api<T>;
    /**
     * Disable RowGroup's interaction with the table
     *
     * @returns DataTables API instance
     */
    disable(): Api<T>;
    /**
     * Enable or disable RowGroup's interaction with the table
     *
     * @param enable Either enables or disables RowGroup depending on the value of the flag
     * @returns DataTables Api instance
     */
    enable(enable?: boolean): Api<T>;
    /**
     * Get the enabled state for RowGroup.
     *
     * @returns true if enabled, false otherwise
     */
    enabled(): boolean;
}
interface Settings {
    dt: Api;
}
interface Grouping {
    dataPoint: string;
    rows: number[];
    children?: Grouping[];
}

export type { Config, DataSrc, Defaults, Grouping, Settings };
