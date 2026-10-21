'use strict';

var colors = require('./colors-47ad171c.cjs.dev.js');

function _toPrimitive(t, r) {
  if ("object" != typeof t || !t) return t;
  var e = t[Symbol.toPrimitive];
  if (void 0 !== e) {
    var i = e.call(t, r || "default");
    if ("object" != typeof i) return i;
    throw new TypeError("@@toPrimitive must return a primitive value.");
  }
  return ("string" === r ? String : Number)(t);
}

function _toPropertyKey(t) {
  var i = _toPrimitive(t, "string");
  return "symbol" == typeof i ? i : i + "";
}

function _defineProperty(e, r, t) {
  return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, {
    value: t,
    enumerable: !0,
    configurable: !0,
    writable: !0
  }) : e[r] = t, e;
}

function ownKeys(e, r) {
  var t = Object.keys(e);
  if (Object.getOwnPropertySymbols) {
    var o = Object.getOwnPropertySymbols(e);
    r && (o = o.filter(function (r) {
      return Object.getOwnPropertyDescriptor(e, r).enumerable;
    })), t.push.apply(t, o);
  }
  return t;
}
function _objectSpread2(e) {
  for (var r = 1; r < arguments.length; r++) {
    var t = null != arguments[r] ? arguments[r] : {};
    r % 2 ? ownKeys(Object(t), !0).forEach(function (r) {
      _defineProperty(e, r, t[r]);
    }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) {
      Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r));
    });
  }
  return e;
}

function _classCallCheck(a, n) {
  if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function");
}

function _defineProperties(e, r) {
  for (var t = 0; t < r.length; t++) {
    var o = r[t];
    o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o);
  }
}
function _createClass(e, r, t) {
  return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", {
    writable: !1
  }), e;
}

function _getPrototypeOf(t) {
  return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) {
    return t.__proto__ || Object.getPrototypeOf(t);
  }, _getPrototypeOf(t);
}

function _isNativeReflectConstruct() {
  try {
    var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {}));
  } catch (t) {}
  return (_isNativeReflectConstruct = function () {
    return !!t;
  })();
}

function _assertThisInitialized(e) {
  if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
  return e;
}

function _possibleConstructorReturn(t, e) {
  if (e && ("object" == typeof e || "function" == typeof e)) return e;
  if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined");
  return _assertThisInitialized(t);
}

function _callSuper(t, o, e) {
  return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e));
}

function _setPrototypeOf(t, e) {
  return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) {
    return t.__proto__ = e, t;
  }, _setPrototypeOf(t, e);
}

function _inherits(t, e) {
  if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function");
  t.prototype = Object.create(e && e.prototype, {
    constructor: {
      value: t,
      writable: !0,
      configurable: !0
    }
  }), Object.defineProperty(t, "prototype", {
    writable: !1
  }), e && _setPrototypeOf(t, e);
}

function _arrayWithoutHoles(r) {
  if (Array.isArray(r)) return colors._arrayLikeToArray(r);
}

function _iterableToArray(r) {
  if ("undefined" != typeof Symbol && null != r[Symbol.iterator] || null != r["@@iterator"]) return Array.from(r);
}

function _nonIterableSpread() {
  throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.");
}

function _toConsumableArray(r) {
  return _arrayWithoutHoles(r) || _iterableToArray(r) || colors._unsupportedIterableToArray(r) || _nonIterableSpread();
}

function _typeof(o) {
  "@babel/helpers - typeof";

  return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) {
    return typeof o;
  } : function (o) {
    return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o;
  }, _typeof(o);
}

/**
 * Sigma.js Node Programs - Type Definitions
 * ==========================================
 *
 * Types and interfaces for the node program architecture.
 * This system separates shape definition (using Signed Distance Fields)
 * from fragment coloring layers, enabling single-pass rendering of
 * complex node appearances.
 *
 * @module
 */

/**
 * Specification for a shader uniform with type-safe value.
 */

/**
 * Specification for a vertex attribute.
 */

/**
 * Definition of a Signed Distance Field shape.
 * Shapes provide GLSL code that computes the signed distance from a point
 * to the shape's boundary (negative inside, 0 on boundary, positive outside).
 */

/**
 * Context provided to a fragment layer's GLSL code via the global `context` struct.
 * This struct is populated by the generator before calling any layer functions.
 *
 * GLSL definition:
 * ```glsl
 * struct LayerContext {
 *   float sdf;             // Signed distance from shape boundary
 *   vec2 uv;               // UV coordinates [-1, 1]
 *   float shapeSize;       // Effective shape radius in UV space
 *   float aaWidth;         // Anti-aliasing width
 *   float pixelSize;       // Node diameter in screen pixels
 *   float correctionRatio; // Scaling factor for consistent rendering
 *   float pixelToUV;       // Conversion: pixels * pixelToUV = UV units
 * };
 * LayerContext context;  // Global instance
 * ```
 */

/**
 * Specification for how to provide a value to a layer.
 * Can be either a constant value or read from a node attribute.
 */

/**
 * Type guard to check if a ValueSource is an attribute reference.
 */
function isAttributeSource(source) {
  return _typeof(source) === "object" && source !== null && "attribute" in source;
}

/**
 * Context provided to layer lifecycle hooks.
 * Gives access to WebGL, renderer, and program capabilities.
 */

/**
 * Lifecycle hooks for layers that need async resources.
 * Returned by the lifecycle factory function.
 */

/**
 * Definition of a fragment layer that can be composed with a shape.
 * Layers output a color that is blended with previous layers by the generator.
 */

/**
 * Font configuration for label rendering.
 */

/**
 * Options for configuring labels rendered by the LabelProgram.
 *
 * Per-item label position is set via the style system (`labelPosition` style),
 * not here — the primitives scope only configures program-wide defaults that
 * styles can override.
 */

/**
 * Options for creating a node program via createNodeProgram().
 *
 * Supports two modes:
 * - Single shape: Use `shape` for a program that renders one shape type
 * - Multi-shape: Use `shapes` for a program that can render different shapes per node
 *
 * When using multi-shape mode, each node can specify which shape to use via
 * the `shape` attribute in its display data (e.g., "circle", "square").
 */

/**
 * Sigma.js SDF Shape - Circle
 * ============================
 *
 * Signed Distance Field for a circle shape.
 *
 * @module
 */

/**
 * Creates a circle SDF shape.
 * A circle has no configurable options - it's always a perfect circle.
 *
 * @returns Circle SDF shape definition
 */
function sdfCircle() {
  // language=GLSL
  var glsl = /*glsl*/"\nfloat sdf_circle(vec2 uv, float size) {\n  return length(uv) - size;\n}\n";
  return {
    name: "circle",
    glsl: glsl,
    uniforms: []
  };
}

/**
 * Sigma.js Fragment Layer - Fill
 * ===============================
 *
 * Simple color fill layer using the node's color attribute.
 *
 * @module
 */
/**
 * Creates a fill layer that fills the shape with a color.
 * This is typically the base layer for most node programs.
 *
 * @param options - Optional configuration
 * @returns Fill layer definition
 */
function layerFill(options) {
  var _options$color;
  var _WebGL2RenderingConte = WebGL2RenderingContext,
    UNSIGNED_BYTE = _WebGL2RenderingConte.UNSIGNED_BYTE;

  // Default to reading from "color" attribute
  var colorSource = (_options$color = options === null || options === void 0 ? void 0 : options.color) !== null && _options$color !== void 0 ? _options$color : {
    attribute: "color"
  };

  // If a fixed value is provided, no attributes needed
  if (!isAttributeSource(colorSource)) {
    // At this point, colorSource is a string (fixed color value)
    var colorString = colorSource;
    // language=GLSL
    var _glsl = "\nvec4 layer_fill() {\n  return ".concat(colors.colorToGLSLString(colorString), ";\n}\n");
    return {
      name: "fill",
      uniforms: [],
      attributes: [],
      glsl: _glsl
    };
  }

  // Use attribute-based color
  var source = colorSource.attribute;

  // language=GLSL
  var glsl = "\nvec4 layer_fill(vec4 v_fillColor) {\n  return v_fillColor;\n}\n";
  return {
    name: "fill",
    uniforms: [],
    attributes: [{
      name: "fillColor",
      size: 4,
      type: UNSIGNED_BYTE,
      normalized: true,
      source: source
    }],
    glsl: glsl
  };
}

/**
 * A color source for edge layer options:
 * - string: constant CSS color
 * - { attribute, default? }: per-edge color read from edge display data
 *   (style-bound variables included)
 * - { node: "source" | "target" }: the source or target node color, read live
 *   from the node data texture (stays in sync with node styles)
 */

/**
 * An EdgeColorValue resolved to GLSL, plus the requirements that come with it.
 */

/**
 * Resolves an EdgeColorValue to a fragment-shader expression.
 *
 * @param value - The color value to resolve
 * @param attributeName - Base name used when value is attribute-based
 *   (e.g. "dashColor" -> attribute a_dashColor, varying v_dashColor)
 */
function resolveEdgeColorValue(value, attributeName) {
  if (typeof value === "string") {
    var _colorToVec4$map = colors.colorToVec4(value).map(function (n) {
        return n.toFixed(6);
      }),
      _colorToVec4$map2 = colors._slicedToArray(_colorToVec4$map, 4),
      r = _colorToVec4$map2[0],
      g = _colorToVec4$map2[1],
      b = _colorToVec4$map2[2],
      a = _colorToVec4$map2[3];
    return {
      glsl: "vec4(".concat(r, ", ").concat(g, ", ").concat(b, ", ").concat(a, ")"),
      attributes: [],
      needsNodeColors: false
    };
  }
  if ("node" in value && value.node) {
    return {
      glsl: value.node === "source" ? "v_sourceColor" : "v_targetColor",
      attributes: [],
      needsNodeColors: true
    };
  }
  var attr = value;
  return {
    glsl: "v_".concat(attributeName),
    attributes: [{
      name: "a_".concat(attributeName),
      size: 4,
      type: WebGL2RenderingContext.UNSIGNED_BYTE,
      normalized: true,
      source: attr.attribute,
      defaultValue: attr["default"]
    }],
    needsNodeColors: false
  };
}

/**
 * Sigma.js Edge Layer - Plain
 * ===========================
 *
 * Solid color layer for edges.
 *
 * @module
 */
/**
 * Creates a plain solid color layer.
 *
 * @param options - Optional configuration
 * @returns EdgeLayer definition for solid color
 */
function layerPlain(options) {
  var color = options === null || options === void 0 ? void 0 : options.color;

  // Default: the edge's own color (edge opacity already folded in)
  if (color === undefined) {
    // language=GLSL
    var _glsl = /*glsl*/"\n// Plain solid color layer\nvec4 layer_plain(EdgeContext ctx) {\n  return v_color;\n}\n";
    return {
      name: "plain",
      glsl: _glsl,
      uniforms: [],
      attributes: []
    };
  }
  var resolved = resolveEdgeColorValue(color, "plainColor");

  // language=GLSL
  var glsl = /*glsl*/"\n// Plain solid color layer\nvec4 layer_plain(EdgeContext ctx) {\n  return ".concat(resolved.glsl, ";\n}\n");
  return {
    name: "plain",
    glsl: glsl,
    uniforms: [],
    attributes: resolved.attributes,
    needsNodeColors: resolved.needsNodeColors
  };
}

/**
 * Sigma.js Edge Path - Line
 * =========================
 *
 * Straight line path for edges.
 * This is the simplest path type, rendered as a quad.
 *
 * @module
 */

/**
 * Creates a straight line edge path.
 *
 * Line edges are the most efficient, rendered as a single quad (6 vertices).
 * All path functions have closed-form solutions.
 *
 * @returns EdgePath definition for straight lines
 */
function pathLine() {
  // language=GLSL
  var glsl = /*glsl*/"\n// Position at parameter t \u2208 [0, 1]\nvec2 path_straight_position(float t, vec2 source, vec2 target) {\n  return mix(source, target, t);\n}\n\n// Total length of the path (analytical - more efficient than sampling)\nfloat path_straight_length(vec2 source, vec2 target) {\n  return length(target - source);\n}\n";
  return {
    name: "straight",
    segments: 1,
    // Simple quad
    minBodyLengthRatio: 0,
    // No minimum for straight edges
    linearParameterization: true,
    // t maps directly to arc distance
    glsl: glsl,
    uniforms: [],
    attributes: []
  };
}

/**
 * Sigma.js Edge Path - Loop (Self-Loop)
 * ======================================
 *
 * Teardrop-shaped loop path for self-referencing edges, built from a cubic
 * Bézier curve. The edge exits the node at one angle and re-enters at a
 * nearby angle, forming a smooth rounded loop in between.
 *
 * @module
 */

/**
 * Options for loop path creation.
 */

/**
 * Creates a loop edge path for self-referencing edges.
 *
 * The shape is a cubic Bézier that exits the node at `loopAngle - spread/2`
 * and re-enters at `loopAngle + spread/2`, with both tangents orthogonal to
 * the node surface. The bulge extends outward by `loopRadius` node-radii.
 *
 * Per-edge attributes:
 * - `loopRadius`: how far the loop extends (multiplier of node visual radius, default 4)
 * - `loopAngle`: direction of the loop (radians, 0 = right, default π/4)
 * - `loopSpread`: angular separation between exit and entry (radians, default ~80°)
 * - `loopFixedOrientation`: when 1, compensates for camera rotation
 *
 * @param options - Path configuration
 * @returns EdgePath definition for loop edges
 */
function pathLoop(options) {
  var _ref = options !== null && options !== void 0 ? options : {},
    _ref$segments = _ref.segments,
    segments = _ref$segments === void 0 ? 32 : _ref$segments;

  // language=GLSL
  var glsl = /*glsl*/"\nconst float LOOP_PI = 3.141592653589793;\n\nfloat loopWorldRadius() {\n  float nodeWorldRadius = v_sourceNodeSize * u_correctionRatio / u_sizeRatio;\n  return max(v_loopRadius * nodeWorldRadius, 0.001);\n}\n\n// Cubic B\xE9zier with P0 = P3 = source.\n// Control points extend outward orthogonal to the node surface at exit/entry angles.\nvec2 path_loop_position(float t, vec2 source, vec2 target) {\n  float R = loopWorldRadius();\n  float angle = v_loopAngle + (v_loopFixedOrientation > 0.5 ? u_cameraAngle : 0.0);\n  float halfSpread = v_loopSpread * 0.5;\n\n  float exitAngle = angle - halfSpread;\n  float entryAngle = angle + halfSpread;\n\n  // Control point distance: at t=0.5 the B\xE9zier reaches 0.75 * cpDist * cos(halfSpread)\n  // from source. Solve for cpDist so the loop tip reaches exactly R.\n  float cpDist = R / (0.75 * cos(halfSpread));\n\n  vec2 cp1 = source + cpDist * vec2(cos(exitAngle), sin(exitAngle));\n  vec2 cp2 = source + cpDist * vec2(cos(entryAngle), sin(entryAngle));\n\n  float u = 1.0 - t;\n  vec2 d1 = cp1 - source;\n  vec2 d2 = cp2 - source;\n  return source + 3.0 * t * u * (u * d1 + t * d2);\n}\n\n// Approximate arc length via chord sampling\nfloat path_loop_length(vec2 source, vec2 target) {\n  float len = 0.0;\n  vec2 prev = path_loop_position(0.0, source, target);\n  const int STEPS = 16;\n  for (int i = 1; i <= STEPS; i++) {\n    float t = float(i) / float(STEPS);\n    vec2 cur = path_loop_position(t, source, target);\n    len += length(cur - prev);\n    prev = cur;\n  }\n  return len;\n}\n";
  return {
    name: "loop",
    segments: segments,
    glsl: glsl,
    needsNodeSize: true,
    uniforms: [],
    attributes: [{
      name: "loopRadius",
      size: 1,
      type: WebGL2RenderingContext.FLOAT
    }, {
      name: "loopAngle",
      size: 1,
      type: WebGL2RenderingContext.FLOAT
    }, {
      name: "loopSpread",
      size: 1,
      type: WebGL2RenderingContext.FLOAT
    }, {
      name: "loopFixedOrientation",
      size: 1,
      type: WebGL2RenderingContext.FLOAT
    }],
    variables: {
      loopRadius: {
        type: "number",
        "default": 4
      },
      loopAngle: {
        type: "number",
        "default": Math.PI / 4
      },
      loopSpread: {
        type: "number",
        "default": 80 * Math.PI / 180
      },
      loopFixedOrientation: {
        type: "number",
        "default": 0
      }
    },
    // Stack parallel self-loops concentrically: each gets a larger radius
    spread: {
      variable: "loopRadius",
      compute: function compute(index) {
        return index + 4;
      }
    }
  };
}

exports._callSuper = _callSuper;
exports._classCallCheck = _classCallCheck;
exports._createClass = _createClass;
exports._defineProperty = _defineProperty;
exports._getPrototypeOf = _getPrototypeOf;
exports._inherits = _inherits;
exports._objectSpread2 = _objectSpread2;
exports._toConsumableArray = _toConsumableArray;
exports._typeof = _typeof;
exports.isAttributeSource = isAttributeSource;
exports.layerFill = layerFill;
exports.layerPlain = layerPlain;
exports.pathLine = pathLine;
exports.pathLoop = pathLoop;
exports.resolveEdgeColorValue = resolveEdgeColorValue;
exports.sdfCircle = sdfCircle;
