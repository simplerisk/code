import { Attributes } from "graphology-types";
import Sigma from "../sigma.js";
import { NodeDisplayData, NonEmptyArray, RenderParams } from "../types.js";
import { NodeHoverDrawingFunction } from "./node-hover.js";
import { NodeLabelDrawingFunction } from "./node-labels.js";
import { AbstractProgram, Program } from "./program.js";
export declare abstract class AbstractNodeProgram<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> extends AbstractProgram<N, E, G> {
    abstract drawLabel: NodeLabelDrawingFunction<N, E, G> | undefined;
    abstract drawHover: NodeHoverDrawingFunction<N, E, G> | undefined;
    abstract process(nodeIndex: number, offset: number, data: NodeDisplayData): void;
}
export declare abstract class NodeProgram<Uniform extends string = string, N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> extends Program<Uniform, N, E, G> implements AbstractNodeProgram<N, E, G> {
    drawLabel: NodeLabelDrawingFunction<N, E, G> | undefined;
    drawHover: NodeHoverDrawingFunction<N, E, G> | undefined;
    kill(): void;
    process(nodeIndex: number, offset: number, data: NodeDisplayData): void;
    abstract processVisibleItem(nodeIndex: number, i: number, data: NodeDisplayData): void;
}
declare class _NodeProgramClass<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> implements AbstractNodeProgram<N, E, G> {
    constructor(_gl: WebGLRenderingContext, _pickingBuffer: WebGLFramebuffer | null, _renderer: Sigma<N, E, G>);
    drawLabel: NodeLabelDrawingFunction<N, E, G> | undefined;
    drawHover: NodeHoverDrawingFunction<N, E, G> | undefined;
    kill(): void;
    reallocate(_capacity: number): void;
    process(_nodeIndex: number, _offset: number, _data: NodeDisplayData): void;
    render(_params: RenderParams): void;
}
export type NodeProgramType<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> = typeof _NodeProgramClass<N, E, G>;
export declare function createNodeCompoundProgram<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes>(programClasses: NonEmptyArray<NodeProgramType<N, E, G>>, drawLabel?: NodeLabelDrawingFunction<N, E, G>, drawHover?: NodeLabelDrawingFunction<N, E, G>): NodeProgramType<N, E, G>;
export {};
