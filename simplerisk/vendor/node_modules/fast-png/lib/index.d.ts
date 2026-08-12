import type { DecoderInputType, PngDecoderOptions, DecodedPng, DecodedApng, ImageData, PngEncoderOptions } from './types';
export { hasPngSignature } from './helpers/signature';
export * from './types';
declare function decodePng(data: DecoderInputType, options?: PngDecoderOptions): DecodedPng;
declare function encodePng(png: ImageData, options?: PngEncoderOptions): Uint8Array;
declare function decodeApng(data: DecoderInputType, options?: PngDecoderOptions): DecodedApng;
export { decodePng as decode, encodePng as encode, decodeApng };
export { convertIndexedToRgb } from './convertIndexedToRgb';
