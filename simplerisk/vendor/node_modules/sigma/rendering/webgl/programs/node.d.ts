/**
 * Sigma.js WebGL Renderer Node Program
 * =====================================
 *
 * Simple program rendering nodes as discs, shaped by triangles using the
 * `gl.TRIANGLES` display mode. So, to draw one node, it will need to store
 * three times the center of the node, with the color, the size and an angle
 * indicating which "corner" of the triangle to draw.
 * It does not extend AbstractNodeProgram, which works very differently, and
 * really targets the gl.POINTS drawing methods.
 * @module
 */
import { NodeDisplayData } from "../../../types";
import { AbstractProgram, RenderParams } from "./common/program";
export default class NodeProgram extends AbstractProgram {
    positionLocation: GLint;
    sizeLocation: GLint;
    colorLocation: GLint;
    angleLocation: GLint;
    matrixLocation: WebGLUniformLocation;
    sqrtZoomRatioLocation: WebGLUniformLocation;
    correctionRatioLocation: WebGLUniformLocation;
    constructor(gl: WebGLRenderingContext);
    bind(): void;
    process(data: NodeDisplayData, hidden: boolean, offset: number): void;
    render(params: RenderParams): void;
}
