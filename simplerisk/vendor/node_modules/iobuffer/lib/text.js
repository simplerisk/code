"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.decode = decode;
exports.encode = encode;
function decode(bytes, encoding = 'utf8') {
    const decoder = new TextDecoder(encoding);
    return decoder.decode(bytes);
}
const encoder = new TextEncoder();
function encode(str) {
    return encoder.encode(str);
}
//# sourceMappingURL=text.js.map