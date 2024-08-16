export interface Boundaries {
    x: number;
    y: number;
    width: number;
    height: number;
}
export interface Rectangle {
    x1: number;
    y1: number;
    x2: number;
    y2: number;
    height: number;
}
export interface Vector {
    x: number;
    y: number;
}
/**
 * Geometry helpers.
 */
/**
 * Function returning whether the given rectangle is axis-aligned.
 *
 * @param  {Rectangle} rect
 * @return {boolean}
 */
export declare function isRectangleAligned(rect: Rectangle): boolean;
/**
 * Function returning the smallest rectangle that contains the given rectangle, and that is aligned with the axis.
 *
 * @param {Rectangle} rect
 * @return {Rectangle}
 */
export declare function getCircumscribedAlignedRectangle(rect: Rectangle): Rectangle;
/**
 *
 * @param x1
 * @param y1
 * @param w
 * @param qx
 * @param qy
 * @param qw
 * @param qh
 */
export declare function squareCollidesWithQuad(x1: number, y1: number, w: number, qx: number, qy: number, qw: number, qh: number): boolean;
export declare function rectangleCollidesWithQuad(x1: number, y1: number, w: number, h: number, qx: number, qy: number, qw: number, qh: number): boolean;
/**
 * QuadTree class.
 *
 * @constructor
 * @param {object} boundaries - The graph boundaries.
 */
export default class QuadTree {
    data: Float32Array;
    containers: Record<number, string[]>;
    cache: string[] | null;
    lastRectangle: Rectangle | null;
    constructor(params?: {
        boundaries?: Boundaries;
    });
    add(key: string, x: number, y: number, size: number): QuadTree;
    resize(boundaries: Boundaries): void;
    clear(): QuadTree;
    point(x: number, y: number): string[];
    rectangle(x1: number, y1: number, x2: number, y2: number, height: number): string[];
}
