import { Attributes } from "graphology-types";
import { EdgeDisplayData, NodeDisplayData, RenderParams } from "../../../types.js";
import { EdgeProgram } from "../../edge.js";
import { ProgramInfo } from "../../utils.js";
declare const UNIFORMS: readonly ["u_matrix", "u_zoomRatio", "u_sizeRatio", "u_correctionRatio", "u_pixelRatio", "u_feather", "u_minEdgeThickness"];
export default class EdgeRectangleProgram<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> extends EdgeProgram<(typeof UNIFORMS)[number], N, E, G> {
    getDefinition(): {
        VERTICES: number;
        VERTEX_SHADER_SOURCE: string;
        FRAGMENT_SHADER_SOURCE: string;
        METHOD: 4;
        UNIFORMS: readonly ["u_matrix", "u_zoomRatio", "u_sizeRatio", "u_correctionRatio", "u_pixelRatio", "u_feather", "u_minEdgeThickness"];
        ATTRIBUTES: ({
            name: string;
            size: number;
            type: 5126;
            normalized?: undefined;
        } | {
            name: string;
            size: number;
            type: 5121;
            normalized: boolean;
        })[];
        CONSTANT_ATTRIBUTES: {
            name: string;
            size: number;
            type: 5126;
        }[];
        CONSTANT_DATA: number[][];
    };
    processVisibleItem(edgeIndex: number, startIndex: number, sourceData: NodeDisplayData, targetData: NodeDisplayData, data: EdgeDisplayData): void;
    setUniforms(params: RenderParams, { gl, uniformLocations }: ProgramInfo): void;
}
export {};
