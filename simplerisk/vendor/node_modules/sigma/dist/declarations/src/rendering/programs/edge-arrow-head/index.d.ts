import { Attributes } from "graphology-types";
import { EdgeProgramType } from "../../edge.js";
export type CreateEdgeArrowHeadProgramOptions = {
    extremity: "source" | "target";
    lengthToThicknessRatio: number;
    widenessToThicknessRatio: number;
};
export declare const DEFAULT_EDGE_ARROW_HEAD_PROGRAM_OPTIONS: CreateEdgeArrowHeadProgramOptions;
export declare function createEdgeArrowHeadProgram<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes>(inputOptions?: Partial<CreateEdgeArrowHeadProgramOptions>): EdgeProgramType<N, E, G>;
declare const EdgeArrowHeadProgram: EdgeProgramType<Attributes, Attributes, Attributes>;
export default EdgeArrowHeadProgram;
