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
 * Returns true if any own property of `partial` differs (strict equality)
 * from the corresponding property in `current`. Uses for...in for speed
 * and short-circuits on the first difference.
 */
function hasNewPartialProps(current, partial) {
  for (var k in partial) {
    if (!Object.prototype.hasOwnProperty.call(partial, k)) continue;
    if (partial[k] !== current[k]) return true;
  }
  return false;
}

/**
 * Returns true if both objects have the same own properties, compared with
 * strict equality. Only checks the top level, and considers a missing key and
 * an `undefined` value to be equal.
 */
function shallowEqual(a, b) {
  var aProps = a;
  var bProps = b;
  for (var k in aProps) {
    if (!Object.prototype.hasOwnProperty.call(aProps, k)) continue;
    if (aProps[k] !== bProps[k]) return false;
  }
  for (var _k in bProps) {
    if (!Object.prototype.hasOwnProperty.call(bProps, _k)) continue;
    if (bProps[_k] !== aProps[_k]) return false;
  }
  return true;
}

/**
 * Sets whether `key` is a member of `set`. Equivalent to
 * `present ? set.add(key) : set.delete(key)`.
 */
function setMembership(set, key, present) {
  if (present) set.add(key);else set["delete"](key);
}

export { setMembership as a, assign as b, extend as e, hasNewPartialProps as h, shallowEqual as s };
