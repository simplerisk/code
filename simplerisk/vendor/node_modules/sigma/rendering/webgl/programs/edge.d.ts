import { EdgeDisplayData, NodeDisplayData } from "../../../types";
import { AbstractEdgeProgram } from "./common/edge";
import { RenderParams } from "./common/program";
export default class EdgeProgram extends AbstractEdgeProgram {
    IndicesArray: Uint32ArrayConstructor | Uint16ArrayConstructor;
    indicesArray: Uint32Array | Uint16Array;
    indicesBuffer: WebGLBuffer;
    indicesType: GLenum;
    canUse32BitsIndices: boolean;
    positionLocation: GLint;
    colorLocation: GLint;
    normalLocation: GLint;
    matrixLocation: WebGLUniformLocation;
    sqrtZoomRatioLocation: WebGLUniformLocation;
    correctionRatioLocation: WebGLUniformLocation;
    constructor(gl: WebGLRenderingContext);
    bind(): void;
    computeIndices(): void;
    bufferData(): void;
    process(sourceData: NodeDisplayData, targetData: NodeDisplayData, data: EdgeDisplayData, hidden: boolean, offset: number): void;
    render(params: RenderParams): void;
}
