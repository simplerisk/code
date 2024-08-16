/**
 * Sigma.js Easings
 * =================
 *
 * Handy collection of easing functions.
 * @module
 */
export declare const linear: (k: number) => number;
export declare const quadraticIn: (k: number) => number;
export declare const quadraticOut: (k: number) => number;
export declare const quadraticInOut: (k: number) => number;
export declare const cubicIn: (k: number) => number;
export declare const cubicOut: (k: number) => number;
export declare const cubicInOut: (k: number) => number;
declare const easings: {
    [key: string]: (k: number) => number;
};
export default easings;
