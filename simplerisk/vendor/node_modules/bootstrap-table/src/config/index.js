/**
 * Bootstrap Table Global Configuration Storage
 * Replaces $.fn.bootstrapTable for jQuery-free operation
 */

import Constants from '../constants/index.js'
import Utils from '../utils/index.js'

// Global configuration object
const BootstrapTableConfig = {
  theme: Constants.THEME,
  VERSION: Constants.VERSION,
  icons: Constants.ICONS,
  defaults: Constants.DEFAULTS,
  columnDefaults: Constants.COLUMN_DEFAULTS,
  events: Constants.EVENTS,
  locales: Constants.LOCALES,
  methods: Constants.METHODS,
  utils: Utils
}

export default BootstrapTableConfig
