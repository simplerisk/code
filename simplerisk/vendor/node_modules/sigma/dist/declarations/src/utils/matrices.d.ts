import { Coordinates } from "../types.js";
export declare function identity(): Float32Array;
export declare function scale(m: Float32Array, x: number, y?: number): Float32Array;
export declare function rotate(m: Float32Array, r: number): Float32Array;
export declare function translate(m: Float32Array, x: number, y: number): Float32Array;
export declare function multiply<T extends number[] | Float32Array>(a: T, b: Float32Array | number[]): T;
export declare function multiplyVec2(a: Float32Array | number[], b: Coordinates, z?: number): Coordinates;
