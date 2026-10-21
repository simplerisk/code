import { g as _typeof, s as sdfCircle, j as layerFill, p as pathLine, k as pathLoop, l as layerPlain, f as _toConsumableArray } from './loop-e859c0ba.esm.js';

// =============================================================================
// GRAPHIC VARIABLES
// =============================================================================

// =============================================================================
// NODE SHAPES
// =============================================================================

// =============================================================================
// NODE LAYERS
// =============================================================================

// =============================================================================
// EDGE PATHS
// =============================================================================

// =============================================================================
// EDGE EXTREMITIES
// =============================================================================

// =============================================================================
// EDGE LAYERS
// =============================================================================

// =============================================================================
// TYPE GUARDS
// =============================================================================

function isCustomNodeLayer(spec) {
  return _typeof(spec) === "object" && "glsl" in spec && !("attributes" in spec);
}
function isCustomEdgeLayer(spec) {
  return _typeof(spec) === "object" && "glsl" in spec && !("attributes" in spec);
}

// =============================================================================
// PRIMITIVES DECLARATIONS
// =============================================================================

/**
 * Backdrop style configuration for hover effects.
 * Values can be constants (baked into shader) or attribute references (per-node storage).
 */

/**
 * A label attachment renderer receives context about the node and returns
 * a drawable image source (canvas, image, etc.) or null to skip.
 */

/**
 * The content returned by a label attachment renderer.
 * - "canvas": an already-rendered HTMLCanvasElement (dimensions from canvas.width/height)
 * - "svg": an SVG string or element (dimensions parsed from width/height attributes or viewBox)
 * - "html": an HTML string or element with explicit dimensions (rendered via SVG foreignObject)
 */

// =============================================================================
// VARIABLE EXTRACTION
// =============================================================================

// =============================================================================

// =============================================================================

var DEFAULT_NODE_PRIMITIVES = {
  shapes: [sdfCircle()],
  variables: {},
  layers: [layerFill()],
  label: {},
  backdrop: {},
  labelAttachments: {}
};
var DEFAULT_EDGE_PRIMITIVES = {
  paths: [pathLine(), pathLoop()],
  extremities: [],
  variables: {},
  layers: [layerPlain()],
  defaultHead: "none",
  defaultTail: "none",
  label: {}
};
var DEFAULT_NODE_DEPTH_LAYERS = ["nodes", "topNodes"];
var DEFAULT_EDGE_DEPTH_LAYERS = ["edges", "topEdges"];
var DEFAULT_DEPTH_LAYERS = [].concat(DEFAULT_EDGE_DEPTH_LAYERS, DEFAULT_NODE_DEPTH_LAYERS);

/**
 * Extracts the depth-layer union from a primitives declaration: the literal
 * `depthLayers` tuple when one is declared, otherwise the default layers. A
 * widened `string[]` (no literal info) also falls back to the defaults.
 */

var DEFAULT_PRIMITIVES = {
  nodes: DEFAULT_NODE_PRIMITIVES,
  edges: DEFAULT_EDGE_PRIMITIVES,
  depthLayers: _toConsumableArray(DEFAULT_DEPTH_LAYERS)
};

export { DEFAULT_DEPTH_LAYERS as D, DEFAULT_NODE_DEPTH_LAYERS as a, DEFAULT_EDGE_DEPTH_LAYERS as b, DEFAULT_PRIMITIVES as c, DEFAULT_NODE_PRIMITIVES as d, DEFAULT_EDGE_PRIMITIVES as e, isCustomEdgeLayer as f, isCustomNodeLayer as i };
