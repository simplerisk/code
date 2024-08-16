/**
 * This helper returns true is the pixel at (x,y) in the given WebGL context is
 * colored, and false else.
 */
export declare function isPixelColored(gl: WebGLRenderingContext, x: number, y: number): boolean;
/**
 * This helper checks whether or not a point (x, y) collides with an
 * edge, connecting a source (xS, yS) to a target (xT, yT) with a thickness in
 * pixels.
 */
export declare function doEdgeCollideWithPoint(x: number, y: number, xS: number, yS: number, xT: number, yT: number, thickness: number): boolean;
