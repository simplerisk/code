import PngDecoder from './PngDecoder';
import PngEncoder from './PngEncoder';
export { hasPngSignature } from './helpers/signature';
export * from './types';
function decodePng(data, options) {
    const decoder = new PngDecoder(data, options);
    return decoder.decode();
}
function encodePng(png, options) {
    const encoder = new PngEncoder(png, options);
    return encoder.encode();
}
function decodeApng(data, options) {
    const decoder = new PngDecoder(data, options);
    return decoder.decodeApng();
}
export { decodePng as decode, encodePng as encode, decodeApng };
export { convertIndexedToRgb } from './convertIndexedToRgb';
//# sourceMappingURL=index.js.map