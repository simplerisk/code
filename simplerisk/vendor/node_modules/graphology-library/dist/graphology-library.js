(function (global, factory) {
	typeof exports === 'object' && typeof module !== 'undefined' ? factory(exports) :
	typeof define === 'function' && define.amd ? define(['exports'], factory) :
	(global = typeof globalThis !== 'undefined' ? globalThis : global || self, factory(global.graphologyLibrary = {}));
})(this, (function (exports) { 'use strict';

	var commonjsGlobal = typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : typeof global !== 'undefined' ? global : typeof self !== 'undefined' ? self : {};

	var browser$2 = {};

	var assertions$2 = {};

	var es6 = function equal(a, b) {
	  if (a === b) return true;

	  if (a && b && typeof a == 'object' && typeof b == 'object') {
	    if (a.constructor !== b.constructor) return false;

	    var length, i, keys;
	    if (Array.isArray(a)) {
	      length = a.length;
	      if (length != b.length) return false;
	      for (i = length; i-- !== 0;)
	        if (!equal(a[i], b[i])) return false;
	      return true;
	    }


	    if ((a instanceof Map) && (b instanceof Map)) {
	      if (a.size !== b.size) return false;
	      for (i of a.entries())
	        if (!b.has(i[0])) return false;
	      for (i of a.entries())
	        if (!equal(i[1], b.get(i[0]))) return false;
	      return true;
	    }

	    if ((a instanceof Set) && (b instanceof Set)) {
	      if (a.size !== b.size) return false;
	      for (i of a.entries())
	        if (!b.has(i[0])) return false;
	      return true;
	    }

	    if (ArrayBuffer.isView(a) && ArrayBuffer.isView(b)) {
	      length = a.length;
	      if (length != b.length) return false;
	      for (i = length; i-- !== 0;)
	        if (a[i] !== b[i]) return false;
	      return true;
	    }


	    if (a.constructor === RegExp) return a.source === b.source && a.flags === b.flags;
	    if (a.valueOf !== Object.prototype.valueOf) return a.valueOf() === b.valueOf();
	    if (a.toString !== Object.prototype.toString) return a.toString() === b.toString();

	    keys = Object.keys(a);
	    length = keys.length;
	    if (length !== Object.keys(b).length) return false;

	    for (i = length; i-- !== 0;)
	      if (!Object.prototype.hasOwnProperty.call(b, keys[i])) return false;

	    for (i = length; i-- !== 0;) {
	      var key = keys[i];

	      if (!equal(a[key], b[key])) return false;
	    }

	    return true;
	  }

	  // true if both NaN, false otherwise
	  return a!==a && b!==b;
	};

	/**
	 * Graphology isGraph
	 * ===================
	 *
	 * Very simple function aiming at ensuring the given variable is a
	 * graphology instance.
	 */

	/**
	 * Checking the value is a graphology instance.
	 *
	 * @param  {any}     value - Target value.
	 * @return {boolean}
	 */
	var isGraph$N = function isGraph(value) {
	  return (
	    value !== null &&
	    typeof value === 'object' &&
	    typeof value.addUndirectedEdgeWithKey === 'function' &&
	    typeof value.dropNode === 'function' &&
	    typeof value.multi === 'boolean'
	  );
	};

	/**
	 * Graphology isGraphConstructor
	 * ==============================
	 *
	 * Very simple function aiming at ensuring the given variable is a
	 * graphology constructor.
	 */

	/**
	 * Checking the value is a graphology constructor.
	 *
	 * @param  {any}     value - Target value.
	 * @return {boolean}
	 */
	var isGraphConstructor$e = function isGraphConstructor(value) {
	  return (
	    value !== null &&
	    typeof value === 'function' &&
	    typeof value.prototype === 'object' &&
	    typeof value.prototype.addUndirectedEdgeWithKey === 'function' &&
	    typeof value.prototype.dropNode === 'function'
	  );
	};

	/**
	 * Graphology Assertions
	 * ======================
	 *
	 * Various assertions concerning graphs.
	 */

	var deepEqual = es6;

	/**
	 * Constants.
	 */
	var SIZE = Symbol('size');

	/**
	 * Helpers.
	 */
	function areUnorderedCollectionsOfAttributesIdentical(a1, a2) {
	  var l1 = a1.length;
	  var l2 = a2.length;

	  if (l1 !== l2) return false;

	  var o1, o2;
	  var i, j;
	  var matches = new Set();

	  outside: for (i = 0; i < l1; i++) {
	    o1 = a1[i];

	    for (j = 0; j < l2; j++) {
	      if (matches.has(j)) continue;

	      o2 = a2[j];

	      if (deepEqual(o1, o2)) {
	        matches.add(j);
	        continue outside;
	      }
	    }

	    return false;
	  }

	  return true;
	}

	function compareNeighborEntries(entries1, entries2) {
	  if (entries1[SIZE] !== entries2[SIZE]) return false;

	  for (var k in entries1) {
	    if (!areUnorderedCollectionsOfAttributesIdentical(entries1[k], entries2[k]))
	      return false;
	  }

	  return true;
	}

	function countOutEdges(graph, node) {
	  var counts = {};
	  var c;

	  graph.forEachOutEdge(node, function (_e, _ea, _s, target) {
	    c = counts[target] || 0;
	    c++;

	    counts[target] = c;
	  });

	  return counts;
	}

	function countAssymetricUndirectedEdges(graph, node) {
	  var counts = {};
	  var c;

	  graph.forEachUndirectedEdge(node, function (_e, _ea, source, target) {
	    target = node === source ? target : source;

	    if (node > target) return;

	    c = counts[target] || 0;
	    c++;

	    counts[target] = c;
	  });

	  return counts;
	}

	function collectOutEdges(graph, node) {
	  var entries = {};
	  entries[SIZE] = 0;
	  var c;

	  graph.forEachOutEdge(node, function (_e, attr, _s, target) {
	    c = entries[target];

	    if (!c) {
	      c = [];
	      entries[SIZE] += 1;
	      entries[target] = c;
	    }

	    c.push(attr);
	  });

	  return entries;
	}

	function collectAssymetricUndirectedEdges(graph, node) {
	  var entries = {};
	  entries[SIZE] = 0;
	  var c;

	  graph.forEachUndirectedEdge(node, function (_e, attr, source, target) {
	    target = node === source ? target : source;

	    if (node > target) return;

	    c = entries[target];

	    if (!c) {
	      c = [];
	      entries[SIZE] += 1;
	      entries[target] = c;
	    }

	    c.push(attr);
	  });

	  return entries;
	}

	/**
	 * Function returning whether the given graphs have the same nodes.
	 *
	 * @param  {boolean} deep - Whether to perform deep comparisons.
	 * @param  {Graph}   G    - First graph.
	 * @param  {Graph}   H    - Second graph.
	 * @return {boolean}
	 */
	function abstractHaveSameNodes(deep, G, H) {
	  if (G === H) return true;

	  if (G.order !== H.order) return false;

	  return G.everyNode(function (node, attr) {
	    if (!H.hasNode(node)) return false;

	    if (!deep) return true;

	    return deepEqual(attr, H.getNodeAttributes(node));
	  });
	}

	/**
	 * Function returning whether the given graphs are identical.
	 *
	 * @param  {boolean} deep    - Whether to perform deep comparison.
	 * @param  {boolean} relaxed - Whether to allow graph options to differ.
	 * @param  {Graph}   G       - First graph.
	 * @param  {Graph}   H       - Second graph.
	 * @return {boolean}
	 */
	function abstractAreSameGraphs(deep, relaxed, G, H) {
	  if (G === H) return true;

	  // If two graphs have incompatible settings they cannot be identical
	  if (relaxed) {
	    if (
	      (G.type === 'directed' && H.type === 'undirected') ||
	      (G.type === 'undirected' && H.type === 'directed')
	    )
	      return false;
	  }

	  // If two graphs don't have the same settings they cannot be identical
	  else {
	    if (
	      G.type !== H.type ||
	      G.allowSelfLoops !== H.allowSelfLoops ||
	      G.multi !== H.multi
	    )
	      return false;
	  }

	  // If two graphs don't have the same number of typed edges, they cannot be identical
	  if (
	    G.directedSize !== H.directedSize ||
	    G.undirectedSize !== H.undirectedSize
	  )
	    return false;

	  // If two graphs don't have the same nodes they cannot be identical
	  if (!abstractHaveSameNodes(deep, G, H)) return false;

	  var sameDirectedEdges = false;
	  var sameUndirectedEdges = false;

	  // In the simple case we don't need refining
	  if (!G.multi && !H.multi) {
	    sameDirectedEdges = G.everyDirectedEdge(function (_e, _ea, source, target) {
	      if (!H.hasDirectedEdge(source, target)) return false;

	      if (!deep) return true;

	      return deepEqual(
	        G.getDirectedEdgeAttributes(source, target),
	        H.getDirectedEdgeAttributes(source, target)
	      );
	    });

	    if (!sameDirectedEdges) return false;

	    sameUndirectedEdges = G.everyUndirectedEdge(function (
	      _e,
	      _ea,
	      source,
	      target
	    ) {
	      if (!H.hasUndirectedEdge(source, target)) return false;

	      if (!deep) return true;

	      return deepEqual(
	        G.getUndirectedEdgeAttributes(source, target),
	        H.getUndirectedEdgeAttributes(source, target)
	      );
	    });

	    if (!sameUndirectedEdges) return false;
	  }

	  // In the multi case, things are a bit more complex
	  else {
	    var aggregationFunction = deep ? collectOutEdges : countOutEdges;
	    var comparisonFunction = deep ? compareNeighborEntries : deepEqual;

	    sameDirectedEdges = G.everyNode(function (node) {
	      var gCounts = aggregationFunction(G, node);
	      var hCounts = aggregationFunction(H, node);

	      return comparisonFunction(gCounts, hCounts);
	    });

	    if (!sameDirectedEdges) return false;

	    aggregationFunction = deep
	      ? collectAssymetricUndirectedEdges
	      : countAssymetricUndirectedEdges;

	    sameUndirectedEdges = G.everyNode(function (node) {
	      var gCounts = aggregationFunction(G, node);
	      var hCounts = aggregationFunction(H, node);

	      return comparisonFunction(gCounts, hCounts);
	    });

	    if (!sameUndirectedEdges) return false;
	  }

	  return true;
	}

	/**
	 * Exporting.
	 */
	assertions$2.isGraph = isGraph$N;
	assertions$2.isGraphConstructor = isGraphConstructor$e;
	assertions$2.haveSameNodes = abstractHaveSameNodes.bind(null, false);
	assertions$2.haveSameNodesDeep = abstractHaveSameNodes.bind(null, true);
	assertions$2.areSameGraphs = abstractAreSameGraphs.bind(null, false, false);
	assertions$2.areSameGraphsDeep = abstractAreSameGraphs.bind(null, true, false);
	assertions$2.haveSameEdges = abstractAreSameGraphs.bind(null, false, true);
	assertions$2.haveSameEdgesDeep = abstractAreSameGraphs.bind(null, true, true);

	var assertions$1 = assertions$2;

	var canvas$2 = {};

	var renderer = {};

	var helpers$c = {};

	var conversion = {};

	/**
	 * Graphology Defaults
	 * ====================
	 *
	 * Helper function used throughout the standard lib to resolve defaults.
	 */

	function isLeaf(o) {
	  return (
	    !o ||
	    typeof o !== 'object' ||
	    typeof o === 'function' ||
	    Array.isArray(o) ||
	    o instanceof Set ||
	    o instanceof Map ||
	    o instanceof RegExp ||
	    o instanceof Date
	  );
	}

	function resolveDefaults$f(target, defaults) {
	  target = target || {};

	  var output = {};

	  for (var k in defaults) {
	    var existing = target[k];
	    var def = defaults[k];

	    // Recursion
	    if (!isLeaf(def)) {
	      output[k] = resolveDefaults$f(existing, def);

	      continue;
	    }

	    // Leaf
	    if (existing === undefined) {
	      output[k] = def;
	    } else {
	      output[k] = existing;
	    }
	  }

	  return output;
	}

	var defaults$7 = resolveDefaults$f;

	var matrices$1 = {};

	/**
	 * Sigma.js WebGL Matrices Helpers
	 * ================================
	 *
	 * Matrices-related helper functions used by sigma's WebGL renderer.
	 * @module
	 */

	matrices$1.identity = function identity() {
	  return Float64Array.of(1, 0, 0, 0, 1, 0, 0, 0, 1);
	};

	matrices$1.scale = function scale(m, x, y) {
	  m[0] = x;
	  m[4] = typeof y === 'number' ? y : x;

	  return m;
	};

	matrices$1.rotate = function rotate(m, r) {
	  var s = Math.sin(r);
	  var c = Math.cos(r);

	  m[0] = c;
	  m[1] = s;
	  m[3] = -s;
	  m[4] = c;

	  return m;
	};

	matrices$1.translate = function translate(m, x, y) {
	  m[6] = x;
	  m[7] = y;

	  return m;
	};

	matrices$1.multiply = function multiply(a, b) {
	  var a00 = a[0];
	  var a01 = a[1];
	  var a02 = a[2];
	  var a10 = a[3];
	  var a11 = a[4];
	  var a12 = a[5];
	  var a20 = a[6];
	  var a21 = a[7];
	  var a22 = a[8];

	  var b00 = b[0];
	  var b01 = b[1];
	  var b02 = b[2];
	  var b10 = b[3];
	  var b11 = b[4];
	  var b12 = b[5];
	  var b20 = b[6];
	  var b21 = b[7];
	  var b22 = b[8];

	  a[0] = b00 * a00 + b01 * a10 + b02 * a20;
	  a[1] = b00 * a01 + b01 * a11 + b02 * a21;
	  a[2] = b00 * a02 + b01 * a12 + b02 * a22;

	  a[3] = b10 * a00 + b11 * a10 + b12 * a20;
	  a[4] = b10 * a01 + b11 * a11 + b12 * a21;
	  a[5] = b10 * a02 + b11 * a12 + b12 * a22;

	  a[6] = b20 * a00 + b21 * a10 + b22 * a20;
	  a[7] = b20 * a01 + b21 * a11 + b22 * a21;
	  a[8] = b20 * a02 + b21 * a12 + b22 * a22;

	  return a;
	};

	matrices$1.multiplyVec2 = function multiplyVec2(a, b) {
	  var a00 = a[0];
	  var a01 = a[1];
	  var a10 = a[3];
	  var a11 = a[4];
	  var a20 = a[6];
	  var a21 = a[7];

	  var b0 = b.x;
	  var b1 = b.y;

	  b.x = b0 * a00 + b1 * a10 + a20;
	  b.y = b0 * a01 + b1 * a11 + a21;
	};

	/**
	 * Graphology Conversion Functions
	 * ================================
	 *
	 * Miscellaneous utility functions used to convert coordinate systems.
	 */

	var resolveDefaults$e = defaults$7;
	var matrices = matrices$1;

	var identity$4 = matrices.identity;
	var multiply = matrices.multiply;
	var translate = matrices.translate;
	var scale = matrices.scale;
	var rotate = matrices.rotate;
	var multiplyVec2 = matrices.multiplyVec2;

	/**
	 * Function taken from sigma and returning a correction factor to suit the
	 * difference between the graph and the viewport's aspect ratio.
	 */
	function getAspectRatioCorrection(
	  graphWidth,
	  graphHeight,
	  viewportWidth,
	  viewportHeight
	) {
	  var viewportRatio = viewportHeight / viewportWidth;
	  var graphRatio = graphHeight / graphWidth;

	  // If the stage and the graphs are in different directions (such as the graph being wider that tall while the stage
	  // is taller than wide), we can stop here to have indeed nodes touching opposite sides:
	  if (
	    (viewportRatio < 1 && graphRatio > 1) ||
	    (viewportRatio > 1 && graphRatio < 1)
	  ) {
	    return 1;
	  }

	  // Else, we need to fit the graph inside the stage:
	  // 1. If the graph is "squarer" (ie. with a ratio closer to 1), we need to make the largest sides touch;
	  // 2. If the stage is "squarer", we need to make the smallest sides touch.
	  return Math.min(
	    Math.max(graphRatio, 1 / graphRatio),
	    Math.max(1 / viewportRatio, viewportRatio)
	  );
	}

	/**
	 * Factory for a function converting from an arbitrary graph space to a
	 * viewport one (like an HTML5 canvas, for instance).
	 */
	var DEFAULT_CAMERA = {
	  x: 0.5,
	  y: 0.5,
	  angle: 0,
	  ratio: 1
	};

	var CONVERSION_FUNCTION_DEFAULTS = {
	  camera: DEFAULT_CAMERA,
	  padding: 0
	};

	function createGraphToViewportConversionFunction$1(
	  graphExtent,
	  viewportDimensions,
	  options
	) {
	  // Resolving options
	  options = resolveDefaults$e(options, CONVERSION_FUNCTION_DEFAULTS);

	  var camera = options.camera;

	  // Computing graph dimensions
	  var maxGX = graphExtent.x[1];
	  var maxGY = graphExtent.y[1];
	  var minGX = graphExtent.x[0];
	  var minGY = graphExtent.y[0];

	  var graphWidth = maxGX - minGX;
	  var graphHeight = maxGY - minGY;

	  var viewportWidth = viewportDimensions.width;
	  var viewportHeight = viewportDimensions.height;

	  // Precomputing values
	  var graphRatio = Math.max(graphWidth, graphHeight) || 1;

	  var gdx = (maxGX + minGX) / 2;
	  var gdy = (maxGY + minGY) / 2;

	  var smallest = Math.min(viewportWidth, viewportHeight);
	  smallest -= 2 * options.padding;

	  var correction = getAspectRatioCorrection(
	    graphWidth,
	    graphHeight,
	    viewportWidth,
	    viewportHeight
	  );

	  var matrix = identity$4();

	  // Realigning with canvas coordinates
	  multiply(matrix, scale(identity$4(), viewportWidth / 2, viewportHeight / 2));
	  multiply(matrix, translate(identity$4(), 1, 1));
	  multiply(matrix, scale(identity$4(), 1, -1));

	  // Applying camera and transforming space
	  multiply(
	    matrix,
	    scale(
	      identity$4(),
	      2 * (smallest / viewportWidth) * correction,
	      2 * (smallest / viewportHeight) * correction
	    )
	  );
	  multiply(matrix, rotate(identity$4(), -camera.angle));
	  multiply(matrix, scale(identity$4(), 1 / camera.ratio));
	  multiply(matrix, translate(identity$4(), -camera.x, -camera.y));

	  // Normalizing graph space to squished square
	  multiply(matrix, translate(identity$4(), 0.5, 0.5));
	  multiply(matrix, scale(identity$4(), 1 / graphRatio));
	  multiply(matrix, translate(identity$4(), -gdx, -gdy));

	  // Assignation function
	  var assign = function (pos) {
	    // Applying matrix transformation
	    multiplyVec2(matrix, pos);

	    return pos;
	  };

	  // Immutable variant
	  var graphToViewport = function (pos) {
	    return assign({x: pos.x, y: pos.y});
	  };

	  graphToViewport.assign = assign;

	  return graphToViewport;
	}

	/**
	 * Exports.
	 */
	conversion.createGraphToViewportConversionFunction =
	  createGraphToViewportConversionFunction$1;

	var defaults$6 = {};

	/**
	 * Removes all key-value entries from the list cache.
	 *
	 * @private
	 * @name clear
	 * @memberOf ListCache
	 */

	function listCacheClear$1() {
	  this.__data__ = [];
	  this.size = 0;
	}

	var _listCacheClear = listCacheClear$1;

	/**
	 * Performs a
	 * [`SameValueZero`](http://ecma-international.org/ecma-262/7.0/#sec-samevaluezero)
	 * comparison between two values to determine if they are equivalent.
	 *
	 * @static
	 * @memberOf _
	 * @since 4.0.0
	 * @category Lang
	 * @param {*} value The value to compare.
	 * @param {*} other The other value to compare.
	 * @returns {boolean} Returns `true` if the values are equivalent, else `false`.
	 * @example
	 *
	 * var object = { 'a': 1 };
	 * var other = { 'a': 1 };
	 *
	 * _.eq(object, object);
	 * // => true
	 *
	 * _.eq(object, other);
	 * // => false
	 *
	 * _.eq('a', 'a');
	 * // => true
	 *
	 * _.eq('a', Object('a'));
	 * // => false
	 *
	 * _.eq(NaN, NaN);
	 * // => true
	 */

	function eq$4(value, other) {
	  return value === other || (value !== value && other !== other);
	}

	var eq_1 = eq$4;

	var eq$3 = eq_1;

	/**
	 * Gets the index at which the `key` is found in `array` of key-value pairs.
	 *
	 * @private
	 * @param {Array} array The array to inspect.
	 * @param {*} key The key to search for.
	 * @returns {number} Returns the index of the matched value, else `-1`.
	 */
	function assocIndexOf$4(array, key) {
	  var length = array.length;
	  while (length--) {
	    if (eq$3(array[length][0], key)) {
	      return length;
	    }
	  }
	  return -1;
	}

	var _assocIndexOf = assocIndexOf$4;

	var assocIndexOf$3 = _assocIndexOf;

	/** Used for built-in method references. */
	var arrayProto = Array.prototype;

	/** Built-in value references. */
	var splice = arrayProto.splice;

	/**
	 * Removes `key` and its value from the list cache.
	 *
	 * @private
	 * @name delete
	 * @memberOf ListCache
	 * @param {string} key The key of the value to remove.
	 * @returns {boolean} Returns `true` if the entry was removed, else `false`.
	 */
	function listCacheDelete$1(key) {
	  var data = this.__data__,
	      index = assocIndexOf$3(data, key);

	  if (index < 0) {
	    return false;
	  }
	  var lastIndex = data.length - 1;
	  if (index == lastIndex) {
	    data.pop();
	  } else {
	    splice.call(data, index, 1);
	  }
	  --this.size;
	  return true;
	}

	var _listCacheDelete = listCacheDelete$1;

	var assocIndexOf$2 = _assocIndexOf;

	/**
	 * Gets the list cache value for `key`.
	 *
	 * @private
	 * @name get
	 * @memberOf ListCache
	 * @param {string} key The key of the value to get.
	 * @returns {*} Returns the entry value.
	 */
	function listCacheGet$1(key) {
	  var data = this.__data__,
	      index = assocIndexOf$2(data, key);

	  return index < 0 ? undefined : data[index][1];
	}

	var _listCacheGet = listCacheGet$1;

	var assocIndexOf$1 = _assocIndexOf;

	/**
	 * Checks if a list cache value for `key` exists.
	 *
	 * @private
	 * @name has
	 * @memberOf ListCache
	 * @param {string} key The key of the entry to check.
	 * @returns {boolean} Returns `true` if an entry for `key` exists, else `false`.
	 */
	function listCacheHas$1(key) {
	  return assocIndexOf$1(this.__data__, key) > -1;
	}

	var _listCacheHas = listCacheHas$1;

	var assocIndexOf = _assocIndexOf;

	/**
	 * Sets the list cache `key` to `value`.
	 *
	 * @private
	 * @name set
	 * @memberOf ListCache
	 * @param {string} key The key of the value to set.
	 * @param {*} value The value to set.
	 * @returns {Object} Returns the list cache instance.
	 */
	function listCacheSet$1(key, value) {
	  var data = this.__data__,
	      index = assocIndexOf(data, key);

	  if (index < 0) {
	    ++this.size;
	    data.push([key, value]);
	  } else {
	    data[index][1] = value;
	  }
	  return this;
	}

	var _listCacheSet = listCacheSet$1;

	var listCacheClear = _listCacheClear,
	    listCacheDelete = _listCacheDelete,
	    listCacheGet = _listCacheGet,
	    listCacheHas = _listCacheHas,
	    listCacheSet = _listCacheSet;

	/**
	 * Creates an list cache object.
	 *
	 * @private
	 * @constructor
	 * @param {Array} [entries] The key-value pairs to cache.
	 */
	function ListCache$4(entries) {
	  var index = -1,
	      length = entries == null ? 0 : entries.length;

	  this.clear();
	  while (++index < length) {
	    var entry = entries[index];
	    this.set(entry[0], entry[1]);
	  }
	}

	// Add methods to `ListCache`.
	ListCache$4.prototype.clear = listCacheClear;
	ListCache$4.prototype['delete'] = listCacheDelete;
	ListCache$4.prototype.get = listCacheGet;
	ListCache$4.prototype.has = listCacheHas;
	ListCache$4.prototype.set = listCacheSet;

	var _ListCache = ListCache$4;

	var ListCache$3 = _ListCache;

	/**
	 * Removes all key-value entries from the stack.
	 *
	 * @private
	 * @name clear
	 * @memberOf Stack
	 */
	function stackClear$1() {
	  this.__data__ = new ListCache$3;
	  this.size = 0;
	}

	var _stackClear = stackClear$1;

	/**
	 * Removes `key` and its value from the stack.
	 *
	 * @private
	 * @name delete
	 * @memberOf Stack
	 * @param {string} key The key of the value to remove.
	 * @returns {boolean} Returns `true` if the entry was removed, else `false`.
	 */

	function stackDelete$1(key) {
	  var data = this.__data__,
	      result = data['delete'](key);

	  this.size = data.size;
	  return result;
	}

	var _stackDelete = stackDelete$1;

	/**
	 * Gets the stack value for `key`.
	 *
	 * @private
	 * @name get
	 * @memberOf Stack
	 * @param {string} key The key of the value to get.
	 * @returns {*} Returns the entry value.
	 */

	function stackGet$1(key) {
	  return this.__data__.get(key);
	}

	var _stackGet = stackGet$1;

	/**
	 * Checks if a stack value for `key` exists.
	 *
	 * @private
	 * @name has
	 * @memberOf Stack
	 * @param {string} key The key of the entry to check.
	 * @returns {boolean} Returns `true` if an entry for `key` exists, else `false`.
	 */

	function stackHas$1(key) {
	  return this.__data__.has(key);
	}

	var _stackHas = stackHas$1;

	/** Detect free variable `global` from Node.js. */

	var freeGlobal$1 = typeof commonjsGlobal == 'object' && commonjsGlobal && commonjsGlobal.Object === Object && commonjsGlobal;

	var _freeGlobal = freeGlobal$1;

	var freeGlobal = _freeGlobal;

	/** Detect free variable `self`. */
	var freeSelf = typeof self == 'object' && self && self.Object === Object && self;

	/** Used as a reference to the global object. */
	var root$4 = freeGlobal || freeSelf || Function('return this')();

	var _root = root$4;

	var root$3 = _root;

	/** Built-in value references. */
	var Symbol$3 = root$3.Symbol;

	var _Symbol = Symbol$3;

	var Symbol$2 = _Symbol;

	/** Used for built-in method references. */
	var objectProto$a = Object.prototype;

	/** Used to check objects for own properties. */
	var hasOwnProperty$8 = objectProto$a.hasOwnProperty;

	/**
	 * Used to resolve the
	 * [`toStringTag`](http://ecma-international.org/ecma-262/7.0/#sec-object.prototype.tostring)
	 * of values.
	 */
	var nativeObjectToString$1 = objectProto$a.toString;

	/** Built-in value references. */
	var symToStringTag$1 = Symbol$2 ? Symbol$2.toStringTag : undefined;

	/**
	 * A specialized version of `baseGetTag` which ignores `Symbol.toStringTag` values.
	 *
	 * @private
	 * @param {*} value The value to query.
	 * @returns {string} Returns the raw `toStringTag`.
	 */
	function getRawTag$1(value) {
	  var isOwn = hasOwnProperty$8.call(value, symToStringTag$1),
	      tag = value[symToStringTag$1];

	  try {
	    value[symToStringTag$1] = undefined;
	    var unmasked = true;
	  } catch (e) {}

	  var result = nativeObjectToString$1.call(value);
	  if (unmasked) {
	    if (isOwn) {
	      value[symToStringTag$1] = tag;
	    } else {
	      delete value[symToStringTag$1];
	    }
	  }
	  return result;
	}

	var _getRawTag = getRawTag$1;

	/** Used for built-in method references. */

	var objectProto$9 = Object.prototype;

	/**
	 * Used to resolve the
	 * [`toStringTag`](http://ecma-international.org/ecma-262/7.0/#sec-object.prototype.tostring)
	 * of values.
	 */
	var nativeObjectToString = objectProto$9.toString;

	/**
	 * Converts `value` to a string using `Object.prototype.toString`.
	 *
	 * @private
	 * @param {*} value The value to convert.
	 * @returns {string} Returns the converted string.
	 */
	function objectToString$1(value) {
	  return nativeObjectToString.call(value);
	}

	var _objectToString = objectToString$1;

	var Symbol$1 = _Symbol,
	    getRawTag = _getRawTag,
	    objectToString = _objectToString;

	/** `Object#toString` result references. */
	var nullTag = '[object Null]',
	    undefinedTag = '[object Undefined]';

	/** Built-in value references. */
	var symToStringTag = Symbol$1 ? Symbol$1.toStringTag : undefined;

	/**
	 * The base implementation of `getTag` without fallbacks for buggy environments.
	 *
	 * @private
	 * @param {*} value The value to query.
	 * @returns {string} Returns the `toStringTag`.
	 */
	function baseGetTag$4(value) {
	  if (value == null) {
	    return value === undefined ? undefinedTag : nullTag;
	  }
	  return (symToStringTag && symToStringTag in Object(value))
	    ? getRawTag(value)
	    : objectToString(value);
	}

	var _baseGetTag = baseGetTag$4;

	/**
	 * Checks if `value` is the
	 * [language type](http://www.ecma-international.org/ecma-262/7.0/#sec-ecmascript-language-types)
	 * of `Object`. (e.g. arrays, functions, objects, regexes, `new Number(0)`, and `new String('')`)
	 *
	 * @static
	 * @memberOf _
	 * @since 0.1.0
	 * @category Lang
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is an object, else `false`.
	 * @example
	 *
	 * _.isObject({});
	 * // => true
	 *
	 * _.isObject([1, 2, 3]);
	 * // => true
	 *
	 * _.isObject(_.noop);
	 * // => true
	 *
	 * _.isObject(null);
	 * // => false
	 */

	function isObject$7(value) {
	  var type = typeof value;
	  return value != null && (type == 'object' || type == 'function');
	}

	var isObject_1 = isObject$7;

	var baseGetTag$3 = _baseGetTag,
	    isObject$6 = isObject_1;

	/** `Object#toString` result references. */
	var asyncTag = '[object AsyncFunction]',
	    funcTag$1 = '[object Function]',
	    genTag = '[object GeneratorFunction]',
	    proxyTag = '[object Proxy]';

	/**
	 * Checks if `value` is classified as a `Function` object.
	 *
	 * @static
	 * @memberOf _
	 * @since 0.1.0
	 * @category Lang
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is a function, else `false`.
	 * @example
	 *
	 * _.isFunction(_);
	 * // => true
	 *
	 * _.isFunction(/abc/);
	 * // => false
	 */
	function isFunction$3(value) {
	  if (!isObject$6(value)) {
	    return false;
	  }
	  // The use of `Object#toString` avoids issues with the `typeof` operator
	  // in Safari 9 which returns 'object' for typed arrays and other constructors.
	  var tag = baseGetTag$3(value);
	  return tag == funcTag$1 || tag == genTag || tag == asyncTag || tag == proxyTag;
	}

	var isFunction_1 = isFunction$3;

	var root$2 = _root;

	/** Used to detect overreaching core-js shims. */
	var coreJsData$1 = root$2['__core-js_shared__'];

	var _coreJsData = coreJsData$1;

	var coreJsData = _coreJsData;

	/** Used to detect methods masquerading as native. */
	var maskSrcKey = (function() {
	  var uid = /[^.]+$/.exec(coreJsData && coreJsData.keys && coreJsData.keys.IE_PROTO || '');
	  return uid ? ('Symbol(src)_1.' + uid) : '';
	}());

	/**
	 * Checks if `func` has its source masked.
	 *
	 * @private
	 * @param {Function} func The function to check.
	 * @returns {boolean} Returns `true` if `func` is masked, else `false`.
	 */
	function isMasked$1(func) {
	  return !!maskSrcKey && (maskSrcKey in func);
	}

	var _isMasked = isMasked$1;

	/** Used for built-in method references. */

	var funcProto$2 = Function.prototype;

	/** Used to resolve the decompiled source of functions. */
	var funcToString$2 = funcProto$2.toString;

	/**
	 * Converts `func` to its source code.
	 *
	 * @private
	 * @param {Function} func The function to convert.
	 * @returns {string} Returns the source code.
	 */
	function toSource$1(func) {
	  if (func != null) {
	    try {
	      return funcToString$2.call(func);
	    } catch (e) {}
	    try {
	      return (func + '');
	    } catch (e) {}
	  }
	  return '';
	}

	var _toSource = toSource$1;

	var isFunction$2 = isFunction_1,
	    isMasked = _isMasked,
	    isObject$5 = isObject_1,
	    toSource = _toSource;

	/**
	 * Used to match `RegExp`
	 * [syntax characters](http://ecma-international.org/ecma-262/7.0/#sec-patterns).
	 */
	var reRegExpChar = /[\\^$.*+?()[\]{}|]/g;

	/** Used to detect host constructors (Safari). */
	var reIsHostCtor = /^\[object .+?Constructor\]$/;

	/** Used for built-in method references. */
	var funcProto$1 = Function.prototype,
	    objectProto$8 = Object.prototype;

	/** Used to resolve the decompiled source of functions. */
	var funcToString$1 = funcProto$1.toString;

	/** Used to check objects for own properties. */
	var hasOwnProperty$7 = objectProto$8.hasOwnProperty;

	/** Used to detect if a method is native. */
	var reIsNative = RegExp('^' +
	  funcToString$1.call(hasOwnProperty$7).replace(reRegExpChar, '\\$&')
	  .replace(/hasOwnProperty|(function).*?(?=\\\()| for .+?(?=\\\])/g, '$1.*?') + '$'
	);

	/**
	 * The base implementation of `_.isNative` without bad shim checks.
	 *
	 * @private
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is a native function,
	 *  else `false`.
	 */
	function baseIsNative$1(value) {
	  if (!isObject$5(value) || isMasked(value)) {
	    return false;
	  }
	  var pattern = isFunction$2(value) ? reIsNative : reIsHostCtor;
	  return pattern.test(toSource(value));
	}

	var _baseIsNative = baseIsNative$1;

	/**
	 * Gets the value at `key` of `object`.
	 *
	 * @private
	 * @param {Object} [object] The object to query.
	 * @param {string} key The key of the property to get.
	 * @returns {*} Returns the property value.
	 */

	function getValue$1(object, key) {
	  return object == null ? undefined : object[key];
	}

	var _getValue = getValue$1;

	var baseIsNative = _baseIsNative,
	    getValue = _getValue;

	/**
	 * Gets the native function at `key` of `object`.
	 *
	 * @private
	 * @param {Object} object The object to query.
	 * @param {string} key The key of the method to get.
	 * @returns {*} Returns the function if it's native, else `undefined`.
	 */
	function getNative$3(object, key) {
	  var value = getValue(object, key);
	  return baseIsNative(value) ? value : undefined;
	}

	var _getNative = getNative$3;

	var getNative$2 = _getNative,
	    root$1 = _root;

	/* Built-in method references that are verified to be native. */
	var Map$3 = getNative$2(root$1, 'Map');

	var _Map = Map$3;

	var getNative$1 = _getNative;

	/* Built-in method references that are verified to be native. */
	var nativeCreate$4 = getNative$1(Object, 'create');

	var _nativeCreate = nativeCreate$4;

	var nativeCreate$3 = _nativeCreate;

	/**
	 * Removes all key-value entries from the hash.
	 *
	 * @private
	 * @name clear
	 * @memberOf Hash
	 */
	function hashClear$1() {
	  this.__data__ = nativeCreate$3 ? nativeCreate$3(null) : {};
	  this.size = 0;
	}

	var _hashClear = hashClear$1;

	/**
	 * Removes `key` and its value from the hash.
	 *
	 * @private
	 * @name delete
	 * @memberOf Hash
	 * @param {Object} hash The hash to modify.
	 * @param {string} key The key of the value to remove.
	 * @returns {boolean} Returns `true` if the entry was removed, else `false`.
	 */

	function hashDelete$1(key) {
	  var result = this.has(key) && delete this.__data__[key];
	  this.size -= result ? 1 : 0;
	  return result;
	}

	var _hashDelete = hashDelete$1;

	var nativeCreate$2 = _nativeCreate;

	/** Used to stand-in for `undefined` hash values. */
	var HASH_UNDEFINED$1 = '__lodash_hash_undefined__';

	/** Used for built-in method references. */
	var objectProto$7 = Object.prototype;

	/** Used to check objects for own properties. */
	var hasOwnProperty$6 = objectProto$7.hasOwnProperty;

	/**
	 * Gets the hash value for `key`.
	 *
	 * @private
	 * @name get
	 * @memberOf Hash
	 * @param {string} key The key of the value to get.
	 * @returns {*} Returns the entry value.
	 */
	function hashGet$1(key) {
	  var data = this.__data__;
	  if (nativeCreate$2) {
	    var result = data[key];
	    return result === HASH_UNDEFINED$1 ? undefined : result;
	  }
	  return hasOwnProperty$6.call(data, key) ? data[key] : undefined;
	}

	var _hashGet = hashGet$1;

	var nativeCreate$1 = _nativeCreate;

	/** Used for built-in method references. */
	var objectProto$6 = Object.prototype;

	/** Used to check objects for own properties. */
	var hasOwnProperty$5 = objectProto$6.hasOwnProperty;

	/**
	 * Checks if a hash value for `key` exists.
	 *
	 * @private
	 * @name has
	 * @memberOf Hash
	 * @param {string} key The key of the entry to check.
	 * @returns {boolean} Returns `true` if an entry for `key` exists, else `false`.
	 */
	function hashHas$1(key) {
	  var data = this.__data__;
	  return nativeCreate$1 ? (data[key] !== undefined) : hasOwnProperty$5.call(data, key);
	}

	var _hashHas = hashHas$1;

	var nativeCreate = _nativeCreate;

	/** Used to stand-in for `undefined` hash values. */
	var HASH_UNDEFINED = '__lodash_hash_undefined__';

	/**
	 * Sets the hash `key` to `value`.
	 *
	 * @private
	 * @name set
	 * @memberOf Hash
	 * @param {string} key The key of the value to set.
	 * @param {*} value The value to set.
	 * @returns {Object} Returns the hash instance.
	 */
	function hashSet$1(key, value) {
	  var data = this.__data__;
	  this.size += this.has(key) ? 0 : 1;
	  data[key] = (nativeCreate && value === undefined) ? HASH_UNDEFINED : value;
	  return this;
	}

	var _hashSet = hashSet$1;

	var hashClear = _hashClear,
	    hashDelete = _hashDelete,
	    hashGet = _hashGet,
	    hashHas = _hashHas,
	    hashSet = _hashSet;

	/**
	 * Creates a hash object.
	 *
	 * @private
	 * @constructor
	 * @param {Array} [entries] The key-value pairs to cache.
	 */
	function Hash$1(entries) {
	  var index = -1,
	      length = entries == null ? 0 : entries.length;

	  this.clear();
	  while (++index < length) {
	    var entry = entries[index];
	    this.set(entry[0], entry[1]);
	  }
	}

	// Add methods to `Hash`.
	Hash$1.prototype.clear = hashClear;
	Hash$1.prototype['delete'] = hashDelete;
	Hash$1.prototype.get = hashGet;
	Hash$1.prototype.has = hashHas;
	Hash$1.prototype.set = hashSet;

	var _Hash = Hash$1;

	var Hash = _Hash,
	    ListCache$2 = _ListCache,
	    Map$2 = _Map;

	/**
	 * Removes all key-value entries from the map.
	 *
	 * @private
	 * @name clear
	 * @memberOf MapCache
	 */
	function mapCacheClear$1() {
	  this.size = 0;
	  this.__data__ = {
	    'hash': new Hash,
	    'map': new (Map$2 || ListCache$2),
	    'string': new Hash
	  };
	}

	var _mapCacheClear = mapCacheClear$1;

	/**
	 * Checks if `value` is suitable for use as unique object key.
	 *
	 * @private
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is suitable, else `false`.
	 */

	function isKeyable$1(value) {
	  var type = typeof value;
	  return (type == 'string' || type == 'number' || type == 'symbol' || type == 'boolean')
	    ? (value !== '__proto__')
	    : (value === null);
	}

	var _isKeyable = isKeyable$1;

	var isKeyable = _isKeyable;

	/**
	 * Gets the data for `map`.
	 *
	 * @private
	 * @param {Object} map The map to query.
	 * @param {string} key The reference key.
	 * @returns {*} Returns the map data.
	 */
	function getMapData$4(map, key) {
	  var data = map.__data__;
	  return isKeyable(key)
	    ? data[typeof key == 'string' ? 'string' : 'hash']
	    : data.map;
	}

	var _getMapData = getMapData$4;

	var getMapData$3 = _getMapData;

	/**
	 * Removes `key` and its value from the map.
	 *
	 * @private
	 * @name delete
	 * @memberOf MapCache
	 * @param {string} key The key of the value to remove.
	 * @returns {boolean} Returns `true` if the entry was removed, else `false`.
	 */
	function mapCacheDelete$1(key) {
	  var result = getMapData$3(this, key)['delete'](key);
	  this.size -= result ? 1 : 0;
	  return result;
	}

	var _mapCacheDelete = mapCacheDelete$1;

	var getMapData$2 = _getMapData;

	/**
	 * Gets the map value for `key`.
	 *
	 * @private
	 * @name get
	 * @memberOf MapCache
	 * @param {string} key The key of the value to get.
	 * @returns {*} Returns the entry value.
	 */
	function mapCacheGet$1(key) {
	  return getMapData$2(this, key).get(key);
	}

	var _mapCacheGet = mapCacheGet$1;

	var getMapData$1 = _getMapData;

	/**
	 * Checks if a map value for `key` exists.
	 *
	 * @private
	 * @name has
	 * @memberOf MapCache
	 * @param {string} key The key of the entry to check.
	 * @returns {boolean} Returns `true` if an entry for `key` exists, else `false`.
	 */
	function mapCacheHas$1(key) {
	  return getMapData$1(this, key).has(key);
	}

	var _mapCacheHas = mapCacheHas$1;

	var getMapData = _getMapData;

	/**
	 * Sets the map `key` to `value`.
	 *
	 * @private
	 * @name set
	 * @memberOf MapCache
	 * @param {string} key The key of the value to set.
	 * @param {*} value The value to set.
	 * @returns {Object} Returns the map cache instance.
	 */
	function mapCacheSet$1(key, value) {
	  var data = getMapData(this, key),
	      size = data.size;

	  data.set(key, value);
	  this.size += data.size == size ? 0 : 1;
	  return this;
	}

	var _mapCacheSet = mapCacheSet$1;

	var mapCacheClear = _mapCacheClear,
	    mapCacheDelete = _mapCacheDelete,
	    mapCacheGet = _mapCacheGet,
	    mapCacheHas = _mapCacheHas,
	    mapCacheSet = _mapCacheSet;

	/**
	 * Creates a map cache object to store key-value pairs.
	 *
	 * @private
	 * @constructor
	 * @param {Array} [entries] The key-value pairs to cache.
	 */
	function MapCache$1(entries) {
	  var index = -1,
	      length = entries == null ? 0 : entries.length;

	  this.clear();
	  while (++index < length) {
	    var entry = entries[index];
	    this.set(entry[0], entry[1]);
	  }
	}

	// Add methods to `MapCache`.
	MapCache$1.prototype.clear = mapCacheClear;
	MapCache$1.prototype['delete'] = mapCacheDelete;
	MapCache$1.prototype.get = mapCacheGet;
	MapCache$1.prototype.has = mapCacheHas;
	MapCache$1.prototype.set = mapCacheSet;

	var _MapCache = MapCache$1;

	var ListCache$1 = _ListCache,
	    Map$1 = _Map,
	    MapCache = _MapCache;

	/** Used as the size to enable large array optimizations. */
	var LARGE_ARRAY_SIZE = 200;

	/**
	 * Sets the stack `key` to `value`.
	 *
	 * @private
	 * @name set
	 * @memberOf Stack
	 * @param {string} key The key of the value to set.
	 * @param {*} value The value to set.
	 * @returns {Object} Returns the stack cache instance.
	 */
	function stackSet$1(key, value) {
	  var data = this.__data__;
	  if (data instanceof ListCache$1) {
	    var pairs = data.__data__;
	    if (!Map$1 || (pairs.length < LARGE_ARRAY_SIZE - 1)) {
	      pairs.push([key, value]);
	      this.size = ++data.size;
	      return this;
	    }
	    data = this.__data__ = new MapCache(pairs);
	  }
	  data.set(key, value);
	  this.size = data.size;
	  return this;
	}

	var _stackSet = stackSet$1;

	var ListCache = _ListCache,
	    stackClear = _stackClear,
	    stackDelete = _stackDelete,
	    stackGet = _stackGet,
	    stackHas = _stackHas,
	    stackSet = _stackSet;

	/**
	 * Creates a stack cache object to store key-value pairs.
	 *
	 * @private
	 * @constructor
	 * @param {Array} [entries] The key-value pairs to cache.
	 */
	function Stack$1(entries) {
	  var data = this.__data__ = new ListCache(entries);
	  this.size = data.size;
	}

	// Add methods to `Stack`.
	Stack$1.prototype.clear = stackClear;
	Stack$1.prototype['delete'] = stackDelete;
	Stack$1.prototype.get = stackGet;
	Stack$1.prototype.has = stackHas;
	Stack$1.prototype.set = stackSet;

	var _Stack = Stack$1;

	var getNative = _getNative;

	var defineProperty$2 = (function() {
	  try {
	    var func = getNative(Object, 'defineProperty');
	    func({}, '', {});
	    return func;
	  } catch (e) {}
	}());

	var _defineProperty = defineProperty$2;

	var defineProperty$1 = _defineProperty;

	/**
	 * The base implementation of `assignValue` and `assignMergeValue` without
	 * value checks.
	 *
	 * @private
	 * @param {Object} object The object to modify.
	 * @param {string} key The key of the property to assign.
	 * @param {*} value The value to assign.
	 */
	function baseAssignValue$3(object, key, value) {
	  if (key == '__proto__' && defineProperty$1) {
	    defineProperty$1(object, key, {
	      'configurable': true,
	      'enumerable': true,
	      'value': value,
	      'writable': true
	    });
	  } else {
	    object[key] = value;
	  }
	}

	var _baseAssignValue = baseAssignValue$3;

	var baseAssignValue$2 = _baseAssignValue,
	    eq$2 = eq_1;

	/**
	 * This function is like `assignValue` except that it doesn't assign
	 * `undefined` values.
	 *
	 * @private
	 * @param {Object} object The object to modify.
	 * @param {string} key The key of the property to assign.
	 * @param {*} value The value to assign.
	 */
	function assignMergeValue$2(object, key, value) {
	  if ((value !== undefined && !eq$2(object[key], value)) ||
	      (value === undefined && !(key in object))) {
	    baseAssignValue$2(object, key, value);
	  }
	}

	var _assignMergeValue = assignMergeValue$2;

	/**
	 * Creates a base function for methods like `_.forIn` and `_.forOwn`.
	 *
	 * @private
	 * @param {boolean} [fromRight] Specify iterating from right to left.
	 * @returns {Function} Returns the new base function.
	 */

	function createBaseFor$1(fromRight) {
	  return function(object, iteratee, keysFunc) {
	    var index = -1,
	        iterable = Object(object),
	        props = keysFunc(object),
	        length = props.length;

	    while (length--) {
	      var key = props[fromRight ? length : ++index];
	      if (iteratee(iterable[key], key, iterable) === false) {
	        break;
	      }
	    }
	    return object;
	  };
	}

	var _createBaseFor = createBaseFor$1;

	var createBaseFor = _createBaseFor;

	/**
	 * The base implementation of `baseForOwn` which iterates over `object`
	 * properties returned by `keysFunc` and invokes `iteratee` for each property.
	 * Iteratee functions may exit iteration early by explicitly returning `false`.
	 *
	 * @private
	 * @param {Object} object The object to iterate over.
	 * @param {Function} iteratee The function invoked per iteration.
	 * @param {Function} keysFunc The function to get the keys of `object`.
	 * @returns {Object} Returns `object`.
	 */
	var baseFor$1 = createBaseFor();

	var _baseFor = baseFor$1;

	var _cloneBuffer = {exports: {}};

	(function (module, exports) {
	var root = _root;

	/** Detect free variable `exports`. */
	var freeExports = exports && !exports.nodeType && exports;

	/** Detect free variable `module`. */
	var freeModule = freeExports && 'object' == 'object' && module && !module.nodeType && module;

	/** Detect the popular CommonJS extension `module.exports`. */
	var moduleExports = freeModule && freeModule.exports === freeExports;

	/** Built-in value references. */
	var Buffer = moduleExports ? root.Buffer : undefined,
	    allocUnsafe = Buffer ? Buffer.allocUnsafe : undefined;

	/**
	 * Creates a clone of  `buffer`.
	 *
	 * @private
	 * @param {Buffer} buffer The buffer to clone.
	 * @param {boolean} [isDeep] Specify a deep clone.
	 * @returns {Buffer} Returns the cloned buffer.
	 */
	function cloneBuffer(buffer, isDeep) {
	  if (isDeep) {
	    return buffer.slice();
	  }
	  var length = buffer.length,
	      result = allocUnsafe ? allocUnsafe(length) : new buffer.constructor(length);

	  buffer.copy(result);
	  return result;
	}

	module.exports = cloneBuffer;
	}(_cloneBuffer, _cloneBuffer.exports));

	var root = _root;

	/** Built-in value references. */
	var Uint8Array$2 = root.Uint8Array;

	var _Uint8Array = Uint8Array$2;

	var Uint8Array$1 = _Uint8Array;

	/**
	 * Creates a clone of `arrayBuffer`.
	 *
	 * @private
	 * @param {ArrayBuffer} arrayBuffer The array buffer to clone.
	 * @returns {ArrayBuffer} Returns the cloned array buffer.
	 */
	function cloneArrayBuffer$1(arrayBuffer) {
	  var result = new arrayBuffer.constructor(arrayBuffer.byteLength);
	  new Uint8Array$1(result).set(new Uint8Array$1(arrayBuffer));
	  return result;
	}

	var _cloneArrayBuffer = cloneArrayBuffer$1;

	var cloneArrayBuffer = _cloneArrayBuffer;

	/**
	 * Creates a clone of `typedArray`.
	 *
	 * @private
	 * @param {Object} typedArray The typed array to clone.
	 * @param {boolean} [isDeep] Specify a deep clone.
	 * @returns {Object} Returns the cloned typed array.
	 */
	function cloneTypedArray$1(typedArray, isDeep) {
	  var buffer = isDeep ? cloneArrayBuffer(typedArray.buffer) : typedArray.buffer;
	  return new typedArray.constructor(buffer, typedArray.byteOffset, typedArray.length);
	}

	var _cloneTypedArray = cloneTypedArray$1;

	/**
	 * Copies the values of `source` to `array`.
	 *
	 * @private
	 * @param {Array} source The array to copy values from.
	 * @param {Array} [array=[]] The array to copy values to.
	 * @returns {Array} Returns `array`.
	 */

	function copyArray$1(source, array) {
	  var index = -1,
	      length = source.length;

	  array || (array = Array(length));
	  while (++index < length) {
	    array[index] = source[index];
	  }
	  return array;
	}

	var _copyArray = copyArray$1;

	var isObject$4 = isObject_1;

	/** Built-in value references. */
	var objectCreate = Object.create;

	/**
	 * The base implementation of `_.create` without support for assigning
	 * properties to the created object.
	 *
	 * @private
	 * @param {Object} proto The object to inherit from.
	 * @returns {Object} Returns the new object.
	 */
	var baseCreate$1 = (function() {
	  function object() {}
	  return function(proto) {
	    if (!isObject$4(proto)) {
	      return {};
	    }
	    if (objectCreate) {
	      return objectCreate(proto);
	    }
	    object.prototype = proto;
	    var result = new object;
	    object.prototype = undefined;
	    return result;
	  };
	}());

	var _baseCreate = baseCreate$1;

	/**
	 * Creates a unary function that invokes `func` with its argument transformed.
	 *
	 * @private
	 * @param {Function} func The function to wrap.
	 * @param {Function} transform The argument transform.
	 * @returns {Function} Returns the new function.
	 */

	function overArg$1(func, transform) {
	  return function(arg) {
	    return func(transform(arg));
	  };
	}

	var _overArg = overArg$1;

	var overArg = _overArg;

	/** Built-in value references. */
	var getPrototype$2 = overArg(Object.getPrototypeOf, Object);

	var _getPrototype = getPrototype$2;

	/** Used for built-in method references. */

	var objectProto$5 = Object.prototype;

	/**
	 * Checks if `value` is likely a prototype object.
	 *
	 * @private
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is a prototype, else `false`.
	 */
	function isPrototype$2(value) {
	  var Ctor = value && value.constructor,
	      proto = (typeof Ctor == 'function' && Ctor.prototype) || objectProto$5;

	  return value === proto;
	}

	var _isPrototype = isPrototype$2;

	var baseCreate = _baseCreate,
	    getPrototype$1 = _getPrototype,
	    isPrototype$1 = _isPrototype;

	/**
	 * Initializes an object clone.
	 *
	 * @private
	 * @param {Object} object The object to clone.
	 * @returns {Object} Returns the initialized clone.
	 */
	function initCloneObject$1(object) {
	  return (typeof object.constructor == 'function' && !isPrototype$1(object))
	    ? baseCreate(getPrototype$1(object))
	    : {};
	}

	var _initCloneObject = initCloneObject$1;

	/**
	 * Checks if `value` is object-like. A value is object-like if it's not `null`
	 * and has a `typeof` result of "object".
	 *
	 * @static
	 * @memberOf _
	 * @since 4.0.0
	 * @category Lang
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is object-like, else `false`.
	 * @example
	 *
	 * _.isObjectLike({});
	 * // => true
	 *
	 * _.isObjectLike([1, 2, 3]);
	 * // => true
	 *
	 * _.isObjectLike(_.noop);
	 * // => false
	 *
	 * _.isObjectLike(null);
	 * // => false
	 */

	function isObjectLike$5(value) {
	  return value != null && typeof value == 'object';
	}

	var isObjectLike_1 = isObjectLike$5;

	var baseGetTag$2 = _baseGetTag,
	    isObjectLike$4 = isObjectLike_1;

	/** `Object#toString` result references. */
	var argsTag$1 = '[object Arguments]';

	/**
	 * The base implementation of `_.isArguments`.
	 *
	 * @private
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is an `arguments` object,
	 */
	function baseIsArguments$1(value) {
	  return isObjectLike$4(value) && baseGetTag$2(value) == argsTag$1;
	}

	var _baseIsArguments = baseIsArguments$1;

	var baseIsArguments = _baseIsArguments,
	    isObjectLike$3 = isObjectLike_1;

	/** Used for built-in method references. */
	var objectProto$4 = Object.prototype;

	/** Used to check objects for own properties. */
	var hasOwnProperty$4 = objectProto$4.hasOwnProperty;

	/** Built-in value references. */
	var propertyIsEnumerable = objectProto$4.propertyIsEnumerable;

	/**
	 * Checks if `value` is likely an `arguments` object.
	 *
	 * @static
	 * @memberOf _
	 * @since 0.1.0
	 * @category Lang
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is an `arguments` object,
	 *  else `false`.
	 * @example
	 *
	 * _.isArguments(function() { return arguments; }());
	 * // => true
	 *
	 * _.isArguments([1, 2, 3]);
	 * // => false
	 */
	var isArguments$2 = baseIsArguments(function() { return arguments; }()) ? baseIsArguments : function(value) {
	  return isObjectLike$3(value) && hasOwnProperty$4.call(value, 'callee') &&
	    !propertyIsEnumerable.call(value, 'callee');
	};

	var isArguments_1 = isArguments$2;

	/**
	 * Checks if `value` is classified as an `Array` object.
	 *
	 * @static
	 * @memberOf _
	 * @since 0.1.0
	 * @category Lang
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is an array, else `false`.
	 * @example
	 *
	 * _.isArray([1, 2, 3]);
	 * // => true
	 *
	 * _.isArray(document.body.children);
	 * // => false
	 *
	 * _.isArray('abc');
	 * // => false
	 *
	 * _.isArray(_.noop);
	 * // => false
	 */

	var isArray$2 = Array.isArray;

	var isArray_1 = isArray$2;

	/** Used as references for various `Number` constants. */

	var MAX_SAFE_INTEGER$1 = 9007199254740991;

	/**
	 * Checks if `value` is a valid array-like length.
	 *
	 * **Note:** This method is loosely based on
	 * [`ToLength`](http://ecma-international.org/ecma-262/7.0/#sec-tolength).
	 *
	 * @static
	 * @memberOf _
	 * @since 4.0.0
	 * @category Lang
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is a valid length, else `false`.
	 * @example
	 *
	 * _.isLength(3);
	 * // => true
	 *
	 * _.isLength(Number.MIN_VALUE);
	 * // => false
	 *
	 * _.isLength(Infinity);
	 * // => false
	 *
	 * _.isLength('3');
	 * // => false
	 */
	function isLength$2(value) {
	  return typeof value == 'number' &&
	    value > -1 && value % 1 == 0 && value <= MAX_SAFE_INTEGER$1;
	}

	var isLength_1 = isLength$2;

	var isFunction$1 = isFunction_1,
	    isLength$1 = isLength_1;

	/**
	 * Checks if `value` is array-like. A value is considered array-like if it's
	 * not a function and has a `value.length` that's an integer greater than or
	 * equal to `0` and less than or equal to `Number.MAX_SAFE_INTEGER`.
	 *
	 * @static
	 * @memberOf _
	 * @since 4.0.0
	 * @category Lang
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is array-like, else `false`.
	 * @example
	 *
	 * _.isArrayLike([1, 2, 3]);
	 * // => true
	 *
	 * _.isArrayLike(document.body.children);
	 * // => true
	 *
	 * _.isArrayLike('abc');
	 * // => true
	 *
	 * _.isArrayLike(_.noop);
	 * // => false
	 */
	function isArrayLike$4(value) {
	  return value != null && isLength$1(value.length) && !isFunction$1(value);
	}

	var isArrayLike_1 = isArrayLike$4;

	var isArrayLike$3 = isArrayLike_1,
	    isObjectLike$2 = isObjectLike_1;

	/**
	 * This method is like `_.isArrayLike` except that it also checks if `value`
	 * is an object.
	 *
	 * @static
	 * @memberOf _
	 * @since 4.0.0
	 * @category Lang
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is an array-like object,
	 *  else `false`.
	 * @example
	 *
	 * _.isArrayLikeObject([1, 2, 3]);
	 * // => true
	 *
	 * _.isArrayLikeObject(document.body.children);
	 * // => true
	 *
	 * _.isArrayLikeObject('abc');
	 * // => false
	 *
	 * _.isArrayLikeObject(_.noop);
	 * // => false
	 */
	function isArrayLikeObject$1(value) {
	  return isObjectLike$2(value) && isArrayLike$3(value);
	}

	var isArrayLikeObject_1 = isArrayLikeObject$1;

	var isBuffer$2 = {exports: {}};

	/**
	 * This method returns `false`.
	 *
	 * @static
	 * @memberOf _
	 * @since 4.13.0
	 * @category Util
	 * @returns {boolean} Returns `false`.
	 * @example
	 *
	 * _.times(2, _.stubFalse);
	 * // => [false, false]
	 */

	function stubFalse() {
	  return false;
	}

	var stubFalse_1 = stubFalse;

	(function (module, exports) {
	var root = _root,
	    stubFalse = stubFalse_1;

	/** Detect free variable `exports`. */
	var freeExports = exports && !exports.nodeType && exports;

	/** Detect free variable `module`. */
	var freeModule = freeExports && 'object' == 'object' && module && !module.nodeType && module;

	/** Detect the popular CommonJS extension `module.exports`. */
	var moduleExports = freeModule && freeModule.exports === freeExports;

	/** Built-in value references. */
	var Buffer = moduleExports ? root.Buffer : undefined;

	/* Built-in method references for those with the same name as other `lodash` methods. */
	var nativeIsBuffer = Buffer ? Buffer.isBuffer : undefined;

	/**
	 * Checks if `value` is a buffer.
	 *
	 * @static
	 * @memberOf _
	 * @since 4.3.0
	 * @category Lang
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is a buffer, else `false`.
	 * @example
	 *
	 * _.isBuffer(new Buffer(2));
	 * // => true
	 *
	 * _.isBuffer(new Uint8Array(2));
	 * // => false
	 */
	var isBuffer = nativeIsBuffer || stubFalse;

	module.exports = isBuffer;
	}(isBuffer$2, isBuffer$2.exports));

	var baseGetTag$1 = _baseGetTag,
	    getPrototype = _getPrototype,
	    isObjectLike$1 = isObjectLike_1;

	/** `Object#toString` result references. */
	var objectTag$1 = '[object Object]';

	/** Used for built-in method references. */
	var funcProto = Function.prototype,
	    objectProto$3 = Object.prototype;

	/** Used to resolve the decompiled source of functions. */
	var funcToString = funcProto.toString;

	/** Used to check objects for own properties. */
	var hasOwnProperty$3 = objectProto$3.hasOwnProperty;

	/** Used to infer the `Object` constructor. */
	var objectCtorString = funcToString.call(Object);

	/**
	 * Checks if `value` is a plain object, that is, an object created by the
	 * `Object` constructor or one with a `[[Prototype]]` of `null`.
	 *
	 * @static
	 * @memberOf _
	 * @since 0.8.0
	 * @category Lang
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is a plain object, else `false`.
	 * @example
	 *
	 * function Foo() {
	 *   this.a = 1;
	 * }
	 *
	 * _.isPlainObject(new Foo);
	 * // => false
	 *
	 * _.isPlainObject([1, 2, 3]);
	 * // => false
	 *
	 * _.isPlainObject({ 'x': 0, 'y': 0 });
	 * // => true
	 *
	 * _.isPlainObject(Object.create(null));
	 * // => true
	 */
	function isPlainObject$1(value) {
	  if (!isObjectLike$1(value) || baseGetTag$1(value) != objectTag$1) {
	    return false;
	  }
	  var proto = getPrototype(value);
	  if (proto === null) {
	    return true;
	  }
	  var Ctor = hasOwnProperty$3.call(proto, 'constructor') && proto.constructor;
	  return typeof Ctor == 'function' && Ctor instanceof Ctor &&
	    funcToString.call(Ctor) == objectCtorString;
	}

	var isPlainObject_1 = isPlainObject$1;

	var baseGetTag = _baseGetTag,
	    isLength = isLength_1,
	    isObjectLike = isObjectLike_1;

	/** `Object#toString` result references. */
	var argsTag = '[object Arguments]',
	    arrayTag = '[object Array]',
	    boolTag = '[object Boolean]',
	    dateTag = '[object Date]',
	    errorTag = '[object Error]',
	    funcTag = '[object Function]',
	    mapTag = '[object Map]',
	    numberTag = '[object Number]',
	    objectTag = '[object Object]',
	    regexpTag = '[object RegExp]',
	    setTag = '[object Set]',
	    stringTag = '[object String]',
	    weakMapTag = '[object WeakMap]';

	var arrayBufferTag = '[object ArrayBuffer]',
	    dataViewTag = '[object DataView]',
	    float32Tag = '[object Float32Array]',
	    float64Tag = '[object Float64Array]',
	    int8Tag = '[object Int8Array]',
	    int16Tag = '[object Int16Array]',
	    int32Tag = '[object Int32Array]',
	    uint8Tag = '[object Uint8Array]',
	    uint8ClampedTag = '[object Uint8ClampedArray]',
	    uint16Tag = '[object Uint16Array]',
	    uint32Tag = '[object Uint32Array]';

	/** Used to identify `toStringTag` values of typed arrays. */
	var typedArrayTags = {};
	typedArrayTags[float32Tag] = typedArrayTags[float64Tag] =
	typedArrayTags[int8Tag] = typedArrayTags[int16Tag] =
	typedArrayTags[int32Tag] = typedArrayTags[uint8Tag] =
	typedArrayTags[uint8ClampedTag] = typedArrayTags[uint16Tag] =
	typedArrayTags[uint32Tag] = true;
	typedArrayTags[argsTag] = typedArrayTags[arrayTag] =
	typedArrayTags[arrayBufferTag] = typedArrayTags[boolTag] =
	typedArrayTags[dataViewTag] = typedArrayTags[dateTag] =
	typedArrayTags[errorTag] = typedArrayTags[funcTag] =
	typedArrayTags[mapTag] = typedArrayTags[numberTag] =
	typedArrayTags[objectTag] = typedArrayTags[regexpTag] =
	typedArrayTags[setTag] = typedArrayTags[stringTag] =
	typedArrayTags[weakMapTag] = false;

	/**
	 * The base implementation of `_.isTypedArray` without Node.js optimizations.
	 *
	 * @private
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is a typed array, else `false`.
	 */
	function baseIsTypedArray$1(value) {
	  return isObjectLike(value) &&
	    isLength(value.length) && !!typedArrayTags[baseGetTag(value)];
	}

	var _baseIsTypedArray = baseIsTypedArray$1;

	/**
	 * The base implementation of `_.unary` without support for storing metadata.
	 *
	 * @private
	 * @param {Function} func The function to cap arguments for.
	 * @returns {Function} Returns the new capped function.
	 */

	function baseUnary$1(func) {
	  return function(value) {
	    return func(value);
	  };
	}

	var _baseUnary = baseUnary$1;

	var _nodeUtil = {exports: {}};

	(function (module, exports) {
	var freeGlobal = _freeGlobal;

	/** Detect free variable `exports`. */
	var freeExports = exports && !exports.nodeType && exports;

	/** Detect free variable `module`. */
	var freeModule = freeExports && 'object' == 'object' && module && !module.nodeType && module;

	/** Detect the popular CommonJS extension `module.exports`. */
	var moduleExports = freeModule && freeModule.exports === freeExports;

	/** Detect free variable `process` from Node.js. */
	var freeProcess = moduleExports && freeGlobal.process;

	/** Used to access faster Node.js helpers. */
	var nodeUtil = (function() {
	  try {
	    // Use `util.types` for Node.js 10+.
	    var types = freeModule && freeModule.require && freeModule.require('util').types;

	    if (types) {
	      return types;
	    }

	    // Legacy `process.binding('util')` for Node.js < 10.
	    return freeProcess && freeProcess.binding && freeProcess.binding('util');
	  } catch (e) {}
	}());

	module.exports = nodeUtil;
	}(_nodeUtil, _nodeUtil.exports));

	var baseIsTypedArray = _baseIsTypedArray,
	    baseUnary = _baseUnary,
	    nodeUtil = _nodeUtil.exports;

	/* Node.js helper references. */
	var nodeIsTypedArray = nodeUtil && nodeUtil.isTypedArray;

	/**
	 * Checks if `value` is classified as a typed array.
	 *
	 * @static
	 * @memberOf _
	 * @since 3.0.0
	 * @category Lang
	 * @param {*} value The value to check.
	 * @returns {boolean} Returns `true` if `value` is a typed array, else `false`.
	 * @example
	 *
	 * _.isTypedArray(new Uint8Array);
	 * // => true
	 *
	 * _.isTypedArray([]);
	 * // => false
	 */
	var isTypedArray$2 = nodeIsTypedArray ? baseUnary(nodeIsTypedArray) : baseIsTypedArray;

	var isTypedArray_1 = isTypedArray$2;

	/**
	 * Gets the value at `key`, unless `key` is "__proto__" or "constructor".
	 *
	 * @private
	 * @param {Object} object The object to query.
	 * @param {string} key The key of the property to get.
	 * @returns {*} Returns the property value.
	 */

	function safeGet$2(object, key) {
	  if (key === 'constructor' && typeof object[key] === 'function') {
	    return;
	  }

	  if (key == '__proto__') {
	    return;
	  }

	  return object[key];
	}

	var _safeGet = safeGet$2;

	var baseAssignValue$1 = _baseAssignValue,
	    eq$1 = eq_1;

	/** Used for built-in method references. */
	var objectProto$2 = Object.prototype;

	/** Used to check objects for own properties. */
	var hasOwnProperty$2 = objectProto$2.hasOwnProperty;

	/**
	 * Assigns `value` to `key` of `object` if the existing value is not equivalent
	 * using [`SameValueZero`](http://ecma-international.org/ecma-262/7.0/#sec-samevaluezero)
	 * for equality comparisons.
	 *
	 * @private
	 * @param {Object} object The object to modify.
	 * @param {string} key The key of the property to assign.
	 * @param {*} value The value to assign.
	 */
	function assignValue$1(object, key, value) {
	  var objValue = object[key];
	  if (!(hasOwnProperty$2.call(object, key) && eq$1(objValue, value)) ||
	      (value === undefined && !(key in object))) {
	    baseAssignValue$1(object, key, value);
	  }
	}

	var _assignValue = assignValue$1;

	var assignValue = _assignValue,
	    baseAssignValue = _baseAssignValue;

	/**
	 * Copies properties of `source` to `object`.
	 *
	 * @private
	 * @param {Object} source The object to copy properties from.
	 * @param {Array} props The property identifiers to copy.
	 * @param {Object} [object={}] The object to copy properties to.
	 * @param {Function} [customizer] The function to customize copied values.
	 * @returns {Object} Returns `object`.
	 */
	function copyObject$1(source, props, object, customizer) {
	  var isNew = !object;
	  object || (object = {});

	  var index = -1,
	      length = props.length;

	  while (++index < length) {
	    var key = props[index];

	    var newValue = customizer
	      ? customizer(object[key], source[key], key, object, source)
	      : undefined;

	    if (newValue === undefined) {
	      newValue = source[key];
	    }
	    if (isNew) {
	      baseAssignValue(object, key, newValue);
	    } else {
	      assignValue(object, key, newValue);
	    }
	  }
	  return object;
	}

	var _copyObject = copyObject$1;

	/**
	 * The base implementation of `_.times` without support for iteratee shorthands
	 * or max array length checks.
	 *
	 * @private
	 * @param {number} n The number of times to invoke `iteratee`.
	 * @param {Function} iteratee The function invoked per iteration.
	 * @returns {Array} Returns the array of results.
	 */

	function baseTimes$1(n, iteratee) {
	  var index = -1,
	      result = Array(n);

	  while (++index < n) {
	    result[index] = iteratee(index);
	  }
	  return result;
	}

	var _baseTimes = baseTimes$1;

	/** Used as references for various `Number` constants. */

	var MAX_SAFE_INTEGER = 9007199254740991;

	/** Used to detect unsigned integer values. */
	var reIsUint = /^(?:0|[1-9]\d*)$/;

	/**
	 * Checks if `value` is a valid array-like index.
	 *
	 * @private
	 * @param {*} value The value to check.
	 * @param {number} [length=MAX_SAFE_INTEGER] The upper bounds of a valid index.
	 * @returns {boolean} Returns `true` if `value` is a valid index, else `false`.
	 */
	function isIndex$2(value, length) {
	  var type = typeof value;
	  length = length == null ? MAX_SAFE_INTEGER : length;

	  return !!length &&
	    (type == 'number' ||
	      (type != 'symbol' && reIsUint.test(value))) &&
	        (value > -1 && value % 1 == 0 && value < length);
	}

	var _isIndex = isIndex$2;

	var baseTimes = _baseTimes,
	    isArguments$1 = isArguments_1,
	    isArray$1 = isArray_1,
	    isBuffer$1 = isBuffer$2.exports,
	    isIndex$1 = _isIndex,
	    isTypedArray$1 = isTypedArray_1;

	/** Used for built-in method references. */
	var objectProto$1 = Object.prototype;

	/** Used to check objects for own properties. */
	var hasOwnProperty$1 = objectProto$1.hasOwnProperty;

	/**
	 * Creates an array of the enumerable property names of the array-like `value`.
	 *
	 * @private
	 * @param {*} value The value to query.
	 * @param {boolean} inherited Specify returning inherited property names.
	 * @returns {Array} Returns the array of property names.
	 */
	function arrayLikeKeys$1(value, inherited) {
	  var isArr = isArray$1(value),
	      isArg = !isArr && isArguments$1(value),
	      isBuff = !isArr && !isArg && isBuffer$1(value),
	      isType = !isArr && !isArg && !isBuff && isTypedArray$1(value),
	      skipIndexes = isArr || isArg || isBuff || isType,
	      result = skipIndexes ? baseTimes(value.length, String) : [],
	      length = result.length;

	  for (var key in value) {
	    if ((inherited || hasOwnProperty$1.call(value, key)) &&
	        !(skipIndexes && (
	           // Safari 9 has enumerable `arguments.length` in strict mode.
	           key == 'length' ||
	           // Node.js 0.10 has enumerable non-index properties on buffers.
	           (isBuff && (key == 'offset' || key == 'parent')) ||
	           // PhantomJS 2 has enumerable non-index properties on typed arrays.
	           (isType && (key == 'buffer' || key == 'byteLength' || key == 'byteOffset')) ||
	           // Skip index properties.
	           isIndex$1(key, length)
	        ))) {
	      result.push(key);
	    }
	  }
	  return result;
	}

	var _arrayLikeKeys = arrayLikeKeys$1;

	/**
	 * This function is like
	 * [`Object.keys`](http://ecma-international.org/ecma-262/7.0/#sec-object.keys)
	 * except that it includes inherited enumerable properties.
	 *
	 * @private
	 * @param {Object} object The object to query.
	 * @returns {Array} Returns the array of property names.
	 */

	function nativeKeysIn$1(object) {
	  var result = [];
	  if (object != null) {
	    for (var key in Object(object)) {
	      result.push(key);
	    }
	  }
	  return result;
	}

	var _nativeKeysIn = nativeKeysIn$1;

	var isObject$3 = isObject_1,
	    isPrototype = _isPrototype,
	    nativeKeysIn = _nativeKeysIn;

	/** Used for built-in method references. */
	var objectProto = Object.prototype;

	/** Used to check objects for own properties. */
	var hasOwnProperty = objectProto.hasOwnProperty;

	/**
	 * The base implementation of `_.keysIn` which doesn't treat sparse arrays as dense.
	 *
	 * @private
	 * @param {Object} object The object to query.
	 * @returns {Array} Returns the array of property names.
	 */
	function baseKeysIn$1(object) {
	  if (!isObject$3(object)) {
	    return nativeKeysIn(object);
	  }
	  var isProto = isPrototype(object),
	      result = [];

	  for (var key in object) {
	    if (!(key == 'constructor' && (isProto || !hasOwnProperty.call(object, key)))) {
	      result.push(key);
	    }
	  }
	  return result;
	}

	var _baseKeysIn = baseKeysIn$1;

	var arrayLikeKeys = _arrayLikeKeys,
	    baseKeysIn = _baseKeysIn,
	    isArrayLike$2 = isArrayLike_1;

	/**
	 * Creates an array of the own and inherited enumerable property names of `object`.
	 *
	 * **Note:** Non-object values are coerced to objects.
	 *
	 * @static
	 * @memberOf _
	 * @since 3.0.0
	 * @category Object
	 * @param {Object} object The object to query.
	 * @returns {Array} Returns the array of property names.
	 * @example
	 *
	 * function Foo() {
	 *   this.a = 1;
	 *   this.b = 2;
	 * }
	 *
	 * Foo.prototype.c = 3;
	 *
	 * _.keysIn(new Foo);
	 * // => ['a', 'b', 'c'] (iteration order is not guaranteed)
	 */
	function keysIn$2(object) {
	  return isArrayLike$2(object) ? arrayLikeKeys(object, true) : baseKeysIn(object);
	}

	var keysIn_1 = keysIn$2;

	var copyObject = _copyObject,
	    keysIn$1 = keysIn_1;

	/**
	 * Converts `value` to a plain object flattening inherited enumerable string
	 * keyed properties of `value` to own properties of the plain object.
	 *
	 * @static
	 * @memberOf _
	 * @since 3.0.0
	 * @category Lang
	 * @param {*} value The value to convert.
	 * @returns {Object} Returns the converted plain object.
	 * @example
	 *
	 * function Foo() {
	 *   this.b = 2;
	 * }
	 *
	 * Foo.prototype.c = 3;
	 *
	 * _.assign({ 'a': 1 }, new Foo);
	 * // => { 'a': 1, 'b': 2 }
	 *
	 * _.assign({ 'a': 1 }, _.toPlainObject(new Foo));
	 * // => { 'a': 1, 'b': 2, 'c': 3 }
	 */
	function toPlainObject$1(value) {
	  return copyObject(value, keysIn$1(value));
	}

	var toPlainObject_1 = toPlainObject$1;

	var assignMergeValue$1 = _assignMergeValue,
	    cloneBuffer = _cloneBuffer.exports,
	    cloneTypedArray = _cloneTypedArray,
	    copyArray = _copyArray,
	    initCloneObject = _initCloneObject,
	    isArguments = isArguments_1,
	    isArray = isArray_1,
	    isArrayLikeObject = isArrayLikeObject_1,
	    isBuffer = isBuffer$2.exports,
	    isFunction = isFunction_1,
	    isObject$2 = isObject_1,
	    isPlainObject = isPlainObject_1,
	    isTypedArray = isTypedArray_1,
	    safeGet$1 = _safeGet,
	    toPlainObject = toPlainObject_1;

	/**
	 * A specialized version of `baseMerge` for arrays and objects which performs
	 * deep merges and tracks traversed objects enabling objects with circular
	 * references to be merged.
	 *
	 * @private
	 * @param {Object} object The destination object.
	 * @param {Object} source The source object.
	 * @param {string} key The key of the value to merge.
	 * @param {number} srcIndex The index of `source`.
	 * @param {Function} mergeFunc The function to merge values.
	 * @param {Function} [customizer] The function to customize assigned values.
	 * @param {Object} [stack] Tracks traversed source values and their merged
	 *  counterparts.
	 */
	function baseMergeDeep$1(object, source, key, srcIndex, mergeFunc, customizer, stack) {
	  var objValue = safeGet$1(object, key),
	      srcValue = safeGet$1(source, key),
	      stacked = stack.get(srcValue);

	  if (stacked) {
	    assignMergeValue$1(object, key, stacked);
	    return;
	  }
	  var newValue = customizer
	    ? customizer(objValue, srcValue, (key + ''), object, source, stack)
	    : undefined;

	  var isCommon = newValue === undefined;

	  if (isCommon) {
	    var isArr = isArray(srcValue),
	        isBuff = !isArr && isBuffer(srcValue),
	        isTyped = !isArr && !isBuff && isTypedArray(srcValue);

	    newValue = srcValue;
	    if (isArr || isBuff || isTyped) {
	      if (isArray(objValue)) {
	        newValue = objValue;
	      }
	      else if (isArrayLikeObject(objValue)) {
	        newValue = copyArray(objValue);
	      }
	      else if (isBuff) {
	        isCommon = false;
	        newValue = cloneBuffer(srcValue, true);
	      }
	      else if (isTyped) {
	        isCommon = false;
	        newValue = cloneTypedArray(srcValue, true);
	      }
	      else {
	        newValue = [];
	      }
	    }
	    else if (isPlainObject(srcValue) || isArguments(srcValue)) {
	      newValue = objValue;
	      if (isArguments(objValue)) {
	        newValue = toPlainObject(objValue);
	      }
	      else if (!isObject$2(objValue) || isFunction(objValue)) {
	        newValue = initCloneObject(srcValue);
	      }
	    }
	    else {
	      isCommon = false;
	    }
	  }
	  if (isCommon) {
	    // Recursively merge objects and arrays (susceptible to call stack limits).
	    stack.set(srcValue, newValue);
	    mergeFunc(newValue, srcValue, srcIndex, customizer, stack);
	    stack['delete'](srcValue);
	  }
	  assignMergeValue$1(object, key, newValue);
	}

	var _baseMergeDeep = baseMergeDeep$1;

	var Stack = _Stack,
	    assignMergeValue = _assignMergeValue,
	    baseFor = _baseFor,
	    baseMergeDeep = _baseMergeDeep,
	    isObject$1 = isObject_1,
	    keysIn = keysIn_1,
	    safeGet = _safeGet;

	/**
	 * The base implementation of `_.merge` without support for multiple sources.
	 *
	 * @private
	 * @param {Object} object The destination object.
	 * @param {Object} source The source object.
	 * @param {number} srcIndex The index of `source`.
	 * @param {Function} [customizer] The function to customize merged values.
	 * @param {Object} [stack] Tracks traversed source values and their merged
	 *  counterparts.
	 */
	function baseMerge$1(object, source, srcIndex, customizer, stack) {
	  if (object === source) {
	    return;
	  }
	  baseFor(source, function(srcValue, key) {
	    stack || (stack = new Stack);
	    if (isObject$1(srcValue)) {
	      baseMergeDeep(object, source, key, srcIndex, baseMerge$1, customizer, stack);
	    }
	    else {
	      var newValue = customizer
	        ? customizer(safeGet(object, key), srcValue, (key + ''), object, source, stack)
	        : undefined;

	      if (newValue === undefined) {
	        newValue = srcValue;
	      }
	      assignMergeValue(object, key, newValue);
	    }
	  }, keysIn);
	}

	var _baseMerge = baseMerge$1;

	/**
	 * This method returns the first argument it receives.
	 *
	 * @static
	 * @since 0.1.0
	 * @memberOf _
	 * @category Util
	 * @param {*} value Any value.
	 * @returns {*} Returns `value`.
	 * @example
	 *
	 * var object = { 'a': 1 };
	 *
	 * console.log(_.identity(object) === object);
	 * // => true
	 */

	function identity$3(value) {
	  return value;
	}

	var identity_1 = identity$3;

	/**
	 * A faster alternative to `Function#apply`, this function invokes `func`
	 * with the `this` binding of `thisArg` and the arguments of `args`.
	 *
	 * @private
	 * @param {Function} func The function to invoke.
	 * @param {*} thisArg The `this` binding of `func`.
	 * @param {Array} args The arguments to invoke `func` with.
	 * @returns {*} Returns the result of `func`.
	 */

	function apply$1(func, thisArg, args) {
	  switch (args.length) {
	    case 0: return func.call(thisArg);
	    case 1: return func.call(thisArg, args[0]);
	    case 2: return func.call(thisArg, args[0], args[1]);
	    case 3: return func.call(thisArg, args[0], args[1], args[2]);
	  }
	  return func.apply(thisArg, args);
	}

	var _apply = apply$1;

	var apply = _apply;

	/* Built-in method references for those with the same name as other `lodash` methods. */
	var nativeMax = Math.max;

	/**
	 * A specialized version of `baseRest` which transforms the rest array.
	 *
	 * @private
	 * @param {Function} func The function to apply a rest parameter to.
	 * @param {number} [start=func.length-1] The start position of the rest parameter.
	 * @param {Function} transform The rest array transform.
	 * @returns {Function} Returns the new function.
	 */
	function overRest$1(func, start, transform) {
	  start = nativeMax(start === undefined ? (func.length - 1) : start, 0);
	  return function() {
	    var args = arguments,
	        index = -1,
	        length = nativeMax(args.length - start, 0),
	        array = Array(length);

	    while (++index < length) {
	      array[index] = args[start + index];
	    }
	    index = -1;
	    var otherArgs = Array(start + 1);
	    while (++index < start) {
	      otherArgs[index] = args[index];
	    }
	    otherArgs[start] = transform(array);
	    return apply(func, this, otherArgs);
	  };
	}

	var _overRest = overRest$1;

	/**
	 * Creates a function that returns `value`.
	 *
	 * @static
	 * @memberOf _
	 * @since 2.4.0
	 * @category Util
	 * @param {*} value The value to return from the new function.
	 * @returns {Function} Returns the new constant function.
	 * @example
	 *
	 * var objects = _.times(2, _.constant({ 'a': 1 }));
	 *
	 * console.log(objects);
	 * // => [{ 'a': 1 }, { 'a': 1 }]
	 *
	 * console.log(objects[0] === objects[1]);
	 * // => true
	 */

	function constant$1(value) {
	  return function() {
	    return value;
	  };
	}

	var constant_1 = constant$1;

	var constant = constant_1,
	    defineProperty = _defineProperty,
	    identity$2 = identity_1;

	/**
	 * The base implementation of `setToString` without support for hot loop shorting.
	 *
	 * @private
	 * @param {Function} func The function to modify.
	 * @param {Function} string The `toString` result.
	 * @returns {Function} Returns `func`.
	 */
	var baseSetToString$1 = !defineProperty ? identity$2 : function(func, string) {
	  return defineProperty(func, 'toString', {
	    'configurable': true,
	    'enumerable': false,
	    'value': constant(string),
	    'writable': true
	  });
	};

	var _baseSetToString = baseSetToString$1;

	/** Used to detect hot functions by number of calls within a span of milliseconds. */

	var HOT_COUNT = 800,
	    HOT_SPAN = 16;

	/* Built-in method references for those with the same name as other `lodash` methods. */
	var nativeNow = Date.now;

	/**
	 * Creates a function that'll short out and invoke `identity` instead
	 * of `func` when it's called `HOT_COUNT` or more times in `HOT_SPAN`
	 * milliseconds.
	 *
	 * @private
	 * @param {Function} func The function to restrict.
	 * @returns {Function} Returns the new shortable function.
	 */
	function shortOut$1(func) {
	  var count = 0,
	      lastCalled = 0;

	  return function() {
	    var stamp = nativeNow(),
	        remaining = HOT_SPAN - (stamp - lastCalled);

	    lastCalled = stamp;
	    if (remaining > 0) {
	      if (++count >= HOT_COUNT) {
	        return arguments[0];
	      }
	    } else {
	      count = 0;
	    }
	    return func.apply(undefined, arguments);
	  };
	}

	var _shortOut = shortOut$1;

	var baseSetToString = _baseSetToString,
	    shortOut = _shortOut;

	/**
	 * Sets the `toString` method of `func` to return `string`.
	 *
	 * @private
	 * @param {Function} func The function to modify.
	 * @param {Function} string The `toString` result.
	 * @returns {Function} Returns `func`.
	 */
	var setToString$1 = shortOut(baseSetToString);

	var _setToString = setToString$1;

	var identity$1 = identity_1,
	    overRest = _overRest,
	    setToString = _setToString;

	/**
	 * The base implementation of `_.rest` which doesn't validate or coerce arguments.
	 *
	 * @private
	 * @param {Function} func The function to apply a rest parameter to.
	 * @param {number} [start=func.length-1] The start position of the rest parameter.
	 * @returns {Function} Returns the new function.
	 */
	function baseRest$1(func, start) {
	  return setToString(overRest(func, start, identity$1), func + '');
	}

	var _baseRest = baseRest$1;

	var eq = eq_1,
	    isArrayLike$1 = isArrayLike_1,
	    isIndex = _isIndex,
	    isObject = isObject_1;

	/**
	 * Checks if the given arguments are from an iteratee call.
	 *
	 * @private
	 * @param {*} value The potential iteratee value argument.
	 * @param {*} index The potential iteratee index or key argument.
	 * @param {*} object The potential iteratee object argument.
	 * @returns {boolean} Returns `true` if the arguments are from an iteratee call,
	 *  else `false`.
	 */
	function isIterateeCall$1(value, index, object) {
	  if (!isObject(object)) {
	    return false;
	  }
	  var type = typeof index;
	  if (type == 'number'
	        ? (isArrayLike$1(object) && isIndex(index, object.length))
	        : (type == 'string' && index in object)
	      ) {
	    return eq(object[index], value);
	  }
	  return false;
	}

	var _isIterateeCall = isIterateeCall$1;

	var baseRest = _baseRest,
	    isIterateeCall = _isIterateeCall;

	/**
	 * Creates a function like `_.assign`.
	 *
	 * @private
	 * @param {Function} assigner The function to assign values.
	 * @returns {Function} Returns the new assigner function.
	 */
	function createAssigner$1(assigner) {
	  return baseRest(function(object, sources) {
	    var index = -1,
	        length = sources.length,
	        customizer = length > 1 ? sources[length - 1] : undefined,
	        guard = length > 2 ? sources[2] : undefined;

	    customizer = (assigner.length > 3 && typeof customizer == 'function')
	      ? (length--, customizer)
	      : undefined;

	    if (guard && isIterateeCall(sources[0], sources[1], guard)) {
	      customizer = length < 3 ? undefined : customizer;
	      length = 1;
	    }
	    object = Object(object);
	    while (++index < length) {
	      var source = sources[index];
	      if (source) {
	        assigner(object, source, index, customizer);
	      }
	    }
	    return object;
	  });
	}

	var _createAssigner = createAssigner$1;

	var baseMerge = _baseMerge,
	    createAssigner = _createAssigner;

	/**
	 * This method is like `_.assign` except that it recursively merges own and
	 * inherited enumerable string keyed properties of source objects into the
	 * destination object. Source properties that resolve to `undefined` are
	 * skipped if a destination value exists. Array and plain object properties
	 * are merged recursively. Other objects and value types are overridden by
	 * assignment. Source objects are applied from left to right. Subsequent
	 * sources overwrite property assignments of previous sources.
	 *
	 * **Note:** This method mutates `object`.
	 *
	 * @static
	 * @memberOf _
	 * @since 0.5.0
	 * @category Object
	 * @param {Object} object The destination object.
	 * @param {...Object} [sources] The source objects.
	 * @returns {Object} Returns `object`.
	 * @example
	 *
	 * var object = {
	 *   'a': [{ 'b': 2 }, { 'd': 4 }]
	 * };
	 *
	 * var other = {
	 *   'a': [{ 'c': 3 }, { 'e': 5 }]
	 * };
	 *
	 * _.merge(object, other);
	 * // => { 'a': [{ 'b': 2, 'c': 3 }, { 'd': 4, 'e': 5 }] }
	 */
	var merge$1 = createAssigner(function(object, source, srcIndex) {
	  baseMerge(object, source, srcIndex);
	});

	var merge_1 = merge$1;

	var merge = merge_1;

	var DEFAULTS$g = {
	  batchSize: 500,
	  padding: 20,
	  width: 2048,
	  height: 2048,
	  nodes: {
	    reducer: null,
	    defaultColor: '#999'
	  },
	  edges: {
	    reducer: null,
	    defaultColor: '#ccc'
	  }
	};

	defaults$6.DEFAULTS = DEFAULTS$g;

	defaults$6.refineSettings = function refineSettings(settings) {
	  settings = settings || {};

	  var dimensions = {
	    width: settings.width,
	    height: settings.height
	  };

	  if (dimensions.width && !dimensions.height)
	    dimensions.height = dimensions.width;

	  if (dimensions.height && !dimensions.width)
	    dimensions.width = dimensions.height;

	  settings = merge({}, DEFAULTS$g, settings, dimensions);

	  if (!settings.width && !settings.height)
	    throw new Error(
	      'graphology-canvas: need at least a valid width or height!'
	    );

	  return settings;
	};

	defaults$6.DEFAULT_NODE_REDUCER = function (settings, node, attr) {
	  var reduced = {
	    type: attr.type || 'circle',
	    label: attr.label || node,
	    x: attr.x,
	    y: attr.y,
	    size: attr.size || 1,
	    color: attr.color || settings.nodes.defaultColor
	  };

	  if (typeof reduced.x !== 'number' || typeof reduced.y !== 'number')
	    throw new Error(
	      'graphology-canvas: the "' + node + '" node has no valid x or y position.'
	    );

	  return reduced;
	};

	defaults$6.DEFAULT_EDGE_REDUCER = function (settings, edge, attr) {
	  var reduced = {
	    type: attr.type || 'line',
	    size: attr.size || 1,
	    color: attr.color || settings.edges.defaultColor
	  };

	  return reduced;
	};

	/**
	 * Graphology Canvas Helpers
	 * ==========================
	 *
	 * Micellaneous helper functions used throughout the library.
	 */

	var helpers$b = conversion;
	var defaults$5 = defaults$6;

	var createGraphToViewportConversionFunction =
	  helpers$b.createGraphToViewportConversionFunction;

	function reduceNodes(graph, settings) {
	  var containerWidth = settings.width;
	  var containerHeight = settings.height;

	  var xMin = Infinity;
	  var xMax = -Infinity;
	  var yMin = Infinity;
	  var yMax = -Infinity;

	  var data = {};

	  graph.forEachNode(function (node, attr) {
	    // Applying user's reducing logic
	    if (typeof settings.nodes.reducer === 'function')
	      attr = settings.nodes.reducer(settings, node, attr);

	    attr = defaults$5.DEFAULT_NODE_REDUCER(settings, node, attr);
	    data[node] = attr;

	    // Finding bounds
	    var x = attr.x;
	    var y = attr.y;

	    if (x < xMin) xMin = x;
	    if (x > xMax) xMax = x;
	    if (y < yMin) yMin = y;
	    if (y > yMax) yMax = y;
	  });

	  var conversion = createGraphToViewportConversionFunction(
	    {
	      x: [xMin, xMax],
	      y: [yMin, yMax]
	    },
	    {width: containerWidth, height: containerHeight},
	    {padding: settings.padding}
	  );

	  for (var n in data) {
	    conversion.assign(data[n]);
	  }

	  return data;
	}

	helpers$c.reduceNodes = reduceNodes;

	/**
	 * Graphology Canvas Background Component
	 * =======================================
	 *
	 * Simple background filling component.
	 */

	var background = function fillBackground(context, color, width, height) {
	  context.fillStyle = color;
	  context.fillRect(0, 0, width, height);
	};

	/**
	 * Graphology Canvas Node Circle Component
	 * ========================================
	 *
	 * Rendering nodes as plain circles.
	 */

	var TAU = Math.PI * 2;

	var circle = function drawNode(settings, context, data) {
	  context.fillStyle = data.color;
	  context.beginPath();
	  context.arc(data.x, data.y, data.size, 0, TAU, true);

	  context.closePath();
	  context.fill();
	};

	/**
	 * Graphology Canvas Edge Line Component
	 * ======================================
	 *
	 * Rendering nodes as plain lines.
	 */

	var line = function drawEdge(
	  settings,
	  context,
	  data,
	  sourceData,
	  targetData
	) {
	  context.strokeStyle = data.color;
	  context.lineWidth = data.size;
	  context.beginPath();
	  context.moveTo(sourceData.x, sourceData.y);
	  context.lineTo(targetData.x, targetData.y);
	  context.stroke();
	};

	/**
	 * Graphology Canvas Renderer
	 * ===========================
	 *
	 * Function taking a canvas context and rendering the given graph.
	 */

	var helpers$a = helpers$c;
	var defaults$4 = defaults$6;
	var fillBackground = background;

	var components$3 = {
	  nodes: {
	    circle: circle
	  },
	  edges: {
	    line: line
	  }
	};

	renderer.renderSync = function renderSync(graph, context, settings) {
	  // Reducing nodes
	  var nodeData = helpers$a.reduceNodes(graph, settings);

	  // Filling background
	  fillBackground(context, 'white', settings.width, settings.height);

	  // Drawing edges
	  var sourceData, targetData;
	  graph.forEachEdge(function (edge, attr, source, target) {
	    // Reducing edge
	    if (typeof settings.edges.reducer === 'function')
	      attr = settings.edges.reducer(settings, edge, attr);

	    attr = defaults$4.DEFAULT_EDGE_REDUCER(settings, edge, attr);

	    sourceData = nodeData[source];
	    targetData = nodeData[target];

	    components$3.edges[attr.type](
	      settings,
	      context,
	      attr,
	      sourceData,
	      targetData
	    );
	  });

	  // Drawing nodes
	  var k, d;

	  for (k in nodeData) {
	    d = nodeData[k];
	    components$3.nodes[d.type](settings, context, d);
	  }
	};

	var raf = function (fn) {
	  return setTimeout(fn, 16);
	};

	if (typeof requestAnimationFrame !== 'undefined') raf = requestAnimationFrame;

	function asyncWhile(condition, task, callback) {
	  if (condition()) {
	    task();

	    // Early termination to avoid delay
	    if (!condition()) return callback();

	    return raf(function () {
	      asyncWhile(condition, task, callback);
	    });
	  }

	  return callback();
	}

	renderer.renderAsync = function renderAsync(graph, context, settings, callback) {
	  // Reducing nodes
	  var nodeData = helpers$a.reduceNodes(graph, settings);

	  // Filling background
	  fillBackground(context, 'white', settings.width, settings.height);

	  // Drawing edges asynchronously
	  var edges = graph.edges();

	  var edgesDone = 0;

	  function renderEdgeBatch() {
	    var l;
	    var edge, attr, extremities, source, target, sourceData, targetData;

	    for (
	      l = Math.min(edgesDone + settings.batchSize, edges.length);
	      edgesDone < l;
	      edgesDone++
	    ) {
	      edge = edges[edgesDone];
	      attr = graph.getEdgeAttributes(edge);
	      extremities = graph.extremities(edge);
	      source = extremities[0];
	      target = extremities[1];

	      // Reducing edge
	      if (typeof settings.edges.reducer === 'function')
	        attr = settings.edges.reducer(settings, edge, attr);

	      attr = defaults$4.DEFAULT_EDGE_REDUCER(settings, edge, attr);

	      sourceData = nodeData[source];
	      targetData = nodeData[target];

	      components$3.edges[attr.type](
	        settings,
	        context,
	        attr,
	        sourceData,
	        targetData
	      );
	    }
	  }

	  var nodes = new Array(graph.order);
	  var step = 0;

	  for (var k in nodeData) nodes[step++] = nodeData[k];

	  var nodesDone = 0;

	  function renderNodeBatch() {
	    var l;
	    var node;

	    for (
	      l = Math.min(nodesDone + settings.batchSize, nodes.length);
	      nodesDone < l;
	      nodesDone++
	    ) {
	      node = nodes[nodesDone];
	      components$3.nodes[node.type](settings, context, node);
	    }
	  }

	  // Async logic
	  function edgesCondition() {
	    return edgesDone < edges.length;
	  }

	  function nodesCondition() {
	    return nodesDone < nodes.length;
	  }

	  asyncWhile(edgesCondition, renderEdgeBatch, function () {
	    asyncWhile(nodesCondition, renderNodeBatch, function () {
	      return callback();
	    });
	  });
	};

	/**
	 * Graphology Canvas Endpoint
	 * ===========================
	 *
	 * Publicly-exposed routine used to render the given graph into an arbitrary
	 * canvas context.
	 */

	var isGraph$M = isGraph$N;
	var lib$1 = renderer;
	var refineSettings = defaults$6.refineSettings;

	canvas$2.render = function render(graph, context, settings) {
	  if (!isGraph$M(graph))
	    throw new Error(
	      'graphology-canvas/render: expecting a valid graphology instance.'
	    );

	  settings = refineSettings(settings);

	  lib$1.renderSync(graph, context, settings);
	};

	canvas$2.renderAsync = function renderAsync(graph, context, settings, callback) {
	  if (arguments.length === 3) {
	    callback = settings;
	    settings = {};
	  }

	  settings = refineSettings(settings);

	  lib$1.renderAsync(graph, context, settings, callback);
	};

	var canvas$1 = canvas$2;

	/**
	 * Graphology inferType
	 * =====================
	 *
	 * Useful function used to "guess" the real type of the given Graph using
	 * introspection.
	 */

	var isGraph$L = isGraph$N;

	/**
	 * Returning the inferred type of the given graph.
	 *
	 * @param  {Graph}   graph - Target graph.
	 * @return {boolean}
	 */
	var inferType$4 = function inferType(graph) {
	  if (!isGraph$L(graph))
	    throw new Error(
	      'graphology-utils/infer-type: expecting a valid graphology instance.'
	    );

	  var declaredType = graph.type;

	  if (declaredType !== 'mixed') return declaredType;

	  if (
	    (graph.directedSize === 0 && graph.undirectedSize === 0) ||
	    (graph.directedSize > 0 && graph.undirectedSize > 0)
	  )
	    return 'mixed';

	  if (graph.directedSize > 0) return 'directed';

	  return 'undirected';
	};

	/**
	 * Obliterator Iterator Class
	 * ===========================
	 *
	 * Simple class representing the library's iterators.
	 */

	/**
	 * Iterator class.
	 *
	 * @constructor
	 * @param {function} next - Next function.
	 */
	function Iterator$6(next) {
	  if (typeof next !== 'function')
	    throw new Error('obliterator/iterator: expecting a function!');

	  this.next = next;
	}

	/**
	 * If symbols are supported, we add `next` to `Symbol.iterator`.
	 */
	if (typeof Symbol !== 'undefined')
	  Iterator$6.prototype[Symbol.iterator] = function () {
	    return this;
	  };

	/**
	 * Returning an iterator of the given values.
	 *
	 * @param  {any...} values - Values.
	 * @return {Iterator}
	 */
	Iterator$6.of = function () {
	  var args = arguments,
	    l = args.length,
	    i = 0;

	  return new Iterator$6(function () {
	    if (i >= l) return {done: true};

	    return {done: false, value: args[i++]};
	  });
	};

	/**
	 * Returning an empty iterator.
	 *
	 * @return {Iterator}
	 */
	Iterator$6.empty = function () {
	  var iterator = new Iterator$6(function () {
	    return {done: true};
	  });

	  return iterator;
	};

	/**
	 * Returning an iterator over the given indexed sequence.
	 *
	 * @param  {string|Array} sequence - Target sequence.
	 * @return {Iterator}
	 */
	Iterator$6.fromSequence = function (sequence) {
	  var i = 0,
	    l = sequence.length;

	  return new Iterator$6(function () {
	    if (i >= l) return {done: true};

	    return {done: false, value: sequence[i++]};
	  });
	};

	/**
	 * Returning whether the given value is an iterator.
	 *
	 * @param  {any} value - Value.
	 * @return {boolean}
	 */
	Iterator$6.is = function (value) {
	  if (value instanceof Iterator$6) return true;

	  return (
	    typeof value === 'object' &&
	    value !== null &&
	    typeof value.next === 'function'
	  );
	};

	/**
	 * Exporting.
	 */
	var iterator = Iterator$6;

	var typedArrays = {};

	/**
	 * Mnemonist Typed Array Helpers
	 * ==============================
	 *
	 * Miscellaneous helpers related to typed arrays.
	 */

	(function (exports) {
	/**
	 * When using an unsigned integer array to store pointers, one might want to
	 * choose the optimal word size in regards to the actual numbers of pointers
	 * to store.
	 *
	 * This helpers does just that.
	 *
	 * @param  {number} size - Expected size of the array to map.
	 * @return {TypedArray}
	 */
	var MAX_8BIT_INTEGER = Math.pow(2, 8) - 1,
	    MAX_16BIT_INTEGER = Math.pow(2, 16) - 1,
	    MAX_32BIT_INTEGER = Math.pow(2, 32) - 1;

	var MAX_SIGNED_8BIT_INTEGER = Math.pow(2, 7) - 1,
	    MAX_SIGNED_16BIT_INTEGER = Math.pow(2, 15) - 1,
	    MAX_SIGNED_32BIT_INTEGER = Math.pow(2, 31) - 1;

	exports.getPointerArray = function(size) {
	  var maxIndex = size - 1;

	  if (maxIndex <= MAX_8BIT_INTEGER)
	    return Uint8Array;

	  if (maxIndex <= MAX_16BIT_INTEGER)
	    return Uint16Array;

	  if (maxIndex <= MAX_32BIT_INTEGER)
	    return Uint32Array;

	  throw new Error('mnemonist: Pointer Array of size > 4294967295 is not supported.');
	};

	exports.getSignedPointerArray = function(size) {
	  var maxIndex = size - 1;

	  if (maxIndex <= MAX_SIGNED_8BIT_INTEGER)
	    return Int8Array;

	  if (maxIndex <= MAX_SIGNED_16BIT_INTEGER)
	    return Int16Array;

	  if (maxIndex <= MAX_SIGNED_32BIT_INTEGER)
	    return Int32Array;

	  return Float64Array;
	};

	/**
	 * Function returning the minimal type able to represent the given number.
	 *
	 * @param  {number} value - Value to test.
	 * @return {TypedArrayClass}
	 */
	exports.getNumberType = function(value) {

	  // <= 32 bits itnteger?
	  if (value === (value | 0)) {

	    // Negative
	    if (Math.sign(value) === -1) {
	      if (value <= 127 && value >= -128)
	        return Int8Array;

	      if (value <= 32767 && value >= -32768)
	        return Int16Array;

	      return Int32Array;
	    }
	    else {

	      if (value <= 255)
	        return Uint8Array;

	      if (value <= 65535)
	        return Uint16Array;

	      return Uint32Array;
	    }
	  }

	  // 53 bits integer & floats
	  // NOTE: it's kinda hard to tell whether we could use 32bits or not...
	  return Float64Array;
	};

	/**
	 * Function returning the minimal type able to represent the given array
	 * of JavaScript numbers.
	 *
	 * @param  {array}    array  - Array to represent.
	 * @param  {function} getter - Optional getter.
	 * @return {TypedArrayClass}
	 */
	var TYPE_PRIORITY = {
	  Uint8Array: 1,
	  Int8Array: 2,
	  Uint16Array: 3,
	  Int16Array: 4,
	  Uint32Array: 5,
	  Int32Array: 6,
	  Float32Array: 7,
	  Float64Array: 8
	};

	// TODO: make this a one-shot for one value
	exports.getMinimalRepresentation = function(array, getter) {
	  var maxType = null,
	      maxPriority = 0,
	      p,
	      t,
	      v,
	      i,
	      l;

	  for (i = 0, l = array.length; i < l; i++) {
	    v = getter ? getter(array[i]) : array[i];
	    t = exports.getNumberType(v);
	    p = TYPE_PRIORITY[t.name];

	    if (p > maxPriority) {
	      maxPriority = p;
	      maxType = t;
	    }
	  }

	  return maxType;
	};

	/**
	 * Function returning whether the given value is a typed array.
	 *
	 * @param  {any} value - Value to test.
	 * @return {boolean}
	 */
	exports.isTypedArray = function(value) {
	  return typeof ArrayBuffer !== 'undefined' && ArrayBuffer.isView(value);
	};

	/**
	 * Function used to concat byte arrays.
	 *
	 * @param  {...ByteArray}
	 * @return {ByteArray}
	 */
	exports.concat = function() {
	  var length = 0,
	      i,
	      o,
	      l;

	  for (i = 0, l = arguments.length; i < l; i++)
	    length += arguments[i].length;

	  var array = new (arguments[0].constructor)(length);

	  for (i = 0, o = 0; i < l; i++) {
	    array.set(arguments[i], o);
	    o += arguments[i].length;
	  }

	  return array;
	};

	/**
	 * Function used to initialize a byte array of indices.
	 *
	 * @param  {number}    length - Length of target.
	 * @return {ByteArray}
	 */
	exports.indices = function(length) {
	  var PointerArray = exports.getPointerArray(length);

	  var array = new PointerArray(length);

	  for (var i = 0; i < length; i++)
	    array[i] = i;

	  return array;
	};
	}(typedArrays));

	/**
	 * Mnemonist SparseMap
	 * ====================
	 *
	 * JavaScript sparse map implemented on top of byte arrays.
	 *
	 * [Reference]: https://research.swtch.com/sparse
	 */

	var Iterator$5 = iterator,
	    getPointerArray$2 = typedArrays.getPointerArray;

	/**
	 * SparseMap.
	 *
	 * @constructor
	 */
	function SparseMap$1(Values, length) {
	  if (arguments.length < 2) {
	    length = Values;
	    Values = Array;
	  }

	  var ByteArray = getPointerArray$2(length);

	  // Properties
	  this.size = 0;
	  this.length = length;
	  this.dense = new ByteArray(length);
	  this.sparse = new ByteArray(length);
	  this.vals = new Values(length);
	}

	/**
	 * Method used to clear the structure.
	 *
	 * @return {undefined}
	 */
	SparseMap$1.prototype.clear = function() {
	  this.size = 0;
	};

	/**
	 * Method used to check the existence of a member in the set.
	 *
	 * @param  {number} member - Member to test.
	 * @return {SparseMap}
	 */
	SparseMap$1.prototype.has = function(member) {
	  var index = this.sparse[member];

	  return (
	    index < this.size &&
	    this.dense[index] === member
	  );
	};

	/**
	 * Method used to get the value associated to a member in the set.
	 *
	 * @param  {number} member - Member to test.
	 * @return {any}
	 */
	SparseMap$1.prototype.get = function(member) {
	  var index = this.sparse[member];

	  if (index < this.size && this.dense[index] === member)
	    return this.vals[index];

	  return;
	};

	/**
	 * Method used to set a value into the map.
	 *
	 * @param  {number} member - Member to set.
	 * @param  {any}    value  - Associated value.
	 * @return {SparseMap}
	 */
	SparseMap$1.prototype.set = function(member, value) {
	  var index = this.sparse[member];

	  if (index < this.size && this.dense[index] === member) {
	    this.vals[index] = value;
	    return this;
	  }

	  this.dense[this.size] = member;
	  this.sparse[member] = this.size;
	  this.vals[this.size] = value;
	  this.size++;

	  return this;
	};

	/**
	 * Method used to remove a member from the set.
	 *
	 * @param  {number} member - Member to delete.
	 * @return {boolean}
	 */
	SparseMap$1.prototype.delete = function(member) {
	  var index = this.sparse[member];

	  if (index >= this.size || this.dense[index] !== member)
	    return false;

	  index = this.dense[this.size - 1];
	  this.dense[this.sparse[member]] = index;
	  this.sparse[index] = this.sparse[member];
	  this.size--;

	  return true;
	};

	/**
	 * Method used to iterate over the set's values.
	 *
	 * @param  {function}  callback - Function to call for each item.
	 * @param  {object}    scope    - Optional scope.
	 * @return {undefined}
	 */
	SparseMap$1.prototype.forEach = function(callback, scope) {
	  scope = arguments.length > 1 ? scope : this;

	  for (var i = 0; i < this.size; i++)
	    callback.call(scope, this.vals[i], this.dense[i]);
	};

	/**
	 * Method used to create an iterator over a set's members.
	 *
	 * @return {Iterator}
	 */
	SparseMap$1.prototype.keys = function() {
	  var size = this.size,
	      dense = this.dense,
	      i = 0;

	  return new Iterator$5(function() {
	    if (i < size) {
	      var item = dense[i];
	      i++;

	      return {
	        value: item
	      };
	    }

	    return {
	      done: true
	    };
	  });
	};

	/**
	 * Method used to create an iterator over a set's values.
	 *
	 * @return {Iterator}
	 */
	SparseMap$1.prototype.values = function() {
	  var size = this.size,
	      values = this.vals,
	      i = 0;

	  return new Iterator$5(function() {
	    if (i < size) {
	      var item = values[i];
	      i++;

	      return {
	        value: item
	      };
	    }

	    return {
	      done: true
	    };
	  });
	};

	/**
	 * Method used to create an iterator over a set's entries.
	 *
	 * @return {Iterator}
	 */
	SparseMap$1.prototype.entries = function() {
	  var size = this.size,
	      dense = this.dense,
	      values = this.vals,
	      i = 0;

	  return new Iterator$5(function() {
	    if (i < size) {
	      var item = [dense[i], values[i]];
	      i++;

	      return {
	        value: item
	      };
	    }

	    return {
	      done: true
	    };
	  });
	};

	/**
	 * Attaching the #.entries method to Symbol.iterator if possible.
	 */
	if (typeof Symbol !== 'undefined')
	  SparseMap$1.prototype[Symbol.iterator] = SparseMap$1.prototype.entries;

	/**
	 * Convenience known methods.
	 */
	SparseMap$1.prototype.inspect = function() {
	  var proxy = new Map();

	  for (var i = 0; i < this.size; i++)
	    proxy.set(this.dense[i], this.vals[i]);

	  // Trick so that node displays the name of the constructor
	  Object.defineProperty(proxy, 'constructor', {
	    value: SparseMap$1,
	    enumerable: false
	  });

	  proxy.length = this.length;

	  if (this.vals.constructor !== Array)
	    proxy.type = this.vals.constructor.name;

	  return proxy;
	};

	if (typeof Symbol !== 'undefined')
	  SparseMap$1.prototype[Symbol.for('nodejs.util.inspect.custom')] = SparseMap$1.prototype.inspect;

	/**
	 * Exporting.
	 */
	var sparseMap = SparseMap$1;

	/**
	 * Mnemonist SparseQueueSet
	 * =========================
	 *
	 * JavaScript sparse queue set implemented on top of byte arrays.
	 *
	 * [Reference]: https://research.swtch.com/sparse
	 */

	var Iterator$4 = iterator,
	    getPointerArray$1 = typedArrays.getPointerArray;

	/**
	 * SparseQueueSet.
	 *
	 * @constructor
	 */
	function SparseQueueSet$1(capacity) {

	  var ByteArray = getPointerArray$1(capacity);

	  // Properties
	  this.start = 0;
	  this.size = 0;
	  this.capacity = capacity;
	  this.dense = new ByteArray(capacity);
	  this.sparse = new ByteArray(capacity);
	}

	/**
	 * Method used to clear the structure.
	 *
	 * @return {undefined}
	 */
	SparseQueueSet$1.prototype.clear = function() {
	  this.start = 0;
	  this.size = 0;
	};

	/**
	 * Method used to check the existence of a member in the queue.
	 *
	 * @param  {number} member - Member to test.
	 * @return {SparseQueueSet}
	 */
	SparseQueueSet$1.prototype.has = function(member) {
	  if (this.size === 0)
	    return false;

	  var index = this.sparse[member];

	  var inBounds = (
	    index < this.capacity &&
	    (
	      index >= this.start &&
	      index < this.start + this.size
	    ) ||
	    (
	      index < ((this.start + this.size) % this.capacity)
	    )
	  );

	  return (
	    inBounds &&
	    this.dense[index] === member
	  );
	};

	/**
	 * Method used to add a member to the queue.
	 *
	 * @param  {number} member - Member to add.
	 * @return {SparseQueueSet}
	 */
	SparseQueueSet$1.prototype.enqueue = function(member) {
	  var index = this.sparse[member];

	  if (this.size !== 0) {
	    var inBounds = (
	      index < this.capacity &&
	      (
	        index >= this.start &&
	        index < this.start + this.size
	      ) ||
	      (
	        index < ((this.start + this.size) % this.capacity)
	      )
	    );

	    if (inBounds && this.dense[index] === member)
	      return this;
	  }

	  index = (this.start + this.size) % this.capacity;

	  this.dense[index] = member;
	  this.sparse[member] = index;
	  this.size++;

	  return this;
	};

	/**
	 * Method used to remove the next member from the queue.
	 *
	 * @param  {number} member - Member to delete.
	 * @return {boolean}
	 */
	SparseQueueSet$1.prototype.dequeue = function() {
	  if (this.size === 0)
	    return;

	  var index = this.start;

	  this.size--;
	  this.start++;

	  if (this.start === this.capacity)
	    this.start = 0;

	  var member = this.dense[index];

	  this.sparse[member] = this.capacity;

	  return member;
	};

	/**
	 * Method used to iterate over the queue's values.
	 *
	 * @param  {function}  callback - Function to call for each item.
	 * @param  {object}    scope    - Optional scope.
	 * @return {undefined}
	 */
	SparseQueueSet$1.prototype.forEach = function(callback, scope) {
	  scope = arguments.length > 1 ? scope : this;

	  var c = this.capacity,
	      l = this.size,
	      i = this.start,
	      j = 0;

	  while (j < l) {
	    callback.call(scope, this.dense[i], j, this);
	    i++;
	    j++;

	    if (i === c)
	      i = 0;
	  }
	};

	/**
	 * Method used to create an iterator over a set's values.
	 *
	 * @return {Iterator}
	 */
	SparseQueueSet$1.prototype.values = function() {
	  var dense = this.dense,
	      c = this.capacity,
	      l = this.size,
	      i = this.start,
	      j = 0;

	  return new Iterator$4(function() {
	    if (j >= l)
	      return {
	        done: true
	      };

	    var value = dense[i];

	    i++;
	    j++;

	    if (i === c)
	      i = 0;

	    return {
	      value: value,
	      done: false
	    };
	  });
	};

	/**
	 * Attaching the #.values method to Symbol.iterator if possible.
	 */
	if (typeof Symbol !== 'undefined')
	  SparseQueueSet$1.prototype[Symbol.iterator] = SparseQueueSet$1.prototype.values;

	/**
	 * Convenience known methods.
	 */
	SparseQueueSet$1.prototype.inspect = function() {
	  var proxy = [];

	  this.forEach(function(member) {
	    proxy.push(member);
	  });

	  // Trick so that node displays the name of the constructor
	  Object.defineProperty(proxy, 'constructor', {
	    value: SparseQueueSet$1,
	    enumerable: false
	  });

	  proxy.capacity = this.capacity;

	  return proxy;
	};

	if (typeof Symbol !== 'undefined')
	  SparseQueueSet$1.prototype[Symbol.for('nodejs.util.inspect.custom')] = SparseQueueSet$1.prototype.inspect;

	/**
	 * Exporting.
	 */
	var sparseQueueSet = SparseQueueSet$1;

	/**
	 * Pandemonium Random Index
	 * =========================
	 *
	 * Random index function.
	 */

	/**
	 * Creating a function returning a random index from the given array.
	 *
	 * @param  {function} rng - RNG function returning uniform random.
	 * @return {function}     - The created function.
	 */
	function createRandomIndex$1(rng) {
	  /**
	   * Random function.
	   *
	   * @param  {array|number}  array - Target array or length of the array.
	   * @return {number}
	   */
	  return function (length) {
	    if (typeof length !== 'number') length = length.length;

	    return Math.floor(rng() * length);
	  };
	}

	/**
	 * Default random index using `Math.random`.
	 */
	var randomIndex = createRandomIndex$1(Math.random);

	/**
	 * Exporting.
	 */
	randomIndex.createRandomIndex = createRandomIndex$1;
	var randomIndex_1 = randomIndex;

	var louvain$1 = {};

	var getters$1 = {};

	/**
	 * Graphology Weight Getter
	 * =========================
	 *
	 * Function creating weight getters.
	 */

	function coerceWeight(value) {
	  // Ensuring target value is a correct number
	  if (typeof value !== 'number' || isNaN(value)) return 1;

	  return value;
	}

	function createNodeValueGetter$1(nameOrFunction, defaultValue) {
	  var getter = {};

	  var coerceToDefault = function (v) {
	    if (typeof v === 'undefined') return defaultValue;

	    return v;
	  };

	  if (typeof defaultValue === 'function') coerceToDefault = defaultValue;

	  var get = function (attributes) {
	    return coerceToDefault(attributes[nameOrFunction]);
	  };

	  var returnDefault = function () {
	    return coerceToDefault(undefined);
	  };

	  if (typeof nameOrFunction === 'string') {
	    getter.fromAttributes = get;
	    getter.fromGraph = function (graph, node) {
	      return get(graph.getNodeAttributes(node));
	    };
	    getter.fromEntry = function (node, attributes) {
	      return get(attributes);
	    };
	  } else if (typeof nameOrFunction === 'function') {
	    getter.fromAttributes = function () {
	      throw new Error(
	        'graphology-utils/getters/createNodeValueGetter: irrelevant usage.'
	      );
	    };
	    getter.fromGraph = function (graph, node) {
	      return coerceToDefault(
	        nameOrFunction(node, graph.getNodeAttributes(node))
	      );
	    };
	    getter.fromEntry = function (node, attributes) {
	      return coerceToDefault(nameOrFunction(node, attributes));
	    };
	  } else {
	    getter.fromAttributes = returnDefault;
	    getter.fromGraph = returnDefault;
	    getter.fromEntry = returnDefault;
	  }

	  return getter;
	}

	function createEdgeValueGetter$1(nameOrFunction, defaultValue) {
	  var getter = {};

	  var coerceToDefault = function (v) {
	    if (typeof v === 'undefined') return defaultValue;

	    return v;
	  };

	  if (typeof defaultValue === 'function') coerceToDefault = defaultValue;

	  var get = function (attributes) {
	    return coerceToDefault(attributes[nameOrFunction]);
	  };

	  var returnDefault = function () {
	    return coerceToDefault(undefined);
	  };

	  if (typeof nameOrFunction === 'string') {
	    getter.fromAttributes = get;
	    getter.fromGraph = function (graph, edge) {
	      return get(graph.getEdgeAttributes(edge));
	    };
	    getter.fromEntry = function (edge, attributes) {
	      return get(attributes);
	    };
	    getter.fromPartialEntry = getter.fromEntry;
	    getter.fromMinimalEntry = getter.fromEntry;
	  } else if (typeof nameOrFunction === 'function') {
	    getter.fromAttributes = function () {
	      throw new Error(
	        'graphology-utils/getters/createEdgeValueGetter: irrelevant usage.'
	      );
	    };
	    getter.fromGraph = function (graph, edge) {
	      // TODO: we can do better, check #310
	      var extremities = graph.extremities(edge);
	      return coerceToDefault(
	        nameOrFunction(
	          edge,
	          graph.getEdgeAttributes(edge),
	          extremities[0],
	          extremities[1],
	          graph.getNodeAttributes(extremities[0]),
	          graph.getNodeAttributes(extremities[1]),
	          graph.isUndirected(edge)
	        )
	      );
	    };
	    getter.fromEntry = function (e, a, s, t, sa, ta, u) {
	      return coerceToDefault(nameOrFunction(e, a, s, t, sa, ta, u));
	    };
	    getter.fromPartialEntry = function (e, a, s, t) {
	      return coerceToDefault(nameOrFunction(e, a, s, t));
	    };
	    getter.fromMinimalEntry = function (e, a) {
	      return coerceToDefault(nameOrFunction(e, a));
	    };
	  } else {
	    getter.fromAttributes = returnDefault;
	    getter.fromGraph = returnDefault;
	    getter.fromEntry = returnDefault;
	    getter.fromMinimalEntry = returnDefault;
	  }

	  return getter;
	}

	getters$1.createNodeValueGetter = createNodeValueGetter$1;
	getters$1.createEdgeValueGetter = createEdgeValueGetter$1;
	getters$1.createEdgeWeightGetter = function (name) {
	  return createEdgeValueGetter$1(name, coerceWeight);
	};

	/**
	 * Graphology Louvain Indices
	 * ===========================
	 *
	 * Undirected & Directed Louvain Index structures used to compute the famous
	 * Louvain community detection algorithm.
	 *
	 * Most of the rationale is explained in `graphology-metrics`.
	 *
	 * Note that this index shares a lot with the classic Union-Find data
	 * structure. It also relies on a unused id stack to make sure we can
	 * increase again the number of communites when isolating nodes.
	 *
	 * [Articles]
	 * M. E. J. Newman, « Modularity and community structure in networks »,
	 * Proc. Natl. Acad. Sci. USA, vol. 103, no 23, 2006, p. 8577–8582
	 * https://dx.doi.org/10.1073%2Fpnas.0601602103
	 *
	 * Newman, M. E. J. « Community detection in networks: Modularity optimization
	 * and maximum likelihood are equivalent ». Physical Review E, vol. 94, no 5,
	 * novembre 2016, p. 052315. arXiv.org, doi:10.1103/PhysRevE.94.052315.
	 * https://arxiv.org/pdf/1606.02319.pdf
	 *
	 * Blondel, Vincent D., et al. « Fast unfolding of communities in large
	 * networks ». Journal of Statistical Mechanics: Theory and Experiment,
	 * vol. 2008, no 10, octobre 2008, p. P10008. DOI.org (Crossref),
	 * doi:10.1088/1742-5468/2008/10/P10008.
	 * https://arxiv.org/pdf/0803.0476.pdf
	 *
	 * Nicolas Dugué, Anthony Perez. Directed Louvain: maximizing modularity in
	 * directed networks. [Research Report] Université d’Orléans. 2015. hal-01231784
	 * https://hal.archives-ouvertes.fr/hal-01231784
	 *
	 * R. Lambiotte, J.-C. Delvenne and M. Barahona. Laplacian Dynamics and
	 * Multiscale Modular Structure in Networks,
	 * doi:10.1109/TNSE.2015.2391998.
	 * https://arxiv.org/abs/0812.1770
	 *
	 * [Latex]:
	 *
	 * Undirected Case:
	 * ----------------
	 *
	 * \Delta Q=\bigg{[}\frac{\sum^{c}_{in}-(2d_{c}+l)}{2m}-\bigg{(}\frac{\sum^{c}_{tot}-(d+l)}{2m}\bigg{)}^{2}+\frac{\sum^{t}_{in}+(2d_{t}+l)}{2m}-\bigg{(}\frac{\sum^{t}_{tot}+(d+l)}{2m}\bigg{)}^{2}\bigg{]}-\bigg{[}\frac{\sum^{c}_{in}}{2m}-\bigg{(}\frac{\sum^{c}_{tot}}{2m}\bigg{)}^{2}+\frac{\sum^{t}_{in}}{2m}-\bigg{(}\frac{\sum^{t}_{tot}}{2m}\bigg{)}^{2}\bigg{]}
	 * \Delta Q=\frac{d_{t}-d_{c}}{m}+\frac{l\sum^{c}_{tot}+d\sum^{c}_{tot}-d^{2}-l^{2}-2dl-l\sum^{t}_{tot}-d\sum^{t}_{tot}}{2m^{2}}
	 * \Delta Q=\frac{d_{t}-d_{c}}{m}+\frac{(l+d)\sum^{c}_{tot}-d^{2}-l^{2}-2dl-(l+d)\sum^{t}_{tot}}{2m^{2}}
	 *
	 * Directed Case:
	 * --------------
	 * \Delta Q_d=\bigg{[}\frac{\sum^{c}_{in}-(d_{c.in}+d_{c.out}+l)}{m}-\frac{(\sum^{c}_{tot.in}-(d_{in}+l))(\sum^{c}_{tot.out}-(d_{out}+l))}{m^{2}}+\frac{\sum^{t}_{in}+(d_{t.in}+d_{t.out}+l)}{m}-\frac{(\sum^{t}_{tot.in}+(d_{in}+l))(\sum^{t}_{tot.out}+(d_{out}+l))}{m^{2}}\bigg{]}-\bigg{[}\frac{\sum^{c}_{in}}{m}-\frac{\sum^{c}_{tot.in}\sum^{c}_{tot.out}}{m^{2}}+\frac{\sum^{t}_{in}}{m}-\frac{\sum^{t}_{tot.in}\sum^{t}_{tot.out}}{m^{2}}\bigg{]}
	 *
	 * [Notes]:
	 * Louvain is a bit unclear on this but delta computation are not derived from
	 * Q1 - Q2 but rather between Q when considered node is isolated in its own
	 * community versus Q with this node in target community. This is in fact
	 * an optimization because the subtract part is constant in the formulae and
	 * does not affect delta comparisons.
	 */

	var typed$4 = typedArrays;
	var resolveDefaults$d = defaults$7;
	var createEdgeWeightGetter$7 =
	  getters$1.createEdgeWeightGetter;

	var INSPECT = Symbol.for('nodejs.util.inspect.custom');

	var DEFAULTS$f = {
	  getEdgeWeight: 'weight',
	  keepDendrogram: false,
	  resolution: 1
	};

	function UndirectedLouvainIndex$1(graph, options) {
	  // Solving options
	  options = resolveDefaults$d(options, DEFAULTS$f);

	  var resolution = options.resolution;

	  // Weight getters
	  var getEdgeWeight = createEdgeWeightGetter$7(options.getEdgeWeight).fromEntry;

	  // Building the index
	  var size = (graph.size - graph.selfLoopCount) * 2;

	  var NeighborhoodPointerArray = typed$4.getPointerArray(size);
	  var NodesPointerArray = typed$4.getPointerArray(graph.order + 1);

	  // NOTE: this memory optimization can yield overflow deopt when computing deltas
	  var WeightsArray = options.getEdgeWeight
	    ? Float64Array
	    : typed$4.getPointerArray(graph.size * 2);

	  // Properties
	  this.C = graph.order;
	  this.M = 0;
	  this.E = size;
	  this.U = 0;
	  this.resolution = resolution;
	  this.level = 0;
	  this.graph = graph;
	  this.nodes = new Array(graph.order);
	  this.keepDendrogram = options.keepDendrogram;

	  // Edge-level
	  this.neighborhood = new NodesPointerArray(size);
	  this.weights = new WeightsArray(size);

	  // Node-level
	  this.loops = new WeightsArray(graph.order);
	  this.starts = new NeighborhoodPointerArray(graph.order + 1);
	  this.belongings = new NodesPointerArray(graph.order);
	  this.dendrogram = [];
	  this.mapping = null;

	  // Community-level
	  this.counts = new NodesPointerArray(graph.order);
	  this.unused = new NodesPointerArray(graph.order);
	  this.totalWeights = new WeightsArray(graph.order);

	  var ids = {};

	  var weight;

	  var i = 0,
	    n = 0;

	  var self = this;

	  graph.forEachNode(function (node) {
	    self.nodes[i] = node;

	    // Node map to index
	    ids[node] = i;

	    // Initializing starts
	    n += graph.undirectedDegreeWithoutSelfLoops(node);
	    self.starts[i] = n;

	    // Belongings
	    self.belongings[i] = i;
	    self.counts[i] = 1;
	    i++;
	  });

	  // Single sweep over the edges
	  graph.forEachEdge(function (edge, attr, source, target, sa, ta, u) {
	    weight = getEdgeWeight(edge, attr, source, target, sa, ta, u);

	    source = ids[source];
	    target = ids[target];

	    self.M += weight;

	    // Self loop?
	    if (source === target) {
	      self.totalWeights[source] += weight * 2;
	      self.loops[source] = weight * 2;
	    } else {
	      self.totalWeights[source] += weight;
	      self.totalWeights[target] += weight;

	      var startSource = --self.starts[source],
	        startTarget = --self.starts[target];

	      self.neighborhood[startSource] = target;
	      self.neighborhood[startTarget] = source;

	      self.weights[startSource] = weight;
	      self.weights[startTarget] = weight;
	    }
	  });

	  this.starts[i] = this.E;

	  if (this.keepDendrogram) this.dendrogram.push(this.belongings.slice());
	  else this.mapping = this.belongings.slice();
	}

	UndirectedLouvainIndex$1.prototype.isolate = function (i, degree) {
	  var currentCommunity = this.belongings[i];

	  // The node is already isolated
	  if (this.counts[currentCommunity] === 1) return currentCommunity;

	  var newCommunity = this.unused[--this.U];

	  var loops = this.loops[i];

	  this.totalWeights[currentCommunity] -= degree + loops;
	  this.totalWeights[newCommunity] += degree + loops;

	  this.belongings[i] = newCommunity;

	  this.counts[currentCommunity]--;
	  this.counts[newCommunity]++;

	  return newCommunity;
	};

	UndirectedLouvainIndex$1.prototype.move = function (i, degree, targetCommunity) {
	  var currentCommunity = this.belongings[i],
	    loops = this.loops[i];

	  this.totalWeights[currentCommunity] -= degree + loops;
	  this.totalWeights[targetCommunity] += degree + loops;

	  this.belongings[i] = targetCommunity;

	  var nowEmpty = this.counts[currentCommunity]-- === 1;
	  this.counts[targetCommunity]++;

	  if (nowEmpty) this.unused[this.U++] = currentCommunity;
	};

	UndirectedLouvainIndex$1.prototype.computeNodeDegree = function (i) {
	  var o, l, weight;

	  var degree = 0;

	  for (o = this.starts[i], l = this.starts[i + 1]; o < l; o++) {
	    weight = this.weights[o];

	    degree += weight;
	  }

	  return degree;
	};

	UndirectedLouvainIndex$1.prototype.expensiveIsolate = function (i) {
	  var degree = this.computeNodeDegree(i);
	  return this.isolate(i, degree);
	};

	UndirectedLouvainIndex$1.prototype.expensiveMove = function (i, ci) {
	  var degree = this.computeNodeDegree(i);
	  this.move(i, degree, ci);
	};

	UndirectedLouvainIndex$1.prototype.zoomOut = function () {
	  var inducedGraph = new Array(this.C - this.U),
	    newLabels = {};

	  var N = this.nodes.length;

	  var C = 0,
	    E = 0;

	  var i, j, l, m, n, ci, cj, data, adj;

	  // Renumbering communities
	  for (i = 0, l = this.C; i < l; i++) {
	    ci = this.belongings[i];

	    if (!(ci in newLabels)) {
	      newLabels[ci] = C;
	      inducedGraph[C] = {
	        adj: {},
	        totalWeights: this.totalWeights[ci],
	        internalWeights: 0
	      };
	      C++;
	    }

	    // We do this to otpimize the number of lookups in next loop
	    this.belongings[i] = newLabels[ci];
	  }

	  // Actualizing dendrogram
	  var currentLevel, nextLevel;

	  if (this.keepDendrogram) {
	    currentLevel = this.dendrogram[this.level];
	    nextLevel = new (typed$4.getPointerArray(C))(N);

	    for (i = 0; i < N; i++) nextLevel[i] = this.belongings[currentLevel[i]];

	    this.dendrogram.push(nextLevel);
	  } else {
	    for (i = 0; i < N; i++) this.mapping[i] = this.belongings[this.mapping[i]];
	  }

	  // Building induced graph matrix
	  for (i = 0, l = this.C; i < l; i++) {
	    ci = this.belongings[i];

	    data = inducedGraph[ci];
	    adj = data.adj;
	    data.internalWeights += this.loops[i];

	    for (j = this.starts[i], m = this.starts[i + 1]; j < m; j++) {
	      n = this.neighborhood[j];
	      cj = this.belongings[n];

	      if (ci === cj) {
	        data.internalWeights += this.weights[j];
	        continue;
	      }

	      if (!(cj in adj)) adj[cj] = 0;

	      adj[cj] += this.weights[j];
	    }
	  }

	  // Rewriting neighborhood
	  this.C = C;

	  n = 0;

	  for (ci = 0; ci < C; ci++) {
	    data = inducedGraph[ci];
	    adj = data.adj;

	    ci = +ci;

	    this.totalWeights[ci] = data.totalWeights;
	    this.loops[ci] = data.internalWeights;
	    this.counts[ci] = 1;

	    this.starts[ci] = n;
	    this.belongings[ci] = ci;

	    for (cj in adj) {
	      this.neighborhood[n] = +cj;
	      this.weights[n] = adj[cj];

	      E++;
	      n++;
	    }
	  }

	  this.starts[C] = E;

	  this.E = E;
	  this.U = 0;
	  this.level++;

	  return newLabels;
	};

	UndirectedLouvainIndex$1.prototype.modularity = function () {
	  var ci, cj, i, j, m;

	  var Q = 0;
	  var M2 = this.M * 2;
	  var internalWeights = new Float64Array(this.C);

	  for (i = 0; i < this.C; i++) {
	    ci = this.belongings[i];
	    internalWeights[ci] += this.loops[i];

	    for (j = this.starts[i], m = this.starts[i + 1]; j < m; j++) {
	      cj = this.belongings[this.neighborhood[j]];

	      if (ci !== cj) continue;

	      internalWeights[ci] += this.weights[j];
	    }
	  }

	  for (i = 0; i < this.C; i++) {
	    Q +=
	      internalWeights[i] / M2 -
	      Math.pow(this.totalWeights[i] / M2, 2) * this.resolution;
	  }

	  return Q;
	};

	UndirectedLouvainIndex$1.prototype.delta = function (
	  i,
	  degree,
	  targetCommunityDegree,
	  targetCommunity
	) {
	  var M = this.M;

	  var targetCommunityTotalWeight = this.totalWeights[targetCommunity];

	  degree += this.loops[i];

	  return (
	    targetCommunityDegree / M - // NOTE: formula is a bit different here because targetCommunityDegree is passed without * 2
	    (targetCommunityTotalWeight * degree * this.resolution) / (2 * M * M)
	  );
	};

	UndirectedLouvainIndex$1.prototype.deltaWithOwnCommunity = function (
	  i,
	  degree,
	  targetCommunityDegree,
	  targetCommunity
	) {
	  var M = this.M;

	  var targetCommunityTotalWeight = this.totalWeights[targetCommunity];

	  degree += this.loops[i];

	  return (
	    targetCommunityDegree / M - // NOTE: formula is a bit different here because targetCommunityDegree is passed without * 2
	    ((targetCommunityTotalWeight - degree) * degree * this.resolution) /
	      (2 * M * M)
	  );
	};

	// NOTE: this is just a faster but equivalent version of #.delta
	// It is just off by a constant factor and is just faster to compute
	UndirectedLouvainIndex$1.prototype.fastDelta = function (
	  i,
	  degree,
	  targetCommunityDegree,
	  targetCommunity
	) {
	  var M = this.M;

	  var targetCommunityTotalWeight = this.totalWeights[targetCommunity];

	  degree += this.loops[i];

	  return (
	    targetCommunityDegree -
	    (degree * targetCommunityTotalWeight * this.resolution) / (2 * M)
	  );
	};

	UndirectedLouvainIndex$1.prototype.fastDeltaWithOwnCommunity = function (
	  i,
	  degree,
	  targetCommunityDegree,
	  targetCommunity
	) {
	  var M = this.M;

	  var targetCommunityTotalWeight = this.totalWeights[targetCommunity];

	  degree += this.loops[i];

	  return (
	    targetCommunityDegree -
	    (degree * (targetCommunityTotalWeight - degree) * this.resolution) / (2 * M)
	  );
	};

	UndirectedLouvainIndex$1.prototype.bounds = function (i) {
	  return [this.starts[i], this.starts[i + 1]];
	};

	UndirectedLouvainIndex$1.prototype.project = function () {
	  var self = this;

	  var projection = {};

	  self.nodes.slice(0, this.C).forEach(function (node, i) {
	    projection[node] = Array.from(
	      self.neighborhood.slice(self.starts[i], self.starts[i + 1])
	    ).map(function (j) {
	      return self.nodes[j];
	    });
	  });

	  return projection;
	};

	UndirectedLouvainIndex$1.prototype.collect = function (level) {
	  if (arguments.length < 1) level = this.level;

	  var o = {};

	  var mapping = this.keepDendrogram ? this.dendrogram[level] : this.mapping;

	  var i, l;

	  for (i = 0, l = mapping.length; i < l; i++) o[this.nodes[i]] = mapping[i];

	  return o;
	};

	UndirectedLouvainIndex$1.prototype.assign = function (prop, level) {
	  if (arguments.length < 2) level = this.level;

	  var mapping = this.keepDendrogram ? this.dendrogram[level] : this.mapping;

	  var i, l;

	  for (i = 0, l = mapping.length; i < l; i++)
	    this.graph.setNodeAttribute(this.nodes[i], prop, mapping[i]);
	};

	UndirectedLouvainIndex$1.prototype[INSPECT] = function () {
	  var proxy = {};

	  // Trick so that node displays the name of the constructor
	  Object.defineProperty(proxy, 'constructor', {
	    value: UndirectedLouvainIndex$1,
	    enumerable: false
	  });

	  proxy.C = this.C;
	  proxy.M = this.M;
	  proxy.E = this.E;
	  proxy.U = this.U;
	  proxy.resolution = this.resolution;
	  proxy.level = this.level;
	  proxy.nodes = this.nodes;
	  proxy.starts = this.starts.slice(0, proxy.C + 1);

	  var eTruncated = ['neighborhood', 'weights'];
	  var cTruncated = ['counts', 'loops', 'belongings', 'totalWeights'];

	  var self = this;

	  eTruncated.forEach(function (key) {
	    proxy[key] = self[key].slice(0, proxy.E);
	  });

	  cTruncated.forEach(function (key) {
	    proxy[key] = self[key].slice(0, proxy.C);
	  });

	  proxy.unused = this.unused.slice(0, this.U);

	  if (this.keepDendrogram) proxy.dendrogram = this.dendrogram;
	  else proxy.mapping = this.mapping;

	  return proxy;
	};

	function DirectedLouvainIndex$1(graph, options) {
	  // Solving options
	  options = resolveDefaults$d(options, DEFAULTS$f);

	  var resolution = options.resolution;

	  // Weight getters
	  var getEdgeWeight = createEdgeWeightGetter$7(options.getEdgeWeight).fromEntry;

	  // Building the index
	  var size = (graph.size - graph.selfLoopCount) * 2;

	  var NeighborhoodPointerArray = typed$4.getPointerArray(size);
	  var NodesPointerArray = typed$4.getPointerArray(graph.order + 1);

	  // NOTE: this memory optimization can yield overflow deopt when computing deltas
	  var WeightsArray = options.getEdgeWeight
	    ? Float64Array
	    : typed$4.getPointerArray(graph.size * 2);

	  // Properties
	  this.C = graph.order;
	  this.M = 0;
	  this.E = size;
	  this.U = 0;
	  this.resolution = resolution;
	  this.level = 0;
	  this.graph = graph;
	  this.nodes = new Array(graph.order);
	  this.keepDendrogram = options.keepDendrogram;

	  // Edge-level
	  // NOTE: edges are stored out then in, in this order
	  this.neighborhood = new NodesPointerArray(size);
	  this.weights = new WeightsArray(size);

	  // Node-level
	  this.loops = new WeightsArray(graph.order);
	  this.starts = new NeighborhoodPointerArray(graph.order + 1);
	  this.offsets = new NeighborhoodPointerArray(graph.order);
	  this.belongings = new NodesPointerArray(graph.order);
	  this.dendrogram = [];

	  // Community-level
	  this.counts = new NodesPointerArray(graph.order);
	  this.unused = new NodesPointerArray(graph.order);
	  this.totalInWeights = new WeightsArray(graph.order);
	  this.totalOutWeights = new WeightsArray(graph.order);

	  var ids = {};

	  var weight;

	  var i = 0,
	    n = 0;

	  var self = this;

	  graph.forEachNode(function (node) {
	    self.nodes[i] = node;

	    // Node map to index
	    ids[node] = i;

	    // Initializing starts & offsets
	    n += graph.outDegreeWithoutSelfLoops(node);
	    self.starts[i] = n;

	    n += graph.inDegreeWithoutSelfLoops(node);
	    self.offsets[i] = n;

	    // Belongings
	    self.belongings[i] = i;
	    self.counts[i] = 1;
	    i++;
	  });

	  // Single sweep over the edges
	  graph.forEachEdge(function (edge, attr, source, target, sa, ta, u) {
	    weight = getEdgeWeight(edge, attr, source, target, sa, ta, u);

	    source = ids[source];
	    target = ids[target];

	    self.M += weight;

	    // Self loop?
	    if (source === target) {
	      self.loops[source] += weight;
	      self.totalInWeights[source] += weight;
	      self.totalOutWeights[source] += weight;
	    } else {
	      self.totalOutWeights[source] += weight;
	      self.totalInWeights[target] += weight;

	      var startSource = --self.starts[source],
	        startTarget = --self.offsets[target];

	      self.neighborhood[startSource] = target;
	      self.neighborhood[startTarget] = source;

	      self.weights[startSource] = weight;
	      self.weights[startTarget] = weight;
	    }
	  });

	  this.starts[i] = this.E;

	  if (this.keepDendrogram) this.dendrogram.push(this.belongings.slice());
	  else this.mapping = this.belongings.slice();
	}

	DirectedLouvainIndex$1.prototype.bounds = UndirectedLouvainIndex$1.prototype.bounds;

	DirectedLouvainIndex$1.prototype.inBounds = function (i) {
	  return [this.offsets[i], this.starts[i + 1]];
	};

	DirectedLouvainIndex$1.prototype.outBounds = function (i) {
	  return [this.starts[i], this.offsets[i]];
	};

	DirectedLouvainIndex$1.prototype.project =
	  UndirectedLouvainIndex$1.prototype.project;

	DirectedLouvainIndex$1.prototype.projectIn = function () {
	  var self = this;

	  var projection = {};

	  self.nodes.slice(0, this.C).forEach(function (node, i) {
	    projection[node] = Array.from(
	      self.neighborhood.slice(self.offsets[i], self.starts[i + 1])
	    ).map(function (j) {
	      return self.nodes[j];
	    });
	  });

	  return projection;
	};

	DirectedLouvainIndex$1.prototype.projectOut = function () {
	  var self = this;

	  var projection = {};

	  self.nodes.slice(0, this.C).forEach(function (node, i) {
	    projection[node] = Array.from(
	      self.neighborhood.slice(self.starts[i], self.offsets[i])
	    ).map(function (j) {
	      return self.nodes[j];
	    });
	  });

	  return projection;
	};

	DirectedLouvainIndex$1.prototype.isolate = function (i, inDegree, outDegree) {
	  var currentCommunity = this.belongings[i];

	  // The node is already isolated
	  if (this.counts[currentCommunity] === 1) return currentCommunity;

	  var newCommunity = this.unused[--this.U];

	  var loops = this.loops[i];

	  this.totalInWeights[currentCommunity] -= inDegree + loops;
	  this.totalInWeights[newCommunity] += inDegree + loops;

	  this.totalOutWeights[currentCommunity] -= outDegree + loops;
	  this.totalOutWeights[newCommunity] += outDegree + loops;

	  this.belongings[i] = newCommunity;

	  this.counts[currentCommunity]--;
	  this.counts[newCommunity]++;

	  return newCommunity;
	};

	DirectedLouvainIndex$1.prototype.move = function (
	  i,
	  inDegree,
	  outDegree,
	  targetCommunity
	) {
	  var currentCommunity = this.belongings[i],
	    loops = this.loops[i];

	  this.totalInWeights[currentCommunity] -= inDegree + loops;
	  this.totalInWeights[targetCommunity] += inDegree + loops;

	  this.totalOutWeights[currentCommunity] -= outDegree + loops;
	  this.totalOutWeights[targetCommunity] += outDegree + loops;

	  this.belongings[i] = targetCommunity;

	  var nowEmpty = this.counts[currentCommunity]-- === 1;
	  this.counts[targetCommunity]++;

	  if (nowEmpty) this.unused[this.U++] = currentCommunity;
	};

	DirectedLouvainIndex$1.prototype.computeNodeInDegree = function (i) {
	  var o, l, weight;

	  var inDegree = 0;

	  for (o = this.offsets[i], l = this.starts[i + 1]; o < l; o++) {
	    weight = this.weights[o];

	    inDegree += weight;
	  }

	  return inDegree;
	};

	DirectedLouvainIndex$1.prototype.computeNodeOutDegree = function (i) {
	  var o, l, weight;

	  var outDegree = 0;

	  for (o = this.starts[i], l = this.offsets[i]; o < l; o++) {
	    weight = this.weights[o];

	    outDegree += weight;
	  }

	  return outDegree;
	};

	DirectedLouvainIndex$1.prototype.expensiveMove = function (i, ci) {
	  var inDegree = this.computeNodeInDegree(i),
	    outDegree = this.computeNodeOutDegree(i);

	  this.move(i, inDegree, outDegree, ci);
	};

	DirectedLouvainIndex$1.prototype.zoomOut = function () {
	  var inducedGraph = new Array(this.C - this.U),
	    newLabels = {};

	  var N = this.nodes.length;

	  var C = 0,
	    E = 0;

	  var i, j, l, m, n, ci, cj, data, offset, out, adj, inAdj, outAdj;

	  // Renumbering communities
	  for (i = 0, l = this.C; i < l; i++) {
	    ci = this.belongings[i];

	    if (!(ci in newLabels)) {
	      newLabels[ci] = C;
	      inducedGraph[C] = {
	        inAdj: {},
	        outAdj: {},
	        totalInWeights: this.totalInWeights[ci],
	        totalOutWeights: this.totalOutWeights[ci],
	        internalWeights: 0
	      };
	      C++;
	    }

	    // We do this to otpimize the number of lookups in next loop
	    this.belongings[i] = newLabels[ci];
	  }

	  // Actualizing dendrogram
	  var currentLevel, nextLevel;

	  if (this.keepDendrogram) {
	    currentLevel = this.dendrogram[this.level];
	    nextLevel = new (typed$4.getPointerArray(C))(N);

	    for (i = 0; i < N; i++) nextLevel[i] = this.belongings[currentLevel[i]];

	    this.dendrogram.push(nextLevel);
	  } else {
	    for (i = 0; i < N; i++) this.mapping[i] = this.belongings[this.mapping[i]];
	  }

	  // Building induced graph matrix
	  for (i = 0, l = this.C; i < l; i++) {
	    ci = this.belongings[i];
	    offset = this.offsets[i];

	    data = inducedGraph[ci];
	    inAdj = data.inAdj;
	    outAdj = data.outAdj;
	    data.internalWeights += this.loops[i];

	    for (j = this.starts[i], m = this.starts[i + 1]; j < m; j++) {
	      n = this.neighborhood[j];
	      cj = this.belongings[n];
	      out = j < offset;

	      adj = out ? outAdj : inAdj;

	      if (ci === cj) {
	        if (out) data.internalWeights += this.weights[j];

	        continue;
	      }

	      if (!(cj in adj)) adj[cj] = 0;

	      adj[cj] += this.weights[j];
	    }
	  }

	  // Rewriting neighborhood
	  this.C = C;

	  n = 0;

	  for (ci = 0; ci < C; ci++) {
	    data = inducedGraph[ci];
	    inAdj = data.inAdj;
	    outAdj = data.outAdj;

	    ci = +ci;

	    this.totalInWeights[ci] = data.totalInWeights;
	    this.totalOutWeights[ci] = data.totalOutWeights;
	    this.loops[ci] = data.internalWeights;
	    this.counts[ci] = 1;

	    this.starts[ci] = n;
	    this.belongings[ci] = ci;

	    for (cj in outAdj) {
	      this.neighborhood[n] = +cj;
	      this.weights[n] = outAdj[cj];

	      E++;
	      n++;
	    }

	    this.offsets[ci] = n;

	    for (cj in inAdj) {
	      this.neighborhood[n] = +cj;
	      this.weights[n] = inAdj[cj];

	      E++;
	      n++;
	    }
	  }

	  this.starts[C] = E;

	  this.E = E;
	  this.U = 0;
	  this.level++;

	  return newLabels;
	};

	DirectedLouvainIndex$1.prototype.modularity = function () {
	  var ci, cj, i, j, m;

	  var Q = 0;
	  var M = this.M;
	  var internalWeights = new Float64Array(this.C);

	  for (i = 0; i < this.C; i++) {
	    ci = this.belongings[i];
	    internalWeights[ci] += this.loops[i];

	    for (j = this.starts[i], m = this.offsets[i]; j < m; j++) {
	      cj = this.belongings[this.neighborhood[j]];

	      if (ci !== cj) continue;

	      internalWeights[ci] += this.weights[j];
	    }
	  }

	  for (i = 0; i < this.C; i++)
	    Q +=
	      internalWeights[i] / M -
	      ((this.totalInWeights[i] * this.totalOutWeights[i]) / Math.pow(M, 2)) *
	        this.resolution;

	  return Q;
	};

	DirectedLouvainIndex$1.prototype.delta = function (
	  i,
	  inDegree,
	  outDegree,
	  targetCommunityDegree,
	  targetCommunity
	) {
	  var M = this.M;

	  var targetCommunityTotalInWeight = this.totalInWeights[targetCommunity],
	    targetCommunityTotalOutWeight = this.totalOutWeights[targetCommunity];

	  var loops = this.loops[i];

	  inDegree += loops;
	  outDegree += loops;

	  return (
	    targetCommunityDegree / M -
	    ((outDegree * targetCommunityTotalInWeight +
	      inDegree * targetCommunityTotalOutWeight) *
	      this.resolution) /
	      (M * M)
	  );
	};

	DirectedLouvainIndex$1.prototype.deltaWithOwnCommunity = function (
	  i,
	  inDegree,
	  outDegree,
	  targetCommunityDegree,
	  targetCommunity
	) {
	  var M = this.M;

	  var targetCommunityTotalInWeight = this.totalInWeights[targetCommunity],
	    targetCommunityTotalOutWeight = this.totalOutWeights[targetCommunity];

	  var loops = this.loops[i];

	  inDegree += loops;
	  outDegree += loops;

	  return (
	    targetCommunityDegree / M -
	    ((outDegree * (targetCommunityTotalInWeight - inDegree) +
	      inDegree * (targetCommunityTotalOutWeight - outDegree)) *
	      this.resolution) /
	      (M * M)
	  );
	};

	DirectedLouvainIndex$1.prototype.collect =
	  UndirectedLouvainIndex$1.prototype.collect;
	DirectedLouvainIndex$1.prototype.assign = UndirectedLouvainIndex$1.prototype.assign;

	DirectedLouvainIndex$1.prototype[INSPECT] = function () {
	  var proxy = {};

	  // Trick so that node displays the name of the constructor
	  Object.defineProperty(proxy, 'constructor', {
	    value: DirectedLouvainIndex$1,
	    enumerable: false
	  });

	  proxy.C = this.C;
	  proxy.M = this.M;
	  proxy.E = this.E;
	  proxy.U = this.U;
	  proxy.resolution = this.resolution;
	  proxy.level = this.level;
	  proxy.nodes = this.nodes;
	  proxy.starts = this.starts.slice(0, proxy.C + 1);

	  var eTruncated = ['neighborhood', 'weights'];
	  var cTruncated = [
	    'counts',
	    'offsets',
	    'loops',
	    'belongings',
	    'totalInWeights',
	    'totalOutWeights'
	  ];

	  var self = this;

	  eTruncated.forEach(function (key) {
	    proxy[key] = self[key].slice(0, proxy.E);
	  });

	  cTruncated.forEach(function (key) {
	    proxy[key] = self[key].slice(0, proxy.C);
	  });

	  proxy.unused = this.unused.slice(0, this.U);

	  if (this.keepDendrogram) proxy.dendrogram = this.dendrogram;
	  else proxy.mapping = this.mapping;

	  return proxy;
	};

	louvain$1.UndirectedLouvainIndex = UndirectedLouvainIndex$1;
	louvain$1.DirectedLouvainIndex = DirectedLouvainIndex$1;

	/**
	 * Graphology Louvain Algorithm
	 * =============================
	 *
	 * JavaScript implementation of the famous Louvain community detection
	 * algorithm for graphology.
	 *
	 * [Articles]
	 * M. E. J. Newman, « Modularity and community structure in networks »,
	 * Proc. Natl. Acad. Sci. USA, vol. 103, no 23, 2006, p. 8577–8582
	 * https://dx.doi.org/10.1073%2Fpnas.0601602103
	 *
	 * Newman, M. E. J. « Community detection in networks: Modularity optimization
	 * and maximum likelihood are equivalent ». Physical Review E, vol. 94, no 5,
	 * novembre 2016, p. 052315. arXiv.org, doi:10.1103/PhysRevE.94.052315.
	 * https://arxiv.org/pdf/1606.02319.pdf
	 *
	 * Blondel, Vincent D., et al. « Fast unfolding of communities in large
	 * networks ». Journal of Statistical Mechanics: Theory and Experiment,
	 * vol. 2008, no 10, octobre 2008, p. P10008. DOI.org (Crossref),
	 * doi:10.1088/1742-5468/2008/10/P10008.
	 * https://arxiv.org/pdf/0803.0476.pdf
	 *
	 * Nicolas Dugué, Anthony Perez. Directed Louvain: maximizing modularity in
	 * directed networks. [Research Report] Université d’Orléans. 2015. hal-01231784
	 * https://hal.archives-ouvertes.fr/hal-01231784
	 *
	 * R. Lambiotte, J.-C. Delvenne and M. Barahona. Laplacian Dynamics and
	 * Multiscale Modular Structure in Networks,
	 * doi:10.1109/TNSE.2015.2391998.
	 * https://arxiv.org/abs/0812.1770
	 *
	 * Traag, V. A., et al. « From Louvain to Leiden: Guaranteeing Well-Connected
	 * Communities ». Scientific Reports, vol. 9, no 1, décembre 2019, p. 5233.
	 * DOI.org (Crossref), doi:10.1038/s41598-019-41695-z.
	 * https://arxiv.org/abs/1810.08473
	 */

	var resolveDefaults$c = defaults$7;
	var isGraph$K = isGraph$N;
	var inferType$3 = inferType$4;
	var SparseMap = sparseMap;
	var SparseQueueSet = sparseQueueSet;
	var createRandomIndex = randomIndex_1.createRandomIndex;

	var indices = louvain$1;

	var UndirectedLouvainIndex = indices.UndirectedLouvainIndex;
	var DirectedLouvainIndex = indices.DirectedLouvainIndex;

	var DEFAULTS$e = {
	  nodeCommunityAttribute: 'community',
	  getEdgeWeight: 'weight',
	  fastLocalMoves: true,
	  randomWalk: true,
	  resolution: 1,
	  rng: Math.random
	};

	function addWeightToCommunity(map, community, weight) {
	  var currentWeight = map.get(community);

	  if (typeof currentWeight === 'undefined') currentWeight = 0;

	  currentWeight += weight;

	  map.set(community, currentWeight);
	}

	var EPSILON = 1e-10;

	function tieBreaker(
	  bestCommunity,
	  currentCommunity,
	  targetCommunity,
	  delta,
	  bestDelta
	) {
	  if (Math.abs(delta - bestDelta) < EPSILON) {
	    if (bestCommunity === currentCommunity) {
	      return false;
	    } else {
	      return targetCommunity > bestCommunity;
	    }
	  } else if (delta > bestDelta) {
	    return true;
	  }

	  return false;
	}

	function undirectedLouvain(detailed, graph, options) {
	  var index = new UndirectedLouvainIndex(graph, {
	    getEdgeWeight: options.getEdgeWeight,
	    keepDendrogram: detailed,
	    resolution: options.resolution
	  });

	  var randomIndex = createRandomIndex(options.rng);

	  // State variables
	  var moveWasMade = true,
	    localMoveWasMade = true;

	  // Communities
	  var currentCommunity, targetCommunity;
	  var communities = new SparseMap(Float64Array, index.C);

	  // Traversal
	  var queue, start, end, weight, ci, ri, s, i, j, l;

	  // Metrics
	  var degree, targetCommunityDegree;

	  // Moves
	  var bestCommunity, bestDelta, deltaIsBetter, delta;

	  // Details
	  var deltaComputations = 0,
	    nodesVisited = 0,
	    moves = [],
	    localMoves,
	    currentMoves;

	  if (options.fastLocalMoves) queue = new SparseQueueSet(index.C);

	  while (moveWasMade) {
	    l = index.C;

	    moveWasMade = false;
	    localMoveWasMade = true;

	    if (options.fastLocalMoves) {
	      currentMoves = 0;

	      // Traversal of the graph
	      ri = options.randomWalk ? randomIndex(l) : 0;

	      for (s = 0; s < l; s++, ri++) {
	        i = ri % l;
	        queue.enqueue(i);
	      }

	      while (queue.size !== 0) {
	        i = queue.dequeue();
	        nodesVisited++;

	        degree = 0;
	        communities.clear();

	        currentCommunity = index.belongings[i];

	        start = index.starts[i];
	        end = index.starts[i + 1];

	        // Traversing neighbors
	        for (; start < end; start++) {
	          j = index.neighborhood[start];
	          weight = index.weights[start];

	          targetCommunity = index.belongings[j];

	          // Incrementing metrics
	          degree += weight;
	          addWeightToCommunity(communities, targetCommunity, weight);
	        }

	        // Finding best community to move to
	        bestDelta = index.fastDeltaWithOwnCommunity(
	          i,
	          degree,
	          communities.get(currentCommunity) || 0,
	          currentCommunity
	        );
	        bestCommunity = currentCommunity;

	        for (ci = 0; ci < communities.size; ci++) {
	          targetCommunity = communities.dense[ci];

	          if (targetCommunity === currentCommunity) continue;

	          targetCommunityDegree = communities.vals[ci];

	          deltaComputations++;

	          delta = index.fastDelta(
	            i,
	            degree,
	            targetCommunityDegree,
	            targetCommunity
	          );

	          deltaIsBetter = tieBreaker(
	            bestCommunity,
	            currentCommunity,
	            targetCommunity,
	            delta,
	            bestDelta
	          );

	          if (deltaIsBetter) {
	            bestDelta = delta;
	            bestCommunity = targetCommunity;
	          }
	        }

	        // Should we move the node?
	        if (bestDelta < 0) {
	          // NOTE: this is to allow nodes to move back to their own singleton
	          // This code however only deals with modularity (e.g. the condition
	          // about bestDelta < 0, which is the delta for moving back to
	          // singleton wrt. modularity). Indeed, rarely, the Louvain
	          // algorithm can produce such cases when a node would be better in
	          // a singleton that in its own community when considering self loops
	          // or a resolution != 1. In this case, delta with your own community
	          // is indeed less than 0. To handle different metrics, one should
	          // consider computing the delta for going back to singleton because
	          // it might not be 0.
	          bestCommunity = index.isolate(i, degree);

	          // If the node was already in a singleton community, we don't consider
	          // a move was made
	          if (bestCommunity === currentCommunity) continue;
	        } else {
	          // If no move was made, we continue to next node
	          if (bestCommunity === currentCommunity) {
	            continue;
	          } else {
	            // Actually moving the node to a new community
	            index.move(i, degree, bestCommunity);
	          }
	        }

	        moveWasMade = true;
	        currentMoves++;

	        // Adding neighbors from other communities to the queue
	        start = index.starts[i];
	        end = index.starts[i + 1];

	        for (; start < end; start++) {
	          j = index.neighborhood[start];
	          targetCommunity = index.belongings[j];

	          if (targetCommunity !== bestCommunity) queue.enqueue(j);
	        }
	      }

	      moves.push(currentMoves);
	    } else {
	      localMoves = [];
	      moves.push(localMoves);

	      // Traditional Louvain iterative traversal of the graph
	      while (localMoveWasMade) {
	        localMoveWasMade = false;
	        currentMoves = 0;

	        ri = options.randomWalk ? randomIndex(l) : 0;

	        for (s = 0; s < l; s++, ri++) {
	          i = ri % l;

	          nodesVisited++;

	          degree = 0;
	          communities.clear();

	          currentCommunity = index.belongings[i];

	          start = index.starts[i];
	          end = index.starts[i + 1];

	          // Traversing neighbors
	          for (; start < end; start++) {
	            j = index.neighborhood[start];
	            weight = index.weights[start];

	            targetCommunity = index.belongings[j];

	            // Incrementing metrics
	            degree += weight;
	            addWeightToCommunity(communities, targetCommunity, weight);
	          }

	          // Finding best community to move to
	          bestDelta = index.fastDeltaWithOwnCommunity(
	            i,
	            degree,
	            communities.get(currentCommunity) || 0,
	            currentCommunity
	          );
	          bestCommunity = currentCommunity;

	          for (ci = 0; ci < communities.size; ci++) {
	            targetCommunity = communities.dense[ci];

	            if (targetCommunity === currentCommunity) continue;

	            targetCommunityDegree = communities.vals[ci];

	            deltaComputations++;

	            delta = index.fastDelta(
	              i,
	              degree,
	              targetCommunityDegree,
	              targetCommunity
	            );

	            deltaIsBetter = tieBreaker(
	              bestCommunity,
	              currentCommunity,
	              targetCommunity,
	              delta,
	              bestDelta
	            );

	            if (deltaIsBetter) {
	              bestDelta = delta;
	              bestCommunity = targetCommunity;
	            }
	          }

	          // Should we move the node?
	          if (bestDelta < 0) {
	            // NOTE: this is to allow nodes to move back to their own singleton
	            // This code however only deals with modularity (e.g. the condition
	            // about bestDelta < 0, which is the delta for moving back to
	            // singleton wrt. modularity). Indeed, rarely, the Louvain
	            // algorithm can produce such cases when a node would be better in
	            // a singleton that in its own community when considering self loops
	            // or a resolution != 1. In this case, delta with your own community
	            // is indeed less than 0. To handle different metrics, one should
	            // consider computing the delta for going back to singleton because
	            // it might not be 0.
	            bestCommunity = index.isolate(i, degree);

	            // If the node was already in a singleton community, we don't consider
	            // a move was made
	            if (bestCommunity === currentCommunity) continue;
	          } else {
	            // If no move was made, we continue to next node
	            if (bestCommunity === currentCommunity) {
	              continue;
	            } else {
	              // Actually moving the node to a new community
	              index.move(i, degree, bestCommunity);
	            }
	          }

	          localMoveWasMade = true;
	          currentMoves++;
	        }

	        localMoves.push(currentMoves);

	        moveWasMade = localMoveWasMade || moveWasMade;
	      }
	    }

	    // We continue working on the induced graph
	    if (moveWasMade) index.zoomOut();
	  }

	  var results = {
	    index: index,
	    deltaComputations: deltaComputations,
	    nodesVisited: nodesVisited,
	    moves: moves
	  };

	  return results;
	}

	function directedLouvain(detailed, graph, options) {
	  var index = new DirectedLouvainIndex(graph, {
	    getEdgeWeight: options.getEdgeWeight,
	    keepDendrogram: detailed,
	    resolution: options.resolution
	  });

	  var randomIndex = createRandomIndex(options.rng);

	  // State variables
	  var moveWasMade = true,
	    localMoveWasMade = true;

	  // Communities
	  var currentCommunity, targetCommunity;
	  var communities = new SparseMap(Float64Array, index.C);

	  // Traversal
	  var queue, start, end, offset, out, weight, ci, ri, s, i, j, l;

	  // Metrics
	  var inDegree, outDegree, targetCommunityDegree;

	  // Moves
	  var bestCommunity, bestDelta, deltaIsBetter, delta;

	  // Details
	  var deltaComputations = 0,
	    nodesVisited = 0,
	    moves = [],
	    localMoves,
	    currentMoves;

	  if (options.fastLocalMoves) queue = new SparseQueueSet(index.C);

	  while (moveWasMade) {
	    l = index.C;

	    moveWasMade = false;
	    localMoveWasMade = true;

	    if (options.fastLocalMoves) {
	      currentMoves = 0;

	      // Traversal of the graph
	      ri = options.randomWalk ? randomIndex(l) : 0;

	      for (s = 0; s < l; s++, ri++) {
	        i = ri % l;
	        queue.enqueue(i);
	      }

	      while (queue.size !== 0) {
	        i = queue.dequeue();
	        nodesVisited++;

	        inDegree = 0;
	        outDegree = 0;
	        communities.clear();

	        currentCommunity = index.belongings[i];

	        start = index.starts[i];
	        end = index.starts[i + 1];
	        offset = index.offsets[i];

	        // Traversing neighbors
	        for (; start < end; start++) {
	          out = start < offset;
	          j = index.neighborhood[start];
	          weight = index.weights[start];

	          targetCommunity = index.belongings[j];

	          // Incrementing metrics
	          if (out) outDegree += weight;
	          else inDegree += weight;

	          addWeightToCommunity(communities, targetCommunity, weight);
	        }

	        // Finding best community to move to
	        bestDelta = index.deltaWithOwnCommunity(
	          i,
	          inDegree,
	          outDegree,
	          communities.get(currentCommunity) || 0,
	          currentCommunity
	        );
	        bestCommunity = currentCommunity;

	        for (ci = 0; ci < communities.size; ci++) {
	          targetCommunity = communities.dense[ci];

	          if (targetCommunity === currentCommunity) continue;

	          targetCommunityDegree = communities.vals[ci];

	          deltaComputations++;

	          delta = index.delta(
	            i,
	            inDegree,
	            outDegree,
	            targetCommunityDegree,
	            targetCommunity
	          );

	          deltaIsBetter = tieBreaker(
	            bestCommunity,
	            currentCommunity,
	            targetCommunity,
	            delta,
	            bestDelta
	          );

	          if (deltaIsBetter) {
	            bestDelta = delta;
	            bestCommunity = targetCommunity;
	          }
	        }

	        // Should we move the node?
	        if (bestDelta < 0) {
	          // NOTE: this is to allow nodes to move back to their own singleton
	          // This code however only deals with modularity (e.g. the condition
	          // about bestDelta < 0, which is the delta for moving back to
	          // singleton wrt. modularity). Indeed, rarely, the Louvain
	          // algorithm can produce such cases when a node would be better in
	          // a singleton that in its own community when considering self loops
	          // or a resolution != 1. In this case, delta with your own community
	          // is indeed less than 0. To handle different metrics, one should
	          // consider computing the delta for going back to singleton because
	          // it might not be 0.
	          bestCommunity = index.isolate(i, inDegree, outDegree);

	          // If the node was already in a singleton community, we don't consider
	          // a move was made
	          if (bestCommunity === currentCommunity) continue;
	        } else {
	          // If no move was made, we continue to next node
	          if (bestCommunity === currentCommunity) {
	            continue;
	          } else {
	            // Actually moving the node to a new community
	            index.move(i, inDegree, outDegree, bestCommunity);
	          }
	        }

	        moveWasMade = true;
	        currentMoves++;

	        // Adding neighbors from other communities to the queue
	        start = index.starts[i];
	        end = index.starts[i + 1];

	        for (; start < end; start++) {
	          j = index.neighborhood[start];
	          targetCommunity = index.belongings[j];

	          if (targetCommunity !== bestCommunity) queue.enqueue(j);
	        }
	      }

	      moves.push(currentMoves);
	    } else {
	      localMoves = [];
	      moves.push(localMoves);

	      // Traditional Louvain iterative traversal of the graph
	      while (localMoveWasMade) {
	        localMoveWasMade = false;
	        currentMoves = 0;

	        ri = options.randomWalk ? randomIndex(l) : 0;

	        for (s = 0; s < l; s++, ri++) {
	          i = ri % l;

	          nodesVisited++;

	          inDegree = 0;
	          outDegree = 0;
	          communities.clear();

	          currentCommunity = index.belongings[i];

	          start = index.starts[i];
	          end = index.starts[i + 1];
	          offset = index.offsets[i];

	          // Traversing neighbors
	          for (; start < end; start++) {
	            out = start < offset;
	            j = index.neighborhood[start];
	            weight = index.weights[start];

	            targetCommunity = index.belongings[j];

	            // Incrementing metrics
	            if (out) outDegree += weight;
	            else inDegree += weight;

	            addWeightToCommunity(communities, targetCommunity, weight);
	          }

	          // Finding best community to move to
	          bestDelta = index.deltaWithOwnCommunity(
	            i,
	            inDegree,
	            outDegree,
	            communities.get(currentCommunity) || 0,
	            currentCommunity
	          );
	          bestCommunity = currentCommunity;

	          for (ci = 0; ci < communities.size; ci++) {
	            targetCommunity = communities.dense[ci];

	            if (targetCommunity === currentCommunity) continue;

	            targetCommunityDegree = communities.vals[ci];

	            deltaComputations++;

	            delta = index.delta(
	              i,
	              inDegree,
	              outDegree,
	              targetCommunityDegree,
	              targetCommunity
	            );

	            deltaIsBetter = tieBreaker(
	              bestCommunity,
	              currentCommunity,
	              targetCommunity,
	              delta,
	              bestDelta
	            );

	            if (deltaIsBetter) {
	              bestDelta = delta;
	              bestCommunity = targetCommunity;
	            }
	          }

	          // Should we move the node?
	          if (bestDelta < 0) {
	            // NOTE: this is to allow nodes to move back to their own singleton
	            // This code however only deals with modularity (e.g. the condition
	            // about bestDelta < 0, which is the delta for moving back to
	            // singleton wrt. modularity). Indeed, rarely, the Louvain
	            // algorithm can produce such cases when a node would be better in
	            // a singleton that in its own community when considering self loops
	            // or a resolution != 1. In this case, delta with your own community
	            // is indeed less than 0. To handle different metrics, one should
	            // consider computing the delta for going back to singleton because
	            // it might not be 0.
	            bestCommunity = index.isolate(i, inDegree, outDegree);

	            // If the node was already in a singleton community, we don't consider
	            // a move was made
	            if (bestCommunity === currentCommunity) continue;
	          } else {
	            // If no move was made, we continue to next node
	            if (bestCommunity === currentCommunity) {
	              continue;
	            } else {
	              // Actually moving the node to a new community
	              index.move(i, inDegree, outDegree, bestCommunity);
	            }
	          }

	          localMoveWasMade = true;
	          currentMoves++;
	        }

	        localMoves.push(currentMoves);

	        moveWasMade = localMoveWasMade || moveWasMade;
	      }
	    }

	    // We continue working on the induced graph
	    if (moveWasMade) index.zoomOut();
	  }

	  var results = {
	    index: index,
	    deltaComputations: deltaComputations,
	    nodesVisited: nodesVisited,
	    moves: moves
	  };

	  return results;
	}

	/**
	 * Function returning the communities mapping of the graph.
	 *
	 * @param  {boolean} assign             - Assign communities to nodes attributes?
	 * @param  {boolean} detailed           - Whether to return detailed information.
	 * @param  {Graph}   graph              - Target graph.
	 * @param  {object}  options            - Options:
	 * @param  {string}    nodeCommunityAttribute - Community node attribute name.
	 * @param  {string}    getEdgeWeight          - Weight edge attribute name or getter function.
	 * @param  {string}    deltaComputation       - Method to use to compute delta computations.
	 * @param  {boolean}   fastLocalMoves         - Whether to use the fast local move optimization.
	 * @param  {boolean}   randomWalk             - Whether to traverse the graph in random order.
	 * @param  {number}    resolution             - Resolution parameter.
	 * @param  {function}  rng                    - RNG function to use.
	 * @return {object}
	 */
	function louvain(assign, detailed, graph, options) {
	  if (!isGraph$K(graph))
	    throw new Error(
	      'graphology-communities-louvain: the given graph is not a valid graphology instance.'
	    );

	  var type = inferType$3(graph);

	  if (type === 'mixed')
	    throw new Error(
	      'graphology-communities-louvain: cannot run the algorithm on a true mixed graph.'
	    );

	  // Attributes name
	  options = resolveDefaults$c(options, DEFAULTS$e);

	  // Empty graph case
	  var c = 0;

	  if (graph.size === 0) {
	    if (assign) {
	      graph.forEachNode(function (node) {
	        graph.setNodeAttribute(node, options.nodeCommunityAttribute, c++);
	      });

	      return;
	    }

	    var communities = {};

	    graph.forEachNode(function (node) {
	      communities[node] = c++;
	    });

	    if (!detailed) return communities;

	    return {
	      communities: communities,
	      count: graph.order,
	      deltaComputations: 0,
	      dendrogram: null,
	      level: 0,
	      modularity: NaN,
	      moves: null,
	      nodesVisited: 0,
	      resolution: options.resolution
	    };
	  }

	  var fn = type === 'undirected' ? undirectedLouvain : directedLouvain;

	  var results = fn(detailed, graph, options);

	  var index = results.index;

	  // Standard output
	  if (!detailed) {
	    if (assign) {
	      index.assign(options.nodeCommunityAttribute);
	      return;
	    }

	    return index.collect();
	  }

	  // Detailed output
	  var output = {
	    count: index.C,
	    deltaComputations: results.deltaComputations,
	    dendrogram: index.dendrogram,
	    level: index.level,
	    modularity: index.modularity(),
	    moves: results.moves,
	    nodesVisited: results.nodesVisited,
	    resolution: options.resolution
	  };

	  if (assign) {
	    index.assign(options.nodeCommunityAttribute);
	    return output;
	  }

	  output.communities = index.collect();

	  return output;
	}

	/**
	 * Exporting.
	 */
	var fn = louvain.bind(null, false, false);
	fn.assign = louvain.bind(null, true, false);
	fn.detailed = louvain.bind(null, false, true);
	fn.defaults = DEFAULTS$e;

	var communitiesLouvain$2 = fn;

	var communitiesLouvain$1 = communitiesLouvain$2;

	var components$2 = {};

	var addNode = {};

	/**
	 * Graphology Node Adders
	 * =======================
	 *
	 * Generic node addition functions that can be used to avoid nasty repetitive
	 * conditions.
	 */

	addNode.copyNode = function (graph, key, attributes) {
	  attributes = Object.assign({}, attributes);
	  return graph.addNode(key, attributes);
	};

	var addEdge = {};

	/**
	 * Graphology Edge Adders
	 * =======================
	 *
	 * Generic edge addition functions that can be used to avoid nasty repetitive
	 * conditions.
	 */

	addEdge.addEdge = function addEdge(
	  graph,
	  undirected,
	  key,
	  source,
	  target,
	  attributes
	) {
	  if (undirected) {
	    if (key === null || key === undefined)
	      return graph.addUndirectedEdge(source, target, attributes);
	    else return graph.addUndirectedEdgeWithKey(key, source, target, attributes);
	  } else {
	    if (key === null || key === undefined)
	      return graph.addDirectedEdge(source, target, attributes);
	    else return graph.addDirectedEdgeWithKey(key, source, target, attributes);
	  }
	};

	addEdge.copyEdge = function copyEdge(
	  graph,
	  undirected,
	  key,
	  source,
	  target,
	  attributes
	) {
	  attributes = Object.assign({}, attributes);

	  if (undirected) {
	    if (key === null || key === undefined)
	      return graph.addUndirectedEdge(source, target, attributes);
	    else return graph.addUndirectedEdgeWithKey(key, source, target, attributes);
	  } else {
	    if (key === null || key === undefined)
	      return graph.addDirectedEdge(source, target, attributes);
	    else return graph.addDirectedEdgeWithKey(key, source, target, attributes);
	  }
	};

	addEdge.mergeEdge = function mergeEdge(
	  graph,
	  undirected,
	  key,
	  source,
	  target,
	  attributes
	) {
	  if (undirected) {
	    if (key === null || key === undefined)
	      return graph.mergeUndirectedEdge(source, target, attributes);
	    else
	      return graph.mergeUndirectedEdgeWithKey(key, source, target, attributes);
	  } else {
	    if (key === null || key === undefined)
	      return graph.mergeDirectedEdge(source, target, attributes);
	    else return graph.mergeDirectedEdgeWithKey(key, source, target, attributes);
	  }
	};

	addEdge.updateEdge = function updateEdge(
	  graph,
	  undirected,
	  key,
	  source,
	  target,
	  updater
	) {
	  if (undirected) {
	    if (key === null || key === undefined)
	      return graph.updateUndirectedEdge(source, target, updater);
	    else return graph.updateUndirectedEdgeWithKey(key, source, target, updater);
	  } else {
	    if (key === null || key === undefined)
	      return graph.updateDirectedEdge(source, target, updater);
	    else return graph.updateDirectedEdgeWithKey(key, source, target, updater);
	  }
	};

	/**
	 * Graphology DFS Stack
	 * =====================
	 *
	 * An experiment to speed up DFS in graphs and connected component detection.
	 *
	 * It should mostly save memory and not improve theoretical runtime.
	 */

	function DFSStack$2(graph) {
	  this.graph = graph;
	  this.stack = new Array(graph.order);
	  this.seen = new Set();
	  this.size = 0;
	}

	DFSStack$2.prototype.hasAlreadySeenEverything = function () {
	  return this.seen.size === this.graph.order;
	};

	DFSStack$2.prototype.countUnseenNodes = function () {
	  return this.graph.order - this.seen.size;
	};

	DFSStack$2.prototype.forEachNodeYetUnseen = function (callback) {
	  var seen = this.seen;
	  var graph = this.graph;

	  graph.someNode(function (node, attr) {
	    // Useful early exit for connected graphs
	    if (seen.size === graph.order) return true; // break

	    // Node already seen?
	    if (seen.has(node)) return false; // continue

	    var shouldBreak = callback(node, attr);

	    if (shouldBreak) return true;

	    return false;
	  });
	};

	DFSStack$2.prototype.has = function (node) {
	  return this.seen.has(node);
	};

	DFSStack$2.prototype.push = function (node) {
	  var seenSizeBefore = this.seen.size;

	  this.seen.add(node);

	  // If node was already seen
	  if (seenSizeBefore === this.seen.size) return false;

	  this.stack[this.size++] = node;

	  return true;
	};

	DFSStack$2.prototype.pushWith = function (node, item) {
	  var seenSizeBefore = this.seen.size;

	  this.seen.add(node);

	  // If node was already seen
	  if (seenSizeBefore === this.seen.size) return false;

	  this.stack[this.size++] = item;

	  return true;
	};

	DFSStack$2.prototype.pop = function () {
	  if (this.size === 0) return;

	  return this.stack[--this.size];
	};

	var dfsStack = DFSStack$2;

	/**
	 * Graphology Components
	 * ======================
	 *
	 * Basic connected components-related functions.
	 */

	var isGraph$J = isGraph$N;
	var copyNode$2 = addNode.copyNode;
	var copyEdge$8 = addEdge.copyEdge;
	var DFSStack$1 = dfsStack;

	/**
	 * Function iterating over a graph's connected component using a callback.
	 *
	 * @param {Graph}    graph    - Target graph.
	 * @param {function} callback - Iteration callback.
	 */
	function forEachConnectedComponent(graph, callback) {
	  if (!isGraph$J(graph))
	    throw new Error(
	      'graphology-components: the given graph is not a valid graphology instance.'
	    );

	  // A null graph has no connected components by definition
	  if (!graph.order) return;

	  var stack = new DFSStack$1(graph);
	  var push = stack.push.bind(stack);

	  stack.forEachNodeYetUnseen(function (node) {
	    var component = [];

	    stack.push(node);

	    var source;

	    while (stack.size !== 0) {
	      source = stack.pop();

	      component.push(source);

	      graph.forEachNeighbor(source, push);
	    }

	    callback(component);
	  });
	}

	function forEachConnectedComponentOrder(graph, callback) {
	  if (!isGraph$J(graph))
	    throw new Error(
	      'graphology-components: the given graph is not a valid graphology instance.'
	    );

	  // A null graph has no connected components by definition
	  if (!graph.order) return;

	  var stack = new DFSStack$1(graph);
	  var push = stack.push.bind(stack);

	  stack.forEachNodeYetUnseen(function (node) {
	    var order = 0;

	    stack.push(node);

	    var source;

	    while (stack.size !== 0) {
	      source = stack.pop();

	      order++;

	      graph.forEachNeighbor(source, push);
	    }

	    callback(order);
	  });
	}

	function forEachConnectedComponentOrderWithEdgeFilter(
	  graph,
	  edgeFilter,
	  callback
	) {
	  if (!isGraph$J(graph))
	    throw new Error(
	      'graphology-components: the given graph is not a valid graphology instance.'
	    );

	  // A null graph has no connected components by definition
	  if (!graph.order) return;

	  var stack = new DFSStack$1(graph);

	  var source;

	  function push(e, a, s, t, sa, ta, u) {
	    if (source === t) t = s;

	    if (!edgeFilter(e, a, s, t, sa, ta, u)) return;

	    stack.push(t);
	  }

	  stack.forEachNodeYetUnseen(function (node) {
	    var order = 0;

	    stack.push(node);

	    while (stack.size !== 0) {
	      source = stack.pop();

	      order++;

	      graph.forEachEdge(source, push);
	    }

	    callback(order);
	  });
	}

	function countConnectedComponents(graph) {
	  var n = 0;

	  forEachConnectedComponentOrder(graph, function () {
	    n++;
	  });

	  return n;
	}

	/**
	 * Function returning a list of a graph's connected components as arrays
	 * of node keys.
	 *
	 * @param  {Graph} graph - Target graph.
	 * @return {array}
	 */
	function connectedComponents(graph) {
	  var components = [];

	  forEachConnectedComponent(graph, function (component) {
	    components.push(component);
	  });

	  return components;
	}

	/**
	 * Function returning the largest component of the given graph.
	 *
	 * @param  {Graph} graph - Target graph.
	 * @return {array}
	 */
	function largestConnectedComponent(graph) {
	  if (!isGraph$J(graph))
	    throw new Error(
	      'graphology-components: the given graph is not a valid graphology instance.'
	    );

	  if (!graph.order) return [];

	  var stack = new DFSStack$1(graph);
	  var push = stack.push.bind(stack);

	  var largestComponent = [];
	  var component;

	  stack.forEachNodeYetUnseen(function (node) {
	    component = [];

	    stack.push(node);

	    var source;

	    while (stack.size !== 0) {
	      source = stack.pop();

	      component.push(source);

	      graph.forEachNeighbor(source, push);
	    }

	    if (component.length > largestComponent.length)
	      largestComponent = component;

	    // Early exit condition:
	    // If current largest component's size is larger than the number of
	    // remaining nodes to visit, we can safely assert we found the
	    // overall largest component already.
	    if (largestComponent.length > stack.countUnseenNodes()) return true;

	    return false;
	  });

	  return largestComponent;
	}

	/**
	 * Function returning a subgraph composed of the largest component of the given graph.
	 *
	 * @param  {Graph} graph - Target graph.
	 * @return {Graph}
	 */
	function largestConnectedComponentSubgraph(graph) {
	  var component = largestConnectedComponent(graph);

	  var S = graph.nullCopy();

	  component.forEach(function (key) {
	    copyNode$2(S, key, graph.getNodeAttributes(key));
	  });

	  graph.forEachEdge(function (
	    key,
	    attr,
	    source,
	    target,
	    sourceAttr,
	    targetAttr,
	    undirected
	  ) {
	    if (S.hasNode(source)) {
	      copyEdge$8(S, undirected, key, source, target, attr);
	    }
	  });

	  return S;
	}

	/**
	 * Function mutating a graph in order to drop every node and edge that does
	 * not belong to its largest connected component.
	 *
	 * @param  {Graph} graph - Target graph.
	 */
	function cropToLargestConnectedComponent(graph) {
	  var component = new Set(largestConnectedComponent(graph));

	  graph.forEachNode(function (key) {
	    if (!component.has(key)) {
	      graph.dropNode(key);
	    }
	  });
	}

	/**
	 * Function returning a list of strongly connected components.
	 *
	 * @param  {Graph} graph - Target directed graph.
	 * @return {array}
	 */
	function stronglyConnectedComponents(graph) {
	  if (!isGraph$J(graph))
	    throw new Error(
	      'graphology-components: the given graph is not a valid graphology instance.'
	    );

	  if (!graph.order) return [];

	  if (graph.type === 'undirected')
	    throw new Error('graphology-components: the given graph is undirected');

	  var nodes = graph.nodes(),
	    components = [],
	    i,
	    l;

	  if (!graph.size) {
	    for (i = 0, l = nodes.length; i < l; i++) components.push([nodes[i]]);
	    return components;
	  }

	  var count = 1,
	    P = [],
	    S = [],
	    preorder = new Map(),
	    assigned = new Set(),
	    component,
	    pop,
	    vertex;

	  var DFS = function (node) {
	    var neighbor;
	    var neighbors = graph.outboundNeighbors(node);
	    var neighborOrder;

	    preorder.set(node, count++);
	    P.push(node);
	    S.push(node);

	    for (var k = 0, n = neighbors.length; k < n; k++) {
	      neighbor = neighbors[k];

	      if (preorder.has(neighbor)) {
	        neighborOrder = preorder.get(neighbor);
	        if (!assigned.has(neighbor))
	          while (preorder.get(P[P.length - 1]) > neighborOrder) P.pop();
	      } else {
	        DFS(neighbor);
	      }
	    }

	    if (preorder.get(P[P.length - 1]) === preorder.get(node)) {
	      component = [];
	      do {
	        pop = S.pop();
	        component.push(pop);
	        assigned.add(pop);
	      } while (pop !== node);
	      components.push(component);
	      P.pop();
	    }
	  };

	  for (i = 0, l = nodes.length; i < l; i++) {
	    vertex = nodes[i];
	    if (!assigned.has(vertex)) DFS(vertex);
	  }

	  return components;
	}

	/**
	 * Exporting.
	 */
	components$2.forEachConnectedComponent = forEachConnectedComponent;
	components$2.forEachConnectedComponentOrder = forEachConnectedComponentOrder;
	components$2.forEachConnectedComponentOrderWithEdgeFilter =
	  forEachConnectedComponentOrderWithEdgeFilter;
	components$2.countConnectedComponents = countConnectedComponents;
	components$2.connectedComponents = connectedComponents;
	components$2.largestConnectedComponent = largestConnectedComponent;
	components$2.largestConnectedComponentSubgraph = largestConnectedComponentSubgraph;
	components$2.cropToLargestConnectedComponent = cropToLargestConnectedComponent;
	components$2.stronglyConnectedComponents = stronglyConnectedComponents;

	var components$1 = components$2;

	var generators$2 = {};

	var classic = {};

	/**
	 * Graphology Complete Graph Generator
	 * ====================================
	 *
	 * Function generating complete graphs.
	 */

	var isGraphConstructor$d = isGraphConstructor$e;

	/**
	 * Generates a complete graph with n nodes.
	 *
	 * @param  {Class}  GraphClass - The Graph Class to instantiate.
	 * @param  {number} order      - Number of nodes of the graph.
	 * @return {Graph}
	 */
	var complete = function complete(GraphClass, order) {
	  if (!isGraphConstructor$d(GraphClass))
	    throw new Error(
	      'graphology-generators/classic/complete: invalid Graph constructor.'
	    );

	  var graph = new GraphClass();

	  var i, j;

	  for (i = 0; i < order; i++) graph.addNode(i);

	  for (i = 0; i < order; i++) {
	    for (j = i + 1; j < order; j++) {
	      if (graph.type !== 'directed') graph.addUndirectedEdge(i, j);

	      if (graph.type !== 'undirected') {
	        graph.addDirectedEdge(i, j);
	        graph.addDirectedEdge(j, i);
	      }
	    }
	  }

	  return graph;
	};

	/**
	 * Graphology Empty Graph Generator
	 * =================================
	 *
	 * Function generating empty graphs.
	 */

	var isGraphConstructor$c = isGraphConstructor$e;

	/**
	 * Generates an empty graph with n nodes and 0 edges.
	 *
	 * @param  {Class}  GraphClass - The Graph Class to instantiate.
	 * @param  {number} order      - Number of nodes of the graph.
	 * @return {Graph}
	 */
	var empty$2 = function empty(GraphClass, order) {
	  if (!isGraphConstructor$c(GraphClass))
	    throw new Error(
	      'graphology-generators/classic/empty: invalid Graph constructor.'
	    );

	  var graph = new GraphClass();

	  var i;

	  for (i = 0; i < order; i++) graph.addNode(i);

	  return graph;
	};

	/**
	 * Graphology Ladder Graph Generator
	 * ==================================
	 *
	 * Function generating ladder graphs.
	 */

	var isGraphConstructor$b = isGraphConstructor$e;

	/**
	 * Generates a ladder graph of length n (order will therefore be 2 * n).
	 *
	 * @param  {Class}  GraphClass - The Graph Class to instantiate.
	 * @param  {number} length     - Length of the ladder.
	 * @return {Graph}
	 */
	var ladder = function ladder(GraphClass, length) {
	  if (!isGraphConstructor$b(GraphClass))
	    throw new Error(
	      'graphology-generators/classic/ladder: invalid Graph constructor.'
	    );

	  var graph = new GraphClass();

	  var i;

	  for (i = 0; i < length - 1; i++) graph.mergeEdge(i, i + 1);
	  for (i = length; i < length * 2 - 1; i++) graph.mergeEdge(i, i + 1);
	  for (i = 0; i < length; i++) graph.addEdge(i, i + length);

	  return graph;
	};

	/**
	 * Graphology Path Graph Generator
	 * ================================
	 *
	 * Function generating path graphs.
	 */

	var isGraphConstructor$a = isGraphConstructor$e;

	/**
	 * Generates a path graph with n nodes.
	 *
	 * @param  {Class}  GraphClass - The Graph Class to instantiate.
	 * @param  {number} order      - Number of nodes of the graph.
	 * @return {Graph}
	 */
	var path = function path(GraphClass, order) {
	  if (!isGraphConstructor$a(GraphClass))
	    throw new Error(
	      'graphology-generators/classic/path: invalid Graph constructor.'
	    );

	  var graph = new GraphClass();

	  for (var i = 0; i < order - 1; i++) graph.mergeEdge(i, i + 1);

	  return graph;
	};

	/**
	 * Graphology Classic Graph Generators
	 * ====================================
	 *
	 * Classic graph generators endpoint.
	 */

	classic.complete = complete;
	classic.empty = empty$2;
	classic.ladder = ladder;
	classic.path = path;

	var community = {};

	/**
	 * Graphology Caveman Graph Generator
	 * ===================================
	 *
	 * Function generating caveman graphs.
	 *
	 * [Article]:
	 * Watts, D. J. 'Networks, Dynamics, and the Small-World Phenomenon.'
	 * Amer. J. Soc. 105, 493-527, 1999.
	 */

	var isGraphConstructor$9 = isGraphConstructor$e,
	  empty$1 = empty$2;

	/**
	 * Function returning a caveman graph with desired properties.
	 *
	 * @param  {Class}    GraphClass    - The Graph Class to instantiate.
	 * @param  {number}   l             - The number of cliques in the graph.
	 * @param  {number}   k             - Size of the cliques.
	 * @return {Graph}
	 */
	var caveman = function caveman(GraphClass, l, k) {
	  if (!isGraphConstructor$9(GraphClass))
	    throw new Error(
	      'graphology-generators/community/caveman: invalid Graph constructor.'
	    );

	  var m = l * k;

	  var graph = empty$1(GraphClass, m);

	  if (k < 2) return graph;

	  var i, j, s;

	  for (i = 0; i < m; i += k) {
	    for (j = i; j < i + k; j++) {
	      for (s = j + 1; s < i + k; s++) graph.addEdge(j, s);
	    }
	  }

	  return graph;
	};

	/**
	 * Graphology Connected Caveman Graph Generator
	 * =============================================
	 *
	 * Function generating connected caveman graphs.
	 *
	 * [Article]:
	 * Watts, D. J. 'Networks, Dynamics, and the Small-World Phenomenon.'
	 * Amer. J. Soc. 105, 493-527, 1999.
	 */

	var isGraphConstructor$8 = isGraphConstructor$e,
	  empty = empty$2;

	/**
	 * Function returning a connected caveman graph with desired properties.
	 *
	 * @param  {Class}    GraphClass    - The Graph Class to instantiate.
	 * @param  {number}   l             - The number of cliques in the graph.
	 * @param  {number}   k             - Size of the cliques.
	 * @return {Graph}
	 */
	var connectedCaveman = function connectedCaveman(GraphClass, l, k) {
	  if (!isGraphConstructor$8(GraphClass))
	    throw new Error(
	      'graphology-generators/community/connected-caveman: invalid Graph constructor.'
	    );

	  var m = l * k;

	  var graph = empty(GraphClass, m);

	  if (k < 2) return graph;

	  var i, j, s;

	  for (i = 0; i < m; i += k) {
	    for (j = i; j < i + k; j++) {
	      for (s = j + 1; s < i + k; s++) {
	        if (j !== i || j !== s - 1) graph.addEdge(j, s);
	      }
	    }

	    if (i > 0) graph.addEdge(i, (i - 1) % m);
	  }

	  graph.addEdge(0, m - 1);

	  return graph;
	};

	/**
	 * Graphology Community Graph Generators
	 * ======================================
	 *
	 * Community graph generators endpoint.
	 */

	community.caveman = caveman;
	community.connectedCaveman = connectedCaveman;

	var random$2 = {};

	/**
	 * Graphology Random Clusters Graph Generator
	 * ===========================================
	 *
	 * Function generating a graph containing the desired number of nodes & edges
	 * and organized in the desired number of clusters.
	 *
	 * [Author]:
	 * Alexis Jacomy
	 */

	var isGraphConstructor$7 = isGraphConstructor$e;

	/**
	 * Generates a random graph with clusters.
	 *
	 * @param  {Class}    GraphClass    - The Graph Class to instantiate.
	 * @param  {object}   options       - Options:
	 * @param  {number}     clusterDensity - Probability that an edge will link two
	 *                                       nodes of the same cluster.
	 * @param  {number}     order          - Number of nodes.
	 * @param  {number}     size           - Number of edges.
	 * @param  {number}     clusters       - Number of clusters.
	 * @param  {function}   rng            - Custom RNG function.
	 * @return {Graph}
	 */
	var clusters = function (GraphClass, options) {
	  if (!isGraphConstructor$7(GraphClass))
	    throw new Error(
	      'graphology-generators/random/clusters: invalid Graph constructor.'
	    );

	  options = options || {};

	  var clusterDensity =
	      'clusterDensity' in options ? options.clusterDensity : 0.5,
	    rng = options.rng || Math.random,
	    N = options.order,
	    E = options.size,
	    C = options.clusters;

	  if (
	    typeof clusterDensity !== 'number' ||
	    clusterDensity > 1 ||
	    clusterDensity < 0
	  )
	    throw new Error(
	      'graphology-generators/random/clusters: `clusterDensity` option should be a number between 0 and 1.'
	    );

	  if (typeof rng !== 'function')
	    throw new Error(
	      'graphology-generators/random/clusters: `rng` option should be a function.'
	    );

	  if (typeof N !== 'number' || N <= 0)
	    throw new Error(
	      'graphology-generators/random/clusters: `order` option should be a positive number.'
	    );

	  if (typeof E !== 'number' || E <= 0)
	    throw new Error(
	      'graphology-generators/random/clusters: `size` option should be a positive number.'
	    );

	  if (typeof C !== 'number' || C <= 0)
	    throw new Error(
	      'graphology-generators/random/clusters: `clusters` option should be a positive number.'
	    );

	  // Creating graph
	  var graph = new GraphClass();

	  // Adding nodes
	  if (!N) return graph;

	  // Initializing clusters
	  var clusters = new Array(C),
	    cluster,
	    nodes,
	    i;

	  for (i = 0; i < C; i++) clusters[i] = [];

	  for (i = 0; i < N; i++) {
	    cluster = (rng() * C) | 0;
	    graph.addNode(i, {cluster: cluster});
	    clusters[cluster].push(i);
	  }

	  // Adding edges
	  if (!E) return graph;

	  var source, target, l;

	  for (i = 0; i < E; i++) {
	    // Adding a link between two random nodes
	    if (rng() < 1 - clusterDensity) {
	      source = (rng() * N) | 0;

	      do {
	        target = (rng() * N) | 0;
	      } while (source === target);
	    }

	    // Adding a link between two nodes from the same cluster
	    else {
	      cluster = (rng() * C) | 0;
	      nodes = clusters[cluster];
	      l = nodes.length;

	      if (!l || l < 2) {
	        // TODO: in those case we may have fewer edges than required
	        // TODO: check where E is over full clusterDensity
	        continue;
	      }

	      source = nodes[(rng() * l) | 0];

	      do {
	        target = nodes[(rng() * l) | 0];
	      } while (source === target);
	    }

	    if (!graph.multi) graph.mergeEdge(source, target);
	    else graph.addEdge(source, target);
	  }

	  return graph;
	};

	var density$2 = {};

	/**
	 * Graphology Simple Size
	 * =======================
	 *
	 * Function returning the simple size of a graph, i.e. the size it would have
	 * if it we assume it is a simple graph.
	 */

	var isGraph$I = isGraph$N;

	/**
	 * Simple size function.
	 *
	 * @param  {Graph}  graph - Target graph.
	 * @return {number}
	 */
	var simpleSize$1 = function simpleSize(graph) {
	  // Handling errors
	  if (!isGraph$I(graph))
	    throw new Error(
	      'graphology-metrics/simple-size: the given graph is not a valid graphology instance.'
	    );

	  if (!graph.multi) return graph.size;

	  var u = 0;
	  var d = 0;

	  function accumulateUndirected() {
	    u++;
	  }

	  function accumulateDirected() {
	    d++;
	  }

	  graph.forEachNode(function (node) {
	    if (graph.type !== 'directed')
	      graph.forEachUndirectedNeighbor(node, accumulateUndirected);

	    if (graph.type !== 'undirected')
	      graph.forEachOutNeighbor(node, accumulateDirected);
	  });

	  return u / 2 + d;
	};

	/**
	 * Graphology Density
	 * ===================
	 *
	 * Functions used to compute the density of the given graph.
	 */

	var isGraph$H = isGraph$N;
	var simpleSize = simpleSize$1;

	/**
	 * Returns the undirected density.
	 *
	 * @param  {number} order - Number of nodes in the graph.
	 * @param  {number} size  - Number of edges in the graph.
	 * @return {number}
	 */
	function undirectedDensity(order, size) {
	  return (2 * size) / (order * (order - 1));
	}

	/**
	 * Returns the directed density.
	 *
	 * @param  {number} order - Number of nodes in the graph.
	 * @param  {number} size  - Number of edges in the graph.
	 * @return {number}
	 */
	function directedDensity(order, size) {
	  return size / (order * (order - 1));
	}

	/**
	 * Returns the mixed density.
	 *
	 * @param  {number} order - Number of nodes in the graph.
	 * @param  {number} size  - Number of edges in the graph.
	 * @return {number}
	 */
	function mixedDensity(order, size) {
	  var d = order * (order - 1);

	  return size / (d + d / 2);
	}

	/**
	 * Returns the density for the given parameters.
	 *
	 * Arity 3:
	 * @param  {boolean} type  - Type of density.
	 * @param  {boolean} multi - Compute multi density?
	 * @param  {Graph}   graph - Target graph.
	 *
	 * Arity 4:
	 * @param  {boolean} type  - Type of density.
	 * @param  {boolean} multi - Compute multi density?
	 * @param  {number}  order - Number of nodes in the graph.
	 * @param  {number}  size  - Number of edges in the graph.
	 *
	 * @return {number}
	 */
	function abstractDensity(type, multi, graph) {
	  var order, size;

	  // Retrieving order & size
	  if (arguments.length > 3) {
	    order = graph;
	    size = arguments[3];

	    if (typeof order !== 'number' || order < 0)
	      throw new Error(
	        'graphology-metrics/density: given order is not a valid number.'
	      );

	    if (typeof size !== 'number' || size < 0)
	      throw new Error(
	        'graphology-metrics/density: given size is not a valid number.'
	      );
	  } else {
	    if (!isGraph$H(graph))
	      throw new Error(
	        'graphology-metrics/density: given graph is not a valid graphology instance.'
	      );

	    order = graph.order;
	    size = graph.size;

	    if (graph.multi && multi === false) size = simpleSize(graph);
	  }

	  // When the graph has only one node, its density is 0
	  if (order < 2) return 0;

	  // Guessing type & multi
	  if (type === null) type = graph.type;
	  if (multi === null) multi = graph.multi;

	  // Getting the correct function
	  var fn;

	  if (type === 'undirected') fn = undirectedDensity;
	  else if (type === 'directed') fn = directedDensity;
	  else fn = mixedDensity;

	  // Applying the function
	  return fn(order, size);
	}

	/**
	 * Exporting.
	 */
	density$2.abstractDensity = abstractDensity;
	density$2.density = abstractDensity.bind(null, null, null);
	density$2.directedDensity = abstractDensity.bind(null, 'directed', false);
	density$2.undirectedDensity = abstractDensity.bind(null, 'undirected', false);
	density$2.mixedDensity = abstractDensity.bind(null, 'mixed', false);
	density$2.multiDirectedDensity = abstractDensity.bind(null, 'directed', true);
	density$2.multiUndirectedDensity = abstractDensity.bind(null, 'undirected', true);
	density$2.multiMixedDensity = abstractDensity.bind(null, 'mixed', true);

	/**
	 * Graphology Erdos-Renyi Graph Generator
	 * =======================================
	 *
	 * Function generating binomial graphs.
	 */

	var isGraphConstructor$6 = isGraphConstructor$e;
	var density$1 = density$2.abstractDensity;

	/**
	 * Generates a binomial graph graph with n nodes.
	 *
	 * @param  {Class}    GraphClass    - The Graph Class to instantiate.
	 * @param  {object}   options       - Options:
	 * @param  {number}     order       - Number of nodes in the graph.
	 * @param  {number}     probability - Probability for edge creation.
	 * @param  {function}   rng         - Custom RNG function.
	 * @return {Graph}
	 */
	function erdosRenyi(GraphClass, options) {
	  if (!isGraphConstructor$6(GraphClass))
	    throw new Error(
	      'graphology-generators/random/erdos-renyi: invalid Graph constructor.'
	    );

	  var order = options.order;
	  var probability = options.probability;
	  var rng = options.rng || Math.random;

	  var graph = new GraphClass();

	  // If user gave a size, we need to compute probability
	  if (typeof options.approximateSize === 'number') {
	    probability = density$1(graph.type, false, order, options.approximateSize);
	  }

	  if (typeof order !== 'number' || order <= 0)
	    throw new Error(
	      'graphology-generators/random/erdos-renyi: invalid `order`. Should be a positive number.'
	    );

	  if (typeof probability !== 'number' || probability < 0 || probability > 1)
	    throw new Error(
	      "graphology-generators/random/erdos-renyi: invalid `probability`. Should be a number between 0 and 1. Or maybe you gave an `approximateSize` exceeding the graph's density."
	    );

	  if (typeof rng !== 'function')
	    throw new Error(
	      'graphology-generators/random/erdos-renyi: invalid `rng`. Should be a function.'
	    );

	  var i, j;

	  for (i = 0; i < order; i++) graph.addNode(i);

	  if (probability <= 0) return graph;

	  for (i = 0; i < order; i++) {
	    for (j = i + 1; j < order; j++) {
	      if (graph.type !== 'directed') {
	        if (rng() < probability) graph.addUndirectedEdge(i, j);
	      }

	      if (graph.type !== 'undirected') {
	        if (rng() < probability) graph.addDirectedEdge(i, j);

	        if (rng() < probability) graph.addDirectedEdge(j, i);
	      }
	    }
	  }

	  return graph;
	}

	/**
	 * Generates a binomial graph graph with n nodes using a faster algorithm
	 * for sparse graphs.
	 *
	 * @param  {Class}    GraphClass    - The Graph Class to instantiate.
	 * @param  {object}   options       - Options:
	 * @param  {number}     order       - Number of nodes in the graph.
	 * @param  {number}     probability - Probability for edge creation.
	 * @param  {function}   rng         - Custom RNG function.
	 * @return {Graph}
	 */
	function erdosRenyiSparse(GraphClass, options) {
	  if (!isGraphConstructor$6(GraphClass))
	    throw new Error(
	      'graphology-generators/random/erdos-renyi: invalid Graph constructor.'
	    );

	  var order = options.order;
	  var probability = options.probability;
	  var rng = options.rng || Math.random;

	  var graph = new GraphClass();

	  // If user gave a size, we need to compute probability
	  if (typeof options.approximateSize === 'number') {
	    probability = density$1(graph.type, false, order, options.approximateSize);
	  }

	  if (typeof order !== 'number' || order <= 0)
	    throw new Error(
	      'graphology-generators/random/erdos-renyi: invalid `order`. Should be a positive number.'
	    );

	  if (typeof probability !== 'number' || probability < 0 || probability > 1)
	    throw new Error(
	      "graphology-generators/random/erdos-renyi: invalid `probability`. Should be a number between 0 and 1. Or maybe you gave an `approximateSize` exceeding the graph's density."
	    );

	  if (typeof rng !== 'function')
	    throw new Error(
	      'graphology-generators/random/erdos-renyi: invalid `rng`. Should be a function.'
	    );

	  for (var i = 0; i < order; i++) graph.addNode(i);

	  if (probability <= 0) return graph;

	  var w = -1,
	    lp = Math.log(1 - probability),
	    lr,
	    v;

	  if (graph.type !== 'undirected') {
	    v = 0;

	    while (v < order) {
	      lr = Math.log(1 - rng());
	      w += 1 + ((lr / lp) | 0);

	      // Avoiding self loops
	      if (v === w) {
	        w++;
	      }

	      while (v < order && order <= w) {
	        w -= order;
	        v++;

	        // Avoiding self loops
	        if (v === w) w++;
	      }

	      if (v < order) graph.addDirectedEdge(v, w);
	    }
	  }

	  w = -1;

	  if (graph.type !== 'directed') {
	    v = 1;

	    while (v < order) {
	      lr = Math.log(1 - rng());

	      w += 1 + ((lr / lp) | 0);

	      while (w >= v && v < order) {
	        w -= v;
	        v++;
	      }

	      if (v < order) graph.addUndirectedEdge(v, w);
	    }
	  }

	  return graph;
	}

	/**
	 * Exporting.
	 */
	erdosRenyi.sparse = erdosRenyiSparse;
	var erdosRenyi_1 = erdosRenyi;

	/**
	 * Graphology Girvan-Newman Graph Generator
	 * =========================================
	 *
	 * Function generating graphs liks the one used to test the Girvan-Newman
	 * community algorithm.
	 *
	 * [Reference]:
	 * http://www.pnas.org/content/99/12/7821.full.pdf
	 *
	 * [Article]:
	 * Community Structure in  social and biological networks.
	 * Girvan Newman, 2002. PNAS June, vol 99 n 12
	 */

	var isGraphConstructor$5 = isGraphConstructor$e;

	/**
	 * Generates a binomial graph graph with n nodes.
	 *
	 * @param  {Class}    GraphClass    - The Graph Class to instantiate.
	 * @param  {object}   options       - Options:
	 * @param  {number}     zOut        - zOut parameter.
	 * @param  {function}   rng         - Custom RNG function.
	 * @return {Graph}
	 */
	var girvanNewman = function girvanNewman(GraphClass, options) {
	  if (!isGraphConstructor$5(GraphClass))
	    throw new Error(
	      'graphology-generators/random/girvan-newman: invalid Graph constructor.'
	    );

	  var zOut = options.zOut,
	    rng = options.rng || Math.random;

	  if (typeof zOut !== 'number')
	    throw new Error(
	      'graphology-generators/random/girvan-newman: invalid `zOut`. Should be a number.'
	    );

	  if (typeof rng !== 'function')
	    throw new Error(
	      'graphology-generators/random/girvan-newman: invalid `rng`. Should be a function.'
	    );

	  var pOut = zOut / 96,
	    pIn = (16 - pOut * 96) / 31,
	    graph = new GraphClass(),
	    random,
	    i,
	    j;

	  for (i = 0; i < 128; i++) graph.addNode(i);

	  for (i = 0; i < 128; i++) {
	    for (j = i + 1; j < 128; j++) {
	      random = rng();

	      if (i % 4 === j % 4) {
	        if (random < pIn) graph.addEdge(i, j);
	      } else {
	        if (random < pOut) graph.addEdge(i, j);
	      }
	    }
	  }

	  return graph;
	};

	/**
	 * Graphology Random Graph Generators
	 * ===================================
	 *
	 * Random graph generators endpoint.
	 */

	random$2.clusters = clusters;
	random$2.erdosRenyi = erdosRenyi_1;
	random$2.girvanNewman = girvanNewman;

	var small = {};

	/**
	 * Graphology mergeStar
	 * =====================
	 *
	 * Function merging the given star to the graph.
	 */

	/**
	 * Merging the given star to the graph.
	 *
	 * @param  {Graph} graph - Target graph.
	 * @param  {array} nodes - Nodes to add, first one being the center of the star.
	 */
	var mergeStar$1 = function mergeStar(graph, nodes) {
	  if (nodes.length === 0) return;

	  var node, i, l;

	  var center = nodes[0];

	  graph.mergeNode(center);

	  for (i = 1, l = nodes.length; i < l; i++) {
	    node = nodes[i];

	    graph.mergeEdge(center, node);
	  }
	};

	/**
	 * Graphology Krackhardt Kite Graph Generator
	 * ===========================================
	 *
	 * Function generating the Krackhardt kite graph.
	 */

	var isGraphConstructor$4 = isGraphConstructor$e,
	  mergeStar = mergeStar$1;

	/**
	 * Data.
	 */
	var ADJACENCY = [
	  ['Andre', 'Beverley', 'Carol', 'Diane', 'Fernando'],
	  ['Beverley', 'Andre', 'Ed', 'Garth'],
	  ['Carol', 'Andre', 'Diane', 'Fernando'],
	  ['Diane', 'Andre', 'Beverley', 'Carol', 'Ed', 'Fernando', 'Garth'],
	  ['Ed', 'Beverley', 'Diane', 'Garth'],
	  ['Fernando', 'Andre', 'Carol', 'Diane', 'Garth', 'Heather'],
	  ['Garth', 'Beverley', 'Diane', 'Ed', 'Fernando', 'Heather'],
	  ['Heather', 'Fernando', 'Garth', 'Ike'],
	  ['Ike', 'Heather', 'Jane'],
	  ['Jane', 'Ike']
	];

	/**
	 * Function generating the Krackhardt kite graph.
	 *
	 * @param  {Class} GraphClass - The Graph Class to instantiate.
	 * @return {Graph}
	 */
	var krackhardtKite = function krackhardtKite(GraphClass) {
	  if (!isGraphConstructor$4(GraphClass))
	    throw new Error(
	      'graphology-generators/social/krackhardt-kite: invalid Graph constructor.'
	    );

	  var graph = new GraphClass(),
	    i,
	    l;

	  for (i = 0, l = ADJACENCY.length; i < l; i++) mergeStar(graph, ADJACENCY[i]);

	  return graph;
	};

	/**
	 * Graphology Small Graph Generators
	 * ==================================
	 *
	 * Small graph generators endpoint.
	 */

	small.krackhardtKite = krackhardtKite;

	var social = {};

	/**
	 * Graphology Florentine Families Graph Generator
	 * ===============================================
	 *
	 * Function generating the Florentine Families graph.
	 *
	 * [Reference]:
	 * Ronald L. Breiger and Philippa E. Pattison
	 * Cumulated social roles: The duality of persons and their algebras,1
	 * Social Networks, Volume 8, Issue 3, September 1986, Pages 215-256
	 */

	var isGraphConstructor$3 = isGraphConstructor$e;

	/**
	 * Data.
	 */
	var EDGES = [
	  ['Acciaiuoli', 'Medici'],
	  ['Castellani', 'Peruzzi'],
	  ['Castellani', 'Strozzi'],
	  ['Castellani', 'Barbadori'],
	  ['Medici', 'Barbadori'],
	  ['Medici', 'Ridolfi'],
	  ['Medici', 'Tornabuoni'],
	  ['Medici', 'Albizzi'],
	  ['Medici', 'Salviati'],
	  ['Salviati', 'Pazzi'],
	  ['Peruzzi', 'Strozzi'],
	  ['Peruzzi', 'Bischeri'],
	  ['Strozzi', 'Ridolfi'],
	  ['Strozzi', 'Bischeri'],
	  ['Ridolfi', 'Tornabuoni'],
	  ['Tornabuoni', 'Guadagni'],
	  ['Albizzi', 'Ginori'],
	  ['Albizzi', 'Guadagni'],
	  ['Bischeri', 'Guadagni'],
	  ['Guadagni', 'Lamberteschi']
	];

	/**
	 * Function generating the florentine families graph.
	 *
	 * @param  {Class} GraphClass - The Graph Class to instantiate.
	 * @return {Graph}
	 */
	var florentineFamilies = function florentineFamilies(GraphClass) {
	  if (!isGraphConstructor$3(GraphClass))
	    throw new Error(
	      'graphology-generators/social/florentine-families: invalid Graph constructor.'
	    );

	  var graph = new GraphClass(),
	    edge,
	    i,
	    l;

	  for (i = 0, l = EDGES.length; i < l; i++) {
	    edge = EDGES[i];

	    graph.mergeEdge(edge[0], edge[1]);
	  }

	  return graph;
	};

	/**
	 * Graphology Karate Graph Generator
	 * ==================================
	 *
	 * Function generating Zachary's karate club graph.
	 *
	 * [Reference]:
	 * Zachary, Wayne W.
	 * "An Information Flow Model for Conflict and Fission in Small Groups."
	 * Journal of Anthropological Research, 33, 452--473, (1977).
	 */

	var isGraphConstructor$2 = isGraphConstructor$e;

	/**
	 * Data.
	 */
	var DATA = [
	  '0111111110111100010101000000000100',
	  '1011000100000100010101000000001000',
	  '1101000111000100000000000001100010',
	  '1110000100001100000000000000000000',
	  '1000001000100000000000000000000000',
	  '1000001000100000100000000000000000',
	  '1000110000000000100000000000000000',
	  '1111000000000000000000000000000000',
	  '1010000000000000000000000000001011',
	  '0010000000000000000000000000000001',
	  '1000110000000000000000000000000000',
	  '1000000000000000000000000000000000',
	  '1001000000000000000000000000000000',
	  '1111000000000000000000000000000001',
	  '0000000000000000000000000000000011',
	  '0000000000000000000000000000000011',
	  '0000011000000000000000000000000000',
	  '1100000000000000000000000000000000',
	  '0000000000000000000000000000000011',
	  '1100000000000000000000000000000001',
	  '0000000000000000000000000000000011',
	  '1100000000000000000000000000000000',
	  '0000000000000000000000000000000011',
	  '0000000000000000000000000101010011',
	  '0000000000000000000000000101000100',
	  '0000000000000000000000011000000100',
	  '0000000000000000000000000000010001',
	  '0010000000000000000000011000000001',
	  '0010000000000000000000000000000101',
	  '0000000000000000000000010010000011',
	  '0100000010000000000000000000000011',
	  '1000000000000000000000001100100011',
	  '0010000010000011001010110000011101',
	  '0000000011000111001110110011111110'
	];

	var CLUB1 = new Set([
	  0, 1, 2, 3, 4, 5, 6, 7, 8, 10, 11, 12, 13, 16, 17, 19, 21
	]);

	/**
	 * Function generating the karate club graph.
	 *
	 * @param  {Class} GraphClass - The Graph Class to instantiate.
	 * @return {Graph}
	 */
	var karateClub = function karateClub(GraphClass) {
	  if (!isGraphConstructor$2(GraphClass))
	    throw new Error(
	      'graphology-generators/social/karate: invalid Graph constructor.'
	    );

	  var graph = new GraphClass(),
	    club;

	  for (var i = 0; i < 34; i++) {
	    club = CLUB1.has(i) ? 'Mr. Hi' : 'Officer';

	    graph.addNode(i, {club: club});
	  }

	  var line, entry, row, column, l, m;

	  for (row = 0, l = DATA.length; row < l; row++) {
	    line = DATA[row].split('');

	    for (column = row + 1, m = line.length; column < m; column++) {
	      entry = +line[column];

	      if (entry) graph.addEdgeWithKey(row + '->' + column, row, column);
	    }
	  }

	  return graph;
	};

	/**
	 * Graphology Social Graph Generators
	 * ===================================
	 *
	 * Social graph generators endpoint.
	 */

	social.florentineFamilies = florentineFamilies;
	social.karateClub = karateClub;

	/**
	 * Graphology Graph Generators
	 * ============================
	 *
	 * Library endpoint.
	 */

	generators$2.classic = classic;
	generators$2.community = community;
	generators$2.random = random$2;
	generators$2.small = small;
	generators$2.social = social;

	var generators$1 = generators$2;

	var layout$2 = {};

	/**
	 * Pandemonium Random
	 * ===================
	 *
	 * Random function.
	 */

	/**
	 * Creating a function returning a random integer such as a <= N <= b.
	 *
	 * @param  {function} rng - RNG function returning uniform random.
	 * @return {function}     - The created function.
	 */
	function createRandom$1(rng) {
	  /**
	   * Random function.
	   *
	   * @param  {number} a - From.
	   * @param  {number} b - To.
	   * @return {number}
	   */
	  return function (a, b) {
	    return a + Math.floor(rng() * (b - a + 1));
	  };
	}

	/**
	 * Default random using `Math.random`.
	 */
	var random$1 = createRandom$1(Math.random);

	/**
	 * Exporting.
	 */
	random$1.createRandom = createRandom$1;
	var random_1 = random$1;

	/**
	 * Pandemonium Shuffle In Place
	 * =============================
	 *
	 * Shuffle function applying the Fisher-Yates algorithm to the provided array.
	 */

	var createRandom = random_1.createRandom;

	/**
	 * Creating a function returning the given array shuffled.
	 *
	 * @param  {function} rng - The RNG to use.
	 * @return {function}     - The created function.
	 */
	function createShuffleInPlace(rng) {
	  var customRandom = createRandom(rng);

	  /**
	   * Function returning the shuffled array.
	   *
	   * @param  {array}  sequence - Target sequence.
	   * @return {array}           - The shuffled sequence.
	   */
	  return function (sequence) {
	    var length = sequence.length,
	      lastIndex = length - 1;

	    var index = -1;

	    while (++index < length) {
	      var r = customRandom(index, lastIndex),
	        value = sequence[r];

	      sequence[r] = sequence[index];
	      sequence[index] = value;
	    }
	  };
	}

	/**
	 * Default shuffle in place using `Math.random`.
	 */
	var shuffleInPlace = createShuffleInPlace(Math.random);

	/**
	 * Exporting.
	 */
	shuffleInPlace.createShuffleInPlace = createShuffleInPlace;
	var shuffleInPlace_1 = shuffleInPlace;

	/**
	 * Graphology CirclePack Layout
	 * =============================
	 *
	 * Circlepack layout from d3-hierarchy/gephi.
	 */

	var resolveDefaults$b = defaults$7;
	var isGraph$G = isGraph$N;
	var shuffle = shuffleInPlace_1;

	/**
	 * Default options.
	 */
	var DEFAULTS$d = {
	  attributes: {
	    x: 'x',
	    y: 'y'
	  },
	  center: 0,
	  hierarchyAttributes: [],
	  rng: Math.random,
	  scale: 1
	};

	/**
	 * Helpers.
	 */
	function CircleWrap(id, x, y, r, circleWrap) {
	  this.wrappedCircle = circleWrap || null; //hacky d3 reference thing

	  this.children = {};
	  this.countChildren = 0;
	  this.id = id || null;
	  this.next = null;
	  this.previous = null;

	  this.x = x || null;
	  this.y = y || null;
	  if (circleWrap) this.r = 1010101;
	  // for debugging purposes - should not be used in this case
	  else this.r = r || 999;
	}

	CircleWrap.prototype.hasChildren = function () {
	  return this.countChildren > 0;
	};

	CircleWrap.prototype.addChild = function (id, child) {
	  this.children[id] = child;
	  ++this.countChildren;
	};

	CircleWrap.prototype.getChild = function (id) {
	  if (!this.children.hasOwnProperty(id)) {
	    var circleWrap = new CircleWrap();
	    this.children[id] = circleWrap;
	    ++this.countChildren;
	  }
	  return this.children[id];
	};

	CircleWrap.prototype.applyPositionToChildren = function () {
	  if (this.hasChildren()) {
	    var root = this; // using 'this' in Object.keys.forEach seems a bad idea
	    for (var key in root.children) {
	      var child = root.children[key];
	      child.x += root.x;
	      child.y += root.y;
	      child.applyPositionToChildren();
	    }
	  }
	};

	function setNode(/*Graph*/ graph, /*CircleWrap*/ parentCircle, /*Map*/ posMap) {
	  for (var key in parentCircle.children) {
	    var circle = parentCircle.children[key];
	    if (circle.hasChildren()) {
	      setNode(graph, circle, posMap);
	    } else {
	      posMap[circle.id] = {x: circle.x, y: circle.y};
	    }
	  }
	}

	function enclosesNot(/*CircleWrap*/ a, /*CircleWrap*/ b) {
	  var dr = a.r - b.r;
	  var dx = b.x - a.x;
	  var dy = b.y - a.y;
	  return dr < 0 || dr * dr < dx * dx + dy * dy;
	}

	function enclosesWeak(/*CircleWrap*/ a, /*CircleWrap*/ b) {
	  var dr = a.r - b.r + 1e-6;
	  var dx = b.x - a.x;
	  var dy = b.y - a.y;
	  return dr > 0 && dr * dr > dx * dx + dy * dy;
	}

	function enclosesWeakAll(/*CircleWrap*/ a, /*Array<CircleWrap>*/ B) {
	  for (var i = 0; i < B.length; ++i) {
	    if (!enclosesWeak(a, B[i])) {
	      return false;
	    }
	  }
	  return true;
	}

	function encloseBasis1(/*CircleWrap*/ a) {
	  return new CircleWrap(null, a.x, a.y, a.r);
	}

	function encloseBasis2(/*CircleWrap*/ a, /*CircleWrap*/ b) {
	  var x1 = a.x,
	    y1 = a.y,
	    r1 = a.r,
	    x2 = b.x,
	    y2 = b.y,
	    r2 = b.r,
	    x21 = x2 - x1,
	    y21 = y2 - y1,
	    r21 = r2 - r1,
	    l = Math.sqrt(x21 * x21 + y21 * y21);
	  return new CircleWrap(
	    null,
	    (x1 + x2 + (x21 / l) * r21) / 2,
	    (y1 + y2 + (y21 / l) * r21) / 2,
	    (l + r1 + r2) / 2
	  );
	}

	function encloseBasis3(/*CircleWrap*/ a, /*CircleWrap*/ b, /*CircleWrap*/ c) {
	  var x1 = a.x,
	    y1 = a.y,
	    r1 = a.r,
	    x2 = b.x,
	    y2 = b.y,
	    r2 = b.r,
	    x3 = c.x,
	    y3 = c.y,
	    r3 = c.r,
	    a2 = x1 - x2,
	    a3 = x1 - x3,
	    b2 = y1 - y2,
	    b3 = y1 - y3,
	    c2 = r2 - r1,
	    c3 = r3 - r1,
	    d1 = x1 * x1 + y1 * y1 - r1 * r1,
	    d2 = d1 - x2 * x2 - y2 * y2 + r2 * r2,
	    d3 = d1 - x3 * x3 - y3 * y3 + r3 * r3,
	    ab = a3 * b2 - a2 * b3,
	    xa = (b2 * d3 - b3 * d2) / (ab * 2) - x1,
	    xb = (b3 * c2 - b2 * c3) / ab,
	    ya = (a3 * d2 - a2 * d3) / (ab * 2) - y1,
	    yb = (a2 * c3 - a3 * c2) / ab,
	    A = xb * xb + yb * yb - 1,
	    B = 2 * (r1 + xa * xb + ya * yb),
	    C = xa * xa + ya * ya - r1 * r1,
	    r = -(A ? (B + Math.sqrt(B * B - 4 * A * C)) / (2 * A) : C / B);
	  return new CircleWrap(null, x1 + xa + xb * r, y1 + ya + yb * r, r);
	}

	function encloseBasis(/*Array<CircleWrap>*/ B) {
	  switch (B.length) {
	    case 1:
	      return encloseBasis1(B[0]);
	    case 2:
	      return encloseBasis2(B[0], B[1]);
	    case 3:
	      return encloseBasis3(B[0], B[1], B[2]);
	    default:
	      throw new Error(
	        'graphology-layout/circlepack: Invalid basis length ' + B.length
	      );
	  }
	}

	function extendBasis(/*Array<CircleWrap>*/ B, /*CircleWrap*/ p) {
	  var i, j;

	  if (enclosesWeakAll(p, B)) return [p];

	  // If we get here then B must have at least one element.
	  for (i = 0; i < B.length; ++i) {
	    if (enclosesNot(p, B[i]) && enclosesWeakAll(encloseBasis2(B[i], p), B)) {
	      return [B[i], p];
	    }
	  }

	  // If we get here then B must have at least two elements.
	  for (i = 0; i < B.length - 1; ++i) {
	    for (j = i + 1; j < B.length; ++j) {
	      if (
	        enclosesNot(encloseBasis2(B[i], B[j]), p) &&
	        enclosesNot(encloseBasis2(B[i], p), B[j]) &&
	        enclosesNot(encloseBasis2(B[j], p), B[i]) &&
	        enclosesWeakAll(encloseBasis3(B[i], B[j], p), B)
	      ) {
	        return [B[i], B[j], p];
	      }
	    }
	  }

	  // If we get here then something is very wrong.
	  throw new Error('graphology-layout/circlepack: extendBasis failure !');
	}

	function score(/*CircleWrap*/ node) {
	  var a = node.wrappedCircle;
	  var b = node.next.wrappedCircle;
	  var ab = a.r + b.r;
	  var dx = (a.x * b.r + b.x * a.r) / ab;
	  var dy = (a.y * b.r + b.y * a.r) / ab;
	  return dx * dx + dy * dy;
	}

	function enclose(circles, shuffleFunc) {
	  var i = 0;
	  var circlesLoc = circles.slice();

	  var n = circles.length;
	  var B = [];
	  var p;
	  var e;
	  shuffleFunc(circlesLoc);
	  while (i < n) {
	    p = circlesLoc[i];
	    if (e && enclosesWeak(e, p)) {
	      ++i;
	    } else {
	      B = extendBasis(B, p);
	      e = encloseBasis(B);
	      i = 0;
	    }
	  }
	  return e;
	}

	function place(/*CircleWrap*/ b, /*CircleWrap*/ a, /*CircleWrap*/ c) {
	  var dx = b.x - a.x,
	    x,
	    a2,
	    dy = b.y - a.y,
	    y,
	    b2,
	    d2 = dx * dx + dy * dy;
	  if (d2) {
	    a2 = a.r + c.r;
	    a2 *= a2;
	    b2 = b.r + c.r;
	    b2 *= b2;
	    if (a2 > b2) {
	      x = (d2 + b2 - a2) / (2 * d2);
	      y = Math.sqrt(Math.max(0, b2 / d2 - x * x));
	      c.x = b.x - x * dx - y * dy;
	      c.y = b.y - x * dy + y * dx;
	    } else {
	      x = (d2 + a2 - b2) / (2 * d2);
	      y = Math.sqrt(Math.max(0, a2 / d2 - x * x));
	      c.x = a.x + x * dx - y * dy;
	      c.y = a.y + x * dy + y * dx;
	    }
	  } else {
	    c.x = a.x + c.r;
	    c.y = a.y;
	  }
	}

	function intersects(/*CircleWrap*/ a, /*CircleWrap*/ b) {
	  var dr = a.r + b.r - 1e-6,
	    dx = b.x - a.x,
	    dy = b.y - a.y;
	  return dr > 0 && dr * dr > dx * dx + dy * dy;
	}

	function packEnclose(/*Array<CircleWrap>*/ circles, shuffleFunc) {
	  var n = circles.length;
	  if (n === 0) return 0;

	  var a, b, c, aa, ca, i, j, k, sj, sk;

	  // Place the first circle.
	  a = circles[0];
	  a.x = 0;
	  a.y = 0;
	  if (n <= 1) return a.r;

	  // Place the second circle.
	  b = circles[1];
	  a.x = -b.r;
	  b.x = a.r;
	  b.y = 0;
	  if (n <= 2) return a.r + b.r;

	  // Place the third circle.
	  c = circles[2];
	  place(b, a, c);

	  // Initialize the front-chain using the first three circles a, b and c.
	  a = new CircleWrap(null, null, null, null, a);
	  b = new CircleWrap(null, null, null, null, b);
	  c = new CircleWrap(null, null, null, null, c);
	  a.next = c.previous = b;
	  b.next = a.previous = c;
	  c.next = b.previous = a;

	  // Attempt to place each remaining circle…
	  pack: for (i = 3; i < n; ++i) {
	    c = circles[i];
	    place(a.wrappedCircle, b.wrappedCircle, c);
	    c = new CircleWrap(null, null, null, null, c);

	    // Find the closest intersecting circle on the front-chain, if any.
	    // “Closeness” is determined by linear distance along the front-chain.
	    // “Ahead” or “behind” is likewise determined by linear distance.
	    j = b.next;
	    k = a.previous;
	    sj = b.wrappedCircle.r;
	    sk = a.wrappedCircle.r;
	    do {
	      if (sj <= sk) {
	        if (intersects(j.wrappedCircle, c.wrappedCircle)) {
	          b = j;
	          a.next = b;
	          b.previous = a;
	          --i;
	          continue pack;
	        }
	        sj += j.wrappedCircle.r;
	        j = j.next;
	      } else {
	        if (intersects(k.wrappedCircle, c.wrappedCircle)) {
	          a = k;
	          a.next = b;
	          b.previous = a;
	          --i;
	          continue pack;
	        }
	        sk += k.wrappedCircle.r;
	        k = k.previous;
	      }
	    } while (j !== k.next);

	    // Success! Insert the new circle c between a and b.
	    c.previous = a;
	    c.next = b;
	    a.next = b.previous = b = c;

	    // Compute the new closest circle pair to the centroid.
	    aa = score(a);
	    while ((c = c.next) !== b) {
	      if ((ca = score(c)) < aa) {
	        a = c;
	        aa = ca;
	      }
	    }
	    b = a.next;
	  }

	  // Compute the enclosing circle of the front chain.
	  a = [b.wrappedCircle];
	  c = b;
	  var safety = 10000;
	  while ((c = c.next) !== b) {
	    if (--safety === 0) {
	      break;
	    }
	    a.push(c.wrappedCircle);
	  }
	  c = enclose(a, shuffleFunc);

	  // Translate the circles to put the enclosing circle around the origin.
	  for (i = 0; i < n; ++i) {
	    a = circles[i];
	    a.x -= c.x;
	    a.y -= c.y;
	  }
	  return c.r;
	}

	function packHierarchy(/*CircleWrap*/ parentCircle, shuffleFunc) {
	  var r = 0;
	  if (parentCircle.hasChildren()) {
	    //pack the children first because the radius is determined by how the children get packed (recursive)
	    for (var key in parentCircle.children) {
	      var circle = parentCircle.children[key];
	      if (circle.hasChildren()) {
	        circle.r = packHierarchy(circle, shuffleFunc);
	      }
	    }
	    //now that each circle has a radius set by its children, pack the circles at this level
	    r = packEnclose(Object.values(parentCircle.children), shuffleFunc);
	  }
	  return r;
	}

	function packHierarchyAndShift(/*CircleWrap*/ parentCircle, shuffleFunc) {
	  packHierarchy(parentCircle, shuffleFunc);
	  for (var key in parentCircle.children) {
	    var circle = parentCircle.children[key];
	    circle.applyPositionToChildren();
	  }
	}

	/**
	 * Abstract function running the layout.
	 *
	 * @param  {Graph}    graph                   - Target  graph.
	 * @param  {object}   [options]               - Options:
	 * @param  {object}     [attributes]          - Attributes names to map.
	 * @param  {number}     [center]              - Center of the layout.
	 * @param  {string[]}   [hierarchyAttributes] - List of attributes used for the layout in decreasing order.
	 * @param  {function}   [rng]                 - Custom RNG function to be used.
	 * @param  {number}     [scale]               - Scale of the layout.
	 * @return {object}                           - The positions by node.
	 */
	function genericCirclePackLayout(assign, graph, options) {
	  if (!isGraph$G(graph))
	    throw new Error(
	      'graphology-layout/circlepack: the given graph is not a valid graphology instance.'
	    );

	  options = resolveDefaults$b(options, DEFAULTS$d);

	  var posMap = {},
	    positions = {},
	    nodes = graph.nodes(),
	    center = options.center,
	    hierarchyAttributes = options.hierarchyAttributes,
	    shuffleFunc = shuffle.createShuffleInPlace(options.rng),
	    scale = options.scale;

	  var container = new CircleWrap();

	  graph.forEachNode(function (key, attributes) {
	    var r = attributes.size ? attributes.size : 1;
	    var newCircleWrap = new CircleWrap(key, null, null, r);
	    var parentContainer = container;

	    hierarchyAttributes.forEach(function (v) {
	      var attr = attributes[v];
	      parentContainer = parentContainer.getChild(attr);
	    });

	    parentContainer.addChild(key, newCircleWrap);
	  });
	  packHierarchyAndShift(container, shuffleFunc);
	  setNode(graph, container, posMap);
	  var l = nodes.length,
	    x,
	    y,
	    i;
	  for (i = 0; i < l; i++) {
	    var node = nodes[i];

	    x = center + scale * posMap[node].x;
	    y = center + scale * posMap[node].y;

	    positions[node] = {
	      x: x,
	      y: y
	    };

	    if (assign) {
	      graph.setNodeAttribute(node, options.attributes.x, x);
	      graph.setNodeAttribute(node, options.attributes.y, y);
	    }
	  }
	  return positions;
	}

	var circlePackLayout = genericCirclePackLayout.bind(null, false);
	circlePackLayout.assign = genericCirclePackLayout.bind(null, true);

	var circlepack = circlePackLayout;

	/**
	 * Graphology Circular Layout
	 * ===========================
	 *
	 * Layout arranging the nodes in a circle.
	 */

	var resolveDefaults$a = defaults$7;
	var isGraph$F = isGraph$N;

	/**
	 * Default options.
	 */
	var DEFAULTS$c = {
	  dimensions: ['x', 'y'],
	  center: 0.5,
	  scale: 1
	};

	/**
	 * Abstract function running the layout.
	 *
	 * @param  {Graph}    graph          - Target  graph.
	 * @param  {object}   [options]      - Options:
	 * @param  {object}     [attributes] - Attributes names to map.
	 * @param  {number}     [center]     - Center of the layout.
	 * @param  {number}     [scale]      - Scale of the layout.
	 * @return {object}                  - The positions by node.
	 */
	function genericCircularLayout(assign, graph, options) {
	  if (!isGraph$F(graph))
	    throw new Error(
	      'graphology-layout/random: the given graph is not a valid graphology instance.'
	    );

	  options = resolveDefaults$a(options, DEFAULTS$c);

	  var dimensions = options.dimensions;

	  if (!Array.isArray(dimensions) || dimensions.length !== 2)
	    throw new Error('graphology-layout/random: given dimensions are invalid.');

	  var center = options.center;
	  var scale = options.scale;
	  var tau = Math.PI * 2;

	  var offset = (center - 0.5) * scale;
	  var l = graph.order;

	  var x = dimensions[0];
	  var y = dimensions[1];

	  function assignPosition(i, target) {
	    target[x] = scale * Math.cos((i * tau) / l) + offset;
	    target[y] = scale * Math.sin((i * tau) / l) + offset;

	    return target;
	  }

	  var i = 0;

	  if (!assign) {
	    var positions = {};

	    graph.forEachNode(function (node) {
	      positions[node] = assignPosition(i++, {});
	    });

	    return positions;
	  }

	  graph.updateEachNodeAttributes(
	    function (_, attr) {
	      assignPosition(i++, attr);
	      return attr;
	    },
	    {
	      attributes: dimensions
	    }
	  );
	}

	var circularLayout = genericCircularLayout.bind(null, false);
	circularLayout.assign = genericCircularLayout.bind(null, true);

	var circular = circularLayout;

	/**
	 * Graphology Random Layout
	 * =========================
	 *
	 * Simple layout giving uniform random positions to the nodes.
	 */

	var resolveDefaults$9 = defaults$7;
	var isGraph$E = isGraph$N;

	/**
	 * Default options.
	 */
	var DEFAULTS$b = {
	  dimensions: ['x', 'y'],
	  center: 0.5,
	  rng: Math.random,
	  scale: 1
	};

	/**
	 * Abstract function running the layout.
	 *
	 * @param  {Graph}    graph          - Target  graph.
	 * @param  {object}   [options]      - Options:
	 * @param  {array}      [dimensions] - List of dimensions of the layout.
	 * @param  {number}     [center]     - Center of the layout.
	 * @param  {function}   [rng]        - Custom RNG function to be used.
	 * @param  {number}     [scale]      - Scale of the layout.
	 * @return {object}                  - The positions by node.
	 */
	function genericRandomLayout(assign, graph, options) {
	  if (!isGraph$E(graph))
	    throw new Error(
	      'graphology-layout/random: the given graph is not a valid graphology instance.'
	    );

	  options = resolveDefaults$9(options, DEFAULTS$b);

	  var dimensions = options.dimensions;

	  if (!Array.isArray(dimensions) || dimensions.length < 1)
	    throw new Error('graphology-layout/random: given dimensions are invalid.');

	  var d = dimensions.length;
	  var center = options.center;
	  var rng = options.rng;
	  var scale = options.scale;

	  var offset = (center - 0.5) * scale;

	  function assignPosition(target) {
	    for (var i = 0; i < d; i++) {
	      target[dimensions[i]] = rng() * scale + offset;
	    }

	    return target;
	  }

	  if (!assign) {
	    var positions = {};

	    graph.forEachNode(function (node) {
	      positions[node] = assignPosition({});
	    });

	    return positions;
	  }

	  graph.updateEachNodeAttributes(
	    function (_, attr) {
	      assignPosition(attr);
	      return attr;
	    },
	    {
	      attributes: dimensions
	    }
	  );
	}

	var randomLayout = genericRandomLayout.bind(null, false);
	randomLayout.assign = genericRandomLayout.bind(null, true);

	var random = randomLayout;

	/**
	 * Graphology Rotation Layout Helper
	 * ==================================
	 *
	 * Function rotating the coordinates of the graph.
	 */

	var resolveDefaults$8 = defaults$7;
	var isGraph$D = isGraph$N;

	/**
	 * Constants.
	 */
	var RAD_CONVERSION = Math.PI / 180;

	/**
	 * Default options.
	 */
	var DEFAULTS$a = {
	  dimensions: ['x', 'y'],
	  centeredOnZero: false,
	  degrees: false
	};

	/**
	 * Abstract function for rotating a graph's coordinates.
	 *
	 * @param  {Graph}    graph          - Target  graph.
	 * @param  {number}   angle          - Rotation angle.
	 * @param  {object}   [options]      - Options.
	 * @return {object}                  - The positions by node.
	 */
	function genericRotation(assign, graph, angle, options) {
	  if (!isGraph$D(graph))
	    throw new Error(
	      'graphology-layout/rotation: the given graph is not a valid graphology instance.'
	    );

	  options = resolveDefaults$8(options, DEFAULTS$a);

	  if (options.degrees) angle *= RAD_CONVERSION;

	  var dimensions = options.dimensions;

	  if (!Array.isArray(dimensions) || dimensions.length !== 2)
	    throw new Error('graphology-layout/random: given dimensions are invalid.');

	  // Handling null graph
	  if (graph.order === 0) {
	    if (assign) return;

	    return {};
	  }

	  var xd = dimensions[0];
	  var yd = dimensions[1];

	  var xCenter = 0;
	  var yCenter = 0;

	  if (!options.centeredOnZero) {
	    // Finding bounds of the graph
	    var xMin = Infinity;
	    var xMax = -Infinity;
	    var yMin = Infinity;
	    var yMax = -Infinity;

	    graph.forEachNode(function (node, attr) {
	      var x = attr[xd];
	      var y = attr[yd];

	      if (x < xMin) xMin = x;
	      if (x > xMax) xMax = x;
	      if (y < yMin) yMin = y;
	      if (y > yMax) yMax = y;
	    });

	    xCenter = (xMin + xMax) / 2;
	    yCenter = (yMin + yMax) / 2;
	  }

	  var cos = Math.cos(angle);
	  var sin = Math.sin(angle);

	  function assignPosition(target) {
	    var x = target[xd];
	    var y = target[yd];

	    target[xd] = xCenter + (x - xCenter) * cos - (y - yCenter) * sin;
	    target[yd] = yCenter + (x - xCenter) * sin + (y - yCenter) * cos;

	    return target;
	  }

	  if (!assign) {
	    var positions = {};

	    graph.forEachNode(function (node, attr) {
	      var o = {};
	      o[xd] = attr[xd];
	      o[yd] = attr[yd];
	      positions[node] = assignPosition(o);
	    });

	    return positions;
	  }

	  graph.updateEachNodeAttributes(
	    function (_, attr) {
	      assignPosition(attr);
	      return attr;
	    },
	    {
	      attributes: dimensions
	    }
	  );
	}

	var rotation = genericRotation.bind(null, false);
	rotation.assign = genericRotation.bind(null, true);

	var rotation_1 = rotation;

	/**
	 * Graphology Layout
	 * ==================
	 *
	 * Library endpoint.
	 */

	layout$2.circlepack = circlepack;
	layout$2.circular = circular;
	layout$2.random = random;
	layout$2.rotation = rotation_1;

	var layout$1 = layout$2;

	var layoutForce$1 = layout$2;

	/* eslint no-constant-condition: 0 */

	/**
	 * Graphology ForceAtlas2 Iteration
	 * =================================
	 *
	 * Function used to perform a single iteration of the algorithm.
	 */

	/**
	 * Matrices properties accessors.
	 */
	var NODE_X$1 = 0;
	var NODE_Y$1 = 1;
	var NODE_DX = 2;
	var NODE_DY = 3;
	var NODE_OLD_DX = 4;
	var NODE_OLD_DY = 5;
	var NODE_MASS = 6;
	var NODE_CONVERGENCE = 7;
	var NODE_SIZE$1 = 8;
	var NODE_FIXED = 9;

	var EDGE_SOURCE = 0;
	var EDGE_TARGET = 1;
	var EDGE_WEIGHT = 2;

	var REGION_NODE = 0;
	var REGION_CENTER_X = 1;
	var REGION_CENTER_Y = 2;
	var REGION_SIZE = 3;
	var REGION_NEXT_SIBLING = 4;
	var REGION_FIRST_CHILD = 5;
	var REGION_MASS = 6;
	var REGION_MASS_CENTER_X = 7;
	var REGION_MASS_CENTER_Y = 8;

	var SUBDIVISION_ATTEMPTS = 3;

	/**
	 * Constants.
	 */
	var PPN$3 = 10;
	var PPE$1 = 3;
	var PPR = 9;

	var MAX_FORCE = 10;

	/**
	 * Function used to perform a single interation of the algorithm.
	 *
	 * @param  {object}       options    - Layout options.
	 * @param  {Float32Array} NodeMatrix - Node data.
	 * @param  {Float32Array} EdgeMatrix - Edge data.
	 * @return {object}                  - Some metadata.
	 */
	var iterate$5 = function iterate(options, NodeMatrix, EdgeMatrix) {
	  // Initializing variables
	  var l, r, n, n1, n2, rn, e, w, g, s;

	  var order = NodeMatrix.length,
	    size = EdgeMatrix.length;

	  var adjustSizes = options.adjustSizes;

	  var thetaSquared = options.barnesHutTheta * options.barnesHutTheta;

	  var outboundAttCompensation, coefficient, xDist, yDist, ewc, distance, factor;

	  var RegionMatrix = [];

	  // 1) Initializing layout data
	  //-----------------------------

	  // Resetting positions & computing max values
	  for (n = 0; n < order; n += PPN$3) {
	    NodeMatrix[n + NODE_OLD_DX] = NodeMatrix[n + NODE_DX];
	    NodeMatrix[n + NODE_OLD_DY] = NodeMatrix[n + NODE_DY];
	    NodeMatrix[n + NODE_DX] = 0;
	    NodeMatrix[n + NODE_DY] = 0;
	  }

	  // If outbound attraction distribution, compensate
	  if (options.outboundAttractionDistribution) {
	    outboundAttCompensation = 0;
	    for (n = 0; n < order; n += PPN$3) {
	      outboundAttCompensation += NodeMatrix[n + NODE_MASS];
	    }

	    outboundAttCompensation /= order / PPN$3;
	  }

	  // 1.bis) Barnes-Hut computation
	  //------------------------------

	  if (options.barnesHutOptimize) {
	    // Setting up
	    var minX = Infinity,
	      maxX = -Infinity,
	      minY = Infinity,
	      maxY = -Infinity,
	      q,
	      q2,
	      subdivisionAttempts;

	    // Computing min and max values
	    for (n = 0; n < order; n += PPN$3) {
	      minX = Math.min(minX, NodeMatrix[n + NODE_X$1]);
	      maxX = Math.max(maxX, NodeMatrix[n + NODE_X$1]);
	      minY = Math.min(minY, NodeMatrix[n + NODE_Y$1]);
	      maxY = Math.max(maxY, NodeMatrix[n + NODE_Y$1]);
	    }

	    // squarify bounds, it's a quadtree
	    var dx = maxX - minX,
	      dy = maxY - minY;
	    if (dx > dy) {
	      minY -= (dx - dy) / 2;
	      maxY = minY + dx;
	    } else {
	      minX -= (dy - dx) / 2;
	      maxX = minX + dy;
	    }

	    // Build the Barnes Hut root region
	    RegionMatrix[0 + REGION_NODE] = -1;
	    RegionMatrix[0 + REGION_CENTER_X] = (minX + maxX) / 2;
	    RegionMatrix[0 + REGION_CENTER_Y] = (minY + maxY) / 2;
	    RegionMatrix[0 + REGION_SIZE] = Math.max(maxX - minX, maxY - minY);
	    RegionMatrix[0 + REGION_NEXT_SIBLING] = -1;
	    RegionMatrix[0 + REGION_FIRST_CHILD] = -1;
	    RegionMatrix[0 + REGION_MASS] = 0;
	    RegionMatrix[0 + REGION_MASS_CENTER_X] = 0;
	    RegionMatrix[0 + REGION_MASS_CENTER_Y] = 0;

	    // Add each node in the tree
	    l = 1;
	    for (n = 0; n < order; n += PPN$3) {
	      // Current region, starting with root
	      r = 0;
	      subdivisionAttempts = SUBDIVISION_ATTEMPTS;

	      while (true) {
	        // Are there sub-regions?

	        // We look at first child index
	        if (RegionMatrix[r + REGION_FIRST_CHILD] >= 0) {
	          // There are sub-regions

	          // We just iterate to find a "leaf" of the tree
	          // that is an empty region or a region with a single node
	          // (see next case)

	          // Find the quadrant of n
	          if (NodeMatrix[n + NODE_X$1] < RegionMatrix[r + REGION_CENTER_X]) {
	            if (NodeMatrix[n + NODE_Y$1] < RegionMatrix[r + REGION_CENTER_Y]) {
	              // Top Left quarter
	              q = RegionMatrix[r + REGION_FIRST_CHILD];
	            } else {
	              // Bottom Left quarter
	              q = RegionMatrix[r + REGION_FIRST_CHILD] + PPR;
	            }
	          } else {
	            if (NodeMatrix[n + NODE_Y$1] < RegionMatrix[r + REGION_CENTER_Y]) {
	              // Top Right quarter
	              q = RegionMatrix[r + REGION_FIRST_CHILD] + PPR * 2;
	            } else {
	              // Bottom Right quarter
	              q = RegionMatrix[r + REGION_FIRST_CHILD] + PPR * 3;
	            }
	          }

	          // Update center of mass and mass (we only do it for non-leave regions)
	          RegionMatrix[r + REGION_MASS_CENTER_X] =
	            (RegionMatrix[r + REGION_MASS_CENTER_X] *
	              RegionMatrix[r + REGION_MASS] +
	              NodeMatrix[n + NODE_X$1] * NodeMatrix[n + NODE_MASS]) /
	            (RegionMatrix[r + REGION_MASS] + NodeMatrix[n + NODE_MASS]);

	          RegionMatrix[r + REGION_MASS_CENTER_Y] =
	            (RegionMatrix[r + REGION_MASS_CENTER_Y] *
	              RegionMatrix[r + REGION_MASS] +
	              NodeMatrix[n + NODE_Y$1] * NodeMatrix[n + NODE_MASS]) /
	            (RegionMatrix[r + REGION_MASS] + NodeMatrix[n + NODE_MASS]);

	          RegionMatrix[r + REGION_MASS] += NodeMatrix[n + NODE_MASS];

	          // Iterate on the right quadrant
	          r = q;
	          continue;
	        } else {
	          // There are no sub-regions: we are in a "leaf"

	          // Is there a node in this leave?
	          if (RegionMatrix[r + REGION_NODE] < 0) {
	            // There is no node in region:
	            // we record node n and go on
	            RegionMatrix[r + REGION_NODE] = n;
	            break;
	          } else {
	            // There is a node in this region

	            // We will need to create sub-regions, stick the two
	            // nodes (the old one r[0] and the new one n) in two
	            // subregions. If they fall in the same quadrant,
	            // we will iterate.

	            // Create sub-regions
	            RegionMatrix[r + REGION_FIRST_CHILD] = l * PPR;
	            w = RegionMatrix[r + REGION_SIZE] / 2; // new size (half)

	            // NOTE: we use screen coordinates
	            // from Top Left to Bottom Right

	            // Top Left sub-region
	            g = RegionMatrix[r + REGION_FIRST_CHILD];

	            RegionMatrix[g + REGION_NODE] = -1;
	            RegionMatrix[g + REGION_CENTER_X] =
	              RegionMatrix[r + REGION_CENTER_X] - w;
	            RegionMatrix[g + REGION_CENTER_Y] =
	              RegionMatrix[r + REGION_CENTER_Y] - w;
	            RegionMatrix[g + REGION_SIZE] = w;
	            RegionMatrix[g + REGION_NEXT_SIBLING] = g + PPR;
	            RegionMatrix[g + REGION_FIRST_CHILD] = -1;
	            RegionMatrix[g + REGION_MASS] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_X] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_Y] = 0;

	            // Bottom Left sub-region
	            g += PPR;
	            RegionMatrix[g + REGION_NODE] = -1;
	            RegionMatrix[g + REGION_CENTER_X] =
	              RegionMatrix[r + REGION_CENTER_X] - w;
	            RegionMatrix[g + REGION_CENTER_Y] =
	              RegionMatrix[r + REGION_CENTER_Y] + w;
	            RegionMatrix[g + REGION_SIZE] = w;
	            RegionMatrix[g + REGION_NEXT_SIBLING] = g + PPR;
	            RegionMatrix[g + REGION_FIRST_CHILD] = -1;
	            RegionMatrix[g + REGION_MASS] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_X] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_Y] = 0;

	            // Top Right sub-region
	            g += PPR;
	            RegionMatrix[g + REGION_NODE] = -1;
	            RegionMatrix[g + REGION_CENTER_X] =
	              RegionMatrix[r + REGION_CENTER_X] + w;
	            RegionMatrix[g + REGION_CENTER_Y] =
	              RegionMatrix[r + REGION_CENTER_Y] - w;
	            RegionMatrix[g + REGION_SIZE] = w;
	            RegionMatrix[g + REGION_NEXT_SIBLING] = g + PPR;
	            RegionMatrix[g + REGION_FIRST_CHILD] = -1;
	            RegionMatrix[g + REGION_MASS] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_X] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_Y] = 0;

	            // Bottom Right sub-region
	            g += PPR;
	            RegionMatrix[g + REGION_NODE] = -1;
	            RegionMatrix[g + REGION_CENTER_X] =
	              RegionMatrix[r + REGION_CENTER_X] + w;
	            RegionMatrix[g + REGION_CENTER_Y] =
	              RegionMatrix[r + REGION_CENTER_Y] + w;
	            RegionMatrix[g + REGION_SIZE] = w;
	            RegionMatrix[g + REGION_NEXT_SIBLING] =
	              RegionMatrix[r + REGION_NEXT_SIBLING];
	            RegionMatrix[g + REGION_FIRST_CHILD] = -1;
	            RegionMatrix[g + REGION_MASS] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_X] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_Y] = 0;

	            l += 4;

	            // Now the goal is to find two different sub-regions
	            // for the two nodes: the one previously recorded (r[0])
	            // and the one we want to add (n)

	            // Find the quadrant of the old node
	            if (
	              NodeMatrix[RegionMatrix[r + REGION_NODE] + NODE_X$1] <
	              RegionMatrix[r + REGION_CENTER_X]
	            ) {
	              if (
	                NodeMatrix[RegionMatrix[r + REGION_NODE] + NODE_Y$1] <
	                RegionMatrix[r + REGION_CENTER_Y]
	              ) {
	                // Top Left quarter
	                q = RegionMatrix[r + REGION_FIRST_CHILD];
	              } else {
	                // Bottom Left quarter
	                q = RegionMatrix[r + REGION_FIRST_CHILD] + PPR;
	              }
	            } else {
	              if (
	                NodeMatrix[RegionMatrix[r + REGION_NODE] + NODE_Y$1] <
	                RegionMatrix[r + REGION_CENTER_Y]
	              ) {
	                // Top Right quarter
	                q = RegionMatrix[r + REGION_FIRST_CHILD] + PPR * 2;
	              } else {
	                // Bottom Right quarter
	                q = RegionMatrix[r + REGION_FIRST_CHILD] + PPR * 3;
	              }
	            }

	            // We remove r[0] from the region r, add its mass to r and record it in q
	            RegionMatrix[r + REGION_MASS] =
	              NodeMatrix[RegionMatrix[r + REGION_NODE] + NODE_MASS];
	            RegionMatrix[r + REGION_MASS_CENTER_X] =
	              NodeMatrix[RegionMatrix[r + REGION_NODE] + NODE_X$1];
	            RegionMatrix[r + REGION_MASS_CENTER_Y] =
	              NodeMatrix[RegionMatrix[r + REGION_NODE] + NODE_Y$1];

	            RegionMatrix[q + REGION_NODE] = RegionMatrix[r + REGION_NODE];
	            RegionMatrix[r + REGION_NODE] = -1;

	            // Find the quadrant of n
	            if (NodeMatrix[n + NODE_X$1] < RegionMatrix[r + REGION_CENTER_X]) {
	              if (NodeMatrix[n + NODE_Y$1] < RegionMatrix[r + REGION_CENTER_Y]) {
	                // Top Left quarter
	                q2 = RegionMatrix[r + REGION_FIRST_CHILD];
	              } else {
	                // Bottom Left quarter
	                q2 = RegionMatrix[r + REGION_FIRST_CHILD] + PPR;
	              }
	            } else {
	              if (NodeMatrix[n + NODE_Y$1] < RegionMatrix[r + REGION_CENTER_Y]) {
	                // Top Right quarter
	                q2 = RegionMatrix[r + REGION_FIRST_CHILD] + PPR * 2;
	              } else {
	                // Bottom Right quarter
	                q2 = RegionMatrix[r + REGION_FIRST_CHILD] + PPR * 3;
	              }
	            }

	            if (q === q2) {
	              // If both nodes are in the same quadrant,
	              // we have to try it again on this quadrant
	              if (subdivisionAttempts--) {
	                r = q;
	                continue; // while
	              } else {
	                // we are out of precision here, and we cannot subdivide anymore
	                // but we have to break the loop anyway
	                subdivisionAttempts = SUBDIVISION_ATTEMPTS;
	                break; // while
	              }
	            }

	            // If both quadrants are different, we record n
	            // in its quadrant
	            RegionMatrix[q2 + REGION_NODE] = n;
	            break;
	          }
	        }
	      }
	    }
	  }

	  // 2) Repulsion
	  //--------------
	  // NOTES: adjustSizes = antiCollision & scalingRatio = coefficient

	  if (options.barnesHutOptimize) {
	    coefficient = options.scalingRatio;

	    // Applying repulsion through regions
	    for (n = 0; n < order; n += PPN$3) {
	      // Computing leaf quad nodes iteration

	      r = 0; // Starting with root region
	      while (true) {
	        if (RegionMatrix[r + REGION_FIRST_CHILD] >= 0) {
	          // The region has sub-regions

	          // We run the Barnes Hut test to see if we are at the right distance
	          distance =
	            Math.pow(
	              NodeMatrix[n + NODE_X$1] - RegionMatrix[r + REGION_MASS_CENTER_X],
	              2
	            ) +
	            Math.pow(
	              NodeMatrix[n + NODE_Y$1] - RegionMatrix[r + REGION_MASS_CENTER_Y],
	              2
	            );

	          s = RegionMatrix[r + REGION_SIZE];

	          if ((4 * s * s) / distance < thetaSquared) {
	            // We treat the region as a single body, and we repulse

	            xDist =
	              NodeMatrix[n + NODE_X$1] - RegionMatrix[r + REGION_MASS_CENTER_X];
	            yDist =
	              NodeMatrix[n + NODE_Y$1] - RegionMatrix[r + REGION_MASS_CENTER_Y];

	            if (adjustSizes === true) {
	              //-- Linear Anti-collision Repulsion
	              if (distance > 0) {
	                factor =
	                  (coefficient *
	                    NodeMatrix[n + NODE_MASS] *
	                    RegionMatrix[r + REGION_MASS]) /
	                  distance;

	                NodeMatrix[n + NODE_DX] += xDist * factor;
	                NodeMatrix[n + NODE_DY] += yDist * factor;
	              } else if (distance < 0) {
	                factor =
	                  (-coefficient *
	                    NodeMatrix[n + NODE_MASS] *
	                    RegionMatrix[r + REGION_MASS]) /
	                  Math.sqrt(distance);

	                NodeMatrix[n + NODE_DX] += xDist * factor;
	                NodeMatrix[n + NODE_DY] += yDist * factor;
	              }
	            } else {
	              //-- Linear Repulsion
	              if (distance > 0) {
	                factor =
	                  (coefficient *
	                    NodeMatrix[n + NODE_MASS] *
	                    RegionMatrix[r + REGION_MASS]) /
	                  distance;

	                NodeMatrix[n + NODE_DX] += xDist * factor;
	                NodeMatrix[n + NODE_DY] += yDist * factor;
	              }
	            }

	            // When this is done, we iterate. We have to look at the next sibling.
	            r = RegionMatrix[r + REGION_NEXT_SIBLING];
	            if (r < 0) break; // No next sibling: we have finished the tree

	            continue;
	          } else {
	            // The region is too close and we have to look at sub-regions
	            r = RegionMatrix[r + REGION_FIRST_CHILD];
	            continue;
	          }
	        } else {
	          // The region has no sub-region
	          // If there is a node r[0] and it is not n, then repulse
	          rn = RegionMatrix[r + REGION_NODE];

	          if (rn >= 0 && rn !== n) {
	            xDist = NodeMatrix[n + NODE_X$1] - NodeMatrix[rn + NODE_X$1];
	            yDist = NodeMatrix[n + NODE_Y$1] - NodeMatrix[rn + NODE_Y$1];

	            distance = xDist * xDist + yDist * yDist;

	            if (adjustSizes === true) {
	              //-- Linear Anti-collision Repulsion
	              if (distance > 0) {
	                factor =
	                  (coefficient *
	                    NodeMatrix[n + NODE_MASS] *
	                    NodeMatrix[rn + NODE_MASS]) /
	                  distance;

	                NodeMatrix[n + NODE_DX] += xDist * factor;
	                NodeMatrix[n + NODE_DY] += yDist * factor;
	              } else if (distance < 0) {
	                factor =
	                  (-coefficient *
	                    NodeMatrix[n + NODE_MASS] *
	                    NodeMatrix[rn + NODE_MASS]) /
	                  Math.sqrt(distance);

	                NodeMatrix[n + NODE_DX] += xDist * factor;
	                NodeMatrix[n + NODE_DY] += yDist * factor;
	              }
	            } else {
	              //-- Linear Repulsion
	              if (distance > 0) {
	                factor =
	                  (coefficient *
	                    NodeMatrix[n + NODE_MASS] *
	                    NodeMatrix[rn + NODE_MASS]) /
	                  distance;

	                NodeMatrix[n + NODE_DX] += xDist * factor;
	                NodeMatrix[n + NODE_DY] += yDist * factor;
	              }
	            }
	          }

	          // When this is done, we iterate. We have to look at the next sibling.
	          r = RegionMatrix[r + REGION_NEXT_SIBLING];

	          if (r < 0) break; // No next sibling: we have finished the tree

	          continue;
	        }
	      }
	    }
	  } else {
	    coefficient = options.scalingRatio;

	    // Square iteration
	    for (n1 = 0; n1 < order; n1 += PPN$3) {
	      for (n2 = 0; n2 < n1; n2 += PPN$3) {
	        // Common to both methods
	        xDist = NodeMatrix[n1 + NODE_X$1] - NodeMatrix[n2 + NODE_X$1];
	        yDist = NodeMatrix[n1 + NODE_Y$1] - NodeMatrix[n2 + NODE_Y$1];

	        if (adjustSizes === true) {
	          //-- Anticollision Linear Repulsion
	          distance =
	            Math.sqrt(xDist * xDist + yDist * yDist) -
	            NodeMatrix[n1 + NODE_SIZE$1] -
	            NodeMatrix[n2 + NODE_SIZE$1];

	          if (distance > 0) {
	            factor =
	              (coefficient *
	                NodeMatrix[n1 + NODE_MASS] *
	                NodeMatrix[n2 + NODE_MASS]) /
	              distance /
	              distance;

	            // Updating nodes' dx and dy
	            NodeMatrix[n1 + NODE_DX] += xDist * factor;
	            NodeMatrix[n1 + NODE_DY] += yDist * factor;

	            NodeMatrix[n2 + NODE_DX] -= xDist * factor;
	            NodeMatrix[n2 + NODE_DY] -= yDist * factor;
	          } else if (distance < 0) {
	            factor =
	              100 *
	              coefficient *
	              NodeMatrix[n1 + NODE_MASS] *
	              NodeMatrix[n2 + NODE_MASS];

	            // Updating nodes' dx and dy
	            NodeMatrix[n1 + NODE_DX] += xDist * factor;
	            NodeMatrix[n1 + NODE_DY] += yDist * factor;

	            NodeMatrix[n2 + NODE_DX] -= xDist * factor;
	            NodeMatrix[n2 + NODE_DY] -= yDist * factor;
	          }
	        } else {
	          //-- Linear Repulsion
	          distance = Math.sqrt(xDist * xDist + yDist * yDist);

	          if (distance > 0) {
	            factor =
	              (coefficient *
	                NodeMatrix[n1 + NODE_MASS] *
	                NodeMatrix[n2 + NODE_MASS]) /
	              distance /
	              distance;

	            // Updating nodes' dx and dy
	            NodeMatrix[n1 + NODE_DX] += xDist * factor;
	            NodeMatrix[n1 + NODE_DY] += yDist * factor;

	            NodeMatrix[n2 + NODE_DX] -= xDist * factor;
	            NodeMatrix[n2 + NODE_DY] -= yDist * factor;
	          }
	        }
	      }
	    }
	  }

	  // 3) Gravity
	  //------------
	  g = options.gravity / options.scalingRatio;
	  coefficient = options.scalingRatio;
	  for (n = 0; n < order; n += PPN$3) {
	    factor = 0;

	    // Common to both methods
	    xDist = NodeMatrix[n + NODE_X$1];
	    yDist = NodeMatrix[n + NODE_Y$1];
	    distance = Math.sqrt(Math.pow(xDist, 2) + Math.pow(yDist, 2));

	    if (options.strongGravityMode) {
	      //-- Strong gravity
	      if (distance > 0) factor = coefficient * NodeMatrix[n + NODE_MASS] * g;
	    } else {
	      //-- Linear Anti-collision Repulsion n
	      if (distance > 0)
	        factor = (coefficient * NodeMatrix[n + NODE_MASS] * g) / distance;
	    }

	    // Updating node's dx and dy
	    NodeMatrix[n + NODE_DX] -= xDist * factor;
	    NodeMatrix[n + NODE_DY] -= yDist * factor;
	  }

	  // 4) Attraction
	  //---------------
	  coefficient =
	    1 * (options.outboundAttractionDistribution ? outboundAttCompensation : 1);

	  // TODO: simplify distance
	  // TODO: coefficient is always used as -c --> optimize?
	  for (e = 0; e < size; e += PPE$1) {
	    n1 = EdgeMatrix[e + EDGE_SOURCE];
	    n2 = EdgeMatrix[e + EDGE_TARGET];
	    w = EdgeMatrix[e + EDGE_WEIGHT];

	    // Edge weight influence
	    ewc = Math.pow(w, options.edgeWeightInfluence);

	    // Common measures
	    xDist = NodeMatrix[n1 + NODE_X$1] - NodeMatrix[n2 + NODE_X$1];
	    yDist = NodeMatrix[n1 + NODE_Y$1] - NodeMatrix[n2 + NODE_Y$1];

	    // Applying attraction to nodes
	    if (adjustSizes === true) {
	      distance =
	        Math.sqrt(xDist * xDist + yDist * yDist) -
	        NodeMatrix[n1 + NODE_SIZE$1] -
	        NodeMatrix[n2 + NODE_SIZE$1];

	      if (options.linLogMode) {
	        if (options.outboundAttractionDistribution) {
	          //-- LinLog Degree Distributed Anti-collision Attraction
	          if (distance > 0) {
	            factor =
	              (-coefficient * ewc * Math.log(1 + distance)) /
	              distance /
	              NodeMatrix[n1 + NODE_MASS];
	          }
	        } else {
	          //-- LinLog Anti-collision Attraction
	          if (distance > 0) {
	            factor = (-coefficient * ewc * Math.log(1 + distance)) / distance;
	          }
	        }
	      } else {
	        if (options.outboundAttractionDistribution) {
	          //-- Linear Degree Distributed Anti-collision Attraction
	          if (distance > 0) {
	            factor = (-coefficient * ewc) / NodeMatrix[n1 + NODE_MASS];
	          }
	        } else {
	          //-- Linear Anti-collision Attraction
	          if (distance > 0) {
	            factor = -coefficient * ewc;
	          }
	        }
	      }
	    } else {
	      distance = Math.sqrt(Math.pow(xDist, 2) + Math.pow(yDist, 2));

	      if (options.linLogMode) {
	        if (options.outboundAttractionDistribution) {
	          //-- LinLog Degree Distributed Attraction
	          if (distance > 0) {
	            factor =
	              (-coefficient * ewc * Math.log(1 + distance)) /
	              distance /
	              NodeMatrix[n1 + NODE_MASS];
	          }
	        } else {
	          //-- LinLog Attraction
	          if (distance > 0)
	            factor = (-coefficient * ewc * Math.log(1 + distance)) / distance;
	        }
	      } else {
	        if (options.outboundAttractionDistribution) {
	          //-- Linear Attraction Mass Distributed
	          // NOTE: Distance is set to 1 to override next condition
	          distance = 1;
	          factor = (-coefficient * ewc) / NodeMatrix[n1 + NODE_MASS];
	        } else {
	          //-- Linear Attraction
	          // NOTE: Distance is set to 1 to override next condition
	          distance = 1;
	          factor = -coefficient * ewc;
	        }
	      }
	    }

	    // Updating nodes' dx and dy
	    // TODO: if condition or factor = 1?
	    if (distance > 0) {
	      // Updating nodes' dx and dy
	      NodeMatrix[n1 + NODE_DX] += xDist * factor;
	      NodeMatrix[n1 + NODE_DY] += yDist * factor;

	      NodeMatrix[n2 + NODE_DX] -= xDist * factor;
	      NodeMatrix[n2 + NODE_DY] -= yDist * factor;
	    }
	  }

	  // 5) Apply Forces
	  //-----------------
	  var force, swinging, traction, nodespeed, newX, newY;

	  // MATH: sqrt and square distances
	  if (adjustSizes === true) {
	    for (n = 0; n < order; n += PPN$3) {
	      if (NodeMatrix[n + NODE_FIXED] !== 1) {
	        force = Math.sqrt(
	          Math.pow(NodeMatrix[n + NODE_DX], 2) +
	            Math.pow(NodeMatrix[n + NODE_DY], 2)
	        );

	        if (force > MAX_FORCE) {
	          NodeMatrix[n + NODE_DX] =
	            (NodeMatrix[n + NODE_DX] * MAX_FORCE) / force;
	          NodeMatrix[n + NODE_DY] =
	            (NodeMatrix[n + NODE_DY] * MAX_FORCE) / force;
	        }

	        swinging =
	          NodeMatrix[n + NODE_MASS] *
	          Math.sqrt(
	            (NodeMatrix[n + NODE_OLD_DX] - NodeMatrix[n + NODE_DX]) *
	              (NodeMatrix[n + NODE_OLD_DX] - NodeMatrix[n + NODE_DX]) +
	              (NodeMatrix[n + NODE_OLD_DY] - NodeMatrix[n + NODE_DY]) *
	                (NodeMatrix[n + NODE_OLD_DY] - NodeMatrix[n + NODE_DY])
	          );

	        traction =
	          Math.sqrt(
	            (NodeMatrix[n + NODE_OLD_DX] + NodeMatrix[n + NODE_DX]) *
	              (NodeMatrix[n + NODE_OLD_DX] + NodeMatrix[n + NODE_DX]) +
	              (NodeMatrix[n + NODE_OLD_DY] + NodeMatrix[n + NODE_DY]) *
	                (NodeMatrix[n + NODE_OLD_DY] + NodeMatrix[n + NODE_DY])
	          ) / 2;

	        nodespeed = (0.1 * Math.log(1 + traction)) / (1 + Math.sqrt(swinging));

	        // Updating node's positon
	        newX =
	          NodeMatrix[n + NODE_X$1] +
	          NodeMatrix[n + NODE_DX] * (nodespeed / options.slowDown);
	        NodeMatrix[n + NODE_X$1] = newX;

	        newY =
	          NodeMatrix[n + NODE_Y$1] +
	          NodeMatrix[n + NODE_DY] * (nodespeed / options.slowDown);
	        NodeMatrix[n + NODE_Y$1] = newY;
	      }
	    }
	  } else {
	    for (n = 0; n < order; n += PPN$3) {
	      if (NodeMatrix[n + NODE_FIXED] !== 1) {
	        swinging =
	          NodeMatrix[n + NODE_MASS] *
	          Math.sqrt(
	            (NodeMatrix[n + NODE_OLD_DX] - NodeMatrix[n + NODE_DX]) *
	              (NodeMatrix[n + NODE_OLD_DX] - NodeMatrix[n + NODE_DX]) +
	              (NodeMatrix[n + NODE_OLD_DY] - NodeMatrix[n + NODE_DY]) *
	                (NodeMatrix[n + NODE_OLD_DY] - NodeMatrix[n + NODE_DY])
	          );

	        traction =
	          Math.sqrt(
	            (NodeMatrix[n + NODE_OLD_DX] + NodeMatrix[n + NODE_DX]) *
	              (NodeMatrix[n + NODE_OLD_DX] + NodeMatrix[n + NODE_DX]) +
	              (NodeMatrix[n + NODE_OLD_DY] + NodeMatrix[n + NODE_DY]) *
	                (NodeMatrix[n + NODE_OLD_DY] + NodeMatrix[n + NODE_DY])
	          ) / 2;

	        nodespeed =
	          (NodeMatrix[n + NODE_CONVERGENCE] * Math.log(1 + traction)) /
	          (1 + Math.sqrt(swinging));

	        // Updating node convergence
	        NodeMatrix[n + NODE_CONVERGENCE] = Math.min(
	          1,
	          Math.sqrt(
	            (nodespeed *
	              (Math.pow(NodeMatrix[n + NODE_DX], 2) +
	                Math.pow(NodeMatrix[n + NODE_DY], 2))) /
	              (1 + Math.sqrt(swinging))
	          )
	        );

	        // Updating node's positon
	        newX =
	          NodeMatrix[n + NODE_X$1] +
	          NodeMatrix[n + NODE_DX] * (nodespeed / options.slowDown);
	        NodeMatrix[n + NODE_X$1] = newX;

	        newY =
	          NodeMatrix[n + NODE_Y$1] +
	          NodeMatrix[n + NODE_DY] * (nodespeed / options.slowDown);
	        NodeMatrix[n + NODE_Y$1] = newY;
	      }
	    }
	  }

	  // We return the information about the layout (no need to return the matrices)
	  return {};
	};

	var helpers$9 = {};

	/**
	 * Graphology ForceAtlas2 Helpers
	 * ===============================
	 *
	 * Miscellaneous helper functions.
	 */

	/**
	 * Constants.
	 */
	var PPN$2 = 10;
	var PPE = 3;

	/**
	 * Very simple Object.assign-like function.
	 *
	 * @param  {object} target       - First object.
	 * @param  {object} [...objects] - Objects to merge.
	 * @return {object}
	 */
	helpers$9.assign = function (target) {
	  target = target || {};

	  var objects = Array.prototype.slice.call(arguments).slice(1),
	    i,
	    k,
	    l;

	  for (i = 0, l = objects.length; i < l; i++) {
	    if (!objects[i]) continue;

	    for (k in objects[i]) target[k] = objects[i][k];
	  }

	  return target;
	};

	/**
	 * Function used to validate the given settings.
	 *
	 * @param  {object}      settings - Settings to validate.
	 * @return {object|null}
	 */
	helpers$9.validateSettings = function (settings) {
	  if ('linLogMode' in settings && typeof settings.linLogMode !== 'boolean')
	    return {message: 'the `linLogMode` setting should be a boolean.'};

	  if (
	    'outboundAttractionDistribution' in settings &&
	    typeof settings.outboundAttractionDistribution !== 'boolean'
	  )
	    return {
	      message:
	        'the `outboundAttractionDistribution` setting should be a boolean.'
	    };

	  if ('adjustSizes' in settings && typeof settings.adjustSizes !== 'boolean')
	    return {message: 'the `adjustSizes` setting should be a boolean.'};

	  if (
	    'edgeWeightInfluence' in settings &&
	    typeof settings.edgeWeightInfluence !== 'number'
	  )
	    return {
	      message: 'the `edgeWeightInfluence` setting should be a number.'
	    };

	  if (
	    'scalingRatio' in settings &&
	    !(typeof settings.scalingRatio === 'number' && settings.scalingRatio >= 0)
	  )
	    return {message: 'the `scalingRatio` setting should be a number >= 0.'};

	  if (
	    'strongGravityMode' in settings &&
	    typeof settings.strongGravityMode !== 'boolean'
	  )
	    return {message: 'the `strongGravityMode` setting should be a boolean.'};

	  if (
	    'gravity' in settings &&
	    !(typeof settings.gravity === 'number' && settings.gravity >= 0)
	  )
	    return {message: 'the `gravity` setting should be a number >= 0.'};

	  if (
	    'slowDown' in settings &&
	    !(typeof settings.slowDown === 'number' || settings.slowDown >= 0)
	  )
	    return {message: 'the `slowDown` setting should be a number >= 0.'};

	  if (
	    'barnesHutOptimize' in settings &&
	    typeof settings.barnesHutOptimize !== 'boolean'
	  )
	    return {message: 'the `barnesHutOptimize` setting should be a boolean.'};

	  if (
	    'barnesHutTheta' in settings &&
	    !(
	      typeof settings.barnesHutTheta === 'number' &&
	      settings.barnesHutTheta >= 0
	    )
	  )
	    return {message: 'the `barnesHutTheta` setting should be a number >= 0.'};

	  return null;
	};

	/**
	 * Function generating a flat matrix for both nodes & edges of the given graph.
	 *
	 * @param  {Graph}    graph         - Target graph.
	 * @param  {function} getEdgeWeight - Edge weight getter function.
	 * @return {object}                 - Both matrices.
	 */
	helpers$9.graphToByteArrays = function (graph, getEdgeWeight) {
	  var order = graph.order;
	  var size = graph.size;
	  var index = {};
	  var j;

	  // NOTE: float32 could lead to issues if edge array needs to index large
	  // number of nodes.
	  var NodeMatrix = new Float32Array(order * PPN$2);
	  var EdgeMatrix = new Float32Array(size * PPE);

	  // Iterate through nodes
	  j = 0;
	  graph.forEachNode(function (node, attr) {
	    // Node index
	    index[node] = j;

	    // Populating byte array
	    NodeMatrix[j] = attr.x;
	    NodeMatrix[j + 1] = attr.y;
	    NodeMatrix[j + 2] = 0; // dx
	    NodeMatrix[j + 3] = 0; // dy
	    NodeMatrix[j + 4] = 0; // old_dx
	    NodeMatrix[j + 5] = 0; // old_dy
	    NodeMatrix[j + 6] = 1; // mass
	    NodeMatrix[j + 7] = 1; // convergence
	    NodeMatrix[j + 8] = attr.size || 1;
	    NodeMatrix[j + 9] = attr.fixed ? 1 : 0;
	    j += PPN$2;
	  });

	  // Iterate through edges
	  j = 0;
	  graph.forEachEdge(function (edge, attr, source, target, sa, ta, u) {
	    var sj = index[source];
	    var tj = index[target];

	    var weight = getEdgeWeight(edge, attr, source, target, sa, ta, u);

	    // Incrementing mass to be a node's weighted degree
	    NodeMatrix[sj + 6] += weight;
	    NodeMatrix[tj + 6] += weight;

	    // Populating byte array
	    EdgeMatrix[j] = sj;
	    EdgeMatrix[j + 1] = tj;
	    EdgeMatrix[j + 2] = weight;
	    j += PPE;
	  });

	  return {
	    nodes: NodeMatrix,
	    edges: EdgeMatrix
	  };
	};

	/**
	 * Function applying the layout back to the graph.
	 *
	 * @param {Graph}         graph         - Target graph.
	 * @param {Float32Array}  NodeMatrix    - Node matrix.
	 * @param {function|null} outputReducer - A node reducer.
	 */
	helpers$9.assignLayoutChanges = function (graph, NodeMatrix, outputReducer) {
	  var i = 0;

	  graph.updateEachNodeAttributes(function (node, attr) {
	    attr.x = NodeMatrix[i];
	    attr.y = NodeMatrix[i + 1];

	    i += PPN$2;

	    return outputReducer ? outputReducer(node, attr) : attr;
	  });
	};

	/**
	 * Function reading the positions (only) from the graph, to write them in the matrix.
	 *
	 * @param {Graph}        graph      - Target graph.
	 * @param {Float32Array} NodeMatrix - Node matrix.
	 */
	helpers$9.readGraphPositions = function (graph, NodeMatrix) {
	  var i = 0;

	  graph.forEachNode(function (node, attr) {
	    NodeMatrix[i] = attr.x;
	    NodeMatrix[i + 1] = attr.y;

	    i += PPN$2;
	  });
	};

	/**
	 * Function collecting the layout positions.
	 *
	 * @param  {Graph}         graph         - Target graph.
	 * @param  {Float32Array}  NodeMatrix    - Node matrix.
	 * @param  {function|null} outputReducer - A nodes reducer.
	 * @return {object}                      - Map to node positions.
	 */
	helpers$9.collectLayoutChanges = function (graph, NodeMatrix, outputReducer) {
	  var nodes = graph.nodes(),
	    positions = {};

	  for (var i = 0, j = 0, l = NodeMatrix.length; i < l; i += PPN$2) {
	    if (outputReducer) {
	      var newAttr = Object.assign({}, graph.getNodeAttributes(nodes[j]));
	      newAttr.x = NodeMatrix[i];
	      newAttr.y = NodeMatrix[i + 1];
	      newAttr = outputReducer(nodes[j], newAttr);
	      positions[nodes[j]] = {
	        x: newAttr.x,
	        y: newAttr.y
	      };
	    } else {
	      positions[nodes[j]] = {
	        x: NodeMatrix[i],
	        y: NodeMatrix[i + 1]
	      };
	    }

	    j++;
	  }

	  return positions;
	};

	/**
	 * Function returning a web worker from the given function.
	 *
	 * @param  {function}  fn - Function for the worker.
	 * @return {DOMString}
	 */
	helpers$9.createWorker = function createWorker(fn) {
	  var xURL = window.URL || window.webkitURL;
	  var code = fn.toString();
	  var objectUrl = xURL.createObjectURL(
	    new Blob(['(' + code + ').call(this);'], {type: 'text/javascript'})
	  );
	  var worker = new Worker(objectUrl);
	  xURL.revokeObjectURL(objectUrl);

	  return worker;
	};

	/**
	 * Graphology ForceAtlas2 Layout Default Settings
	 * ===============================================
	 */

	var defaults$3 = {
	  linLogMode: false,
	  outboundAttractionDistribution: false,
	  adjustSizes: false,
	  edgeWeightInfluence: 1,
	  scalingRatio: 1,
	  strongGravityMode: false,
	  gravity: 1,
	  slowDown: 1,
	  barnesHutOptimize: false,
	  barnesHutTheta: 0.5
	};

	/**
	 * Graphology ForceAtlas2 Layout
	 * ==============================
	 *
	 * Library endpoint.
	 */

	var isGraph$C = isGraph$N;
	var createEdgeWeightGetter$6 =
	  getters$1.createEdgeWeightGetter;
	var iterate$4 = iterate$5;
	var helpers$8 = helpers$9;

	var DEFAULT_SETTINGS$3 = defaults$3;

	/**
	 * Asbtract function used to run a certain number of iterations.
	 *
	 * @param  {boolean}       assign          - Whether to assign positions.
	 * @param  {Graph}         graph           - Target graph.
	 * @param  {object|number} params          - If number, params.iterations, else:
	 * @param  {function}        getWeight     - Edge weight getter function.
	 * @param  {number}          iterations    - Number of iterations.
	 * @param  {function|null}   outputReducer - A node reducer
	 * @param  {object}          [settings]    - Settings.
	 * @return {object|undefined}
	 */
	function abstractSynchronousLayout$1(assign, graph, params) {
	  if (!isGraph$C(graph))
	    throw new Error(
	      'graphology-layout-forceatlas2: the given graph is not a valid graphology instance.'
	    );

	  if (typeof params === 'number') params = {iterations: params};

	  var iterations = params.iterations;

	  if (typeof iterations !== 'number')
	    throw new Error(
	      'graphology-layout-forceatlas2: invalid number of iterations.'
	    );

	  if (iterations <= 0)
	    throw new Error(
	      'graphology-layout-forceatlas2: you should provide a positive number of iterations.'
	    );

	  var getEdgeWeight = createEdgeWeightGetter$6(
	    'getEdgeWeight' in params ? params.getEdgeWeight : 'weight'
	  ).fromEntry;

	  var outputReducer =
	    typeof params.outputReducer === 'function' ? params.outputReducer : null;

	  // Validating settings
	  var settings = helpers$8.assign({}, DEFAULT_SETTINGS$3, params.settings);
	  var validationError = helpers$8.validateSettings(settings);

	  if (validationError)
	    throw new Error(
	      'graphology-layout-forceatlas2: ' + validationError.message
	    );

	  // Building matrices
	  var matrices = helpers$8.graphToByteArrays(graph, getEdgeWeight);

	  var i;

	  // Iterating
	  for (i = 0; i < iterations; i++)
	    iterate$4(settings, matrices.nodes, matrices.edges);

	  // Applying
	  if (assign) {
	    helpers$8.assignLayoutChanges(graph, matrices.nodes, outputReducer);
	    return;
	  }

	  return helpers$8.collectLayoutChanges(graph, matrices.nodes);
	}

	/**
	 * Function returning sane layout settings for the given graph.
	 *
	 * @param  {Graph|number} graph - Target graph or graph order.
	 * @return {object}
	 */
	function inferSettings(graph) {
	  var order = typeof graph === 'number' ? graph : graph.order;

	  return {
	    barnesHutOptimize: order > 2000,
	    strongGravityMode: true,
	    gravity: 0.05,
	    scalingRatio: 10,
	    slowDown: 1 + Math.log(order)
	  };
	}

	/**
	 * Exporting.
	 */
	var synchronousLayout$1 = abstractSynchronousLayout$1.bind(null, false);
	synchronousLayout$1.assign = abstractSynchronousLayout$1.bind(null, true);
	synchronousLayout$1.inferSettings = inferSettings;

	var layoutForceatlas2$1 = synchronousLayout$1;

	var layoutForceatlas2 = layoutForceatlas2$1;

	/**
	 * Graphology Noverlap Iteration
	 * ==============================
	 *
	 * Function used to perform a single iteration of the algorithm.
	 */

	/**
	 * Matrices properties accessors.
	 */
	var NODE_X = 0,
	  NODE_Y = 1,
	  NODE_SIZE = 2;

	/**
	 * Constants.
	 */
	var PPN$1 = 3;

	/**
	 * Helpers.
	 */
	function hashPair(a, b) {
	  return a + '§' + b;
	}

	function jitter() {
	  return 0.01 * (0.5 - Math.random());
	}

	/**
	 * Function used to perform a single interation of the algorithm.
	 *
	 * @param  {object}       options    - Layout options.
	 * @param  {Float32Array} NodeMatrix - Node data.
	 * @return {object}                  - Some metadata.
	 */
	var iterate$3 = function iterate(options, NodeMatrix) {
	  // Caching options
	  var margin = options.margin;
	  var ratio = options.ratio;
	  var expansion = options.expansion;
	  var gridSize = options.gridSize; // TODO: decrease grid size when few nodes?
	  var speed = options.speed;

	  // Generic iteration variables
	  var i, j, x, y, l, size;
	  var converged = true;

	  var length = NodeMatrix.length;
	  var order = (length / PPN$1) | 0;

	  var deltaX = new Float32Array(order);
	  var deltaY = new Float32Array(order);

	  // Finding the extents of our space
	  var xMin = Infinity;
	  var yMin = Infinity;
	  var xMax = -Infinity;
	  var yMax = -Infinity;

	  for (i = 0; i < length; i += PPN$1) {
	    x = NodeMatrix[i + NODE_X];
	    y = NodeMatrix[i + NODE_Y];
	    size = NodeMatrix[i + NODE_SIZE] * ratio + margin;

	    xMin = Math.min(xMin, x - size);
	    xMax = Math.max(xMax, x + size);
	    yMin = Math.min(yMin, y - size);
	    yMax = Math.max(yMax, y + size);
	  }

	  var width = xMax - xMin;
	  var height = yMax - yMin;
	  var xCenter = (xMin + xMax) / 2;
	  var yCenter = (yMin + yMax) / 2;

	  xMin = xCenter - (expansion * width) / 2;
	  xMax = xCenter + (expansion * width) / 2;
	  yMin = yCenter - (expansion * height) / 2;
	  yMax = yCenter + (expansion * height) / 2;

	  // Building grid
	  var grid = new Array(gridSize * gridSize),
	    gridLength = grid.length,
	    c;

	  for (c = 0; c < gridLength; c++) grid[c] = [];

	  var nxMin, nxMax, nyMin, nyMax;
	  var xMinBox, xMaxBox, yMinBox, yMaxBox;

	  var col, row;

	  for (i = 0; i < length; i += PPN$1) {
	    x = NodeMatrix[i + NODE_X];
	    y = NodeMatrix[i + NODE_Y];
	    size = NodeMatrix[i + NODE_SIZE] * ratio + margin;

	    nxMin = x - size;
	    nxMax = x + size;
	    nyMin = y - size;
	    nyMax = y + size;

	    xMinBox = Math.floor((gridSize * (nxMin - xMin)) / (xMax - xMin));
	    xMaxBox = Math.floor((gridSize * (nxMax - xMin)) / (xMax - xMin));
	    yMinBox = Math.floor((gridSize * (nyMin - yMin)) / (yMax - yMin));
	    yMaxBox = Math.floor((gridSize * (nyMax - yMin)) / (yMax - yMin));

	    for (col = xMinBox; col <= xMaxBox; col++) {
	      for (row = yMinBox; row <= yMaxBox; row++) {
	        grid[col * gridSize + row].push(i);
	      }
	    }
	  }

	  // Computing collisions
	  var cell;

	  var collisions = new Set();

	  var n1, n2, x1, x2, y1, y2, s1, s2, h;

	  var xDist, yDist, dist, collision;

	  for (c = 0; c < gridLength; c++) {
	    cell = grid[c];

	    for (i = 0, l = cell.length; i < l; i++) {
	      n1 = cell[i];

	      x1 = NodeMatrix[n1 + NODE_X];
	      y1 = NodeMatrix[n1 + NODE_Y];
	      s1 = NodeMatrix[n1 + NODE_SIZE];

	      for (j = i + 1; j < l; j++) {
	        n2 = cell[j];
	        h = hashPair(n1, n2);

	        if (gridLength > 1 && collisions.has(h)) continue;

	        if (gridLength > 1) collisions.add(h);

	        x2 = NodeMatrix[n2 + NODE_X];
	        y2 = NodeMatrix[n2 + NODE_Y];
	        s2 = NodeMatrix[n2 + NODE_SIZE];

	        xDist = x2 - x1;
	        yDist = y2 - y1;
	        dist = Math.sqrt(xDist * xDist + yDist * yDist);
	        collision = dist < s1 * ratio + margin + (s2 * ratio + margin);

	        if (collision) {
	          converged = false;

	          n2 = (n2 / PPN$1) | 0;

	          if (dist > 0) {
	            deltaX[n2] += (xDist / dist) * (1 + s1);
	            deltaY[n2] += (yDist / dist) * (1 + s1);
	          } else {
	            // Nodes are on the exact same spot, we need to jitter a bit
	            deltaX[n2] += width * jitter();
	            deltaY[n2] += height * jitter();
	          }
	        }
	      }
	    }
	  }

	  for (i = 0, j = 0; i < length; i += PPN$1, j++) {
	    NodeMatrix[i + NODE_X] += deltaX[j] * 0.1 * speed;
	    NodeMatrix[i + NODE_Y] += deltaY[j] * 0.1 * speed;
	  }

	  return {converged: converged};
	};

	var helpers$7 = {};

	/**
	 * Graphology Noverlap Helpers
	 * ============================
	 *
	 * Miscellaneous helper functions.
	 */

	/**
	 * Constants.
	 */
	var PPN = 3;

	/**
	 * Function used to validate the given settings.
	 *
	 * @param  {object}      settings - Settings to validate.
	 * @return {object|null}
	 */
	helpers$7.validateSettings = function (settings) {
	  if (
	    ('gridSize' in settings && typeof settings.gridSize !== 'number') ||
	    settings.gridSize <= 0
	  )
	    return {message: 'the `gridSize` setting should be a positive number.'};

	  if (
	    ('margin' in settings && typeof settings.margin !== 'number') ||
	    settings.margin < 0
	  )
	    return {
	      message: 'the `margin` setting should be 0 or a positive number.'
	    };

	  if (
	    ('expansion' in settings && typeof settings.expansion !== 'number') ||
	    settings.expansion <= 0
	  )
	    return {message: 'the `expansion` setting should be a positive number.'};

	  if (
	    ('ratio' in settings && typeof settings.ratio !== 'number') ||
	    settings.ratio <= 0
	  )
	    return {message: 'the `ratio` setting should be a positive number.'};

	  if (
	    ('speed' in settings && typeof settings.speed !== 'number') ||
	    settings.speed <= 0
	  )
	    return {message: 'the `speed` setting should be a positive number.'};

	  return null;
	};

	/**
	 * Function generating a flat matrix for the given graph's nodes.
	 *
	 * @param  {Graph}        graph   - Target graph.
	 * @param  {function}     reducer - Node reducer function.
	 * @return {Float32Array}         - The node matrix.
	 */
	helpers$7.graphToByteArray = function (graph, reducer) {
	  var order = graph.order;

	  var matrix = new Float32Array(order * PPN);

	  var j = 0;

	  graph.forEachNode(function (node, attr) {
	    if (typeof reducer === 'function') attr = reducer(node, attr);

	    matrix[j] = attr.x;
	    matrix[j + 1] = attr.y;
	    matrix[j + 2] = attr.size || 1;
	    j += PPN;
	  });

	  return matrix;
	};

	/**
	 * Function applying the layout back to the graph.
	 *
	 * @param {Graph}        graph      - Target graph.
	 * @param {Float32Array} NodeMatrix - Node matrix.
	 * @param {function}     reducer    - Reducing function.
	 */
	helpers$7.assignLayoutChanges = function (graph, NodeMatrix, reducer) {
	  var i = 0;

	  graph.forEachNode(function (node) {
	    var pos = {
	      x: NodeMatrix[i],
	      y: NodeMatrix[i + 1]
	    };

	    if (typeof reducer === 'function') pos = reducer(node, pos);

	    graph.mergeNodeAttributes(node, pos);

	    i += PPN;
	  });
	};

	/**
	 * Function collecting the layout positions.
	 *
	 * @param  {Graph}        graph      - Target graph.
	 * @param  {Float32Array} NodeMatrix - Node matrix.
	 * @param  {function}     reducer    - Reducing function.
	 * @return {object}                  - Map to node positions.
	 */
	helpers$7.collectLayoutChanges = function (graph, NodeMatrix, reducer) {
	  var positions = {};

	  var i = 0;

	  graph.forEachNode(function (node) {
	    var pos = {
	      x: NodeMatrix[i],
	      y: NodeMatrix[i + 1]
	    };

	    if (typeof reducer === 'function') pos = reducer(node, pos);

	    positions[node] = pos;

	    i += PPN;
	  });

	  return positions;
	};

	/**
	 * Function returning a web worker from the given function.
	 *
	 * @param  {function}  fn - Function for the worker.
	 * @return {DOMString}
	 */
	helpers$7.createWorker = function createWorker(fn) {
	  var xURL = window.URL || window.webkitURL;
	  var code = fn.toString();
	  var objectUrl = xURL.createObjectURL(
	    new Blob(['(' + code + ').call(this);'], {type: 'text/javascript'})
	  );
	  var worker = new Worker(objectUrl);
	  xURL.revokeObjectURL(objectUrl);

	  return worker;
	};

	/**
	 * Graphology Noverlap Layout Default Settings
	 * ============================================
	 */

	var defaults$2 = {
	  gridSize: 20,
	  margin: 5,
	  expansion: 1.1,
	  ratio: 1.0,
	  speed: 3
	};

	/**
	 * Graphology Noverlap Layout
	 * ===========================
	 *
	 * Library endpoint.
	 */

	var isGraph$B = isGraph$N;
	var iterate$2 = iterate$3;
	var helpers$6 = helpers$7;

	var DEFAULT_SETTINGS$2 = defaults$2;
	var DEFAULT_MAX_ITERATIONS = 500;

	/**
	 * Asbtract function used to run a certain number of iterations.
	 *
	 * @param  {boolean}       assign       - Whether to assign positions.
	 * @param  {Graph}         graph        - Target graph.
	 * @param  {object|number} params       - If number, params.maxIterations, else:
	 * @param  {number}          maxIterations - Maximum number of iterations.
	 * @param  {object}          [settings] - Settings.
	 * @return {object|undefined}
	 */
	function abstractSynchronousLayout(assign, graph, params) {
	  if (!isGraph$B(graph))
	    throw new Error(
	      'graphology-layout-noverlap: the given graph is not a valid graphology instance.'
	    );

	  if (typeof params === 'number') params = {maxIterations: params};
	  else params = params || {};

	  var maxIterations = params.maxIterations || DEFAULT_MAX_ITERATIONS;

	  if (typeof maxIterations !== 'number' || maxIterations <= 0)
	    throw new Error(
	      'graphology-layout-force: you should provide a positive number of maximum iterations.'
	    );

	  // Validating settings
	  var settings = Object.assign({}, DEFAULT_SETTINGS$2, params.settings),
	    validationError = helpers$6.validateSettings(settings);

	  if (validationError)
	    throw new Error('graphology-layout-noverlap: ' + validationError.message);

	  // Building matrices
	  var matrix = helpers$6.graphToByteArray(graph, params.inputReducer),
	    converged = false,
	    i;

	  // Iterating
	  for (i = 0; i < maxIterations && !converged; i++)
	    converged = iterate$2(settings, matrix).converged;

	  // Applying
	  if (assign) {
	    helpers$6.assignLayoutChanges(graph, matrix, params.outputReducer);
	    return;
	  }

	  return helpers$6.collectLayoutChanges(graph, matrix, params.outputReducer);
	}

	/**
	 * Exporting.
	 */
	var synchronousLayout = abstractSynchronousLayout.bind(null, false);
	synchronousLayout.assign = abstractSynchronousLayout.bind(null, true);

	var layoutNoverlap$2 = synchronousLayout;

	var layoutNoverlap$1 = layoutNoverlap$2;

	var metrics$2 = {};

	var centrality = {};

	var degree$1 = {};

	/**
	 * Graphology Degree Centrality
	 * =============================
	 *
	 * Function computing degree centrality.
	 */

	var isGraph$A = isGraph$N;

	/**
	 * Asbtract function to perform any kind of degree centrality.
	 *
	 * Intuitively, the degree centrality of a node is the fraction of nodes it
	 * is connected to.
	 *
	 * @param  {boolean} assign           - Whether to assign the result to the nodes.
	 * @param  {string}  method           - Method of the graph to get the degree.
	 * @param  {Graph}   graph            - A graphology instance.
	 * @param  {object}  [options]        - Options:
	 * @param  {string}    [nodeCentralityAttribute] - Name of the attribute to assign.
	 * @return {object|void}
	 */
	function abstractDegreeCentrality(assign, method, graph, options) {
	  var name = method + 'Centrality';

	  if (!isGraph$A(graph))
	    throw new Error(
	      'graphology-centrality/' +
	        name +
	        ': the given graph is not a valid graphology instance.'
	    );

	  if (method !== 'degree' && graph.type === 'undirected')
	    throw new Error(
	      'graphology-centrality/' +
	        name +
	        ': cannot compute ' +
	        method +
	        ' centrality on an undirected graph.'
	    );

	  // Solving options
	  options = options || {};

	  var centralityAttribute = options.nodeCentralityAttribute || name;

	  var ratio = graph.order - 1;
	  var getDegree = graph[method].bind(graph);

	  if (assign) {
	    graph.updateEachNodeAttributes(
	      function (node, attr) {
	        attr[centralityAttribute] = getDegree(node) / ratio;
	        return attr;
	      },
	      {attributes: [centralityAttribute]}
	    );

	    return;
	  }

	  var centralities = {};

	  graph.forEachNode(function (node) {
	    centralities[node] = getDegree(node) / ratio;
	  });

	  return centralities;
	}

	/**
	 * Building various functions to export.
	 */
	var degreeCentrality = abstractDegreeCentrality.bind(null, false, 'degree');
	var inDegreeCentrality = abstractDegreeCentrality.bind(null, false, 'inDegree');
	var outDegreeCentrality = abstractDegreeCentrality.bind(
	  null,
	  false,
	  'outDegree'
	);

	degreeCentrality.assign = abstractDegreeCentrality.bind(null, true, 'degree');
	inDegreeCentrality.assign = abstractDegreeCentrality.bind(
	  null,
	  true,
	  'inDegree'
	);
	outDegreeCentrality.assign = abstractDegreeCentrality.bind(
	  null,
	  true,
	  'outDegree'
	);

	/**
	 * Exporting.
	 */
	degree$1.degreeCentrality = degreeCentrality;
	degree$1.inDegreeCentrality = inDegreeCentrality;
	degree$1.outDegreeCentrality = outDegreeCentrality;

	var indexedBrandes = {};

	var iterables$4 = {};

	var support$1 = {};

	support$1.ARRAY_BUFFER_SUPPORT = typeof ArrayBuffer !== 'undefined';
	support$1.SYMBOL_SUPPORT = typeof Symbol !== 'undefined';

	/**
	 * Obliterator ForEach Function
	 * =============================
	 *
	 * Helper function used to easily iterate over mixed values.
	 */

	var support = support$1;

	var ARRAY_BUFFER_SUPPORT = support.ARRAY_BUFFER_SUPPORT;
	var SYMBOL_SUPPORT = support.SYMBOL_SUPPORT;

	/**
	 * Function able to iterate over almost any iterable JS value.
	 *
	 * @param  {any}      iterable - Iterable value.
	 * @param  {function} callback - Callback function.
	 */
	var foreach = function forEach(iterable, callback) {
	  var iterator, k, i, l, s;

	  if (!iterable) throw new Error('obliterator/forEach: invalid iterable.');

	  if (typeof callback !== 'function')
	    throw new Error('obliterator/forEach: expecting a callback.');

	  // The target is an array or a string or function arguments
	  if (
	    Array.isArray(iterable) ||
	    (ARRAY_BUFFER_SUPPORT && ArrayBuffer.isView(iterable)) ||
	    typeof iterable === 'string' ||
	    iterable.toString() === '[object Arguments]'
	  ) {
	    for (i = 0, l = iterable.length; i < l; i++) callback(iterable[i], i);
	    return;
	  }

	  // The target has a #.forEach method
	  if (typeof iterable.forEach === 'function') {
	    iterable.forEach(callback);
	    return;
	  }

	  // The target is iterable
	  if (
	    SYMBOL_SUPPORT &&
	    Symbol.iterator in iterable &&
	    typeof iterable.next !== 'function'
	  ) {
	    iterable = iterable[Symbol.iterator]();
	  }

	  // The target is an iterator
	  if (typeof iterable.next === 'function') {
	    iterator = iterable;
	    i = 0;

	    while (((s = iterator.next()), s.done !== true)) {
	      callback(s.value, i);
	      i++;
	    }

	    return;
	  }

	  // The target is a plain object
	  for (k in iterable) {
	    if (iterable.hasOwnProperty(k)) {
	      callback(iterable[k], k);
	    }
	  }

	  return;
	};

	/**
	 * Mnemonist Iterable Function
	 * ============================
	 *
	 * Harmonized iteration helpers over mixed iterable targets.
	 */

	var forEach$2 = foreach;

	var typed$3 = typedArrays;

	/**
	 * Function used to determine whether the given object supports array-like
	 * random access.
	 *
	 * @param  {any} target - Target object.
	 * @return {boolean}
	 */
	function isArrayLike(target) {
	  return Array.isArray(target) || typed$3.isTypedArray(target);
	}

	/**
	 * Function used to guess the length of the structure over which we are going
	 * to iterate.
	 *
	 * @param  {any} target - Target object.
	 * @return {number|undefined}
	 */
	function guessLength(target) {
	  if (typeof target.length === 'number')
	    return target.length;

	  if (typeof target.size === 'number')
	    return target.size;

	  return;
	}

	/**
	 * Function used to convert an iterable to an array.
	 *
	 * @param  {any}   target - Iteration target.
	 * @return {array}
	 */
	function toArray(target) {
	  var l = guessLength(target);

	  var array = typeof l === 'number' ? new Array(l) : [];

	  var i = 0;

	  // TODO: we could optimize when given target is array like
	  forEach$2(target, function(value) {
	    array[i++] = value;
	  });

	  return array;
	}

	/**
	 * Same as above but returns a supplementary indices array.
	 *
	 * @param  {any}   target - Iteration target.
	 * @return {array}
	 */
	function toArrayWithIndices(target) {
	  var l = guessLength(target);

	  var IndexArray = typeof l === 'number' ?
	    typed$3.getPointerArray(l) :
	    Array;

	  var array = typeof l === 'number' ? new Array(l) : [];
	  var indices = typeof l === 'number' ? new IndexArray(l) : [];

	  var i = 0;

	  // TODO: we could optimize when given target is array like
	  forEach$2(target, function(value) {
	    array[i] = value;
	    indices[i] = i++;
	  });

	  return [array, indices];
	}

	/**
	 * Exporting.
	 */
	iterables$4.isArrayLike = isArrayLike;
	iterables$4.guessLength = guessLength;
	iterables$4.toArray = toArray;
	iterables$4.toArrayWithIndices = toArrayWithIndices;

	/**
	 * Mnemonist FixedDeque
	 * =====================
	 *
	 * Fixed capacity double-ended queue implemented as ring deque.
	 */

	var iterables$3 = iterables$4,
	    Iterator$3 = iterator;

	/**
	 * FixedDeque.
	 *
	 * @constructor
	 */
	function FixedDeque$3(ArrayClass, capacity) {

	  if (arguments.length < 2)
	    throw new Error('mnemonist/fixed-deque: expecting an Array class and a capacity.');

	  if (typeof capacity !== 'number' || capacity <= 0)
	    throw new Error('mnemonist/fixed-deque: `capacity` should be a positive number.');

	  this.ArrayClass = ArrayClass;
	  this.capacity = capacity;
	  this.items = new ArrayClass(this.capacity);
	  this.clear();
	}

	/**
	 * Method used to clear the structure.
	 *
	 * @return {undefined}
	 */
	FixedDeque$3.prototype.clear = function() {

	  // Properties
	  this.start = 0;
	  this.size = 0;
	};

	/**
	 * Method used to append a value to the deque.
	 *
	 * @param  {any}    item - Item to append.
	 * @return {number}      - Returns the new size of the deque.
	 */
	FixedDeque$3.prototype.push = function(item) {
	  if (this.size === this.capacity)
	    throw new Error('mnemonist/fixed-deque.push: deque capacity (' + this.capacity + ') exceeded!');

	  var index = (this.start + this.size) % this.capacity;

	  this.items[index] = item;

	  return ++this.size;
	};

	/**
	 * Method used to prepend a value to the deque.
	 *
	 * @param  {any}    item - Item to prepend.
	 * @return {number}      - Returns the new size of the deque.
	 */
	FixedDeque$3.prototype.unshift = function(item) {
	  if (this.size === this.capacity)
	    throw new Error('mnemonist/fixed-deque.unshift: deque capacity (' + this.capacity + ') exceeded!');

	  var index = this.start - 1;

	  if (this.start === 0)
	    index = this.capacity - 1;

	  this.items[index] = item;
	  this.start = index;

	  return ++this.size;
	};

	/**
	 * Method used to pop the deque.
	 *
	 * @return {any} - Returns the popped item.
	 */
	FixedDeque$3.prototype.pop = function() {
	  if (this.size === 0)
	    return;

	  const index = (this.start + this.size - 1) % this.capacity;

	  this.size--;

	  return this.items[index];
	};

	/**
	 * Method used to shift the deque.
	 *
	 * @return {any} - Returns the shifted item.
	 */
	FixedDeque$3.prototype.shift = function() {
	  if (this.size === 0)
	    return;

	  var index = this.start;

	  this.size--;
	  this.start++;

	  if (this.start === this.capacity)
	    this.start = 0;

	  return this.items[index];
	};

	/**
	 * Method used to peek the first value of the deque.
	 *
	 * @return {any}
	 */
	FixedDeque$3.prototype.peekFirst = function() {
	  if (this.size === 0)
	    return;

	  return this.items[this.start];
	};

	/**
	 * Method used to peek the last value of the deque.
	 *
	 * @return {any}
	 */
	FixedDeque$3.prototype.peekLast = function() {
	  if (this.size === 0)
	    return;

	  var index = this.start + this.size - 1;

	  if (index > this.capacity)
	    index -= this.capacity;

	  return this.items[index];
	};

	/**
	 * Method used to get the desired value of the deque.
	 *
	 * @param  {number} index
	 * @return {any}
	 */
	FixedDeque$3.prototype.get = function(index) {
	  if (this.size === 0)
	    return;

	  index = this.start + index;

	  if (index > this.capacity)
	    index -= this.capacity;

	  return this.items[index];
	};

	/**
	 * Method used to iterate over the deque.
	 *
	 * @param  {function}  callback - Function to call for each item.
	 * @param  {object}    scope    - Optional scope.
	 * @return {undefined}
	 */
	FixedDeque$3.prototype.forEach = function(callback, scope) {
	  scope = arguments.length > 1 ? scope : this;

	  var c = this.capacity,
	      l = this.size,
	      i = this.start,
	      j = 0;

	  while (j < l) {
	    callback.call(scope, this.items[i], j, this);
	    i++;
	    j++;

	    if (i === c)
	      i = 0;
	  }
	};

	/**
	 * Method used to convert the deque to a JavaScript array.
	 *
	 * @return {array}
	 */
	// TODO: optional array class as argument?
	FixedDeque$3.prototype.toArray = function() {

	  // Optimization
	  var offset = this.start + this.size;

	  if (offset < this.capacity)
	    return this.items.slice(this.start, offset);

	  var array = new this.ArrayClass(this.size),
	      c = this.capacity,
	      l = this.size,
	      i = this.start,
	      j = 0;

	  while (j < l) {
	    array[j] = this.items[i];
	    i++;
	    j++;

	    if (i === c)
	      i = 0;
	  }

	  return array;
	};

	/**
	 * Method used to create an iterator over the deque's values.
	 *
	 * @return {Iterator}
	 */
	FixedDeque$3.prototype.values = function() {
	  var items = this.items,
	      c = this.capacity,
	      l = this.size,
	      i = this.start,
	      j = 0;

	  return new Iterator$3(function() {
	    if (j >= l)
	      return {
	        done: true
	      };

	    var value = items[i];

	    i++;
	    j++;

	    if (i === c)
	      i = 0;

	    return {
	      value: value,
	      done: false
	    };
	  });
	};

	/**
	 * Method used to create an iterator over the deque's entries.
	 *
	 * @return {Iterator}
	 */
	FixedDeque$3.prototype.entries = function() {
	  var items = this.items,
	      c = this.capacity,
	      l = this.size,
	      i = this.start,
	      j = 0;

	  return new Iterator$3(function() {
	    if (j >= l)
	      return {
	        done: true
	      };

	    var value = items[i];

	    i++;

	    if (i === c)
	      i = 0;

	    return {
	      value: [j++, value],
	      done: false
	    };
	  });
	};

	/**
	 * Attaching the #.values method to Symbol.iterator if possible.
	 */
	if (typeof Symbol !== 'undefined')
	  FixedDeque$3.prototype[Symbol.iterator] = FixedDeque$3.prototype.values;

	/**
	 * Convenience known methods.
	 */
	FixedDeque$3.prototype.inspect = function() {
	  var array = this.toArray();

	  array.type = this.ArrayClass.name;
	  array.capacity = this.capacity;

	  // Trick so that node displays the name of the constructor
	  Object.defineProperty(array, 'constructor', {
	    value: FixedDeque$3,
	    enumerable: false
	  });

	  return array;
	};

	if (typeof Symbol !== 'undefined')
	  FixedDeque$3.prototype[Symbol.for('nodejs.util.inspect.custom')] = FixedDeque$3.prototype.inspect;

	/**
	 * Static @.from function taking an arbitrary iterable & converting it into
	 * a deque.
	 *
	 * @param  {Iterable} iterable   - Target iterable.
	 * @param  {function} ArrayClass - Array class to use.
	 * @param  {number}   capacity   - Desired capacity.
	 * @return {FiniteStack}
	 */
	FixedDeque$3.from = function(iterable, ArrayClass, capacity) {
	  if (arguments.length < 3) {
	    capacity = iterables$3.guessLength(iterable);

	    if (typeof capacity !== 'number')
	      throw new Error('mnemonist/fixed-deque.from: could not guess iterable length. Please provide desired capacity as last argument.');
	  }

	  var deque = new FixedDeque$3(ArrayClass, capacity);

	  if (iterables$3.isArrayLike(iterable)) {
	    var i, l;

	    for (i = 0, l = iterable.length; i < l; i++)
	      deque.items[i] = iterable[i];

	    deque.size = l;

	    return deque;
	  }

	  iterables$3.forEach(iterable, function(value) {
	    deque.push(value);
	  });

	  return deque;
	};

	/**
	 * Exporting.
	 */
	var fixedDeque = FixedDeque$3;

	/**
	 * Mnemonist FixedStack
	 * =====================
	 *
	 * The fixed stack is a stack whose capacity is defined beforehand and that
	 * cannot be exceeded. This class is really useful when combined with
	 * byte arrays to save up some memory and avoid memory re-allocation, hence
	 * speeding up computations.
	 *
	 * This has however a downside: you need to know the maximum size you stack
	 * can have during your iteration (which is not too difficult to compute when
	 * performing, say, a DFS on a balanced binary tree).
	 */

	var Iterator$2 = iterator,
	    iterables$2 = iterables$4;

	/**
	 * FixedStack
	 *
	 * @constructor
	 * @param {function} ArrayClass - Array class to use.
	 * @param {number}   capacity   - Desired capacity.
	 */
	function FixedStack$1(ArrayClass, capacity) {

	  if (arguments.length < 2)
	    throw new Error('mnemonist/fixed-stack: expecting an Array class and a capacity.');

	  if (typeof capacity !== 'number' || capacity <= 0)
	    throw new Error('mnemonist/fixed-stack: `capacity` should be a positive number.');

	  this.capacity = capacity;
	  this.ArrayClass = ArrayClass;
	  this.items = new this.ArrayClass(this.capacity);
	  this.clear();
	}

	/**
	 * Method used to clear the stack.
	 *
	 * @return {undefined}
	 */
	FixedStack$1.prototype.clear = function() {

	  // Properties
	  this.size = 0;
	};

	/**
	 * Method used to add an item to the stack.
	 *
	 * @param  {any}    item - Item to add.
	 * @return {number}
	 */
	FixedStack$1.prototype.push = function(item) {
	  if (this.size === this.capacity)
	    throw new Error('mnemonist/fixed-stack.push: stack capacity (' + this.capacity + ') exceeded!');

	  this.items[this.size++] = item;
	  return this.size;
	};

	/**
	 * Method used to retrieve & remove the last item of the stack.
	 *
	 * @return {any}
	 */
	FixedStack$1.prototype.pop = function() {
	  if (this.size === 0)
	    return;

	  return this.items[--this.size];
	};

	/**
	 * Method used to get the last item of the stack.
	 *
	 * @return {any}
	 */
	FixedStack$1.prototype.peek = function() {
	  return this.items[this.size - 1];
	};

	/**
	 * Method used to iterate over the stack.
	 *
	 * @param  {function}  callback - Function to call for each item.
	 * @param  {object}    scope    - Optional scope.
	 * @return {undefined}
	 */
	FixedStack$1.prototype.forEach = function(callback, scope) {
	  scope = arguments.length > 1 ? scope : this;

	  for (var i = 0, l = this.items.length; i < l; i++)
	    callback.call(scope, this.items[l - i - 1], i, this);
	};

	/**
	 * Method used to convert the stack to a JavaScript array.
	 *
	 * @return {array}
	 */
	FixedStack$1.prototype.toArray = function() {
	  var array = new this.ArrayClass(this.size),
	      l = this.size - 1,
	      i = this.size;

	  while (i--)
	    array[i] = this.items[l - i];

	  return array;
	};

	/**
	 * Method used to create an iterator over a stack's values.
	 *
	 * @return {Iterator}
	 */
	FixedStack$1.prototype.values = function() {
	  var items = this.items,
	      l = this.size,
	      i = 0;

	  return new Iterator$2(function() {
	    if (i >= l)
	      return {
	        done: true
	      };

	    var value = items[l - i - 1];
	    i++;

	    return {
	      value: value,
	      done: false
	    };
	  });
	};

	/**
	 * Method used to create an iterator over a stack's entries.
	 *
	 * @return {Iterator}
	 */
	FixedStack$1.prototype.entries = function() {
	  var items = this.items,
	      l = this.size,
	      i = 0;

	  return new Iterator$2(function() {
	    if (i >= l)
	      return {
	        done: true
	      };

	    var value = items[l - i - 1];

	    return {
	      value: [i++, value],
	      done: false
	    };
	  });
	};

	/**
	 * Attaching the #.values method to Symbol.iterator if possible.
	 */
	if (typeof Symbol !== 'undefined')
	  FixedStack$1.prototype[Symbol.iterator] = FixedStack$1.prototype.values;


	/**
	 * Convenience known methods.
	 */
	FixedStack$1.prototype.toString = function() {
	  return this.toArray().join(',');
	};

	FixedStack$1.prototype.toJSON = function() {
	  return this.toArray();
	};

	FixedStack$1.prototype.inspect = function() {
	  var array = this.toArray();

	  array.type = this.ArrayClass.name;
	  array.capacity = this.capacity;

	  // Trick so that node displays the name of the constructor
	  Object.defineProperty(array, 'constructor', {
	    value: FixedStack$1,
	    enumerable: false
	  });

	  return array;
	};

	if (typeof Symbol !== 'undefined')
	  FixedStack$1.prototype[Symbol.for('nodejs.util.inspect.custom')] = FixedStack$1.prototype.inspect;

	/**
	 * Static @.from function taking an arbitrary iterable & converting it into
	 * a stack.
	 *
	 * @param  {Iterable} iterable   - Target iterable.
	 * @param  {function} ArrayClass - Array class to use.
	 * @param  {number}   capacity   - Desired capacity.
	 * @return {FixedStack}
	 */
	FixedStack$1.from = function(iterable, ArrayClass, capacity) {

	  if (arguments.length < 3) {
	    capacity = iterables$2.guessLength(iterable);

	    if (typeof capacity !== 'number')
	      throw new Error('mnemonist/fixed-stack.from: could not guess iterable length. Please provide desired capacity as last argument.');
	  }

	  var stack = new FixedStack$1(ArrayClass, capacity);

	  if (iterables$2.isArrayLike(iterable)) {
	    var i, l;

	    for (i = 0, l = iterable.length; i < l; i++)
	      stack.items[i] = iterable[i];

	    stack.size = l;

	    return stack;
	  }

	  iterables$2.forEach(iterable, function(value) {
	    stack.push(value);
	  });

	  return stack;
	};

	/**
	 * Exporting.
	 */
	var fixedStack = FixedStack$1;

	var comparators$2 = {};

	/**
	 * Mnemonist Heap Comparators
	 * ===========================
	 *
	 * Default comparators & functions dealing with comparators reversing etc.
	 */

	var DEFAULT_COMPARATOR$2 = function(a, b) {
	  if (a < b)
	    return -1;
	  if (a > b)
	    return 1;

	  return 0;
	};

	var DEFAULT_REVERSE_COMPARATOR = function(a, b) {
	  if (a < b)
	    return 1;
	  if (a > b)
	    return -1;

	  return 0;
	};

	/**
	 * Function used to reverse a comparator.
	 */
	function reverseComparator$2(comparator) {
	  return function(a, b) {
	    return comparator(b, a);
	  };
	}

	/**
	 * Function returning a tuple comparator.
	 */
	function createTupleComparator$1(size) {
	  if (size === 2) {
	    return function(a, b) {
	      if (a[0] < b[0])
	        return -1;

	      if (a[0] > b[0])
	        return 1;

	      if (a[1] < b[1])
	        return -1;

	      if (a[1] > b[1])
	        return 1;

	      return 0;
	    };
	  }

	  return function(a, b) {
	    var i = 0;

	    while (i < size) {
	      if (a[i] < b[i])
	        return -1;

	      if (a[i] > b[i])
	        return 1;

	      i++;
	    }

	    return 0;
	  };
	}

	/**
	 * Exporting.
	 */
	comparators$2.DEFAULT_COMPARATOR = DEFAULT_COMPARATOR$2;
	comparators$2.DEFAULT_REVERSE_COMPARATOR = DEFAULT_REVERSE_COMPARATOR;
	comparators$2.reverseComparator = reverseComparator$2;
	comparators$2.createTupleComparator = createTupleComparator$1;

	/**
	 * Mnemonist Binary Heap
	 * ======================
	 *
	 * Binary heap implementation.
	 */

	var forEach$1 = foreach,
	    comparators$1 = comparators$2,
	    iterables$1 = iterables$4;

	var DEFAULT_COMPARATOR$1 = comparators$1.DEFAULT_COMPARATOR,
	    reverseComparator$1 = comparators$1.reverseComparator;

	/**
	 * Heap helper functions.
	 */

	/**
	 * Function used to sift down.
	 *
	 * @param {function} compare    - Comparison function.
	 * @param {array}    heap       - Array storing the heap's data.
	 * @param {number}   startIndex - Starting index.
	 * @param {number}   i          - Index.
	 */
	function siftDown(compare, heap, startIndex, i) {
	  var item = heap[i],
	      parentIndex,
	      parent;

	  while (i > startIndex) {
	    parentIndex = (i - 1) >> 1;
	    parent = heap[parentIndex];

	    if (compare(item, parent) < 0) {
	      heap[i] = parent;
	      i = parentIndex;
	      continue;
	    }

	    break;
	  }

	  heap[i] = item;
	}

	/**
	 * Function used to sift up.
	 *
	 * @param {function} compare - Comparison function.
	 * @param {array}    heap    - Array storing the heap's data.
	 * @param {number}   i       - Index.
	 */
	function siftUp$1(compare, heap, i) {
	  var endIndex = heap.length,
	      startIndex = i,
	      item = heap[i],
	      childIndex = 2 * i + 1,
	      rightIndex;

	  while (childIndex < endIndex) {
	    rightIndex = childIndex + 1;

	    if (
	      rightIndex < endIndex &&
	      compare(heap[childIndex], heap[rightIndex]) >= 0
	    ) {
	      childIndex = rightIndex;
	    }

	    heap[i] = heap[childIndex];
	    i = childIndex;
	    childIndex = 2 * i + 1;
	  }

	  heap[i] = item;
	  siftDown(compare, heap, startIndex, i);
	}

	/**
	 * Function used to push an item into a heap represented by a raw array.
	 *
	 * @param {function} compare - Comparison function.
	 * @param {array}    heap    - Array storing the heap's data.
	 * @param {any}      item    - Item to push.
	 */
	function push(compare, heap, item) {
	  heap.push(item);
	  siftDown(compare, heap, 0, heap.length - 1);
	}

	/**
	 * Function used to pop an item from a heap represented by a raw array.
	 *
	 * @param  {function} compare - Comparison function.
	 * @param  {array}    heap    - Array storing the heap's data.
	 * @return {any}
	 */
	function pop(compare, heap) {
	  var lastItem = heap.pop();

	  if (heap.length !== 0) {
	    var item = heap[0];
	    heap[0] = lastItem;
	    siftUp$1(compare, heap, 0);

	    return item;
	  }

	  return lastItem;
	}

	/**
	 * Function used to pop the heap then push a new value into it, thus "replacing"
	 * it.
	 *
	 * @param  {function} compare - Comparison function.
	 * @param  {array}    heap    - Array storing the heap's data.
	 * @param  {any}      item    - The item to push.
	 * @return {any}
	 */
	function replace(compare, heap, item) {
	  if (heap.length === 0)
	    throw new Error('mnemonist/heap.replace: cannot pop an empty heap.');

	  var popped = heap[0];
	  heap[0] = item;
	  siftUp$1(compare, heap, 0);

	  return popped;
	}

	/**
	 * Function used to push an item in the heap then pop the heap and return the
	 * popped value.
	 *
	 * @param  {function} compare - Comparison function.
	 * @param  {array}    heap    - Array storing the heap's data.
	 * @param  {any}      item    - The item to push.
	 * @return {any}
	 */
	function pushpop(compare, heap, item) {
	  var tmp;

	  if (heap.length !== 0 && compare(heap[0], item) < 0) {
	    tmp = heap[0];
	    heap[0] = item;
	    item = tmp;
	    siftUp$1(compare, heap, 0);
	  }

	  return item;
	}

	/**
	 * Converts and array into an abstract heap in linear time.
	 *
	 * @param {function} compare - Comparison function.
	 * @param {array}    array   - Target array.
	 */
	function heapify(compare, array) {
	  var n = array.length,
	      l = n >> 1,
	      i = l;

	  while (--i >= 0)
	    siftUp$1(compare, array, i);
	}

	/**
	 * Fully consumes the given heap.
	 *
	 * @param  {function} compare - Comparison function.
	 * @param  {array}    heap    - Array storing the heap's data.
	 * @return {array}
	 */
	function consume$1(compare, heap) {
	  var l = heap.length,
	      i = 0;

	  var array = new Array(l);

	  while (i < l)
	    array[i++] = pop(compare, heap);

	  return array;
	}

	/**
	 * Function used to retrieve the n smallest items from the given iterable.
	 *
	 * @param {function} compare  - Comparison function.
	 * @param {number}   n        - Number of top items to retrieve.
	 * @param {any}      iterable - Arbitrary iterable.
	 * @param {array}
	 */
	function nsmallest(compare, n, iterable) {
	  if (arguments.length === 2) {
	    iterable = n;
	    n = compare;
	    compare = DEFAULT_COMPARATOR$1;
	  }

	  var reverseCompare = reverseComparator$1(compare);

	  var i, l, v;

	  var min = Infinity;

	  var result;

	  // If n is equal to 1, it's just a matter of finding the minimum
	  if (n === 1) {
	    if (iterables$1.isArrayLike(iterable)) {
	      for (i = 0, l = iterable.length; i < l; i++) {
	        v = iterable[i];

	        if (min === Infinity || compare(v, min) < 0)
	          min = v;
	      }

	      result = new iterable.constructor(1);
	      result[0] = min;

	      return result;
	    }

	    forEach$1(iterable, function(value) {
	      if (min === Infinity || compare(value, min) < 0)
	        min = value;
	    });

	    return [min];
	  }

	  if (iterables$1.isArrayLike(iterable)) {

	    // If n > iterable length, we just clone and sort
	    if (n >= iterable.length)
	      return iterable.slice().sort(compare);

	    result = iterable.slice(0, n);
	    heapify(reverseCompare, result);

	    for (i = n, l = iterable.length; i < l; i++)
	      if (reverseCompare(iterable[i], result[0]) > 0)
	        replace(reverseCompare, result, iterable[i]);

	    // NOTE: if n is over some number, it becomes faster to consume the heap
	    return result.sort(compare);
	  }

	  // Correct for size
	  var size = iterables$1.guessLength(iterable);

	  if (size !== null && size < n)
	    n = size;

	  result = new Array(n);
	  i = 0;

	  forEach$1(iterable, function(value) {
	    if (i < n) {
	      result[i] = value;
	    }
	    else {
	      if (i === n)
	        heapify(reverseCompare, result);

	      if (reverseCompare(value, result[0]) > 0)
	        replace(reverseCompare, result, value);
	    }

	    i++;
	  });

	  if (result.length > i)
	    result.length = i;

	  // NOTE: if n is over some number, it becomes faster to consume the heap
	  return result.sort(compare);
	}

	/**
	 * Function used to retrieve the n largest items from the given iterable.
	 *
	 * @param {function} compare  - Comparison function.
	 * @param {number}   n        - Number of top items to retrieve.
	 * @param {any}      iterable - Arbitrary iterable.
	 * @param {array}
	 */
	function nlargest(compare, n, iterable) {
	  if (arguments.length === 2) {
	    iterable = n;
	    n = compare;
	    compare = DEFAULT_COMPARATOR$1;
	  }

	  var reverseCompare = reverseComparator$1(compare);

	  var i, l, v;

	  var max = -Infinity;

	  var result;

	  // If n is equal to 1, it's just a matter of finding the maximum
	  if (n === 1) {
	    if (iterables$1.isArrayLike(iterable)) {
	      for (i = 0, l = iterable.length; i < l; i++) {
	        v = iterable[i];

	        if (max === -Infinity || compare(v, max) > 0)
	          max = v;
	      }

	      result = new iterable.constructor(1);
	      result[0] = max;

	      return result;
	    }

	    forEach$1(iterable, function(value) {
	      if (max === -Infinity || compare(value, max) > 0)
	        max = value;
	    });

	    return [max];
	  }

	  if (iterables$1.isArrayLike(iterable)) {

	    // If n > iterable length, we just clone and sort
	    if (n >= iterable.length)
	      return iterable.slice().sort(reverseCompare);

	    result = iterable.slice(0, n);
	    heapify(compare, result);

	    for (i = n, l = iterable.length; i < l; i++)
	      if (compare(iterable[i], result[0]) > 0)
	        replace(compare, result, iterable[i]);

	    // NOTE: if n is over some number, it becomes faster to consume the heap
	    return result.sort(reverseCompare);
	  }

	  // Correct for size
	  var size = iterables$1.guessLength(iterable);

	  if (size !== null && size < n)
	    n = size;

	  result = new Array(n);
	  i = 0;

	  forEach$1(iterable, function(value) {
	    if (i < n) {
	      result[i] = value;
	    }
	    else {
	      if (i === n)
	        heapify(compare, result);

	      if (compare(value, result[0]) > 0)
	        replace(compare, result, value);
	    }

	    i++;
	  });

	  if (result.length > i)
	    result.length = i;

	  // NOTE: if n is over some number, it becomes faster to consume the heap
	  return result.sort(reverseCompare);
	}

	/**
	 * Binary Minimum Heap.
	 *
	 * @constructor
	 * @param {function} comparator - Comparator function to use.
	 */
	function Heap$3(comparator) {
	  this.clear();
	  this.comparator = comparator || DEFAULT_COMPARATOR$1;

	  if (typeof this.comparator !== 'function')
	    throw new Error('mnemonist/Heap.constructor: given comparator should be a function.');
	}

	/**
	 * Method used to clear the heap.
	 *
	 * @return {undefined}
	 */
	Heap$3.prototype.clear = function() {

	  // Properties
	  this.items = [];
	  this.size = 0;
	};

	/**
	 * Method used to push an item into the heap.
	 *
	 * @param  {any}    item - Item to push.
	 * @return {number}
	 */
	Heap$3.prototype.push = function(item) {
	  push(this.comparator, this.items, item);
	  return ++this.size;
	};

	/**
	 * Method used to retrieve the "first" item of the heap.
	 *
	 * @return {any}
	 */
	Heap$3.prototype.peek = function() {
	  return this.items[0];
	};

	/**
	 * Method used to retrieve & remove the "first" item of the heap.
	 *
	 * @return {any}
	 */
	Heap$3.prototype.pop = function() {
	  if (this.size !== 0)
	    this.size--;

	  return pop(this.comparator, this.items);
	};

	/**
	 * Method used to pop the heap, then push an item and return the popped
	 * item.
	 *
	 * @param  {any} item - Item to push into the heap.
	 * @return {any}
	 */
	Heap$3.prototype.replace = function(item) {
	  return replace(this.comparator, this.items, item);
	};

	/**
	 * Method used to push the heap, the pop it and return the pooped item.
	 *
	 * @param  {any} item - Item to push into the heap.
	 * @return {any}
	 */
	Heap$3.prototype.pushpop = function(item) {
	  return pushpop(this.comparator, this.items, item);
	};

	/**
	 * Method used to consume the heap fully and return its items as a sorted array.
	 *
	 * @return {array}
	 */
	Heap$3.prototype.consume = function() {
	  this.size = 0;
	  return consume$1(this.comparator, this.items);
	};

	/**
	 * Method used to convert the heap to an array. Note that it basically clone
	 * the heap and consumes it completely. This is hardly performant.
	 *
	 * @return {array}
	 */
	Heap$3.prototype.toArray = function() {
	  return consume$1(this.comparator, this.items.slice());
	};

	/**
	 * Convenience known methods.
	 */
	Heap$3.prototype.inspect = function() {
	  var proxy = this.toArray();

	  // Trick so that node displays the name of the constructor
	  Object.defineProperty(proxy, 'constructor', {
	    value: Heap$3,
	    enumerable: false
	  });

	  return proxy;
	};

	if (typeof Symbol !== 'undefined')
	  Heap$3.prototype[Symbol.for('nodejs.util.inspect.custom')] = Heap$3.prototype.inspect;

	/**
	 * Binary Maximum Heap.
	 *
	 * @constructor
	 * @param {function} comparator - Comparator function to use.
	 */
	function MaxHeap(comparator) {
	  this.clear();
	  this.comparator = comparator || DEFAULT_COMPARATOR$1;

	  if (typeof this.comparator !== 'function')
	    throw new Error('mnemonist/MaxHeap.constructor: given comparator should be a function.');

	  this.comparator = reverseComparator$1(this.comparator);
	}

	MaxHeap.prototype = Heap$3.prototype;

	/**
	 * Static @.from function taking an arbitrary iterable & converting it into
	 * a heap.
	 *
	 * @param  {Iterable} iterable   - Target iterable.
	 * @param  {function} comparator - Custom comparator function.
	 * @return {Heap}
	 */
	Heap$3.from = function(iterable, comparator) {
	  var heap = new Heap$3(comparator);

	  var items;

	  // If iterable is an array, we can be clever about it
	  if (iterables$1.isArrayLike(iterable))
	    items = iterable.slice();
	  else
	    items = iterables$1.toArray(iterable);

	  heapify(heap.comparator, items);
	  heap.items = items;
	  heap.size = items.length;

	  return heap;
	};

	MaxHeap.from = function(iterable, comparator) {
	  var heap = new MaxHeap(comparator);

	  var items;

	  // If iterable is an array, we can be clever about it
	  if (iterables$1.isArrayLike(iterable))
	    items = iterable.slice();
	  else
	    items = iterables$1.toArray(iterable);

	  heapify(heap.comparator, items);
	  heap.items = items;
	  heap.size = items.length;

	  return heap;
	};

	/**
	 * Exporting.
	 */
	Heap$3.siftUp = siftUp$1;
	Heap$3.siftDown = siftDown;
	Heap$3.push = push;
	Heap$3.pop = pop;
	Heap$3.replace = replace;
	Heap$3.pushpop = pushpop;
	Heap$3.heapify = heapify;
	Heap$3.consume = consume$1;

	Heap$3.nsmallest = nsmallest;
	Heap$3.nlargest = nlargest;

	Heap$3.MinHeap = Heap$3;
	Heap$3.MaxHeap = MaxHeap;

	var heap = Heap$3;

	var neighborhood = {};

	/**
	 * Graphology Neighborhood Indices
	 * ================================
	 */

	var typed$2 = typedArrays;
	var createEdgeWeightGetter$5 =
	  getters$1.createEdgeWeightGetter;

	function upperBoundPerMethod(method, graph) {
	  if (method === 'outbound' || method === 'inbound')
	    return graph.directedSize + graph.undirectedSize * 2;

	  if (method === 'in' || method === 'out' || method === 'directed')
	    return graph.directedSize;

	  return graph.undirectedSize * 2;
	}

	function NeighborhoodIndex$2(graph, method) {
	  method = method || 'outbound';
	  var getNeighbors = graph[method + 'Neighbors'].bind(graph);

	  var upperBound = upperBoundPerMethod(method, graph);

	  var NeighborhoodPointerArray = typed$2.getPointerArray(upperBound);
	  var NodesPointerArray = typed$2.getPointerArray(graph.order);

	  // NOTE: directedSize + undirectedSize * 2 is an upper bound for
	  // neighborhood size
	  this.graph = graph;
	  this.neighborhood = new NodesPointerArray(upperBound);

	  this.starts = new NeighborhoodPointerArray(graph.order + 1);

	  this.nodes = graph.nodes();

	  var ids = {};

	  var i, l, j, m, node, neighbors;

	  var n = 0;

	  for (i = 0, l = graph.order; i < l; i++) ids[this.nodes[i]] = i;

	  for (i = 0, l = graph.order; i < l; i++) {
	    node = this.nodes[i];
	    neighbors = getNeighbors(node);

	    this.starts[i] = n;

	    for (j = 0, m = neighbors.length; j < m; j++)
	      this.neighborhood[n++] = ids[neighbors[j]];
	  }

	  // NOTE: we keep one more index as upper bound to simplify iteration
	  this.starts[i] = upperBound;
	}

	NeighborhoodIndex$2.prototype.bounds = function (i) {
	  return [this.starts[i], this.starts[i + 1]];
	};

	NeighborhoodIndex$2.prototype.project = function () {
	  var self = this;

	  var projection = {};

	  self.nodes.forEach(function (node, i) {
	    projection[node] = Array.from(
	      self.neighborhood.slice(self.starts[i], self.starts[i + 1])
	    ).map(function (j) {
	      return self.nodes[j];
	    });
	  });

	  return projection;
	};

	NeighborhoodIndex$2.prototype.collect = function (results) {
	  var i, l;

	  var o = {};

	  for (i = 0, l = results.length; i < l; i++) o[this.nodes[i]] = results[i];

	  return o;
	};

	NeighborhoodIndex$2.prototype.assign = function (prop, results) {
	  var i = 0;

	  this.graph.updateEachNodeAttributes(
	    function (_, attr) {
	      attr[prop] = results[i++];

	      return attr;
	    },
	    {attributes: [prop]}
	  );
	};

	neighborhood.NeighborhoodIndex = NeighborhoodIndex$2;

	function WeightedNeighborhoodIndex$3(graph, getEdgeWeight, method) {
	  method = method || 'outbound';
	  var getEdges = graph[method + 'Edges'].bind(graph);

	  var upperBound = upperBoundPerMethod(method, graph);

	  var NeighborhoodPointerArray = typed$2.getPointerArray(upperBound);
	  var NodesPointerArray = typed$2.getPointerArray(graph.order);

	  var weightGetter = createEdgeWeightGetter$5(getEdgeWeight).fromMinimalEntry;

	  // NOTE: directedSize + undirectedSize * 2 is an upper bound for
	  // neighborhood size
	  this.graph = graph;
	  this.neighborhood = new NodesPointerArray(upperBound);
	  this.weights = new Float64Array(upperBound);
	  this.outDegrees = new Float64Array(graph.order);

	  this.starts = new NeighborhoodPointerArray(graph.order + 1);

	  this.nodes = graph.nodes();

	  var ids = {};

	  var i, l, j, m, node, neighbor, edges, edge, weight;

	  var n = 0;

	  for (i = 0, l = graph.order; i < l; i++) ids[this.nodes[i]] = i;

	  for (i = 0, l = graph.order; i < l; i++) {
	    node = this.nodes[i];
	    edges = getEdges(node);

	    this.starts[i] = n;

	    for (j = 0, m = edges.length; j < m; j++) {
	      edge = edges[j];
	      neighbor = graph.opposite(node, edge);
	      weight = weightGetter(edge, graph.getEdgeAttributes(edge));

	      // NOTE: for weighted mixed beware of merging weights if twice the same neighbor
	      this.neighborhood[n] = ids[neighbor];
	      this.weights[n++] = weight;
	      this.outDegrees[i] += weight;
	    }
	  }

	  // NOTE: we keep one more index as upper bound to simplify iteration
	  this.starts[i] = upperBound;
	}

	WeightedNeighborhoodIndex$3.prototype.bounds = NeighborhoodIndex$2.prototype.bounds;
	WeightedNeighborhoodIndex$3.prototype.project =
	  NeighborhoodIndex$2.prototype.project;
	WeightedNeighborhoodIndex$3.prototype.collect =
	  NeighborhoodIndex$2.prototype.collect;
	WeightedNeighborhoodIndex$3.prototype.assign = NeighborhoodIndex$2.prototype.assign;

	neighborhood.WeightedNeighborhoodIndex = WeightedNeighborhoodIndex$3;

	/**
	 * Graphology Indexed Brandes Routine
	 * ===================================
	 *
	 * Indexed version of the famous Brandes routine aiming at computing
	 * betweenness centrality efficiently.
	 */

	var FixedDeque$2 = fixedDeque;
	var FixedStack = fixedStack;
	var Heap$2 = heap;
	var typed$1 = typedArrays;
	var neighborhoodIndices = neighborhood;

	var NeighborhoodIndex$1 = neighborhoodIndices.NeighborhoodIndex;
	var WeightedNeighborhoodIndex$2 = neighborhoodIndices.WeightedNeighborhoodIndex;

	/**
	 * Indexed unweighted Brandes routine.
	 *
	 * [Reference]:
	 * Ulrik Brandes: A Faster Algorithm for Betweenness Centrality.
	 * Journal of Mathematical Sociology 25(2):163-177, 2001.
	 *
	 * @param  {Graph}    graph - The graphology instance.
	 * @return {function}
	 */
	indexedBrandes.createUnweightedIndexedBrandes =
	  function createUnweightedIndexedBrandes(graph) {
	    var neighborhoodIndex = new NeighborhoodIndex$1(graph);

	    var neighborhood = neighborhoodIndex.neighborhood,
	      starts = neighborhoodIndex.starts;

	    var order = graph.order;

	    var S = new FixedStack(typed$1.getPointerArray(order), order),
	      sigma = new Uint32Array(order),
	      P = new Array(order),
	      D = new Int32Array(order);

	    var Q = new FixedDeque$2(Uint32Array, order);

	    var brandes = function (sourceIndex) {
	      var Dv, sigmav, start, stop, j, v, w;

	      for (v = 0; v < order; v++) {
	        P[v] = [];
	        sigma[v] = 0;
	        D[v] = -1;
	      }

	      sigma[sourceIndex] = 1;
	      D[sourceIndex] = 0;

	      Q.push(sourceIndex);

	      while (Q.size !== 0) {
	        v = Q.shift();
	        S.push(v);

	        Dv = D[v];
	        sigmav = sigma[v];

	        start = starts[v];
	        stop = starts[v + 1];

	        for (j = start; j < stop; j++) {
	          w = neighborhood[j];

	          if (D[w] === -1) {
	            Q.push(w);
	            D[w] = Dv + 1;
	          }

	          if (D[w] === Dv + 1) {
	            sigma[w] += sigmav;
	            P[w].push(v);
	          }
	        }
	      }

	      return [S, P, sigma];
	    };

	    brandes.index = neighborhoodIndex;

	    return brandes;
	  };

	function BRANDES_DIJKSTRA_HEAP_COMPARATOR$1(a, b) {
	  if (a[0] > b[0]) return 1;
	  if (a[0] < b[0]) return -1;

	  if (a[1] > b[1]) return 1;
	  if (a[1] < b[1]) return -1;

	  if (a[2] > b[2]) return 1;
	  if (a[2] < b[2]) return -1;

	  if (a[3] > b[3]) return 1;
	  if (a[3] < b[3]) return -1;

	  return 0;
	}

	/**
	 * Indexed Dijkstra Brandes routine.
	 *
	 * [Reference]:
	 * Ulrik Brandes: A Faster Algorithm for Betweenness Centrality.
	 * Journal of Mathematical Sociology 25(2):163-177, 2001.
	 *
	 * @param  {Graph}    graph         - The graphology instance.
	 * @param  {string}   getEdgeWeight - Name of the weight attribute or getter function.
	 * @return {function}
	 */
	indexedBrandes.createDijkstraIndexedBrandes = function createDijkstraIndexedBrandes(
	  graph,
	  getEdgeWeight
	) {
	  var neighborhoodIndex = new WeightedNeighborhoodIndex$2(
	    graph,
	    getEdgeWeight || 'weight'
	  );

	  var neighborhood = neighborhoodIndex.neighborhood,
	    weights = neighborhoodIndex.weights,
	    starts = neighborhoodIndex.starts;

	  var order = graph.order;

	  var S = new FixedStack(typed$1.getPointerArray(order), order),
	    sigma = new Uint32Array(order),
	    P = new Array(order),
	    D = new Float64Array(order),
	    seen = new Float64Array(order);

	  // TODO: use fixed-size heap
	  var Q = new Heap$2(BRANDES_DIJKSTRA_HEAP_COMPARATOR$1);

	  var brandes = function (sourceIndex) {
	    var start, stop, item, dist, pred, cost, j, v, w;

	    var count = 0;

	    for (v = 0; v < order; v++) {
	      P[v] = [];
	      sigma[v] = 0;
	      D[v] = -1;
	      seen[v] = -1;
	    }

	    sigma[sourceIndex] = 1;
	    seen[sourceIndex] = 0;

	    Q.push([0, count++, sourceIndex, sourceIndex]);

	    while (Q.size !== 0) {
	      item = Q.pop();
	      dist = item[0];
	      pred = item[2];
	      v = item[3];

	      if (D[v] !== -1) continue;

	      S.push(v);
	      D[v] = dist;
	      sigma[v] += sigma[pred];

	      start = starts[v];
	      stop = starts[v + 1];

	      for (j = start; j < stop; j++) {
	        w = neighborhood[j];
	        cost = dist + weights[j];

	        if (D[w] === -1 && (seen[w] === -1 || cost < seen[w])) {
	          seen[w] = cost;
	          Q.push([cost, count++, v, w]);
	          sigma[w] = 0;
	          P[w] = [v];
	        } else if (cost === seen[w]) {
	          sigma[w] += sigma[v];
	          P[w].push(v);
	        }
	      }
	    }

	    return [S, P, sigma];
	  };

	  brandes.index = neighborhoodIndex;

	  return brandes;
	};

	/**
	 * Graphology Betweenness Centrality
	 * ==================================
	 *
	 * Function computing betweenness centrality.
	 */

	var isGraph$z = isGraph$N;
	var lib = indexedBrandes;
	var resolveDefaults$7 = defaults$7;

	var createUnweightedIndexedBrandes = lib.createUnweightedIndexedBrandes;
	var createDijkstraIndexedBrandes = lib.createDijkstraIndexedBrandes;

	/**
	 * Defaults.
	 */
	var DEFAULTS$9 = {
	  nodeCentralityAttribute: 'betweennessCentrality',
	  getEdgeWeight: 'weight',
	  normalized: true
	};

	/**
	 * Abstract function computing beetweenness centrality for the given graph.
	 *
	 * @param  {boolean} assign                      - Assign the results to node attributes?
	 * @param  {Graph}   graph                       - Target graph.
	 * @param  {object}  [options]                   - Options:
	 * @param  {object}    [nodeCentralityAttribute] - Name of the attribute to assign.
	 * @param  {string}    [getEdgeWeight]           - Name of the weight attribute or getter function.
	 * @param  {boolean}   [normalized]              - Should the centrality be normalized?
	 * @param  {object}
	 */
	function abstractBetweennessCentrality(assign, graph, options) {
	  if (!isGraph$z(graph))
	    throw new Error(
	      'graphology-centrality/beetweenness-centrality: the given graph is not a valid graphology instance.'
	    );

	  // Solving options
	  options = resolveDefaults$7(options, DEFAULTS$9);

	  var outputName = options.nodeCentralityAttribute;
	  var normalized = options.normalized;

	  var brandes = options.getEdgeWeight
	    ? createDijkstraIndexedBrandes(graph, options.getEdgeWeight)
	    : createUnweightedIndexedBrandes(graph);

	  var N = graph.order;

	  var result, S, P, sigma, coefficient, i, j, m, v, w;

	  var delta = new Float64Array(N);
	  var centralities = new Float64Array(N);

	  // Iterating over each node
	  for (i = 0; i < N; i++) {
	    result = brandes(i);

	    S = result[0];
	    P = result[1];
	    sigma = result[2];

	    // Accumulating
	    j = S.size;

	    while (j--) delta[S.items[S.size - j]] = 0;

	    while (S.size !== 0) {
	      w = S.pop();
	      coefficient = (1 + delta[w]) / sigma[w];

	      for (j = 0, m = P[w].length; j < m; j++) {
	        v = P[w][j];
	        delta[v] += sigma[v] * coefficient;
	      }

	      if (w !== i) centralities[w] += delta[w];
	    }
	  }

	  // Rescaling
	  var scale = null;

	  if (normalized) scale = N <= 2 ? null : 1 / ((N - 1) * (N - 2));
	  else scale = graph.type === 'undirected' ? 0.5 : null;

	  if (scale !== null) {
	    for (i = 0; i < N; i++) centralities[i] *= scale;
	  }

	  if (assign) return brandes.index.assign(outputName, centralities);

	  return brandes.index.collect(centralities);
	}

	/**
	 * Exporting.
	 */
	var betweennessCentrality = abstractBetweennessCentrality.bind(null, false);
	betweennessCentrality.assign = abstractBetweennessCentrality.bind(null, true);

	var betweenness = betweennessCentrality;

	/**
	 * Mnemonist SparseSet
	 * ====================
	 *
	 * JavaScript sparse set implemented on top of byte arrays.
	 *
	 * [Reference]: https://research.swtch.com/sparse
	 */

	var Iterator$1 = iterator,
	    getPointerArray = typedArrays.getPointerArray;

	/**
	 * SparseSet.
	 *
	 * @constructor
	 */
	function SparseSet$1(length) {

	  var ByteArray = getPointerArray(length);

	  // Properties
	  this.size = 0;
	  this.length = length;
	  this.dense = new ByteArray(length);
	  this.sparse = new ByteArray(length);
	}

	/**
	 * Method used to clear the structure.
	 *
	 * @return {undefined}
	 */
	SparseSet$1.prototype.clear = function() {
	  this.size = 0;
	};

	/**
	 * Method used to check the existence of a member in the set.
	 *
	 * @param  {number} member - Member to test.
	 * @return {SparseSet}
	 */
	SparseSet$1.prototype.has = function(member) {
	  var index = this.sparse[member];

	  return (
	    index < this.size &&
	    this.dense[index] === member
	  );
	};

	/**
	 * Method used to add a member to the set.
	 *
	 * @param  {number} member - Member to add.
	 * @return {SparseSet}
	 */
	SparseSet$1.prototype.add = function(member) {
	  var index = this.sparse[member];

	  if (index < this.size && this.dense[index] === member)
	    return this;

	  this.dense[this.size] = member;
	  this.sparse[member] = this.size;
	  this.size++;

	  return this;
	};

	/**
	 * Method used to remove a member from the set.
	 *
	 * @param  {number} member - Member to delete.
	 * @return {boolean}
	 */
	SparseSet$1.prototype.delete = function(member) {
	  var index = this.sparse[member];

	  if (index >= this.size || this.dense[index] !== member)
	    return false;

	  index = this.dense[this.size - 1];
	  this.dense[this.sparse[member]] = index;
	  this.sparse[index] = this.sparse[member];
	  this.size--;

	  return true;
	};

	/**
	 * Method used to iterate over the set's values.
	 *
	 * @param  {function}  callback - Function to call for each item.
	 * @param  {object}    scope    - Optional scope.
	 * @return {undefined}
	 */
	SparseSet$1.prototype.forEach = function(callback, scope) {
	  scope = arguments.length > 1 ? scope : this;

	  var item;

	  for (var i = 0; i < this.size; i++) {
	    item = this.dense[i];

	    callback.call(scope, item, item);
	  }
	};

	/**
	 * Method used to create an iterator over a set's values.
	 *
	 * @return {Iterator}
	 */
	SparseSet$1.prototype.values = function() {
	  var size = this.size,
	      dense = this.dense,
	      i = 0;

	  return new Iterator$1(function() {
	    if (i < size) {
	      var item = dense[i];
	      i++;

	      return {
	        value: item
	      };
	    }

	    return {
	      done: true
	    };
	  });
	};

	/**
	 * Attaching the #.values method to Symbol.iterator if possible.
	 */
	if (typeof Symbol !== 'undefined')
	  SparseSet$1.prototype[Symbol.iterator] = SparseSet$1.prototype.values;

	/**
	 * Convenience known methods.
	 */
	SparseSet$1.prototype.inspect = function() {
	  var proxy = new Set();

	  for (var i = 0; i < this.size; i++)
	    proxy.add(this.dense[i]);

	  // Trick so that node displays the name of the constructor
	  Object.defineProperty(proxy, 'constructor', {
	    value: SparseSet$1,
	    enumerable: false
	  });

	  proxy.length = this.length;

	  return proxy;
	};

	if (typeof Symbol !== 'undefined')
	  SparseSet$1.prototype[Symbol.for('nodejs.util.inspect.custom')] = SparseSet$1.prototype.inspect;

	/**
	 * Exporting.
	 */
	var sparseSet = SparseSet$1;

	/**
	 * Graphology Closeness Centrality
	 * ================================
	 *
	 * JavaScript implementation of the closeness centrality
	 *
	 * [References]:
	 * https://en.wikipedia.org/wiki/Closeness_centrality
	 *
	 * Linton C. Freeman: Centrality in networks: I.
	 * Conceptual clarification. Social Networks 1:215-239, 1979.
	 * https://doi.org/10.1016/0378-8733(78)90021-7
	 *
	 * pg. 201 of Wasserman, S. and Faust, K.,
	 * Social Network Analysis: Methods and Applications, 1994,
	 * Cambridge University Press.
	 */

	var isGraph$y = isGraph$N;
	var resolveDefaults$6 = defaults$7;
	var FixedDeque$1 = fixedDeque;
	var SparseSet = sparseSet;
	var NeighborhoodIndex =
	  neighborhood.NeighborhoodIndex;

	// TODO: can be computed for a single node
	// TODO: weighted
	// TODO: abstract the single source indexed shortest path in lib
	// TODO: what about self loops?
	// TODO: refactor a BFSQueue working on integer ranges in graphology-indices?

	/**
	 * Defaults.
	 */
	var DEFAULTS$8 = {
	  nodeCentralityAttribute: 'closenessCentrality',
	  wassermanFaust: false
	};

	/**
	 * Helpers.
	 */
	function IndexedBFS(graph) {
	  this.index = new NeighborhoodIndex(graph, 'inbound');
	  this.queue = new FixedDeque$1(Array, graph.order);
	  this.seen = new SparseSet(graph.order);
	}

	IndexedBFS.prototype.fromNode = function (i) {
	  var index = this.index;
	  var queue = this.queue;
	  var seen = this.seen;

	  seen.clear();
	  queue.clear();

	  seen.add(i);
	  queue.push([i, 0]);

	  var item, n, d, j, l, neighbor;

	  var total = 0;
	  var count = 0;

	  while (queue.size !== 0) {
	    item = queue.shift();
	    n = item[0];
	    d = item[1];

	    if (d !== 0) {
	      total += d;
	      count += 1;
	    }

	    l = index.starts[n + 1];

	    for (j = index.starts[n]; j < l; j++) {
	      neighbor = index.neighborhood[j];

	      if (seen.has(neighbor)) continue;

	      seen.add(neighbor);
	      queue.push([neighbor, d + 1]);
	    }
	  }

	  return [count, total];
	};

	/**
	 * Abstract function computing the closeness centrality of a graph's nodes.
	 *
	 * @param  {boolean}  assign        - Should we assign the result to nodes.
	 * @param  {Graph}    graph         - Target graph.
	 * @param  {?object}  option        - Options:
	 * @param  {?string}   nodeCentralityAttribute - Name of the centrality attribute to assign.
	 * @param  {?boolean}  wassermanFaust - Whether to compute the Wasserman & Faust
	 *                                      variant of the metric.
	 * @return {object|undefined}
	 */
	function abstractClosenessCentrality(assign, graph, options) {
	  if (!isGraph$y(graph))
	    throw new Error(
	      'graphology-metrics/centrality/closeness: the given graph is not a valid graphology instance.'
	    );

	  options = resolveDefaults$6(options, DEFAULTS$8);

	  var wassermanFaust = options.wassermanFaust;

	  var bfs = new IndexedBFS(graph);

	  var N = graph.order;

	  var i, result, count, total, closeness;

	  var mapping = new Float64Array(N);

	  for (i = 0; i < N; i++) {
	    result = bfs.fromNode(i);
	    count = result[0];
	    total = result[1];

	    closeness = 0;

	    if (total > 0 && N > 1) {
	      closeness = count / total;

	      if (wassermanFaust) {
	        closeness *= count / (N - 1);
	      }
	    }

	    mapping[i] = closeness;
	  }

	  if (assign) {
	    return bfs.index.assign(options.nodeCentralityAttribute, mapping);
	  }

	  return bfs.index.collect(mapping);
	}

	/**
	 * Exporting.
	 */
	var closenessCentrality = abstractClosenessCentrality.bind(null, false);
	closenessCentrality.assign = abstractClosenessCentrality.bind(null, true);

	var closeness = closenessCentrality;

	/**
	 * Graphology Eigenvector Centrality
	 * ==================================
	 *
	 * JavaScript implementation of the eigenvector centrality.
	 *
	 * [References]:
	 * https://en.wikipedia.org/wiki/Eigenvector_centrality
	 *
	 * Phillip Bonacich. "Power and Centrality: A Family of Measures."
	 * American Journal of Sociology, 92(5):1170–1182, 1986
	 * http://www.leonidzhukov.net/hse/2014/socialnetworks/papers/Bonacich-Centrality.pdf
	 *
	 * Mark E. J. Newman.
	 * Networks: An Introduct *
	 * Oxford University Press, USA, 2010, pp. 169.
	 */

	var isGraph$x = isGraph$N;
	var resolveDefaults$5 = defaults$7;
	var WeightedNeighborhoodIndex$1 =
	  neighborhood.WeightedNeighborhoodIndex;

	/**
	 * Defaults.
	 */
	var DEFAULTS$7 = {
	  nodeCentralityAttribute: 'eigenvectorCentrality',
	  getEdgeWeight: 'weight',
	  maxIterations: 100,
	  tolerance: 1e-6
	};

	/**
	 * Helpers.
	 */
	function safeVariadicHypot(x) {
	  var max = 0;
	  var s = 0;

	  for (var i = 0, l = x.length; i < l; i++) {
	    var n = Math.abs(x[i]);

	    if (n > max) {
	      s *= (max / n) * (max / n);
	      max = n;
	    }
	    s += n === 0 && max === 0 ? 0 : (n / max) * (n / max);
	  }

	  // NOTE: In case of numerical error we'll assume the norm is 1 in our case!
	  return max === Infinity ? 1 : max * Math.sqrt(s);
	}

	/**
	 * Abstract function computing the eigenvector centrality of a graph's nodes.
	 *
	 * @param  {boolean}  assign        - Should we assign the result to nodes.
	 * @param  {Graph}    graph         - Target graph.
	 * @param  {?object}  option        - Options:
	 * @param  {?string}    nodeCentralityAttribute - Name of the centrality attribute to assign.
	 * @param  {?string}    getEdgeWeight - Name of the weight algorithm or getter function.
	 * @param  {?number}    maxIterations - Maximum number of iterations to perform.
	 * @param  {?number}    tolerance     - Error tolerance when checking for convergence.
	 * @return {object|undefined}
	 */
	function abstractEigenvectorCentrality(assign, graph, options) {
	  if (!isGraph$x(graph))
	    throw new Error(
	      'graphology-metrics/centrality/eigenvector: the given graph is not a valid graphology instance.'
	    );

	  options = resolveDefaults$5(options, DEFAULTS$7);

	  var maxIterations = options.maxIterations;
	  var tolerance = options.tolerance;

	  var N = graph.order;

	  var index = new WeightedNeighborhoodIndex$1(graph, options.getEdgeWeight);

	  var i, j, l, w;

	  var x = new Float64Array(graph.order);

	  // Initializing
	  for (i = 0; i < N; i++) {
	    x[i] = 1 / N;
	  }

	  // Power iterations
	  var iteration = 0;
	  var error = 0;
	  var neighbor, xLast, norm;
	  var converged = false;

	  while (iteration < maxIterations) {
	    xLast = x;
	    x = new Float64Array(xLast);

	    for (i = 0; i < N; i++) {
	      l = index.starts[i + 1];

	      for (j = index.starts[i]; j < l; j++) {
	        neighbor = index.neighborhood[j];
	        w = index.weights[j];
	        x[neighbor] += xLast[i] * w;
	      }
	    }

	    norm = safeVariadicHypot(x);

	    for (i = 0; i < N; i++) {
	      x[i] /= norm;
	    }

	    // Checking convergence
	    error = 0;

	    for (i = 0; i < N; i++) {
	      error += Math.abs(x[i] - xLast[i]);
	    }

	    if (error < N * tolerance) {
	      converged = true;
	      break;
	    }

	    iteration++;
	  }

	  if (!converged)
	    throw Error(
	      'graphology-metrics/centrality/eigenvector: failed to converge.'
	    );

	  if (assign) {
	    index.assign(options.nodeCentralityAttribute, x);
	    return;
	  }

	  return index.collect(x);
	}

	/**
	 * Exporting.
	 */
	var eigenvectorCentrality = abstractEigenvectorCentrality.bind(null, false);
	eigenvectorCentrality.assign = abstractEigenvectorCentrality.bind(null, true);

	var eigenvector = eigenvectorCentrality;

	/**
	 * Graphology HITS Algorithm
	 * ==========================
	 *
	 * Implementation of the HITS algorithm for the graphology specs.
	 */

	var resolveDefaults$4 = defaults$7;
	var isGraph$w = isGraph$N;
	var createEdgeWeightGetter$4 =
	  getters$1.createEdgeWeightGetter;

	// TODO: optimize using NeighborhoodIndex

	/**
	 * Defaults.
	 */
	var DEFAULTS$6 = {
	  nodeAuthorityAttribute: 'authority',
	  nodeHubAttribute: 'hub',
	  getEdgeWeight: 'weight',
	  maxIterations: 100,
	  normalize: true,
	  tolerance: 1e-8
	};

	/**
	 * Function returning an object with the given keys set to the given value.
	 *
	 * @param  {array}  keys  - Keys to set.
	 * @param  {number} value - Value to set.
	 * @return {object}       - The created object.
	 */
	function dict(keys, value) {
	  var o = Object.create(null);

	  var i, l;

	  for (i = 0, l = keys.length; i < l; i++) o[keys[i]] = value;

	  return o;
	}

	/**
	 * Function returning the sum of an object's values.
	 *
	 * @param  {object} o - Target object.
	 * @return {number}   - The sum.
	 */
	function sum(o) {
	  var nb = 0;

	  for (var k in o) nb += o[k];

	  return nb;
	}

	/**
	 * HITS function taking a Graph instance & some options and returning a map
	 * of nodes to their hubs & authorities.
	 *
	 * @param  {boolean} assign    - Should we assign the results as node attributes?
	 * @param  {Graph}   graph     - A Graph instance.
	 * @param  {object}  [options] - Options:
	 * @param  {number}    [maxIterations] - Maximum number of iterations to perform.
	 * @param  {boolean}   [normalize]     - Whether to normalize the results by the
	 *                                       sum of all values.
	 * @param  {number}    [tolerance]     - Error tolerance used to check
	 *                                       convergence in power method iteration.
	 */
	function hits(assign, graph, options) {
	  if (!isGraph$w(graph))
	    throw new Error(
	      'graphology-hits: the given graph is not a valid graphology instance.'
	    );

	  if (graph.multi)
	    throw new Error(
	      'graphology-hits: the HITS algorithm does not work with MultiGraphs.'
	    );

	  options = resolveDefaults$4(options, DEFAULTS$6);

	  var getEdgeWeight = createEdgeWeightGetter$4(options.getEdgeWeight).fromEntry;

	  // Variables
	  var order = graph.order;
	  var nodes = graph.nodes();
	  var edges;
	  var hubs = dict(nodes, 1 / order);
	  var weights = {};
	  var converged = false;
	  var lastHubs;
	  var authorities;

	  // Iteration variables
	  var node, neighbor, edge, iteration, maxAuthority, maxHub, error, S, i, j, m;

	  // Indexing weights
	  graph.forEachEdge(function (e, a, s, t, sa, ta, u) {
	    weights[e] = getEdgeWeight(e, a, s, t, sa, ta, u);
	  });

	  // Performing iterations
	  for (iteration = 0; iteration < options.maxIterations; iteration++) {
	    lastHubs = hubs;
	    hubs = dict(nodes, 0);
	    authorities = dict(nodes, 0);
	    maxHub = 0;
	    maxAuthority = 0;

	    // Iterating over nodes to update authorities
	    for (i = 0; i < order; i++) {
	      node = nodes[i];
	      edges = graph.outboundEdges(node);

	      // Iterating over neighbors
	      for (j = 0, m = edges.length; j < m; j++) {
	        edge = edges[j];
	        neighbor = graph.opposite(node, edge);

	        authorities[neighbor] += lastHubs[node] * weights[edge];

	        if (authorities[neighbor] > maxAuthority)
	          maxAuthority = authorities[neighbor];
	      }
	    }

	    // Iterating over nodes to update hubs
	    for (i = 0; i < order; i++) {
	      node = nodes[i];
	      edges = graph.outboundEdges(node);

	      for (j = 0, m = edges.length; j < m; j++) {
	        edge = edges[j];
	        neighbor = graph.opposite(node, edge);

	        hubs[node] += authorities[neighbor] * weights[edge];

	        if (hubs[neighbor] > maxHub) maxHub = hubs[neighbor];
	      }
	    }

	    // Normalizing
	    S = 1 / maxHub;

	    for (node in hubs) hubs[node] *= S;

	    S = 1 / maxAuthority;

	    for (node in authorities) authorities[node] *= S;

	    // Checking convergence
	    error = 0;

	    for (node in hubs) error += Math.abs(hubs[node] - lastHubs[node]);

	    if (error < options.tolerance) {
	      converged = true;
	      break;
	    }
	  }

	  if (!converged)
	    throw Error('graphology-metrics/centrality/hits: failed to converge.');

	  // Should we normalize the result?
	  if (options.normalize) {
	    S = 1 / sum(authorities);

	    for (node in authorities) authorities[node] *= S;

	    S = 1 / sum(hubs);

	    for (node in hubs) hubs[node] *= S;
	  }

	  // Should we assign the results to the graph?
	  if (assign) {
	    graph.updateEachNodeAttributes(
	      function (n, attr) {
	        attr[options.nodeAuthorityAttribute] = authorities[n];
	        attr[options.nodeHubAttribute] = hubs[n];

	        return attr;
	      },
	      {
	        attributes: [options.nodeAuthorityAttribute, options.nodeHubAttribute]
	      }
	    );

	    return;
	  }

	  return {hubs: hubs, authorities: authorities};
	}

	/**
	 * Exporting.
	 */
	var main = hits.bind(null, false);
	main.assign = hits.bind(null, true);

	var hits_1 = main;

	/**
	 * Graphology Pagerank
	 * ====================
	 *
	 * JavaScript implementation of the pagerank algorithm for graphology.
	 *
	 * [Reference]:
	 * Page, Lawrence; Brin, Sergey; Motwani, Rajeev and Winograd, Terry,
	 * The PageRank citation ranking: Bringing order to the Web. 1999
	 */

	var isGraph$v = isGraph$N;
	var resolveDefaults$3 = defaults$7;
	var WeightedNeighborhoodIndex =
	  neighborhood.WeightedNeighborhoodIndex;

	/**
	 * Defaults.
	 */
	var DEFAULTS$5 = {
	  nodePagerankAttribute: 'pagerank',
	  getEdgeWeight: 'weight',
	  alpha: 0.85,
	  maxIterations: 100,
	  tolerance: 1e-6
	};

	/**
	 * Abstract function applying the pagerank algorithm to the given graph.
	 *
	 * @param  {boolean}  assign        - Should we assign the result to nodes.
	 * @param  {Graph}    graph         - Target graph.
	 * @param  {?object}  option        - Options:
	 * @param  {?object}    attributes  - Custom attribute names:
	 * @param  {?string}      pagerank  - Name of the pagerank attribute to assign.
	 * @param  {?string}      weight    - Name of the weight algorithm.
	 * @param  {?number}  alpha         - Damping parameter.
	 * @param  {?number}  maxIterations - Maximum number of iterations to perform.
	 * @param  {?number}  tolerance     - Error tolerance when checking for convergence.
	 * @param  {?boolean} weighted      - Should we use the graph's weights.
	 * @return {object|undefined}
	 */
	function abstractPagerank(assign, graph, options) {
	  if (!isGraph$v(graph))
	    throw new Error(
	      'graphology-metrics/centrality/pagerank: the given graph is not a valid graphology instance.'
	    );

	  options = resolveDefaults$3(options, DEFAULTS$5);

	  var alpha = options.alpha;
	  var maxIterations = options.maxIterations;
	  var tolerance = options.tolerance;

	  var pagerankAttribute = options.nodePagerankAttribute;

	  var N = graph.order;
	  var p = 1 / N;

	  var index = new WeightedNeighborhoodIndex(graph, options.getEdgeWeight);

	  var i, j, l, d;

	  var x = new Float64Array(graph.order);

	  // Normalizing edge weights & indexing dangling nodes
	  var normalizedEdgeWeights = new Float64Array(index.weights.length);
	  var danglingNodes = [];

	  for (i = 0; i < N; i++) {
	    x[i] = p;
	    l = index.starts[i + 1];
	    d = index.outDegrees[i];

	    if (d === 0) danglingNodes.push(i);

	    for (j = index.starts[i]; j < l; j++) {
	      normalizedEdgeWeights[j] = index.weights[j] / d;
	    }
	  }

	  // Power iterations
	  var iteration = 0;
	  var error = 0;
	  var dangleSum, neighbor, xLast;
	  var converged = false;

	  while (iteration < maxIterations) {
	    xLast = x;
	    x = new Float64Array(graph.order); // TODO: it should be possible to swap two arrays to avoid allocations (bench)

	    dangleSum = 0;

	    for (i = 0, l = danglingNodes.length; i < l; i++)
	      dangleSum += xLast[danglingNodes[i]];

	    dangleSum *= alpha;

	    for (i = 0; i < N; i++) {
	      l = index.starts[i + 1];

	      for (j = index.starts[i]; j < l; j++) {
	        neighbor = index.neighborhood[j];
	        x[neighbor] += alpha * xLast[i] * normalizedEdgeWeights[j];
	      }

	      x[i] += dangleSum * p + (1 - alpha) * p;
	    }

	    // Checking convergence
	    error = 0;

	    for (i = 0; i < N; i++) {
	      error += Math.abs(x[i] - xLast[i]);
	    }

	    if (error < N * tolerance) {
	      converged = true;
	      break;
	    }

	    iteration++;
	  }

	  if (!converged)
	    throw Error('graphology-metrics/centrality/pagerank: failed to converge.');

	  if (assign) {
	    index.assign(pagerankAttribute, x);
	    return;
	  }

	  return index.collect(x);
	}

	/**
	 * Exporting.
	 */
	var pagerank = abstractPagerank.bind(null, false);
	pagerank.assign = abstractPagerank.bind(null, true);

	var pagerank_1 = pagerank;

	/**
	 * Graphology Metrics Centrality
	 * ==============================
	 *
	 * Sub module endpoint.
	 */

	var degree = degree$1;

	centrality.betweenness = betweenness;
	centrality.closeness = closeness;
	centrality.eigenvector = eigenvector;
	centrality.hits = hits_1;
	centrality.pagerank = pagerank_1;

	centrality.degree = degree.degreeCentrality;
	centrality.inDegree = degree.inDegreeCentrality;
	centrality.outDegree = degree.outDegreeCentrality;

	var edge = {};

	/**
	 * Graphology Edge Disparity
	 * ==========================
	 *
	 * Function computing the disparity score of each edge in the given graph. This
	 * score is typically used to extract the multiscale backbone of a weighted
	 * graph.
	 *
	 * The formula from the paper (relying on integral calculus) can be simplified
	 * to become:
	 *
	 *   disparity(u, v) = min(
	 *     (1 - normalizedWeight(u, v)) ^ (degree(u) - 1)),
	 *     (1 - normalizedWeight(v, u)) ^ (degree(v) - 1))
	 *   )
	 *
	 *   where normalizedWeight(u, v) = weight(u, v) / weightedDegree(u)
	 *   where weightedDegree(u) = sum(weight(u, v) for v in neighbors(u))
	 *
	 * This score can sometimes be found reversed likewise:
	 *
	 *   disparity(u, v) = max(
	 *     1 - (1 - normalizedWeight(u, v)) ^ (degree(u) - 1)),
	 *     1 - (1 - normalizedWeight(v, u)) ^ (degree(v) - 1))
	 *   )
	 *
	 * so that higher score means better edges. I chose to keep the metric close
	 * to the paper to keep the statistical test angle. This means that, in my
	 * implementation at least, a low score for an edge means a high relevance and
	 * increases its chances to be kept in the backbone.
	 *
	 * Note that this algorithm has no proper definition for directed graphs and
	 * is only useful if edges have varying weights. This said, it could be
	 * possible to compute the disparity score only based on edge direction, if
	 * we drop the min part.
	 *
	 * [Article]:
	 * Serrano, M. Ángeles, Marián Boguná, and Alessandro Vespignani. "Extracting
	 * the multiscale backbone of complex weighted networks." Proceedings of the
	 * national academy of sciences 106.16 (2009): 6483-6488.
	 *
	 * [Reference]:
	 * https://www.pnas.org/content/pnas/106/16/6483.full.pdf
	 * https://en.wikipedia.org/wiki/Disparity_filter_algorithm_of_weighted_network
	 */

	var isGraph$u = isGraph$N;
	var inferType$2 = inferType$4;
	var resolveDefaults$2 = defaults$7;
	var createEdgeWeightGetter$3 =
	  getters$1.createEdgeWeightGetter;

	/**
	 * Defaults.
	 */
	var DEFAULTS$4 = {
	  edgeDisparityAttribute: 'disparity',
	  getEdgeWeight: 'weight'
	};

	// TODO: test without weight, to see what happens

	function abstractDisparity(assign, graph, options) {
	  if (!isGraph$u(graph))
	    throw new Error(
	      'graphology-metrics/edge/disparity: the given graph is not a valid graphology instance.'
	    );

	  if (graph.multi || inferType$2(graph) === 'mixed')
	    throw new Error(
	      'graphology-metrics/edge/disparity: not defined for multi nor mixed graphs.'
	    );

	  options = resolveDefaults$2(options, DEFAULTS$4);

	  var getEdgeWeight = createEdgeWeightGetter$3(options.getEdgeWeight).fromEntry;

	  // Computing node weighted degrees
	  var weightedDegrees = {};

	  graph.forEachNode(function (node) {
	    weightedDegrees[node] = 0;
	  });

	  graph.forEachEdge(function (edge, attr, source, target, sa, ta, undirected) {
	    var weight = getEdgeWeight(edge, attr, source, target, sa, ta, undirected);

	    weightedDegrees[source] += weight;
	    weightedDegrees[target] += weight;
	  });

	  // Computing edge disparity
	  var previous, previousDegree, previousWeightedDegree;

	  var disparities = {};

	  graph.forEachAssymetricAdjacencyEntry(function (
	    source,
	    target,
	    sa,
	    ta,
	    edge,
	    attr,
	    undirected
	  ) {
	    var weight = getEdgeWeight(edge, attr, source, target, sa, ta, undirected);

	    if (previous !== source) {
	      previous = source;
	      previousDegree = graph.degree(source);
	      previousWeightedDegree = weightedDegrees[source];
	    }

	    var targetDegree = graph.degree(target);
	    var targetWeightedDegree = weightedDegrees[target];

	    var normalizedWeightPerSource = weight / previousWeightedDegree;
	    var normalizedWeightPerTarget = weight / targetWeightedDegree;

	    var sourceScore = Math.pow(
	      1 - normalizedWeightPerSource,
	      previousDegree - 1
	    );

	    var targetScore = Math.pow(1 - normalizedWeightPerTarget, targetDegree - 1);

	    disparities[edge] = Math.min(sourceScore, targetScore);
	  });

	  if (assign) {
	    graph.updateEachEdgeAttributes(
	      function (edge, attr) {
	        attr[options.edgeDisparityAttribute] = disparities[edge];
	        return attr;
	      },
	      {attributes: [options.edgeDisparityAttribute]}
	    );

	    return;
	  }

	  return disparities;
	}

	var disparity = abstractDisparity.bind(null, false);
	disparity.assign = abstractDisparity.bind(null, true);

	/**
	 * Exporting.
	 */
	var disparity_1 = disparity;

	var set = {};

	/**
	 * Mnemonist Set
	 * ==============
	 *
	 * Useful function related to sets such as union, intersection and so on...
	 */

	(function (exports) {
	// TODO: optimize versions for less variadicities

	/**
	 * Variadic function computing the intersection of multiple sets.
	 *
	 * @param  {...Set} sets - Sets to intersect.
	 * @return {Set}         - The intesection.
	 */
	exports.intersection = function() {
	  if (arguments.length < 2)
	    throw new Error('mnemonist/Set.intersection: needs at least two arguments.');

	  var I = new Set();

	  // First we need to find the smallest set
	  var smallestSize = Infinity,
	      smallestSet = null;

	  var s, i, l = arguments.length;

	  for (i = 0; i < l; i++) {
	    s = arguments[i];

	    // If one of the set has no items, we can stop right there
	    if (s.size === 0)
	      return I;

	    if (s.size < smallestSize) {
	      smallestSize = s.size;
	      smallestSet = s;
	    }
	  }

	  // Now we need to intersect this set with the others
	  var iterator = smallestSet.values(),
	      step,
	      item,
	      add,
	      set;

	  // TODO: we can optimize by iterating each next time over the current intersection
	  // but this probably means more RAM to consume since we'll create n-1 sets rather than
	  // only the one.
	  while ((step = iterator.next(), !step.done)) {
	    item = step.value;
	    add = true;

	    for (i = 0; i < l; i++) {
	      set = arguments[i];

	      if (set === smallestSet)
	        continue;

	      if (!set.has(item)) {
	        add = false;
	        break;
	      }
	    }

	    if (add)
	      I.add(item);
	  }

	  return I;
	};

	/**
	 * Variadic function computing the union of multiple sets.
	 *
	 * @param  {...Set} sets - Sets to unite.
	 * @return {Set}         - The union.
	 */
	exports.union = function() {
	  if (arguments.length < 2)
	    throw new Error('mnemonist/Set.union: needs at least two arguments.');

	  var U = new Set();

	  var i, l = arguments.length;

	  var iterator,
	      step;

	  for (i = 0; i < l; i++) {
	    iterator = arguments[i].values();

	    while ((step = iterator.next(), !step.done))
	      U.add(step.value);
	  }

	  return U;
	};

	/**
	 * Function computing the difference between two sets.
	 *
	 * @param  {Set} A - First set.
	 * @param  {Set} B - Second set.
	 * @return {Set}   - The difference.
	 */
	exports.difference = function(A, B) {

	  // If first set is empty
	  if (!A.size)
	    return new Set();

	  if (!B.size)
	    return new Set(A);

	  var D = new Set();

	  var iterator = A.values(),
	      step;

	  while ((step = iterator.next(), !step.done)) {
	    if (!B.has(step.value))
	      D.add(step.value);
	  }

	  return D;
	};

	/**
	 * Function computing the symmetric difference between two sets.
	 *
	 * @param  {Set} A - First set.
	 * @param  {Set} B - Second set.
	 * @return {Set}   - The symmetric difference.
	 */
	exports.symmetricDifference = function(A, B) {
	  var S = new Set();

	  var iterator = A.values(),
	      step;

	  while ((step = iterator.next(), !step.done)) {
	    if (!B.has(step.value))
	      S.add(step.value);
	  }

	  iterator = B.values();

	  while ((step = iterator.next(), !step.done)) {
	    if (!A.has(step.value))
	      S.add(step.value);
	  }

	  return S;
	};

	/**
	 * Function returning whether A is a subset of B.
	 *
	 * @param  {Set} A - First set.
	 * @param  {Set} B - Second set.
	 * @return {boolean}
	 */
	exports.isSubset = function(A, B) {
	  var iterator = A.values(),
	      step;

	  // Shortcuts
	  if (A === B)
	    return true;

	  if (A.size > B.size)
	    return false;

	  while ((step = iterator.next(), !step.done)) {
	    if (!B.has(step.value))
	      return false;
	  }

	  return true;
	};

	/**
	 * Function returning whether A is a superset of B.
	 *
	 * @param  {Set} A - First set.
	 * @param  {Set} B - Second set.
	 * @return {boolean}
	 */
	exports.isSuperset = function(A, B) {
	  return exports.isSubset(B, A);
	};

	/**
	 * Function adding the items of set B to the set A.
	 *
	 * @param  {Set} A - First set.
	 * @param  {Set} B - Second set.
	 */
	exports.add = function(A, B) {
	  var iterator = B.values(),
	      step;

	  while ((step = iterator.next(), !step.done))
	    A.add(step.value);

	  return;
	};

	/**
	 * Function subtracting the items of set B from the set A.
	 *
	 * @param  {Set} A - First set.
	 * @param  {Set} B - Second set.
	 */
	exports.subtract = function(A, B) {
	  var iterator = B.values(),
	      step;

	  while ((step = iterator.next(), !step.done))
	    A.delete(step.value);

	  return;
	};

	/**
	 * Function intersecting the items of A & B.
	 *
	 * @param  {Set} A - First set.
	 * @param  {Set} B - Second set.
	 */
	exports.intersect = function(A, B) {
	  var iterator = A.values(),
	      step;

	  while ((step = iterator.next(), !step.done)) {
	    if (!B.has(step.value))
	      A.delete(step.value);
	  }

	  return;
	};

	/**
	 * Function disjuncting the items of A & B.
	 *
	 * @param  {Set} A - First set.
	 * @param  {Set} B - Second set.
	 */
	exports.disjunct = function(A, B) {
	  var iterator = A.values(),
	      step;

	  var toRemove = [];

	  while ((step = iterator.next(), !step.done)) {
	    if (B.has(step.value))
	      toRemove.push(step.value);
	  }

	  iterator = B.values();

	  while ((step = iterator.next(), !step.done)) {
	    if (!A.has(step.value))
	      A.add(step.value);
	  }

	  for (var i = 0, l = toRemove.length; i < l; i++)
	    A.delete(toRemove[i]);

	  return;
	};

	/**
	 * Function returning the size of the intersection of A & B.
	 *
	 * @param  {Set} A - First set.
	 * @param  {Set} B - Second set.
	 * @return {number}
	 */
	exports.intersectionSize = function(A, B) {
	  var tmp;

	  // We need to know the smallest set
	  if (A.size > B.size) {
	    tmp = A;
	    A = B;
	    B = tmp;
	  }

	  if (A.size === 0)
	    return 0;

	  if (A === B)
	    return A.size;

	  var iterator = A.values(),
	      step;

	  var I = 0;

	  while ((step = iterator.next(), !step.done)) {
	    if (B.has(step.value))
	      I++;
	  }

	  return I;
	};

	/**
	 * Function returning the size of the union of A & B.
	 *
	 * @param  {Set} A - First set.
	 * @param  {Set} B - Second set.
	 * @return {number}
	 */
	exports.unionSize = function(A, B) {
	  var I = exports.intersectionSize(A, B);

	  return A.size + B.size - I;
	};

	/**
	 * Function returning the Jaccard similarity between A & B.
	 *
	 * @param  {Set} A - First set.
	 * @param  {Set} B - Second set.
	 * @return {number}
	 */
	exports.jaccard = function(A, B) {
	  var I = exports.intersectionSize(A, B);

	  if (I === 0)
	    return 0;

	  var U = A.size + B.size - I;

	  return I / U;
	};

	/**
	 * Function returning the overlap coefficient between A & B.
	 *
	 * @param  {Set} A - First set.
	 * @param  {Set} B - Second set.
	 * @return {number}
	 */
	exports.overlap = function(A, B) {
	  var I = exports.intersectionSize(A, B);

	  if (I === 0)
	    return 0;

	  return I / Math.min(A.size, B.size);
	};
	}(set));

	/**
	 * Graphology Simmelian Strength
	 * ==============================
	 *
	 * Function computing the Simmelian strength, i.e. the number of triangles in
	 * which an edge stands, for each edge in a given graph.
	 */

	var isGraph$t = isGraph$N;
	var intersectionSize$1 = set.intersectionSize;

	function abstractSimmelianStrength(assign, graph) {
	  if (!isGraph$t(graph))
	    throw new Error(
	      'graphology-metrics/simmelian-strength: given graph is not a valid graphology instance.'
	    );

	  // Indexing neighborhoods
	  var neighborhoods = {};

	  graph.forEachNode(function (node) {
	    neighborhoods[node] = new Set(graph.neighbors(node));
	  });

	  if (!assign) {
	    var strengths = {};

	    graph.forEachEdge(function (edge, _, source, target) {
	      strengths[edge] = intersectionSize$1(
	        neighborhoods[source],
	        neighborhoods[target]
	      );
	    });

	    return strengths;
	  }

	  graph.updateEachEdgeAttributes(
	    function (_, attr, source, target) {
	      attr.simmelianStrength = intersectionSize$1(
	        neighborhoods[source],
	        neighborhoods[target]
	      );

	      return attr;
	    },
	    {attributes: ['simmelianStrength']}
	  );
	}

	var simmelianStrength = abstractSimmelianStrength.bind(null, false);
	simmelianStrength.assign = abstractSimmelianStrength.bind(null, true);

	var simmelianStrength_1 = simmelianStrength;

	edge.disparity = disparity_1;
	edge.simmelianStrength = simmelianStrength_1;

	var graph = {};

	var extent$1 = {};

	/**
	 * Graphology Extent
	 * ==================
	 *
	 * Simple function returning the extent of selected attributes of the graph.
	 */

	var isGraph$s = isGraph$N;

	/**
	 * Function returning the extent of the selected node attributes.
	 *
	 * @param  {Graph}        graph     - Target graph.
	 * @param  {string|array} attribute - Single or multiple attributes.
	 * @return {array|object}
	 */
	function nodeExtent(graph, attribute) {
	  if (!isGraph$s(graph))
	    throw new Error(
	      'graphology-metrics/extent: the given graph is not a valid graphology instance.'
	    );

	  var attributes = [].concat(attribute);

	  var value, key, a;

	  var results = {};

	  for (a = 0; a < attributes.length; a++) {
	    key = attributes[a];

	    results[key] = [Infinity, -Infinity];
	  }

	  graph.forEachNode(function (node, data) {
	    for (a = 0; a < attributes.length; a++) {
	      key = attributes[a];
	      value = data[key];

	      if (value < results[key][0]) results[key][0] = value;

	      if (value > results[key][1]) results[key][1] = value;
	    }
	  });

	  return typeof attribute === 'string' ? results[attribute] : results;
	}

	/**
	 * Function returning the extent of the selected edge attributes.
	 *
	 * @param  {Graph}        graph     - Target graph.
	 * @param  {string|array} attribute - Single or multiple attributes.
	 * @return {array|object}
	 */
	function edgeExtent(graph, attribute) {
	  if (!isGraph$s(graph))
	    throw new Error(
	      'graphology-metrics/extent: the given graph is not a valid graphology instance.'
	    );

	  var attributes = [].concat(attribute);

	  var value, key, a;

	  var results = {};

	  for (a = 0; a < attributes.length; a++) {
	    key = attributes[a];

	    results[key] = [Infinity, -Infinity];
	  }

	  graph.forEachEdge(function (edge, data) {
	    for (a = 0; a < attributes.length; a++) {
	      key = attributes[a];
	      value = data[key];

	      if (value < results[key][0]) results[key][0] = value;

	      if (value > results[key][1]) results[key][1] = value;
	    }
	  });

	  return typeof attribute === 'string' ? results[attribute] : results;
	}

	/**
	 * Exporting.
	 */
	extent$1.nodeExtent = nodeExtent;
	extent$1.edgeExtent = edgeExtent;

	var unweighted$1 = {};

	/**
	 * Mnemonist Queue
	 * ================
	 *
	 * Queue implementation based on the ideas of Queue.js that seems to beat
	 * a LinkedList one in performance.
	 */

	var Iterator = iterator,
	    forEach = foreach;

	/**
	 * Queue
	 *
	 * @constructor
	 */
	function Queue$1() {
	  this.clear();
	}

	/**
	 * Method used to clear the queue.
	 *
	 * @return {undefined}
	 */
	Queue$1.prototype.clear = function() {

	  // Properties
	  this.items = [];
	  this.offset = 0;
	  this.size = 0;
	};

	/**
	 * Method used to add an item to the queue.
	 *
	 * @param  {any}    item - Item to enqueue.
	 * @return {number}
	 */
	Queue$1.prototype.enqueue = function(item) {

	  this.items.push(item);
	  return ++this.size;
	};

	/**
	 * Method used to retrieve & remove the first item of the queue.
	 *
	 * @return {any}
	 */
	Queue$1.prototype.dequeue = function() {
	  if (!this.size)
	    return;

	  var item = this.items[this.offset];

	  if (++this.offset * 2 >= this.items.length) {
	    this.items = this.items.slice(this.offset);
	    this.offset = 0;
	  }

	  this.size--;

	  return item;
	};

	/**
	 * Method used to retrieve the first item of the queue.
	 *
	 * @return {any}
	 */
	Queue$1.prototype.peek = function() {
	  if (!this.size)
	    return;

	  return this.items[this.offset];
	};

	/**
	 * Method used to iterate over the queue.
	 *
	 * @param  {function}  callback - Function to call for each item.
	 * @param  {object}    scope    - Optional scope.
	 * @return {undefined}
	 */
	Queue$1.prototype.forEach = function(callback, scope) {
	  scope = arguments.length > 1 ? scope : this;

	  for (var i = this.offset, j = 0, l = this.items.length; i < l; i++, j++)
	    callback.call(scope, this.items[i], j, this);
	};

	/*
	 * Method used to convert the queue to a JavaScript array.
	 *
	 * @return {array}
	 */
	Queue$1.prototype.toArray = function() {
	  return this.items.slice(this.offset);
	};

	/**
	 * Method used to create an iterator over a queue's values.
	 *
	 * @return {Iterator}
	 */
	Queue$1.prototype.values = function() {
	  var items = this.items,
	      i = this.offset;

	  return new Iterator(function() {
	    if (i >= items.length)
	      return {
	        done: true
	      };

	    var value = items[i];
	    i++;

	    return {
	      value: value,
	      done: false
	    };
	  });
	};

	/**
	 * Method used to create an iterator over a queue's entries.
	 *
	 * @return {Iterator}
	 */
	Queue$1.prototype.entries = function() {
	  var items = this.items,
	      i = this.offset,
	      j = 0;

	  return new Iterator(function() {
	    if (i >= items.length)
	      return {
	        done: true
	      };

	    var value = items[i];
	    i++;

	    return {
	      value: [j++, value],
	      done: false
	    };
	  });
	};

	/**
	 * Attaching the #.values method to Symbol.iterator if possible.
	 */
	if (typeof Symbol !== 'undefined')
	  Queue$1.prototype[Symbol.iterator] = Queue$1.prototype.values;

	/**
	 * Convenience known methods.
	 */
	Queue$1.prototype.toString = function() {
	  return this.toArray().join(',');
	};

	Queue$1.prototype.toJSON = function() {
	  return this.toArray();
	};

	Queue$1.prototype.inspect = function() {
	  var array = this.toArray();

	  // Trick so that node displays the name of the constructor
	  Object.defineProperty(array, 'constructor', {
	    value: Queue$1,
	    enumerable: false
	  });

	  return array;
	};

	if (typeof Symbol !== 'undefined')
	  Queue$1.prototype[Symbol.for('nodejs.util.inspect.custom')] = Queue$1.prototype.inspect;

	/**
	 * Static @.from function taking an arbitrary iterable & converting it into
	 * a queue.
	 *
	 * @param  {Iterable} iterable   - Target iterable.
	 * @return {Queue}
	 */
	Queue$1.from = function(iterable) {
	  var queue = new Queue$1();

	  forEach(iterable, function(value) {
	    queue.enqueue(value);
	  });

	  return queue;
	};

	/**
	 * Static @.of function taking an arbitrary number of arguments & converting it
	 * into a queue.
	 *
	 * @param  {...any} args
	 * @return {Queue}
	 */
	Queue$1.of = function() {
	  return Queue$1.from(arguments);
	};

	/**
	 * Exporting.
	 */
	var queue = Queue$1;

	/**
	 * Extend function
	 * ================
	 *
	 * Function used to push a bunch of values into an array at once.
	 *
	 * Its strategy is to mutate target array's length then setting the new indices
	 * to be the values to add.
	 *
	 * A benchmark proved that it is faster than the following strategies:
	 *   1) `array.push.apply(array, values)`.
	 *   2) A loop of pushes.
	 *   3) `array = array.concat(values)`, obviously.
	 *
	 * Intuitively, this is correct because when adding a lot of elements, the
	 * chosen strategies does not need to handle the `arguments` object to
	 * execute #.apply's variadicity and because the array know its final length
	 * at the beginning, avoiding potential multiple reallocations of the underlying
	 * contiguous array. Some engines may be able to optimize the loop of push
	 * operations but empirically they don't seem to do so.
	 */

	/**
	 * Extends the target array with the given values.
	 *
	 * @param  {array} array  - Target array.
	 * @param  {array} values - Values to add.
	 */
	var extend$1 = function extend(array, values) {
	  var l2 = values.length;

	  if (l2 === 0)
	    return;

	  var l1 = array.length;

	  array.length += l2;

	  for (var i = 0; i < l2; i++)
	    array[l1 + i] = values[i];
	};

	/**
	 * Graphology Unweighted Shortest Path
	 * ====================================
	 *
	 * Basic algorithms to find the shortest paths between nodes in a graph
	 * whose edges are not weighted.
	 */

	var isGraph$r = isGraph$N;
	var Queue = queue;
	var extend = extend$1;

	/**
	 * Function attempting to find the shortest path in a graph between
	 * given source & target or `null` if such a path does not exist.
	 *
	 * @param  {Graph}      graph  - Target graph.
	 * @param  {any}        source - Source node.
	 * @param  {any}        target - Target node.
	 * @return {array|null}        - Found path or `null`.
	 */
	function bidirectional(graph, source, target) {
	  if (!isGraph$r(graph))
	    throw new Error('graphology-shortest-path: invalid graphology instance.');

	  if (arguments.length < 3)
	    throw new Error(
	      'graphology-shortest-path: invalid number of arguments. Expecting at least 3.'
	    );

	  if (!graph.hasNode(source))
	    throw new Error(
	      'graphology-shortest-path: the "' +
	        source +
	        '" source node does not exist in the given graph.'
	    );

	  if (!graph.hasNode(target))
	    throw new Error(
	      'graphology-shortest-path: the "' +
	        target +
	        '" target node does not exist in the given graph.'
	    );

	  source = '' + source;
	  target = '' + target;

	  // TODO: do we need a self loop to go there?
	  if (source === target) {
	    return [source];
	  }

	  // Binding functions
	  var getPredecessors = graph.inboundNeighbors.bind(graph),
	    getSuccessors = graph.outboundNeighbors.bind(graph);

	  var predecessor = {},
	    successor = {};

	  // Predecessor & successor
	  predecessor[source] = null;
	  successor[target] = null;

	  // Fringes
	  var forwardFringe = [source],
	    reverseFringe = [target],
	    currentFringe,
	    node,
	    neighbors,
	    neighbor,
	    i,
	    j,
	    l,
	    m;

	  var found = false;

	  outer: while (forwardFringe.length && reverseFringe.length) {
	    if (forwardFringe.length <= reverseFringe.length) {
	      currentFringe = forwardFringe;
	      forwardFringe = [];

	      for (i = 0, l = currentFringe.length; i < l; i++) {
	        node = currentFringe[i];
	        neighbors = getSuccessors(node);

	        for (j = 0, m = neighbors.length; j < m; j++) {
	          neighbor = neighbors[j];

	          if (!(neighbor in predecessor)) {
	            forwardFringe.push(neighbor);
	            predecessor[neighbor] = node;
	          }

	          if (neighbor in successor) {
	            // Path is found!
	            found = true;
	            break outer;
	          }
	        }
	      }
	    } else {
	      currentFringe = reverseFringe;
	      reverseFringe = [];

	      for (i = 0, l = currentFringe.length; i < l; i++) {
	        node = currentFringe[i];
	        neighbors = getPredecessors(node);

	        for (j = 0, m = neighbors.length; j < m; j++) {
	          neighbor = neighbors[j];

	          if (!(neighbor in successor)) {
	            reverseFringe.push(neighbor);
	            successor[neighbor] = node;
	          }

	          if (neighbor in predecessor) {
	            // Path is found!
	            found = true;
	            break outer;
	          }
	        }
	      }
	    }
	  }

	  if (!found) return null;

	  var path = [];

	  while (neighbor) {
	    path.unshift(neighbor);
	    neighbor = predecessor[neighbor];
	  }

	  neighbor = successor[path[path.length - 1]];

	  while (neighbor) {
	    path.push(neighbor);
	    neighbor = successor[neighbor];
	  }

	  return path.length ? path : null;
	}

	/**
	 * Function attempting to find the shortest path in the graph between the
	 * given source node & all the other nodes.
	 *
	 * @param  {Graph}  graph  - Target graph.
	 * @param  {any}    source - Source node.
	 * @return {object}        - The map of found paths.
	 */

	// TODO: cutoff option
	function singleSource(graph, source) {
	  if (!isGraph$r(graph))
	    throw new Error('graphology-shortest-path: invalid graphology instance.');

	  if (arguments.length < 2)
	    throw new Error(
	      'graphology-shortest-path: invalid number of arguments. Expecting at least 2.'
	    );

	  if (!graph.hasNode(source))
	    throw new Error(
	      'graphology-shortest-path: the "' +
	        source +
	        '" source node does not exist in the given graph.'
	    );

	  source = '' + source;

	  var nextLevel = {},
	    paths = {},
	    currentLevel,
	    neighbors,
	    v,
	    w,
	    i,
	    l;

	  nextLevel[source] = true;
	  paths[source] = [source];

	  while (Object.keys(nextLevel).length) {
	    currentLevel = nextLevel;
	    nextLevel = {};

	    for (v in currentLevel) {
	      neighbors = graph.outboundNeighbors(v);

	      for (i = 0, l = neighbors.length; i < l; i++) {
	        w = neighbors[i];

	        if (!paths[w]) {
	          paths[w] = paths[v].concat(w);
	          nextLevel[w] = true;
	        }
	      }
	    }
	  }

	  return paths;
	}

	/**
	 * Function attempting to find the shortest path lengths in the graph between
	 * the given source node & all the other nodes.
	 *
	 * @param  {string} method - Neighbor collection method name.
	 * @param  {Graph}  graph  - Target graph.
	 * @param  {any}    source - Source node.
	 * @return {object}        - The map of found path lengths.
	 */

	// TODO: cutoff option
	function asbtractSingleSourceLength(method, graph, source) {
	  if (!isGraph$r(graph))
	    throw new Error('graphology-shortest-path: invalid graphology instance.');

	  if (!graph.hasNode(source))
	    throw new Error(
	      'graphology-shortest-path: the "' +
	        source +
	        '" source node does not exist in the given graph.'
	    );

	  source = '' + source;

	  // Performing BFS to count shortest paths
	  var seen = new Set();

	  var lengths = {},
	    level = 0;

	  lengths[source] = 0;

	  var currentLevel = [source];

	  var i, l, node;

	  while (currentLevel.length !== 0) {
	    var nextLevel = [];

	    for (i = 0, l = currentLevel.length; i < l; i++) {
	      node = currentLevel[i];

	      if (seen.has(node)) continue;

	      seen.add(node);
	      extend(nextLevel, graph[method](node));

	      lengths[node] = level;
	    }

	    level++;
	    currentLevel = nextLevel;
	  }

	  return lengths;
	}

	var singleSourceLength$1 = asbtractSingleSourceLength.bind(
	  null,
	  'outboundNeighbors'
	);
	var undirectedSingleSourceLength$1 = asbtractSingleSourceLength.bind(
	  null,
	  'neighbors'
	);

	/**
	 * Function using Ulrik Brandes' method to map single source shortest paths
	 * from selected node.
	 *
	 * [Reference]:
	 * Ulrik Brandes: A Faster Algorithm for Betweenness Centrality.
	 * Journal of Mathematical Sociology 25(2):163-177, 2001.
	 *
	 * @param  {Graph}  graph      - Target graph.
	 * @param  {any}    source     - Source node.
	 * @return {array}             - [Stack, Paths, Sigma]
	 */
	function brandes$1(graph, source) {
	  source = '' + source;

	  var S = [],
	    P = {},
	    sigma = {};

	  var nodes = graph.nodes(),
	    Dv,
	    sigmav,
	    neighbors,
	    v,
	    w,
	    i,
	    j,
	    l,
	    m;

	  for (i = 0, l = nodes.length; i < l; i++) {
	    v = nodes[i];
	    P[v] = [];
	    sigma[v] = 0;
	  }

	  var D = {};

	  sigma[source] = 1;
	  D[source] = 0;

	  var queue = Queue.of(source);

	  while (queue.size) {
	    v = queue.dequeue();
	    S.push(v);

	    Dv = D[v];
	    sigmav = sigma[v];

	    neighbors = graph.outboundNeighbors(v);

	    for (j = 0, m = neighbors.length; j < m; j++) {
	      w = neighbors[j];

	      if (!(w in D)) {
	        queue.enqueue(w);
	        D[w] = Dv + 1;
	      }

	      if (D[w] === Dv + 1) {
	        sigma[w] += sigmav;
	        P[w].push(v);
	      }
	    }
	  }

	  return [S, P, sigma];
	}

	/**
	 * Exporting.
	 */
	unweighted$1.bidirectional = bidirectional;
	unweighted$1.singleSource = singleSource;
	unweighted$1.singleSourceLength = singleSourceLength$1;
	unweighted$1.undirectedSingleSourceLength = undirectedSingleSourceLength$1;
	unweighted$1.brandes = brandes$1;

	/**
	 * Graphology Eccentricity
	 * ========================
	 *
	 * Functions used to compute the eccentricity of each node of a given graph.
	 */

	var isGraph$q = isGraph$N;
	var singleSourceLength =
	  unweighted$1.singleSourceLength;

	var eccentricity$1 = function eccentricity(graph, node) {
	  if (!isGraph$q(graph))
	    throw new Error(
	      'graphology-metrics/eccentricity: given graph is not a valid graphology instance.'
	    );

	  if (graph.size === 0) return Infinity;

	  var ecc = -Infinity;

	  var lengths = singleSourceLength(graph, node);

	  var otherNode;

	  var pathLength,
	    l = 0;

	  for (otherNode in lengths) {
	    pathLength = lengths[otherNode];

	    if (pathLength > ecc) ecc = pathLength;

	    l++;
	  }

	  if (l < graph.order) return Infinity;

	  return ecc;
	};

	/**
	 * Graphology Diameter
	 * ========================
	 *
	 * Functions used to compute the diameter of the given graph.
	 */

	var isGraph$p = isGraph$N;
	var eccentricity = eccentricity$1;

	var diameter = function diameter(graph) {
	  if (!isGraph$p(graph))
	    throw new Error(
	      'graphology-metrics/diameter: given graph is not a valid graphology instance.'
	    );

	  if (graph.size === 0) return Infinity;

	  var max = -Infinity;

	  graph.someNode(function (node) {
	    var e = eccentricity(graph, node);

	    if (e > max) max = e;

	    // If the graph is not connected, its diameter is infinite
	    return max === Infinity;
	  });

	  return max;
	};

	/**
	 * Graphology Modularity
	 * ======================
	 *
	 * Implementation of network modularity for graphology.
	 *
	 * Modularity is a bit of a tricky problem because there are a wide array
	 * of different definitions and implementations. The current implementation
	 * try to stay true to Newman's original definition and consider both the
	 * undirected & directed case as well as the weighted one. The current
	 * implementation should also be aligned with Louvain algorithm's definition
	 * of the metric.
	 *
	 * Regarding the directed version, one has to understand that the undirected
	 * version's is basically considering the graph as a directed one where all
	 * edges would be mutual.
	 *
	 * There is one exception to this, though: self loops. To conform with density's
	 * definition, as used in modularity's one, and to keep true to the matrix
	 * formulation of modularity, one has to note that self-loops only count once
	 * in both the undirected and directed cases. This means that a k-clique with
	 * one node having a self-loop will not have the same modularity in the
	 * undirected and mutual case. Indeed, in both cases the modularity of a
	 * k-clique with one loop and minus one internal edge should be equal.
	 *
	 * This also means that, as with the naive density formula regarding loops,
	 * one should increment M when considering a loop. Also, to remain coherent
	 * in this regard, degree should not be multiplied by two because of the loop
	 * else it will have too much importance regarding the considered proportions.
	 *
	 * Hence, here are the retained formulas:
	 *
	 * For dense weighted undirected network:
	 * --------------------------------------
	 *
	 * Q = 1/2m * [ ∑ij[Aij - (di.dj / 2m)] * ∂(ci, cj) ]
	 *
	 * where:
	 *  - i & j being a pair of nodes
	 *  - m is the sum of edge weights
	 *  - Aij being the weight of the ij edge (or 0 if absent)
	 *  - di being the weighted degree of node i
	 *  - ci being the community to which belongs node i
	 *  - ∂ is Kronecker's delta function (1 if x = y else 0)
	 *
	 * For dense weighted directed network:
	 * ------------------------------------
	 *
	 * Qd = 1/m * [ ∑ij[Aij - (dini.doutj / m)] * ∂(ci, cj) ]
	 *
	 * where:
	 *  - dini is the in degree of node i
	 *  - douti is the out degree of node i
	 *
	 * For sparse weighted undirected network:
	 * ---------------------------------------
	 *
	 * Q = ∑c[ (∑cinternal / 2m) - (∑ctotal / 2m)² ]
	 *
	 * where:
	 *  - c is a community
	 *  - ∑cinternal is the total weight of a community internal edges
	 *  - ∑ctotal is the total weight of edges connected to a community
	 *
	 * For sparse weighted directed network:
	 * -------------------------------------
	 *
	 * Qd = ∑c[ (∑cinternal / m) - (∑cintotal * ∑couttotal / m²) ]
	 *
	 * where:
	 *  - ∑cintotal is the total weight of edges pointing towards a community
	 *  - ∑couttotal is the total weight of edges going from a community
	 *
	 * Note that dense version run in O(N²) while sparse version runs in O(V). So
	 * the dense version is mostly here to guarantee the validity of the sparse one.
	 * As such it is not used as default.
	 *
	 * For undirected delta computation:
	 * ---------------------------------
	 *
	 * ∆Q = (dic / 2m) - ((∑ctotal * di) / 2m²)
	 *
	 * where:
	 *  - dic is the degree of the node in community c
	 *
	 * For directed delta computation:
	 * -------------------------------
	 *
	 * ∆Qd = (dic / m) - (((douti * ∑cintotal) + (dini * ∑couttotal)) / m²)
	 *
	 * Gephi's version of undirected delta computation:
	 * ------------------------------------------------
	 *
	 * ∆Qgephi = dic - (di * Ztot) / 2m
	 *
	 * Note that the above formula is erroneous and should really be:
	 *
	 * ∆Qgephi = dic - (di * Ztot) / m
	 *
	 * because then: ∆Qgephi = ∆Q * 2m
	 *
	 * It is used because it is faster to compute. Since Gephi's error is only by
	 * a constant factor, it does not make the result incorrect.
	 *
	 * [Latex]
	 *
	 * Sparse undirected
	 * Q = \sum_{c} \bigg{[} \frac{\sum\nolimits_{c\,in}}{2m} - \left(\frac{\sum\nolimits_{c\,tot}}{2m}\right )^2 \bigg{]}
	 *
	 * Sparse directed
	 * Q_d = \sum_{c} \bigg{[} \frac{\sum\nolimits_{c\,in}}{m} - \frac{\sum_{c\,tot}^{in}\sum_{c\,tot}^{out}}{m^2} \bigg{]}
	 *
	 * [Articles]
	 * M. E. J. Newman, « Modularity and community structure in networks »,
	 * Proc. Natl. Acad. Sci. USA, vol. 103, no 23, 2006, p. 8577–8582
	 * https://dx.doi.org/10.1073%2Fpnas.0601602103
	 *
	 * Newman, M. E. J. « Community detection in networks: Modularity optimization
	 * and maximum likelihood are equivalent ». Physical Review E, vol. 94, no 5,
	 * novembre 2016, p. 052315. arXiv.org, doi:10.1103/PhysRevE.94.052315.
	 * https://arxiv.org/pdf/1606.02319.pdf
	 *
	 * Blondel, Vincent D., et al. « Fast unfolding of communities in large
	 * networks ». Journal of Statistical Mechanics: Theory and Experiment,
	 * vol. 2008, no 10, octobre 2008, p. P10008. DOI.org (Crossref),
	 * doi:10.1088/1742-5468/2008/10/P10008.
	 * https://arxiv.org/pdf/0803.0476.pdf
	 *
	 * Nicolas Dugué, Anthony Perez. Directed Louvain: maximizing modularity in
	 * directed networks. [Research Report] Université d’Orléans. 2015. hal-01231784
	 * https://hal.archives-ouvertes.fr/hal-01231784
	 *
	 * R. Lambiotte, J.-C. Delvenne and M. Barahona. Laplacian Dynamics and
	 * Multiscale Modular Structure in Networks,
	 * doi:10.1109/TNSE.2015.2391998.
	 * https://arxiv.org/abs/0812.1770
	 *
	 * [Links]:
	 * https://math.stackexchange.com/questions/2637469/where-does-the-second-formula-of-modularity-comes-from-in-the-louvain-paper-the
	 * https://www.quora.com/How-is-the-formula-for-Louvain-modularity-change-derived
	 * https://github.com/gephi/gephi/blob/master/modules/StatisticsPlugin/src/main/java/org/gephi/statistics/plugin/Modularity.java
	 * https://github.com/igraph/igraph/blob/eca5e809aab1aa5d4eca1e381389bcde9cf10490/src/community.c#L906
	 */

	var resolveDefaults$1 = defaults$7;
	var isGraph$o = isGraph$N;
	var inferType$1 = inferType$4;
	var getters = getters$1;

	var DEFAULTS$3 = {
	  getNodeCommunity: 'community',
	  getEdgeWeight: 'weight',
	  resolution: 1
	};

	function collectForUndirectedDense(graph, options) {
	  var communities = new Array(graph.order);
	  var weightedDegrees = new Float64Array(graph.order);
	  var weights = {};
	  var M = 0;

	  var getEdgeWeight = getters.createEdgeWeightGetter(
	    options.getEdgeWeight
	  ).fromEntry;
	  var getNodeCommunity = getters.createNodeValueGetter(
	    options.getNodeCommunity
	  ).fromEntry;

	  // Collecting communities
	  var i = 0;
	  var ids = {};
	  graph.forEachNode(function (node, attr) {
	    ids[node] = i;
	    communities[i++] = getNodeCommunity(node, attr);
	  });

	  // Collecting weights
	  graph.forEachUndirectedEdge(function (edge, attr, source, target, sa, ta, u) {
	    var weight = getEdgeWeight(edge, attr, source, target, sa, ta, u);

	    M += weight;
	    weights[edge] = weight;

	    weightedDegrees[ids[source]] += weight;

	    // NOTE: we double degree only if we don't have a loop
	    if (source !== target) weightedDegrees[ids[target]] += weight;
	  });

	  return {
	    weights: weights,
	    communities: communities,
	    weightedDegrees: weightedDegrees,
	    M: M
	  };
	}

	function collectForDirectedDense(graph, options) {
	  var communities = new Array(graph.order);
	  var weightedInDegrees = new Float64Array(graph.order);
	  var weightedOutDegrees = new Float64Array(graph.order);
	  var weights = {};
	  var M = 0;

	  var getEdgeWeight = getters.createEdgeWeightGetter(
	    options.getEdgeWeight
	  ).fromEntry;
	  var getNodeCommunity = getters.createNodeValueGetter(
	    options.getNodeCommunity
	  ).fromEntry;

	  // Collecting communities
	  var i = 0;
	  var ids = {};
	  graph.forEachNode(function (node, attr) {
	    ids[node] = i;
	    communities[i++] = getNodeCommunity(node, attr);
	  });

	  // Collecting weights
	  graph.forEachDirectedEdge(function (edge, attr, source, target, sa, ta, u) {
	    var weight = getEdgeWeight(edge, attr, source, target, sa, ta, u);

	    M += weight;
	    weights[edge] = weight;

	    weightedOutDegrees[ids[source]] += weight;
	    weightedInDegrees[ids[target]] += weight;
	  });

	  return {
	    weights: weights,
	    communities: communities,
	    weightedInDegrees: weightedInDegrees,
	    weightedOutDegrees: weightedOutDegrees,
	    M: M
	  };
	}

	function undirectedDenseModularity(graph, options) {
	  var resolution = options.resolution;

	  var result = collectForUndirectedDense(graph, options);

	  var communities = result.communities;
	  var weightedDegrees = result.weightedDegrees;

	  var M = result.M;

	  var nodes = graph.nodes();

	  var i, j, l, Aij, didj, e;

	  var S = 0;

	  var M2 = M * 2;

	  for (i = 0, l = graph.order; i < l; i++) {
	    // NOTE: it is important to parse the whole matrix here, diagonal and
	    // lower part included. A lot of implementation differ here because
	    // they process only a part of the matrix
	    for (j = 0; j < l; j++) {
	      // NOTE: Kronecker's delta
	      // NOTE: we could go from O(n^2) to O(avg.C^2)
	      if (communities[i] !== communities[j]) continue;

	      e = graph.undirectedEdge(nodes[i], nodes[j]);

	      Aij = result.weights[e] || 0;
	      didj = weightedDegrees[i] * weightedDegrees[j];

	      // We add twice if we have a self loop
	      if (i === j && typeof e !== 'undefined')
	        S += (Aij - (didj / M2) * resolution) * 2;
	      else S += Aij - (didj / M2) * resolution;
	    }
	  }

	  return S / M2;
	}

	function directedDenseModularity(graph, options) {
	  var resolution = options.resolution;

	  var result = collectForDirectedDense(graph, options);

	  var communities = result.communities;
	  var weightedInDegrees = result.weightedInDegrees;
	  var weightedOutDegrees = result.weightedOutDegrees;

	  var M = result.M;

	  var nodes = graph.nodes();

	  var i, j, l, Aij, didj, e;

	  var S = 0;

	  for (i = 0, l = graph.order; i < l; i++) {
	    // NOTE: it is important to parse the whole matrix here, diagonal and
	    // lower part included. A lot of implementation differ here because
	    // they process only a part of the matrix
	    for (j = 0; j < l; j++) {
	      // NOTE: Kronecker's delta
	      // NOTE: we could go from O(n^2) to O(avg.C^2)
	      if (communities[i] !== communities[j]) continue;

	      e = graph.directedEdge(nodes[i], nodes[j]);

	      Aij = result.weights[e] || 0;
	      didj = weightedInDegrees[i] * weightedOutDegrees[j];

	      // Here we multiply by two to simulate iteration through lower part
	      S += Aij - (didj / M) * resolution;
	    }
	  }

	  return S / M;
	}

	function collectCommunitesForUndirected(graph, options) {
	  var communities = {};
	  var totalWeights = {};
	  var internalWeights = {};

	  var getNodeCommunity = getters.createNodeValueGetter(
	    options.getNodeCommunity
	  ).fromEntry;

	  graph.forEachNode(function (node, attr) {
	    var community = getNodeCommunity(node, attr);
	    communities[node] = community;

	    if (typeof community === 'undefined')
	      throw new Error(
	        'graphology-metrics/modularity: the "' +
	          node +
	          '" node is not in the partition.'
	      );

	    totalWeights[community] = 0;
	    internalWeights[community] = 0;
	  });

	  return {
	    communities: communities,
	    totalWeights: totalWeights,
	    internalWeights: internalWeights
	  };
	}

	function collectCommunitesForDirected(graph, options) {
	  var communities = {};
	  var totalInWeights = {};
	  var totalOutWeights = {};
	  var internalWeights = {};

	  var getNodeCommunity = getters.createNodeValueGetter(
	    options.getNodeCommunity
	  ).fromEntry;

	  graph.forEachNode(function (node, attr) {
	    var community = getNodeCommunity(node, attr);
	    communities[node] = community;

	    if (typeof community === 'undefined')
	      throw new Error(
	        'graphology-metrics/modularity: the "' +
	          node +
	          '" node is not in the partition.'
	      );

	    totalInWeights[community] = 0;
	    totalOutWeights[community] = 0;
	    internalWeights[community] = 0;
	  });

	  return {
	    communities: communities,
	    totalInWeights: totalInWeights,
	    totalOutWeights: totalOutWeights,
	    internalWeights: internalWeights
	  };
	}

	function undirectedSparseModularity(graph, options) {
	  var resolution = options.resolution;

	  var result = collectCommunitesForUndirected(graph, options);

	  var M = 0;

	  var totalWeights = result.totalWeights;
	  var internalWeights = result.internalWeights;
	  var communities = result.communities;

	  var getEdgeWeight = getters.createEdgeWeightGetter(
	    options.getEdgeWeight
	  ).fromEntry;

	  graph.forEachUndirectedEdge(function (
	    edge,
	    edgeAttr,
	    source,
	    target,
	    sa,
	    ta,
	    u
	  ) {
	    var weight = getEdgeWeight(edge, edgeAttr, source, target, sa, ta, u);

	    M += weight;

	    var sourceCommunity = communities[source];
	    var targetCommunity = communities[target];

	    totalWeights[sourceCommunity] += weight;
	    totalWeights[targetCommunity] += weight;

	    if (sourceCommunity !== targetCommunity) return;

	    internalWeights[sourceCommunity] += weight * 2;
	  });

	  var Q = 0;
	  var M2 = M * 2;

	  for (var C in internalWeights)
	    Q +=
	      internalWeights[C] / M2 - Math.pow(totalWeights[C] / M2, 2) * resolution;

	  return Q;
	}

	function directedSparseModularity(graph, options) {
	  var resolution = options.resolution;

	  var result = collectCommunitesForDirected(graph, options);

	  var M = 0;

	  var totalInWeights = result.totalInWeights;
	  var totalOutWeights = result.totalOutWeights;
	  var internalWeights = result.internalWeights;
	  var communities = result.communities;

	  var getEdgeWeight = getters.createEdgeWeightGetter(
	    options.getEdgeWeight
	  ).fromEntry;

	  graph.forEachDirectedEdge(function (
	    edge,
	    edgeAttr,
	    source,
	    target,
	    sa,
	    ta,
	    u
	  ) {
	    var weight = getEdgeWeight(edge, edgeAttr, source, target, sa, ta, u);

	    M += weight;

	    var sourceCommunity = communities[source];
	    var targetCommunity = communities[target];

	    totalOutWeights[sourceCommunity] += weight;
	    totalInWeights[targetCommunity] += weight;

	    if (sourceCommunity !== targetCommunity) return;

	    internalWeights[sourceCommunity] += weight;
	  });

	  var Q = 0;

	  for (var C in internalWeights)
	    Q +=
	      internalWeights[C] / M -
	      ((totalInWeights[C] * totalOutWeights[C]) / Math.pow(M, 2)) * resolution;

	  return Q;
	}

	// NOTE: the formula is a bit unclear here but nodeCommunityDegree should be
	// given as the edges count * 2
	function undirectedModularityDelta(
	  M,
	  communityTotalWeight,
	  nodeDegree,
	  nodeCommunityDegree
	) {
	  return (
	    nodeCommunityDegree / (2 * M) -
	    (communityTotalWeight * nodeDegree) / (2 * (M * M))
	  );
	}

	function directedModularityDelta(
	  M,
	  communityTotalInWeight,
	  communityTotalOutWeight,
	  nodeInDegree,
	  nodeOutDegree,
	  nodeCommunityDegree
	) {
	  return (
	    nodeCommunityDegree / M -
	    (nodeOutDegree * communityTotalInWeight +
	      nodeInDegree * communityTotalOutWeight) /
	      (M * M)
	  );
	}

	function denseModularity(graph, options) {
	  if (!isGraph$o(graph))
	    throw new Error(
	      'graphology-metrics/modularity: given graph is not a valid graphology instance.'
	    );

	  if (graph.size === 0)
	    throw new Error(
	      'graphology-metrics/modularity: cannot compute modularity of an empty graph.'
	    );

	  if (graph.multi)
	    throw new Error(
	      'graphology-metrics/modularity: cannot compute modularity of a multi graph. Cast it to a simple one beforehand.'
	    );

	  var trueType = inferType$1(graph);

	  if (trueType === 'mixed')
	    throw new Error(
	      'graphology-metrics/modularity: cannot compute modularity of a mixed graph.'
	    );

	  options = resolveDefaults$1(options, DEFAULTS$3);

	  if (trueType === 'directed') return directedDenseModularity(graph, options);

	  return undirectedDenseModularity(graph, options);
	}

	function sparseModularity(graph, options) {
	  if (!isGraph$o(graph))
	    throw new Error(
	      'graphology-metrics/modularity: given graph is not a valid graphology instance.'
	    );

	  if (graph.size === 0)
	    throw new Error(
	      'graphology-metrics/modularity: cannot compute modularity of an empty graph.'
	    );

	  if (graph.multi)
	    throw new Error(
	      'graphology-metrics/modularity: cannot compute modularity of a multi graph. Cast it to a simple one beforehand.'
	    );

	  var trueType = inferType$1(graph);

	  if (trueType === 'mixed')
	    throw new Error(
	      'graphology-metrics/modularity: cannot compute modularity of a mixed graph.'
	    );

	  options = resolveDefaults$1(options, DEFAULTS$3);

	  if (trueType === 'directed') return directedSparseModularity(graph, options);

	  return undirectedSparseModularity(graph, options);
	}

	var modularity = sparseModularity;

	modularity.sparse = sparseModularity;
	modularity.dense = denseModularity;
	modularity.undirectedDelta = undirectedModularityDelta;
	modularity.directedDelta = directedModularityDelta;

	var modularity_1 = modularity;

	/**
	 * Graphology Weighted Size
	 * =========================
	 *
	 * Function returning the sum of the graph's edges' weights.
	 */

	var isGraph$n = isGraph$N;
	var createEdgeWeightGetter$2 =
	  getters$1.createEdgeWeightGetter;

	/**
	 * Defaults.
	 */
	var DEFAULT_WEIGHT_ATTRIBUTE$2 = 'weight';

	/**
	 * Weighted size function.
	 *
	 * @param  {Graph}  graph                    - Target graph.
	 * @param  {string|function} [getEdgeWeight] - Name of the weight attribute or getter function.
	 * @return {number}
	 */
	var weightedSize = function weightedSize(graph, getEdgeWeight) {
	  // Handling errors
	  if (!isGraph$n(graph))
	    throw new Error(
	      'graphology-metrics/weighted-size: the given graph is not a valid graphology instance.'
	    );

	  getEdgeWeight = createEdgeWeightGetter$2(
	    getEdgeWeight || DEFAULT_WEIGHT_ATTRIBUTE$2
	  ).fromEntry;

	  var size = 0;

	  graph.forEachEdge(function (e, a, s, t, sa, ta, u) {
	    size += getEdgeWeight(e, a, s, t, sa, ta, u);
	  });

	  return size;
	};

	var density = density$2;
	var extent = extent$1;

	graph.diameter = diameter;
	graph.modularity = modularity_1;
	graph.simpleSize = simpleSize$1;
	graph.weightedSize = weightedSize;

	graph.abstractDensity = density.abstractDensity;
	graph.density = density.density;
	graph.directedDensity = density.directedDensity;
	graph.undirectedDensity = density.undirectedDensity;
	graph.mixedDensity = density.mixedDensity;
	graph.multiDirectedDensity = density.multiDirectedDensity;
	graph.multiUndirectedDensity = density.multiUndirectedDensity;
	graph.multiMixedDensity = density.multiMixedDensity;

	graph.nodeExtent = extent.nodeExtent;
	graph.edgeExtent = extent.edgeExtent;

	var layoutQuality = {};

	/**
	 * Graphology Layout Quality - Edge Uniformity
	 * ============================================
	 *
	 * Function computing the layout quality metric named "edge uniformity".
	 * It is basically the normalized standard deviation of edge length.
	 *
	 * [Article]:
	 * Rahman, Md Khaledur, et al. « BatchLayout: A Batch-Parallel Force-Directed
	 * Graph Layout Algorithm in Shared Memory ».
	 * http://arxiv.org/abs/2002.08233.
	 */

	var isGraph$m = isGraph$N;

	function euclideanDistance$1(a, b) {
	  return Math.sqrt(Math.pow(a.x - b.x, 2) + Math.pow(a.y - b.y, 2));
	}

	var edgeUniformity = function edgeUniformity(graph) {
	  if (!isGraph$m(graph))
	    throw new Error(
	      'graphology-metrics/layout-quality/edge-uniformity: given graph is not a valid graphology instance.'
	    );

	  if (graph.size === 0) return 0;

	  var sum = 0,
	    i = 0,
	    l;

	  var lengths = new Float64Array(graph.size);

	  graph.forEachEdge(function (
	    edge,
	    attr,
	    source,
	    target,
	    sourceAttr,
	    targetAttr
	  ) {
	    var edgeLength = euclideanDistance$1(sourceAttr, targetAttr);

	    lengths[i++] = edgeLength;
	    sum += edgeLength;
	  });

	  var avg = sum / graph.size;

	  var stdev = 0;

	  for (i = 0, l = graph.size; i < l; i++)
	    stdev += Math.pow(avg - lengths[i], 2);

	  var metric = stdev / (graph.size * Math.pow(avg, 2));

	  return Math.sqrt(metric);
	};

	/**
	 * Mnemonist Fixed Reverse Heap
	 * =============================
	 *
	 * Static heap implementation with fixed capacity. It's a "reverse" heap
	 * because it stores the elements in reverse so we can replace the worst
	 * item in logarithmic time. As such, one cannot pop this heap but can only
	 * consume it at the end. This structure is very efficient when trying to
	 * find the n smallest/largest items from a larger query (k nearest neigbors
	 * for instance).
	 */

	var comparators = comparators$2,
	    Heap$1 = heap;

	var DEFAULT_COMPARATOR = comparators.DEFAULT_COMPARATOR,
	    reverseComparator = comparators.reverseComparator;

	/**
	 * Helper functions.
	 */

	/**
	 * Function used to sift up.
	 *
	 * @param {function} compare - Comparison function.
	 * @param {array}    heap    - Array storing the heap's data.
	 * @param {number}   size    - Heap's true size.
	 * @param {number}   i       - Index.
	 */
	function siftUp(compare, heap, size, i) {
	  var endIndex = size,
	      startIndex = i,
	      item = heap[i],
	      childIndex = 2 * i + 1,
	      rightIndex;

	  while (childIndex < endIndex) {
	    rightIndex = childIndex + 1;

	    if (
	      rightIndex < endIndex &&
	      compare(heap[childIndex], heap[rightIndex]) >= 0
	    ) {
	      childIndex = rightIndex;
	    }

	    heap[i] = heap[childIndex];
	    i = childIndex;
	    childIndex = 2 * i + 1;
	  }

	  heap[i] = item;
	  Heap$1.siftDown(compare, heap, startIndex, i);
	}

	/**
	 * Fully consumes the given heap.
	 *
	 * @param  {function} ArrayClass - Array class to use.
	 * @param  {function} compare    - Comparison function.
	 * @param  {array}    heap       - Array storing the heap's data.
	 * @param  {number}   size       - True size of the heap.
	 * @return {array}
	 */
	function consume(ArrayClass, compare, heap, size) {
	  var l = size,
	      i = l;

	  var array = new ArrayClass(size),
	      lastItem,
	      item;

	  while (i > 0) {
	    lastItem = heap[--i];

	    if (i !== 0) {
	      item = heap[0];
	      heap[0] = lastItem;
	      siftUp(compare, heap, --size, 0);
	      lastItem = item;
	    }

	    array[i] = lastItem;
	  }

	  return array;
	}

	/**
	 * Binary Minimum FixedReverseHeap.
	 *
	 * @constructor
	 * @param {function} ArrayClass - The class of array to use.
	 * @param {function} comparator - Comparator function.
	 * @param {number}   capacity   - Maximum number of items to keep.
	 */
	function FixedReverseHeap$1(ArrayClass, comparator, capacity) {

	  // Comparator can be omitted
	  if (arguments.length === 2) {
	    capacity = comparator;
	    comparator = null;
	  }

	  this.ArrayClass = ArrayClass;
	  this.capacity = capacity;

	  this.items = new ArrayClass(capacity);
	  this.clear();
	  this.comparator = comparator || DEFAULT_COMPARATOR;

	  if (typeof capacity !== 'number' && capacity <= 0)
	    throw new Error('mnemonist/FixedReverseHeap.constructor: capacity should be a number > 0.');

	  if (typeof this.comparator !== 'function')
	    throw new Error('mnemonist/FixedReverseHeap.constructor: given comparator should be a function.');

	  this.comparator = reverseComparator(this.comparator);
	}

	/**
	 * Method used to clear the heap.
	 *
	 * @return {undefined}
	 */
	FixedReverseHeap$1.prototype.clear = function() {

	  // Properties
	  this.size = 0;
	};

	/**
	 * Method used to push an item into the heap.
	 *
	 * @param  {any}    item - Item to push.
	 * @return {number}
	 */
	FixedReverseHeap$1.prototype.push = function(item) {

	  // Still some place
	  if (this.size < this.capacity) {
	    this.items[this.size] = item;
	    Heap$1.siftDown(this.comparator, this.items, 0, this.size);
	    this.size++;
	  }

	  // Heap is full, we need to replace worst item
	  else {

	    if (this.comparator(item, this.items[0]) > 0)
	      Heap$1.replace(this.comparator, this.items, item);
	  }

	  return this.size;
	};

	/**
	 * Method used to peek the worst item in the heap.
	 *
	 * @return {any}
	 */
	FixedReverseHeap$1.prototype.peek = function() {
	  return this.items[0];
	};

	/**
	 * Method used to consume the heap fully and return its items as a sorted array.
	 *
	 * @return {array}
	 */
	FixedReverseHeap$1.prototype.consume = function() {
	  var items = consume(this.ArrayClass, this.comparator, this.items, this.size);
	  this.size = 0;

	  return items;
	};

	/**
	 * Method used to convert the heap to an array. Note that it basically clone
	 * the heap and consumes it completely. This is hardly performant.
	 *
	 * @return {array}
	 */
	FixedReverseHeap$1.prototype.toArray = function() {
	  return consume(this.ArrayClass, this.comparator, this.items.slice(0, this.size), this.size);
	};

	/**
	 * Convenience known methods.
	 */
	FixedReverseHeap$1.prototype.inspect = function() {
	  var proxy = this.toArray();

	  // Trick so that node displays the name of the constructor
	  Object.defineProperty(proxy, 'constructor', {
	    value: FixedReverseHeap$1,
	    enumerable: false
	  });

	  return proxy;
	};

	if (typeof Symbol !== 'undefined')
	  FixedReverseHeap$1.prototype[Symbol.for('nodejs.util.inspect.custom')] = FixedReverseHeap$1.prototype.inspect;

	/**
	 * Exporting.
	 */
	var fixedReverseHeap = FixedReverseHeap$1;

	var quick = {};

	/**
	 * Mnemonist Quick Sort
	 * =====================
	 *
	 * Quick sort related functions.
	 * Adapted from: https://alienryderflex.com/quicksort/
	 */

	var LOS = new Float64Array(64),
	    HIS = new Float64Array(64);

	function inplaceQuickSort(array, lo, hi) {
	  var p, i, l, r, swap;

	  LOS[0] = lo;
	  HIS[0] = hi;
	  i = 0;

	  while (i >= 0) {
	    l = LOS[i];
	    r = HIS[i] - 1;

	    if (l < r) {
	      p = array[l];

	      while (l < r) {
	        while (array[r] >= p && l < r)
	          r--;

	        if (l < r)
	          array[l++] = array[r];

	        while (array[l] <= p && l < r)
	          l++;

	        if (l < r)
	          array[r--] = array[l];
	      }

	      array[l] = p;
	      LOS[i + 1] = l + 1;
	      HIS[i + 1] = HIS[i];
	      HIS[i++] = l;

	      if (HIS[i] - LOS[i] > HIS[i - 1] - LOS[i - 1]) {
	        swap = LOS[i];
	        LOS[i] = LOS[i - 1];
	        LOS[i - 1] = swap;

	        swap = HIS[i];
	        HIS[i] = HIS[i - 1];
	        HIS[i - 1] = swap;
	      }
	    }
	    else {
	      i--;
	    }
	  }

	  return array;
	}

	quick.inplaceQuickSort = inplaceQuickSort;

	function inplaceQuickSortIndices$1(array, indices, lo, hi) {
	  var p, i, l, r, t, swap;

	  LOS[0] = lo;
	  HIS[0] = hi;
	  i = 0;

	  while (i >= 0) {
	    l = LOS[i];
	    r = HIS[i] - 1;

	    if (l < r) {
	      t = indices[l];
	      p = array[t];

	      while (l < r) {
	        while (array[indices[r]] >= p && l < r)
	          r--;

	        if (l < r)
	          indices[l++] = indices[r];

	        while (array[indices[l]] <= p && l < r)
	          l++;

	        if (l < r)
	          indices[r--] = indices[l];
	      }

	      indices[l] = t;
	      LOS[i + 1] = l + 1;
	      HIS[i + 1] = HIS[i];
	      HIS[i++] = l;

	      if (HIS[i] - LOS[i] > HIS[i - 1] - LOS[i - 1]) {
	        swap = LOS[i];
	        LOS[i] = LOS[i - 1];
	        LOS[i - 1] = swap;

	        swap = HIS[i];
	        HIS[i] = HIS[i - 1];
	        HIS[i - 1] = swap;
	      }
	    }
	    else {
	      i--;
	    }
	  }

	  return indices;
	}

	quick.inplaceQuickSortIndices = inplaceQuickSortIndices$1;

	/**
	 * Mnemonist KDTree
	 * =================
	 *
	 * Low-level JavaScript implementation of a k-dimensional tree.
	 */

	var iterables = iterables$4;
	var typed = typedArrays;
	var createTupleComparator = comparators$2.createTupleComparator;
	var FixedReverseHeap = fixedReverseHeap;
	var inplaceQuickSortIndices = quick.inplaceQuickSortIndices;

	/**
	 * Helper function used to compute the squared distance between a query point
	 * and an indexed points whose values are stored in a tree's axes.
	 *
	 * Note that squared distance is used instead of euclidean to avoid
	 * costly sqrt computations.
	 *
	 * @param  {number} dimensions - Number of dimensions.
	 * @param  {array}  axes       - Axes data.
	 * @param  {number} pivot      - Pivot.
	 * @param  {array}  point      - Query point.
	 * @return {number}
	 */
	function squaredDistanceAxes(dimensions, axes, pivot, b) {
	  var d;

	  var dist = 0,
	      step;

	  for (d = 0; d < dimensions; d++) {
	    step = axes[d][pivot] - b[d];
	    dist += step * step;
	  }

	  return dist;
	}

	/**
	 * Helper function used to reshape input data into low-level axes data.
	 *
	 * @param  {number} dimensions - Number of dimensions.
	 * @param  {array}  data       - Data in the shape [label, [x, y, z...]]
	 * @return {object}
	 */
	function reshapeIntoAxes(dimensions, data) {
	  var l = data.length;

	  var axes = new Array(dimensions),
	      labels = new Array(l),
	      axis;

	  var PointerArray = typed.getPointerArray(l);

	  var ids = new PointerArray(l);

	  var d, i, row;

	  var f = true;

	  for (d = 0; d < dimensions; d++) {
	    axis = new Float64Array(l);

	    for (i = 0; i < l; i++) {
	      row = data[i];
	      axis[i] = row[1][d];

	      if (f) {
	        labels[i] = row[0];
	        ids[i] = i;
	      }
	    }

	    f = false;
	    axes[d] = axis;
	  }

	  return {axes: axes, ids: ids, labels: labels};
	}

	/**
	 * Helper function used to build a kd-tree from axes data.
	 *
	 * @param  {number} dimensions - Number of dimensions.
	 * @param  {array}  axes       - Axes.
	 * @param  {array}  ids        - Indices to sort.
	 * @param  {array}  labels     - Point labels.
	 * @return {object}
	 */
	function buildTree(dimensions, axes, ids, labels) {
	  var l = labels.length;

	  // NOTE: +1 because we need to keep 0 as null pointer
	  var PointerArray = typed.getPointerArray(l + 1);

	  // Building the tree
	  var pivots = new PointerArray(l),
	      lefts = new PointerArray(l),
	      rights = new PointerArray(l);

	  var stack = [[0, 0, ids.length, -1, 0]],
	      step,
	      parent,
	      direction,
	      median,
	      pivot,
	      lo,
	      hi;

	  var d, i = 0;

	  while (stack.length !== 0) {
	    step = stack.pop();

	    d = step[0];
	    lo = step[1];
	    hi = step[2];
	    parent = step[3];
	    direction = step[4];

	    inplaceQuickSortIndices(axes[d], ids, lo, hi);

	    l = hi - lo;
	    median = lo + (l >>> 1); // Fancy floor(l / 2)
	    pivot = ids[median];
	    pivots[i] = pivot;

	    if (parent > -1) {
	      if (direction === 0)
	        lefts[parent] = i + 1;
	      else
	        rights[parent] = i + 1;
	    }

	    d = (d + 1) % dimensions;

	    // Right
	    if (median !== lo && median !== hi - 1) {
	      stack.push([d, median + 1, hi, i, 1]);
	    }

	    // Left
	    if (median !== lo) {
	      stack.push([d, lo, median, i, 0]);
	    }

	    i++;
	  }

	  return {
	    axes: axes,
	    labels: labels,
	    pivots: pivots,
	    lefts: lefts,
	    rights: rights
	  };
	}

	/**
	 * KDTree.
	 *
	 * @constructor
	 */
	function KDTree$1(dimensions, build) {
	  this.dimensions = dimensions;
	  this.visited = 0;

	  this.axes = build.axes;
	  this.labels = build.labels;

	  this.pivots = build.pivots;
	  this.lefts = build.lefts;
	  this.rights = build.rights;

	  this.size = this.labels.length;
	}

	/**
	 * Method returning the query's nearest neighbor.
	 *
	 * @param  {array}  query - Query point.
	 * @return {any}
	 */
	KDTree$1.prototype.nearestNeighbor = function(query) {
	  var bestDistance = Infinity,
	      best = null;

	  var dimensions = this.dimensions,
	      axes = this.axes,
	      pivots = this.pivots,
	      lefts = this.lefts,
	      rights = this.rights;

	  var visited = 0;

	  function recurse(d, node) {
	    visited++;

	    var left = lefts[node],
	        right = rights[node],
	        pivot = pivots[node];

	    var dist = squaredDistanceAxes(
	      dimensions,
	      axes,
	      pivot,
	      query
	    );

	    if (dist < bestDistance) {
	      best = pivot;
	      bestDistance = dist;

	      if (dist === 0)
	        return;
	    }

	    var dx = axes[d][pivot] - query[d];

	    d = (d + 1) % dimensions;

	    // Going the correct way?
	    if (dx > 0) {
	      if (left !== 0)
	        recurse(d, left - 1);
	    }
	    else {
	      if (right !== 0)
	        recurse(d, right - 1);
	    }

	    // Going the other way?
	    if (dx * dx < bestDistance) {
	      if (dx > 0) {
	        if (right !== 0)
	          recurse(d, right - 1);
	      }
	      else {
	        if (left !== 0)
	          recurse(d, left - 1);
	      }
	    }
	  }

	  recurse(0, 0);

	  this.visited = visited;
	  return this.labels[best];
	};

	var KNN_HEAP_COMPARATOR_3 = createTupleComparator(3);
	var KNN_HEAP_COMPARATOR_2 = createTupleComparator(2);

	/**
	 * Method returning the query's k nearest neighbors.
	 *
	 * @param  {number} k     - Number of nearest neighbor to retrieve.
	 * @param  {array}  query - Query point.
	 * @return {array}
	 */

	// TODO: can do better by improving upon static-kdtree here
	KDTree$1.prototype.kNearestNeighbors = function(k, query) {
	  if (k <= 0)
	    throw new Error('mnemonist/kd-tree.kNearestNeighbors: k should be a positive number.');

	  k = Math.min(k, this.size);

	  if (k === 1)
	    return [this.nearestNeighbor(query)];

	  var heap = new FixedReverseHeap(Array, KNN_HEAP_COMPARATOR_3, k);

	  var dimensions = this.dimensions,
	      axes = this.axes,
	      pivots = this.pivots,
	      lefts = this.lefts,
	      rights = this.rights;

	  var visited = 0;

	  function recurse(d, node) {
	    var left = lefts[node],
	        right = rights[node],
	        pivot = pivots[node];

	    var dist = squaredDistanceAxes(
	      dimensions,
	      axes,
	      pivot,
	      query
	    );

	    heap.push([dist, visited++, pivot]);

	    var point = query[d],
	        split = axes[d][pivot],
	        dx = point - split;

	    d = (d + 1) % dimensions;

	    // Going the correct way?
	    if (point < split) {
	      if (left !== 0) {
	        recurse(d, left - 1);
	      }
	    }
	    else {
	      if (right !== 0) {
	        recurse(d, right - 1);
	      }
	    }

	    // Going the other way?
	    if (dx * dx < heap.peek()[0] || heap.size < k) {
	      if (point < split) {
	        if (right !== 0) {
	          recurse(d, right - 1);
	        }
	      }
	      else {
	        if (left !== 0) {
	          recurse(d, left - 1);
	        }
	      }
	    }
	  }

	  recurse(0, 0);

	  this.visited = visited;

	  var best = heap.consume();

	  for (var i = 0; i < best.length; i++)
	    best[i] = this.labels[best[i][2]];

	  return best;
	};

	/**
	 * Method returning the query's k nearest neighbors by linear search.
	 *
	 * @param  {number} k     - Number of nearest neighbor to retrieve.
	 * @param  {array}  query - Query point.
	 * @return {array}
	 */
	KDTree$1.prototype.linearKNearestNeighbors = function(k, query) {
	  if (k <= 0)
	    throw new Error('mnemonist/kd-tree.kNearestNeighbors: k should be a positive number.');

	  k = Math.min(k, this.size);

	  var heap = new FixedReverseHeap(Array, KNN_HEAP_COMPARATOR_2, k);

	  var i, l, dist;

	  for (i = 0, l = this.size; i < l; i++) {
	    dist = squaredDistanceAxes(
	      this.dimensions,
	      this.axes,
	      this.pivots[i],
	      query
	    );

	    heap.push([dist, i]);
	  }

	  var best = heap.consume();

	  for (i = 0; i < best.length; i++)
	    best[i] = this.labels[this.pivots[best[i][1]]];

	  return best;
	};

	/**
	 * Convenience known methods.
	 */
	KDTree$1.prototype.inspect = function() {
	  var dummy = new Map();

	  dummy.dimensions = this.dimensions;

	  Object.defineProperty(dummy, 'constructor', {
	    value: KDTree$1,
	    enumerable: false
	  });

	  var i, j, point;

	  for (i = 0; i < this.size; i++) {
	    point = new Array(this.dimensions);

	    for (j = 0; j < this.dimensions; j++)
	      point[j] = this.axes[j][i];

	    dummy.set(this.labels[i], point);
	  }

	  return dummy;
	};

	if (typeof Symbol !== 'undefined')
	  KDTree$1.prototype[Symbol.for('nodejs.util.inspect.custom')] = KDTree$1.prototype.inspect;

	/**
	 * Static @.from function taking an arbitrary iterable & converting it into
	 * a structure.
	 *
	 * @param  {Iterable} iterable   - Target iterable.
	 * @param  {number}   dimensions - Space dimensions.
	 * @return {KDTree}
	 */
	KDTree$1.from = function(iterable, dimensions) {
	  var data = iterables.toArray(iterable);

	  var reshaped = reshapeIntoAxes(dimensions, data);

	  var result = buildTree(dimensions, reshaped.axes, reshaped.ids, reshaped.labels);

	  return new KDTree$1(dimensions, result);
	};

	/**
	 * Static @.from function building a KDTree from given axes.
	 *
	 * @param  {Iterable} iterable   - Target iterable.
	 * @param  {number}   dimensions - Space dimensions.
	 * @return {KDTree}
	 */
	KDTree$1.fromAxes = function(axes, labels) {
	  if (!labels)
	    labels = typed.indices(axes[0].length);

	  var dimensions = axes.length;

	  var result = buildTree(axes.length, axes, typed.indices(labels.length), labels);

	  return new KDTree$1(dimensions, result);
	};

	/**
	 * Exporting.
	 */
	var kdTree = KDTree$1;

	/**
	 * Graphology Layout Quality - Neighborhood Preservation
	 * ======================================================
	 *
	 * Function computing the layout quality metric named "neighborhood preservation".
	 * It is basically the average of overlap coefficient between node neighbors in
	 * the graph and equivalent k-nn in the 2d layout space.
	 *
	 * [Article]:
	 * Rahman, Md Khaledur, et al. « BatchLayout: A Batch-Parallel Force-Directed
	 * Graph Layout Algorithm in Shared Memory ».
	 * http://arxiv.org/abs/2002.08233.
	 */

	var isGraph$l = isGraph$N,
	  KDTree = kdTree,
	  intersectionSize = set.intersectionSize;

	var neighborhoodPreservation = function neighborhoodPreservation(graph) {
	  if (!isGraph$l(graph))
	    throw new Error(
	      'graphology-metrics/layout-quality/neighborhood-preservation: given graph is not a valid graphology instance.'
	    );

	  if (graph.order === 0)
	    throw new Error(
	      'graphology-metrics/layout-quality/neighborhood-preservation: cannot compute stress for a null graph.'
	    );

	  if (graph.size === 0) return 0;

	  var axes = [new Float64Array(graph.order), new Float64Array(graph.order)],
	    i = 0;

	  graph.forEachNode(function (node, attr) {
	    axes[0][i] = attr.x;
	    axes[1][i++] = attr.y;
	  });

	  var tree = KDTree.fromAxes(axes, graph.nodes());

	  var sum = 0;

	  graph.forEachNode(function (node, attr) {
	    var neighbors = new Set(graph.neighbors(node));

	    // If node has no neighbors or is connected to every other node
	    if (neighbors.size === 0 || neighbors.size === graph.order - 1) {
	      sum += 1;
	      return;
	    }

	    var knn = tree.kNearestNeighbors(neighbors.size + 1, [attr.x, attr.y]);
	    knn = new Set(knn.slice(1));

	    var I = intersectionSize(neighbors, knn);

	    // Computing overlap coefficient
	    sum += I / knn.size;
	  });

	  return sum / graph.order;
	};

	/**
	 * Graphology Layout Quality - Stress
	 * ===================================
	 *
	 * Function computing the layout quality metric named "stress".
	 * It is basically the sum of normalized deltas between graph topology distances
	 * and 2d space distances of the layout.
	 *
	 * [Article]:
	 * Rahman, Md Khaledur, et al. « BatchLayout: A Batch-Parallel Force-Directed
	 * Graph Layout Algorithm in Shared Memory ».
	 * http://arxiv.org/abs/2002.08233.
	 */

	var isGraph$k = isGraph$N,
	  undirectedSingleSourceLength =
	    unweighted$1.undirectedSingleSourceLength;

	function euclideanDistance(a, b) {
	  return Math.sqrt(Math.pow(a.x - b.x, 2) + Math.pow(a.y - b.y, 2));
	}

	var stress = function stress(graph) {
	  if (!isGraph$k(graph))
	    throw new Error(
	      'graphology-metrics/layout-quality/stress: given graph is not a valid graphology instance.'
	    );

	  if (graph.order === 0)
	    throw new Error(
	      'graphology-metrics/layout-quality/stress: cannot compute stress for a null graph.'
	    );

	  var nodes = new Array(graph.order),
	    entries = new Array(graph.order),
	    i = 0;

	  // We choose an arbitrary large distance for when two nodes cannot be
	  // connected because they belong to different connected components
	  // and because we cannot deal with Infinity in our computations
	  // This is what most papers recommend anyway
	  var maxDistance = graph.order * 4;

	  graph.forEachNode(function (node, attr) {
	    nodes[i] = node;
	    entries[i++] = attr;
	  });

	  var j, l, p1, p2, shortestPaths, dij, wij, cicj;

	  var sum = 0;

	  for (i = 0, l = graph.order; i < l; i++) {
	    shortestPaths = undirectedSingleSourceLength(graph, nodes[i]);

	    p1 = entries[i];

	    for (j = i + 1; j < l; j++) {
	      p2 = entries[j];

	      // NOTE: dij should be 0 since we don't consider self-loops
	      dij = shortestPaths[nodes[j]];

	      // Target is in another castle
	      if (typeof dij === 'undefined') dij = maxDistance;

	      cicj = euclideanDistance(p1, p2);
	      wij = 1 / (dij * dij);

	      sum += wij * Math.pow(cicj - dij, 2);
	    }
	  }

	  return sum;
	};

	layoutQuality.edgeUniformity = edgeUniformity;
	layoutQuality.neighborhoodPreservation = neighborhoodPreservation;
	layoutQuality.stress = stress;

	var node = {};

	var weightedDegree = {};

	/**
	 * Graphology Weighted Degree
	 * ===========================
	 *
	 * Function computing the weighted degree of nodes. The weighted degree is the
	 * sum of a node's edges' weights.
	 */

	var isGraph$j = isGraph$N;

	/**
	 * Defaults.
	 */
	var DEFAULT_WEIGHT_ATTRIBUTE$1 = 'weight';

	/**
	 * Asbtract function to perform any kind of weighted degree.
	 *
	 * @param  {string}          name          - Name of the implemented function.
	 * @param  {string}          method        - Method of the graph to get the edges.
	 * @param  {Graph}           graph         - A graphology instance.
	 * @param  {string}          node          - Target node.
	 * @param  {string|function} getEdgeWeight - Name of edge weight attribute or getter function.
	 *
	 * @return {number}
	 */
	function abstractWeightedDegree(name, method, graph, node, getEdgeWeight) {
	  if (!isGraph$j(graph))
	    throw new Error(
	      'graphology-metrics/' +
	        name +
	        ': the given graph is not a valid graphology instance.'
	    );

	  getEdgeWeight = getEdgeWeight || DEFAULT_WEIGHT_ATTRIBUTE$1;

	  var d = 0;

	  graph[method](node, function (e, a, s, t, sa, ta, u) {
	    var w =
	      typeof getEdgeWeight === 'function'
	        ? getEdgeWeight(e, a, s, t, sa, ta, u)
	        : a[getEdgeWeight];

	    if (typeof w !== 'number' || isNaN(w)) w = 1;

	    d += w;
	  });

	  return d;
	}

	/**
	 * Exports.
	 */
	weightedDegree.weightedDegree = abstractWeightedDegree.bind(
	  null,
	  'weightedDegree',
	  'forEachEdge'
	);
	weightedDegree.weightedInDegree = abstractWeightedDegree.bind(
	  null,
	  'weightedInDegree',
	  'forEachInEdge'
	);
	weightedDegree.weightedOutDegree = abstractWeightedDegree.bind(
	  null,
	  'weightedOutDegree',
	  'forEachOutEdge'
	);
	weightedDegree.weightedInboundDegree = abstractWeightedDegree.bind(
	  null,
	  'weightedInboundDegree',
	  'forEachInboundEdge'
	);
	weightedDegree.weightedOutboundDegree = abstractWeightedDegree.bind(
	  null,
	  'weightedOutboundDegree',
	  'forEachOutboundEdge'
	);
	weightedDegree.weightedUndirectedDegree = abstractWeightedDegree.bind(
	  null,
	  'weightedUndirectedDegree',
	  'forEachUndirectedEdge'
	);
	weightedDegree.weightedDirectedDegree = abstractWeightedDegree.bind(
	  null,
	  'weightedDirectedDegree',
	  'forEachDirectedEdge'
	);

	var wd = weightedDegree;

	node.eccentricity = eccentricity$1;

	node.weightedDegree = wd.weightedDegree;
	node.weightedInDegree = wd.weightedInDegree;
	node.weightedOutDegree = wd.weightedOutDegree;
	node.weightedInboundDegree = wd.weightedInboundDegree;
	node.weightedOutboundDegree = wd.weightedOutboundDegree;
	node.weightedUndirectedDegree = wd.weightedUndirectedDegree;
	node.weightedDirectedDegree = wd.weightedDirectedDegree;

	/**
	 * Graphology Metrics
	 * ===================
	 *
	 * Library endpoint.
	 */

	metrics$2.centrality = centrality;
	metrics$2.edge = edge;
	metrics$2.graph = graph;
	metrics$2.layoutQuality = layoutQuality;
	metrics$2.node = node;

	var metrics$1 = metrics$2;

	var operators$2 = {};

	/**
	 * Graphology Disjoint Union Operator
	 * ===================================
	 */

	var isGraph$i = isGraph$N;
	var copyNode$1 = addNode.copyNode;
	var copyEdge$7 = addEdge.copyEdge;

	/**
	 * Function returning the disjoint union of two given graphs by giving new keys
	 * to nodes & edges.
	 *
	 * @param  {Graph} G - The first graph.
	 * @param  {Graph} H - The second graph.
	 * @return {Graph}
	 */
	var disjointUnion = function disjointUnion(G, H) {
	  if (!isGraph$i(G) || !isGraph$i(H))
	    throw new Error('graphology-operators/disjoint-union: invalid graph.');

	  if (G.multi !== H.multi)
	    throw new Error(
	      'graphology-operators/disjoint-union: both graph should be simple or multi.'
	    );

	  var R = G.nullCopy();

	  // TODO: in the spirit of this operator we should probably prefix something
	  R.mergeAttributes(G.getAttributes());

	  var labelsG = {};
	  var labelsH = {};

	  var i = 0;

	  // Adding nodes
	  G.forEachNode(function (key, attr) {
	    labelsG[key] = i;

	    copyNode$1(R, i, attr);

	    i++;
	  });

	  H.forEachNode(function (key, attr) {
	    labelsH[key] = i;

	    copyNode$1(R, i, attr);

	    i++;
	  });

	  // Adding edges
	  i = 0;

	  G.forEachEdge(function (key, attr, source, target, _s, _t, undirected) {
	    copyEdge$7(
	      R,
	      undirected,
	      i++,
	      labelsG[source],
	      labelsG[target],
	      target,
	      attr
	    );
	  });

	  H.forEachEdge(function (key, attr, source, target, _s, _t, undirected) {
	    copyEdge$7(
	      R,
	      undirected,
	      i++,
	      labelsH[source],
	      labelsH[target],
	      target,
	      attr
	    );
	  });

	  return R;
	};

	/**
	 * Graphology Revers Operator
	 * ===========================
	 */

	var isGraph$h = isGraph$N;
	var copyEdge$6 = addEdge.copyEdge;

	/**
	 * Function reversing the given graph.
	 *
	 * @param  {Graph} graph - Target graph.
	 * @return {Graph}
	 */
	var reverse = function reverse(graph) {
	  if (!isGraph$h(graph))
	    throw new Error('graphology-operators/reverse: invalid graph.');

	  var reversed = graph.emptyCopy();

	  // Importing undirected edges
	  graph.forEachUndirectedEdge(function (key, attr, source, target) {
	    copyEdge$6(reversed, true, key, source, target, attr);
	  });

	  // Reversing directed edges
	  graph.forEachDirectedEdge(function (key, attr, source, target) {
	    copyEdge$6(reversed, false, key, target, source, attr);
	  });

	  return reversed;
	};

	/**
	 * Graphology Sub Graph
	 * =====================
	 *
	 * Function returning the subgraph composed of the nodes passed as parameters.
	 */

	var isGraph$g = isGraph$N;
	var copyNode = addNode.copyNode;
	var copyEdge$5 = addEdge.copyEdge;

	var subgraph = function subgraph(graph, nodes) {
	  if (!isGraph$g(graph))
	    throw new Error('graphology-operators/subgraph: invalid graph instance.');

	  var S = graph.nullCopy();

	  var filterNode = nodes;

	  if (Array.isArray(nodes)) {
	    if (nodes.length === 0) return S;

	    nodes = new Set(nodes);
	  }

	  if (nodes instanceof Set) {
	    if (nodes.size === 0) return S;

	    filterNode = function (key) {
	      return nodes.has(key);
	    };

	    // Ensuring given keys are casted to string
	    var old = nodes;
	    nodes = new Set();

	    old.forEach(function (node) {
	      nodes.add('' + node);
	    });
	  }

	  if (typeof filterNode !== 'function')
	    throw new Error(
	      'graphology-operators/subgraph: invalid nodes. Expecting an array or a set or a filtering function.'
	    );

	  if (typeof nodes === 'function') {
	    graph.forEachNode(function (key, attr) {
	      if (!filterNode(key, attr)) return;

	      copyNode(S, key, attr);
	    });

	    // Early termination
	    if (S.order === 0) return S;
	  } else {
	    nodes.forEach(function (key) {
	      if (!graph.hasNode(key))
	        throw new Error(
	          'graphology-operators/subgraph: the "' +
	            key +
	            '" node was not found in the graph.'
	        );

	      copyNode(S, key, graph.getNodeAttributes(key));
	    });
	  }

	  graph.forEachEdge(function (
	    key,
	    attr,
	    source,
	    target,
	    sourceAttr,
	    targetAttr,
	    undirected
	  ) {
	    if (!filterNode(source, sourceAttr)) return;

	    if (target !== source && !filterNode(target, targetAttr)) return;

	    copyEdge$5(S, undirected, key, source, target, attr);
	  });

	  return S;
	};

	/**
	 * Graphology Operators To Directed Caster
	 * ========================================
	 *
	 * Function used to cast any graph to a directed one.
	 */

	var isGraph$f = isGraph$N;
	var copyEdge$4 = addEdge.copyEdge;

	var toDirected = function toDirected(graph, options) {
	  if (!isGraph$f(graph))
	    throw new Error(
	      'graphology-operators/to-directed: expecting a valid graphology instance.'
	    );

	  if (typeof options === 'function') options = {mergeEdge: options};

	  options = options || {};

	  var mergeEdge =
	    typeof options.mergeEdge === 'function' ? options.mergeEdge : null;

	  if (graph.type === 'directed') return graph.copy();

	  var directedGraph = graph.emptyCopy({type: 'directed'});

	  // Adding directed edges
	  graph.forEachDirectedEdge(function (edge, attr, source, target) {
	    copyEdge$4(directedGraph, false, edge, source, target, attr);
	  });

	  // Merging undirected edges
	  graph.forEachUndirectedEdge(function (_, attr, source, target) {
	    var existingOutEdge =
	      !graph.multi &&
	      graph.type === 'mixed' &&
	      directedGraph.edge(source, target);

	    var existingInEdge =
	      !graph.multi &&
	      graph.type === 'mixed' &&
	      directedGraph.edge(target, source);

	    if (existingOutEdge) {
	      directedGraph.replaceEdgeAttributes(
	        existingOutEdge,
	        mergeEdge(directedGraph.getEdgeAttributes(existingOutEdge), attr)
	      );
	    } else {
	      copyEdge$4(directedGraph, false, null, source, target, attr);
	    }

	    // Don't add self-loops twice
	    if (source === target) return;

	    if (existingInEdge) {
	      directedGraph.replaceEdgeAttributes(
	        existingInEdge,
	        mergeEdge(directedGraph.getEdgeAttributes(existingInEdge), attr)
	      );
	    } else {
	      copyEdge$4(directedGraph, false, null, target, source, attr);
	    }
	  });

	  return directedGraph;
	};

	/**
	 * Graphology Operators To Mixed Caster
	 * =====================================
	 *
	 * Function used to cast any graph to a mixed one.
	 */

	var isGraph$e = isGraph$N;

	var toMixed$2 = function toMixed(graph) {
	  if (!isGraph$e(graph))
	    throw new Error(
	      'graphology-operators/to-mixed: expecting a valid graphology instance.'
	    );

	  return graph.copy({type: 'mixed'});
	};

	/**
	 * Graphology Operators To Multi Caster
	 * =====================================
	 *
	 * Function used to cast any graph to a multi one.
	 */

	var isGraph$d = isGraph$N;

	var toMulti$2 = function toMulti(graph) {
	  if (!isGraph$d(graph))
	    throw new Error(
	      'graphology-operators/to-multi: expecting a valid graphology instance.'
	    );

	  return graph.copy({multi: true});
	};

	/**
	 * Graphology Operators To Simple Caster
	 * ======================================
	 *
	 * Function used to cast a multi graph to a simple one.
	 */

	var isGraph$c = isGraph$N;
	var copyEdge$3 = addEdge.copyEdge;

	var toSimple = function toSimple(multiGraph, options) {
	  if (!isGraph$c(multiGraph))
	    throw new Error(
	      'graphology-operators/to-simple: expecting a valid graphology instance.'
	    );

	  if (typeof options === 'function') options = {mergeEdge: options};

	  options = options || {};

	  var mergeEdge =
	    typeof options.mergeEdge === 'function' ? options.mergeEdge : null;

	  // The graph is not multi. We just return a plain copy
	  if (!multiGraph.multi) return multiGraph.copy();

	  // Creating a tweaked empty copy
	  var simpleGraph = multiGraph.emptyCopy({multi: false});

	  // Processing edges
	  multiGraph.forEachEdge(function (
	    edge,
	    attr,
	    source,
	    target,
	    _sa,
	    _ta,
	    undirected
	  ) {
	    var existingEdge = undirected
	      ? simpleGraph.undirectedEdge(source, target)
	      : simpleGraph.directedEdge(source, target);

	    if (existingEdge) {
	      if (mergeEdge) {
	        simpleGraph.replaceEdgeAttributes(
	          existingEdge,
	          mergeEdge(simpleGraph.getEdgeAttributes(existingEdge), attr)
	        );
	      }
	      return;
	    }

	    copyEdge$3(simpleGraph, undirected, edge, source, target, attr);
	  });

	  return simpleGraph;
	};

	/**
	 * Graphology Operators To Undirected Caster
	 * ==========================================
	 *
	 * Function used to cast any graph to an undirected one.
	 */

	var isGraph$b = isGraph$N;
	var copyEdge$2 = addEdge.copyEdge;

	var toUndirected = function toUndirected(graph, options) {
	  if (!isGraph$b(graph))
	    throw new Error(
	      'graphology-operators/to-undirected: expecting a valid graphology instance.'
	    );

	  if (typeof options === 'function') options = {mergeEdge: options};

	  options = options || {};

	  var mergeEdge =
	    typeof options.mergeEdge === 'function' ? options.mergeEdge : null;

	  if (graph.type === 'undirected') return graph.copy();

	  var undirectedGraph = graph.emptyCopy({type: 'undirected'});

	  // Adding undirected edges
	  graph.forEachUndirectedEdge(function (edge, attr, source, target) {
	    copyEdge$2(undirectedGraph, true, edge, source, target, attr);
	  });

	  // TODO: one loop for better performance

	  // Merging directed edges
	  graph.forEachDirectedEdge(function (edge, attr, source, target) {
	    if (!graph.multi) {
	      var existingEdge = undirectedGraph.edge(source, target);

	      if (existingEdge) {
	        // We need to merge
	        if (mergeEdge)
	          undirectedGraph.replaceEdgeAttributes(
	            existingEdge,
	            mergeEdge(undirectedGraph.getEdgeAttributes(existingEdge), attr)
	          );

	        return;
	      }
	    }

	    copyEdge$2(undirectedGraph, true, null, source, target, attr);
	  });

	  return undirectedGraph;
	};

	/**
	 * Graphology Union Operator
	 * ==========================
	 */

	var isGraph$a = isGraph$N;

	/**
	 * Function returning the union of two given graphs.
	 *
	 * @param  {Graph} G - The first graph.
	 * @param  {Graph} H - The second graph.
	 * @return {Graph}
	 */
	var union = function union(G, H) {
	  if (!isGraph$a(G) || !isGraph$a(H))
	    throw new Error('graphology-operators/union: invalid graph.');

	  if (G.multi !== H.multi)
	    throw new Error(
	      'graphology-operators/union: both graph should be simple or multi.'
	    );

	  var R = G.copy();
	  R.import(H, true);

	  return R;
	};

	/**
	 * Graphology Operators
	 * =====================
	 *
	 * Library endpoint.
	 */

	operators$2.disjointUnion = disjointUnion;
	operators$2.reverse = reverse;
	operators$2.subgraph = subgraph;
	operators$2.toDirected = toDirected;
	operators$2.toMixed = toMixed$2;
	operators$2.toMulti = toMulti$2;
	operators$2.toSimple = toSimple;
	operators$2.toUndirected = toUndirected;
	operators$2.union = union;

	var operators$1 = operators$2;

	var shortestPath$2 = {};

	var utils$7 = {};

	/**
	 * Graphology Shortest Path Utils
	 * ===============================
	 *
	 * Miscellaneous shortest-path helper functions.
	 */

	var returnTrue = function () {
	  return true;
	};

	utils$7.edgePathFromNodePath = function (graph, nodePath) {
	  var l = nodePath.length;

	  var i, source, target, edge;

	  // Self loops
	  if (l < 2) {
	    source = nodePath[0];

	    edge = graph.multi
	      ? graph.findEdge(source, source, returnTrue)
	      : graph.edge(source, source);

	    if (edge) return [edge];

	    return [];
	  }

	  l--;

	  var edgePath = new Array(l);

	  for (i = 0; i < l; i++) {
	    source = nodePath[i];
	    target = nodePath[i + 1];

	    edge = graph.multi
	      ? graph.findOutboundEdge(source, target, returnTrue)
	      : graph.edge(source, target);

	    if (edge === undefined)
	      throw new Error(
	        'graphology-shortest-path: given path is impossible in given graph.'
	      );

	    edgePath[i] = edge;
	  }

	  return edgePath;
	};

	var dijkstra = {};

	/**
	 * Graphology Dijkstra Shortest Path
	 * ==================================
	 *
	 * Graphology implementation of Dijkstra shortest path for weighted graphs.
	 */

	var isGraph$9 = isGraph$N;
	var createEdgeWeightGetter$1 =
	  getters$1.createEdgeWeightGetter;
	var Heap = heap;

	/**
	 * Defaults & helpers.
	 */
	var DEFAULT_WEIGHT_ATTRIBUTE = 'weight';

	function DIJKSTRA_HEAP_COMPARATOR(a, b) {
	  if (a[0] > b[0]) return 1;
	  if (a[0] < b[0]) return -1;

	  if (a[1] > b[1]) return 1;
	  if (a[1] < b[1]) return -1;

	  if (a[2] > b[2]) return 1;
	  if (a[2] < b[2]) return -1;

	  return 0;
	}

	function BRANDES_DIJKSTRA_HEAP_COMPARATOR(a, b) {
	  if (a[0] > b[0]) return 1;
	  if (a[0] < b[0]) return -1;

	  if (a[1] > b[1]) return 1;
	  if (a[1] < b[1]) return -1;

	  if (a[2] > b[2]) return 1;
	  if (a[2] < b[2]) return -1;

	  if (a[3] > b[3]) return 1;
	  if (a[3] < b[3]) return -1;

	  return 0;
	}

	/**
	 * Bidirectional Dijkstra shortest path between source & target node abstract.
	 *
	 * Note that this implementation was basically copied from networkx.
	 *
	 * @param  {Graph}  graph         - The graphology instance.
	 * @param  {string} source        - Source node.
	 * @param  {string} target        - Target node.
	 * @param  {string} getEdgeWeight - Name of the weight attribute or getter function.
	 * @param  {array}                - The found path if any and its cost.
	 */
	function abstractBidirectionalDijkstra(graph, source, target, getEdgeWeight) {
	  source = '' + source;
	  target = '' + target;

	  // Sanity checks
	  if (!isGraph$9(graph))
	    throw new Error(
	      'graphology-shortest-path/dijkstra: invalid graphology instance.'
	    );

	  if (source && !graph.hasNode(source))
	    throw new Error(
	      'graphology-shortest-path/dijkstra: the "' +
	        source +
	        '" source node does not exist in the given graph.'
	    );

	  if (target && !graph.hasNode(target))
	    throw new Error(
	      'graphology-shortest-path/dijkstra: the "' +
	        target +
	        '" target node does not exist in the given graph.'
	    );

	  getEdgeWeight = createEdgeWeightGetter$1(
	    getEdgeWeight || DEFAULT_WEIGHT_ATTRIBUTE
	  ).fromMinimalEntry;

	  if (source === target) return [0, [source]];

	  var distances = [{}, {}],
	    paths = [{}, {}],
	    fringe = [
	      new Heap(DIJKSTRA_HEAP_COMPARATOR),
	      new Heap(DIJKSTRA_HEAP_COMPARATOR)
	    ],
	    seen = [{}, {}];

	  paths[0][source] = [source];
	  paths[1][target] = [target];

	  seen[0][source] = 0;
	  seen[1][target] = 0;

	  var finalPath = [],
	    finalDistance = Infinity;

	  var count = 0,
	    dir = 1,
	    item,
	    edges,
	    cost,
	    d,
	    v,
	    u,
	    e,
	    i,
	    l;

	  fringe[0].push([0, count++, source]);
	  fringe[1].push([0, count++, target]);

	  while (fringe[0].size && fringe[1].size) {
	    // Swapping direction
	    dir = 1 - dir;

	    item = fringe[dir].pop();
	    d = item[0];
	    v = item[2];

	    if (v in distances[dir]) continue;

	    distances[dir][v] = d;

	    // Shortest path is found?
	    if (v in distances[1 - dir]) return [finalDistance, finalPath];

	    edges = dir === 1 ? graph.inboundEdges(v) : graph.outboundEdges(v);

	    for (i = 0, l = edges.length; i < l; i++) {
	      e = edges[i];
	      u = graph.opposite(v, e);
	      cost = distances[dir][v] + getEdgeWeight(e, graph.getEdgeAttributes(e));

	      if (u in distances[dir] && cost < distances[dir][u]) {
	        throw Error(
	          'graphology-shortest-path/dijkstra: contradictory paths found. Do some of your edges have a negative weight?'
	        );
	      } else if (!(u in seen[dir]) || cost < seen[dir][u]) {
	        seen[dir][u] = cost;
	        fringe[dir].push([cost, count++, u]);
	        paths[dir][u] = paths[dir][v].concat(u);

	        if (u in seen[0] && u in seen[1]) {
	          d = seen[0][u] + seen[1][u];

	          if (finalPath.length === 0 || finalDistance > d) {
	            finalDistance = d;
	            finalPath = paths[0][u].concat(paths[1][u].slice(0, -1).reverse());
	          }
	        }
	      }
	    }
	  }

	  // No path was found
	  return [Infinity, null];
	}

	/**
	 * Multisource Dijkstra shortest path abstract function. This function is the
	 * basis of the algorithm that every other will use.
	 *
	 * Note that this implementation was basically copied from networkx.
	 * TODO: it might be more performant to use a dedicated objet for the heap's
	 * items.
	 *
	 * @param  {Graph}  graph         - The graphology instance.
	 * @param  {array}  sources       - A list of sources.
	 * @param  {string} getEdgeWeight - Name of the weight attribute or getter function.
	 * @param  {number} cutoff        - Maximum depth of the search.
	 * @param  {string} target        - Optional target to reach.
	 * @param  {object} paths         - Optional paths object to maintain.
	 * @return {object}               - Returns the paths.
	 */
	function abstractDijkstraMultisource(
	  graph,
	  sources,
	  getEdgeWeight,
	  cutoff,
	  target,
	  paths
	) {
	  if (!isGraph$9(graph))
	    throw new Error(
	      'graphology-shortest-path/dijkstra: invalid graphology instance.'
	    );

	  if (target && !graph.hasNode(target))
	    throw new Error(
	      'graphology-shortest-path/dijkstra: the "' +
	        target +
	        '" target node does not exist in the given graph.'
	    );

	  getEdgeWeight = createEdgeWeightGetter$1(
	    getEdgeWeight || DEFAULT_WEIGHT_ATTRIBUTE
	  ).fromMinimalEntry;

	  var distances = {},
	    seen = {},
	    fringe = new Heap(DIJKSTRA_HEAP_COMPARATOR);

	  var count = 0,
	    edges,
	    item,
	    cost,
	    v,
	    u,
	    e,
	    d,
	    i,
	    j,
	    l,
	    m;

	  for (i = 0, l = sources.length; i < l; i++) {
	    v = sources[i];
	    seen[v] = 0;
	    fringe.push([0, count++, v]);

	    if (paths) paths[v] = [v];
	  }

	  while (fringe.size) {
	    item = fringe.pop();
	    d = item[0];
	    v = item[2];

	    if (v in distances) continue;

	    distances[v] = d;

	    if (v === target) break;

	    edges = graph.outboundEdges(v);

	    for (j = 0, m = edges.length; j < m; j++) {
	      e = edges[j];
	      u = graph.opposite(v, e);
	      cost = getEdgeWeight(e, graph.getEdgeAttributes(e)) + distances[v];

	      if (cutoff && cost > cutoff) continue;

	      if (u in distances && cost < distances[u]) {
	        throw Error(
	          'graphology-shortest-path/dijkstra: contradictory paths found. Do some of your edges have a negative weight?'
	        );
	      } else if (!(u in seen) || cost < seen[u]) {
	        seen[u] = cost;
	        fringe.push([cost, count++, u]);

	        if (paths) paths[u] = paths[v].concat(u);
	      }
	    }
	  }

	  return distances;
	}

	/**
	 * Single source Dijkstra shortest path between given node & other nodes in
	 * the graph.
	 *
	 * @param  {Graph}  graph         - The graphology instance.
	 * @param  {string} source        - Source node.
	 * @param  {string} getEdgeWeight - Name of the weight attribute or getter function.
	 * @return {object}               - An object of found paths.
	 */
	function singleSourceDijkstra(graph, source, getEdgeWeight) {
	  var paths = {};

	  abstractDijkstraMultisource(graph, [source], getEdgeWeight, 0, null, paths);

	  return paths;
	}

	function bidirectionalDijkstra(graph, source, target, getEdgeWeight) {
	  return abstractBidirectionalDijkstra(graph, source, target, getEdgeWeight)[1];
	}

	/**
	 * Function using Ulrik Brandes' method to map single source shortest paths
	 * from selected node.
	 *
	 * [Reference]:
	 * Ulrik Brandes: A Faster Algorithm for Betweenness Centrality.
	 * Journal of Mathematical Sociology 25(2):163-177, 2001.
	 *
	 * @param  {Graph}  graph         - Target graph.
	 * @param  {any}    source        - Source node.
	 * @param  {string} getEdgeWeight - Name of the weight attribute or getter function.
	 * @return {array}                - [Stack, Paths, Sigma]
	 */
	function brandes(graph, source, getEdgeWeight) {
	  source = '' + source;

	  getEdgeWeight = createEdgeWeightGetter$1(
	    getEdgeWeight || DEFAULT_WEIGHT_ATTRIBUTE
	  ).fromMinimalEntry;

	  var S = [],
	    P = {},
	    sigma = {};

	  var nodes = graph.nodes(),
	    edges,
	    item,
	    pred,
	    dist,
	    cost,
	    v,
	    w,
	    e,
	    i,
	    l;

	  for (i = 0, l = nodes.length; i < l; i++) {
	    v = nodes[i];
	    P[v] = [];
	    sigma[v] = 0;
	  }

	  var D = {};

	  sigma[source] = 1;

	  var seen = {};
	  seen[source] = 0;

	  var count = 0;

	  var Q = new Heap(BRANDES_DIJKSTRA_HEAP_COMPARATOR);
	  Q.push([0, count++, source, source]);

	  while (Q.size) {
	    item = Q.pop();
	    dist = item[0];
	    pred = item[2];
	    v = item[3];

	    if (v in D) continue;

	    sigma[v] += sigma[pred];
	    S.push(v);
	    D[v] = dist;

	    edges = graph.outboundEdges(v);

	    for (i = 0, l = edges.length; i < l; i++) {
	      e = edges[i];
	      w = graph.opposite(v, e);
	      cost = dist + getEdgeWeight(e, graph.getEdgeAttributes(e));

	      if (!(w in D) && (!(w in seen) || cost < seen[w])) {
	        seen[w] = cost;
	        Q.push([cost, count++, v, w]);
	        sigma[w] = 0;
	        P[w] = [v];
	      } else if (cost === seen[w]) {
	        sigma[w] += sigma[v];
	        P[w].push(v);
	      }
	    }
	  }

	  return [S, P, sigma];
	}

	/**
	 * Exporting.
	 */
	dijkstra.bidirectional = bidirectionalDijkstra;
	dijkstra.singleSource = singleSourceDijkstra;
	dijkstra.brandes = brandes;

	/**
	 * Graphology Shortest Path
	 * =========================
	 *
	 * Library endpoint.
	 */

	var unweighted = unweighted$1;
	var utils$6 = utils$7;

	shortestPath$2.unweighted = unweighted;
	shortestPath$2.dijkstra = dijkstra;

	shortestPath$2.bidirectional = unweighted.bidirectional;
	shortestPath$2.singleSource = unweighted.singleSource;
	shortestPath$2.singleSourceLength = unweighted.singleSourceLength;
	shortestPath$2.undirectedSingleSourceLength = unweighted.undirectedSingleSourceLength;
	shortestPath$2.brandes = unweighted.brandes;

	shortestPath$2.edgePathFromNodePath = utils$6.edgePathFromNodePath;

	var shortestPath$1 = shortestPath$2;

	var simplePath$2 = {};

	/**
	 * Graphology isGraph
	 * ===================
	 *
	 * Very simple function aiming at ensuring the given variable is a
	 * graphology instance.
	 */

	/**
	 * Checking the value is a graphology instance.
	 *
	 * @param  {any}     value - Target value.
	 * @return {boolean}
	 */
	var isGraph$8 = function isGraph(value) {
	  return (
	    value !== null &&
	    typeof value === 'object' &&
	    typeof value.addUndirectedEdgeWithKey === 'function' &&
	    typeof value.dropNode === 'function' &&
	    typeof value.multi === 'boolean'
	  );
	};

	/**
	 * Graphology Simple Path
	 * =======================
	 *
	 * Functions related to simple paths to be used with graphology.
	 */

	var isGraph$7 = isGraph$8;

	/**
	 * A StackSet helper class.
	 */
	function StackSet() {
	  this.set = new Set();
	  this.stack = [];
	}

	StackSet.prototype.has = function (value) {
	  return this.set.has(value);
	};

	// NOTE: we don't check earlier existence because we don't need to
	StackSet.prototype.push = function (value) {
	  this.stack.push(value);
	  this.set.add(value);
	};

	StackSet.prototype.pop = function () {
	  this.set.delete(this.stack.pop());
	};

	StackSet.prototype.path = function (value) {
	  return this.stack.concat(value);
	};

	StackSet.of = function (value, cycle) {
	  var set = new StackSet();

	  if (!cycle) {
	    // Normally we add source both to set & stack
	    set.push(value);
	  } else {
	    // But in case of cycle, we only add to stack so that we may reach the
	    // source again (as it was not already visited)
	    set.stack.push(value);
	  }

	  return set;
	};

	/**
	 * A RecordStackSet helper class.
	 */
	function RecordStackSet() {
	  this.set = new Set();
	  this.stack = [];
	}

	RecordStackSet.prototype.has = function (value) {
	  return this.set.has(value);
	};

	// NOTE: we don't check earlier existence because we don't need to
	RecordStackSet.prototype.push = function (record) {
	  this.stack.push(record);
	  this.set.add(record[1]);
	};

	RecordStackSet.prototype.pop = function () {
	  this.set.delete(this.stack.pop()[1]);
	};

	RecordStackSet.prototype.path = function (record) {
	  return this.stack
	    .slice(1)
	    .map(function (r) {
	      return r[0];
	    })
	    .concat([record[0]]);
	};

	RecordStackSet.of = function (value, cycle) {
	  var set = new RecordStackSet();
	  var record = [null, value];

	  if (!cycle) {
	    // Normally we add source both to set & stack
	    set.push(record);
	  } else {
	    // But in case of cycle, we only add to stack so that we may reach the
	    // source again (as it was not already visited)
	    set.stack.push(record);
	  }

	  return set;
	};

	/**
	 * Function returning all the paths between source & target in the graph.
	 *
	 * @param  {Graph}   graph      - Target graph.
	 * @param  {string}  source     - Source node.
	 * @param  {string}  target     - Target node.
	 * @param  {options} options    - Options:
	 * @param  {number}    maxDepth   - Max traversal depth (default: infinity).
	 * @return {array}              - The found paths.
	 */
	function allSimplePaths(graph, source, target, options) {
	  if (!isGraph$7(graph))
	    throw new Error(
	      'graphology-simple-path.allSimplePaths: expecting a graphology instance.'
	    );

	  if (!graph.hasNode(source))
	    throw new Error(
	      'graphology-simple-path.allSimplePaths: expecting: could not find source node "' +
	        source +
	        '" in the graph.'
	    );

	  if (!graph.hasNode(target))
	    throw new Error(
	      'graphology-simple-path.allSimplePaths: expecting: could not find target node "' +
	        target +
	        '" in the graph.'
	    );

	  options = options || {};
	  var maxDepth =
	    typeof options.maxDepth === 'number' ? options.maxDepth : Infinity;

	  source = '' + source;
	  target = '' + target;

	  var cycle = source === target;

	  var stack = [graph.outboundNeighbors(source)];
	  var visited = StackSet.of(source, cycle);

	  var paths = [];
	  var p;

	  var children, child;

	  while (stack.length !== 0) {
	    children = stack[stack.length - 1];
	    child = children.pop();

	    if (!child) {
	      stack.pop();
	      visited.pop();
	    } else {
	      if (visited.has(child)) continue;

	      if (child === target) {
	        p = visited.path(child);
	        paths.push(p);
	      }

	      visited.push(child);

	      if (!visited.has(target) && stack.length < maxDepth)
	        stack.push(graph.outboundNeighbors(child));
	      else visited.pop();
	    }
	  }

	  return paths;
	}

	/**
	 * Helpers used to collect edges with their targets.
	 */
	function collectEdges(graph, source) {
	  var records = [];

	  graph.forEachOutboundEdge(source, function (edge, attr, ext1, ext2) {
	    records.push([edge, source === ext1 ? ext2 : ext1]);
	  });

	  return records;
	}

	function collectMultiEdges(graph, source) {
	  var index = {};

	  var target;

	  graph.forEachOutboundEdge(source, function (edge, attr, ext1, ext2) {
	    target = source === ext1 ? ext2 : ext1;

	    if (!(target in index)) index[target] = [];

	    index[target].push(edge);
	  });

	  var records = [];

	  for (target in index) records.push([index[target], target]);

	  return records;
	}

	/**
	 * Function returning all the edge paths between source & target in the graph.
	 *
	 * @param  {Graph}  graph       - Target graph.
	 * @param  {string} source      - Source node.
	 * @param  {string} target      - Target node.
	 * @param  {options} options    - Options:
	 * @param  {number}    maxDepth   - Max traversal depth (default: infinity).
	 * @return {array}              - The found paths.
	 */
	function allSimpleEdgePaths(graph, source, target, options) {
	  if (!isGraph$7(graph))
	    throw new Error(
	      'graphology-simple-path.allSimpleEdgePaths: expecting a graphology instance.'
	    );

	  if (graph.multi)
	    throw new Error(
	      'graphology-simple-path.allSimpleEdgePaths: not implemented for multi graphs.'
	    );

	  if (!graph.hasNode(source))
	    throw new Error(
	      'graphology-simple-path.allSimpleEdgePaths: expecting: could not find source node "' +
	        source +
	        '" in the graph.'
	    );

	  if (!graph.hasNode(target))
	    throw new Error(
	      'graphology-simple-path.allSimpleEdgePaths: expecting: could not find target node "' +
	        target +
	        '" in the graph.'
	    );

	  options = options || {};
	  var maxDepth =
	    typeof options.maxDepth === 'number' ? options.maxDepth : Infinity;

	  source = '' + source;
	  target = '' + target;

	  var cycle = source === target;

	  var stack = [collectEdges(graph, source)];
	  var visited = RecordStackSet.of(source, cycle);

	  var paths = [];
	  var p;

	  var record, children, child;

	  while (stack.length !== 0) {
	    children = stack[stack.length - 1];
	    record = children.pop();

	    if (!record) {
	      stack.pop();
	      visited.pop();
	    } else {
	      child = record[1];

	      if (visited.has(child)) continue;

	      if (child === target) {
	        p = visited.path(record);
	        paths.push(p);
	      }

	      visited.push(record);

	      if (!visited.has(target) && stack.length < maxDepth)
	        stack.push(collectEdges(graph, child));
	      else visited.pop();
	    }
	  }

	  return paths;
	}

	/**
	 * Function returning all the compressed edge paths between source & target
	 * in the graph.
	 *
	 * @param  {Graph}  graph       - Target graph.
	 * @param  {string} source      - Source node.
	 * @param  {string} target      - Target node.
	 * @param  {options} options    - Options:
	 * @param  {number}    maxDepth   - Max traversal depth (default: infinity).
	 * @return {array}              - The found paths.
	 */
	function allSimpleEdgeGroupPaths(graph, source, target, options) {
	  if (!isGraph$7(graph))
	    throw new Error(
	      'graphology-simple-path.allSimpleEdgeGroupPaths: expecting a graphology instance.'
	    );

	  if (!graph.hasNode(source))
	    throw new Error(
	      'graphology-simple-path.allSimpleEdgeGroupPaths: expecting: could not find source node "' +
	        source +
	        '" in the graph.'
	    );

	  if (!graph.hasNode(target))
	    throw new Error(
	      'graphology-simple-path.allSimpleEdgeGroupPaths: expecting: could not find target node "' +
	        target +
	        '" in the graph.'
	    );

	  options = options || {};
	  var maxDepth =
	    typeof options.maxDepth === 'number' ? options.maxDepth : Infinity;

	  source = '' + source;
	  target = '' + target;

	  var cycle = source === target;

	  var stack = [collectMultiEdges(graph, source)];
	  var visited = RecordStackSet.of(source, cycle);

	  var paths = [];
	  var p;

	  var record, children, child;

	  while (stack.length !== 0) {
	    children = stack[stack.length - 1];
	    record = children.pop();

	    if (!record) {
	      stack.pop();
	      visited.pop();
	    } else {
	      child = record[1];

	      if (visited.has(child)) continue;

	      if (child === target) {
	        p = visited.path(record);
	        paths.push(p);
	      }

	      visited.push(record);

	      if (!visited.has(target) && stack.length < maxDepth)
	        stack.push(collectMultiEdges(graph, child));
	      else visited.pop();
	    }
	  }

	  return paths;
	}

	simplePath$2.allSimplePaths = allSimplePaths;
	simplePath$2.allSimpleEdgePaths = allSimpleEdgePaths;
	simplePath$2.allSimpleEdgeGroupPaths = allSimpleEdgeGroupPaths;

	var simplePath$1 = simplePath$2;

	var traversal$2 = {};

	var bfs = {};

	/**
	 * Graphology BFS Queue
	 * =====================
	 *
	 * An experiment to speed up BFS in graphs and connected component detection.
	 *
	 * It should mostly save memory and not improve theoretical runtime.
	 */

	var FixedDeque = fixedDeque;

	function BFSQueue$1(graph) {
	  this.graph = graph;
	  this.queue = new FixedDeque(Array, graph.order);
	  this.seen = new Set();
	  this.size = 0;
	}

	BFSQueue$1.prototype.hasAlreadySeenEverything = function () {
	  return this.seen.size === this.graph.order;
	};

	BFSQueue$1.prototype.countUnseenNodes = function () {
	  return this.graph.order - this.seen.size;
	};

	BFSQueue$1.prototype.forEachNodeYetUnseen = function (callback) {
	  var seen = this.seen;
	  var graph = this.graph;

	  graph.someNode(function (node, attr) {
	    // Useful early exit for connected graphs
	    if (seen.size === graph.order) return true; // break

	    // Node already seen?
	    if (seen.has(node)) return false; // continue

	    var shouldBreak = callback(node, attr);

	    if (shouldBreak) return true;

	    return false;
	  });
	};

	BFSQueue$1.prototype.has = function (node) {
	  return this.seen.has(node);
	};

	BFSQueue$1.prototype.push = function (node) {
	  var seenSizeBefore = this.seen.size;

	  this.seen.add(node);

	  // If node was already seen
	  if (seenSizeBefore === this.seen.size) return false;

	  this.queue.push(node);
	  this.size++;

	  return true;
	};

	BFSQueue$1.prototype.pushWith = function (node, item) {
	  var seenSizeBefore = this.seen.size;

	  this.seen.add(node);

	  // If node was already seen
	  if (seenSizeBefore === this.seen.size) return false;

	  this.queue.push(item);
	  this.size++;

	  return true;
	};

	BFSQueue$1.prototype.shift = function () {
	  var item = this.queue.shift();
	  this.size = this.queue.size;

	  return item;
	};

	var bfsQueue = BFSQueue$1;

	var utils$5 = {};

	/**
	 * Graphology Traversal Utils
	 * ===========================
	 *
	 * Miscellaneous utils used throughout the library.
	 */

	function TraversalRecord$2(node, attr, depth) {
	  this.node = node;
	  this.attributes = attr;
	  this.depth = depth;
	}

	function capitalize$2(string) {
	  return string[0].toUpperCase() + string.slice(1);
	}

	utils$5.TraversalRecord = TraversalRecord$2;
	utils$5.capitalize = capitalize$2;

	/**
	 * Graphology Traversal BFS
	 * =========================
	 *
	 * Breadth-First Search traversal function.
	 */

	var isGraph$6 = isGraph$N;
	var BFSQueue = bfsQueue;
	var utils$4 = utils$5;

	var TraversalRecord$1 = utils$4.TraversalRecord;
	var capitalize$1 = utils$4.capitalize;

	/**
	 * BFS traversal in the given graph using a callback function
	 *
	 * @param {Graph}    graph        - Target graph.
	 * @param {string}   startingNode - Optional Starting node.
	 * @param {function} callback     - Iteration callback.
	 * @param {object}   options      - Options:
	 * @param {string}     mode         - Traversal mode.
	 */
	function abstractBfs(graph, startingNode, callback, options) {
	  options = options || {};

	  if (!isGraph$6(graph))
	    throw new Error(
	      'graphology-traversal/bfs: expecting a graphology instance.'
	    );

	  if (typeof callback !== 'function')
	    throw new Error(
	      'graphology-traversal/bfs: given callback is not a function.'
	    );

	  // Early termination
	  if (graph.order === 0) return;

	  var queue = new BFSQueue(graph);

	  var forEachNeighbor =
	    graph['forEach' + capitalize$1(options.mode || 'outbound') + 'Neighbor'].bind(
	      graph
	    );

	  var forEachNode;

	  if (startingNode === null) {
	    forEachNode = queue.forEachNodeYetUnseen.bind(queue);
	  } else {
	    forEachNode = function (fn) {
	      startingNode = '' + startingNode;
	      fn(startingNode, graph.getNodeAttributes(startingNode));
	    };
	  }

	  var record, stop;

	  function visit(neighbor, attr) {
	    queue.pushWith(
	      neighbor,
	      new TraversalRecord$1(neighbor, attr, record.depth + 1)
	    );
	  }

	  forEachNode(function (node, attr) {
	    queue.pushWith(node, new TraversalRecord$1(node, attr, 0));

	    while (queue.size !== 0) {
	      record = queue.shift();

	      stop = callback(record.node, record.attributes, record.depth);

	      if (stop === true) continue;

	      forEachNeighbor(record.node, visit);
	    }
	  });
	}

	bfs.bfs = function (graph, callback, options) {
	  return abstractBfs(graph, null, callback, options);
	};
	bfs.bfsFromNode = abstractBfs;

	var dfs = {};

	/**
	 * Graphology Traversal DFS
	 * =========================
	 *
	 * Depth-First Search traversal function.
	 */

	var isGraph$5 = isGraph$N;
	var DFSStack = dfsStack;
	var utils$3 = utils$5;

	var TraversalRecord = utils$3.TraversalRecord;
	var capitalize = utils$3.capitalize;

	/**
	 * DFS traversal in the given graph using a callback function
	 *
	 * @param {Graph}    graph        - Target graph.
	 * @param {string}   startingNode - Optional Starting node.
	 * @param {function} callback     - Iteration callback.
	 * @param {object}   options      - Options:
	 * @param {string}     mode         - Traversal mode.
	 */
	function abstractDfs(graph, startingNode, callback, options) {
	  options = options || {};

	  if (!isGraph$5(graph))
	    throw new Error(
	      'graphology-traversal/dfs: expecting a graphology instance.'
	    );

	  if (typeof callback !== 'function')
	    throw new Error(
	      'graphology-traversal/dfs: given callback is not a function.'
	    );

	  // Early termination
	  if (graph.order === 0) return;

	  var stack = new DFSStack(graph);

	  var forEachNeighbor =
	    graph['forEach' + capitalize(options.mode || 'outbound') + 'Neighbor'].bind(
	      graph
	    );

	  var forEachNode;

	  if (startingNode === null) {
	    forEachNode = stack.forEachNodeYetUnseen.bind(stack);
	  } else {
	    forEachNode = function (fn) {
	      startingNode = '' + startingNode;
	      fn(startingNode, graph.getNodeAttributes(startingNode));
	    };
	  }

	  var record, stop;

	  function visit(neighbor, attr) {
	    stack.pushWith(
	      neighbor,
	      new TraversalRecord(neighbor, attr, record.depth + 1)
	    );
	  }

	  forEachNode(function (node, attr) {
	    stack.pushWith(node, new TraversalRecord(node, attr, 0));

	    while (stack.size !== 0) {
	      record = stack.pop();

	      stop = callback(record.node, record.attributes, record.depth);

	      if (stop === true) continue;

	      forEachNeighbor(record.node, visit);
	    }
	  });
	}

	dfs.dfs = function (graph, callback, options) {
	  return abstractDfs(graph, null, callback, options);
	};
	dfs.dfsFromNode = abstractDfs;

	var bfsModule = bfs;
	var dfsModule = dfs;

	traversal$2.bfs = bfsModule.bfs;
	traversal$2.bfsFromNode = bfsModule.bfsFromNode;
	traversal$2.dfs = dfsModule.dfs;
	traversal$2.dfsFromNode = dfsModule.dfsFromNode;

	var traversal$1 = traversal$2;

	var utils$2 = {};

	/**
	 * Graphology inferMulti
	 * ======================
	 *
	 * Useful function used to "guess" if the given graph is truly multi.
	 */

	var isGraph$4 = isGraph$N;

	/**
	 * Returning whether the given graph is inferred as multi.
	 *
	 * @param  {Graph}   graph - Target graph.
	 * @return {boolean}
	 */
	var inferMulti = function inferMulti(graph) {
	  if (!isGraph$4(graph))
	    throw new Error(
	      'graphology-utils/infer-multi: expecting a valid graphology instance.'
	    );

	  if (!graph.multi || graph.order === 0 || graph.size < 2) return false;

	  var multi = false;

	  // TODO: improve this with suitable methods
	  var previousSource, previousTarget, wasUndirected, tmp;

	  graph.forEachAssymetricAdjacencyEntry(function (s, t, sa, ta, e, ea, u) {
	    if (multi) return; // TODO: we need #.someAdjacencyEntry

	    if (u && s > t) {
	      tmp = t;
	      t = s;
	      s = tmp;
	    }

	    if (s === previousSource && t === previousTarget && u === wasUndirected) {
	      multi = true;
	      return;
	    }

	    previousSource = s;
	    previousTarget = t;
	    wasUndirected = u;
	  });

	  return multi;
	};

	/**
	 * Graphology mergeClique
	 * =======================
	 *
	 * Function merging the given clique to the graph.
	 */

	/**
	 * Merging the given clique to the graph.
	 *
	 * @param  {Graph} graph - Target graph.
	 * @param  {array} nodes - Nodes representing the clique to merge.
	 */
	var mergeClique = function mergeClique(graph, nodes) {
	  if (nodes.length === 0) return;

	  var source, target, i, j, l;

	  for (i = 0, l = nodes.length; i < l; i++) {
	    source = nodes[i];

	    for (j = i + 1; j < l; j++) {
	      target = nodes[j];

	      graph.mergeEdge(source, target);
	    }
	  }
	};

	/**
	 * Graphology mergeCycle
	 * =====================
	 *
	 * Function merging the given cycle to the graph.
	 */

	/**
	 * Merging the given cycle to the graph.
	 *
	 * @param  {Graph} graph - Target graph.
	 * @param  {array} nodes - Nodes representing the cycle to merge.
	 */
	var mergeCycle = function mergeCycle(graph, nodes) {
	  if (nodes.length === 0) return;

	  var previousNode, node, i, l;

	  graph.mergeNode(nodes[0]);

	  if (nodes.length === 1) return;

	  for (i = 1, l = nodes.length; i < l; i++) {
	    previousNode = nodes[i - 1];
	    node = nodes[i];

	    graph.mergeEdge(previousNode, node);
	  }

	  graph.mergeEdge(node, nodes[0]);
	};

	/**
	 * Graphology mergePath
	 * =====================
	 *
	 * Function merging the given path to the graph.
	 */

	/**
	 * Merging the given path to the graph.
	 *
	 * @param  {Graph} graph - Target graph.
	 * @param  {array} nodes - Nodes representing the path to merge.
	 */
	var mergePath = function mergePath(graph, nodes) {
	  if (nodes.length === 0) return;

	  var previousNode, node, i, l;

	  graph.mergeNode(nodes[0]);

	  for (i = 1, l = nodes.length; i < l; i++) {
	    previousNode = nodes[i - 1];
	    node = nodes[i];

	    graph.mergeEdge(previousNode, node);
	  }
	};

	/**
	 * Graphology Rename Graph Keys
	 * =============================
	 *
	 * Helpers allowing you to rename (ie. change the key) of nodes & edges .
	 */

	var copyEdge$1 = addEdge.copyEdge;

	var renameGraphKeys = function renameGraphKeys(
	  graph,
	  nodeKeyMapping,
	  edgeKeyMapping
	) {
	  if (typeof nodeKeyMapping === 'undefined') nodeKeyMapping = {};
	  if (typeof edgeKeyMapping === 'undefined') edgeKeyMapping = {};

	  var renamed = graph.nullCopy();

	  // Renaming nodes
	  graph.forEachNode(function (key, attr) {
	    var renamedKey = nodeKeyMapping[key];

	    if (typeof renamedKey === 'undefined') renamedKey = key;

	    renamed.addNode(renamedKey, attr);
	  });

	  // Renaming edges
	  var currentSource, currentSourceRenamed;

	  graph.forEachAssymetricAdjacencyEntry(function (
	    source,
	    target,
	    _sa,
	    _ta,
	    key,
	    attr,
	    undirected
	  ) {
	    // Leveraging the ordered adjacency to save lookups
	    if (source !== currentSource) {
	      currentSource = source;
	      currentSourceRenamed = nodeKeyMapping[source];

	      if (typeof currentSourceRenamed === 'undefined')
	        currentSourceRenamed = source;
	    }

	    var targetRenamed = nodeKeyMapping[target];

	    if (typeof targetRenamed === 'undefined') targetRenamed = target;

	    var renamedKey = edgeKeyMapping[key];

	    if (typeof renamedKey === 'undefined') renamedKey = key;

	    copyEdge$1(
	      renamed,
	      undirected,
	      renamedKey,
	      currentSourceRenamed,
	      targetRenamed,
	      attr
	    );
	  });

	  return renamed;
	};

	/**
	 * Graphology Update Graph Keys
	 * =============================
	 *
	 * Helpers allowing you to update keys of nodes & edges .
	 */

	var copyEdge = addEdge.copyEdge;

	var updateGraphKeys = function updateGraphKeys(
	  graph,
	  nodeKeyUpdater,
	  edgeKeyUpdater
	) {
	  var renamed = graph.nullCopy();

	  // Renaming nodes
	  graph.forEachNode(function (key, attr) {
	    var renamedKey = nodeKeyUpdater ? nodeKeyUpdater(key, attr) : key;
	    renamed.addNode(renamedKey, attr);
	  });

	  // Renaming edges
	  var currentSource, currentSourceRenamed;

	  graph.forEachAssymetricAdjacencyEntry(function (
	    source,
	    target,
	    sourceAttr,
	    targetAttr,
	    key,
	    attr,
	    undirected
	  ) {
	    // Leveraging the ordered adjacency to save calls
	    if (source !== currentSource) {
	      currentSource = source;
	      currentSourceRenamed = nodeKeyUpdater
	        ? nodeKeyUpdater(source, sourceAttr)
	        : source;
	    }

	    var targetRenamed = nodeKeyUpdater
	      ? nodeKeyUpdater(target, targetAttr)
	      : target;

	    var renamedKey = edgeKeyUpdater
	      ? edgeKeyUpdater(
	          key,
	          attr,
	          source,
	          target,
	          sourceAttr,
	          targetAttr,
	          undirected
	        )
	      : key;

	    copyEdge(
	      renamed,
	      undirected,
	      renamedKey,
	      currentSourceRenamed,
	      targetRenamed,
	      attr
	    );
	  });

	  return renamed;
	};

	/**
	 * Graphology Utils
	 * =================
	 *
	 * Library endpoint.
	 */

	utils$2.inferMulti = inferMulti;
	utils$2.inferType = inferType$4;
	utils$2.isGraph = isGraph$N;
	utils$2.isGraphConstructor = isGraphConstructor$e;
	utils$2.mergeClique = mergeClique;
	utils$2.mergeCycle = mergeCycle;
	utils$2.mergePath = mergePath;
	utils$2.mergeStar = mergeStar$1;
	utils$2.renameGraphKeys = renameGraphKeys;
	utils$2.updateGraphKeys = updateGraphKeys;

	var utils$1 = utils$2;

	/**
	 * Graphology Force Layout Iteration
	 * ==================================
	 *
	 * Function describing a single iteration of the force layout.
	 */

	const {
	  createNodeValueGetter,
	  createEdgeValueGetter
	} = getters$1;

	// const EPSILON = -Infinity;

	// function isVeryCloseToZero(x) {
	//   return Math.abs(x) < EPSILON;
	// }

	var iterate$1 = function iterate(graph, nodeStates, params) {
	  const {nodeXAttribute: xKey, nodeYAttribute: yKey} = params;
	  const {attraction, repulsion, gravity, inertia, maxMove} = params.settings;

	  let {shouldSkipNode, shouldSkipEdge, isNodeFixed} = params;

	  isNodeFixed = createNodeValueGetter(isNodeFixed);
	  shouldSkipNode = createNodeValueGetter(shouldSkipNode, false);
	  shouldSkipEdge = createEdgeValueGetter(shouldSkipEdge, false);

	  const nodes = graph.filterNodes((n, attr) => {
	    return !shouldSkipNode.fromEntry(n, attr);
	  });

	  const adjustedOrder = nodes.length;

	  // Check nodeStatess and inertia
	  for (let i = 0; i < adjustedOrder; i++) {
	    const n = nodes[i];
	    const attr = graph.getNodeAttributes(n);
	    const nodeState = nodeStates[n];

	    if (!nodeState)
	      nodeStates[n] = {
	        dx: 0,
	        dy: 0,
	        x: attr[xKey] || 0,
	        y: attr[yKey] || 0
	      };
	    else
	      nodeStates[n] = {
	        dx: nodeState.dx * inertia,
	        dy: nodeState.dy * inertia,
	        x: attr[xKey] || 0,
	        y: attr[yKey] || 0
	      };
	  }

	  // Repulsion
	  if (repulsion)
	    for (let i = 0; i < adjustedOrder; i++) {
	      const n1 = nodes[i];
	      const n1State = nodeStates[n1];

	      for (let j = i + 1; j < adjustedOrder; j++) {
	        const n2 = nodes[j];
	        const n2State = nodeStates[n2];

	        // Compute distance:
	        const dx = n2State.x - n1State.x;
	        const dy = n2State.y - n1State.y;
	        const distance = Math.sqrt(dx * dx + dy * dy) || 1;

	        // Repulse nodes relatively to 1 / distance:
	        const repulsionX = (repulsion / distance) * dx;
	        const repulsionY = (repulsion / distance) * dy;
	        n1State.dx -= repulsionX;
	        n1State.dy -= repulsionY;
	        n2State.dx += repulsionX;
	        n2State.dy += repulsionY;
	      }
	    }

	  // Attraction
	  if (attraction)
	    graph.forEachEdge(
	      (edge, attr, source, target, sourceAttr, targetAttr, undirected) => {
	        if (source === target) return;

	        if (
	          shouldSkipNode.fromEntry(source, sourceAttr) ||
	          shouldSkipNode.fromEntry(target, targetAttr)
	        )
	          return;

	        if (
	          shouldSkipEdge.fromEntry(
	            edge,
	            attr,
	            source,
	            target,
	            sourceAttr,
	            targetAttr,
	            undirected
	          )
	        )
	          return;

	        const n1State = nodeStates[source];
	        const n2State = nodeStates[target];

	        // Compute distance:
	        const dx = n2State.x - n1State.x;
	        const dy = n2State.y - n1State.y;

	        const distance = Math.sqrt(dx * dx + dy * dy) || 1;

	        // Attract nodes relatively to their distance:
	        const attractionX = attraction * distance * dx;
	        const attractionY = attraction * distance * dy;
	        n1State.dx += attractionX;
	        n1State.dy += attractionY;
	        n2State.dx -= attractionX;
	        n2State.dy -= attractionY;
	      }
	    );

	  // Gravity
	  if (gravity)
	    for (let i = 0; i < adjustedOrder; i++) {
	      const n = nodes[i];
	      const nodeState = nodeStates[n];

	      // Attract nodes to [0, 0] relatively to the distance:
	      const {x, y} = nodeState;
	      const distance = Math.sqrt(x * x + y * y) || 1;
	      nodeStates[n].dx -= x * gravity * distance;
	      nodeStates[n].dy -= y * gravity * distance;
	    }

	  // Apply forces
	  const converged = false;

	  for (let i = 0; i < adjustedOrder; i++) {
	    const n = nodes[i];
	    const nodeState = nodeStates[n];

	    const distance = Math.sqrt(
	      nodeState.dx * nodeState.dx + nodeState.dy * nodeState.dy
	    );

	    if (distance > maxMove) {
	      nodeState.dx *= maxMove / distance;
	      nodeState.dy *= maxMove / distance;
	    }

	    // if (!isVeryCloseToZero(nodeState.dx) || !isVeryCloseToZero(nodeState.dy)) {
	    //   converged = false;
	    // }

	    if (!isNodeFixed.fromGraph(graph, n)) {
	      nodeState.x += nodeState.dx;
	      nodeState.y += nodeState.dy;
	      nodeState.fixed = false;
	    } else {
	      nodeState.fixed = true;
	    }

	    // NOTE: possibility to assign here to save one loop in the future
	  }

	  return {converged};
	};

	var helpers$5 = {};

	/**
	 * Graphology Force Layout Helpers
	 * ================================
	 *
	 * Miscellaneous helper functions related to the force layout.
	 */

	helpers$5.assignLayoutChanges = function (graph, nodeStates, params) {
	  const {nodeXAttribute: x, nodeYAttribute: y} = params;

	  graph.updateEachNodeAttributes(
	    (n, attr) => {
	      const state = nodeStates[n];

	      if (!state || state.fixed) return attr;

	      attr[x] = state.x;
	      attr[y] = state.y;

	      return attr;
	    },
	    {attributes: ['x', 'y']}
	  );
	};

	helpers$5.collectLayoutChanges = function (nodeStates) {
	  const mapping = {};

	  for (const n in nodeStates) {
	    const state = nodeStates[n];

	    mapping[n] = {x: state.x, y: state.y};
	  }

	  return mapping;
	};

	/**
	 * Graphology Force Layout Defaults
	 * =================================
	 *
	 * Default options & settings used by the library.
	 */

	var defaults$1 = {
	  nodeXAttribute: 'x',
	  nodeYAttribute: 'y',
	  isNodeFixed: 'fixed',
	  shouldSkipNode: null,
	  shouldSkipEdge: null,
	  settings: {
	    attraction: 0.0005,
	    repulsion: 0.1,
	    gravity: 0.0001,
	    inertia: 0.6,
	    maxMove: 200
	  }
	};

	/**
	 * Graphology Force Layout Worker
	 * ===============================
	 *
	 * A worker made for running a force layout live.
	 *
	 * Note that it does not run in a webworker yet but respect animation frames.
	 */

	const isGraph$3 = isGraph$N;
	const resolveDefaults = defaults$7;

	const iterate = iterate$1;
	const helpers$4 = helpers$5;
	const DEFAULTS$2 = defaults$1;

	function ForceSupervisor(graph, params) {
	  // Validation
	  if (!isGraph$3(graph))
	    throw new Error(
	      'graphology-layout-force/worker: the given graph is not a valid graphology instance.'
	    );

	  params = resolveDefaults(params, DEFAULTS$2);

	  this.callbacks = {};

	  if (params.onConverged) this.callbacks.onConverged = params.onConverged;

	  this.graph = graph;
	  this.params = params;
	  this.nodeStates = {};
	  this.frameID = null;
	  this.running = false;
	  this.killed = false;

	  // TODO: hook listeners on graph to listen to dropNode, dropEdge, clear, clearEdges
	}

	ForceSupervisor.prototype.isRunning = function () {
	  return this.running;
	};

	ForceSupervisor.prototype.runFrame = function () {
	  let {converged} = iterate(this.graph, this.nodeStates, this.params);

	  helpers$4.assignLayoutChanges(this.graph, this.nodeStates, this.params);

	  // TODO: figure out convergence
	  converged = false;

	  if (converged) {
	    if (this.callbacks.onConverged) this.callbacks.onConverged();
	    this.stop();
	  } else {
	    this.frameID = window.requestAnimationFrame(() => this.runFrame());
	  }
	};

	ForceSupervisor.prototype.stop = function () {
	  this.running = false;

	  if (this.frameID !== null) {
	    window.cancelAnimationFrame(this.frameID);
	    this.frameID = null;
	  }

	  return this;
	};

	ForceSupervisor.prototype.start = function () {
	  if (this.killed)
	    throw new Error('graphology-layout-force/worker.start: layout was killed.');

	  if (this.running) return;

	  this.running = true;
	  this.runFrame();
	};

	ForceSupervisor.prototype.kill = function () {
	  this.stop();
	  delete this.nodeStates;
	  this.killed = true;

	  // TODO: cleanup events
	};

	var worker$2 = ForceSupervisor;

	/**
	 * Graphology ForceAtlas2 Layout Webworker
	 * ========================================
	 *
	 * Web worker able to run the layout in a separate thread.
	 */

	var webworker$1 = function worker() {
	  var NODES, EDGES;

	  var moduleShim = {};

	  (function () {
	    /* eslint no-constant-condition: 0 */
	/**
	 * Graphology ForceAtlas2 Iteration
	 * =================================
	 *
	 * Function used to perform a single iteration of the algorithm.
	 */

	/**
	 * Matrices properties accessors.
	 */
	var NODE_X = 0;
	var NODE_Y = 1;
	var NODE_DX = 2;
	var NODE_DY = 3;
	var NODE_OLD_DX = 4;
	var NODE_OLD_DY = 5;
	var NODE_MASS = 6;
	var NODE_CONVERGENCE = 7;
	var NODE_SIZE = 8;
	var NODE_FIXED = 9;

	var EDGE_SOURCE = 0;
	var EDGE_TARGET = 1;
	var EDGE_WEIGHT = 2;

	var REGION_NODE = 0;
	var REGION_CENTER_X = 1;
	var REGION_CENTER_Y = 2;
	var REGION_SIZE = 3;
	var REGION_NEXT_SIBLING = 4;
	var REGION_FIRST_CHILD = 5;
	var REGION_MASS = 6;
	var REGION_MASS_CENTER_X = 7;
	var REGION_MASS_CENTER_Y = 8;

	var SUBDIVISION_ATTEMPTS = 3;

	/**
	 * Constants.
	 */
	var PPN = 10;
	var PPE = 3;
	var PPR = 9;

	var MAX_FORCE = 10;

	/**
	 * Function used to perform a single interation of the algorithm.
	 *
	 * @param  {object}       options    - Layout options.
	 * @param  {Float32Array} NodeMatrix - Node data.
	 * @param  {Float32Array} EdgeMatrix - Edge data.
	 * @return {object}                  - Some metadata.
	 */
	moduleShim.exports = function iterate(options, NodeMatrix, EdgeMatrix) {
	  // Initializing variables
	  var l, r, n, n1, n2, rn, e, w, g, s;

	  var order = NodeMatrix.length,
	    size = EdgeMatrix.length;

	  var adjustSizes = options.adjustSizes;

	  var thetaSquared = options.barnesHutTheta * options.barnesHutTheta;

	  var outboundAttCompensation, coefficient, xDist, yDist, ewc, distance, factor;

	  var RegionMatrix = [];

	  // 1) Initializing layout data
	  //-----------------------------

	  // Resetting positions & computing max values
	  for (n = 0; n < order; n += PPN) {
	    NodeMatrix[n + NODE_OLD_DX] = NodeMatrix[n + NODE_DX];
	    NodeMatrix[n + NODE_OLD_DY] = NodeMatrix[n + NODE_DY];
	    NodeMatrix[n + NODE_DX] = 0;
	    NodeMatrix[n + NODE_DY] = 0;
	  }

	  // If outbound attraction distribution, compensate
	  if (options.outboundAttractionDistribution) {
	    outboundAttCompensation = 0;
	    for (n = 0; n < order; n += PPN) {
	      outboundAttCompensation += NodeMatrix[n + NODE_MASS];
	    }

	    outboundAttCompensation /= order / PPN;
	  }

	  // 1.bis) Barnes-Hut computation
	  //------------------------------

	  if (options.barnesHutOptimize) {
	    // Setting up
	    var minX = Infinity,
	      maxX = -Infinity,
	      minY = Infinity,
	      maxY = -Infinity,
	      q,
	      q2,
	      subdivisionAttempts;

	    // Computing min and max values
	    for (n = 0; n < order; n += PPN) {
	      minX = Math.min(minX, NodeMatrix[n + NODE_X]);
	      maxX = Math.max(maxX, NodeMatrix[n + NODE_X]);
	      minY = Math.min(minY, NodeMatrix[n + NODE_Y]);
	      maxY = Math.max(maxY, NodeMatrix[n + NODE_Y]);
	    }

	    // squarify bounds, it's a quadtree
	    var dx = maxX - minX,
	      dy = maxY - minY;
	    if (dx > dy) {
	      minY -= (dx - dy) / 2;
	      maxY = minY + dx;
	    } else {
	      minX -= (dy - dx) / 2;
	      maxX = minX + dy;
	    }

	    // Build the Barnes Hut root region
	    RegionMatrix[0 + REGION_NODE] = -1;
	    RegionMatrix[0 + REGION_CENTER_X] = (minX + maxX) / 2;
	    RegionMatrix[0 + REGION_CENTER_Y] = (minY + maxY) / 2;
	    RegionMatrix[0 + REGION_SIZE] = Math.max(maxX - minX, maxY - minY);
	    RegionMatrix[0 + REGION_NEXT_SIBLING] = -1;
	    RegionMatrix[0 + REGION_FIRST_CHILD] = -1;
	    RegionMatrix[0 + REGION_MASS] = 0;
	    RegionMatrix[0 + REGION_MASS_CENTER_X] = 0;
	    RegionMatrix[0 + REGION_MASS_CENTER_Y] = 0;

	    // Add each node in the tree
	    l = 1;
	    for (n = 0; n < order; n += PPN) {
	      // Current region, starting with root
	      r = 0;
	      subdivisionAttempts = SUBDIVISION_ATTEMPTS;

	      while (true) {
	        // Are there sub-regions?

	        // We look at first child index
	        if (RegionMatrix[r + REGION_FIRST_CHILD] >= 0) {
	          // There are sub-regions

	          // We just iterate to find a "leaf" of the tree
	          // that is an empty region or a region with a single node
	          // (see next case)

	          // Find the quadrant of n
	          if (NodeMatrix[n + NODE_X] < RegionMatrix[r + REGION_CENTER_X]) {
	            if (NodeMatrix[n + NODE_Y] < RegionMatrix[r + REGION_CENTER_Y]) {
	              // Top Left quarter
	              q = RegionMatrix[r + REGION_FIRST_CHILD];
	            } else {
	              // Bottom Left quarter
	              q = RegionMatrix[r + REGION_FIRST_CHILD] + PPR;
	            }
	          } else {
	            if (NodeMatrix[n + NODE_Y] < RegionMatrix[r + REGION_CENTER_Y]) {
	              // Top Right quarter
	              q = RegionMatrix[r + REGION_FIRST_CHILD] + PPR * 2;
	            } else {
	              // Bottom Right quarter
	              q = RegionMatrix[r + REGION_FIRST_CHILD] + PPR * 3;
	            }
	          }

	          // Update center of mass and mass (we only do it for non-leave regions)
	          RegionMatrix[r + REGION_MASS_CENTER_X] =
	            (RegionMatrix[r + REGION_MASS_CENTER_X] *
	              RegionMatrix[r + REGION_MASS] +
	              NodeMatrix[n + NODE_X] * NodeMatrix[n + NODE_MASS]) /
	            (RegionMatrix[r + REGION_MASS] + NodeMatrix[n + NODE_MASS]);

	          RegionMatrix[r + REGION_MASS_CENTER_Y] =
	            (RegionMatrix[r + REGION_MASS_CENTER_Y] *
	              RegionMatrix[r + REGION_MASS] +
	              NodeMatrix[n + NODE_Y] * NodeMatrix[n + NODE_MASS]) /
	            (RegionMatrix[r + REGION_MASS] + NodeMatrix[n + NODE_MASS]);

	          RegionMatrix[r + REGION_MASS] += NodeMatrix[n + NODE_MASS];

	          // Iterate on the right quadrant
	          r = q;
	          continue;
	        } else {
	          // There are no sub-regions: we are in a "leaf"

	          // Is there a node in this leave?
	          if (RegionMatrix[r + REGION_NODE] < 0) {
	            // There is no node in region:
	            // we record node n and go on
	            RegionMatrix[r + REGION_NODE] = n;
	            break;
	          } else {
	            // There is a node in this region

	            // We will need to create sub-regions, stick the two
	            // nodes (the old one r[0] and the new one n) in two
	            // subregions. If they fall in the same quadrant,
	            // we will iterate.

	            // Create sub-regions
	            RegionMatrix[r + REGION_FIRST_CHILD] = l * PPR;
	            w = RegionMatrix[r + REGION_SIZE] / 2; // new size (half)

	            // NOTE: we use screen coordinates
	            // from Top Left to Bottom Right

	            // Top Left sub-region
	            g = RegionMatrix[r + REGION_FIRST_CHILD];

	            RegionMatrix[g + REGION_NODE] = -1;
	            RegionMatrix[g + REGION_CENTER_X] =
	              RegionMatrix[r + REGION_CENTER_X] - w;
	            RegionMatrix[g + REGION_CENTER_Y] =
	              RegionMatrix[r + REGION_CENTER_Y] - w;
	            RegionMatrix[g + REGION_SIZE] = w;
	            RegionMatrix[g + REGION_NEXT_SIBLING] = g + PPR;
	            RegionMatrix[g + REGION_FIRST_CHILD] = -1;
	            RegionMatrix[g + REGION_MASS] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_X] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_Y] = 0;

	            // Bottom Left sub-region
	            g += PPR;
	            RegionMatrix[g + REGION_NODE] = -1;
	            RegionMatrix[g + REGION_CENTER_X] =
	              RegionMatrix[r + REGION_CENTER_X] - w;
	            RegionMatrix[g + REGION_CENTER_Y] =
	              RegionMatrix[r + REGION_CENTER_Y] + w;
	            RegionMatrix[g + REGION_SIZE] = w;
	            RegionMatrix[g + REGION_NEXT_SIBLING] = g + PPR;
	            RegionMatrix[g + REGION_FIRST_CHILD] = -1;
	            RegionMatrix[g + REGION_MASS] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_X] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_Y] = 0;

	            // Top Right sub-region
	            g += PPR;
	            RegionMatrix[g + REGION_NODE] = -1;
	            RegionMatrix[g + REGION_CENTER_X] =
	              RegionMatrix[r + REGION_CENTER_X] + w;
	            RegionMatrix[g + REGION_CENTER_Y] =
	              RegionMatrix[r + REGION_CENTER_Y] - w;
	            RegionMatrix[g + REGION_SIZE] = w;
	            RegionMatrix[g + REGION_NEXT_SIBLING] = g + PPR;
	            RegionMatrix[g + REGION_FIRST_CHILD] = -1;
	            RegionMatrix[g + REGION_MASS] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_X] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_Y] = 0;

	            // Bottom Right sub-region
	            g += PPR;
	            RegionMatrix[g + REGION_NODE] = -1;
	            RegionMatrix[g + REGION_CENTER_X] =
	              RegionMatrix[r + REGION_CENTER_X] + w;
	            RegionMatrix[g + REGION_CENTER_Y] =
	              RegionMatrix[r + REGION_CENTER_Y] + w;
	            RegionMatrix[g + REGION_SIZE] = w;
	            RegionMatrix[g + REGION_NEXT_SIBLING] =
	              RegionMatrix[r + REGION_NEXT_SIBLING];
	            RegionMatrix[g + REGION_FIRST_CHILD] = -1;
	            RegionMatrix[g + REGION_MASS] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_X] = 0;
	            RegionMatrix[g + REGION_MASS_CENTER_Y] = 0;

	            l += 4;

	            // Now the goal is to find two different sub-regions
	            // for the two nodes: the one previously recorded (r[0])
	            // and the one we want to add (n)

	            // Find the quadrant of the old node
	            if (
	              NodeMatrix[RegionMatrix[r + REGION_NODE] + NODE_X] <
	              RegionMatrix[r + REGION_CENTER_X]
	            ) {
	              if (
	                NodeMatrix[RegionMatrix[r + REGION_NODE] + NODE_Y] <
	                RegionMatrix[r + REGION_CENTER_Y]
	              ) {
	                // Top Left quarter
	                q = RegionMatrix[r + REGION_FIRST_CHILD];
	              } else {
	                // Bottom Left quarter
	                q = RegionMatrix[r + REGION_FIRST_CHILD] + PPR;
	              }
	            } else {
	              if (
	                NodeMatrix[RegionMatrix[r + REGION_NODE] + NODE_Y] <
	                RegionMatrix[r + REGION_CENTER_Y]
	              ) {
	                // Top Right quarter
	                q = RegionMatrix[r + REGION_FIRST_CHILD] + PPR * 2;
	              } else {
	                // Bottom Right quarter
	                q = RegionMatrix[r + REGION_FIRST_CHILD] + PPR * 3;
	              }
	            }

	            // We remove r[0] from the region r, add its mass to r and record it in q
	            RegionMatrix[r + REGION_MASS] =
	              NodeMatrix[RegionMatrix[r + REGION_NODE] + NODE_MASS];
	            RegionMatrix[r + REGION_MASS_CENTER_X] =
	              NodeMatrix[RegionMatrix[r + REGION_NODE] + NODE_X];
	            RegionMatrix[r + REGION_MASS_CENTER_Y] =
	              NodeMatrix[RegionMatrix[r + REGION_NODE] + NODE_Y];

	            RegionMatrix[q + REGION_NODE] = RegionMatrix[r + REGION_NODE];
	            RegionMatrix[r + REGION_NODE] = -1;

	            // Find the quadrant of n
	            if (NodeMatrix[n + NODE_X] < RegionMatrix[r + REGION_CENTER_X]) {
	              if (NodeMatrix[n + NODE_Y] < RegionMatrix[r + REGION_CENTER_Y]) {
	                // Top Left quarter
	                q2 = RegionMatrix[r + REGION_FIRST_CHILD];
	              } else {
	                // Bottom Left quarter
	                q2 = RegionMatrix[r + REGION_FIRST_CHILD] + PPR;
	              }
	            } else {
	              if (NodeMatrix[n + NODE_Y] < RegionMatrix[r + REGION_CENTER_Y]) {
	                // Top Right quarter
	                q2 = RegionMatrix[r + REGION_FIRST_CHILD] + PPR * 2;
	              } else {
	                // Bottom Right quarter
	                q2 = RegionMatrix[r + REGION_FIRST_CHILD] + PPR * 3;
	              }
	            }

	            if (q === q2) {
	              // If both nodes are in the same quadrant,
	              // we have to try it again on this quadrant
	              if (subdivisionAttempts--) {
	                r = q;
	                continue; // while
	              } else {
	                // we are out of precision here, and we cannot subdivide anymore
	                // but we have to break the loop anyway
	                subdivisionAttempts = SUBDIVISION_ATTEMPTS;
	                break; // while
	              }
	            }

	            // If both quadrants are different, we record n
	            // in its quadrant
	            RegionMatrix[q2 + REGION_NODE] = n;
	            break;
	          }
	        }
	      }
	    }
	  }

	  // 2) Repulsion
	  //--------------
	  // NOTES: adjustSizes = antiCollision & scalingRatio = coefficient

	  if (options.barnesHutOptimize) {
	    coefficient = options.scalingRatio;

	    // Applying repulsion through regions
	    for (n = 0; n < order; n += PPN) {
	      // Computing leaf quad nodes iteration

	      r = 0; // Starting with root region
	      while (true) {
	        if (RegionMatrix[r + REGION_FIRST_CHILD] >= 0) {
	          // The region has sub-regions

	          // We run the Barnes Hut test to see if we are at the right distance
	          distance =
	            Math.pow(
	              NodeMatrix[n + NODE_X] - RegionMatrix[r + REGION_MASS_CENTER_X],
	              2
	            ) +
	            Math.pow(
	              NodeMatrix[n + NODE_Y] - RegionMatrix[r + REGION_MASS_CENTER_Y],
	              2
	            );

	          s = RegionMatrix[r + REGION_SIZE];

	          if ((4 * s * s) / distance < thetaSquared) {
	            // We treat the region as a single body, and we repulse

	            xDist =
	              NodeMatrix[n + NODE_X] - RegionMatrix[r + REGION_MASS_CENTER_X];
	            yDist =
	              NodeMatrix[n + NODE_Y] - RegionMatrix[r + REGION_MASS_CENTER_Y];

	            if (adjustSizes === true) {
	              //-- Linear Anti-collision Repulsion
	              if (distance > 0) {
	                factor =
	                  (coefficient *
	                    NodeMatrix[n + NODE_MASS] *
	                    RegionMatrix[r + REGION_MASS]) /
	                  distance;

	                NodeMatrix[n + NODE_DX] += xDist * factor;
	                NodeMatrix[n + NODE_DY] += yDist * factor;
	              } else if (distance < 0) {
	                factor =
	                  (-coefficient *
	                    NodeMatrix[n + NODE_MASS] *
	                    RegionMatrix[r + REGION_MASS]) /
	                  Math.sqrt(distance);

	                NodeMatrix[n + NODE_DX] += xDist * factor;
	                NodeMatrix[n + NODE_DY] += yDist * factor;
	              }
	            } else {
	              //-- Linear Repulsion
	              if (distance > 0) {
	                factor =
	                  (coefficient *
	                    NodeMatrix[n + NODE_MASS] *
	                    RegionMatrix[r + REGION_MASS]) /
	                  distance;

	                NodeMatrix[n + NODE_DX] += xDist * factor;
	                NodeMatrix[n + NODE_DY] += yDist * factor;
	              }
	            }

	            // When this is done, we iterate. We have to look at the next sibling.
	            r = RegionMatrix[r + REGION_NEXT_SIBLING];
	            if (r < 0) break; // No next sibling: we have finished the tree

	            continue;
	          } else {
	            // The region is too close and we have to look at sub-regions
	            r = RegionMatrix[r + REGION_FIRST_CHILD];
	            continue;
	          }
	        } else {
	          // The region has no sub-region
	          // If there is a node r[0] and it is not n, then repulse
	          rn = RegionMatrix[r + REGION_NODE];

	          if (rn >= 0 && rn !== n) {
	            xDist = NodeMatrix[n + NODE_X] - NodeMatrix[rn + NODE_X];
	            yDist = NodeMatrix[n + NODE_Y] - NodeMatrix[rn + NODE_Y];

	            distance = xDist * xDist + yDist * yDist;

	            if (adjustSizes === true) {
	              //-- Linear Anti-collision Repulsion
	              if (distance > 0) {
	                factor =
	                  (coefficient *
	                    NodeMatrix[n + NODE_MASS] *
	                    NodeMatrix[rn + NODE_MASS]) /
	                  distance;

	                NodeMatrix[n + NODE_DX] += xDist * factor;
	                NodeMatrix[n + NODE_DY] += yDist * factor;
	              } else if (distance < 0) {
	                factor =
	                  (-coefficient *
	                    NodeMatrix[n + NODE_MASS] *
	                    NodeMatrix[rn + NODE_MASS]) /
	                  Math.sqrt(distance);

	                NodeMatrix[n + NODE_DX] += xDist * factor;
	                NodeMatrix[n + NODE_DY] += yDist * factor;
	              }
	            } else {
	              //-- Linear Repulsion
	              if (distance > 0) {
	                factor =
	                  (coefficient *
	                    NodeMatrix[n + NODE_MASS] *
	                    NodeMatrix[rn + NODE_MASS]) /
	                  distance;

	                NodeMatrix[n + NODE_DX] += xDist * factor;
	                NodeMatrix[n + NODE_DY] += yDist * factor;
	              }
	            }
	          }

	          // When this is done, we iterate. We have to look at the next sibling.
	          r = RegionMatrix[r + REGION_NEXT_SIBLING];

	          if (r < 0) break; // No next sibling: we have finished the tree

	          continue;
	        }
	      }
	    }
	  } else {
	    coefficient = options.scalingRatio;

	    // Square iteration
	    for (n1 = 0; n1 < order; n1 += PPN) {
	      for (n2 = 0; n2 < n1; n2 += PPN) {
	        // Common to both methods
	        xDist = NodeMatrix[n1 + NODE_X] - NodeMatrix[n2 + NODE_X];
	        yDist = NodeMatrix[n1 + NODE_Y] - NodeMatrix[n2 + NODE_Y];

	        if (adjustSizes === true) {
	          //-- Anticollision Linear Repulsion
	          distance =
	            Math.sqrt(xDist * xDist + yDist * yDist) -
	            NodeMatrix[n1 + NODE_SIZE] -
	            NodeMatrix[n2 + NODE_SIZE];

	          if (distance > 0) {
	            factor =
	              (coefficient *
	                NodeMatrix[n1 + NODE_MASS] *
	                NodeMatrix[n2 + NODE_MASS]) /
	              distance /
	              distance;

	            // Updating nodes' dx and dy
	            NodeMatrix[n1 + NODE_DX] += xDist * factor;
	            NodeMatrix[n1 + NODE_DY] += yDist * factor;

	            NodeMatrix[n2 + NODE_DX] -= xDist * factor;
	            NodeMatrix[n2 + NODE_DY] -= yDist * factor;
	          } else if (distance < 0) {
	            factor =
	              100 *
	              coefficient *
	              NodeMatrix[n1 + NODE_MASS] *
	              NodeMatrix[n2 + NODE_MASS];

	            // Updating nodes' dx and dy
	            NodeMatrix[n1 + NODE_DX] += xDist * factor;
	            NodeMatrix[n1 + NODE_DY] += yDist * factor;

	            NodeMatrix[n2 + NODE_DX] -= xDist * factor;
	            NodeMatrix[n2 + NODE_DY] -= yDist * factor;
	          }
	        } else {
	          //-- Linear Repulsion
	          distance = Math.sqrt(xDist * xDist + yDist * yDist);

	          if (distance > 0) {
	            factor =
	              (coefficient *
	                NodeMatrix[n1 + NODE_MASS] *
	                NodeMatrix[n2 + NODE_MASS]) /
	              distance /
	              distance;

	            // Updating nodes' dx and dy
	            NodeMatrix[n1 + NODE_DX] += xDist * factor;
	            NodeMatrix[n1 + NODE_DY] += yDist * factor;

	            NodeMatrix[n2 + NODE_DX] -= xDist * factor;
	            NodeMatrix[n2 + NODE_DY] -= yDist * factor;
	          }
	        }
	      }
	    }
	  }

	  // 3) Gravity
	  //------------
	  g = options.gravity / options.scalingRatio;
	  coefficient = options.scalingRatio;
	  for (n = 0; n < order; n += PPN) {
	    factor = 0;

	    // Common to both methods
	    xDist = NodeMatrix[n + NODE_X];
	    yDist = NodeMatrix[n + NODE_Y];
	    distance = Math.sqrt(Math.pow(xDist, 2) + Math.pow(yDist, 2));

	    if (options.strongGravityMode) {
	      //-- Strong gravity
	      if (distance > 0) factor = coefficient * NodeMatrix[n + NODE_MASS] * g;
	    } else {
	      //-- Linear Anti-collision Repulsion n
	      if (distance > 0)
	        factor = (coefficient * NodeMatrix[n + NODE_MASS] * g) / distance;
	    }

	    // Updating node's dx and dy
	    NodeMatrix[n + NODE_DX] -= xDist * factor;
	    NodeMatrix[n + NODE_DY] -= yDist * factor;
	  }

	  // 4) Attraction
	  //---------------
	  coefficient =
	    1 * (options.outboundAttractionDistribution ? outboundAttCompensation : 1);

	  // TODO: simplify distance
	  // TODO: coefficient is always used as -c --> optimize?
	  for (e = 0; e < size; e += PPE) {
	    n1 = EdgeMatrix[e + EDGE_SOURCE];
	    n2 = EdgeMatrix[e + EDGE_TARGET];
	    w = EdgeMatrix[e + EDGE_WEIGHT];

	    // Edge weight influence
	    ewc = Math.pow(w, options.edgeWeightInfluence);

	    // Common measures
	    xDist = NodeMatrix[n1 + NODE_X] - NodeMatrix[n2 + NODE_X];
	    yDist = NodeMatrix[n1 + NODE_Y] - NodeMatrix[n2 + NODE_Y];

	    // Applying attraction to nodes
	    if (adjustSizes === true) {
	      distance =
	        Math.sqrt(xDist * xDist + yDist * yDist) -
	        NodeMatrix[n1 + NODE_SIZE] -
	        NodeMatrix[n2 + NODE_SIZE];

	      if (options.linLogMode) {
	        if (options.outboundAttractionDistribution) {
	          //-- LinLog Degree Distributed Anti-collision Attraction
	          if (distance > 0) {
	            factor =
	              (-coefficient * ewc * Math.log(1 + distance)) /
	              distance /
	              NodeMatrix[n1 + NODE_MASS];
	          }
	        } else {
	          //-- LinLog Anti-collision Attraction
	          if (distance > 0) {
	            factor = (-coefficient * ewc * Math.log(1 + distance)) / distance;
	          }
	        }
	      } else {
	        if (options.outboundAttractionDistribution) {
	          //-- Linear Degree Distributed Anti-collision Attraction
	          if (distance > 0) {
	            factor = (-coefficient * ewc) / NodeMatrix[n1 + NODE_MASS];
	          }
	        } else {
	          //-- Linear Anti-collision Attraction
	          if (distance > 0) {
	            factor = -coefficient * ewc;
	          }
	        }
	      }
	    } else {
	      distance = Math.sqrt(Math.pow(xDist, 2) + Math.pow(yDist, 2));

	      if (options.linLogMode) {
	        if (options.outboundAttractionDistribution) {
	          //-- LinLog Degree Distributed Attraction
	          if (distance > 0) {
	            factor =
	              (-coefficient * ewc * Math.log(1 + distance)) /
	              distance /
	              NodeMatrix[n1 + NODE_MASS];
	          }
	        } else {
	          //-- LinLog Attraction
	          if (distance > 0)
	            factor = (-coefficient * ewc * Math.log(1 + distance)) / distance;
	        }
	      } else {
	        if (options.outboundAttractionDistribution) {
	          //-- Linear Attraction Mass Distributed
	          // NOTE: Distance is set to 1 to override next condition
	          distance = 1;
	          factor = (-coefficient * ewc) / NodeMatrix[n1 + NODE_MASS];
	        } else {
	          //-- Linear Attraction
	          // NOTE: Distance is set to 1 to override next condition
	          distance = 1;
	          factor = -coefficient * ewc;
	        }
	      }
	    }

	    // Updating nodes' dx and dy
	    // TODO: if condition or factor = 1?
	    if (distance > 0) {
	      // Updating nodes' dx and dy
	      NodeMatrix[n1 + NODE_DX] += xDist * factor;
	      NodeMatrix[n1 + NODE_DY] += yDist * factor;

	      NodeMatrix[n2 + NODE_DX] -= xDist * factor;
	      NodeMatrix[n2 + NODE_DY] -= yDist * factor;
	    }
	  }

	  // 5) Apply Forces
	  //-----------------
	  var force, swinging, traction, nodespeed, newX, newY;

	  // MATH: sqrt and square distances
	  if (adjustSizes === true) {
	    for (n = 0; n < order; n += PPN) {
	      if (NodeMatrix[n + NODE_FIXED] !== 1) {
	        force = Math.sqrt(
	          Math.pow(NodeMatrix[n + NODE_DX], 2) +
	            Math.pow(NodeMatrix[n + NODE_DY], 2)
	        );

	        if (force > MAX_FORCE) {
	          NodeMatrix[n + NODE_DX] =
	            (NodeMatrix[n + NODE_DX] * MAX_FORCE) / force;
	          NodeMatrix[n + NODE_DY] =
	            (NodeMatrix[n + NODE_DY] * MAX_FORCE) / force;
	        }

	        swinging =
	          NodeMatrix[n + NODE_MASS] *
	          Math.sqrt(
	            (NodeMatrix[n + NODE_OLD_DX] - NodeMatrix[n + NODE_DX]) *
	              (NodeMatrix[n + NODE_OLD_DX] - NodeMatrix[n + NODE_DX]) +
	              (NodeMatrix[n + NODE_OLD_DY] - NodeMatrix[n + NODE_DY]) *
	                (NodeMatrix[n + NODE_OLD_DY] - NodeMatrix[n + NODE_DY])
	          );

	        traction =
	          Math.sqrt(
	            (NodeMatrix[n + NODE_OLD_DX] + NodeMatrix[n + NODE_DX]) *
	              (NodeMatrix[n + NODE_OLD_DX] + NodeMatrix[n + NODE_DX]) +
	              (NodeMatrix[n + NODE_OLD_DY] + NodeMatrix[n + NODE_DY]) *
	                (NodeMatrix[n + NODE_OLD_DY] + NodeMatrix[n + NODE_DY])
	          ) / 2;

	        nodespeed = (0.1 * Math.log(1 + traction)) / (1 + Math.sqrt(swinging));

	        // Updating node's positon
	        newX =
	          NodeMatrix[n + NODE_X] +
	          NodeMatrix[n + NODE_DX] * (nodespeed / options.slowDown);
	        NodeMatrix[n + NODE_X] = newX;

	        newY =
	          NodeMatrix[n + NODE_Y] +
	          NodeMatrix[n + NODE_DY] * (nodespeed / options.slowDown);
	        NodeMatrix[n + NODE_Y] = newY;
	      }
	    }
	  } else {
	    for (n = 0; n < order; n += PPN) {
	      if (NodeMatrix[n + NODE_FIXED] !== 1) {
	        swinging =
	          NodeMatrix[n + NODE_MASS] *
	          Math.sqrt(
	            (NodeMatrix[n + NODE_OLD_DX] - NodeMatrix[n + NODE_DX]) *
	              (NodeMatrix[n + NODE_OLD_DX] - NodeMatrix[n + NODE_DX]) +
	              (NodeMatrix[n + NODE_OLD_DY] - NodeMatrix[n + NODE_DY]) *
	                (NodeMatrix[n + NODE_OLD_DY] - NodeMatrix[n + NODE_DY])
	          );

	        traction =
	          Math.sqrt(
	            (NodeMatrix[n + NODE_OLD_DX] + NodeMatrix[n + NODE_DX]) *
	              (NodeMatrix[n + NODE_OLD_DX] + NodeMatrix[n + NODE_DX]) +
	              (NodeMatrix[n + NODE_OLD_DY] + NodeMatrix[n + NODE_DY]) *
	                (NodeMatrix[n + NODE_OLD_DY] + NodeMatrix[n + NODE_DY])
	          ) / 2;

	        nodespeed =
	          (NodeMatrix[n + NODE_CONVERGENCE] * Math.log(1 + traction)) /
	          (1 + Math.sqrt(swinging));

	        // Updating node convergence
	        NodeMatrix[n + NODE_CONVERGENCE] = Math.min(
	          1,
	          Math.sqrt(
	            (nodespeed *
	              (Math.pow(NodeMatrix[n + NODE_DX], 2) +
	                Math.pow(NodeMatrix[n + NODE_DY], 2))) /
	              (1 + Math.sqrt(swinging))
	          )
	        );

	        // Updating node's positon
	        newX =
	          NodeMatrix[n + NODE_X] +
	          NodeMatrix[n + NODE_DX] * (nodespeed / options.slowDown);
	        NodeMatrix[n + NODE_X] = newX;

	        newY =
	          NodeMatrix[n + NODE_Y] +
	          NodeMatrix[n + NODE_DY] * (nodespeed / options.slowDown);
	        NodeMatrix[n + NODE_Y] = newY;
	      }
	    }
	  }

	  // We return the information about the layout (no need to return the matrices)
	  return {};
	};

	  })();

	  var iterate = moduleShim.exports;

	  self.addEventListener('message', function (event) {
	    var data = event.data;

	    NODES = new Float32Array(data.nodes);

	    if (data.edges) EDGES = new Float32Array(data.edges);

	    // Running the iteration
	    iterate(data.settings, NODES, EDGES);

	    // Sending result to supervisor
	    self.postMessage(
	      {
	        nodes: NODES.buffer
	      },
	      [NODES.buffer]
	    );
	  });
	};

	/**
	 * Graphology ForceAtlas2 Layout Supervisor
	 * =========================================
	 *
	 * Supervisor class able to spawn a web worker to run the FA2 layout in a
	 * separate thread not to block UI with heavy synchronous computations.
	 */

	var workerFunction$1 = webworker$1;
	var isGraph$2 = isGraph$N;
	var createEdgeWeightGetter =
	  getters$1.createEdgeWeightGetter;
	var helpers$3 = helpers$9;

	var DEFAULT_SETTINGS$1 = defaults$3;

	/**
	 * Class representing a FA2 layout run by a webworker.
	 *
	 * @constructor
	 * @param  {Graph}         graph        - Target graph.
	 * @param  {object|number} params       - Parameters:
	 * @param  {object}          [settings] - Settings.
	 */
	function FA2LayoutSupervisor(graph, params) {
	  params = params || {};

	  // Validation
	  if (!isGraph$2(graph))
	    throw new Error(
	      'graphology-layout-forceatlas2/worker: the given graph is not a valid graphology instance.'
	    );

	  var getEdgeWeight = createEdgeWeightGetter(
	    'getEdgeWeight' in params ? params.getEdgeWeight : 'weight'
	  ).fromEntry;

	  // Validating settings
	  var settings = helpers$3.assign({}, DEFAULT_SETTINGS$1, params.settings);
	  var validationError = helpers$3.validateSettings(settings);

	  if (validationError)
	    throw new Error(
	      'graphology-layout-forceatlas2/worker: ' + validationError.message
	    );

	  // Properties
	  this.worker = null;
	  this.graph = graph;
	  this.settings = settings;
	  this.getEdgeWeight = getEdgeWeight;
	  this.matrices = null;
	  this.running = false;
	  this.killed = false;
	  this.outputReducer =
	    typeof params.outputReducer === 'function' ? params.outputReducer : null;

	  // Binding listeners
	  this.handleMessage = this.handleMessage.bind(this);

	  var respawnFrame = undefined;
	  var self = this;

	  this.handleGraphUpdate = function () {
	    if (self.worker) self.worker.terminate();

	    if (respawnFrame) clearTimeout(respawnFrame);

	    respawnFrame = setTimeout(function () {
	      respawnFrame = undefined;
	      self.spawnWorker();
	    }, 0);
	  };

	  graph.on('nodeAdded', this.handleGraphUpdate);
	  graph.on('edgeAdded', this.handleGraphUpdate);
	  graph.on('nodeDropped', this.handleGraphUpdate);
	  graph.on('edgeDropped', this.handleGraphUpdate);

	  // Spawning worker
	  this.spawnWorker();
	}

	FA2LayoutSupervisor.prototype.isRunning = function () {
	  return this.running;
	};

	/**
	 * Internal method used to spawn the web worker.
	 */
	FA2LayoutSupervisor.prototype.spawnWorker = function () {
	  if (this.worker) this.worker.terminate();

	  this.worker = helpers$3.createWorker(workerFunction$1);
	  this.worker.addEventListener('message', this.handleMessage);

	  if (this.running) {
	    this.running = false;
	    this.start();
	  }
	};

	/**
	 * Internal method used to handle the worker's messages.
	 *
	 * @param {object} event - Event to handle.
	 */
	FA2LayoutSupervisor.prototype.handleMessage = function (event) {
	  if (!this.running) return;

	  var matrix = new Float32Array(event.data.nodes);

	  helpers$3.assignLayoutChanges(this.graph, matrix, this.outputReducer);
	  if (this.outputReducer) helpers$3.readGraphPositions(this.graph, matrix);
	  this.matrices.nodes = matrix;

	  // Looping
	  this.askForIterations();
	};

	/**
	 * Internal method used to ask for iterations from the worker.
	 *
	 * @param  {boolean} withEdges - Should we send edges along?
	 * @return {FA2LayoutSupervisor}
	 */
	FA2LayoutSupervisor.prototype.askForIterations = function (withEdges) {
	  var matrices = this.matrices;

	  var payload = {
	    settings: this.settings,
	    nodes: matrices.nodes.buffer
	  };

	  var buffers = [matrices.nodes.buffer];

	  if (withEdges) {
	    payload.edges = matrices.edges.buffer;
	    buffers.push(matrices.edges.buffer);
	  }

	  this.worker.postMessage(payload, buffers);

	  return this;
	};

	/**
	 * Method used to start the layout.
	 *
	 * @return {FA2LayoutSupervisor}
	 */
	FA2LayoutSupervisor.prototype.start = function () {
	  if (this.killed)
	    throw new Error(
	      'graphology-layout-forceatlas2/worker.start: layout was killed.'
	    );

	  if (this.running) return this;

	  // Building matrices
	  this.matrices = helpers$3.graphToByteArrays(this.graph, this.getEdgeWeight);

	  this.running = true;
	  this.askForIterations(true);

	  return this;
	};

	/**
	 * Method used to stop the layout.
	 *
	 * @return {FA2LayoutSupervisor}
	 */
	FA2LayoutSupervisor.prototype.stop = function () {
	  this.running = false;

	  return this;
	};

	/**
	 * Method used to kill the layout.
	 *
	 * @return {FA2LayoutSupervisor}
	 */
	FA2LayoutSupervisor.prototype.kill = function () {
	  if (this.killed) return this;

	  this.running = false;
	  this.killed = true;

	  // Clearing memory
	  this.matrices = null;

	  // Terminating worker
	  this.worker.terminate();

	  // Unbinding listeners
	  this.graph.removeListener('nodeAdded', this.handleGraphUpdate);
	  this.graph.removeListener('edgeAdded', this.handleGraphUpdate);
	  this.graph.removeListener('nodeDropped', this.handleGraphUpdate);
	  this.graph.removeListener('edgeDropped', this.handleGraphUpdate);
	};

	/**
	 * Exporting.
	 */
	var worker$1 = FA2LayoutSupervisor;

	/**
	 * Graphology Noverlap Layout Webworker
	 * =====================================
	 *
	 * Web worker able to run the layout in a separate thread.
	 */

	var webworker = function worker() {
	  var NODES;

	  var moduleShim = {};

	  (function () {
	    /**
	 * Graphology Noverlap Iteration
	 * ==============================
	 *
	 * Function used to perform a single iteration of the algorithm.
	 */

	/**
	 * Matrices properties accessors.
	 */
	var NODE_X = 0,
	  NODE_Y = 1,
	  NODE_SIZE = 2;

	/**
	 * Constants.
	 */
	var PPN = 3;

	/**
	 * Helpers.
	 */
	function hashPair(a, b) {
	  return a + '§' + b;
	}

	function jitter() {
	  return 0.01 * (0.5 - Math.random());
	}

	/**
	 * Function used to perform a single interation of the algorithm.
	 *
	 * @param  {object}       options    - Layout options.
	 * @param  {Float32Array} NodeMatrix - Node data.
	 * @return {object}                  - Some metadata.
	 */
	moduleShim.exports = function iterate(options, NodeMatrix) {
	  // Caching options
	  var margin = options.margin;
	  var ratio = options.ratio;
	  var expansion = options.expansion;
	  var gridSize = options.gridSize; // TODO: decrease grid size when few nodes?
	  var speed = options.speed;

	  // Generic iteration variables
	  var i, j, x, y, l, size;
	  var converged = true;

	  var length = NodeMatrix.length;
	  var order = (length / PPN) | 0;

	  var deltaX = new Float32Array(order);
	  var deltaY = new Float32Array(order);

	  // Finding the extents of our space
	  var xMin = Infinity;
	  var yMin = Infinity;
	  var xMax = -Infinity;
	  var yMax = -Infinity;

	  for (i = 0; i < length; i += PPN) {
	    x = NodeMatrix[i + NODE_X];
	    y = NodeMatrix[i + NODE_Y];
	    size = NodeMatrix[i + NODE_SIZE] * ratio + margin;

	    xMin = Math.min(xMin, x - size);
	    xMax = Math.max(xMax, x + size);
	    yMin = Math.min(yMin, y - size);
	    yMax = Math.max(yMax, y + size);
	  }

	  var width = xMax - xMin;
	  var height = yMax - yMin;
	  var xCenter = (xMin + xMax) / 2;
	  var yCenter = (yMin + yMax) / 2;

	  xMin = xCenter - (expansion * width) / 2;
	  xMax = xCenter + (expansion * width) / 2;
	  yMin = yCenter - (expansion * height) / 2;
	  yMax = yCenter + (expansion * height) / 2;

	  // Building grid
	  var grid = new Array(gridSize * gridSize),
	    gridLength = grid.length,
	    c;

	  for (c = 0; c < gridLength; c++) grid[c] = [];

	  var nxMin, nxMax, nyMin, nyMax;
	  var xMinBox, xMaxBox, yMinBox, yMaxBox;

	  var col, row;

	  for (i = 0; i < length; i += PPN) {
	    x = NodeMatrix[i + NODE_X];
	    y = NodeMatrix[i + NODE_Y];
	    size = NodeMatrix[i + NODE_SIZE] * ratio + margin;

	    nxMin = x - size;
	    nxMax = x + size;
	    nyMin = y - size;
	    nyMax = y + size;

	    xMinBox = Math.floor((gridSize * (nxMin - xMin)) / (xMax - xMin));
	    xMaxBox = Math.floor((gridSize * (nxMax - xMin)) / (xMax - xMin));
	    yMinBox = Math.floor((gridSize * (nyMin - yMin)) / (yMax - yMin));
	    yMaxBox = Math.floor((gridSize * (nyMax - yMin)) / (yMax - yMin));

	    for (col = xMinBox; col <= xMaxBox; col++) {
	      for (row = yMinBox; row <= yMaxBox; row++) {
	        grid[col * gridSize + row].push(i);
	      }
	    }
	  }

	  // Computing collisions
	  var cell;

	  var collisions = new Set();

	  var n1, n2, x1, x2, y1, y2, s1, s2, h;

	  var xDist, yDist, dist, collision;

	  for (c = 0; c < gridLength; c++) {
	    cell = grid[c];

	    for (i = 0, l = cell.length; i < l; i++) {
	      n1 = cell[i];

	      x1 = NodeMatrix[n1 + NODE_X];
	      y1 = NodeMatrix[n1 + NODE_Y];
	      s1 = NodeMatrix[n1 + NODE_SIZE];

	      for (j = i + 1; j < l; j++) {
	        n2 = cell[j];
	        h = hashPair(n1, n2);

	        if (gridLength > 1 && collisions.has(h)) continue;

	        if (gridLength > 1) collisions.add(h);

	        x2 = NodeMatrix[n2 + NODE_X];
	        y2 = NodeMatrix[n2 + NODE_Y];
	        s2 = NodeMatrix[n2 + NODE_SIZE];

	        xDist = x2 - x1;
	        yDist = y2 - y1;
	        dist = Math.sqrt(xDist * xDist + yDist * yDist);
	        collision = dist < s1 * ratio + margin + (s2 * ratio + margin);

	        if (collision) {
	          converged = false;

	          n2 = (n2 / PPN) | 0;

	          if (dist > 0) {
	            deltaX[n2] += (xDist / dist) * (1 + s1);
	            deltaY[n2] += (yDist / dist) * (1 + s1);
	          } else {
	            // Nodes are on the exact same spot, we need to jitter a bit
	            deltaX[n2] += width * jitter();
	            deltaY[n2] += height * jitter();
	          }
	        }
	      }
	    }
	  }

	  for (i = 0, j = 0; i < length; i += PPN, j++) {
	    NodeMatrix[i + NODE_X] += deltaX[j] * 0.1 * speed;
	    NodeMatrix[i + NODE_Y] += deltaY[j] * 0.1 * speed;
	  }

	  return {converged: converged};
	};

	  })();

	  var iterate = moduleShim.exports;

	  self.addEventListener('message', function (event) {
	    var data = event.data;

	    NODES = new Float32Array(data.nodes);

	    // Running the iteration
	    var result = iterate(data.settings, NODES);

	    // Sending result to supervisor
	    self.postMessage(
	      {
	        result: result,
	        nodes: NODES.buffer
	      },
	      [NODES.buffer]
	    );
	  });
	};

	/**
	 * Graphology Noverlap Layout Supervisor
	 * ======================================
	 *
	 * Supervisor class able to spawn a web worker to run the Noverlap layout in a
	 * separate thread not to block UI with heavy synchronous computations.
	 */

	var workerFunction = webworker,
	  isGraph$1 = isGraph$N,
	  helpers$2 = helpers$7;

	var DEFAULT_SETTINGS = defaults$2;

	/**
	 * Class representing a Noverlap layout run by a webworker.
	 *
	 * @constructor
	 * @param  {Graph}         graph        - Target graph.
	 * @param  {object|number} params       - Parameters:
	 * @param  {object}          [settings] - Settings.
	 */
	function NoverlapLayoutSupervisor(graph, params) {
	  params = params || {};

	  // Validation
	  if (!isGraph$1(graph))
	    throw new Error(
	      'graphology-layout-noverlap/worker: the given graph is not a valid graphology instance.'
	    );

	  // Validating settings
	  var settings = Object.assign({}, DEFAULT_SETTINGS, params.settings),
	    validationError = helpers$2.validateSettings(settings);

	  if (validationError)
	    throw new Error(
	      'graphology-layout-noverlap/worker: ' + validationError.message
	    );

	  // Properties
	  this.worker = null;
	  this.graph = graph;
	  this.settings = settings;
	  this.matrices = null;
	  this.running = false;
	  this.killed = false;

	  this.inputReducer = params.inputReducer;
	  this.outputReducer = params.outputReducer;

	  this.callbacks = {
	    onConverged:
	      typeof params.onConverged === 'function' ? params.onConverged : null
	  };

	  // Binding listeners
	  this.handleMessage = this.handleMessage.bind(this);

	  var alreadyRespawning = false;
	  var self = this;

	  this.handleAddition = function () {
	    if (alreadyRespawning) return;

	    alreadyRespawning = true;

	    self.spawnWorker();
	    setTimeout(function () {
	      alreadyRespawning = false;
	    }, 0);
	  };

	  graph.on('nodeAdded', this.handleAddition);
	  graph.on('edgeAdded', this.handleAddition);

	  // Spawning worker
	  this.spawnWorker();
	}

	NoverlapLayoutSupervisor.prototype.isRunning = function () {
	  return this.running;
	};

	/**
	 * Internal method used to spawn the web worker.
	 */
	NoverlapLayoutSupervisor.prototype.spawnWorker = function () {
	  if (this.worker) this.worker.terminate();

	  this.worker = helpers$2.createWorker(workerFunction);
	  this.worker.addEventListener('message', this.handleMessage);

	  if (this.running) {
	    this.running = false;
	    this.start();
	  }
	};

	/**
	 * Internal method used to handle the worker's messages.
	 *
	 * @param {object} event - Event to handle.
	 */
	NoverlapLayoutSupervisor.prototype.handleMessage = function (event) {
	  if (!this.running) return;

	  var matrix = new Float32Array(event.data.nodes);

	  helpers$2.assignLayoutChanges(this.graph, matrix, this.outputReducer);
	  this.matrices.nodes = matrix;

	  if (event.data.result.converged) {
	    if (this.callbacks.onConverged) this.callbacks.onConverged();

	    this.stop();
	    return;
	  }

	  // Looping
	  this.askForIterations();
	};

	/**
	 * Internal method used to ask for iterations from the worker.
	 *
	 * @return {NoverlapLayoutSupervisor}
	 */
	NoverlapLayoutSupervisor.prototype.askForIterations = function () {
	  var matrices = this.matrices;

	  var payload = {
	    settings: this.settings,
	    nodes: matrices.nodes.buffer
	  };

	  var buffers = [matrices.nodes.buffer];

	  this.worker.postMessage(payload, buffers);

	  return this;
	};

	/**
	 * Method used to start the layout.
	 *
	 * @return {NoverlapLayoutSupervisor}
	 */
	NoverlapLayoutSupervisor.prototype.start = function () {
	  if (this.killed)
	    throw new Error(
	      'graphology-layout-noverlap/worker.start: layout was killed.'
	    );

	  if (this.running) return this;

	  // Building matrices
	  this.matrices = {
	    nodes: helpers$2.graphToByteArray(this.graph, this.inputReducer)
	  };

	  this.running = true;
	  this.askForIterations();

	  return this;
	};

	/**
	 * Method used to stop the layout.
	 *
	 * @return {NoverlapLayoutSupervisor}
	 */
	NoverlapLayoutSupervisor.prototype.stop = function () {
	  this.running = false;

	  return this;
	};

	/**
	 * Method used to kill the layout.
	 *
	 * @return {NoverlapLayoutSupervisor}
	 */
	NoverlapLayoutSupervisor.prototype.kill = function () {
	  if (this.killed) return this;

	  this.running = false;
	  this.killed = true;

	  // Clearing memory
	  this.matrices = null;

	  // Terminating worker
	  this.worker.terminate();

	  // Unbinding listeners
	  this.graph.removeListener('nodeAdded', this.handleAddition);
	  this.graph.removeListener('edgeAdded', this.handleAddition);
	};

	/**
	 * Exporting.
	 */
	var worker = NoverlapLayoutSupervisor;

	var browser$1 = {};

	var helpers$1 = {};

	/**
	 * Graphology Common GEXF Helpers
	 * ===============================
	 *
	 * Miscellaneous helpers used by both instance of the code.
	 */

	/**
	 * Function used to cast a string value to the desired type.
	 *
	 * @param  {string} type - Value type.
	 * @param  {string} type - String value.
	 * @return {any}         - Parsed type.
	 */
	helpers$1.cast = function (type, value) {
	  switch (type) {
	    case 'boolean':
	      value = value === 'true';
	      break;

	    case 'integer':
	    case 'long':
	    case 'float':
	    case 'double':
	      value = +value;
	      break;

	    case 'liststring':
	      value = value ? value.split('|') : [];
	      break;
	  }

	  return value;
	};

	/**
	 * Function deleting illegal characters from a potential tag name to avoid
	 * generating invalid XML.
	 *
	 * @param  {string} type - Tag name.
	 * @return {string}
	 */
	var SANITIZE_PATTERN = /["'<>&\s]/g;

	helpers$1.sanitizeTagName = function sanitizeTagName(tagName) {
	  return tagName.replace(SANITIZE_PATTERN, '').trim();
	};

	/* eslint no-self-compare: 0 */

	/**
	 * Graphology Browser GEXF Parser
	 * ===============================
	 *
	 * Browser version of the graphology GEXF parser using DOMParser to function.
	 */
	var isGraphConstructor$1 = isGraphConstructor$e;
	var mergeEdge$1 = addEdge.mergeEdge;
	var toMixed$1 = toMixed$2;
	var toMulti$1 = toMulti$2;
	var helpers = helpers$1;

	var cast$1 = helpers.cast;

	/**
	 * Function checking whether the given value is a NaN.
	 *
	 * @param  {any} value - Value to test.
	 * @return {boolean}
	 */
	function isReallyNaN(value) {
	  return value !== value;
	}

	/**
	 * Function used to convert a viz:color attribute into a CSS rgba? string.
	 *
	 * @param  {Node}   element - DOM element.
	 * @return {string}
	 */
	function toRGBString(element) {
	  var a = element.getAttribute('a'),
	    r = element.getAttribute('r'),
	    g = element.getAttribute('g'),
	    b = element.getAttribute('b');

	  return a
	    ? 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')'
	    : 'rgb(' + r + ',' + g + ',' + b + ')';
	}

	/**
	 * Function returning the first matching tag of the `viz` namespace matching
	 * the desired tag name.
	 *
	 * @param  {Node}   element - Target DOM element.
	 * @param  {string} name    - Tag name.
	 * @return {Node}
	 */
	function getFirstMatchingVizTag(element, name) {
	  var vizElement = element.getElementsByTagName('viz:' + name)[0];

	  if (!vizElement) vizElement = element.getElementsByTagNameNS('viz', name)[0];

	  if (!vizElement) vizElement = element.getElementsByTagName(name)[0];

	  return vizElement;
	}

	/**
	 * Function used to collect meta information.
	 *
	 * @param  {Array<Node>} elements - Target DOM element.
	 * @return {object}
	 */
	function collectMeta(elements) {
	  var meta = {},
	    element,
	    value;

	  for (var i = 0, l = elements.length; i < l; i++) {
	    element = elements[i];

	    if (element.nodeName === '#text') continue;

	    value = element.textContent.trim();

	    if (value) meta[element.tagName.toLowerCase()] = element.textContent;
	  }

	  return meta;
	}

	/**
	 * Function used to extract the model from the right elements.
	 *
	 * @param  {Array<Node>} elements - Target DOM elements.
	 * @return {array}                - The model & default attributes.
	 */
	function extractModel(elements) {
	  var model = {},
	    defaults = {},
	    element,
	    defaultElement,
	    id;

	  for (var i = 0, l = elements.length; i < l; i++) {
	    element = elements[i];
	    id = element.getAttribute('id') || element.getAttribute('for');

	    model[id] = {
	      id: id,
	      type: element.getAttribute('type') || 'string',
	      title: !isReallyNaN(+id) ? element.getAttribute('title') || id : id
	    };

	    // Default?
	    defaultElement = element.getElementsByTagName('default')[0];

	    if (defaultElement)
	      defaults[model[id].title] = cast$1(
	        model[id].type,
	        defaultElement.textContent
	      );
	  }

	  return [model, defaults];
	}

	/**
	 * Function used to collect an element's attributes.
	 *
	 * @param  {object} model    - Data model to use.
	 * @param  {object} defaults - Default values.
	 * @param  {Node}   element  - Target DOM element.
	 * @return {object}          - The collected attributes.
	 */
	function collectAttributes$1(model, defaults, element) {
	  var data = {},
	    label = element.getAttribute('label'),
	    weight = element.getAttribute('weight');

	  if (label) data.label = label;

	  if (weight) data.weight = +weight;

	  var valueElements = element.getElementsByTagName('attvalue'),
	    valueElement,
	    id;

	  for (var i = 0, l = valueElements.length; i < l; i++) {
	    valueElement = valueElements[i];
	    id = valueElement.getAttribute('id') || valueElement.getAttribute('for');

	    data[model[id].title] = cast$1(
	      model[id].type,
	      valueElement.getAttribute('value')
	    );
	  }

	  // Applying default values
	  var k;

	  for (k in defaults) {
	    if (!(k in data)) data[k] = defaults[k];
	  }

	  // TODO: shortcut here to avoid viz when namespace is not set

	  // Attempting to find viz namespace tags

	  //-- 1) Color
	  var vizElement = getFirstMatchingVizTag(element, 'color');

	  if (vizElement) data.color = toRGBString(vizElement);

	  //-- 2) Size
	  vizElement = getFirstMatchingVizTag(element, 'size');

	  if (vizElement) data.size = +vizElement.getAttribute('value');

	  //-- 3) Position
	  var x, y, z;

	  vizElement = getFirstMatchingVizTag(element, 'position');

	  if (vizElement) {
	    x = vizElement.getAttribute('x');
	    y = vizElement.getAttribute('y');
	    z = vizElement.getAttribute('z');

	    if (x) data.x = +x;
	    if (y) data.y = +y;
	    if (z) data.z = +z;
	  }

	  //-- 4) Shape
	  vizElement = getFirstMatchingVizTag(element, 'shape');

	  if (vizElement) data.shape = vizElement.getAttribute('value');

	  //-- 5) Thickness
	  vizElement = getFirstMatchingVizTag(element, 'thickness');

	  if (vizElement) data.thickness = +vizElement.getAttribute('value');

	  return data;
	}

	/**
	 * Factory taking implementations of `DOMParser` & `Document` returning
	 * the parser function.
	 */
	var parser$3 = function createParserFunction(DOMParser, Document) {
	  /**
	   * Function taking either a string or a document and returning a
	   * graphology instance.
	   *
	   * @param {function}        Graph  - A graphology constructor.
	   * @param {string|Document} source - The source to parse.
	   * @param {object}          options - Parsing options.
	   */

	  // TODO: option to map the data to the attributes for customization, nodeModel, edgeModel, nodeReducer, edgeReducer
	  // TODO: option to disable the model mapping heuristic
	  return function parse(Graph, source, options) {
	    options = options || {};

	    var addMissingNodes = options.addMissingNodes === true;
	    var mergeResult;

	    var xmlDoc = source;

	    var element, result, type, attributes, id, s, t, i, l;

	    if (!isGraphConstructor$1(Graph))
	      throw new Error('graphology-gexf/parser: invalid Graph constructor.');

	    // If source is a string, we are going to parse it
	    if (typeof source === 'string')
	      xmlDoc = new DOMParser().parseFromString(source, 'application/xml');

	    if (!(xmlDoc instanceof Document))
	      throw new Error(
	        'graphology-gexf/parser: source should either be a XML document or a string.'
	      );

	    // Finding useful elements
	    var GRAPH_ELEMENT = xmlDoc.getElementsByTagName('graph')[0],
	      META_ELEMENT = xmlDoc.getElementsByTagName('meta')[0],
	      META_ELEMENTS = (META_ELEMENT && META_ELEMENT.childNodes) || [],
	      NODE_ELEMENTS = xmlDoc.getElementsByTagName('node'),
	      EDGE_ELEMENTS = xmlDoc.getElementsByTagName('edge'),
	      MODEL_ELEMENTS = xmlDoc.getElementsByTagName('attributes'),
	      NODE_MODEL_ELEMENTS = [],
	      EDGE_MODEL_ELEMENTS = [];

	    for (i = 0, l = MODEL_ELEMENTS.length; i < l; i++) {
	      element = MODEL_ELEMENTS[i];

	      if (element.getAttribute('class') === 'node')
	        NODE_MODEL_ELEMENTS = element.getElementsByTagName('attribute');
	      else if (element.getAttribute('class') === 'edge')
	        EDGE_MODEL_ELEMENTS = element.getElementsByTagName('attribute');
	    }

	    // Information
	    var DEFAULT_EDGE_TYPE =
	      GRAPH_ELEMENT.getAttribute('defaultedgetype') || 'undirected';

	    if (DEFAULT_EDGE_TYPE === 'mutual') DEFAULT_EDGE_TYPE = 'undirected';

	    // Computing models
	    result = extractModel(NODE_MODEL_ELEMENTS);

	    var NODE_MODEL = result[0],
	      NODE_DEFAULT_ATTRIBUTES = result[1];

	    result = extractModel(EDGE_MODEL_ELEMENTS);

	    var EDGE_MODEL = result[0],
	      EDGE_DEFAULT_ATTRIBUTES = result[1];

	    // Polling the first edge to guess the type of the edges
	    var graphType = EDGE_ELEMENTS[0]
	      ? EDGE_ELEMENTS[0].getAttribute('type') || DEFAULT_EDGE_TYPE
	      : 'mixed';

	    // Instantiating our graph
	    var graph = new Graph({
	      type: graphType
	    });

	    // Collecting meta
	    var meta = collectMeta(META_ELEMENTS),
	      lastModifiedDate =
	        META_ELEMENT && META_ELEMENT.getAttribute('lastmodifieddate');

	    graph.replaceAttributes(meta);

	    if (lastModifiedDate)
	      graph.setAttribute('lastModifiedDate', lastModifiedDate);

	    // Adding nodes
	    for (i = 0, l = NODE_ELEMENTS.length; i < l; i++) {
	      element = NODE_ELEMENTS[i];

	      graph.addNode(
	        element.getAttribute('id'),
	        collectAttributes$1(NODE_MODEL, NODE_DEFAULT_ATTRIBUTES, element)
	      );
	    }

	    // Adding edges
	    for (i = 0, l = EDGE_ELEMENTS.length; i < l; i++) {
	      element = EDGE_ELEMENTS[i];

	      id = element.getAttribute('id');
	      type = element.getAttribute('type') || DEFAULT_EDGE_TYPE;
	      s = element.getAttribute('source');
	      t = element.getAttribute('target');
	      attributes = collectAttributes$1(
	        EDGE_MODEL,
	        EDGE_DEFAULT_ATTRIBUTES,
	        element
	      );

	      // If we encountered an edge with a different type, we upgrade the graph
	      if (type !== graph.type && graph.type !== 'mixed') {
	        graph = toMixed$1(graph);
	      }

	      // If we encountered twice the same edge, we upgrade the graph
	      if (
	        !graph.multi &&
	        ((type === 'directed' && graph.hasDirectedEdge(s, t)) ||
	          graph.hasUndirectedEdge(s, t))
	      ) {
	        graph = toMulti$1(graph);
	      }

	      mergeResult = mergeEdge$1(
	        graph,
	        type !== 'directed',
	        id || null,
	        s,
	        t,
	        attributes
	      );

	      if (!addMissingNodes && (mergeResult[2] || mergeResult[3])) {
	        throw new Error(
	          'graphology-gexf/parser: one of your gexf file edges points to an inexisting node. Set the parser `addMissingNodes` option to `true` if you do not care.'
	        );
	      }
	    }

	    return graph;
	  };
	};

	/**
	 * Graphology Browser GEXF Parser
	 * ===============================
	 *
	 * Browser version of the graphology GEXF parser.
	 */

	var createParserFunction$1 = parser$3;

	var parser$2 = createParserFunction$1(DOMParser, Document);

	function isFalse(s) {
	  return typeof s !== 'number' && !s;
	}

	function strval(s) {
	  if (typeof s == 'string') {
	    return s;
	  }
	  else if (typeof s == 'number') {
	    return s+'';
	  }
	  else if (typeof s == 'function') {
	    return s();
	  }
	  else if (s instanceof XMLWriter$1) {
	    return s.toString();
	  }
	  else throw Error('Bad Parameter');
	}

	function XMLWriter$1(indent, callback) {

	    if (!(this instanceof XMLWriter$1)) {
	        return new XMLWriter$1();
	    }

	    this.name_regex = /[_:A-Za-z][-._:A-Za-z0-9]*/;
	    this.indent = indent ? true : false;
	    this.indentString = this.indent && typeof indent === 'string' ? indent : '    ';
	    this.output = '';
	    this.stack = [];
	    this.tags = 0;
	    this.attributes = 0;
	    this.attribute = 0;
	    this.texts = 0;
	    this.comment = 0;
	    this.dtd = 0;
	    this.root = '';
	    this.pi = 0;
	    this.cdata = 0;
	    this.started_write = false;
	    this.writer;
	    this.writer_encoding = 'UTF-8';

	    if (typeof callback == 'function') {
	        this.writer = callback;
	    } else {
	        this.writer = function (s, e) {
	            this.output += s;
	        };
	    }
	}

	XMLWriter$1.prototype = {
	    toString : function () {
	        this.flush();
	        return this.output;
	    },

	    indenter : function () {
	      if (this.indent) {
	        this.write('\n');
	        for (var i = 1; i < this.tags; i++) {
	          this.write(this.indentString);
	        }
	      }
	    },

	    write : function () {
	        for (var i = 0; i < arguments.length; i++) {
	            this.writer(arguments[i], this.writer_encoding);
	        }
	    },


	    flush : function () {
	        for (var i = this.tags; i > 0; i--) {
	            this.endElement();
	        }
	        this.tags = 0;
	    },

	    startDocument : function (version, encoding, standalone) {
	        if (this.tags || this.attributes) return this;

	        this.startPI('xml');
	        this.startAttribute('version');
	        this.text(typeof version == "string" ? version : "1.0");
	        this.endAttribute();
	        if (typeof encoding == "string") {
	            this.startAttribute('encoding');
	            this.text(encoding);
	            this.endAttribute();
	            this.writer_encoding = encoding;
	        }
	        if (standalone) {
	            this.startAttribute('standalone');
	            this.text("yes");
	            this.endAttribute();
	        }
	        this.endPI();
	        if (!this.indent) {
	          this.write('\n');
	        }
	        return this;
	    },

	    endDocument : function () {
	        if (this.attributes) this.endAttributes();
	        return this;
	    },

	    writeElement : function (name, content) {
	        return this.startElement(name).text(content).endElement();
	    },

	    writeElementNS : function (prefix, name, uri, content) {
	        if (!content) {
	            content = uri;
	        }
	        return this.startElementNS(prefix, name, uri).text(content).endElement();
	    },

	    startElement : function (name) {
	        name = strval(name);
	        if (!name.match(this.name_regex)) throw Error('Invalid Parameter');
	        if (this.tags === 0 && this.root && this.root !== name) throw Error('Invalid Parameter');
	        if (this.attributes) this.endAttributes();
	        ++this.tags;
	        this.texts = 0;
	        if (this.stack.length > 0)
	          this.stack[this.stack.length-1].containsTag = true;

	        this.stack.push({
	            name: name,
	            tags: this.tags
	        });
	        if (this.started_write) this.indenter();
	        this.write('<', name);
	        this.startAttributes();
	        this.started_write = true;
	        return this;
	    },
	    startElementNS : function (prefix, name, uri) {
	        prefix = strval(prefix);
	        name = strval(name);

	        if (!prefix.match(this.name_regex)) throw Error('Invalid Parameter');
	        if (!name.match(this.name_regex)) throw Error('Invalid Parameter');
	        if (this.attributes) this.endAttributes();
	        ++this.tags;
	        this.texts = 0;
	        if (this.stack.length > 0)
	          this.stack[this.stack.length-1].containsTag = true;

	        this.stack.push({
	            name: prefix + ':' + name,
	            tags: this.tags
	        });
	        if (this.started_write) this.indenter();
	        this.write('<', prefix + ':' + name);
	        this.startAttributes();
	        this.started_write = true;
	        return this;
	    },

	    endElement : function () {
	        if (!this.tags) return this;
	        var t = this.stack.pop();
	        if (this.attributes > 0) {
	            if (this.attribute) {
	                if (this.texts) this.endAttribute();
	                this.endAttribute();
	            }
	            this.write('/');
	            this.endAttributes();
	        } else {
	            if (t.containsTag) this.indenter();
	            this.write('</', t.name, '>');
	        }
	        --this.tags;
	        this.texts = 0;
	        return this;
	    },

	    writeAttribute : function (name, content) {
	        if (typeof content == 'function') {
	          content = content();
	        }
	        if (isFalse(content)) {
	           return this;
	        }
	        return this.startAttribute(name).text(content).endAttribute();
	    },
	    writeAttributeNS : function (prefix, name, uri, content) {
	        if (!content) {
	            content = uri;
	        }
	        if (typeof content == 'function') {
	          content = content();
	        }
	        if (isFalse(content)) {
	          return this;
	        }
	        return this.startAttributeNS(prefix, name, uri).text(content).endAttribute();
	    },

	    startAttributes : function () {
	        this.attributes = 1;
	        return this;
	    },

	    endAttributes : function () {
	        if (!this.attributes) return this;
	        if (this.attribute) this.endAttribute();
	        this.attributes = 0;
	        this.attribute = 0;
	        this.texts = 0;
	        this.write('>');
	        return this;
	    },

	    startAttribute : function (name) {
	        name = strval(name);
	        if (!name.match(this.name_regex)) throw Error('Invalid Parameter');
	        if (!this.attributes && !this.pi) return this;
	        if (this.attribute) return this;
	        this.attribute = 1;
	        this.write(' ', name, '="');
	        return this;
	    },
	    startAttributeNS : function (prefix, name, uri) {
	        prefix = strval(prefix);
	        name = strval(name);

	        if (!prefix.match(this.name_regex)) throw Error('Invalid Parameter');
	        if (!name.match(this.name_regex)) throw Error('Invalid Parameter');
	        if (!this.attributes && !this.pi) return this;
	        if (this.attribute) return this;
	        this.attribute = 1;
	        this.write(' ', prefix + ':' + name, '="');
	        return this;
	    },
	    endAttribute : function () {
	        if (!this.attribute) return this;
	        this.attribute = 0;
	        this.texts = 0;
	        this.write('"');
	        return this;
	    },

	    text : function (content) {
	        content = strval(content);
	        if (!this.tags && !this.comment && !this.pi && !this.cdata) return this;
	        if (this.attributes && this.attribute) {
	            ++this.texts;
	            this.write(content
	                       .replace(/&/g, '&amp;')
	                       .replace(/</g, '&lt;')
	                       .replace(/"/g, '&quot;')
	                       .replace(/\t/g, '&#x9;')
	                       .replace(/\n/g, '&#xA;')
	                       .replace(/\r/g, '&#xD;')
	                      );
	            return this;
	        } else if (this.attributes && !this.attribute) {
	            this.endAttributes();
	        }
	        if (this.comment || this.cdata) {
	            this.write(content);
	        }
	        else {
	          this.write(content.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'));
	        }
	        ++this.texts;
	        this.started_write = true;
	        return this;
	    },

	    writeComment : function (content) {
	        return this.startComment().text(content).endComment();
	    },

	    startComment : function () {
	        if (this.comment) return this;
	        if (this.attributes) this.endAttributes();
	        this.indenter();
	        this.write('<!--');
	        this.comment = 1;
	        this.started_write = true;
	        return this;
	    },

	    endComment : function () {
	        if (!this.comment) return this;
	        this.write('-->');
	        this.comment = 0;
	        return this;
	    },

	    writeDocType : function (name, pubid, sysid, subset) {
	        return this.startDocType(name, pubid, sysid, subset).endDocType()
	    },

	    startDocType : function (name, pubid, sysid, subset) {
	        if (this.dtd || this.tags) return this;

	        name = strval(name);
	        pubid = pubid ? strval(pubid) : pubid;
	        sysid = sysid ? strval(sysid) : sysid;
	        subset = subset ? strval(subset) : subset;

	        if (!name.match(this.name_regex)) throw Error('Invalid Parameter');
	        if (pubid && !pubid.match(/^[\w\-][\w\s\-\/\+\:\.]*/)) throw Error('Invalid Parameter');
	        if (sysid && !sysid.match(/^[\w\.][\w\-\/\\\:\.]*/)) throw Error('Invalid Parameter');
	        if (subset && !subset.match(/[\w\s\<\>\+\.\!\#\-\?\*\,\(\)\|]*/)) throw Error('Invalid Parameter');

	        pubid = pubid ? ' PUBLIC "' + pubid + '"' : (sysid) ? ' SYSTEM' : '';
	        sysid = sysid ? ' "' + sysid + '"' : '';
	        subset = subset ? ' [' + subset + ']': '';

	        if (this.started_write) this.indenter();
	        this.write('<!DOCTYPE ', name, pubid, sysid, subset);
	        this.root = name;
	        this.dtd = 1;
	        this.started_write = true;
	        return this;
	    },

	    endDocType : function () {
	        if (!this.dtd) return this;
	        this.write('>');
	        return this;
	    },

	    writePI : function (name, content) {
	        return this.startPI(name).text(content).endPI()
	    },

	    startPI : function (name) {
	        name = strval(name);
	        if (!name.match(this.name_regex)) throw Error('Invalid Parameter');
	        if (this.pi) return this;
	        if (this.attributes) this.endAttributes();
	        if (this.started_write) this.indenter();
	        this.write('<?', name);
	        this.pi = 1;
	        this.started_write = true;
	        return this;
	    },

	    endPI : function () {
	        if (!this.pi) return this;
	        this.write('?>');
	        this.pi = 0;
	        return this;
	    },

	    writeCData : function (content) {
	        return this.startCData().text(content).endCData();
	    },

	    startCData : function () {
	        if (this.cdata) return this;
	        if (this.attributes) this.endAttributes();
	        this.indenter();
	        this.write('<![CDATA[');
	        this.cdata = 1;
	        this.started_write = true;
	        return this;
	    },

	    endCData : function () {
	        if (!this.cdata) return this;
	        this.write(']]>');
	        this.cdata = 0;
	        return this;
	    },

	    writeRaw : function(content) {
	        content = strval(content);
	        if (!this.tags && !this.comment && !this.pi && !this.cdata) return this;
	        if (this.attributes && this.attribute) {
	            ++this.texts;
	            this.write(content.replace('&', '&amp;').replace('"', '&quot;'));
	            return this;
	        } else if (this.attributes && !this.attribute) {
	            this.endAttributes();
	        }
	        ++this.texts;
	        this.write(content);
	        this.started_write = true;
	        return this;
	    }

	};

	var xmlWriter$1 = XMLWriter$1;

	var xmlWriter = xmlWriter$1;

	/* eslint no-self-compare: 0 */

	/**
	 * Graphology Common GEXF Writer
	 * ==============================
	 *
	 * GEXF writer working for both node.js & the browser.
	 */
	var isGraph = isGraph$N,
	  inferType = inferType$4,
	  XMLWriter = xmlWriter,
	  sanitizeTagName = helpers$1.sanitizeTagName;

	// TODO: handle object in color, position with object for viz

	/**
	 * Constants.
	 */
	var GEXF_NAMESPACE = 'http://www.gexf.net/1.2draft',
	  GEXF_VIZ_NAMESPACE = 'http:///www.gexf.net/1.1draft/viz';

	var VIZ_RESERVED_NAMES = new Set([
	  'color',
	  'size',
	  'x',
	  'y',
	  'z',
	  'shape',
	  'thickness'
	]);

	var RGBA_TEST = /^\s*rgba?\s*\(/i,
	  RGBA_MATCH =
	    /^\s*rgba?\s*\(\s*([0-9]*)\s*,\s*([0-9]*)\s*,\s*([0-9]*)\s*(?:,\s*([.0-9]*))?\)\s*$/;

	/**
	 * Function used to transform a CSS color into a RGBA object.
	 *
	 * @param  {string} value - Target value.
	 * @return {object}
	 */
	function CSSColorToRGBA(value) {
	  if (!value || typeof value !== 'string') return {};

	  if (value[0] === '#') {
	    value = value.slice(1);

	    return value.length === 3
	      ? {
	          r: parseInt(value[0] + value[0], 16),
	          g: parseInt(value[1] + value[1], 16),
	          b: parseInt(value[2] + value[2], 16)
	        }
	      : {
	          r: parseInt(value[0] + value[1], 16),
	          g: parseInt(value[2] + value[3], 16),
	          b: parseInt(value[4] + value[5], 16)
	        };
	  } else if (RGBA_TEST.test(value)) {
	    var result = {};

	    value = value.match(RGBA_MATCH);
	    result.r = +value[1];
	    result.g = +value[2];
	    result.b = +value[3];

	    if (value[4]) result.a = +value[4];

	    return result;
	  }

	  return {};
	}

	/**
	 * Function used to map an element's attributes to a standardized map of
	 * GEXF expected properties (label, viz, attributes).
	 *
	 * @param  {string} type       - The element's type.
	 * @param  {string} key        - The element's key.
	 * @param  {object} attributes - The element's attributes.
	 * @return {object}
	 */
	function DEFAULT_ELEMENT_FORMATTER(type, key, attributes) {
	  var output = {},
	    name;

	  for (name in attributes) {
	    if (name === 'label') {
	      output.label = attributes.label;
	    } else if (type === 'edge' && name === 'weight') {
	      output.weight = attributes.weight;
	    } else if (VIZ_RESERVED_NAMES.has(name)) {
	      output.viz = output.viz || {};
	      output.viz[name] = attributes[name];
	    } else {
	      output.attributes = output.attributes || {};
	      output.attributes[name] = attributes[name];
	    }
	  }

	  return output;
	}

	var DEFAULT_NODE_FORMATTER = DEFAULT_ELEMENT_FORMATTER.bind(null, 'node'),
	  DEFAULT_EDGE_FORMATTER = DEFAULT_ELEMENT_FORMATTER.bind(null, 'edge');

	/**
	 * Function used to check whether the given integer is 32 bits or not.
	 *
	 * @param  {number} number - Target number.
	 * @return {boolean}
	 */
	function is32BitInteger(number) {
	  return number <= 0x7fffffff && number >= -0x7fffffff;
	}

	/**
	 * Function used to check whether the given value is "empty".
	 *
	 * @param  {any} value - Target value.
	 * @return {boolean}
	 */
	function isEmptyValue(value) {
	  return (
	    typeof value === 'undefined' ||
	    value === null ||
	    value === '' ||
	    value !== value
	  );
	}

	/**
	 * Function used to detect a JavaScript's value type in the GEXF model.
	 *
	 * @param  {any}    value - Target value.
	 * @return {string}
	 */
	function detectValueType(value) {
	  if (isEmptyValue(value)) return 'empty';

	  if (Array.isArray(value)) return 'liststring';

	  if (typeof value === 'boolean') return 'boolean';

	  if (typeof value === 'object') return 'string';

	  // Numbers
	  if (typeof value === 'number') {
	    // Integer
	    if (value === (value | 0)) {
	      // Long (JavaScript integer can go up to 53 bit)?
	      return is32BitInteger(value) ? 'integer' : 'long';
	    }

	    // JavaScript numbers are 64 bit float, hence the double
	    return 'double';
	  }

	  return 'string';
	}

	/**
	 * Function used to cast the given value into the given type.
	 *
	 * @param  {string} type  - Target type.
	 * @param  {any}    value - Value to cast.
	 * @return {string}
	 */
	function cast(type, value) {
	  if (type === 'liststring' && Array.isArray(value)) return value.join('|');
	  return '' + value;
	}

	/**
	 * Function used to collect data from a graph's nodes.
	 *
	 * @param  {Graph}    graph   - Target graph.
	 * @param  {function} format  - Function formatting the nodes attributes.
	 * @return {array}
	 */
	function collectNodeData(graph, format) {
	  var nodes = new Array(graph.order);
	  var i = 0;

	  graph.forEachNode(function (node, attr) {
	    var data = format(node, attr);
	    data.key = node;
	    nodes[i++] = data;
	  });

	  return nodes;
	}

	/**
	 * Function used to collect data from a graph's edges.
	 *
	 * @param  {Graph}    graph   - Target graph.
	 * @param  {function} reducer - Function reducing the edges attributes.
	 * @return {array}
	 */
	function collectEdgeData(graph, reducer) {
	  var edges = new Array(graph.size);
	  var i = 0;

	  graph.forEachEdge(function (
	    edge,
	    attr,
	    source,
	    target,
	    _sa,
	    _ta,
	    undirected
	  ) {
	    var data = reducer(edge, attr);
	    data.key = edge;
	    data.source = source;
	    data.target = target;
	    data.undirected = undirected;
	    edges[i++] = data;
	  });

	  return edges;
	}

	/**
	 * Function used to infer the model of the graph's nodes or edges.
	 *
	 * @param  {array} elements - The graph's relevant elements.
	 * @return {array}
	 */

	// TODO: on large graph, we could also sample or let the user indicate the types
	function inferModel(elements) {
	  var model = {},
	    attributes,
	    type,
	    k;

	  // Testing every attributes
	  for (var i = 0, l = elements.length; i < l; i++) {
	    attributes = elements[i].attributes;

	    if (!attributes) continue;

	    for (k in attributes) {
	      type = detectValueType(attributes[k]);

	      if (type === 'empty') continue;

	      if (!model[k]) model[k] = type;
	      else {
	        if (model[k] === 'integer' && type === 'long') model[k] = type;
	        else if (model[k] !== type) model[k] = 'string';
	      }
	    }
	  }

	  // TODO: check default values
	  return model;
	}

	/**
	 * Function used to write a model.
	 *
	 * @param {XMLWriter} writer     - The writer to use.
	 * @param {object}    model      - Model to write.
	 * @param {string}    modelClass - Class of the model.
	 */
	function writeModel(writer, model, modelClass) {
	  var name;

	  if (!Object.keys(model).length) return;

	  writer.startElement('attributes');
	  writer.writeAttribute('class', modelClass);

	  for (name in model) {
	    writer.startElement('attribute');
	    writer.writeAttribute('id', name);
	    writer.writeAttribute('title', name);
	    writer.writeAttribute('type', model[name]);
	    writer.endElement();
	  }

	  writer.endElement();
	}

	function writeElements(writer, type, model, elements) {
	  var emptyModel = !Object.keys(model).length,
	    element,
	    name,
	    color,
	    value,
	    edgeType,
	    attributes,
	    weight,
	    viz,
	    k,
	    i,
	    l;

	  writer.startElement(type + 's');

	  for (i = 0, l = elements.length; i < l; i++) {
	    element = elements[i];
	    attributes = element.attributes;
	    viz = element.viz;

	    writer.startElement(type);
	    writer.writeAttribute('id', element.key);

	    if (type === 'edge') {
	      edgeType = element.undirected ? 'undirected' : 'directed';

	      if (edgeType !== writer.defaultEdgeType)
	        writer.writeAttribute('type', edgeType);

	      writer.writeAttribute('source', element.source);
	      writer.writeAttribute('target', element.target);

	      weight = element.weight;

	      if (
	        (typeof weight === 'number' && !isNaN(weight)) ||
	        typeof weight === 'string'
	      )
	        writer.writeAttribute('weight', element.weight);
	    }

	    if (element.label) writer.writeAttribute('label', element.label);

	    if (!emptyModel && attributes) {
	      writer.startElement('attvalues');

	      for (name in model) {
	        if (name in attributes) {
	          value = attributes[name];

	          if (isEmptyValue(value)) continue;

	          writer.startElement('attvalue');
	          writer.writeAttribute('for', name);
	          writer.writeAttribute('value', cast(model[name], value));
	          writer.endElement();
	        }
	      }

	      writer.endElement();
	    }

	    if (viz) {
	      //-- 1) Color
	      if (viz.color) {
	        color = CSSColorToRGBA(viz.color);

	        writer.startElementNS('viz', 'color');

	        for (k in color) writer.writeAttribute(k, color[k]);

	        writer.endElement();
	      }

	      //-- 2) Size
	      if ('size' in viz) {
	        writer.startElementNS('viz', 'size');
	        writer.writeAttribute('value', viz.size);
	        writer.endElement();
	      }

	      //-- 3) Position
	      if ('x' in viz || 'y' in viz || 'z' in viz) {
	        writer.startElementNS('viz', 'position');

	        if ('x' in viz) writer.writeAttribute('x', viz.x);

	        if ('y' in viz) writer.writeAttribute('y', viz.y);

	        if ('z' in viz) writer.writeAttribute('z', viz.z);

	        writer.endElement();
	      }

	      //-- 4) Shape
	      if (viz.shape) {
	        writer.startElementNS('viz', 'shape');
	        writer.writeAttribute('value', viz.shape);
	        writer.endElement();
	      }

	      //-- 5) Thickness
	      if ('thickness' in viz) {
	        writer.startElementNS('viz', 'thickness');
	        writer.writeAttribute('value', viz.thickness);
	        writer.endElement();
	      }
	    }

	    writer.endElement();
	  }

	  writer.endElement();
	}

	/**
	 * Defaults.
	 */
	var DEFAULTS$1 = {
	  encoding: 'UTF-8',
	  pretty: true,
	  formatNode: DEFAULT_NODE_FORMATTER,
	  formatEdge: DEFAULT_EDGE_FORMATTER
	};

	/**
	 * Function taking a graphology instance & outputting a gexf string.
	 *
	 * @param  {Graph}  graph        - Target graphology instance.
	 * @param  {object} options      - Options:
	 * @param  {string}   [encoding]   - Character encoding.
	 * @param  {boolean}  [pretty]     - Whether to pretty print output.
	 * @param  {function} [formatNode] - Function formatting nodes' output.
	 * @param  {function} [formatEdge] - Function formatting edges' output.
	 * @return {string}              - GEXF string.
	 */
	var writer = function write(graph, options) {
	  if (!isGraph(graph))
	    throw new Error('graphology-gexf/writer: invalid graphology instance.');

	  options = options || {};

	  var indent = options.pretty === false ? false : '  ';

	  var formatNode = options.formatNode || DEFAULTS$1.formatNode,
	    formatEdge = options.formatEdge || DEFAULTS$1.formatEdge;

	  var writer = new XMLWriter(indent);

	  writer.startDocument('1.0', options.encoding || DEFAULTS$1.encoding);

	  // Starting gexf
	  writer.startElement('gexf');
	  writer.writeAttribute('version', '1.2');
	  writer.writeAttribute('xmlns', GEXF_NAMESPACE);
	  writer.writeAttribute('xmlns:viz', GEXF_VIZ_NAMESPACE);

	  // Processing meta
	  writer.startElement('meta');
	  var graphAttributes = graph.getAttributes();

	  if (graphAttributes.lastModifiedDate)
	    writer.writeAttribute('lastmodifieddate', graphAttributes.lastModifiedDate);

	  var metaTagName;
	  var graphAttribute;

	  for (var k in graphAttributes) {
	    if (k === 'lastModifiedDate') continue;

	    metaTagName = sanitizeTagName(k);

	    if (!metaTagName) continue;

	    graphAttribute = graphAttributes[k];

	    // NOTE: if the graph attribute is not a scalar, we do not bother writing
	    // it as metadata in the gexf output. This means the writer/parser is not
	    // idempotent, but we cannot do better because the gexf format does not
	    // allow it, since it was not meant to handle complex values as graph
	    // metadata anyway.
	    if (
	      typeof graphAttribute === 'string' ||
	      typeof graphAttribute === 'number' ||
	      typeof graphAttribute === 'boolean'
	    ) {
	      writer.writeElement(metaTagName, '' + graphAttribute);
	    }
	  }

	  writer.endElement();
	  writer.startElement('graph');

	  var type = inferType(graph);

	  writer.defaultEdgeType = type === 'mixed' ? 'directed' : type;

	  writer.writeAttribute('defaultedgetype', writer.defaultEdgeType);

	  // Processing model
	  var nodes = collectNodeData(graph, formatNode),
	    edges = collectEdgeData(graph, formatEdge);

	  var nodeModel = inferModel(nodes);

	  writeModel(writer, nodeModel, 'node');

	  var edgeModel = inferModel(edges);

	  writeModel(writer, edgeModel, 'edge');

	  // Processing nodes
	  writeElements(writer, 'node', nodeModel, nodes);

	  // Processing edges
	  writeElements(writer, 'edge', edgeModel, edges);

	  return writer.toString();
	};

	/**
	 * Graphology Browser GEXF Endpoint
	 * =================================
	 *
	 * Endpoint gathering both parser & writer for the browser.
	 */

	browser$1.parse = parser$2;
	browser$1.write = writer;

	var browser = {};

	var defaults = {};

	/**
	 * Graphology GRAPHML Defaults
	 * ============================
	 *
	 * Sane defaults for the library.
	 */

	function byteToHex(b) {
	  return ('0' + (b | 0).toString(16)).slice(-2);
	}

	function rgbToHex(r, g, b) {
	  return '#' + byteToHex(r) + byteToHex(g) + byteToHex(b);
	}

	function omitRgb(o) {
	  var t = {};

	  for (var k in o) {
	    if (k === 'r' || k === 'g' || k === 'b') continue;
	    t[k] = o[k];
	  }

	  return t;
	}

	function DEFAULT_FORMATTER$1(attr) {
	  var newAttr;

	  // Converting color
	  if (
	    typeof attr.r === 'number' &&
	    typeof attr.g === 'number' &&
	    typeof attr.b === 'number'
	  ) {
	    newAttr = omitRgb(attr);
	    newAttr.color = rgbToHex(attr.r, attr.g, attr.b);

	    return newAttr;
	  }

	  return attr;
	}

	defaults.DEFAULT_FORMATTER = DEFAULT_FORMATTER$1;

	/**
	 * Graphology GRAPHML Parser
	 * ==========================
	 *
	 * graphology GRAPHML parser using DOMParser to function.
	 */

	var isGraphConstructor = isGraphConstructor$e;
	var mergeEdge = addEdge.mergeEdge;
	var toMixed = toMixed$2;
	var toMulti = toMulti$2;

	var DEFAULTS = defaults;
	var DEFAULT_FORMATTER = DEFAULTS.DEFAULT_FORMATTER;

	function numericCaster(v) {
	  return +v;
	}

	function identity(v) {
	  return v;
	}

	var CASTERS = {
	  boolean: function (v) {
	    return v.toLowerCase() === 'true';
	  },
	  int: numericCaster,
	  long: numericCaster,
	  float: numericCaster,
	  double: numericCaster,
	  string: identity
	};

	function getGraphDataElements(graphElement) {
	  var children = graphElement.childNodes;
	  var dataElements = [];

	  var element;

	  for (var i = 0, l = children.length; i < l; i++) {
	    element = children[i];

	    if (element.nodeType !== 1) continue;

	    if (element.tagName.toLowerCase() !== 'data') break;

	    dataElements.push(element);
	  }

	  return dataElements;
	}

	function collectModel(modelElements) {
	  var i, l, m, id, name, type, element, defaultElement, defaultValue;

	  var models = {
	    graph: {},
	    node: {},
	    edge: {}
	  };

	  var defaults = {
	    graph: {},
	    node: {},
	    edge: {}
	  };

	  for (i = 0, l = modelElements.length; i < l; i++) {
	    element = modelElements[i];
	    m = element.getAttribute('for') || 'node';
	    id = element.getAttribute('id');
	    name = element.getAttribute('attr.name');
	    type = element.getAttribute('attr.type') || 'string';

	    defaultValue = undefined;
	    defaultElement = element.getElementsByTagName('default');

	    if (defaultElement.length !== 0)
	      defaultValue = defaultElement[0].textContent;

	    models[m][id] = {
	      name: name,
	      cast: CASTERS[type]
	    };

	    if (typeof defaultValue !== 'undefined') defaults[m][name] = defaultValue;
	  }

	  return {
	    models: models,
	    defaults: defaults
	  };
	}

	function collectAttributes(model, defaults, element) {
	  var dataElements = element.getElementsByTagName('data'),
	    dataElement;

	  var i, l, key, spec;

	  var attr = {};

	  for (i = 0, l = dataElements.length; i < l; i++) {
	    dataElement = dataElements[i];
	    key = dataElement.getAttribute('key');
	    spec = model[key];

	    if (typeof spec === 'undefined') attr[key] = dataElement.textContent;
	    else attr[spec.name] = spec.cast(dataElement.textContent);
	  }

	  for (key in defaults) {
	    if (!(key in attr)) attr[key] = defaults[key];
	  }

	  return attr;
	}

	/**
	 * Factory taking implementations of `DOMParser` & `Document` returning
	 * the parser function.
	 */
	var parser$1 = function createParserFunction(DOMParser, Document) {
	  /**
	   * Function taking either a string or a document and returning a
	   * graphology instance.
	   *
	   * @param {function}        Graph   - A graphology constructor.
	   * @param {string|Document} source  - The source to parse.
	   * @param {object}          options - Parsing options.
	   */
	  return function parse(Graph, source, options) {
	    options = options || {};

	    var addMissingNodes = options.addMissingNodes === true;
	    var mergeResult;

	    var xmlDoc = source;

	    if (!isGraphConstructor(Graph))
	      throw new Error('graphology-graphml/parser: invalid Graph constructor.');

	    // If source is a string, we are going to parse it
	    if (typeof source === 'string')
	      xmlDoc = new DOMParser().parseFromString(source, 'application/xml');

	    if (!(xmlDoc instanceof Document))
	      throw new Error(
	        'graphology-gexf/parser: source should either be a XML document or a string.'
	      );

	    var GRAPH_ELEMENT = xmlDoc.getElementsByTagName('graph')[0];
	    var GRAPH_DATA_ELEMENTS = getGraphDataElements(GRAPH_ELEMENT);
	    var MODEL_ELEMENTS = xmlDoc.getElementsByTagName('key');
	    var NODE_ELEMENTS = xmlDoc.getElementsByTagName('node');
	    var EDGE_ELEMENTS = xmlDoc.getElementsByTagName('edge');
	    var EDGE_DEFAULT_TYPE =
	      GRAPH_ELEMENT.getAttribute('edgedefault') || 'undirected';

	    var MODEL = collectModel(MODEL_ELEMENTS);

	    var graph = new Graph({type: EDGE_DEFAULT_TYPE});

	    // Graph-level attributes
	    var graphId = GRAPH_ELEMENT.getAttribute('id');

	    if (graphId) graph.setAttribute('id', graphId);

	    var dummyGraphElement = xmlDoc.createElement('graph');
	    GRAPH_DATA_ELEMENTS.forEach(function (el) {
	      dummyGraphElement.appendChild(el);
	    });
	    var graphAttributes = collectAttributes(
	      MODEL.models.graph,
	      MODEL.defaults.graph,
	      dummyGraphElement
	    );

	    graph.mergeAttributes(graphAttributes);

	    // Collecting nodes
	    var i, l, nodeElement, id, attr;

	    for (i = 0, l = NODE_ELEMENTS.length; i < l; i++) {
	      nodeElement = NODE_ELEMENTS[i];
	      id = nodeElement.getAttribute('id');

	      attr = collectAttributes(
	        MODEL.models.node,
	        MODEL.defaults.node,
	        nodeElement
	      );
	      attr = DEFAULT_FORMATTER(attr);

	      graph.addNode(id, attr);
	    }

	    // Collecting edges
	    var edgeElement, s, t, type;

	    for (i = 0, l = EDGE_ELEMENTS.length; i < l; i++) {
	      edgeElement = EDGE_ELEMENTS[i];
	      id = edgeElement.getAttribute('id');
	      s = edgeElement.getAttribute('source');
	      t = edgeElement.getAttribute('target');
	      type =
	        edgeElement.getAttribute('directed') === 'true'
	          ? 'directed'
	          : EDGE_DEFAULT_TYPE;

	      attr = collectAttributes(
	        MODEL.models.edge,
	        MODEL.defaults.edge,
	        edgeElement
	      );
	      attr = DEFAULT_FORMATTER(attr);

	      // Should we upgrade to a mixed graph?
	      if (!graph.type !== 'mixed' && type !== graph.type)
	        graph = toMixed(graph);

	      // Should we upgrade to a multi graph?
	      if (!graph.multi) {
	        if (type === 'undirected') {
	          if (graph.hasUndirectedEdge(s, t)) graph = toMulti(graph);
	        } else if (graph.hasDirectedEdge(s, t)) graph = toMulti(graph);
	      }

	      mergeResult = mergeEdge(
	        graph,
	        type === 'undirected',
	        id ? id : null,
	        s,
	        t,
	        attr
	      );

	      if (!addMissingNodes && (mergeResult[2] || mergeResult[3])) {
	        throw new Error(
	          'graphology-graphml/parser: one of your graphml file edges points to an inexisting node. Set the parser `addMissingNodes` option to `true` if you do not care.'
	        );
	      }
	    }

	    return graph;
	  };
	};

	/**
	 * Graphology Browser GRAPHML Parser
	 * ==================================
	 *
	 * Browser version of the graphology GRAPHML parser.
	 */

	var createParserFunction = parser$1;

	var parser = createParserFunction(DOMParser, Document);

	/**
	 * Graphology Browser GRAPHML Endpoint
	 * ====================================
	 *
	 * Endpoint gathering both parser & writer for the browser.
	 */

	browser.parse = parser;

	/**
	 * Graphology Standard Library
	 * ============================
	 *
	 * Library endpoint for the browser.
	 */

	var assertions = browser$2.assertions = assertions$1;
	var canvas = browser$2.canvas = canvas$1;
	var communitiesLouvain = browser$2.communitiesLouvain = communitiesLouvain$1;
	var components = browser$2.components = components$1;
	var generators = browser$2.generators = generators$1;
	var layout = browser$2.layout = layout$1;
	var layoutForce = browser$2.layoutForce = layoutForce$1;
	var layoutForceAtlas2 = browser$2.layoutForceAtlas2 = layoutForceatlas2;
	var layoutNoverlap = browser$2.layoutNoverlap = layoutNoverlap$1;
	var metrics = browser$2.metrics = metrics$1;
	var operators = browser$2.operators = operators$1;
	var shortestPath = browser$2.shortestPath = shortestPath$1;
	var simplePath = browser$2.simplePath = simplePath$1;
	var traversal = browser$2.traversal = traversal$1;
	var utils = browser$2.utils = utils$1;

	// Browser specific
	var ForceLayout = browser$2.ForceLayout = worker$2;
	var FA2Layout = browser$2.FA2Layout = worker$1;
	var NoverlapLayout = browser$2.NoverlapLayout = worker;
	var gexf = browser$2.gexf = browser$1;
	var graphml = browser$2.graphml = browser;

	exports.FA2Layout = FA2Layout;
	exports.ForceLayout = ForceLayout;
	exports.NoverlapLayout = NoverlapLayout;
	exports.assertions = assertions;
	exports.canvas = canvas;
	exports.communitiesLouvain = communitiesLouvain;
	exports.components = components;
	exports["default"] = browser$2;
	exports.generators = generators;
	exports.gexf = gexf;
	exports.graphml = graphml;
	exports.layout = layout;
	exports.layoutForce = layoutForce;
	exports.layoutForceAtlas2 = layoutForceAtlas2;
	exports.layoutNoverlap = layoutNoverlap;
	exports.metrics = metrics;
	exports.operators = operators;
	exports.shortestPath = shortestPath;
	exports.simplePath = simplePath;
	exports.traversal = traversal;
	exports.utils = utils;

	Object.defineProperty(exports, '__esModule', { value: true });

}));
//# sourceMappingURL=graphology-library.js.map
