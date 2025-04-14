'use strict';

Object.defineProperty(exports, '__esModule', { value: true });

var index = require('../../dist/index-88310d0d.cjs.dev.js');
var inherits = require('../../dist/inherits-04acba6b.cjs.dev.js');
var colors = require('../../dist/colors-fe6de9d2.cjs.dev.js');

// language=GLSL
var SHADER_SOURCE$6 = /*glsl*/"\nprecision mediump float;\n\nvarying vec4 v_color;\nvarying float v_border;\n\nconst float radius = 0.5;\nconst vec4 transparent = vec4(0.0, 0.0, 0.0, 0.0);\n\nvoid main(void) {\n  vec2 m = gl_PointCoord - vec2(0.5, 0.5);\n  float dist = radius - length(m);\n\n  // No antialiasing for picking mode:\n  #ifdef PICKING_MODE\n  if (dist > v_border)\n    gl_FragColor = v_color;\n  else\n    gl_FragColor = transparent;\n\n  #else\n  float t = 0.0;\n  if (dist > v_border)\n    t = 1.0;\n  else if (dist > 0.0)\n    t = dist / v_border;\n\n  gl_FragColor = mix(transparent, v_color, t);\n  #endif\n}\n";
var FRAGMENT_SHADER_SOURCE$2 = SHADER_SOURCE$6;

// language=GLSL
var SHADER_SOURCE$5 = /*glsl*/"\nattribute vec4 a_id;\nattribute vec4 a_color;\nattribute vec2 a_position;\nattribute float a_size;\n\nuniform float u_sizeRatio;\nuniform float u_pixelRatio;\nuniform mat3 u_matrix;\n\nvarying vec4 v_color;\nvarying float v_border;\n\nconst float bias = 255.0 / 254.0;\n\nvoid main() {\n  gl_Position = vec4(\n    (u_matrix * vec3(a_position, 1)).xy,\n    0,\n    1\n  );\n\n  // Multiply the point size twice:\n  //  - x SCALING_RATIO to correct the canvas scaling\n  //  - x 2 to correct the formulae\n  gl_PointSize = a_size / u_sizeRatio * u_pixelRatio * 2.0;\n\n  v_border = (0.5 / a_size) * u_sizeRatio;\n\n  #ifdef PICKING_MODE\n  // For picking mode, we use the ID as the color:\n  v_color = a_id;\n  #else\n  // For normal mode, we use the color:\n  v_color = a_color;\n  #endif\n\n  v_color.a *= bias;\n}\n";
var VERTEX_SHADER_SOURCE$3 = SHADER_SOURCE$5;

var _WebGLRenderingContex$3 = WebGLRenderingContext,
  UNSIGNED_BYTE$3 = _WebGLRenderingContex$3.UNSIGNED_BYTE,
  FLOAT$3 = _WebGLRenderingContex$3.FLOAT;
var UNIFORMS$3 = ["u_sizeRatio", "u_pixelRatio", "u_matrix"];
var NodePointProgram = /*#__PURE__*/function (_NodeProgram) {
  function NodePointProgram() {
    inherits._classCallCheck(this, NodePointProgram);
    return inherits._callSuper(this, NodePointProgram, arguments);
  }
  inherits._inherits(NodePointProgram, _NodeProgram);
  return inherits._createClass(NodePointProgram, [{
    key: "getDefinition",
    value: function getDefinition() {
      return {
        VERTICES: 1,
        VERTEX_SHADER_SOURCE: VERTEX_SHADER_SOURCE$3,
        FRAGMENT_SHADER_SOURCE: FRAGMENT_SHADER_SOURCE$2,
        METHOD: WebGLRenderingContext.POINTS,
        UNIFORMS: UNIFORMS$3,
        ATTRIBUTES: [{
          name: "a_position",
          size: 2,
          type: FLOAT$3
        }, {
          name: "a_size",
          size: 1,
          type: FLOAT$3
        }, {
          name: "a_color",
          size: 4,
          type: UNSIGNED_BYTE$3,
          normalized: true
        }, {
          name: "a_id",
          size: 4,
          type: UNSIGNED_BYTE$3,
          normalized: true
        }]
      };
    }
  }, {
    key: "processVisibleItem",
    value: function processVisibleItem(nodeIndex, startIndex, data) {
      var array = this.array;
      array[startIndex++] = data.x;
      array[startIndex++] = data.y;
      array[startIndex++] = data.size;
      array[startIndex++] = colors.floatColor(data.color);
      array[startIndex++] = nodeIndex;
    }
  }, {
    key: "setUniforms",
    value: function setUniforms(_ref, _ref2) {
      var sizeRatio = _ref.sizeRatio,
        pixelRatio = _ref.pixelRatio,
        matrix = _ref.matrix;
      var gl = _ref2.gl,
        uniformLocations = _ref2.uniformLocations;
      var u_sizeRatio = uniformLocations.u_sizeRatio,
        u_pixelRatio = uniformLocations.u_pixelRatio,
        u_matrix = uniformLocations.u_matrix;
      gl.uniform1f(u_pixelRatio, pixelRatio);
      gl.uniform1f(u_sizeRatio, sizeRatio);
      gl.uniformMatrix3fv(u_matrix, false, matrix);
    }
  }]);
}(index.NodeProgram);

// language=GLSL
var SHADER_SOURCE$4 = /*glsl*/"\nattribute vec4 a_id;\nattribute vec4 a_color;\nattribute vec2 a_normal;\nattribute float a_normalCoef;\nattribute vec2 a_positionStart;\nattribute vec2 a_positionEnd;\nattribute float a_positionCoef;\nattribute float a_sourceRadius;\nattribute float a_targetRadius;\nattribute float a_sourceRadiusCoef;\nattribute float a_targetRadiusCoef;\n\nuniform mat3 u_matrix;\nuniform float u_zoomRatio;\nuniform float u_sizeRatio;\nuniform float u_pixelRatio;\nuniform float u_correctionRatio;\nuniform float u_minEdgeThickness;\nuniform float u_lengthToThicknessRatio;\nuniform float u_feather;\n\nvarying vec4 v_color;\nvarying vec2 v_normal;\nvarying float v_thickness;\nvarying float v_feather;\n\nconst float bias = 255.0 / 254.0;\n\nvoid main() {\n  float minThickness = u_minEdgeThickness;\n\n  vec2 normal = a_normal * a_normalCoef;\n  vec2 position = a_positionStart * (1.0 - a_positionCoef) + a_positionEnd * a_positionCoef;\n\n  float normalLength = length(normal);\n  vec2 unitNormal = normal / normalLength;\n\n  // These first computations are taken from edge.vert.glsl. Please read it to\n  // get better comments on what's happening:\n  float pixelsThickness = max(normalLength, minThickness * u_sizeRatio);\n  float webGLThickness = pixelsThickness * u_correctionRatio / u_sizeRatio;\n\n  // Here, we move the point to leave space for the arrow heads:\n  // Source arrow head\n  float sourceRadius = a_sourceRadius * a_sourceRadiusCoef;\n  float sourceDirection = sign(sourceRadius);\n  float webGLSourceRadius = sourceDirection * sourceRadius * 2.0 * u_correctionRatio / u_sizeRatio;\n  float webGLSourceArrowHeadLength = webGLThickness * u_lengthToThicknessRatio * 2.0;\n  vec2 sourceCompensationVector =\n    vec2(-sourceDirection * unitNormal.y, sourceDirection * unitNormal.x)\n    * (webGLSourceRadius + webGLSourceArrowHeadLength);\n    \n  // Target arrow head\n  float targetRadius = a_targetRadius * a_targetRadiusCoef;\n  float targetDirection = sign(targetRadius);\n  float webGLTargetRadius = targetDirection * targetRadius * 2.0 * u_correctionRatio / u_sizeRatio;\n  float webGLTargetArrowHeadLength = webGLThickness * u_lengthToThicknessRatio * 2.0;\n  vec2 targetCompensationVector =\n  vec2(-targetDirection * unitNormal.y, targetDirection * unitNormal.x)\n    * (webGLTargetRadius + webGLTargetArrowHeadLength);\n\n  // Here is the proper position of the vertex\n  gl_Position = vec4((u_matrix * vec3(position + unitNormal * webGLThickness + sourceCompensationVector + targetCompensationVector, 1)).xy, 0, 1);\n\n  v_thickness = webGLThickness / u_zoomRatio;\n\n  v_normal = unitNormal;\n\n  v_feather = u_feather * u_correctionRatio / u_zoomRatio / u_pixelRatio * 2.0;\n\n  #ifdef PICKING_MODE\n  // For picking mode, we use the ID as the color:\n  v_color = a_id;\n  #else\n  // For normal mode, we use the color:\n  v_color = a_color;\n  #endif\n\n  v_color.a *= bias;\n}\n";
var VERTEX_SHADER_SOURCE$2 = SHADER_SOURCE$4;

var _WebGLRenderingContex$2 = WebGLRenderingContext,
  UNSIGNED_BYTE$2 = _WebGLRenderingContex$2.UNSIGNED_BYTE,
  FLOAT$2 = _WebGLRenderingContex$2.FLOAT;
var UNIFORMS$2 = ["u_matrix", "u_zoomRatio", "u_sizeRatio", "u_correctionRatio", "u_pixelRatio", "u_feather", "u_minEdgeThickness", "u_lengthToThicknessRatio"];
var DEFAULT_EDGE_DOUBLE_CLAMPED_PROGRAM_OPTIONS = {
  lengthToThicknessRatio: index.DEFAULT_EDGE_ARROW_HEAD_PROGRAM_OPTIONS.lengthToThicknessRatio
};
function createEdgeDoubleClampedProgram(inputOptions) {
  var options = index._objectSpread2(index._objectSpread2({}, DEFAULT_EDGE_DOUBLE_CLAMPED_PROGRAM_OPTIONS), inputOptions || {});
  return /*#__PURE__*/function (_EdgeProgram) {
    function EdgeDoubleClampedProgram() {
      inherits._classCallCheck(this, EdgeDoubleClampedProgram);
      return inherits._callSuper(this, EdgeDoubleClampedProgram, arguments);
    }
    inherits._inherits(EdgeDoubleClampedProgram, _EdgeProgram);
    return inherits._createClass(EdgeDoubleClampedProgram, [{
      key: "getDefinition",
      value: function getDefinition() {
        return {
          VERTICES: 6,
          VERTEX_SHADER_SOURCE: VERTEX_SHADER_SOURCE$2,
          FRAGMENT_SHADER_SOURCE: index.FRAGMENT_SHADER_SOURCE,
          METHOD: WebGLRenderingContext.TRIANGLES,
          UNIFORMS: UNIFORMS$2,
          ATTRIBUTES: [{
            name: "a_positionStart",
            size: 2,
            type: FLOAT$2
          }, {
            name: "a_positionEnd",
            size: 2,
            type: FLOAT$2
          }, {
            name: "a_normal",
            size: 2,
            type: FLOAT$2
          }, {
            name: "a_color",
            size: 4,
            type: UNSIGNED_BYTE$2,
            normalized: true
          }, {
            name: "a_id",
            size: 4,
            type: UNSIGNED_BYTE$2,
            normalized: true
          }, {
            name: "a_sourceRadius",
            size: 1,
            type: FLOAT$2
          }, {
            name: "a_targetRadius",
            size: 1,
            type: FLOAT$2
          }],
          CONSTANT_ATTRIBUTES: [
          // If 0, then position will be a_positionStart
          // If 1, then position will be a_positionEnd
          {
            name: "a_positionCoef",
            size: 1,
            type: FLOAT$2
          }, {
            name: "a_normalCoef",
            size: 1,
            type: FLOAT$2
          }, {
            name: "a_sourceRadiusCoef",
            size: 1,
            type: FLOAT$2
          }, {
            name: "a_targetRadiusCoef",
            size: 1,
            type: FLOAT$2
          }],
          CONSTANT_DATA: [[0, 1, -1, 0], [0, -1, 1, 0], [1, 1, 0, 1], [1, 1, 0, 1], [0, -1, 1, 0], [1, -1, 0, -1]]
        };
      }
    }, {
      key: "processVisibleItem",
      value: function processVisibleItem(edgeIndex, startIndex, sourceData, targetData, data) {
        var thickness = data.size || 1;
        var x1 = sourceData.x;
        var y1 = sourceData.y;
        var x2 = targetData.x;
        var y2 = targetData.y;
        var color = colors.floatColor(data.color);

        // Computing normals
        var dx = x2 - x1;
        var dy = y2 - y1;
        var sourceRadius = sourceData.size || 1;
        var targetRadius = targetData.size || 1;
        var len = dx * dx + dy * dy;
        var n1 = 0;
        var n2 = 0;
        if (len) {
          len = 1 / Math.sqrt(len);
          n1 = -dy * len * thickness;
          n2 = dx * len * thickness;
        }
        var array = this.array;
        array[startIndex++] = x1;
        array[startIndex++] = y1;
        array[startIndex++] = x2;
        array[startIndex++] = y2;
        array[startIndex++] = n1;
        array[startIndex++] = n2;
        array[startIndex++] = color;
        array[startIndex++] = edgeIndex;
        array[startIndex++] = sourceRadius;
        array[startIndex++] = targetRadius;
      }
    }, {
      key: "setUniforms",
      value: function setUniforms(params, _ref) {
        var gl = _ref.gl,
          uniformLocations = _ref.uniformLocations;
        var u_matrix = uniformLocations.u_matrix,
          u_zoomRatio = uniformLocations.u_zoomRatio,
          u_feather = uniformLocations.u_feather,
          u_pixelRatio = uniformLocations.u_pixelRatio,
          u_correctionRatio = uniformLocations.u_correctionRatio,
          u_sizeRatio = uniformLocations.u_sizeRatio,
          u_minEdgeThickness = uniformLocations.u_minEdgeThickness,
          u_lengthToThicknessRatio = uniformLocations.u_lengthToThicknessRatio;
        gl.uniformMatrix3fv(u_matrix, false, params.matrix);
        gl.uniform1f(u_zoomRatio, params.zoomRatio);
        gl.uniform1f(u_sizeRatio, params.sizeRatio);
        gl.uniform1f(u_correctionRatio, params.correctionRatio);
        gl.uniform1f(u_pixelRatio, params.pixelRatio);
        gl.uniform1f(u_feather, params.antiAliasingFeather);
        gl.uniform1f(u_minEdgeThickness, params.minEdgeThickness);
        gl.uniform1f(u_lengthToThicknessRatio, options.lengthToThicknessRatio);
      }
    }]);
  }(index.EdgeProgram);
}
var EdgeDoubleClampedProgram = createEdgeDoubleClampedProgram();
var EdgeDoubleClampedProgram$1 = EdgeDoubleClampedProgram;

function createEdgeDoubleArrowProgram(inputOptions) {
  return index.createEdgeCompoundProgram([createEdgeDoubleClampedProgram(inputOptions), index.createEdgeArrowHeadProgram(inputOptions), index.createEdgeArrowHeadProgram(index._objectSpread2(index._objectSpread2({}, inputOptions), {}, {
    extremity: "source"
  }))]);
}
var EdgeDoubleArrowProgram = createEdgeDoubleArrowProgram();
var EdgeDoubleArrowProgram$1 = EdgeDoubleArrowProgram;

// language=GLSL
var SHADER_SOURCE$3 = /*glsl*/"\nprecision mediump float;\n\nvarying vec4 v_color;\n\nvoid main(void) {\n  gl_FragColor = v_color;\n}\n";
var FRAGMENT_SHADER_SOURCE$1 = SHADER_SOURCE$3;

// language=GLSL
var SHADER_SOURCE$2 = /*glsl*/"\nattribute vec4 a_id;\nattribute vec4 a_color;\nattribute vec2 a_position;\n\nuniform mat3 u_matrix;\n\nvarying vec4 v_color;\n\nconst float bias = 255.0 / 254.0;\n\nvoid main() {\n  // Scale from [[-1 1] [-1 1]] to the container:\n  gl_Position = vec4(\n    (u_matrix * vec3(a_position, 1)).xy,\n    0,\n    1\n  );\n\n  #ifdef PICKING_MODE\n  // For picking mode, we use the ID as the color:\n  v_color = a_id;\n  #else\n  // For normal mode, we use the color:\n  v_color = a_color;\n  #endif\n\n  v_color.a *= bias;\n}\n";
var VERTEX_SHADER_SOURCE$1 = SHADER_SOURCE$2;

var _WebGLRenderingContex$1 = WebGLRenderingContext,
  UNSIGNED_BYTE$1 = _WebGLRenderingContex$1.UNSIGNED_BYTE,
  FLOAT$1 = _WebGLRenderingContex$1.FLOAT;
var UNIFORMS$1 = ["u_matrix"];
var EdgeLineProgram = /*#__PURE__*/function (_EdgeProgram) {
  function EdgeLineProgram() {
    inherits._classCallCheck(this, EdgeLineProgram);
    return inherits._callSuper(this, EdgeLineProgram, arguments);
  }
  inherits._inherits(EdgeLineProgram, _EdgeProgram);
  return inherits._createClass(EdgeLineProgram, [{
    key: "getDefinition",
    value: function getDefinition() {
      return {
        VERTICES: 2,
        VERTEX_SHADER_SOURCE: VERTEX_SHADER_SOURCE$1,
        FRAGMENT_SHADER_SOURCE: FRAGMENT_SHADER_SOURCE$1,
        METHOD: WebGLRenderingContext.LINES,
        UNIFORMS: UNIFORMS$1,
        ATTRIBUTES: [{
          name: "a_position",
          size: 2,
          type: FLOAT$1
        }, {
          name: "a_color",
          size: 4,
          type: UNSIGNED_BYTE$1,
          normalized: true
        }, {
          name: "a_id",
          size: 4,
          type: UNSIGNED_BYTE$1,
          normalized: true
        }]
      };
    }
  }, {
    key: "processVisibleItem",
    value: function processVisibleItem(edgeIndex, startIndex, sourceData, targetData, data) {
      var array = this.array;
      var x1 = sourceData.x;
      var y1 = sourceData.y;
      var x2 = targetData.x;
      var y2 = targetData.y;
      var color = colors.floatColor(data.color);

      // First point
      array[startIndex++] = x1;
      array[startIndex++] = y1;
      array[startIndex++] = color;
      array[startIndex++] = edgeIndex;

      // Second point
      array[startIndex++] = x2;
      array[startIndex++] = y2;
      array[startIndex++] = color;
      array[startIndex++] = edgeIndex;
    }
  }, {
    key: "setUniforms",
    value: function setUniforms(params, _ref) {
      var gl = _ref.gl,
        uniformLocations = _ref.uniformLocations;
      var u_matrix = uniformLocations.u_matrix;
      gl.uniformMatrix3fv(u_matrix, false, params.matrix);
    }
  }]);
}(index.EdgeProgram);

// language=GLSL
var SHADER_SOURCE$1 = /*glsl*/"\nprecision mediump float;\n\nvarying vec4 v_color;\n\nvoid main(void) {\n  gl_FragColor = v_color;\n}\n";
var FRAGMENT_SHADER_SOURCE = SHADER_SOURCE$1;

// language=GLSL
var SHADER_SOURCE = /*glsl*/"\nattribute vec4 a_id;\nattribute vec4 a_color;\nattribute vec2 a_normal;\nattribute float a_normalCoef;\nattribute vec2 a_positionStart;\nattribute vec2 a_positionEnd;\nattribute float a_positionCoef;\n\nuniform mat3 u_matrix;\nuniform float u_sizeRatio;\nuniform float u_correctionRatio;\n\nvarying vec4 v_color;\n\nconst float minThickness = 1.7;\nconst float bias = 255.0 / 254.0;\n\nvoid main() {\n  vec2 normal = a_normal * a_normalCoef;\n  vec2 position = a_positionStart * (1.0 - a_positionCoef) + a_positionEnd * a_positionCoef;\n\n  // The only different here with edge.vert.glsl is that we need to handle null\n  // input normal vector. Apart from that, you can read edge.vert.glsl more info\n  // on how it works:\n  float normalLength = length(normal);\n  vec2 unitNormal = normal / normalLength;\n  if (normalLength <= 0.0) unitNormal = normal;\n  float pixelsThickness = max(normalLength, minThickness * u_sizeRatio);\n  float webGLThickness = pixelsThickness * u_correctionRatio / u_sizeRatio;\n\n  gl_Position = vec4((u_matrix * vec3(position + unitNormal * webGLThickness, 1)).xy, 0, 1);\n\n  #ifdef PICKING_MODE\n  // For picking mode, we use the ID as the color:\n  v_color = a_id;\n  #else\n  // For normal mode, we use the color:\n  v_color = a_color;\n  #endif\n\n  v_color.a *= bias;\n}\n";
var VERTEX_SHADER_SOURCE = SHADER_SOURCE;

var _WebGLRenderingContex = WebGLRenderingContext,
  UNSIGNED_BYTE = _WebGLRenderingContex.UNSIGNED_BYTE,
  FLOAT = _WebGLRenderingContex.FLOAT;
var UNIFORMS = ["u_matrix", "u_sizeRatio", "u_correctionRatio", "u_minEdgeThickness"];
var EdgeTriangleProgram = /*#__PURE__*/function (_EdgeProgram) {
  function EdgeTriangleProgram() {
    inherits._classCallCheck(this, EdgeTriangleProgram);
    return inherits._callSuper(this, EdgeTriangleProgram, arguments);
  }
  inherits._inherits(EdgeTriangleProgram, _EdgeProgram);
  return inherits._createClass(EdgeTriangleProgram, [{
    key: "getDefinition",
    value: function getDefinition() {
      return {
        VERTICES: 3,
        VERTEX_SHADER_SOURCE: VERTEX_SHADER_SOURCE,
        FRAGMENT_SHADER_SOURCE: FRAGMENT_SHADER_SOURCE,
        METHOD: WebGLRenderingContext.TRIANGLES,
        UNIFORMS: UNIFORMS,
        ATTRIBUTES: [{
          name: "a_positionStart",
          size: 2,
          type: FLOAT
        }, {
          name: "a_positionEnd",
          size: 2,
          type: FLOAT
        }, {
          name: "a_normal",
          size: 2,
          type: FLOAT
        }, {
          name: "a_color",
          size: 4,
          type: UNSIGNED_BYTE,
          normalized: true
        }, {
          name: "a_id",
          size: 4,
          type: UNSIGNED_BYTE,
          normalized: true
        }],
        CONSTANT_ATTRIBUTES: [
        // If 0, then position will be a_positionStart
        // If 1, then position will be a_positionEnd
        {
          name: "a_positionCoef",
          size: 1,
          type: FLOAT
        }, {
          name: "a_normalCoef",
          size: 1,
          type: FLOAT
        }],
        CONSTANT_DATA: [[0, 1], [0, -1], [1, 0]]
      };
    }
  }, {
    key: "processVisibleItem",
    value: function processVisibleItem(edgeIndex, startIndex, sourceData, targetData, data) {
      var thickness = data.size || 1;
      var x1 = sourceData.x;
      var y1 = sourceData.y;
      var x2 = targetData.x;
      var y2 = targetData.y;
      var color = colors.floatColor(data.color);

      // Computing normals
      var dx = x2 - x1;
      var dy = y2 - y1;
      var len = dx * dx + dy * dy;
      var n1 = 0;
      var n2 = 0;
      if (len) {
        len = 1 / Math.sqrt(len);
        n1 = -dy * len * thickness;
        n2 = dx * len * thickness;
      }
      var array = this.array;

      // First point
      array[startIndex++] = x1;
      array[startIndex++] = y1;
      array[startIndex++] = x2;
      array[startIndex++] = y2;
      array[startIndex++] = n1;
      array[startIndex++] = n2;
      array[startIndex++] = color;
      array[startIndex++] = edgeIndex;
    }
  }, {
    key: "setUniforms",
    value: function setUniforms(params, _ref) {
      var gl = _ref.gl,
        uniformLocations = _ref.uniformLocations;
      var u_matrix = uniformLocations.u_matrix,
        u_sizeRatio = uniformLocations.u_sizeRatio,
        u_correctionRatio = uniformLocations.u_correctionRatio,
        u_minEdgeThickness = uniformLocations.u_minEdgeThickness;
      gl.uniformMatrix3fv(u_matrix, false, params.matrix);
      gl.uniform1f(u_sizeRatio, params.sizeRatio);
      gl.uniform1f(u_correctionRatio, params.correctionRatio);
      gl.uniform1f(u_minEdgeThickness, params.minEdgeThickness);
    }
  }]);
}(index.EdgeProgram);

exports.AbstractEdgeProgram = index.AbstractEdgeProgram;
exports.AbstractNodeProgram = index.AbstractNodeProgram;
exports.AbstractProgram = index.AbstractProgram;
exports.DEFAULT_EDGE_ARROW_HEAD_PROGRAM_OPTIONS = index.DEFAULT_EDGE_ARROW_HEAD_PROGRAM_OPTIONS;
exports.DEFAULT_EDGE_CLAMPED_PROGRAM_OPTIONS = index.DEFAULT_EDGE_CLAMPED_PROGRAM_OPTIONS;
exports.EdgeArrowHeadProgram = index.EdgeArrowHeadProgram;
exports.EdgeArrowProgram = index.EdgeArrowProgram;
exports.EdgeClampedProgram = index.EdgeClampedProgram;
exports.EdgeProgram = index.EdgeProgram;
exports.EdgeRectangleProgram = index.EdgeRectangleProgram;
exports.NodeCircleProgram = index.NodeCircleProgram;
exports.NodeProgram = index.NodeProgram;
exports.Program = index.Program;
exports.createEdgeArrowHeadProgram = index.createEdgeArrowHeadProgram;
exports.createEdgeArrowProgram = index.createEdgeArrowProgram;
exports.createEdgeClampedProgram = index.createEdgeClampedProgram;
exports.createEdgeCompoundProgram = index.createEdgeCompoundProgram;
exports.createNodeCompoundProgram = index.createNodeCompoundProgram;
exports.drawDiscNodeHover = index.drawDiscNodeHover;
exports.drawDiscNodeLabel = index.drawDiscNodeLabel;
exports.drawStraightEdgeLabel = index.drawStraightEdgeLabel;
exports.getAttributeItemsCount = index.getAttributeItemsCount;
exports.getAttributesItemsCount = index.getAttributesItemsCount;
exports.killProgram = index.killProgram;
exports.loadFragmentShader = index.loadFragmentShader;
exports.loadProgram = index.loadProgram;
exports.loadVertexShader = index.loadVertexShader;
exports.numberToGLSLFloat = index.numberToGLSLFloat;
exports.DEFAULT_EDGE_DOUBLE_CLAMPED_PROGRAM_OPTIONS = DEFAULT_EDGE_DOUBLE_CLAMPED_PROGRAM_OPTIONS;
exports.EdgeDoubleArrowProgram = EdgeDoubleArrowProgram$1;
exports.EdgeDoubleClampedProgram = EdgeDoubleClampedProgram$1;
exports.EdgeLineProgram = EdgeLineProgram;
exports.EdgeTriangleProgram = EdgeTriangleProgram;
exports.NodePointProgram = NodePointProgram;
exports.createEdgeDoubleArrowProgram = createEdgeDoubleArrowProgram;
exports.createEdgeDoubleClampedProgram = createEdgeDoubleClampedProgram;
