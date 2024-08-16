/**
 * Sigma.js WebGL Renderer Arrow Program
 * ======================================
 *
 * Program rendering direction arrows as a simple triangle.
 * @module
 */
import { EdgeDisplayData, NodeDisplayData } from "../../../types";
import { AbstractEdgeProgram } from "./common/edge";
import { RenderParams } from "./common/program";
export default class EdgeArrowHeadProgram extends AbstractEdgeProgram {
    positionLocation: GLint;
    colorLocation: GLint;
    normalLocation: GLint;
    radiusLocation: GLint;
    barycentricLocation: GLint;
    matrixLocation: WebGLUniformLocation;
    sqrtZoomRatioLocation: WebGLUniformLocation;
    correctionRatioLocation: WebGLUniformLocation;
    constructor(gl: WebGLRenderingContext);
    bind(): void;
    computeIndices(): void;
    process(sourceData: NodeDisplayData, targetData: NodeDisplayData, data: EdgeDisplayData, hidden: boolean, offset: number): void;
    render(params: RenderParams): void;
}
