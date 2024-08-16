/**
 * Sigma.js WebGL Renderer Node Program
 * =====================================
 *
 * Simple program rendering nodes using GL_POINTS. This is faster than the
 * three triangle option but has some quirks and is not supported equally by
 * every GPU.
 * @module
 */
import { NodeDisplayData } from "../../../types";
import { AbstractNodeProgram } from "./common/node";
import { RenderParams } from "./common/program";
export default class NodeFastProgram extends AbstractNodeProgram {
    constructor(gl: WebGLRenderingContext);
    process(data: NodeDisplayData, hidden: boolean, offset: number): void;
    render(params: RenderParams): void;
}
