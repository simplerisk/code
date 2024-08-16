/**
 * Sigma.js Shader Utils
 * ======================
 *
 * Code used to load sigma's shaders.
 * @module
 */
export declare function loadVertexShader(gl: WebGLRenderingContext, source: string): WebGLShader;
export declare function loadFragmentShader(gl: WebGLRenderingContext, source: string): WebGLShader;
/**
 * Function used to load a program.
 */
export declare function loadProgram(gl: WebGLRenderingContext, shaders: Array<WebGLShader>): WebGLProgram;
