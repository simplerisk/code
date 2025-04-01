export declare function getAttributeItemsCount(attr: ProgramAttributeSpecification): number;
export declare function getAttributesItemsCount(attrs: ProgramAttributeSpecification[]): number;
export interface ProgramInfo<Uniform extends string = string> {
    name: string;
    isPicking: boolean;
    program: WebGLProgram;
    gl: WebGLRenderingContext | WebGL2RenderingContext;
    frameBuffer: WebGLFramebuffer | null;
    buffer: WebGLBuffer;
    constantBuffer: WebGLBuffer;
    uniformLocations: Record<Uniform, WebGLUniformLocation>;
    attributeLocations: Record<string, number>;
    vertexShader: WebGLShader;
    fragmentShader: WebGLShader;
}
export interface ProgramAttributeSpecification {
    name: string;
    size: number;
    type: number;
    normalized?: boolean;
}
export interface ProgramDefinition<Uniform extends string = string> {
    VERTICES: number;
    VERTEX_SHADER_SOURCE: string;
    FRAGMENT_SHADER_SOURCE: string;
    UNIFORMS: ReadonlyArray<Uniform>;
    ATTRIBUTES: Array<ProgramAttributeSpecification>;
    METHOD: number;
}
export interface InstancedProgramDefinition<Uniform extends string = string> extends ProgramDefinition<Uniform> {
    CONSTANT_ATTRIBUTES: Array<ProgramAttributeSpecification>;
    CONSTANT_DATA: number[][];
}
export declare function loadVertexShader(gl: WebGLRenderingContext, source: string): WebGLShader;
export declare function loadFragmentShader(gl: WebGLRenderingContext, source: string): WebGLShader;
export declare function loadProgram(gl: WebGLRenderingContext, shaders: Array<WebGLShader>): WebGLProgram;
export declare function killProgram({ gl, buffer, program, vertexShader, fragmentShader }: ProgramInfo): void;
export declare function numberToGLSLFloat(n: number): string;
