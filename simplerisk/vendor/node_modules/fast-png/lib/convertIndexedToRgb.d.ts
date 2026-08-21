import type { DecodedPng } from './types';
/**
 * Converts indexed data into RGB/RGBA format
 * @param decodedImage - Image to decode data from.
 * @returns Uint8Array with RGB data.
 */
export declare function convertIndexedToRgb(decodedImage: DecodedPng): Uint8Array<ArrayBuffer>;
