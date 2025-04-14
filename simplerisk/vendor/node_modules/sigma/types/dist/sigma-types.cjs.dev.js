'use strict';

Object.defineProperty(exports, '__esModule', { value: true });

var inherits = require('../../dist/inherits-04acba6b.cjs.dev.js');
var events = require('events');

/**
 * Util type to represent maps of typed elements, but implemented with
 * JavaScript objects.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any

/**
 * Returns a type similar to T, but with the K set of properties of the type
 * T *required*, and the rest optional.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any

/**
 * Returns a type similar to Partial<T>, but with at least one key set.
 */

/**
 * Custom event emitter types.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any

var TypedEventEmitter = /*#__PURE__*/function (_ref) {
  function TypedEventEmitter() {
    var _this;
    inherits._classCallCheck(this, TypedEventEmitter);
    _this = inherits._callSuper(this, TypedEventEmitter);
    _this.rawEmitter = _this;
    return _this;
  }
  inherits._inherits(TypedEventEmitter, _ref);
  return inherits._createClass(TypedEventEmitter);
}(events.EventEmitter);

/**
 * Event types.
 */

/**
 * Export various other types:
 */

exports.TypedEventEmitter = TypedEventEmitter;
