/**
 * Sigma.js WebGL Abstract Node Program
 * =====================================
 *
 * @module
 */
import { AbstractProgram, IProgram, RenderParams } from "./program";
import { NodeDisplayData } from "../../../../types";
import Sigma from "../../../../sigma";
export interface INodeProgram extends IProgram {
    process(data: NodeDisplayData, hidden: boolean, offset: number): void;
    render(params: RenderParams): void;
}
/**
 * Node Program class.
 *
 * @constructor
 */
export declare abstract class AbstractNodeProgram extends AbstractProgram implements INodeProgram {
    positionLocation: GLint;
    sizeLocation: GLint;
    colorLocation: GLint;
    matrixLocation: WebGLUniformLocation;
    ratioLocation: WebGLUniformLocation;
    scaleLocation: WebGLUniformLocation;
    constructor(gl: WebGLRenderingContext, vertexShaderSource: string, fragmentShaderSource: string, points: number, attributes: number);
    bind(): void;
    abstract process(data: NodeDisplayData, hidden: boolean, offset: number): void;
}
export interface NodeProgramConstructor {
    new (gl: WebGLRenderingContext, renderer: Sigma): INodeProgram;
}
/**
 * Helper function combining two or more programs into a single compound one.
 * Note that this is more a quick & easy way to combine program than a really
 * performant option. More performant programs can be written entirely.
 *
 * @param  {array}    programClasses - Program classes to combine.
 * @return {function}
 */
export declare function createNodeCompoundProgram(programClasses: Array<NodeProgramConstructor>): NodeProgramConstructor;
