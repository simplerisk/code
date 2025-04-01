import { Attributes } from "graphology-types";
import type Sigma from "../sigma.js";
import type { RenderParams } from "../types.js";
import { InstancedProgramDefinition, ProgramAttributeSpecification, ProgramDefinition, ProgramInfo } from "./utils.js";
export declare abstract class AbstractProgram<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> {
    constructor(_gl: WebGLRenderingContext, _pickGl: WebGLRenderingContext, _renderer: Sigma<N, E, G>);
    abstract reallocate(capacity: number): void;
    abstract render(params: RenderParams): void;
    abstract kill(): void;
}
export declare abstract class Program<Uniform extends string = string, N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> implements AbstractProgram<N, E, G>, InstancedProgramDefinition {
    VERTICES: number;
    VERTEX_SHADER_SOURCE: string;
    FRAGMENT_SHADER_SOURCE: string;
    UNIFORMS: ReadonlyArray<Uniform>;
    ATTRIBUTES: Array<ProgramAttributeSpecification>;
    METHOD: number;
    CONSTANT_ATTRIBUTES: Array<ProgramAttributeSpecification>;
    CONSTANT_DATA: number[][];
    ATTRIBUTES_ITEMS_COUNT: number;
    STRIDE: number;
    renderer: Sigma<N, E, G>;
    array: Float32Array;
    constantArray: Float32Array;
    capacity: number;
    verticesCount: number;
    normalProgram: ProgramInfo;
    pickProgram: ProgramInfo | null;
    isInstanced: boolean;
    abstract getDefinition(): ProgramDefinition<Uniform> | InstancedProgramDefinition<Uniform>;
    constructor(gl: WebGLRenderingContext | WebGL2RenderingContext, pickingBuffer: WebGLFramebuffer | null, renderer: Sigma<N, E, G>);
    kill(): void;
    protected getProgramInfo(name: "normal" | "pick", gl: WebGLRenderingContext | WebGL2RenderingContext, vertexShaderSource: string, fragmentShaderSource: string, frameBuffer: WebGLFramebuffer | null): ProgramInfo;
    protected bindProgram(program: ProgramInfo): void;
    protected unbindProgram(program: ProgramInfo): void;
    protected bindAttribute(attr: ProgramAttributeSpecification, program: ProgramInfo, offset: number, setDivisor?: boolean): number;
    protected unbindAttribute(attr: ProgramAttributeSpecification, program: ProgramInfo, unsetDivisor?: boolean): void;
    reallocate(capacity: number): void;
    hasNothingToRender(): boolean;
    abstract setUniforms(params: RenderParams, programInfo: ProgramInfo): void;
    protected renderProgram(params: RenderParams, programInfo: ProgramInfo): void;
    render(params: RenderParams): void;
    drawWebGL(method: number, { gl, frameBuffer }: ProgramInfo): void;
}
declare class _ProgramClass<Uniform extends string = string, N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> extends Program<Uniform, N, E, G> {
    getDefinition(): ProgramDefinition<Uniform> | InstancedProgramDefinition<Uniform>;
    setUniforms(_params: RenderParams, _programInfo: ProgramInfo): undefined;
}
export type ProgramType<Uniform extends string = string, N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> = typeof _ProgramClass<Uniform, N, E, G>;
export {};
