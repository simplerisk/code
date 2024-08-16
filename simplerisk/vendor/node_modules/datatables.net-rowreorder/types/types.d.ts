// Type definitions for DataTables RowReorder
//
// Project: https://datatables.net/extensions/rowreorder/, https://datatables.net
// Definitions by:
//   SpryMedia
//   Vincent Biret <https://github.com/baywet>

import DataTables, {Api} from 'datatables.net';

export default DataTables;

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * DataTables' types integration
 */
declare module 'datatables.net' {
	interface Config {
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

	interface DataTablesStatic {
		/**
		 * RowReorder class
		 */
		RowReorder: {
			/**
			 * Create a new RowReorder instance for the target DataTable
			 */
			new (dt: Api<any>, settings: boolean | ConfigRowReorder): DataTablesStatic['RowReorder'];

			/**
			 * RowReorder version
			 */
			version: string;

			/**
			 * Default configuration values
			 */
			defaults: ConfigRowReorder;
		}
	}
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Options
 */

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


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * API
 */

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
