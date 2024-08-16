"use strict";
var __extends = (this && this.__extends) || (function () {
    var extendStatics = function (d, b) {
        extendStatics = Object.setPrototypeOf ||
            ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
            function (d, b) { for (var p in b) if (Object.prototype.hasOwnProperty.call(b, p)) d[p] = b[p]; };
        return extendStatics(d, b);
    };
    return function (d, b) {
        if (typeof b !== "function" && b !== null)
            throw new TypeError("Class extends value " + String(b) + " is not a constructor or null");
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
var utils_1 = require("../../../utils");
var edge_fast_vert_glsl_1 = __importDefault(require("../shaders/edge.fast.vert.glsl.js"));
var edge_fast_frag_glsl_1 = __importDefault(require("../shaders/edge.fast.frag.glsl.js"));
var edge_1 = require("./common/edge");
var POINTS = 2, ATTRIBUTES = 3;
var EdgeFastProgram = /** @class */ (function (_super) {
    __extends(EdgeFastProgram, _super);
    function EdgeFastProgram(gl) {
        var _this = _super.call(this, gl, edge_fast_vert_glsl_1.default, edge_fast_frag_glsl_1.default, POINTS, ATTRIBUTES) || this;
        // Locations:
        _this.positionLocation = gl.getAttribLocation(_this.program, "a_position");
        _this.colorLocation = gl.getAttribLocation(_this.program, "a_color");
        // Uniform locations:
        var matrixLocation = gl.getUniformLocation(_this.program, "u_matrix");
        if (matrixLocation === null)
            throw new Error("EdgeFastProgram: error while getting matrixLocation");
        _this.matrixLocation = matrixLocation;
        _this.bind();
        return _this;
    }
    EdgeFastProgram.prototype.bind = function () {
        var gl = this.gl;
        // Bindings
        gl.enableVertexAttribArray(this.positionLocation);
        gl.enableVertexAttribArray(this.colorLocation);
        gl.vertexAttribPointer(this.positionLocation, 2, gl.FLOAT, false, this.attributes * Float32Array.BYTES_PER_ELEMENT, 0);
        gl.vertexAttribPointer(this.colorLocation, 4, gl.UNSIGNED_BYTE, true, this.attributes * Float32Array.BYTES_PER_ELEMENT, 8);
    };
    EdgeFastProgram.prototype.computeIndices = function () {
        //nothing to do
    };
    EdgeFastProgram.prototype.process = function (sourceData, targetData, data, hidden, offset) {
        var array = this.array;
        var i = 0;
        if (hidden) {
            for (var l = i + POINTS * ATTRIBUTES; i < l; i++)
                array[i] = 0;
            return;
        }
        var x1 = sourceData.x, y1 = sourceData.y, x2 = targetData.x, y2 = targetData.y, color = (0, utils_1.floatColor)(data.color);
        i = POINTS * ATTRIBUTES * offset;
        // First point
        array[i++] = x1;
        array[i++] = y1;
        array[i++] = color;
        // Second point
        array[i++] = x2;
        array[i++] = y2;
        array[i] = color;
    };
    EdgeFastProgram.prototype.render = function (params) {
        if (this.hasNothingToRender())
            return;
        var gl = this.gl;
        var program = this.program;
        gl.useProgram(program);
        gl.uniformMatrix3fv(this.matrixLocation, false, params.matrix);
        gl.drawArrays(gl.LINES, 0, this.array.length / ATTRIBUTES);
    };
    return EdgeFastProgram;
}(edge_1.AbstractEdgeProgram));
exports.default = EdgeFastProgram;
