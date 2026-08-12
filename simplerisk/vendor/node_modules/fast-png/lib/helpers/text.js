"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.textChunkName = void 0;
exports.decodetEXt = decodetEXt;
exports.encodetEXt = encodetEXt;
exports.readKeyword = readKeyword;
exports.readLatin1 = readLatin1;
const crc_1 = require("./crc");
// https://www.w3.org/TR/png/#11tEXt
exports.textChunkName = 'tEXt';
const NULL = 0;
const latin1Decoder = new TextDecoder('latin1');
function validateKeyword(keyword) {
    validateLatin1(keyword);
    if (keyword.length === 0 || keyword.length > 79) {
        throw new Error('keyword length must be between 1 and 79');
    }
}
// eslint-disable-next-line no-control-regex
const latin1Regex = /^[\u0000-\u00FF]*$/;
function validateLatin1(text) {
    if (!latin1Regex.test(text)) {
        throw new Error('invalid latin1 text');
    }
}
function decodetEXt(text, buffer, length) {
    const keyword = readKeyword(buffer);
    text[keyword] = readLatin1(buffer, length - keyword.length - 1);
}
function encodetEXt(buffer, keyword, text) {
    validateKeyword(keyword);
    validateLatin1(text);
    const length = keyword.length + 1 /* NULL */ + text.length;
    buffer.writeUint32(length);
    buffer.writeChars(exports.textChunkName);
    buffer.writeChars(keyword);
    buffer.writeByte(NULL);
    buffer.writeChars(text);
    (0, crc_1.writeCrc)(buffer, length + 4);
}
// https://www.w3.org/TR/png/#11keywords
function readKeyword(buffer) {
    buffer.mark();
    while (buffer.readByte() !== NULL) {
        /* advance */
    }
    const end = buffer.offset;
    buffer.reset();
    const keyword = latin1Decoder.decode(buffer.readBytes(end - buffer.offset - 1));
    // NULL
    buffer.skip(1);
    validateKeyword(keyword);
    return keyword;
}
function readLatin1(buffer, length) {
    return latin1Decoder.decode(buffer.readBytes(length));
}
//# sourceMappingURL=text.js.map