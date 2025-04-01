import { Attributes } from "graphology-types";
import Sigma from "../sigma.js";
import { EdgeDisplayData, NodeDisplayData, RenderParams } from "../types.js";
import { EdgeLabelDrawingFunction } from "./edge-labels.js";
import { AbstractProgram, Program } from "./program.js";
export declare abstract class AbstractEdgeProgram<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> extends AbstractProgram<N, E, G> {
    abstract drawLabel: EdgeLabelDrawingFunction<N, E, G> | undefined;
    abstract process(edgeIndex: number, offset: number, sourceData: NodeDisplayData, targetData: NodeDisplayData, data: EdgeDisplayData): void;
}
export declare abstract class EdgeProgram<Uniform extends string = string, N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> extends Program<Uniform, N, E, G> implements AbstractEdgeProgram<N, E, G> {
    drawLabel: EdgeLabelDrawingFunction<N, E, G> | undefined;
    kill(): void;
    process(edgeIndex: number, offset: number, sourceData: NodeDisplayData, targetData: NodeDisplayData, data: EdgeDisplayData): void;
    abstract processVisibleItem(edgeIndex: number, startIndex: number, sourceData: NodeDisplayData, targetData: NodeDisplayData, data: EdgeDisplayData): void;
}
declare class _EdgeProgramClass<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> implements AbstractEdgeProgram<N, E, G> {
    constructor(_gl: WebGLRenderingContext, _pickingBuffer: WebGLFramebuffer | null, _renderer: Sigma<N, E, G>);
    drawLabel: EdgeLabelDrawingFunction<N, E, G> | undefined;
    kill(): void;
    reallocate(_capacity: number): void;
    process(_edgeIndex: number, _offset: number, _sourceData: NodeDisplayData, _targetData: NodeDisplayData, _data: EdgeDisplayData): void;
    render(_params: RenderParams): void;
}
export type EdgeProgramType<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> = typeof _EdgeProgramClass<N, E, G>;
export declare function createEdgeCompoundProgram<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes>(programClasses: Array<EdgeProgramType<N, E, G>>, drawLabel?: EdgeLabelDrawingFunction<N, E, G>): EdgeProgramType<N, E, G>;
export {};
