import { IOBuffer } from 'iobuffer';
import type { PngEncoderOptions, ImageData } from './types';
export default class PngEncoder extends IOBuffer {
    private readonly _png;
    private readonly _zlibOptions;
    private _colorType;
    private readonly _interlaceMethod;
    constructor(data: ImageData, options?: PngEncoderOptions);
    encode(): Uint8Array;
    private encodeIHDR;
    private encodeIEND;
    private encodePLTE;
    private encodeTRNS;
    private encodeIDAT;
    private encodeData;
    private _checkData;
}
