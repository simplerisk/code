"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.BlendOpType = exports.DisposeOpType = exports.InterlaceMethod = exports.FilterMethod = exports.CompressionMethod = exports.ColorType = void 0;
exports.ColorType = {
    UNKNOWN: -1,
    GREYSCALE: 0,
    TRUECOLOUR: 2,
    INDEXED_COLOUR: 3,
    GREYSCALE_ALPHA: 4,
    TRUECOLOUR_ALPHA: 6,
};
exports.CompressionMethod = {
    UNKNOWN: -1,
    DEFLATE: 0,
};
exports.FilterMethod = {
    UNKNOWN: -1,
    ADAPTIVE: 0,
};
exports.InterlaceMethod = {
    UNKNOWN: -1,
    NO_INTERLACE: 0,
    ADAM7: 1,
};
exports.DisposeOpType = {
    NONE: 0,
    BACKGROUND: 1,
    PREVIOUS: 2,
};
exports.BlendOpType = {
    SOURCE: 0,
    OVER: 1,
};
//# sourceMappingURL=internalTypes.js.map