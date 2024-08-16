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
var node_vert_glsl_1 = __importDefault(require("../shaders/node.vert.glsl.js"));
var node_frag_glsl_1 = __importDefault(require("../shaders/node.frag.glsl.js"));
var program_1 = require("./common/program");
var POINTS = 3;
var ATTRIBUTES = 5;
var ANGLE_1 = 0;
var ANGLE_2 = (2 * Math.PI) / 3;
var ANGLE_3 = (4 * Math.PI) / 3;
var NodeProgram = /** @class */ (function (_super) {
    __extends(NodeProgram, _super);
    function NodeProgram(gl) {
        var _this = _super.call(this, gl, node_vert_glsl_1.default, node_frag_glsl_1.default, POINTS, ATTRIBUTES) || this;
        // Locations
        _this.positionLocation = gl.getAttribLocation(_this.program, "a_position");
        _this.sizeLocation = gl.getAttribLocation(_this.program, "a_size");
        _this.colorLocation = gl.getAttribLocation(_this.program, "a_color");
        _this.angleLocation = gl.getAttribLocation(_this.program, "a_angle");
        // Uniform Location
        var matrixLocation = gl.getUniformLocation(_this.program, "u_matrix");
        if (matrixLocation === null)
            throw new Error("AbstractNodeProgram: error while getting matrixLocation");
        _this.matrixLocation = matrixLocation;
        var sqrtZoomRatioLocation = gl.getUniformLocation(_this.program, "u_sqrtZoomRatio");
        if (sqrtZoomRatioLocation === null)
            throw new Error("NodeProgram: error while getting sqrtZoomRatioLocation");
        _this.sqrtZoomRatioLocation = sqrtZoomRatioLocation;
        var correctionRatioLocation = gl.getUniformLocation(_this.program, "u_correctionRatio");
        if (correctionRatioLocation === null)
            throw new Error("NodeProgram: error while getting correctionRatioLocation");
        _this.correctionRatioLocation = correctionRatioLocation;
        _this.bind();
        return _this;
    }
    NodeProgram.prototype.bind = function () {
        var gl = this.gl;
        gl.enableVertexAttribArray(this.positionLocation);
        gl.enableVertexAttribArray(this.sizeLocation);
        gl.enableVertexAttribArray(this.colorLocation);
        gl.enableVertexAttribArray(this.angleLocation);
        gl.vertexAttribPointer(this.positionLocation, 2, gl.FLOAT, false, this.attributes * Float32Array.BYTES_PER_ELEMENT, 0);
        gl.vertexAttribPointer(this.sizeLocation, 1, gl.FLOAT, false, this.attributes * Float32Array.BYTES_PER_ELEMENT, 8);
        gl.vertexAttribPointer(this.colorLocation, 4, gl.UNSIGNED_BYTE, true, this.attributes * Float32Array.BYTES_PER_ELEMENT, 12);
        gl.vertexAttribPointer(this.angleLocation, 1, gl.FLOAT, false, this.attributes * Float32Array.BYTES_PER_ELEMENT, 16);
    };
    NodeProgram.prototype.process = function (data, hidden, offset) {
        var array = this.array;
        var i = offset * POINTS * ATTRIBUTES;
        if (hidden) {
            for (var l = i + POINTS * ATTRIBUTES; i < l; i++)
                array[i] = 0;
            return;
        }
        var color = (0, utils_1.floatColor)(data.color);
        array[i++] = data.x;
        array[i++] = data.y;
        array[i++] = data.size;
        array[i++] = color;
        array[i++] = ANGLE_1;
        array[i++] = data.x;
        array[i++] = data.y;
        array[i++] = data.size;
        array[i++] = color;
        array[i++] = ANGLE_2;
        array[i++] = data.x;
        array[i++] = data.y;
        array[i++] = data.size;
        array[i++] = color;
        array[i] = ANGLE_3;
    };
    NodeProgram.prototype.render = function (params) {
        if (this.hasNothingToRender())
            return;
        var gl = this.gl;
        var program = this.program;
        gl.useProgram(program);
        gl.uniformMatrix3fv(this.matrixLocation, false, params.matrix);
        gl.uniform1f(this.sqrtZoomRatioLocation, Math.sqrt(params.ratio));
        gl.uniform1f(this.correctionRatioLocation, params.correctionRatio);
        gl.drawArrays(gl.TRIANGLES, 0, this.array.length / ATTRIBUTES);
    };
    return NodeProgram;
}(program_1.AbstractProgram));
exports.default = NodeProgram;
