import type { DecodeInterlaceNullParams } from './decodeInterlaceNull';
/**
 * Decodes the Adam7 interlaced PNG data.
 *
 * @param params - DecodeInterlaceNullParams
 * @returns - array of pixel data.
 */
export declare function decodeInterlaceAdam7(params: DecodeInterlaceNullParams): Uint8Array<ArrayBuffer> | Uint16Array<ArrayBuffer>;
