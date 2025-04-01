import { Attributes } from "graphology-types";
import { EdgeProgramType } from "../../edge.js";
import { CreateEdgeArrowHeadProgramOptions } from "../edge-arrow-head/index.js";
export type CreateEdgeClampedProgramOptions = Pick<CreateEdgeArrowHeadProgramOptions, "lengthToThicknessRatio">;
export declare const DEFAULT_EDGE_CLAMPED_PROGRAM_OPTIONS: CreateEdgeClampedProgramOptions;
export declare function createEdgeClampedProgram<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes>(inputOptions?: Partial<CreateEdgeClampedProgramOptions>): EdgeProgramType<N, E, G>;
declare const EdgeClampedProgram: EdgeProgramType<Attributes, Attributes, Attributes>;
export default EdgeClampedProgram;
