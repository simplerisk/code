// Type definitions for DataTables RowGroup
//
// Project: https://datatables.net/extensions/rowgroup/, https://datatables.net
// Definitions by:
//   SpryMedia
//   Matthieu Tabuteau <https://github.com/maixiu>

/// <reference types="jquery" />

import DataTables, {Api, ApiRowMethods} from 'datatables.net';

export default DataTables;

type DataSrc = string | number | Array<number | string>;


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * DataTables' types integration
 */
declare module 'datatables.net' {
	interface Config {
		/**
		 * RowGroup extension options
		 */
		rowGroup?: boolean | ConfigRowGroup;
	}

	interface Api<T> {
		/**
		 * RowGroup methods container
		 * 
		 * @returns Api for chaining with the additional RowGroup methods
		 */
		rowGroup(): ApiRowGroup<T>;
	}

	interface DataTablesStatic {
		/**
		 * RowGroup class
		 */
		RowGroup: {
			/**
			 * Create a new RowGroup instance for the target DataTable
			 */
			new (dt: Api<any>, settings: boolean | ConfigRowGroup): DataTablesStatic['RowGroup'];

			/**
			 * Default configuration values
			 */
			defaults: ConfigRowGroup;

			/**
			 * RowGroup version
			 */
			version: string;
		}
	}
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Options
 */

interface ConfigRowGroup {
	/**
	 * Set the class name to be used for the grouping rows
	 */
	className?: string;

	/**
	 * Set the data point to use as the grouping data source
	 */
	dataSrc?: DataSrc;

	/**
	 * Text to show for rows which have `null`, `undefined` or empty string group data
	 * 
	 * @since 1.0.2
	 */
	emptyDataGroup?: string;

	/**
	 * Provides the ability to disable row grouping at initialisation
	 */
	enable?: boolean;

	/**
	 * Set the class name to be used for the grouping end rows
	 */
	endClassName?: string;

	/**
	 * Provide a function that can be used to control the data shown in the end grouping row
	 */
	endRender?: (rows: ApiRowMethods<any>, group: string, level: number) => string|HTMLElement|JQuery;

	/**
	 * Set the class name to be used for the start grouping rows
	 */
	startClassName?: string;

	/**
	 * Provide a function that can be used to control the data shown in the start grouping row
	 */
	startRender?: (rows: ApiRowMethods<any>, group: string, level: number) => string|HTMLElement|JQuery;
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
