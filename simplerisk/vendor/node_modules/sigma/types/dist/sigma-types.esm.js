import { _ as _inherits, a as _createClass, b as _classCallCheck, c as _callSuper } from '../../dist/inherits-d1a1e29b.esm.js';
import { EventEmitter } from 'events';

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
    _classCallCheck(this, TypedEventEmitter);
    _this = _callSuper(this, TypedEventEmitter);
    _this.rawEmitter = _this;
    return _this;
  }
  _inherits(TypedEventEmitter, _ref);
  return _createClass(TypedEventEmitter);
}(EventEmitter);

/**
 * Event types.
 */

/**
 * Export various other types:
 */

export { TypedEventEmitter };
