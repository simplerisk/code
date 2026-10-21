var linear = function linear(k) {
  return k;
};
var quadraticIn = function quadraticIn(k) {
  return k * k;
};
var quadraticOut = function quadraticOut(k) {
  return k * (2 - k);
};
var quadraticInOut = function quadraticInOut(k) {
  if ((k *= 2) < 1) return 0.5 * k * k;
  return -0.5 * (--k * (k - 2) - 1);
};
var cubicIn = function cubicIn(k) {
  return k * k * k;
};
var cubicOut = function cubicOut(k) {
  return --k * k * k + 1;
};
var cubicInOut = function cubicInOut(k) {
  if ((k *= 2) < 1) return 0.5 * k * k * k;
  return 0.5 * ((k -= 2) * k * k + 2);
};
var exponentialIn = function exponentialIn(k) {
  return k === 0 ? 0 : Math.pow(2, 10 * (k - 1));
};
var exponentialOut = function exponentialOut(k) {
  return k === 1 ? 1 : 1 - Math.pow(2, -10 * k);
};
var exponentialInOut = function exponentialInOut(k) {
  if (k === 0) return 0;
  if (k === 1) return 1;
  if (k < 0.5) return Math.pow(2, 10 * (2 * k - 1)) / 2;
  return (2 - Math.pow(2, -10 * (2 * k - 1))) / 2;
};

/**
 * Registry of built-in named easing functions.
 * Typed with `satisfies` so that `keyof typeof easings` yields the literal
 * union of names — the `Easing` type below can't drift from this record.
 */
var easings = {
  linear: linear,
  quadraticIn: quadraticIn,
  quadraticOut: quadraticOut,
  quadraticInOut: quadraticInOut,
  cubicIn: cubicIn,
  cubicOut: cubicOut,
  cubicInOut: cubicInOut,
  exponentialIn: exponentialIn,
  exponentialOut: exponentialOut,
  exponentialInOut: exponentialInOut
};

/**
 * An easing is either the name of a built-in function or a custom function
 * mapping a progress value in [0, 1] to an eased value (typically in [0, 1]).
 */

/**
 * Resolves an `Easing` (name or function) to a concrete function.
 * Falls back to `linear` when the easing is missing.
 */
function resolveEasing(easing) {
  if (!easing) return easings.linear;
  if (typeof easing === "function") return easing;
  return easings[easing];
}

export { quadraticOut as a, quadraticInOut as b, cubicIn as c, cubicOut as d, easings as e, cubicInOut as f, exponentialIn as g, exponentialOut as h, exponentialInOut as i, linear as l, quadraticIn as q, resolveEasing as r };
