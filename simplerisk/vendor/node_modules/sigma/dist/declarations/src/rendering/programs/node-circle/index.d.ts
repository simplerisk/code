import { Attributes } from "graphology-types";
import { NodeDisplayData, RenderParams } from "../../../types.js";
import { NodeProgram } from "../../node.js";
import { ProgramInfo } from "../../utils.js";
declare const UNIFORMS: readonly ["u_sizeRatio", "u_correctionRatio", "u_matrix"];
export default class NodeCircleProgram<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> extends NodeProgram<(typeof UNIFORMS)[number], N, E, G> {
    static readonly ANGLE_1 = 0;
    static readonly ANGLE_2: number;
    static readonly ANGLE_3: number;
    getDefinition(): {
        VERTICES: number;
        VERTEX_SHADER_SOURCE: string;
        FRAGMENT_SHADER_SOURCE: string;
        METHOD: 4;
        UNIFORMS: readonly ["u_sizeRatio", "u_correctionRatio", "u_matrix"];
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
    processVisibleItem(nodeIndex: number, startIndex: number, data: NodeDisplayData): void;
    setUniforms(params: RenderParams, { gl, uniformLocations }: ProgramInfo): void;
}
export {};
