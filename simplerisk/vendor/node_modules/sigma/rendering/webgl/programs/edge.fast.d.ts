/**
 * Sigma.js WebGL Renderer Fast Edge Program
 * ==========================================
 *
 * Program rendering edges using GL_LINES which is presumably very fast but
 * won't render thickness correctly on some GPUs and has some quirks.
 * @module
 */
import { EdgeDisplayData, NodeDisplayData } from "../../../types";
import { AbstractEdgeProgram } from "./common/edge";
import { RenderParams } from "./common/program";
export default class EdgeFastProgram extends AbstractEdgeProgram {
    positionLocation: GLint;
    colorLocation: GLint;
    matrixLocation: WebGLUniformLocation;
    constructor(gl: WebGLRenderingContext);
    bind(): void;
    computeIndices(): void;
    process(sourceData: NodeDisplayData, targetData: NodeDisplayData, data: EdgeDisplayData, hidden: boolean, offset: number): void;
    render(params: RenderParams): void;
}
