/**
 * Sigma.js WebGL Abstract Edge Program
 * =====================================
 *
 * @module
 */
import { AbstractProgram, IProgram, RenderParams } from "./program";
import { EdgeDisplayData, NodeDisplayData } from "../../../../types";
import Sigma from "../../../../sigma";
export interface IEdgeProgram extends IProgram {
    computeIndices(): void;
    process(sourceData: NodeDisplayData, targetData: NodeDisplayData, data: EdgeDisplayData, hidden: boolean, offset: number): void;
    render(params: RenderParams): void;
}
/**
 * Edge Program class.
 *
 * @constructor
 */
export declare abstract class AbstractEdgeProgram extends AbstractProgram implements IEdgeProgram {
    constructor(gl: WebGLRenderingContext, vertexShaderSource: string, fragmentShaderSource: string, points: number, attributes: number);
    abstract bind(): void;
    abstract computeIndices(): void;
    abstract process(sourceData: NodeDisplayData, targetData: NodeDisplayData, data: EdgeDisplayData, hidden: boolean, offset: number): void;
    abstract render(params: RenderParams): void;
}
export interface EdgeProgramConstructor {
    new (gl: WebGLRenderingContext, renderer: Sigma): IEdgeProgram;
}
export declare function createEdgeCompoundProgram(programClasses: Array<EdgeProgramConstructor>): EdgeProgramConstructor;
