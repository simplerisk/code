'use strict';

var easings = require('./easings-331c0d2b.cjs.prod.js');
var isGraph = require('graphology-utils/is-graph');
var colors = require('./colors-2b1720c5.cjs.prod.js');

function _interopDefault (e) { return e && e.__esModule ? e : { 'default': e }; }

var isGraph__default = /*#__PURE__*/_interopDefault(isGraph);

var ANIMATE_DEFAULTS = {
  easing: "quadraticInOut",
  duration: 150
};

/**
 * Function used to animate the nodes.
 */
function animateNodes(graph, targets, opts, callback) {
  var options = Object.assign({}, ANIMATE_DEFAULTS, opts);
  var easing = easings.resolveEasing(options.easing);
  var start = Date.now();
  var startPositions = {};
  for (var node in targets) {
    var attrs = targets[node];
    startPositions[node] = {};
    for (var k in attrs) startPositions[node][k] = graph.getNodeAttribute(node, k);
  }
  var frame = null;
  var _step = function step() {
    frame = null;
    var p = (Date.now() - start) / options.duration;
    if (p >= 1) {
      // Animation is done
      for (var _node in targets) {
        var _attrs = targets[_node];

        // We use given values to avoid precision issues and for convenience
        for (var _k in _attrs) graph.setNodeAttribute(_node, _k, _attrs[_k]);
      }
      if (typeof callback === "function") callback();
      return;
    }
    p = easing(p);
    for (var _node2 in targets) {
      var _attrs2 = targets[_node2];
      var s = startPositions[_node2];
      for (var _k2 in _attrs2) graph.setNodeAttribute(_node2, _k2, _attrs2[_k2] * p + s[_k2] * (1 - p));
    }
    frame = requestAnimationFrame(_step);
  };
  _step();
  return function () {
    if (frame) cancelAnimationFrame(frame);
  };
}

/**
 * Predicates over node/edge display data.
 *
 * These check the resolved visibility/labelVisibility/backdropVisibility
 * fields produced by the styles pipeline. They tolerate missing fields so
 * they can be called on partial shapes (e.g. before defaults are merged).
 */

function hasForcedLabel(data) {
  return data.labelVisibility === "visible" && data.visibility !== "hidden";
}
function hasBackdrop(data) {
  return data.backdropVisibility === "visible" && data.visibility !== "hidden";
}

/**
 * Encodes a node's rotation-alignment styles as the two GPU flags written to
 * texel 1 of the node-data texture: `[nodeRotation, labelRotation]`, each
 * 0 = viewport (screen-upright) or 1 = graph (turns with the camera).
 */
function nodeRotationFlags(data) {
  return [data.rotationAlignment === "graph" ? 1 : 0, data.labelRotationAlignment === "graph" ? 1 : 0];
}

/**
 * Remove a single position from a depth range, splitting the containing
 * fragment if needed.
 */
function removePositionFromDepthRanges(ranges, depth, position) {
  var fragments = ranges[depth];
  if (!fragments) return;
  for (var i = 0; i < fragments.length; i++) {
    var f = fragments[i];
    if (position < f.offset || position >= f.offset + f.count) continue;
    if (f.count === 1) {
      fragments.splice(i, 1);
    } else if (position === f.offset) {
      f.offset++;
      f.count--;
    } else if (position === f.offset + f.count - 1) {
      f.count--;
    } else {
      // Split into two fragments
      var secondOffset = position + 1;
      var secondCount = f.offset + f.count - secondOffset;
      f.count = position - f.offset;
      fragments.splice(i + 1, 0, {
        offset: secondOffset,
        count: secondCount
      });
    }
    return;
  }
}

/**
 * Add a single position to a depth range, merging with adjacent fragments
 * when possible.
 */
function addPositionToDepthRanges(ranges, depth, position) {
  if (!ranges[depth]) {
    ranges[depth] = [{
      offset: position,
      count: 1
    }];
    return;
  }
  var fragments = ranges[depth];

  // Find insertion point (fragments are sorted by offset)
  var insertIdx = fragments.length;
  for (var i = 0; i < fragments.length; i++) {
    if (position < fragments[i].offset) {
      insertIdx = i;
      break;
    }
  }

  // Check if we can merge with neighbors
  var prev = insertIdx > 0 ? fragments[insertIdx - 1] : null;
  var next = insertIdx < fragments.length ? fragments[insertIdx] : null;
  var mergeWithPrev = prev && prev.offset + prev.count === position;
  var mergeWithNext = next && position + 1 === next.offset;
  if (mergeWithPrev && mergeWithNext) {
    prev.count += 1 + next.count;
    fragments.splice(insertIdx, 1);
  } else if (mergeWithPrev) {
    prev.count++;
  } else if (mergeWithNext) {
    next.offset--;
    next.count++;
  } else {
    fragments.splice(insertIdx, 0, {
      offset: position,
      count: 1
    });
  }
}

/**
 * Check if the graph variable is a valid graph, and if sigma can render it.
 */
function validateGraph(graph) {
  // check if it's a valid graphology instance
  if (!isGraph__default["default"](graph)) throw new Error("Sigma: invalid graph instance.");
}

/**
 * Factory returning a function normalizing the given node's position & size.
 */

function createNormalizationFunction(extent) {
  var _extent$x = colors._slicedToArray(extent.x, 2),
    minX = _extent$x[0],
    maxX = _extent$x[1],
    _extent$y = colors._slicedToArray(extent.y, 2),
    minY = _extent$y[0],
    maxY = _extent$y[1];
  var ratio = Math.max(maxX - minX, maxY - minY),
    dX = (maxX + minX) / 2,
    dY = (maxY + minY) / 2;
  if (ratio === 0 || Math.abs(ratio) === Infinity || isNaN(ratio)) ratio = 1;
  if (isNaN(dX)) dX = 0;
  if (isNaN(dY)) dY = 0;
  var fn = function fn(data) {
    return {
      x: 0.5 + (data.x - dX) / ratio,
      y: 0.5 + (data.y - dY) / ratio
    };
  };

  // TODO: possibility to apply this in batch over array of indices
  fn.applyTo = function (data) {
    data.x = 0.5 + (data.x - dX) / ratio;
    data.y = 0.5 + (data.y - dY) / ratio;
  };
  fn.inverse = function (data) {
    return {
      x: dX + ratio * (data.x - 0.5),
      y: dY + ratio * (data.y - 0.5)
    };
  };
  fn.ratio = ratio;
  return fn;
}

exports.ANIMATE_DEFAULTS = ANIMATE_DEFAULTS;
exports.addPositionToDepthRanges = addPositionToDepthRanges;
exports.animateNodes = animateNodes;
exports.createNormalizationFunction = createNormalizationFunction;
exports.hasBackdrop = hasBackdrop;
exports.hasForcedLabel = hasForcedLabel;
exports.nodeRotationFlags = nodeRotationFlags;
exports.removePositionFromDepthRanges = removePositionFromDepthRanges;
exports.validateGraph = validateGraph;
