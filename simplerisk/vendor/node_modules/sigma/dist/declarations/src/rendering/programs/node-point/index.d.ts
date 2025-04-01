import { Attributes } from "graphology-types";
import { NodeDisplayData, RenderParams } from "../../../types.js";
import { NodeProgram } from "../../node.js";
import { ProgramInfo } from "../../utils.js";
declare const UNIFORMS: readonly ["u_sizeRatio", "u_pixelRatio", "u_matrix"];
export default class NodePointProgram<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> extends NodeProgram<(typeof UNIFORMS)[number], N, E, G> {
    getDefinition(): {
        VERTICES: number;
        VERTEX_SHADER_SOURCE: string;
        FRAGMENT_SHADER_SOURCE: string;
        METHOD: 0;
        UNIFORMS: readonly ["u_sizeRatio", "u_pixelRatio", "u_matrix"];
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
    };
    processVisibleItem(nodeIndex: number, startIndex: number, data: NodeDisplayData): void;
    setUniforms({ sizeRatio, pixelRatio, matrix }: RenderParams, { gl, uniformLocations }: ProgramInfo): void;
}
export {};
