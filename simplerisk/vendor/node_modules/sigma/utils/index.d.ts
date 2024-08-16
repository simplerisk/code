/**
 * Sigma.js Utils
 * ===============
 *
 * Various helper functions & classes used throughout the library.
 * @module
 */
import Graph from "graphology-types";
import { CameraState, Coordinates, Dimensions, Extent, PlainObject } from "../types";
/**
 * Checks whether the given value is a plain object.
 *
 * @param  {mixed}   value - Target value.
 * @return {boolean}
 */
export declare function isPlainObject(value: any): boolean;
/**
 * Helper to use Object.assign with more than two objects.
 *
 * @param  {object} target       - First object.
 * @param  {object} [...objects] - Objects to merge.
 * @return {object}
 */
export declare function assign<T>(target: Partial<T> | undefined, ...objects: Array<Partial<T | undefined>>): T;
/**
 * Very simple recursive Object.assign-like function.
 *
 * @param  {object} target       - First object.
 * @param  {object} [...objects] - Objects to merge.
 * @return {object}
 */
export declare function assignDeep<T>(target: Partial<T> | undefined, ...objects: Array<Partial<T | undefined>>): T;
/**
 * Just some dirty trick to make requestAnimationFrame and cancelAnimationFrame "work" in Node.js, for unit tests:
 */
export declare const requestFrame: (callback: FrameRequestCallback) => number;
export declare const cancelFrame: (requestID: number) => void;
/**
 * Function used to create DOM elements easily.
 *
 * @param  {string} tag        - Tag name of the element to create.
 * @param  {object} style      - Styles map.
 * @param  {object} attributes - Attributes map.
 * @return {HTMLElement}
 */
export declare function createElement<T extends HTMLElement>(tag: string, style?: Partial<CSSStyleDeclaration>, attributes?: PlainObject<string>): T;
/**
 * Function returning the browser's pixel ratio.
 *
 * @return {number}
 */
export declare function getPixelRatio(): number;
/**
 * Function returning the graph's node extent in x & y.
 *
 * @param  {Graph}
 * @return {object}
 */
export declare function graphExtent(graph: Graph): {
    x: Extent;
    y: Extent;
};
/**
 * Factory returning a function normalizing the given node's position & size.
 *
 * @param  {object}   extent  - Extent of the graph.
 * @return {function}
 */
export interface NormalizationFunction {
    (data: Coordinates): Coordinates;
    ratio: number;
    inverse(data: Coordinates): Coordinates;
    applyTo(data: Coordinates): void;
}
export declare function createNormalizationFunction(extent: {
    x: Extent;
    y: Extent;
}): NormalizationFunction;
/**
 * Function ordering the given elements in reverse z-order so they drawn
 * the correct way.
 *
 * @param  {number}   extent   - [min, max] z values.
 * @param  {function} getter   - Z attribute getter function.
 * @param  {array}    elements - The array to sort.
 * @return {array} - The sorted array.
 */
export declare function zIndexOrdering<T>(extent: Extent, getter: (e: T) => number, elements: Array<T>): Array<T>;
declare type RGBAColor = {
    r: number;
    g: number;
    b: number;
    a: number;
};
export declare function parseColor(val: string): RGBAColor;
export declare function floatArrayColor(val: string): Float32Array;
export declare function floatColor(val: string): number;
/**
 * In sigma, the graph is normalized into a [0, 1], [0, 1] square, before being given to the various renderers. This
 * helps dealing with quadtree in particular.
 * But at some point, we need to rescale it so that it takes the best place in the screen, ie. we always want to see two
 * nodes "touching" opposite sides of the graph, with the camera being at its default state.
 *
 * This function determines this ratio.
 */
export declare function getCorrectionRatio(viewportDimensions: {
    width: number;
    height: number;
}, graphDimensions: {
    width: number;
    height: number;
}): number;
/**
 * Function returning a matrix from the current state of the camera.
 */
export declare function matrixFromCamera(state: CameraState, viewportDimensions: {
    width: number;
    height: number;
}, graphDimensions: {
    width: number;
    height: number;
}, padding: number, inverse?: boolean): Float32Array;
/**
 * All these transformations we apply on the matrix to get it rescale the graph
 * as we want make it very hard to get pixel-perfect distances in WebGL. This
 * function returns a factor that properly cancels the matrix effect on lengths.
 *
 * [jacomyal]
 * To be fully honest, I can't really explain happens here... I notice that the
 * following ratio works (ie. it correctly compensates the matrix impact on all
 * camera states I could try):
 * > `R = size(V) / size(M * V) / W`
 * as long as `M * V` is in the direction of W (ie. parallel to (Ox)). It works
 * as well with H and a vector that transforms into something parallel to (Oy).
 *
 * Also, note that we use `angle` and not `-angle` (that would seem logical,
 * since we want to anticipate the rotation), because of the fact that in WebGL,
 * the image is vertically swapped.
 */
export declare function getMatrixImpact(matrix: Float32Array, cameraState: CameraState, viewportDimensions: Dimensions): number;
/**
 * Function extracting the color at the given pixel.
 */
export declare function extractPixel(gl: WebGLRenderingContext, x: number, y: number, array: Uint8Array): Uint8Array;
/**
 * Function used to know whether given webgl context can use 32 bits indices.
 */
export declare function canUse32BitsIndices(gl: WebGLRenderingContext): boolean;
/**
 * Check if the graph variable is a valid graph, and if sigma can render it.
 */
export declare function validateGraph(graph: Graph): void;
export {};
