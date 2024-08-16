/**
 * Sigma.js WebGL Renderer Node Program
 * =====================================
 *
 * Program rendering nodes using GL_POINTS, but that draws an image on top of
 * the classic colored disc.
 * @module
 */
import { NodeDisplayData } from "../../../types";
import { AbstractNodeProgram } from "./common/node";
import { RenderParams } from "./common/program";
import Sigma from "../../../sigma";
declare class AbstractNodeImageProgram extends AbstractNodeProgram {
    constructor(gl: WebGLRenderingContext, renderer: Sigma);
    bind(): void;
    process(data: NodeDisplayData & {
        image?: string;
    }, hidden: boolean, offset: number): void;
    render(params: RenderParams): void;
    rebindTexture(): void;
}
/**
 * To share the texture between the program instances of the graph and the
 * hovered nodes (to prevent some flickering, mostly), this program must be
 * "built" for each sigma instance:
 */
export default function getNodeImageProgram(): typeof AbstractNodeImageProgram;
export {};
