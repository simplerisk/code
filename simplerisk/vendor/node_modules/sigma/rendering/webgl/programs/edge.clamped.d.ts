/**
 * Sigma.js WebGL Renderer Edge Program
 * =====================================
 *
 * Program rendering edges as thick lines but with a twist: the end of edge
 * does not sit in the middle of target node but instead stays by some margin.
 *
 * This is useful when combined with arrows to draw directed edges.
 * @module
 */
import { EdgeDisplayData, NodeDisplayData } from "../../../types";
import { AbstractEdgeProgram } from "./common/edge";
import { RenderParams } from "./common/program";
export default class EdgeClampedProgram extends AbstractEdgeProgram {
    IndicesArray: Uint32ArrayConstructor | Uint16ArrayConstructor;
    indicesArray: Uint32Array | Uint16Array;
    indicesBuffer: WebGLBuffer;
    indicesType: GLenum;
    positionLocation: GLint;
    colorLocation: GLint;
    normalLocation: GLint;
    radiusLocation: GLint;
    matrixLocation: WebGLUniformLocation;
    sqrtZoomRatioLocation: WebGLUniformLocation;
    correctionRatioLocation: WebGLUniformLocation;
    canUse32BitsIndices: boolean;
    constructor(gl: WebGLRenderingContext);
    bind(): void;
    process(sourceData: NodeDisplayData, targetData: NodeDisplayData, data: EdgeDisplayData, hidden: boolean, offset: number): void;
    computeIndices(): void;
    bufferData(): void;
    render(params: RenderParams): void;
}
