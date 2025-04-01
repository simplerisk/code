export declare const HTML_COLORS: Record<string, string>;
export declare function extractPixel(gl: WebGLRenderingContext, x: number, y: number, array: Uint8Array): Uint8Array;
type RGBAColor = {
    r: number;
    g: number;
    b: number;
    a: number;
};
export declare function parseColor(val: string): RGBAColor;
export declare function rgbaToFloat(r: number, g: number, b: number, a: number, masking?: boolean): number;
export declare function floatColor(val: string): number;
export declare function colorToArray(val: string, masking?: boolean): [number, number, number, number];
export declare function indexToColor(index: number): number;
export declare function colorToIndex(r: number, g: number, b: number, _a: number): number;
export declare function getPixelColor(gl: WebGLRenderingContext, frameBuffer: WebGLBuffer | null, x: number, y: number, pixelRatio: number, downSizingRatio: number): [number, number, number, number];
export {};
