import { EdgeDisplayData, NodeDisplayData } from "../../../types";
import { AbstractEdgeProgram } from "./common/edge";
import { RenderParams } from "./common/program";
export default class EdgeTriangleProgram extends AbstractEdgeProgram {
    positionLocation: GLint;
    colorLocation: GLint;
    normalLocation: GLint;
    matrixLocation: WebGLUniformLocation;
    sqrtZoomRatioLocation: WebGLUniformLocation;
    correctionRatioLocation: WebGLUniformLocation;
    constructor(gl: WebGLRenderingContext);
    bind(): void;
    process(sourceData: NodeDisplayData, targetData: NodeDisplayData, data: EdgeDisplayData, hidden: boolean, offset: number): void;
    computeIndices(): void;
    render(params: RenderParams): void;
}
