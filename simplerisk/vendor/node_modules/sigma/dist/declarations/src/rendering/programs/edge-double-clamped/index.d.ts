import { Attributes } from "graphology-types";
import { EdgeProgramType } from "../../edge.js";
import { CreateEdgeArrowHeadProgramOptions } from "../edge-arrow-head/index.js";
export type CreateEdgeDoubleClampedProgramOptions = Pick<CreateEdgeArrowHeadProgramOptions, "lengthToThicknessRatio">;
export declare const DEFAULT_EDGE_DOUBLE_CLAMPED_PROGRAM_OPTIONS: CreateEdgeDoubleClampedProgramOptions;
export declare function createEdgeDoubleClampedProgram<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes>(inputOptions?: Partial<CreateEdgeDoubleClampedProgramOptions>): EdgeProgramType<N, E, G>;
declare const EdgeDoubleClampedProgram: EdgeProgramType<Attributes, Attributes, Attributes>;
export default EdgeDoubleClampedProgram;
