import type { PngDataArray } from '../types';
export interface DecodeInterlaceNullParams {
    data: Uint8Array;
    width: number;
    height: number;
    channels: number;
    depth: number;
}
export declare function decodeInterlaceNull(params: DecodeInterlaceNullParams): PngDataArray;
