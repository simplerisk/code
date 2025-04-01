function _typeof(o) {
  "@babel/helpers - typeof";

  return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) {
    return typeof o;
  } : function (o) {
    return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o;
  }, _typeof(o);
}

/**
 * Extends the target array with the given values.
 */
function extend(array, values) {
  var l2 = values.size;
  if (l2 === 0) return;
  var l1 = array.length;
  array.length += l2;
  var i = 0;
  values.forEach(function (value) {
    array[l1 + i] = value;
    i++;
  });
}

/**
 * Checks whether the given value is a plain object.
 */
function isPlainObject(value) {
  return _typeof(value) === "object" && value !== null && value.constructor === Object;
}

/**
 * Helper to use `Object.assign` with more than two objects.
 */
function assign(target) {
  target = target || {};
  for (var i = 0, l = arguments.length <= 1 ? 0 : arguments.length - 1; i < l; i++) {
    var o = i + 1 < 1 || arguments.length <= i + 1 ? undefined : arguments[i + 1];
    if (!o) continue;
    Object.assign(target, o);
  }
  return target;
}

/**
 * Very simple recursive `Object.assign` like function.
 */
function assignDeep(target) {
  target = target || {};
  for (var i = 0, l = arguments.length <= 1 ? 0 : arguments.length - 1; i < l; i++) {
    var o = i + 1 < 1 || arguments.length <= i + 1 ? undefined : arguments[i + 1];
    if (!o) continue;
    for (var k in o) {
      if (isPlainObject(o[k])) {
        target[k] = assignDeep(target[k], o[k]);
      } else {
        target[k] = o[k];
      }
    }
  }
  return target;
}

export { _typeof as _, assign as a, assignDeep as b, extend as e, isPlainObject as i };
