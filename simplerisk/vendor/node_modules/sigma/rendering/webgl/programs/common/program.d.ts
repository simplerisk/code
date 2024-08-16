export interface RenderParams {
    width: number;
    height: number;
    ratio: number;
    matrix: Float32Array;
    scalingRatio: number;
    correctionRatio: number;
}
export interface IProgram {
    bufferData(): void;
    allocate(capacity: number): void;
    bind(): void;
    render(params: RenderParams): void;
}
/**
 * Abstract Program class.
 *
 * @constructor
 */
export declare abstract class AbstractProgram implements IProgram {
    points: number;
    attributes: number;
    gl: WebGLRenderingContext;
    array: Float32Array;
    buffer: WebGLBuffer;
    vertexShaderSource: string;
    vertexShader: WebGLShader;
    fragmentShaderSource: string;
    fragmentShader: WebGLShader;
    program: WebGLProgram;
    constructor(gl: WebGLRenderingContext, vertexShaderSource: string, fragmentShaderSource: string, points: number, attributes: number);
    bufferData(): void;
    allocate(capacity: number): void;
    hasNothingToRender(): boolean;
    abstract bind(): void;
    abstract render(params: RenderParams): void;
}
