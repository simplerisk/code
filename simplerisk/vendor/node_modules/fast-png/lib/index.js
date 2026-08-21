"use strict";
var __createBinding = (this && this.__createBinding) || (Object.create ? (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    var desc = Object.getOwnPropertyDescriptor(m, k);
    if (!desc || ("get" in desc ? !m.__esModule : desc.writable || desc.configurable)) {
      desc = { enumerable: true, get: function() { return m[k]; } };
    }
    Object.defineProperty(o, k2, desc);
}) : (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    o[k2] = m[k];
}));
var __exportStar = (this && this.__exportStar) || function(m, exports) {
    for (var p in m) if (p !== "default" && !Object.prototype.hasOwnProperty.call(exports, p)) __createBinding(exports, m, p);
};
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.convertIndexedToRgb = exports.hasPngSignature = void 0;
exports.decode = decodePng;
exports.encode = encodePng;
exports.decodeApng = decodeApng;
const PngDecoder_1 = __importDefault(require("./PngDecoder"));
const PngEncoder_1 = __importDefault(require("./PngEncoder"));
var signature_1 = require("./helpers/signature");
Object.defineProperty(exports, "hasPngSignature", { enumerable: true, get: function () { return signature_1.hasPngSignature; } });
__exportStar(require("./types"), exports);
function decodePng(data, options) {
    const decoder = new PngDecoder_1.default(data, options);
    return decoder.decode();
}
function encodePng(png, options) {
    const encoder = new PngEncoder_1.default(png, options);
    return encoder.encode();
}
function decodeApng(data, options) {
    const decoder = new PngDecoder_1.default(data, options);
    return decoder.decodeApng();
}
var convertIndexedToRgb_1 = require("./convertIndexedToRgb");
Object.defineProperty(exports, "convertIndexedToRgb", { enumerable: true, get: function () { return convertIndexedToRgb_1.convertIndexedToRgb; } });
//# sourceMappingURL=index.js.map